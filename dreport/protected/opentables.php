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
    session_start();
    if(!isset($_SESSION['s_authenticated']) || empty($_SESSION['s_authenticated'])){
    header("location:../index.php");
    }

    define("C_DEBUG", true);
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


    include('database.class.php');
    $pDatabase = Database::getInstance();
    $result = $pDatabase->query("set names 'utf8'");

    //check expire date
    $qry = $pDatabase->query("SELECT s_customername, s_expiredate FROM t_subscriptions WHERE s_objectid = '".sql_safe($_SESSION['s_objectid'])."'");
    while ($row = mysqli_fetch_assoc($qry)) {
     $expiredate = strtotime($row['s_expiredate']);
     $customername = $row['s_customername'];
    }

    if ($expiredate >= time()) {
        $objectpswd = "";
        $rptsql = "";
        $rname = "";

        $qry = $pDatabase->query("select r_sql_".sql_safe($_SESSION['lang']).", r_name from t_reports where r_name='opentables'");
        while ($row = mysqli_fetch_assoc($qry)) {
         $rptsql = $row['r_sql_'.$_SESSION['lang']];
         $rname = $row['r_name'];
        }

        $qry = $pDatabase->query("select d_objectpswd from t_devices where d_objectid='".sql_safe($objectid)."';");
        while ($row = mysqli_fetch_assoc($qry)) {
         $objectpswd = $row['d_objectpswd'];
        }

        $pDatabase->logevent(OPER_COMMAND,$deviceid,'report: '.$rname.' objectid='.$objectid);

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

//$str  = '{"ResultCode":0,"ResultMessage":"OK","CurrentTurnover":[{"SELL_SUM":340.01,"CURR_SIGN":"лв"}]';
//$str .= ',"LastTurnover":[{"SELL_DATE":"04.01.2016","SELL_SUM":3333.2,"CURR_SIGN":"лв"},{"SELL_DATE":"05.01.2016","SELL_SUM":265.13,"CURR_SIGN":"лв"},{"SELL_DATE":"06.01.2016","SELL_SUM":394.62,"CURR_SIGN":"лв"},{"SELL_DATE":"07.01.2016","SELL_SUM":473.04,"CURR_SIGN":"лв"},{"SELL_DATE":"08.01.2016","SELL_SUM":1172.95,"CURR_SIGN":"лв"},{"SELL_DATE":"09.01.2016","SELL_SUM":1843.24,"CURR_SIGN":"лв"},{"SELL_DATE":"10.01.2016","SELL_SUM":1428.96,"CURR_SIGN":"лв"},{"SELL_DATE":"11.01.2016","SELL_SUM":340.01,"CURR_SIGN":"лв"}]';
//$str .= ',"CurrentPayType":[{"PAY_TYPE":1,"PAYTYPE_NAME":"В БРОЙ","PAY_SUM":338.82},{"PAY_TYPE":2,"PAYTYPE_NAME":"ПЛАЩАНЕ СЛУЖЕБНА КАРТА","PAY_SUM":1.19}]}';

        // inititialize default erorr values
        $CurrentTurnover = "";
        $LastTurnoverLabel = '""';
        $LastTurnoverData = '"0"';
        $CurrencySign = '';
        //$str = @file_get_contents($url);
        if (!$str) {
          //show error essage
          $pDatabase->show_alert(ALERT_ERROR,$lang["AlertError"],$lang["rptNoReceivedData"],false,false);
          $pDatabase->logevent(OPER_ERROR,$deviceid,'report: '.$rname.' error: '.$lang["rptNoReceivedData"]);
        } else {
            $rptdata = @json_decode($str, false);
            if (C_DEBUG) {echo "rptdata: ";  var_dump($rptdata); echo "<br/>";}
            if ($rptdata == null && json_last_error() !== JSON_ERROR_NONE) {
              $pDatabase->show_alert(ALERT_ERROR,$lang["AlertError"],$lang["rptInvalidData"],false,false);
              $pDatabase->logevent(OPER_ERROR,$deviceid,'report: '.$rname.' error: '.$lang["rptInvalidData"]);

            } else {
              //continue only if "ResultCode": 0,
              if ($rptdata->ResultCode == 0) {
                  if (!empty($rptdata->CurrentTurnover)) {
                      //$CurrentTurnover =   $rptdata->CurrentTurnover[0]->SELL_SUM;
                      $CurrentTurnover = number_format((float)$rptdata->CurrentTurnover[0]->SELL_SUM, 2, '.', '');
                      $CurrencySign = $rptdata->CurrentTurnover[0]->CURR_SIGN;
                      $CurrentTurnover .= ' '.$CurrencySign;
                  }
                  $LastTurnoverLabel = '';
                  $LastTurnoverData = '';
                  foreach($rptdata->LastTurnover as $item) {
                   $ymd = DateTime::createFromFormat('d.m.Y', $item->SELL_DATE )->format('d.m');
                   $LastTurnoverLabel .= '"'.$ymd.'",';
                   $LastTurnoverData  .= $item->SELL_SUM.',';
                  }
                  $LastTurnoverLabel = rtrim($LastTurnoverLabel, ",");
                  $LastTurnoverData = rtrim($LastTurnoverData, ",");
              } else {
                  $pDatabase->show_alert(ALERT_ERROR,$lang["AlertError"],$pDatabase->getTCPErrorMessage($rptdata->ResultCode,$lang),false,false);
                  $pDatabase->logevent(OPER_ERROR,$deviceid,'report: '.$rname.' ResultCode: '.$rptdata->ResultCode.' ResultMessage: '.$rptdata->ResultMessage);
              } // end else ResultCode=
            } // end JSON error
        } // end else !$str
    } // end check expire date
