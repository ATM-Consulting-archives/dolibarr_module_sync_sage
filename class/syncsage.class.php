<?php

class TSyncSage {
	var $sagedb;
	
	function __construct() {
		global $conf;
		
		$dsn = $conf->global->SYNCSAGE_DSN;
		$usr = $conf->global->SYNCSAGE_USR;
		$pwd = $conf->global->SYNCSAGE_PWD;
		
		$this->sagedb = new TPDOdb('odbc', $dsn, $usr, $pwd);
	}
	
	/***************************************************************************************
	 * Fonctions concernant les produits
	 * - Synchro Sage => Dolibarr avec création / maj des produits
	 ***************************************************************************************/
	
	/*
	 * Récupération de la liste des produit dans la base Sage 
	 */
	function get_product_from_sage() {
		$sql = 'SELECT a.*,ae.*,ag1.*,ag2.*';
		$sql.= ' FROM [POLYPAP_DOLIBARR].[dbo].[F_ARTICLE] a';
		$sql.= ' LEFT JOIN [POLYPAP_DOLIBARR].[dbo].[F_ARTENUMREF] ae ON (ae.AR_Ref = a.AR_Ref)';
		$sql.= ' LEFT JOIN [POLYPAP_DOLIBARR].[dbo].[F_ARTGAMME] ag1 ON (ag1.AG_No = ae.AG_No1)';
		$sql.= ' LEFT JOIN [POLYPAP_DOLIBARR].[dbo].[F_ARTGAMME] ag2 ON (ag2.AG_No = ae.AG_No2)';
		$sql.= ' LIMIT 10';
		
		return $this->sagedb->ExecuteAsArray($sql,array(),PDO::FETCH_ASSOC);
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
			echo '<br>ERR '.$p->error;
		}
	}
	
	/*
	 * Construction d'une référence unique pour Dolibarr dans le cas d'une utilisation de gamme dans Sage
	 */
	function build_product_ref($dataline) {
		$ref = $dataline['AR_Ref'];
		if(!empty($dataline['AG_No1'])) {
			$ref.= '_'.$dataline['AG_No1'];
		}
		if(!empty($dataline['AG_No2'])) {
			$ref.= '_'.$dataline['AG_No2'];
		}
		
		return $ref;
	}
	
	function build_product_label($dataline) {
		$label = $dataline['AR_Ref'];
		if(!empty($dataline['EG_Enumere'])) {
			$label.= ' - '.$dataline['EG_Enumere'];
		}
		if(!empty($dataline['EG_Enumere'])) {
			$label.= ' - '.$dataline['EG_Enumere'];
		}
		
		return $label;
	}
}
