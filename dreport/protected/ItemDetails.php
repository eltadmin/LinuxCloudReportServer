<?php
session_start();

// Set session lifetime to 12 hours
$lifetime = 12 * 60 * 60; // 12 hours in seconds

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
$editUserName = $_GET['editUserName'] ?? '';
$editPassword = $_GET['editPassword'] ?? '';
$isCentralDb = isset($_GET['isCentralDb']) ? $_GET['isCentralDb'] : '0';
$pluLocalPrice = $_GET['pluLocalPrice'] ?? '0'; 
$pluSellDisabled = $_GET['pluSellDisabled'] ?? '0';


$searchQuery = $_GET['search'] ?? '';
$searchFilter = $_GET['filter'] ?? '';
$page = $_GET['page'] ?? 1;

define("C_DEBUG", false);
define("C_BAR_HEIGHT", 20);
define("C_CANVAS_HEIGHT", 30);
define("C_RPTNAME", "updateDetails");
define("C_TIMEOFFSET", 0);
$otimeoffset = C_TIMEOFFSET;
$rname = C_RPTNAME;

include_once 'language.php';
require_once('class.loading.div.php');
$divLoader = new loadingDiv;

$objectid = $_GET["id"] ?? $_SESSION['s_objectid'];
$_SESSION['s_objectid'] = $objectid;
$deviceid = $_SESSION['s_deviceid'] ?? "0";
$rptdate = $_GET["date"] ?? '';

include('database.class.php');
$pDatabase = Database::getInstance();

$qry = $pDatabase->query("SELECT s_customername, s_expiredate FROM t_subscriptions WHERE s_objectid = '".sql_safe($_SESSION['s_objectid'])."'");
while ($row = mysqli_fetch_assoc($qry)) {
    $expiredate = strtotime($row['s_expiredate']);
    $customername = $row['s_customername'];
}

$qry = $pDatabase->query("SELECT d_objectpswd, d_timeoffset FROM t_devices WHERE d_deviceid = '$deviceid' AND d_objectid = '".sql_safe($objectid)."'");
while ($row = mysqli_fetch_assoc($qry)) {
    $objectpswd = $row['d_objectpswd'];
    $otimeoffset = $row['d_timeoffset'];
}

if ($rptdate == '') {
    $dt = new DateTime();
    $rptdate = $dt->format('Y-m-d');
}
 

$qry = $pDatabase->query("SELECT d_editUsername, d_editPassword FROM t_devices WHERE d_deviceid = '$deviceid' AND d_objectid = '" . sql_safe($objectid) . "'");
$deviceRow = mysqli_fetch_assoc($qry);
if ($deviceRow) {
    $editUserName = $deviceRow['d_editUsername'];
    $dbEditPassword = $deviceRow['d_editPassword'];
} else { 
      $pDatabase->logevent(OPER_COMMAND, $objectid, 'report: '.$rname.' No device row found for the given device ID and object ID='.$objectid);;
    exit;
}
 