?>



  <script>

        var randomScalingFactor = function(){ return Math.round(Math.random()*100)};
        var lineChartData = {
//            labels : ["10 Окт","11 Окт","12 Окт","13 Окт","14 Окт","15 Окт","16 Окт"],
    <?php
       //echo "labels : [" . $rptdata['rpt'][0]['lbl'] . "],";
       echo "labels : [" . $LastTurnoverLabel . "],";
     ?>

            datasets : [
                {
                    label: <?php echo '"'.$lang["rptRevenueChartLabel"].'",'; ?>
                    fillColor : "rgba(151,187,205,0.2)",
                    strokeColor : "rgba(151,187,205,1)",
                    pointColor : "rgba(151,187,205,1)",
                    pointStrokeColor : "#fff",
                    pointHighlightFill : "#fff",
                    pointHighlightStroke : "rgba(151,187,205,1)",
//                    data : [randomScalingFactor(),randomScalingFactor(),randomScalingFactor(),randomScalingFactor(),randomScalingFactor(),randomScalingFactor(),randomScalingFactor()]
    <?php
       //echo "data : [" . $rptdata['rpt'][0]['data'] . "]";
       echo  "data : [" . $LastTurnoverData . "]";
     ?>

                }
            ]
        }

    window.onload = function(){
        var ctx = document.getElementById("canvas").getContext("2d");
        window.myLine = new Chart(ctx).Line(lineChartData, {
            responsive: true
        });
    }

  </script>
</head>

<body>
  <div class="login-card">
    <div class="login-help">
      <?php
       if ($expiredate < time()){
         $pDatabase->show_alert(ALERT_WARNING,$lang["AlertWarning"],$lang["errObjectExpired"].date("d.m.Y", $expiredate),false,false);
         echo '<div><center><a href="rptlist.php" class="medium color-5 button">'.$lang["btnExit"].'</a></center></div>';
         $pDatabase->logevent(OPER_ERROR,$deviceid,'report: '.$rname.' error: '.$lang["errObjectExpired"].date("d.m.Y", $expiredate));
         exit;
        }
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
    <h4 align="center"><?php echo $lang["rptRevenueLast5Days"]; ?></h4>
    <br>
    <center><a href="rptlist.php" class="medium color-5 button"><?php echo $lang["btnExit"]; ?></a></center>
  </div>
</body>
</html>


