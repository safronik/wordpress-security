<?php

/**
 * Admin action 'admin_init' - Add the admin settings and such
 */
function spbc_admin_init() {

	//Update logic
    $spbc_data = get_option( SPBC_DATA );
	$current_version = (isset($spbc_data['plugin_version']) ? $spbc_data['plugin_version'] : '1.0.0');
	if($current_version != SPBC_VERSION){
		if(is_main_site()){
			require_once(SPBC_PLUGIN_DIR . 'inc/spbc-tools.php');
			spbc_run_update_actions($current_version, SPBC_VERSION);
		}
		$spbc_data['were_updated'] = (isset($spbc_data['plugin_version']) ? true : false); //Flag - plugin were updated
		$spbc_data['plugin_version'] = SPBC_VERSION;
		update_option( SPBC_DATA , $spbc_data);
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
				
				error_log(json_encode($result));
				
				$spbc_settings = get_option( SPBC_SETTINGS );
			
				$spbc_data['user_token'] = $result['user_token'];
				$spbc_settings['spbc_key'] = $result['auth_key'];
				$_POST['spbc_settings']['spbc_key'] = $result['auth_key'];
				$spbc_data['key_is_ok'] = true;
				
				update_option( SPBC_DATA , $spbc_data);
				update_option( SPBC_SETTINGS , $spbc_settings);
				error_log(json_encode($result));
				if(is_main_site()){
					$spbc_network_settings = get_site_option( SPBC_NETWORK_SETTINGS );
					
					error_log(json_encode($result));
					
					$spbc_network_settings['spbc_key'] = $result['auth_key'];
					$spbc_network_settings['user_token'] = $result['user_token'];
					$spbc_network_settings['key_is_ok'] = true;
					
					update_site_option ( SPBC_NETWORK_SETTINGS, $spbc_network_settings);
				}
				
				
			}
		}
	}
	
	//Logging admin actions
	if(!defined( 'DOING_AJAX' ))
		spbc_admin_log_action();	
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
		}else{
			$key_is_ok = ($spbc_network_settings['key_is_ok'] ? 'true' : 'false');
		}
	}else{
		$spbc_data = get_option( SPBC_DATA );
		$key_is_ok = (isset($spbc_data['key_is_ok']) && $spbc_data['key_is_ok'] == 1 ? true : false);
	}

	$page = get_current_screen();
	
	if(!$key_is_ok && $page->id != 'settings_page_spbc' && $page->id != 'settings_page_spbc-network'){
		
		echo "<div id='spbcTopWarning' class='error' style='position: relative;'>";
			
			if(is_network_admin()){
				$link = get_site_option('siteurl').'wp-admin/options-general.php?page=spbc';
				printf("<h3><u>Security by CleanTalk:</u> " . __("API key is not valid. Enter into %splugin settings%s in the main site dashboard to get API key.", "spbc") . "</h3>", "<a href='$link'>", "</a>");
			}else
				printf("<h3><u>Security by CleanTalk:</u> " . __("API key is not valid. Enter into %splugin settings%s to get API key.", "spbc") . "</h3>", "<a href='options-general.php?page=spbc'>", "</a>");
			
			if(isset($spbc_data['were_updated']) && $spbc_data['were_updated'] == 1)
				printf("<h3>". __("Why do you need an API key? Please, learn more %shere%s.", "spbc"). "</h3>", "<a href='https://wordpress.org/support/topic/why-do-you-need-an-access-key-updated/'>", "</a>");
			
			echo "<button type='button' class='notice-dismiss'><span class='screen-reader-text'>".__("Dismiss this notice.", "spbc")."</span></button>";
		echo "</div>";
	}
}

/**
 * Manage links in plugins list
 * @return array
*/
function spbc_plugin_action_links ($links, $file) {
	
	if(is_network_admin())
		$settings_link = '<a href="settings.php?page=spbc">' . __( 'Settings' ) . '</a>';
	else
		$settings_link = '<a href="options-general.php?page=spbc">' . __( 'Settings' ) . '</a>';
	
	array_unshift( $links, $settings_link ); // before other links
	return $links;
}

/**
 * Manage links and plugins page
 * @return array
*/
function spbc_plugin_links_meta ($meta) {
	$meta[] = '<a href="settings.php?page=spbc">' . __( 'Settings' ) . '</a>';
	return $meta;
}

/**
 * Register stylesheet and scripts.
 */
