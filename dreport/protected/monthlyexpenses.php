<html>
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=EmulateIE11" />
  <title>Detelina Reports</title>
  <link rel="stylesheet" href="css/style.css">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="css/w3.css">

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
  .color-1 { background-color: #a9c352; }
  .color-5 { background-color: #FBB03B; }
  
  .color-1:hover, .color-1:active  {
    background-color: #6b7c32;  
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

    $C_SQL  = '{"Id":"DatabaseId","Pass":"1234","MonthlyExpensesHeader":{"Type":"Query", "SQL":"SELECT SP.SUPP_PLUNUMB, SP.SUPP_PLUNAME, SUM(SP.SUPP_SUPQUANT) \"QTY\", AVG(SP.SUPP_BUYPRICE) \"PRCE\", SUM(SP.SUPP_SUPQUANT * SP.SUPP_BUYPRICE) \"TOTAL\" FROM SUPPLIES_PLU SP LEFT JOIN SUPPLIES S ON SP.SUPP_DOCID = S.SUP_ID LEFT JOIN PLUES P ON SP.SUPP_PLUNUMB = P.PLU_NUMB WHERE (SP.SUPP_DATETIME BETWEEN START_DATE AND END_DATE ) AND (P.PLU_GROUP_ID = 84) AND (S.SUP_TYPE = 1) AND (S.SUP_REVOKED_ = 0)AND (SP.SUPP_REVOKED_ = 0 ) GROUP BY SP.SUPP_PLUNUMB, SP.SUPP_PLUNAME ORDER BY SP.SUPP_PLUNAME;"},"MonthlyExpensesDetails":{"Type":"Query","SQL":"SELECT SP.SUPP_PLUNUMB, SP.SUPP_PLUNAME, SP.SUPP_DATETIME, SP.SUPP_SUPQUANT \"QTY\", SP.SUPP_BUYPRICE \"PRCE\", SP.SUPP_SUPQUANT * SP.SUPP_BUYPRICE \"TOTAL\" FROM SUPPLIES_PLU SP LEFT JOIN SUPPLIES S ON SP.SUPP_DOCID = S.SUP_ID LEFT JOIN PLUES P ON SP.SUPP_PLUNUMB = P.PLU_NUMB WHERE (SP.SUPP_DATETIME BETWEEN START_DATE AND END_DATE ) AND (P.PLU_GROUP_ID=84) AND(S.SUP_TYPE=1) AND(S.SUP_REVOKED_=0) AND(SP.SUPP_REVOKED_=0) ORDER BY SP.SUPP_PLUNAME, SP.SUPP_DATETIME;"}}';

    define("C_DEBUG", false);
    define("C_RPTNAME", "monthlyExpenses");
    $rname = C_RPTNAME;
    
    include_once 'language.php';
    // show loader for slow connections
    require_once ('class.loading.div.php');
    $divLoader = new loadingDiv;
    $divLoader->loader();

    $objectid = "";
    if(isset($_GET["id"]))
	{
		$objectid = $_GET["id"];
    }
    else 
	{
		$objectid = $_SESSION['s_objectid'];
    }
	
    $_SESSION['s_objectid'] = $objectid;

    if(isset($_SESSION['s_deviceid']))
	{
		$deviceid = $_SESSION['s_deviceid'];
    }
    else 
	{
		$deviceid = "0";
    }
	
    $rptdate = "";
    if(isset($_GET["date"]))
	{
		$rptdate = $_GET["date"];
    }   

    include('database.class.php');
    $pDatabase = Database::getInstance();
    $result = $pDatabase->query("set names 'utf8'");

    //check expire date
    $qry = $pDatabase->query("SELECT s_customername, s_expiredate FROM t_subscriptions WHERE s_objectid = '".sql_safe($_SESSION['s_objectid'])."'");
    while ($row = mysqli_fetch_assoc($qry)) 
	{
		$expiredate = strtotime($row['s_expiredate']);
		$customername = $row['s_customername'];
    }

    if ($expiredate >= time()) {
        $objectpswd = "";
        $rptsql = "";
        $rname = "";
        
        $rptsql = $C_SQL;
        $rname = C_RPTNAME;
 
        $qry = $pDatabase->query("select d_objectpswd,d_timeoffset from t_devices where d_deviceid = '$deviceid' and d_objectid='".sql_safe($objectid)."' ;");

        while ($row = mysqli_fetch_assoc($qry)) 
		{
			$objectpswd = $row['d_objectpswd'];
			$otimeoffset = $row['d_timeoffset'];
        }
        if (C_DEBUG) {echo "Time offset: "; var_dump($otimeoffset); echo "<br/>";}

         // set data parameters
		if ($rptdate == '')
		{ 
			$dt = new DateTime();
			$rptdate = $dt->format('Y-m-d');
		} 
		else 
		{
			$dt = new DateTime($rptdate);
		}

		$dt->setTime( 0, 0, 0 );
		if (C_DEBUG) {echo "Report date: "; var_dump($rptdate); echo "<br/>";}
		
		$rptstartdate = $dt->format('Y-m-01');
        if (C_DEBUG) {echo "StartDate: ".$rptstartdate; echo "<br/>";}	
        
		$dt->add(new DateInterval('PT'.(24+$otimeoffset).'H'));
        $rptenddate = $dt->format('Y-m-d');
        if (C_DEBUG) {echo "EndDate: ".$rptenddate; echo "<br/>";}         
		
		$dt->sub(new DateInterval('PT'.(24+$otimeoffset).'H'));
        
        //$rptstartdate = $dt->format('Y-m-01');
        //if (C_DEBUG) {echo "StartDate: ".$rptstartdate; echo "<br/>";}	
		
		//replace timeoffset
		$rptsql = str_replace('TIMEOFFSET',$otimeoffset,$rptsql);	
		
        //replace parameters in SQL 
        $rptsql = str_replace('START_DATE','\''.$rptstartdate.'\'',$rptsql);
        $rptsql = str_replace('END_DATE','\''.$rptenddate.'\'',$rptsql);
                  
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
//result example
/* 
		 $str  = '{"ResultCode":0,"ResultMessage":"OK","VoidPluesOpen":[';
		 $str .= '{"BP_OPERNUMB":40,"BP_OPERNAME":"Мария Иванова","BP_MARKDATETIME":"13.09.2017 15:49:11","BH_BILLNUMB":1,"BP_NUMB":1,"BP_SHORTNAME":"Test PLU","BP_QUANTITY":1,"BP_MSRUNITNAME":"Бр","BP_SELLPRICE":1,"BP_SELLCURRABR":"BGN","BP_BUYPRICE":0,"BP_BUYCURRABR":"BGN"},';
		 $str .= '{"BP_OPERNUMB":40,"BP_OPERNAME":"Мария Иванова","BP_MARKDATETIME":"13.09.2017 15:49:13","BH_BILLNUMB":1,"BP_NUMB":1,"BP_SHORTNAME":"Test PLU","BP_QUANTITY":1,"BP_MSRUNITNAME":"Бр","BP_SELLPRICE":1,"BP_SELLCURRABR":"BGN","BP_BUYPRICE":0,"BP_BUYCURRABR":"BGN"},';
		 $str .= '{"BP_OPERNUMB":41,"BP_OPERNAME":"Иван Петров","BP_MARKDATETIME":"12.09.2017 15:49:13","BH_BILLNUMB":1,"BP_NUMB":1,"BP_SHORTNAME":"Test PLU","BP_QUANTITY":1,"BP_MSRUNITNAME":"Бр","BP_SELLPRICE":1,"BP_SELLCURRABR":"BGN","BP_BUYPRICE":0,"BP_BUYCURRABR":"BGN"},';
		 $str .= '{"BP_OPERNUMB":41,"BP_OPERNAME":"Иван Петров","BP_MARKDATETIME":"12.09.2017 15:49:13","BH_BILLNUMB":1,"BP_NUMB":1,"BP_SHORTNAME":"Test PLU","BP_QUANTITY":1,"BP_MSRUNITNAME":"Бр","BP_SELLPRICE":1,"BP_SELLCURRABR":"BGN","BP_BUYPRICE":0,"BP_BUYCURRABR":"BGN"},';
		 $str .= '{"BP_OPERNUMB":41,"BP_OPERNAME":"Иван Петров","BP_MARKDATETIME":"13.09.2017 15:49:13","BH_BILLNUMB":1,"BP_NUMB":1,"BP_SHORTNAME":"Test PLU","BP_QUANTITY":1,"BP_MSRUNITNAME":"Бр","BP_SELLPRICE":1,"BP_SELLCURRABR":"BGN","BP_BUYPRICE":0,"BP_BUYCURRABR":"BGN"},';
		 $str .= '{"BP_OPERNUMB":41,"BP_OPERNAME":"Иван Петров","BP_MARKDATETIME":"13.09.2017 15:49:13","BH_BILLNUMB":1,"BP_NUMB":1,"BP_SHORTNAME":"Test PLU","BP_QUANTITY":1,"BP_MSRUNITNAME":"Бр","BP_SELLPRICE":1,"BP_SELLCURRABR":"BGN","BP_BUYPRICE":0,"BP_BUYCURRABR":"BGN"},';
		 $str .= '{"BP_OPERNUMB":42,"BP_OPERNAME":"Георги","BP_MARKDATETIME":"14.09.2017 15:49:13","BH_BILLNUMB":1,"BP_NUMB":1,"BP_SHORTNAME":"Test PLU","BP_QUANTITY":1,"BP_MSRUNITNAME":"Бр","BP_SELLPRICE":1,"BP_SELLCURRABR":"BGN","BP_BUYPRICE":0,"BP_BUYCURRABR":"BGN"}';
		 $str .= '],"VoidPluesClosed":[{"OPERATOR_ID":40,"OPERATOR_FULLNAME":"","SPLU_DATETIME":"13.09.2017 15:50:42","SPLU_BONNUMB":10,"SPLU_PLUNUMB":1,"SPLU_NAME":"Test PLU","SPLU_SOLDQUANT":1,"PRIMARYMUNIT_NAME":"Бр","SPLU_SELLPRICE":1,"SELL_CURR":"лв","SPLU_BUYPRICE":0,"BUY_CURR":"лв"},{"OPERATOR_ID":40,"OPERATOR_FULLNAME":"","SPLU_DATETIME":"13.09.2017 15:50:42","SPLU_BONNUMB":10,"SPLU_PLUNUMB":1,"SPLU_NAME":"Test PLU","SPLU_SOLDQUANT":1,"PRIMARYMUNIT_NAME":"Бр","SPLU_SELLPRICE":1,"SELL_CURR":"лв","SPLU_BUYPRICE":0,"BUY_CURR":"лв"},{"OPERATOR_ID":40,"OPERATOR_FULLNAME":"","SPLU_DATETIME":"14.09.2017 17:21:21","SPLU_BONNUMB":12,"SPLU_PLUNUMB":1,"SPLU_NAME":"Test PLU","SPLU_SOLDQUANT":1,"PRIMARYMUNIT_NAME":"Бр","SPLU_SELLPRICE":1,"SELL_CURR":"лв","SPLU_BUYPRICE":0,"BUY_CURR":"лв"},{"OPERATOR_ID":40,"OPERATOR_FULLNAME":"","SPLU_DATETIME":"14.09.2017 17:21:21","SPLU_BONNUMB":12,"SPLU_PLUNUMB":2,"SPLU_NAME":"PLU 2","SPLU_SOLDQUANT":1,"PRIMARYMUNIT_NAME":"Бр","SPLU_SELLPRICE":2,"SELL_CURR":"лв","SPLU_BUYPRICE":0,"BUY_CURR":"лв"},{"OPERATOR_ID":40,"OPERATOR_FULLNAME":"","SPLU_DATETIME":"14.09.2017 17:21:22","SPLU_BONNUMB":12,"SPLU_PLUNUMB":3,"SPLU_NAME":"PLU 3","SPLU_SOLDQUANT":1,"PRIMARYMUNIT_NAME":"Бр","SPLU_SELLPRICE":3,"SELL_CURR":"лв","SPLU_BUYPRICE":0,"BUY_CURR":"лв"}]}';
*/
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
              } else {
                  $pDatabase->show_alert(ALERT_ERROR,$lang["AlertError"],$pDatabase->getTCPErrorMessage($rptdata->ResultCode,$lang),false,false);
                  $pDatabase->logevent(OPER_ERROR,$objectid,'report: '.$rname.' ResultCode: '.$rptdata->ResultCode.' ResultMessage: '.$rptdata->ResultMessage);
              } // end else ResultCode=
            } // end JSON error
        } // end else !$str
    } // end check expire date
