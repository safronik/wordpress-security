<?php
class SpbcFireWall
{
	
	const URL = 'https://api.cleantalk.org';
	
	public $ip = 0;
	public $ip_str = '';
	public $ip_array = Array();
	public $blocked_ip = '';
	public $passed_ip = '';
	public $result = null;
	
	public function get_real_ip($cdn){
		
		$result['remote_addr'] = filter_var( $_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 );
		$this->ip_array[] = sprintf("%u", ip2long($result['remote_addr']));
		
		if(isset($_SERVER['HTTP_CF_CONNECTING_IP'])){
			foreach($cdn as $cidr){
				if($this->ip_mask_match($result['remote_addr'], $cidr)){
					$result['cf_connecting_ip'] = filter_var( $_SERVER['HTTP_CF_CONNECTING_IP'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 );
					$this->ip_array[] = sprintf("%u", ip2long($result['cf_connecting_ip']));
					unset($result['remote_addr']);
					break;
				}
			}
		}
		
		if(isset($_GET['security_test_ip'])){
			$result['test'] = $_GET['security_test_ip'];
			$this->ip_array[]=sprintf("%u", ip2long($result['test']));
		}
		
		return $result;
	}
	
	public function ip_mask_match($ip, $cidr){
		$exploded = explode ('/', $cidr);
		$net = $exploded[0];
		$mask = 4294967295 << (32 - $exploded[1]);
		return (ip2long($ip) & $mask) == (ip2long($net) & $mask);
	}
	
	public function check_ip($traffic_control = false, $traffic_control_limit = 1000){		
	
		global $wpdb;
		
		foreach($this->ip_array as $key => $current_ip_long){
			
			$current_ip = long2ip($current_ip_long);
			
			$sql = "SELECT *  
				FROM `".$wpdb->base_prefix."spbc_firewall_data` 
				WHERE spbc_network = ".$current_ip_long." & spbc_mask;";
						
			$result = $wpdb->get_results($sql, ARRAY_A);
			
			if(!empty($result)){
				
				$in_base = true;
				
				foreach($result as $enrty){
					if($enrty['status'] == 1)  $whitelisted = true;
					if($enrty['status'] == 0)  $deny        = true;
					if($enrty['status'] == -1) $deny_by_net = true;
					if($enrty['status'] == -2) $deny_by_dos = true;
				}
			}else{
				$in_base = false;
			}
			
			if($traffic_control){
				$http_user_agent = !empty($_SERVER['HTTP_USER_AGENT']) 
					? addslashes(substr($_SERVER['HTTP_USER_AGENT'], 0, 300))
					: 'unknown';
				$id = md5($current_ip.$http_user_agent);
				$sql = 'SELECT allowed_entry + blocked_entry as cnt
					FROM `'.$wpdb->base_prefix."spbc_firewall_logs` 
					WHERE entry_id = '$id';";
				$tc_result = $wpdb->get_results($sql, ARRAY_A);
				if(!empty($tc_result)){
					if($tc_result[0]['cnt'] >= $traffic_control_limit){
						$deny_by_dos = true;
					}
				}							
			}
			
			if(!empty($in_base) || $traffic_control){
				if(!empty($whitelisted)){
					$this->result = 'PASS_BY_WHITELIST';
					$this->passed_ip = $current_ip;
				}elseif(!empty($deny_by_dos)){
					$this->result = 'DENY_BY_DOS';
					$this->blocked_ip=$current_ip;
				}elseif(!empty($deny_by_net)){
					$this->result = 'DENY_BY_NETWORK';
					$this->blocked_ip=$current_ip;
				}elseif(!empty($deny)){
					$this->result = 'DENY';
					$this->blocked_ip=$current_ip;		
				}elseif($traffic_control){
					$this->result = 'PASS';
					$this->passed_ip = $current_ip;
				}
			}
		}		
	}
	
	public function spbc_die($service_id){
		
		$spbc_die_page = file_get_contents(SPBC_PLUGIN_DIR . 'inc/spbc_die_page.html');
		
		$spbc_die_page = str_replace( "{REMOTE_ADDRESS}", $this->blocked_ip,     $spbc_die_page );
		$spbc_die_page = str_replace( "{SERVICE_ID}",     $service_id,           $spbc_die_page );
		$spbc_die_page = str_replace( "{HOST}",           $_SERVER['HTTP_HOST'], $spbc_die_page );
		
		if(headers_sent() === false){
			header("Cache-Control: no-store, no-cache, must-revalidate");
			header("Pragma: no-cache");
			header("Last-Modified: " . gmdate("D, d M Y H:i:s") . "GMT");
			header("Expires: 0");
			header("HTTP/1.0 403 Forbidden");
			$spbc_die_page = str_replace("{GENERATED}", "", $spbc_die_page);
		}else{
			$spbc_die_page = str_replace("{GENERATED}", "<h2 class='second'>The page was generated at&nbsp;".date("D, d M Y H:i:s")."</h2>",$spbc_die_page);
		}
		wp_die( $spbc_die_page, "Blacklisted", Array('response'=>403) );
	}
	
	static public function firewall_update($spbc_key){
		
		$result = self::securityFirewallData($spbc_key);
		
		if(empty($result['error'])){
			
			global $wpdb;
			
			$wpdb->query("DELETE FROM `".$wpdb->base_prefix."spbc_firewall_data`;");
			
			$row_to_write = SPBC_WRITE_LIMIT;
			$sql_write_iteration = ceil(count($result)/$row_to_write);
					
			$count_result = 0;
			for($j=0; $j < $sql_write_iteration; $j++){
				
				$to_mysql = array_slice($result, $j*$row_to_write, $row_to_write, false);
								
				$query="INSERT INTO `".$wpdb->base_prefix."spbc_firewall_data` VALUES ";
				
				for($i=0; $i<sizeof($to_mysql); $i++){
					
					$ip = $to_mysql[$i][0];
					$mask = pow(2, 32) - pow(2, 32 - $to_mysql[$i][1]);
					$status = $to_mysql[$i][3]; // New status 
					
					$query .= '('.$ip.','.$mask.','.$status.')';
					$query .=  ($i == count($to_mysql)-1 ? ";" : ", "); //If the last value
				}
								
				$wpdb->query($query);
				
				$count_result += count($to_mysql);
			}
			return $count_result;
		}else{
			return $result;
		}
	}
	
	//Add entries to SFW log
	static public function firewall_update_logs($ip, $result, $status){
		
		if($ip === NULL || $result === NULL){
			error_log('SPBC Firewall log update failed');
			return;
		}
				
		global $wpdb;
		
		$allowed = ($result  ? '1' : '0');
		$blocked = (!$result ? '1' : '0');
		
		// Parameters
		$time            = time();
		$page_url        = addslashes((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']);
		$page_url        = substr($page_url, 0 , 4096);
		$http_user_agent = !empty($_SERVER['HTTP_USER_AGENT']) 
			? addslashes(substr($_SERVER['HTTP_USER_AGENT'], 0, 300))
			: 'unknown';
		$request_method  = $_SERVER['REQUEST_METHOD'];
		$x_forwarded_for = !empty($_SERVER['HTTP_X_FORWARDED_FOR']) 
			? $_SERVER['HTTP_X_FORWARDED_FOR']
			: 'NULL';
		$x_forwarded_for = addslashes(substr($x_forwarded_for, 0 , 15));
		$id              = md5($ip.$http_user_agent);
		
		$query = "INSERT INTO `".$wpdb->base_prefix."spbc_firewall_logs`
			(`entry_id`, `ip_entry`, `entry_timestamp`, `allowed_entry`, `blocked_entry`, `status`, `page_url`, `http_user_agent`, `request_method`, `x_forwarded_for`) 
			VALUES
				('$id', '$ip', $time, $allowed, $blocked, '$status', '$page_url', '$http_user_agent', '$request_method', '$x_forwarded_for')
			ON DUPLICATE KEY UPDATE 
				ip_entry = ip_entry,
				entry_timestamp = $time,
				allowed_entry = allowed_entry + $allowed,
				blocked_entry = blocked_entry + $blocked,
				status = '$status',
				page_url = '$page_url',
				http_user_agent = http_user_agent,
				request_method = '$request_method',
				x_forwarded_for = '$x_forwarded_for'";
			
		$result = $wpdb->query($query);
	}
	
	//*Send and wipe SFW log
	public static function send_logs($spbc_key){
		
		global $wpdb;
		
		//Getting logs
		$result = $wpdb->get_results("SELECT * FROM `".$wpdb->base_prefix."spbc_firewall_logs` LIMIT ".SPBC_SELECT_LIMIT, ARRAY_A);
		
		if(count($result)){
			//Compile logs
			$data = array();
			
			foreach($result as $key => $value){
				
				//Compile log
				$to_data = array(
					'datetime'        => date('Y-m-d H:i:s', $value['entry_timestamp']),
					'visitor_ip'      => sprintf('%u', ip2long(trim($value['ip_entry']))),
					'page_url'        => $value['page_url'],
					'http_user_agent' => $value['http_user_agent'],
					'request_method'  => $value['request_method'],
					'x_forwarded_for' => $value['x_forwarded_for']
				);
				
				if(strpos($value['status'], 'PASS') !== false) $to_data['status_efw'] = 1;
				if($value['status']      == 'DENY')            $to_data['status_efw'] = 0;
				if($value['status']      == 'DENY_BY_NETWORK') $to_data['status_efw'] = -1;
				if($value['status']      == 'DENY_BY_DOS')     $to_data['status_efw'] = -2;
				
				// Adding Blocked entries
				if(!empty($value['blocked_entry'])){
					$to_data['hits'] = $value['blocked_entry'];
					$to_data['status'] = 0;
					$data[] = $to_data;
				}
				
				// Adding allowed entries if exists
				if(!empty($value['allowed_entry'])){
					$to_data['hits'] = $value['allowed_entry'];
					$to_data['status'] = 1;
					$data[] = $to_data;
				}
			
			} unset($key, $value, $result, $to_data);
			
			// Sendings request
			$result = self::securityLogs($spbc_key, $data);	
			
			// Checking answer and deleting all lines from the table
			if(empty($result['error'])){
				if($result['rows'] == count($data)){
					$wpdb->query("DELETE FROM `".$wpdb->base_prefix."spbc_firewall_logs`");
					return count($data);
				}
			}else{
				return $result;
			}
		}else{
			return array(
				'error' => true,
				'error_string' => 'NO_LOGS_TO_SEND'
			);
		}
	}
	
	static public function securityLogs($api_key, $data, $do_check = true){
		
		$request = array(
			'agent' => SPBC_AGENT,
			'auth_key' => $api_key,
			'method_name' => 'security_logs',
			'timestamp' => current_time('timestamp'),
			'data_fw' => json_encode($data),
			'rows_fw' => count($data),
		);
		
		$result = self::sendRawRequest(self::URL, $request);
		$result = $do_check ? self::checkRequestResult($result, 'security_logs') : $result;
		
		return $result;
	}
	
	static public function securityFirewallData($api_key, $do_check = true){
				
		$request = array(
			'agent' => SPBC_AGENT,
			'auth_key' => $api_key,
			'method_name' => 'security_firewall_data',
		);
		
		$result = self::sendRawRequest(self::URL, $request, false, 20);
		$result = $do_check ? self::checkRequestResult($result, 'security_firewall_data') : $result;
		
		return $result;
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
				'error_string' => 'CONNECTION_ERROR'
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
	
	static function unpackCSV($data, $decoded = array()){
		$count = strlen($data);
		for($offset = 0, $field = 0, $entry = 0, $mask = ''; $offset < $count; $offset++){
			if($data[$offset] === ','){
				$field++;
				if($field == 1){
					if($data[$offset+1]){ $mask .=  $data[$offset+1]; $mask_len = 1; }
					if($data[$offset+2]){ $mask .=  $data[$offset+2]; $mask_len = 2; }
					$mask = pow(2, 32) - pow(2, 32 - (int)$mask);
					$data = substr_replace($data, $mask, $offset+1, $mask_len);
					$mask = '';
				}
			}
			if($data[$offset] == "\n"){
				$field = 0;
				$entry++;
			}
		}
		return $data;
	}
}
