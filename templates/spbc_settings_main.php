<?php
$t_traffic_control = '
<div class="spbc_table_general">
	<table border="0" class="spbc_table_general" id="spbc_security_firewall_logs_table">
		<tr>
			<th>
				'.__('IP', 'security-malware-firewall').'
			</th>
			<th>
				'.__('Country', 'security-malware-firewall').'
			</th>
			<th>
			   '.__('Last request', 'security-malware-firewall').'
			</th>
			<th>
				'.__('Allowed/Blocked HTTP requests', 'security-malware-firewall').'
			</th>
			<th>
				'.__('Status', 'security-malware-firewall').'
			</th>
			<th>
				'.__('Page', 'security-malware-firewall').' 
			</th>
			<th>
				'.__('User agent', 'security-malware-firewall').'
			</th>
		</tr>
		%s
	</table>
</div>';

$t_last_attacks_tpl = '
<div class="spbc_table_general">
	<table border="0" class="spbc_table_general" id="spbc_security_logs_table">
		<tr>
			<th>
			   '.__('Date and time', 'security-malware-firewall').'
			</th>
			<th>
				'.__('User', 'security-malware-firewall').'
			</th>
			<th>
				'.__('Action', 'security-malware-firewall').' 
			</th>
			<th>
				'.__('Page', 'security-malware-firewall').' 
			</th>
			<th>
				'.__('Time on page, sec', 'security-malware-firewall').'
			</th>
			<th>
				'.__('IP, Country', 'security-malware-firewall').'
			</th>
		</tr>
		%s
	</table>
</div>';

$row_last_attacks_tpl = <<<EOT
<tr class='spbc_sec_log_string'>
	<td>
		%s 
	</td>
	<td>
		%s 
	</td>
	<td>
		%s 
	</td>
	<td>
		%s 
	</td>
	<td>
		%s 
	</td>
	<td>
		%s 
	</td>
</tr>
EOT;


$spbc_rate_plugin_tpl = '
<div class="spbc_settings_banner" id="spbc_rate_plugin">
    <div class="spbc_rate_block">
        <p>'.__('Tell other users about your experience with %s.', 'security-malware-firewall').'</p>
        <p>'.__('Write your review on WordPress.org', 'security-malware-firewall').'</p>
        <div>
            <a class="spbc_button_rate" href="https://wordpress.org/support/plugin/security-malware-firewall/reviews/?filter=5" target="_blank">'.__('RATE IT NOW', 'security-malware-firewall').'</a>
        </div>
        <div class="spbc_rate_block_stars">
			<span class="star-icon full">☆</span>
			<span class="star-icon full">☆</span>
			<span class="star-icon full">☆</span>
			<span class="star-icon full">☆</span>
			<span class="star-icon full">☆</span>
        </div>
    </div>
</div>';

$spbc_translate_banner_tpl = '
<div class="spbc_settings_banner" id="spbc_translate_plugin">
    <div class="spbc_rate_block">
        <p>'.__('Help others use the plugin in your language.', 'security-malware-firewall').'</p>
        <p>'.__('We ask you to help with the translation of the plugin in your language. Please take a few minutes to make the plugin more comfortable.', 'security-malware-firewall').'</p>
        <div>
            <a class="spbc_button_rate" href="https://translate.wordpress.org/locale/%s/default/wp-plugins/security-malware-firewall" target="_blank">'.__('TRANSLATE', 'security-malware-firewall').'</a>
        </div>
    </div>
</div>';

$spbc_scan_result_bad_files_tpl = '
<table border="0" class="spbc_table_general">
	<tr>
		<th>
			'.__('Path to File', 'security-malware-firewall').'
		</th>
		<th style="width: 60px;">
			'.__('File size', 'security-malware-firewall').'
		</th>
		<th style="width: 100px;">
			'.sprintf(__('Permissions<sup>&nbsp;<a href="%s" target="blank">info</a></sup>', 'security-malware-firewall'), 'https://wikipedia.org/wiki/Chmod').'
		</th>
		<th style="width: 130px;">
			'.__('Last modified at', 'security-malware-firewall').'
		</th>
		<th style="width: 400px;">
			'.__('Actions', 'security-malware-firewall').'
		</th>
	</tr>
	%s
</table>';

$spbc_scan_result_links_tpl = '
<table border="0" class="spbc_table_general">
	<tr>
		<th>
			'.__('Link', 'security-malware-firewall').'
		</th>
		<th>
			'.__('Page URL', 'security-malware-firewall').'
		</th>
		<th>
			'.__('Link Text', 'security-malware-firewall').'
		</th>
	</tr>
	%s
</table>';

$spbc_tpl = array(
	't_traffic_control' => $t_traffic_control,
    't_last_attacks_tpl' => $t_last_attacks_tpl,
    'row_last_attacks_tpl' => $row_last_attacks_tpl,
    'spbc_rate_plugin_tpl' => $spbc_rate_plugin_tpl,
	'spbc_translate_banner_tpl' => $spbc_translate_banner_tpl,
	'spbc_scan_result_bad_files_tpl' => $spbc_scan_result_bad_files_tpl,
	'spbc_scan_result_links_tpl' => $spbc_scan_result_links_tpl,	
);
