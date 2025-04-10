<?php
session_start();
if (!isset($_SESSION['s_authenticated']) || empty($_SESSION['s_authenticated'])) {
    header("location:../index.php");
    exit();
}
include_once 'language.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_POST)) {

	
    include('database.class.php');
    $pDatabase = Database::getInstance();
    $pDatabase->query("SET NAMES 'utf8'"); 
	

    $devid = $_SESSION['s_deviceid'];
    $connection = $pDatabase->getConnection();
    $oname = mysqli_real_escape_string($connection, stripslashes($_POST['oname']));
    $oid = mysqli_real_escape_string($connection, stripslashes($_POST['oid']));
    $opsw = mysqli_real_escape_string($connection, stripslashes($_POST['opsw'])); 
 

    $pDatabase->logevent(OPER_ADDOBJECT, $devid, 'Add object: ' . $oid . ' name: ' . $oname);

    // Check if object is unique in devices table
    $sql = "SELECT d_objectid FROM t_devices WHERE d_objectid = '$oid' AND d_deviceid='$devid'";
    $result = $pDatabase->query($sql);

    if (mysqli_num_rows($result) > 0) {
        $pDatabase->show_alert(ALERT_ERROR, $lang["AlertError"], $lang["errObjectIDAlreadyExist"], true, true);
        $pDatabase->logevent(OPER_ERROR, $devid, 'Add object: ' . $oid . ' name: ' . $oname . ' error: ' . $lang["errObjectIDAlreadyExist"]);
    } else {
        // Check if object is subscribed
        $sql = "SELECT s_expiredate, s_customername, s_active FROM t_subscriptions WHERE s_objectid = '$oid'";
        $result = $pDatabase->query($sql);

        if (mysqli_num_rows($result) == 0) {
            // Object not subscribed
            $pDatabase->show_alert(ALERT_ERROR, $lang["AlertError"], $lang["errObjectNotSubscribed"], true, true);
            $pDatabase->logevent(OPER_ERROR, $devid, 'Add object: ' . $oid . ' name: ' . $oname . ' error: ' . $lang["errObjectNotSubscribed"]);
        } else {
            while ($row = mysqli_fetch_array($result)) {
                $expiredate = strtotime($row['s_expiredate']);
                $custname = $row['s_customername'];
                $active = $row['s_active'];
            }
            // Check whether object is active
            if ($active == 0) {
                $pDatabase->show_alert(ALERT_WARNING, $lang["AlertWarning"], $lang["errObjectNotActive"], false, false);
                $pDatabase->logevent(OPER_ERROR, $devid, 'Add object: ' . $oid . ' name: ' . $oname . ' error: ' . $lang["errObjectNotActive"]);
            } else {
                // Insert data in database
                $opsw = md5($opsw);
                $sql = "INSERT INTO t_devices (d_deviceid, d_objectname, d_objectid, d_objectpswd) VALUES ('$devid', '$oname', '$oid', '$opsw')";
                $result = $pDatabase->query($sql);

                if ($result) {
                    if (!empty($_POST['operatorid']) && !empty($_POST['operatorpswd'])) {
                        $operatorid = mysqli_real_escape_string($connection, stripslashes($_POST['operatorid']));
                        $operatorpswd = mysqli_real_escape_string($connection, stripslashes($_POST['operatorpswd']));

                        $operatorpswd = encryptPassword($operatorpswd);

                        $sql = "UPDATE t_devices SET d_editUsername = '$operatorid', d_editPassword = '$operatorpswd' 
                                WHERE d_deviceid = '$devid' AND d_objectid = '$oid'";
                        $result = $pDatabase->query($sql);

                        if (!$result) {
                            $pDatabase->show_alert(ALERT_ERROR, $lang["AlertError"], mysqli_error($connection), true, false);
                            $pDatabase->logevent(OPER_ERROR, $devid, 'Add operator: ' . $operatorid . ' error: ' . mysqli_error($connection));
                        }
                    }
                    echo '<script>location.href = "device.php?id=' . $_SESSION['s_deviceid'] . '";</script>';
                } else {
                    $pDatabase->show_alert(ALERT_ERROR, $lang["AlertError"], mysqli_error($connection), true, false);
                    $pDatabase->logevent(OPER_ERROR, $devid, 'Add object: ' . $oid . ' name: ' . $oname . ' error: ' . mysqli_error($connection));
                }
            }
        }
    }
}

function encryptPassword($plaintext) {
    $encryption_key = 'IVi}|7D"Te7m5h6eZ.E)8.f'; 
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encryptedPassword = openssl_encrypt($plaintext, 'aes-256-cbc', $encryption_key, 0, $iv);
    return base64_encode($iv . $encryptedPassword);
}
?>



