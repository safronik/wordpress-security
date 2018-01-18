<?php
	
	// Settings page
	require_once('spbc-settings.php'); 

/**
 * Admin action 'admin_init' - Add the admin settings and such
 */
function spbc_admin_init() {
	
	global $spbc;
		
	//Update logic
	$current_version = (!empty($spbc->data['plugin_version']) ? $spbc->data['plugin_version'] : '1.0.0');
	
	if($current_version != SPBC_VERSION){
		if(is_main_site()){
			require_once(SPBC_PLUGIN_DIR . 'inc/spbc-updater.php');
			spbc_run_update_actions($current_version, SPBC_VERSION);
		}
		$spbc->data['notice_were_updated'] = (isset($spbc->data['plugin_version']) ? true : false); //Flag - plugin were updated
		$spbc->data['plugin_version'] = SPBC_VERSION;
		$spbc->save('data');
		$spbc->save('settings'); // Saving default settings
	}
	
	// Drop debug data
	if(!empty($_POST['spbc_drop_debug'])){
		delete_option(SPBC_DEBUG);
	}
	
	//Get auto key button
	if (isset($_POST['spbc_get_apikey_auto'])){
		
		if(SPBC_WPMS && defined('SUBDOMAIN_INSTALL') && !SUBDOMAIN_INSTALL)
			$wpms_subdirs = true;
		else
			$wpms_subdirs = false;
		$result = SpbcHelper::getApiKey(get_option('admin_email'), parse_url(get_option('siteurl'),PHP_URL_HOST), 'wordpress', $wpms_subdirs);
		
		if(!empty($result['error'])){
				
			$spbc->data['key_is_ok'] = false;
			SpbcHelper::addError('get_key', $result);
			
		}else{
			
			$_POST['spbc_settings']['traffic_control_enabled'] = true;
			$_POST['spbc_settings']['spbc_key'] = $result['auth_key'];
			
			$spbc->data['user_token'] = (!empty($result['user_token']) ? $result['user_token'] : '');
			$spbc->data['key_is_ok'] = true;
			$spbc->save('data');
			
			$spbc->settings['spbc_key'] = $result['auth_key'];
			$spbc->save('settings');
			
		}
	}
		
	//Logging admin actions
	if(!defined( 'DOING_AJAX' ))
		spbc_admin_log_action();
	
	// AJAX Actions
	
	// Logs
	add_action('wp_ajax_spbc_show_more_security_logs',          'spbc_show_more_security_logs_callback');
	add_action('wp_ajax_spbc_show_more_security_firewall_logs', 'spbc_show_more_security_firewall_logs_callback');
	
	// Scanner
	add_action('wp_ajax_spbc_scanner_get_remote_hashs', 'spbc_scanner_get_remote_hashs');
	add_action('wp_ajax_spbc_scanner_scan',             'spbc_scanner_scan');
	add_action('wp_ajax_spbc_scanner_scan_modified',    'spbc_scanner_scan_modified');
	add_action('wp_ajax_spbc_scanner_scan_links_from_url','spbc_scanner_scan_links');	
	add_action('wp_ajax_spbc_scanner_clear',            'spbc_scanner_clear');
	add_action('wp_ajax_spbc_scanner_list_results',     'spbc_scanner_list_results');
	// Scanner buttons
	add_action('wp_ajax_spbc_scanner_send_results',     'spbc_scanner_send_results');
	add_action('wp_ajax_spbc_scanner_file_send',        'spbc_scanner_file_send');
	add_action('wp_ajax_spbc_scanner_file_delete',      'spbc_scanner_file_delete');
	add_action('wp_ajax_spbc_scanner_file_approve',     'spbc_scanner_file_approve');
	add_action('wp_ajax_spbc_scanner_file_view',        'spbc_scanner_file_view');
	add_action('wp_ajax_spbc_scanner_file_edit',        'spbc_scanner_file_edit');
	add_action('wp_ajax_spbc_scanner_file_compare',     'spbc_scanner_file_compare');
	add_action('wp_ajax_spbc_scanner_file_replace',     'spbc_scanner_file_replace');
	
	// SpbcList based on WP_List_Table
	require_once(SPBC_PLUGIN_DIR . 'lib/SpbcFireWall.php');
	
}

