<?php

/**
 * Admin action 'admin_init' - Add the admin settings and such
 */
function spbc_admin_init() {

	//Update logic
    $spbc_data = get_option( SPBC_DATA );
	$current_version = (isset($spbc_data['plugin_version']) ? $spbc_data['plugin_version'] : '1.0.0');
	if($current_version != SPBC_VERSION){
		require_once(SPBC_PLUGIN_DIR . 'inc/spbc-tools.php');
		spbc_run_update_actions($current_version, SPBC_VERSION);
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
				
			}elseif(isset($result['data']) && is_array($result['data'])){
				
				$result = $result['data'];
				
				$spbc_settings = get_option( SPBC_SETTINGS );
			
				$spbc_data['user_token'] = $result['user_token'];
				$spbc_settings['spbc_key'] = $result['auth_key'];
				$_POST['spbc_settings']['spbc_key'] = $result['auth_key'];
				$spbc_data['key_is_ok'] = true;
				
				update_option( SPBC_DATA , $spbc_data);
				update_option( SPBC_SETTINGS , $spbc_settings);
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
	
	$spbc_data = get_option( SPBC_DATA );
	$key_is_ok = (isset($spbc_data['key_is_ok']) && $spbc_data['key_is_ok'] == 1 ? true : false);
	
	$page = get_current_screen();
		
	if(!$key_is_ok && $page->id != 'settings_page_spbc'){
		if(isset($spbc_data['were_updated']) && $spbc_data['were_updated'] == 1)
			echo "<div id='spbcTopWarning' class='error' style='position: relative;'>
				<h3><u>Security by CleanTalk:</u> API key is not valid. Enter the <a href='options-general.php?page=spbc'>plugin settings</a> to get API key.</h3>
				<h3>Why do you need an API key? Please, learn more <a href='https://wordpress.org/support/topic/why-do-you-need-an-access-key/' target='_blank'>here</a>.</h3>
				<button type='button' class='notice-dismiss'><span class='screen-reader-text'>Dismiss this notice.</span></button>
			</div>";
		else
			echo "<div id='spbcTopWarning' class='error' style='position: relative;'>
				<h3><u>Security by CleanTalk:</u> API key is not valid. Enter the <a href='options-general.php?page=spbc'>plugin settings</a> to get API key.</h3>
				<button type='button' class='notice-dismiss'><span class='screen-reader-text'>Dismiss this notice.</span></button>
			</div>";
	}
}

/**
 * Manage links in plugins list
 * @return array
*/
function spbc_plugin_action_links ($links, $file) {
	
	//if(!is_network_admin())
		$settings_link = '<a href="options-general.php?page=spbc">' . __( 'Settings' ) . '</a>';
	//else
	//	$settings_link = '<a href="settings.php?page=spbc">' . __( 'Settings' ) . '</a>';
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
	
	$spbc_data = get_option( SPBC_DATA );
	$spbc_settings = get_option( SPBC_SETTINGS );
	
	$user_token = (isset($spbc_data['user_token']) ? $spbc_data['user_token'] : 'none');
	$key_is_ok = (isset($spbc_data['key_is_ok']) && $spbc_data['key_is_ok'] == 1 ? true : false);
	
	//If have error message
	$error_msg = '';
	$error_msg .= (!isset($spbc_settings['spbc_key'], $spbc_data['key_is_ok']) || $spbc_data['key_is_ok'] == false || $spbc_settings['spbc_key'] == '' ? "API key is not valid. Use the buttons below to get API key.<br><br>" : '');
	$error_msg .= (isset($spbc_data['errors']['sent_error']) && $spbc_data['errors']['sent_error'] != '' ? $spbc_data['errors']['sent_error']."<br><br>" : '');
	$error_msg .= (isset($spbc_data['errors']['apikey']) && $spbc_data['errors']['apikey'] != '' ? $spbc_data['errors']['apikey']."<br><br>" : '');
	
	?>
	<div class="wrap">
		<h2><?php echo SPBC_NAME; ?></h2>
		<br>
		<div id='spbcTopInfoBlock' class='spbc-div-1'>
			<? 
				if($error_msg != ''){
					echo "<div id='spbcTopWarning' class='error' style='position: relative;'>";
						echo "<h3>CleanTalk Security</h3>";
						echo "<h4>$error_msg</h4>";
						echo "<button type='button' class='notice-dismiss'><span class='screen-reader-text'>Dismiss this notice.</span></button>";
					echo "</div>";
				} 
			if($key_is_ok){
			?>
				<div id='goToCleanTalk' class='spbc-div-2'>
					<a disabled id='goToCleanTalkLink' class='spbc_manual_link' target='_blank' href='https://cleantalk.org/my?user_token=<?php echo $user_token ?>'><?php _e('Click here to get security statistics', 'spbc'); ?></a>
				</div>
				<br>
				<div id='showLink' class='spbc-div-2'>
					<a id='showHideLink' class='spbc-links' style='color:#666;' href='#' ><?php _e('Show access key', 'spbc'); ?></a>
				</div>&nbsp;&nbsp;
			<?php } ?>
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
	</div>
	<?php
}

/**
 * Admin action 'admin_menu' - Add the admin options page
 */
function spbc_admin_add_page() {
	
	//Adding setting menu
    register_setting('spbc_settings', 'spbc_settings', 'spbc_sanitize_settings');
	
	//Adding setting page
    add_options_page( __( SPBC_NAME . 'settings', 'spbc'), SPBC_NAME, 'manage_options', 'spbc', 'spbc_settings_page');
		
	//Adding menu sections
	add_settings_section('spbc_key_section', '', 'spbc_section_key', 'spbc');
	add_settings_section('spbc_status_section', "<hr />".__('Security status', 'spbc'), 'spbc_section_security_status', 'spbc');
	add_settings_section('spbc_log_section', "<hr />".__('Brute-force attacks log', 'spbc'), 'spbc_section_log', 'spbc');
	
	//Adding fields
	add_settings_field('spbc-apikey', __('Access key', 'spbc'), 'spbc_field_key', 'spbc', 'spbc_key_section', array('id' => 'spbc_key', 'option_name' => 'my_option', 'class' => 'spbc-field'));
	
}

function spbc_section_key(){
}

function spbc_section_security_status() {
	
	$spbc_data = get_option( SPBC_DATA );
	
	$key_is_ok = (isset($spbc_data['key_is_ok']) && $spbc_data['key_is_ok'] == 1 ? true : false);
	
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
    
	$spbc_settings = get_option( SPBC_SETTINGS );
	$current_key = (isset($spbc_settings['spbc_key']) ? $spbc_settings['spbc_key'] : '');
	
	$spbc_data = get_option( SPBC_DATA );
	$key_is_ok = (isset($spbc_data['key_is_ok']) && $spbc_data['key_is_ok'] == 1 ? 'true' : 'false');

	$admin_email = get_option('admin_email');
	$site_url = get_option('siteurl');
	
	echo "<script>
			var keyIsOk = $key_is_ok;
	</script>";
	
	$field_id = $val['id'];
	$option_name = $val['id'];
	
	if($key_is_ok == 'true'){
		echo "<input id='$field_id' name='spbc_settings[spbc_key]' size='20' type='text' value='$current_key' style=\"font-size: 14pt;\" placeholder='" . __('Enter the key', 'cleantalk') . "' />";
	}else{
		echo "<input id='$field_id' name='spbc_settings[spbc_key]' size='20' type='text' value='$current_key' style=\"font-size: 14pt;\" placeholder='" . __('Enter the key', 'cleantalk') . "' />";
		echo "<br/><br/>";
		echo "<a target='_blank' href='https://cleantalk.org/register?platform=security&email=".urlencode($admin_email)."&website=".urlencode(parse_url($site_url,PHP_URL_HOST))."'>
				<input type='button' class='spbc_auto_link' value='".__('Get access key manually', 'spbc')."' />
			</a>";
		echo __('or', 'spbc');
		echo '<input name="spbc_get_apikey_auto" type="submit" class="spbc_manual_link" value="' . __('Get access key automatically', 'spbc') . '" />';
		echo "<br/><br/>";
		echo "<div style='font-size: 10pt; color: #666 !important'>" . sprintf(__('Admin e-mail (%s) will be used for registration', 'cleantalk'), get_option('admin_email')) . "</div>";
		echo "<div style='font-size: 10pt; color: #666 !important'><a target='__blank' style='color:#BBB;' href='https://cleantalk.org/publicoffer'>" . __('License agreement', 'cleantalk') . "</a></div>";
	}
	
	submit_button();
	
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
				$spbc_data['errors']['apikey'] = date('Y-m-d H:i:s')." - Error while checcking the access key \"".$matches[2]."\". Error # : ".$result['error_no']." Comment: ".$result['error_message'];
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
					$spbc_data['errors']['apikey'] = date('Y-m-d H:i:s')." - Key is not valid. Key: \"".$matches[2]."\".";
					$spbc_data['key_is_ok'] = false;			
				}
			}
		}else{
			$spbc_data['errors']['apikey'] = 'Cleantalk spbc_sendRawRequest() returns "false" while checking access key. Possible reasons: Bad connection or cloud server error(less possible).';
		}
	}
	
	update_option(SPBC_DATA, $spbc_data);
	
	$settings['spbc_key'] = $matches[2];

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

    $spbc_auth_logs_table = $wpdb->prefix . SPBC_LOG_TABLE;
    $sql = sprintf('select id,datetime,user_login,page,page_time,event,auth_ip from %s order by datetime desc limit %d;',
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
	
	$spbc_auth_logs_table = $wpdb->prefix . SPBC_LOG_TABLE;
	
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
