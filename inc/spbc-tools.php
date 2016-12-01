<?php

//
// Do updates in SQL database after plugin update.
//
function spbc_run_update_actions ($current_version, $new_version) {
    global $wpdb;
	
   $spbc_auth_logs_table = $wpdb->prefix . SPBC_LOG_TABLE;
    	
	$current_version = explode('.', $current_version);
	$new_version = explode('.', $new_version);
		
	if(intval($current_version[0]) == 1){
		if(isset($current_version[1]) && intval($current_version[1]) < 4){
			$sql = "ALTER TABLE `$spbc_auth_logs_table` 
				CHANGE `event`
				`event` VARCHAR(32) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL;";
			$wpdb->query($sql);
		}
		if(isset($current_version[1]) && intval($current_version[1]) < 5){
			$sql = "ALTER TABLE `$spbc_auth_logs_table`
				ADD COLUMN `page` VARCHAR(500) NULL AFTER `event`,
				ADD COLUMN `page_time` VARCHAR(10) NULL AFTER `page`;";
			$wpdb->query($sql);
		}
		if(isset($current_version[1]) && intval($current_version[1]) == 5){
			if(!isset($current_version[2])){ // == 0
				$sql = "ALTER TABLE `$spbc_auth_logs_table`
					ADD COLUMN `page` VARCHAR(500) NULL AFTER `event`,
					ADD COLUMN `page_time` VARCHAR(10) NULL AFTER `page`;";
				$wpdb->query($sql);
			}
		}
	}

    return null;
}

//
// Returns country part for emails
//
function spbc_report_country_part($ips_c = null, $ip) {
    
    if (isset($ips_c[$ip]['country_code'])) {
		
        $country_code = strtolower($ips_c[$ip]['country_code']);
		$country_name = (isset($ips_c[$ip]['country_name']) ? $ips_c[$ip]['country_name'] : '-');
		
        $country_part = sprintf('<img src="https://cleantalk.org/images/flags/%s.png" alt="%s" />&nbsp;%s',
            $country_code,
            $country_code,
            $country_name
        );
    }else{
		$country_part = '-';
	}

    return $country_part;
}

//
//Getting the api key
//
function spbc_getAutoKey($email, $host, $platform,$product_name){
	$request=Array();
	$request['method_name'] = 'get_api_key'; 
	$request['email'] = $email;
	$request['website'] = $host;
	$request['platform'] = $platform;
	$request['product_name'] = $product_name;
	$url = SPBC_API_URL;
	
	$result = spbc_sendRawRequest($url,$request);
	return $result;
}

