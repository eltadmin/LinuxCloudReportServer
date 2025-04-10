<?php

define('OPER_STARTAPP', 0);
define('OPER_COMMAND',  1);
define('OPER_ADDOBJECT', 2);
define('OPER_DELOBJECT', 3);
define('OPER_REST', 4);
define('OPER_ERROR', 5);


//alert types
define('ALERT_ERROR', 'error');
define('ALERT_WARNING', 'warning');
define('ALERT_SUCCESS', 'success');
define('ALERT_NOTICE', 'notice');

class Database
{
   // Store the single instance of Database
   //private static $m_pInstance;
    private static $instance = null;
    private $db_host;
    private $db_user;
    private $db_pass;
    private $db_name;
    private $db_link;
    private $last_query;
    private $magic_quotes_active;
    private $real_escape_string_exists;


   //local settings
   //private $db_host='127.0.0.1';
    //private $db_host='localhost:3307';
//    private $db_host ='127.0.0.1:3306';
//   private $db_user = 'root';
//   private $db_pass = '';
//   private $db_name = 'dreports';

  
       // cloud1 settings
       //private $db_host='127.0.0.1';
       //private $db_user = 'dreports';
       //private $db_pass = 'ftUk58_HoRs3sAzz8jk';
       //private $db_name = 'dreports';
         // Private constructor to limit object instantiation to within the class

   /* private function __construct()
    {
        mysqli_connect($this->db_host, $this->db_user, $this->db_pass, $this->db_name);
        //mysqli_select_db($this->db_name);
    }
*/
    private function __construct()
    {
        $this->db_host = getenv('DB_HOST') ?: 'localhost';
        $this->db_user = getenv('DB_USER') ?: 'dreports';
        $this->db_pass = getenv('DB_PASSWORD') ?: 'dreports';
        $this->db_name = getenv('DB_NAME') ?: 'dreports';
        
        $this->open_connection();
        $this->magic_quotes_active = false;
        $this->real_escape_string_exists = function_exists("mysqli_real_escape_string");
    }

    private function open_connection() {
        $this->db_link = new mysqli($this->db_host, $this->db_user, $this->db_pass, $this->db_name);
        
        if ($this->db_link->connect_error) {
            $this->logevent("Database connection failed: " . $this->db_link->connect_error, 1);
            die("Database connection failed: " . $this->db_link->connect_error);
        }

        $this->db_link->set_charset('utf8');
    }

       // Getter method for creating/returning the single instance of this class
       public static function getInstance()
       {
           if (!self::$instance)
           {
               self::$instance = new Database();
           }
           return self::$instance;
       }

       //public function query($query)
       //{
       //   return mysql_query($query);
       //}
    public function query($query)
    {
        return mysqli_query($this->db_link, $query);
    }
    public function getConnection()
    {
        return $this->db_link;
    }


    public function logevent($opertype,$operid, $operdecription)
       {
          /*
          * opertype - predefined
           define('OPER_STARTAPP', 0);
           define('OPER_COMMAND',  1);
           define('OPER_ADDOBJECT', 2);
           define('OPER_DELOBJECT', 3);
           define('OPER_REST', 4);
           define('OPER_ERROR', 5);
          * operid - objectid, deviceid or other unique ID releted to operation
          * description - additional description of operation, if applicable (rest call, UI operation etc.)
          */

      /*
       * log_level
       * 0 - disabled - nothing is logged
       * 1 - log errors and rest - 4,5
       * 2 - log errors, rest,del/add objects - 2,3,4,5
       * 3 - log all operations including query and open - 0 to 5
       *
       */
       if(isset($_SESSION["log_level"])){
        $loglevel = $_SESSION["log_level"];
       }
       else {
             $qry = $this->query("select * from t_settings");
             while ($row = mysqli_fetch_assoc($qry)) {
               switch ($row['s_name']) {
                   case "log_level":
                       $loglevel = $row['s_value'];
                       break;
               } //end swithch
             } // while
       }

       $savelog = false;
       switch ($loglevel) {
           case "1": // log errors and rest
               if( in_array( $opertype , range(OPER_REST,OPER_ERROR))) {
                $savelog = true;
               }
               break;
           case "2":
               if( in_array( $opertype , range(OPER_ADDOBJECT,OPER_ERROR))) {
                $savelog = true;
               }
               break;
           case "3":
               if( in_array( $opertype , range(OPER_STARTAPP,OPER_ERROR))) {
                $savelog = true;
               }
               break;
           default:
               $savelog = false;
       }


      if ($savelog) {
          $result = $this->query("INSERT INTO t_statistics(s_opertype, s_operid, s_description) VALUES ('".sql_safe($opertype)."','".sql_safe($operid)."','".sql_safe($operdecription)."')");
          if($result) {
           return true;
          } else {
           return false;
          }
      }
   }


