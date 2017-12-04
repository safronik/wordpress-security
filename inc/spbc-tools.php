<?php

// Returns country part for emails
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

//Function to send logs
function spbc_send_logs($api_key = null){
	
	global $spbc, $wpdb;
		
	if($api_key == null){
		if(SPBC_WPMS && !is_main_site() && !$spbc->allow_custom_key)
			$api_key = $spbc->network_settings['spbc_key'];
		else
			$api_key = $spbc->settings['spbc_key'];
	}
			
	$rows = $wpdb->get_results("SELECT id, datetime, user_login, page, page_time, event, auth_ip, role 
		FROM ". SPBC_DB_PREFIX . SPBC_LOG_TABLE
		.(SPBC_WPMS ? " WHERE blog_id = ".get_current_blog_id() : '')
		." ORDER BY datetime DESC"
		." LIMIT ".SPBC_SELECT_LIMIT.";");
		
	$rows_count = count($rows);
		
    if ($rows_count){
		
        foreach ($rows as $record) {
			$data[] = array(
				'log_id' => 		strval($record->id),
				'datetime' => 	    strval($record->datetime),
				'user_log' =>       strval($record->user_login),
				'event' => 		    strval($record->event),
				'auth_ip' => 	    intval($record->auth_ip),
				'page_url' => 		strval($record->page),
				'event_runtime' => 	strval($record->page_time),
				'role' => 	        strval($record->role),
			);
		}
		
		$result = SpbcHelper::securityLogs($api_key, $data);
		
		if(empty($result['error'])){
			
			//Clear local table if it's ok.
			if($result['rows'] == $rows_count){
				
				if(SPBC_WPMS){
					$wpdb->query('DELETE 
						FROM ' . SPBC_DB_PREFIX . SPBC_LOG_TABLE.
						(empty($spbc->allow_custom_key) ? '' : ' WHERE blog_id = '.get_current_blog_id())
					);
				}else{
					$wpdb->query('DELETE FROM '. SPBC_DB_PREFIX . SPBC_LOG_TABLE);
				}
				$result = $rows_count;
				
			}else{
				$result = array(
					'error' => true,
					'error_time' => current_time('timestamp'),
					'error_string' => sprintf(__('Sent: %d. Confirmed receiving of %d rows.', 'security-malware-firewall'), $rows_count, intval($result['rows']))
				);
			}
		}
	}else{
		$result = array(
			'error' => true,
			'error_time' => current_time('timestamp'),
			'error_string' => 'NO_LOGS_TO_SEND'
		);
	}
	
	if(defined('SPBC_CRON') && SPBC_CRON == true){
		
		if(empty($result['error'])){
			$spbc->data['logs_last_sent'] = current_time('timestamp');
			$spbc->data['last_sent_events_count'] = $result;
			unset($spbc->data['errors']['sec_logs']);
		}else{
			if($result['error_string'] != 'NO_LOGS_TO_SEND'){
				$spbc->data['errors']['sec_logs'] = $result;
				$spbc->data['errors']['sec_logs']['error_time'] = current_time('timestamp');
			}
		}
		$spbc->save('data');
	}
	
	return $result;
}

// The functions check to check an account
// Execute only via cron (on the main blog)
function spbc_access_key_notices() {
			
	global $spbc;
	
	if(!$spbc->key_is_ok)
		return false;
	
	if(SPBC_WPMS && !is_main_site() && !$spbc->allow_custom_key)
		$spbc_key = (!empty($spbc->network_settings['spbc_key']) ? $spbc->network_settings['spbc_key'] : false);
	else
		$spbc_key = (!empty($spbc->settings['spbc_key']) ? $spbc->settings['spbc_key'] : false);
	
	if(!$spbc_key)
		return false;
	
	$result = SpbcHelper::noticePaidTill($spbc_key);
	
	if(empty($result['error'])){
		
		$spbc->data['notice_show']	= $result['show_notice'];
		$spbc->data['notice_renew'] = $result['renew'];
		$spbc->data['notice_trial'] = $result['trial'];
		$spbc->data['service_id']   = $result['service_id'];
		$spbc->save('data');
		
		if($spbc->data['notice_renew'] == 1){
			SpbcCron::updateTask('access_key_notices', 'spbc_access_key_notices', 3600, time()+3500);
		}
		if($spbc->data['notice_trial'] == 0){
			SpbcCron::updateTask('access_key_notices', 'spbc_access_key_notices', 86400, time()+86400);
		}
			
		return true;
		
	}else{
		return $result['error_string'];
	}
}

function spbc_get_root_path($end_slah = false){
	return $end_slah ? ABSPATH : substr(ABSPATH, 0, -1);
}

// The functions sends daily reports about attempts to login. 
function spbc_send_daily_report($skip_data_rotation = false) {
    
	if(!function_exists('wp_mail')){
		add_action('plugins_loaded', 'spbc_send_daily_report');
		return;
	}
	
	global $spbc, $wpdb;
	
	//If key is not ok, send daily report!
	if(!$spbc->key_is_ok){
	
		include_once(SPBC_PLUGIN_DIR . 'templates/spbc_send_daily_report.php');

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

		$spbc_auth_logs_table = SPBC_DB_PREFIX . SPBC_LOG_TABLE;
		$sql = sprintf('SELECT id,datetime,user_login,event,auth_ip,page,page_time 
			FROM %s WHERE datetime between now() - interval %d hour and now();',
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
