var page_url = '';
var tasks = null;
var users = null;
var current_user = null;
var current_task_id = 0; 
var active = true;
var mytasks = true;

function dateToStr(time) {
	var d=new Date(time * 1000);
	return d.toDateString();
}

function getDateFromComment(str) {
	str = str.replace(/-/g, "/");
	return new Date(str);
}

function compareDates(date1, date2) {

	var day1 =  date1.getDate();
	var month1 =  date1.getMonth();
	var year1 =  date1.getFullYear();

	var day2 =  date2.getDate();
	var month2 =  date2.getMonth();
	var year2 =  date2.getFullYear();

	if ((year1 == year2) && (month1 == month2) && (day1 == day2)) {
		return 0;
	} else {
		if (date1 > date2) {
			return 1;
		} else {
			return -1;
		}
	}
}

function getTaskById(id) {
	var task = null;
	for (var i in tasks) {
		if (tasks[i].ID == id) {
			task = tasks[i];
			break;
		}
	}
	return task;
}

function getUserById(id) {
	var user = null;
	for (var i in users) {
		if (users[i].ID == id) {
			user = users[i];
			break;
		}
	}
	return user;
}

// get the URL of the blog
// e.g. http://example.com
function getBlogUrl() {
  var url = "";
  // check to see if the user has set their own.
  // if so, use their own.
  // otherwise, put "" (empty)
  url = localStorage.customDomain || "";

  // check to see if there is a "/" at the end of the URL
  if (url.charAt(url.length - 1) == "/")
    // found. remove it.
  	url = url.substring(0, url.length - 1);
  return 'http://' + url;
}

// get the URL of the blog administration
// e.g. http://example.com/wp-admin/
function getAdminUrl() {
	var url = "";
	// he didn't set their own. use the default.
	// notice: there is no a "/" at the end of the return value of getBlogURL()
	url = getBlogUrl() + "/wp-admin/";
	// check to see if there isn't a "/" at the end of the URL
	if (url.charAt(url.length - 1) != "/")
	// not found. add it.
	url = url + "/";
	return url;
}

// check to see if url is a administration page
function isAdminUrl(url) {
  var blog = getAdminUrl();
  // if we found getAdminUrl() in url at the very beginning of url, return true
  return ((url.indexOf(blog) == 0) || (url.indexOf('wp-login.php') != -1))
}

// check to see if url is a administration page
function isBlogUrl(url) {
  var blog = getBlogUrl();
  // if we found getBlogUrl() in url at the very beginning of url, return true
  return ((!isAdminUrl(url)) && (url.indexOf(blog) == 0))
}

function goToInbox() {
	var isadminopen = false;
  // check to see if the user have already opened one
  chrome.tabs.getAllInWindow(undefined, function(tabs) {
    for (var i = 0, tab; tab = tabs[i]; i++) {
      // check to see if tab.url is valid and it's a administration page
      if (tab.url && isBlogUrl(tab.url)) {
        // select it
		if (!tab.selected) {
			chrome.tabs.update(tab.id, {selected: true});
		} else {
			getTasks(tab.url);
		}
        return;
      } else {
		if (tab.url && isAdminUrl(tab.url)) {
			isadminopen = true;
		}
	  }
    }
    // not found. create a new tab.
	if (isadminopen) {
		chrome.tabs.create({url: getBlogUrl()});
	} else {
		chrome.tabs.create({url: getAdminUrl()});
	}
  });
}

function setPageStatus() {
	$("a:contains('Active')").removeClass('ui-btn-active');
	$("a:contains('Completed')").removeClass('ui-btn-active');
	$("a:contains('My Tasks')").removeClass('ui-btn-active');
	$("a:contains('All Tasks')").removeClass('ui-btn-active');
	if (active) {
		$("a:contains('Active')").addClass('ui-btn-active');
	} else {
		$("a:contains('Completed')").addClass('ui-btn-active');
	}
	if (mytasks) {
		$("a:contains('My Tasks')").addClass('ui-btn-active');
	} else {
		$("a:contains('All Tasks')").addClass('ui-btn-active');
	}
}

