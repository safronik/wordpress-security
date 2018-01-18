function spbc_perform_scan_event(obj){
	jQuery(obj).data('scan_status', 'start');
	params = {
		button: obj,
		spinner: obj.nextElementSibling,
		callback: spbc_getHashs_callback,
		progressbar: jQuery('#spbc_scaner_progress_bar'),
		progressbar_text: jQuery('.spbc_progressbar_counter span'),
	};
	params.progressbar.show(200);
	params.progressbar.progressbar('option', 'value', 0);
	params.progressbar_text.text(spbcScaner.progressbar_get_hashs);
	spbc_sendAJAXRequest({action: 'spbc_scanner_get_remote_hashs'}, params, obj);
}

function spbc_get_remote_hashs_event(obj){
	jQuery(obj).data('scan_status', 'getting_hashs');
	params = {
		button: obj,
		spinner: obj.nextElementSibling,
		callback: spbc_getHashs_callback,
	};
	spbc_sendAJAXRequest({action: 'spbc_scanner_get_remote_hashs'}, params);
}

function spbc_scan_event(obj){
	jQuery(obj).data('scan_status', 'surf_scanning_count');
	data = {
		action : 'spbc_scanner_scan',
		offset : 0,
		amount : 300,
		count  : true,
	};
	params = {
		button: obj,
		spinner: obj.nextElementSibling,
		progressbar: jQuery('#spbc_scaner_progress_bar'),
		progressbar_text: jQuery('.spbc_progressbar_counter span'),
		callback: spbc_scan_callback,
	};
	params.progressbar.progressbar('option', 'value', 0);
	params.progressbar_text.text(spbcScaner.progressbar_preparing);
	spbc_sendAJAXRequest(data, params, obj);
}

function spbc_scan_modified_event(obj){
	jQuery(obj).data('scan_status', 'scanning_modified_count');
	data = {
		action : 'spbc_scanner_scan_modified',
		offset : 0,
		amount : 30,
		count  : true,
		status : 'COMPROMISED',
	};
	params = {
		button:   obj,
		spinner:  obj.nextElementSibling,
		progressbar: jQuery('#spbc_scaner_progress_bar'),
		progressbar_text: jQuery('.spbc_progressbar_counter span'),
		callback: spbc_scan_modified_callback,
		timeout:  30000,
	};
	params.progressbar.progressbar('option', 'value', 0);
	params.progressbar_text.text(spbcScaner.progressbar_scan_modified_prep);
	spbc_sendAJAXRequest(data, params, obj);
}

function spbc_scan_links_event(obj){
	jQuery(obj).data('scan_status','scanning_links_count');
	data ={
		action: 'spbc_scanner_scan_links',
	};
	params = {
		button:obj,
		spinner: obj.nextElementSibling,
		progressbar: jQuery('#spbc_scaner_progress_bar'),
		progressbar_text: jQuery('.spbc_progressbar_counter span'),
		callback: spbc_scan_links_callback
	};
	params.progressbar.progressbar('option','value',0);
	params.progressbar_text.text(spbcScaner.progressbar_scan_links);
	spbc_sendAJAXRequest(data, params, obj);
}

function spbc_scanner_list_results_event(obj){
	// List unchecked
	params = {
		button: params.button,
		spinner: params.spinner,
		callback: spbc_listUncheckedFiles_callback,
		wrapper: document.getElementsByClassName('spbc_unchecked_file_list'),
	};
	var data = {
		action: 'spbc_scanner_list_results',
	};
	spbc_sendAJAXRequest(data, params, obj);
}

function spbc_scanner_clear(obj){
	jQuery(obj).data('scan_status', 'clearing');
	var data   = { action : 'spbc_scanner_clear' };
	var params = {
		button: obj,
		spinner: obj.nextElementSibling,
		callback: spbc_scanerClear_callback,
	};
	spbc_sendAJAXRequest(data, params);
}

function spbc_scanner_send_results_event(obj){
	jQuery(obj).data('scan_status', 'sending_results');
	var params = {
		button: obj,
		spinner: obj.nextElementSibling,
		progressbar: jQuery('#spbc_scaner_progress_bar'),
		progressbar_text: jQuery('.spbc_progressbar_counter span'),
		callback: spbc_scannerSendResults_callback,
	};
	var data = {
		action: 'spbc_scanner_send_results',
		total_scanned: jQuery(obj).data('total_scanned'),
		links_scanned: jQuery(obj).data('links_scanned'),
		total_links:   jQuery(obj).data('total_links'),		
	};
	params.progressbar.progressbar('option', 'value', 0);
	params.progressbar_text.text(spbcScaner.progressbar_send_results);
	spbc_sendAJAXRequest(data, params, obj);
}

