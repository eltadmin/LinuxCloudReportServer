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
  .color-1 { background-color: #00B79C; }
  .color-5 { background-color: #FBB03B; }
  
  .color-1:hover, .color-1:active  {
    background-color: #147e5b;  
  }  
  .color-5:hover, .color-5:active  {
    background-color: #e18e0a;  
  }
  
</style>

<?php
require_once 'init.php';

    session_start();
	date_default_timezone_set('Europe/Sofia');
    if(!isset($_SESSION['s_authenticated']) || empty($_SESSION['s_authenticated'])){
    header("location:../index.php");
    }

    $C_SQL  = '{"Id":"DatabaseId","Pass":"1234","CurrentTurnover":{"Type":"Query","SQL":"select cast(S.SELL_DATETIME - cast(TIMEOFFSET as float)/24 as date) \"SELL_DATE\", sum(S.SELL_SUM) \"SELL_SUM\", C.CURR_SIGN from SALES_BON S inner join N_CURRENCY C on S.SELL_CURRTYPE = C.CURR_ID where (cast(S.SELL_DATETIME - cast(TIMEOFFSET as float)/24 as date) >= CURRENT_DATE-1) and (S.SELL_REVOKED_ = 0) group by 1, 3 order by 1 desc"},';
    $C_SQL .= '"LastTurnover":{"Type":"Query","SQL":"select cast(S.SELL_DATETIME - cast(TIMEOFFSET as float)/24 as date) \"SELL_DATE\",sum(S.SELL_SUM) \"SELL_SUM\", C.CURR_SIGN from SALES_BON S inner join N_CURRENCY C on S.SELL_CURRTYPE = C.CURR_ID where (cast(S.SELL_DATETIME - cast(TIMEOFFSET as float)/24 as date) between START_DATE and END_DATE)and(S.SELL_REVOKED_ = 0) group by 1, 3 order by 1 asc"},';
    $C_SQL .= '"CurrentPayType":{"Type":"Query","SQL":"select P.PAY_TYPE, T.PAYTYPE_NAME, SUM(P.PAY_SUM) \"PAY_SUM\" from SALES_PAYMENT P inner join SALES_BON S on P.PAY_SELL_ID = S.SELL_ID inner join N_PAYMENT_TYPES T on P.PAY_TYPE = T.PAYTYPE_NUMB where (cast(S.SELL_DATETIME - cast(TIMEOFFSET as float)/24 as date) = CURRENT_DATE)and(S.SELL_REVOKED_ = 0) group by 1, 2 order by 1 asc"}}';

    define("C_DEBUG", false);
    define("C_RPTNAME", "dayturnover");
    define("C_TIMEOFFSET", 0);

    $otimeoffset = C_TIMEOFFSET;
    $rname = C_RPTNAME;

    include_once 'language.php';
    // show loader for slow connections
    require_once ('class.loading.div.php');
    $divLoader = new loadingDiv;
    $divLoader->loader();

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
        while ($row = mysql_fetch_assoc($qry)) {
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
        if (C_DEBUG) {echo "time offset: "; var_dump($otimeoffset); echo "<br/>";}

         // set data parameters
         if ($rptdate == ''){
             $dt = new DateTime();
             $rptdate = $dt->format('Y-m-d');
         } else {
             $dt = new DateTime($rptdate);
         }

         $dt->setTime( 0, 0, 0 );

         $dt->add(new DateInterval('PT'.(24+$otimeoffset).'H'));
         $rptenddate = $dt->format('Y-m-d');
         if (C_DEBUG) {echo "EndDate: ".$rptenddate; echo "<br/>";}

         $dt->sub(new DateInterval('PT168H'));
         $rptstartdate = $dt->format('Y-m-d');
         if (C_DEBUG) {echo "StartDate: ".$rptstartdate; echo "<br/>";}
/*
         $dt->add(new DateInterval('PT'.(24+$otimeoffset).'H'));
         $rptenddate = $dt->format('Y-m-d H:i').':00';
         if (C_DEBUG) {echo "EndDate: ".$rptenddate; echo "<br/>";}

         $dt->sub(new DateInterval('PT168H'));
         $rptstartdate = $dt->format('Y-m-d H:i').':00';
         if (C_DEBUG) {echo "StartDate: ".$rptstartdate; echo "<br/>";}
*/
		//replace timeoffset
		$rptsql = str_replace('TIMEOFFSET',$otimeoffset,$rptsql);
        //replace parameters in SQL
        $rptsql = str_replace('START_DATE','\''.$rptstartdate.'\'',$rptsql);
        $rptsql = str_replace('END_DATE','\''.$rptenddate.'\'',$rptsql);
        if (C_DEBUG) {echo "report date: "; var_dump($rptdate); echo "<br/>";}

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
            'timeout' => 29, //timeout in seconds *2, 10 means 20 sec.
            'header'  => "Content-type: text/xml\r\n",
            'method'  => 'GET',
            'content' => $rptsql
            ),
         );
         $context  = stream_context_create($options);
         $str = @file_get_contents($url, false, $context);
         if (C_DEBUG) {echo "response: "; var_dump($str); echo "<br/>";}
         //echo 'end: '.  date("h:i:sa",time())  .'<br>';

/*
$str  = '{"ResultCode":0,"ResultMessage":"OK"';
$str .= ',"CurrentTurnover":[{"SELL_DATE":"14.01.2016","SELL_SUM":110,"CURR_SIGN":"лв"}]';
$str .= ',"CurrentTurnover":[{"SELL_DATE":"14.01.2016","SELL_SUM":110,"CURR_SIGN":"лв"},{"SELL_DATE":"13.01.2016","SELL_SUM":22,"CURR_SIGN":"лв"}]';
$str .= ',"LastTurnover":[{"SELL_DATE":"06.01.2016","SELL_SUM":1536.21,"CURR_SIGN":"лв"},{"SELL_DATE":"07.01.2016","SELL_SUM":1700.64,"CURR_SIGN":"лв"},{"SELL_DATE":"08.01.2016","SELL_SUM":2261.94,"CURR_SIGN":"лв"},{"SELL_DATE":"09.01.2016","SELL_SUM":960.05,"CURR_SIGN":"лв"},{"SELL_DATE":"10.01.2016","SELL_SUM":805.66,"CURR_SIGN":"лв"},{"SELL_DATE":"11.01.2016","SELL_SUM":1869.41,"CURR_SIGN":"лв"},{"SELL_DATE":"12.01.2016","SELL_SUM":1428.6,"CURR_SIGN":"лв"},{"SELL_DATE":"13.01.2016","SELL_SUM":81.6,"CURR_SIGN":"лв"}]';
$str .= ',"CurrentPayType":[{"PAY_TYPE":1,"PAYTYPE_NAME":"В БРОЙ","PAY_SUM":78.4},{"PAY_TYPE":2,"PAYTYPE_NAME":"КАРТА","PAY_SUM":3.2}]}';
*/

        // inititialize default erorr values
        $CurrentTurnover = "";
        $LastTurnoverLabel = '""';
        $LastTurnoverData = '"0"';
        $CurrencySign = '';
        $AvgTurnover = 0;
        $ItemsCount = 0;
        $AvgTurnoverData = "";
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
                  if (!empty($rptdata->CurrentTurnover)) {
                      $CurrentTurnover = number_format((float)$rptdata->CurrentTurnover[0]->SELL_SUM, 2, '.', '');
                      $CurrencySign = $rptdata->CurrentTurnover[0]->CURR_SIGN;
                      $CurrentTurnover .= ' '.$CurrencySign;
                  }
                  $LastTurnoverLabel = '';
                  $LastTurnoverData = '';
                  foreach($rptdata->LastTurnover as $item) {
                   $ymd = DateTime::createFromFormat('d.m.Y', $item->SELL_DATE )->format('d.m');
                   $LastTurnoverLabel .= '"'.$ymd.'",';
                   $LastTurnoverData  .= number_format((float)$item->SELL_SUM, 2, '.', '').',';

				   //Calculate average turnover

				   $today = new DateTime();
				   $todayStr = $today->format('d.m.Y');
				   if($item->SELL_DATE != $todayStr){
					   $AvgTurnover = $AvgTurnover + (float)$item->SELL_SUM;
					   $ItemsCount++;
				   }
                  }

                  $LastTurnoverLabel = rtrim($LastTurnoverLabel, ",");
                  $LastTurnoverData = rtrim($LastTurnoverData, ",");

                  if ($ItemsCount > 0) {
                      $AvgTurnover = $AvgTurnover / $ItemsCount;
                      for ($x = 0; $x < $ItemsCount; $x++) {
                          $AvgTurnoverData .= number_format((float)$AvgTurnover, 2, '.', '') . ',';
                      }
                      $AvgTurnoverData = rtrim($AvgTurnoverData, ",");

                  /*$AvgTurnover = $AvgTurnover/$ItemsCount;
                  for ($x = 0; $x <= $ItemsCount; $x++) {
                   $AvgTurnoverData .= number_format((float)$AvgTurnover, 2, '.', '').',';
                  }
                  $AvgTurnoverData = rtrim($AvgTurnoverData, ",");
                 */
              } }else {
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
                    pointHoverBackgroundColor : "#fff",
                    pointHoverBorderColor : "rgba(151,187,205,1)",
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
       //echo '<a>'.$customername.'</a> • <a>'.$lang["ObjectExpireOn"].date("d.m.Y", $expiredate).'</a>';

       //echo '<img src="images/refresh.png" alt="refresh">';
       echo '<a>'.$customername.'</a>';

       if ($_SESSION['lang'] == 'bg'){
         echo '&nbsp;•&nbsp;<a href="dayturnover.php?lang=en"><img src="images/en.png" /></a>';
       } else {
         echo '&nbsp;•&nbsp;<a href="dayturnover.php?lang=bg"><img src="images/bg.png" /></a>';
       }
       echo '&nbsp;•&nbsp;<a href="javascript:history.go(0)"><img src="images/refresh.png" alt="refresh"></a>';

      ?>

    </div>
    <h1 align="center"> <?php  echo $_SESSION['s_objectname']; ?> </h1>
    <h1 align="center"><?php echo $lang["rptRevenueTitle"]; ?></h1>
    <p class="val_money">
    <?php
      echo $CurrentTurnover;
      if (!empty($rptdata)) {
       if ($rptdata->ResultCode == 0 && !empty($rptdata->CurrentTurnover)) {
           if (count($rptdata->CurrentTurnover) <  2) {
              echo '<h4>'.$lang["rptRevenuePreviousTurnover"].$rptdata->CurrentTurnover[0]->SELL_DATE.'</h4>';
           }
       }
      }	  // end if rptdata
      //echo $CurrentTurnover;
	  
    ?>
     </p>

    <?php
      // add pay types table
      if (!empty($rptdata->CurrentPayType)) {
        echo '<div class="datagrid"><table align="center"><tbody>';
        foreach($rptdata->CurrentPayType as $item) {
          echo '<tr><td>'.$item->PAYTYPE_NAME.'</td><td class="price">'.number_format((float)$item->PAY_SUM, 2, '.', '').' '.$CurrencySign.'</td></tr>';
        }
        echo '</tbody></table></div>';
      } // end if CurrentPayType
    ?>


    <h4 align="center">
    <?php
      echo $lang["rptRevenueToDate"].date('d.m.Y H:i', time());
     ?>
    </h4>
        <div class="chart">
            <div>
                <canvas id="canvas" ></canvas>
            </div>
        </div>
    <h4 align="center"><?php echo $lang["rptRevenueLast5Days"].' '.date('d.m.Y',strtotime($rptstartdate)).' - '.date('d.m.Y',strtotime($rptenddate)); ?></h4>
    <br>
    <table style="width:100%; line-height:22px;">
      <tr>
           <?php
            if ($expiredate >= time()){
			    echo '<td align="center"><a href="';
				$urlparams = $_GET;
				$urlparams['date'] = (new DateTime($rptdate))->modify('-7 day')->format('Y-m-d');
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
 				 $urlparams['date'] = (new DateTime($rptdate))->modify('+7 day')->format('Y-m-d');
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


