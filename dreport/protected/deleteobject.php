<?php
    session_start();
    if(!isset($_SESSION['s_authenticated']) || empty($_SESSION['s_authenticated'])){
    header("location:../index.php");
    }


    $deviceid = "0";
    $objid = "0";

    if(isset($_SESSION['s_objectid'])){
     $objid = $_SESSION['s_objectid'];
    }

    if(isset($_SESSION['s_deviceid'])){
     $deviceid = $_SESSION['s_deviceid'];
    }

    include('database.class.php');
    $pDatabase = Database::getInstance();
    $result = $pDatabase->query("set names 'utf8'");

    $pDatabase->logevent(OPER_DELOBJECT,$deviceid,'Delete object: '.$objid);

    $sql = "delete from t_devices where d_deviceid= '".sql_safe($deviceid)."' and d_objectid ='".sql_safe($objid)."'";

    //@mysql_query($sql);
    //$result = mysql_query($sql);
    $result = $pDatabase->query($sql);
    if($result)
     {
         echo("<script>location.href = 'device.php?id=$deviceid';</script>");

     }
    else
     {
        echo "Error save data to database.";
        $pDatabase->logevent(OPER_ERROR,$deviceid,'Delete object: '.$objid.' error: '.mysqli_error());
     }

?>
