<?php

class iTunes
{
	protected function init_curl ()
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_USERAGENT, "iTunes/10.6 (Windows; Microsoft Windows 7 x64 Ultimate Edition Service Pack 1 (Build 7601)) AppleWebKit/534.54.16");
		//curl_setopt($ch, CURLOPT_USERAGENT, "iTunes/8.6 AppleWebKit/334.52.7");
		curl_setopt($ch, CURLOPT_COOKIEFILE, '_cookie.txt');
		curl_setopt($ch, CURLOPT_COOKIEJAR, '_cookie.txt');
		
		curl_setopt($ch, CURLOPT_TIMEOUT, 60);
		curl_setopt($ch, CURLOPT_VERBOSE, 1);
		curl_setopt($ch, CURLOPT_HEADER, 1);
		$this->curl = & $ch;
	}
	protected $curl;
	
	public function __construct()
	{
		$this->init_curl();
	}
	
	public function __destruct()
	{
		curl_close($this->curl);
	}


	private function _http_log($msg)
	{
		return file_put_contents('logs/request.log', $msg."\n", FILE_APPEND);
	}

	protected function http_post($url, $data, $content_type='application/x-www-form-urlencoded')
	{
		$ch = & $this->curl;
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		$this->_http_log("HTTP POST: {$url}\nPOST FIELDS: {$data}");
		$retry_count = 0;
		
		$this->http_add_header('Content-Type', $content_type);
POST_EXEC:
		$v = $this->curl_exec();
		if (($code = curl_getinfo($ch, CURLINFO_HTTP_CODE)) != '200') {
			$retry_count++;
			$this->_http_log('Retry++'.$code);
			goto POST_EXEC;
		}
		return trim($v);
	}
	
	protected function http_get($url)
	{
		$ch = & $this->curl;
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, 0);
		curl_setopt($ch, CURLOPT_HTTPGET, 1);
		$this->_http_log("HTTP GET: {$url}");
		$retry_count = 0;
