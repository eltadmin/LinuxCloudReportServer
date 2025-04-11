<?php
session_start();
if(!isset($_SESSION['s_authenticated']) || empty($_SESSION['s_authenticated'])){
    header("location:../index.php");
}

$_SESSION['parent_url'] = array();

include_once 'language.php';
include('database.class.php');
$pDatabase = Database::getInstance();
$result = $pDatabase->query("set names 'utf8'");

$objid = "";
if(isset($_GET["id"])){
    $objid = $_GET["id"];
} else {
    $objid = $_SESSION['s_objectid'];
}
$_SESSION['s_objectid'] = $objid;

$objname = "";
if(isset($_GET["n"])){
    $objname = html_entity_decode($_GET["n"]);
} else {
    $objname = html_entity_decode($_SESSION['s_objectname']);
}
$_SESSION['s_objectname'] = $objname;

if(isset($_SESSION['s_deviceid'])){
    $deviceid = $_SESSION['s_deviceid'];
} else {
    $deviceid = "0";
}
?>
<!DOCTYPE html>
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=EmulateIE11" />
  <title>Detelina Reports</title>
  <link rel="stylesheet" href="css/style.css">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="css/w3.css">
  <link rel="stylesheet" href="css/jquery.alerts.css">
  <script type="text/javascript" src="js/jquery.min.js" ></script>
  <script type="text/javascript" src="js/jquery.alerts.js"></script>
  <link rel="stylesheet" href="css/box.css">
    <style>
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            width: 100%;
        }

        .login-card {
            background-color: white;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            padding: 20px;
            box-sizing: border-box;
        }

        .login-card h1 {
            text-align: center;
            color: black;
            font-size: 30px;
        }

        .login-card h2 {
            text-align: center;
            color: black;
            font-size: 25px;
			margin-bottom: 5px;
        }

        .login-help {
            text-align: center;
            font-size: 12px;
            color: #555;
            margin-bottom: 5px;
			margin-top: -10px;
        }

        .reports {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            width: 100%;
            box-sizing: border-box;
        }

        .reports a {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 70px;
            border-radius: 20px;
            color: white;
            text-align: center;
            text-decoration: none;
            font-size: 13px;
            transition: all 0.3s ease;
            box-shadow: rgb(38, 70, 83) 0px 11px 8px -4px;
            padding: 10px;
			
			font-weight: 500;
        }

        .login-card a:hover {
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
            transform: translateY(-2px);
        }

        .custom-button-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 100%;
            gap: 10px;
            margin-top: 10px;
        }

        .custom-button-container a {
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 40px;
            border-radius: 50px;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s ease;
            box-shadow: rgb(38, 70, 83) 0px 11px 8px -5px;
            color: white;
            margin-top: 5px;
        }

        .custom-button-container a:hover {
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
            transform: translateY(-2px);
        }

        .button-articles {
            background-color: #e98654;
			width: 100%;
        }

        .button-objdetails {
            background-color: #6956A5;
			width: 100%;
        }

        .button-exit {
            background-color: #FBB03B;
			width: 100%;
        }
    </style>
</head>

