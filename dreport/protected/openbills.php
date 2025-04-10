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

<?php
    session_start();
    if(!isset($_SESSION['s_authenticated']) || empty($_SESSION['s_authenticated'])){
        header("location:../index.php");
    }

    $C_SQL  = '{"Id":"DatabaseId", "Pass":"1234","OpenBillsH":{"Type":"Query","SQL":"SELECT H.BH_ID AS A, H.BH_BILLNUMB AS B, H.BH_STARTDATETIME AS C, H.BH_SUM AS D, CH.CURR_SIGN AS E, H.BH_OPENTERMINAL AS F, H.BH_OPERNUMB AS G, H.BH_OPERNAME AS H FROM POSBILLS_HEADER H INNER JOIN N_CURRENCY CH ON H.BH_CURRENCYID = CH.CURR_ID WHERE (H.BH_SELLID = 0)AND(H.BH_ENDDATETIME IS NULL)AND(H.BH_REVOKED_ = 0)AND(H.BH_BILLNUMB > 0)AND(H.BH_PLUCOUNT > 0) ORDER BY H.BH_STARTDATETIME ASC"},"OpenBillsP":{"Type":"Query","SQL":"SELECT P.BP_HDRID AS I, P.BP_NUMB AS J, P.BP_SHORTNAME AS K, P.BP_QUANTITY AS L, P.BP_MSRUNITNAME AS M, P.BP_QUANTITY * (P.BP_SELLPRICE + (P.BP_SELLPRICE * P.BP_DISCOUNT / 100)) / (BP_QUANTITY + 0.0000001) AS N, P.BP_QUANTITY * (P.BP_SELLPRICE + (P.BP_SELLPRICE * P.BP_DISCOUNT / 100)) AS O, CP.CURR_SIGN AS P FROM POSBILLS_PLU P INNER JOIN POSBILLS_HEADER H ON P.BP_HDRID = H.BH_ID INNER JOIN N_CURRENCY CP ON P.BP_SELLCURRID = CP.CURR_ID WHERE (H.BH_SELLID = 0)AND(H.BH_ENDDATETIME IS NULL)AND(H.BH_REVOKED_ = 0)AND(H.BH_BILLNUMB > 0)AND(UPPER(P.BP_TYPE)=\'P\') ORDER BY H.BH_STARTDATETIME ASC, P.BP_MARKDATETIME ASC"}}';

    define("C_DEBUG", false);
    define("C_RPTNAME", "openbills");
    $rname = C_RPTNAME;
    
    include_once 'language.php';
    // show loader for slow connections
    require_once ('class.loading.div.php');
    $divLoader = new loadingDiv;
    $divLoader->loader();

    $objectid = "";
    if(isset($_GET["id"])){
        $objectid = $_GET["id"];
    } else {
        $objectid = $_SESSION['s_objectid'];
    }
    $_SESSION['s_objectid'] = $objectid;

    if(isset($_SESSION['s_deviceid'])){
        $deviceid = $_SESSION['s_deviceid'];
    } else {
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
        
        $rptsql = $C_SQL;
        $rname = C_RPTNAME;

        $qry = $pDatabase->query("select d_objectpswd,d_timeoffset from t_devices where d_deviceid = '$deviceid' and d_objectid='".sql_safe($objectid)."' ;");
        while ($row = mysqli_fetch_assoc($qry)) {
            $objectpswd = $row['d_objectpswd'];
        }

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
                } else {
                    $pDatabase->show_alert(ALERT_ERROR,$lang["AlertError"],$pDatabase->getTCPErrorMessage($rptdata->ResultCode,$lang),false,false);
                    $pDatabase->logevent(OPER_ERROR,$objectid,'report: '.$rname.' ResultCode: '.$rptdata->ResultCode.' ResultMessage: '.$rptdata->ResultMessage);
                } // end else ResultCode=
            } // end JSON error
        } // end else !$str
    } // end check expire date
?>
<style>
table#tBills th, table#tBills td {
  border-left: 1px solid #90EE90;
  border-right: 1px solid #90EE90;
  font-size: 12px;
  border-collapse: separate;  
  border-spacing: 1px;  
}

table#tBills th:first-child, table#tBills td:first-child {
  width: 30px;  
  text-align: center;  
  padding: 4px; 
}

table#tBills th:nth-child(2), table#tBills td:nth-child(2) {  
  width: 10%;  
  text-align: center; 
}

table#tBills th:nth-child(3), table#tBills td:nth-child(3) {  
  width: 40px;  
  text-align: center;  
  padding: 4px;  
  white-space: normal;  
}

table#tBills th:nth-child(4),
table#tBills td:nth-child(4) { 
  width :30%;
  padding: 1px; 
  white-space: normal; 
}

table#tBills th:nth-child(4) {
  text-align: center;
}

table#tBills th:nth-child(5), table#tBills td:nth-child(5) { 
  text-align: center;  
  padding: 1px;  
  white-space: nowrap; 
}

.saleRow td:nth-child(1) {
  text-align: center;
  width: 30px;  
  padding: 3px;
}

table.tbItems th {
  font-size: 10px !important;  
  padding: 3px !important;  
}

.saleRow td:nth-child(4), .saleRow td:nth-child(5) {
  padding: 3px;  
  text-align: center;
}

table#tBills th:nth-child(5) {
  width: 7%;
  padding: 1px;
  text-align: center !important;  
}

