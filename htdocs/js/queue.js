$(document).ready( function() {
	RefreshPage();
});

function RefreshPage()
{
	Wait();
	
	$.ajax({url: "ajax/list-queues.php"}).done(function(data) {
		$('#list-queues').html(data);
		
		Ready();
		
		$('.fa-file-o').click({
			form_div:$('#queue-editor'),
			group:'queue',
			title:'Create queue',
			message:'Queue created'
		}, evqueueCreateFormHandler);
		
		$('.fa-edit').click({
			form_div:$('#queue-editor'),
			group:'queue',
			title:'Edit queue',
			message:'Queue saved'
		}, evqueueEditFormHandler);
		
		$('.fa-remove:not(.git)').click(function() {
			evqueueAPI({
				confirm: 'You are about to delete the selected queue',
				group: 'queue',
				action: 'delete',
				attributes: { 'id': $(this).parents('tr').data('id') }
			}).done(function() {
				Message('Queue has been deleted');
				RefreshPage();
			});
		});
	});
}
