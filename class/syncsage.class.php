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
	function build_product_ref($dataline) {
		$ref = $dataline['a.AR_Ref'];
		if(!empty($dataline['ae.AG_No1'])) {
			$ref.= '_'.$dataline['ae.AG_No1'];
		}
		if(!empty($dataline['ae.AG_No2'])) {
			$ref.= '_'.$dataline['ae.AG_No2'];
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
}
