<?php
include 'fauxapi_client.php';
$obj = new Fauxapi_client;
$suricata_checksum = '/usr/local/www/suricata_checksum';
if (file_exists($suricata_checksum)) {
	if (substr(file_get_contents($suricata_checksum), 2, 3) != '1') {
		if (substr(file_get_contents($suricata_checksum), 0, 1) != '1') {
			if ($interface=$obj->suricata_interfaces()) {
				if (substr(file_get_contents($suricata_checksum), 1, 2) != '1') {
					if ($globel=$obj->suricata_global()) {
						shell_exec('/usr/local/etc/rc.d/suricata.sh start');
						shell_exec("/usr/local/bin/php-cgi -f /usr/local/pkg/suricata/suricata_check_for_rule_updates.php &");
						file_put_contents($suricata_checksum, '111');
					}
					else{
						file_put_contents($suricata_checksum, '100');
					}
				}
				else{
					shell_exec('/usr/local/etc/rc.d/suricata.sh start');
					shell_exec("/usr/local/bin/php-cgi -f /usr/local/pkg/suricata/suricata_check_for_rule_updates.php &");
					file_put_contents($suricata_checksum, '111');
				}
			}
			else{
				file_put_contents($suricata_checksum, '000');
			}
		}
		else{
			if (substr(file_get_contents($suricata_checksum), 1, 2) != '1') {
				if ($globel=$obj->suricata_global()) {
					shell_exec('/usr/local/etc/rc.d/suricata.sh start');
					shell_exec("/usr/local/bin/php-cgi -f /usr/local/pkg/suricata/suricata_check_for_rule_updates.php &");
					file_put_contents($suricata_checksum, '111');
				}
				else{
					file_put_contents($suricata_checksum, '100');
				}
			}
			else{
				shell_exec('/usr/local/etc/rc.d/suricata.sh start');
				shell_exec("/usr/local/bin/php-cgi -f /usr/local/pkg/suricata/suricata_check_for_rule_updates.php &");
				file_put_contents($suricata_checksum, '111');
			}
		}
	}
	else{
	}
}
else{
	if ($interface=$obj->suricata_interfaces()) {
		if ($interface=$obj->suricata_global()) {
			shell_exec('/usr/local/etc/rc.d/suricata.sh start');
			shell_exec("/usr/local/bin/php-cgi -f /usr/local/pkg/suricata/suricata_check_for_rule_updates.php &");
			file_put_contents($suricata_checksum, '111');
		}
		else{
			file_put_contents($suricata_checksum, '100');
		}
	}
	else{
		file_put_contents($suricata_checksum, '000');
	}
}

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
