<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=EmulateIE11" />
  <title>Detelina Reports</title>
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
  .color-1 { background-color: #6082B6; }
  .color-5 { background-color: #FBB03B; }
  
  .color-1:hover, .color-1:active  {
    background-color: #395989;  
  }  
  .color-5:hover, .color-5:active  {
    background-color: #e18e0a;  
  }
    .login-help{
    margin-top:-30px;
  }
  .h1{
    font-size: 100px;
  }
</style>
<?php
session_start();
date_default_timezone_set('Europe/Sofia');
if (!isset($_SESSION['s_authenticated']) || empty($_SESSION['s_authenticated'])) {
    header("location:../index.php");
    exit;
}

$pluNumb = $_GET['pluNumb'] ?? '';

define("C_DEBUG", false);
define("C_RPTNAME", "pluessales");
define("C_TIMEOFFSET", 0);

// Retrieve the data from the URL
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

$otimeoffset = C_TIMEOFFSET;
$rname = C_RPTNAME;

$currentDate = new DateTime();
if (isset($_GET['date'])) {
    $currentDate = new DateTime($_GET['date']);
}
$currentDateStr = $currentDate->format('Y-m-d');

// Dates for LastTurnover (from 5 days ago to 1 day ago)
$startDate = isset($_GET['start_date']) ? new DateTime($_GET['start_date']) : (clone $currentDate)->modify('-5 days');
$endDate = isset($_GET['end_date']) ? new DateTime($_GET['end_date']) : (clone $currentDate)->modify('-1 day');

// Convert dates to string for SQL queries
$startDateStr = $startDate->format('Y-m-d 00:00:00');
$endDateStr = $endDate->format('Y-m-d 23:59:59');

// Initialize $rptstartdate and $rptenddate
$rptstartdate = $startDateStr;
$rptenddate = $endDateStr;

$todayDateStr = (new DateTime())->format('Y-m-d');

$rptdate = isset($_GET['date']) ? $_GET['date'] : '';

$C_SQL = '{"Id":"DatabaseId","Pass":"1234",';
$C_SQL .= '"LastTurnover":{"Type":"Query","SQL":"SELECT CAST(S.SPLU_DATETIME AS DATE) AS SELL_DATE, SUM(ROUND(S.SPLU_SELLPRICE * S.SPLU_CURRCOURCE * S.SPLU_SOLDQUANT, 2) + ROUND(S.SPLU_SOLDQUANT * S.SPLU_SELLPRICE * S.SPLU_CURRCOURCE * S.SPLU_SELLDISCOUNT / 100, 2)) AS SELL_SUM FROM SALES_PLUES S  WHERE S.SPLU_DATETIME BETWEEN \'' . $rptstartdate . '\' AND \'' . $rptenddate . '\' AND S.SPLU_PLUNUMB = \'' . $pluNumb . '\' AND S.SPLU_REVOKED_ = 0 GROUP BY 1 ORDER BY 1 ASC"},';
$C_SQL .= '"CurrentTurnover":{"Type":"Query","SQL":"SELECT CAST(S.SPLU_DATETIME AS DATE) AS SELL_DATE, SUM(ROUND(S.SPLU_SELLPRICE * S.SPLU_CURRCOURCE * S.SPLU_SOLDQUANT, 2) + ROUND(S.SPLU_SOLDQUANT * S.SPLU_SELLPRICE * S.SPLU_CURRCOURCE * S.SPLU_SELLDISCOUNT / 100, 2)) AS SELL_SUM, COUNT(*) AS SELL_COUNT, M.PRIMARYMUNIT_NAME, C.CURR_SIGN FROM SALES_PLUES S LEFT JOIN PLUES P ON S.SPLU_PLUNUMB = P.PLU_NUMB LEFT JOIN N_PRIMARY_MEASUREUNITS M ON P.PLU_MEASUREUNIT_ID = M.PRIMARYMUNIT_ID LEFT JOIN N_CURRENCY C ON S.SPLU_SELLCURRENCY = C.CURR_ID WHERE S.SPLU_DATETIME >= \'' . $currentDateStr . ' 00:00:00\' AND S.SPLU_DATETIME < \'' . $currentDateStr . ' 23:59:59\' AND S.SPLU_PLUNUMB = \'' . $pluNumb . '\'  AND S.SPLU_REVOKED_ = 0 GROUP BY 1, 4, 5 ORDER BY 1 DESC"},';
$C_SQL .= '"AverageTurnover":{"Type":"Query","SQL":"SELECT AVG(SELL_SUM) AS AVG_SELL_SUM FROM (SELECT CAST(S.SPLU_DATETIME AS DATE) AS SELL_DATE, SUM(S.SPLU_SELLPRICE * S.SPLU_SOLDQUANT) AS SELL_SUM FROM SALES_PLUES S WHERE S.SPLU_DATETIME BETWEEN \'' . $rptstartdate . '\' AND \'' . $rptenddate . '\' AND S.SPLU_PLUNUMB = \'' . $pluNumb . '\' AND S.SPLU_REVOKED_ = 0 GROUP BY 1)"}}';

include_once 'language.php';
// show loader for slow connections
require_once('class.loading.div.php');
$divLoader = new loadingDiv;
$divLoader->loader();

$objectid = $_GET["id"] ?? $_SESSION['s_objectid'];
$_SESSION['s_objectid'] = $objectid;
$deviceid = $_SESSION['s_deviceid'] ?? "0";

include('database.class.php');
$pDatabase = Database::getInstance();
$result = $pDatabase->query("set names 'utf8'");

// Check expire date
$qry = $pDatabase->query("SELECT s_customername, s_expiredate FROM t_subscriptions WHERE s_objectid = '" . sql_safe($_SESSION['s_objectid']) . "'");
while ($row = mysqli_fetch_assoc($qry)) {
    $expiredate = strtotime($row['s_expiredate']);
    $customername = $row['s_customername'];
}

if ($expiredate >= time()) {
    $objectpswd = "";
    $rptsql = "";
    $rname = "";
    $rptsql = $C_SQL;
    $rname = C_RPTNAME;

    $qry = $pDatabase->query("SELECT d_objectpswd, d_timeoffset FROM t_devices WHERE d_deviceid = '$deviceid' AND d_objectid='" . sql_safe($objectid) . "';");
    while ($row = mysqli_fetch_assoc($qry)) {
        $objectpswd = $row['d_objectpswd'];
        $otimeoffset = $row['d_timeoffset'];
    }

    // Replace timeoffset
    $rptsql = str_replace('TIMEOFFSET', $otimeoffset, $rptsql);

    // Replace parameters in SQL
    $rptsql = str_replace('START_DATE', '\'' . $rptstartdate . '\'', $rptsql);
    $rptsql = str_replace('END_DATE', '\'' . $rptenddate . '\'', $rptsql);
    if (C_DEBUG) {
        echo "report date: ";
        var_dump($rptdate);
        echo "<br/>";
    }

    
    $pDatabase->logevent(OPER_COMMAND,$objectid,'report: Plu monthly sales for plu= '.$pluNumb.' objectid='.$objectid);

    $url = 'http://' . $_SESSION['s_rpt_server_host'] . ':' . $_SESSION['s_rpt_server_port'] . '/report/' . $rname . '/?id=' . $objectid . '&u=' . $_SESSION['s_rpt_server_user'] . '&p=' . $_SESSION['rpt_server_pswd'];
    if (C_DEBUG) {
        echo "url: ";
        var_dump($url);
        echo "<br/>";
    }
    // set ObjectId and password
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
            'timeout' => 45, //timeout in seconds *2, 10 means 20 sec.
            'header' => "Content-type: text/xml\r\n",
            'method' => 'GET',
            'content' => $rptsql
        ),
    );
    $context = stream_context_create($options);
    $str = @file_get_contents($url, false, $context);
    if (C_DEBUG) {
        echo "response: ";
        var_dump($str);
        echo "<br/>";
    }

    if (!$str) {
        $pDatabase->show_alert(ALERT_ERROR, $lang["AlertError"], $lang["rptNoReceivedData"], false, false);
        $pDatabase->logevent(OPER_ERROR, $objectid, 'report: ' . $rname . ' error: ' . $lang["rptNoReceivedData"]);
    } else {
        $rptdata = @json_decode($str, false);
        if (C_DEBUG) {
            echo "rptdata: ";
            var_dump($rptdata);
            echo "<br/>";
        }
        if ($rptdata == null && json_last_error() !== JSON_ERROR_NONE) {
            $pDatabase->show_alert(ALERT_ERROR, $lang["AlertError"], $lang["rptInvalidData"], false, false);
            $pDatabase->logevent(OPER_ERROR, $objectid, 'report: ' . $rname . ' error: ' . $lang["rptInvalidData"]);
        } else {
            $lastTurnoverLabels = [];
            $lastTurnoverData = [];
            $currentTurnover = "";
            $currencySign = '';
            $avgTurnover = 0;
            $itemsCount = 0;

            // Generate the full range of dates between startDate and endDate
            $interval = new DateInterval('P1D');
            $period = new DatePeriod($startDate, $interval, (clone $endDate)->modify('+1 day')); // Include the end date

            $fullDateRange = [];
            foreach ($period as $date) {
                $fullDateRange[$date->format('d.m.Y')] = 0; // Initialize with 0 sales
            }

            // Populate sales data into the full date range
            if (isset($rptdata->LastTurnover) && is_array($rptdata->LastTurnover)) {
                foreach ($rptdata->LastTurnover as $item) {
                    $ymd = DateTime::createFromFormat('d.m.Y', $item->SELL_DATE);
                    if ($ymd !== false) {
                        $formattedDate = $ymd->format('d.m.Y');
                        $fullDateRange[$formattedDate] = number_format((float)$item->SELL_SUM, 2, '.', '');
                        $avgTurnover += (float)$item->SELL_SUM;
                        $itemsCount++;
                    }
                }
            }

            // Prepare the data for JavaScript
            $lastTurnoverLabels = json_encode(array_keys($fullDateRange));
            $lastTurnoverData = json_encode(array_values($fullDateRange));

            $averageTurnoverValue = 0;
            if (isset($rptdata->AverageTurnover)) {
                foreach ($rptdata->AverageTurnover as $item) {
                    $averageTurnoverValue = number_format((float)$item->AVG_SELL_SUM, 2, '.', '');
                }
            }
        }
    }
}
?>

