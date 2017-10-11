<?php

class TSyncSage {
	var $sagedb;
	var $debug = false;
	
	var $TProductCategory = array();
	
	function __construct() {
		global $conf;
		
		$dsn = $conf->global->SYNCSAGE_DSN;
		$usr = $conf->global->SYNCSAGE_USR;
		$pwd = $conf->global->SYNCSAGE_PWD;
		
		$this->sagedb = new TPDOdb('sqlsrv', $dsn, $usr, $pwd);
	}
	
	/***************************************************************************************
	 * Fonctions concernant les produits
	 * - Synchro Sage => Dolibarr avec création / maj des produits
	 * - Synchro Sage => Dolibarr avec création des famille de produit
	 ***************************************************************************************/
	/*
	 * Fonction générale de synchro catégorie
	 */
	function sync_category_from_sage() {
		$sql = $this->get_sql_category_sage();
		$this->sagedb->Execute($sql);
		
		while($dataline = $this->sagedb->Get_line(PDO::FETCH_ASSOC)) {
			$data = $this->construct_array_data('category', $dataline);
			$this->create_category_in_dolibarr($data);
		}
	}
	
	/*
	 * Construction de la requête SQL pour récupérer les produits dans Sage 
	 */
	function get_sql_category_sage() {
		global $conf;
		
		$sql = 'SELECT ';
		$sql.= $this->sagedb->Get_column_list('F_FAMILLE', 'f');
		$sql.= ' FROM F_FAMILLE f';
		$sql.= ' WHERE 1 = 1';
		
		return $sql;
	}
	
	/*
	 * Fonction générale de synchro produit
	 */
	function sync_product_from_sage() {
		$sql = $this->get_sql_product_sage();
		$this->sagedb->Execute($sql);
		
		while($dataline = $this->sagedb->Get_line(PDO::FETCH_ASSOC)) {
			$data = $this->construct_array_data('product', $dataline);
			$this->create_product_in_dolibarr($data);
		}
	}
	
	/*
	 * Construction de la requête SQL pour récupérer les produits dans Sage 
	 */
	function get_sql_product_sage() {
		global $conf;
		
		$sql = 'SELECT ';
		$sql.= $this->sagedb->Get_column_list('F_ARTICLE', 'a');
		$sql.= ', ' . $this->sagedb->Get_column_list('F_ARTENUMREF', 'ae');
		$sql.= ', ' . $this->sagedb->Get_column_list('F_ARTGAMME', 'ag1');
		$sql.= ', ' . $this->sagedb->Get_column_list('F_ARTGAMME', 'ag2');
		$sql.= ' FROM F_ARTICLE a';
		$sql.= ' LEFT JOIN F_ARTENUMREF ae ON (ae.AR_Ref = a.AR_Ref)';
		$sql.= ' LEFT JOIN F_ARTGAMME ag1 ON (ag1.AG_No = ae.AG_No1)';
		$sql.= ' LEFT JOIN F_ARTGAMME ag2 ON (ag2.AG_No = ae.AG_No2)';
		$sql.= ' WHERE 1 = 1';
		
		return $sql;
	}
	
	function import_sorties_stock() {
		$sql = $this->get_sql_import_sorties_stock();
		$this->sagedb->Execute($sql);
		
		while($dataline = $this->sagedb->Get_line(PDO::FETCH_ASSOC)) {
			$data = $this->construct_array_data('sortie_stock', $dataline);
			$this->add_sortie_stock_in_dolibarr($data);
		}
		
	}
	
	function get_sql_import_sorties_stock() {
		
		$sql = 'SELECT l.AR_Ref, l.AG_No1, l.AG_No2, l.DL_QteBL';
		$sql.= ' FROM F_DOCLIGNE l';
		$sql.= " WHERE DO_Date = '".date('Ymd')."'"; // En SQL Server la date doit être entourée par des quotes
		$sql.= ' AND Do_Type = 21'; // 21 = Lignes de mouvements de sorties de stock
		
		return $sql;
		
	}
	
	/*
	 * Construction du tableau contenant les données
	 */
	function construct_array_data($type, $dataline) {
		switch ($type) {
			case 'product':
				
				$data = array(
					'ref'				=> $this->build_product_ref($dataline)
					,'label'			=> $this->build_product_label($dataline)
					,'barcode'			=> $dataline['ae.AE_CodeBarre']
					,'type'				=> 0
					,'status'			=> ($dataline['a.AR_Sommeil'] || $dataline['ae.AE_Sommeil']) ? 0 : 1
					,'status_buy'		=> ($dataline['a.AR_Sommeil'] || $dataline['ae.AE_Sommeil']) ? 0 : 1
					,'cost_price'		=> $dataline['ae.AE_PrixAch']
					,'category'			=> $dataline['a.FA_CodeFamille']
				);
				
				break;
			
			case 'category':
				
				$data = array(
					'label'			=> $dataline['f.FA_CodeFamille']
					,'description'	=> $dataline['f.FA_Intitule']
					,'type'			=> 0
				);
				
				break;
			
			case 'sortie_stock':
				
				$data = array(
					'ref'			=> $this->build_product_ref($dataline, '', '')
					,'qty'			=> $dataline['DL_QteBL']
				);
				
				break;
				
			default:
				$data = array();
				break;
		}
		
		
		return $data;
	}
	
