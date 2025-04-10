<?php
session_start();
if (!isset($_SESSION['s_authenticated']) || empty($_SESSION['s_authenticated'])) {
    header("location:../index.php");
    exit;
}
define("C_DEBUG", false);
define("C_BAR_HEIGHT", 20);
define("C_CANVAS_HEIGHT", 30);
define("C_RPTNAME", "disablePluSell");
define("C_TIMEOFFSET", 0);
$otimeoffset = C_TIMEOFFSET;
$rname = C_RPTNAME;

$pluNumb = $_POST['pluNumb'];
$pluDisabled = $_POST['pluDisabled'];
$sellPrice = $_POST['sellPrice'];

$C_SQL = '{"Id":"DatabaseId","Pass":"1234","PLUESQuery":{"Type":"Query","SQL":"UPDATE PLUES SET PLU_SELL_DISABLED_ = \'' . $pluDisabled . '\' WHERE PLU_NUMB = \'' . $pluNumb . '\'"}}';

include_once 'language.php';
// show loader for slow connections
require_once('class.loading.div.php');
$divLoader = new loadingDiv;
$divLoader->loader();

$objectid = "";
if (isset($_GET["id"])) {
    $objectid = $_GET["id"];
} else {
    $objectid = $_SESSION['s_objectid'];
}
$_SESSION['s_objectid'] = $objectid;

if (isset($_SESSION['s_deviceid'])) {
    $deviceid = $_SESSION['s_deviceid'];
} else {
    $deviceid = "0";
}
$rptdate = "";
if (isset($_GET["date"])) {
    $rptdate = $_GET["date"];
}

include('database.class.php');
$pDatabase = Database::getInstance();

$qry = $pDatabase->query("SELECT s_customername, s_expiredate FROM t_subscriptions WHERE s_objectid = '" . sql_safe($_SESSION['s_objectid']) . "'");
while ($row = mysqli_fetch_assoc($qry)) {
    $expiredate = strtotime($row['s_expiredate']);
    $customername = $row['s_customername'];
}

$objectpswd = "";
$operator = "";
$rptsql = "";
$rname = "";

$rptsql = $C_SQL;
$rname = C_RPTNAME;

$qry = $pDatabase->query("SELECT d_objectpswd, d_timeoffset, d_editUsername, d_deviceid FROM t_devices WHERE d_deviceid = '$deviceid' AND d_objectid='" . sql_safe($objectid) . "' ;");
while ($row = mysqli_fetch_assoc($qry)) {
    $objectpswd = $row['d_objectpswd'];
    $otimeoffset = $row['d_timeoffset'];
    $operator = $row['d_editUsername'];
    $device_id = $row['d_deviceid'];
}
if (C_DEBUG) {
    echo "time offset: ";
    var_dump($otimeoffset);
    echo "<br/>";
    echo "operator: ";
    var_dump($operator);
    echo "<br/>";
    echo "device_id: ";
    var_dump($device_id);
    echo "<br/>";
}
//replace timoffset parameter in SQL
//$rptsql = str_replace('TIMEOFFSET','\''.$otimeoffset.'\'',$rptsql);

//replace date parameters in SQL
if ($rptdate == '') {
    $dt = new DateTime();
    $rptdate = $dt->format('Y-m-d');
}
$rptsql = str_replace('PARAMDATE', '\'' . $rptdate . '\'', $rptsql);
if (C_DEBUG) {
    echo "report date: ";
    var_dump($rptdate);
    echo "<br/>";
}

$pDatabase->logevent(OPER_COMMAND,$objectid,'report: DisablePluSell.php Sell disabled for plu= '.$pluNumb.' objectid='.$objectid);


if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['pluNumb'], $_POST['pluDisabled'])) {
    $pluNumb = $_POST['pluNumb'];
    $pluDisabled = $_POST['pluDisabled'];

    $url = 'http://' . $_SESSION['s_rpt_server_host'] . ':' . $_SESSION['s_rpt_server_port'] . '/report/' . $rname . '/?id=' . $objectid . '&u=' . $_SESSION['s_rpt_server_user'] . '&p=' . $_SESSION['rpt_server_pswd'];
    if (C_DEBUG) {
        echo "url: ";
        var_dump($url);
        echo "<br/>";
    }
    $obj = json_decode($rptsql);
    $obj->{"Id"} = $objectid;
    $obj->{"Pass"} = $objectpswd;
    $rptsql = json_encode($obj);

    if (C_DEBUG) {
        echo "content: ";
        var_dump($rptsql);
        echo "<br/>";
    }
    $options = array(
        'http' => array(
            'timeout' => 45, //timeout in seconds
            'header'  => "Content-type: application/json\r\n",
            'method'  => 'POST',
            'content' => $rptsql
        ),
    );

    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    if (defined("C_DEBUG") && C_DEBUG) {
        echo "response: ";
        var_dump($result);
        echo "<br/>";
    }

    if ($result === FALSE) {
        echo "Error performing update";
    } else {
        echo "PluName updated successfully";

        // Construct the dynamic EV_DESCRIPTION and EV_DATATEXT
        $EV_DESCRIPTION = '[МОБИЛНИ СПРАВКИ] [ПРОМЯНА АРТИКУЛ] ОБЕКТ (' . $objectid . ') Оператор(' . $operator . ') забрани за продажба артикул с артикулен номер(' . $pluNumb . ').';
        $EV_DATATEXT = 'Device ID - ' . $device_id;

        // Execute the additional SQL command for EVENTS
        $EVENTS_SQL = '{"Id":"DatabaseId","Pass":"1234","EVENTSInsert":{"Type":"Query","SQL":"INSERT INTO EVENTS (EV_ID, EV_TYPE, EV_MODULE, EV_OPERATOR, EV_DATETIME, EV_DESCRIPTION, EV_DATANUMB, EV_DATATEXT, EV_EXPORTED_, EV_OFFICE) VALUES (NEXT VALUE FOR GEN_EVENTS_ID, 6, 24, 1, CURRENT_TIMESTAMP, \'' . $EV_DESCRIPTION . '\', ' . $pluNumb . ', \'' . $EV_DATATEXT . '\', 0, 0)"}}';

        $obj = json_decode($EVENTS_SQL);
        $obj->{"Id"} = $objectid;
        $obj->{"Pass"} = $objectpswd;
        $events_sql = json_encode($obj);

        if (C_DEBUG) {
            echo "content: ";
            var_dump($events_sql);
            echo "<br/>";
        }
        $options = array(
            'http' => array(
                'timeout' => 45, //timeout in seconds
                'header'  => "Content-type: application/json\r\n",
                'method'  => 'POST',
                'content' => $events_sql
            ),
        );

        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        if (defined("C_DEBUG") && C_DEBUG) {
            echo "response: ";
            var_dump($result);
            echo "<br/>";
        }

        if ($result === FALSE) {
            echo "Error inserting into EVENTS";
        } else {
            echo "EVENTS record inserted successfully";
        }
    }
} else {
    echo "Invalid request";
}
session_write_close();
?>
