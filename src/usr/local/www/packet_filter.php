<?php
include "guiconfig_packet_filter.inc";
include 'fauxapi_client.php';
$obj = new Fauxapi_client;
$uuid = $obj->serial_no;
function get_ip($addr) {
  $parts = explode(":", $addr);
  if (count($parts) == 2) {
    return (trim($parts[0]));
  } 
  else {
    /* IPv6 */
    $parts = explode("[", $addr);
    if (count($parts) == 2) {
      return (trim($parts[0]));
    }
  }
  return ("");
}
$res = pfSense_get_pf_states();
$json_file = file_get_contents('aisense_states.json');
$json = json_decode($json_file,true);
if (empty($json)) {
  $json=array(); 
}
$establish_start_array = array();
$establish_stop_array = array();
$tmp=array();
$establish='';
foreach ($res as $res_key => $res_value) {
  $info = $res_value['src'];
  $srcip = get_ip($res_value['src']);
  $dstip = get_ip($res_value['dst']);
  if ($res_value['src-orig']) {
    $info .= " (" . $res_value['src-orig'] . ")";
  }
  $info .= " -> ";
  $info .= $res_value['dst'];
  if ($res_value['dst-orig']) {
    $info .= " (" . $res_value['dst-orig'] . ")";
    $killdstip = get_ip($res_value['dst-orig']);
  }
  else {
    $killdstip = $dstip;
  }
  $ssplit = explode(':', $res_value['src']);
  $res_value['src-ip'] = $ssplit[0];
  $res_value['src-port'] = $ssplit[1];

  $dsplit = explode(':', $res_value['dst']);
  $res_value['dst-ip'] = $dsplit[0];
  $res_value['dst-port'] = $dsplit[1];
  //infrassist code
  if (!array_key_exists($res_value['src']."-".$res_value['dst'], $json)) {
    $establish.="\n\n START -- ".date('Y-m-d h:i:s')." serial-no:".$uuid.",if:".$res_value['if'].",proto:".$res_value['proto'].",direction:".$res_value['direction'].",src:".$res_value['src'].",src-orig:".$res_value['src-orig'].",dst:".$res_value['dst'].",state:".$res_value['state'].",age:".$res_value['age'].",expires in:".$res_value['expires in'].",packets total:".$res_value['packets total'].",packets in:".$res_value['packets in'].",packets out:".$res_value['packets out'].",bytes total:".$res_value['bytes total'].",bytes in:".$res_value['bytes in'].",bytes out:".$res_value['bytes out'].",rule:".$res_value['rule'].",id:".$res_value['id'].",creatorid:".$res_value['creatorid'].",src-ip:".$res_value['src-ip'].",src-port:".$res_value['src-port'].",dst-ip:".$res_value['dst-ip'].",dst-port:".$res_value['dst-port']." \n";
  }
  $json[$res_value['src']."-".$res_value['dst']]=$res_value;
  $tmp[$res_value['src']."-".$res_value['dst']]=$res_value;
}
foreach ($json as $json_key => $json_value) {
  if (!array_key_exists($json_key,$tmp)) {
    $establish.="\n\n STOP -- ".date('Y-m-d h:i:s')." serial-no:".$uuid.",if:".$json_value['if'].",proto:".$json_value['proto'].",direction:".$json_value['direction'].",src:".$json_value['src'].",src-orig:".$json_value['src-orig'].",dst:".$json_value['dst'].",state:".$json_value['state'].",age:".$json_value['age'].",expires in:".$json_value['expires in'].",packets total:".$json_value['packets total'].",packets in:".$json_value['packets in'].",packets out:".$json_value['packets out'].",bytes total:".$json_value['bytes total'].",bytes in:".$json_value['bytes in'].",bytes out:".$json_value['bytes out'].",rule:".$json_value['rule'].",id:".$json_value['id'].",creatorid:".$json_value['creatorid'].",src-ip:".$json_value['src-ip'].",src-port:".$json_value['src-port'].",dst-ip:".$json_value['dst-ip'].",dst-port:".$json_value['dst-port']." \n";
    unset($json[$json_key]);
  }
}
file_put_contents('/var/log/filter.log', $establish, FILE_APPEND);
file_put_contents('aisense_states.json', json_encode($json,JSON_PRETTY_PRINT));
?>