	/*
	 * Création / MAJ d'un produit dans Dolibarr
	 */
	function create_product_in_dolibarr($data) {
		global $db,$user;
		
		$p = new Product($db);
		$p->fetch(0,$data['ref']);
		
		foreach($data as $k => $v) {
			$p->{$k} = $v;
		}
		
		if($p->id > 0) {
			$res = $p->update($p->id, $user);
		} else {
			$res = $p->create($user);
		}
		
		if($res < 0) {
			echo '<br>ERR '.$p->ref.' : '.$p->error;
			return $res;
		} else {
			$p->setValueFrom('cost_price', price2num($data['cost_price']));
		}
		
		if($this->debug) {
			echo '<br>OK '.$p->ref.' - '.$p->label;
		}
		
		// Ajout de la catégorie de produit
		if(!empty($this->TProductCategory[$data['category']])) {
			$this->TProductCategory[$data['category']]->add_type($p, 'product');
		}
		
		return $res;
	}
	
	/*
	 * Construction d'une référence unique pour Dolibarr dans le cas d'une utilisation de gamme dans Sage
	 */
	function build_product_ref($dataline, $table1='a.', $table2='ae.') {
		$ref = $dataline[$table1.'AR_Ref'];
		if(!empty($dataline[$table2.'AG_No1'])) {
			$ref.= '_'.$dataline[$table2.'AG_No1'];
		}
		if(!empty($dataline[$table2.'AG_No2'])) {
			$ref.= '_'.$dataline[$table2.'AG_No2'];
		}
		
		return $ref;
	}
	
	function build_product_label($dataline) {
		$label = $dataline['a.AR_Design'];
		if(!empty($dataline['ag1.EG_Enumere'])) {
			$label.= ' - '.$dataline['ag1.EG_Enumere'];
		}
		if(!empty($dataline['ag2.EG_Enumere'])) {
			$label.= ' - '.$dataline['ag2.EG_Enumere'];
		}
		
		return utf8_encode($label);
	}
	
	/*
	 * Création d'une catégorie dans Dolibarr
	 */
	function create_category_in_dolibarr($data) {
		global $db,$user;
		
		$cat = new Categorie($db);
		$cat->fetch(0,$data['label']);
		
		foreach($data as $k => $v) {
			$cat->{$k} = $v;
		}
		
		if($cat->id > 0) {
			$res = $cat->update($user);
		} else {
			$res = $cat->create($user);
		}
		
		if($res < 0) {
			echo '<br>ERR '.$cat->label.' : '.$cat->error;
		} else if($this->debug) {
			echo '<br>OK '.$cat->label;
		}
		
		$this->TProductCategory[$cat->label] = &$cat;
		
		return $res;
	}
	
	function add_sortie_stock_in_dolibarr(&$data) {
		
		global $db, $user, $langs;
		
		$langs->load('syncsage@syncsage');
		
		$product = new Product($db);
		$entrepot_polypap = new Entrepot($db);
		if($product->fetch('', $data['ref']) > 0 && $entrepot_polypap->fetch('', 'POLYPAP')) {
			
			$result = $product->correct_stock(
							$user,
							$entrepot_polypap->id,
							$data['qty'],
							1, // Suppression
							$langs->trans('SyncSageLabelMvtDel', date('d/m/Y')),
							0, // TODO quel Tarif ?
							''
						);
			if($result <= 0){
				echo 'Erreur sortie stock produit '.$data['ref'].', entrepot '.$entrepot_polypap->libelle.', retour : '.$result.', erreur : '.$product->error.'<br />';
				return 0;
			}
		} else {
			echo 'Erreur fetch produit ou entrepot, prod '.$data['ref'].' id = '.(int)$product->id.', entrepot '.$entrepot_polypap->libelle.' id = '.(int)$entrepot_polypap->id.'<br />';
			return 0;
		}
		
		print 'Sortie de stock Sage produit '.$data['ref'].', entrepot id = '.(int)$entrepot_polypap->id.'<br />';
		return 1;
	}
	
}