//
//Function to send logs
//
function spbc_send_logs($api_key = null){
	global $wpdb;
	
	if($api_key == null){
		$spbc_settings = get_option( SPBC_SETTINGS );
		$api_key = $spbc_settings['spbc_key'];
	}
	
	$rows = $wpdb->get_results("SELECT id, datetime, user_login, page, page_time, event, auth_ip 
		FROM ".$wpdb->prefix . SPBC_LOG_TABLE."
		ORDER BY datetime DESC;");
	$rows_count = count($rows);
	
    if ($rows_count){
		
		$request_data = array(
			'method_name' => 'security_logs',
			'auth_key' => $api_key,
			'rows' => $rows_count,
			'agent' => SPBC_AGENT,
			'data' => array()
		);
				
        foreach ($rows as $record) {
			$request_data['data'][] = array(
				'log_id' => 		strval($record->id),
				'datetime' => 	strval($record->datetime),
				'user_log' => strval($record->user_login),
				'event' => 		strval($record->event),
				'auth_ip' => 	intval($record->auth_ip),
				'page_url' => 		strval($record->page),
				'event_runtime' => 	strval($record->page_time),
			);
		}
		$request_data['data'] = json_encode($request_data['data']);
		
		$result = spbc_sendRawRequest(SPBC_API_URL, $request_data);
		
		if($result){
			$result = json_decode($result, true);
			if(isset($result['error_message']) || isset($result['error_no'])){
				return date('Y-m-d H:i:s')." - ". sprintf(__('Error while sending logs: Error #%d Comment: %s. Api Key is "%s".', 'spbc'), $result['error_no'], $result['error_message'], $api_key);
			}else{
				//Clear local table if it's ok.
				if($result['data']['rows'] == $rows_count){
					$wpdb->query('TRUNCATE TABLE '.$wpdb->prefix . SPBC_LOG_TABLE);
				}else{
					return date('Y-m-d H:i:s')." - ". sprintf(__('Logs sending error: Sent: %d. Confirmed receiving of %d rows.', 'spbc'), $rows_count, intval($result['data']['rows']));
				}
			}
		}else{
			return date('Y-m-d H:i:s')." - ". __('Cleantalk spbc_sendRawRequest == false. Possible reasons: Bad connection or cloud server error(less possible).', 'spbc');
		}
	}
	
	return false;
}

//
// The functions sends daily reports about attempts to login. 
//
function spbc_send_daily_report ($skip_data_rotation = false) {
    global $wpdb;
	
	$spbc_data = get_option( SPBC_DATA );
    
	//If key is not ok, send daily report!
	if(!isset($spbc_data['key_is_ok']) || $spbc_data['key_is_ok'] == false){
	
		include_once("/../templates/spbc_send_daily_report.php");

		// Hours
		$report_interval = 24 * 7;

		$admin_email = get_option('admin_email');
		if (!$admin_email) {
			error_log(sprintf('%s: can\'t send the Daily report because of empty Admin email. File: %s, line %d.',
				SPBC_NAME,
				__FILE__,
				__LINE__
			));
			return false;
		}

		$spbc_auth_logs_table = $wpdb->prefix . SPBC_LOG_TABLE;
		$sql = sprintf('select id,datetime,user_login,event,auth_ip,page,page_time from %s where datetime between now() - interval %d hour and now();',
			$spbc_auth_logs_table,
			$report_interval
		);
		$rows = $wpdb->get_results($sql);
		foreach ($rows as $k => $v) {
			if (isset($v->datetime))
				$v->datetime_ts = strtotime($v->datetime);
			$rows[$k] = $v;
		}
		usort($rows, "spbc_usort_desc");

		$record_datetime = time();
		$events = array();
		$auth_failed_events = array();
		$invalid_username_events = array();
		$auth_failed_count = 0;
		$invalid_username_count = 0;
		$ips_data = '';
		foreach ($rows as $record) {
			if (strtotime($record->datetime) > $record_datetime) {
				$record_datetime = strtotime($record->datetime);
			}
			$events[$record->event][$record->user_login][] = array(
				'datetime' => $record->datetime,
				'auth_ip' => long2ip($record->auth_ip),
				'user_login' => $record->user_login,
				'page' => ($record->page ? $record->page : '-'),
				'page_time' => ($record->page_time ? $record->page_time : 'Unknown')
			);
			
			switch ($record->event) {
				case 'auth_failed':
					$auth_failed_events[$record->user_login][$record->auth_ip] = array(
						'attempts' => isset($auth_failed_events[$record->user_login][$record->auth_ip]['attempts']) ? $auth_failed_events[$record->user_login][$record->auth_ip]['attempts'] + 1 : 1, 
						'auth_ip' => long2ip($record->auth_ip),
						'user_login' => $record->user_login
					);
					$auth_failed_count++;
					break;
				case 'invalid_username':
					$invalid_username_events[$record->user_login][$record->auth_ip] = array(
						'attempts' => isset($invalid_username_events[$record->user_login][$record->auth_ip]['attempts']) ? $invalid_username_events[$record->user_login][$record->auth_ip]['attempts'] + 1 : 1, 
						'auth_ip' => long2ip($record->auth_ip),
						'user_login' => $record->user_login
					);
					$invalid_username_count++;
					break;
			}
			if ($ips_data != '') {
				$ips_data .= ',';
			}
			$ips_data .= long2ip($record->auth_ip);
		}

		$ips_c = spbc_get_countries_by_ips($ips_data);

		$event_part = '';
		$auth_failed_part = sprintf("<p style=\"color: #666;\">%s</p>",
			_("0 brute force attacks have been made for past day.")
		);
		if ($auth_failed_count) {
			foreach ($auth_failed_events as $e) {
				$ip_part = '';
				foreach ($e as $ip) {
					$country_part = spbc_report_country_part($ips_c, $ip['auth_ip']);
					$ip_part .= sprintf("<a href=\"https://cleantalk.org/blacklists/%s\">%s</a>, #%d, %s<br />",
						$ip['auth_ip'],
						$ip['auth_ip'],
						$ip['attempts'],
						$country_part
					);
				}
				$event_part .= sprintf($spbc_tpl['event_part_tpl'],
					$ip['user_login'],
					$ip_part
				);
			} 
			$auth_failed_part = sprintf($spbc_tpl['auth_failed_part'],
				$event_part
			);
		} 
		
		$invalid_username_part= sprintf("<p style=\"color: #666;\">%s</p>",
			_('0 brute force attacks have been made for past day.')
		);
		
		if ($invalid_username_count) {
			foreach ($invalid_username_events as $e) {
				$ip_part = '';
				foreach ($e as $ip) {
					$country_part = spbc_report_country_part($ips_c, $ip['auth_ip']);
					$ip_part .= sprintf("<a href=\"https://cleantalk.org/blacklists/%s\">%s</a>, #%d, %s<br />",
						$ip['auth_ip'],
						$ip['auth_ip'],
						$ip['attempts'],
						$country_part
					);
				}
				$event_part .= sprintf($spbc_tpl['event_part_tpl'],
					$ip['user_login'],
					$ip_part
				);
			} 
			$invalid_username_part = sprintf($spbc_tpl['auth_failed_part'],
				$event_part
			);
		} 
	   
		$logins_part = sprintf("<p style=\"color: #666;\">%s</p>",
			_('0 users have been logged in for past day.')
		);
		if (isset($events['login']) && count($events['login'])) {
			$event_part = '';
			foreach ($events['login'] as $user_login => $e) {
				$l_part = '';
				foreach ($e as $e2) {
					$country_part = spbc_report_country_part($ips_c, $e2['auth_ip']);
					$l_part .= sprintf("%s, <a href=\"https://cleantalk.org/blacklists/%s\">%s</a>, %s<br />",
						date("M d Y H:i:s", strtotime($e2['datetime'])),
						$e2['auth_ip'],
						$e2['auth_ip'],
						$country_part
					);
				}
				$event_part .= sprintf($event_part_tpl,
					$user_login,
					$l_part
				);
			}
			$logins_part = sprintf($spbc_tpl['logins_part_tpl'],
				$event_part
			);
		}
		
		$title_main_part = _('Daily security report');
		$subject = sprintf('%s %s',
			parse_url(get_option('siteurl'),PHP_URL_HOST), 
			$title_main_part
		);
		
		$message_anounce = sprintf(_('%s brute force attacks or failed logins, %d successful logins.'),
			number_format($auth_failed_count + $invalid_username_count, 0, ',', ' '),
			isset($events['login']) ? count($events['login']) : 0
		);


		$message = sprintf($spbc_tpl['message_tpl'],
			$spbc_tpl['message_style'],
			$title_main_part,
			$message_anounce,
			$auth_failed_part,
			$invalid_username_part,
			$logins_part,
			SPBC_NAME
		);


		$headers = array('Content-Type: text/html; charset=UTF-8');
		wp_mail(
			$admin_email,
			$subject,
			$message,
			$headers
		);
		
		if (!$skip_data_rotation) {
			$sql = sprintf("delete from %s where datetime <= '%s';",
			   $spbc_auth_logs_table,
			   date("Y-m-d H:i:s", $record_datetime)
			);
			$wpdb->query($sql);
		};
	}
	
	return null;
}

//
// Sends a HTTP request.
//
function spbc_sendRawRequest($url,$data,$isJSON=false,$timeout=3)
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
		
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		
		$result = @curl_exec($ch);
		
		if($result!==false)
			$curl_exec=true;
		
		@curl_close($ch);
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

?>
