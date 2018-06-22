<?php

date_default_timezone_set("UTC");

class DB extends mysqli {
    public function __construct($host, $user, $pass, $db) {
        parent::init();
        if (!parent::options(MYSQLI_INIT_COMMAND, "SET AUTOCOMMIT = 1"))
            throw new Error("MYSQLI_INIT_COMMAND Fail");
        if (!parent::options(MYSQLI_OPT_CONNECT_TIMEOUT, 5))
            throw new Error("MYSQLI_OPT_CONNECT_TIMEOUT Fail");
        if (!parent::real_connect($host, 'coremgr', 'ZPTD2Wv4OQ', $db))
            throw new Error("Connection ERROR. ".mysqli_connect_errno().": ".mysqli_connect_error());
        setToLog("MySQL connection established");
    }
    public function __destruct() {
        setToLog("MySQL connection closed");
        parent::close();
    }
}

function LocalQuery($function, $param, $auth = NULL) {
	$cmd = "/usr/local/mgr5/sbin/mgrctl -m billmgr -o xml " . escapeshellarg($function) . " ";
	foreach ($param as $key => $value) {
            $cmd .= escapeshellarg($key) . "=" . escapeshellarg($value) . " ";
	}
	if (!is_null($auth)) {
            $cmd .= " auth=" . escapeshellarg($auth);
	}
	$out = array();
	exec($cmd, $out);
	$out_str = "";
	foreach ($out as $value) {
            $out_str .= $value . "\n";
	}
	//Debug("[LOCAL QUERY FUNCTION] mgrctl out: ". $out_str);
	return simplexml_load_string($out_str);
}

function GetConnection() {
    $param_map = array();
    $param_map["DBHost"] = "localhost";
    $param_map["DBUser"] = "coremgr";
    $param_map["DBPassword"] = "ZPTD2Wv4OQ";
    $param_map["DBName"] = "billmgr";
    return new DB($param_map["DBHost"], $param_map["DBUser"], $param_map["DBPassword"], $param_map["DBName"]);
}

function ItemParam($db, $iid) {
    $res = $db->query("SELECT i.id AS item_id, i.processingmodule AS item_module, i.period AS item_period, i.status AS item_status, i.expiredate, 
                              tld.name AS tld_name 
                       FROM item i 
                       JOIN pricelist p ON p.id = i.pricelist 
                       JOIN tld ON tld.id = p.intname 
                       WHERE i.id=" . $iid);
    if ($res == FALSE)
        throw new Error("query", $db->error);
    $param = $res->fetch_assoc();
    $param_res = $db->query("SELECT intname, value FROM itemparam WHERE item = ".$iid);
    while ($row = $param_res->fetch_assoc()) {
        $param[$row["intname"]] = $row["value"];
    }
    return $param;
}

function ItemProfiles($db, $iid, $module) {
    $param = array();
    $res = $db->query("SELECT sp2i.service_profile AS service_profile, sp2i.type AS type, sp2p.externalid AS externalid, sp2p.externalpassword AS externalpassword 
                       FROM item i 
                       JOIN service_profile2item sp2i ON sp2i.item = i.id 
                       LEFT JOIN service_profile2processingmodule sp2p ON sp2p.service_profile = sp2i.service_profile AND sp2i.type = sp2p.type AND sp2p.processingmodule = " . $module . "
                       WHERE i.id=" . $iid);
    while ($row = $res->fetch_assoc()) {
        $param[$row["type"]] = array();
        $param[$row["type"]]["externalid"] = $row["externalid"];
        $param[$row["type"]]["externalpassword"] = $row["externalpassword"];
        $param[$row["type"]]["service_profile"] = $row["service_profile"];
        $profile_res = $db->query("SELECT intname, value 
                       FROM service_profileparam 
                       WHERE service_profile=" . $row["service_profile"]);
        while ($profile_row = $profile_res->fetch_assoc()) {
            $param[$row["type"]][$profile_row["intname"]] = $profile_row["value"];
        }
    }
    return $param;
}

function getSignature($db, $moduleId) {
    $param_res = $db->query('SELECT value FROM processingcryptedparam WHERE processingmodule = ' . $moduleId . ' AND intname = "signature" LIMIT 1');
    if (!$param_res) {
        return '';
    }
    $signatureHash = $param_res->fetch_row()[0];
    $signature = system("echo '$signatureHash' | base64 -d | openssl rsautl -decrypt -inkey /usr/local/mgr5/etc/billmgr.pem");
    return $signature;
}

function getApiUrl($db, $moduleId) {
    $param_res = $db->query('SELECT value FROM processingparam WHERE processingmodule = ' . $moduleId . ' AND intname = "url" LIMIT 1');
    if (!$param_res) {
        return '';
    }
    $urlApi = $param_res->fetch_row()[0];
    return $urlApi;
}

function getCountryISO($db, $code) {
    $param_res = $db->query('SELECT iso2 FROM country WHERE id = ' . $code . ' LIMIT 1');
    if (!$param_res) {
        return '';
    }
    $iso = $param_res->fetch_row()[0];
    return strtolower($iso);
}

function setToLog($text) {
    error_log(date('d.m.Y H:i:s') . ' ' . $text . "\n", 3, '/usr/local/mgr5/processing/module_query_log.txt');
}

function HttpQuery($url, $param, $requesttype = 'POST', $username = '', $password = '', $header = ['Accept: application/xml'])
{
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
    if ($requesttype != 'POST' && $requesttype != 'GET') {
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $requesttype);
    } elseif ($requesttype == 'POST') {
        curl_setopt($curl, CURLOPT_POST, 1);
    } elseif ($requesttype == 'GET') {
        curl_setopt($curl, CURLOPT_HTTPGET, 1);
    }
    if (count($param) > 0) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, $param);
    }
    if (count($header) > 0) {
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
    }
    if ($username != '' || $password != '') {
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($curl, CURLOPT_USERPWD, $username . ':' . $password);
    }
    $out = curl_exec($curl);
    curl_close($curl);
    return $out;
}

class Error extends Exception
{
    private $m_object = "";
    private $m_value = "";
    private $m_param = "";
    function __construct($message, $object = "", $value = "", $param = array()) {
        parent::__construct($message);
        $this->m_object = $object;
        $this->m_value = $value;
        $this->m_param = $param;
        $error_msg = "Error: ". $message;
        if ($this->m_object != "")
                $error_msg .= ". Object: ". $this->m_object;
        if ($this->m_value != "")
                $error_msg .= ". Value: ". $this->m_value;
        setToLog($error_msg);
    }
    public function __toString()
    {
    	global $default_xml_string;
        $error_xml = simplexml_load_string($default_xml_string);
        $error_node = $error_xml->addChild("error");
        $error_node->addAttribute("type", parent::getMessage());
        if ($this->m_object != "") {
            $error_node->addAttribute("object", $this->m_object);
            $param = $error_node->addChild("param", $this->m_object);
            $param->addAttribute("name", "object");
            $param->addAttribute("type", "msg");
            $param->addAttribute("msg", $this->m_object);
        }
        if ($this->m_value != "") {
            $param = $error_node->addChild("param", $this->m_value);
            $param->addAttribute("name", "value");
            $desc = $error_node->addChild("param", "desck_empty");
            $desc->addAttribute("name", "desc");
            $desc->addAttribute("type", "msg");
        }
        foreach ($this->m_param as $name => $value) {
            $param = $error_node->addChild("param", $value);
            $param->addAttribute("name", $name);
        }
        return $error_xml->asXML();
    }
}
