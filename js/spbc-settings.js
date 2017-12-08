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

// Switching tabs
function spbc_switchTab(tab){
	jQuery('.spbc_tab_nav').removeClass('spbc_tab_nav-active');
	jQuery('.spbc_tab').removeClass('spbc_tab-active');
	jQuery(tab).toggleClass('spbc_tab_nav-active');
	jQuery('#'+tab.id.replace('-control', '')).toggleClass('spbc_tab-active');
}

// Settings dependences
function spbcSettingsDependencies(spbcSettingSwitchId){
	var spbcSettingToSwitch = document.getElementById(spbcSettingSwitchId);
	if(spbcSettingToSwitch.getAttribute('disabled') === null)
		spbcSettingToSwitch.setAttribute('disabled', 'disabled');
	else
		spbcSettingToSwitch.removeAttribute('disabled');
}

function spbcStartShowHide(){
	jQuery('.spbcShortText').on('mouseover', function(){ jQuery(this).next().show();	});
	jQuery('.spbcFullText').on('mouseout',   function(){ jQuery(this).hide();           });
}

function spbc_sendAJAXRequest(data, params, obj){
	
	// Default params
	var button   = params.button   || null;
	var spinner  = params.spinner  || null;
	var bar      = params.bar      || null;
	var callback = params.callback || null;
	var notJson  = params.notJson  || null;
	var timeout  = params.timeout  || 15000;
	var obj      = obj             || null;
	
	// Button and spinner
	if(button)  {button.setAttribute('disabled', 'disabled'); button.style.cursor = 'not-allowed'; }
	if(spinner) spinner.style.display = 'inline';
	
	// Adding security code
	data.security = spbcSettings.ajax_nonce;
	
	jQuery.ajax({
		type: "POST",
		url: spbcSettings.ajaxurl,
		data: data,
		success: function(result){
			if(button){  button.removeAttribute('disabled'); button.style.cursor = 'pointer'; }
			if(spinner)  spinner.style.display = 'none';
			if(!notJson) result = JSON.parse(result);
			console.log(result);
			if(callback)
				callback(result, data, params, obj);
		},
		error: function(jqXHR, textStatus, errorThrown){
			if(button){  button.removeAttribute('disabled'); button.style.cursor = 'pointer'; }
			if(spinner)  spinner.style.display = 'none';
			console.log('SPBC_AJAX_ERROR');
			console.log(jqXHR);
			console.log(textStatus);
			console.log(errorThrown);
			alert(errorThrown);
		},
		timeout: timeout,
	});
}
	
// Callback for displaying Firewall logs
var spbcSecurityFirewallLogCallback = function(result, data, params){
	
	var wrapper = jQuery(params.wrapper) || null;
	
	// Deleting style attr for comparison (prepearing element)
	jQuery('.spbcFullText, .spbcShortText').removeAttr('style');
	
	if(Object.keys(result.data).length > jQuery('.spbc_fw_log_string').length){
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
			// Text change
			wrapper.children('.spbc_hint').html(result.text);
			// Adding log rows
			var pattern = '<tr class="spbc_fw_log_string" entry_id="%s"><td>%s</td><td>%s</td><td class="spbc_fw_log_date">%s</td><td class="spbcTextCenter spbc_fw_log_entries">%s</td><td class="spbcTextCenter spbc_fw_log_status">%s</td><td class="spbc_fw_log_url">%s</td><td>%s</td></tr>';
			wrapper.children('.spbc_table_general').show();
			wrapper.children('.spbc_show_more_button_wrapper').show();
			for(var key in result.data){
				wrapper.children('.spbc_table_general').children().children().first().after(
					pattern.printf(key, result.data[key].ip, result.data[key].country, result.data[key].time, result.data[key].entries, result.data[key].status, result.data[key].page_url, result.data[key].user_agent)
				)
				.next().css('background-color', '#33bb33').animate({backgroundColor : '#fdfdfd'}, 5000);
			}
		}
		spbcStartShowHide();
	}else if(params.button){
		// Hide "More logs" button, show "Control Panel" button
		jQuery(params.button).hide();
		jQuery(params.button).siblings('.spbc_show_cp_button').css('display', 'inline-block');
	}
}