table#tBills td:nth-child(5) {
  width: 7%;
  padding: 3px;
  text-align: right !important;  
  white-space: nowrap; 
}

.itemsRow td {
  padding: 1px;
  font-size: 10px !important;  
}

.itemsRow .tbItems th:nth-child(2),
.itemsRow .tbItems td:nth-child(2),
.itemsRow .tbItems th:nth-child(3),
.itemsRow .tbItems td:nth-child(3) {
  width: auto !important; 
}

.itemsRow .tbItems th:nth-child(4),
.itemsRow .tbItems td:nth-child(4) {
  width: auto !important;  
  padding: 1px !important; 
  text-align: right !important;
}

.itemsRow .tbItems th:nth-child(4) { 
  text-align: center !important;
}


.itemsRow .tbItems td:nth-child(1) {
  width: auto !important;  
  padding: 1px !important;  
  text-align: left !important; 
  word-wrap: break-word;
}
.itemsRow .tbItems th:nth-child(1){
  width: auto !important;  
  padding: 1px !important;  
  text-align: left !important; 
  word-wrap: break-word;
}

.itemsRow .tbItems td:nth-child(2),
.itemsRow .tbItems td:nth-child(3) {
  padding: 1px !important;  
  text-align: right !important;  
}

.itemsRow .tbItems th:nth-child(2),
.itemsRow .tbItems th:nth-child(3) {
  padding: 1px !important;  
  text-align: center !important;  
}
  .button {
    display: inline-block;  
    border-radius: 20px;
    font-size: 14px;
    text-align: center; 
    color: white;
	transition: background-color 0.3s ease;
	box-shadow: rgb(38, 70, 83) 0px 11px 8px -5px;
  }
  .color-1 { background-color: #a085e0; }
  .color-5 { background-color: #FBB03B; }
  
  .color-1:hover, .color-1:active  {
    background-color: #147e5b;  
  }  
  .color-5:hover, .color-5:active  {
    background-color: #e18e0a;  
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
         echo '&nbsp;•&nbsp;<a href="openbills.php?lang=en"><img src="images/en.png" /></a>';
       } else {
         echo '&nbsp;•&nbsp;<a href="openbills.php?lang=bg"><img src="images/bg.png" /></a>';
       }
       echo '&nbsp;•&nbsp;<a href="javascript:history.go(0)"><img src="images/refresh.png" alt="refresh"></a>';

      ?>

    </div>
    <h1 align="center"> <?php echo $_SESSION['s_objectname']; ?> </h1>
    <h1 align="center"><?php echo $lang["rptOpenbillsTitle"]; ?></h1>

    <h4 align="center">
    <?php
      echo $lang["rptOpenbillsToDate"].date('d.m.Y H:i', time());
     ?>
    </h4>

    <?php
      $billcount = 0;
      $billsum = 0;
      $CurrencySign = '';
      // add pay types table
      if (!empty($rptdata->OpenBillsP) &&  !empty($rptdata->OpenBillsH)) {
         //bills table header
         echo '<div class="otables"><table id="tBills">';
         echo $lang["rptBillsHeader"];
         echo '  <tbody>';
         //load bills data
         $billcount = count($rptdata->OpenBillsH);
         foreach($rptdata->OpenBillsH as $bill) {
           $billsum = $billsum + (float)($bill->D ?? 0);
           $CurrencySign = $bill->E ?? '';
           echo '<tr class="saleRow">';
           echo ' <td></td><td nowrap>'.($bill->B ?? '').'</td><td nowrap>'.date("d.m.y H:i", strtotime($bill->C ?? 'now')).'</td><td>'.($bill->H ?? '').'</td><td nowrap>'.number_format((float)($bill->D ?? 0), 2, '.', '').' '.($bill->E ?? '').'</td>';
           echo '</tr>';
           // load plu data

           echo '<tr class="itemsRow">';
           echo ' <td colspan="5">';
           echo '  <table class="tbItems">';
           echo $lang["rptBillsInnerHeader"];
           echo '<tbody>';
           //add plu data for this bill
           foreach($rptdata->OpenBillsP as $item) {
             if (($bill->A ?? '') ==  ($item->I ?? '')) {
              echo '<tr><td>'.($item->K ?? '').'</td><td nowrap>'.($item->L ?? '').' '.($item->M ?? '').'</td><td nowrap>'.number_format((float)($item->N ?? 0), 2, '.', '').' '.($item->P ?? '').'</td><td nowrap>'.number_format((float)($item->O ?? 0), 2, '.', '').' '.($item->P ?? '').'</td></tr>';
            } // end if
           } // end foreach OpenBillsP
           echo '</tbody></table></td></tr>';

         } //end foreach OpenBillsH
         // check tbody?
         echo '</tbody></table></div>';

      } // end if CurrentPayType

    ?>

    <h4 align="center"><?php echo $lang["rptOpenbillsCount"].$billcount; ?></h4>
    <h4 align="center"><?php echo $lang["rptOpenbillsSum"].number_format((float)$billsum, 2, '.', '').' '.$CurrencySign; ?></h4>

    <br>
    <center><a href="rptlist.php" class="medium color-5 button"><?php echo $lang["btnExit"]; ?></a></center>
  </div>

 <script>
   $(".saleRow td:nth-child(1)").html("<img src='images/open.png' class='btnDetail'/>");

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
</script>

</body>
</html>
