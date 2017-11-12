<?php

class TSyncSage {
	var $sagedb;
	var $debug = false;
	
	var $TProductCategory = array();
	
	function __construct() {
		global $conf, $langs;
		
		$langs->load('syncsage@syncsage');
		
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
		
		return 0;
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
		
		return 0;
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
		$sql.= ', ' . $this->sagedb->Get_column_list('F_ARTCOMPTA', 'ac');
		$sql.= ' FROM F_ARTICLE a';
		$sql.= ' LEFT JOIN F_ARTENUMREF ae ON (ae.AR_Ref = a.AR_Ref)';
		$sql.= ' LEFT JOIN F_ARTGAMME ag1 ON (ag1.AG_No = ae.AG_No1)';
		$sql.= ' LEFT JOIN F_ARTGAMME ag2 ON (ag2.AG_No = ae.AG_No2)';
		$sql.= ' LEFT JOIN F_ARTCOMPTA ac ON (ac.AR_Ref = a.AR_Ref)';
		$sql.= ' WHERE 1 = 1';
		$sql.= ' AND ac.ACP_Type = 1';
		$sql.= ' AND ac.ACP_Champ = 1';
		
		return $sql;
	}
	
	/**
	 * Fonction générale d'import des sorties de stock
	 */
	function import_sorties_stock_from_sage($time) {
		$sql = $this->get_sql_import_sorties_stock_sage($time);
		$this->sagedb->Execute($sql);
		
		while($dataline = $this->sagedb->Get_line(PDO::FETCH_ASSOC)) {
			$data = $this->construct_array_data('sortie_stock', $dataline);
			$this->add_sortie_stock_in_dolibarr($data);
		}
	}
	
	/**
	 * Construction de la requête pour récupérer les quantités sorties du stock
	 */
	function get_sql_import_sorties_stock_sage($time) {
		
		$sql = 'SELECT l.AR_Ref, l.AG_No1, l.AG_No2, l.DL_QteBL, l.DL_DateBL';
		$sql.= ' FROM F_DOCLIGNE l';
		$sql.= " WHERE l.DL_DateBL = '".date('Ymd', $time)."'"; // En SQL Server la date doit être entourée par des quotes
		
		return $sql;
	}
	
	/**
	 * Fonction générale de gestion du besoin de stock
	 * Une commande client va être créée et contiendra tous les produits en commande client dans Sage
	 * Cela permet d'avoir le stock théorique calculé dans Dolibarr basé sur le besoin client venant de Sage
	 * Fonction rappelée tous les jour pour mise à jour de la commande
	 */
	function import_besoin_stock() {
		global $db, $user, $langs;
		
		$sql = $this->get_sql_import_besoin_stock();
		$this->sagedb->Execute($sql);
		
		// Nom du client que l'on utilise
		$name = 'CustomerModSyncSage';
		// Identifiant (ref_ext) de la commande client que l'on utilise
		$refext = 'syncsage_cmd_client_besoin_stock';
		
		// On supprime la commande
		$cmd = new Commande($db);
		$cmd->fetch('','',$refext);
		$cmd->delete($user);
		
		// On créé la commande
		$soc = new Societe($db);
		if($soc->fetch('',$name) <= 0) {
			echo '<br>ERR fetch client '.$name.' : '.$soc->error;
			return 0;
		}
		
		$cmd = new Commande($db);
		$cmd->ref_ext = $refext;
		$cmd->ref_client = $langs->trans('SyncSageCmdName');
		$cmd->socid = $soc->id;
		$cmd->date = dol_now();
		if($cmd->create($user) <= 0) {
			echo '<br>ERR creation commande : '.$cmd->error;
			return 0;
		}
		
		while($dataline = $this->sagedb->Get_line(PDO::FETCH_ASSOC)) {
			$data = $this->construct_array_data('besoin_stock', $dataline);
			$this->add_besoin_stock_in_dolibarr($cmd, $data);
		}
		
		// Validation de la commande pour déclencher le calcul du stock théorique
		$cmd->valid($user);
	}
	
	/**
	 * Construction de la requête pour récupérer les besoins de stock
	 */
	function get_sql_import_besoin_stock() {
		
		$sql = 'SELECT AR_Ref, AG_No1, AG_No2, SUM(GS_QteRes) as qte';
		$sql.= ' FROM F_GAMSTOCK';
		$sql.= ' GROUP BY AR_Ref, AG_No1, AG_No2';
		$sql.= ' HAVING SUM(GS_QteRes) > 0';
		
		return $sql;
	}
	
