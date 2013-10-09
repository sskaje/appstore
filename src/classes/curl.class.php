<?php

class spCurl 
{
	protected function init_curl()
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_USERAGENT, $this->curlopt_useragent);
		if ($this->curlopt_cookiefile) {
			curl_setopt($ch, CURLOPT_COOKIEFILE, $this->curlopt_cookiefile);
		}
		if ($this->curlopt_cookiejar) {
			curl_setopt($ch, CURLOPT_COOKIEJAR, $this->curlopt_cookiejar);
		}
		
		curl_setopt($ch, CURLOPT_TIMEOUT, 60);
		curl_setopt($ch, CURLOPT_VERBOSE, 1);
		curl_setopt($ch, CURLOPT_HEADER, 1);
        # Set to HTTP/1.1 to enable keep-alive by default.
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
		
		$this->curl_setopt($ch);
		
		$this->curl = & $ch;
	}
	
	protected function curl_setopt(&$ch) 
	{
		return true;
	}
	
	protected $curl;
	protected $curlopt_useragent;
	protected $curlopt_cookiefile = '_cookie.txt';
	protected $curlopt_cookiejar = '_cookie.txt';
	
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
		return file_put_contents('_http_request.log', $msg."\n", FILE_APPEND);
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
}

# EOF