<html>
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=EmulateIE11" />
  <title>Detelina Reports</title>
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/box.css">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="css/w3.css">
  <link rel="stylesheet" href="css/jquery.alerts.css">
  <script type="text/javascript" src="js/jquery.min.js"></script>
  <script type="text/javascript" src="js/jquery.alerts.js"></script>
  <script>
    $(document).on('click', '.alert-box', function(){
        $(this).closest('div').fadeTo(300,0,function(){
           var element = document.getElementById("alert-box");
           element.parentNode.removeChild(element);
        });
    });

    function myClose(){
      setTimeout(function(){
          $(this).closest('div').removeClass("alert-box error");
      }, 1000);
    }

    function btnclick() {
        var f = document.getElementsByTagName('form')[0];
        var oname = document.forms["addobject"]["oname"].value;
        var oid = document.forms["addobject"]["oid"].value;
        var opsw = document.forms["addobject"]["opsw"].value;
        var operatorid = document.forms["addobject"]["operatorid"].value;
        var operatorpswd = document.forms["addobject"]["operatorpswd"].value;

        if (oname == null || oname == "", oid == null || oid == "", opsw == null || opsw == "") {
            jAlert(<?php echo "'".$lang['errAllFieldsAreMandatory']."','".$lang["AddObjectTitle"]."'"; ?> );
        } else {
            if (operatorid != "" && operatorpswd != "") {
                var xhr = new XMLHttpRequest();
                xhr.open('POST', 'addUserPassEdit.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onload = function() {
                    if (xhr.status === 200) {
                        f.submit();
                     } else {
                        alert('Error creating table. Server responded with status: ' + xhr.status);
                    }
                };
                xhr.send('editUserName=' + encodeURIComponent(operatorid) + '&editPassword=' + encodeURIComponent(operatorpswd));
            } else {
                f.submit();
            }
        }
    }
  </script>
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
  .color-1 { background-color: #7db026; }
  .color-5 { background-color: #FBB03B; }
  
  .color-1:hover, .color-1:active  {
    background-color: #6d9c1c;  
  }  
  .color-5:hover, .color-5:active  {
    background-color: #e18e0a;  
  }
  
</style>
</head>
<body>
  <div class="login-card">
    <div class="login-help">
      <a href="http://eltrade.com">www.eltrade.com</a> • <a href="http://eltrade.com/bg/contacts"><?php echo $lang['contacts']; ?></a> •
       <?php
         if ($_SESSION['lang'] == 'bg'){
            echo '<a href="addobject.php?lang=en"><img src="images/en.png" /></a>';
         } else {
            echo '<a href="addobject.php?lang=bg"><img src="images/bg.png" /></a>';
         }
       ?>
    </div>

    <h1><?php echo $lang['DETELINA']; ?></h1>
    <h1><?php echo $lang['AddObjectTitle']; ?></h1><br>

    <form action="" method="post" class="addobject" id="addobject" autocomplete="off">
        <center>
            <input type="text" name="oname" id="oname" autocomplete="off" placeholder="<?php echo $lang['ObjectName']; ?>" required maxlength="20">
            <input type="text" name="oid" id="oid" placeholder="<?php echo $lang['ObjectID']; ?>" autocomplete="off" required maxlength="20">
            <input type="password" name="opsw" id="opsw" placeholder="<?php echo $lang['ObjectPswd']; ?>" autocomplete="off" required maxlength="20">
            
            <div style="margin-top: 20px; font-weight: bold; text-align: center; font-size: 10px; font-family: Arial, Helvetica, sans-serif; font-weight: bold; color: red;">
                <?php echo $lang['OptionalFields']; ?> 
            </div>
            
            <input type="text" name="operatorid" id="operatorid" placeholder="<?php echo $lang['ObjectOperatorID']; ?>" autocomplete="off" maxlength="20" style="margin-top: 10px;">
            <input type="password" name="operatorpswd" id="operatorpswd" placeholder="<?php echo $lang['ObjectOperatorPswd']; ?>" autocomplete="off" maxlength="20" style="margin-top: 10px;">
            
            <input type="button" name="add-obj" class="medium color-1 button" onClick="btnclick();" value="<?php echo $lang['btnAddObject']; ?>" style="width: 95%; margin-bottom: 15px; margin-left: 10px; margin-top: 10px;border-color:#7db026;">
            <a href="device.php" class="medium color-5 button" style="margin-left: 10px; width: 95%;"><?php echo $lang['btnExit']; ?></a>
        </center>
    </form>

    <div class="login-help">
        <a><?php echo $lang['infoAddObjectSeetings']; ?></a>
    </div>
  </div>
</body>
</html>
