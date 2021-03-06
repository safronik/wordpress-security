// Callback for displaying Firewall logs
function spbc_tc__show_more__callback(result, data, params){
	if(data.full_refresh){
		jQuery(params.wrapper).html(result);
		spbc_tbl__row_actions__listen();
		spbc_tbl__pagination__listen();
		spbc_tbl__sort__listen();
		spbcStartShowHide();
	}else if(result.size > 0){
		jQuery(params.wrapper).html(result.html);
		spbc_tbl__row_actions__listen();
		spbcStartShowHide();
	}else{
		// Hide "More logs" button, show "Control Panel" button
		jQuery(params.button).hide();
		jQuery(params.button).siblings('.spbc__show_more_logs').css('display', 'inline-block');
	}
}

function spbc_tc__show_more__listen(){	
	jQuery('#spbc_show_more_fw_logs_button').on('click', function(){
		if(spbcSettingsFWLogs.clicks < 2){
			spbcSettingsFWLogs.clicks++;
			var data = {
				action: 'spbc_show_more_security_firewall_logs',
				amount: spbcSettingsFWLogs.amount * (+spbcSettingsFWLogs.clicks+1),
				args: eval('args_'+jQuery('#spbc_tbl__traffic_control_logs').attr('id')),
			};			
			var params = {
				button: this,
				spinner: this.nextElementSibling,
				wrapper: jQuery('#spbc_tbl__traffic_control_logs').find('tbody'),
				callback: spbc_tc__show_more__callback,
			};
			spbc_sendAJAXRequest( data, params );
		}else{
			// Hide "More logs" button, show "Control Panel" button
			jQuery(this).hide();
			jQuery(this).siblings('.spbc__show_more_logs').css('display', 'inline-block');
		}
	});	
}

jQuery(document).ready(function(){
	
	spbc_tbl__row_actions__listen(); // Table. Row actions handler
	spbc_tbl__pagination__listen();  // Table. Pagination handler
	spbc_tbl__sort__listen();        // Table. Sort handler
	
	// Start to hide long values in a table
	spbcStartShowHide();
	
	// FIREWALL LOGS EVENTS
	
	// Handler for show more FIREWALL LOGS
	spbc_tc__show_more__listen();
	
	// Timer for FireWall logs
	var spbcFireWallLogsUpdateTimer = setTimeout(function spbc_heartbeat(){		
		// Do refresh only if traffic control is enabled and tab is active
		if( +spbcSettingsFWLogs.tc_status && jQuery('.spbc_tab_nav-traffic_control').hasClass('spbc_tab_nav--active')){
			var data = {
				action: 'spbc_show_more_security_firewall_logs',
				args: eval('args_'+jQuery('#spbc_tbl__traffic_control_logs').attr('id')),
				full_refresh: true,
			};
			var params = {
				wrapper: jQuery('#spbc_tbl__traffic_control_logs'),
				callback: spbc_tc__show_more__callback,
				notJson: true,
			};
			spbc_sendAJAXRequest( data, params );
		}
		setTimeout(spbc_heartbeat, 60000);
	}, 60000);	
});