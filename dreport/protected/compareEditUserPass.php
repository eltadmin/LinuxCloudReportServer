<?php
session_start();
if (!isset($_SESSION['s_authenticated']) || empty($_SESSION['s_authenticated'])) {
    header("location:../index.php");
    exit;
}

define("C_BAR_HEIGHT", 20);
define("C_CANVAS_HEIGHT", 30);
define("C_RPTNAME", "updatePrice");
define("C_TIMEOFFSET", 0);
$otimeoffset = C_TIMEOFFSET;
$rname = C_RPTNAME;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['operatorUsername'])) {
    $operatorUsername = $_POST['operatorUsername'];

    if (empty($operatorUsername)) {
        echo "Invalid request: operatorUsername is empty";
        exit;
    }

    $C_SQL_OPERATORS = '{"Id":"DatabaseId","Pass":"1234","PLUESQuery":{"Type":"Query","SQL":"SELECT OPERATOR_USERNAME, OPERATOR_PASSWORD, OPERATOR_ACCESS, OPERATOR_ACTIVETODATE FROM N_OPERATORS WHERE OPERATOR_USERNAME = \'' . $operatorUsername . '\'"}}';

    include_once 'language.php';
    require_once('class.loading.div.php');
    $divLoader = new loadingDiv;
    $divLoader->loader();

    $objectid = isset($_GET["id"]) ? $_GET["id"] : $_SESSION['s_objectid'];
    $_SESSION['s_objectid'] = $objectid;

    $deviceid = isset($_SESSION['s_deviceid']) ? $_SESSION['s_deviceid'] : "0";
    $rptdate = isset($_GET["date"]) ? $_GET["date"] : "";

    include('database.class.php');
    $pDatabase = Database::getInstance();

    $qry = $pDatabase->query("SELECT s_customername, s_expiredate FROM t_subscriptions WHERE s_objectid = '".sql_safe($_SESSION['s_objectid'])."'");
    $subscription = mysqli_fetch_assoc($qry);
    if ($subscription) {
        $expiredate = strtotime($subscription['s_expiredate']);
        $customername = $subscription['s_customername'];
    } else {
        echo "No subscription found for the object ID.";
        exit;
    }

    $objectpswd = "";
    $otimeoffset = 0;

    $qry = $pDatabase->query("SELECT d_objectpswd, d_timeoffset FROM t_devices WHERE d_deviceid = '$deviceid' AND d_objectid = '".sql_safe($objectid)."'");
    $device = mysqli_fetch_assoc($qry);
    if ($device) {
        $objectpswd = $device['d_objectpswd'];
        $otimeoffset = $device['d_timeoffset'];
    } else {
        echo "No device found for the given device ID and object ID.";
        exit;
    }

    if ($rptdate == '') {
        $dt = new DateTime();
        $rptdate = $dt->format('Y-m-d');
    }

    // Initialize variables to avoid undefined variable error
    $dbOperatorUsername = "N/A";
    $dbOperatorPassword = "N/A";
    $dbEditUsername = "N/A";
    $dbEditPassword = "N/A";

    // Execute C_SQL to get operator details
    $url = 'http://' . $_SESSION['s_rpt_server_host'] . ':' . $_SESSION['s_rpt_server_port'] . '/report/' . $rname . '/?id=' . $objectid . '&u=' . $_SESSION['s_rpt_server_user'] . '&p=' . $_SESSION['rpt_server_pswd'];
    $obj = json_decode($C_SQL_OPERATORS);
    $obj->{"Id"} = $objectid;
    $obj->{"Pass"} = $objectpswd;
    $rptsql = json_encode($obj);

    $options = array(
        'http' => array(
            'timeout' => 45,
            'header' => "Content-type: text/xml\r\n",
            'method' => 'GET',
            'content' => $rptsql
        ),
    );

    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    if ($result !== FALSE) {
        $operatorData = json_decode($result, true);
        if (isset($operatorData['PLUESQuery'][0])) {
            $operatorDetails = $operatorData['PLUESQuery'][0];
            $dbOperatorUsername = $operatorDetails['OPERATOR_USERNAME'];
            $dbOperatorPassword = $operatorDetails['OPERATOR_PASSWORD'];

            echo "Operator Username: $dbOperatorUsername<br>";
            echo "Operator Password: $dbOperatorPassword<br>";

            // Retrieve and compare from t_devices
            $qry = $pDatabase->query("SELECT d_editUsername, d_editPassword FROM t_devices WHERE d_deviceid = '$deviceid' AND d_objectid = '" . sql_safe($objectid) . "'");
            $deviceRow = mysqli_fetch_assoc($qry);
            if ($deviceRow) {
                $dbEditUsername = $deviceRow['d_editUsername'];
                $dbEditPassword = $deviceRow['d_editPassword'];  

                echo "Edit Username: $dbEditUsername<br>";
                echo "Edit Password: $dbEditPassword<br>";

                
                function validatePassword($inputPassword, $storedPassword) {
                    // extract the salt from the stored password
                    $salt = substr($storedPassword, 0, 2);

                    // encrypt input password with the extracted salt
                    $encryptedPassword = crypt($inputPassword, $salt);

                    // compare encrypted passwords
                    return hash_equals($encryptedPassword, $storedPassword);
                }

                // validate passwords
                $operatorPasswordValid = validatePassword($dbEditPassword, $dbOperatorPassword);

                if ($dbOperatorUsername == $dbEditUsername && $operatorPasswordValid) {
                    echo "Operator credentials match in both databases.";
                } else {
                    echo "Operator credentials do not match in both databases.";
                }
            } else {
                echo "Device details not found in t_devices.";
            }
        } else {
            echo "Operator not found in N_OPERATORS.";
            echo "IBEXPERT NAME: $dbOperatorUsername<br>";
            echo "IBEXPERT PASS: $dbOperatorPassword<br>";
            echo "CLOUD NAME: $dbEditUsername<br>";
            echo "CLOUD PASS: $dbEditPassword<br>";
        }
    } else {
        echo "Error performing operator query";
    }
} else {
    echo "Invalid request";
}

session_write_close();
?>
