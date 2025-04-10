<?php
session_start();
if (!isset($_SESSION['s_authenticated']) || empty($_SESSION['s_authenticated'])) {
    header("location:../index.php");
    exit;
}
define("C_DEBUG", false);
define("C_BAR_HEIGHT", 20);
define("C_CANVAS_HEIGHT", 30);
define("C_RPTNAME", "updatePrice");
define("C_TIMEOFFSET", 0);
$otimeoffset = C_TIMEOFFSET;
$rname = C_RPTNAME;

//$pluNumb = $_POST['pluNumb'];
//$pluName = $_POST['pluName'];
//

$taxGrpId = $_POST['$taxGrpId'] ?? '';

$taxGrpId = $_SESSION['pluTaxgroupId'] ?? '';

$C_SQL = '{"Id":"DatabaseId","Pass":"1234","PLUESQuery":{"Type":"Query","SQL":"SELECT TAXGRP_DESCR FROM N_WAT WHERE TAXGRP_NUMB =  \'' . $taxGrpId . '\'"}}';


//$C_SQL = '{"Id":"DatabaseId","Pass":"1234","PLUESQuery":{"Type":"Query","SQL":"SELECT TAXGRP_DESCR FROM N_WAT WHERE TAXGRP_NUMB =  2"}}';

include_once 'language.php';
// show loader for slow connections
require_once ('class.loading.div.php');
$divLoader = new loadingDiv;
$divLoader->loader();

$objectid = "";
if(isset($_GET["id"])){
    $objectid = $_GET["id"];
}
else {
    $objectid = $_SESSION['s_objectid'];
}
$_SESSION['s_objectid'] = $objectid;

if(isset($_SESSION['s_deviceid'])){
    $deviceid = $_SESSION['s_deviceid'];
}
else {
    $deviceid = "0";
}
$rptdate = "";
if(isset($_GET["date"])){
    $rptdate = $_GET["date"];
}


include('database.class.php');
$pDatabase = Database::getInstance();

$qry = $pDatabase->query("SELECT s_customername, s_expiredate FROM t_subscriptions WHERE s_objectid = '".sql_safe($_SESSION['s_objectid'])."'");
while ($row = mysqli_fetch_assoc($qry)) {
    $expiredate = strtotime($row['s_expiredate']);
    $customername = $row['s_customername'];
}

//    if ($expiredate >= time()) {
$objectpswd = "";
$rptsql = "";
$rname = "";

$rptsql = $C_SQL;
$rname = C_RPTNAME;

$qry = $pDatabase->query("select d_objectpswd,d_timeoffset from t_devices where d_deviceid = '$deviceid' and d_objectid='".sql_safe($objectid)."' ;");
while ($row = mysqli_fetch_assoc($qry)) {
    $objectpswd = $row['d_objectpswd'];
    $otimeoffset = $row['d_timeoffset'];
}
if (C_DEBUG) {echo "time offset: "; var_dump($otimeoffset); echo "<br/>";}
//replace timoffset parameter inSQL
//$rptsql = str_replace('TIMEOFFSET','\''.$otimeoffset.'\'',$rptsql);

//replace date parameters in SQL
if ($rptdate == ''){
    $dt = new DateTime();
    $rptdate = $dt->format('Y-m-d');
}
$rptsql = str_replace('PARAMDATE','\''.$rptdate.'\'',$rptsql);
if (C_DEBUG) {echo "report date: "; var_dump($rptdate); echo "<br/>";}


$pDatabase->logevent(OPER_COMMAND,$objectid,'report: '.$rname.' objectid='.$objectid);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['$taxGrpId'],)) {$$taxGrpId = $_POST['$taxGrpId'];

    $url = 'http://'.$_SESSION['s_rpt_server_host'].':'.$_SESSION['s_rpt_server_port'].'/report/'.$rname.'/?id='.$objectid.'&u='.$_SESSION['s_rpt_server_user'].'&p='.$_SESSION['rpt_server_pswd'];
    if (C_DEBUG) {echo "url: "; var_dump($url); echo "<br/>";}
    $obj = json_decode($rptsql);
    $obj->{"Id"} = $objectid;
    $obj->{"Pass"} = $objectpswd;
    $rptsql = json_encode($obj);

    if (C_DEBUG) {echo "content: "; var_dump($rptsql); echo "<br/>";}
    $options = array(
        'http' => array(
            'timeout' => 45, //timeout in seconds
            'header'  => "Content-type: text/xml\r\n",
            'method'  => 'GET',
            'content' => $rptsql
        ),
    );

    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);

    if (defined("C_DEBUG") && C_DEBUG) {echo "response: "; var_dump($result); echo "<br/>";}

    if ($result === FALSE) {
        echo "Error performing update";
    } else {
        echo "PluName updated successfully";
    }
} else {
    echo "Invalid request";
}
//file_put_contents('debug.txt', var_export($_POST, true));
file_put_contents('debug.txt', var_export($rptsql, true));
?>