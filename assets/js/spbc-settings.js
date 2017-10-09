// Printf for JS
String.prototype.printf = function(){
    var formatted = this;
    for( var arg in arguments ) {
		var before_formatted = formatted.substring(0, formatted.indexOf("%s", 0));
		var after_formatted  = formatted.substring(formatted.indexOf("%s", 0)+2, formatted.length);
		formatted = before_formatted + arguments[arg] + after_formatted;
    }
    return formatted;
};

var spbc_click_count_security_log = 0,
	spbc_click_count_security_firewall_log = 0;

// Settings dependences
function spbcSettingsDependencies(spbcSettingSwitchId){
	
	var spbcSettingToSwitch = jQuery('#'+spbcSettingSwitchId);
		
	if(spbcSettingToSwitch.attr('disabled') === undefined)
		spbcSettingToSwitch.attr('disabled', 'disabled');
	else
		spbcSettingToSwitch.removeAttr('disabled');
	
}

function spbcStartShowHide(){
	jQuery('.spbcShortText').on('mouseover', function(){
		jQuery(this)
		.siblings('.spbcFullText')
			.css('display', 'block')
	});
	jQuery('.spbcFullText').on('mouseout', function(){
		jQuery(this)
			.hide();
	});
}

// Performs AJAX request for logs
	function spbcRequestLogs(action, numberToShow, callback, spbcButton, wrapper){
		
		if(numberToShow > 60)
			numberToShow = 60;
		
		var data = {
			'action'      : action,
			'security'    : spbcSettings.ajax_nonce,
			'show_entries': numberToShow
		};
		
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

jQuery(document).ready(function(){
	
	// Start to hide long values in a table
	spbcStartShowHide();
	
	// Show/Hide access key
	jQuery('#showHideLink').on('click', function(){
		jQuery('#spbc_key').val(jQuery('#spbc_key').attr('key'));
		jQuery(this).fadeOut(300);
	});

	jQuery("#spbc_settings_form").on('submit', function(){
		if(jQuery('#spbc_key').val().indexOf('***') != -1)
			jQuery('#showHideLink').click();
	});
	
	// Switching to security logs tab
	if(keyIsOk){
		jQuery('.spbc_tab_nav').removeClass('spbc_tab_nav-active')
			.filter('#spbc_security_log-control').toggleClass('spbc_tab_nav-active');
		jQuery('.spbc_tab').removeClass('spbc_tab-active')
			.filter('#spbc_security_log').toggleClass('spbc_tab-active');
	}else{
		jQuery('.spbcTopWarning').val('Key is bad');
	}
	
	// Switch to Debug to tab if debug is iset
	if(spbcSettings.debug){
		jQuery('.spbc_tab_nav').removeClass('spbc_tab_nav-active')
			.filter('#spbc_debug-control').toggleClass('spbc_tab_nav-active');
		jQuery('.spbc_tab').removeClass('spbc_tab-active')
			.filter('#spbc_debug').toggleClass('spbc_tab-active');
	}
	
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

/*/
	// Callback for displaying Firewall logs
	var spbcSecurityFirewallLogCallback = function(result, spbcButton, wrapper, timestamp){
		
		// Hiding spinner
		spbcButton.show().siblings('img').hide();
		// Deleting style attr for comparison (prepearing element)
		jQuery('.spbcFullText, .spbcShortText').removeAttr('style');
				
		if(result.count){
			
			jQuery(".spbc_fw_log_string").each(function(key, elem){
				
				var elem   = jQuery(elem),
					log_id = elem.attr("entry_id");
					
				if(result.data[log_id]){
					if(encodeURIComponent(elem.children('.spbc_fw_log_date').html()) != encodeURIComponent(result.data[log_id].time)){
						elem.children('.spbc_fw_log_date').html(result.data[log_id].time)
							.css('background-color', '#33bb33').animate({backgroundColor : '#fdfdfd'}, 5000);
					}
					if(elem.children('.spbc_fw_log_entries').html() != result.data[log_id].entries){
						elem.children('.spbc_fw_log_entries').html(result.data[log_id].entries)
							.css('background-color', '#33bb33').animate({backgroundColor : '#fdfdfd'}, 5000);
					}
					if(elem.children('.spbc_fw_log_status').html() != result.data[log_id].status){
						elem.children('.spbc_fw_log_status').html(result.data[log_id].status)
							.css('background-color', '#33bb33').animate({backgroundColor : '#fdfdfd'}, 5000);
					}
					if(elem.children('.spbc_fw_log_url').html().replace(/&amp;/g, '&') != result.data[log_id].page_url){ //Replacing &amp; with &
						elem.children('.spbc_fw_log_url').html(result.data[log_id].page_url)
							.css('background-color', '#33bb33').animate({backgroundColor : '#fdfdfd'}, 5000);
					}
					delete result.data[log_id];
				}
				
			});
			
			if(Object.keys(result.data).length > 0){
				var pattern = '<tr class="spbc_fw_log_string" entry_id="%s"><td>%s</td><td>%s</td><td class="spbc_fw_log_date">%s</td><td class="spbcTextCenter spbc_fw_log_entries">%s</td><td class="spbcTextCenter spbc_fw_log_status">%s</td><td class="spbc_fw_log_url">%s</td><td>%s</td></tr>';
				for(var key in result.data){
					jQuery(wrapper).children().first().after(
						pattern.printf(key, result.data[key].ip, result.data[key].country, result.data[key].time, result.data[key].entries, result.data[key].status, result.data[key].page_url, result.data[key].user_agent)
					)
					.next().css('background-color', '#33bb33').animate({backgroundColor : '#fdfdfd'}, 5000);
				}
			}
			spbcStartShowHide();
		}
	}
	
	// Handler for "Show more security logs" button
	jQuery('#spbc_show_more_button').on('click', function(){
		var callback   = spbcSecurityFirewallLogCallback,
			spbcButton = jQuery(this),
			wrapper    = jQuery('#spbc_security_firewall_logs_table tbody');
		// Hide buttnon, show spinner
		spbcButton.hide().siblings('img').show();
			
		spbcRequestLogs(
			'spbc_show_more_security_logs_callback',
			spbcSettingsSecLogs.start_nubmer,
			callback,
			spbcButton,
			wrapper
		);
	});
//*/
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
	
	// Firewall logs
	
	// Callback for displaying Firewall logs
	var spbcSecurityFirewallLogCallback = function(result, spbcButton, wrapper, timestamp){
		
		// Hiding spinner
		spbcButton.show().siblings('img').hide();
		// Deleting style attr for comparison (prepearing element)
		jQuery('.spbcFullText, .spbcShortText').removeAttr('style');
				
		if(result.count){
			
			jQuery(".spbc_fw_log_string").each(function(key, elem){
				
				var elem   = jQuery(elem),
					log_id = elem.attr("entry_id");
					
				if(result.data[log_id]){
					if(encodeURIComponent(elem.children('.spbc_fw_log_date').html()) != encodeURIComponent(result.data[log_id].time)){
						elem.children('.spbc_fw_log_date').html(result.data[log_id].time)
							.css('background-color', '#33bb33').animate({backgroundColor : '#fdfdfd'}, 5000);
					}
					if(elem.children('.spbc_fw_log_entries').html() != result.data[log_id].entries){
						elem.children('.spbc_fw_log_entries').html(result.data[log_id].entries)
							.css('background-color', '#33bb33').animate({backgroundColor : '#fdfdfd'}, 5000);
					}
					if(elem.children('.spbc_fw_log_status').html() != result.data[log_id].status){
						elem.children('.spbc_fw_log_status').html(result.data[log_id].status)
							.css('background-color', '#33bb33').animate({backgroundColor : '#fdfdfd'}, 5000);
					}
					if(elem.children('.spbc_fw_log_url').html().replace(/&amp;/g, '&') != result.data[log_id].page_url){ //Replacing &amp; with &
						elem.children('.spbc_fw_log_url').html(result.data[log_id].page_url)
							.css('background-color', '#33bb33').animate({backgroundColor : '#fdfdfd'}, 5000);
					}
					delete result.data[log_id];
				}
				
			});
			
			if(Object.keys(result.data).length > 0){
				var pattern = '<tr class="spbc_fw_log_string" entry_id="%s"><td>%s</td><td>%s</td><td class="spbc_fw_log_date">%s</td><td class="spbcTextCenter spbc_fw_log_entries">%s</td><td class="spbcTextCenter spbc_fw_log_status">%s</td><td class="spbc_fw_log_url">%s</td><td>%s</td></tr>';
				for(var key in result.data){
					jQuery(wrapper).children().first().after(
						pattern.printf(key, result.data[key].ip, result.data[key].country, result.data[key].time, result.data[key].entries, result.data[key].status, result.data[key].page_url, result.data[key].user_agent)
					)
					.next().css('background-color', '#33bb33').animate({backgroundColor : '#fdfdfd'}, 5000);
				}
			}
			
			spbcStartShowHide();
			
		}
	}
		
	// Handler for show more firewall logs
	jQuery('#spbc_show_more_fw_logs_button').on('click', function(){
		
		var callback   = spbcSecurityFirewallLogCallback,
			spbcButton = jQuery(this),
			wrapper    = jQuery('#spbc_security_firewall_logs_table tbody');
		
		if(spbc_click_count_security_firewall_log < 2){
				
			spbcSettingsFWLogs.start_nubmer = parseInt(spbcSettingsFWLogs.start_nubmer) + parseInt(spbcSettingsFWLogs.show_entries);
			spbc_click_count_security_firewall_log++;
			
			
			
			// Hide buttnon, show spinner
			spbcButton.hide().siblings('img').show();
					
			spbcRequestLogs(
				'spbc_show_more_security_firewall_logs',
				spbcSettingsFWLogs.start_nubmer,
				callback,
				spbcButton,
				wrapper
			);
			
		}else{
			spbcButton.hide();
			// Showing Control Panel button
			spbcButton.siblings('.spbc_show_cp_button').css('display', 'inline-block');
		}
		
	});	
	
	// Timer for FireWall logs
	var spbcFireWallLogsUpdateTimer = setTimeout(function spbc_heartbeat(){
		
		var callback   = spbcSecurityFirewallLogCallback,
			spbcButton = jQuery(this),
			wrapper    = jQuery('#spbc_security_firewall_logs_table tbody');
		
		spbcRequestLogs(
			'spbc_show_more_security_firewall_logs',
			spbcSettingsFWLogs.start_nubmer,
			callback,
			spbcButton,
			wrapper
		);
		setTimeout(spbc_heartbeat, 30000);
	}, 30000);
});