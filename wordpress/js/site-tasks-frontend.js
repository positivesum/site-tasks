var name = ".ui-dialog:first";
var menuYloc = 50;
var disableFloat = false;

jQuery(function(){
	jQuery('#site_tasks_dialog').dialog({position: ['left','bottom']});
});

jQuery(document).ready(function(){
	if (jQuery(name).length > 0) {
		jQuery(window).scroll(function () {
			if(disableFloat)
				return;
			offset = jQuery(window).height() - jQuery('#site_tasks_dialog').parent().height() + jQuery(document).scrollTop() +"px";
			jQuery(name).animate({top:offset},{duration:500,queue:false});
		});
	}	
});   



