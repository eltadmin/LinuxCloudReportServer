<?php
session_start();
if (!isset($_SESSION['s_authenticated']) || empty($_SESSION['s_authenticated'])) {
    header("location:../index.php");
    exit;
}

define("C_DEBUG", false);
define("C_BAR_HEIGHT", 20);
define("C_CANVAS_HEIGHT", 30);
define("C_RPTNAME", "updateDetails");
define("C_TIMEOFFSET", 0);
$otimeoffset = C_TIMEOFFSET;
$rname = C_RPTNAME;

$pluNumb = $_GET['pluNumb'] ?? '';
$pluName = $_GET['pluName'] ?? '';
$groupId = $_GET['groupId'] ?? '';
$sellPrice = $_GET['sellPrice'] ?? '';
$promotion = $_GET['promotion'] ?? '';
$barcode = $_GET['barcode'] ?? '';
$pluEcrName = $_GET['pluEcrName'] ?? '';
$pluBuyPrice = $_GET['pluBuyPrice'] ?? '';
$pluTaxgroupId = $_GET['pluTaxgroupId'] ?? '';
$taxGroupDescr = $_GET['taxGroupDescr'] ?? '';
$pGrpName = $_GET['pGrpName'] ?? '';
$isOperatorValidated = $_GET['isOperatorValidated'] ?? '0';
$editUserName = $_GET['editUserName'] ?? '';
$editPassword = $_GET['editPassword'] ?? '';
$isCentralDb = $_GET['isCentralDb'] ?? '0';
$pluLocalPrice = $_GET['pluLocalPrice'] ?? '0';
$pluSellDisabled = $_GET['pluSellDisabled'] ?? '0';

$searchQuery = isset($_GET['search']) ? $_GET['search'] : '';
$searchFilter = isset($_GET['filter']) ? $_GET['filter'] : '';
$page = isset($_GET['page']) ? $_GET['page'] : 1;

$C_SQL_SELECT = '{"Id":"DatabaseId","Pass":"1234","PLUESQuery":{"Type":"Query","SQL":"SELECT PR_ID, PR_PRIORITY, PR_FROMDATE, PR_TODATE, PR_FROMTIME, PR_TOTIME, PR_PRICE, PR_PACKET_TYPE, PR_ISACTIVE_ FROM PROMOTIONS WHERE PR_PLUNUMB = \'' . $pluNumb . '\'"}}';
$C_SQL_DELETE = '{"Id":"DatabaseId","Pass":"1234","PLUESQuery":{"Type":"Query","SQL":"DELETE FROM PROMOTIONS WHERE PR_ID = ?"}}';

include_once 'language.php';
require_once('class.loading.div.php');
$divLoader = new loadingDiv;

$objectid = $_GET["id"] ?? $_SESSION['s_objectid'];
$_SESSION['s_objectid'] = $objectid;
$deviceid = $_SESSION['s_deviceid'] ?? "0";
$rptdate = $_GET["date"] ?? '';

include('database.class.php');
$pDatabase = Database::getInstance();

$qry = $pDatabase->query("SELECT s_customername, s_expiredate FROM t_subscriptions WHERE s_objectid = '".sql_safe($_SESSION['s_objectid'])."'");
while ($row = mysqli_fetch_assoc($qry)) {
    $expiredate = strtotime($row['s_expiredate']);
    $customername = $row['s_customername'];
}
$qry = $pDatabase->query("SELECT d_objectpswd, d_timeoffset, d_editUsername, d_deviceid FROM t_devices WHERE d_deviceid = '$deviceid' AND d_objectid='".sql_safe($objectid)."' ;");
while ($row = mysqli_fetch_assoc($qry)) {
    $objectpswd = $row['d_objectpswd'];
    $otimeoffset = $row['d_timeoffset'];
    $operator = $row['d_editUsername'];
    $device_id = $row['d_deviceid'];
}
if (C_DEBUG) {
    echo "time offset: "; var_dump($otimeoffset); echo "<br/>";
    echo "operator: "; var_dump($operator); echo "<br/>";
    echo "device_id: "; var_dump($device_id); echo "<br/>";
}

