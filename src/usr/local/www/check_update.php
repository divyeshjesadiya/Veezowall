<?php
include 'fauxapi_client.php';
$obj = new Fauxapi_client;
$url = $obj->base_url.'/Fauxapi_client/check_file_update';
if (file_exists('aisense_update.json')) {
	$get_file = file_get_contents($file);
	$current_files = json_decode($get_file,true);
}
else{
	$current_files = array();
}
$temp = $current_files;
$temp = http_build_query($temp);
$headers[] = 'Content-Type: application/x-www-form-urlencoded; charset=utf-8';
$ch = curl_init();    
curl_setopt($ch, CURLOPT_URL,$url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $temp);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER,$headers);
$output = curl_exec ($ch);
curl_close ($ch);
$response = json_decode($output,true);//var_dump($response);
if ($response['status'] == "200") {
	//$protocol = "http://";//((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
	foreach ($response['data'] as $key => $value) {
		if ($value['flag']!='same') {
			file_put_contents($value['name'], file_get_contents($obj->base_url."/".$value['file_path']));
		}
	}
	file_put_contents('aisense_update.json',json_encode($response['data']));
}
?>
