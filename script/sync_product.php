<?php

ini_set('display_errors',true);
set_time_limit(0);
define('INC_FROM_CRON_SCRIPT', true);

require('../config.php');
dol_include_once('/syncsage/class/syncsage.class.php');
dol_include_once('/product/class/product.class.php');

$sync = new TSyncSage();

$sync->sagedb->debug = true;
$sync->debug = true;

$sync->sync_product_from_sage();
