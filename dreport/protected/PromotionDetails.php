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
$isCentralDb = $_GET['isCentralDb'] ?? '0';
$pluLocalPrice = $_GET['pluLocalPrice'] ?? '0';
$pluSellDisabled = $_GET['pluSellDisabled'] ?? '0';

$searchQuery = isset($_GET['search']) ? $_GET['search'] : '';
$searchFilter = isset($_GET['filter']) ? $_GET['filter'] : '';
$page = isset($_GET['page']) ? $_GET['page'] : 1;

$promotionStartDate = '';
$promotionEndDate = '';
$promotionStartTime = '';
$promotionEndTime = '';

$promotionPrice = '';
$promotionDiscount = '';
$promotionType = '';
$promotionPriority = '';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['promotionStartDate'])) {
        $errors[] = "Start date is required.";
    }
    if (empty($_POST['promotionEndDate'])) {
        $errors[] = "End date is required.";
    }
    if (empty($_POST['promotionValue'])) {
        $errors[] = "Promotion value is required.";
    }

    if (!empty($errors)) {
        $errMsg = urlencode(implode(", ", $errors));
        header("Location: PromotionDetails.php?err=" . $errMsg);
        exit;
    }
}

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

$qry = $pDatabase->query("select d_objectpswd,d_timeoffset from t_devices where d_deviceid = '$deviceid' and d_objectid='".sql_safe($objectid)."' ;");
while ($row = mysqli_fetch_assoc($qry)) {
    $objectpswd = $row['d_objectpswd'];
    $otimeoffset = $row['d_timeoffset'];
}

if ($rptdate == '') {
    $dt = new DateTime();
    $rptdate = $dt->format('Y-m-d');
}

$pDatabase->logevent(OPER_COMMAND,$objectid,'report: AddNewPromotion.php objectid='.$objectid);


