<?php
     
define('OBJECT_CREATED_SUCCESSFULLY', 0);
define('OBJECT_UPDATE_EXISTED', 1); 
define('OBJECT_CREATE_FAILED', 2);

 
/**
 * Class to handle all db operations
 * This class will have CRUD methods for database tables
 */
class DbHandler {
 
   // private $conn;
    private $pDatabase;
 
    function __construct() {
        require_once('database.class.php');  
        $this->pDatabase = Database::getInstance();
        $this->conn = $this->pDatabase->getConnection(); // Add this line
        $this->pDatabase->query("set names 'utf8'");
    }


    public function logevent($opertype,$operid, $operdecription) {
            
        $result = $this->pDatabase->logevent($opertype,$operid, $operdecription);
        if (!$result) {
            return false;
        } else {
            return true;
        }
                            
    }

    public function getIPwhitelist() {
        $result = array(); 
        $rs = $this->pDatabase->query("SELECT r_ip FROM t_restip");
        while ($row = mysqli_fetch_assoc($rs)) {
            array_push($result, $row['r_ip']);  
        } 
        return $result;        
    }

    
     
    /**
     * Creating or update if exist Object 
     * @param objectid
     * @param objectname 
     * @param customername 
     * @param eik
     * @param address
     * @param hostname 
     * @param appip
     * @param apptype
     * @param appver
     * @param appdbtype
     * @param comment
     * @return 0 - insert, 1 fail, 2 update
     */
    public function createObject($objectid, $objectname, $customername, $eik, $address, $hostname, $appip, $apptype, $appver, $appdbtype, $comment) {
        $response = array();
        
        $objectid = mysqli_real_escape_string($this->conn, $objectid);
        $objectname = mysqli_real_escape_string($this->conn, $objectname);
        $customername = mysqli_real_escape_string($this->conn, $customername);
        $eik = mysqli_real_escape_string($this->conn, $eik);
        $address = mysqli_real_escape_string($this->conn, $address);
        $hostname = mysqli_real_escape_string($this->conn, $hostname);
        $appip = mysqli_real_escape_string($this->conn, $appip);
        $apptype = mysqli_real_escape_string($this->conn, $apptype);
        $appver = mysqli_real_escape_string($this->conn, $appver);
        $appdbtype = mysqli_real_escape_string($this->conn, $appdbtype);
        $comment = mysqli_real_escape_string($this->conn, $comment);

        $qry = $this->pDatabase->query("select * from t_settings");
        $trial_days = -1;
        while ($row = mysqli_fetch_assoc($qry)) {
            switch ($row['s_name']) {
                case "trial_days":
                    $trial_days = $row['s_value'];
                    break;
            }
        }
        if ($trial_days <= 0) {$trial_days = 10;}
        $date = date("Y-m-d", time() + 86400*$trial_days);
        
        if (!$this->isObjectExists($objectid)) {
            $qry = "INSERT INTO t_subscriptions(s_objectid, s_objectname, s_expiredate, s_customername, s_eik, s_address, s_hostname, s_appip, s_apptype, s_appver, s_appdbtype, s_active";
            if ($comment !== '') { $qry .=", s_comment"; }
            $qry .= ") VALUES ('$objectid', '$objectname', '".$date."', '$customername', '$eik', '$address', '$hostname', '$appip', '$apptype', '$appver', '$appdbtype', 1";
            if ($comment !== '') { $qry .=",'$comment'"; }
            $qry .= ")";
            $result = $this->pDatabase->query($qry);
            return $result ? OBJECT_CREATED_SUCCESSFULLY : OBJECT_CREATE_FAILED;
        } else {
            $qry = "UPDATE t_subscriptions SET s_objectname='$objectname', s_customername='$customername', s_eik='$eik', s_address='$address', s_hostname='$hostname', s_lastupdatedate='".date("Y-m-d H:i:s")."'";
            $qry .= ", s_appip='$appip', s_apptype='$apptype', s_appver='$appver', s_appdbtype='$appdbtype'";
            if ($comment !== '') { $qry .=", s_comment='$comment'"; }
            $qry .= " WHERE s_objectid='$objectid'";
            $result = $this->pDatabase->query($qry);
            return $result ? OBJECT_UPDATE_EXISTED : OBJECT_CREATE_FAILED;
        }
    }