// Callback for displaying Security logs
var spbc_SecurityLogCallback = function(result, data, params){
	jQuery(params.wrapper).children('.spbc_sec_log_string').remove();
	if(result.size > 0){
		for(i=0; i < result.size; i++){
			jQuery(params.wrapper)
				.append('<tr class="spbc_sec_log_string"></tr>')
				.children()
				.last()
					.append('<td>'+result.data[i].datetime  +'</td>')
					.append('<td>'+result.data[i].user      +'</td>')
					.append('<td>'+result.data[i].action    +'</td>')
					.append('<td>'+result.data[i].page      +'</td>')
					.append('<td>'+result.data[i].page_time +'</td>')
					.append('<td>'+result.data[i].ip        +'</td>');
		}
	}else{
		// Hide "More logs" button, show "Control Panel" button
		jQuery(params.button).hide();
		jQuery(params.button).siblings('.spbc_show_cp_button').css('display', 'inline-block');
	}
}

jQuery(document).ready(function(){
	
	//Tab control
	jQuery('.spbc_tabs_nav_wrapper').on('click', '.spbc_tab_nav', function(event){ spbc_switchTab(event.currentTarget); });
	
	// if(spbcSettings.key_is_ok) spbc_switchTab(document.getElementById('spbc_security_log-control')); // Switching to security logs tab
	// if(spbcSettings.debug)     spbc_switchTab(document.getElementById('spbc_debug-control'));        // Switch to Debug to tab if debug is iset
	
	spbc_switchTab(document.getElementById('spbc_scaner-control'));
	
	// Temporary
	// spbc_switchTab(document.getElementById('spbc_scaner-control'));   
	
	// Start to hide long values in a table
	spbcStartShowHide();
	
	// Show/Hide access key
	jQuery('#showHideLink').on('click', function(){
		jQuery('#spbc_key').val(jQuery('#spbc_key').attr('key'));
		jQuery(this).fadeOut(300);
	});
		
	// SECURITY LOGS EVENTS
	
	// Handler for show more logs
	jQuery('#spbc_show_more_button').on('click', function(){
		
		if(spbcSettingsSecLogs.clicks < 2){
			spbcSettingsSecLogs.clicks++;
			var data = {
				action: 'spbc_show_more_security_logs',
				amount: spbcSettingsSecLogs.amount * (+spbcSettingsSecLogs.clicks+1),
			};
			var params = {
				button: this,
				spinner: this.nextElementSibling,
				wrapper: document.getElementById('spbc_security_logs_table').firstElementChild,
				callback: spbc_SecurityLogCallback,
			};
			spbc_sendAJAXRequest( data, params );
		}else{
			// Hide "More logs" button, show "Control Panel" button
			jQuery(this).hide();
			jQuery(this).siblings('.spbc_show_cp_button').css('display', 'inline-block');
		}
	});
	
	// FIREWALL LOGS EVENTS
	
	// Handler for show more firewall logs
	jQuery('#spbc_show_more_fw_logs_button').on('click', function(){
		
		if(spbcSettingsFWLogs.clicks < 2){
			spbcSettingsFWLogs.clicks++;
			var data = {
				action: 'spbc_show_more_security_firewall_logs',
				amount: spbcSettingsFWLogs.amount * (+spbcSettingsFWLogs.clicks+1),
			};			
			var params = {
				button: this,
				spinner: this.nextElementSibling,
				wrapper: document.getElementById('spbc_traffic_control').firstElementChild.firstElementChild,
				callback: spbcSecurityFirewallLogCallback,
			};
			spbc_sendAJAXRequest( data, params );
		}else{
			// Hide "More logs" button, show "Control Panel" button
			jQuery(this).hide();
			jQuery(this).siblings('.spbc_show_cp_button').css('display', 'inline-block');
		}
	});	
	
	// Timer for FireWall logs
	var spbcFireWallLogsUpdateTimer = setTimeout(function spbc_heartbeat(){
		// Do refresh only if traffic control is enabled and tab is active
		if(jQuery('#spbc_traffic_control_enabled').attr('checked') && jQuery('#spbc_traffic_control-control').hasClass('spbc_tab_nav-active')){
			var data = {
				action: 'spbc_show_more_security_firewall_logs',
				amount: jQuery('.spbc_fw_log_string').length > +spbcSettingsFWLogs.amount ? jQuery('.spbc_fw_log_string').length : +spbcSettingsFWLogs.amount,
			};
			var params = {
				wrapper: document.getElementById('spbc_traffic_control').firstElementChild.firstElementChild,
				callback: spbcSecurityFirewallLogCallback,
			};
			spbc_sendAJAXRequest( data, params );
		}
		setTimeout(spbc_heartbeat, 30000);
	}, 30000);	
});