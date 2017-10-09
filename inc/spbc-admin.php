<?php

/**
 * Admin action 'admin_init' - Add the admin settings and such
 */
function spbc_admin_init() {
	
	global $spbc;
	
	//Update logic
	if($spbc->spbc_installing){
		$spbc->deleteOption('spbc_installing');
		$spbc->data['plugin_version'] = SPBC_VERSION;
		$spbc->save('data');
		$spbc->save('settings'); // Saving default settings
	}else{
		$current_version = (isset($spbc->data['plugin_version']) ? $spbc->data['plugin_version'] : '1.0.0');
			
		if($current_version != SPBC_VERSION){
			if(is_main_site()){
				require_once(SPBC_PLUGIN_DIR . 'inc/spbc-updater.php');
				spbc_run_update_actions($current_version, SPBC_VERSION);
			}
			$spbc->data['notice_were_updated'] = (isset($spbc->data['plugin_version']) ? true : false); //Flag - plugin were updated
			$spbc->data['plugin_version'] = SPBC_VERSION;
			$spbc->save('data');
		}
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
			$spbc->data['errors']['get_key'] = $result;
			$spbc->save('data');
			
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
	
	add_action('wp_ajax_spbc_show_more_security_logs',          'spbc_show_more_security_logs_callback');
	add_action('wp_ajax_spbc_show_more_security_firewall_logs', 'spbc_show_more_security_firewall_logs_callback');
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
		$link = sprintf('<a  target="_blank" href="https://cleantalk.org/my/bill/security?cp_mode=security&utm_source=wp-backend&utm_medium=cpc&utm_campaign=WP%%20backend%%20trial_security&user_token=%s">%s</a>', $spbc->user_token, $button);
		
		echo '<div class="error" style="position: relative;">
				<h3 style="margin: 10px;">
					<u>'.$plugin_settings_link.'</u>: '
					. __('trial period ends, please upgrade to premium version to keep your site secure and safe!', 'security-malware-firewall').
				'</h3>'.
				$link.
				'<br><br>
			</div>';
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
	
	// For ALL admin pages
	wp_enqueue_style ('spbc_admin_css', SPBC_PATH . '/assets/css/spbc-admin.css', array(), SPBC_VERSION, 'all');
	wp_enqueue_script('spbc_admin_js',  SPBC_PATH . '/assets/js/spbc-admin.js',   array('jquery'), SPBC_VERSION, false);

	// For settings page
	if($hook == 'settings_page_spbc'){
		
		$debug = get_option( SPBC_DEBUG );
		
		$ajax_nonce = wp_create_nonce("spbc_secret_nonce");
		
		wp_enqueue_style ('spbc-settings-css', SPBC_PATH . '/assets/css/spbc-settings.css', array(),         SPBC_VERSION, 'all');
		wp_enqueue_script('spbc-settings-js',  SPBC_PATH . '/assets/js/spbc-settings.js',   array('jquery'), SPBC_VERSION, false);
		wp_enqueue_script('jquery-ui',         SPBC_PATH . '/assets/js/jquery-ui.min.js',   array('jquery'), '1.12.1',     false);
		
		wp_localize_script('jquery', 'spbcSettings', array(
			'ajax_nonce'               => $ajax_nonce,
			'ajaxurl'                  => admin_url('admin-ajax.php'),
			'debug'                    => !empty($debug) ? true : false
		));
		
		wp_localize_script('jquery', 'spbcSettingsSecLogs', array(
			'start_nubmer'             => SPBC_LAST_ACTIONS_TO_VIEW,
			'show_entries'             => SPBC_LAST_ACTIONS_TO_VIEW,
		));
		
		wp_localize_script('jquery', 'spbcSettingsFWLogs', array(
			'start_nubmer'             => SPBC_LAST_ACTIONS_TO_VIEW,
			'show_entries'             => SPBC_LAST_ACTIONS_TO_VIEW,
		));
	}
}

/**
 * Admin action 'admin_menu' - Add the admin options page
 */
function spbc_admin_add_page() {
	
	global $spbc;
	
	//Adding setting page
	if(is_network_admin())
		add_submenu_page("settings.php", __( SPBC_NAME . ' Settings', 'security-malware-firewall'), SPBC_NAME, 'manage_options', 'spbc', 'spbc_settings_page');
	else
		add_options_page(                __( SPBC_NAME . ' Settings', 'security-malware-firewall'), SPBC_NAME, 'manage_options', 'spbc', 'spbc_settings_page');
	
	//Adding setting menu
    register_setting(SPBC_SETTINGS, SPBC_SETTINGS, 'spbc_sanitize_settings');
	
	//Adding menu sections
	add_settings_section('spbc_section_status',              '', 'spbc_section_security_status', 'spbc');
	add_settings_section('spbc_key_section',                 '', 'spbc_section_key',             'spbc');
	add_settings_section('spbc_security_section',            '', 'spbc_section_security',        'spbc');
	add_settings_section('spbc_misc_section',                '', 'spbc_section_misc',            'spbc');
	add_settings_section('spbc_security_log_section',        '', 'spbc_section_security_log',    'spbc');
	add_settings_section('spbc_traffic_control_log_section', '', 'spbc_section_traffic_control', 'spbc');
	add_settings_section('spbc_debug_section',               '', 'spbc_section_debug',           'spbc');
	add_settings_section('spbc_save_button_section',         '', 'spbc_section_save_button',     'spbc');
	
	//Adding fields
	
	// Status section
		// Security status field
		add_settings_field('spbc_security_status', '', 'spbc_field_security_status', 'spbc', 'spbc_section_status');
	
	// Debug section
		// Debug drop
		add_settings_field('spbc_debug_drop', '', 'spbc_field_debug_drop', 'spbc', 'spbc_debug_section');
		// Debug data
		add_settings_field('spbc_debug', '', 'spbc_field_debug', 'spbc', 'spbc_debug_section');
	
	// Key section
		//Key field
		add_settings_field('spbc_apikey', '', 'spbc_field_key', 'spbc', 'spbc_key_section',
			array(
				'id' => 'spbc_key',
				'class' => 'spbc-key-section'
			)
		);
		
		//Allow custom key for WPMS field
		if(is_main_site() && SPBC_WPMS){
			add_settings_field('spbc_allow_custom_key', '', 'spbc_field_custom_key', 'spbc', 'spbc_key_section',
				array(
					'id' => 'custom_key',
					'class' => 'spbc-key-section',
					'value' => (isset($spbc->allow_custom_key) ? $spbc->allow_custom_key : false)
				)
			);
		}
		
	// Traffic control
		// Enable TC
		add_settings_field('spbc_traffic_control_enabled', '', 'spbc_field_traffic_control_enabled', 'spbc', 'spbc_security_section', 
			array(
				'id' => 'spbc_traffic_control_enabled',
				'class' => 'spbc-settings-section',
				'value' => (isset($spbc->settings['traffic_control_enabled']) ? $spbc->settings['traffic_control_enabled'] : false),
				'enabled' => ($spbc->key_is_ok ? true : false)
			)
		);
		// TC amount of request to block 
		add_settings_field('spbc_traffic_control_autoblock_requests_amount', '', 'spbc_field_traffic_control_autoblock_requests_amount', 'spbc', 'spbc_security_section', 
			array(
				'id' => 'spbc_option_traffic_control',
				'class' => 'spbc-settings-section spbc_short_text_field',
				'value' => (isset($spbc->settings['traffic_control_autoblock_amount']) ? $spbc->settings['traffic_control_autoblock_amount'] : 1000),
				'enabled' => (isset($spbc->spbc_settings['traffic_control_enabled']) && $spbc->key_is_ok ? $spbc->settings['traffic_control_enabled'] : false)
			)
		);
	
	// Miscellaneous section
		//Show link in registration form field
		add_settings_field('spbc_show_link_in_login_form', '', 'spbc_field_show_link_login_form', 'spbc', 'spbc_misc_section', 
			array(
				'id' => 'spbc_option_show_link_in_login_form',
				'class' => 'spbc-settings-section',
				'value' => (isset($spbc->settings['show_link_in_login_form']) ? $spbc->settings['show_link_in_login_form'] : false)
			)
		);
		
		// Settings Only for main blog
		if(is_main_site()){
			
			// Set cookies
			add_settings_field('spbc_set_cookies', '', 'spbc_field_set_cookies', 'spbc', 'spbc_misc_section', 
				array(
					'id' => 'spbc_option_set_cookies',
					'class' => 'spbc-settings-section',
					'value' => (isset($spbc->settings['set_cookies']) ? $spbc->settings['set_cookies'] : false)
				)
			);
			
			// Complete deactivation
			add_settings_field('spbc_complete_deactivation', '', 'spbc_field_complete_deactivation', 'spbc', 'spbc_misc_section', 
				array(
					'id' => 'spbc_option_complete_deactivation',
					'class' => 'spbc-settings-section',
					'value' => (isset($spbc->settings['complete_deactivation']) ? $spbc->settings['complete_deactivation'] : false)
				)
			);
		}
	
	// Security log section
		//Security log field
		add_settings_field('spbc_security_logs', '', 'spbc_field_security_logs', 'spbc', 'spbc_security_log_section',
			array(
				'id' => 'spbc_option_security_logs',
				'class' => 'spbc-settings-section',
				'user_token' => $spbc->user_token,
			)
		);
		
	// Traffic control section
		//Traffic control field
		add_settings_field('spbc_traffic_control_log', '', 'spbc_field_traffic_control_log', 'spbc', 'spbc_traffic_control_section',
			array(
				'key_is_ok' => $spbc->key_is_ok,
				'user_token' => $spbc->user_token,
				'allow_custom_key' => $spbc->allow_custom_key
			)
		);
}

/**
 * Admin callback function - Displays plugin options page
 */
function spbc_settings_page() {
	
	global $spbc, $spbc_tpl;
		
	if(is_network_admin()){
		$link = get_site_option('siteurl').'wp-admin/options-general.php?page=spbc';
		printf("<h2>" . __("Please, enter the %splugin settings%s in main site dashboard.", 'security-malware-firewall') . "</h2>", "<a href='$link'>", "</a>");
		return;
	}
	
	$debug = get_option( SPBC_DEBUG );
	echo !empty($debug) ? "<script>var spbc_debug = true;</script>" : '';
			
	// If have error message.	
	// Output error block
	
	if(!empty($spbc->data['errors'])){
		
		$errors = $spbc->data['errors'];
		
		if(!empty($errors['apikey'])){
			$errors['apikey'] = date('Y-m-d H:i:s', $errors['apikey']['error_time']) . ': ' . __('Error occured while API key validating. Error: ', 'security-malware-firewall') . $errors['apikey']['error_string'];
		}
		
		// if(empty($spbc->settings['spbc_key']) || $spbc->key_is_ok == false){
			// $errors['apikey'] = __("API key is not valid. Use the buttons below to get API key.", 'security-malware-firewall')."<br>"; 
		// }
		
		if(!empty($errors['get_key']))
			$errors['get_key']  = date('Y-m-d H:i:s', $errors['get_key']['error_time'])  . ': ' . __('Error occured while automatically gettings access key. Error: ', 'security-malware-firewall') . $errors['get_key']['error_string'];		
		if(!empty($errors['sec_logs']))
			$errors['sec_logs']  = date('Y-m-d H:i:s', $errors['sec_logs']['error_time'])  . ': ' . __('Error occured while sending sending security logs. Error: ', 'security-malware-firewall') . $errors['sec_logs']['error_string'];
		if(!empty($errors['fw_logs']))
			$errors['fw_logs']   = date('Y-m-d H:i:s', $errors['fw_logs']['error_time']) . ': ' . __('Error occured while sending sending firewall logs. Error: ', 'security-malware-firewall') . $errors['fw_logs']['error_string'];
		if(!empty($errors['fw_update']))
			$errors['fw_update'] = date('Y-m-d H:i:s', $errors['fw_update']['error_time']) . ': ' . __('Error occured while updating firewall. Error: '            , 'security-malware-firewall') . $errors['fw_update']['error_string'];
		
		echo '<div id="spbcTopWarning" class="error" style="position: relative;">'
			.'<h3>'.__('Errors:', 'security-malware-firewall').'</h3>';
			foreach($errors as $value)
				echo '<h4>'.$value.'</h4>';
		echo '</div>';
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
					<?php if($debug){ echo "<h2 id='spbc_debug-control' class='spbc_tab_nav'>Debug</h2>"; } ?>
					<h2 id='spbc_general-control' class='spbc_tab_nav spbc_tab_nav-active'><?php _e('General Settings', 'security-malware-firewall'); ?></h2>
					<h2 id='spbc_security_log-control' class='spbc_tab_nav'><?php _e('Security Log', 'security-malware-firewall'); ?></h2>
					<?php if(!empty($spbc->settings['traffic_control_enabled']) && is_main_site()){ ?>
						<h2 id='spbc_traffic_control-control' class='spbc_tab_nav'><?php _e('Traffic control', 'security-malware-firewall'); ?></h2>
					<?php } ?>
					<?php if($spbc->key_is_ok){?>
					<div id='goToCleanTalk' class='spbc-div-2' style='display: inline-block; position: relative; top: -2px; left: 8px; margin-right: 7px;'>
						<a disabled id='goToCleanTalkLink' class='spbc_manual_link' target='_blank' href='https://cleantalk.org/my?user_token=<?php echo $spbc->user_token ?>&cp_mode=security'><?php _e('Security Control Panel', 'security-malware-firewall'); ?></a>
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
					<?php if(is_main_site()){ ?>
					<div class='spbc_tab_fields_group'>
						<h3 class='spbc_group_header'><?php _e('Security', 'security-malware-firewall'); ?></h3>
							<?php do_settings_fields('spbc', 'spbc_security_section'); ?>
					</div>
					<?php } ?>
					<div class='spbc_tab_fields_group'>
						<h3 class='spbc_group_header'><?php _e('Miscellaneous', 'security-malware-firewall'); ?></h3>
							<?php do_settings_fields('spbc', 'spbc_misc_section'); ?>
					</div>
				</div>
				<div class='spbc_tab' id='spbc_security_log'>
					<div class='spbc_tab_fields_group'>
						<div class='spbc_wrapper_field'>
								<?php do_settings_fields('spbc', 'spbc_security_log_section'); ?>
						</div>
					</div>
				</div>
				<?php if(!empty($spbc->settings['traffic_control_enabled'])){ ?>
				<div class='spbc_tab' id='spbc_traffic_control'>
					<div class='spbc_tab_fields_group'>
						<div class='spbc_wrapper_field'>
								<?php do_settings_fields('spbc', 'spbc_traffic_control_section'); ?>
						</div>
					</div>
				</div>
				<?php } ?>
				<?php if($debug){ ?>
				<div class='spbc_tab' id='spbc_debug'>
					<div class='spbc_tab_fields_group'>
						<div class='spbc_wrapper_field'>
								<?php do_settings_fields('spbc', 'spbc_debug_section'); ?>
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

// function spbc_section_security_status(){}
// function spbc_section_key(){}
// function spbc_section_security(){}
// function spbc_section_misc(){}
// function spbc_section_security_log(){}
// function spbc_section_traffic_control(){}
// function spbc_section_debug(){}
// function spbc_section_save_button(){
	// submit_button(); 
// }

/**
 * Admin callback function - Displays field of security status
 */
function spbc_field_security_status(){
	
	global $spbc;
	
	echo "<hr /><h2 style='display: inline-block;'>".__('Security status:', 'security-malware-firewall')."</h2>";
	
	$path_to_img = SPBC_PATH . "/images/";
	
	$img = $path_to_img."yes.png";
	$img_no = $path_to_img."no.png";
	$color="black";
	$test_failed=false;

	if($spbc->key_is_ok){
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
		echo ' &nbsp; <img style="vertical-align: text-bottom" src="'.($spbc->key_is_ok ? $img : $img_no).'" alt=""  height="" /> '.__('Brute Force Protection', 'security-malware-firewall');
		echo ' &nbsp; <img style="vertical-align: text-bottom" src="'.($spbc->key_is_ok ? $img : $img_no).'" alt=""  height="" /> '.__('Security Report', 'security-malware-firewall');
		echo ' &nbsp; <img style="vertical-align: text-bottom" src="'.($spbc->key_is_ok ? $img : $img_no).'" alt=""  height="" /> '.__('Security Audit Log', 'security-malware-firewall');
		echo ' &nbsp; <img style="vertical-align: text-bottom" src="'.($spbc->key_is_ok ? $img : $img_no).'" alt=""  height="" /> '.__('FireWall', 'security-malware-firewall');
	echo "</div>";	
		
	//if(!$test_failed)
		//echo __("Testing is failed, check settings. Tech support <a target=_blank href='mailto:support@cleantalk.org'>support@cleantalk.org</a>", 'security-malware-firewall');
	
	echo "<br>";
	echo (isset($spbc->data['logs_last_sent'], $spbc->data['last_sent_events_count']) ? $spbc->data['last_sent_events_count'].' '.__('events have been sent to CleanTalk Cloud on', 'security-malware-firewall').' '.date("M d Y H:i:s", $spbc->data['logs_last_sent']).'.' : __('Unknow last logs sending time.', 'security-malware-firewall'));
	echo '<br />';
	echo (isset($spbc->data['last_firewall_send'], $spbc->data['last_firewall_send_count']) ? sprintf(__('Information about %d blocked entries have been sent to CleanTalk Cloud on %s.', 'security-malware-firewall'), $spbc->data['last_firewall_send_count'], date("M d Y H:i:s", $spbc->data['last_firewall_send'])) : __('Unknow last filrewall logs sending time.', 'security-malware-firewall'));
	echo '<br />';
	echo (isset($spbc->data['last_firewall_updated'], $spbc->data['firewall_entries']) ? sprintf(__('Security FireWall database has %d IPs. Last updated at %s.', 'security-malware-firewall'), $spbc->data['firewall_entries'], date('M d Y H:i:s', $spbc->data['last_firewall_updated'])) : __('Unknow last Security FireWall updating time.', 'security-malware-firewall'));
	
	echo "<h2><hr /></h2>";
}

/**
 * Admin callback function - Displays field of Api Key
 */
function spbc_field_key( $val ) {

	global $spbc;
	
	echo '<script>
		var keyIsOk = '.$spbc->key_is_ok.';
	</script>';
		
	echo "<div class='spbc_wrapper_field'>";
	
	if($spbc->allow_custom_key || is_main_site()){
		
		if($spbc->key_is_ok){
			
			echo '<input id="'.$val['id'].'" name="spbc_settings[spbc_key]" size="20" type="text" value="'.str_repeat('*', strlen($spbc->settings['spbc_key'])).'" key="'.$spbc->settings['spbc_key'].'" style="font-size: 14pt;" placeholder="' . __('Enter the key', 'security-malware-firewall') . '" />';
			echo '<a id="showHideLink" class="spbc-links" style="color:#666;" href="#">'.__('Show access key', 'security-malware-firewall').'</a>';
			
		}else{
			
			echo '<input id="'.$val['id'].'" name="spbc_settings[spbc_key]" size="20" type="text" value="'.$spbc->settings['spbc_key'].'" style=\'font-size: 14pt;\' placeholder="' . __('Enter the key', 'security-malware-firewall') . '" />';
			echo '<br/><br/>';
			echo '<a target="_blank" href="https://cleantalk.org/register?platform=wordpress&email='.urlencode(get_option('admin_email')).'&website='.urlencode(parse_url(get_option('siteurl'), PHP_URL_HOST)).'&product_name=security" style="display: inline-block;">
					<input type="button" class="spbc_auto_link" value="'.__('Get access key manually', 'security-malware-firewall').'" />
				</a>';
			echo '&nbsp;'.__('or', 'security-malware-firewall').'&nbsp;';
			echo '<input name="spbc_get_apikey_auto" type="submit" class="spbc_manual_link" value="' . __('Get access key automatically', 'security-malware-firewall') . '" />';
			echo '<br/><br/>';
			echo '<div style="font-size: 10pt; color: #666 !important">' . sprintf(__('Admin e-mail (%s) will be used for registration', 'security-malware-firewall'), get_option('admin_email')) . '</div>';
			echo '<div style="font-size: 10pt; color: #666 !important"><a target="__blank" style="color:#BBB;" href="https://cleantalk.org/publicoffer">' . __('License agreement', 'security-malware-firewall') . '</a></div>';
		}
		
	}else{
		_e('<h3>Key is provided by Super Admin.<h3>', 'spbc');
	}
	
	echo '</div>';
	
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
 * Admin callback function - Displays description of 'main' plugin parameters section
 */
 
function spbc_field_security_logs($value){
	
	global $wpdb, $spbc_tpl;
    
    include_once(SPBC_PLUGIN_DIR . 'templates/spbc_settings_main.php');

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
					."<h3 class='spbc_show_cp_button'>"
						.__('Proceed to:', 'security-malware-firewall')."&nbsp;"
					."</h3>"
					."<a target='_blank' href='' class='spbc_manual_link spbc_show_cp_button spbc_cp_button' style='display: none;''>"
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

function spbc_field_traffic_control_enabled( $values ){
	echo "<div class='spbc_wrapper_field'>";
		echo '<input type="checkbox" id="'.$values['id'].'" name="spbc_settings[traffic_control_enabled]" value="1" ' 
			.($values['value'] == '1' ? ' checked' : '')
			.($values['enabled'] ? '' : ' disabled').' onclick="spbcSettingsDependencies(\'spbc_option_traffic_control\')"/>'
		.'<label for="'.$values['id'].'">'.
			__('Traffic control', 'security-malware-firewall').'<sup class="spbc_new">&nbsp;New!</sup>'.
		'</label>'.
		'<div class="spbc_settings_description">'.
			__('Traffic control shows visits and hits on the web site. Allows you ban any visitor or hole a country or a network.', 'security-malware-firewall').
		'</div>';
	echo '</div>';
}

function spbc_field_traffic_control_autoblock_requests_amount( $values ){
	echo "<div class='spbc_wrapper_field'>";
		echo "<input type='text' id='{$values['id']}' class='{$values['class']}' name='spbc_settings[traffic_control_autoblock_amount]' value='{$values['value']}' ". ($values['enabled'] ? '' : 'disabled=\'disabled\'') . " />
		<label for='{$values['id']}'>".
			__('Block user after requests amount more than', 'security-malware-firewall').
		"</label>".
		"<div class='spbc_settings_description'>".
			__('Traffic control shows visits and hits on the web site. Allows you ban any visitor or hole a country or a network.', 'security-malware-firewall').
		"</div>";
	echo "</div>";
}

function spbc_field_traffic_control_log($value){
	
	global $wpdb, $spbc_tpl;
	
	$user_token = $value['key_is_ok'] && ($value['allow_custom_key'] || is_main_site()) ? $value['user_token'] : false;
	
	$sql = sprintf('SELECT entry_id, ip_entry, entry_timestamp, allowed_entry, blocked_entry, status, page_url, http_user_agent
		FROM %s ' . 
		' ORDER BY entry_timestamp DESC
		LIMIT %d;',
        SPBC_DB_PREFIX . SPBC_FIREWALL_LOG,
        SPBC_LAST_ACTIONS_TO_VIEW
    );
    $rows = $wpdb->get_results($sql, ARRAY_A);
	
	echo '<p class="spbc_hint" style="display: inline-block;">';
		if(empty($rows)){
			echo "Local log is empty.";
			echo '<script>var spbcNewestEntryTimestamp = 0;</script>';
		}else{
			printf(__('The log shows list of access attempts for past hour and shows only last %d records.', 'security-malware-firewall'),
				SPBC_LAST_ACTIONS_TO_VIEW
			);
		}
		if($user_token){
			echo ' '.__('To see full report visit', 'security-malware-firewall')
			.' <a target="_blank" href="https://cleantalk.org/my?user_token='.$user_token.'&cp_mode=security">'
				.__('Security Control Panel', 'security-malware-firewall')
			.'</a>.';
		}
	echo '</p>';
	
	if(empty($rows))
		return;	
	
	echo '<script>var spbcNewestEntryTimestamp = '.$rows[0]['entry_timestamp'].';</script>';
	
	$ips = '';
	foreach($rows as $entry){
		$ips .= ($ips == '' ? $entry['ip_entry'] : ','.$entry['ip_entry']);
	}
    $ip_countries = spbc_get_countries_by_ips($ips);
	
	$data = '';
	foreach($rows as $entry){
		
		$data .= '<tr class="spbc_fw_log_string" entry_id="'.$entry['entry_id'].'">';
		
			$data .= '<td><a href="https://cleantalk.org/blacklists/'.$entry['ip_entry'].'" target="_blank">'.$entry['ip_entry'].'</a></td>';
			$data .= '<td>'.spbc_report_country_part($ip_countries, $entry['ip_entry']).'</td>';
			$data .= '<td class="spbc_fw_log_date">'.date('M d Y, H:i:s', $entry['entry_timestamp']).'</td>';
			$data .= '<td class="spbcTextCenter spbc_fw_log_entries">'
				.($entry['allowed_entry'] ? '<b class="spbcGreen">'.$entry['allowed_entry'].'</b>' : 0).' / '
				.($entry['blocked_entry'] ? '<b class="spbcRed">'.$entry['blocked_entry'].'</b>' : 0)
			.'</td>';
			$data .=  '<td class="spbcTextCenter spbc_fw_log_status">';
			switch($entry['status']){
				case 'PASS':              $data .= '<span class="spbcGreen">' . __('Passed', 'security-malware-firewall').'</span>';                           break;
				case 'PASS_BY_WHITELIST': $data .= '<span class="spbcGreen">' . __('Whitelisted', 'security-malware-firewall').'</span>';                      break;
				case 'DENY':              $data .= '<span class="spbcRed">'   . __('Blacklisted', 'security-malware-firewall').'</span>';                      break;
				case 'DENY_BY_NETWORK':	  $data .= '<span class="spbcRed">'   . __('Blocked, Hazardous network', 'security-malware-firewall').'</span>';       break; 
				case 'DENY_BY_DOS':       $data .= '<span class="spbcRed">'   . __('Blocked by DOS prevertion system', 'security-malware-firewall').'</span>'; break;
				default:                  $data .= __('Unknown', 'security-malware-firewall');                                                                 break;
			}
			$data .=  '</td>';
			$data .= '<td class="spbc_fw_log_url">'
				.(strlen($entry['page_url']) >= 60
					 ? '<span class="spbcShortText">'.substr($entry['page_url'], 0, 60).'...</span>'
					  .'<span class="spbcFullText spbcDisplayNone">'.$entry['page_url'].'</span>'
					 : $entry['page_url']
				)
			.'</td>';
			$data .= '<td>'
				.(strlen($entry['http_user_agent']) >= 60
					? '<span class="spbcShortText">'.substr($entry['http_user_agent'], 0, 60).'...</span>'
					 .'<span class="spbcFullText spbcDisplayNone">'.$entry['http_user_agent'].'</span>'
					: $entry['http_user_agent']
				)
			.'</td>';
			
		$data .= '</tr>';
	} unset($rows, $entry, $key, $value);
	
	printf($spbc_tpl['t_traffic_control'], $data);
	
	if($user_token){
		echo "<div class='spbc_show_more_button_wrapper'>"
			."<h3 class='spbc_show_cp_button'>"
				.__('Proceed to:', 'security-malware-firewall')."&nbsp;"
			."</h3>"
			."<a target='_blank' href='https://cleantalk.org/my?user_token' class='spbc_manual_link spbc_show_cp_button spbc_cp_button' style='display: none;''>"
				.__('Security Control Panel', 'security-malware-firewall')
			."</a>"
			."<h3 class='spbc_show_cp_button'>&nbsp;"
				.__('to see more.', 'security-malware-firewall')
			."</h3>"
			."<div id='spbc_show_more_fw_logs_button' class='spbc_manual_link'>"
				.__('Show more', 'security-malware-firewall')
			."</div>"
			."<img src='".SPBC_PATH."/images/preloader.gif' style='display: none;'/>"
		."</div>";
	}
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

/**
 * Admin callback function - Sanitize settings
 */
function spbc_sanitize_settings( $settings ){
	
	global $spbc;
	
	//Sanitizing traffic_control_autoblock_amount setting
	if(isset($settings['traffic_control_autoblock_amount'])){
		$settings['traffic_control_autoblock_amount'] = floor(intval($settings['traffic_control_autoblock_amount']));
		$settings['traffic_control_autoblock_amount'] = ($settings['traffic_control_autoblock_amount'] == 0  ? 1000 : $settings['traffic_control_autoblock_amount']);
		$settings['traffic_control_autoblock_amount'] = ($settings['traffic_control_autoblock_amount'] <  20 ? 20   : $settings['traffic_control_autoblock_amount']);
	}
	
	//Checking the accepted key
	preg_match('/^(\s*)([a-z\d]*)(\s*)$/', !empty($settings['spbc_key']) ? $settings['spbc_key'] : '', $matches);
	
	if($matches[2] == ''){
		$spbc->data['key_is_ok'] = false;
		unset(
			$spbc->data['notice_show'],
			$spbc->data['notice_renew'],
			$spbc->data['notice_trial'],
			$spbc->data['notice_were_updated'],
			$spbc->data['user_token'],
			$spbc->data['service_id']
		);
	}else{
		
		//Clearing all errors
		$spbc->data['errors'] = array();
		
		$result = SpbcHelper::noticeValidateKey($matches[2], preg_replace('/http[s]?:\/\//', '', get_option('siteurl'), 1));
		
		if(empty($result['error'])){
				
			if($result['valid'] == '1' ){
				$spbc->data['key_is_ok'] = true;
			}else{
				$spbc->data['errors']['apikey']['error_string'] = sprintf(__('Key is not valid. Key: %s.', 'security-malware-firewall'), $matches[2]);
				$spbc->data['errors']['apikey']['error_time']   = time();
				unset(
					$spbc->data['notice_show'],
					$spbc->data['notice_renew'],
					$spbc->data['notice_trial'],
					$spbc->data['notice_were_updated'],
					$spbc->data['user_token'],
					$spbc->data['service_id']
				);
				$spbc->data['key_is_ok'] = false;
			}
			
		}else{
			$spbc->data['errors']['apikey']['error_string'] = sprintf(__('Error occured while checking the API key. Error: %s', 'security-malware-firewall'), $result['error_string']);
			$spbc->data['errors']['apikey']['error_time']   = time();
		}
	}
	
	// If key is ok
	if($spbc->data['key_is_ok'] == true){
		
		// Sending logs.
		$result = spbc_send_logs($matches[2]);		
		if(empty($result['error'])){
			$spbc->data['logs_last_sent'] = time();
			$spbc->data['last_sent_events_count'] = $result;
		}else{
			if($result['error_string'] != 'NO_LOGS_TO_SEND')
				$spbc->data['errors']['sec_logs'] = $result;
		}
		
		// Updating FW
		$result = spbc_security_firewall_update($matches[2]);
		if(empty($result['error'])){
			$spbc->data['last_firewall_updated'] = time();
			$spbc->data['firewall_entries']      = $result;
		}else{
			$spbc->data['errors']['fw_update']   = $result;
		}
		
		// Sending FW logs
		$result = spbc_send_firewall_logs($matches[2]);
		if(empty($result['error'])){
			$spbc->data['last_firewall_send'] = time();
			$spbc->data['last_firewall_send_count'] = $result;
		}else{
			if($result['error_string'] != 'NO_LOGS_TO_SEND')
				$spbc->data['errors']['fw_logs'] = $result;
		}
		
		// Checking account status
		$result = SpbcHelper::noticePaidTill($matches[2]);
		if(empty($result['error'])){
			if(isset($result['user_token'])) $spbc->data['user_token'] = $result['user_token'];
			$spbc->data['notice_show']	= $result['show_notice'];
			$spbc->data['notice_renew'] = $result['renew'];
			$spbc->data['notice_trial'] = $result['trial'];
			$spbc->data['service_id']   = $result['service_id'];
		}
		
	}else{
		$settings['traffic_control_enabled'] = 0;
	}
	
	$spbc->save('data');
	
	$settings['spbc_key'] = $matches[2];
	
	if(SPBC_WPMS && is_main_site()){
			
		$spbc->network_settings = array(
			'key_is_ok'          => $spbc->data['key_is_ok'],
			'spbc_key'           => $settings['spbc_key'],
			'user_token'         => isset($spbc->data['user_token']) ? $spbc->data['user_token'] : '',
			'allow_custom_key'   => isset($settings['custom_key'])   ? $settings['custom_key']   : false,
			'allow_cleantalk_cp' => isset($settings['allow_ct_cp'])  ? $settings['allow_ct_cp']  : false,
			'service_id'         => isset($spbc->data['service_id']) ? $spbc->data['service_id'] : ''
		);
		$spbc->saveNetworkSettings();
	}
	
	return $settings;
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

function spbc_show_more_security_logs_callback(){
	
	check_ajax_referer('spbc_secret_nonce', 'security');
	
	global $spbc, $wpdb;
	
	$start = intval($_POST['start_nubmer']);
	$amount = intval($_POST['show_entries']);
	
	$result = $wpdb->get_results(
		"SELECT 
			*
			FROM ".SPBC_DB_PREFIX.SPBC_LOG_TABLE." "
			.(SPBC_WPMS ? 'WHERE blog_id = '.get_current_blog_id().' ' : '').
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
		'user_token' => $spbc->allow_custom_key ? $spbc->user_token : 0
	);
	$to_return = json_encode($to_return);
	echo  $to_return;
	die();
}

function spbc_show_more_security_firewall_logs_callback(){
	
	check_ajax_referer('spbc_secret_nonce', 'security');
	
	global $spbc, $wpdb;
	
	$amount = intval($_POST['show_entries']);
	
	$rows = $wpdb->get_results(
		"SELECT entry_id, ip_entry, entry_timestamp, allowed_entry, blocked_entry, status, page_url, http_user_agent
		FROM ".SPBC_DB_PREFIX.SPBC_FIREWALL_LOG."
		ORDER BY entry_timestamp DESC
		LIMIT 0, ".$amount.";",
		ARRAY_A
	);
	
	$text = '<p class="spbc_hint" style="display: inline-block;">';
	
		$text .= sprintf(__('The log shows list of access attempts for past hour and shows only last %d records.', 'security-malware-firewall'), SPBC_LAST_ACTIONS_TO_VIEW);
		
		if(empty($rows))
			$text .= ' '.__('Local log is empty', 'cleantalk');
		
		if($spbc->user_token && (is_main_site() || $spbc->allow_custom_key)){
			$text .= ' '.__('To see full report visit', 'security-malware-firewall')
			.' <a target="_blank" href="https://cleantalk.org/my?user_token='.$spbc->user_token.'&cp_mode=security">'
				.__('Security Control Panel', 'security-malware-firewall')
			.'</a>.';
		}
		
	$text .= '</p>';
	
	$data = array();
	
	if(is_array($rows) && count($rows)){
			
		$ips = '';
		foreach($rows as $entry){
			$ips .= ($ips == '' ? $entry['ip_entry'] : ','.$entry['ip_entry']);
		}
		$ip_countries = spbc_get_countries_by_ips($ips);
		
		foreach($rows as $entry){
			
			// Preparing status
			switch($entry['status']){
				case 'PASS':              $status = '<span class="spbcGreen">' . __('Passed', 'security-malware-firewall').'</span>';                           break;
				case 'PASS_BY_WHITELIST': $status = '<span class="spbcGreen">' . __('Whitelisted', 'security-malware-firewall').'</span>';                      break;
				case 'DENY':              $status = '<span class="spbcRed">'   . __('Blacklisted', 'security-malware-firewall').'</span>';                      break;
				case 'DENY_BY_NETWORK':	  $status = '<span class="spbcRed">'   . __('Blocked, Hazardous network', 'security-malware-firewall').'</span>';       break; 
				case 'DENY_BY_DOS':       $status = '<span class="spbcRed">'   . __('Blocked by DOS prevertion system', 'security-malware-firewall').'</span>'; break;
				default:                  $status = __('Unknown', 'security-malware-firewall');                                                                 break;
			}
			// Preparing user agent
			if(strlen($entry['http_user_agent']) >= 60)
				$user_agent='<span class="spbcShortText">'.substr($entry['http_user_agent'], 0, 60).'...</span><span class="spbcFullText spbcDisplayNone">'.$entry['http_user_agent'].'</span>';
			else
				$user_agent = $entry['http_user_agent'];
			// Preparing page url
			if(strlen($entry['page_url']) >= 60)
				$page_url='<span class="spbcShortText">'.substr($entry['page_url'], 0, 60).'...</span><span class="spbcFullText spbcDisplayNone">'.$entry['page_url'].'</span>';
			else
				$page_url = $entry['page_url'];
			// Preparing allowed/blocked
			$entries = ($entry['allowed_entry'] ? '<b class="spbcGreen">'.$entry['allowed_entry'].'</b>' : 0)
				.' / '
				.($entry['blocked_entry'] ? '<b class="spbcRed">'.$entry['blocked_entry'].'</b>' : 0);
			// Data for return
			$data[$entry['entry_id']] = array(
				'ip'         => '<a href="https://cleantalk.org/blacklists/'.$entry['ip_entry'].'" target="_blank">'.$entry['ip_entry'].'</a>',
				'country'    => spbc_report_country_part($ip_countries, $entry['ip_entry']),
				'time'       => date('M d Y, H:i:s', $entry['entry_timestamp']),
				'entries'    => $entries,
				'status'     => $status,
				'page_url'   => $page_url,
				'user_agent' => $user_agent,
			);			
		}
	}
	
	die(
		json_encode(
			array(
				'text' => $text,
				'data' => !empty($data) ? $data : 0,
				'count' => count($rows) ? count($rows) : 0,
			)
		)
	);
	
}