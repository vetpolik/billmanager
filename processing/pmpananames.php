#!/usr/bin/php
<?php

set_include_path(get_include_path() . PATH_SEPARATOR . '/usr/local/mgr5/include/php'); 
require_once 'pananames_helper.php';

$longopts = array
(
    "command:",
    "subcommand:",
    "id:",
    "item:",
    "lang:",
    "module:",
    "itemtype:",
    "intname:",
    "param:",
    "value:",
    "runningoperation:",
    "level:",
    "addon:",
    "tld:",
    "searchstring:",
);

$options = getopt('', $longopts);

setToLog(json_encode($options));

try {
    $command = $options['command'];
    
    $runningoperation = array_key_exists("runningoperation", $options) ? (int)$options['runningoperation'] : 0;
    $item = array_key_exists("item", $options) ? (int)$options['item'] : 0;
    if ($command == "features") {
        echo '<?xml version="1.0" encoding="UTF-8"?>
        <doc>
          <itemtypes>
            <itemtype name="domain"/>
          </itemtypes>
          <params>
            <param name="url"/>
            <param name="signature" crypted="yes"/>
          </params>
          <features>
             <feature name="tune_connection"/>
             <feature name="check_connection"/>
             <feature name="prolong"/>
             <feature name="cancel_prolong"/>
             <feature name="check_param"/>
             <feature name="open"/>
             <feature name="close"/>
             <feature name="sync_server"/>
             <feature name="setparam"/>
          </features>
        </doc>';
    } elseif ($command == "tune_connection") {
        // Add whois_lang select for 'ru' or 'en'
        $connection_form = simplexml_load_string(file_get_contents('php://stdin'));
        $lang = $connection_form->addChild("slist");
        $lang->addAttribute("name", "whois_lang");
        $lang->addChild("msg", "ru");
        $lang->addChild("msg", "en");
        echo $connection_form->asXML();
    } elseif ($command == "check_connection") {
        $connection_param = simplexml_load_string(file_get_contents('php://stdin'));
	try {
	    $signature = $connection_param->processingmodule->signature;
            $url =  $connection_param->processingmodule->url . 'account/balance';
    	    $param = [];
    	    $requesttype = 'GET';
    	    $header = ['SIGNATURE: ' . $signature, 'accept: application/json', 'content-type: application/json'];
    	    $result = json_decode(HttpQuery($url, $param, $requesttype, '', '', $header));

            setToLog(json_encode($result));
            
    	    if (isset($result->errors)) {
        	$return = $result->errors[0]->message . ' ' . $result->errors[0]->description;
        	$return = '<?xml version="1.0" encoding="UTF-8"?>
        	<doc>
        	  <error type="xml" object="xpath" report="yes" lang="ru">
        	    <param name="object" type="msg" msg="Ошибка авторизации">signature</param>
        	    <param name="value">bad auth</param>
        	      <stack>
        	        <action level="30" user="root">autherror</action>
        	      </stack>
        	      <group>Возникла ошибка при попытке авторизации</group>
        	      <msg>Возникла ошибка при авторизации через signature (Api key authorization). Ошибка "bad auth"</msg>
        	  </error>
        	</doc>';
    	    } else {
        	$data = $result->data;
        	$return = '<?xml version="1.0" encoding="UTF-8"?>
        	<doc>
        	  <ok/>
        	</doc>';
    	    }
        } catch (Exception $e) {
            throw new Error("invalid_login_or_passwd");
        }
        echo $return;
    } elseif ($command == "open" || $command == "transfer") {
        $db = GetConnection();
        $iid = $options['item'];
        $item_param = ItemParam($db, $iid);
        $profile_params = ItemProfiles($db, $iid, $item_param["item_module"]);

        $params = [
            'domain' => $item_param['domain'],
            'period' => round($item_param['item_period'] / 12, 1),
            'whois_privacy' => true,
            'registrant_contact' => [
                'org' => '',
                'name' => $profile_params['owner']['firstname'] . ' ' . $profile_params['owner']['middlename'] . ' ' . $profile_params['owner']['lastname'],
                'email' => $profile_params['owner']['email'],
                'address' => $profile_params['owner']['location_address'],
                'city' => $profile_params['owner']['location_city'],
                'state' => $profile_params['owner']['location_state'],
                'zip' => $profile_params['owner']['location_postcode'],
                'country' => getCountryISO($db, $profile_params['owner']['location_country']),
                'phone' => str_replace([' (', ') ', '-'], ['.', '', ''], $profile_params['owner']['phone'])
            ],    
            'admin_contact' => [
                'org' => '',
                'name' => $profile_params['admin']['firstname'] . ' ' . $profile_params['admin']['middlename'] . ' ' . $profile_params['admin']['lastname'],
                'email' => $profile_params['admin']['email'],
                'address' => $profile_params['admin']['location_address'],
                'city' => $profile_params['admin']['location_city'],
                'state' => $profile_params['admin']['location_state'],
                'zip' => $profile_params['admin']['location_postcode'],
                'country' => getCountryISO($db, $profile_params['admin']['location_country']),
                'phone' => str_replace([' (', ') ', '-'], ['.', '', ''], $profile_params['admin']['phone'])
            ],
            'tech_contact' => [
                'org' => '',
                'name' => $profile_params['tech']['firstname'] . ' ' . $profile_params['tech']['middlename'] . ' ' . $profile_params['tech']['lastname'],
                'email' => $profile_params['tech']['email'],
                'address' => $profile_params['tech']['location_address'],
                'city' => $profile_params['tech']['location_city'],
                'state' => $profile_params['tech']['location_state'],
                'zip' => $profile_params['tech']['location_postcode'],
                'country' => getCountryISO($db, $profile_params['tech']['location_country']),
                'phone' => str_replace([' (', ') ', '-'], ['.', '', ''], $profile_params['tech']['phone'])
            ],
            'billing_contact' => [
                'org' => '',
                'name' => $profile_params['bill']['firstname'] . ' ' . $profile_params['bill']['middlename'] . ' ' . $profile_params['bill']['lastname'],
                'email' => $profile_params['bill']['email'],
                'address' => $profile_params['bill']['location_address'],
                'city' => $profile_params['bill']['location_city'],
                'state' => $profile_params['bill']['location_state'],
                'zip' => $profile_params['bill']['location_postcode'],
                'country' => getCountryISO($db, $profile_params['bill']['location_country']),
                'phone' => str_replace([' (', ') ', '-'], ['.', '', ''], $profile_params['bill']['phone'])
            ],
            'premium_price' => 0,
            'claims_accepted' => true
        ];
    
        $url = getApiUrl($db, $item_param["item_module"]) . 'domains';
    	$requesttype = 'POST';
    	$header = ['SIGNATURE: ' .  getSignature($db, $item_param["item_module"]), 'accept: application/json', 'content-type: application/json'];
    	
        setToLog('URL for open ' . $url);
	setToLog('SIGNATURE for open ' . getSignature($db, $item_param["item_module"]));
        setToLog('POST ' . json_encode($params));
        
        $result = json_decode(HttpQuery($url, json_encode($params), $requesttype, '', '', $header));

        setToLog(json_encode($result));
        
    	if (!isset($result->errors)) {
            LocalQuery("domain.open", array("elid" => $item, "sok" => "ok"));
        } else {
            throw new Error("query", 'Error registration domain on Pananames', $result->errors[0]->description);
        }
    } elseif ($command == "close") {
        $db = GetConnection();
        $iid = $options['item'];
        $item_param = ItemParam($db, $iid);
    
        $url = getApiUrl($db, $item_param["item_module"]) . 'domains/' . $item_param['domain'];
    	$param = [];
    	$requesttype = 'DELETE';
    	$header = ['SIGNATURE: ' . getSignature($db, $item_param["item_module"]), 'accept: application/json', 'content-type: application/json'];
    	
        setToLog('URL for close ' . $url);
	setToLog('SIGNATURE for close ' . getSignature($db, $item_param["item_module"]));
        
        $result = json_decode(HttpQuery($url, $param, $requesttype, '', '', $header));

        setToLog(json_encode($result));
        
    	if (!isset($result->errors)) {
    	    LocalQuery("service.postclose", array("elid" => $item, "sok" => "ok", ));
	} else {
            throw new Error("query", 'Error delete domain on Pananames', $result->errors[0]->description);
        }
    } elseif ($command == "setparam") {
        // No example at now
        // delete running operation
        LocalQuery("service.postsetparam", array("elid" => $item, "sok" => "ok", ));
    } elseif ($command == "prolong") {
        $db = GetConnection();
        $iid = $options['item'];
        $item_param = ItemParam($db, $iid);
        $ddb = GetDomainConnection($item_param["item_module"]);
        $expiredate = $ddb->query("SELECT GREATEST(expiredate, NOW()) FROM domain WHERE name = '" . $ddb->real_escape_string($item_param["domain"]) . "' AND status != 'deleted'")->fetch_row()[0];
        $ddb->query("UPDATE domain SET status = 'active', expiredate = DATE_ADD('" . $expiredate . "', INTERVAL " . $item_param["item_period"] . " MONTH) WHERE name = '" . $ddb->real_escape_string($item_param["domain"]) . "' AND status != 'deleted'");
        // delete running operation
        LocalQuery("service.postprolong", array("elid" => $item, "sok" => "ok", ));
    } elseif ($command == "cancel_prolong") {
        // No example at now
        // Cancel auto prolong for domain via registrar API if need for provided tld
    } elseif ($command == "sync_item") {
        // Get domain info from registrar and update in BILLmanager.
        $db = GetConnection();
        $iid = $options['item'];
        $item_param = ItemParam($db, $iid);
        
        $url =  getApiUrl($db, $item_param["item_module"]) . 'domains/' . $item_param['domain'];
    	$param = [];
    	$requesttype = 'GET';
    	$header = ['SIGNATURE: ' . getSignature($db, $item_param["item_module"]), 'accept: application/json', 'content-type: application/json'];
    	$result = json_decode(HttpQuery($url, $param, $requesttype, '', '', $header));

	setToLog('URL for sync_item ' . $url);
	setToLog('SIGNATURE for sync_item ' . getSignature($db, $item_param["item_module"]));
	setToLog('Domain info for sync_item ' . json_encode($result));
	
    	if (!isset($result->errors)) {
	    if ($result->data->status == 'ok') {
	        setToLog('$result->data->status = ' . $result->data->status);
                LocalQuery("service.postresume", array("elid" => $item, "sok" => "ok", ));
                LocalQuery("service.setstatus", array("elid" => $item, "service_status" => "2", ));
            } else {
                LocalQuery("service.postsuspend", array("elid" => $item, "sok" => "ok", ));
                LocalQuery("service.setstatus", array("elid" => $item, "service_status" => "8", ));
            }
            LocalQuery("service.setexpiredate", array("elid" => $item, "expiredate" => $param["expiredate"], ));
	} else {
	    setToLog('Error sync_item domain on Pananames $result->errors[0]->description = ' . $result->errors[0]->description);
        }
    }
} catch (Exception $e) {
    if ($runningoperation > 0) {
        // save error message for operation in BILLmanager
        LocalQuery("runningoperation.edit", array("sok" => "ok", "elid" => $runningoperation, "errorxml" => $e,));
        if ($item > 0) {
            // set manual rerung
            LocalQuery("runningoperation.setmanual", array("elid" => $runningoperation,));
            // create task
            $task_type = LocalQuery("task.gettype", array("operation" => $command,))->task_type;
            if ($task_type != "") {
                LocalQuery("task.edit", array("sok" => "ok", "item" => $item, "runningoperation" => $runningoperation, "type" => $task_type, ));
            }
        }
    }
    echo $e;
}
