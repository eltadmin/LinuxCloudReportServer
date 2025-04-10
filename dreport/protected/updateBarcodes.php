<?php
session_start();
if (!isset($_SESSION['s_authenticated']) || empty($_SESSION['s_authenticated'])) {
    header("location:../index.php");
    exit;
}
define("C_DEBUG", false);
define("C_BAR_HEIGHT", 20);
define("C_CANVAS_HEIGHT", 30);
define("C_RPTNAME", "updateBarcodes");
define("C_TIMEOFFSET", 0);
$otimeoffset = C_TIMEOFFSET;
$rname = C_RPTNAME;

$pluNumb = $_POST['pluNumb'];
$pluBarcode = $_POST['pluBarcode'];

$C_SQL = '{"Id":"DatabaseId","Pass":"1234","PLUESQuery":{"Type":"Query","SQL":"UPDATE BARCODES SET PLU_BARCODE = \'' . $pluBarcode . '\' WHERE PLU_NUMB = \'' . $pluNumb . '\'"}}';

include_once 'language.php';
// show loader for slow connections
require_once('class.loading.div.php');
$divLoader = new loadingDiv;
$divLoader->loader();

$objectid = $_SESSION['s_objectid'] ?? '';
$deviceid = $_SESSION['s_deviceid'] ?? '0';

include('database.class.php');
$pDatabase = Database::getInstance();  

$qry = $pDatabase->query("SELECT s_customername, s_expiredate FROM t_subscriptions WHERE s_objectid = '".sql_safe($_SESSION['s_objectid'])."'");
while ($row = mysqli_fetch_assoc($qry)) {
    $expiredate = strtotime($row['s_expiredate']);
    $customername = $row['s_customername'];
}

$objectpswd = '';
$rptsql = '';
$rname = '';

$rptsql = $C_SQL;
$rname = C_RPTNAME;

$qry = $pDatabase->query("SELECT d_objectpswd, d_timeoffset FROM t_devices WHERE d_deviceid = '$deviceid' AND d_objectid='".sql_safe($objectid)."'");
while ($row = mysqli_fetch_assoc($qry)) {
    $objectpswd = $row['d_objectpswd'];
    $otimeoffset = $row['d_timeoffset'];
}

if ($rptdate == '') {
    $dt = new DateTime();
    $rptdate = $dt->format('Y-m-d');
}
$rptsql = str_replace('PARAMDATE', '\'' . $rptdate . '\'', $rptsql);

$pDatabase->logevent(OPER_COMMAND, $objectid, 'report: ' . $rname . ' objectid=' . $objectid);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['pluNumb'], $_POST['pluBarcode'])) {
    $url = 'http://' . $_SESSION['s_rpt_server_host'] . ':' . $_SESSION['s_rpt_server_port'] . '/report/' . $rname . '/?id=' . $objectid . '&u=' . $_SESSION['s_rpt_server_user'] . '&p=' . $_SESSION['rpt_server_pswd'];
    $obj = json_decode($rptsql);
    $obj->{"Id"} = $objectid;
    $obj->{"Pass"} = $objectpswd;
    $rptsql = json_encode($obj);

    $options = [
        'http' => [
            'timeout' => 45,
            'header' => "Content-type: text/xml\r\n",
            'method' => 'GET',
            'content' => $rptsql
        ],
    ];

    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);

    if ($result === FALSE) {
        echo "Error performing update";
    } else {
        echo "Barcode updated successfully";
    }
} else {
    echo "Invalid request";
}
?>
