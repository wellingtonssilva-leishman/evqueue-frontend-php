$(document).ready( function() {
	var menu_selected=$('ul.topmenu li.selected').attr('id');
	$('ul.topmenu li').mouseenter(function(event) {
			$('ul.submenu').hide();
			$('ul.submenu#submenu-'+event.target.id).show();
		}
	);
	
	$('.tabs').tabs();
	
	$('.spinner').spinner();
	
	$('.evq-autorefresh').each(function() {
		var el = $(this);
		
		var toggle = el.find('span.evq-autorefresh-toggle');
		if(toggle)
		{
			if(el.data('interval')>0)
			{
				toggle.addClass('fa-spin');
				toggle.attr('title','Auto-refresh is enabled');
			}
			
			toggle.click(function() {
				if(el.data('interval')==0)
					autorefresh(el,toggle);
				else
				{
					$(this).toggleClass('fa-spin');
					
					if($(this).hasClass('fa-spin'))
					{
						autorefresh(el,toggle);
						$(this).attr('title','Auto-refresh is enabled');
						Message('Auto refresh enabled');
					}
					else
					{
						$(this).attr('title','Auto-refresh is disabled');
						clearTimeout(el.data('timeout'));
						Message('Auto refresh stopped');
					}
				}
			});
		}
		
		Wait();
		autorefresh(el,toggle).done(function() {
			Ready();
		});
	});
});

function evqueueAPI(options){
	options = $.extend({confirm: '', attributes: [], parameters: []}, options);
	
	if (options.confirm && !confirm(options.confirm))
		return;
	delete options.confirm;
	
	var promise = new jQuery.Deferred();
	
	$.ajax({
		url: 'ajax/evqueue_api.php',
		type: 'post',
		data: options,
	}).done(function(xml){
		error = $(xml).find('error');
		if ($(error).length > 0) {
			alert(error.html());
			promise.reject();
			return;
		}
		
		promise.resolve(xml);
	});
	
	return promise;
}

function Wait()
{
	$('html').css('height','calc(100% - 50px)');
	$('body').css('height','calc(100% - 50px)');
	$('body').css("cursor", "wait");
}

function Ready()
{
	$('html').css('height','auto');
	$('body').css('height','auto');
	$('body').css("cursor", "default");
}

function Message(msg)
{
	$('#message').html(msg);
	$('#message').show();
	$('#message').delay(2000).fadeOut();
}

var dialog_currpos = 0;
var dialog_positions = ['left top', 'right top', 'left bottom', 'right bottom'];
jQuery.fn.extend({
  dialogTiled: function(options) {
    return this.each(function() {
			var pos = dialog_positions[dialog_currpos];
			dialog_currpos = (dialog_currpos + 1) % 4;
			
			options.position = {
				my: pos,
				at: pos,
				of: window
			};
      $(this).dialog(options);
    });
  }
});


function autorefresh(el,toggle)
{
	var interval = el.data('interval')?el.data('interval'):0;
	var url = el.data('url');
	var pannel = el.find('div.evq-autorefresh-pannel').first();
	
	return $.ajax({url: url}).done(function(data) {
		pannel.html(data);
		
		if(interval>0 && toggle.hasClass('fa-spin'))
		{
			var timeout = setTimeout(function() { autorefresh(el,toggle); } ,interval*1000);
			el.data('timeout',timeout);
		}
	});
}
