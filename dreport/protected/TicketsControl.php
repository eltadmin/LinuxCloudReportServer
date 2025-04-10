<?php
session_start();
if (!isset($_SESSION['s_authenticated']) || empty($_SESSION['s_authenticated'])) {
    header("location:../index.php");
    exit;
}

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
$pluLocalPrice = $_GET['pluLocalPrice'] ?? '0';
$isCentralDb = $_GET['isCentralDb'] ?? '0';
$pluSellDisabled = $_GET['pluSellDisabled'] ?? '0';

$searchQuery = isset($_GET['search']) ? $_GET['search'] : '';
$searchFilter = isset($_GET['filter']) ? $_GET['filter'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$recordsPerPage = 15;

define("C_DEBUG", false);
define("C_BAR_HEIGHT", 20);
define("C_CANVAS_HEIGHT", 30);
define("C_RPTNAME", "storageinfo");
define("C_TIMEOFFSET", 0);
$otimeoffset = C_TIMEOFFSET;
$rname = C_RPTNAME;

$pluNumb = $_GET['pluNumb'] ?? '';

$C_SQL = '{"Id":"DatabaseId","Pass":"1234","PLUESQuery":{"Type":"Query","SQL":"SELECT CASE WHEN PAC_OWNER = \'\' THEN \'POS\' ELSE PAC_OWNER END AS PAC_OWNER, PAC_VALIDFROMD, PAC_VALIDFROMT, PAC_VALIDTOD, PAC_VALIDTOT FROM PAC_ACCESSLIST WHERE ((PAC_VALIDFROMD IS NULL) OR (PAC_VALIDFROMD <= CURRENT_DATE)) AND ((PAC_VALIDTOD IS NULL) OR (PAC_VALIDTOD >= CURRENT_DATE)) AND ((PAC_VALIDFROMT IS NULL) OR (PAC_VALIDFROMT <= CURRENT_TIME)) AND ((PAC_VALIDTOT IS NULL) OR (PAC_VALIDTOT >= CURRENT_TIME)) AND (PAC_DISABLED = 0) ORDER BY 1;"}}';
 
include_once 'language.php';
require_once('class.loading.div.php');
$divLoader = new loadingDiv;
$divLoader->loader();

$objectid = isset($_GET["id"]) ? $_GET["id"] : $_SESSION['s_objectid'];
$_SESSION['s_objectid'] = $objectid;

$deviceid = isset($_SESSION['s_deviceid']) ? $_SESSION['s_deviceid'] : "0";
$rptdate = isset($_GET["date"]) ? $_GET["date"] : '';

include('database.class.php');
$pDatabase = Database::getInstance();
$pDatabase->query("set names 'utf8'");

$qry = $pDatabase->query("SELECT s_customername, s_expiredate FROM t_subscriptions WHERE s_objectid = '" . sql_safe($_SESSION['s_objectid']) . "'");
while ($row = mysqli_fetch_assoc($qry)) {
    $expiredate = strtotime($row['s_expiredate']);
    $customername = $row['s_customername'];
}

$objectpswd = "";
$rptsql = "";
$rname = C_RPTNAME;

$qry = $pDatabase->query("select d_objectpswd,d_timeoffset from t_devices where d_deviceid = '$deviceid' and d_objectid='" . sql_safe($objectid) . "' ;");
while ($row = mysqli_fetch_assoc($qry)) {
    $objectpswd = $row['d_objectpswd'];
    $otimeoffset = $row['d_timeoffset'];
}
if (C_DEBUG) {
    echo "time offset: ";
    var_dump($otimeoffset);
    echo "<br/>";
}

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

$combinedItems = [];
$totalItems = 0;

$url = 'http://' . $_SESSION['s_rpt_server_host'] . ':' . $_SESSION['s_rpt_server_port'] . '/report/' . $rname . '/?id=' . $objectid . '&u=' . $_SESSION['s_rpt_server_user'] . '&p=' . $_SESSION['rpt_server_pswd'];
if (C_DEBUG) {
    echo "url: ";
    var_dump($url);
    echo "<br/>";
}
$obj = json_decode($C_SQL);
$obj->{"Id"} = $objectid;
$obj->{"Pass"} = $objectpswd;
$C_SQL = json_encode($obj);

if (C_DEBUG) {
    echo "content: ";
    var_dump($C_SQL);
    echo "<br/>";
}

$options = [
    'http' => [
        'timeout' => 45,
        'header' => "Content-type: text/xml\r\n",
        'method' => 'GET',
        'content' => $C_SQL
    ]
];
$context = stream_context_create($options);
$str = @file_get_contents($url, false, $context);
if (C_DEBUG) {
    echo "response: ";
    var_dump($str);
    echo "<br/>";
}
$pDatabase->logevent(OPER_COMMAND,$objectid,'report: Storage Availability for plu= '.$pluNumb.' objectid='.$objectid);
if ($str === false) {
    echo "Error fetching data";
} else {
    $response = json_decode($str, true);
}

if ($str !== false) {
    $items = isset($response['PLUESQuery']) ? $response['PLUESQuery'] : [];
    $combinedItems = array_merge($combinedItems, $items);
    $totalItems += count($items);
}

// Pagination logic
$start = ($page - 1) * $recordsPerPage;
$paginatedItems = array_slice($combinedItems, $start, $recordsPerPage);
$totalPages = ceil($totalItems / $recordsPerPage);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=EmulateIE11"/>
    <title>Ticket Control</title>
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

        .color-1 { background-color: #125c5b; }
        .color-3 { background-color: #FBB03B; }

        .color-1:hover, .color-1:active  {
            background-color: #125c5b;
        }
        .color-3:hover, .color-3:active  {
            background-color: #e18e0a;
        }
        .login-help {
            font-size: 11.3px;
        }
        .pagination-buttons {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
        }
        .pagination-buttons .prev-next {
            width: 40%;
        }
        .pagination-buttons .back {
            width: 100%;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        table th, table td {
            padding: 3px;
            font-size: 12px;
            text-align: center;
            word-wrap: break-word; /* This ensures that long words are broken into the next line */
        }
        @media (max-width: 600px) {
            table th, table td {
                padding: 2px;
                font-size: 10px;
            }
            .pagination-buttons .prev-next {
                width: 30%;
            }
            .pagination-buttons .back {
                width: 40%;
            }
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
    <div class="login-card" style="overflow-x: auto;">
        <div>
        <div class="login-help">
            <a href="http://eltrade.com">www.eltrade.com</a> • <a href="http://eltrade.com/bg/contacts"><?php echo $lang['contacts']; ?></a> •
            <?php
            $commonParams = 'pluNumb=' . urlencode($pluNumb) . '&pluName=' . urlencode($pluName) . '&group=' . urlencode($groupId) . '&sellPrice=' . urlencode($sellPrice) . '&promotion=' . urlencode($promotion) . '&barcode=' . urlencode($barcode) . '&pluEcrName=' . urlencode($pluEcrName) . '&pluBuyPrice=' . urlencode($pluBuyPrice) . '&pluTaxgroupId=' . urlencode($pluTaxgroupId) . '&taxGroupDescr=' . urlencode($taxGroupDescr) . '&pGrpName=' . urlencode($pGrpName) . '&search=' . urlencode($searchQuery) . '&filter=' . urlencode($searchFilter) . '&page=' . urlencode($page) . '&isOperatorValidated=' . urlencode($isOperatorValidated) . '&isCentralDb=' . urlencode($isCentralDb) . '&barcode=' . urlencode($barcode) . '&pluLocalPrice=' . urlencode($pluLocalPrice);

            echo '<a>' . htmlspecialchars($customername) . '</a> • ';
            if ($_SESSION['lang'] == 'bg') {
                echo '<a href="ActiveTickets.php?lang=en&' . $commonParams . '"><img src="images/en.png" /></a>';
            } else {
                echo '<a href="ActiveTickets.php?lang=bg&' . $commonParams . '"><img src="images/bg.png" /></a>';
            }
            ?>
        </div>
            <h1 align="center" ><?php echo $_SESSION['s_objectname']; ?></h1>
            <h1><?php echo $lang['objTicketControl']; ?></h1>

            <h4 align="center" >
             <?php
                echo $lang["rptOpenbillsToDate"].date('d.m.Y H:i', time());
             ?>
            </h4>

            <?php if (!empty($paginatedItems)): ?>
               <table border="0" cellpadding="10" cellspacing="0" style="width: 100%; border-collapse: separate; text-align: center;">
                    <thead>
                        <tr style="background-color: #36752d;">
                            <th style="color: white; border-left: 1px solid #36752d; border-right: 1px solid #36752d; font-size: 12px; font-family: Arial, Helvetica, sans-serif;"><?php echo $lang['objOwner']; ?></th> 
                            <th style="color: white; border-left: 1px solid #36752d; border-right: 1px solid #36752d; font-size: 12px; font-family: Arial, Helvetica, sans-serif;"><?php echo $lang['StartDate']; ?></th>
                            <th style="color: white; border-left: 1px solid #36752d; border-right: 1px solid #36752d; font-size: 12px; font-family: Arial, Helvetica, sans-serif;"><?php echo $lang['fromTime']; ?></th>
                            <th style="color: white; border-left: 1px solid #36752d; border-right: 1px solid #36752d; font-size: 12px; font-family: Arial, Helvetica, sans-serif;"><?php echo $lang['EndDate']; ?></th>
                            <th style="color: white; border-left: 1px solid #36752d; border-right: 1px solid #36752d; font-size: 12px; font-family: Arial, Helvetica, sans-serif;"><?php echo $lang['toTime']; ?></th>
                        </tr>
                    </thead>
                    <tbody>
					<?php
					$currentDate = date('d.m.Y');

					foreach ($paginatedItems as $item): ?>
						<tr>
							<td style="border-left: 1px solid #36752d; border-right: 1px solid #36752d; padding: 8px; font-size: 9px; font-family: Arial, Helvetica, sans-serif;">
								<?= isset($item['PAC_OWNER']) ? ($item['PAC_OWNER'] == '30.12.1899' ? '-' : htmlspecialchars($item['PAC_OWNER'])) : '' ?>
							</td>
							<td style="border-left: 1px solid #36752d; border-right: 1px solid #36752d; padding: 8px; font-size: 10px; font-family: Arial, Helvetica, sans-serif;">
								<?= isset($item['PAC_VALIDFROMD']) ? ($item['PAC_VALIDFROMD'] == '30.12.1899' ?  '-' : htmlspecialchars($item['PAC_VALIDFROMD'])) : '' ?>
							</td>
							<td style="border-left: 1px solid #36752d; border-right: 1px solid #36752d; padding: 8px; font-size: 10px; font-family: Arial, Helvetica, sans-serif;">
								<?= isset($item['PAC_VALIDFROMT']) ? ($item['PAC_VALIDFROMT'] == '30.12.1899' ?  '-' : htmlspecialchars($item['PAC_VALIDFROMT'])) : '' ?>
							</td>
							<td style="border-left: 1px solid #36752d; border-right: 1px solid #36752d; padding: 8px; font-size: 10px; font-family: Arial, Helvetica, sans-serif;">
								<?= isset($item['PAC_VALIDTOD']) ? ($item['PAC_VALIDTOD'] == '30.12.1899' ?  '-' : htmlspecialchars($item['PAC_VALIDTOD'])) : '' ?>
							</td>
							<td style="border-left: 1px solid #36752d; border-right: 1px solid #36752d; padding: 8px; font-size: 10px; font-family: Arial, Helvetica, sans-serif;">
								<?= isset($item['PAC_VALIDTOT']) ? ($item['PAC_VALIDTOT'] == '30.12.1899' ?  '-' : htmlspecialchars($item['PAC_VALIDTOT'])) : '' ?>
							</td>
						</tr>
					<?php endforeach; ?>

                    </tbody>
                </table>
            <?php else: ?>
                <p><?php echo $lang['NoItemsFound']; ?></p>
            <?php endif; ?>

<div class="pagination-buttons">
    <?php 
    $prevPage = $page - 1;
    $nextPage = $page + 1;
    $prevUrl = $_SERVER['PHP_SELF'] . '?' . $commonParams . '&page=' . $prevPage;
    $nextUrl = $_SERVER['PHP_SELF'] . '?' . $commonParams . '&page=' . $nextPage;
    ?>
    <a href="<?php echo $prevPage > 0 ? $prevUrl : '#'; ?>" class="medium color-1 button prev-next" <?php echo $prevPage > 0 ? '' : 'onclick="return false;"'; ?>><<</a>
    <a href="rptlist.php" class="medium color-3 button back"><?php echo $lang["btnBack2"]; ?></a>
    <a href="<?php echo $nextPage <= $totalPages ? $nextUrl : '#'; ?>" class="medium color-1 button prev-next" <?php echo $nextPage <= $totalPages ? '' : 'onclick="return false;"'; ?>>>></a>
</div>


        </div>
    </div>
</body>
</html>
