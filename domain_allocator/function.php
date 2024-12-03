<?php

use WHMCS\Database\Capsule;

header('Content-Type: application/json');

if (!empty($_POST['allocator_ajax_command'])) {
    require_once __DIR__ . '/../../../init.php';
}
if(!empty($_POST['allocator_ajax_command'])){

    $hostingInfo = Capsule::table('tblhosting')->where('domain', $_POST['webHosting'])->first();
    $serverInfo = Capsule::table('tblservers')->where('id', $hostingInfo->server)->first();

    $decryptPassword = damDecryptPassword($serverInfo->password);

    $domain = Capsule::table('tbldomains')->where('id', $_POST['id'])->value("domain");
    $subDomain = str_replace('.','',$domain);

    $domainStatus = damDomainStatus([
        'domain_id' => $_POST['id'],
        'action' => $_POST['assignmentType'],
        'hosting_domain_name' => $hostingInfo->domain,
        'check' => $_POST['check'],
        'user_name' => $hostingInfo->username,
        'server_id' => $hostingInfo->server,
        'server_hostname' => $serverInfo->hostname,
        'server_username' => $serverInfo->username,
        'server_type' => $serverInfo->type,
        'server_password' => $decryptPassword,
        'nameserver1' => $serverInfo->nameserver1,
        'nameserver2' => $serverInfo->nameserver2,
        'domain' => $domain,
        'sub_domain' => $subDomain,
        'token' => $serverInfo->accesshash,
    ]);

    $curlResponse = $domainStatus;
    echo json_encode($curlResponse);
    exit();
}

function damDomainStatus($data){

    $enable_module_logs = Capsule::table('tbladdonmodules')
        ->where('module', 'domain_allocator')
        ->where('setting', 'Enable Module Logs')
        ->value('value');

    $module = 'Domain Allocator';
    $responseData = '';
    $getResponse = [];

    if(!empty($data['check'])){
        $nameserversResponse  = damDomainUpdateNameservers($data['domain_id'],$data['nameserver1'],$data['nameserver2']);

        $getResponse[0] = $nameserversResponse;
        $requestString = 'The '.$data['domain'].' has been successfully added in '.$data['hosting_domain_name']. ' hosting and the domain nameservers has been changed.';
        $changeDomainNameServers = 1;
    }else{
        $requestString = 'The '.$data['domain'].' has been successfully added in '.$data['hosting_domain_name']. ' hosting.';
    }

    $action = $data['action'];
    if($data['action'] == 'Addon domain'){
        $query = "https://".$data['server_hostname'].":2087/json-api/cpanel?cpanel_jsonapi_user=".$data['user_name']."&cpanel_jsonapi_apiversion=2&cpanel_jsonapi_module=AddonDomain&cpanel_jsonapi_func=addaddondomain&newdomain=".$data['domain']."&subdomain=".$data['sub_domain']."&rootdomain=".$data['hosting_domain_name']."&dir=".$data['domain'];
    }elseif($data['action'] == 'Parked/Alias domain'){
        $query = "https://".$data['server_hostname'].":2087/json-api/create_parked_domain_for_user?api.version=1&domain=".$data['domain']."&username=".$data['user_name']."&web_vhost_domain=".$data['hosting_domain_name'];
    }

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER,0);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST,0);
    curl_setopt($curl, CURLOPT_HEADER,0);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER,1);

    if(!empty($data['token'])){
        $name = $data['server_username'];
        $token = $data['token'];
        $header[0] = "Authorization: whm $name:$token";
    }else{
        $header[0] = "Authorization: Basic " . base64_encode($data['server_username'].":".$data['server_password']) . "\n\r";
    }

    curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
    curl_setopt($curl, CURLOPT_URL, $query);
    $result = curl_exec($curl);

    $reason = json_decode($result,true);

    if (curl_errno($curl)) {
        print ('cURL error: ' . curl_error($curl));
    }

    foreach ($reason as $key => $row){}

    foreach ($row as $key => $row2){

        if($key == 'data'){
            $getResponse[1] = $row2;
            $responseData = $getResponse;

            if(!empty($enable_module_logs)){
                logModuleCall($module, $action, $requestString, $responseData);
            }
        }

        if($key == 'error'){

            $startingString = 'A DNS entry for the domain';
            $endString = 'already exists.';
            if (strpos($row2, $startingString) !== false) {
                $error = damExtractError($row2,$startingString,$endString);

                $curlResponse['status']  = 'error';
                $curlResponse['message'] = $error;
                $curlResponse['alertClass'] = 'alert-danger';
                return $curlResponse;

            } else {
                if($changeDomainNameServers == 1){
                    $curlResponse['status']  = 'error';
                    $curlResponse['message'] = $row2;
                    $curlResponse['alertClass'] = 'alert-danger';
                    return $curlResponse;
                }else{
                    $startingString = 'The domain '.$data['domain'].' already exists in the userdata.';
                    //$endString = 'exists in the userdata.';

                    if (strpos($row2, $startingString) !== false) {
                        //$error = damExtractError($row2,$startingString,$endString);

                        $curlResponse['status']  = 'error';
                        $curlResponse['message'] = 'The domain '.$data['domain'].' already exists in another hosting . Please either remove the domain form previous hosting account or contact support';
                        $curlResponse['alertClass'] = 'alert-danger';
                        return $curlResponse;

                    }else{
                        $curlResponse['status']  = 'error';
                        $curlResponse['message'] = $row2;
                        $curlResponse['alertClass'] = 'alert-danger';
                        return $curlResponse;
                    }

                }
            }
        }
    }

    curl_close($curl);

    $curlResponse['status']  = 'success';
    $curlResponse['message'] = $requestString;
    $curlResponse['alertClass'] = 'alert-success';
    return $curlResponse;
}
function damDecryptPassword($serverPassword){
    $adminUserName = Capsule::table('tbladdonmodules')->where([
        ['module', 'domain_allocator'],
        ['setting', 'Admin User']
    ])->value('value');


    $command = 'DecryptPassword';
    $postData = array(
        'password2' => $serverPassword,
    );

    $results = localAPI($command, $postData, $adminUserName);
    return $results['password'];
}
function damDomainUpdateNameservers($domainId,$nameServer1,$nameServer2){
    $command = 'DomainUpdateNameservers';
    $postData = array(
        'domainid' => $domainId,
        'ns1' => $nameServer1,
        'ns2' => $nameServer2,
    );
    $results = localAPI($command, $postData);

    if ($results['result'] == 'success'){
        //echo "Nameservers updated successfully!";
    } else {
        logactivity('Error : '.print_r($results['message'],true));
        return $results;
    }
}
function damExtractError($string,$startingString,$endString){

    $startPos = strpos($string, $startingString);
    if ($startPos == false){
        logactivity('starting_string not found');
    }

    $endPos = strpos($string, $endString);
    if ($endPos == false){
        logactivity('end_string not found');
    }

    $length = $endPos - $startPos + strlen($endString);
    $result = substr($string, $startPos, $length);
    return $result;
}