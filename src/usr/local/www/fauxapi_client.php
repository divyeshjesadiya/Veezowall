<?php
class Fauxapi_client {
    public $apikey = '';
    public $apisecret = '';
    public $base_url='';
    public $reg_url='';
    public $current_device_ip='';
    public $password='';
    public $serial_no='';
    public $gui_ip='';
    public function __construct() {
        $this->log_file_name="/usr/local/www/aisense_logs/error.log";
        $this->curl_response="/usr/local/www/aisense_logs/curl.response";

        $filename='/etc/fauxapi/central_device_ip.json';
        $data_array = json_decode(file_get_contents($filename),true);
        $this->serial_no=$data_array['serial_no'];
        $this->apisecret=$data_array['faux_apisecret'];
        $this->apikey=$data_array['faux_apikey'];
        $this->current_device_ip=$data_array['device_ip'];
        $this->gui_ip=exec("ifconfig ".$data_array['wan_interface']." | grep 'inet' | tail -n 1 | cut -d ' ' -f2");
        $this->password=$data_array['password'];
        $this->base_url=$data_array['base_url'];
        $this->reg_url="http://veezowall.veezo.org:3000/";
    }
    
    public function _generate_auth($apikey='', $apisecret='', $use_verified_https=false, $debug=false) {
        $nonce=utf8_decode(base64_encode($this->devurandom_rand(40)));
        $nonce=mb_substr(str_ireplace('=', '', str_ireplace('+', '', str_ireplace('/', '', $nonce))), 0, 8);
        $timestamp=gmdate('Ymd\ZHis');
        $hash=hash('sha256', $apisecret.$timestamp.$nonce);
        $token = $apikey.":".$timestamp.":".$nonce.":".$hash;
        return $token;
    }

    public function devurandom_rand($min = 0, $max = 0x7FFFFFFF) {
        $diff = $max - $min;
        if ($diff < 0 || $diff > 0x7FFFFFFF) {
        throw new RuntimeException("Bad range");
        }
        $bytes = mcrypt_create_iv(4, MCRYPT_DEV_URANDOM);
        if ($bytes === false || strlen($bytes) != 4) {
            throw new RuntimeException("Unable to get 4 bytes");
        }
        $ary = unpack("Nint", $bytes);
        $val = $ary['int'] & 0x7FFFFFFF;   // 32-bit safe
        $fp = (float) $val / 2147483647.0; // convert to [0,1]
        return round($fp * $diff) + $min;
    }

    public function config_get($apikey,$apisecret,$device_id='checksum_config_check') {
        $ip=$this->gui_ip;
        $token=$this->_generate_auth($apikey,$apisecret);
        $url="http://".$ip."/fauxapi/v1/?action=config_get";
        $headers[] = 'Content-Type: application/json; charset=utf-8';
        $headers[] = 'fauxapi-auth: '.$token;
        $ch = curl_init();    
        curl_setopt($ch, CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER,$headers); 
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $output = curl_exec ($ch);
        $data=json_decode($output,true);
        $filename='/tmp/'.$device_id.'.json';
        $fp = fopen($filename, 'w');
        unset($data['data']['config']['revision']);
        fwrite($fp, json_encode($data['data']['config'], JSON_PRETTY_PRINT));
        fclose($fp);
        curl_close ($ch);
        return md5_file($filename);
    }

    public function curl($url, $post = array()){
        if(empty($post)){   //  FOR GET_CSRF TOKEN ONLY
            $this->ckfile = tempnam ("/tmp", "CURLCOOKIE");
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.0.12) Gecko/20070508 Firefox/1.5.0.12");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->ckfile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->ckfile);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,2);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch,CURLOPT_URL, $url);
        if(!empty($post)){
            $post['__csrf_magic'] = $this->csrf;
            $post_string = "";
            foreach($post as $key=>$value) { $post_string .= $key.'='.urlencode($value).'&'; }
            rtrim($post_string, '&');
            curl_setopt($ch,CURLOPT_POST, count($post));
            curl_setopt($ch,CURLOPT_POSTFIELDS, http_build_query($post));
        }
        $res = curl_exec($ch);
        file_put_contents($this->curl_response, "URL : ".$url." response is ".$res.PHP_EOL, FILE_APPEND);
        if (curl_errno($ch)) {
            $this->deliver_responce('201','Couldn\'t send request: ' . curl_error($ch));exit();
        }
        else {
            $resultStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($resultStatus == 200) {
                //echo "Post Successfully!";
                $this->csrf = substr($res, strpos($res,'sid:') , 55);
                return true;
            }
            else{
                $this->deliver_responce('200','Request failed: HTTP status code: ' . $resultStatus);exit();
            }
        }
    }

