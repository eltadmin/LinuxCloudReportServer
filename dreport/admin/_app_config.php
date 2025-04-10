<?php
/**
 * @package DREPORTS
 *
 * APPLICATION-WIDE CONFIGURATION SETTINGS
 *
 * This file contains application-wide configuration settings.  The settings
 * here will be the same regardless of the machine on which the app is running.
 *
 * This configuration should be added to version control.
 *
 * No settings should be added to this file that would need to be changed
 * on a per-machine basic (ie local, staging or production).  Any
 * machine-specific settings should be added to _machine_config.php
 */

/**
 * APPLICATION ROOT DIRECTORY
 * If the application doesn't detect this correctly then it can be set explicitly
 */
if (!GlobalConfig::$APP_ROOT) GlobalConfig::$APP_ROOT = realpath("./");

/**
 * check is needed to ensure asp_tags is not enabled
 */
if (ini_get('asp_tags')) 
	die('<h3>Server Configuration Problem: asp_tags is enabled, but is not compatible with Savant.</h3>'
	. '<p>You can disable asp_tags in .htaccess, php.ini or generate your app with another template engine such as Smarty.</p>');

/**
 * INCLUDE PATH
 * Adjust the include path as necessary so PHP can locate required libraries
 */
set_include_path(
		GlobalConfig::$APP_ROOT . '/libs/' . PATH_SEPARATOR .
		'phar://' . GlobalConfig::$APP_ROOT . '/libs/phreeze-3.3.8.phar' . PATH_SEPARATOR .
		GlobalConfig::$APP_ROOT . '/../phreeze/libs' . PATH_SEPARATOR .
		GlobalConfig::$APP_ROOT . '/vendor/phreeze/phreeze/libs/' . PATH_SEPARATOR .
		get_include_path()
);

/**
 * COMPOSER AUTOLOADER
 * Uncomment if Composer is being used to manage dependencies
 */
// $loader = require 'vendor/autoload.php';
// $loader->setUseIncludePath(true);

/**
 * SESSION CLASSES
 * Any classes that will be stored in the session can be added here
 * and will be pre-loaded on every page
 */
//require_once "App/ExampleUser.php";
//add auth
require_once "Model/User.php";

/**
 * RENDER ENGINE
 * You can use any template system that implements
 * IRenderEngine for the view layer.  Phreeze provides pre-built
 * implementations for Smarty, Savant and plain PHP.
 */
require_once 'verysimple/Phreeze/SavantRenderEngine.php';
GlobalConfig::$TEMPLATE_ENGINE = 'SavantRenderEngine';
GlobalConfig::$TEMPLATE_PATH = GlobalConfig::$APP_ROOT . '/templates/';

/**
 * ROUTE MAP
 * The route map connects URLs to Controller+Method and additionally maps the
 * wildcards to a named parameter so that they are accessible inside the
 * Controller without having to parse the URL for parameters such as IDs
 */
