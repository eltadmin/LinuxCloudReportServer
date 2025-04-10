<?php 
session_start();
header("Content-Type: text/html;charset=UTF-8");
//define paths
$root=pathinfo($_SERVER['SCRIPT_FILENAME']);
define ('BASE_FOLDER', basename($root['dirname']));
define ('SITE_ROOT',    realpath(dirname(__FILE__)));
define ('SITE_URL',    'http://'.$_SERVER['HTTP_HOST'].'/'.BASE_FOLDER);


include_once SITE_ROOT.'/protected/language.php';
if(isset($_GET['id']) && !empty($_GET['id']))
 {
      $deviceid = $_GET['id'];

      include(SITE_ROOT.'/protected/database.class.php');  
      $pDatabase = Database::getInstance();
      $result = $pDatabase->query("set names 'utf8'");
    
      $_SESSION['s_deviceid'] = $deviceid;
      $_SESSION['s_authenticated'] = true;
      // read system settings                           
      $qry = $pDatabase->query("select * from t_settings");
      while ($row = mysqli_fetch_assoc($qry)) {
        switch ($row['s_name']) {
            case "rpt_server_host":
                $_SESSION['s_rpt_server_host'] = $row['s_value'];
                break;
            case "rpt_server_port":
                $_SESSION['s_rpt_server_port'] = $row['s_value'];
                break;
            case "rpt_server_user":
                $_SESSION['s_rpt_server_user'] = $row['s_value'];
                break;
            case "rpt_server_pswd":
                $_SESSION['rpt_server_pswd'] = $row['s_value'];
                break;
            case "log_level":
                $_SESSION['log_level'] = $row['s_value'];
                break;
    
        } //end swithch                   
      } // while
            
      $pDatabase->logevent(OPER_STARTAPP,$deviceid,'');      
      echo("<script>location.href = 'protected/device.php';</script>");  

}else{
?>

<html>
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=EmulateIE11" />
  <title><?php echo $lang['SiteTitle']; ?></title>    
  <link rel="stylesheet" href="protected/css/style.css">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="protected/css/w3.css">
  
 
</head>
<body>

  <div class="login-help">
   <?php
     if ($_SESSION['lang'] == 'bg'){
      echo '<a href="index.php?lang=en"><img src="protected/images/en.png" /></a>';   
     } else {
      echo '<a href="index.php?lang=bg"><img src="protected/images/bg.png" /></a>';    
     }           
        
    ?>
  </div>

  <div class="login-card">
    <h1><?php echo $lang['DETELINA']; ?></h1>
    <h1><?php echo $lang['reports']; ?></h1><br>
    <h1>1.0.0</h1><br>
    <div class="login-help">
      <a href="http://eltrade.com" target="_blank">www.eltrade.com</a> • <a href="http://eltrade.com/bg/contacts"><?php echo $lang['contacts']; ?></a>
    </div>
  </div>
</body>
</html>
<?php } 
?>

