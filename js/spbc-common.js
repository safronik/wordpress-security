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


function spbc_admin_set_cookie(name, data, expires){
	var date = new Date(new Date().getTime() + 1000 * expires );
	document.cookie = name + '=' + data + '; path=/; expires=' + date.toUTCString();
}

// Hightlights element
function spbcHighlightElement(id, times){
	times = times-1 || 0;
	jQuery('#'+id).addClass('spbc_highlighted');
	jQuery('#'+id).animate({outlineColor: "rgba(255, 0, 0, 1.0)" }, 400, 'linear', function(){
		jQuery('#'+id).animate({outlineColor: "rgba(255, 0, 0, 0.0)" }, 400, 'linear', function(){
			if(times>0){
				spbcHighlightElement(id, times);
			}else{
				jQuery('#'+id).removeClass('spbc_highlighted');
			}
		});
	});
}

function spbc_sendAJAXRequest(data, params, obj){
	
	// console.log(data);
	// console.log(params);
	// console.log(obj);
	
	// Default params
	var button      = params.button      || null;
	var spinner     = params.spinner     || null;
	var progressbar = params.progressbar || null;
	var callback    = params.callback    || null;
	var notJson     = params.notJson     || null;
	var timeout     = params.timeout     || 15000;
	var obj         = obj                || null;
	
	// Button and spinner
	if(button)  {button.setAttribute('disabled', 'disabled'); button.style.cursor = 'not-allowed'; }
	if(spinner) jQuery(spinner).css('display', 'inline');
	
	// Adding security code
	data.security = spbcSettings.ajax_nonce;
	
	jQuery.ajax({
		type: "POST",
		url: spbcSettings.ajaxurl,
		data: data,
		success: function(result){
			if(button){  button.removeAttribute('disabled'); button.style.cursor = 'pointer'; }
			if(spinner)  jQuery(spinner).css('display', 'none');
			if(!notJson) result = JSON.parse(result);
			if(result.error){
				console.log(result);
				console.log(data);
				console.log(params);
				setTimeout(function(){ if(progressbar) progressbar.fadeOut('slow'); }, 1000);
				alert('Error happens: ' + (result.error_string || 'Unkown'));
			}else{
				if(callback)
					callback(result, data, params, obj);
			}
		},
		error: function(jqXHR, textStatus, errorThrown){
			if(button){  button.removeAttribute('disabled'); button.style.cursor = 'pointer'; }
			if(spinner) jQuery(spinner).css('display', 'none');
			console.log('SPBC_AJAX_ERROR');
			console.log(jqXHR);
			console.log(textStatus);
			console.log(errorThrown);
			alert(errorThrown);
		},
		timeout: timeout,
	});
}