<script>
    var lastTurnoverLabels = <?php echo $lastTurnoverLabels; ?>;
    var lastTurnoverData = <?php echo $lastTurnoverData; ?>;
    var averageTurnoverValue = <?php echo $averageTurnoverValue; ?>;

    var averageTurnoverData = [];
    for (var i = 0; i < lastTurnoverLabels.length; i++) {
        averageTurnoverData.push(averageTurnoverValue);
    }

    var config = {
        type: 'line',
        data: {
            labels: lastTurnoverLabels,
            datasets: [{
                label: '<?php echo $lang["rptRevenueChartLabel"]; ?>',
                data: lastTurnoverData,
                borderColor : "rgba(151,187,205,1)",
                backgroundColor : "rgba(151,187,205,0.2)",
                pointBorderColor : "#fff",
                pointBackgroundColor : "rgba(151,187,205,1)",
                pointStrokeColor : "#ff6c23",
                pointHoverBackgroundColor : "#fff",
                pointHoverBorderColor : "rgba(151,187,205,1)",
                pointHighlightStroke: "#ff6c23",
                hitRadius : 30
            },{
                label: '<?php echo $lang["rptRevenueAvgTurnoverLabel"]; ?>',
                data: averageTurnoverData,
                fill: false,
                borderDash: [10, 5],
                pointStyle: "dash",
                borderColor : "rgba(255, 99, 132,1)",
                borderWidth : 1,
                hitRadius : 30,
            }]
        },
        options: {
            responsive: true,
            legend: {
                display: false,
            },
            hover: {
                mode: 'dataset'
            }
        }
    };

    window.onload = function() {
        var ctx = document.getElementById("canvas").getContext("2d");
        window.myLine = new Chart(ctx, config);
    };
