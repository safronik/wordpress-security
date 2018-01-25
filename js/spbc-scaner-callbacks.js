function spbc_scanerClear_callback(result, data, params){
	console.log('DELETED');
}

function spbc_getHashs_callback(result, data, params, obj){
	console.log('HASHS RECEIVED');
	if(result.error){
		setTimeout(function(){
			params.progressbar.fadeOut('slow');
		}, 1000);
		alert('Error happens: ' + result.error_string);
	}else{
		params.progressbar.progressbar('option', 'value', 100);
		params.progressbar_text.text(spbcScaner.progressbar_get_hashs_done);
		spbc_scan_event(obj);
	}
}

function spbc_scan_callback(result, data, params, obj){
	if(data.count){ // First call for count files
		jQuery(obj)
			.data('scan_status', 'surf_scanning')
			.data('total_scanned', +result.files_count);			
		params.progressbar.progressbar('option', 'value', 100);
		params.progressbar_text.text(spbcScaner.progressbar_preparing_done);
		data.scan_precent = +result.files_count / 90;
		data.count = 0;
		spbc_sendAJAXRequest(data, params, obj);
	}else{
		if(result.files_count != 0 && result.files_count >= data.amount){
			data.offset += data.amount;
			params.progressbar_text.text(spbcScaner.progressbar_scan);
			if(params.progressbar.progressbar('option', 'value') == 100)
				params.progressbar.progressbar('option', 'value', 0);
			params.progressbar.progressbar('option', 'value', params.progressbar.progressbar('option', 'value') + Math.floor(data.amount / data.scan_precent));
			spbc_sendAJAXRequest(data, params, obj);
		}else{
			params.progressbar.progressbar('option', 'value', 100);
			params.progressbar_text.text(spbcScaner.progressbar_scan_done);
			console.log('SCAN COMPLETED');
			spbc_scanner_list_results_event(obj);
		}
	}
}

function spbc_scan_modified_callback(result, data, params, obj){
	console.log('MODIFIED FILES SCANNED');
	// Counting amount
	if(data.count){
		jQuery(obj).data('scan_status', 'scanning_modified');
		data.scan_precent = result.files_total / 95;
		data.count = 0;
		params.progressbar.progressbar('option', 'value', 100);
		params.progressbar_text.text(spbcScaner.progressbar_preparing_done);
		spbc_sendAJAXRequest(data, params, obj);
		return;
	}
	// Perform another scan iteration
	if(result.scanned){
		if(params.progressbar.progressbar('option', 'value') == 100)
			params.progressbar.progressbar('option', 'value', 0);
		params.progressbar.progressbar('option', 'value', params.progressbar.progressbar('option', 'value') + Math.floor(data.amount / data.scan_precent));
		params.progressbar_text.text(spbcScaner.progressbar_scan_modified);
		data.offset = +data.offset + 30;
		spbc_sendAJAXRequest(data, params, obj);
		return;		
	}
	// Switching type for scan
	// if(result.scanned === 0 && data.status === 'COMPROMISED'){
		// data.count = 1;
		// data.status = 'UNKNOWN';
		// params.progressbar_text.text('Scanning '+data.status);
		// spbc_sendAJAXRequest(data, params);
		// return;
	// }
	// Last request
	if(result.scanned == 0){
		console.log('FILES SCANNED END');
		params.progressbar.progressbar('option', 'value', 100);
		params.progressbar_text.text(spbcScaner.progressbar_scan_modified_done);
		spbc_scan_links_event(obj);	
	}
}

function spbc_scan_links_callback (result, data, params, obj){
	if (result.scan_links)
	{
		if (result.scanned)
		{
			console.log('LINKS SCANNED');		
			params.progressbar.progressbar('option', 'value', 100);
			params.progressbar_text.text(spbcScaner.progressbar_scan_links_done);	
			jQuery(obj).data('scan_status', 'scanning_links');
			jQuery(obj).data('total_links', +result.links_count);	
		}
		else
		{
			data.offset+=data.amount;
			data.scan_precent = result.urls_count/100;
			params.progressbar.progressbar('option', 'value', Math.ceil(data.offset/data.scan_precent));	
			spbc_sendAJAXRequest(data, params, obj);
			return;
		}		
	}
	else jQuery(obj).data('scan_status', 'scanning_links');
	// List unchecked
	spbc_scanner_list_results_event(obj);
}

