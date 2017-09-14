var spbc_click_count_security_log = 0,
	spbc_click_count_security_firewall_log = 0;

// Settings dependences
function spbcSettingsDependencies(spbcSettingSwitchId){
	
	var spbcSettingToSwitch = jQuery('#'+spbcSettingSwitchId);
	
	console.log(spbcSettingToSwitch.attr('disabled'));
	
	if(spbcSettingToSwitch.attr('disabled') === undefined)
		spbcSettingToSwitch.attr('disabled', 'disabled');
	else
		spbcSettingToSwitch.removeAttr('disabled');
	
}

jQuery(document).ready(function(){
		
	jQuery('#showHideLink').on('click', function(){
		jQuery('#spbc_key').val(jQuery('#spbc_key').attr('key'));
		jQuery(this).fadeOut(300);
	});

	jQuery("#spbc_settings_form").on('submit', function(){
		if(jQuery('#spbc_key').val().indexOf('***') != -1)
			jQuery('#showHideLink').click();
	});
	
	// Switching to logs if key is ok
	if(keyIsOk){
		jQuery('.spbc_tab_nav').removeClass('spbc_tab_nav-active')
			.filter('#spbc_security_log-control').toggleClass('spbc_tab_nav-active');
		jQuery('.spbc_tab').removeClass('spbc_tab-active')
			.filter('#spbc_security_log').toggleClass('spbc_tab-active');
	}else{
		jQuery('.spbcTopWarning').val('Key is bad');
	}
	
	// if Debug data is set
	if(spbcSettings.debug){
		jQuery('.spbc_tab_nav').removeClass('spbc_tab_nav-active')
			.filter('#spbc_debug-control').toggleClass('spbc_tab_nav-active');
		jQuery('.spbc_tab').removeClass('spbc_tab-active')
			.filter('#spbc_debug').toggleClass('spbc_tab-active');
	}
	
	// DEBUG TC
	// if(true){
		// jQuery('.spbc_tab_nav').removeClass('spbc_tab_nav-active')
			// .filter('#spbc_traffic_control-control').toggleClass('spbc_tab_nav-active');
		// jQuery('.spbc_tab').removeClass('spbc_tab-active')
			// .filter('#spbc_traffic_control').toggleClass('spbc_tab-active');
	// }
	
	jQuery('#spbcTopWarning').on('click', '.notice-dismiss', function(){
		jQuery(this).parent('div').fadeOut(300);
	});
	

		
	//Tab control
	jQuery('.spbc_tabs_nav_wrapper').on('click', '.spbc_tab_nav', function(){
		jQuery('.spbc_tab_nav').removeClass('spbc_tab_nav-active');
		jQuery('.spbc_tab').removeClass('spbc_tab-active');
		jQuery('#'+this.id.replace('-control', '')).toggleClass('spbc_tab-active');
		jQuery(this).toggleClass('spbc_tab_nav-active');
	});

/* Log uploading */

	// Handler for show more logs
	jQuery('#spbc_show_more_button').on('click', function(){
		
		var spbc_obj = jQuery(this);
		
		// Showing spinner
		spbc_obj
			.hide()
		.siblings('img')
			.show();
		
		jQuery.ajax({
			type: "POST",
			url: spbcSettings.ajaxurl,
			data: {
				'action': 'spbc_show_more_security_logs',
				'security': spbcSettings.ajax_nonce,
				'start_nubmer': spbcSettingsSecLogs.start_nubmer,
				'show_entries': spbcSettingsSecLogs.show_entries
			},
			success: function(msg){
				
				var spbc_result = JSON.parse(msg);
				
				// Hiding spinner
				spbc_obj
					.show()
				.siblings('img')
					.hide();
				
				if(spbc_result.count && spbc_click_count_security_log < 1){
					
					spbc_click_count_security_log++;
					spbcSettingsSecLogs.start_nubmer = parseInt(spbcSettingsSecLogs.start_nubmer) + parseInt(spbcSettingsSecLogs.show_entries);
					
					for(i=0; i < spbc_result.count; i++){
						
						jQuery('#spbc_security_logs_table tbody')
							.append('<tr></tr>')
							.children()
							.last()
								.append('<td>'+spbc_result.data[i].datetime+'</td>')
								.append('<td>'+spbc_result.data[i].user+'</td>')
								.append('<td>'+spbc_result.data[i].action+'</td>')
								.append('<td>'+spbc_result.data[i].page+'</td>')
								.append('<td>'+spbc_result.data[i].page_time+'</td>')
								.append('<td>'+spbc_result.data[i].ip+'</td>');
					}
				}else{
					// Hidding button
					jQuery('#spbc_show_more_button').hide();
					// Showing Control Panel button
					if(spbc_result.user_token){
						jQuery('.spbc_show_cp_button')
							.css('display', 'inline-block')
						.filter('.spbc_cp_button').attr('href', 'https://cleantalk.org/my?user_token='+spbc_result.user_token+'&cp_mode=security');
					}
				}
				
			},
			error: function(jqXHR, textStatus, errorThrown) {
				
				console.log('error');
				console.log(jqXHR);
				console.log(textStatus);
				console.log(errorThrown);
				
			},
			timeout: 5000
		});
	});
	
/*/ FireWall logs
	
	// Callback
	var spbcSecurityFirewallLogCallback = function(result, spbcButton, wrapper, timestamp){
		
		// Hiding spinner
		spbcButton.show()
			.siblings('img').hide();
		
		console.log('Result');
		console.log(result);
		// console.log(spbcButton);
		// console.log(wrapper);
		
		if(result.count && spbc_click_count_security_firewall_log < 3){
			
			spbc_click_count_security_firewall_log++;
			spbcSettingsFWLogs.start_nubmer = parseInt(spbcSettingsFWLogs.start_nubmer) + parseInt(spbcSettingsFWLogs.show_entries);
			
			var output = {};
			var old_logs = {};
			jQuery(".spbc_fw_log_string").each(function(key, elem){
				
				// console.log('key elem.getAttribute("entry_id")');
				// console.log(elem.getAttribute("entry_id"));
				
				// console.log('elem.innerHTML');
				// console.log(elem.innerHTML);
				
				// console.log('result.data[key]');
				// console.log(result.data[elem.getAttribute("entry_id")]);
				
				console.log(result.data[elem.getAttribute("entry_id")]);
				console.log(elem);
				
				if(result.data[elem.getAttribute("entry_id")] == elem)
					console.log('equal');
				else
					console.log('not equal');
				
				if(result.data[elem.getAttribute("entry_id")]){
					
					console.log('exists ' + elem.getAttribute("entry_id"));
					
					if(result.data[elem.getAttribute("entry_id")] != elem.innerHTML)
						output[elem.getAttribute("entry_id")] = 'updated<tr class="spbc_fw_log_string" entry_id="' + elem.getAttribute("entry_id") + '">' + result.data[elem.getAttribute("entry_id")] + '</tr>';
					else
						output[elem.getAttribute("entry_id")] = 'old'+elem;
					
				}else{
					console.log('not exists ' + elem.getAttribute("entry_id"));
					output[key] = 'new<tr class="spbc_fw_log_string" entry_id="' + elem.getAttribute("entry_id") + '">' + result.data[elem.getAttribute("entry_id")] + '</tr>';
				}
			});
			
			console.log('output');
			console.log(output);
			
		}else{
			spbcButton.hide();
			// Showing Control Panel button
			if(result.user_token){
				spbcButton.siblings('.spbc_show_cp_button').css('display', 'inline-block');
			}
		}
	}
	
	function spbcRequestLogs(action, numberToShow, callback, spbcButton, wrapper){
		
		if(numberToShow > 60)
			numberToShow = 60;
		
		var data = {
			'action'      : action,
			'security'    : spbcSettings.ajax_nonce,
			'show_entries': numberToShow
		};
		
		console.log('Request Data:');
		console.log(data);
		
		jQuery.ajax({
			type: "POST",
			url: spbcSettings.ajaxurl,
			data: data,
			success: function(msg){			
				callback(JSON.parse(msg), spbcButton, wrapper);
			},
			error: function(jqXHR, textStatus, errorThrown) {
				console.log('error');
				console.log(jqXHR);
				console.log(textStatus);
				console.log(errorThrown);
			},
			timeout: 5000
		});
		
	}
	
	// Handler for show more firewall logs
	jQuery('#spbc_show_more_fw_logs_button').on('click', function(){
		
		spbcSettingsFWLogs.start_nubmer = parseInt(spbcSettingsFWLogs.start_nubmer) + parseInt(spbcSettingsFWLogs.show_entries);
		
		var callback   = spbcSecurityFirewallLogCallback,
			spbcButton = jQuery(this),
			wrapper    = jQuery('#spbc_security_firewall_logs_table tbody');
		
		// Showing spinner
		spbcButton.hide()
			.siblings('img').show();
				
		spbcRequestLogs(
			'spbc_show_more_security_firewall_logs',
			spbcSettingsFWLogs.start_nubmer,
			callback,
			spbcButton,
			wrapper
		);
		
	});	
	
	// Timer for FireWall logs
	// var spbcFireWallLogsUpdateTimer = setTimeout(function spbc_heartbeat(){
		
		// var callback   = spbcSecurityFirewallLogCallback,
			// spbcButton = jQuery(this),
			// wrapper    = jQuery('#spbc_security_firewall_logs_table tbody');
		
		// spbcRequestLogs(
			// 'spbc_show_more_security_firewall_logs',
			// 20,
			// callback,
			// spbcButton,
			// wrapper
		// );
		// setTimeout(spbc_heartbeat, 5000);
	// }, 5000);
		
//*/

	jQuery('.spbcShortText').on('mouseover', function(){
		jQuery(this)
			// .hide()
		.siblings('.spbcFullText')
			.css('display', 'block');
	});
	jQuery('.spbcFullText').on('mouseout', function(){
		jQuery(this)
			.hide();
		// .siblings('.spbcShortText')
			// .css('display', 'inline');
	});
});