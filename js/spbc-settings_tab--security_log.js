// SECURITY LOGS SHOW MORE
function spbc_sec_logs__show_more__listen(){
	jQuery('#spbc_show_more_button').on('click', function(){
		if(spbcSettingsSecLogs.clicks < 2){
			spbcSettingsSecLogs.clicks++;
			var data = {
				action: 'spbc_show_more_security_logs',
				amount: spbcSettingsSecLogs.amount * (+spbcSettingsSecLogs.clicks+1),
				args: eval('args_'+jQuery('#spbc_tbl__secuirty_logs').attr('id')),
			};
			var params = {
				button: this,
				spinner: this.nextElementSibling,
				wrapper: jQuery('#spbc_tbl__secuirty_logs').find('tbody'),
				callback: spbc_sec_logs__show_more__callback,
			};
			spbc_sendAJAXRequest( data, params );
		}else{
			// Hide "More logs" button, show "Control Panel" button
			jQuery(this).hide();
			jQuery(this).siblings('.spbc__show_more_logs').css('display', 'inline-block');
		}
	});
}

	// Callback for SHOW MORE SECURITY LOGS
function spbc_sec_logs__show_more__callback(result, data, params){	
	console.log(result);
	if(result.size > 0){
		jQuery(params.wrapper).html(result.html);
		spbc_tbl__row_actions__listen();
		spbcStartShowHide();
	}else{
		// Hide "More logs" button, show "Control Panel" button
		jQuery(params.button).hide();
		jQuery(params.button).siblings('.spbc__show_more_logs').css('display', 'inline-block');
	}
}

jQuery(document).ready(function(){
	
	spbc_tbl__row_actions__listen(); // Table. Row actions handler
	spbc_tbl__pagination__listen();  // Table. Pagination handler
	spbc_tbl__sort__listen();        // Table. Sort handler
	
	// Handler for show more SECURITY LOGS
	spbc_sec_logs__show_more__listen();
	
	// Start to hide long values in a table
	spbcStartShowHide();
	
});