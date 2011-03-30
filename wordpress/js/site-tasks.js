function setPriority(el) {
	el.className = el.options[el.selectedIndex].text.toLowerCase();	
}

function addTasksItem(tasks_post_id, type) {
	jQuery('#tasks-title-error').hide();	
	var tasks_newname = jQuery('#site-tasks-add input[name=tasks_newname]').val(); 
	if (tasks_newname == '') {
		jQuery('#tasks-title-error').show();
		return;
	}
	var tasks_day = jQuery('#site-tasks-add input[name=tasks_day]').val(); 
	var tasks_month = jQuery('#site-tasks-add select[name=tasks_month]').val(); 	
	var tasks_year = jQuery('#site-tasks-add input[name=tasks_year]').val(); 
	var tasks_owner = jQuery('#site-tasks-add select[name=tasks_owner]').val(); 	
	var tasks_priority = jQuery('#site-tasks-add select[name=tasks_priority]').val(); 	
	var tasks_status = jQuery('#site-tasks-add select[name=tasks_status]').val(); 	
	var tasks_post_type = type;
	jQuery.ajax({
	   type: "POST",
	   url: "/wp-admin/admin-ajax.php",
	   data: 'action=wp_site_tasks&operation=add' + 
			 '&tasks_post_id='	+ tasks_post_id +			 			 
			 '&tasks_page_id='	+ tasks_post_id +			 			 			 
			 '&tasks_post_type='	+ tasks_post_type +			 			 			 
			 '&post_title='	+ tasks_newname +			 
			 '&tasks_day='	+ tasks_day +
			 '&tasks_month='	+ tasks_month +
			 '&tasks_year='	+ tasks_year +			 
			 '&tasks_owner='	+ tasks_owner +
			 '&tasks_priority='	+ tasks_priority +
			 '&tasks_status='	+ tasks_status,			 
       dataType: 'json',	   
	   success: function(data){
			if (data.result) {
				jQuery('#site-tasks-div .inside:first').html(data.result); 				
				jQuery('#site-tasks-add-toggle').click(function(event){
					jQuery('#site-tasks-add').slideToggle();					
				});		

				jQuery('select[name="tasks_priority"]').change(function(event){
					setPriority(this);	
				});							
			}
	   },
		error: function(XMLHttpRequest, textStatus, errorThrown){
			alert(textStatus);		
		}	   
	 });			
}

function checkTasksItem(id, tasks_post_id, type) {

	var tasks_day = jQuery('#site-tasks-edit'+id+' input[name=tasks_day]').val(); 
	var tasks_month = jQuery('#site-tasks-edit'+id+' select[name=tasks_month]').val(); 	
	var tasks_year = jQuery('#site-tasks-edit'+id+' input[name=tasks_year]').val(); 
	var tasks_owner = jQuery('#site-tasks-edit'+id+' select[name=tasks_owner]').val(); 	
	var tasks_priority = jQuery('#site-tasks-edit'+id+' select[name=tasks_priority]').val(); 	
	var tasks_post_type = type;	
	var tasks_status = 1; 	
	if (jQuery('#site-tasks-checkbox'+id).is(':checked')) {
		tasks_status = 6;		
	}
	
	jQuery.ajax({
	   type: "POST",
	   url: "/wp-admin/admin-ajax.php",
	   data: 'action=wp_site_tasks&operation=update' + 
			 '&post_ID='	+ id + 		
			 '&tasks_post_id='	+ tasks_post_id +			 
			 '&tasks_page_id='	+ tasks_post_id +			 			 			 
			 '&tasks_post_type='	+ tasks_post_type +			 			 			 			 
			 '&tasks_day='	+ tasks_day +
			 '&tasks_month='	+ tasks_month +
			 '&tasks_year='	+ tasks_year +			 
			 '&tasks_owner='	+ tasks_owner +
			 '&tasks_priority='	+ tasks_priority +
			 '&tasks_status='	+ tasks_status,			 
       dataType: 'json',	   
	   success: function(data){
			if (data.result) {
				jQuery('#site-tasks-div .inside:first').html(data.result); 				
				jQuery('#site-tasks-add-toggle').click(function(event){
					jQuery('#site-tasks-add').slideToggle();					
				});		

				jQuery('select[name="tasks_priority"]').change(function(event){
					setPriority(this);	
				});							
			}
	   },
		error: function(XMLHttpRequest, textStatus, errorThrown){
			alert(textStatus);		
		}	   
	 });			
}

function updateTasksItem(id, tasks_post_id, type) {
	var tasks_day = jQuery('#site-tasks-edit'+id+' input[name=tasks_day]').val(); 
	var tasks_month = jQuery('#site-tasks-edit'+id+' select[name=tasks_month]').val(); 	
	var tasks_year = jQuery('#site-tasks-edit'+id+' input[name=tasks_year]').val(); 
	var tasks_owner = jQuery('#site-tasks-edit'+id+' select[name=tasks_owner]').val(); 	
	var tasks_priority = jQuery('#site-tasks-edit'+id+' select[name=tasks_priority]').val(); 	
	var tasks_status = jQuery('#site-tasks-edit'+id+' select[name=tasks_status]').val(); 	
	var tasks_post_type = type;	
	
	jQuery.ajax({
	   type: "POST",
	   url: "/wp-admin/admin-ajax.php",
	   data: 'action=wp_site_tasks&operation=update' + 
			 '&post_ID='	+ id + 		
			 '&tasks_post_id='	+ tasks_post_id +			 
			 '&tasks_page_id='	+ tasks_post_id +			 			 			 			 
			 '&tasks_post_type='	+ tasks_post_type +			 			 			 			 			 
			 '&tasks_day='	+ tasks_day +
			 '&tasks_month='	+ tasks_month +
			 '&tasks_year='	+ tasks_year +			 
			 '&tasks_owner='	+ tasks_owner +
			 '&tasks_priority='	+ tasks_priority +
			 '&tasks_status='	+ tasks_status,			 
       dataType: 'json',	   
	   success: function(data){
			if (data.result) {
				jQuery('#site-tasks-div .inside:first').html(data.result); 				
				jQuery('#site-tasks-add-toggle').click(function(event){
					jQuery('#site-tasks-add').slideToggle();					
				});		

				jQuery('select[name="tasks_priority"]').change(function(event){
					setPriority(this);	
				});							
			}
	   },
		error: function(XMLHttpRequest, textStatus, errorThrown){
			alert(textStatus);		
		}	   
	 });			
}

function showTasksItem(id) {
	jQuery('#site-tasks-edit'+id).show();
	jQuery('#site-tasks-edit-toggle'+id).hide();	
}

function hideTasksItem(id) {
	jQuery('#site-tasks-edit-toggle'+id).show();	
	jQuery('#site-tasks-edit'+id).hide();
}

function hideAddTasksItem() {
	jQuery('#site-tasks-add').hide();	
}

jQuery(function(){
	jQuery('#site-tasks-add-toggle').click(function(event){
		jQuery('#site-tasks-add').slideToggle();					
	});		

	jQuery('select[name="tasks_priority"]').change(function(event){
		setPriority(this);	
	});			

});
