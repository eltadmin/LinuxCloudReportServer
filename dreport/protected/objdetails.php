<?php
session_start();

if (!isset($_SESSION['s_authenticated']) || empty($_SESSION['s_authenticated'])) {
    header("location:../index.php");
    die();
}
define("C_DEBUG", false);
define("C_BAR_HEIGHT", 20);
define("C_CANVAS_HEIGHT", 30);
define("C_RPTNAME", "updateDetails");
define("C_TIMEOFFSET", 0);
$otimeoffset = C_TIMEOFFSET;
$rname = C_RPTNAME;
include_once 'language.php';

$objectid = "";
if (isset($_REQUEST["id"])) {
    $objectid = $_REQUEST["id"];
} else {
    $objectid = $_SESSION['s_objectid'];
}
$_SESSION['s_objectid'] = $objectid;

if (isset($_SESSION['s_deviceid'])) {
    $deviceid = $_SESSION['s_deviceid'];
} else {
    $deviceid = "0";
}

$errMsg = "";
if (isset($_REQUEST["err"])) {
    switch (intval($_REQUEST["err"])) {
        case 1:
            $errMsg = $lang["objEnter"] . $lang["objName"];
            break;

        case 2:
            $errMsg = $lang["objOldPassErr"];
            break;

        case 3:
            $errMsg = $lang["objPassLength"];
            break;

        case 4:
            $errMsg = $lang["objPassMatch"];
            break;
    }
}

include('database.class.php');

$pDatabase = Database::getInstance();
$result = $pDatabase->query("set names 'utf8'");

$deviceid = sql_safe($deviceid);
$objectid = sql_safe($objectid);

// Get object details
$sql = "select t_devices.d_objectname, t_devices.d_objectpswd, t_devices.d_timeoffset, t_subscriptions.* from t_devices inner join t_subscriptions on d_objectid = s_objectid where d_deviceid = '$deviceid' and d_objectid ='$objectid'";

$qry = $pDatabase->query($sql);

if (!$row = mysqli_fetch_assoc($qry)) {
    header("location: device.php");
    die();
}

$expiredate = strtotime($row['s_expiredate']);
$customername = $row['s_customername'];
$opassword = $row['d_objectpswd'];
$objectpswd = $row['d_objectpswd']; // Ensure this variable is defined
$otimeoffset = $row['d_timeoffset'];

