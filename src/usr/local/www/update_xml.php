<?php
if (!empty($_POST['config_xml']))
{
	$config = file_get_contents("/cf/conf/config.xml");
    file_put_contents("/cf/conf/config.xml_ORIG", $config);
    include 'fauxapi_client.php';
    $obj= new Fauxapi_client();
    if (file_put_contents("/cf/conf/config.xml", $_POST['config_xml'])) {
    	shell_exec("/usr/local/bin/php  /etc/rc.filter_configure");
        $obj->deliver_responce('200','Config Updated Successfully',$_POST['config_xml']);
    }
    else{
    	$obj->deliver_responce('201','Config Not Updated',$_POST['config_xml']);
    }
}
else{
	$obj->deliver_responce('201','Blank Config Pushed!','');
}
?>
