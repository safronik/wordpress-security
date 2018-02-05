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