function getTasks(url) {
	//cue the page loader 			
	$.mobile.pageLoading();	
	jQuery.ajax({
	   type: "POST",
	   url: getBlogUrl() + "/wp-admin/admin-ajax.php",
	   data: 'action=chrome_site_tasks&operation=get-tasks' + 
			 '&url='	+ url,			 
       dataType: 'json',	   
	   success: function(data){
			if (data.result) {
				page_url = url;
				tasks = data.result.tasks;
				users = data.result.users;
				current_user = data.result.current_user;
				setContent('active');
				$.mobile.pageLoading( true );	
			}
	   },
		error: function(XMLHttpRequest, textStatus, errorThrown){
			alert(textStatus);		
		}	   
	 });			
}

function updateTask(params) {
	//cue the page loader 			
	$.mobile.pageLoading();	
	jQuery.ajax({
	   type: "POST",
	   url: getBlogUrl() + "/wp-admin/admin-ajax.php",
	   data: 'action=chrome_site_tasks&operation=update-task' + 
			 '&url='	+ page_url +
			 '&id='	+ current_task_id +
			 '&' + params,			 
       dataType: 'json',	   
	   success: function(data){
			if (data.result) {
				tasks = data.result.tasks;
				users = data.result.users;
				current_user = data.result.current_user;
				setContent('active');
				$.mobile.pageLoading( true );
			}
	   },
		error: function(XMLHttpRequest, textStatus, errorThrown){
			alert(textStatus);		
		}	   
	 });			
}

function addTask(params) {
	//cue the page loader 			
	$.mobile.pageLoading();	
	jQuery.ajax({
	   type: "POST",
	   url: getBlogUrl() + "/wp-admin/admin-ajax.php",
	   data: 'action=chrome_site_tasks&operation=add-task' + 
			 '&url='	+ page_url +
			 '&' + params,			 
       dataType: 'json',	   
	   success: function(data){
			if (data.result) {
				tasks = data.result.tasks;
				users = data.result.users;
				current_user = data.result.current_user;
				setContent('active');
				$.mobile.pageLoading( true );
			}
	   },
		error: function(XMLHttpRequest, textStatus, errorThrown){
			alert(textStatus);		
		}	   
	 });			
}

function addComment(params) {
	//cue the page loader 			
	$.mobile.pageLoading();	
	jQuery.ajax({
	   type: "POST",
	   url: getBlogUrl() + "/wp-admin/admin-ajax.php",
	   data: 'action=chrome_site_tasks&operation=add-comment' + 
			 '&url='	+ page_url +
			 '&id='	+ current_task_id +			 
			 '&' + params,			 
       dataType: 'json',	   
	   success: function(data){
			if (data.result) {
				tasks = data.result.tasks;
				users = data.result.users;
				current_user = data.result.current_user;
				setComments(current_task_id);
				// setContent('active');
				$.mobile.pageLoading( true );
			}
	   },
		error: function(XMLHttpRequest, textStatus, errorThrown){
			alert(textStatus);		
		}	   
	 });			
}

function changeStatus(event, id) {
	//cue the page loader 			
	$.mobile.pageLoading();	
	jQuery.ajax({
	   type: "POST",
	   url: getBlogUrl() + "/wp-admin/admin-ajax.php",
	   data: 'action=chrome_site_tasks&operation=change-status' + 
			 '&url='	+ page_url +
			 '&id='	+ id,			 
       dataType: 'json',	   
	   success: function(data){
			if (data.result) {
				tasks = data.result.tasks;
				users = data.result.users;
				current_user = data.result.current_user;
				setContent('active');
				$.mobile.pageLoading( true );
			}
	   },
		error: function(XMLHttpRequest, textStatus, errorThrown){
			alert(textStatus);		
		}	   
	 });
	event.stopPropagation();

}