function spbc_enqueue_scripts($hook) {

	if($hook == 'settings_page_spbc'){
				
		$ajax_nonce = wp_create_nonce( "ct_secret_nonce" );
		
		wp_enqueue_style('spbc-admin', SPBC_PATH . '/assets/css/spbc-admin.css', array(), SPBC_VERSION, 'all');
		wp_enqueue_script('spbc-settings', SPBC_PATH . '/assets/js/spbc-settings.js', array(), SPBC_VERSION, false);
		
		wp_localize_script( 'jquery', 'ctCommentsCheck', array(
			'ct_ajax_nonce' => $ajax_nonce,
		));
	}
	wp_enqueue_script('spbc-admin', SPBC_PATH . '/assets/js/spbc-admin.js', array(), SPBC_VERSION, false);
}

/**
 * Admin callback function - Displays plugin options page
 */
function spbc_settings_page() {
	
	if(is_network_admin()){
		$link = get_site_option('siteurl').'wp-admin/options-general.php?page=spbc';
		printf("<h2>" . __("Please, enter the %splugin settings%s in main site dashboard.", "spbc") . "</h2>", "<a href='$link'>", "</a>");
		return;
	}
		
	$spbc_data = get_option( SPBC_DATA );
	$spbc_settings = get_option( SPBC_SETTINGS );
	
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
	
	//If have error message
	$error_msg = '';
	$error_msg .= (!isset($spbc_settings['spbc_key'], $spbc_data['key_is_ok']) || $spbc_data['key_is_ok'] == false || $spbc_settings['spbc_key'] == '' ? __("API key is not valid. Use the buttons below to get API key.", "spbc")."<br><br>" : '');
	$error_msg .= (isset($spbc_data['errors']['sent_error']) && $spbc_data['errors']['sent_error'] != '' ? $spbc_data['errors']['sent_error']."<br><br>" : '');
	$error_msg .= (isset($spbc_data['errors']['apikey']) && $spbc_data['errors']['apikey'] != '' ? $spbc_data['errors']['apikey']."<br><br>" : '');
	
	?>
	<div class="wrap">
		<h2><?php echo SPBC_NAME; ?></h2>
		<br>
		<div id='spbcTopInfoBlock' class='spbc-div-1'>
			<?php
				if($error_msg != '' && is_main_site()){
					echo "<div id='spbcTopWarning' class='error' style='position: relative;'>";
						echo "<h3>CleanTalk Security</h3>";
						echo "<h4>$error_msg</h4>";
						echo "<button type='button' class='notice-dismiss'><span class='screen-reader-text'>".__("Dismiss this notice.", "spbc")."</span></button>";
					echo "</div>";
				} 
			if($key_is_ok){
				if($allow_custom_key || is_main_site()){
			?>
					<div id='goToCleanTalk' class='spbc-div-2'>
						<a disabled id='goToCleanTalkLink' class='spbc_manual_link' target='_blank' href='https://cleantalk.org/my?user_token=<?php echo $user_token ?>&cp_mode=security'><?php _e('Click here to get security statistics', 'spbc'); ?></a>
					</div>
					<br>
			<?php 
				}
				if($allow_custom_key || is_main_site()){
			?>
					<div id='showLink' class='spbc-div-2'>
						<a id='showHideLink' class='spbc-links' style='color:#666;' href='#' ><?php _e('Show access key', 'spbc'); ?></a>
					</div>&nbsp;&nbsp;
			<?php 
				}
			} 
			?>
		</div>
		<form method="post" action="options.php">
			<?php
				settings_fields('spbc_settings');
				// do_settings_fields('spbc', 'spbc_main_section'); 
				// do_settings_fields('spbc', 'spbc_log_section'); 
				// do_settings_fields('spbc', 'spbc_key_section'); 
				do_settings_sections('spbc');
				//submit_button(); 
			?>
		</form>
		<?php
			echo __('Tech support CleanTalk: CleanTalk tech forum. https://wordpress.org/support/plugin/security-malware-firewall', 'spbc');
			echo '<br>';
			echo __('CleanTalk is registered Trademark. All rights reserved.', 'spbc');
		?>
	</div>
	<?php
}

/**
 * Admin action 'admin_menu' - Add the admin options page
 */
