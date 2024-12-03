<?php

use WHMCS\Database\Capsule;
use WHMCS\View\Menu\Item as MenuItem;

$localkey = '';
$params_ajax = false;
$licensekey = Capsule::table('tbladdonmodules')
    ->where('module', 'domain_allocator')
    ->where('setting', 'License Key')
    ->value('value');

function domainAllocatorCheckLicense($licensekey, $localkey, $params_ajax){
        $whmcsurl = 'https://whmcs.whmpress.com/';

        $licensing_secret_key = 'domain_allocator';

        $localkeydays = 15;

        $allowcheckfaildays = 5;



        // -----------------------------------

        //  -- Do not edit below this line --

        // -----------------------------------


        $check_token = time() . md5(mt_rand(100000000, mt_getrandmax()) . $licensekey);

        $checkdate = date("Ymd");

        $domain = $_SERVER['SERVER_NAME'];

        $usersip = isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : $_SERVER['LOCAL_ADDR'];

        $dirpath = dirname(__FILE__);

        $verifyfilepath = 'modules/servers/licensing/verify.php';

        $localkeyvalid = false;

        if ($localkey) {

            $localkey = str_replace("\n", '', $localkey); # Remove the line breaks

            $localdata = substr($localkey, 0, strlen($localkey) - 32); # Extract License Data

            $md5hash = substr($localkey, strlen($localkey) - 32); # Extract MD5 Hash

            if ($md5hash == md5($localdata . $licensing_secret_key)) {

                $localdata = strrev($localdata); # Reverse the string

                $md5hash = substr($localdata, 0, 32); # Extract MD5 Hash

                $localdata = substr($localdata, 32); # Extract License Data

                $localdata = base64_decode($localdata);

                $localkeyresults = json_decode($localdata, true);

                $originalcheckdate = $localkeyresults['checkdate'];

                if ($md5hash == md5($originalcheckdate . $licensing_secret_key)) {

                    $localexpiry = date("Ymd", mktime(0, 0, 0, date("m"), date("d") - $localkeydays, date("Y")));

                    if ($originalcheckdate > $localexpiry) {

                        $localkeyvalid = true;

                        $results = $localkeyresults;

                        $validdomains = explode(',', $results['validdomain']);

                        if (!in_array($_SERVER['SERVER_NAME'], $validdomains)) {

                            $localkeyvalid = false;

                            $localkeyresults['status'] = "Invalid";

                            $results = array();

                        }

                        $validips = explode(',', $results['validip']);

                        if (!in_array($usersip, $validips)) {

                            $localkeyvalid = false;

                            $localkeyresults['status'] = "Invalid";

                            $results = array();

                        }

                        $validdirs = explode(',', $results['validdirectory']);

                        if (!in_array($dirpath, $validdirs)) {

                            $localkeyvalid = false;

                            $localkeyresults['status'] = "Invalid";

                            $results = array();

                        }

                    }

                }

            }

        }

        if (!$localkeyvalid) {

            $responseCode = 0;

            $postfields = array(

                'licensekey' => $licensekey,

                'domain' => $domain,

                'ip' => $usersip,

                'dir' => $dirpath,

            );

            if ($check_token) $postfields['check_token'] = $check_token;

            $query_string = '';

            foreach ($postfields as $k => $v) {

                $query_string .= $k . '=' . urlencode($v) . '&';

            }

            if (function_exists('curl_exec')) {

                $ch = curl_init();

                curl_setopt($ch, CURLOPT_URL, $whmcsurl . $verifyfilepath);

                curl_setopt($ch, CURLOPT_POST, 1);

                curl_setopt($ch, CURLOPT_POSTFIELDS, $query_string);

                curl_setopt($ch, CURLOPT_TIMEOUT, 30);

                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

                $data = curl_exec($ch);

                $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

                curl_close($ch);

            } else {

                $responseCodePattern = '/^HTTP\/\d+\.\d+\s+(\d+)/';

                $fp = @fsockopen($whmcsurl, 80, $errno, $errstr, 5);

                if ($fp) {

                    $newlinefeed = "\r\n";

                    $header = "POST " . $whmcsurl . $verifyfilepath . " HTTP/1.0" . $newlinefeed;

                    $header .= "Host: " . $whmcsurl . $newlinefeed;

                    $header .= "Content-type: application/x-www-form-urlencoded" . $newlinefeed;

                    $header .= "Content-length: " . @strlen($query_string) . $newlinefeed;

                    $header .= "Connection: close" . $newlinefeed . $newlinefeed;

                    $header .= $query_string;

                    $data = $line = '';

                    @stream_set_timeout($fp, 20);

                    @fputs($fp, $header);

                    $status = @socket_get_status($fp);

                    while (!@feof($fp) && $status) {

                        $line = @fgets($fp, 1024);

                        $patternMatches = array();

                        if (!$responseCode

                            && preg_match($responseCodePattern, trim($line), $patternMatches)

                        ) {

                            $responseCode = (empty($patternMatches[1])) ? 0 : $patternMatches[1];

                        }

                        $data .= $line;

                        $status = @socket_get_status($fp);

                    }

                    @fclose($fp);

                }

            }

            if ($responseCode != 200) {

                $localexpiry = date("Ymd", mktime(0, 0, 0, date("m"), date("d") - ($localkeydays + $allowcheckfaildays), date("Y")));

                if ($originalcheckdate > $localexpiry) {

                    $results = $localkeyresults;

                } else {

                    $results = array();

                    $results['status'] = "Invalid";

                    $results['description'] = "Remote Check Failed";

                    return $results;

                }

            } else {

                preg_match_all('/<(.*?)>([^<]+)<\/\\1>/i', $data, $matches);

                $results = array();

                foreach ($matches[1] as $k => $v) {

                    $results[$v] = $matches[2][$k];

                }

            }

            if (!is_array($results)) {

                die("Invalid License Server Response");

            }

            if ($results['md5hash']) {

                if ($results['md5hash'] != md5($licensing_secret_key . $check_token)) {

                    $results['status'] = "Invalid";

                    $results['description'] = "MD5 Checksum Verification Failed";

                    return $results;

                }

            }

            if ($results['status'] == "Active") {

                $results['checkdate'] = $checkdate;

                $data_encoded = json_encode($results);

                $data_encoded = base64_encode($data_encoded);

                $data_encoded = md5($checkdate . $licensing_secret_key) . $data_encoded;

                $data_encoded = strrev($data_encoded);

                $data_encoded = $data_encoded . md5($data_encoded . $licensing_secret_key);

                $data_encoded = wordwrap($data_encoded, 80, "\n", true);

                $results['localkey'] = $data_encoded;

            }

            $results['remotecheck'] = true;

        }

        unset($postfields, $data, $matches, $whmcsurl, $licensing_secret_key, $checkdate, $usersip, $localkeydays, $allowcheckfaildays, $md5hash);

        if ($params_ajax == true) {

            return [

                'status' => "OK",

                'curl_response' => $results,

            ];

        }

        return $results;

    }

