function spbc_setHandlers(delete_handlers){
	
	delete_handlers = delete_handlers || true;
	
	if(delete_handlers){
		jQuery('.spbc_scanner_button_file_send').off();
		jQuery('.spbc_scanner_button_file_delete').off();
		jQuery('.spbc_scanner_button_file_approve').off();
		jQuery('.spbc_scanner_button_file_view').off();
		jQuery('.spbc_scanner_button_file_compare').off();
		jQuery('.spbc_scanner_button_file_replace').off();
	}
	
	// FILE BUTTONS
	jQuery('.spbc_scanner_button_file_send').on('click',    function(){ spbc_scanner_button_file_send_event(this);          }); // Send file
	jQuery('.spbc_scanner_button_file_delete').on('click',  function(){ spbc_scanner_button_file_delete_event(event, this); }); // Delete file
	jQuery('.spbc_scanner_button_file_approve').on('click', function(){ spbc_scanner_button_file_approve_event(this);       }); // Approve file
	jQuery('.spbc_scanner_button_file_view').on('click',    function(){ spbc_scanner_button_file_view_event(this)           }); // View file
	jQuery('.spbc_scanner_button_file_compare').on('click', function(){ spbc_scanner_button_file_compare_event(this);       }); // Comapre files
	jQuery('.spbc_scanner_button_file_replace').on('click', function(){ spbc_scanner_button_file_replace_event(this);       }); // Replace file
	
	// Pagination
	jQuery('.spbc_page').on('click', function(event){
		event.preventDefault();
		jQuery('.current_page').removeClass('current_page');
		this.firstElementChild.setAttribute('class', 'current_page');
		var params = {
			callback: jQuery.spbc.scanner.listResults_callback,
		};
		var data = {
			action: 'spbc_scanner_list_results',
			type: this.getAttribute('type'),
			page: this.getAttribute('page'),
			offset: (this.getAttribute('page')-1) * 20,
			amount: 20,
		};
		spbc_sendAJAXRequest(data, params);
	});
}

jQuery(document).ready(function(){
	
	// Preparing progressbar
	jQuery('#spbc_scaner_progress_bar').progressbar({
		value: 0,
		create: function( event, ui ) {
			event.target.style.position = 'relative';
			event.target.style.marginBottom = '12px';
		},
		change: function(event, ui){
			jQuery('.spbc_progressbar_counter span').text(jQuery(event.target).progressbar('option', 'value') + ' %');
		},
	});
	
	// Preparing accordion
	jQuery('#spbc_scan_accordion').accordion({
		header: "h3",
		heightStyle: 'content',
		collapsible: true,
		active: false,
		beforeActivate: function(event, ui){
			// jQuery( "#spbc_scan_accordion" ).accordion( "option", "active", 2 );
		},
	});
	
	// Init scanner plugin
	jQuery('#spbc_perform_scan').spbcScannerPlugin({
		status: 'stopped',
		button: jQuery('#spbc_perform_scan'),
		spinner: jQuery('#spbc_perform_scan').next(),
		callback: null,
		progressbar: jQuery('#spbc_scaner_progress_bar'),
		progressbar_text: jQuery('.spbc_progressbar_counter span'),
		wrapper: document.getElementsByClassName('spbc_unchecked_file_list'),
	});
	
	// EVENT HADLING
	spbc_setHandlers(true);
		
	jQuery('#spbc_perform_scan').on('click', function(){
		jQuery.spbc.scanner.control(null, null, 'start');
	});
	
	//DEBUG
		// Clear table
		jQuery('#spbc_scanner_clear').on('click', function(){
			jQuery.spbc.scanner.clear();
		});
	
});