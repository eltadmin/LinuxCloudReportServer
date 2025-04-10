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
</style>

<?php
    session_start();
    if(!isset($_SESSION['s_authenticated']) || empty($_SESSION['s_authenticated'])){
        header("location:../index.php");
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
    $isCentralDb = $_GET['isCentralDb'] ?? '0';
    $pluLocalPrice = $_GET['pluLocalPrice'] ?? '0';
    $pluSellDisabled = $_GET['pluSellDisabled'] ?? '0';

    $searchQuery = isset($_GET['search']) ? $_GET['search'] : '';
    $searchFilter = isset($_GET['filter']) ? $_GET['filter'] : '';
    $page = isset($_GET['page']) ? $_GET['page'] : 1;

	$C_SQL  = '{"Id":"DatabaseId","Pass":"1234","CurrentTurnover":{"Type":"Query","SQL":"SELECT SUM(RoundTo((S.SPLU_SELLPRICE * S.SPLU_CURRCOURCE * S.SPLU_SOLDQUANT), 2) + RoundTo((S.SPLU_SOLDQUANT * S.SPLU_SELLPRICE * S.SPLU_CURRCOURCE * S.SPLU_SELLDISCOUNT / 100), 2)) AS \\"SELL_SUM\\", COUNT(*) AS \\"SELL_COUNT\\", M.PRIMARYMUNIT_NAME, C.CURR_SIGN FROM SALES_PLUES S LEFT JOIN PLUES P ON S.SPLU_PLUNUMB = P.PLU_NUMB LEFT JOIN N_PRIMARY_MEASUREUNITS M ON P.PLU_MEASUREUNIT_ID = M.PRIMARYMUNIT_ID LEFT JOIN N_CURRENCY C ON S.SPLU_SELLCURRENCY = C.CURR_ID WHERE S.SPLU_DATETIME BETWEEN PAYTYPE_DT_START AND END_DATE AND S.SPLU_PLUNUMB = \'' . $pluNumb . '\'  AND S.SPLU_REVOKED_ = 0 GROUP BY 3, 4"},';
	$C_SQL .= '"LastTurnover":{"Type":"Query","SQL":"SELECT EXTRACT(MONTH FROM S.SPLU_DATETIME) AS \\"SELL_DATE_MONTH\\", EXTRACT(YEAR FROM S.SPLU_DATETIME) AS \\"SELL_DATE_YEAR\\", SUM(RoundTo((S.SPLU_SELLPRICE * S.SPLU_CURRCOURCE * S.SPLU_SOLDQUANT), 2) + RoundTo((S.SPLU_SOLDQUANT * S.SPLU_SELLPRICE * S.SPLU_CURRCOURCE * S.SPLU_SELLDISCOUNT / 100), 2)) AS \\"SELL_SUM\\", SUM(S.SPLU_SOLDQUANT) AS \\"SELL_QUANT\\", COUNT(S.SPLU_PLUNUMB) AS \\"SELL_COUNT\\", M.PRIMARYMUNIT_NAME, C.CURR_SIGN FROM SALES_PLUES S LEFT JOIN PLUES P ON S.SPLU_PLUNUMB = P.PLU_NUMB LEFT JOIN N_PRIMARY_MEASUREUNITS M ON P.PLU_MEASUREUNIT_ID = M.PRIMARYMUNIT_ID LEFT JOIN N_CURRENCY C ON S.SPLU_SELLCURRENCY = C.CURR_ID WHERE S.SPLU_DATETIME BETWEEN START_DATE AND END_DATE AND S.SPLU_PLUNUMB = \'' . $pluNumb . '\'  AND S.SPLU_REVOKED_ = 0 GROUP BY 1, 2, 6, 7 ORDER BY 2, 1 ASC"}}';
    
    //define("C_DEBUG", false);
    define("C_RPTNAME", "monthturnover");
    define("C_TIMEOFFSET", 0);
    
    $otimeoffset = C_TIMEOFFSET;
    $rname = C_RPTNAME;   
        
    include_once 'language.php';
    // show loader for slow connections
    require_once ('class.loading.div.php');
    $divLoader = new loadingDiv;
    $divLoader->loader();
    
    if(isset($_GET["debug"])){
        define("C_DEBUG", true);
    }
    else{
        define("C_DEBUG", false);
    }

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
    // get pluturnover date parma for sql
    $rptdate = "";
    if(isset($_GET["date"])){
     $rptdate = $_GET["date"];
    }   

    include('database.class.php');
    $pDatabase = Database::getInstance();
    $result = $pDatabase->query("set names 'utf8'");

    //check expire date
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
        
/*        
        $qry = $pDatabase->query("select r_sql_".sql_safe($_SESSION['lang']).", r_name from t_reports where r_name='dayturnover'");
        while ($row was mysql_fetch_assoc($qry)) {
         $rptsql = $row['r_sql_'.$_SESSION['lang']];
         $rname = $row['r_name'];
        }
*/

//        $qry = $pDatabase->query("select d_objectpswd from t_devices where d_objectid='".sql_safe($objectid)."';");
        $qry = $pDatabase->query("select d_objectpswd,d_timeoffset from t_devices where d_deviceid = '$deviceid' and d_objectid='".sql_safe($objectid)."' ;");

        while ($row = mysqli_fetch_assoc($qry)) {
         $objectpswd = $row['d_objectpswd'];
         $otimeoffset = $row['d_timeoffset'];
        }
        if (C_DEBUG) {echo "Time offset: "; var_dump($otimeoffset); echo "<br/>";}

        // set data parameters
if ($rptdate == ''){ 
    $dt = new DateTime();
    $rptdate = $dt->format('Y-m-d');
} else {
    $dt = new DateTime($rptdate);
}

if (C_DEBUG) {echo "Report date: "; var_dump($rptdate); echo "<br/>";}        
$dt->setTime( 0, 0, 0 );        

$rptPaymentTypeStartDate = $dt->format('Y-m-01');
if (C_DEBUG) {echo "PayType StartDate: ".$rptPaymentTypeStartDate; echo "<br/>";}

$rptstartdate = new DateTime($dt->format('Y-m-01'));
$rptstartdate = $rptstartdate->modify('-3 month')->format('Y-m-d');
if (C_DEBUG) {echo "StartDate: ".$rptstartdate; echo "<br/>";}

// Set end date to the last day of the month
$rptenddate = new DateTime($dt->format('Y-m-t'));
$rptenddate = $rptenddate->format('Y-m-d');
if (C_DEBUG) {echo "EndDate: ".$rptenddate; echo "<br/>";}

//replace timeoffset
$rptsql = str_replace('TIMEOFFSET',$otimeoffset,$rptsql);       
//replace parameters in SQL 
$rptsql = str_replace('START_DATE','\''.$rptstartdate.'\'',$rptsql);
$rptsql = str_replace('PAYTYPE_DT_START','\''.$rptPaymentTypeStartDate.'\'',$rptsql);
$rptsql = str_replace('END_DATE','\''.$rptenddate.'\'',$rptsql);

                  
       
        $pDatabase->logevent(OPER_COMMAND,$objectid,'report: Storage Availability for plu= '.$pluNumb.' objectid='.$objectid);
                
        $url = 'http://'.$_SESSION['s_rpt_server_host'].':'.$_SESSION['s_rpt_server_port'].'/report/'.$rname.'/?id='.$objectid.'&u='.$_SESSION['s_rpt_server_user'].'&p='.$_SESSION['rpt_server_pswd'];
        if (C_DEBUG) {echo "url: "; var_dump($url); echo "<br/>";}
        // set ObjectId and password
        $obj = json_decode($rptsql);
        $obj->Id = $objectid;
        $obj->Pass = $objectpswd;
        $rptsql = json_encode($obj);
        if (C_DEBUG) {echo "content: "; var_dump($rptsql); echo "<br/>";}

        $options = array(
            'http' => array(
            'timeout' => 45, //timeout in seconds *2, 10 means 20 sec.
            'header'  => "Content-type: text/xml\r\n",
            'method'  => 'GET',
            'content' => $rptsql
            ),
         );
         $context  = stream_context_create($options);
         $str = @file_get_contents($url, false, $context);
         if (C_DEBUG) {echo "response: "; var_dump($str); echo "<br/>";}

        // inititialize default error values
        $CurrentTurnover = "";
        $LastTurnoverLabel = '""';
        $LastTurnoverData = '"0"';
        $CurrencySign = '';
        $AvgTurnover = 0;
        $TotalTurnover = 0;
        $ItemsCount = 0;
        $AvgTurnoverData = "";
        //$str = @file_get_contents($url);
        if (!$str) {
          //show error message
          $pDatabase->show_alert(ALERT_ERROR,$lang["AlertError"],$lang["rptNoReceivedData"],false,false);
          $pDatabase->logevent(OPER_ERROR,$objectid,'report: '.$rname.' error: '.$lang["rptNoReceivedData"]);
        } else {
            $rptdata = @json_decode($str, false);
            
            if (C_DEBUG) {echo "rptdata: ";  var_dump($rptdata); echo "<br/>";}
            if ($rptdata == null && json_last_error() !== JSON_ERROR_NONE) {
              $pDatabase->show_alert(ALERT_ERROR,$lang["AlertError"],$lang["rptInvalidData"],false,false);
              $pDatabase->logevent(OPER_ERROR,$objectid,'report: '.$rname.' error: '.$lang["rptInvalidData"]);

            } else {
              //continue only if "ResultCode": 0,
              if ($rptdata->ResultCode == 0) {
                  $CurrentTurnoverSum = '0.00';
if (isset($rptdata->CurrentTurnover) && is_array($rptdata->CurrentTurnover) && !empty($rptdata->CurrentTurnover)) {
    $CurrentTurnoverSum = number_format((float)$rptdata->CurrentTurnover[0]->SELL_SUM, 2, '.', '');
    $CurrencySign = $rptdata->CurrentTurnover[0]->CURR_SIGN;
    $CurrentTurnoverSum .= ' ' . $CurrencySign;
}

if (is_array($rptdata->LastTurnover) && !empty($rptdata->LastTurnover)) {
    $lastMonth = end($rptdata->LastTurnover);
    $lastMonthDt = new DateTime();
    $lastMonthDt->setDate($lastMonth->SELL_DATE_YEAR, $lastMonth->SELL_DATE_MONTH, 1);
    $TotalTurnover = (float)$lastMonth->SELL_SUM;
} else {
    $TotalTurnover = 0; // or handle the case when LastTurnover is not available
}

$rptDateObj = isset($rptdate) && !empty($rptdate) ? new DateTime($rptdate) : new DateTime();
$startMonthLabel = $rptDateObj->format('M Y');
$startMonthTurnover = '0.00';
$CurrencySign = '';

// Update values if data exists
if (isset($rptdata->LastTurnover) && is_array($rptdata->LastTurnover) && !empty($rptdata->LastTurnover)) {
    // Get the first month from LastTurnover
    $firstMonth = $rptdata->LastTurnover[0];
    $ymd = new DateTime();
    $ymd->setDate($firstMonth->SELL_DATE_YEAR, $firstMonth->SELL_DATE_MONTH, 1);
    $startMonthTurnover = number_format((float)$firstMonth->SELL_SUM, 2, '.', '');
    $CurrencySign = $firstMonth->CURR_SIGN;
    $startMonthLabel = $ymd->format('M Y');
}

$LastTurnoverLabel = '';
$LastTurnoverData = '';
$AvgTurnoverData = '';
$selectedMonthTurnover = '0.00';
$selectedMonthLabel = '';
$selectedMonthCurrency = '';
if (is_array($rptdata->LastTurnover)) {
    foreach ($rptdata->LastTurnover as $item) {
        $ymd = new DateTime();
        $ymd->setDate($item->SELL_DATE_YEAR, $item->SELL_DATE_MONTH, 1);
        $LastTurnoverLabel .= '"' . $ymd->format('M Y') . '",';
        $LastTurnoverData .= number_format((float)$item->SELL_SUM, 2, '.', '') . ',';

        if ($item->SELL_DATE_MONTH == $rptDateObj->format('m') && $item->SELL_DATE_YEAR == $rptDateObj->format('Y')) {
            $selectedMonthTurnover = number_format((float)$item->SELL_SUM, 2, '.', '');
            $selectedMonthLabel = $ymd->format('M Y');
            $selectedMonthCurrency = $item->CURR_SIGN;
        }

        // Calculate average turnover
        $today = new DateTime();
        if ((int)$item->SELL_DATE_MONTH != (int)$today->format('m')) {
            $AvgTurnover = $AvgTurnover + (float)$item->SELL_SUM;
            $ItemsCount++;
        }
    }

    $LastTurnoverLabel = rtrim($LastTurnoverLabel, ',');
    $LastTurnoverData = rtrim($LastTurnoverData, ',');

    $AvgTurnover = $ItemsCount ? $AvgTurnover / $ItemsCount : 0;
    $AvgTurnoverData = str_repeat(number_format($AvgTurnover, 2, '.', '') . ',', $ItemsCount + 1);
    $AvgTurnoverData = rtrim($AvgTurnoverData, ',');
} else { 
    $TotalTurnover = 0;
    $LastTurnoverLabel = '';
    $LastTurnoverData = '';
    $AvgTurnoverData = '0';
}
                  
                  if (C_DEBUG) {echo "Average turnover: "; var_dump($AvgTurnoverData); echo "<br/>";}
                  if (C_DEBUG) {echo "Item Count: "; var_dump($ItemsCount); echo "<br/>";}
                  
              } else {
                  $pDatabase->show_alert(ALERT_ERROR,$lang["AlertError"],$pDatabase->getTCPErrorMessage($rptdata->ResultCode,$lang),false,false);
                  $pDatabase->logevent(OPER_ERROR,$objectid,'report: '.$rname.' ResultCode: '.$rptdata->ResultCode.' ResultMessage: '.$rptdata->ResultMessage);
              } // end else ResultCode=
            } // end JSON error
        } // end else !$str
 //   } // end check expire date
?>

  <script>
        //var randomScalingFactor = function(){ return Math.round(Math.random()*100)};
        //chart.js 2.x      
        var config = {
            type: 'line',
            data: {
                <?php echo "labels : [" . $LastTurnoverLabel . "],"; ?>                
                datasets: [{
                    label: <?php echo '"'.$lang["rptRevenueChartLabel"].'",'; ?>                    
                    <?php echo  "data : [" . $LastTurnoverData . "],"; ?>                    
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
                    label: <?php echo '"'.$lang["rptRevenueAvgTurnoverLabel"].'",'; ?>
                    fill: false,
                    borderDash: [10, 5],
                    pointStyle: "dash",
                    borderColor : "rgba(255, 99, 132,1)",
                    borderWidth : 1,
                    hitRadius : 30,
                    <?php echo  "data : [" . $AvgTurnoverData . "],"; ?>
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
        
        //
        
   window.onload = function() {
     var ctx = document.getElementById("canvas").getContext("2d");   
     window.myLine = new Chart(ctx, config);
   };

  </script>
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
      //echo 'setTimeout(function(){var element = document.getElementById("alert-box-exp").style.display = "none";},5000);';
      echo 'function show_exp() {document.getElementById("alert-box-exp").removeAttribute("style"); setTimeout(function(){document.getElementById("alert-box-exp").style.display = "none";},5000); }';
      echo '</script>';
     }
 ?>
 
    <?php
     $startDate = isset($_GET['start_date']) ? new DateTime($_GET['start_date']) : new DateTime();
     $endDate = isset($_GET['end_date']) ? new DateTime($_GET['end_date']) : new DateTime();
    ?>
 
  <div class="login-card">
    <div class="login-help">
      <?php
/*
      if ($expiredate < time()){
         $pDatabase->show_alert(ALERT_WARNING,$lang["AlertWarning"],$lang["errObjectExpired"].date("d.m.Y", $expiredate),false,true);
//         echo '<div><center><a href="rptlist.php" class="medium purple button">'.$lang["btnExit"].'</a></center></div>';
//         $pDatabase->logevent(OPER_ERROR,$objectid,'report: '.$rname.' error: '.$lang["errObjectExpired"].date("d.m.Y", $expiredate));
//         exit;
        }
*/      
       //echo '<a>'.$customername.'</a> • <a>'.$lang["ObjectExpireOn"].date("d.m.Y", $expiredate).'</a>';

       //echo '<img src="images/refresh.png" alt="refresh">';
    
        echo '<a href="http://eltrade.com">www.eltrade.com</a> &#8226; <a href="http://eltrade.com/bg/contacts">' . $lang['contacts'] . '</a> &#8226;';
 
    
        if ($_SESSION['lang'] == 'bg') {
            echo '&nbsp;&nbsp;<a href="plueMonthlySales.php?lang=en&pluNumb=' . urlencode($pluNumb) . '&pluName=' . urlencode($pluName) . '&sellPrice=' . urlencode($sellPrice) . '&pluBuyPrice=' . urlencode($pluBuyPrice) . '&groupId=' . urlencode($groupId) . '&pluTaxgroupId=' . urlencode($pluTaxgroupId) . '&pluEcrName=' . urlencode($pluEcrName) . '&taxGroupDescr=' . urlencode($taxGroupDescr) . '&pGrpName=' . urlencode($pGrpName) . '&search=' . urlencode($searchQuery) . '&filter=' . urlencode($searchFilter) . '&page=' . urlencode($page) . '&isOperatorValidated=' . urlencode($isOperatorValidated) . '&isCentralDb=' . urlencode($isCentralDb) . '&barcode=' . urlencode($barcode) . '&pluLocalPrice=' . urlencode($pluLocalPrice). '&pluSellDisabled=' . urlencode($pluSellDisabled). '&start_date=' . urlencode($rptstartdate) . '&end_date=' . urlencode($rptenddate) . '"><img src="images/en.png" /></a>';
        } else {
            echo '&nbsp;&nbsp;<a href="plueMonthlySales.php?lang=bg&pluNumb=' . urlencode($pluNumb) . '&pluName=' . urlencode($pluName) . '&sellPrice=' . urlencode($sellPrice) . '&pluBuyPrice=' . urlencode($pluBuyPrice) . '&groupId=' . urlencode($groupId) . '&pluTaxgroupId=' . urlencode($pluTaxgroupId) . '&pluEcrName=' . urlencode($pluEcrName) . '&taxGroupDescr=' . urlencode($taxGroupDescr) . '&pGrpName=' . urlencode($pGrpName) . '&search=' . urlencode($searchQuery) . '&filter=' . urlencode($searchFilter) . '&page=' . urlencode($page) . '&isOperatorValidated=' . urlencode($isOperatorValidated) . '&isCentralDb=' . urlencode($isCentralDb) . '&barcode=' . urlencode($barcode) . '&pluLocalPrice=' . urlencode($pluLocalPrice). '&pluSellDisabled=' . urlencode($pluSellDisabled). '&start_date=' . urlencode($rptstartdate) . '&end_date=' . urlencode($rptenddate) . '"><img src="images/en.png" /></a>';
        }

       echo '&nbsp;&nbsp;<a href="javascript:history.go(0)"><img src="images/refresh.png" alt="refresh"></a>';

      ?>

    </div>
    <h1 align="center"style="font-size:25px"> <?php  echo $_SESSION['s_objectname']; ?> </h1>
<h1 align="center"style="font-size:20px">
    <?php 
        echo $lang["rptMonthRevenue"]. ' - ' . $pluName;
        
    ?>
</h1>
<h1 align="center">
    <?php 
        echo ' ' . $selectedMonthLabel;
    ?>
</h1>


<p class="val_money">
    <?php
        echo $selectedMonthTurnover . ' ' . $selectedMonthCurrency;
    ?>
</p>


<?php 
  if (!empty($rptdata->CurrentTurnover)) {
    $totalQuantity = 0;
    $measureUnit = '';
    foreach($rptdata->CurrentTurnover as $item) {
        $totalQuantity += $item->SELL_COUNT;
        $measureUnit = $item->PRIMARYMUNIT_NAME; 
    }
    echo '<div class="datagrid"><table align="center"><tbody>';
    echo '<tr><td>' . $lang["rptQuantity"] . ': </td><td class="quantity">'.number_format((float)$totalQuantity, 0, '.', '').' ' . $measureUnit . '</td></tr>';
    echo '</tbody></table></div>';
  } // end if LastTurnover
?>
        <div class="chart">
            <div>
                <canvas id="canvas" ></canvas>
            </div>
        </div>
    <!--<h4 align="center"><?php echo $lang["rptRevenueLast5Days"].' '.date('d.m.Y',strtotime($rptstartdate)).' - '.date('d.m.Y',strtotime($rptdate)); ?></h4>-->
    <br>
<h4 align="center">
    <?php 
        echo $lang['forPeriod'] . ' ' . date('d.m.Y', strtotime($rptstartdate)) . ' - ' . date('d.m.Y', strtotime($rptenddate)); 
    ?>
</h4>   
   <table style="width:100%; line-height:22px;">
      <tr>
           <?php 
            if ($expiredate >= time()){                
                echo '<td align="center"><a href="';
                $urlparams = $_GET;
                $urlparams['date'] = (new DateTime($rptPaymentTypeStartDate))->modify('-1 month')->format('Y-m-d');
                $urlparams = http_build_query($urlparams);
                echo $_SERVER['PHP_SELF']."?".$urlparams; 
                echo '" class="medium color-1 button"><<</a></td>';
            } else {
                echo '<td align="center"><a href="#" onclick="show_exp()" class="medium color-1 button"><<</a></td>';             
            }          
           ?> 
        

        <td align="center" style="width:60%;">
                        <a href="ItemDetails.php?pluNumb=<?php echo urlencode($pluNumb); ?>&pluName=<?php echo urlencode($pluName); ?>&sellPrice=<?php echo urlencode($sellPrice); ?>&pluBuyPrice=<?php echo urlencode($pluBuyPrice); ?>&groupId=<?php echo urlencode($groupId); ?>&pluTaxgroupId=<?php echo urlencode($pluTaxgroupId); ?>&pluEcrName=<?php echo urlencode($pluEcrName); ?>&taxGroupDescr=<?php echo urlencode($taxGroupDescr); ?>&pGrpName=<?php echo urlencode($pGrpName); ?>&search=<?php echo urlencode($searchQuery); ?>&filter=<?php echo urlencode($searchFilter); ?>&page=<?php echo urlencode($page); ?>&isOperatorValidated=<?php echo urlencode($isOperatorValidated);?>&isCentralDb=<?php echo urlencode($isCentralDb);?>&pluLocalPrice=<?php echo urlencode($pluLocalPrice);?> &barcode=<?php echo urlencode($barcode);?> &pluSellDisabled=<?php echo urlencode($pluSellDisabled);?>" class="medium color-5 button" style="width:95%;"><?php echo $lang["btnBack2"]; ?></a>
            </td>
        <?php 
            if ($expiredate >= time()){                
               echo '<td align="center"><a href="';
                $urlparams = $_GET;
                $urlparams['date'] = (new DateTime($rptPaymentTypeStartDate))->modify('+1 month')->format('Y-m-d');
                $urlparams = http_build_query($urlparams);
               echo $_SERVER['PHP_SELF']."?".$urlparams;           
               echo '" class="medium color-1 button">>></a></td>';
            } else {
               echo '<td align="center"><a href="#" onclick="show_exp()" class="medium color-1 button">>></a></td>';              
            }  
            
           ?> 
          
      </tr>
    </table>

  </div>
</body>
</html>
