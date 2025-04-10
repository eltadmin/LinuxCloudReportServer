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
$page = isset($_GET['page']) ? $_GET['page'] : 1;

define("C_DEBUG", false);
define("C_BAR_HEIGHT", 20);
define("C_CANVAS_HEIGHT", 30);
define("C_RPTNAME", "storageinfo");
define("C_TIMEOFFSET", 0);
$otimeoffset = C_TIMEOFFSET;
$rname = C_RPTNAME;

$pluNumb = $_GET['pluNumb'] ?? '';

$C_SQL = '{"Id":"DatabaseId","Pass":"1234","PLUESQuery":{"Type":"Query","SQL":"SELECT P.PLU_NUMB, P.PLU_NAME, NS.STORAGE_NAME, NS.STORAGE_NUMB, SQ.SQ_QUANTITY FROM PLUES P JOIN STORAGEQUANT SQ ON P.PLU_NUMB = SQ.SQ_PLUNUMB JOIN N_STORAGES NS ON SQ.SQ_STORAGEID = NS.STORAGE_NUMB WHERE P.PLU_NUMB = \'' . $pluNumb . '\'"}}';

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

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=EmulateIE11"/>
    <title>Storage Information</title>
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
  .color-1 { background-color: #89cb3c; }
  .color-2 { background-color: #f32b2b; }
  .color-3 { background-color: #FBB03B; }
  
  .color-1:hover, .color-1:active  {
    background-color: #63af0a;  
  }  
  .color-2:hover, .color-2:active  {
    background-color: #d90000; 
  } 
  .color-3:hover, .color-3:active  {
    background-color: #e18e0a; 
  }
  .login-help{
	  font-size: 11.3px;
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
    <div class="login-card" >
        <div>
		<div class="login-help">
            <a href="http://eltrade.com">www.eltrade.com</a> • <a href="http://eltrade.com/bg/contacts"><?php echo $lang['contacts']; ?></a> •
            <?php
            $commonParams = 'pluNumb=' . urlencode($pluNumb) . '&pluName=' . urlencode($pluName) . '&group=' . urlencode($groupId) . '&sellPrice=' . urlencode($sellPrice) . '&promotion=' . urlencode($promotion) . '&barcode=' . urlencode($barcode) . '&pluEcrName=' . urlencode($pluEcrName) . '&pluBuyPrice=' . urlencode($pluBuyPrice) . '&pluTaxgroupId=' . urlencode($pluTaxgroupId) . '&taxGroupDescr=' . urlencode($taxGroupDescr) . '&pGrpName=' . urlencode($pGrpName) . '&search=' . urlencode($searchQuery) . '&filter=' . urlencode($searchFilter) . '&page=' . urlencode($page) . '&isOperatorValidated=' . urlencode($isOperatorValidated) . '&isCentralDb=' . urlencode($isCentralDb) . '&barcode=' . urlencode($barcode) . '&pluLocalPrice=' . urlencode($pluLocalPrice);

            echo '<a>' . htmlspecialchars($customername) . '</a> • ';
            if ($_SESSION['lang'] == 'bg') {
                echo '<a href="plueStorageAvailability.php?lang=en&' . $commonParams . '"><img src="images/en.png" /></a>';
            } else {
                echo '<a href="plueStorageAvailability.php?lang=bg&' . $commonParams . '"><img src="images/bg.png" /></a>';
            }
            ?>
        </div>
            <h1 align="center" ><?php echo $_SESSION['s_objectname']; ?></h1>
            <h1><?php echo $lang['objPlueAvailability']; ?></h1> 
            
            <h4 align="center" >
             <?php
                echo $lang["rptOpenbillsToDate"].date('d.m.Y H:i', time());
             ?>
            </h4>
                        
            <?php if (!empty($combinedItems)): ?>
               <table border="0" cellpadding="10" cellspacing="0" style="width: 100%; border-collapse: separate; text-align: center;">
                    <thead>
                        <tr style="background-color: #36752d;">
                            <th style="color: white; border-left: 1px solid #36752d; border-right: 1px solid #36752d; font-size: 12px; font-family: Arial, Helvetica, sans-serif;"><?php echo $lang['objAricleNumber']; ?></th>
                            <th style="color: white; border-left: 1px solid #36752d; border-right: 1px solid #36752d; font-size: 12px; font-family: Arial, Helvetica, sans-serif;"><?php echo $lang['objArticleName']; ?></th>
                            <th style="color: white; border-left: 1px solid #36752d; border-right: 1px solid #36752d; font-size: 12px; font-family: Arial, Helvetica, sans-serif;"><?php echo $lang['objStorageName']; ?></th>
                            <th style="color: white; border-left: 1px solid #36752d; border-right: 1px solid #36752d; font-size: 12px; font-family: Arial, Helvetica, sans-serif;"><?php echo $lang['objStorageNumber']; ?></th>
                            <th style="color: white; border-left: 1px solid #36752d; border-right: 1px solid #36752d; font-size: 12px; font-family: Arial, Helvetica, sans-serif;"><?php echo $lang['objQuantity']; ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($combinedItems as $item): ?>
                            <tr>
                                <td style="border-left: 1px solid #36752d; border-right: 1px solid #36752d; padding: 8px; font-size: 12px; font-family: Arial, Helvetica, sans-serif;"><?= isset($item['PLU_NUMB']) ? htmlspecialchars($item['PLU_NUMB']) : '' ?></td>
                                <td style="border-left: 1px solid #36752d; border-right: 1px solid #36752d; padding: 8px; font-size: 12px; font-family: Arial, Helvetica, sans-serif;"><?= isset($item['PLU_NAME']) ? htmlspecialchars($item['PLU_NAME']) : '' ?></td>
                                <td style="border-left: 1px solid #36752d; border-right: 1px solid #36752d; padding: 8px; font-size: 12px; font-family: Arial, Helvetica, sans-serif;"><?= isset($item['STORAGE_NAME']) ? htmlspecialchars($item['STORAGE_NAME']) : '' ?></td>
                                <td style="border-left: 1px solid #36752d; border-right: 1px solid #36752d; padding: 8px; font-size: 12px; font-family: Arial, Helvetica, sans-serif;"><?= isset($item['STORAGE_NUMB']) ? htmlspecialchars($item['STORAGE_NUMB']) : '' ?></td>
                                <td style="border-left: 1px solid #36752d; border-right: 1px solid #36752d; padding: 8px; font-size: 12px; font-family: Arial, Helvetica, sans-serif;"><?= isset($item['SQ_QUANTITY']) ? number_format($item['SQ_QUANTITY'], 3) : '' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p><?php echo $lang['NoItemsFound']; ?></p>
            <?php endif; ?>
 
            <p style="text-align: center;">
                 <a href="ItemDetails.php?pluNumb=<?php echo urlencode($pluNumb); ?>&pluName=<?php echo urlencode($pluName); ?>&sellPrice=<?php echo urlencode($sellPrice); ?>&pluBuyPrice=<?php echo urlencode($pluBuyPrice); ?>&groupId=<?php echo urlencode($groupId); ?>&pluTaxgroupId=<?php echo urlencode($pluTaxgroupId); ?>&pluEcrName=<?php echo urlencode($pluEcrName); ?>&taxGroupDescr=<?php echo urlencode($taxGroupDescr); ?>&pGrpName=<?php echo urlencode($pGrpName); ?>&search=<?php echo urlencode($searchQuery); ?>&filter=<?php echo urlencode($searchFilter); ?>&page=<?php echo urlencode($page); ?>&isOperatorValidated=<?php echo urlencode($isOperatorValidated);?>&isCentralDb=<?php echo urlencode($isCentralDb);?>&pluLocalPrice=<?php echo urlencode($pluLocalPrice);?>&barcode=<?php echo urlencode($barcode);?>&pluSellDisabled=<?php echo urlencode($pluSellDisabled);?>" class="medium color-3 button" style="width:100%;margin-top:10px;">
                    <?php echo $lang["btnBack2"]; ?>
                </a>
            </p>

        </div>
    </div>
</body>
</html>