	/*
	 * Fonction générale d'initialisation du stock Dolibarr depuis le stock Sage
	 */
	function init_stock_from_sage($id_entrepot_sage, $id_entrepot_dolibarr) {
		global $db;
		
		// Mise à 0 du stock Dolibarr
		$sql = 'TRUNCATE '.MAIN_DB_PREFIX.'product_stock';
		$db->query($sql);
		$sql = 'TRUNCATE '.MAIN_DB_PREFIX.'stock_mouvement';
		$db->query($sql);
		$sql = 'UPDATE '.MAIN_DB_PREFIX.'product SET stock = 0, pmp = 0';
		$db->query($sql);
		
		$sql = $this->get_sql_init_stock_sage($id_entrepot_sage);
		$this->sagedb->Execute($sql);
		
		while($dataline = $this->sagedb->Get_line(PDO::FETCH_ASSOC)) {
			$data = $this->construct_array_data('init_stock', $dataline);
			$this->init_stock_in_dolibarr($data, $id_entrepot_dolibarr);
		}
	}
	
	/*
	 * Construction de la requête SQL pour récupérer les stocks dans Sage 
	 */
	function get_sql_init_stock_sage($id_entrepot) {
		global $conf;
		
		$sql = 'SELECT stock.AR_Ref, stock.AG_No1, stock.AG_No2, stock.GS_MontSto, stock.GS_QteSto ';
		$sql.= ' FROM F_GAMSTOCK stock';
		$sql.= ' WHERE stock.GS_QteSto > 0';
		$sql.= ' AND stock.DE_No = '.$id_entrepot.' ';
		
		return $sql;
	}
	
	/*
	 * Export des mouvements de stock Dolibarr dans un fichier pour import ensuite dans Sage
	 */
	function export_mouvements_stock_from_dolibarr($time) {
		global $db;
		
		$sql = $this->get_sql_mouvements_stock_dolibarr($time);
		$resql = $db->query($sql);
		
		// Ouverture fichier
		$filename = DOL_DATA_ROOT . '/syncsage/export/mvt_stock_'.date('Ymd').'csv';
		$handle = fopen($filename, 'w');
		
		while($dataline = $db->fetch_array($resql)) {
			$data = $this->construct_array_data('mouvements_stock', $dataline);
			// Écriture fichier
			fputcsv($handle, $data);
		}
		
		fclose($handle);
		
		return 0;
	}
	
	/*
	 * Construction de la requête SQL pour récupérer les mouvements de stocks dans Dolibarr
	 */
	function get_sql_mouvements_stock_dolibarr($time) {
		global $conf;
		
		$sql = 'SELECT p.rowid, DATE(m.datem) as datem, pext.ref_sage, pext.gam1_sage, pext.gam2_sage, SUM(m.value) as qty ';
		$sql.= 'FROM '.MAIN_DB_PREFIX.'stock_mouvement m ';
		$sql.= 'LEFT JOIN '.MAIN_DB_PREFIX.'product p ON (p.rowid = m.fk_product) ';
		$sql.= 'LEFT JOIN '.MAIN_DB_PREFIX.'product_extrafields pext ON (p.rowid = pext.fk_object) ';
		$sql.= 'WHERE DATE(m.datem) = \''. date('Y-m-d', $time) .'\' ';
		$sql.= 'GROUP BY p.rowid, m.datem, pext.ref_sage, pext.gam1_sage, pext.gam2_sage';
		
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
					,'price'			=> $dataline['a.AR_PrixVen']
					,'cost_price'		=> $dataline['ae.AE_PrixAch']
					,'accountancy_code_buy'		=> $dataline['ac.ACP_ComptaCPT_CompteG']
					,'category'			=> $dataline['a.FA_CodeFamille']
					,'array_options'	=> array(
						'options_ref_sage'			=> $dataline['a.AR_Ref']
						,'options_gam1_sage'		=> $dataline['ae.AG_No1']
						,'options_gam2_sage'		=> $dataline['ae.AG_No2']
						,'options_origine'			=> $dataline['a.ORIGINE']
					)
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
					,'date'			=> date('Y-m-d', strtotime($dataline['DL_DateBL']))
				);
				