    public function get_csrf(){
        $ip=$this->gui_ip;
        $url = "http://".$ip."/index.php";
        $res = $this->curl($url);
    }

    public function get_login(){
        $ip=$this->gui_ip;
        $password = $this->password;
        $post = array(
            'login' => 'Login',
            'usernamefld' => 'admin',
            'passwordfld' => $password
        );
        $url = "http://".$ip."/index.php";
        $res = $this->curl($url, $post);
    }

    function apply_changes($module){
        $ip=$this->gui_ip;
        $this->get_csrf();
        $this->get_login();
        $url = $ip.'/'.$module;
        $post = array(
            'apply' => 'Apply Changes'
        );
        $res = $this->curl($url, $post);
    }

    public function suricata_interfaces(){
        $add_aliases=$this->add_aliases();
        file_put_contents($this->log_file_name, "add_aliases : ".$add_aliases.PHP_EOL, FILE_APPEND);
        $apply_changes=$this->apply_changes('firewall_aliases.php');
        file_put_contents($this->log_file_name, "apply_changes firewall_aliases.php : ".$apply_changes.PHP_EOL, FILE_APPEND);
        $suricata_passlist=$this->suricata_passlist();
        file_put_contents($this->log_file_name, "suricata_passlist : ".$suricata_passlist.PHP_EOL, FILE_APPEND);
        $ip=$this->gui_ip;
        $url = 'http://'.$ip.'/suricata/suricata_interfaces_edit.php?id=0';
        $post=array("eve_log_alerts_payload"=>"on","eve_log_http"=>"on","eve_log_dns"=>"on","eve_log_tls"=>"on","eve_log_files"=>"on","eve_log_ssh"=>"on","blockoffenders"=>"on","ips_mode"=>"ips_mode_inline","enable_eve_log"=>"on","eve_output_type"=>"syslog","eve_log_alerts"=>"on","enable"=>"on","interface"=>"wan","descr"=>"WAN","enable_http_log"=>"on","append_http_log"=>"on","http_log_extended"=>"on","max_pending_packets"=>"2048","detect_eng_profile"=>"medium","mpm_algo"=>"ac","sgh_mpm_context"=>"auto","intf_promisc_mode"=>"on","homelistname"=>"pass_bridge","externallistname"=>"pass_bridge","passlistname"=>"none","suppresslistname"=>"default","alertsystemlog"=>"on","alertsystemlog_facility"=>"auth","alertsystemlog_priority"=>"notice");
        $post['save'] = 'Save';
        $this->get_csrf();
        $this->get_login();
        $res = $this->curl($url, $post);
        $suricata_rulesets_ret = $this->suricata_rulesets();
        file_put_contents($this->log_file_name, "suricata_rulesets : ".$suricata_rulesets_ret.PHP_EOL, FILE_APPEND);
        $syslog_settings_ret = $this->syslog_settings();
        file_put_contents($this->log_file_name, "syslog_settings : ".$syslog_settings_ret.PHP_EOL, FILE_APPEND);
        return $syslog_settings_ret;
    }

    public function suricata_rulesets(){
        $ip=$this->gui_ip;
        $url = 'http://'.$ip.'/suricata/suricata_rulesets.php?id=0';
        $post=array("id"=>0,"toenable"=>array('GPLv2_community.rules','emerging-activex.rules','emerging-attack_response.rules','emerging-botcc.portgrouped.rules','emerging-botcc.rules','emerging-chat.rules','emerging-ciarmy.rules','emerging-compromised.rules','emerging-current_events.rules','emerging-deleted.rules','emerging-dns.rules','emerging-dos.rules','emerging-drop.rules','emerging-dshield.rules','emerging-exploit.rules','emerging-ftp.rules','emerging-games.rules','emerging-icmp.rules','emerging-icmp_info.rules','emerging-imap.rules','emerging-inappropriate.rules','emerging-info.rules','emerging-malware.rules','emerging-misc.rules','emerging-mobile_malware.rules','emerging-netbios.rules','emerging-p2p.rules','emerging-policy.rules','emerging-pop3.rules','emerging-rpc.rules','emerging-scada.rules','emerging-scan.rules','emerging-shellcode.rules','emerging-smtp.rules','emerging-snmp.rules','emerging-sql.rules','emerging-telnet.rules','emerging-tftp.rules','emerging-tor.rules','emerging-trojan.rules','emerging-user_agents.rules','emerging-voip.rules','emerging-web_client.rules','emerging-web_server.rules','emerging-web_specific_apps.rules','emerging-worm.rules'));
        $post['save'] = 'Save';
        $this->get_csrf();
        $this->get_login();
        $res = $this->curl($url, $post);
        return $res;
    }

