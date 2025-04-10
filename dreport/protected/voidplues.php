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
  .color-1 { background-color: #9e60ad; }
  .color-5 { background-color: #FBB03B; }
  
  .color-1:hover, .color-1:active  {
    background-color: #6f2f7f;  
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

    $C_SQL  = '{"Id":"DatabaseId", "Pass":"1234","VoidPluesOpen":{"Type":"Query","SQL":"SELECT BP.BP_OPERNUMB, BP.BP_OPERNAME, BP.BP_MARKDATETIME, BH.BH_BILLNUMB, BP.BP_NUMB, BP.BP_SHORTNAME, BP.BP_QUANTITY, BP.BP_MSRUNITNAME, BP.BP_SELLPRICE, BP.BP_SELLCURRABR, BP.BP_BUYPRICE, BP.BP_BUYCURRABR FROM POSBILLS_PLU BP LEFT JOIN POSBILLS_HEADER BH ON BP.BP_HDRID = BH.BH_ID WHERE (BH.BH_SELLID = 0)AND(BH.BH_ENDDATETIME IS NULL)AND(BH.BH_BILLNUMB > 0)AND (BH.BH_REVOKED_ = 0)AND(BP.BP_TYPE = \'P\')AND(BP.BP_REVOKED_ = 1) ORDER BY 1,2,3"},"VoidPluesClosed":{"Type":"Query","SQL":"SELECT O.OPERATOR_ID, O.OPERATOR_FULLNAME, SP.SPLU_DATETIME, SP.SPLU_BONNUMB, SP.SPLU_PLUNUMB, SP.SPLU_NAME, SP.SPLU_SOLDQUANT, M.PRIMARYMUNIT_NAME, SP.SPLU_SELLPRICE, SELL_CURRENCY.CURR_SIGN \"SELL_CURR\", SP.SPLU_BUYPRICE, BUY_CURRENCY.CURR_SIGN \"BUY_CURR\" FROM SALES_PLUES SP LEFT JOIN SALES_BON SB ON SP.SPLU_SELL_ID = SB.SELL_ID LEFT JOIN N_OPERATORS O ON SB.SELL_OPERATOR = O.OPERATOR_ID LEFT JOIN PLUES P ON SP.SPLU_PLUNUMB = P.PLU_NUMB LEFT JOIN N_PRIMARY_MEASUREUNITS M ON P.PLU_MEASUREUNIT_ID = M.PRIMARYMUNIT_ID LEFT JOIN N_CURRENCY BUY_CURRENCY ON SP.SPLU_BUYCURRENCY = BUY_CURRENCY.CURR_ID LEFT JOIN N_CURRENCY SELL_CURRENCY ON SP.SPLU_SELLCURRENCY = SELL_CURRENCY.CURR_ID WHERE (cast(SP.SPLU_DATETIME - cast(TIMEOFFSET as float)/24 as date) between START_DATE and END_DATE) AND (SP.SPLU_REVOKED_ = 1)AND(SB.SELL_REVOKED_ = 0) ORDER BY 1,2,3"}}';

    define("C_DEBUG", false);
    define("C_RPTNAME", "voidplues");
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

    if ($expiredate >= time()) {
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
<style>  
table#tBills th,td {
	border-color: 
  border-left: 1px solid #7C0A02;
  border-right: 1px solid #7C0A02;
  font-size: 12px;
  word-wrap: break-word;
  white-space: normal;
  padding: 5px;
  text-align: center; 

}

.saleRow td:nth-child(2),
.saleRow td:nth-child(3) {
  text-align: center;  
  padding: 3px;  
}

.saleRow td:nth-child(4) {
  text-align: right;  
  padding: 3px;  
}

/* Styles for itemsRow */
.itemsRow .tbItems th {
  text-align: center;  
  padding: 3px;  
}

.itemsRow .tbItems td:nth-child(1),
.itemsRow .tbItems td:nth-child(2),
.itemsRow .tbItems td:nth-child(3),
.itemsRow .tbItems td:nth-child(4) {
  text-align: center;  
  padding: 3px; 
  
}

.itemsRow .tbItems td:nth-child(5),
.itemsRow .tbItems td:nth-child(6) {
  text-align: right; 
  padding: 3px; 
  white-space: nowrap;  
}

table#tBills1 th {
  border: 1px solid #7C0A02;
  font-size: 12px;
  word-wrap: break-word;
  white-space: normal;
  padding: 5px;
  text-align: center;  
}

.salesRow1 td:nth-child(2),
.salesRow1 td:nth-child(3) {
  text-align: center;  
  padding: 3px; 
}

.salesRow1 td:nth-child(4) {
  text-align: right; 
  padding: 3px;  
}

.red.otables table#tBills1 tbody td:nth-child(4) {
  text-align: right !important;
}

.red.otables table#tBills tbody td:nth-child(4) {
  text-align: right !important;
}

