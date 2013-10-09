<?php
require_once(__DIR__ . '/curl.class.php');
define('APPSHOPPER_STATUS_WANT', 'want');
define('APPSHOPPER_STATUS_OWN', 'own');
define('APPSHOPPER_STATUS_NIL', '');
/**
 * Class for manipulating Appshopper.com 
 * API from 'Appshopper iOS App' and 'Appshopper Importer Tools for Windows'
 *
 * @author sskaje (sskaje [at] gmail [dot] com)
 */
class Appshopper extends spCurl
{
	protected $curlopt_useragent = 'AppShopper/90 CFNetwork/598.1 Darwin/13.0.0 (sskaje)';
	protected $curlopt_cookiefile = '';
	protected $curlopt_cookiejar = '_cookie.txt';
	
	public function __construct()
	{
		parent::__construct();
	}
	
	protected function curl_setopt(&$ch) 
	{
		curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		curl_setopt($ch, CURLOPT_USERPWD, "appshop_api:cZgcWBKc"); 
		return true;
	}
	
	protected $version = '1.4.3';
	protected $appdevice = 'iPad3,3';
	protected $userid = '';
	protected $username = '';
	protected $userkey = '';
	protected $password = '';
	
	public function login($username, $password)
	{
		$this->username = $username;
		$this->password = $password;
		
		$url = 'https://api.appshopper.com/iphoneapplogin.php?appversion=' . urlencode($this->version) . '&appdevice=' . urlencode($this->appdevice) . '&username=' . urlencode($username) . '&rememberme=on&password=' . urlencode($password);
		$ret = $this->http_get($url);
		list($header, $response) = explode("\r\n\r\n", $ret, 2);
		if ($response != 'Success!') {
			die('Login failed');
		}
		$m = array();
		if (preg_match('#Set-Cookie: as_remember_userid=(\d+);#', $header, $m)) {
			$this->userid = $m[1];
		}
		if (preg_match('#Set-Cookie: as_remember_username=(.+);#U', $header, $m)) {
			$this->username = $m[1];
		}
		if (preg_match('#Set-Cookie: as_remember_key=(.+);#U', $header, $m)) {
			$this->userkey = $m[1];
		}
		return true;
	}
	
	public function detail($app_id)
	{
		# country Code ?
		$url = 'https://api.appshopper.com/iphoneappdetails.php?appversion=' . urlencode($this->version) . '&appdevice=' . urlencode($this->appdevice) . '&appleid='.$app_id.'&country=US';
		
		$ret = $this->http_get($url);
		list(, $response) = explode("\r\n\r\n", $ret, 2);
		
		$m = array();
		preg_match('#<key>updateid</key>\s*<string>(\d+)</string>#sU', $response, $m);
		if (!isset($m[1])) {
			return false;
		}
		$ownit = strpos($response, '<key>ownit</key><false/>') === false;
		$wantit = strpos($response, '<key>wantit</key><false/>') === false;
		
		return array(
			'updateid'	=>	$m[1],
			'want'		=>	$wantit,
			'own'		=>	$ownit,
		);
	}
	
	public function update($app_id, $status)
	{
		$detail = $this->detail($app_id);
		if (!$detail) {
			return false;
		}
		if ($status == APPSHOPPER_STATUS_OWN && $detail['own'] || $status == APPSHOPPER_STATUS_WANT && $detail['want']) {
			return true;
		}
		
		# Status: want, own, 
		$url = 'https://api.appshopper.com/iphoneappstatusajax.php?appleid=' . $app_id . '&updateid=' . $detail['updateid'] . '&status=' . $status;
		$ret = $this->http_get($url);
		return strpos($ret, '200 OK') !== false && strpos($ret, 'Content-Length: 0') !== false;
	}
	
	public function import($ids)
	{
		$id_list = '';
		$time = time();
		foreach ($ids as $id) {
			$id_list .= $id . ',' . $time . "\n";
		}
		
		$url = 'http://appshopper.com/api/userappimport.php';
		$postfields = 'username=' . urlencode($this->username) . '&password=' . urlencode($this->password) . '&appids=' . urlencode($id_list) . '';
		$ret = $this->http_post($url, $postfields);
		# there's an http 100 at the beginning, so just take the last piece of the splitted data
		$res = explode("\r\n\r\n", $ret);
		$response = end($res);
		return $response;
	}
}

# EOF