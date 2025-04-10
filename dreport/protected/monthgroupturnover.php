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
  .color-1 { background-color: #6082B6; }
  .color-5 { background-color: #FBB03B; }
  
  .color-1:hover, .color-1:active  {
    background-color: #395989;  
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

    define("C_DEBUG", false);
    define("C_BAR_HEIGHT", 20);
    define("C_CANVAS_HEIGHT", 30);
    define("C_RPTNAME", "groupturnover");
    define("C_TIMEOFFSET", 0);

	// param TIMEOFFSET, PARAMDATE
	$C_SQL  = '{ "Id":"DatabaseId", "Pass":"1234", "GroupTurnover": { "Type":"Query", "SQL":"select G.O_GRP_NAME as \"O_GRP_NAME_TOP\", G.O_GRP_ID as \"O_GRP_ID_TOP\", cast(-1 as integer) as \"O_GRP_PARENT_TOP\", sum(RoundTo( (S.SPLU_SELLPRICE * S.SPLU_SOLDQUANT * S.SPLU_CURRCOURCE),2)+RoundTo((S.SPLU_SOLDQUANT * S.SPLU_SELLPRICE * S.SPLU_CURRCOURCE * S.SPLU_SELLDISCOUNT/100), 2)) as \"TURNOVER\", C.CURR_SIGN from SALES_PLUES S left join PLUES P on S.SPLU_PLUNUMB = P.PLU_NUMB left join GET_TOPGROUPFORPLUGROUP(P.PLU_GROUP_ID, 0) G on G.O_GRP_ID > 0 left join N_CURRENCY C on C.CURR_ISBASE_ = 1 where (cast(S.SPLU_DATETIME - cast(TIMEOFFSET as float)/24 as date) BETWEEN PARAMDTSTART AND PARAMDTEND)and (S.SPLU_REVOKED_ = 0) group by 1,2,3,5 order by 4 desc" } }';

	// param TIMEOFFSET, PARAMGROUP, PARAMDATE
	$C_SQL_SUB  = '{ "Id":"DatabaseId", "Pass":"1234", "GroupTurnover": { "Type":"Query", "SQL":"';
    $C_SQL_SUB .= 'select SG.O_GRP_NAME_TOP, SG.O_GRP_ID_TOP, SG.O_GRP_PARENT_TOP, ';
    $C_SQL_SUB .= 'sum(RoundTo((S.SPLU_SELLPRICE * S.SPLU_SOLDQUANT * S.SPLU_CURRCOURCE),2)+RoundTo((S.SPLU_SOLDQUANT * S.SPLU_SELLPRICE * S.SPLU_CURRCOURCE * S.SPLU_SELLDISCOUNT/100), 2)) as \"TURNOVER\", C.CURR_SIGN ';
    $C_SQL_SUB .= 'from SALES_PLUES S ';
    $C_SQL_SUB .= 'left join PLUES P on S.SPLU_PLUNUMB = P.PLU_NUMB ';
    $C_SQL_SUB .= 'left join N_CURRENCY C on C.CURR_ISBASE_ = 1 ';
    $C_SQL_SUB .= 'inner join GET_SUBGROUPSFORGROUP_RR(PARAMGROUP, 0) SG on (P.PLU_GROUP_ID = SG.O_GRP_ID) ';
    $C_SQL_SUB .= 'where (cast(S.SPLU_DATETIME - cast(TIMEOFFSET as float)/24 as date) BETWEEN PARAMDTSTART AND PARAMDTEND) and (S.SPLU_REVOKED_ = 0) ';
    $C_SQL_SUB .= 'group by 1,2,3,5 order by 4 desc " } }';

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
    // get date parameter for sql
    $rptdate = "";
    if(isset($_GET["date"])){
     $rptdate = $_GET["date"];
    }

    /* Get group params
    /  gname - group name
    /  gval - group value/sum
    /  gid - group id for sub sql
    /  if gname is empty - show gruops level one
    */
    $groupname = "";
    if(isset($_GET["gname"])){
     $groupname = $_GET["gname"];
    }
    $groupval = "";
    if(isset($_GET["gval"])){
     $groupval = $_GET["gval"];
    }
    $gid = -1;
    if(isset($_GET["gid"])){
     $gid = $_GET["gid"];
    }

    $chartBars = 1;
    $TurnoverLabel = '';
    $TurnoverData = '';
    $TurnoverCurrency = '';
    $GroupIdData = '';
    $GroupIdParent = -1;

    include('database.class.php');
    $pDatabase = Database::getInstance();
    $result = $pDatabase->query("set names 'utf8'");

    //check expire date
    $qry = $pDatabase->query("SELECT s_customername, s_expiredate FROM t_subscriptions WHERE s_objectid = '".sql_safe($_SESSION['s_objectid'])."'");
    while ($row = mysqli_fetch_assoc($qry)) {
     $expiredate = strtotime($row['s_expiredate']);
     $customername = $row['s_customername'];
    }

        $objectpswd = "";
        $rptsql = "";
        $rname = "";

        $rname = C_RPTNAME;
        //set parent group parameter
        if ($gid>0) {
         $rptsql = $C_SQL_SUB;
         $rptsql = str_replace('PARAMGROUP',$gid,$rptsql);
        } else {
         $rptsql = $C_SQL;
        }

        $qry = $pDatabase->query("select d_objectpswd, d_timeoffset from t_devices where d_deviceid = '$deviceid' and d_objectid ='".sql_safe($_SESSION['s_objectid'])."';");

        while ($row = mysqli_fetch_assoc($qry)) {
         $objectpswd = $row['d_objectpswd'];
         $otimeoffset = $row['d_timeoffset'];
        }
        if (C_DEBUG) {echo "Time offset: "; var_dump($otimeoffset); echo "<br/>";}
        //replace timoffset parameter inSQL
        $rptsql = str_replace('TIMEOFFSET','\''.$otimeoffset.'\'',$rptsql);

        //replace date parameters in SQL
        if ($rptdate == ''){
             $dt = new DateTime();
             $rptdate = $dt->format('Y-m-d');
        }
		else{
			$dt = new DateTime($rptdate);
		}
		
		$rptStartDate = $dt->format('Y-m-01');
		$rptsql = str_replace('PARAMDTSTART','\''.$rptStartDate.'\'',$rptsql);
		if (C_DEBUG) {echo "report start date: "; var_dump($rptStartDate); echo "<br/>";}
		
		$rptEndDate = $dt->format('Y-m-d');
        $rptsql = str_replace('PARAMDTEND','\''.$rptEndDate.'\'',$rptsql);
        if (C_DEBUG) {echo "report end date: "; var_dump($rptEndDate); echo "<br/>";}

/*
        // set date parameters
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
        //replace date parameters in SQL
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


//test data
//$str  = '{"ResultCode":0,"ResultMessage":"OK","GroupTurnover":[{"O_GRP_NAME":"БАР","O_GRP_ID":224,"O_GRP_PARENT_TOP":-1,"TURNOVER":129.82,"CURR_SIGN":"лв"},{"O_GRP_NAME":"ГИШЕ","O_GRP_ID":396,"O_GRP_PARENT_TOP":-1,"TURNOVER":263.95,"CURR_SIGN":"лв"},{"O_GRP_NAME":"КУХНЯ","O_GRP_ID":395,"O_GRP_PARENT_TOP":-1,"TURNOVER":465.52,"CURR_SIGN":"лв"}]}';

        // inititialize default erorr values
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
                  $TurnoverCurrency = '';
                  $GroupIdData = '';
                  $GroupIdParent = -1;
                  if (!empty($rptdata->GroupTurnover)) {$chartBars = count($rptdata->GroupTurnover);}
                  foreach($rptdata->GroupTurnover as $item) {
                   $TurnoverLabel .= '"'.str_replace('"', "'", $item->O_GRP_NAME_TOP).'",';
                   $TurnoverData  .= number_format((float)$item->TURNOVER, 2, '.', '').',';
                   $TurnoverCurrency = $item->CURR_SIGN;
                   $GroupIdData .= '"'.$item->O_GRP_ID_TOP.'",';
                   $GroupIdParent = $item->O_GRP_PARENT_TOP;
                  }
                  $TurnoverLabel = rtrim($TurnoverLabel, ",");
                  $TurnoverData = rtrim($TurnoverData, ",");
                  $GroupIdData = rtrim($GroupIdData, ",");
              } else {
                  $pDatabase->show_alert(ALERT_ERROR,$lang["AlertError"], $pDatabase->getTCPErrorMessage($rptdata->ResultCode, $lang),false,false);
                  $pDatabase->logevent(OPER_ERROR,$objectid,'report: '.$rname.' ResultCode: '.$rptdata->ResultCode.' ResultMessage: '.$rptdata->ResultMessage);
              } // end else ResultCode=
            } // end JSON error
        } // end else !$str
//    } // end check expire date
?>


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
         echo '&nbsp;•&nbsp;<a href="monthgroupturnover.php?lang=en"><img src="images/en.png" /></a>';
       } else {
         echo '&nbsp;•&nbsp;<a href="monthgroupturnover.php?lang=bg"><img src="images/bg.png" /></a>';
       }
       echo '&nbsp;•&nbsp;<a href="javascript:history.go(0)"><img src="images/refresh.png" alt="refresh"></a>';
      ?>

    </div>
    <h1 align="center"> <?php  echo $_SESSION['s_objectname']; ?> </h1>
    <h1 align="center"><?php echo $lang["rptGroupMonthlyTurnoverTitle"]; ?></h1>
    <?php
      if ($groupname<>"") {echo '<h3 align="center">'.$groupname.' ('.number_format($groupval, 2, '.', '') .' '.$TurnoverCurrency.')</h3>';}
    ?>

    <h4 align="center">
    <?php
		//echo $lang["rptRevenueLast5Days"].' '.date('d.m.Y',strtotime($rptStartDate)).' - '.date('d.m.Y',strtotime($rptEndDate));
		echo $lang["rptGroupTurnoverToDate"].(new DateTime($rptdate))->format('d.m.Y');
     ?>
    </h4>

        <div class="chart">
            <div>
                <canvas id="canvas" <?php if ($chartBars==1) {$chartBars = 2;} elseif ($chartBars == 2) {$chartBars=3;} elseif ($chartBars == 3) {$chartBars = 3.5;} echo 'height="'.C_CANVAS_HEIGHT*$chartBars.'"'; ?> ></canvas>
            </div>
        </div>
    <h4 align="center">
	<?php 
		echo $lang["rptRevenueLast5Days"].' '.date('d.m.Y',strtotime($rptStartDate)).' - '.date('d.m.Y',strtotime($rptEndDate));
		//echo $lang["rptGroupTurnoverCurrentDate"]; 
	?></
	h4>
    <br>


    <center>
    <table style="width:100%; line-height:22px;">
      <tr>
      <td></td>
       <td>
  <?php
    if ($expiredate >= time()){
		// show back button
		$_SESSION['parent_url'][$gid] = $_SERVER['REQUEST_URI'];

		if ($gid > -1) {
			if ($GroupIdParent == $gid) {
			$GroupIdParent = -1;
		}

		parse_str($_SESSION['parent_url'][$GroupIdParent], $urlparams);
		$urlparams['date'] = $rptdate;
		$urlparams = http_build_query($urlparams);

		echo '<center><a href="'.$_SERVER['PHP_SELF'].'?'.$urlparams.'" class="medium color-5 button" style="width:95%;">'.$lang["btnBack"].'</a></center>';

		}
    }

    if (C_DEBUG) {
        echo "rptdate: ".$rptdate; echo "<br/>";
        echo 'gid: '.var_dump($gid);echo "<br/>";
        echo 'GroupIdParent: '.var_dump($GroupIdParent);echo "<br/>";
        echo 'Back url: '.$_SESSION['parent_url'][$GroupIdParent]; echo "<br/>"; echo "<br/>";
        echo 'url array: '.var_dump($_SESSION['parent_url']);echo "<br/>";
    }
  ?>
       </td>
      <td></td>
      </tr>
      <tr>
           <?php
            if ($expiredate >= time()){
			    echo '<td align="center"><a href="';
				$urlparams = $_GET;
				$urlparams['date'] = (new DateTime($rptStartDate))->modify('-1 day')->format('Y-m-d');
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
				$thisMonth = (int)$today->format('m');

				$match_date = new DateTime($rptEndDate );
				$match_date->setTime( 0, 0, 0 );
				$matchDateMonth = (int)$match_date->modify('+1 day')->format('m');
				
				if($matchDateMonth - $thisMonth == 0){
					unset($_GET['date']);
				} 
				
				$urlparams = $_GET;
				
				if($matchDateMonth - $thisMonth != 0){
					$urlparams['date'] = (new DateTime($rptEndDate))->modify('+1 day')->format('Y-m-t');
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
    </center>
  </div>

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
                    hoverBackgroundColor : "rgba(255, 99, 132, 0.2)",
                    hoverBorderColor : "#ff6384"
                },{
                hidden: true,
                label: 'gid',
                backgroundColor: "rgba(151,187,205,0.5)",
                <?php echo  "data : [" . $GroupIdData . "]"; ?>
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
                },
            }
        };

   window.onload = function() {
     var ctx = document.getElementById("canvas").getContext("2d");
     window.myLine = new Chart(ctx, config);
   };


   <?php
    if ($expiredate >= time()){
		echo 'var canvas = document.getElementById("canvas");';
		echo 'canvas.onclick = function (evt) {var activePoints = myLine.getElementAtEvent(evt);';
        echo 'if (activePoints[0] != null){';
        echo 'window.location.href = "monthgroupturnover.php?id=';
		echo $objectid;
		if ($rptdate <> '') { echo '&date='.$rptdate; }
		echo '&gname=" +activePoints[0]._view.label+';
		echo "'&gval='+config.data.datasets[activePoints[0]._datasetIndex].data[activePoints[0]._index]+'&gid='+config.data.datasets[1].data[activePoints[0]._index];";
        echo '} };';
    } else {
		echo 'var canvas = document.getElementById("canvas");';
		echo 'canvas.onclick = function (evt) {var activePoints = myLine.getElementAtEvent(evt);';
        echo 'if (activePoints[0] != null){';
        echo 'show_exp();';
        echo '} };';
	}
   ?>
  </script>
</body>
</html>