</script>

</head>
<body>
   <?php
        if ($expiredate >= time()){
            $nextStartDate = clone $startDate;
            $nextEndDate = clone $endDate;
            $nextStartDate->modify('+5 days');
            $nextEndDate->modify('+5 days');
            $prevStartDate = clone $startDate;
            $prevEndDate = clone $endDate;
            $prevStartDate->modify('-5 days');
            $prevEndDate->modify('-5 days');
        }
  ?>
  <div class="login-card">
    <div class="login-help">
      <?php
       if ($expiredate < time()) {
         $pDatabase->show_alert(ALERT_WARNING, $lang["AlertWarning"], $lang["errObjectExpired"] . date("d.m.Y", $expiredate), false, false);
         echo '<div><center><a href="rptlist.php" class="medium color-5 button">' . $lang["btnExit"] . '</a></center></div>';
         $pDatabase->logevent(OPER_ERROR, $objectid, 'report: ' . $rname . ' error: ' . $lang["errObjectExpired"] . date("d.m.Y", $expiredate));
         exit;
       }
       
        echo '<a href="http://eltrade.com">www.eltrade.com</a> • <a href="http://eltrade.com/bg/contacts">' . $lang['contacts'] . '</a> •';
 
        if ($_SESSION['lang'] == 'bg') {
            echo '&nbsp;&nbsp;<a href="plueDailySales.php?lang=en&pluNumb=' . urlencode($pluNumb) . '&pluName=' . urlencode($pluName) . '&sellPrice=' . urlencode($sellPrice) . '&pluBuyPrice=' . urlencode($pluBuyPrice) . '&groupId=' . urlencode($groupId) . '&pluTaxgroupId=' . urlencode($pluTaxgroupId) . '&pluEcrName=' . urlencode($pluEcrName) . '&taxGroupDescr=' . urlencode($taxGroupDescr) . '&pGrpName=' . urlencode($pGrpName) . '&search=' . urlencode($searchQuery) . '&filter=' . urlencode($searchFilter) . '&page=' . urlencode($page). '&isOperatorValidated=' . urlencode($isOperatorValidated) . '&isCentralDb=' . urlencode($isCentralDb) . '&barcode=' . urlencode($barcode) . '&pluLocalPrice=' . urlencode($pluLocalPrice) . '&pluSellDisabled=' . urlencode($pluSellDisabled). '&start_date=' . urlencode($startDate->format('Y-m-d')) . '&end_date=' . urlencode($endDate->format('Y-m-d')) . '"><img src="images/en.png" /></a>';
        } else {
            echo '&nbsp;&nbsp;<a href="plueDailySales.php?lang=bg&pluNumb=' . urlencode($pluNumb) . '&pluName=' . urlencode($pluName) . '&sellPrice=' . urlencode($sellPrice) . '&pluBuyPrice=' . urlencode($pluBuyPrice) . '&groupId=' . urlencode($groupId) . '&pluTaxgroupId=' . urlencode($pluTaxgroupId) . '&pluEcrName=' . urlencode($pluEcrName) . '&taxGroupDescr=' . urlencode($taxGroupDescr) . '&pGrpName=' . urlencode($pGrpName) . '&search=' . urlencode($searchQuery) . '&filter=' . urlencode($searchFilter) . '&page=' . urlencode($page). '&isOperatorValidated=' . urlencode($isOperatorValidated) . '&isCentralDb=' . urlencode($isCentralDb) . '&barcode=' . urlencode($barcode) . '&pluLocalPrice=' . urlencode($pluLocalPrice) . '&pluSellDisabled=' . urlencode($pluSellDisabled). '&start_date=' . urlencode($startDate->format('Y-m-d')) . '&end_date=' . urlencode($endDate->format('Y-m-d')) . '"><img src="images/bg.png" /></a>';
        }
       echo '&nbsp;&nbsp;<a href="javascript:history.go(0)"><img src="images/refresh.png" alt="refresh"></a>';
      ?>
    </div>
    <h1 align="center"style="font-size:25px"> <?php echo $_SESSION['s_objectname']; ?> </h1>
    <h1 align="center"style="font-size:20px">
    <?php
        echo $lang["rptDailyRevenue"] . ' - ' . $pluName ;
    ?>
    </h1>

    <?php
    // Initialize the variables with default values
    $totalSalesSum = '0.00';
    $currencySign = '';
    $totalSalesCount = 0;
    $measureUnitName = '';

    if (isset($rptdata->CurrentTurnover) && is_array($rptdata->CurrentTurnover)) {
        foreach ($rptdata->CurrentTurnover as $item) {
            $totalSalesCount = $item->SELL_COUNT;
            $totalSalesSum = number_format((float)$item->SELL_SUM, 2, '.', '');
            $measureUnitName = $item->PRIMARYMUNIT_NAME;
            $currencySign = isset($item->CURR_SIGN) ? $item->CURR_SIGN : '';
        }
    }
    ?>
    <p class="val_money">
        <?php
            echo $totalSalesSum . ' ' . $currencySign;
        ?>
    </p>

    <div class="datagrid">
    <table align="center">
    <tbody>
        <?php
          echo '<tr><td>' . $lang["rptQuantity"] . '</td><td class="price">'. ' ' . $totalSalesCount . ' ' . $measureUnitName. '</td></tr>';
         ?>
    </tbody>
    </table>
    </div>

    <h4 align="center">
    <?php
      echo $lang["rptRevenueToDate"] . date('d.m.Y H:i', time());
     ?>
    </h4>

    <br>
    <div class="chart">
        <div>
            <canvas id="canvas"></canvas>
        </div>
    </div>
    <h4 align="center"><?php echo $lang['forPeriod'] . ' ' . date('d.m.Y', strtotime($rptstartdate)) . ' - ' . date('d.m.Y', strtotime($rptenddate)); ?></h4>

    <br>
    <table style="width:100%; line-height:22px;">
      <tr>
           <?php
            if ($expiredate >= time()){
                $prevStartDate = clone $startDate;
                $prevEndDate = clone $endDate;
                $prevStartDate->modify('-5 days');
                $prevEndDate->modify('-5 days');
                echo '<td align="center"><a href="plueDailySales.php?pluNumb=' . urlencode($pluNumb) . '&pluName=' . urlencode($pluName) . '&sellPrice=' . urlencode($sellPrice) . '&pluBuyPrice=' . urlencode($pluBuyPrice) . '&groupId=' . urlencode($groupId) . '&pluTaxgroupId=' . urlencode($pluTaxgroupId) . '&pluEcrName=' . urlencode($pluEcrName) . '&taxGroupDescr=' . urlencode($taxGroupDescr) . '&pGrpName=' . urlencode($pGrpName) . '&search=' . urlencode($searchQuery) . '&filter=' . urlencode($searchFilter) . '&page=' . urlencode($page) .'&isOperatorValidated=' . urlencode($isOperatorValidated) . '&isCentralDb=' . urlencode($isCentralDb) . '&barcode=' . urlencode($barcode) . '&pluLocalPrice=' . urlencode($pluLocalPrice) . '&pluSellDisabled=' . urlencode($pluSellDisabled) . '&start_date=' . $prevStartDate->format('Y-m-d') . '&end_date=' . $prevEndDate->format('Y-m-d') . '" class="medium color-1 button"><<</a></td>';
            } else {
                echo '<td align="center"><a href="#" onclick="show_exp()" class="medium color-1 button"><<</a></td>';
            }
           ?>

        <td align="center" style="width:60%;">
                        <a href="ItemDetails.php?pluNumb=<?php echo urlencode($pluNumb); ?>&pluName=<?php echo urlencode($pluName); ?>&sellPrice=<?php echo urlencode($sellPrice); ?>&pluBuyPrice=<?php echo urlencode($pluBuyPrice); ?>&groupId=<?php echo urlencode($groupId); ?>&pluTaxgroupId=<?php echo urlencode($pluTaxgroupId); ?>&pluEcrName=<?php echo urlencode($pluEcrName); ?>&taxGroupDescr=<?php echo urlencode($taxGroupDescr); ?>&pGrpName=<?php echo urlencode($pGrpName); ?>&search=<?php echo urlencode($searchQuery); ?>&filter=<?php echo urlencode($searchFilter); ?>&page=<?php echo urlencode($page); ?>&isOperatorValidated=<?php echo urlencode($isOperatorValidated);?>&isCentralDb=<?php echo urlencode($isCentralDb);?>&pluLocalPrice=<?php echo urlencode($pluLocalPrice);?>&barcode=<?php echo urlencode($barcode);?> &pluSellDisabled=<?php echo urlencode($pluSellDisabled);?>" class="medium color-5 button" style="width:95%;"><?php echo $lang["btnBack2"]; ?></a>
            </td>

           <?php
            if ($expiredate >= time()){
                $nextStartDate = clone $startDate;
                $nextEndDate = clone $endDate;
                $nextStartDate->modify('+5 days');
                $nextEndDate->modify('+5 days');
                echo '<td align="center"><a href="plueDailySales.php?pluNumb=' . urlencode($pluNumb) . '&pluName=' . urlencode($pluName) . '&sellPrice=' . urlencode($sellPrice) . '&pluBuyPrice=' . urlencode($pluBuyPrice) . '&groupId=' . urlencode($groupId) . '&pluTaxgroupId=' . urlencode($pluTaxgroupId) . '&pluEcrName=' . urlencode($pluEcrName) . '&taxGroupDescr=' . urlencode($taxGroupDescr) . '&pGrpName=' . urlencode($pGrpName) . '&search=' . urlencode($searchQuery) . '&filter=' . urlencode($searchFilter) . '&page=' . urlencode($page). '&isOperatorValidated=' . urlencode($isOperatorValidated) . '&isCentralDb=' . urlencode($isCentralDb) . '&barcode=' . urlencode($barcode) . '&pluLocalPrice=' . urlencode($pluLocalPrice) . '&pluSellDisabled=' . urlencode($pluSellDisabled) .  '&start_date=' . $nextStartDate->format('Y-m-d') . '&end_date=' . $nextEndDate->format('Y-m-d') . '" class="medium color-1 button">>></a></td>';
            } else {
                echo '<td align="center"><a href="#" onclick="show_exp()" class="medium color-1 button">>></a></td>';
            }
           ?>
      </tr>
    </table>
  </div>

 <script>
   function show_exp() {
       alert('<?php echo $lang["errObjectExpired"].date("d.m.Y", $expiredate); ?>');
   }
</script>

</body>
</html>