/* Remove borders from buttons and images */
.saleRow td img, .saleRow1 td img {
  border: none;
  outline: none;
  display: block;
}

.saleRow, .saleRow1 {
  margin: 0;
  padding: 0;
  border: none;
}

td{
	margin: 0;
	padding:0;
	border: none;
}

 
</style>


 
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
         echo '&nbsp;•&nbsp;<a href="voidplues.php?lang=en"><img src="images/en.png" /></a>';
       } else {
         echo '&nbsp;•&nbsp;<a href="voidplues.php?lang=bg"><img src="images/bg.png" /></a>';
       }
       echo '&nbsp;•&nbsp;<a href="javascript:history.go(0)"><img src="images/refresh.png" alt="refresh"></a>';

      ?>

    </div>
    <h1 align="center"> <?php  echo $_SESSION['s_objectname']; ?> </h1>
    <h1 align="center"><?php echo $lang["rptVoidPluesTitle"]; ?></h1>
    <h1 align="center"><?php echo $lang["rptVoidPluesOpenBills"]; ?></h1>
    <h4 align="center"><?php echo $lang["rptOpenbillsToDate"].date('d.m.Y H:i', time()); ?></h4>

    <?php
      $voidcount = 0;
      $voidsum = 0;
      $CurrencySign = '';
	  
	  // VoidPluesOpen
      if (!empty($rptdata->VoidPluesOpen)) {
   	     $rptdataDetail = $rptdata->VoidPluesOpen;
   	     //bills table header
         echo '<div class="otables red"><table id="tBills">';
         //echo ' <thead><tr><th></th><th>Оператор</th><th>Брой</th><th>Сума</th></tr></thead>';
		 echo $lang["rptVoidPluesHeader"]; 
         echo '  <tbody>';
         // get uniqiue operators
         $operators = array();
         foreach ($rptdata->VoidPluesOpen as $each) {
           $operators[$each->BP_OPERNUMB] = true;
         }
		 $operators = array_keys($operators);
         // get header data for every operator
		 //echo "$oper\n";
		 foreach ($operators as $oper) {
          $voidcount = 0;
          $voidsum = 0;
		  $CurrencySign = '';		 
          foreach($rptdata->VoidPluesOpen as $bill) {	 
            if ($bill->BP_OPERNUMB ==  $oper) {
             $voidsum = $voidsum + (float)$bill->BP_SELLPRICE*$bill->BP_QUANTITY;
             $CurrencySign = $bill->BP_SELLCURRABR; 		  
			 $voidcount = $voidcount + 1;
			 //$dataheader = '<td width="20"></td><td nowrap>'.$bill->BP_OPERNAME.'</td><td nowrap>'.$voidcount.'</td><td nowrap>'.number_format((float)$voidsum, 2, '.', '').' '.$CurrencySign.'</td>';
			 $dataheader = '<td width="20"></td><td>'.$bill->BP_OPERNAME.'</td><td>'.$voidcount.'</td><td>'.number_format((float)$voidsum, 2, '.', '').' '.$CurrencySign.'</td>';
		    }//if
		  }//foreach rptdata
          //print operator header  
          echo '<tr class="saleRow">';
		  echo $dataheader;
		  echo '</tr>';
           // load detail data
           echo '<tr class="itemsRow red">';
           echo ' <td colspan="5">';
           echo '  <table class="tbItems">';
           //echo '   <thead><tr><th>Сметка</th><th>Дата</th><th>Артикул име</th><th>Кол.</th><th>ед.цена</th><th>сума</th></tr></thead>';
		   echo $lang["rptVoidPluesDetails"];
           echo '<tbody>';
           //add plu data for this bill
           foreach($rptdataDetail as $item) {
             if ($oper ==  $item->BP_OPERNUMB) {
			  //var_dump($item);	 
              echo '<tr><td>'.$item->BH_BILLNUMB.'</td><td nowrap>'.date("d.m.y H:i", strtotime($item->BP_MARKDATETIME)).'</td><td>'.$item->BP_SHORTNAME.'</td><td nowrap>'.$item->BP_QUANTITY.' '.$item->BP_MSRUNITNAME.'</td><td nowrap>'.number_format((float)$item->BP_SELLPRICE, 2, '.', '').' '.$item->BP_SELLCURRABR.'</td><td nowrap>'.number_format((float)$item->BP_SELLPRICE*$item->BP_QUANTITY, 2, '.', '').' '.$item->BP_SELLCURRABR.'</td></tr>';
            } // end if
           } // end foreach OpenBillsP
           echo '</tbody></table></td></tr>';	
         }// foreach operator
		 
         echo '</tbody></table></div>';

          $voidcount = 0;
          $voidsum = 0;
		  $CurrencySign = '';		 
          foreach($rptdata->VoidPluesOpen as $bill) {	 
             $voidsum = $voidsum + (float)$bill->BP_SELLPRICE*$bill->BP_QUANTITY;
             $CurrencySign = $bill->BP_SELLCURRABR; 		  
			 $voidcount = $voidcount + 1;
		  }//foreach
      } // end if empty 
	  
	?>  
    <h4 align="center"><?php echo $lang["rptVoidPluesCount"].$voidcount; ?></h4>
    <h4 align="center"><?php echo $lang["rptVoidPluesSum"].number_format((float)$voidsum, 2, '.', '').' '.$CurrencySign; ?></h4>
    
	<br>

    <h1 align="center"><?php echo $lang["rptVoidPluesClosedBills"]; ?></h1>
    <h4 align="center"><?php echo $lang["rptVoidPluesClosedDates"].' '.date('d.m.Y',strtotime($rptstartdate)).' - '.date('d.m.Y',strtotime($rptenddate)); ?></h4>
	
	<?php
      $voidcount = 0;
      $voidsum = 0;
      $CurrencySign = '';
	  
	  // VoidPluesClosed
      if (!empty($rptdata->VoidPluesClosed)) {
     	 $rptdataDetail = $rptdata->VoidPluesClosed;
		 //bills table header
         echo '<div class="otables red"><table id="tBills1">';
         //echo ' <thead><tr><th></th><th>Оператор</th><th>Брой</th><th>Сума</th></tr></thead>';
		 echo $lang["rptVoidPluesHeader"]; 
         echo '  <tbody>';
         // get uniqiue operators
         $operators = array();
         foreach ($rptdata->VoidPluesClosed as $each) {
           $operators[$each->OPERATOR_ID] = true;
         }
		 $operators = array_keys($operators);
         // get header data for every operator
		 //echo "$oper\n";
		 foreach ($operators as $oper) {
          $voidcount = 0;
          $voidsum = 0;
		  $CurrencySign = '';		 
          foreach($rptdata->VoidPluesClosed as $bill) {	 
            if ($bill->OPERATOR_ID ==  $oper) {
             $voidsum = $voidsum + (float)$bill->SPLU_SELLPRICE*$bill->SPLU_SOLDQUANT;
             $CurrencySign = $bill->SELL_CURR; 		  
			 $voidcount = $voidcount + 1;
			 $dataheader = '<td width="20"></td><td nowrap>'.$bill->OPERATOR_FULLNAME.'</td><td nowrap>'.$voidcount.'</td><td nowrap>'.number_format((float)$voidsum, 2, '.', '').' '.$CurrencySign.'</td>';
		    }//if
		  }//foreach rptdata
          //print operator header  
          echo '<tr class="saleRow1">';
		  echo $dataheader;
		  echo '</tr>';
           // load detail data
           echo '<tr class="itemsRow red">';
           echo ' <td colspan="5">';
           echo '  <table class="tbItems">';
		   echo $lang["rptVoidPluesDetails"];
           echo '<tbody>';
           //add plu data for this bill
           foreach($rptdataDetail as $item) {
             if ($oper ==  $item->OPERATOR_ID) {
			  //var_dump($item);	 
              echo '<tr><td>'.$item->SPLU_BONNUMB.'</td><td nowrap>'.date("d.m.y H:i", strtotime($item->SPLU_DATETIME)).'</td><td>'.$item->SPLU_NAME.'</td><td nowrap>'.$item->SPLU_SOLDQUANT.' '.$item->PRIMARYMUNIT_NAME.'</td><td nowrap>'.number_format((float)$item->SPLU_SELLPRICE, 2, '.', '').' '.$item->SELL_CURR.'</td><td nowrap>'.number_format((float)$item->SPLU_SELLPRICE*$item->SPLU_SOLDQUANT, 2, '.', '').' '.$item->SELL_CURR.'</td></tr>';
            } // end if
           } // end foreach OpenBillsP
           echo '</tbody></table></td></tr>';	
         }// foreach operator
		 
         echo '</tbody></table></div>';

          $voidcount = 0;
          $voidsum = 0;
		  $CurrencySign = '';		 
          foreach($rptdata->VoidPluesClosed as $bill) {	 
             $voidsum = $voidsum + (float)$bill->SPLU_SELLPRICE*$bill->SPLU_SOLDQUANT;
             $CurrencySign = $bill->SELL_CURR; 		  
			 $voidcount = $voidcount + 1;
		  }//foreach
      } // end if empty 
	
    ?>
    <h4 align="center"><?php echo $lang["rptVoidPluesCount"].$voidcount; ?></h4>
    <h4 align="center"><?php echo $lang["rptVoidPluesSum"].number_format((float)$voidsum, 2, '.', '').' '.$CurrencySign; ?></h4>

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


