<?php

/*
 * 
 * CleanTalk Security Firewall class
 * 
 * @package Security Plugin by CleanTalk
 * @subpackage Firewall
 * @Version 2.0-wp
 * @author Cleantalk team (welcome@cleantalk.org)
 * @copyright (C) 2014 CleanTalk team (http://cleantalk.org)
 * @license GNU/GPL: http://www.gnu.org/copyleft/gpl.html
 *
 */

class SpbcFireWall extends SpbcHelper
{	
	public $ip_array = Array(); // Array with detected IPs
	public $blocked_ip = '';    // Blocked IP
	public $passed_ip = '';     // Passed IP
	public $result = null;      // Result
	
	public $tc_enabled = false; // Traffic control
	public $tc_limit = 1000;    // Traffic control limit requests
	
	function __construct($tc_enabled = false, $tc_limit = 1000){
		$this->tc_enabled = $tc_enabled;
		$this->tc_limit   = $tc_limit;
	}
	
	static public function ip_get($ip_types = array('real', 'remote_addr', 'x_forwarded_for', 'x_real_ip', 'cloud_flare')){
		
		$result = parent::ip_get($ip_types);
		
		if(isset($_GET['security_test_ip'])){
			$ip_type = self::ip__validate($_GET['security_test_ip']);
			$test_ip = $ip_type == 'v6' ? self::ip__v6_normalizе($_GET['security_test_ip']) : $_GET['security_test_ip'];
			if($ip_type)
				$result['test'] = $test_ip;
		}
		
		return $result;
	}
	