function spbc_scanner_button_file_send_event(obj){
	var params = {
		button: obj,
		spinner: obj.firstElementChild,
		callback: spbc_scannerButtonFileSend_callback,
	};
	var data = {
		action: 'spbc_scanner_file_send',
		file_id: obj.parentElement.parentElement.getAttribute('file_id'),
	};
	spbc_sendAJAXRequest(data, params);
}

function spbc_scanner_button_file_delete_event(event, obj){
	if(!confirm(spbcScaner.delete_warning)){
		event.preventDefault();
		return;
	}
	var params = {
		button: obj,
		spinner: obj.firstElementChild,
		callback: spbc_scannerButtonFileDelete_callback,
		wrapper: obj.parentElement.parentElement,
		counter: document.getElementsByClassName('spbc_bad_type_count '+ obj.parentElement.parentElement.getAttribute('type') +'_counter')[0],
	};
	var data = {
		action: 'spbc_scanner_file_delete',
		file_id: obj.parentElement.parentElement.getAttribute('file_id'),
	};
	spbc_sendAJAXRequest(data, params);
}

function spbc_scanner_button_file_approve_event(obj){
	var params = {
		button: obj,
		spinner: obj.firstElementChild,
		callback: spbc_scannerButtonFileApprove_callback,
		wrapper: obj.parentElement.parentElement,
		counter: document.getElementsByClassName('spbc_bad_type_count '+ obj.parentElement.parentElement.getAttribute('type') +'_counter')[0],
	};
	var data = {
		action: 'spbc_scanner_file_approve',
		file_id: obj.parentElement.parentElement.getAttribute('file_id'),
	};
	spbc_sendAJAXRequest(data, params);
}

function spbc_scanner_button_file_view_event(obj){
	var params = {
		button: obj,
		spinner: obj.firstElementChild,
		callback: spbc_scannerButtonFileView_callback,
	};
	var data = {
		action: 'spbc_scanner_file_view',
		file_id: obj.parentElement.parentElement.getAttribute('file_id'),
	};
	spbc_sendAJAXRequest(data, params);
}

function spbc_scanner_button_file_compare_event(obj){
	var params = {
		button: obj,
		spinner: obj.firstElementChild,
		callback: spbc_scannerButtonFileCompare_callback,
	};
	var data = {
		action: 'spbc_scanner_file_compare',
		file_id: obj.parentElement.parentElement.getAttribute('file_id'),
	};
	spbc_sendAJAXRequest(data, params);
}

function spbc_scanner_button_file_replace_event(obj){
	var params = {
		button: obj,
		spinner: obj.firstElementChild,
		callback: spbc_scannerButtonFileReplace_callback,
		wrapper: obj.parentElement.parentElement,
		counter: document.getElementsByClassName('spbc_bad_type_count '+ obj.parentElement.parentElement.getAttribute('type') +'_counter')[0],
	};
	var data = {
		action: 'spbc_scanner_file_replace',
		file_id: obj.parentElement.parentElement.getAttribute('file_id'),
	};
	spbc_sendAJAXRequest(data, params);
}

function spbc_setHandlers(delete_handlers){
	
	if(delete_handlers){
		jQuery('.spbc_scanner_button_file_send').off();
		jQuery('.spbc_scanner_button_file_delete').off();
		jQuery('.spbc_scanner_button_file_approve').off();
		jQuery('.spbc_scanner_button_file_view').off();
		jQuery('.spbc_scanner_button_file_compare').off();
		jQuery('.spbc_scanner_button_file_replace').off();
	}
	
	// FILE BUTTONS
	
	// Send file
	jQuery('.spbc_scanner_button_file_send').on('click', function(){    spbc_scanner_button_file_send_event(this); });
	// Delete file
	jQuery('.spbc_scanner_button_file_delete').on('click', function(){  spbc_scanner_button_file_delete_event(event, this); });
	// Approve file
	jQuery('.spbc_scanner_button_file_approve').on('click', function(){ spbc_scanner_button_file_approve_event(this); });
	// View file
	jQuery('.spbc_scanner_button_file_view').on('click', function(){    spbc_scanner_button_file_view_event(this) });
	// Comapre files
	jQuery('.spbc_scanner_button_file_compare').on('click', function(){ spbc_scanner_button_file_compare_event(this); });
	// Replace file
	jQuery('.spbc_scanner_button_file_replace').on('click', function(){ spbc_scanner_button_file_replace_event(this); });
	
	// Pagination
	jQuery('.spbc_page').on('click', function(event){
		event.preventDefault();
		jQuery('.current_page').removeClass('current_page');
		this.firstElementChild.setAttribute('class', 'current_page');
		var params = {
			callback: spbc_listUncheckedFiles_callback,
			wrapper: [this.parentElement.parentElement.parentElement.previousElementSibling],
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