//
//Admin notice
//
function spbc_admin_notice_message(){
	
	global $spbc;

	$page = get_current_screen();
	$plugin_settings_link = '<a href="'. (is_network_admin() ? 'settings.php' : 'options-general.php' ) .'?page=spbc">'.__('Security by CleanTalk', 'security-malware-firewall').'</a>';
	
	// Trial ends
	if($spbc->show_notice && $spbc->trial){
		
		$button = '<input type="button" class="button button-primary" value="'.__('UPGRADE', 'security-malware-firewall').'"  />';
		$link = sprintf(
			'<a  target="_blank"  style="vertical-align: super;" href="https://cleantalk.org/my/bill/security?cp_mode=security&utm_source=wp-backend&utm_medium=cpc&utm_campaign=WP%%20backend%%20trial_security&user_token=%s">%s</a>',
			$spbc->user_token,
			$button
		);
		
		echo '<div class="error" style="position: relative;">'
				.'<h3 style="margin: 10px;">'
					.'<u>'.$plugin_settings_link.'</u>: '
					. __('trial period ends, please upgrade to premium version to keep your site secure and safe!', 'security-malware-firewall')
					.'&nbsp'
					.$link
				.'</h3>'
			.'</div>';
		return;
	}
	
	// Renew. Licence ends
	if($spbc->show_notice && $spbc->renew ){
		
		$button = '<input type="button" class="button button-primary" value="'.__('RENEW', 'security-malware-firewall').'"  />';
		$link = sprintf('<a target="_blank" href="https://cleantalk.org/my/bill/security?cp_mode=security&utm_source=wp-backend&utm_medium=cpc&utm_campaign=WP%%20backend%%20trial_security&user_token=%s">%s</a>', $spbc->user_token, $button);
		
		echo '<div class="error" style="position: relative;">
				<h3 style="margin: 10px;">
					<u>'. $plugin_settings_link .'</u>: '
					. __('Please renew your security license.', 'security-malware-firewall').
				'</h3>'.
				$link.
				'<br><br>
			</div>';
		return;
	}
	
	// Wrong key
	if(!$spbc->key_is_ok && $page->id != 'settings_page_spbc' && $page->id != 'settings_page_spbc-network'){
		
		echo '<div class="error" style="position: relative;">';
			
			if(is_network_admin())
				printf('<h3  style="margin: 10px;"><u>'. $plugin_settings_link .'</u>: ' . __('API key is not valid. Enter into %splugin settings%s in the main site dashboard to get API key.', 'security-malware-firewall') . '</h3>', '<a href="'. get_site_option('siteurl') .'wp-admin/settings.php?page=spbc">', '</a>');
			else
				printf('<h3 style="margin: 10px 20px 10px 10px;"><u>'. $plugin_settings_link .'</u>: ' . __('API key is not valid. Enter into %splugin settings%s to get API key.', 'security-malware-firewall') . '</h3>', '<a href="options-general.php?page=spbc">', '</a>');
			
			if($spbc->were_updated)
				printf('<h3 style="margin: 10px;">'. __('Why do you need an API key? Please, learn more %shere%s.', 'security-malware-firewall'). '</h3>', '<a href="https://wordpress.org/support/topic/why-do-you-need-an-access-key-updated/">', '</a>');
			
			echo '<button type="button" class="notice-dismiss"><span class="screen-reader-text">' .__('Dismiss this notice.', 'security-malware-firewall'). '</span></button>';
		echo '</div>';
	}
}

/**
 * Manage links in plugins list
 * @return array
*/
function spbc_plugin_action_links($links, $file) {
	
	$settings_link = is_network_admin()
		? '<a href="settings.php?page=spbc">' . __( 'Settings' ) . '</a>'
		: '<a href="options-general.php?page=spbc">' . __( 'Settings' ) . '</a>';
		
	array_unshift( $links, $settings_link ); // before other links
	return $links;
}

/**
 * Manage links and plugins page
 * @return array
*/
function spbc_plugin_links_meta($meta, $plugin_file){
	
	//Return if it's not our plugin
	if(strpos($plugin_file, SPBC_PLUGIN_BASE_NAME) === false)
		return $meta;
	
	// $links[] = is_network_admin()
		// ? '<a class="ct_meta_links ct_setting_links" href="settings.php?page=spbc">' . __( 'Settings' ) . '</a>'
		// : '<a class="ct_meta_links ct_setting_links" href="options-general.php?page=spbc">' . __( 'Settings' ) . '</a>';
	
	if(substr(get_locale(), 0, 2) != 'en')
		$meta[] = '<a class="spbc_meta_links spbc_translate_links" href="'
				.sprintf('https://translate.wordpress.org/locale/%s/default/wp-plugins/security-malware-firewall', substr(get_locale(), 0, 2))
				.'" target="_blank">'
				.__('Translate', 'security-malware-firewall')
			.'</a>';
	$meta[] = '<a class="spbc_meta_links spbc_faq_links" href="http://wordpress.org/plugins/security-malware-firewall/faq/" target="_blank">' . __('FAQ', 'security-malware-firewall') . '</a>';
	$meta[] = '<a class="spbc_meta_links spbc_support_links" href="https://wordpress.org/support/plugin/security-malware-firewall" target="_blank">' . __('Support', 'security-malware-firewall') . '</a>';
	
	return $meta;
}