$objectpswd = "";
$rptsql = "";
$rname = "";
$pDatabase->logevent(OPER_COMMAND,$objectid,'report: ActivePromotions objectid='.$objectid);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['promotionId'])) {
    header('Content-Type: application/json');
    ob_start();

    $promotionId = $_POST['promotionId'];
    $rptsql = str_replace('?', $promotionId, $C_SQL_DELETE);
    $rname = C_RPTNAME;

    $qry = $pDatabase->query("select d_objectpswd,d_timeoffset from t_devices where d_deviceid = '$deviceid' and d_objectid='".sql_safe($objectid)."' ;");
    while ($row = mysqli_fetch_assoc($qry)) {
        $objectpswd = $row['d_objectpswd'];
        $otimeoffset = $row['d_timeoffset'];
    }

    if ($rptdate == '') {
        $dt = new DateTime();
        $rptdate = $dt->format('Y-m-d');
    }
    $rptsql = str_replace('PARAMDATE','\''.$rptdate.'\'',$rptsql); 

    $url = 'http://' . $_SESSION['s_rpt_server_host'] . ':' . $_SESSION['s_rpt_server_port'] . '/report/' . $rname . '/?id=' . $objectid . '&u=' . $_SESSION['s_rpt_server_user'] . '&p=' . $_SESSION['rpt_server_pswd'];
    $obj = json_decode($rptsql);
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

    ob_end_clean();
    if ($result === FALSE) {
        echo json_encode(['success' => false, 'message' => 'Error deleting promotion.']);
    } else {
        // Log the deletion event
        $EV_DESCRIPTION = '[МОБИЛНИ СПРАВКИ] [ПРЕМАХВАНЕ НА ПРОМОЦИЯ] ОБЕКТ (' . $objectid . ') Оператор (' . $operator . ') премахна промоция с ID (' . $promotionId . ').';
        $EV_DATATEXT = 'Device ID - ' . $deviceid;

        $EVENTS_SQL = '{"Id":"DatabaseId","Pass":"1234","EVENTSInsert":{"Type":"Query","SQL":"INSERT INTO EVENTS (EV_ID, EV_TYPE, EV_MODULE, EV_OPERATOR, EV_DATETIME, EV_DESCRIPTION, EV_DATANUMB, EV_DATATEXT, EV_EXPORTED_, EV_OFFICE) VALUES (NEXT VALUE FOR GEN_EVENTS_ID, 6, 24, 1, CURRENT_TIMESTAMP, \'' . $EV_DESCRIPTION . '\', ' . $promotionId . ', \'' . $EV_DATATEXT . '\', 0, 0)"}}';
		$pDatabase->logevent(OPER_COMMAND,$objectid,'report: ActivePromotions.php - promotion deleted objectid='.$objectid);
       
        $obj = json_decode($EVENTS_SQL);
        $obj->{"Id"} = $objectid;
        $obj->{"Pass"} = $objectpswd;
        $events_sql = json_encode($obj);

        $options = array(
            'http' => array(
                'timeout' => 45,
                'header' => "Content-type: application/json\r\n",
                'method' => 'POST',
                'content' => $events_sql
            ),
        );

        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);

        if ($result === FALSE) {
            echo json_encode(['success' => true, 'message' => 'Promotion deleted but failed to log event.']);
        } else {
            echo json_encode(['success' => true, 'message' => 'Promotion deleted and event logged successfully.']);
        }
    }
    exit;
}

$rptsql = $C_SQL_SELECT;
$rname = C_RPTNAME;

$qry = $pDatabase->query("select d_objectpswd,d_timeoffset from t_devices where d_deviceid = '$deviceid' and d_objectid='".sql_safe($objectid)."' ;");
while ($row = mysqli_fetch_assoc($qry)) {
    $objectpswd = $row['d_objectpswd'];
    $otimeoffset = $row['d_timeoffset'];
}

if ($rptdate == '') {
    $dt = new DateTime();
    $rptdate = $dt->format('Y-m-d');
}
$rptsql = str_replace('PARAMDATE','\''.$rptdate.'\'',$rptsql);
 

