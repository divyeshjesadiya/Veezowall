<?php
	//shell_exec('/etc/rc.filter_configure');
	include 'fauxapi_client.php';
	$obj = new Fauxapi_client;
	$checksum=$obj->config_get($obj->apikey,$obj->apisecret,$device_id='checksum_config_check');
	$url=$obj->base_url.'/Fauxapi_client/post_checksum';
	$temp = array('serial_no'=>$obj->serial_no,'checksum'=>$checksum);
	$temp = http_build_query($temp);
	$headers[] = 'Content-Type: application/x-www-form-urlencoded; charset=utf-8';
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT ,0); 
	curl_setopt($ch, CURLOPT_TIMEOUT, 40);
	curl_setopt($ch, CURLOPT_URL,$url);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $temp);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER,$headers);
	$output = curl_exec ($ch);
	curl_close ($ch);
    echo($output);exit;
?>
