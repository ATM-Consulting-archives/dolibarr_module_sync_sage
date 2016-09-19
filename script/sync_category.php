<?php

ini_set('display_errors',true);
define('INC_FROM_CRON_SCRIPT', true);

require('../config.php');
dol_include_once('/syncsage/class/syncsage.class.php');
dol_include_once('/categorie/class/categorie.class.php');

$sync = new TSyncSage();

$sync->sagedb->debug = true;
$sync->debug = true;

$sync->sync_category_from_sage();
