function spbc_scanner_button_file_view_event(obj){
	var self = jQuery(obj);
	var data = {
		action: 'spbc_scanner_file_view',
		file_id: self.parent().attr('uid'),
	};
	var params = {
		spinner: self.parent().siblings('.tbl-preloader--tiny'),
		callback: spbc_scannerButtonFileView_callback,
	};
	spbc_sendAJAXRequest(data, params);
}

function spbc_scannerButtonFileView_callback(result, data, params){
	console.log('FILE_VIEWED');
	var row_template = '<div class="spbc_view_file_row_wrapper"><span class="spbc_view_file_row_num">%s</span><p class="spbc_view_file_row">%s</p><br /></div>';
	jQuery('#spbc_dialog').empty();
	for(row in result.file){
		jQuery('#spbc_dialog').append(row_template.printf(row, result.file[row]));
	}
	
	var content_height = Object.keys(result.file).length * 19 + 19,
		visible_height = (document.documentElement.clientHeight) / 100 * 75;
	var overflow = content_height < visible_height ? 'no_scroll' : 'scroll';
	
	jQuery('#spbc_dialog').data('overflow', overflow);
	jQuery('#spbc_dialog').dialog({
		modal:true, 
		title: result.file_path,
		position: { my: "center top", at: "center top+40px" , of: window },
		width: +(jQuery('#wpbody').width() / 100 * 70),
		height: overflow == 'scroll' ? visible_height : content_height,
		show: { effect: "blind", duration: 500 },
		draggable: false,
		resizable: false,
		closeText: "X",
		open: function(event, ui) {
			console.log(jQuery(event.target).data('overflow'));
			document.body.style.overflow = 'hidden';
			if(jQuery(event.target).data('overflow') == 'scroll') event.target.style.overflow = 'scroll';
		},
		beforeClose: function(event, ui) { document.body.style.overflow = 'auto'; },
	});
}

function spbc_scanner_button_file_view_bad_event(obj){
	var self = jQuery(obj);
	var data = {
		action: 'spbc_scanner_file_view',
		file_id: self.parent().attr('uid'),
	};
	var params = {
		spinner: self.parent().siblings('.tbl-preloader--tiny'),
		callback: spbc_scannerButtonFileViewBad_callback,
	};
	spbc_sendAJAXRequest(data, params);
}

function spbc_scannerButtonFileViewBad_callback(result, data, params){
	console.log('FILE_VIEWED_BAD_CODE');
	console.log(arguments);
	result.difference = JSON.parse(result.difference);
	result.weak_spots = JSON.parse(result.weak_spots);
	var row_template     = '<div class="spbc_view_file_row_wrapper"><span class="spbc_view_file_row_num">%s</span><p class="spbc_view_file_row">%s</p><br /></div>';
	var row_template_bad = '<div class="spbc_view_file_row_wrapper" style="background: rgba(200,40,40,0.5);"><span class="spbc_view_file_row_num">%s</span><p class="spbc_view_file_row">%s</p><br /></div>';
	jQuery('#spbc_dialog').empty();
	for(var row = 1; typeof result.file[row] !== 'undefined'; row++){
		if(result.difference.indexOf(row) !== -1){
			jQuery('#spbc_dialog').append(row_template.    printf(row-2, result.file[row-2]));
			jQuery('#spbc_dialog').append(row_template.    printf(row-1, result.file[row-1]));
			jQuery('#spbc_dialog').append(row_template_bad.printf(row,   result.file[row]));
			jQuery('#spbc_dialog').append(row_template.    printf(row+1, result.file[row+1]));
			jQuery('#spbc_dialog').append(row_template.    printf(row+2, result.file[row+2]));
			jQuery('#spbc_dialog').append(row_template.    printf('', ''));
		}
	}
	
	var content_height = jQuery('#spbc_dialog div').length * 19 + 19,
		visible_height = (document.documentElement.clientHeight) / 100 * 75;
	var overflow = content_height < visible_height ? 'no_scroll' : 'scroll';
	
	jQuery('#spbc_dialog').data('overflow', overflow);
	jQuery('#spbc_dialog').dialog({
		modal:true, 
		title: result.file_path,
		position: { my: "center top", at: "center top+40px" , of: window },
		width: +(jQuery('#wpbody').width() / 100 * 70),
		height: overflow == 'scroll' ? visible_height : content_height,
		show: { effect: "blind", duration: 500 },
		draggable: false,
		resizable: false,
		closeText: "X",
		open: function(event, ui) {
			console.log(jQuery(event.target).data('overflow'));
			document.body.style.overflow = 'hidden';
			if(jQuery(event.target).data('overflow') == 'scroll') event.target.style.overflow = 'scroll';
		},
		beforeClose: function(event, ui) { document.body.style.overflow = 'auto'; },
	});
}