$results = domainAllocatorCheckLicense($licensekey, $localkey, $params_ajax);

//logactivity('xyz : '.print_r($results,true));

if ($results['status'] == "Active"){
    add_hook('ClientAreaPrimarySidebar', 1, 'damPrimarySidebar');
    add_hook('ClientAreaFooterOutput', 1, 'damFooterOutput');
}

//----------------Functions-------------------

function damPrimarySidebar(MenuItem $checkout){

    if (App::get_req_var('action') == 'domaindetails') {
        if (!is_null($checkout->getChild('Domain Details Management'))) {
            $checkout->getChild('Domain Details Management')
                ->addChild('Mailing List Subscription Prefs')
                ->setLabel('<button style="border: 0;background: transparent;display: block;width: 100%;padding: 0;text-align: left;" type="button" data-toggle="modal" data-target="#Mailing_List_Subscription_Prefs" id="Primary_Sidebar-Domain_Details_Management-Mailing_List_Subscription_Prefs">Assign to Hosting</button>')
                ->setOrder(100);
        }
    }
}
function damFooterOutput(){


    if (App::get_req_var('action') == 'domaindetails') {
        echo '<style>
.domain_allocator .hosting_status_message {
    background: #fff6e5;
    display: table;
    padding: 5px 10px;
    font-size: 14px;
    margin: 0 auto 20px;
}
.domain_allocator .clients_hostings label {
    font-size: 15px;
    color: #333;
}
.domain_allocator .modal-header h5 {
    color: #333;
    font-size: 16px;
    font-weight: 600;
}
.domain_allocator .modal-header h5 b {
    color: #0e5077;
}
.domain_allocator button.close {
    position: absolute;
    right: 15px;
    top: 14px;
    color: #0e5077;
    opacity: 1;
    font-weight: 700;
    font-size: 26px;
}
.domain_allocator button {
    border: 0 !important;
    outline: 0 !important;
}
.domain_allocator form#change_hostings {
    margin-bottom: 10px;
}
</style>';

        $pageId = $_GET['id'];
        if (!empty($pageId)){
            $userId = Capsule::table('tbldomains')->where('id', $pageId)->value("userid");
            $domain = Capsule::table('tbldomains')->where('id', $pageId)->value("domain");
        }

        $checkDomainHostings[] = damCheckDomainHostings($userId);

        foreach ($checkDomainHostings[0] as $key => $checkDomainHosting) {
            if ($domain == $key){
                $hostingLocation = '<div class="hosting_status_message">This domain is currently link with <b>'.$key.'</b> hosting</div>';
            }else{
                foreach ($checkDomainHosting as $checkDomain){
                    if ($domain == $checkDomain){
                        $hostingLocation = '<div class="hosting_status_message">This domain is currently link with <b>'.$key.'</b> hosting</div>';
                    }
                }
            }
        }
        $hostingAccounts = damGetClientHosting($userId);
        include "modal_view.php";
    }
}

