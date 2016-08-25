<?php

ini_set('display_errors',true);
define('INC_FROM_CRON_SCRIPT', true);

require('../config.php');
dol_include_once('/syncsage/class/syncsage.class.php');
dol_include_once('/product/class/product.class.php');

$sync = new TSyncSage();
$sync->sagedb->debug = true;
$TProduct = $sync->get_product_from_sage();

foreach ($TProduct as $dataline) {
	//pre($dataline,true);
	$data = array(
		'ref'				=> $sync->build_product_ref($dataline)
		,'label'			=> $sync->build_product_label($dataline)
		,'barcode'			=> $dataline['AE_CodeBarre']
	);
	pre($data,true);
	$sync->create_product_in_dolibarr($data);
}
