<?php
class SpbcHelper
{
	
	const URL = 'https://api.cleantalk.org';
		
	static public function getApiKey($email, $website, $platform, $wpms = false, $do_check = true)
	{
		$request = array(
			'agent' => SPBC_AGENT,
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
			'agent' => SPBC_AGENT,
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
			'agent' => SPBC_AGENT,
			'method_name' => 'notice_paid_till',
			'auth_key' => $api_key
		);
		
		$result = self::sendRawRequest(self::URL, $request);
		$result = $do_check ? self::checkRequestResult($result, 'notice_paid_till') : $result;
		
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
	
	static public function securityLogs($api_key, $data, $do_check = true)
	{
		$request = array(
			'agent' => SPBC_AGENT,
			'auth_key' => $api_key,
			'method_name' => 'security_logs',
			'timestamp' => current_time('timestamp'),
			'data' => json_encode($data),
			'rows' => count($data),
		);
		
		$result = self::sendRawRequest(self::URL, $request);
		$result = $do_check ? self::checkRequestResult($result, 'security_logs') : $result;
		
		return $result;
	}
		
	static public function securityMscanLogs($api_key, $service_id, $scan_time, $scan_result, $scanned_total, $modified, $unknown, $do_check = true)
	{
		$request = array(
			'agent'              => SPBC_AGENT,
			'method_name'        => 'security_mscan_logs',
			'auth_key'           => $api_key,
			'service_id'         => $service_id,
			'started'            => $scan_time,
			'result'             => $scan_result,
			'total_core_files'   => $scanned_total,
		);
		
		if(!empty($modified)){
			$request['failed_files']      = json_encode($modified, JSON_FORCE_OBJECT);
			$request['failed_files_rows'] = count($modified);
		}
		if(!empty($unknown)){
			$request['unknown_files']      = json_encode($unknown, JSON_FORCE_OBJECT);
			$request['unknown_files_rows'] = count($unknown);
		}
		
		$result = self::sendRawRequest(self::URL, $request);
		$result = $do_check ? self::checkRequestResult($result, 'security_mscan_logs') : $result;
		
		return $result;
	}
	
	static public function securityMscanFiles($api_key, $file_path, $file, $file_md5, $do_check = true)
	{
		$request = array(
			'agent' => SPBC_AGENT,
			'method_name' => 'security_mscan_files',
			'auth_key' => $api_key,
			'path_to_sfile' => $file_path,
			'attached_sfile' => $file,
			'md5sum_sfile' => $file_md5,
		);
		
		$result = self::sendRawRequest(self::URL, $request);
		$result = $do_check ? self::checkRequestResult($result, 'security_mscan_files') : $result;
		
		return $result;
	}
	
	static public function sendRawRequest($url,$data,$isJSON=false,$timeout=3)
	{	
		
		$result = null;
		if(!$isJSON){
			$data = http_build_query($data);
			$data = str_replace("&amp;", "&", $data);
		}else{
			$data = json_encode($data);
		}
		
		$curl_exec = false;

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
			
			if($result === false)
				$curl_error = curl_error($ch);
			
			curl_close($ch);
		}else{
			$curl_error = 'CURL_NOT_INSTALLED';
		}
		
		if($curl_error){
			
			$opts = array(
				'http'=>array(
					'method'  => "POST",
					'timeout' => $timeout,
					'content' => $data,
				)
			);
			$context = stream_context_create($opts);
			$result = @file_get_contents($url, 0, $context);
		}
		
		if(!$result && $curl_error)
			return array('error' => true, 'error_string' => $curl_error);
		
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
		if(is_array($result) && isset($result['error'])){
			return array(
				'error' => true,
				'error_string' => 'CONNECTION_ERROR' . (isset($result['error_string']) ? ' '.$result['error_string'] : ''),
			);
		}
		
		// JSON decode errors
		$result = json_decode($result, true);
		if(empty($result)){
			return array(
				'error' => true,
				'error_string' => 'JSON_DECODE_ERROR'
			);
		}
		
		// Server errors
		if($result && (isset($result['error_no']) || isset($result['error_message']))){
			return array(
				'error' => true,
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
	
	/**
	 * Prepares an adds an error to the plugin's data
	 *
	 * @param string type
	 * @param mixed array || string
	 * @returns null
	 */
	static public function addError($type, $error, $set_time = true)
	{	
		global $spbc;
		
		$error_string = is_array($error)
			? $error['error_string']
			: $error;
		
		// Exceptions
		if( ($type == 'send_logs'          && $error_string == 'NO_LOGS_TO_SEND') ||
			($type == 'send_firewall_logs' && $error_string == 'NO_LOGS_TO_SEND')
		)
			return;
		
		if($set_time == true)
			$spbc->data['errors'][$type]['error_time']   = current_time('timestamp');
		$spbc->data['errors'][$type]['error_string'] = $error_string;
		$spbc->save('data');
	}
	
	/**
	 * Deletes an error from the plugin's data
	 *
	 * @param mixed (array of strings || string 'elem1 elem2...' || string 'elem') type
	 * @param delay saving
	 * @returns null
	 */
	static public function deleteError($type, $save_flag = false)
	{
		global $spbc;
		
		$before = empty($spbc->data['errors']) ? 0 : count($spbc->data['errors']);
		
		if(is_string($type))
			$type = explode(' ', $type);
		
		foreach($type as $val){
			if(isset($spbc->data['errors'][$val])){
				unset($spbc->data['errors'][$val]);
			}
		}
		
		$after = empty($spbc->data['errors']) ? 0 : count($spbc->data['errors']);
		// Save if flag is set and there are changes
		if($save_flag && $before != $after)
			$spbc->save('data');
	}
	
	/**
	 * Deletes all errors from the plugin's data
	 *
	 * @param delay saving
	 * @returns null
	 */
	static public function deleteAllErrors($save_flag = false)
	{
		global $spbc;
		
		if(isset($spbc->data['errors']))
			unset($spbc->data['errors']);
		
		if($save_flag)
			$spbc->save('data');
	}
	
	public static function get_real_ip($cdn){
		
		// REMOTE_ADDR
		$result = filter_var( $_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 );
		
		// Or HTTP_CF_CONNECTING_IP as result
		if(isset($_SERVER['HTTP_CF_CONNECTING_IP'])){
			foreach($cdn as $cidr){
				if(self::ip_mask_match($result, $cidr)){
					$result = filter_var( $_SERVER['HTTP_CF_CONNECTING_IP'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 );
					break;
				}
			}
		}
		
		return $result;
	}
	
	public static function ip_mask_match($ip, $cidr){
		$exploded = explode ('/', $cidr);
		$net = $exploded[0];
		$mask = 4294967295 << (32 - $exploded[1]);
		return (ip2long($ip) & $mask) == (ip2long($net) & $mask);
	}
}