function spbc_listUncheckedFiles_callback(result, data, params, obj){
	
	var i = 0,
		item = undefined,
		actions = '';
		
	for(type in result.data){
		var wrapper = params.wrapper[i];
			hint = wrapper.previousElementSibling,
			pagination = jQuery(wrapper.nextElementSibling),
			header = jQuery(wrapper.parentElement.previousElementSibling);
		// Rows
		jQuery(wrapper).find('.spbc_scan_result_row').remove();
		for(key in result.data[type].list){
			item = result.data[type].list[key];
			// Different button set for different file types
			if(type == 'unknown'){
				actions = spbcScaner.actions_unknown;
				if( +item.size == 0 || +item.size > 1048570 || (+item.mtime < +item.last_sent && item.last_sent !== null)){
					actions = actions.replace('file_send"', 'file_send" disabled');
				}
			}else{
				actions = spbcScaner.actions_modified;
			}
			if (type == 'outbound links'){
				var url_text = (key.length>=60)?'<span class = "spbcShortText">'+key.substr(0,60)+'...</span>':key;
				var page_url_text = (item.page_url.length>=60)?'<span class = "spbcShortText">'+item.page_url.substr(0,60)+'...</span>':item.page_url;
				var link_text = (item.link_text.length>=60)?'<span class = "spbcShortText">'+item.link_text.substr(0,60)+'...</span>':item.link_text;
				jQuery(wrapper).find('tbody').append(spbcScaner.row_template_links.printf(key,url_text,item.page_url,page_url_text,link_text));
			}
			else
				jQuery(wrapper).find('tbody').append(spbcScaner.row_template.printf(type, item.fast_hash, item.path, item.size_str, item.perms, item.mtime_str, actions));
		}
		// Table visibility and Text
		if(result.data[type].amount > 0){
			wrapper.style.display = 'block';
			hint.innerHTML = spbcScaner.result_text_bad_template.printf(result.data[type].amount)
			pagination.find('li.pagination').remove();
			var pages = Math.ceil(+result.data[type].amount / +spbcScaner.on_page),
				curr_page = data.page || 1;
			for(var page = 1; page <= pages; page++){
				pagination.find('ul.pagination').append(spbcScaner.page_selector_template.printf(type, page, (page == curr_page ? ' class=\'current_page\'' : ''), page))
			}
			if(pages < 2)
				pagination.hide();
			else
				pagination.show();
			spbc_setHandlers(true);
		}else{
			wrapper.style.display = 'none';
			hint.innerHTML = spbcScaner.result_text_good_template;
			pagination.hide();
		}
		header.find('.spbc_bad_type_count').text(result.data[type].amount);
		i++;
	}
	
	// Perform detailed scan
	if(jQuery(obj).data('scan_status') == 'surf_scanning'){
		console.log('status '+jQuery(obj).data('scan_status'));
		if( +result.data.compromised.amount < 30){
			spbc_scan_modified_event(obj);
		}else{
			if(confirm(spbcScaner.scan_modified_confiramation))
				spbc_scan_modified_event(obj);
			else
				alert(spbcScaner.warning_about_cancel);
		}
	// Send results
	}else if(jQuery(obj).data('scan_status') == 'scanning_links'){
		console.log('status '+jQuery(obj).data('scan_status'));
		spbc_scanner_send_results_event(obj);
	}
	spbcStartShowHide();
}

function spbc_scannerSendResults_callback(result, data, params, obj){
	console.log('RESULTS_SENT');
	params.progressbar.progressbar('option', 'value', 100);
	params.progressbar_text.text(spbcScaner.progressbar_send_results_done);
	obj.parentElement.previousElementSibling.innerHTML = spbcScaner.look_below_for_scan_res + spbcScaner.view_all_results;
	if (data.total_links)
		obj.parentElement.nextElementSibling.innerHTML = spbcScaner.last_scan_was_just_now.printf(data.total_scanned, data.total_links);
	else obj.parentElement.nextElementSibling.innerHTML = spbcScaner.last_scan_was_just_now.printf(data.total_scanned);
	jQuery('#spbc_scanner_status_icon').attr('src', spbcSettings.img_path + '/yes.png');
	setTimeout(function(){
		params.progressbar.fadeOut('slow');
	}, 1000);
	
}

function spbc_scannerButtonFileSend_callback(result, data, params){
	console.log('FILE_SENT');
	if(result.error) alert('Error happens: ' + result.error_string);
	if(result.success){
		params.button.setAttribute('disabled', 'disabled'); 
		params.button.style.cursor = 'not-allowed';
	}
}

function spbc_scannerButtonFileDelete_callback(result, data, params){
	console.log('FILE_DELETED');
	if(result.error) alert('Error happens: ' + result.error_string);
	if(result.success === true){
		params.counter.innerHTML = +params.counter.innerHTML - 1;
		if(params.counter.innerHTML == 0){ params.wrapper.parentElement.parentElement.parentElement.style.display = 'none'; }
		params.wrapper.parentElement.removeChild(params.wrapper);
	}	
}