function damCheckDomainHostings($userId)
{
    $enable_module_logs = Capsule::table('tbladdonmodules')
        ->where('module', 'domain_allocator')
        ->where('setting', 'Enable Module Logs')
        ->value('value');



    $allUserHostings = Capsule::table('tblhosting')->where('userid', $userId)->select('domain')->get()->toArray();
    foreach ($allUserHostings as $allUserHosting) {
        if (!empty($allUserHosting->domain)) {
            $allHostings[] = $allUserHosting->domain;
        }
    }

    foreach ($allHostings as $allHosting){


        $domainServerId = Capsule::table('tblhosting')->where('domain', $allHosting)->value("server");

        $domainServer = Capsule::table('tblservers')->where('id', $domainServerId)->value("type");

        if ($domainServer == 'cpanel') {

            $domainUsername = Capsule::table('tblhosting')->where('domain', $allHosting)->value("username");

            if ($domainUsername) {

                $serverDetails = Capsule::table('tblservers')->where('id',$domainServerId)->first(['username', 'password', 'accesshash', 'hostname']);

                if ($serverDetails) {
                    $whmUser = $serverDetails->username;
                    $decryptPassword = damDecryptPassword($serverDetails->password);
                    $whmToken = $serverDetails->accesshash;
                    $currentHostname = $serverDetails->hostname;
                }

                $url = "https://$currentHostname:2087/json-api/cpanel";
                $params = [
                    'api.version' => 1,
                    'cpanel_jsonapi_user' => $domainUsername,
                    'cpanel_jsonapi_module' => 'DomainInfo',
                    'cpanel_jsonapi_func' => 'list_domains',
                    'cpanel_jsonapi_apiversion' => 3,
                    'user' => $domainUsername
                ];

                $ch = curl_init($url);

                if(!empty($whmToken)){
                    $header[0] = "Authorization: whm $whmUser:$whmToken";
                }else{
                    $header[0] = "Authorization: Basic " . base64_encode($whmUser.":".$decryptPassword) . "\n\r";
                }
                curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));

                $response = curl_exec($ch);

                $module = 'Domain Allocator';
                $action = 'existing-hosting-check';
                $requestString = 'Check if the domain is already added in any of the existing hosting';


                if ($response === false){

                    $responseData = "Error: " . curl_error($ch);
                    if(!empty($enable_module_logs)){
                        logModuleCall($module, $action, $requestString, $responseData);
                    }
                } else {

                    $decodedResponse = json_decode($response, true);
                    $responseData = $decodedResponse;

                    if(!empty($enable_module_logs)){
                        logModuleCall($module, $action, $requestString, $responseData);
                    }

                    if ($decodedResponse === null) {
                        logactivity('domain status error');
                    } else {
                        $subDomains = !empty($decodedResponse['result']['data']['sub_domains']) ? $decodedResponse['result']['data']['sub_domains'] : [];
                        $parkedDomains = !empty($decodedResponse['result']['data']['parked_domains']) ? $decodedResponse['result']['data']['parked_domains'] : [];
                        $addonDomains = !empty($decodedResponse['result']['data']['addon_domains']) ? $decodedResponse['result']['data']['addon_domains'] : [];
                        $parkedMainDomains = !empty($decodedResponse['result']['data']['main_domain']) ? $decodedResponse['result']['data']['main_domain'] : [];
                        if (!is_array($parkedMainDomains)){
                            $parkedMainDomains = array($parkedMainDomains);
                        }
                        if (!empty($decodedResponse)) {
                            $margeDomain[$allHosting] = array_merge($subDomains, $parkedDomains, $parkedMainDomains, $addonDomains);
                        }
                    }
                }

                curl_close($ch);
            }
        }
    }
    return $margeDomain;
}
function damGetClientHosting($userId)
{
    $allClientHostings = Capsule::table('tblhosting')
        ->where('userid', $userId)
        ->select('domain','server')
        ->get()
        ->toArray();
    foreach ($allClientHostings as $allClientHosting){
        $serverId = $allClientHosting->server;
        $serverStatus = Capsule::table('tblservers')->where('id', $serverId)->value("type");

        if ($serverStatus == 'cpanel'){
            $clientDomain = strtolower(str_replace(' ', '_', $allClientHosting->domain));
            if(!empty($clientDomain) && !empty($allClientHosting->domain)){
                $clientFormation = '<option value="'.$clientDomain.'">'.$allClientHosting->domain.'</option>';
            }
        }
    }
    return $clientFormation;
}
function damDecryptPassword($serverPassword){
    $adminUsername = 'samama';
    $command = 'DecryptPassword';
    $postData = array(
        'password2' => $serverPassword,
    );

    $results = localAPI($command, $postData, $adminUsername);
    return $results['password'];
}