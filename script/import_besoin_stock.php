<?php

ini_set('display_errors',true);
set_time_limit(0);
define('INC_FROM_CRON_SCRIPT', true);

require('../config.php');
dol_include_once('/syncsage/class/syncsage.class.php');
dol_include_once('/commande/class/commande.class.php');
dol_include_once('/user/class/user.class.php');
dol_include_once('/societe/class/societe.class.php');
dol_include_once('/product/class/product.class.php');

$sync = new TSyncSage();

$sync->sagedb->debug = true;
$sync->debug = true;

$user = new User($db);
$user->fetch(1); // superadmin
$user->getrights();

// On importe les sorties de stock enregistrées dans Sage
$sync->import_besoin_stock();