function spbc_scannerButtonFileApprove_callback(result, data, params){
	console.log('FILE_APPROVED');
	if(result.error) alert('Error happens: ' + result.error_string);
	if(result.success === true){
		params.counter.innerHTML = +params.counter.innerHTML - 1;
		if(params.counter.innerHTML == 0){ params.wrapper.parentElement.parentElement.parentElement.style.display = 'none'; }
		params.wrapper.parentElement.removeChild(params.wrapper);
	}
}

function spbc_scannerButtonFileReplace_callback(result, data, params){
	console.log('FILE_REPLACED');
	if(result.error) alert('Error happens: ' + result.error_string);
	if(result.success === true){
		params.counter.innerHTML = +params.counter.innerHTML - 1;
		if(params.counter.innerHTML == 0){ params.wrapper.parentElement.parentElement.parentElement.style.display = 'none'; }
		params.wrapper.parentElement.removeChild(params.wrapper);
	}
}

function spbc_scannerButtonFileCompare_callback(result, data, params){
	console.log('FILE_COMPARED');
	if(result.error){alert('Error happens: ' + result.error_string); return;}
	var row_template = '<div class="spbc_compare_file_row_wrapper"><span class="spbc_compare_file_row_num">%s</span><p class="spbc_compare_file_row">%s</p><p class="spbc_compare_file_row">%s</p><br /></div>';
	jQuery('#spbc_dialog').empty();
	for(var row=1, prev = false, next = false; typeof result.file[row] != 'undefined' || typeof result.file_original[row] != 'undefined'; row++){
		if(typeof result.file[row] == 'undefined')          result.file[row] = '';
		if(typeof result.file_original[row] == 'undefined') result.file_original[row] = '';
		if(result.difference.indexOf(row) !== -1){
			jQuery('#spbc_dialog').append(row_template.printf(row, result.file[row], result.file_original[row]));
			jQuery('#spbc_dialog').append(row_template.printf(row, result.file[row], result.file_original[row]));
			jQuery('#spbc_dialog').append(row_template.printf(row, result.file[row], result.file_original[row]));
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

function spbc_scannerButtonFileView_callback(result, data, params){
	console.log('FILE_VIEWED');
	if(result.error){alert('Error happens: ' + result.error_string); return;}
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

function spbc_setHandlers(){
	
	// Send file
	jQuery('.spbc_scanner_button_file_send').on('click', function(){
		var params = {
			button: this,
			spinner: this.firstElementChild,
			callback: spbc_scannerButtonFileSend_callback,
		};
		var data = {
			action: 'spbc_scanner_file_send',
			file_id: this.parentElement.parentElement.getAttribute('file_id'),
		};
		spbc_sendAJAXRequest(data, params);
	});
	
	// Delete file
	jQuery('.spbc_scanner_button_file_delete').on('click', function(){
		var params = {
			button: this,
			spinner: this.firstElementChild,
			callback: spbc_scannerButtonFileDelete_callback,
			wrapper: this.parentElement.parentElement,
		};
		var data = {
			action: 'spbc_scanner_file_delete',
			file_id: this.parentElement.parentElement.getAttribute('file_id'),
		};
		spbc_sendAJAXRequest(data, params);
	});
	
	// Approve file
	jQuery('.spbc_scanner_button_file_approve').on('click', function(){
		var params = {
			button: this,
			spinner: this.firstElementChild,
			callback: spbc_scannerButtonFileApprove_callback,
			wrapper: this.parentElement.parentElement,
		};
		var data = {
			action: 'spbc_scanner_file_approve',
			file_id: this.parentElement.parentElement.getAttribute('file_id'),
		};
		spbc_sendAJAXRequest(data, params);
	});
	
	// View file
	jQuery('.spbc_scanner_button_file_view').on('click', function(){
		var params = {
			button: this,
			spinner: this.firstElementChild,
			callback: spbc_scannerButtonFileView_callback,
		};
		var data = {
			action: 'spbc_scanner_file_view',
			file_id: this.parentElement.parentElement.getAttribute('file_id'),
		};
		spbc_sendAJAXRequest(data, params);
	});
	
	// Comapre files
	jQuery('.spbc_scanner_button_file_compare').on('click', function(){
		var params = {
			button: this,
			spinner: this.firstElementChild,
			callback: spbc_scannerButtonFileCompare_callback,
		};
		var data = {
			action: 'spbc_scanner_file_compare',
			file_id: this.parentElement.parentElement.getAttribute('file_id'),
		};
		spbc_sendAJAXRequest(data, params);
	});
	
	// Replace file
	jQuery('.spbc_scanner_button_file_replace').on('click', function(){
		var params = {
			button: this,
			spinner: this.firstElementChild,
			callback: spbc_scannerButtonFileReplace_callback,
			wrapper: this.parentElement.parentElement,
		};
		var data = {
			action: 'spbc_scanner_file_replace',
			file_id: this.parentElement.parentElement.getAttribute('file_id'),
		};
		spbc_sendAJAXRequest(data, params);
	});
	
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