<body>
    <div class="login-card">
        <div class="login-help">
            <?php
            $appdbtype = '';
            $qry = $pDatabase->query("SELECT s_customername, s_expiredate, s_appdbtype FROM t_subscriptions WHERE s_objectid = '" . sql_safe($_SESSION['s_objectid']) . "'");
            if (mysqli_num_rows($qry) == 0) {
                $pDatabase->show_alert(ALERT_ERROR, $lang["AlertError"], $lang["errObjectNotSubscribed"], false, false);
                $expiredate = 0;
                $pDatabase->logevent(OPER_ERROR, $deviceid, 'object: ' . $objid . ' error: ' . $lang["errObjectNotSubscribed"]);
            } else {
                while ($row = mysqli_fetch_assoc($qry)) {
                    $expiredate = strtotime($row['s_expiredate']);
                    $appdbtype = $row['s_appdbtype'];
                    if ($expiredate <= time()) {
                        $pDatabase->show_alert(ALERT_WARNING, $lang["AlertWarning"], $lang["errObjectExpired"] . date("d.m.Y", $expiredate), false, true);
                        $pDatabase->logevent(OPER_ERROR, $deviceid, 'object: ' . $objid . ' error: ' . $lang["errObjectExpired"]);
                    }
                    echo '<span>' . $row['s_customername'] . ' • ' . $lang["ObjectExpireOn"] . date("d.m.Y", $expiredate) . '</span>';
                }
            }

            if ($_SESSION['lang'] == 'bg') {
                echo ' • <a href="rptlist.php?lang=en"><img src="images/en.png" /></a>';
            } else {
                echo ' • <a href="rptlist.php?lang=bg"><img src="images/bg.png" /></a>';
            }
            ?>
        </div>
        <h1><?php echo $lang["DETELINA"]; ?></h1>
        <h2><?= htmlspecialchars($objname) ?></h2><br>

        <div class="reports">
            <?php
            $qry = $pDatabase->query("SELECT r_friendlyname_" . sql_safe($_SESSION['lang']) . ", r_href, r_appdbtype, r_color, r_textcolor FROM t_reports WHERE NULLIF(r_objectid, '') IS NULL OR r_objectid = '" . sql_safe($_SESSION['s_objectid']) . "' ORDER BY r_order");

            while ($row = mysqli_fetch_assoc($qry)) {
                if ($row['r_href'] == 'articles.php') {
                    continue;
                }
                $textcolor = htmlspecialchars($row['r_textcolor']);
                $button_color = htmlspecialchars($row['r_color']);
                $rpttype = $row['r_appdbtype'];

                if ($rpttype != null && $rpttype != '' && $appdbtype != '' && $appdbtype != null) {
                    $typearr = str_split($appdbtype);
                    foreach ($typearr as $dbtype) {
                        if (strpos($rpttype, $dbtype) !== false) {
                            switch ($row['r_href']) {
                                case 'openbills.php':
                                case 'closedbills.php':
                                case 'voidplues.php':
                                    if ($expiredate <= time()) {
                                        echo '<a href="#" onclick="location.reload();" style="background-color: #9e60ad;"><span style="color:' . $textcolor . ';">' . htmlspecialchars($row['r_friendlyname_' . $_SESSION['lang']]) . '</span></a>';
                                    } else {
                                        echo '<a href="' . $row['r_href'] . '?id=' . $_SESSION['s_objectid'] . '" style="background-color:' . $button_color . '"><span style="color:' . $textcolor . ';">' . htmlspecialchars($row['r_friendlyname_' . $_SESSION['lang']]) . '</span></a>';
                                    }
                                    break;
                                default:
                                    echo '<a href="' . $row['r_href'] . '?id=' . $_SESSION['s_objectid'] . '" style="background-color:' . $button_color . '"><span style="color:' . $textcolor . ';">' . htmlspecialchars($row['r_friendlyname_' . $_SESSION['lang']]) . '</span></a>';
                                    break;
                            }
                        }
                    }
                } else {
                    echo '<a href="' . $row['r_href'] . '?id=' . $_SESSION['s_objectid'] . '" style="background-color:' . $button_color . '"><span style="color:' . $textcolor . ';">' . htmlspecialchars($row['r_friendlyname_' . $_SESSION['lang']]) . '</span></a>';
                }
            }
            ?>
        </div>
        <div class="custom-button-container">
            <?php
            $qry = $pDatabase->query("SELECT r_friendlyname_" . sql_safe($_SESSION['lang']) . ", r_href, r_appdbtype, r_color, r_textcolor FROM t_reports WHERE r_href = 'articles.php' AND (NULLIF(r_objectid, '') IS NULL OR r_objectid = '" . sql_safe($_SESSION['s_objectid']) . "') ORDER BY r_order");
            while ($row = mysqli_fetch_assoc($qry)) {
                $textcolor = htmlspecialchars($row['r_textcolor']);
                $button_color = htmlspecialchars($row['r_color']);
                echo '<a href="' . $row['r_href'] . '?id=' . $_SESSION['s_objectid'] . '" style="background-color:' . $button_color . '" class="button-articles"><span style="color:' . $textcolor . ';">' . htmlspecialchars($row['r_friendlyname_' . $_SESSION['lang']]) . '</span></a>';
            }
            ?>
            <a href="objdetails.php" class="button-objdetails"><span style="color: white; font-size: 14px"><?php echo $lang["objDetails"]; ?></span></a>
            <a href="device.php" class="button-exit"><span style="color: white; font-size: 14px"><?php echo $lang["btnExit"]; ?></span></a>
        </div>
    </div>
</body>

</html>
