<?php
/** @package    DREPORTS::Controller */

/** import supporting libraries */
require_once("verysimple/Phreeze/Controller.php");
require_once("Model/Statistic.php");


/**
 * AppBaseController is a base class Controller class from which
 * the front controllers inherit.  it is not necessary to use this
 * class or any code, however you may use if for application-wide
 * functions such as authentication
 *
 * @package DREPORTS::Controller
 * @author ClassBuilder
 * @version 1.0
 */
class AppBaseController extends Controller
{

	static $DEFAULT_PAGE_SIZE = 40;
    

	/**
	 * Init is called by the base controller before the action method
	 * is called.  This provided an oportunity to hook into the system
	 * for all application actions.  This is a good place for authentication
	 * code.
	 */
	protected function Init()
	{
		// TODO: add app-wide bootsrap code
		
		// EXAMPLE: require authentication to access the app
		/*
		if ( !in_array($this->GetRouter()->GetUri(),array('login','loginform','logout')) )
		{
			require_once("App/ExampleUser.php");
			$this->RequirePermission(ExampleUser::$PERMISSION_ADMIN,'SecureExample.LoginForm');
		}
		//*/
	}

	/**
	 * Returns the number of records to return per page
	 * when pagination is used
	 */
	protected function GetDefaultPageSize()
	{
		return self::$DEFAULT_PAGE_SIZE;
	}

	/**
	 * Returns the name of the JSONP callback function (if allowed)
	 */
	protected function JSONPCallback()
	{
		// TODO: uncomment to allow JSONP
		// return RequestUtil::Get('callback','');

		return '';
	}

	/**
	 * Return the default SimpleObject params used when rendering objects as JSON
	 * @return array
	 */
	protected function SimpleObjectParams()
	{
		return array('camelCase'=>true);
	}

	/**
	 * Helper method to get values from stdClass without throwing errors
	 * @param stdClass $json
	 * @param string $prop
	 * @param string $default
	 */
	protected function SafeGetVal($json, $prop, $default='')
	{
		return (property_exists($json,$prop))
			? $json->$prop
			: $default;
	}

	/**
	 * Helper utility that calls RenderErrorJSON
	 * @param Exception
	 */
	protected function RenderExceptionJSON(Exception $exception)
	{
		$this->RenderErrorJSON($exception->getMessage(),null,$exception);
	}

	/**
	 * Output a Json error message to the browser
	 * @param string $message
	 * @param array key/value pairs where the key is the fieldname and the value is the error
	 */
	protected function RenderErrorJSON($message, $errors = null, $exception = null)
	{
		$err = new stdClass();
		$err->success = false;
		$err->message = $message;
		$err->errors = array();

		if ($errors != null)
		{
			foreach ($errors as $key=>$val)
			{
				$err->errors[lcfirst($key)] = $val;
			}
		}

		if ($exception)
		{
			$err->stackTrace = explode("\n#", substr($exception->getTraceAsString(),1) );
		}

		@header('HTTP/1.1 401 Unauthorized');
		$this->RenderJSON($err,RequestUtil::Get('callback'));
	}

    /* Save log in statistic for every operation
     * operid is loged username
     * operation type:
     * 101 - login success 
     * 
     * 105 - user add
     * 106 - user edit
     * 107 - user delete
     * 
     * 110 - role add
     * 111 - role edit
     * 112 - role delete
     *
     * 115 - RestIP add
     * 116 - RestIP edit
     * 117 - RestIP delete
     *
     * 120 - Settings add
     * 121 - settings edit
     * 122 - settings delete
     *
     * 125 - Devices add
     * 126 - Devices edit
     * 127 - Devices delete
     *
     * 130 - Reports add
     * 131 - Reports edit
     * 132 - Reports delete
     *
     * 136 - Subscriptions edit
     * 137 - Subscriptions deltete
     *
    */ 
    public function saveLog($sopertype, $sdescription)
    {
        try
        {
            
            $statistic = new Statistic($this->Phreezer);

            $statistic->Opertype = $sopertype;
            //$statistic->Operid = $soperid;
            $statistic->Operid = $this->GetCurrentUser()->Username;;
            //user_error('user:'.var_dump($this->GetCurrentUser()),E_USER_NOTICE);
            $statistic->Datetime = date('Y-m-d H:i:s');
            $statistic->Description = $sdescription;
            $statistic->Save();
 
        }
        catch (Exception $ex)
        {
            $this->RenderExceptionJSON($ex);
        }
    }    
    
    
}
?>