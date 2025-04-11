<?php
define('DREPORT_INIT', true);
require_once __DIR__ . '/init.php';

// Initialize session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
checkAuth();

// Initialize variables
$chartBars = 0;
$objectid = isset($_GET["id"]) ? $_GET["id"] : (isset($_SESSION['s_objectid']) ? $_SESSION['s_objectid'] : '');
$deviceid = isset($_SESSION['s_deviceid']) ? $_SESSION['s_deviceid'] : '0';
$rptdate = isset($_GET["date"]) ? $_GET["date"] : '';
$objectname = isset($_SESSION['s_objectname']) ? $_SESSION['s_objectname'] : '';

// Store objectid in session
$_SESSION['s_objectid'] = $objectid;

?>
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
  <script type="text/javascript" src="js/jquery.min.js" ></script>
  <script type="text/javascript" src="js/jquery.alerts.js"></script>
  <link rel="stylesheet" href="css/jquery.alerts.css">
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
  .color-1 { background-color: #00898C; }
  .color-5 { background-color: #FBB03B; }
  
  .color-1:hover, .color-1:active  {
    background-color: #015f61;  
  }  
  .color-5:hover, .color-5:active  {
    background-color: #e18e0a;  
  }
  </style>

<?php
    if(!isset($_SESSION['s_authenticated']) || empty($_SESSION['s_authenticated'])){
    header("location:../index.php");
    }

//    $C_SQL  = '{ "Id":"DatabaseId", "Pass":"1234", "PluTurnover": { "Type":"Query", "SQL":"select first 10 S.SPLU_NAME, sum(S.SPLU_SOLDQUANT) \"SPLU_SOLDQUANT\", sum(RoundTo((S.SPLU_SELLPRICE * S.SPLU_SOLDQUANT),2)+RoundTo((S.SPLU_SOLDQUANT * S.SPLU_SELLPRICE * S.SPLU_SELLDISCOUNT/100), 2)) as \"TURNOVER\", C.CURR_SIGN from SALES_PLUES S left join N_CURRENCY C on S.SPLU_SELLCURRENCY = C.CURR_ID where S.SPLU_DATETIME between START_DATE and END_DATE group by 1, 4 order by 3 desc" }}';

    $C_SQL  = '{ "Id":"DatabaseId", "Pass":"1234", "PluTurnover": { "Type":"Query", "SQL":"select first 10 S.SPLU_NAME, sum(S.SPLU_SOLDQUANT) \"SPLU_SOLDQUANT\", ';
    $C_SQL .= 'sum(RoundTo((S.SPLU_SELLPRICE * S.SPLU_CURRCOURCE * S.SPLU_SOLDQUANT),2)+RoundTo((S.SPLU_SOLDQUANT * S.SPLU_SELLPRICE * S.SPLU_CURRCOURCE * S.SPLU_SELLDISCOUNT/100), 2)) as \"TURNOVER\", C.CURR_SIGN ';
    $C_SQL .= 'from SALES_PLUES S left join N_CURRENCY C on C.CURR_ISBASE_ = 1';
    $C_SQL .= 'where (cast(S.SPLU_DATETIME - cast(TIMEOFFSET as float)/24 as date) = PARAMDATE) and (S.SPLU_REVOKED_ = 0)';
    $C_SQL .= 'group by 1, 4 order by 3 desc " }}';


    define("C_DEBUG", false);
    define("C_BAR_HEIGHT", 20);
    define("C_CANVAS_HEIGHT", 30);
    define("C_RPTNAME", "pluturnover");
    define("C_TIMEOFFSET", 0);

    $otimeoffset = C_TIMEOFFSET;
    $rname = C_RPTNAME;

    include_once 'language.php';
    // show loader for slow connections
    require_once ('class.loading.div.php');
    $divLoader = new loadingDiv;
    $divLoader->loader();

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

        $qry = $pDatabase->query("select d_objectpswd,d_timeoffset from t_devices where d_deviceid = '$deviceid' and d_objectid='".sql_safe($objectid)."' ;");
        while ($row = mysqli_fetch_assoc($qry)) {
         $objectpswd = $row['d_objectpswd'];
         $otimeoffset = $row['d_timeoffset'];
        }
        if (C_DEBUG) {echo "time offset: "; var_dump($otimeoffset); echo "<br/>";}
        //replace timoffset parameter inSQL
        $rptsql = str_replace('TIMEOFFSET','\''.$otimeoffset.'\'',$rptsql);

        //replace date parameters in SQL
        if ($rptdate == ''){
             $dt = new DateTime();
             $rptdate = $dt->format('Y-m-d');
        }
        $rptsql = str_replace('PARAMDATE','\''.$rptdate.'\'',$rptsql);
        if (C_DEBUG) {echo "report date: "; var_dump($rptdate); echo "<br/>";}


/*
         // set data parameters
         if ($rptdate == ''){
             $dt = new DateTime();
             $rptdate = $dt->format('Y-m-d');
         } else {
             $dt = new DateTime($rptdate);
         }

         $dt->setTime( 0, 0, 0 );

         $dt->add(new DateInterval('PT'.(24+$otimeoffset).'H'));
         $rptenddate = $dt->format('Y-m-d H:i').':00';
         if (C_DEBUG) {echo "EndDate: ".$rptenddate; echo "<br/>";}

         $dt->sub(new DateInterval('PT24H'));
         $rptstartdate = $dt->format('Y-m-d H:i').':00';
         if (C_DEBUG) {echo "StartDate: ".$rptstartdate; echo "<br/>";}

        //replace parameters in SQL
        $rptsql = str_replace('START_DATE','\''.$rptstartdate.'\'',$rptsql);
        $rptsql = str_replace('END_DATE','\''.$rptenddate.'\'',$rptsql);
        if (C_DEBUG) {echo "report date: "; var_dump($rptdate); echo "<br/>";}
*/
        $pDatabase->logevent(OPER_COMMAND,$objectid,'report: '.$rname.' objectid='.$objectid);

        $url = 'http://'.$_SESSION['s_rpt_server_host'].':'.$_SESSION['s_rpt_server_port'].'/report/'.$rname.'/?id='.$objectid.'&u='.$_SESSION['s_rpt_server_user'].'&p='.$_SESSION['rpt_server_pswd'];
        if (C_DEBUG) {echo "url: "; var_dump($url); echo "<br/>";}

        // set ObjectId and password
        $obj = json_decode($rptsql);
        $obj->{"Id"} = $objectid;
        $obj->{"Pass"} = $objectpswd;
        $rptsql = json_encode($obj);

        if (C_DEBUG) {echo "content: "; var_dump($rptsql); echo "<br/>";}
        $options = array(
            'http' => array(
            'timeout' => 45, //timeout in seconds
            'header'  => "Content-type: text/xml\r\n",
            'method'  => 'GET',
            'content' => $rptsql
            ),
         );
         $context  = stream_context_create($options);
         $str = @file_get_contents($url, false, $context);
         if (C_DEBUG) {echo "response: "; var_dump($str); echo "<br/>";}


//responce from server
//$str  = '{"ResultCode":0,"ResultMessage":"OK","PluTurnover":[';
//$str .= '{"SPLU_NAME":"Пакет ФАБРИКА","SPLU_SOLDQUANT":30,"TURNOVER":111,"CURR_SIGN":"лв"},{"SPLU_NAME":"Пакет ДЕТСКИ","SPLU_SOLDQUANT":24,"TURNOVER":76.8,"CURR_SIGN":"лв"},{"SPLU_NAME":"Пиле с грах ","SPLU_SOLDQUANT":9,"TURNOVER":35.2,"CURR_SIGN":"лв"},{"SPLU_NAME":"МИЛИНКА ","SPLU_SOLDQUANT":21,"TURNOVER":31.5,"CURR_SIGN":"лв"},{"SPLU_NAME":"Пица шунка 1\/3","SPLU_SOLDQUANT":17,"TURNOVER":30.6,"CURR_SIGN":"лв"},{"SPLU_NAME":"ПИЛ.ПЪРЖ.НА ПЛОЧА","SPLU_SOLDQUANT":4,"TURNOVER":26.36,"CURR_SIGN":"лв"},{"SPLU_NAME":"Пица микс 1\/3","SPLU_SOLDQUANT":12,"TURNOVER":22.8,"CURR_SIGN":"лв"},{"SPLU_NAME":"ЕКЛЕРОВА ТОРТА ","SPLU_SOLDQUANT":5,"TURNOVER":22.45,"CURR_SIGN":"лв"},{"SPLU_NAME":"Кафе с каничка мля","SPLU_SOLDQUANT":14,"TURNOVER":21.6,"CURR_SIGN":"лв"},{"SPLU_NAME":"Старобърно 500мл","SPLU_SOLDQUANT":7,"TURNOVER":20.7,"CURR_SIGN":"лв"}]}';


        // inititialize default erorr values
        $CurrencySign = '""';
        $TurnoverLabel = '""';
        $TurnoverData = '"0"';
        //$str = @file_get_contents($url);
        if (!$str) {
          //show error essage
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
                  $chartBars = 1;
                  $TurnoverLabel = '';
                  $TurnoverData = '';
                  if (!empty($rptdata->PluTurnover)) {$chartBars = count($rptdata->PluTurnover);}
                  foreach($rptdata->PluTurnover as $item) {
                   $TurnoverLabel .= '"'.str_replace('"', "'", $item->SPLU_NAME).'",';
                   $TurnoverData  .= number_format($item->TURNOVER, 2, '.', '').',';
                   //$TurnoverLabel = '"'.$item->SPLU_NAME.'",'.$TurnoverLabel;
                   //$TurnoverData  = $item->TURNOVER.','.$TurnoverData;

                   $CurrencySign = $item->CURR_SIGN;
                  }
                  $TurnoverLabel = rtrim($TurnoverLabel, ",");
                  $TurnoverData = rtrim($TurnoverData, ",");
              } else {
                  $pDatabase->show_alert(ALERT_ERROR,$lang["AlertError"], $pDatabase->getTCPErrorMessage($rptdata->ResultCode, $lang),false,false);
                  $pDatabase->logevent(OPER_ERROR,$objectid,'report: '.$rname.' ResultCode: '.$rptdata->ResultCode.' ResultMessage: '.$rptdata->ResultMessage);
              } // end else ResultCode=
            } // end JSON error
        } // end else !$str