   /**
    * Show alert box function
    * @param constant $atype - ALERT_ERROR/WARNING/NOTCIE/SUCCESS
    * @param String $aspan span text
    * @param String $amessage display message
    * @param boolean $aclosebtn show close button
    * @param boolean $autoclose autoclose alert box 5 sec
    */
   public function show_alert($atype, $aspan, $amessage, $aclosebtn, $autoclose)
   {
	 $rnd =  mt_rand(10,99);   
     echo '<meta http-equiv="X-UA-Compatible" content="IE=EmulateIE11" />';
     echo '<div class="alert-box '.$atype.'" id="alert-box'.$rnd.'">';
     echo '<span>'.$aspan.'</span>'.$amessage;
     if ($aclosebtn) {
       echo '<button class="btn btn-close" type="button" align="right" >&times;</button>';
     }
     echo '</div>';
     if ($autoclose) {
       echo '<script>';
       echo 'setTimeout(function(){var element = document.getElementById("alert-box'.$rnd.'");element.parentNode.removeChild(element);},5000);';
       echo '</script>';
     }

   /* tests
    $pDatabase->show_alert(ALERT_ERROR,'error','description message,',false,false);
    $pDatabase->show_alert(ALERT_NOTICE,'notice','description message,',true,false);
    $pDatabase->show_alert(ALERT_WARNING,'error','description message,',true,true);
    $pDatabase->show_alert(ALERT_SUCCESS,'error','description message,',false,true);
   */
   } //end show alert


   /**
    * Get error from TCPserver
    * @param integer $ResultCode from REST api call
    */
   public function getTCPErrorMessage($ResultCode, $lang) {
     $errorcode = array(100,102,103,200,201,202,203,204,205,1000,1001,1002,1003,1004,1005,1006,1007,1008,1009,1010,1011,1020);
     if (in_array($ResultCode, $errorcode)) {
        return $lang[strval($ResultCode)];
     } else {
        return $lang["C_HttpErr_NotDefined"];
     }
   }
}

// =============================================================================================================================================
/*
function sql_safe($param, $strip_tags = false, $strip_slashes = true)
{
	if($strip_tags)
	{
		if ($strip_slashes)
		{
			return mysqli_real_escape_string(trim(stripslashes(strip_tags($param))));
		}
		else
		{
			return mysqli_real_escape_string(trim(strip_tags($param)));
		}
	}
	elseif ($strip_slashes)
	{
			return mysqli_real_escape_string(trim(stripslashes($param)));
	}

	return mysqli_real_escape_string(trim($param));
}
*/


function sql_safe($param, $strip_tags = false, $strip_slashes = true)
{
    $pDatabase = Database::getInstance();
    $connection = $pDatabase->getConnection();

    if ($strip_tags) {
        if ($strip_slashes) {
            return mysqli_real_escape_string($connection, trim(stripslashes(strip_tags($param))));
        } else {
            return mysqli_real_escape_string($connection, trim(strip_tags($param)));
        }
    } elseif ($strip_slashes) {
        return mysqli_real_escape_string($connection, trim(stripslashes($param)));
    }

    return mysqli_real_escape_string($connection, trim($param));
}



function isvaliddate($day, $month, $year) {
   $day = intval($day);
   $month = intval($month);
   $year = intval($year);

   if (!$day ||
   			$day < 1 ||
   			$day > 31 ||
   			!$month ||
   			$month < 1 ||
   			$month > 12 ||
   			!$year ||
   			$year < 1900) return false;

   if ($year < 1970) $year = 1972;

   $time = @mktime(0,0,0,$month,$day,$year);

   if ($day != @date("j",$time)) return false;
   if ($month != @date("n",$time)) return false;
   if (($year != @date("Y",$time)) AND ($year != @date("y",$time))) return false;

   return true;
}

function format_sql_date($sql_date)
{
	$len = strlen($sql_date);

	if ($len < 8)
	{
		return '-';
	}

	if ($len > 10)
	{
		$sql_date = substr($sql_date, 0, 10);
	}

	$arr = explode('-', $sql_date);

	if (count($arr) < 3) return '-';

	if (!isvaliddate($arr[2], $arr[1], $arr[0])) return '-';

	return $arr[2].'.'.$arr[1].'.'.$arr[0];
}

function format_sql_date_long($sql_date)
{
	$len = strlen($sql_date);

	if ($len < 8)
	{
		return '-';
	}

	$d = '';
	$h = '';

	$sql_date_d = substr($sql_date, 0, 10);

	$arr = explode('-', $sql_date_d);

	if (count($arr) < 3) return '-';

	$d = $arr[2].'.'.$arr[1].'.'.$arr[0];

	if ($len > 10)
	{
		$h = ' '.substr($sql_date, 10, 6);
	}

	if (!isvaliddate($arr[2], $arr[1], $arr[0])) return '-';

	return $d.$h;
}
?>