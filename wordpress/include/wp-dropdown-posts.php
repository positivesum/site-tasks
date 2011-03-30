<?php
/*
Plugin Name: WP Dropdown Posts
Plugin URI: http://takien.com/661/wp-dropdown-posts-plugins.php
Description: So you want to list your post in dropdown? Here we go... Paste this code somewhere in your theme file code: <em>&lt;?php wp_dropdown_posts('sort_column=post_date&sort_order=DESC&number=5<strong>&cut_word=1&cut_limit=20&cut_replacement=...</strong>'); ?&gt;</em>. Bold args are NEW feature in version 0.2, in order to cut long titles.
Author: Takien
Version: 0.2
Author URI: http://takien.com
*/

////////////////////////////
class Walker_PostDropdown extends Walker {
	/**
	 * @see Walker::$tree_type
	 * @since 2.1.0
	 * @var string
	 */
	var $tree_type = 'post';

	/**
	 * @see Walker::$db_fields
	 * @since 2.1.0
	 * @todo Decouple this
	 * @var array
	 */
	var $db_fields = array ('parent' => 'post_parent', 'id' => 'ID');

	/**
	 * @see Walker::start_el()
	 * @since 2.1.0
	 *
	 * @param string $output Passed by reference. Used to append additional content.
	 * @param object $page Page data object.
	 * @param int $depth Depth of page in reference to parent pages. Used for padding.
	 * @param array $args Uses 'selected' argument for selected page to set selected HTML attribute for option element.
	 */
	function start_el(&$output, $page, $depth, $args) {
		$pad = str_repeat('&nbsp;', $depth * 3);
		
		$value = (($args['jump_to']) ? get_permalink($page->ID) : $page->ID);
		$output .= "\t<option class=\"level-$depth\" value=\"$value\"";
		if ( $page->ID == $args['selected'] )
			$output .= ' selected="selected"';
		$output .= '>';
		$title = esc_html($page->post_title);
		
		if($args['cut_word']) {
		$title = dropdown_cutword($title, $args['cut_replacement'], $args['cut_limit']);
		}
		$output .= "$pad$title";
		$output .= "</option>\n";
	}
}
/////////////
function &wp_get_posts($args = '') {
	global $wpdb;

	$defaults = array(
		'child_of' => 0,
		'sort_order' => 'DESC',
		'sort_column' => 'post_title',
		'hierarchical' => 1,
		'exclude' => '',
		'include' => '',
		'meta_key' => '',
		'meta_value' => '',
		'authors' => '',
		'parent' => -1,
		'exclude_tree' => '',
		'number' => '',
		'offset' => 0
	);

	$r = wp_parse_args( $args, $defaults );
	extract( $r, EXTR_SKIP );
	$number = (int) $number;
	$offset = (int) $offset;

	$cache = array();
	$key = md5( serialize( compact(array_keys($defaults)) ) );
	if ( $cache = wp_cache_get( 'wp_get_posts', 'posts' ) ) {
		if ( is_array($cache) && isset( $cache[ $key ] ) ) {
			$pages = apply_filters('wp_get_posts', $cache[ $key ], $r );
			return $pages;
		}
	}

	if ( !is_array($cache) )
		$cache = array();

	$inclusions = '';
	if ( !empty($include) ) {
		$child_of = 0; //ignore child_of, parent, exclude, meta_key, and meta_value params if using include
		$parent = -1;
		$exclude = '';
		$meta_key = '';
		$meta_value = '';
		$hierarchical = false;
		$incpages = preg_split('/[\s,]+/',$include);
		if ( count($incpages) ) {
			foreach ( $incpages as $incpage ) {
				if (empty($inclusions))
					$inclusions = $wpdb->prepare(' AND ( ID = %d ', $incpage);
				else
					$inclusions .= $wpdb->prepare(' OR ID = %d ', $incpage);
			}
		}
	}
	if (!empty($inclusions))
		$inclusions .= ')';

	$exclusions = '';
	if ( !empty($exclude) ) {
		$expages = preg_split('/[\s,]+/',$exclude);
		if ( count($expages) ) {
			foreach ( $expages as $expage ) {
				if (empty($exclusions))
					$exclusions = $wpdb->prepare(' AND ( ID <> %d ', $expage);
				else
					$exclusions .= $wpdb->prepare(' AND ID <> %d ', $expage);
			}
		}
	}
	if (!empty($exclusions))
		$exclusions .= ')';

	$author_query = '';
	if (!empty($authors)) {
		$post_authors = preg_split('/[\s,]+/',$authors);

		if ( count($post_authors) ) {
			foreach ( $post_authors as $post_author ) {
				//Do we have an author id or an author login?
				if ( 0 == intval($post_author) ) {
					$post_author = get_userdatabylogin($post_author);
					if ( empty($post_author) )
						continue;
					if ( empty($post_author->ID) )
						continue;
					$post_author = $post_author->ID;
				}

				if ( '' == $author_query )
					$author_query = $wpdb->prepare(' post_author = %d ', $post_author);
				else
					$author_query .= $wpdb->prepare(' OR post_author = %d ', $post_author);
			}
			if ( '' != $author_query )
				$author_query = " AND ($author_query)";
		}
	}

	$join = '';
	$where = "$exclusions $inclusions ";
	if ( ! empty( $meta_key ) || ! empty( $meta_value ) ) {
		$join = " LEFT JOIN $wpdb->postmeta ON ( $wpdb->posts.ID = $wpdb->postmeta.post_id )";

		// meta_key and meta_value might be slashed
		$meta_key = stripslashes($meta_key);
		$meta_value = stripslashes($meta_value);
		if ( ! empty( $meta_key ) )
			$where .= $wpdb->prepare(" AND $wpdb->postmeta.meta_key = %s", $meta_key);
		if ( ! empty( $meta_value ) )
			$where .= $wpdb->prepare(" AND $wpdb->postmeta.meta_value = %s", $meta_value);

	}

	if ( $parent >= 0 )
		$where .= $wpdb->prepare(' AND post_parent = %d ', $parent);

	$query = "SELECT * FROM $wpdb->posts $join WHERE (post_type = 'post' AND post_status = 'publish') $where ";
	$query .= $author_query;
	$query .= " ORDER BY " . $sort_column . " " . $sort_order ;

	if ( !empty($number) )
		$query .= ' LIMIT ' . $offset . ',' . $number;

	$pages = $wpdb->get_results($query);

	if ( empty($pages) ) {
		$pages = apply_filters('wp_get_posts', array(), $r);
		return $pages;
	}

	// Sanitize before caching so it'll only get done once
	$num_pages = count($pages);
	for ($i = 0; $i < $num_pages; $i++) {
		$pages[$i] = sanitize_post($pages[$i], 'raw');
	}

	// Update cache.
	update_page_cache($pages);

	if ( $child_of || $hierarchical )
		$pages = & get_page_children($child_of, $pages);

	if ( !empty($exclude_tree) ) {
		$exclude = (int) $exclude_tree;
		$children = get_page_children($exclude, $pages);
		$excludes = array();
		foreach ( $children as $child )
			$excludes[] = $child->ID;
		$excludes[] = $exclude;
		$num_pages = count($pages);
		for ( $i = 0; $i < $num_pages; $i++ ) {
			if ( in_array($pages[$i]->ID, $excludes) )
				unset($pages[$i]);
		}
	}

	$cache[ $key ] = $pages;
	wp_cache_set( 'wp_get_posts', $cache, 'posts' );

	$pages = apply_filters('wp_get_posts', $pages, $r);

	return $pages;
}
//////////////