function setContent(page) {
	setPageStatus();
	var status = '';
	switch (page) {
		case 'active':
			$('#active ul:[data-role="listview"]').empty();
			$('<li data-role="list-divider" >Urgent</li><li data-role="list-divider">Normal</li><li data-role="list-divider">Low</li>').appendTo($('#active ul:[data-role="listview"]'));
			for (var i in tasks) {
				var task = tasks[i];
				var listDivider = null;
				var classLink = ''; 
				var priority = '';
				if (mytasks && (task.custom.tasks_owner[0] != current_user.ID)) {
					continue;
				}
				if (task.custom.tasks_status[0] != 6) {
					if (task.custom.tasks_status[0] < 2) {
						status = 'Start';
					} else {
						status = 'Finish';
					}
					switch (task.custom.tasks_priority[0]) { // 1 => 'Low', 2 => 'Normal', 3 => 'Urgent'
						case '1': listDivider = $("#active li:contains('Low')");
							classLink = 'low';
							break;
						case '2': listDivider = $("#active li:contains('Normal')");
							classLink = 'normal';
							break;
						case '3': listDivider = $("#active li:contains('Urgent')");
							classLink = 'urgent';
							break;
					}
					var obj = $('<li><div class="status-button ' + status.toLowerCase() + '"><input onclick="changeStatus(event,' + task.ID + ');" type="button" value="' + status + '"></div><a href="#details"  onclick="current_task_id=' + task.ID + ';" class="' + classLink + '">' + task.post_title + '</a></li>');
					obj.insertAfter(listDivider);
					obj.page();
				} 
			}
			$('#active ul:[data-role="listview"]').listview('refresh');
			break;
		case 'completed': 
			$('#completed ul:[data-role="listview"]').empty();
			for (var i in tasks) {
				var task = tasks[i];
				var priority = '';
				if (mytasks && (task.custom.tasks_owner[0] != current_user.ID)) {
					continue;
				}
				if (task.custom.tasks_status[0] == 6) {
					switch (task.custom.tasks_priority[0]) { // 1 => 'Low', 2 => 'Normal', 3 => 'Urgent'
						case '1': priority = 'Low';
							break;
						case '2': priority = 'Normal';
							break;
						case '3': priority = 'Urgent';
							break;
					}			
					$('<li><h3><a onclick="current_task_id=' + task.ID + ';" href="#details">' + task.post_title + '</a></h3><p>Completed by <strong>' + task.user_info.first_name + ' ' + task.user_info.last_name + '</strong> on <strong>' + dateToStr(task.custom.tasks_date_due[0]) + '</strong></p><span class="ui-li-count">' + priority + '</span></li>').appendTo('#completed ul:[data-role="listview"]');
				}
			}
			$('#completed ul:[data-role="listview"]').listview('refresh');
			break;
		case 'show-my':
			$('#show-my ul:[data-role="listview"]').empty();
			if (active) {
				$('<li data-role="list-divider" >Urgent</li><li data-role="list-divider">Normal</li><li data-role="list-divider">Low</li>').appendTo($('#show-my ul:[data-role="listview"]'));
				for (var i in tasks) {
					var task = tasks[i];
					var listDivider = null;
					var classLink = ''; 
					if (task.custom.tasks_owner[0] != current_user.ID) {
						continue;
					}
					if (task.custom.tasks_status[0] != 6) {
						if (task.custom.tasks_status[0] < 2) {
							status = 'Start';
						} else {
							status = 'Finish';
						}
						switch (task.custom.tasks_priority[0]) { // 1 => 'Low', 2 => 'Normal', 3 => 'Urgent'
							case '1': listDivider = $("#show-my li:contains('Low')");
								classLink = 'low';
								break;
							case '2': listDivider = $("#show-my li:contains('Normal')");
								classLink = 'normal';
								break;
							case '3': listDivider = $("#show-my li:contains('Urgent')");
								classLink = 'urgent';
								break;
						}
						var obj = $('<li><div class="status-button ' + status.toLowerCase() + '"><input onclick="changeStatus(event,' + task.ID + ');" type="button" value="' + status + '"></div><a href="#details"  onclick="current_task_id=' + task.ID + ';" class="' + classLink + '">' + task.post_title + '</a></li>');
						obj.insertAfter(listDivider);
						obj.page();
					} 
				}
			} else {
				for (var i in tasks) {
					var task = tasks[i];
					var priority = '';
					if (task.custom.tasks_owner[0] != current_user.ID) {
						continue;
					}
					if (task.custom.tasks_status[0] == 6) {
						switch (task.custom.tasks_priority[0]) { // 1 => 'Low', 2 => 'Normal', 3 => 'Urgent'
							case '1': priority = 'Low';
								break;
							case '2': priority = 'Normal';
								break;
							case '3': priority = 'Urgent';
								break;
						}			
						$('<li><h3><a onclick="current_task_id=' + task.ID + ';" href="#details">' + task.post_title + '</a></h3><p>Completed by <strong>' + task.user_info.first_name + ' ' + task.user_info.last_name + '</strong> on <strong>' + dateToStr(task.custom.tasks_date_due[0]) + '</strong></p><span class="ui-li-count">' + priority + '</span></li>').appendTo('#show-my ul:[data-role="listview"]');
						
					}
				}
			}
			$('#show-my ul:[data-role="listview"]').listview('refresh');
			break;
		case 'show-all': 
			$('#show-all ul:[data-role="listview"]').empty();
			if (active) {
				$('<li data-role="list-divider" >Urgent</li><li data-role="list-divider">Normal</li><li data-role="list-divider">Low</li>').appendTo('#show-all ul:[data-role="listview"]');
				for (var i in tasks) {
					var task = tasks[i];
					var listDivider = null;
					var classLink = ''; 
					if (task.custom.tasks_status[0] != 6) {
						if (task.custom.tasks_status[0] < 2) {
							status = 'Start';
						} else {
							status = 'Finish';
						}
						switch (task.custom.tasks_priority[0]) { // 1 => 'Low', 2 => 'Normal', 3 => 'Urgent'
							case '1': listDivider = $("#show-all li:contains('Low')");
								classLink = 'low';
								break;
							case '2': listDivider = $("#show-all li:contains('Normal')");
								classLink = 'normal';
								break;
							case '3': listDivider = $("#show-all li:contains('Urgent')");
								classLink = 'urgent';
								break;
						}
						var obj = $('<li><div class="status-button ' + status.toLowerCase() + '"><input onclick="changeStatus(event,' + task.ID + ');" type="button" value="' + status + '"></div><a href="#details"  onclick="current_task_id=' + task.ID + ';" class="' + classLink + '">' + task.post_title + '</a></li>');
						obj.insertAfter(listDivider);
						obj.page();
					} 
				}
			} else {
				for (var i in tasks) {
					var task = tasks[i];
					var priority = '';
					if (task.custom.tasks_status[0] == 6) {
						switch (task.custom.tasks_priority[0]) { // 1 => 'Low', 2 => 'Normal', 3 => 'Urgent'
							case '1': priority = 'Low';
								break;
							case '2': priority = 'Normal';
								break;
							case '3': priority = 'Urgent';
								break;
						}			
						$('<li><h3><a onclick="current_task_id=' + task.ID + ';" href="#details">' + task.post_title + '</a></h3><p>Completed by <strong>' + task.user_info.first_name + ' ' + task.user_info.last_name + '</strong> on <strong>' + dateToStr(task.custom.tasks_date_due[0]) + '</strong></p><span class="ui-li-count">' + priority + '</span></li>').appendTo('#show-all ul:[data-role="listview"]');
					}
				}
			}
			$('#show-all ul:[data-role="listview"]').listview('refresh');
			break;
	}
}