function spbc_scanner_button_file_compare_event(obj){
	var self = jQuery(obj);
	var data = {
		action: 'spbc_scanner_file_compare',
		file_id: self.parent().attr('uid'),
	};
	var params = {
		spinner: self.parent().siblings('.tbl-preloader--tiny'),
		callback: spbc_scannerButtonFileCompare_callback,
	};
	spbc_sendAJAXRequest(data, params);
}

function spbc_scannerButtonFileCompare_callback(result, data, params){
	console.log('FILE_COMPARED');
	result.difference = JSON.parse(result.difference);
	var row_template = '<div class="spbc_compare_file_row_wrapper"><span class="spbc_compare_file_row_num">%s</span><p class="spbc_compare_file_row">%s</p><p class="spbc_compare_file_row">%s</p><br /></div>';
	jQuery('#spbc_dialog').empty();
	for(var row=1, prev = false, next = false; typeof result.file[row] != 'undefined' || typeof result.file_original[row] != 'undefined'; row++){
		if(typeof result.file[row] == 'undefined')          result.file[row] = '';
		if(typeof result.file_original[row] == 'undefined') result.file_original[row] = '';
		if(result.difference.indexOf(row) !== -1){
			jQuery('#spbc_dialog').append(row_template.printf(row-2, result.file[row-2], result.file_original[row-2]));
			jQuery('#spbc_dialog').append(row_template.printf(row-1, result.file[row-1], result.file_original[row-1]));
			jQuery('#spbc_dialog').append(row_template.printf(row,   result.file[row],   result.file_original[row]));
			jQuery('#spbc_dialog').append(row_template.printf(row+1, result.file[row+1], result.file_original[row+1]));
			jQuery('#spbc_dialog').append(row_template.printf(row+2, result.file[row+2], result.file_original[row+2]));
		}
	}
	
	var content_height = jQuery('#spbc_dialog div').length * 19 + 19,
		visible_height = (document.documentElement.clientHeight) / 100 * 75;
	var overflow = content_height < visible_height ? 'no_scroll' : 'scroll';
	
	jQuery('#spbc_dialog').data('overflow', overflow);
	jQuery('#spbc_dialog').dialog({
		modal:true, 
		title: result.file_path,
		position: { my: "center top", at: "center top+40px" , of: window },
		width: 700, //+(jQuery('#wpbody').width() / 100 * 70),
		height: overflow == 'scroll' ? visible_height + 20 : content_height + 20,
		show: { effect: "blind", duration: 500 },
		draggable: false,
		resizable: false,
		closeText: "X",
		open: function(event, ui) {
			console.log(jQuery(event.target).data('overflow'));
			document.body.style.overflow = 'hidden';
			if(jQuery(event.target).data('overflow') == 'scroll') event.target.style.overflow = 'scroll';
		},
		beforeClose: function(event, ui) { document.body.style.overflow = 'auto'; }
	});
}

jQuery(document).ready(function(){
	
	// EVENT HADLING
	spbc_tbl__bulk_actions__listen(); // Table. Row bulk handler
	spbc_tbl__row_actions__listen();  // Table. Row actions handler
	spbc_tbl__pagination__listen();   // Table. Pagination handler
	spbc_tbl__sort__listen();         // Table. Sort handler
	
	spbcStartShowHide();
	
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
		status: null,
		button: jQuery('#spbc_perform_scan'),
		spinner: jQuery('#spbc_perform_scan').next(),
		callback: null,
		progress_overall: jQuery('#spbc_scaner_progress_overall'),
		progressbar: jQuery('#spbc_scaner_progress_bar'),
		progressbar_text: jQuery('.spbc_progressbar_counter span'),
		wrapper: document.getElementsByClassName('spbc_unchecked_file_list'),
	});
	
	
		
	jQuery('#spbc_perform_scan').on('click', function(){
		jQuery.spbc.scanner.control(null, null, true);
	});
	
	//DEBUG
		// Clear table
		jQuery('#spbc_scanner_clear').on('click', function(){
			jQuery.spbc.scanner.clear();
		});
	
});