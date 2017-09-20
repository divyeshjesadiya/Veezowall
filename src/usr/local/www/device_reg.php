<?php
	$rurl=$_POST['cip'].'/fauxapi_client/VW_registration';
	//get config
	$config = file_get_contents("/cf/conf/config.xml");
	//save it to other file for back up
	file_put_contents("/cf/conf/config.xml_ORIG", $config);
	
	$rtemp = array('device_ip'=>$_POST['device_ip'],'serial_no'=>$_POST['serial_no'],'faux_apikey'=>$_POST['faux_key'],'faux_apisecret'=>$_POST['faux_secret'],'reseller_com'=>$_POST['com_name'],'addr'=>$_POST['address'],'city'=>$_POST['city'],'country'=>$_POST['country'],'config_xml'=>$config);

	$rtemp = http_build_query($rtemp);
	
	$headers[] = 'Content-Type: application/x-www-form-urlencoded; charset=utf-8';
	$headers[] = 'Content-Type: text/xml';
	$rch = curl_init();
	curl_setopt($rch, CURLOPT_CONNECTTIMEOUT, 60);
	curl_setopt($rch, CURLOPT_TIMEOUT, 60);
	curl_setopt($rch, CURLOPT_URL,$rurl);
	curl_setopt($rch, CURLOPT_POST, true);
	curl_setopt($rch, CURLOPT_POSTFIELDS, $rtemp);
	curl_setopt($rch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($rch, CURLOPT_HTTPHEADER,$headers);
	$routput = curl_exec ($rch);
	curl_close ($rch);
	if ($routput === false) {
		header("location:index.php?rstatus=no&ope=VCC not reachable");
	}
	else{
		$rresponse = json_decode($routput,true);
	    if ($rresponse['status'] == "200") {
	    	//replace config
	    	$config_file = "../../../cf/conf/config.xml";
	    	file_put_contents($config_file, $rresponse['data']['config_xml']);

	    	//credencial info save
	    	$credential_file = "/etc/fauxapi/credentials.ini";
	    	$append_data = "\n\n[".$_POST['faux_key']."]\n";
	    	$append_data .= "secret = ".$_POST['faux_secret']."\n";
	    	$append_data .= "owner = ".$_POST['com_name']." Firstname Lastname";
	    	file_put_contents($credential_file, $append_data);
	    	
	    	//central device info save
	    	$filename='/etc/fauxapi/central_device_ip.json';
	    	$fp = fopen($filename, 'w');
	    	file_put_contents($filename,json_encode($_POST));
	    	
	    	//check for file update
	    	include "check_update.php";
	    	// shell script for interfaces apply
	    	//shell_exec("/bin/sh /etc/rc.reload_interfaces");
	    	//shutdown after config replace
	    	shell_exec("/sbin/shutdown -r now");

	    	header("location:index.php?rstatus=yes&ope=".$rresponse['msg']);
	    }
	    else{
	    	header("location:index.php?rstatus=no&ope=".$rresponse['msg']);
	    }
	}
?>