function setDetails(id) {
	var task = getTaskById(id);
	if (task != null) {
		if (task.custom.tasks_status[0] != 6) {
			if (task.custom.tasks_status[0] < 2) {
				status = 'Start';
			} else {
				status = 'Finish';
			}
			// $('#details a.ui-btn-right').show();
		} else {
			// $('#details a.ui-btn-right').hide();
			status = 'Restart';
		}
		$('#details a.ui-btn-right span.ui-btn-text').html(status);
				
		var comment_count = 'Comments ( ' + task.comment_count + ' )';
		$('#details li.ui-block-b span.ui-btn-text').text(comment_count);
		
		$('#details #title').val(task.post_title);
		$('#details #textarea').val(task.post_content);
		$('#details #select-choice-1').empty();
		for (var i in users) {
			if (task.custom.tasks_owner[0] == users[i].ID) {
				var obj = $('<option value="' + users[i].ID + '" selected >' + users[i].first_name + ' ' + users[i].last_name + '</option>');
			} else {
				var obj = $('<option value="' + users[i].ID + '">' + users[i].first_name + ' ' + users[i].last_name + '</option>');
			}
			obj.appendTo('#details #select-choice-1');
		}
		$('#details #select-choice-1').selectmenu('refresh', true);
		$('#details input[type=radio][value="' + task.custom.tasks_priority[0] + '"]').attr('checked',true);
		$('#details input[type=radio]').checkboxradio("refresh");
		var date_due = new Date(task.custom.tasks_date_due[0] * 1000);
		var month = date_due.getMonth() + 1;
		$('#details #duedate').val( date_due.getFullYear() + '-' + month + '-' +  date_due.getDate());
	}
}

