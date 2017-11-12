<?php
if(!class_exists('TObjetStd')) {
	define('INC_FROM_DOLIBARR', true);
	require __DIR__.'/../config.php';
}

class CronSage {

	function __construct(&$db) {
		
		$this->db = &$db;

	}

	function syncCategory() {
		global $conf, $user, $langs;

		dol_include_once('/syncsage/class/syncsage.class.php');
		dol_include_once('/categories/class/categorie.class.php');
		
		$sync = new TSyncSage();
		
		$sync->sagedb->debug = true;
		$sync->debug = true;
		
		$sync->sync_category_from_sage();
	}
	
	function syncProduct() {
		dol_include_once('/syncsage/class/syncsage.class.php');
		dol_include_once('/product/class/product.class.php');
		dol_include_once('/categories/class/categorie.class.php');
		
		$sync = new TSyncSage();
		
		$sync->sagedb->debug = true;
		$sync->debug = true;
		
		// On synchronise d'abord les catégorie de produit
		$sync->sync_category_from_sage();
		$sync->sync_product_from_sage();
		
		return 1;
	}
	
	function importSortiesStock() {
		dol_include_once('/syncsage/class/syncsage.class.php');
		dol_include_once('/product/stock/class/entrepot.class.php');
		dol_include_once('/product/class/product.class.php');
		
		$sync = new TSyncSage();
		
		$sync->sagedb->debug = true;
		$sync->debug = true;
		
		$date = GETPOST('date');
		$time = empty($date) ? time() : strtotime($date);
		
		// On importe les sorties de stock enregistrées dans Sage
		$sync->import_sorties_stock_from_sage($time);
	}
	
	function importBesoinsStock() {
		dol_include_once('/syncsage/class/syncsage.class.php');
		dol_include_once('/commande/class/commande.class.php');
		dol_include_once('/user/class/user.class.php');
		dol_include_once('/societe/class/societe.class.php');
		dol_include_once('/product/class/product.class.php');
		
		$sync = new TSyncSage();
		
		$sync->sagedb->debug = true;
		$sync->debug = true;
		
		// On importe les sorties de stock enregistrées dans Sage
		$sync->import_besoin_stock();
	}
	
	function exportMouvementsStock() {
		dol_include_once('/syncsage/class/syncsage.class.php');
		
		$sync = new TSyncSage();
		
		$sync->sagedb->debug = true;
		$sync->debug = true;
		
		$date = GETPOST('date');
		$time = empty($date) ? time() : strtotime($date);
		
		// On exporte les mouvements de stock enregistrés dans Dolibarr
		return $sync->export_mouvements_stock_from_dolibarr($time);
	}
}