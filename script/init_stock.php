<?php

ini_set('display_errors',true);
define('INC_FROM_CRON_SCRIPT', true);

require('../config.php');
dol_include_once('/syncsage/class/syncsage.class.php');
dol_include_once('/product/class/product.class.php');

$sync = new TSyncSage();

$sync->sagedb->debug = true;
$sync->debug = true;

$id_entrepot_sage = GETPOST('id_from');
$id_entrepot_dolibarr = GETPOST('id_to');

if(!empty($id_entrepot_sage) && !empty($id_entrepot_dolibarr)) {
	$sync->init_stock_from_sage($id_entrepot_sage, $id_entrepot_dolibarr);
} else {
	echo 'id_from et id_to obligatoires';
}