function setAdd() {
	$('#add #select-choice-1').empty();
	for (var i in users) {
		if ( current_user.ID == users[i].ID) {
			var obj = $('<option value="' + users[i].ID + '" selected >' + users[i].first_name + ' ' + users[i].last_name + '</option>');
		} else {
			var obj = $('<option value="' + users[i].ID + '">' + users[i].first_name + ' ' + users[i].last_name + '</option>');
		}
		obj.appendTo('#add #select-choice-1');
	}
	$('#add #select-choice-1').selectmenu('refresh', true);
	var date_due = new Date();
	var month = date_due.getMonth() + 1;
	$('#add #duedate').val( date_due.getFullYear() + '-' + month + '-' +  date_due.getDate());
}

function setComments(id) {
	var task = getTaskById(id);
	if (task != null) {
		if (task.custom.tasks_status[0] != 6) {
			if (task.custom.tasks_status[0] < 2) {
				status = 'Start';
			} else {
				status = 'Finish';
			}
		} else {
			status = 'Restart';
		}
		$('#comments a.ui-btn-right span.ui-btn-text').html(status);
				
		var comment_count = 'Comments ( ' + task.comment_count + ' )';
		$('#comments li.ui-block-b span.ui-btn-text').text(comment_count);
		$('#comments ul:[data-role="listview"]').empty();
		var user = null;
		var current_date = new Date();
		var task_date = null;
		var count = 0;
		var name = '';
		var hours = '';
		var minutes = '';
		var time = '';
		for (var i in task.comments) {
			user_id = task.comments[i].user_id;
			user = getUserById(user_id);
			task_date = getDateFromComment(task.comments[i].comment_date);
			if (compareDates(current_date, task_date) != 0) {
				current_date = task_date;
				$('<li data-role="list-divider">' + current_date.toDateString() + '<span class="ui-li-count"></span></li>').appendTo('#comments ul:[data-role="listview"]'); 
			}
			if (user != null) {
				name = user.first_name + ' ' + user.last_name;
			}

			hours = task_date.getHours();
			minutes = task_date.getMinutes();
			if (hours > 11) {
				if (hours != 12) {
					hours = hours - 12;
				}
				time = '<strong>' + hours + ':' + minutes + '</strong> PM';
			} else {
				time = '<strong>' + hours + ':' + minutes + '</strong> AM';
			}
			$('<li><h3>' + name + '</h3><p>' + task.comments[i].comment_content + '</p><p class="ui-li-aside">' + time + '</p></li>').appendTo('#comments ul:[data-role="listview"]'); 
		}
		count = $('#comments ul:[data-role="listview"] li span.ui-li-count').length;
		$('#comments ul:[data-role="listview"] li span.ui-li-count').each(function(index) {
			$(this).text(count - index);
		});

		$('#comments ul:[data-role="listview"]').listview('refresh');		
	}
}