if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['pluNumb'])) {
    $pluNumb = $_GET['pluNumb'];

    $url = 'http://' . $_SESSION['s_rpt_server_host'] . ':' . $_SESSION['s_rpt_server_port'] . '/report/' . $rname . '/?id=' . $objectid . '&u=' . $_SESSION['s_rpt_server_user'] . '&p=' . $_SESSION['rpt_server_pswd'];
    $obj = json_decode($rptsql);
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

    if ($result === FALSE) {
        echo "<p>Error fetching data.</p>";
    } else {
        $promotions = json_decode($result, true);
        if (isset($promotions['ResultCode']) && $promotions['ResultCode'] == 0) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=EmulateIE11"/>
    <title>Active Promotions</title>
    <link rel="stylesheet" href="css/style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="css/w3.css">
    <script src="js/Chart.min.js"></script>
    <link rel="stylesheet" href="css/box.css">
    <script type="text/javascript" src="js/jquery.min.js"></script>
    <script type="text/javascript" src="js/jquery.alerts.js"></script>
	<style>
	  .button {
    display: inline-block;  
    border-radius: 20px;
    font-size: 14px;
    text-align: center; 
    color: white;
	transition: background-color 0.3s ease;
	box-shadow: rgb(38, 70, 83) 0px 11px 8px -5px;
  }
  .color-1 { background-color: #36752d; }
  .color-5 { background-color: #FBB03B; }
  
  .color-1:hover, .color-1:active  {
    background-color: #1e5516;  
  }  
  .color-5:hover, .color-5:active  {
    background-color: #e18e0a;  
  }
  .login-help{
	  font-size: 11.4px;
  }
  
	</style>
</head>

<body>
 <?php
     //show message for expired account
     if ($expiredate < time()){
      echo '<meta http-equiv="X-UA-Compatible" content="IE=EmulateIE11" />';
      echo '<div class="alert-box warning" id="alert-box-exp" style= "display : none">';
      echo '<span>'.$lang["AlertWarning"].'</span>'.$lang["errObjectExpired"].date("d.m.Y", $expiredate);
      echo '</div>';
      echo '<script>';
      echo 'function show_exp() {document.getElementById("alert-box-exp").removeAttribute("style"); setTimeout(function(){document.getElementById("alert-box-exp").style.display = "none";},5000); }';
      echo '</script>';
     }
 ?>
<script> 
function deletePromotion(promotionId) {
        if (confirm("Are you sure you want to delete this promotion?")) {
            var xhr = new XMLHttpRequest();
            // Set the URL to the current file
            var currentUrl = window.location.href;
            xhr.open("POST", currentUrl, true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4) {
                    if (xhr.status >= 200 && xhr.status < 300) {
                        try {
                            var response = JSON.parse(xhr.responseText);
                            if (response.success) {
                                location.reload();
                            } else {
                                alert("Error: " + response.message);
                            }
                        } catch (e) {
                            alert("Error processing response: " + xhr.responseText);
                        }
                    } else {
                        alert("Error: Unable to complete request. Status: " + xhr.status);
                    }
                }
            };

            xhr.onerror = function () {
                alert("Request error.");
            };
 
            console.log("Sending request: promotionId=" + encodeURIComponent(promotionId));

            xhr.send("promotionId=" + encodeURIComponent(promotionId));
        }
    }

    function managePromotion(pluNumb, pluName, sellPrice, groupId, pluEcrName, pluBuyPrice, pluTaxgroupId, taxGroupDescr, pGrpName, searchQuery, searchFilter, page, isOperatorValidated, pluLocalPrice, barcode, isCentralDb, pluSellDisabled) {
        var url = "PromotionDetails.php?" +
            "pluNumb=" + encodeURIComponent(pluNumb) +
            "&pluName=" + encodeURIComponent(pluName) +
            "&sellPrice=" + encodeURIComponent(sellPrice) +
            "&groupId=" + encodeURIComponent(groupId) +
            "&pluEcrName=" + encodeURIComponent(pluEcrName) +
            "&pluBuyPrice=" + encodeURIComponent(pluBuyPrice) +
            "&pluTaxgroupId=" + encodeURIComponent(pluTaxgroupId) +
            "&taxGroupDescr=" + encodeURIComponent(taxGroupDescr) +
            "&pGrpName=" + encodeURIComponent(pGrpName) +
            "&search=" + encodeURIComponent(searchQuery) +
            "&filter=" + encodeURIComponent(searchFilter) +
            "&page=" + encodeURIComponent(page) + 
            "&isOperatorValidated=" + encodeURIComponent(isOperatorValidated) + 
            "&isCentralDb=" + encodeURIComponent(isCentralDb) + 
            "&pluLocalPrice=" + encodeURIComponent(pluLocalPrice) + 
			"&barcode=" + encodeURIComponent(barcode) +
			"&pluSellDisabled=" + encodeURIComponent(pluSellDisabled);
			
        window.location.href = url;
    }

    function displayPromotionStatus(promotions) {
        var hasActivePromotions = false;
        console.log("Checking promotions: ", promotions); // Log the promotions data received
        for (var i = 0; i < promotions['PLUESQuery'].length; i++) {
            var promotion = promotions['PLUESQuery'][i];
            if (promotion['PR_ISACTIVE_'] >= 1) {
                hasActivePromotions = true;
                break;
            }
        }

        var promotionStatus = document.getElementById('promotionStatus');
        if (!hasActivePromotions) {
            console.log("No active promotions."); // Log when no active promotions are found
            promotionStatus.innerText = "<?php echo htmlspecialchars($pluName); ?><?php echo $lang['hasNoActivePromotions'];?>";
        } else {
            console.log("Active promotions found."); // Log when active promotions are found
            promotionStatus.innerText = "<?php echo htmlspecialchars($pluName); ?><?php echo $lang['hasActivePromotions'];?>";
        }
    }

    function toggleDetails(id) {
        var detailsRow = document.getElementById('details-' + id);
        var img = detailsRow.previousElementSibling.querySelector('img[alt="More"]');

        if (detailsRow.style.display === 'none' || detailsRow.style.display === '') {
            detailsRow.style.display = 'table-row';
            img.src = 'images/collapse.png';
        } else {
            detailsRow.style.display = 'none';
            img.src = 'images/open.png';
        }
    }

    if (document.addEventListener) {
        document.addEventListener('DOMContentLoaded', function() {
            var isOperatorValidated = "<?php echo $isOperatorValidated; ?>"; // Set the validation result
            var isCentralDb = "<?php echo $isCentralDb; ?>"; // Get the value of isCentralDb
			var pluLocalPrice = "<?php echo $pluLocalPrice; ?>"; 
			var barcode = "<?php echo $barcode; ?>";
			var pluSellDisabled	= "<?php echo $pluSellDisabled; ?>";	
            var newPromotionsLink = document.getElementById('newPromotionsLink');
            var deleteButtons = document.querySelectorAll('.deleteButton');
			
            // Show/hide new promotion button based on conditions
            newPromotionsLink.style.display = ((isOperatorValidated === '1') || (isCentralDb === '1')) ? 'block' : 'none';

            // Show/hide delete buttons based on conditions
            deleteButtons.forEach(function(button) {
                button.style.display = ((isOperatorValidated === '1') || (isCentralDb === '1')) ? 'block' : 'none';
            });

            displayPromotionStatus(<?php echo json_encode($promotions); ?>);

            newPromotionsLink.addEventListener('click', function() {
                managePromotion(
                    "<?php echo addslashes($pluNumb); ?>",
                    "<?php echo addslashes($pluName); ?>",
                    "<?php echo addslashes($sellPrice); ?>",
                    "<?php echo addslashes($groupId); ?>",
                    "<?php echo addslashes($pluEcrName); ?>",
                    "<?php echo addslashes($pluBuyPrice); ?>",
                    "<?php echo addslashes($pluTaxgroupId); ?>",
                    "<?php echo addslashes($taxGroupDescr); ?>",
                    "<?php echo addslashes($pGrpName); ?>",
                    "<?php echo addslashes($searchQuery); ?>",
                    "<?php echo addslashes($searchFilter); ?>",
                    "<?php echo addslashes($page); ?>",
                    isOperatorValidated,
					pluLocalPrice,
					barcode,
					isCentralDb,
					pluSellDisabled
                );
            });
        });
    } else {
        document.attachEvent('onreadystatechange', function() {
            if (document.readyState === 'complete') {
                var isOperatorValidated = "<?php echo $isOperatorValidated; ?>"; // Set the validation result
                var isCentralDb = "<?php echo $isCentralDb; ?>"; // Get the value of isCentralDb
                var pluSellDisabled = "<?php echo $pluSellDisabled; ?>"; // Get the value of isCentralDb
                var newPromotionsLink = document.getElementById('newPromotionsLink');
                var deleteButtons = document.querySelectorAll('.deleteButton');

                // Show/hide new promotion button based on conditions
                newPromotionsLink.style.display = ((isOperatorValidated === '1') || (isCentralDb === '1')) ? 'block' : 'none';

                // Show/hide delete buttons based on conditions
                deleteButtons.forEach(function(button) {
                    button.style.display = ((isOperatorValidated === '1') || (isCentralDb === '1')) ? 'block' : 'none';
                });

                displayPromotionStatus(<?php echo json_encode($promotions); ?>);

                newPromotionsLink.attachEvent('onclick', function() {
                    managePromotion(
                        "<?php echo addslashes($pluNumb); ?>",
                        "<?php echo addslashes($pluName); ?>",
                        "<?php echo addslashes($sellPrice); ?>",
                        "<?php echo addslashes($groupId); ?>",
                        "<?php echo addslashes($pluEcrName); ?>",
                        "<?php echo addslashes($pluBuyPrice); ?>",
                        "<?php echo addslashes($pluTaxgroupId); ?>",
                        "<?php echo addslashes($taxGroupDescr); ?>",
                        "<?php echo addslashes($pGrpName); ?>",
                        "<?php echo addslashes($searchQuery); ?>",
                        "<?php echo addslashes($searchFilter); ?>",
                        "<?php echo addslashes($page); ?>",
                        isOperatorValidated,
						pluLocalPrice,
						barcode,
						isCentralDb,
						pluSellDisabled
                    );
                });
            }
        });
    }
</script>

    <div class="login-card">
        <div>
            <div class="login-help">            
                <a href="http://eltrade.com">www.eltrade.com</a> • <a href="http://eltrade.com/bg/contacts"><?php echo $lang['contacts']; ?></a> •
                <?php
                echo '<a>' . htmlspecialchars($customername) . '</a> • ';
                $commonParams = 'pluNumb=' . urlencode($pluNumb) . '&pluName=' . urlencode($pluName) . '&group=' . urlencode($groupId) . '&sellPrice=' . urlencode($sellPrice) . '&promotion=' . urlencode($promotion) . '&barcode=' . urlencode($barcode) . '&pluEcrName=' . urlencode($pluEcrName) . '&pluBuyPrice=' . urlencode($pluBuyPrice) . '&pluTaxgroupId=' . urlencode($pluTaxgroupId) . '&taxGroupDescr=' . urlencode($taxGroupDescr) . '&pGrpName=' . urlencode($pGrpName) . '&search=' . urlencode($searchQuery) . '&filter=' . urlencode($searchFilter) . '&page=' . urlencode($page) . '&isOperatorValidated=' . urlencode($isOperatorValidated) . '&isCentralDb=' . urlencode($isCentralDb) . '&barcode=' . urlencode($barcode) .  '&pluSellDisabled=' . urlencode($pluSellDisabled) . '&pluLocalPrice=' . urlencode($pluLocalPrice);

                if ($_SESSION['lang'] == 'bg') {
                    echo '<a href="ActivePromotionsCSS.php?lang=en&' . $commonParams . '"><img src="images/en.png" /></a>';
                } else {
                    echo '<a href="ActivePromotionsCSS.php?lang=bg&' . $commonParams . '"><img src="images/bg.png" /></a>';
                }
                ?>
            </div>
            <h1 style="text-align: center; color: black;"><?php echo $lang['objActivePromotions']; ?>
            <span style="display: inline-block; width: 15px; height: 15px; background-color: <?php echo $isOperatorValidated === '1' ? 'green' : 'red'; ?>; border-radius: 50%; margin-left: 10px;"></span>
            </h1>
            <h2 style="text-align: center; color: black;"><?php echo htmlspecialchars($pluName); ?></h2>
            <div id="promotionStatus" style="text-align: center; color: #36752d; margin-top: 1px;"></div>
            <h4 align="center">
            <?php
              echo $lang["rptRevenueToDate"].date('d.m.Y H:i', time());
            ?>
            </h4>   
            <table id="tPromotions" style="width: 100%; border-collapse: collapse; margin: 1px 0; table-layout: fixed;">
                <thead>
                    <tr>
                        <th style="padding: 7px; text-align: left; border-left: 1px solid #36752d; border-right: 1px solid #36752d; background-color: #36752d; color: white; text-align: center; font-family: Arial, Helvetica, sans-serif; font-size: 11px;"><?php echo $lang['StartDate']; ?></th>
                        <th style="padding: 7px; text-align: left; border-left: 1px solid #36752d; border-right: 1px solid #36752d; background-color: #36752d; color: white; text-align: center; font-family: Arial, Helvetica, sans-serif; font-size: 11px;"><?php echo $lang['EndDate']; ?></th>
                        <th style="padding: 7px; text-align: left; border-left: 1px solid #36752d; border-right: 1px solid #36752d; background-color: #36752d; color: white; text-align: center; font-family: Arial, Helvetica, sans-serif; font-size: 11px;"><?php echo $lang['objPrice%']; ?></th>
                        <th style="padding: 7px; text-align: left; border-left: 1px solid #36752d; border-right: 1px solid #36752d; background-color: #36752d; color: white; text-align: center; font-family: Arial, Helvetica, sans-serif; font-size: 11px; width: 100px;"></th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $loopIndex = 0;
                foreach ($promotions['PLUESQuery'] as $item) {
                    echo '<tr class="promotionRow">';
                    echo '<td style="padding: 7px; border-left: 1px solid #36752d; border-right: 1px solid #36752d; text-align: center; word-wrap: break-word;  font-size: 11px; font-family: Arial, Helvetica, sans-serif;">' . htmlspecialchars($item['PR_FROMDATE']) . '</td>';
                    echo '<td style="padding: 7px; border-left: 1px solid #36752d; border-right: 1px solid #36752d; text-align: center;  word-wrap: break-word; font-size: 11px; font-family: Arial, Helvetica, sans-serif;">' . htmlspecialchars($item['PR_TODATE']) . '</td>';
                    echo '<td style="padding: 7px; border-left: 1px solid #36752d; border-right: 1px solid #36752d; text-align: center; word-wrap: break-word; font-size: 11px; font-family: Arial, Helvetica, sans-serif;">' . number_format($item['PR_PRICE'], 2) . '</td>';
                    echo '<td style="padding: 7px; border-left: 1px solid #36752d; border-right: 1px solid #36752d; text-align: center; word-wrap: break-word; width: 100px; font-family: Arial, Helvetica, sans-serif;">';
                    if (($isCentralDb === '1' && $isOperatorValidated === '1') || ($isCentralDb === '0' && $isOperatorValidated === '1')) {
                        echo '<span style="display: flex; align-items: center; justify-content: center;">';
                        echo '<button class="deleteButton" style="background: none; border: none; cursor: pointer; margin-right: 10px;" onclick="deletePromotion(' . htmlspecialchars($item['PR_ID']) . ')">';
                        echo '<img src="images/delete.png" alt="Delete" style="cursor: pointer; width: 18px; height: 18px;"></button>';
                        echo '<button style="background: none; border: none; cursor: pointer; margin-left: 10px;" onclick="toggleDetails(' . htmlspecialchars($item['PR_ID']) . ')">';
                        echo '<img src="images/open.png" alt="More" style="cursor: pointer; width: 18px; height: 18px;"></button>';
                        echo '</span>';
                    } else {
                        echo '<span style="display: flex; align-items: center; justify-content: center;">';
                        echo '<button style="background: none; border: none; cursor: pointer; margin-left: 10px;" onclick="toggleDetails(' . htmlspecialchars($item['PR_ID']) . ')">';
                        echo '<img src="images/open.png" alt="More" style="cursor: pointer; width: 18px; height: 18px;"></button>';
                        echo '</span>';
                    }
                    echo '</td>';
                    echo '</tr>';

                    echo '<tr id="details-' . htmlspecialchars($item['PR_ID']) . '" class="detailsRow" style="display: none;">';
                    echo '<td colspan="4" style="padding: 12px; border-left: 1px solid #36752d; border-right: 1px solid #36752d;">';
                    echo '<table class="tbItems" style="width: 100%;">';
                    echo '<thead>';
                    echo '<tr>';
                    echo '<th style="padding: 2px; text-align: center; border-left: 1px solid #36752d; border-right: 1px solid #36752d; background-color: #36752d; color: white; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">' . $lang['fromTime'] . '</th>';
                    echo '<th style="padding: 2px; text-align: center; border-left: 1px solid #36752d; border-right: 1px solid #36752d; background-color: #36752d; color: white; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">' . $lang['toTime'] . '</th>';
                    echo '<th style="padding: 2px; text-align: center; border-left: 1px solid #36752d; border-right: 1px solid #36752d; background-color: #36752d; color: white; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">' . $lang['promotionalPrio'] . '</th>';
                    echo '</tr>';
                    echo '</thead>';
                    echo '<tbody>';
                    echo '<tr>';
                    echo '<td style="padding: 2px; border-left: 1px solid #36752d; border-right: 1px solid #36752d; text-align: center; background-color: white; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">' . htmlspecialchars($item['PR_FROMTIME']) . '</td>';
                    echo '<td style="padding: 2px; border-left: 1px solid #36752d; border-right: 1px solid #36752d; text-align: center; background-color: white; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">' . htmlspecialchars($item['PR_TOTIME']) . '</td>';
                    echo '<td style="padding: 2px; border-left: 1px solid #36752d; border-right: 1px solid #36752d; text-align: center; background-color: white; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">' . htmlspecialchars($item['PR_PRIORITY']) . '</td>';
                    echo '</tr>';
                    echo '</tbody>';
                    echo '</table>';
                    echo '</td>';
                    echo '</tr>';
                    $loopIndex++;
                }
                ?>
                </tbody>
            </table>
            <script>
                function toggleDetails(id) {
                    const detailsRow = document.getElementById('details-' + id);
                    const img = detailsRow.previousElementSibling.querySelector('img[alt="More"]');

                    if (detailsRow.style.display === 'none' || detailsRow.style.display === '') {
                        detailsRow.style.display = 'table-row';
                        img.src = 'images/collapse.png';
                    } else {
                        detailsRow.style.display = 'none';
                        img.src = 'images/open.png';
                    }
                }
            </script>
            <p style="text-align: center;">
                <a id="newPromotionsLink" class="medium color-1 button" style="display: <?php echo ($isOperatorValidated === '1' || $isCentralDb === '1') ? 'block' : 'none'; ?>; width: 100%; margin-top:10px; "><?php echo $lang['btnNewPromotion'];?></a>
                <a href="ItemDetails.php?pluNumb=<?php echo urlencode($pluNumb); ?>&pluName=<?php echo urlencode($pluName); ?>&sellPrice=<?php echo urlencode($sellPrice); ?>&pluBuyPrice=<?php echo urlencode($pluBuyPrice); ?>&groupId=<?php echo urlencode($groupId); ?>&pluTaxgroupId=<?php echo urlencode($pluTaxgroupId); ?>&pluEcrName=<?php echo urlencode($pluEcrName); ?>&taxGroupDescr=<?php echo urlencode($taxGroupDescr); ?>&pGrpName=<?php echo urlencode($pGrpName); ?>&search=<?php echo urlencode($searchQuery); ?>&filter=<?php echo urlencode($searchFilter); ?>&page=<?php echo urlencode($page); ?>&isOperatorValidated=<?php echo urlencode($isOperatorValidated);?>&isCentralDb=<?php echo urlencode($isCentralDb);?>&pluLocalPrice=<?php echo urlencode($pluLocalPrice);?>&barcode=<?php echo urlencode($barcode);?>&pluSellDisabled=<?php echo urlencode($pluSellDisabled);?>" class="medium color-5 button" style="width:100%;margin-top:10px;">
                    <?php echo $lang["btnBack2"]; ?>
                </a>
            </p>

            <?php
                } else {
                    echo '<p style="color: red;">Error: ' . htmlspecialchars($promotions['ResultMessage'] ?? 'Unknown error') . '</p>';
                }
            }
        }
        session_write_close();
        ?>
    </div>
</body>
</html>

