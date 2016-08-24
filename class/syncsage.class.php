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
	 ***************************************************************************************/
	
	/*
	 * Récupération de la liste des produit dans la base Sage 
	 */
	function get_product_from_sage() {
		$sql = '';
		
		$this->sagedb->Execute($sql);
		
		return $this->sagedb->Get_All();
	}
}
