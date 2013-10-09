<?php
require_once(__DIR__ . '/curl.class.php');
/**
 * iTunes Appstore Protocol class
 *
 * @author sskaje (sskaje [at] gmail [dot] com)
 */
class iTunes extends spCurl
{
	protected $curlopt_useragent = 'iTunes/10.6 (Windows; Microsoft Windows 7 x64 Ultimate Edition Service Pack 1 (Build 7601)) AppleWebKit/534.54.16 (sskaje)';
	
	public function __construct()
	{
		parent::__construct();
	}
	
	public $guid = '00000000.11111111.22222222.44444444.88888888.00000000.11111111';
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
		#echo $ret;

		$m = array();
		if (preg_match('#x-set-apple-store-front: ([\d\-\,]+)#', $ret, $m)) {
			$this->store_front = $m[1];
		} else {
			$this->store_front = '143441-1';
		}
		
		# <key>passwordToken</key><string>...</string>
		$m = array();
		if (!preg_match('#<key>passwordToken</key><string>([a-zA-Z0-9\+\=\/]+)</string>#', $ret, $m)) {
			die('bad pwd token');
		}
		$this->password_token = $m[1];
	
		# <key>dsid</key><integer>...</integer>
		# <key>dsPersonId</key><string>...</string>
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
#		echo $post;
		$ret = $this->http_post($url, $post, 'application/x-apple-plist');
		
		var_dump($ret);
	}
	
	

	public function redeem($redeem_code)
	{
		$redeem_code = strtoupper($redeem_code);
		echo "Code: {$redeem_code} ... ";
	
		$page_url = "https://buy.itunes.apple.com/WebObjects/MZFinance.woa/wa/redeemLandingPage";
		$ret = $this->http_get($page_url);

        $form_url = 'https://buy.itunes.apple.com/WebObjects/MZFinance.woa/wa/redeemCodeSrv';
        $post = "code={$redeem_code}&dsPersonId={$this->dsid}&response-content-type=application%2Fjson&cameraRecognizedCode=false&has4GBLimit=false";
        $result = $this->http_post($form_url, $post);

        list(, $body) = explode("\r\n\r\n", $result, 2);

        if (!($json = json_decode($body, true))) {
            if ($json['status'] == -1) {
                echo "Congrats, your code might be working, all you need to do is: ". $json['userPresentableErrorMessage'];
                return true;
            } else if ($json['status'] == 0) {
                echo "Your code has successfully been redeemed\n";
                return true;
            } else {
                echo "Something went wrong: " . $json['userPresentableErrorMessage'];
                return false;
            }
        }

        echo "Bad response";
        return false;
	}
	
	public function getPurchasedIDList($hidden=false)
	{
		list($s,) = explode('-', $this->store_front, 2);
		$url = 'https://se.itunes.apple.com/WebObjects/MZStoreElements.woa/wa/purchases?s=' . $s;
		$postfields = 'action=POST&mt=8&vt=lockerData&restoreMode=' . ($hidden ? 'true' : 'undefined');
		$ret = $this->http_post($url, $postfields);
		# Apps: appid:{3=>Universal, 2=>iPad, 1=>iPhone}
		list($header, $response) = explode("\r\n\r\n", $ret, 2);
		return json_decode($response, true);
	}
	
	public function getPurchasedAppInfo(array $app_ids=array(), $hidden=false)
	{
		if (empty($app_ids)) {
			# get from getPurchasedIDList();
			$full_purchased = $this->getPurchasedIDList($hidden);
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
		$postfields = 'action=POST&contentIds='.implode(',', $app_ids).'&pillId=0&mt=8&sortValue='.$sort.'&vt=contentData&restoreMode=' . ($hidden ? 'true' : 'undefined');
		$ret = $this->http_post($url, $postfields);
		var_dump($ret);
	}
	
}

# EOF
