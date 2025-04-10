<?php
session_start();

if (!isset($_SESSION['s_authenticated']) || empty($_SESSION['s_authenticated'])) {
    header("location:../index.php");
    exit;
}

ob_start();

define("C_RPTNAME", "articlesinfo");

$pluTaxgroupId = isset($_POST['pluTaxgroupId']) ? $_POST['pluTaxgroupId'] : '';
$pGrpId = isset($_POST['groupId']) ? $_POST['groupId'] : '';

$taxGroupDescr = '';
$pGrpName = '';

include('database.class.php');
$pDatabase = Database::getInstance();
$pDatabase->query("set names 'utf8'");

$objectid = $_SESSION['s_objectid'];
$objectpswd = "";

// Fetch tax group description
if (!empty($pluTaxgroupId)) {
    $C_SQL3 = '{"Id":"DatabaseId","Pass":"1234","PLUESQuery":{"Type":"Query","SQL":"SELECT TAXGRP_DESCR FROM N_WAT WHERE TAXGRP_NUMB = \'' . $pluTaxgroupId . '\'"}}';
    $obj = json_decode($C_SQL3);
    $obj->{"Id"} = $objectid;
    $obj->{"Pass"} = $objectpswd;
    $C_SQL3 = json_encode($obj);

    $options = [
        'http' => [
            'timeout' => 45,
            'header' => "Content-type: text/xml\r\n",
            'method' => 'GET',
            'content' => $C_SQL3
        ]
    ];
    $context = stream_context_create($options);
    $str = @file_get_contents('http://' . $_SESSION['s_rpt_server_host'] . ':' . $_SESSION['s_rpt_server_port'] . '/report/' . C_RPTNAME . '/?id=' . $objectid . '&u=' . $_SESSION['s_rpt_server_user'] . '&p=' . $_SESSION['rpt_server_pswd'], false, $context);

    if ($str !== false) {
        $response = json_decode($str, true); // Assuming the response is in JSON format
        if (isset($response['PLUESQuery'][0]['TAXGRP_DESCR'])) {
            $taxGroupDescr = $response['PLUESQuery'][0]['TAXGRP_DESCR'];
        }
    }
}

// Fetch group name
if (!empty($pGrpId)) {
    $C_SQL2 = '{"Id":"DatabaseId","Pass":"1234","PLUESQuery":{"Type":"Query","SQL":"SELECT PGRP_NAME FROM N_PLUGROUPS WHERE PGRP_ID = \'' . $pGrpId . '\'"}}';
    $obj = json_decode($C_SQL2);
    $obj->{"Id"} = $objectid;
    $obj->{"Pass"} = $objectpswd;
    $C_SQL2 = json_encode($obj);

    $options['http']['content'] = $C_SQL2;
    $context = stream_context_create($options);
    $str = @file_get_contents('http://' . $_SESSION['s_rpt_server_host'] . ':' . $_SESSION['s_rpt_server_port'] . '/report/' . C_RPTNAME . '/?id=' . $objectid . '&u=' . $_SESSION['s_rpt_server_user'] . '&p=' . $_SESSION['rpt_server_pswd'], false, $context);

    if ($str !== false) {
        $response = json_decode($str, true); // Assuming the response is in JSON format
        if (isset($response['PLUESQuery'][0]['PGRP_NAME'])) {
            $pGrpName = $response['PLUESQuery'][0]['PGRP_NAME'];
        }
    }
}

ob_end_clean(); 

header('Content-Type: application/json');
echo json_encode(['taxGroupDescr' => $taxGroupDescr, 'pGrpName' => $pGrpName]);
?>
