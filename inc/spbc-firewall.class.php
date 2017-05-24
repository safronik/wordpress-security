<?php
class SecurityFireWall
{
	public $ip = 0;
	public $ip_str = '';
	public $ip_array = Array();
	public $ip_str_array = Array();
	public $blocked_ip = '';
	public $passed_ip = '';
	public $result = null;
	
	public function get_real_ip(){
		
		$result=Array();
		if ( function_exists( 'apache_request_headers' ) )
			$headers = apache_request_headers();
		else
			$headers = $_SERVER;

		$the_ip = filter_var( $_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 );
		$result[] = $the_ip;
		$this->ip_str_array[]=$the_ip;
		$this->ip_array[]=sprintf("%u", ip2long($the_ip));
		
		if ( array_key_exists( 'X-Forwarded-For', $headers ) ){
			$the_ip = explode(",", trim($headers['X-Forwarded-For']));
			$the_ip = trim($the_ip[0]);
			$result[] = $the_ip;
			$this->ip_str_array[]=$the_ip;
			$this->ip_array[]=sprintf("%u", ip2long($the_ip));
		}
		
		if ( array_key_exists( 'HTTP_X_FORWARDED_FOR', $headers )){
			$the_ip = explode(",", trim($headers['HTTP_X_FORWARDED_FOR']));
			$the_ip = trim($the_ip[0]);
			$result[] = $the_ip;
			$this->ip_str_array[]=$the_ip;
			$this->ip_array[]=sprintf("%u", ip2long($the_ip));
		}
		
		if(isset($_GET['security_test_ip'])){
			$the_ip = $_GET['security_test_ip'];
			$result[] = $the_ip;
			$this->ip_str_array[]=$the_ip;
			$this->ip_array[]=sprintf("%u", ip2long($the_ip));
		}
		
		return $result;
	}
	
	public function check_ip(){		
	
		global $wpdb;
		
		for($i=0;$i<sizeof($this->ip_array);$i++){
			$sql = "SELECT *  
				FROM `".$wpdb->base_prefix."spbc_firewall_data` 
				WHERE spbc_network = ".$this->ip_array[$i]." & spbc_mask;";
						
			$result = $wpdb->get_results($sql, ARRAY_A);
						
			if(!empty($result)){
				
				$blacklisted = true;
				
				if(count($result)){
					foreach($result as $enrty){
						if(!empty($enrty['status']))
							$whitelisted = true;
					} unset($value);
				}
			}
			
			if(!empty($blacklisted)){
				
				if(!empty($whitelisted)){
					$this->result = 'whitelisted';
					$this->passed_ip = $this->ip_str_array[$i];
				}else{
					$this->result = 'blacklisted';
					$this->blocked_ip=$this->ip_str_array[$i];
				}
			}
		}
	}
	
	public function spbc_die(){
		
		global $ct_options, $ct_data;
		$spbc_die_page=file_get_contents(dirname(__FILE__)."/spbc_die_page.html");
		$spbc_die_page=str_replace("{REMOTE_ADDRESS}",$this->blocked_ip,$spbc_die_page);
		$spbc_die_page=str_replace("{REQUEST_URI}",$_SERVER['REQUEST_URI'],$spbc_die_page);
		$spbc_die_page=str_replace("{SFW_COOKIE}",md5($this->blocked_ip.$ct_options['apikey']),$spbc_die_page);
		
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
			
		global $wpdb;
		
		if(!function_exists('spbc_sendRawRequest'))
			require_once(plugin_dir_path(__FILE__) . 'spbc-tools.php');
		
		$data = Array('auth_key' => $spbc_key, 'method_name' => 'security_firewall_data');	
		$result = spbc_sendRawRequest('https://api.cleantalk.org/2.1', $data, false, 20);
		
		$result=json_decode($result, true);
		
		if(isset($result['data']) && count($result['data'])){

			$result=$result['data'];
			
			$wpdb->query("TRUNCATE TABLE `".$wpdb->base_prefix."spbc_firewall_data`;");
			
			$row_to_write = 100000;
			$sql_write_iteration = ceil(count($result)/$row_to_write);
					
			$count_result = 0;
			for($j=0; $j < $sql_write_iteration; $j++){
				
				$to_mysql = array_slice($result, $j*$row_to_write, $row_to_write, false);
								
				$query="INSERT INTO `".$wpdb->base_prefix."spbc_firewall_data` VALUES ";
				
				for($i=0; $i<sizeof($to_mysql); $i++){
					
					$ip = $to_mysql[$i][0];
					$mask = pow(2, 32) - pow(2, 32 - $to_mysql[$i][1]);
					$status = $to_mysql[$i][2];
					
					$query.="(".$ip.",".$mask.",".$status.")";
					$query .=  ($i == count($to_mysql)-1 ? ";" : ", "); //If the last value
				}
								
				$wpdb->query($query);
				
				$count_result += count($to_mysql);
			}
			return $count_result;
		}
	}
	
