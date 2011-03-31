var admin_patt = /wp-admin/gi;
var meta_patt = /wordpress/gi;
var link_patt = /wp-content/gi;
var isWP = false;
var url = window.location.href;

if (!admin_patt.test(url)) {
	var metas = $('meta');
	var content = '';
	for (var i in metas) {
		content = $(metas[i]).attr('content');
		if (meta_patt.test(content)) {
			isWP = true;
			break;
		}
	}

	if (!isWP) {
		var links = $('link[rel=stylesheet]');
		var src = '';
		for (var i in links) {
			src = $(links[i]).attr('src');
			if (link_patt.test(src)) {
				isWP = true;
				break;
			}
		}
	}

	if (!isWP) {
		var scripts = $('scripts[src]');
		var src = '';
		for (var i in scripts) {
			src = $(scripts[i]).attr('src');
			if (link_patt.test(src)) {
				isWP = true;
				break;
			}
		}
	}
	
	if (isWP) {
		var result = {};
		chrome.extension.sendRequest(result, function(response) {});
	}
}