function spbc_admin_add_page() {
	
	//Adding setting page
	if(is_network_admin())
		add_submenu_page("settings.php", __( SPBC_NAME . 'settings', 'spbc'), SPBC_NAME, 'manage_options', 'spbc', 'spbc_settings_page');
	else
		add_options_page( __( SPBC_NAME . 'settings', 'spbc'), SPBC_NAME, 'manage_options', 'spbc', 'spbc_settings_page');
	
	$spbc_network_settings = get_site_option( SPBC_NETWORK_SETTINGS );
	
	//Adding setting menu
    register_setting('spbc_settings', 'spbc_settings', 'spbc_sanitize_settings');
	
	//Adding menu sections
	add_settings_section('spbc_key_section', '', 'spbc_section_key', 'spbc');
	add_settings_section('spbc_status_section', "<hr />".__('Security status', 'spbc'), 'spbc_section_security_status', 'spbc');
	add_settings_section('spbc_log_section', "<hr />".__('Brute-force attacks log', 'spbc'), 'spbc_section_log', 'spbc');
	
	//Adding fields
	if(is_main_site() && SPBC_WPMS){
		add_settings_field('spbc-allow-custom-key', __('Allow users to use other key', 'spbc'), 'spbc_field_custom_key', 'spbc', 'spbc_key_section', array('id' => 'custom_key', 'value' => $spbc_network_settings['allow_custom_key']));
		/* DELETED
		add_settings_field('spbc-allow-cleantlak-cp', __('Allow users to enter to CleanTalk control panel', 'spbc'), 'spbc_field_cleantalk_cp', 'spbc', 'spbc_key_section', array('id' => 'allow_ct_cp', 'value' => $spbc_network_settings['allow_cleantalk_cp']));
		*/
	}
	add_settings_field('spbc-apikey', __('Access key', 'spbc'), 'spbc_field_key', 'spbc', 'spbc_key_section', array('id' => 'spbc_key', 'option_name' => 'my_option', 'class' => 'spbc-field'));
}

function spbc_section_key(){
}

