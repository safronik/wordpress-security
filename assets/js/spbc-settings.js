jQuery(document).ready(function(){
		
	jQuery('#showHideLink').click(function(){
		jQuery('.form-table').fadeToggle(300);
	});
	
	if(keyIsOk){
		jQuery('.form-table').toggleClass('spbcDisplayNone');
		jQuery('.spbcTopWarning').html('All is OK');
		jQuery('#goToCleanTalkLink').val('All is OK');
	}else{
		jQuery('.spbcTopWarning').val('Key is bad');
	}
	
	jQuery('#spbcTopWarning').on('click', '.notice-dismiss', function(){
		jQuery(this).parent('div').fadeOut(300);
	});
});