GET_EXEC:
		$v = $this->curl_exec();
		if (($code = curl_getinfo($ch, CURLINFO_HTTP_CODE)) != '200') {
			$retry_count++;
			$this->_http_log('Retry++'.$code);
			goto GET_EXEC;
		}
        return trim($v);
	}
	
	protected $headers = array();

	protected function http_add_headers($headers)
	{
		foreach ($headers as $k=>$v) {
			$this->http_add_header($k, $v);
		}
	}
	protected function http_add_header($key, $value)
	{
		$this->headers[$key] = $value;
	}
	
	protected function curl_exec()
	{
		$headers = array();
		foreach ($this->headers as $k=>$v) {
			$headers[] = "{$k}: {$v}";
		}
		
		curl_setopt($this->curl, CURLOPT_HTTPHEADER, $headers);
		return curl_exec($this->curl);
	}
	
	public $guid = '';
	public $apple_id = '';
	public $password = '';
	
	protected $dsid = '';
	protected $password_token = '';
	protected $store_front = '';
	
	public function login()
	{
		$login_url = "https://buy.itunes.apple.com/WebObjects/MZFinance.woa/wa/authenticate";
		$login_referer = 'http://itunes.apple.com/WebObjects/MZStore.woa/wa/viewGrouping?id=25204&mt=8&s=143441&pillIdentifier=iphone';
		$login_post = 'matchineName=LOCALHOST&why=signin&attempt=1&createSession=true&guid='.urlencode($this->guid);
		$login_post .= '&appleId='.urlencode($this->apple_id).'&password='.urlencode($this->password);

		$this->http_add_header('Content-Type', 'application/x-apple-plist');
		$ret = $this->http_post($login_url, $login_post, 'application/x-www-form-urlencoded');
		echo $ret;

		# <key>passwordToken</key><string>AwIAAAECAAGI+AAAAABPW1YUwpINRvzzJQmNsoq+mk4wiFDFK4g=</string>
		$m = array();
		if (preg_match('#x-set-apple-store-front: ([\d\-\,]+)#', $ret, $m)) {
			$this->store_front = $m[1];
		} else {
			$this->store_front = '143441-1';
		}
		
		# <key>passwordToken</key><string>AwIAAAECAAGI+AAAAABPW1YUwpINRvzzJQmNsoq+mk4wiFDFK4g=</string>
		$m = array();
		if (!preg_match('#<key>passwordToken</key><string>([a-zA-Z0-9\+\=\/]+)</string>#', $ret, $m)) {
			die('bad pwd token');
		}
		$this->password_token = $m[1];
	
		# <key>dsid</key><integer>1237308672</integer>
		# <key>dsPersonId</key><string>1237308672</string>
		$m = array();
		if (!preg_match('#<key>dsid</key><integer>([0-9]+)</integer>#', $ret, $m)) {
			die('bad dsid');
		}
		$this->dsid = $m[1];
	
		$cuid = '7c1c1c990bcd9faec5493e45e6fd8d69';
	
		$headers = array(
			'X-Token'		=> $this->password_token,
		    'X-Apple-Tz'	=> '28800',
			'X-Dsid'		=> $this->dsid,
		    'X-Apple-Store-Front'	=>	$this->store_front,
		
		    'X-Apple-Cuid' => $cuid,
		#    "Origin: https://p{$domain_suffix}-buy.itunes.apple.com",
		);
		
		$this->http_add_headers($headers);
	}
	
	public function is_valid_redeem($redeem_code) 
	{
	    return preg_match('#^X[A-Z]{15}$#', $redeem_code);
	}
	
	public function build_request_xml($xml_name, $template_vars)
	{
		$xml_path = __DIR__ . '/xmls/' . $xml_name . '.xml';
		if (!is_file($xml_path)) {
			die('aaa');
		} else {
			$f = file_get_contents($xml_path);
			$replaces = array();
			foreach ($template_vars as $k=>$v) {
				$replaces['{#'.$k.'#}'] = $v;
			}
			return strtr($f, $replaces);
		}
	}
	
	public function buy_purchased($appid)
	{
		$template_vars = array(
			'PRICINGPARAMETERS' =>  'STDQ',
			'PRODUCTTYPE'       =>  'C',
			'APPID'             =>  $appid,
			'GUID'              =>  $this->guid,
		);
	
		$post = $this->build_request_xml('download_purchased', $template_vars);
		$url = 'https://buy.itunes.apple.com/WebObjects/MZBuy.woa/wa/buyProduct?xToken=' . urlencode($this->password_token);
		
		curl_setopt($this->curl, CURLOPT_REFERER, 'http://itunes.apple.com/cn/app/id'.$appid.'?mt=8');
		echo $post;
		$ret = $this->http_post($url, $post, 'application/x-apple-plist');
		
		var_dump($ret);
	}
	
	

	public function redeem($redeem_code, $test_only=false)
	{
		$redeem_code = strtoupper($redeem_code);
		echo "Code: {$redeem_code} ... ";
	
		$page_url = "https://buy.itunes.apple.com/WebObjects/MZFinance.woa/wa/redeemLandingPage";
		$ret = $this->http_get($page_url);
	
		$m = array();
		if (!preg_match('#<form name=".+" method="post" action="(/WebObjects/MZFinance.woa/wo/[0-9\.]+)">#', $ret, $m)) {
			die('bad form in redeem landing page');
		}
		$form_url = "https://buy.itunes.apple.com{$m[1]}";
	
		if (!preg_match('#<input class="submit" id="redeemButton" type="submit" value="Redeem" name="([0-9\.]+)" />#', $ret, $m)) {
			die('bad redeem form input name');
		}
		$form_name = $m[1];

		$post_fields = "code={$redeem_code}&{$form_name}=Redeem";
		$ret = $this->http_post($form_url, $post_fields);
		
		if (false !== (strpos($ret, 'already been redeemed'))) {
			echo "Already redeemed\n";
			return false;
		} else if (false !== (strpos($ret, 'The code you entered is not recognized as a valid code'))) {
			echo "Invalid code\n";
			return false;
		} else {
			echo "OK!\n";
			file_put_contents('code.txt', $redeem_code . "\n", FILE_APPEND);
			file_put_contents('redeemed.txt', $ret);
			
			if (!$test_only) {
				$m = array();
				preg_match('#<key>url</key><string>(https://buy.itunes.apple.com/WebObjects/MZFinance.woa/wa/com.apple.jingle.app.finance.DirectAction/redeemGiftCertificate.+)</string>#', $ret, $m);
				$url = htmlspecialchars_decode($m[1]);
				$ret = $this->http_get($url);
				
				file_put_contents('redeem_output.txt', $ret);
				
				if (strpos($ret, 'Your Apple ID has been credited with $') !== false) {
					$m = array();
					preg_match('#Your Apple ID has been credited with \$([0-9\.]+).#', $ret, $m);
					$redeemed = $m[1];
					preg_match('#Your balance is \$([0-9\.,]+).#', $ret, $m);
					$balance = $m[1];
					echo "Redeemed: \${$redeemed}. Balance: \${$balance}\n";
				}
			}
			return true;
		}
	}
	
	public function getPurchasedIDList()
	{
		list($s,) = explode('-', $this->store_front, 2);
		$url = 'https://se.itunes.apple.com/WebObjects/MZStoreElements.woa/wa/purchases?s=' . $s;
		$postfields = 'action=POST&mt=8&vt=lockerData&restoreMode=undefined';
		$ret = $this->http_post($url, $postfields);
		# Apps: appid:{3=>Universal, 2=>iPad, 1=>iPhone}
		list($header, $response) = explode("\r\n\r\n", $ret, 2);
		return json_decode($response, true);
	}
	
	public function getPurchasedAppInfo(array $app_ids=array())
	{
		if (empty($app_ids)) {
			# get from getPurchasedIDList();
			$full_purchased = $this->getPurchasedIDList();
			if (isset($full_purchased['Apps'])) {
				$app_ids = array_keys($full_purchased['Apps']);
			}
		}
		
		if (empty($app_ids)) {
			return null;
		}
		
		$sort = 0;	# 0 by default, 4 by names

		list($s,) = explode('-', $this->store_front, 2);
		$url = 'https://se.itunes.apple.com/WebObjects/MZStoreElements.woa/wa/purchases?s=' . $s;
		$postfields = 'action=POST&contentIds='.implode(',', $app_ids).'&pillId=0&mt=8&sortValue='.$sort.'&vt=contentData&restoreMode=undefined';
		$ret = $this->http_post($url, $postfields);
		var_dump($ret);
	}
	
}

# EOF