function decryptPassword($encrypted) {
    $encryption_key = 'IVi}|7D"Te7m5h6eZ.E)8.f'; 
    $data = base64_decode($encrypted);
    $iv = substr($data, 0, openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = substr($data, openssl_cipher_iv_length('aes-256-cbc'));
    return openssl_decrypt($encrypted, 'aes-256-cbc', $encryption_key, 0, $iv);
}
 
$decryptedDbEditPassword = decryptPassword($dbEditPassword);

// function to validate the password using crypt for the IBExpert password
function validatePassword($inputPassword, $storedPassword) {
    // extract the salt from the stored password (first 2 characters)
    $salt = substr($storedPassword, 0, 2);

    // encrypt the input password with the extracted salt using crypt
    $encryptedInputPassword = crypt($inputPassword, $salt);
 

    // compare the encrypted input password with the stored password
    return hash_equals($encryptedInputPassword, $storedPassword);
}

$isOperatorValidated = '0'; 

// New logic to handle isCentralDb
$C_SQL_OPERATORS = '{"Id":"DatabaseId","Pass":"1234","PLUESQuery":{"Type":"Query","SQL":"SELECT OPERATOR_USERNAME, OPERATOR_PASSWORD, OPERATOR_ACCESS, OPERATOR_ACTIVETODATE FROM N_OPERATORS WHERE OPERATOR_USERNAME = \'' . $editUserName . '\'"}}';

$url = 'http://' . $_SESSION['s_rpt_server_host'] . ':' . $_SESSION['s_rpt_server_port'] . '/report/' . $rname . '/?id=' . $objectid . '&u=' . $_SESSION['s_rpt_server_user'] . '&p=' . $_SESSION['rpt_server_pswd'];
$obj = json_decode($C_SQL_OPERATORS);
$obj->{"Id"} = $objectid;
$obj->{"Pass"} = $objectpswd;
$rptsql = json_encode($obj);

$options = array(
    'http' => array(
        'timeout' => 45,
        'header' => "Content-type: text/xml\r\n",
        'method' => 'GET',
        'content' => $rptsql
    ),
);
$pDatabase->logevent(OPER_COMMAND,$objectid,'report: ItemDetails objectid='.$objectid);
$context = stream_context_create($options);
$result = file_get_contents($url, false, $context);
if ($result !== FALSE) {
    $operatorData = json_decode($result, true);
    if (isset($operatorData['PLUESQuery'][0])) {
        $operatorDetails = $operatorData['PLUESQuery'][0];
        $dbOperatorUsername = $operatorDetails['OPERATOR_USERNAME'];
        $dbOperatorPassword = $operatorDetails['OPERATOR_PASSWORD'];
        $operatorActiveToDate = $operatorDetails['OPERATOR_ACTIVETODATE'];

        $qry = $pDatabase->query("SELECT d_editUsername, d_editPassword FROM t_devices WHERE d_deviceid = '$deviceid' AND d_objectid = '" . sql_safe($objectid) . "'");
        $deviceRow = mysqli_fetch_assoc($qry);
        if ($deviceRow) {
            $dbEditUsername = $deviceRow['d_editUsername'];
            $dbEditPassword = $deviceRow['d_editPassword'];

            // Decrypt the CloudDB password
            $decryptedDbEditPassword = decryptPassword($dbEditPassword);

            // Validate the CloudDB password against the IBExpert password
            $operatorPasswordValid = validatePassword($decryptedDbEditPassword, $dbOperatorPassword);

            // Check if OPERATOR_ACTIVETODATE is not less than today's date
            $currentDate = new DateTime();
            if ($operatorActiveToDate !== null && $operatorActiveToDate !== '30.12.1899 00:00:00') {
                $operatorActiveDate = new DateTime($operatorActiveToDate);
            } else {
                // If the date is null or '30.12.1899', treat it as "forever"
                $operatorActiveDate = null;
            }

            if ($dbOperatorUsername == $dbEditUsername && $operatorPasswordValid && ($operatorActiveDate === null || $operatorActiveDate >= $currentDate)) {
                $isOperatorValidated = '1';
            } else {
                $isOperatorValidated = '0';
            }
        }
    }
}

$_SESSION['isOperatorValidated'] = $isOperatorValidated;

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=EmulateIE11"/>
    <title>Item Details</title>
    <link rel="stylesheet" href="css/style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="css/w3.css">
    <script src="js/Chart.min.js"></script>
    <link rel="stylesheet" href="css/box.css">
    <script type="text/javascript" src="js/jquery.min.js"></script>
    <script type="text/javascript" src="js/jquery.alerts.js"></script>
<style>
.login-help{ 
    color: #797979;
}

.flex-container {
    display: flex;
    align-items: center;
}
.flex-container .edit-icon {
    margin-left: 10px;
}
.button-group {
    display: none;
    flex-direction: column;
    align-items: center;
    width: 95%;
    margin-top: 10px; 
}

.close:hover,
.close:focus {
    color: black;
    text-decoration: none;
    cursor: pointer;
}
.button {
    display: inline-block;  
    border-radius: 20px;
    font-size: 14px;
    text-align: center; 
    color: white;
    transition: background-color 0.3s ease;
    box-shadow: rgb(38, 70, 83) 0px 11px 8px -9px;
}
.color-1 { background-color: #a085e0; }
.color-5 { background-color: #FBB03B; }
.color-6 { background-color: #d90000; }
.color-7 { background-color: #6082B6; }
.color-8 { background-color: #00898C; }
.color-1:hover, .color-1:active  { background-color: #6c55a5; }  
.color-5:hover, .color-5:active  { background-color: #e18e0a; }
.color-6:hover, .color-6:active  { background-color: #e18e0a; }
.color-7:hover, .color-7:active  { background-color: #395989; }
.color-8:hover, .color-8:active  { background-color: #015f61; }

.card {
    background: #f5f5f5;
    border-radius: 20px;  
    box-shadow: rgb(38, 70, 83) 0px 9px 15px -12px;
    padding: 20px;
    margin: 20px auto;
    max-width: 600px;
    overflow: hidden;  
}
.card h1, .card h2 {
    margin: 0;
    text-align: center;
    color: black;
}
.card table {
    width: 100%;
    border-collapse: separate;  
    border-spacing: 1px;; 
    overflow: hidden;   
}
.card th,
.card td {
    padding: 5px;
    border: 1px solid #858585;
    text-align: left;
    font-size: 14px;
    font-family: Arial, Helvetica, sans-serif;
    color: black;
    border-radius: 5px; 
}
.card th {
    text-align: right;
}

.card .edit-icon {
    margin-left: 10px;
    cursor: pointer;
    width: 20px;
    height: 20px;
}
.card tr:last-child td,
.card tr:last-child th {
    border : none;
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
<div class="login-card">
    <div>
        <div class="login-help">
            <a href="http://eltrade.com">www.eltrade.com</a> • <a href="http://eltrade.com/bg/contacts"><?php echo $lang['contacts']; ?></a> •
            <?php
            echo htmlspecialchars($customername)  ;
                $commonParams = 'pluNumb=' . urlencode($pluNumb) . '&pluName=' . urlencode($pluName) . '&group=' . urlencode($groupId) . '&sellPrice=' . urlencode($sellPrice) . '&promotion=' . urlencode($promotion) . '&barcode=' . urlencode($barcode) . '&pluEcrName=' . urlencode($pluEcrName) . '&pluBuyPrice=' . urlencode($pluBuyPrice) . '&pluTaxgroupId=' . urlencode($pluTaxgroupId) . '&taxGroupDescr=' . urlencode($taxGroupDescr) . '&pGrpName=' . urlencode($pGrpName) . '&search=' . urlencode($searchQuery) . '&filter=' . urlencode($searchFilter) . '&page=' . urlencode($page) . '&isOperatorValidated=' . urlencode($isOperatorValidated) . '&isCentralDb=' . urlencode($isCentralDb) . '&barcode=' . urlencode($barcode) .  '&pluSellDisabled=' . urlencode($pluSellDisabled) . '&pluLocalPrice=' . urlencode($pluLocalPrice);

                if ($_SESSION['lang'] == 'bg') {
                    echo '<a href="ItemDetails.php?lang=en&' . $commonParams . '"><img src="images/en.png" /></a>';
                } else {
                    echo '<a href="ItemDetails.php?lang=bg&' . $commonParams . '"><img src="images/bg.png" /></a>';
                }
            ?>
        </div>
        <div class="card">
            <h1><?php echo $lang['objitemDetails']; ?>
                <span style="display: inline-block; width: 15px; height: 15px; background-color: <?php echo ($isCentralDb == '1' && $isOperatorValidated == '1') ? 'green' : 'red'; ?>; border-radius: 50%; margin-left: 10px;"></span>
            </h1>
            <h2><?php echo htmlspecialchars($pluName); ?></h2>
            <table>
                <tr>
                    <th><?php echo $lang["objAricleNumber"]; ?></th>
                    <td><?php echo htmlspecialchars($pluNumb); ?></td>
                </tr>
                <tr>
                    <th><?php echo $lang["objArticleName"]; ?></th>
                    <td>
                        <div class="flex-container">
                            <span id="displayPluName" style="display: inline;"><?php echo htmlspecialchars($pluName); ?></span>
                            <?php if ($isCentralDb == '1' && $isOperatorValidated == '1'): ?>
                            <input type="text" id="editPluName" value="<?php echo htmlspecialchars($pluName); ?>" style="display: none; width: 130px;">
                            <img src="images/newedit.png" class="edit-icon" onclick="toggleEditable('PluName')" style="cursor: pointer;">
                            <?php endif; ?>
                        </div>
                    </td>
                </tr> 
                <tr>
                    <th><?php echo $lang["PluDisabled"]; ?></th>
                    <td>
                        <div class="flex-container">
                            <input type="checkbox" id="pluDisabledCheckbox" <?php echo ($pluSellDisabled == '1') ? 'checked' : ''; ?> disabled>
                            <?php if ($isCentralDb == '1' && $isOperatorValidated == '1'): ?>
                            <img src="images/newedit.png" class="edit-icon" onclick="toggleEditable('PluDisabled')" style="cursor: pointer;">
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th><?php echo $lang["objSellPrice"]; ?></th>
                    <td>
                        <div class="flex-container">
                            <span id="displaySellPrice" style="display: inline;"><?php echo is_numeric($sellPrice) ? number_format((float)$sellPrice, 2) : '0.00'; ?> лв.</span>
                            <?php if (($isCentralDb == '1' && $isOperatorValidated == '1') || ($isCentralDb == '0' && $isOperatorValidated == '1' && $pluLocalPrice == '1')): ?>
                            <input type="text" id="editSellPrice" value="<?php echo htmlspecialchars($sellPrice); ?>" style="display: none; width: 70px;">
                            <img src="images/newedit.png" class="edit-icon" onclick="toggleEditable('SellPrice')" style="cursor: pointer;">
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th><?php echo $lang["objBuyPrice"]; ?></th>
                    <td><?php echo is_numeric($pluBuyPrice) ? number_format((float)$pluBuyPrice, 2) : '0.00'; ?> лв.</td>
                </tr>
                <tr>
                    <th><?php echo $lang["objECRName"]; ?></th>
                    <td>
                        <div class="flex-container">
                            <span id="displayEcrName" style="display: inline;"><?php echo htmlspecialchars($pluEcrName); ?></span>
                            <?php if ($isCentralDb == '1' && $isOperatorValidated == '1'): ?>
                            <input type="text" id="editEcrName" value="<?php echo htmlspecialchars($pluEcrName); ?>" style="display: none; width: 130px;">
                            <img src="images/newedit.png" class="edit-icon" onclick="toggleEditable('EcrName')" style="cursor: pointer;">
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th><?php echo $lang["objTaxGroupDescr"]; ?></th>
                    <td><?php echo htmlspecialchars($taxGroupDescr); ?></td>
                </tr>
                <tr>
                    <th><?php echo $lang["GroupDescr"]; ?></th>
                    <td><?php echo htmlspecialchars($pGrpName); ?></td>
                </tr>
                <tr>
                    <td colspan="2" align="center">
                        <?php if (($isCentralDb == '1' && $isOperatorValidated == '1') || ($isCentralDb == '0' && $isOperatorValidated == '1' && $pluLocalPrice == '1')): ?>
                        <button type="button" class="medium orange button" id="saveNameButton" style="display:none; text-align: center; width:100%; background-color: #36752d; color: white; padding: 8px; text-decoration: none; border-radius: 20px;" onclick="updateName('<?php echo htmlspecialchars($pluNumb); ?>')"><?php echo $lang["btnSaveName"]; ?></button>
                        <button type="button" class="medium orange button" id="savePriceButton" style="display:none; text-align: center;width:100%; background-color: #36752d; color: white; padding: 8px; text-decoration: none; border-radius: 20px;" onclick="updatePrice('<?php echo htmlspecialchars($pluNumb); ?>')"><?php echo $lang["btnSavePrice"]; ?></button>
                        <button type="button" class="medium orange button" id="saveEcrNameButton" style="display:none; text-align: center;width:100%; background-color: #36752d; color: white; padding: 8px; text-decoration: none; border-radius: 20px;" onclick="updateEcrName('<?php echo htmlspecialchars($pluNumb); ?>')"><?php echo $lang["btnSaveEcrName"]; ?></button>
                        <button type="button" class="medium orange button" id="savePluDisabledButton" style="display:none; text-align:center; width:100%; center; background-color: #36752d; color: white; padding: 8px; text-decoration: none; border-radius: 20px;" onclick="updatePluDisabled('<?php echo htmlspecialchars($pluNumb); ?>')"><?php echo $lang["btnSavePluDisabled"]; ?></button>
                        <button type="button" class="medium orange button" id="saveBarcodeButton" style="display:none; text-align: center;width:100%; background-color: #36752d; color: white; padding: 8px; text-decoration: none; border-radius: 5px;" onclick="updateBarcode('<?php echo htmlspecialchars($pluNumb); ?>')"><?php echo $lang["btnSaveBarcode"]; ?></button>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>
        <table>
            <tr>
                <td colspan="2" align="center" style="width:60%; padding-top: 5px;">
                    <a href="javascript:void(0);" onclick="toggleOperations()" class="medium color-1 button" style="width:95%;"><?php echo $lang["btnOperations"]; ?></a>
                    <div id="buttonGroup" class="button-group">
                        <a href="ActivePromotionsCSS.php?pluNumb=<?php echo urlencode($pluNumb); ?>&pluName=<?php echo urlencode($pluName); ?>&groupId=<?php echo urlencode($groupId); ?>&sellPrice=<?php echo urlencode($sellPrice); ?>&promotion=<?php echo urlencode($promotion); ?>&barcode=<?php echo urlencode($barcode); ?>&pluEcrName=<?php echo urlencode($pluEcrName); ?>&pluBuyPrice=<?php echo urlencode($pluBuyPrice); ?>&pluTaxgroupId=<?php echo urlencode($pluTaxgroupId); ?>&taxGroupDescr=<?php echo urlencode($taxGroupDescr); ?>&pGrpName=<?php echo urlencode($pGrpName); ?>&search=<?php echo urlencode($searchQuery); ?>&filter=<?php echo urlencode($searchFilter); ?>&page=<?php echo urlencode($page); ?>&isOperatorValidated=<?php echo urlencode($isOperatorValidated);?>&isCentralDb=<?php echo urlencode($isCentralDb);?>&pluLocalPrice=<?php echo urlencode($pluLocalPrice);?>&barcode=<?php echo urlencode($barcode);?>&pluSellDisabled=<?php echo urlencode($pluSellDisabled);?>" class="medium color-7 button" style="width:80%;"><?php echo $lang["btnPromotions"]; ?></a>
                        <a href="plueStorageAvailability.php?pluNumb=<?php echo urlencode($pluNumb); ?>&pluName=<?php echo urlencode($pluName); ?>&groupId=<?php echo urlencode($groupId); ?>&sellPrice=<?php echo urlencode($sellPrice); ?>&promotion=<?php echo urlencode($promotion); ?>&barcode=<?php echo urlencode($barcode); ?>&pluEcrName=<?php echo urlencode($pluEcrName); ?>&pluBuyPrice=<?php echo urlencode($pluBuyPrice); ?>&pluTaxgroupId=<?php echo urlencode($pluTaxgroupId); ?>&taxGroupDescr=<?php echo urlencode($taxGroupDescr); ?>&pGrpName=<?php echo urlencode($pGrpName); ?>&search=<?php echo urlencode($searchQuery); ?>&filter=<?php echo urlencode($searchFilter); ?>&page=<?php echo urlencode($page); ?>&isOperatorValidated=<?php echo urlencode($isOperatorValidated)?>&isCentralDb=<?php echo urlencode($isCentralDb);?>&pluLocalPrice=<?php echo urlencode($pluLocalPrice);?>&barcode=<?php echo urlencode($barcode);?>&pluSellDisabled=<?php echo urlencode($pluSellDisabled);?>" class="medium  color-7 button" style="width:80%;"><?php echo $lang["btnAvailability"]; ?></a>                                       
                        <a href="plueMonthlySales.php?pluNumb=<?php echo urlencode($pluNumb); ?>&pluName=<?php echo urlencode($pluName); ?>&groupId=<?php echo urlencode($groupId); ?>&sellPrice=<?php echo urlencode($sellPrice); ?>&promotion=<?php echo urlencode($promotion); ?>&barcode=<?php echo urlencode($barcode); ?>&pluEcrName=<?php echo urlencode($pluEcrName); ?>&pluBuyPrice=<?php echo urlencode($pluBuyPrice); ?>&pluTaxgroupId=<?php echo urlencode($pluTaxgroupId); ?>&taxGroupDescr=<?php echo urlencode($taxGroupDescr); ?>&pGrpName=<?php echo urlencode($pGrpName); ?>&search=<?php echo urlencode($searchQuery); ?>&filter=<?php echo urlencode($searchFilter); ?>&page=<?php echo urlencode($page); ?>&isOperatorValidated=<?php echo urlencode($isOperatorValidated)?>&isCentralDb=<?php echo urlencode($isCentralDb);?>&pluLocalPrice=<?php echo urlencode($pluLocalPrice);?>&barcode=<?php echo urlencode($barcode);?>&pluSellDisabled=<?php echo urlencode($pluSellDisabled);?>" class="medium  color-8 button" style="width:80%;"><?php echo $lang["btnMonthlySales"]; ?></a>
                        <a href="plueDailySales.php?pluNumb=<?php echo urlencode($pluNumb); ?>&pluName=<?php echo urlencode($pluName); ?>&groupId=<?php echo urlencode($groupId); ?>&sellPrice=<?php echo urlencode($sellPrice); ?>&promotion=<?php echo urlencode($promotion); ?>&barcode=<?php echo urlencode($barcode); ?>&pluEcrName=<?php echo urlencode($pluEcrName); ?>&pluBuyPrice=<?php echo urlencode($pluBuyPrice); ?>&pluTaxgroupId=<?php echo urlencode($pluTaxgroupId); ?>&taxGroupDescr=<?php echo urlencode($taxGroupDescr); ?>&pGrpName=<?php echo urlencode($pGrpName); ?>&search=<?php echo urlencode($searchQuery); ?>&filter=<?php echo urlencode($searchFilter); ?>&page=<?php echo urlencode($page); ?>&isOperatorValidated=<?php echo urlencode($isOperatorValidated)?>&isCentralDb=<?php echo urlencode($isCentralDb);?>&pluLocalPrice=<?php echo urlencode($pluLocalPrice);?>&barcode=<?php echo urlencode($barcode);?>&pluSellDisabled=<?php echo urlencode($pluSellDisabled);?>" class="medium  color-8 button" style="width:80%;"><?php echo $lang["btnDailySales"]; ?></a>
                    </div>
                </td>
            </tr> 
            <tr>
                <td colspan="2" align="center" style="width:60%; padding-top: 5px;">
                    <a href="articles.php?pluNumb=<?php echo urlencode($pluNumb); ?>&pluName=<?php echo urlencode($pluName); ?>&sellPrice=<?php echo urlencode($sellPrice); ?>&pluBuyPrice=<?php echo urlencode($pluBuyPrice); ?>&groupId=<?php echo urlencode($groupId); ?>&pluTaxgroupId=<?php echo urlencode($pluTaxgroupId); ?>&pluEcrName=<?php echo urlencode($pluEcrName); ?>&taxGroupDescr=<?php echo urlencode($taxGroupDescr); ?>&pGrpName=<?php echo urlencode($pGrpName); ?>&search=<?php echo urlencode($searchQuery); ?>&filter=<?php echo urlencode($searchFilter); ?>&page=<?php echo urlencode($page); ?>&isOperatorValidated=<?php echo urlencode($isOperatorValidated)?>&isCentralDb=<?php echo urlencode($isCentralDb);?>&pluLocalPrice=<?php echo urlencode($pluLocalPrice);?>&barcode=<?php echo urlencode($barcode); ?>" class="medium color-5 button" style="width:95%;"><?php echo $lang["btnBack2"]; ?></a>
                </td>
            </tr>
        </table>
    </div>
</div>
<script>
    function validateInput(event) {
        var value = event.target.value;
        event.target.value = value.replace(/[^0-9.]/g, '');   
    }

    function updateName(pluNumb) {
        var pluNameElement = document.getElementById('editPluName');
        var pluName = pluNameElement.value;

        var xhrName = new XMLHttpRequest();
        xhrName.open("POST", "UpdateName.php", true);
        xhrName.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        xhrName.onload = function() {
            if (xhrName.status === 200) {
                document.getElementById('displayPluName').innerText = pluName;
                document.getElementById('editPluName').style.display = 'none';
                document.getElementById('displayPluName').style.display = 'block';
                document.getElementById('saveNameButton').style.display = 'none';
                globalPluName = pluName;
                updateNavigationLinks();
                reloadPageWithNewParams();
            } else {
                console.error("Error updating name. Server responded with status: " + xhrName.status);
            }
        };
        xhrName.onerror = function() {
            console.error("Network error. Could not connect to server for name update.");
        };
        xhrName.send("pluNumb=" + encodeURIComponent(pluNumb) + "&pluName=" + encodeURIComponent(pluName));
    }

    function updatePrice(pluNumb) {
        var sellPriceElement = document.getElementById('editSellPrice');
        var sellPrice = sellPriceElement.value;

        var xhrPrice = new XMLHttpRequest();
        xhrPrice.open("POST", "UpdatePrice.php", true);
        xhrPrice.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        xhrPrice.onload = function() {
            if (xhrPrice.status === 200) {
                document.getElementById('displaySellPrice').innerText = sellPrice + " лв.";
                document.getElementById('editSellPrice').style.display = 'none';
                document.getElementById('displaySellPrice').style.display = 'block';
                document.getElementById('savePriceButton').style.display = 'none';
                globalSellPrice = sellPrice;
                updateNavigationLinks();
                reloadPageWithNewParams();
            } else {
                console.error("Error updating price. Server responded with status: " + xhrPrice.status);
            }
        };
        xhrPrice.onerror = function() {
            console.error("Network error. Could not connect to server for price update.");
        };
        xhrPrice.send("pluNumb=" + encodeURIComponent(pluNumb) + "&sellPrice=" + encodeURIComponent(sellPrice));
    }

    function updateEcrName(pluNumb) {
        var ecrNameElement = document.getElementById('editEcrName');
        var ecrName = ecrNameElement.value;

        var xhrEcrName = new XMLHttpRequest();
        xhrEcrName.open("POST", "UpdateEcrName.php", true);  // Ensure this URL is correct
        xhrEcrName.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        xhrEcrName.onload = function() { 
            if (xhrEcrName.status === 200) {
                document.getElementById('displayEcrName').innerText = ecrName;
                document.getElementById('editEcrName').style.display = 'none';
                document.getElementById('displayEcrName').style.display = 'block';
                document.getElementById('saveEcrNameButton').style.display = 'none';
                globalEcrName = ecrName;
                updateNavigationLinks();
                reloadPageWithNewParams();
            } else {
                console.error("Error updating ECR name. Server responded with status:", xhrEcrName.status);
            }
        };
        xhrEcrName.onerror = function() {
            console.error("Network error. Could not connect to server for ECR name update.");
        };
        xhrEcrName.send("pluNumb=" + encodeURIComponent(pluNumb) + "&pluEcrName=" + encodeURIComponent(ecrName));
    }

    function updateNavigationLinks() {
        var links = document.querySelectorAll('.button-group a, .medium.purple.button');
        for (var i = 0; i < links.length; i++) {
            var link = links[i];
            var href = link.href;
            var params = new URLSearchParams(href.split('?')[1] || '');
            params.set('pluName', decodeURIComponent(globalPluName));
            params.set('sellPrice', decodeURIComponent(globalSellPrice));
            params.set('pluEcrName', decodeURIComponent(globalEcrName));
            params.set('barcode', decodeURIComponent(globalBarcode));
            params.set('pluSellDisabled', decodeURIComponent(globalPluSellDisabled));
            link.href = href.split('?')[0] + '?' + params.toString();
        }
    }

    function reloadPageWithNewParams() {
        var href = window.location.href;
        var params = new URLSearchParams(href.split('?')[1] || '');
        params.set('pluName', decodeURIComponent(globalPluName));
        params.set('sellPrice', decodeURIComponent(globalSellPrice));
        params.set('pluEcrName', decodeURIComponent(globalEcrName));
        params.set('barcode', decodeURIComponent(globalBarcode));
        params.set('pluSellDisabled', decodeURIComponent(globalPluSellDisabled));
        window.location.href = href.split('?')[0] + '?' + params.toString();
    }

    // Polyfill for URLSearchParams for older browsers
    (function (w) {
        function URLSearchParams(searchString) {
            var self = this;
            self.searchString = searchString;
            self.params = {};

            if (searchString) {
                searchString = searchString.substring(searchString.indexOf('?') + 1);
                var pairs = searchString.split('&');
                for (var i = 0; i < pairs.length; i++) {
                    var pair = pairs[i].split('=');
                    self.params[decodeURIComponent(pair[0])] = decodeURIComponent(pair[1] || '');
                }
            }
        }

        URLSearchParams.prototype.set = function (key, value) {
            this.params[key] = value;
        };

        URLSearchParams.prototype.toString = function () {
            var pairs = [];
            for (var key in this.params) {
                if (this.params.hasOwnProperty(key)) {
                    pairs.push(encodeURIComponent(key) + '=' + encodeURIComponent(this.params[key]));
                }
            }
            return pairs.join('&');
        };

        w.URLSearchParams = w.URLSearchParams || URLSearchParams;
    })(window);

    function updateBarcode(pluNumb) {
        var barcodeElement = document.getElementById('editBarcode');
        var barcode = barcodeElement.value;

        var xhrBarcode = new XMLHttpRequest();
        xhrBarcode.open("POST", "UpdateBarcodes.php", true);
        xhrBarcode.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        xhrBarcode.onload = function() {
            if (xhrBarcode.status === 200) {
                document.getElementById('displayBarcode').innerText = barcode;
                document.getElementById('editBarcode').style.display = 'none';
                document.getElementById('displayBarcode').style.display = 'block';
                document.getElementById('saveBarcodeButton').style.display = 'none';
                globalBarcode = barcode;
                updateNavigationLinks();
                reloadPageWithNewParams();
            } else {
                console.error("Error updating barcode. Server responded with status: " + xhrBarcode.status);
            }
        };
        xhrBarcode.onerror = function() {
            console.error("Network error. Could not connect to server for barcode update.");
        };
        xhrBarcode.send("pluNumb=" + encodeURIComponent(pluNumb) + "&pluBarcode=" + encodeURIComponent(barcode));
    }

    function toggleEditable(field) {
        var fields = ['PluName', 'SellPrice', 'EcrName', 'Barcode', 'PluDisabled'];
        var isCurrentFieldBeingEdited = false;

        fields.forEach(function(f) {
            var otherDisplayField = document.getElementById('display' + f);
            var otherEditField = document.getElementById('edit' + f);
            var otherSaveButton;

            if (f === 'PluName') {
                otherSaveButton = document.getElementById('saveNameButton');
            } else if (f === 'SellPrice') {
                otherSaveButton = document.getElementById('savePriceButton');
            } else if (f === 'EcrName') {
                otherSaveButton = document.getElementById('saveEcrNameButton');
            } else if (f === 'Barcode') {
                otherSaveButton = document.getElementById('saveBarcodeButton');
            } else if (f === 'PluDisabled') {
                otherSaveButton = document.getElementById('savePluDisabledButton');
                otherEditField = document.getElementById('pluDisabledCheckbox');
            }

            if (f !== field) {
                if (otherDisplayField) {
                    otherDisplayField.style.display = 'block';
                }
                if (otherEditField) {
                    if (f === 'PluDisabled') {
                        otherEditField.disabled = true;
                    } else {
                        otherEditField.style.display = 'none';
                    }
                }
                if (otherSaveButton) {
                    otherSaveButton.style.display = 'none';
                }
            } else {
                // Check if the current field is already being edited
                if (otherEditField && otherEditField.style.display === 'inline-block' || (f === 'PluDisabled' && !otherEditField.disabled)) {
                    isCurrentFieldBeingEdited = true;
                }
            }
        });

        // Now toggle the selected field
        var displayField = document.getElementById('display' + field);
        var editField = document.getElementById('edit' + field);
        var saveButton;

        if (field === 'PluName') {
            saveButton = document.getElementById('saveNameButton');
        } else if (field === 'SellPrice') {
            saveButton = document.getElementById('savePriceButton');
        } else if (field === 'EcrName') {
            saveButton = document.getElementById('saveEcrNameButton');
        } else if (field === 'Barcode') {
            saveButton = document.getElementById('saveBarcodeButton');
        } else if (field === 'PluDisabled') {
            saveButton = document.getElementById('savePluDisabledButton');
            editField = document.getElementById('pluDisabledCheckbox');
        }

        if (isCurrentFieldBeingEdited) {
            if (displayField) displayField.style.display = 'block';
            if (field === 'PluDisabled') {
                editField.disabled = true;
            } else {
                editField.style.display = 'none';
            }
            saveButton.style.display = 'none';
        } else {
            if (displayField) displayField.style.display = 'none';
            if (field === 'PluDisabled') {
                editField.disabled = false;
            } else {
                editField.style.display = 'inline-block';
                editField.disabled = false; // Ensure the field is enabled when opened
            }
            saveButton.style.display = 'inline-block';
        }
    }

    function updatePluDisabled(pluNumb) {
        var pluDisabledCheckbox = document.getElementById('pluDisabledCheckbox');
        var pluDisabled = pluDisabledCheckbox.checked ? '1' : '0';

        var xhrDisabled = new XMLHttpRequest();
        xhrDisabled.open("POST", "UpdatePluDisabled.php", true);
        xhrDisabled.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        xhrDisabled.onload = function() {
            if (xhrDisabled.status === 200) {
                console.log("PLU Disabled status updated successfully.");
                toggleEditable('PluDisabled');
                globalPluSellDisabled = pluDisabled;
                updateNavigationLinks();
                reloadPageWithNewParams();
            } else {
                console.error("Error updating PLU Disabled status. Server responded with status: " + xhrDisabled.status);
            }
        };
        xhrDisabled.onerror = function() {
            console.error("Network error. Could not connect to server for PLU Disabled status update.");
        };
        xhrDisabled.send("pluNumb=" + encodeURIComponent(pluNumb) + "&pluDisabled=" + encodeURIComponent(pluDisabled));
    }

    var globalBarcode = "<?php echo htmlspecialchars($barcode); ?>";

    function toggleOperations() {
        var buttonGroup = document.getElementById('buttonGroup');
        if (buttonGroup.style.display === 'flex') {
            buttonGroup.style.display = 'none';
        } else {
            buttonGroup.style.display = 'flex';
        }
    }

    function showCreateTableModal() {
        document.getElementById('createTableModal').style.display = 'block';
    }

    var modal = document.getElementById('createTableModal');
    var span = document.getElementsByClassName('close')[0];
    if (span) {
        span.onclick = function() {
            modal.style.display = 'none';
        }
    }
    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }

    function createTable() {
        var username = document.getElementById('username').value;
        var password = document.getElementById('password').value;

        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'addUserPassEdit.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function() {
            if (xhr.status === 200) {
                alert('User and password added successfully');
                modal.style.display = 'none';
            } else {
                alert('Error creating table. Server responded with status: ' + xhr.status);
            }
        };
        xhr.send('editUserName=' + encodeURIComponent(username) + '&editPassword=' + encodeURIComponent(password));
    }

    if (document.addEventListener) {
        document.addEventListener("DOMContentLoaded", function() {
            var editSellPrice = document.getElementById('editSellPrice'); 
            if (editSellPrice) {
                editSellPrice.addEventListener('input', validateInput);
            }
        });
    } else {
        document.attachEvent("onreadystatechange", function() {
            if (document.readyState === "complete") {
                var editSellPrice = document.getElementById('editSellPrice'); 
                if (editSellPrice) {
                    editSellPrice.attachEvent('oninput', validateInput);
                }
            }
        });
    }

    var globalPluName = "<?php echo htmlspecialchars($pluName); ?>";  
    var globalSellPrice = "<?php echo htmlspecialchars($sellPrice); ?>";  
    var globalEcrName = "<?php echo htmlspecialchars($pluEcrName); ?>";  
    var globalPluSellDisabled = "<?php echo htmlspecialchars($pluSellDisabled); ?>";
</script>

</body>
</html>
