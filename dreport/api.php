<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Debug logging
error_log("Script started");
error_log("REQUEST_URI: " . $_SERVER['REQUEST_URI']);
error_log("QUERY_STRING: " . $_SERVER['QUERY_STRING']);
error_log("REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);
error_log("Remote IP: " . $_SERVER['REMOTE_ADDR']);
error_log("X-Forwarded-For: " . (isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : 'not set'));
error_log("HTTP_X_FORWARDED_FOR: " . (isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : 'not set'));

require_once 'protected/restDbHandler.php';
require 'protected/Slim/Slim.php';


\Slim\Slim::registerAutoloader();

$app = new \Slim\Slim(array(
    'debug' => true
));
$app->contentType('text/html; charset=utf-8');

/**
 * Function to authenticate rest calls - IP whitelist
 */
function authenticate(\Slim\Route $route) {
    error_log("Authenticate function called");
    
    $app = \Slim\Slim::getInstance();
    
    $db = new DbHandler();
    $whitelist = $db->getIPwhitelist();
    error_log("Whitelist: " . print_r($whitelist, true));
    
    $clientip = get_ip();
    error_log("Client IP: " . $clientip);
    
    $GLOBALS['clientip'] = $clientip;

    // Verifying ip
    if (!in_array($clientip, $whitelist)) {
        error_log("IP not in whitelist: " . $clientip);
        // IP is missing in list
        $response = array();
        $response["result"] = 1;
        $response["message"] = "IP is not allowed: $clientip";
        echoRespnse(400, $response);
        $db->logevent(OPER_ERROR,$clientip,'rest authentication failed: IP='.$clientip.' RESP:'.$response["result"].':'.$response["message"]);
        $app->stop();
    }
    error_log("Authentication successful for IP: " . $clientip);
}

/**
 * Function to get client IP, in IPv4 or IPv6 format
 */
function get_ip() {
    return $_SERVER['REMOTE_ADDR'];
}


/**
 * Verifying required params posted or not
 */
function verifyRequiredParams($required_fields) {
    $error = false;
    $error_fields = "";
    $request_params = array();
    $request_params = $_REQUEST;
    // Handling PUT request params
    if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
        $app = \Slim\Slim::getInstance();
        parse_str($app->request()->getBody(), $request_params);
    }
    foreach ($required_fields as $field) {
        if (!isset($request_params[$field]) || strlen(trim($request_params[$field])) <= 0) {
            $error = true;
            $error_fields .= $field . ', ';
        }
    }

    if ($error) {
        // Required field(s) are missing or empty
        // echo error json and stop the app
        $response = array();
        $app = \Slim\Slim::getInstance();
        $response["result"] = 400;
        $response["message"] = 'Required field(s) ' . substr($error_fields, 0, -2) . ' is(are) missing or empty';
        $db = new DbHandler();
        $db->logevent(OPER_REST,$GLOBALS['clientip'],$response["message"]);
        echoRespnse(400, $response);
        $app->stop();
    }
}


/**
 * Echoing json response to client
 * @param String $status_code Http response code
 * @param Int $response Json response
 */
function echoRespnse($status_code, $response) {
    $app = \Slim\Slim::getInstance();
    // Http response code
    $app->status($status_code);

    // setting response content type to json
    $app->contentType('application/json');

    echo json_encode($response);
}


/**
 * Request/update ObjectID,
 * method GET
 * url /getallusers
 * @param: objectid
 * @param: objectname
 * @param: customername,
 * @param: eik,
 * @param: address,
 * @param: hostname,
 * @param: appip
 * @param: apptype
 * @param: appver
 * @param: appdbtype
 * @param: comment
 * return: objectid, expiredate, active, state (0 - added, 1 - updated)
 */
$app->get('/objectinfo', 'authenticate', function() use ($app) {
            // check for required params
            //verifyRequiredParams(array('objectid', 'objectname', 'customername', 'eik','address','hostname', 'appip', 'apptype', 'appver', 'appdbtype'));
            verifyRequiredParams(array('objectid', 'objectname', 'customername', 'eik','hostname', 'appip', 'apptype', 'appver', 'appdbtype'));

            $response = array();
            // reading params
            $objectid = $app->request->get('objectid');
            $objectname = $app->request->get('objectname');
            $customername = $app->request->get('customername');
            $eik = $app->request->get('eik');
            $address = $app->request->get('address');
            $hostname = $app->request->get('hostname');
            $appip = $app->request->get('appip');
            $apptype = $app->request->get('apptype');
            $appver = $app->request->get('appver');
            $appdbtype = $app->request->get('appdbtype');
            $comment = $app->request->get('comment');

            $db = new DbHandler();

            if (!$db->isObjectExists($objectid) && strcmp($objectid, "-1") != 0) {
                $response["result"] = 3;
                $response["message"] = "Error object not found";
                echoRespnse(201, $response);
            } else {
                if (strcmp($objectid, "-1") == 0) { $objectid = $db->generateObjectId();}
                $res = $db->createObject($objectid,$objectname,$customername,$eik,$address,$hostname, $appip, $apptype, $appver, $appdbtype,$comment);

                if ($res == 0 or $res == 1) {
                    $response["result"] = 0;//$res;
                    $tmp = $db->getObjectData($objectid);
                    $response["message"] = "OK";
                    $response["objectid"] = $tmp["objectid"];
                    $response["objectname"] = $tmp["objectname"];
                    $response["expiredate"] = $tmp["expiredate"];
                    $response["active"] = $tmp["active"];
                    $response["createdate"] = $tmp["createdate"];
                    $response["lastupdatedate"] = $tmp["lastupdatedate"];
                    $response["appip"] = $tmp["appip"];
                    $response["apptype"] = $tmp["apptype"];
                    $response["appver"] = $tmp["appver"];
                    $response["appdbtype"] = $tmp["appdbtype"];
                    echoRespnse(200, $response);
                } else {
                    $response["result"] = $res;
                    $response["message"] = "Error object create/update";
                    echoRespnse(201, $response);
                }
            }
            $db->logevent(OPER_REST,$objectid,'IP='.$GLOBALS['clientip'].' /objectinfo: objectid='.$objectid.' objectname='.$objectname.'customername='.$customername.' RESP:'.$response["result"].':'.$response["message"]);


});


