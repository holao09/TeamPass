<?php
/**
 * @package       kb.queries.table.php
 * @author        Nils Laumaillé <nils@teampass.net>
 * @version       2.1.27
 * @copyright     2009-2018 Nils Laumaillé
 * @license       GNU GPL-3.0
 * @link          https://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

require '../SecureHandler.php';
session_start();
if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1) {
    die('Hacking attempt...');
}

// Load config
if (file_exists('../../includes/config/tp.config.php')) {
    include_once '../../includes/config/tp.config.php';
} else {
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
}

global $k, $settings;
require_once $SETTINGS['cpassman_dir'].'/includes/config/settings.php';
require_once $SETTINGS['cpassman_dir'].'/includes/config/include.php';
require_once $SETTINGS['cpassman_dir'].'/sources/SplClassLoader.php';
require_once $SETTINGS['cpassman_dir'].'/sources/main.functions.php';
header("Content-type: text/html; charset=utf-8");

//Connect to DB
require_once $SETTINGS['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
$pass = defuse_return_decrypted($pass);
DB::$host = $server;
DB::$user = $user;
DB::$password = $pass;
DB::$dbName = $database;
DB::$port = $port;
DB::$encoding = $encoding;
DB::$error_handler = true;
$link = mysqli_connect($server, $user, $pass, $database, $port);
$link->set_charset($encoding);

//Columns name
$aColumns = array('id', 'category_id', 'label', 'description', 'author_id');
$aSortTypes = array('ASC', 'DESC');

//init SQL variables
$sWhere = $sOrder = $sLimit = "";

/* BUILD QUERY */
//Paging
$sLimit = "";
if (isset($_GET['iDisplayStart']) && $_GET['iDisplayLength'] != '-1') {
    $sLimit = "LIMIT ".filter_var($_GET['iDisplayStart'], FILTER_SANITIZE_NUMBER_INT).", ".filter_var($_GET['iDisplayLength'], FILTER_SANITIZE_NUMBER_INT)."";
}

//Ordering
if (isset($_GET['iSortCol_0']) && in_array($_GET['iSortCol_0'], $aSortTypes)) {
    $sOrder = "ORDER BY  ";
    for ($i = 0; $i < intval($_GET['iSortingCols']); $i++) {
        if ($_GET['bSortable_'.filter_var($_GET['iSortCol_'.$i], FILTER_SANITIZE_NUMBER_INT)] == "true" &&
            preg_match("#^(asc|desc)\$#i", $_GET['sSortDir_'.$i])
        ) {
            $sOrder .= "".$aColumns[filter_var($_GET['iSortCol_'.$i], FILTER_SANITIZE_NUMBER_INT)]." "
            .mysqli_escape_string($link, $_GET['sSortDir_'.$i]).", ";
        }
    }

    $sOrder = substr_replace($sOrder, "", -2);
    if ($sOrder == "ORDER BY") {
        $sOrder = "";
    }
}

/*
   * Filtering
   * NOTE this does not match the built-in DataTables filtering which does it
   * word by word on any field. It's possible to do here, but concerned about efficiency
   * on very large tables, and MySQL's regex functionality is very limited
*/
if ($_GET['sSearch'] != "") {
    $sWhere = " WHERE ";
    for ($i = 0; $i < count($aColumns); $i++) {
        $sWhere .= $aColumns[$i]." LIKE %ss_".$i." OR ";
    }
    $sWhere = substr_replace($sWhere, "", -3);
}

DB::query(
    "SELECT * FROM ".$pre."kb
    $sWhere
    $sOrder",
    array(
        '0' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
        '1' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
        '2' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
        '3' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
        '4' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING)
    )
);
$iTotal = DB::count();

$rows = DB::query(
    "SELECT * FROM ".$pre."kb
    $sWhere
    $sOrder
    $sLimit",
    array(
        '0' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
        '1' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
        '2' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
        '3' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
        '4' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING)
    )
);
$iFilteredTotal = DB::count();

/*
   * Output
*/
$sOutput = '{';
$sOutput .= '"sEcho": '.intval($_GET['sEcho']).', ';
$sOutput .= '"iTotalRecords": '.$iTotal.', ';
$sOutput .= '"iTotalDisplayRecords": '.$iTotal.', ';
$sOutput .= '"aaData": ';

if ($iFilteredTotal > 0) {
    $sOutput .= '[';
}
foreach ($rows as $record) {
    $sOutput .= "[";

    //col1
    $sOutput .= '"<i class=\"fa fa-external-link fa-lg\" onclick=\"openKB(\''.$record['id'].'\')\" style=\"cursor:pointer;\"></i>';
    if ($record['anyone_can_modify'] == 1 || $record['author_id'] == $_SESSION['user_id']) {
        $sOutput .= '&nbsp;&nbsp;<i class=\"fa fa-trash-o mi-red fa-lg\" onclick=\"deleteKB(\''.$record['id'].'\')\" style=\"cursor:pointer;\"></i>';
    }
    $sOutput .= '",';

    //col2
    $ret_cat = DB::queryfirstrow("SELECT category FROM ".$pre."kb_categories WHERE id = %i", $record['category_id']);
    $sOutput .= '"'.htmlspecialchars(stripslashes($ret_cat['category']), ENT_QUOTES).'",';

    //col3
    $sOutput .= '"'.htmlspecialchars(stripslashes($record['label']), ENT_QUOTES).'",';

    //col4
    $ret_author = DB::queryfirstrow("SELECT login FROM ".$pre."users WHERE id = %i", $record['author_id']);
    $sOutput .= '"'.html_entity_decode($ret_author['login'], ENT_NOQUOTES).'"';

    //Finish the line
    $sOutput .= '],';
}

if (count($rows) > 0) {
    $sOutput = substr_replace($sOutput, "", -1);
    $sOutput .= '] }';
} else {
    $sOutput .= '[] }';
}

echo $sOutput;
