<?php

//
// Do updates in SQL database after plugin update.
//
function spbc_run_update_actions($current_version, $new_version) {
	
    global $spbc, $wpdb;
	
	$spbc_auth_logs_table     = SPBC_DB_PREFIX . SPBC_LOG_TABLE;
	$spbc_firewall_logs_table = SPBC_DB_PREFIX . SPBC_FIREWALL_LOG;
	$spbc_firewall_data_table = SPBC_DB_PREFIX . SPBC_FIREWALL_DATA;
	
	$current_version = spbc_version_standartization($current_version);
	$new_version     = spbc_version_standartization($new_version);
		
	if($current_version[0] == 1){
		if($current_version[1] < 4){
			$sql = "ALTER TABLE `$spbc_auth_logs_table` 
				CHANGE `event`
				`event` VARCHAR(32) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL;";
			$wpdb->query($sql);
		}
		if($current_version[1] < 5){
			$sql = "ALTER TABLE `$spbc_auth_logs_table`
				ADD COLUMN `page` VARCHAR(500) NULL AFTER `event`,
				ADD COLUMN `page_time` VARCHAR(10) NULL AFTER `page`;";
			$wpdb->query($sql);
		}
		if($current_version[1]  <= 5){
			if($current_version[2] == 0){
				$sql = "ALTER TABLE `$spbc_auth_logs_table`
					ADD COLUMN `page` VARCHAR(500) NULL AFTER `event`,
					ADD COLUMN `page_time` VARCHAR(10) NULL AFTER `page`;";
				$wpdb->query($sql);
			}
		}
		if($current_version[1] <= 6){
			$sql = "ALTER TABLE `$spbc_auth_logs_table`
				ADD COLUMN `blog_id` int(11) NOT NULL AFTER `page`;";
			$wpdb->query($sql);
		}
		if($current_version[1] <= 8){
			//Adding send logs cron hook if not exists
			if ( !wp_next_scheduled('spbc_send_logs_hook') )
				wp_schedule_event(time() + 1800, 'hourly', 'spbc_send_logs_hook');
			// Update Security FireWall cron hook
			if ( !wp_next_scheduled('spbc_security_firewall_update_hook') )
				wp_schedule_event(time() + 1800, 'hourly', 'spbc_security_firewall_update_hook');
			// Send logs cron hook
			if ( !wp_next_scheduled('spbc_send_firewall_logs_hook') )
				wp_schedule_event(time() + 1800, 'hourly', 'spbc_send_firewall_logs_hook');

			$sql = "CREATE TABLE IF NOT EXISTS $spbc_firewall_data_table (
				`spbc_network` int(11) unsigned NOT NULL,
				`spbc_mask` int(11) unsigned NOT NULL,
				INDEX (`spbc_network` , `spbc_mask`)
				) ENGINE = MYISAM ;";
			$wpdb->query($sql);
						
			$sql = "CREATE TABLE IF NOT EXISTS $spbc_firewall_logs_table (
				`ip_entry` VARCHAR(15) NOT NULL , 
				`all_entry` INT NOT NULL , 
				`blocked_entry` INT NOT NULL , 
				`entry_timestamp` INT NOT NULL , 
				PRIMARY KEY (`ip_entry`)) 
				ENGINE = MYISAM;";
			$wpdb->query($sql);
		}
		if($current_version[1] <= 9){
			if($current_version[2] <= 1){
				wp_clear_scheduled_hook('spbc_send_logs_hourly_hook');
				wp_clear_scheduled_hook('spbc_send_daily_report');
				wp_clear_scheduled_hook('spbc_send_daily_report_hook');
				wp_clear_scheduled_hook('spbc_security_firewall_update_hourly_hook');
				wp_clear_scheduled_hook('spbc_send_firewall_logs_hourly_hook');
				
				wp_schedule_event(time() + 1800, 'hourly', 'spbc_send_logs_hook');
				wp_schedule_event(time() + 43200, 'daily', 'spbc_send_report_hook');	
				wp_schedule_event(time() + 43200, 'daily', 'spbc_security_firewall_update_hook');
				wp_schedule_event(time() + 1800, 'hourly', 'spbc_send_firewall_logs_hook');
				wp_schedule_event(time() + 1800, 'hourly', 'spbc_access_key_notices_hook');
			}
		}
		if($current_version[1] <= 10){
			$sql = "ALTER TABLE `$spbc_auth_logs_table`
				ADD COLUMN `role` VARCHAR(64) NULL AFTER `auth_ip`;";
			$wpdb->query($sql);
		}
		if($current_version[1] <= 13){
			$sql = "ALTER TABLE `$spbc_firewall_data_table`
				ADD COLUMN `status` TINYINT(1) NULL AFTER `spbc_mask`;";
			$wpdb->query($sql);
			$sql = "ALTER TABLE `$spbc_firewall_logs_table`
				 CHANGE `all_entry` `allowed_entry` INT(11) NOT NULL;";
			$wpdb->query($sql);
		}
		if($current_version[1] <= 16){
			$sql = "ALTER TABLE `$spbc_firewall_logs_table`
				ADD COLUMN `page_url` VARCHAR(4096) NULL AFTER `blocked_entry`,
				ADD COLUMN `request_method` VARCHAR(5) NULL AFTER `page_url`,
				ADD COLUMN `x_forwarded_for` VARCHAR(15) NULL AFTER `request_method`,
				ADD COLUMN `http_user_agent` VARCHAR(300) NULL AFTER `x_forwarded_for`;";
			$wpdb->query($sql);
		}
		if($current_version[1] <= 18){
			wp_clear_scheduled_hook('spbc_send_logs_hook');
			wp_clear_scheduled_hook('spbc_send_report_hook');
			wp_clear_scheduled_hook('spbc_security_firewall_update_hook');
			wp_clear_scheduled_hook('spbc_send_firewall_logs_hook');
			wp_clear_scheduled_hook('spbc_access_key_notices_hook');
			
			// Self cron system
			SpbcCron::addTask('send_logs',           'spbc_send_logs',                3600,  time()+1800);
			SpbcCron::addTask('send_report',         'spbc_send_daily_report',        86400, time()+43200);
			SpbcCron::addTask('firewall_update',     'spbc_security_firewall_update', 86400, time()+43200);
			SpbcCron::addTask('send_firewall_logs',  'spbc_send_firewall_logs',       3600,  time()+1800);
			SpbcCron::addTask('access_key_notices',  'spbc_access_key_notices',       3600,  time()+3500);
		}
		if($current_version[1] <= 19){
			wp_clear_scheduled_hook('spbc_access_key_notices_hook');
		}
		if($current_version[1] <= 20){
			if(!in_array('entry_id', $wpdb->get_col("DESC $spbc_firewall_logs_table", 0))){
				$sql = "ALTER TABLE $spbc_firewall_logs_table
					ADD COLUMN `entry_id` VARCHAR(40) FIRST,
					ADD COLUMN `status` ENUM('PASS', 'PASS_BY_WHITELIST', 'DENY','DENY_BY_NETWORK','DENY_BY_DOS') AFTER `blocked_entry`,
					DROP PRIMARY KEY,
					ADD PRIMARY KEY(`entry_id`);";
				$wpdb->query($sql);
			}
			
			// Clearing errors because format changed
			$spbc->data['errors'] = array();
			
		}
		if($current_version[1] <= 21){
			// Adding service ID and refreshing other account params
			$result = SpbcHelper::noticePaidTill($spbc_key);
			if(empty($result['error'])){
				$spbc->data['notice_show']	= $result['show_notice'];
				$spbc->data['notice_renew'] = $result['renew'];
				$spbc->data['notice_trial'] = $result['trial'];
				$spbc->data['service_id']   = $result['service_id'];
			}
			if(SPBC_WPMS && is_main_site()){
				$spbc->network_settings['service_id'] = $result['service_id'];
				$spbc->saveNetworkSettings();
			}
		}
	}
	
    return true;
	
}

function spbc_version_standartization($version){
	
	$version = explode('.', $version);
	$version = !empty($version) ? $version : array();
	
	$version[0] = !empty($version[0]) ? (int)$version[0] : 0;
	$version[1] = !empty($version[1]) ? (int)$version[1] : 0;
	$version[2] = !empty($version[2]) ? (int)$version[2] : 0;
	
	return $version;
}
