var spbc_click_count_security_log = 0;

jQuery(document).ready(function(){
		
	jQuery('#showHideLink').on('click', function(){
		jQuery('#spbc_key').val(jQuery('#spbc_key').attr('key'));
		jQuery(this).fadeOut(300);
	});

	jQuery("#spbc_settings_form").on('submit', function(){
		if(jQuery('#spbc_key').val().indexOf('***') != -1)
			jQuery('#showHideLink').click();
	});
	
	
	if(keyIsOk){
		// Switching tabs if key is ok
		jQuery('.spbc_tab_nav').toggleClass('spbc_tab_nav-active');
		jQuery('.spbc_tab').toggleClass('spbc_tab-active');
	}else{
		jQuery('.spbcTopWarning').val('Key is bad');
	}
	
	jQuery('#spbcTopWarning').on('click', '.notice-dismiss', function(){
		jQuery(this).parent('div').fadeOut(300);
	});
	
/* Log uploading */
	
	spbcSettings.start_nubmer = parseInt(spbcSettings.start_nubmer);
	spbcSettings.show_entries = parseInt(spbcSettings.show_entries);
	
	//Tab control
	jQuery('.spbc_tabs_nav_wrapper').on('click', '.spbc_tab_nav', function(){
		jQuery('.spbc_tab_nav').removeClass('spbc_tab_nav-active');
		jQuery('.spbc_tab').removeClass('spbc_tab-active');
		jQuery('#'+this.id.replace('-control', '')).toggleClass('spbc_tab-active');
		jQuery(this).toggleClass('spbc_tab_nav-active');
	});
	
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
				'start_nubmer': spbcSettings.start_nubmer,
				'show_entries': spbcSettings.show_entries
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
					spbcSettings.start_nubmer += spbcSettings.show_entries;
					
					for(i=0; i < spbc_result.count; i++){
						jQuery('#spbc_security_logs_table tbody')
							.append('<tr></tr>')
							.children('tr')
							.last()
								.append('<td>'+spbc_result.data[i].datetime+'</td>')
								.append('<td>'+spbc_result.data[i].user+'</td>')
								.append('<td>'+spbc_result.data[i].action+'</td>')
								.append('<td>'+spbc_result.data[i].page+'</td>')
								.append('<td>'+spbc_result.data[i].page_time+'</td>')
								.append('<td>'+spbc_result.data[i].ip+'</td>');
					}
				}else{
					// No entries loaded
					if(spbc_result.count < 1)
						jQuery('.spbc_no_more_entries').show();
					// Hidding button
					jQuery('#spbc_show_more_button').hide();
					// Showing Control Panel button
					if(spbc_result.user_token){
						jQuery('.spbc_show_cp_button').css('display', 'inline-block');
						jQuery('#spbc_cp_button').attr('href', 'https://cleantalk.org/my?user_token='+spbc_result.user_token+'&cp_mode=security');
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
});