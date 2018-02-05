<?php

//
// Do updates in SQL database after plugin update.
//
function spbc_run_update_actions($current_version, $new_version) {
	
    global $spbc, $wpdb, $wp_version;
	
	$spbc_auth_logs_table      = SPBC_DB_PREFIX . SPBC_LOG_TABLE;
	$spbc_firewall_logs_table  = SPBC_DB_PREFIX . SPBC_FIREWALL_LOG;
	$spbc_firewall_data_table  = SPBC_DB_PREFIX . SPBC_FIREWALL_DATA;
	$spbc_scan_results_table   = SPBC_DB_PREFIX . SPBC_SCAN_RESULTS;
	$spbc_scan_links_log_table = SPBC_DB_PREFIX . SPBC_SCAN_LINKS_LOG;
	
	$current_version = spbc_version_standartization($current_version);
	$new_version     = spbc_version_standartization($new_version);
	
	if($current_version[0] == 1){
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
			// Clearing errors because format changed
			$spbc->data['errors'] = array();
		}
		if($current_version[1] <= 21){
			// Adding service ID and refreshing other account params
			if(!empty($spbc->settings['spbc_key'])){
				$result = SpbcHelper::noticePaidTill($spbc->settings['spbc_key']);
				if(empty($result['error'])){
					$spbc->data['notice_show']	= $result['show_notice'];
					$spbc->data['notice_renew'] = $result['renew'];
					$spbc->data['notice_trial'] = $result['trial'];
					$spbc->data['service_id']   = $result['service_id'];
					if(SPBC_WPMS && is_main_site()){
						$spbc->network_settings['service_id'] = $result['service_id'];
						$spbc->saveNetworkSettings();
					}
				}
			}
		}
	}
	
	if($current_version[0] < 2){
		// Scanner's cron
		SpbcCron::addTask('perform_scan_wrapper', 'spbc_perform_scan_wrapper', 86400, time()+86400);
		// Drop existing table and create scanner's table
		$wpdb->query("DROP TABLE IF EXISTS $spbc_scan_results_table;");
		$wpdb->query("CREATE TABLE IF NOT EXISTS $spbc_scan_results_table (
			`path` VARCHAR(1024) NOT NULL,
			`size` INT(10) NOT NULL,
			`perms` INT(4) NOT NULL,
			`mtime` INT(11) NOT NULL,
			`status` ENUM('NOT_CHECKED','UNKNOWN','OK','APROVED','COMPROMISED','INFECTED') NOT NULL DEFAULT 'NOT_CHECKED',
			`severity` ENUM('CRITICAL','DANGER','SUSPICIOUS') NULL,
			`weak_spots` VARCHAR(1024) NULL,
			`difference` VARCHAR(1024) NULL,
			`last_sent` INT(11) NOT NULL,
			`fast_hash` VARCHAR(32) NULL DEFAULT NULL,
			`full_hash` VARCHAR(32) NULL DEFAULT NULL,
			`real_full_hash` VARCHAR(32) NULL,
			UNIQUE (`fast_hash`)
		) ENGINE = MYISAM;");
	}
	if($current_version[0] <= 2){
		if($current_version[1] == 0){
			unset($spbc->data['errors']);
			$spbc->save('data');
		}
		if($current_version[1] <= 3){
			$wpdb->query("DROP TABLE IF EXISTS $spbc_auth_logs_table;");
			$wpdb->query("CREATE TABLE IF NOT EXISTS $spbc_auth_logs_table (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`datetime` datetime NOT NULL,
				`user_login` varchar(60) NOT NULL,
				`event` varchar(32) NOT NULL,
				`page` VARCHAR(500) NULL,
				`page_time` VARCHAR(10) NULL,
				`blog_id` int(11) NOT NULL,
				`auth_ip` int(10) unsigned DEFAULT NULL,
				`role` varchar(64) DEFAULT NULL,
				PRIMARY KEY (`id`),
				KEY `datetime` (`datetime`,`event`)
				) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1;");
			$wpdb->query("DROP TABLE IF EXISTS $spbc_firewall_data_table;");
			$wpdb->query("CREATE TABLE IF NOT EXISTS $spbc_firewall_data_table (
				`spbc_network` int(11) unsigned NOT NULL,
				`spbc_mask` int(11) unsigned NOT NULL,
				`status` TINYINT(1) NULL,
				INDEX (`spbc_network` , `spbc_mask`)
				) ENGINE = MYISAM ;");
			$wpdb->query("DROP TABLE IF EXISTS $spbc_firewall_logs_table;");
			$wpdb->query("CREATE TABLE IF NOT EXISTS $spbc_firewall_logs_table (
				`entry_id` VARCHAR(40) NOT NULL,
				`ip_entry` VARCHAR(15) NULL, 
				`allowed_entry` INT NOT NULL, 
				`blocked_entry` INT NOT NULL,
				`status` ENUM('PASS','PASS_BY_WHITELIST','DENY','DENY_BY_NETWORK','DENY_BY_DOS') NULL,
				`page_url` VARCHAR(4096) NULL,
				`request_method` VARCHAR(5) NULL,
				`x_forwarded_for` VARCHAR(15) NULL,
				`http_user_agent` VARCHAR(300) NULL,
				`entry_timestamp` INT NOT NULL , 
				PRIMARY KEY (`entry_id`)) 
				ENGINE = MYISAM;");
			$wpdb->query("DROP TABLE IF EXISTS $spbc_scan_links_log_table;");
			$wpdb->query("CREATE TABLE IF NOT EXISTS $spbc_scan_links_log_table (
				`log_id` int(11) NOT NULL AUTO_INCREMENT,
				`user_id` int(11) unsigned DEFAULT NULL,
				`service_id` int(11) unsigned DEFAULT NULL,
				`submited` datetime NOT NULL,
				`total_links_found` INT NOT NULL,
				`links_list` TEXT DEFAULT NULL,
				PRIMARY KEY (`log_id`)
				) ENGINE = MYISAM DEFAULT CHARSET=latin1 AUTO_INCREMENT=1;");
			// $wpdb->query("ALTER TABLE `$spbc_scan_results_table`
				// ADD COLUMN `last_full_hash` varchar(32) NULL AFTER `real_full_hash`;");
		}
	}
	
    return true;
	
}

function spbc_version_standartization($version){
	
	$version = explode('.', $version);
	$version = !empty($version) ? $version : array();
	
	// Version
	$version[0] = !empty($version[0]) ? (int)$version[0] : 0; // Major
	$version[1] = !empty($version[1]) ? (int)$version[1] : 0; // Minor
	$version[2] = !empty($version[2]) ? (int)$version[2] : 0; // Fix
	
	return $version;
}
