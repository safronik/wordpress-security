<?php
class SpbcHelper
{
	
	const URL = 'https://api.cleantalk.org';
		
	static public function getApiKey($email, $website, $platform, $wpms = false, $do_check = true)
	{
		$request = array(
			'method_name' => 'get_api_key',
			'email' => $email,
			'website' => $website,
			'platform' => $platform,
			'product_name' => 'security',
			'wpms_setup' => $wpms,
		);
		
		$result = self::sendRawRequest(self::URL, $request);
		$result = $do_check ? self::checkRequestResult($result, 'get_api_key') : $result;
		
		return $result;
	}
	
	static public function noticeValidateKey($api_key, $path_to_cms, $do_check = true)
	{
		$request = array(
			'method_name' => 'notice_validate_key',
			'auth_key' => $api_key,
			'path_to_cms' => $path_to_cms	
		);
		
		$result = self::sendRawRequest(self::URL, $request);
		$result = $do_check ? self::checkRequestResult($result, 'notice_validate_key') : $result;
		
		return $result;
	}
	
	static public function noticePaidTill($api_key, $do_check = true)
	{
		$request = array(
			'method_name' => 'notice_paid_till',
			'auth_key' => $api_key
		);
		
		$result = self::sendRawRequest(self::URL, $request);
		$result = $do_check ? self::checkRequestResult($result, 'notice_paid_till') : $result;
		
		return $result;
	}
	
	static public function securityLogs($api_key, $rows_count, $spbc_agent, $data, $do_check = true)
	{
		$request = array(
			'method_name' => 'security_logs',
			'auth_key' => $api_key,
			'rows' => $rows_count,
			'agent' => $spbc_agent,
			'data' => json_encode($data)
		);
		
		$result = self::sendRawRequest(self::URL, $request);
		$result = $do_check ? self::checkRequestResult($result, 'security_logs') : $result;
		
		return $result;
	}
	
	static public function ipInfo($data, $do_check = true)
	{
		$request = array(
			'method_name' => 'ip_info',
			'data' => $data
		);
		
		$result = self::sendRawRequest(self::URL, $request);
		$result = $do_check ? self::checkRequestResult($result, 'ip_info') : $result;
		
		return $result;
	}
	
	/**
	 * Function checks server response
	 *
	 * @param string result
	 * @param string request_method
	 * @return mixed (array || array('error' => true))
	 */
	static public function checkRequestResult($result, $method_name = null)
	{		
		// Errors handling
		
		// Bad connection
		if(empty($result)){
			return array(
				'error' => true,
				'error_time' => time(),
				'error_string' => 'CONNECTION_ERROR'
			);
		}
		
		// JSON decode errors
		$result = json_decode($result, true);
		if(empty($result)){
			return array(
				'error' => true,
				'error_time' => time(),
				'error_string' => 'JSON_DECODE_ERROR'
			);
		}
		
		// Server errors
		if($result && (isset($result['error_no']) || isset($result['error_message']))){
			return array(
				'error' => true,
				'error_time' => time(),
				'error_string' => "SERVER_ERROR NO: {$result['error_no']} MSG: {$result['error_message']}",
				'error_no' => $result['error_no'],
				'error_message' => $result['error_message']
			);
		}
		
		// Pathces for different methods
		
		// mehod_name = notice_validate_key
		if($method_name == 'notice_validate_key' && isset($result['valid'])){
			return $result;
		}
		
		// Other methods
		if(isset($result['data']) && is_array($result['data'])){
			return $result['data'];
		}
	}
	
	static public function sendRawRequest($url,$data,$isJSON=false,$timeout=3)
	{
		
		$result=null;
		if(!$isJSON){
			$data=http_build_query($data);
			$data=str_replace("&amp;", "&", $data);
		}else{
			$data= json_encode($data);
		}
		
		$curl_exec=false;

		if (function_exists('curl_init') && function_exists('json_decode')){
		
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
			
			// receive server response ...
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			// resolve 'Expect: 100-continue' issue
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:'));
			
			$result = curl_exec($ch);
			
			if($result!==false)
				$curl_exec=true;
			
			curl_close($ch);
		}
		if(!$curl_exec){
			
			$opts = array(
				'http'=>array(
					'method' => "POST",
					'timeout'=> $timeout,
					'content' => $data
				)
			);
			$context = stream_context_create($opts);
			$result = @file_get_contents($url, 0, $context);
		}
		
		return $result;
	}
}