				break;
			
			case 'besoin_stock':
				
				$data = array(
					'ref'			=> $this->build_product_ref($dataline, '', '')
					,'qty'			=> $dataline['qte']
				);
				
				break;
			
			case 'init_stock':
				
				$data = array(
					'ref'			=> $this->build_product_ref($dataline, '', '')
					,'qty'			=> $dataline['GS_QteSto']
					,'pmp'			=> round($dataline['GS_MontSto'] / $dataline['GS_QteSto'],2)
				);
				
				break;
			
			case 'mouvements_stock':
				
				$data = array(
					'type_doc'	=> 20
					,'ref_doc'	=> date('ymd')
					,'date'		=> date('dmy')
					,'tiers'	=> '2'
					,'ref'		=> $dataline['ref_sage']
					,'gam1'		=> $dataline['gam1_sage']
					,'gam2'		=> $dataline['gam2_sage']
					,'qty'		=> $dataline['qty']
					,'ent'		=> '2'
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
	
	/**
	 * Ajout d'un mouvement de sortie de stock dans Dolibarr
	 */
	function add_sortie_stock_in_dolibarr($data) {
		global $db, $user, $langs;
		
		if(empty($data['ref'])) return 0;
		
		$product = new Product($db);
		$resp = $product->fetch('', $data['ref']);
		
		$entrepot_polypap = new Entrepot($db);
		$rese = $entrepot_polypap->fetch('', 'POLYPAP');
		
		if($resp > 0 && $rese > 0) {
			
			$result = $product->correct_stock(
							$user,
							$entrepot_polypap->id,
							$data['qty'],
							1, // Suppression
							$langs->trans('SyncSageLabelMvtDel', $data['date']),
							0,
							'SyncFromSage'
						);
			
			if($result <= 0){
				echo '<br>ERR sortie stock '.$data['ref'].', entrepot '.$entrepot_polypap->libelle.', retour : '.$result.', erreur : '.$product->error;
				return 0;
			}
		} else {
			echo '<br>ERR fetch '.$data['ref'].' id = '.(int)$product->id.', entrepot '.$entrepot_polypap->libelle.' id = '.(int)$entrepot_polypap->id;
			return 0;
		}
		
		echo '<br>OK Sortie stock '.$data['ref'].', qty '.$data['qty'];
		return 0;
	}

	/**
	 * fonction d'ajout des besoins à une commande client spécifique
	 */
	function add_besoin_stock_in_dolibarr(&$cmd, $data) {
		global $db;
		
		// Ajout de la ligne
		$prod = new Product($db);
		if($prod->fetch('', $data['ref']) <= 0) {
			print '<br>ERR fetch produit : '.$data['ref'];
		}
		else {
			if($cmd->addline('', 0, $data['qty'], $prod->tva_tx, 0, 0, $prod->id) <= 0) {
				print '<br>ERR addline produit '.$data['ref'].' : '.$cmd->error;
			}
			else {
				print '<br>OK addline produit '.$data['ref'].', qty '.$data['qty'];
			}
		}
	}
	
	/**
	 * fonction d'initialisation du stock Dolibarr
	 */
	function init_stock_in_dolibarr($data, $id_entrepot) {
		global $db, $langs, $user;
		
		$product = new Product($db);
		$resp = $product->fetch('', $data['ref']);
		
		$entrepot_polypap = new Entrepot($db);
		$rese = $entrepot_polypap->fetch($id_entrepot);
		
		if($resp > 0 && $rese > 0) {
			
			$result = $product->correct_stock(
							$user,
							$entrepot_polypap->id,
							$data['qty'],
							0, // Ajout
							$langs->trans('SyncSageLabelMvtInit', $data['date']),
							$data['pmp'],
							'InitFromSage'
						);
			
			if($result <= 0){
				echo '<br>ERR init stock '.$data['ref'].', entrepot '.$entrepot_polypap->libelle.', retour : '.$result.', erreur : '.$product->error;
				return 0;
			}
		} else {
			echo '<br>ERR fetch '.$data['ref'].' id = '.(int)$product->id.', entrepot '.$entrepot_polypap->libelle.' id = '.(int)$entrepot_polypap->id;
			return 0;
		}
		
		echo '<br>OK Init stock '.$data['ref'].', qty '.$data['qty'];
	}
}