include_once 'language.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=EmulateIE11"/>
    <title>Promotion Details</title>
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
    border-radius: 30px;
    font-size: 14px;
    text-align: center; 
    color: white;
	transition: background-color 0.3s ease;
	box-shadow: rgb(38, 70, 83) 0px 11px 8px -5px;
  }
  .color-1 { background-color: #98c04d;
			border-color: #98c04d;}
  .color-5 { background-color: #FBB03B; }
  
  .color-1:hover, .color-1:active  {
    background-color: #36752d;  
  }  
  .color-5:hover, .color-5:active  {
    background-color: #e18e0a;  
  }
  .login-help{
	  font-size: 11.3px;
  }
  
	</style>
</head>
<body>
<?php
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
<div class="alert-box error" id="alert-box-error" style="display: none;">
    <span id="error-message"></span>
</div>
<div class="alert-box success" id="alert-box-success" style="display: none;">
    <span id="success-message"></span>
</div>

    <div>
        <div class="login-help">            
            <a href="http://eltrade.com">www.eltrade.com</a> • <a href="http://eltrade.com/bg/contacts"><?php echo $lang['contacts']; ?></a> •
            <?php
            $commonParams = 'pluNumb=' . urlencode($pluNumb) . '&pluName=' . urlencode($pluName) . '&group=' . urlencode($groupId) . '&sellPrice=' . urlencode($sellPrice) . '&promotion=' . urlencode($promotion) . '&barcode=' . urlencode($barcode) . '&pluEcrName=' . urlencode($pluEcrName) . '&pluBuyPrice=' . urlencode($pluBuyPrice) . '&pluTaxgroupId=' . urlencode($pluTaxgroupId) . '&taxGroupDescr=' . urlencode($taxGroupDescr) . '&pGrpName=' . urlencode($pGrpName) . '&search=' . urlencode($searchQuery) . '&filter=' . urlencode($searchFilter) . '&page=' . urlencode($page) . '&isOperatorValidated=' . urlencode($isOperatorValidated) . '&isCentralDb=' . urlencode($isCentralDb) . '&barcode=' . urlencode($barcode) . '&pluLocalPrice=' . urlencode($pluLocalPrice). '&pluSellDisabled=' . urlencode($pluSellDisabled);

            echo '<a>' . htmlspecialchars($customername) . '</a> • ';
            if ($_SESSION['lang'] == 'bg') {
                echo '<a href="PromotionDetails.php?lang=en&' . $commonParams . '"><img src="images/en.png" /></a>';
            } else {
                echo '<a href="PromotionDetails.php?lang=bg&' . $commonParams . '"><img src="images/bg.png" /></a>';
            }
            ?>
        </div>
        <h1 style="text-align: center; color: #82b327;"><?php echo  $lang['objPromotionDetails']; ?></h1>
        <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">                                         
            <tr>
                <td style="font-weight: bold; color: black; text-align: right; padding-right: 10px; white-space: nowrap;"><?php echo $lang['objAricleNumber']; ?></td>
                <td style="text-align: left; color: black; width: 50%; overflow-wrap: break-word;"><?php echo htmlspecialchars($pluNumb); ?></td>
            </tr>
            <tr>
                <td style="font-weight: bold; color: black; text-align: right; padding-right: 10px; white-space: nowrap;"><?php echo $lang['objArticleName']; ?></td>
                <td style="text-align: left; color: black; width: 50%; overflow-wrap: break-word;"><?php echo htmlspecialchars($pluName); ?></td>
            </tr>
            <tr>
                <td style="font-weight: bold; color: black; text-align: right; padding-right: 10px; white-space: nowrap;"><?php echo $lang['objSellPrice']; ?></td>
                <td style="text-align: left; color: black; width: 50%; overflow-wrap: break-word;"><?php echo number_format($sellPrice, 2); ?> лв.</td>
            </tr>
            <form id="promotionForm" method="POST" action="updatePromotion.php">
                <input type="hidden" name="pluNumb" value="<?php echo htmlspecialchars($pluNumb); ?>">
                <tr class="input-group">
                    <td style="font-weight: bold; color: black; text-align: right; padding-right: 10px;">
                        <label><?php echo $lang['promotionalPrice']; ?></label>
                        <input type="radio" name="promotionType" value="price" checked>
                    </td>
                    <td style="font-weight: bold; color: black; text-align: left; padding-left: 10px;">
                        <label><?php echo $lang['promotionalDiscount']; ?></label>
                        <input type="radio" name="promotionType" value="discount">
                    </td>
                </tr>
                <tr class="input-group">
                    <td style="font-weight: bold; color: black; text-align: right; padding-right: 10px;">
                        <label for="promotionValue"><?php echo $lang['promotionalPrice']; ?></label>
                    </td>
                    <td style="text-align: left;"><input type="text" name="promotionValue" id="promotionValue" style="width: 50%; padding: 10px; border-radius: 4px; box-sizing: border-box; background-color: #f8f8f8; border: 1px solid #ccc;">
				</tr>
                <tr class="input-group">
                    <td style="font-weight: bold; color: black; text-align: right; padding-right: 10px;"><label for="promotionPriceAfterDiscount"><?php echo $lang['priceAfterDiscount']; ?></label></td>
                    <td style="text-align: left;"><input type="text" id="promotionPriceAfterDiscount" value="<?php echo htmlspecialchars($sellPrice); ?>" readonly style="width: 50%; border-radius: 4px; box-sizing: border-box; background-color: #f8f8f8; border: 1px solid #ccc;"></td>
                </tr>
                <tr class="input-group">
                    <td style="font-weight: bold; color: black; text-align: right; padding-right: 10px;"><label><?php echo $lang['promotionalPrio']; ?></label></td>
                    <td style="text-align: left;">
                        <div id="promotionPriority" class="btn-group" style="display: flex; flex-wrap: nowrap; overflow-x: auto; white-space: nowrap; gap: 2px;">
                            <?php for ($i = 1; $i <= 10; $i++): ?>
                                <input type="radio" class="btn-check" name="promotionPriority" id="type<?php echo $i; ?>" value="<?php echo $i; ?>" style="display: none;" <?php echo ($i == 5) ? 'checked' : ''; ?>>
                                <label class="btn btn-outline-primary" for="type<?php echo $i; ?>" style="padding: 5px 5px; font-size: 10px; border: 1px solid #82b327; color: #82b327; background-color: transparent; cursor: pointer; transition: all 0.3s ease; min-width: 1px; text-align: center;"><?php echo $i; ?></label>
                            <?php endfor; ?>
                        </div>
                    </td>
                </tr>
                <tr class="input-group">
                    <td style="font-weight: bold; color: black; text-align: right; padding-right: 10px;"><label for="promotionStartDate"><?php echo $lang['StartDate']; ?></label></td>
                    <td style="text-align: left;"><input type="date" name="promotionStartDate" id="promotionStartDate" value="<?php echo htmlspecialchars($promotionStartDate); ?>" style="width: 75%; padding: 10px; border-radius: 4px; box-sizing: border-box; background-color: #f8f8f8; border: 1px solid #ccc;"></td>
                </tr>
                <tr class="input-group">
                    <td style="font-weight: bold; color: black; text-align: right; padding-right: 10px;"><label for="promotionEndDate"><?php echo $lang['EndDate']; ?></label></td>
                    <td style="text-align: left;"><input type="date" name="promotionEndDate" id="promotionEndDate" value="<?php echo htmlspecialchars($promotionEndDate); ?>" style="width: 75%; padding: 10px; border-radius: 4px; box-sizing: border-box; background-color: #f8f8f8; border: 1px solid #ccc;"></td>
                </tr>
				<tr class="input-group">
					<td style="font-weight: bold; color: black; text-align: right; padding-right: 10px;">
						<label for="promotionStartTime"><?php echo $lang['fromTime']?></label>
					</td>
					<td style="text-align: left;">
						<input type="time" name="promotionStartTime" id="promotionStartTime" value="<?php echo htmlspecialchars($promotionStartTime); ?>" step="1" style="width: 75%; padding: 10px; border-radius: 4px; box-sizing: border-box; background-color: #f8f8f8; border: 1px solid #ccc;">
					</td>
				</tr>
				<tr class="input-group">
					<td style="font-weight: bold; color: black; text-align: right; padding-right: 10px;">
						<label for="promotionEndTime"><?php echo $lang['toTime']?></label>
					</td>
					<td style="text-align: left;">
						<input type="time" name="promotionEndTime" id="promotionEndTime" value="<?php echo htmlspecialchars($promotionEndTime); ?>" step="1" style="width: 75%; padding: 10px; border-radius: 4px; box-sizing: border-box; background-color: #f8f8f8; border: 1px solid #ccc;">
					</td>
				</tr>
            </form>
        </table>
       <p >
            <button type="submit" form="promotionForm" class="medium color-1 button" style="display: block;width: 100%; padding: 10px;"><?php echo $lang["objSave"]?></button>
        </p>
        <p style="text-align: center;">
            <a href="ActivePromotionsCSS.php?pluNumb=<?php echo urlencode($pluNumb); ?>&pluName=<?php echo urlencode($pluName); ?>&groupId=<?php echo urlencode($groupId); ?>&sellPrice=<?php echo urlencode($sellPrice); ?>&promotion=<?php echo urlencode($promotion);?>&pluEcrName=<?php echo urlencode($pluEcrName); ?>&pluBuyPrice=<?php echo urlencode($pluBuyPrice); ?>&pluTaxgroupId=<?php echo urlencode($pluTaxgroupId); ?>&taxGroupDescr=<?php echo urlencode($taxGroupDescr); ?>&pGrpName=<?php echo urlencode($pGrpName); ?>&search=<?php echo urlencode($searchQuery); ?>&filter=<?php echo urlencode($searchFilter); ?>&page=<?php echo urlencode($page); ?>&isOperatorValidated=<?php echo urlencode($isOperatorValidated);?>&pluLocalPrice=<?php echo urlencode($pluLocalPrice);?>&barcode=<?php echo urlencode($barcode);?>&isCentralDb=<?php echo urlencode($isCentralDb);?>&pluSellDisabled=<?php echo urlencode($pluSellDisabled);?>" class="medium color-5 button" style="display: block; width: 100%;  padding: 10px; text-decoration: none; ">
                <?php echo $lang["btnBack2"]; ?>
            </a>
        </p>
    </div>
</div> 

<script>
document.addEventListener("DOMContentLoaded", function() {
    function validateInput(event) {
        var value = event.target.value;
        event.target.value = value.replace(/[^0-9.]/g, '');  // Replace any character that is not a number or dot
    }

    var promotionValueInput = document.getElementById('promotionValue');
    var promotionPriceAfterDiscountInput = document.getElementById('promotionPriceAfterDiscount');
    var submitButton = document.querySelector('button[type="submit"]');

    promotionValueInput.addEventListener('input', validateInput);
    promotionPriceAfterDiscountInput.addEventListener('input', validateInput);
    
    function validateEndTime() {
        var startTime = document.getElementById('promotionStartTime').value;
        var endTime = document.getElementById('promotionEndTime').value;

        if (startTime && endTime && endTime <= startTime) {
            displayError("<?php echo $lang['errEndTimeMustBeGreaterThanStartTime']; ?>");
            submitButton.disabled = true;   
            return false;
        } else {
            submitButton.disabled = false;  
        }
        return true;
    }

    document.getElementById('promotionStartTime').addEventListener('input', validateEndTime);
    document.getElementById('promotionEndTime').addEventListener('input', validateEndTime);

});
    function displayError(message) {
        var alertBox = document.getElementById('alert-box-error');
        var errorMessage = document.getElementById('error-message');
        errorMessage.textContent = message;
        alertBox.style.display = 'block';
        setTimeout(function() {
            alertBox.style.display = 'none';
        }, 5000);
    }
	    function displaySuccess(message) {
        var alertBox = document.getElementById('alert-box-success');
        var successMessage = document.getElementById('success-message');
        successMessage.textContent = message;
        alertBox.style.display = 'block';
        setTimeout(function() {
            alertBox.style.display = 'none';
        }, 5000);
    }
document.addEventListener("DOMContentLoaded", function() {
    function displayError(message) {
        var alertBox = document.getElementById('alert-box-error');
        var errorMessage = document.getElementById('error-message');
        errorMessage.textContent = message;
        alertBox.style.display = 'block';
        setTimeout(function() {
            alertBox.style.display = 'none';
        }, 5000);
    }



    var promotionTypeInputs = document.querySelectorAll('input[name="promotionType"]');
    var promotionValueInput = document.getElementById('promotionValue');
    var promotionPriceAfterDiscountInput = document.getElementById('promotionPriceAfterDiscount');
    var radios = document.querySelectorAll('input[name="promotionPriority"]');
    var labels = document.querySelectorAll('.btn-outline-primary');

    function calculateDiscountedPrice() {
        var sellPrice = parseFloat(<?php echo json_encode($sellPrice); ?>);
        var promotionValue = parseFloat(promotionValueInput.value);
        var promotionType = document.querySelector('input[name="promotionType"]:checked').value;

        if (promotionType === 'price') {
            promotionPriceAfterDiscountInput.value = promotionValue.toFixed(2);
        } else if (promotionType === 'discount') {
            var discountedPrice = sellPrice - (sellPrice * (promotionValue / 100));
            promotionPriceAfterDiscountInput.value = discountedPrice.toFixed(2);
        }
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

    URLSearchParams.prototype.get = function (key) {
        return this.params[key];
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

// Display error message from server if any
var params = new URLSearchParams(window.location.search);
var serverErrorMessage = params.get('err');
if (serverErrorMessage) {
    displayError(decodeURIComponent(serverErrorMessage));
}


promotionValueInput.addEventListener('input', function() {
    calculateDiscountedPrice();
});

for (var i = 0; i < promotionTypeInputs.length; i++) {
    promotionTypeInputs[i].addEventListener('change', function() {
        calculateDiscountedPrice();
    });
}


	function updateLabelClasses(selectedRadio) {
		for (var i = 0; i < labels.length; i++) {
			var label = labels[i];
			label.style.backgroundColor = '';
			label.style.color = '#82b327';
		}
		var selectedLabel = document.querySelector('label[for="' + selectedRadio.id + '"]');
		if (selectedLabel) {
			selectedLabel.style.backgroundColor = '#82b327';
			selectedLabel.style.color = 'white';
		}
	}


for (var i = 0; i < radios.length; i++) {
    (function(radio) {
        radio.addEventListener('change', function() {
            updateLabelClasses(this);
        });
        if (radio.checked) {
            updateLabelClasses(radio);
        }
    })(radios[i]);
}


    var defaultRadio = document.getElementById('type5');
    defaultRadio.checked = true;
    updateLabelClasses(defaultRadio);

    document.getElementById('promotionStartDate').addEventListener('change', function() {
        var startDateInput = document.getElementById('promotionStartDate');
        var endDateInput = document.getElementById('promotionEndDate');
        endDateInput.setAttribute('min', startDateInput.value);
        if (endDateInput.value < startDateInput.value) {
            endDateInput.value = startDateInput.value;
        }
    });
});

$(document).ready(function() {
    $("#promotionStartDate").datepicker({
        dateFormat: "yy-mm-dd"
    });
    $("#promotionEndDate").datepicker({
        dateFormat: "yy-mm-dd"
    });
});


function updateMinimumEndDate() {
    var startDateInput = document.getElementById('promotionStartDate');
    var endDateInput = document.getElementById('promotionEndDate');
    endDateInput.setAttribute('min', startDateInput.value);
    if (endDateInput.value < startDateInput.value) {
        endDateInput.value = startDateInput.value;
    }
}

document.getElementById('promotionStartDate').addEventListener('change', updateMinimumEndDate);

   function savePromotionDetails(event) {
        event.preventDefault(); // Prevent the default form submission
        console.log("Save promotion details function called");

        var pluNumb = document.getElementsByName('pluNumb')[0].value;
        var promotionStartDate = document.getElementById('promotionStartDate').value;
        var promotionEndDate = document.getElementById('promotionEndDate').value;
        var promotionStartTime = document.getElementsByName('promotionStartTime')[0].value;
        var promotionEndTime = document.getElementsByName('promotionEndTime')[0].value;
        var promotionType = document.querySelector('input[name="promotionType"]:checked');
        var promotionValue = document.getElementsByName('promotionValue')[0].value;
        var promotionPriority = document.querySelector('input[name="promotionPriority"]:checked');

        if (!pluNumb || !promotionStartDate || !promotionEndDate || !promotionStartTime || !promotionEndTime || !promotionType || !promotionValue || !promotionPriority) {
            displayError("<?php echo $lang['errAllFieldsAreMandatory']; ?>");
            return;
        }

        var promotionPriceAfterDiscount = document.getElementById('promotionPriceAfterDiscount').value;

        var prType = (promotionType.value === 'price') ? 1 : 4;
        var prPrice = (promotionType.value === 'price') ? promotionValue : -promotionValue;

        var taxGroupDescr = "<?php echo addslashes($taxGroupDescr); ?>";
        var pGrpName = "<?php echo addslashes($pGrpName); ?>";
        var searchQuery = "<?php echo addslashes($searchQuery); ?>";
        var searchFilter = "<?php echo addslashes($searchFilter); ?>";
        var page = "<?php echo addslashes($page); ?>";
        var isOperatorValidated = "<?php echo addslashes($isOperatorValidated); ?>";
        var pluLocalPrice = "<?php echo addslashes($pluLocalPrice); ?>";
        var isCentralDb = "<?php echo addslashes($isCentralDb); ?>";
        var pluSellDisabled = "<?php echo addslashes($pluSellDisabled); ?>";

        // Ensure time format includes seconds
        if (!promotionStartTime.includes(":")) {
            promotionStartTime += ":00";
        }
        if (!promotionEndTime.includes(":")) {
            promotionEndTime += ":00";
        }
        if (promotionStartTime.length === 5) {
            promotionStartTime += ":00";
        }
        if (promotionEndTime.length === 5) {
            promotionEndTime += ":00";
        }

        var xhr = new XMLHttpRequest();
        xhr.open("POST", "updatePromotion.php", true);
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.success) { 
                        displaySuccess("<?php echo $lang["objSuccess"]; ?>");
                    } else {
                        displayError("Error: " + (response.message || "Error updating promotion."));
                    }
                } catch(e) { 
                    displayError("Error parsing server response: " + e.message);
                }
            } else {
                displayError("Error updating promotion. Server responded with status: " + xhr.status);
            }
            window.location.href = 'ActivePromotionsCSS.php?pluNumb=' + encodeURIComponent(pluNumb) +
                '&pluName=' + encodeURIComponent("<?php echo htmlspecialchars($pluName); ?>") +
                '&sellPrice=' + encodeURIComponent("<?php echo htmlspecialchars($sellPrice); ?>") +
                '&barcode=' + encodeURIComponent("<?php echo htmlspecialchars($barcode); ?>") +
                '&pluBuyPrice=' + encodeURIComponent("<?php echo htmlspecialchars($pluBuyPrice); ?>") +
                '&groupId=' + encodeURIComponent("<?php echo htmlspecialchars($groupId); ?>") +
                '&pluTaxgroupId=' + encodeURIComponent("<?php echo htmlspecialchars($pluTaxgroupId); ?>") +
                '&pluEcrName=' + encodeURIComponent("<?php echo htmlspecialchars($pluEcrName); ?>") +
                '&taxGroupDescr=' + encodeURIComponent(taxGroupDescr) +
                '&pGrpName=' + encodeURIComponent(pGrpName) +
                '&search=' + encodeURIComponent(searchQuery) +
                '&filter=' + encodeURIComponent(searchFilter) +
                '&page=' + encodeURIComponent(page) +
                '&isOperatorValidated=' + encodeURIComponent(isOperatorValidated) +
                '&isCentralDb=' + encodeURIComponent(isCentralDb) +
                '&pluLocalPrice=' + encodeURIComponent(pluLocalPrice) +
                '&pluSellDisabled=' + encodeURIComponent(pluSellDisabled);
        };
        xhr.onerror = function() {
            displayError("Network error. Could not connect to server.");
        };
        xhr.send("pluNumb=" + encodeURIComponent(pluNumb) +
            "&promotionStartDate=" + encodeURIComponent(promotionStartDate) +
            "&promotionEndDate=" + encodeURIComponent(promotionEndDate) +
            "&promotionStartTime=" + encodeURIComponent(promotionStartTime) +
            "&promotionEndTime=" + encodeURIComponent(promotionEndTime) +
            "&promotionType=" + encodeURIComponent(promotionType.value) +
            "&promotionValue=" + encodeURIComponent(promotionValue) +
            "&promotionPrice=" + encodeURIComponent(prPrice) +
            "&promotionPriority=" + encodeURIComponent(promotionPriority.value) +
            "&prType=" + encodeURIComponent(prType));
    }

    document.getElementById('promotionForm').addEventListener('submit', savePromotionDetails);

</script>

</body>
</html>