if (isset($_POST) && !empty($_POST)) {
    $oname = sql_safe($_POST['oname']);
    $otimeoffset =  sql_safe($_POST['otimeoffset']);

    if (trim(strlen($oname)) < 1) {
        header("location: objdetails.php?err=1");
        die();
    }

    $pDatabase->logevent(OPER_ADDOBJECT, $deviceid, 'Edit object: ' . $objectid . ' name: ' . $oname);
  
    // Update the object details
    $sql = "UPDATE t_devices SET d_objectname = '$oname', d_timeoffset = '$otimeoffset' WHERE d_deviceid = '$deviceid' and d_objectid ='$objectid'";
    $result = $pDatabase->query($sql);

    if ($result) {
        $_SESSION['s_objectname'] = $oname;
        $_SESSION['s_timeoffset'] = $otimeoffset;
        
        // Check if password change is requested
        if (isset($_POST['chPass'])) {
            $oldpassword = md5($_POST['oldpassword']);
            $password = md5($_POST['password']);
            $password2 = md5($_POST['password']);

            if ($oldpassword != $opassword) {
                header("location: objdetails.php?err=2");
                die();
            }

            if (strlen($password) < 3) {
                header("location: objdetails.php?err=3");
                die();
            }

            if ($password != $password2) {
                header("location: objdetails.php?err=4");
                die();
            }

            $pDatabase->logevent(OPER_ADDOBJECT, $deviceid, 'ChPassword object: ' . $objectid . ' name: ' . $oname);

            $sql = "UPDATE t_devices SET d_password = '$password' WHERE d_deviceid = '$deviceid' and d_objectid ='$objectid'";
            $result = $pDatabase->query($sql);

            if ($result) {
                header("location: objdetails.php?saved=1");
                die();
            } else {
                $pDatabase->show_alert(ALERT_ERROR, $lang["AlertError"], mysqli_error($pDatabase->getConnection()), true, false);
                $pDatabase->logevent(OPER_ERROR, $deviceid, 'ChPassword object: ' . $objectid . ' name: ' . $oname . ' error: ' . mysqli_error($pDatabase->getConnection()));
            }
        }

        // Check if operator ID and password are provided
        if (!empty($_POST['editUserName']) && !empty($_POST['editPassword'])) {
            $editUserName = sql_safe($_POST['editUserName']);
            $editPassword = sql_safe($_POST['editPassword']);
            $encryptedPassword = encryptPassword($editPassword);

            $updateSql = "UPDATE t_devices SET d_editUsername = '$editUserName', d_editPassword = '$encryptedPassword' WHERE d_deviceid = '$deviceid' AND d_objectid = '$objectid'";

            $result = $pDatabase->query($updateSql);

            if ($result === FALSE) {
                echo "Error performing update";
            } else {
                // Log the update in the t_statistics table
                $s_datetime = date('Y-m-d H:i:s');
                $s_description = "report: addUserPassEdit.php, Added new Username/Password: $editUserName / $editPassword";
                $logSql = "INSERT INTO t_statistics (s_opertype, s_operid, s_datetime, s_description) 
                           VALUES (10, '$objectid', '$s_datetime', '$s_description')";

                $logResult = $pDatabase->query($logSql);

                if ($logResult === FALSE) {
                    echo "Error logging the update";
                } else {
                    // Perform operator validation after the update
                    $isOperatorValidated = validateOperator($editUserName, $encryptedPassword, $deviceid, $objectid, $pDatabase, $lang, $rname, $objectpswd);
                    if ($isOperatorValidated === '1') {
                        $_SESSION['success_message'] = $lang["objSavedOperatorInfo"];
                        header("location: objdetails.php?saved=1");
                        die();
                    } else {
                        $pDatabase->show_alert(ALERT_WARNING, $lang["warnObjOperatorNotValid"], '', true, false);
                    }
                }
            }
        } else {
            $_SESSION['success_message'] = $lang["objSuccess"];
            header("location: objdetails.php?saved=1");
            die();
        }
    } else {
        $pDatabase->show_alert(ALERT_ERROR, $lang["errObjOperatorNotValid"], mysqli_error($pDatabase->getConnection()), true, false);
        $pDatabase->logevent(OPER_ADDOBJECT, $deviceid, 'Edit object: ' . $objectid . ' name: ' . $oname . ' error: ' . mysqli_error($pDatabase->getConnection()));
    }
} // post

function encryptPassword($plaintext) {
    $encryption_key = 'IVi}|7D"Te7m5h6eZ.E)8.f'; 
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encryptedPassword = openssl_encrypt($plaintext, 'aes-256-cbc', $encryption_key, 0, $iv);
    return base64_encode($iv . $encryptedPassword);
}

function decryptPassword($encrypted) {
    $encryption_key = 'IVi}|7D"Te7m5h6eZ.E)8.f'; 
    $data = base64_decode($encrypted);
    $iv = substr($data, 0, openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = substr($data, openssl_cipher_iv_length('aes-256-cbc'));
    return openssl_decrypt($encrypted, 'aes-256-cbc', $encryption_key, 0, $iv);
}

function validateOperator($editUserName, $editPassword, $deviceid, $objectid, $pDatabase, $lang, $rname, $objectpswd) {
    $C_SQL_OPERATORS = '{"Id":"DatabaseId","Pass":"' . $objectpswd . '","PLUESQuery":{"Type":"Query","SQL":"SELECT OPERATOR_USERNAME, OPERATOR_PASSWORD, OPERATOR_ACCESS, OPERATOR_ACTIVETODATE FROM N_OPERATORS WHERE OPERATOR_USERNAME = \'' . $editUserName . '\'"}}';

    $url = 'http://' . $_SESSION['s_rpt_server_host'] . ':' . $_SESSION['s_rpt_server_port'] . '/report/' . $rname . '/?id=' . $objectid . '&u=' . $_SESSION['s_rpt_server_user'] . '&p=' . $_SESSION['rpt_server_pswd'];
    $obj = json_decode($C_SQL_OPERATORS);
    $obj->{"Id"} = $objectid;
    $rptsql = json_encode($obj);

    $options = array(
        'http' => array(
            'timeout' => 45,
            'header' => "Content-type: text/xml\r\n",
            'method' => 'GET',
            'content' => $rptsql
        ),
    );

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
                $dbEditPassword = decryptPassword($deviceRow['d_editPassword']);

                // Validate passwords
                $operatorPasswordValid = validatePassword($dbEditPassword, $dbOperatorPassword);

                // Check if OPERATOR_ACTIVETODATE is not less than today's date
                $currentDate = new DateTime();
                if ($operatorActiveToDate !== null && $operatorActiveToDate !== '30.12.1899 00:00:00') {
                    $operatorActiveDate = new DateTime($operatorActiveToDate);
                } else {
                    // If the date is null or '30.12.1899 00:00:00', treat it as "forever"
                    $operatorActiveDate = null;
                }

                if ($dbOperatorUsername == $dbEditUsername && $operatorPasswordValid && ($operatorActiveDate === null || $operatorActiveDate >= $currentDate)) {
                    return '1';
                } else {
                    return '0';
                }
            }
        }
    }
    return '0';
}