/**
 * Register stylesheet and scripts.
 */
function spbc_enqueue_scripts($hook) {
	
	global $spbc;
	
	// For ALL admin pages
	wp_enqueue_style ('spbc_admin_css', SPBC_PATH . '/css/spbc-admin.css', array(), SPBC_VERSION, 'all');

	// For settings page
	if($hook == 'settings_page_spbc'){
		
		$debug = get_option( SPBC_DEBUG );
		
		$button_template = '<button %sclass="spbc_scanner_button_file_%s">%s<img class="spbc_preloader_button" src="'.SPBC_PATH.'/images/preloader.gif" /></button>';
			
		$button_template_send    = sprintf($button_template, '', 'send',    __('Send for analysys', 'security-malware-firewall'));
		$button_template_delete  = sprintf($button_template, '', 'delete',  __('Delete', 'security-malware-firewall'));
		$button_template_approve = sprintf($button_template, '', 'approve', __('Approve', 'security-malware-firewall'));
		$button_template_view    = sprintf($button_template, '', 'view',    __('View', 'security-malware-firewall'));
		$button_template_edit    = sprintf($button_template, '', 'edit',    __('Edit', 'security-malware-firewall'));
		$button_template_replace = sprintf($button_template, '', 'replace', __('Replace with original', 'security-malware-firewall'));
		$button_template_compare = sprintf($button_template, '', 'compare', __('Show difference', 'security-malware-firewall'));
		
		$actions_unknown  = $button_template_send.$button_template_delete.$button_template_approve.$button_template_view;
		$actions_modified = $button_template_approve.$button_template_replace.$button_template_compare;
				
		// CSS
		wp_enqueue_style ('spbc-settings',     SPBC_PATH . '/css/spbc-settings.css', array(),                             SPBC_VERSION, 'all');
		wp_enqueue_style ('jquery-ui',         SPBC_PATH . '/css/jquery-ui.min.css', array(),                             '1.12.1',     'all');
		
		// JS
		wp_enqueue_script('jquery-ui',         SPBC_PATH . '/js/jquery-ui.min.js',   array('jquery'),                     '1.12.1',     false);
		wp_enqueue_script('spbc-settings-js',  SPBC_PATH . '/js/spbc-settings.js',   array('jquery'),                     SPBC_VERSION, false);
		// Scanner
		wp_enqueue_script('spbc-scaner-js',    SPBC_PATH . '/js/spbc-scaner.js',     array('jquery', 'spbc-settings-js'), SPBC_VERSION, false);
		wp_enqueue_script('spbc-scaner-callbacks-js', SPBC_PATH . '/js/spbc-scaner-callbacks.js', array('jquery', 'spbc-settings-js'), SPBC_VERSION, false);
		wp_enqueue_script('spbc-scaner-events-js',    SPBC_PATH . '/js/spbc-scaner-events.js', array('jquery', 'spbc-settings-js'), SPBC_VERSION, false);
		
		wp_localize_script('jquery', 'spbcSettings', array(
			'img_path'   => SPBC_PATH . '/images',
			'key_is_ok'  => $spbc->key_is_ok,
			'ajax_nonce' => wp_create_nonce("spbc_secret_nonce"),
			'ajaxurl'    => admin_url('admin-ajax.php'),
			'debug'      => !empty($debug) ? true : false,
		));
		
		wp_localize_script('jquery', 'spbcSettingsSecLogs', array(
			'amount'     => SPBC_LAST_ACTIONS_TO_VIEW,
			'clicks'     => 0,
		));
		
		wp_localize_script('jquery', 'spbcSettingsFWLogs', array(
			'amount'     => SPBC_LAST_ACTIONS_TO_VIEW,
			'clicks'     => 0,
		));
		
		wp_localize_script('jquery', 'spbcScaner', array(
			//Confirmation
			'scan_modified_confiramation' => __('There is more than 30 modified files and this could take time. Do you want to proceed?', 'security-malware-firewall'),
			'warning_about_cancel' => __('Scan will be performed in the background mode soon.', 'security-malware-firewall'),
			'delete_warning' => __('Are you sure you want to delete the file? It can not be undone.'),
			// Progress bar
			'progressbar_get_hashs'          => __('Recieving hashes',                    'security-malware-firewall'),
			'progressbar_get_hashs_done'     => __('Hashs recieved',                     'security-malware-firewall'),
			'progressbar_preparing'          => __('Counting, sorting, locating files.', 'security-malware-firewall'),
			'progressbar_preparing_done'     => __('Done',                               'security-malware-firewall'),
			'progressbar_scan'               => __('Scanning for modifications',         'security-malware-firewall'),
			'progressbar_scan_done'          => __('Surface files scan complete',        'security-malware-firewall'),
			'progressbar_scan_modified_prep' => __('Counting modified files',            'security-malware-firewall'),
			'progressbar_scan_modified'      => __('Scanning modified files',            'security-malware-firewall'),
			'progressbar_scan_modified_done' => __('Modified files are scanned',         'security-malware-firewall'),
			'progressbar_scan_links		'    => __('Scanning links',          			 'security-malware-firewall'),
			'progressbar_scan_links_done' 	 => __('Links are scanned',        			 'security-malware-firewall'),			
			'progressbar_send_results'       => __('Sending results',                    'security-malware-firewall'),
			'progressbar_send_results_done'  => __('Scan completed',                       'security-malware-firewall'),
			// Warnings
			'result_text_bad_template' => __('Recommend to scan all (%s) of the found files to make sure the website is secure.', 'security-malware-firewall'),
			'result_text_good_template' => __('No threats are found.', 'security-malware-firewall'),
			// Templates
			'row_template'  => '<tr type="%s" class="spbc_scan_result_row" file_id="%s"><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
			'row_template_links'=>'<tr class="spbc_scan_result_row"><td><a href=%s target="_blank">%s</a></td><td><a href=%s target="_blank">%s</a></td><td>%s</td></tr>',			
			'actions_unknown'  => $actions_unknown,
			'actions_modified' => $actions_modified,
			'page_selector_template' => '<li class="pagination"><a href="#" class="spbc_page" type="%s" page="%s"><span%s>%s</span></a></li>',
			//Misc
			'look_below_for_scan_res' => __('Look below for scan results.', 'security-malware-firewall'),
			'view_all_results'        => sprintf(
				__('</br>%sView all scan results for this website%s', 'security-malware-firewall'),
				'<a target="blank" href="https://cleantalk.org/my/logs_mscan?service='.$spbc->service_id.'">',
				'</a>'
			),
			'last_scan_was_just_now'  => __('Website last scan was just now. %s files were scanned. %s outbound links were found.', 'security-malware-firewall'),
			// Params
			'on_page' => 20,
		));
	}
}