function spbc_section_security_status() {
		
	if(!is_main_site()){
		$spbc_network_settings = get_site_option( SPBC_NETWORK_SETTINGS );
		if($spbc_network_settings){
			$allow_custom_key = ($spbc_network_settings['allow_custom_key'] ? true : false);
			if(!$allow_custom_key){
				$key_is_ok = ($spbc_network_settings['key_is_ok'] == 1 ? true : false);
				$key = $spbc_network_settings['spbc_key'];
			}
		}else
			$key_is_ok = false;
	}else
		$allow_custom_key = true;
	
	if($allow_custom_key || is_main_site()){
		$spbc_data = get_option( SPBC_DATA );
		$spbc_settings = get_option( SPBC_SETTINGS );
		$key_is_ok = (isset($spbc_data['key_is_ok']) && $spbc_data['key_is_ok'] == 1 ? true : false);
		$key = $spbc_settings['spbc_key'];
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
	
	echo "<div style='color:$color'>";
		//echo ' &nbsp; <img src="'.(($ct_options['comments_test']==1 || $ct_moderate) ? $img : $img_no).'" alt=""  height="" /> '.__('Comments forms', 'cleantalk');
		echo ' &nbsp; <img src="'.($key_is_ok ? $img : $img_no).'" alt=""  height="" /> '.__('Brute Force Protection', 'spbc');
		echo ' &nbsp; <img src="'.($key_is_ok ? $img : $img_no).'" alt=""  height="" /> '.__('Security Report', 'spbc');
		echo ' &nbsp; <img src="'.($key_is_ok ? $img : $img_no).'" alt=""  height="" /> '.__('Security Audit Log', 'spbc');
	echo "</div>";	
	
	//if(!$test_failed)
		//echo __("Testing is failed, check settings. Tech support <a target=_blank href='mailto:support@cleantalk.org'>support@cleantalk.org</a>", 'cleantalk');
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
	$option_name = $val['id'];
	
	if($allow_custom_key || is_main_site()){
		if($key_is_ok == 'true'){
			echo "<input id='$field_id' name='spbc_settings[spbc_key]' size='20' type='text' value='$current_key' style=\"font-size: 14pt;\" placeholder='" . __('Enter the key', 'cleantalk') . "' />";
		}else{
			echo "<input id='$field_id' name='spbc_settings[spbc_key]' size='20' type='text' value='$current_key' style=\"font-size: 14pt;\" placeholder='" . __('Enter the key', 'cleantalk') . "' />";
			echo "<br/><br/>";
			echo "<a target='_blank' href='https://cleantalk.org/register?platform=wordpress&email=".urlencode($admin_email)."&website=".urlencode(parse_url($site_url,PHP_URL_HOST))."&product_name=security'>
					<input type='button' class='spbc_auto_link' value='".__('Get access key manually', 'spbc')."' />
				</a>";
			echo __('or', 'spbc');
			echo '<input name="spbc_get_apikey_auto" type="submit" class="spbc_manual_link" value="' . __('Get access key automatically', 'spbc') . '" />';
			echo "<br/><br/>";
			echo "<div style='font-size: 10pt; color: #666 !important'>" . sprintf(__('Admin e-mail (%s) will be used for registration', 'cleantalk'), get_option('admin_email')) . "</div>";
			echo "<div style='font-size: 10pt; color: #666 !important'><a target='__blank' style='color:#BBB;' href='https://cleantalk.org/publicoffer'>" . __('License agreement', 'cleantalk') . "</a></div>";
		}
		
		submit_button();
	}else{
		_e("<h3>Key is provided by Super Admin.<h3>", "spbc");
	}	
}

function spbc_field_custom_key( $values ){
	echo "<input type='checkbox' id='".$values['id']."' name='spbc_settings[custom_key]' value='1' " . ($values['value'] == '1' ? 'checked' : '') . " /><label for='collect_details1'> " . __('Allow users to use different Access key in their plugin settings. They could use different CleanTalk account.', 'spbc');
}

// INACTIVE
function spbc_field_cleantalk_cp( $values ){
	echo "<input type='checkbox' id='".$values['id']."' name='spbc_settings[allow_ct_cp]' value='1' " . ($values['value'] == '1' ? 'checked' : '') . " /><label for='collect_details1'> " . __('Allow users to u access to CleanTalk control panel from their Wordpress dashboard (only "read" access).', 'spbc');
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
		);
		
		require_once(SPBC_PLUGIN_DIR . 'inc/spbc-tools.php');
		$result = spbc_sendRawRequest(SPBC_API_URL, $data);
		
		$result = ($result != false ? json_decode($result, true): null);
		if($result){
			if(isset($result['error_message']) || isset($result['error_no'])){
				$spbc_data['errors']['apikey'] = date('Y-m-d H:i:s')." - ". sprintf(__('Error while checking the API key "%s" Error #%d Comment: %s.', 'spbc'), $matches[2], $result['error_no'], $result['error_message']);
			}else{
				if($result['valid'] == '1' ){
					$spbc_data['key_is_ok'] = true;
					$spbc_data['errors']['apikey'] = '';
					//If key is ok, sending logs.
					$sent_errors = spbc_send_logs($matches[2]);
					if($sent_errors)
						$spbc_data['errors']['sent_error'] = $sent_errors;
					else
						$spbc_data['errors']['sent_error'] = '';
				}else{
					$spbc_data['errors']['apikey'] = date('Y-m-d H:i:s')." - ".sprintf(__('Key is not valid. Key: %s.', 'spbc'), $matches[2]);
					$spbc_data['key_is_ok'] = false;			
				}
			}
		}else{
			$spbc_data['errors']['apikey'] = __('Cleantalk spbc_sendRawRequest() returns "false" while checking access key. Possible reasons: Bad connection or cloud server error(less possible).', 'spbc');
		}
	}
	
	update_option(SPBC_DATA, $spbc_data);
	
	$settings['spbc_key'] = $matches[2];
	
	if(is_main_site()){
	
		$network_settings = array(
			'key_is_ok' => $spbc_data['key_is_ok'],
			'spbc_key' => $settings['spbc_key'],
			'user_token' => $spbc_data['user_token'],
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
function spbc_section_log(){
	 global $wpdb, $spbc_tpl;
    
    include_once(SPBC_PLUGIN_DIR . "/templates/spbc_settings_main.php");

	$message_about_log = sprintf(__('The log includes list of attacks for past 24 hours and shows only last %d records. To see the full report please check the Daily security report in your Inbox (%s).'),
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
				$page_time,
                $ip_part
            );
        }
        $t_last_attacks = sprintf($spbc_tpl['t_last_attacks_tpl'],
            $row_last_attacks 
        );
        echo $t_last_attacks;

        // Rate block
        echo sprintf($spbc_tpl['spbc_rate_plugin_tpl'],
            SPBC_NAME  
        );
    } else {
        echo $records_count . ' brute-force attacks have been made.';
    }
    $report_footer = sprintf('
		<br />
		<br />
		<div class="spbc_hiht">
			The plugin home page <a href="https://wordpress.org/plugins/security-malware-firewall/" target="_blank">%s</a>.
		</div>',
		SPBC_NAME
	);
    echo $report_footer;
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
        $log_id = spbc_auth_log(array(
            'username' => $user->get('user_login'), 
            'event' => 'view',
			'page' => $_SERVER['REQUEST_URI'],
			'blog_id' => get_current_blog_id()
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
