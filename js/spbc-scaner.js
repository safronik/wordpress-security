jQuery(document).ready(function(){
	
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
	
	jQuery('#spbc_scan_accordion').accordion({
		header: "h3",
		heightStyle: 'content',
		collapsible: true,
		active: false,
		beforeActivate: function(event, ui){
			// jQuery( "#spbc_scan_accordion" ).accordion( "option", "active", 2 );
		},
	});
	
	// EVENT HADLING
	spbc_setHandlers();
	
	var main_button = document.getElementById('spbc_perform_scan');
	// Perform scan
	jQuery('#spbc_perform_scan').on('click', function(){  spbc_get_remote_hashs_event(main_button); });
	
	// Get remote hashs
	jQuery('#spbc_get_hashs').on('click', function(){     spbc_get_remote_hashs_event(main_button, true); });
	// Start scan files
	jQuery('#spbc_scan').on('click', function(){          spbc_scan_event(main_button, true); });
	// Start scan modified files
	jQuery('#spbc_scan_modified').on('click', function(){ spbc_scan_modified_event(main_button, true); });
	// Clear table
	jQuery('#spbc_scanner_clear').on('click', function(){ spbc_scanner_clear_event(main_button, true); });
	// Send scan results
	jQuery('#spbc_scanner_send_results').on('click', function(){ spbc_scanner_send_results_event(main_button, true); });
	
});