    /**
     * Checking for duplicate objectid
     * @param String $objectid to check in db
     * @return boolean
     */
    public function isObjectExists($objectid) {
        $objectid = mysqli_real_escape_string($this->conn, $objectid);
        $rs = $this->pDatabase->query("SELECT s_objectid FROM t_subscriptions WHERE s_objectid = '$objectid'");
        $num_rows = $rs ? mysqli_num_rows($rs) : 0;
        if ($rs) {
            mysqli_free_result($rs);
        }
        return $num_rows > 0;
    }


    /**
     * Generate unique objectid
     * @param none
     * @return unique objectid
     */
    public function generateObjectId() {
        $objid = substr(md5(rand()), 0, 8);
        $rs = $this->pDatabase->query("SELECT s_objectid FROM t_subscriptions WHERE s_objectid = '$objid'");
        $num_rows = $rs ? mysqli_num_rows($rs) : 0;
        if ($rs) {
            mysqli_free_result($rs);
        }
        while ($num_rows > 0) {
            $objid = substr(md5(rand()), 0, 8);
            $rs = $this->pDatabase->query("SELECT s_objectid FROM t_subscriptions WHERE s_objectid = '$objid'");
            $num_rows = $rs ? mysqli_num_rows($rs) : 0;
            if ($rs) {
                mysqli_free_result($rs);
            }
        }
        return $objid;
    }
    
    
    
    /**
     * Get object subscription data
     * @param String $objectid
     * @return array, all columns for this object
     */
    public function getObjectData($objectid) {
        $rs = $this->pDatabase->query("SELECT * FROM t_subscriptions WHERE s_objectid = '$objectid'");
        $tmp = array();
        while($obj = mysqli_fetch_assoc($rs)){
            $tmp["objectid"] = $obj["s_objectid"];
            $tmp["objectname"] = $obj["s_objectname"];
            $tmp["expiredate"] = $obj["s_expiredate"];
            $tmp["active"] = $obj["s_active"];
            $tmp["createdate"] = $obj["s_createdate"];
            $tmp["lastupdatedate"] = $obj["s_lastupdatedate"];
            $tmp["appip"] = $obj["s_appip"];
            $tmp["apptype"] = $obj["s_apptype"];
            $tmp["appver"] = $obj["s_appver"];
            $tmp["appdbtype"] = $obj["s_appdbtype"];
        }
        return $tmp;
    }

    
    /**
     * Subscription info for sibgle object
     * @param objectid sting
     * @return object subscription info
     */
    public function getSubscriptionInfo($objid) {
        $objid = mysqli_real_escape_string($this->conn, $objid);
        $result = $this->pDatabase->query("SELECT * FROM t_subscriptions WHERE s_objectid = '$objid'");
        return $result ? mysqli_fetch_assoc($result) : NULL;
    }
    
    /**
     * Update Subscription info for single object
     * @param objectid sting
     * @param expiredate sting
     * @param active integer (0 or 1)
     * @param comment sting
     * @return object subscription info
     */    
    public function updateSubscriptionInfo($objectid, $expiredate, $active, $comment) {
        $objectid = mysqli_real_escape_string($this->conn, $objectid);
        $expiredate = mysqli_real_escape_string($this->conn, $expiredate);
        $active = mysqli_real_escape_string($this->conn, $active);
        $comment = mysqli_real_escape_string($this->conn, $comment);
        
        $qry = "UPDATE t_subscriptions SET s_lastupdatedate='".date("Y-m-d H:i:s")."'";
        if ($expiredate !== '') { $qry .=", s_expiredate='$expiredate'"; }
        if ($active !== '') { $qry .=", s_active='$active'"; }
        if ($comment !== '') { $qry .=", s_comment='$comment'"; }
        $qry .= " WHERE s_objectid = '$objectid'";
        
        $result = $this->pDatabase->query($qry);
        return $result ? true : false;
    }
}
 

                            
//  $db = new DbHandler();
//  $db->updateSubscriptionInfo('07ff46bb','2016-8-8','','');

  //  $res = $db->getSubscriptionInfo('07ff46bb');
//  $res = $db->updateSubscriptionInfo('07ff46bb', '2016-7-7', '1');
//  var_dump($res); 

//  $res = $db->createObject('-1','varobjname1','varcustname1','vareik1','varaddress1','varhostname1');
//  echo json_encode($res);
 
?>