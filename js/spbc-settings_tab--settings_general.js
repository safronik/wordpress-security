jQuery(document).ready(function(){
	
	// Show/Hide access key
	jQuery('#showHideLink').on('click', function(){
		jQuery('#spbc_key').val(jQuery('#spbc_key').attr('key'));
		jQuery(this).fadeOut(300);
	});
	
});