function walk_post_dropdown_tree() {
	$args = func_get_args();
	if ( empty($args[2]['walker']) ) // the user's options are the third parameter
		$walker = new Walker_PostDropdown;
	else
		$walker = $args[2]['walker'];

	return call_user_func_array(array(&$walker, 'walk'), $args);
}

function wp_dropdown_posts($args = '') {
	$defaults = array(
		'depth' => 0,
		'child_of' => 0,
		'selected' => current_post_id(),
		'echo' => 1,
		'id' => '',		
		'name' => 'page_id',
		'show_option_none' => '',
		'show_option_no_change' => '',
		'option_none_value' => '',
		'jump_to' 			=> 1,
		'cut_word' 			=> 0,
		'cut_limit' 		=> 30,
		'cut_replacement' 	=> '...'
	);

	$r = wp_parse_args( $args, $defaults );
	extract( $r, EXTR_SKIP );

	$pages = wp_get_posts($r);
	$output = '';
	$name = esc_attr($name);

	if ($id == '') {
		$id = $name;
	}

	if ( ! empty($pages) ) {
		$output = "<select name=\"$name\" id=\"$id\" ".($jump_to ? ' onchange="javascript:dropdown_post_js(this)"' : '').">\n";
		if ( $show_option_no_change )
			$output .= "\t<option value=\"-1\">$show_option_no_change</option>";
		if ( $show_option_none )
			$output .= "\t<option value=\"" . esc_attr($option_none_value) . "\">$show_option_none</option>\n";
		$output .= walk_post_dropdown_tree($pages, $depth, $r);
		$output .= "</select>\n";
	}

	$output = apply_filters('wp_dropdown_posts', $output);

	if ( $echo )
		echo $output;

	return $output;
}

function current_post_id() {
global $wp_query;
return $wp_query->post->ID;
}
/*cut long title*/
function dropdown_cutword($string, $replacement = '...', $start = 30) { 
if (strlen($string) <= $start) return $string; 

$string = substr_replace($string, '', $start);
$space 	= strrpos($string,' ');
if($space === false) {
   return $string;
}
else {
	return substr_replace($string, $replacement, $space);
}
}

function dropdown_post_js() {
echo "
<script type=\"text/javascript\">
function dropdown_post_js(menuObj) {
	var i = menuObj.selectedIndex;
	
	if(i > 0)
	{
	if(menuObj.options[i].value != '#')
		{
			window.location = menuObj.options[i].value;
		}
	}
}
</script>";
}
add_action('wp_head','dropdown_post_js');
?>