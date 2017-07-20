<?php

/**
 * Admin action 'admin_init' - Add the admin settings and such
 */
function spbc_admin_init() {

    $spbc_data = get_option( SPBC_DATA );
	
	//Update logic
	$is_installing = get_option('spbc_installing');
	if($is_installing){
		delete_option('spbc_installing');
		$spbc_data['plugin_version'] = SPBC_VERSION;
		update_option( SPBC_DATA , $spbc_data);
	}else{
		$current_version = (isset($spbc_data['plugin_version']) ? $spbc_data['plugin_version'] : '1.0.0');
			
		if($current_version != SPBC_VERSION){
			if(is_main_site()){
				require_once(SPBC_PLUGIN_DIR . 'inc/spbc-updater.php');
				spbc_run_update_actions($current_version, SPBC_VERSION);
			}
			$spbc_data['notice_were_updated'] = (isset($spbc_data['plugin_version']) ? true : false); //Flag - plugin were updated
			$spbc_data['plugin_version'] = SPBC_VERSION;
			update_option( SPBC_DATA , $spbc_data);
		}
		
	}
	
	// Drop debug data
	if(!empty($_POST['spbc_drop_debug'])){
		delete_option(SPBC_DEBUG);
	}
	
	//Get auto key button
	if (isset($_POST['spbc_get_apikey_auto'])){
		
		$website = parse_url(get_option('siteurl'),PHP_URL_HOST);
		$platform = 'wordpress';
		$product_name = 'security';
		
		if(!function_exists('spbc_getAutoKey'))
			require_once(SPBC_PLUGIN_DIR . 'inc/spbc-tools.php');
		
		$result = spbc_getAutoKey(get_option('admin_email'), $website, $platform, $product_name);
		
		if($result){

			$result = json_decode($result, true);
			
			if(isset($result['error_no']) || isset($result['error_message'])){
				
				$spbc_data['key_is_ok'] = false;
				update_option( SPBC_DATA , $spbc_data);
				if(is_main_site()){
					$spbc_network_settings = get_site_option( SPBC_NETWORK_SETTINGS );
					$spbc_network_settings['key_is_ok'] = false;
				}
				
			}elseif(isset($result['data']) && is_array($result['data'])){
				
				$result = $result['data'];
								
				$spbc_settings = get_option( SPBC_SETTINGS );
			
				$spbc_data['user_token'] = (!empty($result['user_token']) ? $result['user_token'] : '');
				$spbc_settings['spbc_key'] = $result['auth_key'];
				$_POST['spbc_settings']['spbc_key'] = $result['auth_key'];
				$spbc_data['key_is_ok'] = true;
				
				update_option( SPBC_DATA , $spbc_data);
				update_option( SPBC_SETTINGS , $spbc_settings);

				if(is_main_site()){
					$spbc_network_settings = get_site_option( SPBC_NETWORK_SETTINGS );
										
					$spbc_network_settings['spbc_key'] = $result['auth_key'];
					$spbc_network_settings['user_token'] = (!empty($result['user_token']) ? $result['user_token'] : '');
					$spbc_network_settings['key_is_ok'] = true;
					
					update_site_option ( SPBC_NETWORK_SETTINGS, $spbc_network_settings);
				}
				
				
			}
		}
	}
	
	//Logging admin actions
	if(!defined( 'DOING_AJAX' ))
		spbc_admin_log_action();
	
	add_action('wp_ajax_spbc_show_more_security_logs', 'spbc_show_more_security_logs_callback');
}

function spbc_show_more_security_logs_callback(){
	
	check_ajax_referer('spbc_secret_nonce', 'security');
	
	global $wpdb;
	
	$spbc_data = get_option( SPBC_DATA );
		
	if(!is_main_site()){
		$spbc_network_settings = get_site_option( SPBC_NETWORK_SETTINGS );
		if($spbc_network_settings){
			$allow_custom_key = 	($spbc_network_settings['allow_custom_key'] ? true : false);
			if($allow_custom_key)
				$user_token = 		(isset($spbc_data['user_token']) ? $spbc_data['user_token'] : 'none');
			else
				$user_token = 			($spbc_network_settings['user_token'] ? $spbc_network_settings['user_token'] : '');
			
		}else{
			$user_token = (isset($spbc_data['user_token']) ? $spbc_data['user_token'] : 'none');
			$allow_custom_key = true;
		}
	}else{
		$user_token = (isset($spbc_data['user_token']) ? $spbc_data['user_token'] : 'none');
		$allow_custom_key = true;
	}
	
	$start = intval($_POST['start_nubmer']);
	$amount = intval($_POST['show_entries']);
	
	$result = $wpdb->get_results(
		"SELECT 
			*
			FROM ".SPBC_DB_PREFIX.SPBC_LOG_TABLE." "
			.(SPBC_WPMS ? 'WHERE blog_id = '.get_current_blog_id() : '').
			"LIMIT ".$start.", ".$amount.";",
			ARRAY_A
	);
	
	if(is_array($result)){
		$count = count($result);
		if($count){
			$data = array();
			
			$ip_info = array();
			for($i=0; $i < $count; $i++){
				$result[$i]['auth_ip'] = long2ip($result[$i]['auth_ip']);
				$ip_info[] = $result[$i]['auth_ip'];
			}
			
			$ip_info = spbc_get_countries_by_ips(implode(',',$ip_info));
			
			foreach($result as $value){
				
				$user = get_user_by('login', $value['user_login']);
				$user_id = isset($user->data->ID) ? $user->data->ID : 'none';
				
				$ip = $value['auth_ip'];
				$country_part = spbc_report_country_part($ip_info, $ip);
				
				$data[] = array(
					'datetime' => date("M d Y, H:i:s", strtotime($value['datetime'])),
					'user' => "<a href='".admin_url()."/user-edit.php?user_id=".$user_id."' target='_blank'>".$value['user_login'].'</a>',
					'action' => $value['event'],
					'page' => "<a href='".$value['page']."' target='_blank'>".$value['page'].'</a>',
					'page_time' => $value['page_time'],
					'ip' => "<a href='https://cleantalk.org/blacklists/$ip' target='_blank'>$ip</a>,&nbsp;$country_part",
				);
			}
		}
	}
	
	$to_return = array(
		'count' => !empty($count) ? $count : 0,
		'data' => !empty($data) ? $data : 0,
		'user_token' => $allow_custom_key ? $user_token : 0
	);
	$to_return = json_encode($to_return);
	echo  $to_return;
	die();
}

