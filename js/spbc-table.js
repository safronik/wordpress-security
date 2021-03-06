spbc_bulk_action = null;

// TABLE BULK ACTIONS
function spbc_tbl__bulk_actions__listen(){
	jQuery('.tbl-bulk_actions--apply').on('click', function(){
		var self = spbc_bulk_action || jQuery(this);
		spbc_bulk_action = self;
		var action = self.siblings('select').children()[self.siblings('select').first()[0].selectedIndex].value;
		if(self.parents('.tbl-root').find('.cb-select').is(':checked')){
			if(self.parents('.tbl-root').find('.cb-select:checked').first().parents('tr').find('.tbl-row_action--'+action)[0]){
				self.parents('.tbl-root').find('.cb-select:checked').first().parents('tr').find('.tbl-row_action--'+action).click();
				self.parents('.tbl-root').find('.cb-select:checked').first().prop('checked', false);
			}else{
				self.parents('.tbl-root').find('.cb-select:checked').first().prop('checked', false);
				self.click();
			}
		}else{
			spbc_bulk_action = null;
		}
	});
}	

	// Callback for TABLE BULK ACTIONS
function spbc_tbl__row_actions__callback(result, data, params, obj){
	if(result.color)    {obj.css({background: result.background, color: result.color});}
	if(result.html)     {obj.html(result.html); setTimeout(function(){obj.fadeOut(300);}, 1500);}
	if(result.temp_html){
		var tmp=obj.html(); 
		obj.html(result.temp_html);
		setTimeout(function(){
			obj.html(tmp).css({background: 'inherit'}).find('.column-primary .row-actions .tbl-row_action--'+data.add_action).remove();
		},4000);
	}
}

// TABLE ROW ACTIONS
function spbc_tbl__row_actions__listen(){
	jQuery('.tbl-row_action--ajax').on('click', function(){
		console.log('spbc_tbl__row_actions__listen click');
		var self = jQuery(this);
		var data = {
			action: 'spbc_tbl-action--row',
			add_action: self.attr('row-action'),
			id: self.parent().attr('uid'),
			cols: self.parent().attr('cols_amount'),
		};
		var params = {
			callback: spbc_tbl__row_actions__callback,
			spinner: self.parent().siblings('.tbl-preloader--tiny'),
		};
		if(!spbc_bulk_action && confirm('This can\'t be undone. Are you sure?'))
			spbc_sendAJAXRequest(data, params, self.parents('tr'));
		if(spbc_bulk_action)
			spbc_sendAJAXRequest(data, params, self.parents('tr'));
	});
}

	// Callback for TABLE ROW ACTIONS
function spbc_tbl__row_actions__callback(result, data, params, obj){
	if(result.color)    {obj.css({background: result.background, color: result.color});}
	if(result.html)     {obj.html(result.html); setTimeout(function(){obj.fadeOut(300);}, 1500);}
	if(result.temp_html){
		var tmp=obj.html(); 
		obj.html(result.temp_html);
		setTimeout(function(){
			obj.html(tmp).css({background: 'inherit'}).find('.column-primary .row-actions .tbl-row_action--'+data.add_action).remove();
		},1500);
	}
	if(spbc_bulk_action)
		spbc_bulk_action.click();
}

// TABLE ROW ACTIONS
function spbc_tbl__pagination__listen(){
	var data = {action: 'spbc_tbl-pagination',};
	var params = {callback: spbc_tbl__pagination__callback, notJson: true,};
	jQuery('.tbl-pagination--button').on('click', function(){
		jQuery(this).parents('.tbl-root').find('.tbl-pagination--button').attr('disabled', 'disabled');
	});
	jQuery('.tbl-pagination--go').on('click', function(){
		var self = jQuery(this);
		var obj = self.parents('.tbl-root');
		data.page = self.siblings('.tbl-pagination--curr_page').val();
		data.args = eval('args_'+obj.attr('id'));
		params.spinner = self.siblings('.tbl-preloader--small');
		spbc_sendAJAXRequest(data, params, obj);
	});
	jQuery('.tbl-pagination--prev').on('click', function(){
		var self = jQuery(this);
		var obj = self.parents('.tbl-root');
		data.page=self.parents('.tbl-pagination--wrapper').attr('prev_page');
		data.args=eval('args_'+obj.attr('id'));
		params.spinner = self.siblings('.tbl-preloader--small');
		spbc_sendAJAXRequest(data, params, obj);
	});
	jQuery('.tbl-pagination--next').on('click', function(){
		var self = jQuery(this);
		var obj = self.parents('.tbl-root');
		data.page=self.parents('.tbl-pagination--wrapper').attr('next_page');
		data.args=eval('args_'+obj.attr('id'));
		params.spinner = self.siblings('.tbl-preloader--small');
		spbc_sendAJAXRequest(data, params, obj);
	});
	jQuery('.tbl-pagination--end').on('click', function(){
		var self = jQuery(this);
		var obj = self.parents('.tbl-root');
		data.page=self.parents('.tbl-pagination--wrapper').attr('last_page');
		data.args=eval('args_'+obj.attr('id'));
		params.spinner = self.siblings('.tbl-preloader--small');
		spbc_sendAJAXRequest(data, params, obj);
	});
	jQuery('.tbl-pagination--start').on('click', function(){
		var self = jQuery(this);
		var obj = self.parents('.tbl-root');
		data.page=1;
		data.args=eval('args_'+obj.attr('id'));
		params.spinner = self.siblings('.tbl-preloader--small');
		spbc_sendAJAXRequest(data, params, obj);
	});
}

	// Callback for TABLE ROW ACTIONS
function spbc_tbl__pagination__callback(result, data, params, obj){
	
	jQuery(obj)
		.html(result)
		.find('.tbl-pagination--button').removeAttr('disabled');
	spbc_tbl__bulk_actions__listen();
	spbc_tbl__row_actions__listen();
	spbc_tbl__pagination__listen();
	spbc_tbl__sort__listen();
	spbcStartShowHide();
}

// TABLE SORT ACTIONS
function spbc_tbl__sort__listen(){
	
	var params = {callback: spbc_tbl__sort__callback, notJson: true,};
	jQuery('.tbl-column-sortable').on('click', function(){
		var self = jQuery(this);
		var obj = self.parents('.tbl-root');
		var data = {
			action: 'spbc_tbl-sort',
			order_by: self.attr('id'),
			order: self.attr('sort_direction'),
			args: eval('args_'+obj.attr('id')),
		};
		spbc_sendAJAXRequest(data, params, obj);
	});
}

	// Callback for TABLE SORT ACTIONS
function spbc_tbl__sort__callback(result, data, params, obj){
	jQuery(obj).html(result);
	spbc_tbl__bulk_actions__listen();
	spbc_tbl__row_actions__listen();
	spbc_tbl__pagination__listen();
	spbc_tbl__sort__listen();
}

jQuery(document).ready(function(){
	
	// Table. Row actions handler
	spbc_tbl__bulk_actions__listen();
	spbc_tbl__row_actions__listen();
	spbc_tbl__pagination__listen();
	spbc_tbl__sort__listen();
});