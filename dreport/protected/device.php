<?php
define('DREPORT_INIT', true);
require_once __DIR__ . '/init.php';

// Initialize session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
checkAuth();

// Initialize variables
$deviceid = isset($_SESSION['s_deviceid']) ? $_SESSION['s_deviceid'] : '0';
$objectid = isset($_SESSION['s_objectid']) ? $_SESSION['s_objectid'] : '';

include_once 'language.php';

include('database.class.php');
$pDatabase = Database::getInstance();
$pDatabase->query("set names 'utf8'");

if(isset($_GET["id"])){
 $deviceid = $_GET["id"];
}
$_SESSION['s_deviceid'] = $deviceid;

?>
<html>
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=EmulateIE11" />
  <title>Detelina Reports</title>
  <link rel="stylesheet" href="css/style.css">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="css/w3.css">
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
  .color-1 { background-color: #2d7fc1; }
  .color-5 { background-color: #FBB03B; }
  
  .color-1:hover, .color-1:active  {
    background-color: #276da6;  
  }  
  .color-5:hover, .color-5:active  {
    background-color: #805c99;  
  }
  
</style>

  <script>
  </script>
</head>

<body>
  <div class="login-card">
    <div class="login-help">
      <a href="http://eltrade.com">www.eltrade.com</a> • <a href="http://eltrade.com/bg/contacts"><?php echo $lang['contacts']; ?></a> •
       <?php
         if ($_SESSION['lang'] == 'bg'){
          echo '<a href="device.php?lang=en"><img src="images/en.png" /></a>';
         } else {
          echo '<a href="device.php?lang=bg"><img src="images/bg.png" /></a>';
         }
       ?>
    </div>
    <h1><?php echo $lang['DETELINA'].' '.$lang['OBJECTS']; ?></h1><br>

    <center>
<?php
    //list all objects for this device
    $qry = $pDatabase->query("select d_objectname, d_objectid FROM t_devices where d_deviceid = '". sql_safe($_SESSION['s_deviceid'])."' order by d_objectname");
    while ($row = mysqli_fetch_assoc($qry)) {
      echo '<a href="rptlist.php?id='.$row['d_objectid'].'&n='.htmlspecialchars($row['d_objectname']).'" class="medium color-1 button">'.htmlspecialchars($row['d_objectname']).'</a>';
    }
?>
    </center>
    <center><a href="addobject.php" class="medium blue round button" style="width:auto; box-shadow: rgb(38, 70, 83) 0px 11px 8px -5px;">+</a></center>
  </div>
</body>
</html>
