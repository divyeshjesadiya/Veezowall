<?php
class Fauxapi_client {
    public $apikey = '';
    public $apisecret = '';
    public $base_url='';
    public $reg_url='';
    public $current_device_ip='';
    public $serial_no='';
    public function __construct() {
        $filename='/etc/fauxapi/central_device_ip.json';
        $data_array = json_decode(file_get_contents($filename),true);
        $this->serial_no=$data_array['serial_no'];
        $this->apisecret=$data_array['faux_secret'];
        $this->apikey=$data_array['faux_key'];
        $this->current_device_ip=$data_array['device_ip'];
        $this->base_url="http://10.0.0.4/";
        $this->reg_url="http://veezowall.infrassist.com:3000/";
        // $this->base_url="http://35.195.48.62:3000/";
        // $this->reg_url="http://172.16.1.80/registration/";
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
        $ip=$this->current_device_ip;
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
}
?>
