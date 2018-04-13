<?php
class Fauxapi_client {
    public $apikey = '';
    public $apisecret = '';
    public $base_url='';
    public $reg_url='';
    public $current_device_ip='';
    public $password='';
    public $serial_no='';
    public function __construct() {
        $filename='/etc/fauxapi/central_device_ip.json';
        $data_array = json_decode(file_get_contents($filename),true);
        $this->serial_no=$data_array['serial_no'];
        $this->apisecret=$data_array['faux_apisecret'];
        $this->apikey=$data_array['faux_apikey'];
        $this->current_device_ip=$data_array['device_ip'];
        $this->gui_ip=exec("ifconfig ".$data_array['wan_interface']." | grep 'inet' | tail -n 1 | cut -d ' ' -f2");
        $this->password=$data_array['password'];
        $this->base_url=$data_array['base_url'];
        $this->reg_url="http://veezowall.infrassist.com:3000/";
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
        fwrite($fp, json_encode($data['data']['config']));
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
            curl_setopt($ch,CURLOPT_POSTFIELDS, $post_string);
        }
        $res = curl_exec($ch);
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

    public function suricata_interfaces(){
        $ip=$this->gui_ip;
        $url = 'http://'.$ip.'/suricata/suricata_interfaces_edit.php?id=0';
        $post=array("eve_log_alerts_payload"=>"on","eve_log_http"=>"on","eve_log_dns"=>"on","eve_log_tls"=>"on","eve_log_files"=>"on","eve_log_ssh"=>"on","enable_eve_log"=>"on","eve_output_type"=>"syslog","eve_log_alerts"=>"on","enable"=>"on","interface"=>"wan","descr"=>"WAN","enable_http_log"=>"on","append_http_log"=>"on","http_log_extended"=>"on","max_pending_packets"=>"1024","detect_eng_profile"=>"medium","mpm_algo"=>"ac","sgh_mpm_context"=>"auto","intf_promisc_mode"=>"on","homelistname"=>"default","externallistname"=>"default","suppresslistname"=>"default","alertsystemlog"=>"on","alertsystemlog_facility"=>"auth","alertsystemlog_priority"=>"notice");
        $post['save'] = 'Save';
        $this->get_csrf();
        $this->get_login();
        $res = $this->curl($url, $post);
        $syslog_settings_ret = $this->syslog_settings();
        $suricata_rulesets_ret = $this->suricata_rulesets();
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
        $url = 'http://'.$ip.'/suricata/suricata_global.php';
        $post=array("enable_etopen_rules"=>"on","snortcommunityrules"=>"on","hide_deprecated_rules"=>"on","autoruleupdate"=>"6h_up","autoruleupdatetime"=>"00:30","autogeoipupdate"=>"on","rm_blocked"=>"never_b","forcekeepsettings"=>"on");
        $post['save'] = 'Save';
        $this->get_csrf();
        $this->get_login();
        $res = $this->curl($url, $post);
        return $res;
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