    public function add_aliases(){
        $vip=$this->gui_ip;
        $vurl = 'http://'.$vip.'/firewall_aliases_edit.php?id=0';
        $vpost=array("name"=>"every_network","type"=>"network","address0"=>"0.0.0.0","address_subnet0"=>"1","address1"=>"128.0.0.0","address_subnet1"=>"1");
        $vpost['save'] = 'Save';
        $this->get_csrf();
        $this->get_login();
        $vres = $this->curl($vurl, $vpost);
        return $vres;
    }

    public function suricata_passlist(){
        $vip=$this->gui_ip;
        $vurl = 'http://'.$vip.'/suricata/suricata_passlist_edit.php?id=0';
        $vpost=array("name"=>"pass_bridge","localnets"=>"yes","wanips"=>"yes","wangateips"=>"yes","wandnsips"=>"yes","vips"=>"yes","vpnips"=>"yes","address"=>"every_network");
        $vpost['save'] = 'Save';
        $this->get_csrf();
        $this->get_login();
        $vres = $this->curl($vurl, $vpost);
        return $vres;
    }

    public function system_advanced_network(){
        $vip=$this->gui_ip;
        $vurl = 'http://'.$vip.'/system_advanced_network.php';
        $vpost=array("ipv6allow"=>"yes","disablechecksumoffloading"=>"yes","disablesegmentationoffloading"=>"yes","disablelargereceiveoffloading"=>"yes");
        $vpost['save'] = 'Save';
        $this->get_csrf();
        $this->get_login();
        $vres = $this->curl($vurl, $vpost);
        return $vres;
    }

    public function syslog_settings(){
        $vip=$this->gui_ip;
        $vurl = 'http://'.$vip.'/status_logs_settings.php';
        $vpost=array("nentries"=>"50","logdefaultblock"=>"yes","logbogons"=>"yes","logprivatenets"=>"yes","lognginx"=>"yes","enable"=>"yes","enable"=>"yes","remoteserver"=>"10.0.4.5:514","logall"=>"yes");
        $vpost['save'] = 'Save';
        $this->get_csrf();
        $this->get_login();
        $vres = $this->curl($vurl, $vpost);
        sleep(120);
        return $vres;
    }

    public function suricata_global(){
        $ip=$this->gui_ip;
        $autoruleupdatetime = array('00:30','00:31','00:32','00:33','00:34','00:35','00:36','00:37','00:38','00:39','00:40','00:41','00:42','00:43','00:44','00:45','00:46','00:47','00:48','00:49','00:50','00:51','00:52','00:53','00:54','00:55','00:56','00:57','00:58','00:59','01:00','01:01','01:02','01:03','01:04','01:05','01:06','01:07','01:08','01:09','01:10','01:11','01:12','01:13','01:14','01:15','01:16','01:17','01:18','01:19','01:20','01:21','01:22','01:23','01:24','01:25','01:26','01:27','01:28','01:29','01:30');
        $time = $autoruleupdatetime[array_rand($autoruleupdatetime)];
        $url = 'http://'.$ip.'/suricata/suricata_global.php';
        $post=array("enable_etopen_rules"=>"on","snortcommunityrules"=>"on","hide_deprecated_rules"=>"on","autoruleupdate"=>"6h_up","autoruleupdatetime"=>$time,"autogeoipupdate"=>"on","rm_blocked"=>"never_b","forcekeepsettings"=>"on");
        $post['save'] = 'Save';
        $this->get_csrf();
        $this->get_login();
        $res = $this->curl($url, $post);
        file_put_contents($this->log_file_name, "suricata_global : ".$res.PHP_EOL, FILE_APPEND);
        return $res;
    }

    public function interfaces(){
        $ip=$this->gui_ip;
        $url = 'http://'.$ip.'/interfaces.php?if=opt1';
        $post=array("enable"=>"yes","descr"=>"OPT1","type"=>"dhcp");
        $post['save'] = 'Save';
        $this->get_csrf();
        $this->get_login();
        $res = $this->curl($url, $post);
        return $res;
        $apply_changes=$this->apply_changes('interfaces.php?if=opt1');
    }

    public function deliver_responce($status,$msg,$data=array(),$print_flag=true){
        $responce=array();
        header('Access-Control-Allow-Origin: *');
        header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
        header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
        header('Content-type: application/json');
        $responce['status']=$status;
        $responce['msg']=$msg;
        $responce['data']=$data;
        $json_responce=json_encode($responce);
        if ($print_flag) {
            echo $json_responce;
        }
        return $json_responce;
    }
    
}
?>