GlobalConfig::$ROUTE_MAP = array(

	// default controller when no route specified
	'GET:' => array('route' => 'Default.Home'),

//active objects
    'GET:activeobjects' => array('route' => 'activeobjects.ListView'),


    		
	// example authentication routes
	'GET:loginform' => array('route' => 'SecureExample.LoginForm'),
	'POST:login' => array('route' => 'SecureExample.Login'),
	'GET:secureuser' => array('route' => 'SecureExample.UserPage'),
	'GET:secureadmin' => array('route' => 'SecureExample.AdminPage'),
	'GET:logout' => array('route' => 'SecureExample.Logout'),
		
	// Role
	'GET:roles' => array('route' => 'Role.ListView'),
	'GET:role/(:num)' => array('route' => 'Role.SingleView', 'params' => array('id' => 1)),
	'GET:api/roles' => array('route' => 'Role.Query'),
	'POST:api/role' => array('route' => 'Role.Create'),
	'GET:api/role/(:num)' => array('route' => 'Role.Read', 'params' => array('id' => 2)),
	'PUT:api/role/(:num)' => array('route' => 'Role.Update', 'params' => array('id' => 2)),
	'DELETE:api/role/(:num)' => array('route' => 'Role.Delete', 'params' => array('id' => 2)),
/*		
	// Device
	'GET:devices' => array('route' => 'Device.ListView'),
	'GET:device/(:any)' => array('route' => 'Device.SingleView', 'params' => array('objectid' => 1)),
	'GET:api/devices' => array('route' => 'Device.Query'),
	'POST:api/device' => array('route' => 'Device.Create'),
	'GET:api/device/(:any)' => array('route' => 'Device.Read', 'params' => array('objectid' => 2)),
	'PUT:api/device/(:any)' => array('route' => 'Device.Update', 'params' => array('objectid' => 2)),
	'DELETE:api/device/(:any)' => array('route' => 'Device.Delete', 'params' => array('objectid' => 2)),
*/
    // Device
    'GET:devices' => array('route' => 'Device.ListView'),
    'GET:device/(:num)' => array('route' => 'Device.SingleView', 'params' => array('id' => 1)),
    'GET:api/devices' => array('route' => 'Device.Query'),
    'POST:api/device' => array('route' => 'Device.Create'),
    'GET:api/device/(:num)' => array('route' => 'Device.Read', 'params' => array('id' => 2)),
    'PUT:api/device/(:num)' => array('route' => 'Device.Update', 'params' => array('id' => 2)),
    'DELETE:api/device/(:num)' => array('route' => 'Device.Delete', 'params' => array('id' => 2)),


		
	// Report
	'GET:reports' => array('route' => 'Report.ListView'),
	'GET:report/(:any)' => array('route' => 'Report.SingleView', 'params' => array('id' => 1)),
	'GET:api/reports' => array('route' => 'Report.Query'),
	'POST:api/report' => array('route' => 'Report.Create'),
	'GET:api/report/(:any)' => array('route' => 'Report.Read', 'params' => array('id' => 2)),
	'PUT:api/report/(:any)' => array('route' => 'Report.Update', 'params' => array('id' => 2)),
	'DELETE:api/report/(:any)' => array('route' => 'Report.Delete', 'params' => array('id' => 2)),
		
	// RestIP
	'GET:restips' => array('route' => 'RestIP.ListView'),
	'GET:restip/(:num)' => array('route' => 'RestIP.SingleView', 'params' => array('id' => 1)),
	'GET:api/restips' => array('route' => 'RestIP.Query'),
	'POST:api/restip' => array('route' => 'RestIP.Create'),
	'GET:api/restip/(:num)' => array('route' => 'RestIP.Read', 'params' => array('id' => 2)),
	'PUT:api/restip/(:num)' => array('route' => 'RestIP.Update', 'params' => array('id' => 2)),
	'DELETE:api/restip/(:num)' => array('route' => 'RestIP.Delete', 'params' => array('id' => 2)),
		
	// Setting
	'GET:settings' => array('route' => 'Setting.ListView'),
	'GET:setting/(:any)' => array('route' => 'Setting.SingleView', 'params' => array('name' => 1)),
	'GET:api/settings' => array('route' => 'Setting.Query'),
	'POST:api/setting' => array('route' => 'Setting.Create'),
	'GET:api/setting/(:any)' => array('route' => 'Setting.Read', 'params' => array('name' => 2)),
	'PUT:api/setting/(:any)' => array('route' => 'Setting.Update', 'params' => array('name' => 2)),
	'DELETE:api/setting/(:any)' => array('route' => 'Setting.Delete', 'params' => array('name' => 2)),
		
	// Statistic
	'GET:statisticses' => array('route' => 'Statistic.ListView'),
	'GET:statistic/(:num)' => array('route' => 'Statistic.SingleView', 'params' => array('id' => 1)),
	'GET:api/statisticses' => array('route' => 'Statistic.Query'),
//	'POST:api/statistic' => array('route' => 'Statistic.Create'),
//	'GET:api/statistic/(:num)' => array('route' => 'Statistic.Read', 'params' => array('id' => 2)),
//	'PUT:api/statistic/(:num)' => array('route' => 'Statistic.Update', 'params' => array('id' => 2)),
//	'DELETE:api/statistic/(:num)' => array('route' => 'Statistic.Delete', 'params' => array('id' => 2)),
		
	// Subscription
	'GET:subscriptions' => array('route' => 'Subscription.ListView'),
	'GET:subscription/(:any)' => array('route' => 'Subscription.SingleView', 'params' => array('objectid' => 1)),
	'GET:api/subscriptions' => array('route' => 'Subscription.Query'),
	'POST:api/subscription' => array('route' => 'Subscription.Create'),
	'GET:api/subscription/(:any)' => array('route' => 'Subscription.Read', 'params' => array('objectid' => 2)),
	'PUT:api/subscription/(:any)' => array('route' => 'Subscription.Update', 'params' => array('objectid' => 2)),
	'DELETE:api/subscription/(:any)' => array('route' => 'Subscription.Delete', 'params' => array('objectid' => 2)),
/*		
	// User
	'GET:users' => array('route' => 'User.ListView'),
	'GET:user/(:any)' => array('route' => 'User.SingleView', 'params' => array('id' => 1)),
	'GET:api/users' => array('route' => 'User.Query'),
	'POST:api/user' => array('route' => 'User.Create'),
	'GET:api/user/(:any)' => array('route' => 'User.Read', 'params' => array('id' => 2)),
	'PUT:api/user/(:any)' => array('route' => 'User.Update', 'params' => array('id' => 2)),
	'DELETE:api/user/(:any)' => array('route' => 'User.Delete', 'params' => array('id' => 2)),
*/		
	// User
	'GET:users' => array('route' => 'User.ListView'),
	'GET:user/(:num)' => array('route' => 'User.SingleView', 'params' => array('id' => 1)),
	'GET:api/users' => array('route' => 'User.Query'),
	'POST:api/user' => array('route' => 'User.Create'),
	'GET:api/user/(:num)' => array('route' => 'User.Read', 'params' => array('id' => 2)),
	'PUT:api/user/(:num)' => array('route' => 'User.Update', 'params' => array('id' => 2)),
	'DELETE:api/user/(:num)' => array('route' => 'User.Delete', 'params' => array('id' => 2)),

	// catch any broken API urls
	'GET:api/(:any)' => array('route' => 'Default.ErrorApi404'),
	'PUT:api/(:any)' => array('route' => 'Default.ErrorApi404'),
	'POST:api/(:any)' => array('route' => 'Default.ErrorApi404'),
	'DELETE:api/(:any)' => array('route' => 'Default.ErrorApi404')
);

/**
 * FETCHING STRATEGY
 * You may uncomment any of the lines below to specify always eager fetching.
 * Alternatively, you can copy/paste to a specific page for one-time eager fetching
 * If you paste into a controller method, replace $G_PHREEZER with $this->Phreezer
 */
// $GlobalConfig->GetInstance()->GetPhreezer()->SetLoadType("User","u_role",KM_LOAD_EAGER); // KM_LOAD_INNER | KM_LOAD_EAGER | KM_LOAD_LAZY
?>