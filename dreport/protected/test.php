<?php
 session_start();
 if(!isset($_SESSION['s_authenticated']) || empty($_SESSION['s_authenticated'])){
 header("location:../index.php");
 }
 include_once 'language.php';

 if(isset($_POST) && !empty($_POST))
{
  include('database.class.php');
  $pDatabase = Database::getInstance();
  $result = $pDatabase->query("set names 'utf8'");

  $devid = $_SESSION['s_deviceid'];
  $oname = mysql_real_escape_string(stripslashes($_POST['oname']));
  $oid = mysql_real_escape_string(stripslashes($_POST['oid']));
  $opsw = mysql_real_escape_string(stripslashes($_POST['opsw']));

  $pDatabase->logevent(OPER_ADDOBJECT,$devid,'Add object: '.$oid.' name: '.$oname);
  //TODO: remove this check. Object is uniques for device
  // check if object is unique in devices table
  $sql = "select d_objectid from t_devices where d_objectid = '$oid' and d_deviceid='$devid'";
  $result = $pDatabase->query($sql);
  if (mysql_num_rows($result) > 0) {
    $pDatabase->show_alert(ALERT_ERROR,$lang["AlertError"],$lang["errObjectIDAlreadyExist"],true,true);
    $pDatabase->logevent(OPER_ERROR,$devid,'Add object: '.$oid.' name: '.$oname.' error: '.$lang["errObjectIDAlreadyExist"]);
  } else {
      // check if object is subscribed
      $sql = "select s_expiredate, s_customername,s_active from t_subscriptions where s_objectid = '$oid'";
      $result = $pDatabase->query($sql);
      if (mysql_num_rows($result) == 0) {
          // Object not subscribed
          $pDatabase->show_alert(ALERT_ERROR,$lang["AlertError"],$lang["errObjectNotSubscribed"],true,true);
          $pDatabase->logevent(OPER_ERROR,$devid,'Add object: '.$oid.' name: '.$oname.' error: '.$lang["errObjectNotSubscribed"]);
      } else {
          while($row = mysql_fetch_array($result)) {
            $expiredate = strtotime($row['s_expiredate']);
            $custname = $row['s_customername'];
            $active = $row['s_active'];
          }
          // check whether object is active
          if ($active == 0){
             $pDatabase->show_alert(ALERT_WARNING,$lang["AlertWarning"],$lang["errObjectNotActive"],false,false);
             $pDatabase->logevent(OPER_ERROR,$devid,'Add object: '.$oid.' name: '.$oname.' error: '.$lang["errObjectNotActive"]);
          // check whether object have valid subscription
          } else {
//               if($expiredate <= time()){
//                 $pDatabase->show_alert(ALERT_WARNING,$lang["AlertWarning"],$lang["errObjectExpired"].date("d.m.Y", $expiredate),false,false);
//                 $pDatabase->logevent(OPER_ERROR,$devid,'Add object: '.$oid.' name: '.$oname.' error: '.$lang["errObjectExpired"]);
//              } else {
                  // insert data in database
                  $opsw = md5($opsw);
                  $sql = "INSERT INTO t_devices(d_deviceid,d_objectname,d_objectid,d_objectpswd)";
                  $sql .= "VALUES ('".$_SESSION['s_deviceid']."','$oname','$oid','$opsw')";

                  $result = $pDatabase->query($sql);
                  if($result) {
                     echo '<script>location.href = "device.php?id='.$_SESSION['s_deviceid'].'";</script>';
                  } else {
                    //echo 'Error save data to database.';
                    $pDatabase->show_alert(ALERT_ERROR,$lang["AlertError"],mysql_error(),true,false);
                    $pDatabase->logevent(OPER_ERROR,$devid,'Add object: '.$oid.' name: '.$oname.' error: '.mysql_error());
                  }
//              } //else insert in database
          } // else onject is active
      } //else object subscription
  } //else onject unique
} // post

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
  <script type="text/javascript" src="js/jquery.min.js" ></script>
  <script type="text/javascript" src="js/jquery.alerts.js"></script>

  <script>


    $(document).on('click','.alert-box',function(){
        $(this).closest('div').fadeTo(300,0,function(){
           var element = document.getElementById("alert-box");
           element.parentNode.removeChild(element);
        });
    });


    function myClose(){
      setTimeout(function(){
          //console.log("timer");
            $(this).closest('div').removeClass("alert-box error");
    }, 1000);
    }


    function btnclick() {
         var f = document.getElementsByTagName('form')[0];
         var oname=document.forms["addobject"]["oname"].value;
         var oid=document.forms["addobject"]["oid"].value;
         var opsw=document.forms["addobject"]["opsw"].value;
         if (oname==null || oname=="",oid==null || oid=="",opsw==null || opsw=="") {

            jAlert(<?php echo "'".$lang['errAllFieldsAreMandatory']."','".$lang["AddObjectTitle"]."'"; ?> );
          } else {
            f.submit();
          }
/*
      this.form.submit(); this.disabled=true; this.value='Запис…';
      jConfirm('Обекта ще бъде добавен.Желаете ли да продължите?', 'Добавяне обект', function(r)
       {
         if (r) {
           document.getElementById("addobject").submit();
         }
           //jAlert('Confirmed: ' + r, 'Confirmation Results');
       });
      //document.getElementById("demo").style.color = "red";
*/
    }
</script>

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
     <input type="text" name="oid" id="oid" placeholder="<?php echo $lang['ObjectID']; ?>" autocomplete="off"  required maxlength="20">
     <input type="password" name="opsw" id="opsw" placeholder="<?php echo $lang['ObjectPswd']; ?>" autocomplete="off" required maxlength="20">
     <input type="button" name="add-obj"  class="medium green button" onClick="btnclick();" value="<?php echo $lang['btnAddObject']; ?>" style="width: 90%; margin-bottom: 15px; margin-left: 10px; margin-top: 10px;">
     <a href="device.php" class="medium blue button" style = "margin-left: 10px; width: 90%;"><?php echo $lang['btnExit']; ?></a>
    </center>

   </form>

  <div class="login-help">
    <a><?php echo $lang['infoAddObjectSeetings']; ?></a>
  </div>

  </div>
</body>
</html>