/*
 * Logging admin action
*/
function spbc_admin_log_action() {
	
    $user = wp_get_current_user();

	spbc_init_session();
		
	if(isset($_SESSION['spbc']))
		$result = spbc_write_timer($_SESSION['spbc']);
			
    if (isset($user->ID) && $user->ID > 0) {
		
		$roles = (is_array($user->roles) && !empty($user->roles) ? $user->roles[0] : null); // Takes only first role.
		
        $log_id = spbc_auth_log(array(
            'username' => $user->get('user_login'), 
            'event' => 'view',
			'page' => $_SERVER['REQUEST_URI'],
			'blog_id' => get_current_blog_id(),
			'roles' => $roles
        ));
    }
	
	//Seting timer with event ID
	if($log_id){
		$_SESSION['spbc']['log_id'] = $log_id;
		$_SESSION['spbc']['timer'] = time();	
	}
		
    return;
}

/*
 * Calculates and writes page time to DB
*/
function spbc_write_timer($timer){
	global $wpdb;
	
	$spbc_auth_logs_table = SPBC_DB_PREFIX . SPBC_LOG_TABLE;
	
	$result = $wpdb->update(
		$spbc_auth_logs_table,
		array ('page_time' => strval(time()-$timer['timer'])),
		array ('id' => $timer['log_id']),
		'%s',
		'%s'
    );
	
	return;
}
