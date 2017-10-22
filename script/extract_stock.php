<?php

ini_set('display_errors',true);
set_time_limit(0);
define('INC_FROM_CRON_SCRIPT', true);

require('../config.php');

$date = GETPOST('date');
$time = empty($date) ? time() : strtotime($date);

// Récupération des mouvements de stock par produit du jour indiqué
$sql = 'SELECT p.rowid, DATE(m.datem) as datem, pext.ref_sage, pext.gam1_sage, pext.gam2_sage, SUM(m.value) as qty ';
$sql.= 'FROM '.MAIN_DB_PREFIX.'stock_mouvement m ';
$sql.= 'LEFT JOIN '.MAIN_DB_PREFIX.'product p ON (p.rowid = m.fk_product) ';
$sql.= 'LEFT JOIN '.MAIN_DB_PREFIX.'product_extrafields pext ON (p.rowid = pext.fk_object) ';
$sql.= 'WHERE DATE(m.datem) = \''. date('Y-m-d', $time) .'\' ';
$sql.= 'AND m.inventorycode != \'SyncFromSage\' ';
$sql.= 'GROUP BY p.rowid, m.datem, pext.ref_sage, pext.gam1_sage, pext.gam2_sage';
echo $sql;

// Génération d'un fichier contenant ces mouvements
$resql = $db->query($sql);

while($obj = $db->fetch_row($resql)) {
	echo '<hr>';
	var_dump($obj);
}