	public function check_ip(){		
	
		global $wpdb;
		
		foreach($this->ip_array as $current_ip){
			
			$ip_type = self::ip__validate($current_ip);
			
			if($ip_type && $ip_type == 'v4'){
				
				$current_ip_v4 = sprintf("%u", ip2long($current_ip));
				
				$sql = "SELECT spbc_network_1, spbc_mask_1, status, ipv6
				FROM `{$wpdb->base_prefix}spbc_firewall_data` 
				WHERE spbc_network_4 = $current_ip_v4 & spbc_mask_4
				AND ipv6 = 0;";
				
			}elseif($ip_type){
				
				$current_ip_txt = explode(':', $current_ip);
				$current_ip_1 = hexdec($current_ip_txt[0].$current_ip_txt[1]);
				$current_ip_2 = hexdec($current_ip_txt[2].$current_ip_txt[3]);
				$current_ip_3 = hexdec($current_ip_txt[4].$current_ip_txt[5]);
				$current_ip_4 = hexdec($current_ip_txt[6].$current_ip_txt[7]);
				
				$sql = "SELECT status
				FROM `{$wpdb->base_prefix}spbc_firewall_data` 
				WHERE spbc_network_1 = $current_ip_1 & spbc_mask_1
				AND   spbc_network_2 = $current_ip_2 & spbc_mask_2
				AND   spbc_network_3 = $current_ip_3 & spbc_mask_3
				AND   spbc_network_4 = $current_ip_4 & spbc_mask_4
				AND   ipv6 = 1;";
			}
			
			$result = $wpdb->get_results($sql, ARRAY_A);
			
			if(!empty($result)){
				
				$in_base = true;
				
				foreach($result as $enrty){
					if($enrty['status'] == 2)  $trusted     = true;
					if($enrty['status'] == 1)  $whitelisted = true;
					if($enrty['status'] == 0)  $deny        = true;
					if($enrty['status'] == -1) $deny_by_net = true;
					if($enrty['status'] == -2) $deny_by_dos = true;
				}
			}else{
				$in_base = false;
			}
			
			if($this->tc_enabled && empty($trusted) && empty($whitelisted)){
				$http_user_agent = !empty($_SERVER['HTTP_USER_AGENT']) 
					? addslashes(substr($_SERVER['HTTP_USER_AGENT'], 0, 300))
					: 'unknown';
				$id = md5($current_ip.$http_user_agent);
				$sql = 'SELECT allowed_entry + blocked_entry as cnt
					FROM `'.$wpdb->base_prefix."spbc_firewall_logs` 
					WHERE entry_id = '$id';";
				$tc_result = $wpdb->get_results($sql, ARRAY_A);
				if(!empty($tc_result)){
					if($tc_result[0]['cnt'] >= $this->tc_limit){
						$deny_by_dos = true;
					}
				}							
			}
			
			if(!empty($in_base) || $this->tc_enabled){
				if(!empty($trusted)){
					$this->result = 'PASS_BY_TRUSTED_NETWORK';
					$this->passed_ip = $current_ip;
				}elseif(!empty($whitelisted)){
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
				}elseif($this->tc_enabled){
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
		
		$result = self::api_method__security_firewall_data($spbc_key);
		
		if(empty($result['error'])){
			
			global $wpdb;
			
			$wpdb->query("DELETE FROM `".$wpdb->base_prefix."spbc_firewall_data`;");
			
			$row_to_write = SPBC_WRITE_LIMIT;
			$sql_write_iteration = ceil(count($result)/$row_to_write);
			
			
			for($count_result = 0, $j = 0; $j < $sql_write_iteration; $j++, $count_result += count($to_mysql)){
				
				$to_mysql = array_slice($result, $j*$row_to_write, $row_to_write, false);
				
				$query="INSERT INTO `".$wpdb->base_prefix."spbc_firewall_data` VALUES ";
				
				for($i = 0; $i < sizeof($to_mysql); $i++){
					
					// IPv4
					if(is_numeric($to_mysql[$i][0])){
						
						$ip = $to_mysql[$i][0];
						$mask = sprintf('%u', 4294967295 << (32 - $to_mysql[$i][1]));
						$status = $to_mysql[$i][3];
						$query .= "(0, 0, 0, $ip, 0, 0, 0, $mask, $status, 0)";
						
					// IPv6
					}else{
						
						$ip    = explode(':', $to_mysql[$i][0]);
						$ip_1 = hexdec($ip[0].$ip[1]);
						$ip_2 = hexdec($ip[2].$ip[3]);
						$ip_3 = hexdec($ip[4].$ip[5]);
						$ip_4 = hexdec($ip[6].$ip[7]);
						
						for($mask = $to_mysql[$i][1], $k = 1; $k < 5; $k++){
							$curr = 'mask_'.$k;
							$$curr = pow(2, 32) - pow(2, 32 - ($mask - 32 >= 0 ? 32 : $mask));
							$mask = ($mask - 32 <= 0 ? 0 : $mask - 32);
						}
						
						$status = $to_mysql[$i][3];
						
						$query .= "($ip_1, $ip_2, $ip_3, $ip_4, $mask_1, $mask_2, $mask_3, $mask_4, $status, 1)";
					}
					
					$query .=  ($i == count($to_mysql)-1 ? ";" : ", "); //If the last value
				}
				
				$wpdb->query($query);
				
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
					'page_url'        => $value['page_url'],
					'visitor_ip'      => self::ip__validate($value['ip_entry']) == 'v4' ? (int)sprintf('%u', ip2long($value['ip_entry'])) : (string)$value['ip_entry'],
					'http_user_agent' => $value['http_user_agent'],
					'request_method'  => $value['request_method'],
					'x_forwarded_for' => $value['x_forwarded_for'],
				);
				
				switch($value['status']){
					case 'PASS_BY_TRUSTED_NETWORK': $to_data['status_efw'] = 3;  break;
					case 'PASS_BY_WHITELIST':       $to_data['status_efw'] = 2;  break;
					case 'PASS':                    $to_data['status_efw'] = 1;  break;
					case 'DENY':                    $to_data['status_efw'] = 0;  break;
					case 'DENY_BY_NETWORK':         $to_data['status_efw'] = -1; break;
					case 'DENY_BY_DOS':             $to_data['status_efw'] = -2; break;
				}
				
				// Adding Blocked entries
				if(!empty($value['blocked_entry'])){
					$to_data['hits'] = (int)$value['blocked_entry'];
					$to_data['status'] = 0;
					$data[] = $to_data;
				}
				
				// Adding allowed entries if exists
				if(!empty($value['allowed_entry'])){
					$to_data['hits'] = (int)$value['allowed_entry'];
					$to_data['status'] = 1;
					$data[] = $to_data;
				}
			
			} unset($key, $value, $result, $to_data);
			
			// Sendings request
			$result = self::api_method__security_logs__sendFWData($spbc_key, $data);	
			
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