//
//Admin notice
//
function spbc_admin_notice_message(){
		
	if(SPBC_WPMS){
		
		$spbc_network_settings = get_site_option( SPBC_NETWORK_SETTINGS );
		if($spbc_network_settings)
			$allow_custom_key = ($spbc_network_settings['allow_custom_key'] ? true : false);
		
		if(is_main_site() || $allow_custom_key){
			
			$spbc_data = get_option( SPBC_DATA );
			$key_is_ok = (isset($spbc_data['key_is_ok']) && $spbc_data['key_is_ok'] == 1 ? true : false);
			$user_token = (!empty($spbc_data['user_token']) ? $spbc_data['user_token'] : '');
			
			//Notices flags
			$show_notice = (isset($spbc_data['notice_show']) && $spbc_data['notice_show'] == 1 ? true : false);
			$renew = (isset($spbc_data['notice_renew']) && $spbc_data['notice_renew'] ? true : false);
			$trial = (isset($spbc_data['notice_trial']) && $spbc_data['notice_trial'] ? true : false);
			$were_updated = (isset($spbc_data['notice_were_updated']) && $spbc_data['notice_were_updated'] == 1 ? true : false);
			
		}else{
			$key_is_ok = ($spbc_network_settings['key_is_ok'] ? true : false);
			$user_token = (!empty($spbc_network_settings['user_token']) ? $spbc_network_settings['user_token'] : '');
			$show_notice = false;
			$renew = false;
			$trial = false;
		}
	}else{
		
		$spbc_data = get_option( SPBC_DATA );
		
		//Notices flags
		$show_notice = (isset($spbc_data['notice_show']) && $spbc_data['notice_show'] == 1 ? true : false);
		$renew = (isset($spbc_data['notice_renew']) && $spbc_data['notice_renew'] ? true : false);
		$trial = (isset($spbc_data['notice_trial']) && $spbc_data['notice_trial'] ? true : false);
		$were_updated = (isset($spbc_data['notice_were_updated']) && $spbc_data['notice_were_updated'] == 1 ? true : false);
		
		//Misc
		$key_is_ok = (isset($spbc_data['key_is_ok']) && $spbc_data['key_is_ok'] == 1 ? true : false);
		$user_token = (!empty($spbc_data['user_token']) ? $spbc_data['user_token'] : '');
	}

	$page = get_current_screen();
	$plugin_settings_link = "<a href='".(is_network_admin() ? "settings.php" : "options-general.php" )."?page=spbc'>".__("Security by CleanTalk", 'security-malware-firewall')."</a>";
	
	// Trial ends
	if($show_notice && $trial){
		$button = '<input type="button" class="button button-primary" value="'.__('UPGRADE', 'security-malware-firewall').'"  />';
		$link = sprintf("<a  target='_blank' href='https://cleantalk.org/my/bill/security?cp_mode=security&utm_source=wp-backend&utm_medium=cpc&utm_campaign=WP%%20backend%%20trial_security&user_token=%s'>%s</a>", $user_token, $button);
		echo "<div id='spbcTopWarning' class='error dissmisable' style='position: relative;'>
				<h3 style='margin: 10px;'>
					<u>$plugin_settings_link</u>: "
					. __("trial period ends, please upgrade to premium version to keep your site secure and safe!", 'security-malware-firewall').
				"</h3>".
				$link.
				"<br><br>
			</div>";
		return;
	}
	
	// Renew. Licence ends
	if($show_notice && $renew){
		$button = '<input type="button" class="button button-primary" value="'.__('RENEW', 'security-malware-firewall').'"  />';
		$link = sprintf("<a target='_blank' href='https://cleantalk.org/my/bill/security?cp_mode=security&utm_source=wp-backend&utm_medium=cpc&utm_campaign=WP%%20backend%%20trial_security&user_token=%s'>%s</a>", $user_token, $button);
		echo "<div id='spbcTopWarning' class='error' style='position: relative;'>
				<h3 style='margin: 10px;'>
					<u>$plugin_settings_link</u>: "
					. __("Please renew your security license.", 'security-malware-firewall').
				"</h3>".
				$link.
				"<br><br>
			</div>";
		return;
	}
	
	// Wrong key
	if(!$key_is_ok && $page->id != 'settings_page_spbc' && $page->id != 'settings_page_spbc-network'){
		
		echo "<div id='spbcTopWarning' class='error' style='position: relative;'>";
			
			if(is_network_admin())
				printf("<h3  style='margin: 10px;'><u>$plugin_settings_link</u>: " . __("API key is not valid. Enter into %splugin settings%s in the main site dashboard to get API key.", 'security-malware-firewall') . "</h3>", "<a href='".get_site_option('siteurl')."wp-admin/settings.php?page=spbc'>", "</a>");
			else
				printf("<h3 style='margin: 10px 20px 10px 10px;'><u>$plugin_settings_link</u>: " . __("API key is not valid. Enter into %splugin settings%s to get API key.", 'security-malware-firewall') . "</h3>", "<a href='options-general.php?page=spbc'>", "</a>");
			
			if($were_updated)
				printf("<h3 style='margin: 10px;'>". __("Why do you need an API key? Please, learn more %shere%s.", 'security-malware-firewall'). "</h3>", "<a href='https://wordpress.org/support/topic/why-do-you-need-an-access-key-updated/'>", "</a>");
			
			echo "<button type='button' class='notice-dismiss'><span class='screen-reader-text'>".__("Dismiss this notice.", 'security-malware-firewall')."</span></button>";
		echo "</div>";
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

	// For ALL admin pages
	wp_enqueue_style ('spbc_admin_css', SPBC_PATH . '/assets/css/spbc-admin.css', array(), SPBC_VERSION, 'all');
	wp_enqueue_script('spbc_admin_js',  SPBC_PATH . '/assets/js/spbc-admin.js',   array('jquery'), SPBC_VERSION, false);

	// For settings page
	if($hook == 'settings_page_spbc'){
		
		$spbc_data = get_option( SPBC_DATA );
		$debug = get_option( SPBC_DEBUG );
		
		$ajax_nonce = wp_create_nonce("spbc_secret_nonce");
		
		wp_enqueue_style ('spbc-settings-css', SPBC_PATH . '/assets/css/spbc-settings.css', array(), SPBC_VERSION, 'all');
		wp_enqueue_script('spbc-settings-js',  SPBC_PATH . '/assets/js/spbc-settings.js',   array('jquery'), SPBC_VERSION, false);
				
		wp_localize_script('jquery', 'spbcSettings', array(
			'ajax_nonce'               => $ajax_nonce,
			'ajaxurl'                  => admin_url('admin-ajax.php'),
			'start_nubmer'             => SPBC_LAST_ACTIONS_TO_VIEW,
			'show_entries'             => SPBC_LAST_ACTIONS_TO_VIEW,
			'debug'                    => !empty($debug) ? true : false
		));
		
	}
}

/**
 * Admin action 'admin_menu' - Add the admin options page
 */
function spbc_admin_add_page() {
	
	$spbc_network_settings = get_site_option( SPBC_NETWORK_SETTINGS );
	$spbc_settings = get_option( SPBC_SETTINGS );
	
	//Adding setting page
	if(is_network_admin())
		add_submenu_page("settings.php", __( SPBC_NAME . ' Settings', 'security-malware-firewall'), SPBC_NAME, 'manage_options', 'spbc', 'spbc_settings_page');
	else
		add_options_page( __( SPBC_NAME . ' Settings', 'security-malware-firewall'), SPBC_NAME, 'manage_options', 'spbc', 'spbc_settings_page');
	
	//Adding setting menu
    register_setting(SPBC_SETTINGS, SPBC_SETTINGS, 'spbc_sanitize_settings');
	
	//Adding menu sections
	add_settings_section('spbc_key_section', '', 'spbc_section_key', 'spbc');
	add_settings_section('spbc_section_status', '', 'spbc_section_security_status', 'spbc');
	add_settings_section('spbc_settings_section', "<hr />", 'spbc_section_setting', 'spbc');
	add_settings_section('spbc_save_button_section', '', 'spbc_section_save_button', 'spbc');
	add_settings_section('spbc_section_security_log', '', 'spbc_section_security_log_callback', 'spbc');
	add_settings_section('spbc_section_debug', '', 'spbc_section_debug', 'spbc');
	//Adding fields
	
	// Security status field
	add_settings_field('spbc_field_security_status', '', 'spbc_field_security_status_callback', 'spbc', 'spbc_section_status',
		array()
	);
	
	// Debug drop
	add_settings_field('spbc_field_debug_drop', '', 'spbc_field_debug_drop', 'spbc', 'spbc_section_debug',
		array()
	);
	// Debug data
	add_settings_field('spbc_field_debug', '', 'spbc_field_debug', 'spbc', 'spbc_section_debug',
		array()
	);
	
	//Security log field
	add_settings_field('spbc_field_security_logs', '', 'spbc_field_security_logs_callback', 'spbc', 'spbc_section_security_log',
		array(
			'id' => 'ct_option_security_logs',
			'class' => 'spbc-settings-section',
			'value' => false
		)
	);
		
		
	//Show link in registration form field
	add_settings_field('spbc_show_link_in_login_form', '', 'spbc_field_show_link_login_form', 'spbc', 'spbc_settings_section', 
		array(
			'id' => 'ct_option_show_link_in_login_form',
			'class' => 'spbc-settings-section',
			'value' => (isset($spbc_settings['show_link_in_login_form']) ? $spbc_settings['show_link_in_login_form'] : false)
		)
	);
	
	//Complete deactivation. Only for main blog.
	if(is_main_site()){
		add_settings_field('spbc_complete_deactivation', '', 'spbc_field_complete_deactivation', 'spbc', 'spbc_settings_section', 
			array(
				'id' => 'ct_option_complete_deactivation',
				'class' => 'spbc-settings-section',
				'value' => (isset($spbc_settings['complete_deactivation']) ? $spbc_settings['complete_deactivation'] : false)
			)
		);
		add_settings_field('spbc_set_cookies', '', 'spbc_field_set_cookies', 'spbc', 'spbc_settings_section', 
			array(
				'id' => 'ct_option_set_cookies',
				'class' => 'spbc-settings-section',
				'value' => (isset($spbc_settings['set_cookies']) ? $spbc_settings['set_cookies'] : false)
			)
		);
	}
	
	//Key field
	add_settings_field('spbc-apikey', '', 'spbc_field_key', 'spbc', 'spbc_key_section',
		array(
			'id' => 'spbc_key',
			'class' => 'spbc-key-section'
		)
	);
	
	//Allow custom key for WPMS field
	if(is_main_site() && SPBC_WPMS){
		add_settings_field('spbc-allow-custom-key', '', 'spbc_field_custom_key', 'spbc', 'spbc_key_section',
			array(
				'id' => 'custom_key',
				'class' => 'spbc-key-section',
				'value' => (isset($spbc_network_settings['allow_custom_key']) ? $spbc_network_settings['allow_custom_key'] : false)
			)
		);
	}
}

/**
 * Admin callback function - Displays plugin options page
 */
function spbc_settings_page() {
	
	global $spbc_tpl;
		
	if(is_network_admin()){
		$link = get_site_option('siteurl').'wp-admin/options-general.php?page=spbc';
		printf("<h2>" . __("Please, enter the %splugin settings%s in main site dashboard.", 'security-malware-firewall') . "</h2>", "<a href='$link'>", "</a>");
		return;
	}
		
	$spbc_data = get_option( SPBC_DATA );
	$spbc_settings = get_option( SPBC_SETTINGS );
	$debug = get_option( SPBC_DEBUG );
	echo !empty($debug) ? "<script>var spbc_debug = true;</script>" : '';
	
	if(!is_main_site()){
		$spbc_network_settings = get_site_option( SPBC_NETWORK_SETTINGS );
		if($spbc_network_settings){
			$allow_custom_key = 	($spbc_network_settings['allow_custom_key'] ? true : false);
			if($allow_custom_key){
				$user_token = 		(isset($spbc_data['user_token']) ? $spbc_data['user_token'] : 'none');
				$key_is_ok = 		(isset($spbc_data['key_is_ok']) && $spbc_data['key_is_ok'] == 1 ? true : false);
			}else{
				$user_token = 			($spbc_network_settings['user_token'] ? $spbc_network_settings['user_token'] : '');
				$key_is_ok = 			($spbc_network_settings['key_is_ok'] ? 'true' : 'false');
			}
		}else{
			$user_token = (isset($spbc_data['user_token']) ? $spbc_data['user_token'] : 'none');
			$key_is_ok = (isset($spbc_data['key_is_ok']) && $spbc_data['key_is_ok'] == 1 ? true : false);
			$allow_custom_key = true;
		}
	}else{
		$user_token = (isset($spbc_data['user_token']) ? $spbc_data['user_token'] : 'none');
		$key_is_ok = (isset($spbc_data['key_is_ok']) && $spbc_data['key_is_ok'] == 1 ? true : false);
		$allow_custom_key = true;
	}
		
	// If have error message.
	$error_msg = '';
	$error_msg .= (!isset($spbc_settings['spbc_key'], $spbc_data['key_is_ok']) || $spbc_data['key_is_ok'] == false || $spbc_settings['spbc_key'] == '' ? __("API key is not valid. Use the buttons below to get API key.", 'security-malware-firewall')."<br>" : '');
	$error_msg .= (!empty($spbc_data['errors']['apikey'])    ? "<span>{$spbc_data['errors']['apikey']}</span><br>" : '');
	$error_msg .= (!empty($spbc_data['errors']['sent_error'])? "<span>{$spbc_data['errors']['sent_error']}</span>"     : '');
	
	// Output error block
	if($error_msg != '' && is_main_site()){
		echo "<div id='spbcTopWarning' class='error' style='position: relative;'>"
			."<h3>".__('Errors:', 'security-malware-firewall')."</h3>"
			."<h4>$error_msg</h4>"
		."</div>";
	}
	
	?>
	<div class="wrap">
	
		<h2><?php echo SPBC_NAME; ?></h2>
		<?php do_settings_fields('spbc', 'spbc_section_status'); ?>		
		<form id='spbc_settings_form' method='post' action='options.php'>
			<?php settings_fields(SPBC_SETTINGS); ?>	
			
			<div class='spbc_wrapper_settings'>
				<!-- TABS NAV -->
				<div class='spbc_tabs_nav_wrapper'>
					<?php if($debug) echo "<h2 id='spbc_debug-control' class='spbc_tab_nav'>Debug</h2>"; ?>
					<h2 id='spbc_general-control' class='spbc_tab_nav spbc_tab_nav-active'><?php _e('General Settings', 'security-malware-firewall'); ?></h2>
					<h2 id='spbc_security_log-control' class='spbc_tab_nav'><?php _e('Security Log', 'security-malware-firewall'); ?></h2>
					<?php if($key_is_ok && ($allow_custom_key || is_main_site())){?>
					<div id='goToCleanTalk' class='spbc-div-2' style='display: inline-block; position: relative; top: -2px; left: 8px; margin-right: 7px;'>
						<a disabled id='goToCleanTalkLink' class='spbc_manual_link' target='_blank' href='https://cleantalk.org/my?user_token=<?php echo $user_token ?>&cp_mode=security'><?php _e('Security Control Panel', 'security-malware-firewall'); ?></a>
					</div>
					<?php } ?>
					<a target='_blank' href='https://wordpress.org/support/plugin/security-malware-firewall' style='display: inline-block; position: relative; top: -2px; left: 8px;'>
						<input type='button' class='spbc_auto_link' value='<?php _e('Support', 'security-malware-firewall'); ?>' />
					</a>
				</div>
				<!-- TABS -->
				<div class='spbc_tab spbc_tab-active' id='spbc_general'>
					<div class='spbc_tab_fields_group'>
						<h3 class='spbc_group_header'><?php _e('Access Key', 'security-malware-firewall'); ?></h3>
							<?php do_settings_fields('spbc', 'spbc_key_section'); ?>
					</div>
					<div class='spbc_tab_fields_group'>
						<h3 class='spbc_group_header'><?php _e('Miscellaneous', 'security-malware-firewall'); ?></h3>
							<?php do_settings_fields('spbc', 'spbc_settings_section'); ?>
					</div>
				</div>
				<div class='spbc_tab' id='spbc_security_log'>
					<div class='spbc_tab_fields_group'>
						<div class='spbc_wrapper_field'>
								<?php do_settings_fields('spbc', 'spbc_section_security_log'); ?>
						</div>
					</div>
				</div>
				<?php if($debug){ ?>
					<div class='spbc_tab' id='spbc_debug'>
						<div class='spbc_tab_fields_group'>
							<div class='spbc_wrapper_field'>
									<?php do_settings_fields('spbc', 'spbc_section_debug'); ?>
							</div>
						</div>
					</div>
				<?php } ?>
			</div>
			<?php submit_button(); ?>
		</form>
		<?php		
			// FOOTER
			
			// Rate banner
			echo sprintf($spbc_tpl['spbc_rate_plugin_tpl'],
				SPBC_NAME  
			);
			
			// Translate banner
			if(substr(get_locale(), 0, 2) != 'en'){
				echo sprintf($spbc_tpl['spbc_translate_banner_tpl'],
						substr(get_locale(), 0, 2)
					);
			}
			
			echo '<br /><br />';
			printf(__('The plugin home page', 'security-malware-firewall') .' <a href="https://wordpress.org/plugins/security-malware-firewall/" target="_blank">%s</a>.', SPBC_NAME);
			echo '<br>';
			echo __('Tech support CleanTalk: ', 'security-malware-firewall') . '<a target="_blank" href="https://wordpress.org/support/plugin/security-malware-firewall">https://wordpress.org/support/plugin/security-malware-firewall</a>';
			echo '<br>';
			echo __('CleanTalk is registered Trademark. All rights reserved.', 'security-malware-firewall');
		?>
	</div>
	<?php
}

function spbc_section_key(){
}

function spbc_section_security_status() {
	
}

function spbc_section_setting(){
}

function spbc_section_save_button(){
	submit_button(); 
}

/**
 * Admin callback function - Displays field of security status
 */
function spbc_field_security_status_callback(){
	
	echo "<hr /><h2 style='display: inline-block;'>".__('Security status:', 'security-malware-firewall')."</h2>";
	
	if(!is_main_site()){
		$spbc_network_settings = get_site_option( SPBC_NETWORK_SETTINGS );
		if($spbc_network_settings){
			$allow_custom_key = ($spbc_network_settings['allow_custom_key'] ? true : false);
			if(!$allow_custom_key){
				$key_is_ok = ($spbc_network_settings['key_is_ok'] == 1 ? true : false);
			}
		}else
			$key_is_ok = false;
	}else
		$allow_custom_key = true;
	
	if($allow_custom_key || is_main_site()){
		$spbc_data = get_option( SPBC_DATA );
		$key_is_ok = (isset($spbc_data['key_is_ok']) && $spbc_data['key_is_ok'] == 1 ? true : false);
	}
		
	$path_to_img = SPBC_PATH . "/images/";
	
	$img = $path_to_img."yes.png";
	$img_no = $path_to_img."no.png";
	$color="black";
	$test_failed=false;

	if($key_is_ok){
		$img = $path_to_img."yes.png";
		$img_no = $path_to_img."no.png";
		$color="black";
		$test_failed == true;
	}else{
		$img=$path_to_img."no.png";
		$img_no=$path_to_img."no.png";
		$color="black";
		$test_failed == false;
	}
	
	echo "<div style='color:$color; display: inline-block;'>";
		echo ' &nbsp; <img style="vertical-align: text-bottom" src="'.($key_is_ok ? $img : $img_no).'" alt=""  height="" /> '.__('Brute Force Protection', 'security-malware-firewall');
		echo ' &nbsp; <img style="vertical-align: text-bottom" src="'.($key_is_ok ? $img : $img_no).'" alt=""  height="" /> '.__('Security Report', 'security-malware-firewall');
		echo ' &nbsp; <img style="vertical-align: text-bottom" src="'.($key_is_ok ? $img : $img_no).'" alt=""  height="" /> '.__('Security Audit Log', 'security-malware-firewall');
		echo ' &nbsp; <img style="vertical-align: text-bottom" src="'.($key_is_ok ? $img : $img_no).'" alt=""  height="" /> '.__('FireWall', 'security-malware-firewall');
	echo "</div>";	
		
	//if(!$test_failed)
		//echo __("Testing is failed, check settings. Tech support <a target=_blank href='mailto:support@cleantalk.org'>support@cleantalk.org</a>", 'security-malware-firewall');
	
	echo "<br>";
	echo (isset($spbc_data['logs_last_sent'], $spbc_data['last_sent_events_count']) ? $spbc_data['last_sent_events_count'].' '.__('events have been sent to CleanTalk Cloud on', 'security-malware-firewall').' '.date("M d Y H:i:s", $spbc_data['logs_last_sent']).'.' : __('Unknow last logs sending time.', 'security-malware-firewall'));
	echo '<br />';
	echo (isset($spbc_data['last_firewall_send'], $spbc_data['last_firewall_send_count']) ? sprintf(__('Information about %d blocked entries have been sent to CleanTalk Cloud on %s.', 'security-malware-firewall'), $spbc_data['last_firewall_send_count'], date("M d Y H:i:s", $spbc_data['last_firewall_send'])) : __('Unknow last filrewall logs sending time.', 'security-malware-firewall'));
	echo '<br />';
	echo (isset($spbc_data['last_firewall_updated'], $spbc_data['firewall_entries']) ? sprintf(__('Security FireWall database has %d IPs. Last updated at %s.', 'security-malware-firewall'), $spbc_data['firewall_entries'], date('M d Y H:i:s', $spbc_data['last_firewall_updated'])) : __('Unknow last Security FireWall updating time.', 'security-malware-firewall'));
	
	echo "<h2><hr /></h2>";
}

/**
 * Admin callback function - Displays field of Api Key
 */
function spbc_field_key( $val ) {

	if(!is_main_site()){
		$spbc_network_settings = get_site_option( SPBC_NETWORK_SETTINGS );
		if($spbc_network_settings){
			$allow_custom_key = ($spbc_network_settings['allow_custom_key'] ? true : false);
			if(!$allow_custom_key){
				$current_key = ($spbc_network_settings['spbc_key'] ? $spbc_network_settings['spbc_key'] : '');
				$key_is_ok = ($spbc_network_settings['key_is_ok'] == 1 ? 'true' : 'false');
				$admin_email = get_site_option('admin_email');
				$site_url = get_site_option('siteurl');
			}else{
				
			}
		}else{
			$current_key = '';
			$key_is_ok = 'false';
			$allow_custom_key = false;
		}
	}else
		$allow_custom_key = true;
	
	if(is_main_site() || $allow_custom_key){
		$spbc_settings = get_option( SPBC_SETTINGS );
		$current_key = (isset($spbc_settings['spbc_key']) ? $spbc_settings['spbc_key'] : '');
		
		$spbc_data = get_option( SPBC_DATA );
		$key_is_ok = (isset($spbc_data['key_is_ok']) && $spbc_data['key_is_ok'] == 1 ? 'true' : 'false');
		
		$admin_email = get_option('admin_email');
		$site_url = get_option('siteurl');
	}
	
	echo "<script>
		var keyIsOk = $key_is_ok;
	</script>";
	
	$field_id = $val['id'];
	
	echo "<div class='spbc_wrapper_field'>";
	
	if($allow_custom_key || is_main_site()){
		if($key_is_ok == 'true'){
			echo "<input id='$field_id' name='spbc_settings[spbc_key]' size='20' type='text' value='".str_repeat('*', strlen($current_key))."' key='$current_key' style=\"font-size: 14pt;\" placeholder='" . __('Enter the key', 'security-malware-firewall') . "' />";
			echo "<a id='showHideLink' class='spbc-links' style='color:#666;' href='#'>".__('Show access key', 'security-malware-firewall')."</a>";
		}else{
			echo "<input id='$field_id' name='spbc_settings[spbc_key]' size='20' type='text' value='$current_key' style=\"font-size: 14pt;\" placeholder='" . __('Enter the key', 'security-malware-firewall') . "' />";
			echo "<br/><br/>";
			echo "<a target='_blank' href='https://cleantalk.org/register?platform=wordpress&email=".urlencode($admin_email)."&website=".urlencode(parse_url($site_url,PHP_URL_HOST))."&product_name=security' style='display: inline-block;'>
					<input type='button' class='spbc_auto_link' value='".__('Get access key manually', 'security-malware-firewall')."' />
				</a>";
			echo "&nbsp;".__('or', 'security-malware-firewall')."&nbsp;";
			echo '<input name="spbc_get_apikey_auto" type="submit" class="spbc_manual_link" value="' . __('Get access key automatically', 'security-malware-firewall') . '" />';
			echo "<br/><br/>";
			echo "<div style='font-size: 10pt; color: #666 !important'>" . sprintf(__('Admin e-mail (%s) will be used for registration', 'security-malware-firewall'), get_option('admin_email')) . "</div>";
			echo "<div style='font-size: 10pt; color: #666 !important'><a target='__blank' style='color:#BBB;' href='https://cleantalk.org/publicoffer'>" . __('License agreement', 'security-malware-firewall') . "</a></div>";
		}
	}else{
		_e("<h3>Key is provided by Super Admin.<h3>", "spbc");
	}
	
	echo "</div>";
	
}

function spbc_field_custom_key( $values ){
	echo "<div class='spbc_wrapper_field'>";
		echo "<input type='checkbox' id='".$values['id']."' name='spbc_settings[custom_key]' value='1' " . ($values['value'] == '1' ? 'checked' : '') . " />
		<label for='".$values['id']."'>".
			__('Allow users to use other key', 'security-malware-firewall').
		"</label>".
		"<div class='spbc_settings_description'>".
			__('Allow users to use different Access key in their plugin settings. They could use different CleanTalk account.', 'security-malware-firewall').
		"</div>";
	echo "</div>";
}

function spbc_field_show_link_login_form( $values ) {
	echo "<div class='spbc_wrapper_field'>
			<input type='checkbox' id='".$values['id']."' name='spbc_settings[show_link_in_login_form]' value='1' " . ($values['value'] == '1' ? 'checked' : '') . " />
			<label for='".$values['id']."'>" . __('Let them know about protection', 'security-malware-firewall') . "</label>
			<div class='spbc_settings_description'>".
				__('Place a warning under login form: "Brute Force Protection by CleanTalk security. All attempts are logged".', 'security-malware-firewall').
			"</div>";
		echo "</div>";
}

function spbc_field_complete_deactivation( $values ) {
		echo "<div class='spbc_wrapper_field'>".
			"<input type='checkbox' id='".$values['id']."' name='spbc_settings[complete_deactivation]' value='1' " . ($values['value'] == '1' ? 'checked' : '') . " />
			<label for='".$values['id']."'>" . __('Complete deactivation', 'security-malware-firewall') . "</label>
			<div class='spbc_settings_description'>".
				__('Leave no trace in Wordpress after deactivation. This could help if you do have problems with the plugin.', 'security-malware-firewall').
				(SPBC_WPMS ? " ".__('It affects ALL websites. Use it wisely!', 'security-malware-firewall') : '').
			"</div>";
		echo "</div>";
}

function spbc_field_set_cookies( $values ) {
		echo "<div class='spbc_wrapper_field'>".
			"<input type='checkbox' id='".$values['id']."' name='spbc_settings[set_cookies]' value='1' " . ($values['value'] == '1' ? 'checked' : '') . " />
			<label for='".$values['id']."'>" . __('Set cookies', 'security-malware-firewall') . "</label>
			<div class='spbc_settings_description'>".
				__('Turn this option of to deny plugin generates any cookies on website front-end. This option is helpful if you use Varnish or other caching solutions. But disabling will slow down FireWall a little.', 'security-malware-firewall').
				(SPBC_WPMS ? " ".__('It affects ALL websites. Use it wisely!', 'security-malware-firewall') : '').
			"</div>";
		echo "</div>";
}

// INACTIVE
function spbc_field_cleantalk_cp( $values ){
	echo "<input type='checkbox' id='".$values['id']."' name='spbc_settings[allow_ct_cp]' value='1' " . ($values['value'] == '1' ? 'checked' : '') . " /><label for='collect_details1'> " . __('Allow users to access to CleanTalk control panel from their Wordpress dashboard (only "read" access).', 'security-malware-firewall');
}

/**
 * Admin callback function - Sanitize settings
 */
function spbc_sanitize_settings( $settings ){
		
	$spbc_data = get_option( SPBC_DATA );
		
	//Checking the accepted key
	preg_match('/^(\s*)([a-z\d]*)(\s*)$/', $settings['spbc_key'], $matches);
	
	if($matches[2] == ''){
		$spbc_data['key_is_ok'] = false;
		$spbc_data['errors']['sent_error'] = '';
		$spbc_data['errors']['apikey'] = '';
	}else{
		$data = array(
			"method_name" => "notice_validate_key",
			"auth_key" => $matches[2],
			"path_to_cms" => preg_replace('/http[s]?:\/\//', '', get_option('siteurl'), 1)
		);
		
		require_once(SPBC_PLUGIN_DIR . 'inc/spbc-tools.php');
		$result = spbc_sendRawRequest(SPBC_API_URL, $data);

		$result = ($result != false ? json_decode($result, true): null);
		if($result){
			if(isset($result['error_message']) || isset($result['error_no'])){
				
				$spbc_data['errors']['apikey'] = date('M d Y H:i:s')." - ". sprintf(__('Error while checking the API key "%s" Error #%d Comment: %s.', 'security-malware-firewall'), $matches[2], $result['error_no'], $result['error_message']);
			}else{
				if($result['valid'] == '1' ){
					$spbc_data['key_is_ok'] = true;
					$spbc_data['errors']['apikey'] = '';
					//If key is ok, sending logs.
					$return_val = spbc_send_logs($matches[2]);
					if(!$return_val['result'])
						$spbc_data['errors']['sent_error'] = $return_val['error'];
					else{
						$spbc_data['logs_last_sent'] = time();
						$spbc_data['last_sent_events_count'] = $return_val['count'];
						$spbc_data['errors']['sent_error'] = '';
					}
				}else{
					$spbc_data['errors']['apikey'] = date('M d Y H:i:s')." - ".sprintf(__('Key is not valid. Key: %s.', 'security-malware-firewall'), $matches[2]);
					$spbc_data['key_is_ok'] = false;			
				}
			}
		}else{
			$spbc_data['errors']['apikey'] = __('Cleantalk spbc_sendRawRequest() returns "false" while checking access key. Possible reasons: Bad connection or cloud server error(less possible).', 'security-malware-firewall');
		}
	}
	
	if($spbc_data['key_is_ok'] == true){
		
		$result = spbc_security_firewall_update($matches[2]);
		$spbc_data['last_firewall_updated'] = time();
		$spbc_data['firewall_entries'] = $result;
		
		$result = spbc_send_firewall_logs($matches[2]);
		$spbc_data['last_firewall_send'] = time();
		$spbc_data['last_firewall_send_count'] = $result;
		
		$data = array(
			"method_name" => "notice_paid_till",
			"auth_key" => $matches[2],
		);
		require_once(SPBC_PLUGIN_DIR . 'inc/spbc-tools.php');
		$result = spbc_sendRawRequest(SPBC_API_URL, $data);
		$result = ($result != false ? json_decode($result, true): null);
		if($result){
			$spbc_data['user_token'] 	= $result['data']['user_token'];
			$spbc_data['notice_show']	= $result['data']['show_notice'];
			$spbc_data['notice_renew'] 	= $result['data']['renew'];
			$spbc_data['notice_trial'] 	= $result['data']['trial'];
		}
	}
	
	update_option(SPBC_DATA, $spbc_data);
	
	$settings['spbc_key'] = $matches[2];
	
	if(is_main_site()){
	
		$network_settings = array(
			'key_is_ok' => $spbc_data['key_is_ok'],
			'spbc_key' => $settings['spbc_key'],
			'user_token' => (!empty($spbc_data['user_token']) ? $spbc_data['user_token'] : ''),
			'allow_custom_key' => (isset($settings['custom_key']) ? $settings['custom_key'] : false),
			'allow_cleantalk_cp' => (isset($settings['allow_ct_cp']) ? $settings['allow_ct_cp'] : false)
		);
		
		update_site_option ( SPBC_NETWORK_SETTINGS, $network_settings);
	}
		
	return $settings;
}

/**
 * Admin callback function - Displays description of 'main' plugin parameters section
 */
 
function spbc_field_security_logs_callback(){
	global $wpdb, $spbc_tpl;
    
    include_once(SPBC_PLUGIN_DIR . "/templates/spbc_settings_main.php");

	$message_about_log = sprintf(__('The log includes list of attacks for past 24 hours and shows only last %d records. To see the full report please check the Daily security report in your Inbox (%s).', 'security-malware-firewall'),
		SPBC_LAST_ACTIONS_TO_VIEW,
		get_option('admin_email')
	);
	
    echo "<p class='spbc_hint'>$message_about_log</p>";
	
	$spbc_auth_logs_table = SPBC_DB_PREFIX . SPBC_LOG_TABLE;
		
    $sql = sprintf('SELECT id,datetime,user_login,page,page_time,event,auth_ip 
		FROM %s ' . 
		(SPBC_WPMS ? 'WHERE blog_id = '.get_current_blog_id() : '') . 
		' ORDER BY datetime DESC
		LIMIT %d;',
        $spbc_auth_logs_table,
        SPBC_LAST_ACTIONS_TO_VIEW
    );
		
    $rows = $wpdb->get_results($sql);
    $records_count = 0;
    if ($rows) {
        $records_count = count($rows);
    }
    
    if ($records_count) {
        $ips_data = '';
        foreach ($rows as $record) {
            if ($ips_data != '') {
                $ips_data .= ',';
            }
            $ips_data .= long2ip($record->auth_ip);
            
        }
		
        $ips_c = spbc_get_countries_by_ips($ips_data);
        $row_last_attacks = '';
        $ip_part = '';
		
		$i=0;
        foreach ($rows as $record) {
            $ip_dec = long2ip($record->auth_ip);
            $country_part = spbc_report_country_part($ips_c, $ip_dec);
            
            $user_id = null;
            $user = get_user_by('login', $record->user_login);
            $user_part = $record->user_login;
            if (isset($user->data->ID)) {
                $user_id = $user->data->ID;
                $url = admin_url() . '/user-edit.php?user_id=' . $user_id;
                $user_part = sprintf("<a href=\"%s\">%s</a>",
                    $url,
                    $record->user_login
                );
            }
			
			$page = ($record->page == NULL ? '-' : "<a href='".$record->page."' target='_blank'>".$record->page."</a>");
			
			$page_time = ($i==0 ? 'Calculating' : ($record->page_time == null ? 'Unknown' : strval($record->page_time)));
			$i++;
			
            $ip_part = sprintf("<a href=\"https://cleantalk.org/blacklists/%s\" target=\"_blank\">%s</a>,&nbsp;%s",
                $ip_dec, 
                $ip_dec, 
                $country_part
            );
			
            $row_last_attacks .= sprintf($spbc_tpl['row_last_attacks_tpl'],
                date("M d Y, H:i:s", strtotime($record->datetime)),
                $user_part, 
                $record->event, 
				$page,
				($record->event == 'view' ? $page_time : '-'),
                $ip_part
            );
        }
        $t_last_attacks = sprintf($spbc_tpl['t_last_attacks_tpl'],
            $row_last_attacks 
        );
        echo $t_last_attacks;
		
		$result = $wpdb->get_results("SELECT COUNT(*) as cnt FROM $spbc_auth_logs_table ".(SPBC_WPMS ? 'WHERE blog_id = '.get_current_blog_id() : '').";",
			ARRAY_A
		);
		$records_count = $result[0]['cnt'];
		
		if($records_count > SPBC_LAST_ACTIONS_TO_VIEW){
			echo "<div class='spbc_show_more_button_wrapper'>"
					."<h3 class='spbc_no_more_entries spbcDisplayNone'>"
						.__('That was the last entry.', 'security-malware-firewall')
					."</h3>"
					."<h3 class='spbc_show_cp_button'>"
						.__('Proceed to:', 'security-malware-firewall')."&nbsp;"
					."</h3>"
					."<a target='_blank' href='' id='spbc_cp_button' class='spbc_manual_link spbc_show_cp_button' style='display: none;''>"
						.__('Security Control Panel', 'security-malware-firewall')
					."</a>"
					."<h3 class='spbc_show_cp_button'>&nbsp;"
						.__('to see more.', 'security-malware-firewall')
					."</h3>"
					."<div id='spbc_show_more_button' class='spbc_manual_link'>"
						.__('Show more', 'security-malware-firewall')
					."</div>"
					."<img src='".SPBC_PATH."/images/preloader.gif' style='display: none;'/>"
				."</div>";
		}
		
    } else {
        printf(__("%s brute-force attacks have been made.", 'security-malware-firewall'), $records_count);
    }
}
 
function spbc_section_security_log_callback(){
}

function spbc_section_debug(){
	
}

function spbc_field_debug_drop(){
	echo "<div class='spbc_wrapper_field'>";
		echo "<br>";
		echo "<input type='submit' name='spbc_drop_debug' value='Drop debug data' />"
		."<div class='spbc_settings_description'>If you don't what is this just push the button =)</div>";
	echo "</div>";
}

function spbc_field_debug(){
	$debug = get_option(SPBC_DEBUG);
	$output = print_r($debug, true);
	$output = str_replace("\n", "<br>", $output);
	$output = preg_replace("/[^\S]{4}/", "&nbsp;&nbsp;&nbsp;&nbsp;", $output);
	echo "<div class='spbc_wrapper_field'>";
		echo $output
		."<label for=''>".
			
		"</label>".
		"<div class='spbc_settings_description'>".
			
		"</div>";
	echo "</div>";
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
 * Initiate session
*/
function spbc_init_session() {

    $session_id = session_id(); 
    if(empty($session_id) && !headers_sent()) {
        $result = @session_start();
        if(!$result){
            session_regenerate_id(true);
            @session_start(); 
        }
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
?>