?>
</head>

<body>
  <div class="login-card">
    <div class="login-help">
      <?php
       if ($expiredate < time()){
         $pDatabase->show_alert(ALERT_WARNING,$lang["AlertWarning"],$lang["errObjectExpired"].date("d.m.Y", $expiredate),false,false);
         echo '<div><center><a href="rptlist.php" class="medium color-5 button">'.$lang["btnExit"].'</a></center></div>';
         $pDatabase->logevent(OPER_ERROR,$objectid,'report: '.$rname.' error: '.$lang["errObjectExpired"].date("d.m.Y", $expiredate));
         exit;
        }
       echo '<a>'.$customername.'</a>';

       if ($_SESSION['lang'] == 'bg'){
         echo '&nbsp;•&nbsp;<a href="monthlyexpenses.php?lang=en"><img src="images/en.png" /></a>';
       } else {
         echo '&nbsp;•&nbsp;<a href="monthlyexpenses.php?lang=bg"><img src="images/bg.png" /></a>';
       }
       echo '&nbsp;•&nbsp;<a href="javascript:history.go(0)"><img src="images/refresh.png" alt="refresh"></a>';

      ?>

    </div>
    <h1 align="center"> <?php  echo $_SESSION['s_objectname']; ?> </h1>
    <h1 align="center">
	<?php 
		echo $lang["rptMonthlyExpenses"]; 
		
		$lastMonthDt = new DateTime();
		$lastMonthDt->setDate((int)$dt->format("Y"), (int)$dt->format("m"), 1);
		
		echo strftime(" %b %Y", $lastMonthDt->getTimestamp());
	?>
	</h1>
	<p class="val_money">
	<?php
		$rptHeader = $rptdata->MonthlyExpensesHeader;
		$rptDetails = $rptdata->MonthlyExpensesDetails;
		 
		$CurrencySign = 'лв.';
		
		$currentExpenses = 0;
		foreach($rptHeader as $item)
		{
			$currentExpenses += $item->TOTAL;
		}
		
		if (C_DEBUG) {echo "Total Expenses: "; var_dump($currentExpenses); echo "<br/>";}
		
		echo number_format((float)$currentExpenses, 2, '.', '').' '.$CurrencySign;
	?>
	</p>

    <?php
      $voidcount = 0;
      $voidsum = 0;
	  
	  // MonthlyExpensesHeader
	  
      if (!empty($rptdata->MonthlyExpensesHeader)) 
	  {
	   	//bills table header
        echo '<div class="otables red"><table id="tBills">';
        //echo ' <thead><tr><th></th><th>Оператор</th><th>Брой</th><th>Сума</th></tr></thead>';
		 		 
		echo $lang['rptMonthlyExpensesHeaderTHEAD'];
		echo '  <tbody>';
		foreach($rptHeader as $each)
		{
			echo '<tr class="saleRow">';
			echo '<td width="20"></td><td>'.$each->SUPP_PLUNAME.'</td><td>'.$each->QTY.'</td><td>'.number_format((float)$each->TOTAL, 2, '.', '').' '.$CurrencySign.'</td>';
			echo '</tr>';
			echo '<tr class="itemsRow red">';
			echo '<td colspan="5">';
			echo '<table class="tbItems">';
			echo $lang['rptMonthlyExpensesDetailsTHEAD'];
			echo '<tbody>';
			foreach($rptDetails as $detail)
			{
				if($each->SUPP_PLUNUMB == $detail->SUPP_PLUNUMB)
				{
					echo '<tr>';
					echo '<td>'.$detail->SUPP_PLUNAME.'</td><td>'.$detail->SUPP_DATETIME.'</td><td>'.$detail->QTY.'</td><td>'.number_format((float)$detail->PRCE, 2, '.', '').' '.$CurrencySign.'</td><td>'.number_format((float)$detail->TOTAL, 2, '.', '').' '.$CurrencySign.'</td>';
					echo '</tr>';
				}
			}
			echo '</tbody></table></td></tr>';
		}
		 
		echo '</tbody></table></div>';
		
	} // end if empty 
	  
	?>  
    
    <br>
    <table style="width:100%; line-height:22px;">
		<tr>
        
			<?php 
				if ($expiredate >= time())
				{		   		   
					echo '<td align="center"><a href="';
					$urlparams = $_GET;
					$urlparams['date'] = (new DateTime($rptstartdate))->modify('-1 day')->format('Y-m-d');
					$urlparams = http_build_query($urlparams);
					echo $_SERVER['PHP_SELF']."?".$urlparams; 
					echo '" class="medium color-1 button"><<</a></td>';
				} 
				else 
				{
					echo '<td align="center"><a href="#" onclick="show_exp()" class="medium color-1 button"><<</a></td>';				
				}  		   
           ?> 
        
			<td align="center" style="width:60%;"><a href="rptlist.php" class="medium color-5 button" style="width:95%;"><?php echo $lang["btnExit"]; ?></a></td>
			
			<?php 
				if ($expiredate >= time())
				{		   		   
					echo '<td align="center"><a href="';
					
					$today = new DateTime(); 
					$today->setTime( 0, 0, 0 ); 
					$thisMonth = (int)$today->format('m');

					$match_date = new DateTime($rptenddate);
					$match_date->setTime( 0, 0, 0 );
					$matchDateMonth = (int)$match_date->format('m');
					
					if($matchDateMonth - $thisMonth == 0)
					{
						unset($_GET['date']);
					}
					
					$urlparams = $_GET;
					
					if($matchDateMonth - $thisMonth != 0)
					{
						$urlparams['date'] = (new DateTime($rptenddate))->modify('+1 day')->format('Y-m-t');
					}
					
					$urlparams = http_build_query($urlparams);
					echo $_SERVER['PHP_SELF']."?".$urlparams; 		   
					echo '" class="medium color-1 button">>></a></td>';
				} 
				else 
				{
					echo '<td align="center"><a href="#" onclick="show_exp()" class="medium color-1 button">>></a></td>';				
				}  
           ?> 
          
      </tr>
    </table>

  </div>

 <script>
   $(".saleRow td:nth-child(1)").html("<img src='images/open.png' class='btnDetail'/>");
   $(".saleRow1 td:nth-child(1)").html("<img src='images/open.png' class='btnDetail1'/>");

   $(".btnDetail").click(function(){
       var index = $(this).parent().parent().index();
       var detail = $(this).parent().parent().next();
       var status = $(detail).css("display");
       if(status == "none") {
           $(detail).css("display", "table-row");
           document.getElementById("tBills").rows[index+1].cells[0].firstElementChild.src = 'images/collapse.png';
       } else {
           $(detail).css("display", "none");
           document.getElementById("tBills").rows[index+1].cells[0].firstElementChild.src = 'images/open.png';
       }
   });

   $(".btnDetail1").click(function(){
       var index = $(this).parent().parent().index();
       var detail = $(this).parent().parent().next();
       var status = $(detail).css("display");
       if(status == "none") {
           $(detail).css("display", "table-row");
           document.getElementById("tBills1").rows[index+1].cells[0].firstElementChild.src = 'images/collapse.png';
       } else {
           $(detail).css("display", "none");
           document.getElementById("tBills1").rows[index+1].cells[0].firstElementChild.src = 'images/open.png';
       }
   });
</script>

</body>
</html>


