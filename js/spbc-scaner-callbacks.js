function spbc_scannerButtonFileSend_callback(result, data, params){
	console.log('FILE_SENT');
	if(result.success){
		params.button.setAttribute('disabled', 'disabled'); 
		params.button.style.cursor = 'not-allowed';
	}
}

function spbc_scannerButtonFileDelete_callback(result, data, params){
	console.log('FILE_DELETED');
	if(result.success === true){
		params.counter.innerHTML = +params.counter.innerHTML - 1;
		if(params.counter.innerHTML == 0){ params.wrapper.parentElement.parentElement.parentElement.style.display = 'none'; }
		params.wrapper.parentElement.removeChild(params.wrapper);
	}	
}

function spbc_scannerButtonFileApprove_callback(result, data, params){
	console.log('FILE_APPROVED');
	if(result.success === true){
		params.counter.innerHTML = +params.counter.innerHTML - 1;
		if(params.counter.innerHTML == 0){ params.wrapper.parentElement.parentElement.parentElement.style.display = 'none'; }
		params.wrapper.parentElement.removeChild(params.wrapper);
	}
}

function spbc_scannerButtonFileReplace_callback(result, data, params){
	console.log('FILE_REPLACED');
	if(result.success === true){
		params.counter.innerHTML = +params.counter.innerHTML - 1;
		if(params.counter.innerHTML == 0){ params.wrapper.parentElement.parentElement.parentElement.style.display = 'none'; }
		params.wrapper.parentElement.removeChild(params.wrapper);
	}
}

function spbc_scannerButtonFileCompare_callback(result, data, params){
	console.log('FILE_COMPARED');
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