//    } // end check expire date
?>



  <script>
        var config = {
            type: 'horizontalBar',
            data: {
                <?php echo "labels : [" . $TurnoverLabel . "],"; ?>
                datasets: [{
                    label: <?php echo '"'.$lang["rptRevenueChartLabel"].'",'; ?>
                    <?php echo  "data : [" . $TurnoverData . "],"; ?>
                    borderColor : "rgba(151,187,205,1)",
                    backgroundColor : "rgba(151,187,205,0.5)",
                    borderWidth : 1,
                    hoverBackgroundColor : "rgba(151,187,205,0.8)",
                    hoverBorderColor : "rgba(151,187,205,1)"
                }]
            },
            options: {
                responsive: true,
                 scales: {
                 yAxes: [{
					 ticks: {mirror: true},
                  barThickness: <?php echo C_BAR_HEIGHT ?>
                 }]
                },
                legend: {
                  display: false,
                },
                hover: {
                    mode: 'label'
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

       echo '<a>'.$customername.'</a>';
       if ($_SESSION['lang'] == 'bg'){
         echo '&nbsp;•&nbsp;<a href="pluturnover.php?lang=en"><img src="images/en.png" /></a>';
       } else {
         echo '&nbsp;•&nbsp;<a href="pluturnover.php?lang=bg"><img src="images/bg.png" /></a>';
       }
       echo '&nbsp;•&nbsp;<a href="javascript:history.go(0)"><img src="images/refresh.png" alt="refresh"></a>';
      ?>

    </div>
    <h1 align="center"> <?php  echo $_SESSION['s_objectname']; ?> </h1>
    <h1 align="center"><?php echo $lang["rptPluTurnoverTitle"]; ?></h1>
    <h4 align="center">
    <?php
      echo $lang["rptPluTurnoverToDate"].(new DateTime($rptdate))->format('d.m.Y');
     ?>
    </h4>

        <div class="chart">
            <div>
                <canvas id="canvas" <?php if ($chartBars==1) { $chartBars = 2;} echo 'height="'.C_CANVAS_HEIGHT*$chartBars.'"';?> ></canvas>
            </div>
        </div>
    <h4 align="center"><?php echo $lang["rptPluTurnoverCurrentDate"].$CurrencySign; ?></h4>
    <br>

    <table style="width:100%; line-height:22px;">
      <tr>
           <?php
            if ($expiredate >= time()){
			    echo '<td align="center"><a href="';
				$urlparams = $_GET;
				$urlparams['date'] = (new DateTime($rptdate))->modify('-1 day')->format('Y-m-d');
				$urlparams = http_build_query($urlparams);
				echo $_SERVER['PHP_SELF']."?".$urlparams;
				echo '" class="medium color-1 button"><<</a></td>';
			} else {
			    echo '<td align="center"><a href="#" onclick="show_exp()" class="medium color-1 button"><<</a></td>';
			}
           ?>
        <td align="center" style="width:60%;"><a href="rptlist.php" class="medium color-5 button" style="width:95%;"><?php echo $lang["btnExit"]; ?></a></td>
           <?php
            if ($expiredate >= time()){
			   echo '<td align="center"><a href="';
				$today = new DateTime();
				$today->setTime( 0, 0, 0 );

				$match_date = new DateTime($rptdate );
				$match_date->setTime( 0, 0, 0 );

				$diff = $today->diff( $match_date );
				$diffDays = (integer)$diff->format( "%R%a" );

				$urlparams = $_GET;

				if ( $diffDays < 0 ) {
					$urlparams['date'] = (new DateTime($rptdate))->modify('+1 day')->format('Y-m-d');
				}
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


