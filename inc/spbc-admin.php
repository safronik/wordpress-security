<?php

$spbc_plugin_basename = 'security-malware-firewall/security-malware-firewall.php';
$spbc_plugin_dir_name = 'security-malware-firewall';

/**
 * Admin action 'admin_init' - Add the admin settings and such
 */
function spbc_admin_init() {
    global $spbc_plugin_name;

    register_setting('spbc_settings', 'spbc_settings');
	add_settings_section('spbc_settings_main', __($spbc_plugin_name, 'spbc'), 'spbc_section_settings_main', 'spbc');
}

/**
 * Register style sheet.
 */
function spbc_wp_enqueue_scripts() {
    global $spbc_plugin_dir_name;
    
    $css_path = plugins_url($spbc_plugin_dir_name . '/assets/css/spbc-admin.css');
    wp_enqueue_style('spbc-admin', $css_path);

    return;
}

/**
 * Admin callback function - Displays plugin options page
 */
function spbc_settings_page() {
    global $spbc_plugin_name;
	?>
<link rel='stylesheet' href='<?php plugins_url( '/assets/ccs/spbc-admin.css' , __FILE__); ?>' type='text/css' media='all' />

<div class="wrap">
<form method="post" action="options.php">
<?php settings_fields('spbc_settings'); ?>
<?php do_settings_sections('spbc'); ?>
<?php //submit_button(); ?>
</form>
</div>
<?php

}
/**
 * Admin action 'admin_menu' - Add the admin options page
 */
function spbc_admin_add_page() {
    global $spbc_plugin_name;

    add_options_page(__($spbc_plugin_name . 'settings', 'spbc'), $spbc_plugin_name, 'manage_options', 'spbc', 'spbc_settings_page');
}

/**
 * Manage links in plugins list
 * @return array
*/
if (!function_exists ( 'spbc_plugin_action_links')) {
	function spbc_plugin_action_links ($links, $file) {
		global $spbc_plugin_basename;

		if ($file == $spbc_plugin_basename) {
			if(!is_network_admin())
			{
				$settings_link = '<a href="options-general.php?page=spbc">' . __( 'Settings' ) . '</a>';
			}
			else
			{
				$settings_link = '<a href="settings.php?page=spbc">' . __( 'Settings' ) . '</a>';
			}
			array_unshift( $links, $settings_link ); // before other links
		}
		return $links;
	}
}

/**
 * Admin callback function - Displays description of 'main' plugin parameters section
 */
function spbc_section_settings_main() {
    global $spbc_auth_logs_table_label,$spbc_settings_last_report_limit,$wpdb, $spbc_tpl, $spbc_plugin_name;

    spbc_wp_enqueue_scripts();
    
    include_once(SPBC_PLUGIN_DIR . "/templates/spbc_settings_main.php");

    echo '<h3>' . __('Brute-force attacks log') . '</h3>';
    echo '<p class="spbc_hint">' . __('The log includes list of attacks for past 24 hours and shows only last ' . $spbc_settings_last_report_limit . ' records. To see the full report please check the Daily security report in your Inbox (' . get_option('admin_email') . ').') . '</p>';

    $spbc_auth_logs_table = $wpdb->prefix . $spbc_auth_logs_table_label;
    $sql = sprintf('select id,datetime,user_login,event,auth_ip from %s order by datetime desc limit 50;',
        $spbc_auth_logs_table,
        $spbc_settings_last_report_limit
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

            $ip_part = sprintf("<a href=\"https://cleantalk.org/blacklists/%s\" target=\"_blank\">%s</a>, %s",
                $ip_dec, 
                $ip_dec, 
                $country_part
            );
            $row_last_attacks .= sprintf($spbc_tpl['row_last_attacks_tpl'],
                date("M d Y, H:i:s", strtotime($record->datetime)),
                $user_part, 
                $record->event, 
                $ip_part
            );
        }
        $t_last_attacks = sprintf($spbc_tpl['t_last_attacks_tpl'],
            $row_last_attacks 
        );
        echo $t_last_attacks;
    } else {
        echo $records_count . ' brute-force attacks have been made.';
    }
    $report_footer = sprintf('
<br />
<br />
<div class="spbc_hiht">
    The plugin home page <a href="https://wordpress.org/plugins/security-malware-firewall/" target="_blank">%s</a>.
</div>',
    $spbc_plugin_name
);
    echo $report_footer;

    return null;
}
?>