function validatePassword($inputPassword, $storedPassword) {
    // Extract the salt from the stored password
    $salt = substr($storedPassword, 0, 2);

    // Encrypt input password with the extracted salt
    $encryptedPassword = crypt($inputPassword, $salt);

    // Compare encrypted passwords
    return hash_equals($encryptedPassword, $storedPassword);
}

?>


<html>
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=EmulateIE11" />
  <title>Detelina Reports</title>
  <link rel="stylesheet" href="css/style.css?rnd=<?=microtime()?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="css/w3.css">
  <link rel="stylesheet" href="css/jquery.alerts.css">
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
  .color-1 { background-color: #89cb3c; }
  .color-2 { background-color: #f32b2b; }
  .color-3 { background-color: #FBB03B; }
  
  .color-1:hover, .color-1:active  {
    background-color: #63af0a; /* Darker shade of color-5 */
  }  
  .color-2:hover, .color-2:active  {
    background-color: #d90000; /* Darker shade of color-5 */
  } 
  .color-3:hover, .color-3:active  {
    background-color: #e18e0a; /* Darker shade of color-5 */
  }
  
</style>
<script>

   function delbtnclick() {
      $.alerts.okButton= <?php echo "'".$lang["btnOk"]."';"; ?>
      $.alerts.cancelButton= <?php echo "'".$lang["btnCancel"]."';"; ?>
      jConfirm( <?php echo "'".$lang["confirmDeleteObject"]."','".$lang["DeleteObjectTitle"]."'"; ?> , function(r) {
         if (r) {
           window.location='deleteobject.php';
         }
       });
   }

   function saveObject() {
        var form = document.getElementById('addobject');
        var username = document.getElementById('editUserName').value;
        var password = document.getElementById('editPassword').value;

        if (username && password) {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'addUserPassEdit.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                if (xhr.status === 200) {
                    form.submit();
                } else {
                    alert('Error creating table. Server responded with status: ' + xhr.status);
                }
            };
            xhr.send('editUserName=' + encodeURIComponent(username) + '&editPassword=' + encodeURIComponent(password));
        } else {
            form.submit();
        }
    }

    $(document).ready(function (){

        $(document).on('click','.alert-box',function(){
            $(this).closest('div').fadeTo(300,0,function(){
                var element = document.getElementById("alert-box");
                element.parentNode.removeChild(element);
            });
        });

        $('#chPass').click(function () {
            var value = $(this).is(':checked');
            if (value){
                $('.elemHidden').show();
            }else{
                $('.elemHidden').hide();
                $('#elemHidden').val("");
                $('#oldpassword').val("");
                $('#password').val("");
                $('#password2').val("");
            }
        });
    });
</script>
</head>

<body>
  <div class="login-card">
    <div class="login-help">
      <?php
       if ($expiredate < time()){
         $pDatabase->show_alert(ALERT_WARNING, $lang["AlertWarning"], $lang["errObjectExpired"].date("d.m.Y", $expiredate), false, true);
        }

        if($errMsg){
            $pDatabase->show_alert(ALERT_ERROR, $lang["AlertWarning"], $errMsg, false, true);
        }

        if(isset($_REQUEST["saved"])){
            if(isset($_SESSION['success_message'])) {
                $successMessage = $_SESSION['success_message'];
                unset($_SESSION['success_message']);
                $pDatabase->show_alert(ALERT_SUCCESS, $lang["AlertWarning"], $successMessage, false, true);
            } else {
                $pDatabase->show_alert(ALERT_SUCCESS, $lang["AlertWarning"], $lang["objSuccess"], false, true);
            }
        }

       echo '<a>'.$customername.'</a>';
       if ($_SESSION['lang'] == 'bg'){
         echo '&nbsp;•&nbsp;<a href="objdetails.php?lang=en"><img src="images/en.png" /></a>';
       } else {
         echo '&nbsp;•&nbsp;<a href="objdetails.php?lang=bg"><img src="images/bg.png" /></a>';
       }
       echo '&nbsp;•&nbsp;<a href="javascript:history.go(0)"><img src="images/refresh.png" alt="refresh"></a>';
      ?>
    </div>

    <h1 align="center"><?=htmlspecialchars($_SESSION['s_objectname'])?></h1>
    <h3 align="center"><?=htmlspecialchars($row['s_customername'])?></h3>
    <h3 align="center"><?=$lang['objEIK'].': '.htmlspecialchars($row['s_eik'])?></h3>

    <br>

<form action="objdetails.php" method="post" class="" name="addobject" id="addobject" autocomplete="off">
    <input type="hidden" name="id" value="<?=htmlspecialchars($objectid)?>" />

<table>
    <tr>
        <th align="right"><h5><?=$lang['objObjectId']?>:</h5></th>
        <td><label class="control-label"><h5><?=$row['s_objectid']?></h5></label></td>
    </tr>
    <tr>
        <th align="right"><h5><?=$lang['objValidTo']?>:</h5></th>
        <td><label class="control-label"><h5><?=format_sql_date($row['s_expiredate'])?></h5></label></td>
    </tr>
    <tr>
        <th align="right"><h5><?=$lang['objName']?>:</h5></th>
        <td><label class="control-label"><h5><?=htmlspecialchars($row['s_objectname'])?></h5></label></td>
    </tr>
    <tr>
        <th align="right"><h5><?=$lang['objAddress']?>:</h5></th>
        <td><label class="control-label"><h5><?=htmlspecialchars($row['s_address'])?></h5></label></td>
    </tr>
    
    <tr>
        <th align="right"><h5><?=$lang['ObjectOperatorID']?>:</h5></th>
        <td><h5><input type="text" class="input" name="editUserName" id="editUserName" style="margin-top:10px;"></h5></td>
    </tr>
    <tr>
        <th align="right"><h5><?=$lang['ObjectOperatorPswd']?>:</h5></th>
        <td><h5><input type="password" class="input" name="editPassword" id="editPassword" style="margin-top:10px;"></h5></td>
    </tr>
<tr><td colspan="2" style="margin-top: 20px; font-weight: bold; text-align: center; font-size: 10px; font-family: Arial, Helvetica, sans-serif; font-weight: bold; color: red;"><h5><?=$lang['OptionalFields']?></h5></td></tr>
 
    <tr>
        <th align="right" valign="middle"><h5><?=$lang['objViewName']?>:</h5></th>
        <td><h5><input type="text" class="input" name="oname" id="oname" style="margin-top:10px;" value="<?=htmlspecialchars($row['d_objectname'])?>"></h5></td>
    </tr>

    <tr>
     <th align="right"><h5><?=$lang['objTimeOffset']?>:</h5></th>
     <td align="left">
      <h5>
      <select name="otimeoffset">
      <?php
       for ($i = 0; $i <= 8; $i++) {      
         if ($i == $otimeoffset) {
           echo '<option selected="selected" value="'.$i.'">+'.$i.'</option>'; 
         } else {
           echo '<option value="'.$i.'">+'.$i.'</option>'; 
         }                                                             
       }
      ?>
      </select>
      </h5>
     </td>
    </tr>
 
    <tr>
        <td></td>
        <td>
                <label class="control-label" for="chPass"><h5><input type="checkbox" name="chPass" id="chPass" value="1"> <?=$lang['objChPass']?></h5></label>
        </td>
    </tr>

    <tr class="elemHidden">
        <th align="right"><h5><?=$lang['objOldPassword']?>:</h5></th>
        <td><h5><input type="password" class="input" name="oldpassword" id="oldpassword" value=""></h5></td>
    </tr>

    <tr class="elemHidden">
        <th align="right"><h5><?=$lang['objNewPassword']?>:</h5></th>
        <td><h5><input type="password" class="input" name="password" id="password" value=""></h5></td>
    </tr>

    <tr class="elemHidden">
        <th align="right"><h5><?=$lang['objNewPassword2']?>:</h5></th>
        <td><h5><input type="password" class="input" name="password2" id="password2" value=""></h5></td>
    </tr>
</table>

<br />

<center>
<a href="#" class="medium color-1 button" onclick="saveObject()"><?=$lang["objSave"]?></a>
<a href="#" class="medium color-2 button" onclick="delbtnclick();"><?php echo $lang["objDelete"]; ?></a>
<a href="rptlist.php" class="medium color-3 button"><?php echo $lang["btnExit"]; ?></a>
</center>

        </form>

  </div>
</body>
</html>
