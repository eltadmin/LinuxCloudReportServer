<?php
session_start();
if (!isset($_SESSION['s_authenticated']) || empty($_SESSION['s_authenticated'])) {
    header("location:../index.php");
    exit;
}

define("C_DEBUG", false);
define("C_BAR_HEIGHT", 20);
define("C_CANVAS_HEIGHT", 30);
define("C_RPTNAME", "addUserPassEdit");
define("C_TIMEOFFSET", 0);
$otimeoffset = C_TIMEOFFSET;
$rname = C_RPTNAME;

$editUserName = $_POST['editUserName'] ?? '';
$editPassword = $_POST['editPassword'] ?? '';

include_once 'language.php';
require_once('class.loading.div.php');
$divLoader = new loadingDiv;
$divLoader->loader();

$objectid = $_GET["id"] ?? $_SESSION['s_objectid'];
$_SESSION['s_objectid'] = $objectid;

$deviceid = $_SESSION['s_deviceid'] ?? "0";
$rptdate = $_GET["date"] ?? "";

include('database.class.php');
$pDatabase = Database::getInstance();

$qry = $pDatabase->query("SELECT s_customername, s_expiredate FROM t_subscriptions WHERE s_objectid = '".sql_safe($_SESSION['s_objectid'])."'");
while ($row = mysqli_fetch_assoc($qry)) {
    $expiredate = strtotime($row['s_expiredate']);
    $customername = $row['s_customername'];
}

$objectpswd = "";
$otimeoffset = 0;

$qry = $pDatabase->query("SELECT d_objectpswd, d_timeoffset FROM t_devices WHERE d_deviceid = '$deviceid' AND d_objectid = '".sql_safe($objectid)."'");
while ($row = mysqli_fetch_assoc($qry)) {
    $objectpswd = $row['d_objectpswd'];
    $otimeoffset = $row['d_timeoffset'];
}
if (C_DEBUG) { echo "time offset: "; var_dump($otimeoffset); echo "<br/>"; }

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['editUserName'], $_POST['editPassword'])) {
    $safeEditUserName = sql_safe($editUserName);
    $safeEditPassword = sql_safe($editPassword);
    $updateSql = "UPDATE t_devices SET d_editUsername = '$safeEditUserName', d_editPassword = '$safeEditPassword' WHERE d_deviceid = '$deviceid' AND d_objectid = '".sql_safe($objectid)."'";

    if (C_DEBUG) { echo "SQL Query: "; var_dump($updateSql); echo "<br/>"; }

    $result = $pDatabase->query($updateSql);

    if ($result === FALSE) {
        echo "Error performing update";
    } else {
        echo "User and password added successfully<br>";

        // Log the event in the main log table
        $pDatabase->logevent(OPER_ADDOBJECT, $deviceid, 'Device ID: '.$deviceid.' added operatorName: '.$editUserName.' for object: '.$objectid.' with OperatorPass: '.$editPassword);

        // Log the update in the t_statistics table
        $s_datetime = date('Y-m-d H:i:s');
        $s_description = "report: AddUserPassEdit.php, Added new Username/Password: $editUserName / $editPassword";
        $logSql = "INSERT INTO t_statistics (s_opertype, s_operid, s_datetime, s_description) 
                   VALUES (10, '".sql_safe($objectid)."', '$s_datetime', '".sql_safe($s_description)."')";

        if (C_DEBUG) { echo "Log SQL Query: "; var_dump($logSql); echo "<br/>"; }

        $logResult = $pDatabase->query($logSql);

        if ($logResult === FALSE) {
            echo "Error logging the update";
        }
    }
} else {
    echo "Invalid request";
}
session_write_close();
?>
