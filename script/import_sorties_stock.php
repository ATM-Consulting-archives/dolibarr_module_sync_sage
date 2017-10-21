<?php

ini_set('display_errors',true);
set_time_limit(0);
define('INC_FROM_CRON_SCRIPT', true);

require('../config.php');
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