	//Add entries to SFW log
	static public function firewall_update_logs($ip, $result){
		
		if($ip === NULL || $result === NULL){
			error_log('SPBC Firewall log update failed');
			return;
		}
				
		global $wpdb;
		
		$blocked_new = ($result == 'blocked' ? '1' : '0');
		$blocked = ($result == 'blocked' ? ' + 1' : '');
		
		$allowed_new = ($result == 'passed' ? '1' : '0');
		$allowed = ($result == 'passed' ? ' + 1' : '');
		
		// Parameters
		$time = time(); //(int)current_time('timestamp');
		$page_url        = addslashes((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']);
		$page_url        = substr($page_url, 0 , 4096);
		$http_user_agent = addslashes(substr($_SERVER['HTTP_USER_AGENT'], 0, 300));
		$request_method  = $_SERVER['REQUEST_METHOD'];
		$x_forwarded_for = !empty($_SERVER['HTTP_X_FORWARDED_FOR']) 
			? $_SERVER['HTTP_X_FORWARDED_FOR']
			: !empty($_SERVER['X-Forwarded-For']) 
				? $_SERVER['X-Forwarded-For'] 
				: 'null';
		$x_forwarded_for = addslashes(substr($x_forwarded_for, 0 , 15));
		
		$query = "INSERT INTO `".$wpdb->base_prefix."spbc_firewall_logs`
		SET 
			`ip_entry` = '$ip',
			`allowed_entry` = {$allowed_new},
			`blocked_entry` = {$blocked_new},
			`page_url` = '$page_url',
			`http_user_agent` = '$http_user_agent',
			`request_method` = '$request_method',
			`x_forwarded_for` ='$x_forwarded_for',
			`entry_timestamp` = '{$time}'
		ON DUPLICATE KEY UPDATE 
			`allowed_entry` = `allowed_entry`{$allowed},
			`blocked_entry` = `blocked_entry`{$blocked},
			`entry_timestamp` = '{$time}'";
			
		$result = $wpdb->query($query);
	}
	
	//*Send and wipe SFW log
	public static function send_logs($spbc_key){
		
		global $wpdb;
		
		//Getting logs
		$result = $wpdb->get_results("SELECT * FROM `".$wpdb->base_prefix."spbc_firewall_logs`", ARRAY_A);
				
		if(count($result)){
			//Compile logs
			$data = array();
			
			foreach($result as $key => $value){
				
				//Compile log
				$to_data = array(
					'datetime' => date('Y-m-d H:i:s', $value['entry_timestamp']),
					'visitor_ip' => ip2long(trim($value['ip_entry'])),
					'page_url' => $value['page_url'],
					'http_user_agent' => $value['http_user_agent'],
					'request_method' => $value['request_method'],
					'x_forwarded_for' => $value['x_forwarded_for']
				);
				
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
						
			//Final compile
			$request_data = array (
				'data_fw' => json_encode($data),
				'rows_fw' => count($data),
				'timestamp' => time()
			);
						
			//Sendings request
			if(!function_exists('spbc_sendRawRequest'))
				require_once(plugin_dir_path(__FILE__) . 'spbc-tools.php');
			
			$result = spbc_sendRawRequest("https://api.cleantalk.org/?method_name=security_logs&auth_key={$spbc_key}", $request_data, false);
			$result = json_decode($result);
			// Checking answer and truncate table
			if(isset($result->data) && isset($result->data->rows))
				if($result->data->rows == count($data)){
					$wpdb->query("TRUNCATE TABLE `".$wpdb->base_prefix."spbc_firewall_logs`");
					return count($data);
				}
				
		}else		
			return false;
	}
}