/**
 * Subscription info for single object
 * method GET
 * url /subscriptioninfo
 */
$app->get('/subscriptioninfo', 'authenticate', function() use ($app) {
            // check for required params
            verifyRequiredParams(array('objectid'));

            $response = array();

            // reading post params
            $objectid = $app->request->get('objectid');

            $db = new DbHandler();
            $res = $db->getSubscriptionInfo($objectid);

             if ($res) {
                // object found successfully
                $response["result"] = 0;
                $response["message"] = "OK";
                $response["objectid"] = $res["s_objectid"];
                $response["objectname"] = $res["s_objectname"];
                $response["customername"] = $res["s_customername"];
                $response["eik"] = $res["s_eik"];
                $response["address"] = $res["s_address"];
                $response["hostname"] = $res["s_hostname"];
                $response["expiredate"] = $res["s_expiredate"];
                $response["active"] = $res["s_active"];
                $response["createdate"] = $res["s_createdate"];
                $response["lastupdatedate"] = $res["s_lastupdatedate"];
                $response["appip"] = $res["s_appip"];
                $response["apptype"] = $res["s_apptype"];
                $response["appver"] = $res["s_appver"];
                $response["appdbtype"] = $res["s_appdbtype"];
                $response["comment"] = $res["s_comment"];

            } else {
                // obkect not found
                $response["result"] = 1;
                $response["message"] = "Object not found.";
            }
            echoRespnse(200, $response);
            $db->logevent(OPER_REST,$objectid,'IP='.$GLOBALS['clientip'].' /subscriptioninfo: objectid='.$objectid.' RESP:'.$response["result"].':'.$response["message"]);

});

$app->get('/subscribeobject', 'authenticate', function() use ($app) {
            // check for required params
            //verifyRequiredParams(array('objectid','expiredate','active','comment'));
            verifyRequiredParams(array('objectid'));

            $response = array();

            $objectid = $app->request->get('objectid');
            $expiredate = $app->request->get('expiredate');
            $active = $app->request->get('active');
            $comment = $app->request->get('comment');


           $db = new DbHandler();

           if (!isset($expiredate) && !isset($active)) {
              // no such object
              $response["result"] = 1;
              $response["message"] = "active or expiredate parameters should be set.";
              echoRespnse(400, $response);
              exit;
           }


       if ((0 <= $active) && ($active <= 1)) {

           //$db = new DbHandler();

           if (!$db->isObjectExists($objectid)) {
              // no such object
              $response["result"] = 1;
              $response["message"] = "Object not found.";
              echoRespnse(400, $response);
              exit;
           }

           $res = $db->updateSubscriptionInfo($objectid,$expiredate,$active,$comment);
           if ($res) {
                $response["result"] = 0;
                $response["message"] = "Subscription updated for Objectid: $objectid";
                echoRespnse(200, $response);
           } else {
                $response["result"] = 1;
                $response["message"] = "Error update subscription info.";
                echoRespnse(400, $response);
           }
       } else {
           // active not 1 or 0
              $response["result"] = 1;
              $response["message"] = "active paramater can be 0 or 1.";
              echoRespnse(400, $response);
            }

          //echoRespnse(200, $response);
          $db->logevent(OPER_REST,$objectid,'IP='.$GLOBALS['clientip'].' /subscribeobject: objectid='.$objectid.' expiredate='.$expiredate.' active='.$active.' comment='.$comment.' RESP:'.$response["result"].':'.$response["message"]);
});


/**
 * Test function - return some text when executed
 * url - /
 * method - GET
 */
$app->get("/", 'authenticate', function () use ($app) {
    //echo "<h2 align=center>Detelina reports restful API 1.0.0</h2>";
    //define paths
    include 'protected/restabout.php';
    $db = new DbHandler();
    $db->logevent(OPER_REST,$GLOBALS['clientip'],'/ IP='.$GLOBALS['clientip']);

});



$app->run();

//http://localhost/dreport/api.php/objectinfo?objectid=1111222&objectname=111&customername=111&eik=111&address=111&hostname=111

?>
