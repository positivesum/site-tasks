<?php
/*
Plugin Name: Site Tasks 
Plugin URI: http://wordpress.org/extend/plugins/site-tasks/ 
Description:  Ability to create/edit/delete tasks. Dashboard widget that shows available site tasks
Version: 0.8 
Author: Taras Mankovski 
Author URI: http://taras.cc 
*/

if ( !class_exists( 'The_Site_Tasks' ) ) {
	class The_Site_Tasks {
		const POSTTYPE				= 'site_task';	
		const TAXONOMY				= 'site_task_cat';		
		const TAXREWRITE			= 'site_task/category';				
		const TAG   				= 'site_task_tag';				
		const TAGREWRITE			= 'site_task/tag';				
		public $theme;
		public $pluginUrl;	
		private $metaFields = array('tasks_post_id', 'tasks_post_type', 'tasks_owner', 'tasks_date_due', 'tasks_status', 'tasks_priority');
		private $tagLabels = array(
			'name' =>  'Tasks Tags',
			'singular_name' =>  'Tasks Tag',
			'search_items' =>  'Search Tasks Tags', 
			'all_items' => 'All Tasks Tags',
			'parent_item' => null,
			'parent_item_colon' =>  null,
			'edit_item' =>  'Edit Tasks Tag',
			'update_item' =>  'Update Tasks Tag',
			'add_new_item' =>  'Add New Tasks Tag',
			'new_item_name' =>  'New Tasks Tag Name', 
		);		
		private $taxonomyLabels = array(
			'name' =>  'Tasks Categories',
			'singular_name' =>  'Tasks Category',
			'search_items' =>  'Search Tasks Categories', 
			'all_items' => 'All Tasks Categories',
			'parent_item' => 'Parent Tasks Category',
			'parent_item_colon' =>  'Parent Tasks Category:',
			'edit_item' =>  'Edit Site Tasks Category',
			'update_item' =>  'Update Tasks Category',
			'add_new_item' =>  'Add New Tasks Category',
			'new_item_name' =>  'New Tasks Category Name', 
		);		
		
		private $priorities = array(1 => 'Low', 2 => 'Normal', 3 => 'Urgent');		
		private $statuses = array(1 => 'Draft', 2=> 'New', 3 => 'Assigned', 4 => 'Requires', 5 => 'Verification', 6 => 'Complete');

		
		/**
		 * Initializes plugin variables and sets up wordpress hooks/actions.
		 *
		 * @return void
		 */
		function __construct( ) {
			$this->pluginDir		= basename(dirname(__FILE__));
			$this->pluginPath		= WP_PLUGIN_DIR . '/' . $this->pluginDir;
			$this->pluginUrl 		= WP_PLUGIN_URL.'/'.$this->pluginDir;		
			require_once($this->pluginPath . "/include/wp-dropdown-posts.php");			
			// Initiate the plugin
			add_action('init',  array(&$this, 'site_tasks_init'));
		}
		
		function site_tasks_init() {
			// Register custom post types
			register_post_type(self::POSTTYPE, array(
				'labels' => array(
										'name' => __('Site Tasks'),
										'singular_name' => __( 'Site Task' ),
										'add_new' => __( 'Add New' ),
										'add_new_item' => __( 'Add New Site Task' ),
										'edit' => __( 'Edit' ),
										'edit_item' => __( 'Edit Site Task' ),
										'new_item' => __( 'New Site Task' ),
										'view' => null,
										'view_item' => null,
										'search_items' => __( 'Search Site Tasks' ),
										'not_found' => __( 'No Site Tasks found' ),
										'not_found_in_trash' => __( 'No Site Tasks found in Trash' ),
							),
				'singular_label' => __('Site Task'),
				'exclude_from_search' => true,
				'public' => true,
				'show_ui' => true, // UI in admin panel
				'_builtin' => false, // It's a custom post type, not built in
				'_edit_link' => 'post.php?post=%d',
				'capability_type' => 'post',
				'hierarchical' => false,
				'rewrite' => array("slug" => "site_task"), // Permalinks
				'query_var' => false, // This goes to the WP_Query schema
				'supports' => array('title', 'editor', 'author', 'comments')
			));

			register_taxonomy( self::TAG, self::POSTTYPE, array(
				'hierarchical' => false,				
				'update_count_callback' => '',
				'rewrite' => array('slug'=>self::TAGREWRITE),
				'public' => true,
				'show_ui' => true,
				'labels' => $this->tagLabels
			));
			
			register_taxonomy( self::TAXONOMY, self::POSTTYPE, array(
				'hierarchical' => true,
				'update_count_callback' => '',
				'rewrite' => array('slug'=>self::TAXREWRITE),
				'public' => true,
				'show_ui' => true,
				'labels' => $this->taxonomyLabels
			));
			
			if(is_admin()){
				add_filter("post_updated_messages", array(&$this, "site_tasks_post_updated_messages"));							
				add_filter("post_row_actions", array(&$this, "site_tasks_row_actions"));							
				add_filter("manage_edit-site_task_columns", array(&$this, "site_tasks_edit_columns"));				
				add_action("admin_enqueue_scripts", array( &$this, "site_tasks_add_styles" ) );											
				// Admin interface init
				add_action("admin_init", array(&$this, "site_tasks_admin_init"));
				add_action("manage_posts_custom_column", array( &$this, "site_tasks_custom_columns"), 10, 2);	
				add_action("wp_dashboard_setup", array( &$this, "site_tasks_dashboard_setup"));
				add_action("posts_results", array(&$this, "site_tasks_posts_results"));				
				add_action('admin_menu', array(&$this, 'site_tasks_admin_menu'));				
			}

			// Insert post hook
			add_action("wp_insert_post", array(&$this, "site_tasks_wp_insert_post"), 10, 2);
			add_action("get_footer", array(&$this, "site_tasks_get_footer"));	
			wp_enqueue_style( self::POSTTYPE.'-admin', $this->pluginUrl . '/css/site-tasks.css' );		
			add_action("template_redirect", array(&$this, "site_tasks_template_redirect"));			
			add_action('wp_ajax_chrome_site_tasks', array(&$this, "site_tasks_chrome_ajax"));			
			add_action('wp_ajax_wp_site_tasks', array(&$this, "site_tasks_wp_ajax"));
			add_action('wp_ajax_nopriv_import_site_tasks', array(&$this, "import_site_tasks"));			
		}

		//Target of WPRackTest plugin filter. Provides the plugin
		function site_tasks_get_plugin( $plugins ) {
			return array_merge($plugins, array('site-tasks/site-tasks.php' => 'site-tasks/site-tasks.php'));
		}

		function site_tasks_chrome_ajax() {
			global $wpdb;
			$response = array();
			// Allowed actions: add, update, delete
			$action = isset( $_REQUEST['operation'] ) ? $_REQUEST['operation'] : 'add';
			$url = $_REQUEST['url'];
			$post_id = intval(url_to_postid( $url ));
			if ($post_id < 1) {
				$post_id = get_option('page_on_front');
			}
			if ($post_id > 0) {
				switch ( $action ) {
					case 'add-task':	
						$id = wp_insert_post( array( 'post_type' => 'site_task', 'post_title' => $_REQUEST['name'], 'post_content' => $_REQUEST['description'], 'post_status' => 'publish' ) );
						$post = get_post($post_id);	
						update_post_meta($id, 'tasks_post_id', $post_id);
						update_post_meta($id, 'tasks_post_type', $post->post_type);
						update_post_meta($id, 'tasks_owner', $_REQUEST['select-choice-1']);
						update_post_meta($id, 'tasks_priority', $_REQUEST['radio-choice-1']);
						$parts = explode("/", $_REQUEST['duedate']);
						$date = mktime(0, 0, 0, $parts[0], $parts[1], $parts[2]);						
						update_post_meta($id, 'tasks_date_due', $date);
						update_post_meta($id, 'tasks_status',1);
						
						$comment_post_ID = $id;
						$user = wp_get_current_user();
						$user_ID = $user->ID;
						if ( empty( $user->display_name ) )
							$user->display_name=$user->user_login;
						$comment_author       = $wpdb->escape($user->display_name);
						$comment_author_email = $wpdb->escape($user->user_email);
						$comment_author_url   = $wpdb->escape($user->user_url);						
						$comment_type = '';
						$author = $user->first_name . ' ' . $user->last_name;
						
						$tasks_owner = get_userdata(intval($_REQUEST['select-choice-1']));
						$tasks_owner_name = $tasks_owner->first_name . ' ' . $tasks_owner->last_name;						
						$comment_content = $author." created task and assigned it to ".$tasks_owner_name;
						$commentdata = compact('comment_post_ID', 'comment_author', 'comment_author_email', 'comment_author_url', 'comment_content', 'comment_type', 'user_ID');
						$comment_id = wp_new_comment( $commentdata );						
					break;
					case 'add-comment':	
						$id = intval($_REQUEST['id']);
						$comment_post_ID = $id;
						$user = wp_get_current_user();
						$user_ID = $user->ID;
						if ( empty( $user->display_name ) )
							$user->display_name=$user->user_login;
						$comment_author       = $wpdb->escape($user->display_name);
						$comment_author_email = $wpdb->escape($user->user_email);
						$comment_author_url   = $wpdb->escape($user->user_url);						
						$comment_type = '';
						$comment_content = $_REQUEST['textarea'];
						$commentdata = compact('comment_post_ID', 'comment_author', 'comment_author_email', 'comment_author_url', 'comment_content', 'comment_type', 'user_ID');
						$comment_id = wp_new_comment( $commentdata );

						if(isset($_FILES['attachment']['name'])){
							$file = wp_upload_bits($_FILES["attachment"]["name"], null, file_get_contents($_FILES["attachment"]["tmp_name"]));
							$url = $file['url'];
							$file = $file['file'];
							$wp_filetype = wp_check_filetype(basename($file), null );
							
							// Construct the attachment array
							$attachment = array(
								'post_mime_type' => $wp_filetype['type'],
								'post_title' => preg_replace('/\.[^.]+$/', '', basename($file)),
								'post_content' => '',
								'post_status' => 'inherit',
								'guid' => $url,
								);
							// Save the data
							$attach_id = wp_insert_attachment($attachment, $file, $comment_id);
						}
					break;
					case 'update-task':	
						$id = intval($_REQUEST['id']);
						wp_update_post( array( 'ID' => $id, 'post_title' => $_REQUEST['name'], 'post_content' => $_REQUEST['description'] ) );
						update_post_meta($id, 'tasks_owner', $_REQUEST['select-choice-1']);
						update_post_meta($id, 'tasks_priority', $_REQUEST['radio-choice-1']);
						$parts = explode("/", $_REQUEST['duedate']);
						$date = mktime(0, 0, 0, $parts[0], $parts[1], $parts[2]);						
						update_post_meta($id, 'tasks_date_due', $date);
					break;
					case 'change-status':	
						$id = intval($_REQUEST['id']);
						$tasks_status = get_post_meta( $id , 'tasks_status' , true );
						++$tasks_status;
						if ($tasks_status > 6) {
							$tasks_status = 1;
						} else {
							if ($tasks_status > 2) {
								$tasks_status = 6;
							}
						}
						update_post_meta($id, 'tasks_status', $tasks_status);
					break;
					case 'get-tasks':	
					break;			
				}
				$response['result']['tasks'] = $this->site_task_chrome_list_page($post_id);
				global $current_user;
				$response['result']['current_user'] = $current_user;
				$blog_users = get_users_of_blog();
				$users = array();
				foreach ($blog_users as $user) {
					$pos = strpos($user->meta_value, 'administrator');
					$pos1 = strpos($user->meta_value, 'editor');
					if (($pos !== false) || ($pos1 !== false)) {
						$users[] = get_userdata($user->ID);
					}
				}
				$response['result']['users'] = $users;
			}
			echo (json_encode($response));
			die();				
		}

		function site_task_chrome_list_page($page_id) {
			$post = get_post($page_id);
			$type = $post->post_type;
			$site_tasks_list = $this->get_tasks_by_post_id($page_id);
			if($site_tasks_list){
				function cmp($a, $b) {
					$custom = get_post_custom($a->ID);
					$tasks_status_a = intval($custom["tasks_status"][0]);											
					$custom = get_post_custom($b->ID);
					$tasks_status_b = intval($custom["tasks_status"][0]);																
					if (($tasks_status_a != 6) && ($tasks_status_b == 6)) {
						return -1;
					}
					if (($tasks_status_a == 6) && ($tasks_status_b != 6)) {
						return 1;
					}					
					return 0;				
				}
				uasort($site_tasks_list, "cmp");
				foreach($site_tasks_list as $site_tasks){
					$id = absint($site_tasks->ID);				
					$custom = get_post_custom($id);
					$site_tasks->custom = $custom;
					$id = get_post_meta( $id , 'tasks_owner' , true ); 				
					$user_info = get_userdata($id);
					$site_tasks->user_info = $user_info;
					$comments = get_comments(array('post_id' => $site_tasks->ID));
					
					foreach ($comments as $comment) {
						$comment->attachment = get_children( array('post_parent' => $comment->comment_ID, 'post_status' => 'inherit', 'post_type' => 'attachment') );
					}
					
					$site_tasks->comments = $comments;
				}
			}
			return $site_tasks_list;			
		}

		
		function site_tasks_wp_ajax() {
			$response = array();
			// Allowed actions: add, update, delete
			$action = isset( $_REQUEST['operation'] ) ? $_REQUEST['operation'] : 'add';
			$post_id = intval($_REQUEST["tasks_post_id"]);						
			switch ( $action ) {
				case 'add':			
					wp_insert_post( array( 'post_title' => $_REQUEST['post_title'], 'post_type' => 'site_task', 'post_status' => 'publish' ) );
				break;			
				case 'update':			
					$id = intval($_REQUEST['post_ID']);
					$post = get_post($id);	
					$this->site_tasks_wp_insert_post($post->ID, $post);
				break;
			}
			$response['result'] = $this->site_tasks_list_page($post_id);
			echo (json_encode($response));
			die();				
		}

		function import_site_tasks() {
			$response = '';
			$action = isset( $_REQUEST['operation'] ) ? $_REQUEST['operation'] : 'get-tasks';
			switch ( $action ) {
				case 'get-tasks':
					$site_tasks_list = $this->get_tasks_by_post_id();
					if($site_tasks_list){
						foreach($site_tasks_list as $site_tasks){
							$id = absint($site_tasks->ID);				
							$custom = get_post_custom($id);
							$id = get_post_meta( $id , 'tasks_owner' , true ); 				
							$user_info = get_userdata($id);
							$external_id = '&#160;';
							$name = '&#160;';
							$description = '&#160;';
							$requested_by =  $user_info->user_login;
							$created_at = '&#160;';
							$story_type = '&#160;';
							$estimate = '&#160;';
							
							if ($site_tasks->ID > 0) {
								$external_id = $site_tasks->ID;
							}
							if ($site_tasks->post_title != '') {
								$name = $site_tasks->post_title;
							}
							if ($site_tasks->post_content != '') {
								$description = $site_tasks->post_content;
							}
							if (($user_info->first_name != '') && ($user_info->last_name != '')) {
								$requested_by = $user_info->first_name . ' ' . $user_info->last_name;
							}
							if ($site_tasks->post_date_gmt != '') {
								$created_at = $site_tasks->post_date_gmt;
							}
							
							$response .= '<external_story>
									<external_id>' . $external_id . '</external_id>
									<name>' . $name . '</name>
									<description>' . $description . '</description>
									<requested_by>' . $requested_by . '</requested_by> 
									<created_at type="datetime">' . $created_at . '</created_at>
									<story_type>feature</story_type>
									<estimate type="integer">0</estimate>
							</external_story>';
						}
					}
				break;			
			}
			$response = '<?xml version="1.0" encoding="UTF-8"?><external_stories type="array">'
						. $response . 
						'</external_stories>';
			echo($response);
			die();				
		}
		
		function site_tasks_admin_menu() {
			add_meta_box('site-tasks-div', 'Site Tasks', array(&$this, 'site_tasks_metabox_page'), 'page', 'side');
			add_meta_box('site-tasks-div', 'Site Tasks', array(&$this, 'site_tasks_metabox_page'), 'post', 'side');			
		}

		function site_tasks_metabox_page() {
			global $post, $current_user;
			wp_get_current_user();
			if (is_super_admin( $current_user->ID ) || ( current_user_can( 'edit_posts' ))) {
				echo ($this->site_tasks_list_page($post->ID));
			}
		}

		function site_tasks_list_page($page_id) {
			$post = get_post($page_id);
			$type = $post->post_type;
			$site_tasks_list = $this->get_tasks_by_post_id($page_id);
			if($site_tasks_list){
				function cmp($a, $b) {
					$custom = get_post_custom($a->ID);
					$tasks_status_a = intval($custom["tasks_status"][0]);											
					$custom = get_post_custom($b->ID);
					$tasks_status_b = intval($custom["tasks_status"][0]);																
					if (($tasks_status_a != 6) && ($tasks_status_b == 6)) {
						return -1;
					}
					if (($tasks_status_a == 6) && ($tasks_status_b != 6)) {
						return 1;
					}					
					return 0;				
				}
				uasort($site_tasks_list, "cmp");
				$output = '<table class="widefat post fixed" cellspacing="0">
							<tbody class="list:user user-list" id="site-tasks-body">';
				$this->theme = '';	
				foreach($site_tasks_list as $site_tasks){
					$this->theme = ('class="alternate"' == $this->theme) ? '' : 'class="alternate"';
					$id = absint($site_tasks->ID);
					$title = esc_html($site_tasks->post_title);
					$custom = get_post_custom($id);
					$tasks_priority = intval($custom["tasks_priority"][0]);
					$tasks_status = intval($custom["tasks_status"][0]);							
					$checked = '';
					if ($tasks_status == 6) {
						$checked = 'checked';
						$class_tasks = 'completed';						
					} else {
						$class_tasks = strtolower($this->priorities[$tasks_priority]);					
					}					

					$args = array(
						'post_id' => $id,
						'title' => true,						
						'echo' => false,										
					); 															
					$edit_block = $this->site_tasks_edit_block($args);				
					$output .= '<tr valign="top" '.$this->theme.'>						
						<td class="post-title column-title">
							<div>
								<input value="on" onclick="checkTasksItem('.$id.', '.$page_id.', \''.$type.'\');" type="checkbox" id="site-tasks-checkbox'.$id.'"  '.$checked.'/>																		
								<a id="site-tasks-name'.$id.'" title="Edit '.$title.'" href="/wp-admin/post.php?action=edit&amp;post='.$id.'" class="row-title '.$class_tasks.'">'.$title.'</a>																			
								<a onclick="showTasksItem('.$id.');" href="javascript:void(0);" title="Edit '.$title.'" id="site-tasks-edit-toggle'.$id.'" class="edit-visibility hide-if-no-js">Edit</a>										
							</div>
							<div class="site-tasks-edit wp-hidden-child" id="site-tasks-edit'.$id.'" style="display: none;">
								<div id="site-tasks-edit-table'.$id.'">'.$edit_block.'</div>
								<p class="textleft">
									<a class="button" href="javascript:void(0);" onclick="updateTasksItem('.$id.', '.$page_id.', \''.$type.'\');">Save</a>
									<a class="cancel-post-status hide-if-no-js" href="javascript:void(0);" onclick="hideTasksItem('.$id.');">Cancel</a>											
								</p>											
							</div>									
						</td>
					</tr>';
				}
				$output .= '</tbody></table>';
			} 	
			$args = array(
				'title' => true,						
				'echo' => false,										
			); 																		
			$add_block = $this->site_tasks_edit_block($args);	
			$output .= '<div class="wp-hidden-children" id="site-tasks-adder">
						<h4>
							<a class="hide-if-no-js" href="#site-tasks-add" id="site-tasks-add-toggle">
								+ Add New
							</a>
						</h4>
							<div class="site-tasks-add wp-hidden-child" id="site-tasks-add" style="display: none;">'
								.$add_block.'
								<p class="textleft">
									<a onclick="addTasksItem('.$page_id.', \''.$type.'\');" class="button" href="javascript:void(0);">Add New</a>						
									<a onclick="hideAddTasksItem();" class="cancel-post-status hide-if-no-js" href="javascript:void(0);">Cancel</a>																		
								</p>											
							</div>
						</div>				
						<p class="textright">
							<a class="button" href="'.admin_url().'edit.php?post_type=site_task">Goto Site Tasks List</a>
						</p>';	
			return $output;			
		}
		
		function site_tasks_post_updated_messages($messages) {
			global $post;
			$custom = get_post_custom($post->ID);
			if(isset($custom["tasks_post_id"][0])) {
				$tasks_post_id = intval($custom["tasks_post_id"][0]);			
				$page = get_post($tasks_post_id);				
				$messages["post"][6] = 'New Site Tasks was created, return to the '.
				'<a title="Return to '.esc_html($page->post_title).'" href="'.get_permalink($page->ID).'" class="row-title" style="display:inline;">'.esc_html($page->post_title).'</a>'; 						
			}

			return $messages;
		}
		
		function site_tasks_template_redirect() {
			global $is_chrome;
			if(is_admin() || $is_chrome) return;
			wp_enqueue_style( self::POSTTYPE, $this->pluginUrl . '/css/themes/base/ui.all.css' );			
			wp_enqueue_script('site-tasks', $this->pluginUrl . '/js/site-tasks.js', array('jquery'), '1.0'); // 						
			wp_enqueue_script('site-tasks-frontend', $this->pluginUrl . '/js/site-tasks-frontend.js', 
			array('jquery', 'jquery-ui-core', 'jquery-ui-draggable', 'jquery-ui-resizable', 'jquery-ui-dialog'), '1.0');			
		}

		function get_tasks_by_post_id($page_id = 0, $output = OBJECT) {
			global $wpdb;
			if ($page_id > 0) {
				$results = $wpdb->get_results( $wpdb->prepare( "SELECT DISTINCT post_id as ID FROM $wpdb->postmeta WHERE meta_key ='tasks_post_id'  AND 	
	meta_value=%s", $page_id ));
			} else {
				$results = $wpdb->get_results($wpdb->prepare("SELECT DISTINCT post_id as ID FROM $wpdb->postmeta WHERE meta_key ='tasks_post_id' "));
			}
			if ( is_array( $results ) && count( $results ) ) {
				$return = array();						
				foreach ( $results as $row ) {
					$post = get_post($row->ID, $output);
					if ($post->post_type == self::POSTTYPE) {
						$return[] = $post;
					}
				}
				return $return;
			}
			 return false;
		}
		
		function site_tasks_get_footer() { 
			global $post, $current_user;
			wp_get_current_user();
			if ((is_super_admin( $current_user->ID )  || ( current_user_can( 'edit_posts' )))) {
			?>
				<div id="site_tasks_dialog" title="Site Tasks List">
				<div class="postbox " id="site-tasks-div">
				<div class="inside">
			<?php
				echo ($this->site_tasks_list_page($post->ID));
			?>
				</div></div></div>							
			<?php
			}
		}
		
		function site_tasks_row_actions($actions) {
			global $post;
			if ($post->post_type == self::POSTTYPE) {
				unset($actions["view"]);
			}
			return 	$actions;			
		}		
		
		function site_tasks_posts_results($posts) {
			if (isset($_REQUEST["meta_key"]) && isset($_REQUEST["meta_value"])) {
				$result = array();
				$priority = intval($_REQUEST["meta_value"]);
				foreach ($posts as $post) {
					$custom = get_post_custom($post->ID);				
					$tasks_priority = intval($custom["tasks_priority"][0]);					
					if ($tasks_priority == $priority) {
						$result[] = $post;
					}
				}
				return $result;				
			} else {
				return $posts;
			}
		}
		
		
		function site_tasks_admin_init() {
			add_meta_box('site-tasks-post-div', 'Site Tasks Details', array(&$this, 'site_tasks_metabox_admin'), 'site_task', 'normal', 'high');
		}

		function site_tasks_add_styles() {
			wp_enqueue_script('site-tasks', $this->pluginUrl . '/js/site-tasks.js', array('jquery'), '1.0');			
		}		
		
		function site_tasks_edit_columns($columns)	{
			$columns = array(
				"cb" => "<input type=\"checkbox\" />",
				"title" => "Title",
				"tasks_post_id" => "Post/Page",				
				"site_task_cat" => "Category",				
				"site_task_tag" => "Tags",
				"tasks_date_due" => "Due",				
				"tasks_priority" => "Priority",				
				"tasks_status" => "Status",				
			);
			return $columns;
		}
		
		function site_tasks_custom_columns( $column ) {
			global $post;
			
			switch ( $column ) {
					case 'tasks_post_id':
					$id = get_post_meta( $post->ID , 'tasks_post_id' , true ); 
					if ($id) {
						$page = get_post($id);
						echo sprintf('<a href="%s">%s</a>',get_permalink($id), $page->post_title);					
					}
					break;								
				case 'site_task_cat':
					$site_tasks_cats = get_the_term_list( $post_id, self::TAXONOMY, '', ', ', '' );
					echo ( $site_tasks_cats ) ? strip_tags( $site_tasks_cats ) : '';
					break;								
				case 'site_task_tag':
					$site_tasks_tags = get_the_term_list( $post_id, self::TAG, '', ', ', '' );
					echo ( $site_tasks_tags ) ? strip_tags( $site_tasks_tags ) : '';
					break;													
				case 'tasks_owner':
					$id = get_post_meta( $post->ID , 'tasks_owner' , true ); 				
					if ($id) {
						$user_info = get_userdata($id);
						echo($user_info->user_login);
					}				
					break;					
				case 'tasks_date_due':
					$tasks_date_due = get_post_meta( $post->ID , 'tasks_date_due' , true ); 
					if ($tasks_date_due) {
						$tasks_month = date('M', $tasks_date_due);
						$tasks_day = date('d', $tasks_date_due);
						$tasks_year = date('Y', $tasks_date_due);					
						echo $tasks_month.' '.$tasks_day.' '.$tasks_year;										
					}
					break;
				case 'tasks_priority':
					$id = get_post_meta( $post->ID , 'tasks_priority' , true ); 
					if ($id) {
						echo '<span class="'.strtolower($this->priorities[$id]).'">'.$this->priorities[$id].'</span>';
					}									
					break;										
				case 'tasks_status':
					$id = get_post_meta( $post->ID , 'tasks_status' , true ); 
					if ($id) {
						echo $this->statuses[$id];
					}									
					break;					
			}

		}
		
		function site_tasks_metabox_admin() {
			global $post;
			?>
			<style>
				#message a {
					display: none;
				}
				#preview-action {
					display: none;				
				}
			</style>	
			<?php	
			$args = array(
				'post_id' => $post->ID,
			); 					
			$this->site_tasks_edit_block($args);			
		}

		function site_tasks_edit_block($args = '') {		
			global $wpdb;

			$defaults = array(
				'post_id' => 0,
			    'title' => false,				
				'echo' => true,
			); 		
		
			$r = wp_parse_args( $args, $defaults );
			extract( $r, EXTR_SKIP );		

			$custom = get_post_custom($post_id);
			if(isset($custom["tasks_post_id"][0])) 
				$tasks_post_id = intval($custom["tasks_post_id"][0]);
			elseif((isset($_REQUEST["tasks_post_id"])) ) 
				$tasks_post_id = $_REQUEST["tasks_post_id"];			
			else 	
				$tasks_post_id = 0;						
			if(isset($custom["tasks_post_type"][0])) 
				$tasks_post_type = $custom["tasks_post_type"][0];
			else $tasks_post_type = 'page';				
			if(isset($custom["tasks_owner"][0])) 
				$tasks_owner = intval($custom["tasks_owner"][0]);
			else $tasks_owner = 0;
			if(isset($custom["tasks_date_due"][0])) 
				$tasks_date_due = intval($custom["tasks_date_due"][0]);
			else $tasks_date_due = time();
			if(isset($custom["tasks_priority"][0])) 
				$tasks_priority = intval($custom["tasks_priority"][0]);
			else $tasks_priority = 1;			
			if(isset($custom["tasks_status"][0])) 
				$tasks_status = intval($custom["tasks_status"][0]);
			else $tasks_status = 2;
			$output_html = '<table class="site-tasks-edit-block" cellpadding="5" cellspacing="2" width="100%">
							<tbody>
							<$TypeList$>							
							<tr>
								<td align="right"><label for="tasks_month">Due: </label></td>
								<td>
									<$Due$>
								</td>
								</tr>
								<tr>
									<td align="right"><label for="tasks_owner">Assign to:</label></td>
									<td>
									<select name="tasks_owner">
										<$UserList$>
									</select>
									</td>
								</tr>
								<tr>
									<td align="right"><label for="tasks_priority">Priority:</label></td>
									<td>
									<select class="<$PrioritiesListClass$>" name="tasks_priority">
										<$PrioritiesList$>
									</select>
									</td>
								</tr>								
								<tr>
									<td align="right"><label for="tasks_status">Status:</label></td>
									<td>
									<select name="tasks_status">
										<$StatusList$>
									</select>
									</td>
								</tr>
							</tbody>
							</table>';
			
			$month_array = array('Jan', 'Feb', 'Mar', 'Apr', 
				'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec');	
			$cur_month = date('n', $tasks_date_due);
			$month_html = '';
			for($i=1; $i < 13; $i++)
			{
				$month_html .= '<option value="' . $i;
				if($cur_month == $i)
				{
					$month_html .= '" selected="selected';
				}
				$month_html .= '">' . $month_array[$i-1] . '</option>';
			}
				
			$month_html = '<select name="tasks_month">' . $month_html . '</select>' .
				' <input name="tasks_day" size="2" maxlength="2" value="' . date('d', $tasks_date_due) . '" />' .
				'<input name="tasks_year" size="4" maxlength="4" value="' . date('Y', $tasks_date_due) . '" />';
				
			$output_html = str_replace('<$Due$>', $month_html, $output_html);						
			$users = $wpdb->get_results("SELECT * FROM `$wpdb->users`");
			$userlist = '';
			foreach($users as $user) {
				if ($tasks_owner == $user->ID) {
					$selected = ' selected="selected"';
				} else {
					$selected = '';			
				}
				$userlist .= '<option value="' . $user->ID . '" '.$selected.'>' . $user->display_name . '</option>';
			}
			$output_html = str_replace('<$UserList$>', $userlist, $output_html);		

			$statuslist = '';
			foreach($this->statuses as $key => $value) {
				if ($tasks_status == $key) {
					$selected = ' selected="selected"';
				} else {
					$selected = '';			
				}
				$statuslist .= '<option value="' . $key . '" '.$selected.'>' . $value . '</option>';
			}
			$output_html = str_replace('<$StatusList$>', $statuslist, $output_html);								  
			
			if ($title) {
				if ($post_id == 0) {
					$pageslist = '<tr>
									<td colspan="2"><div style="display:none" id="tasks-title-error" class="tasks-title-error"><p>Please provide a title.</p></div></td>
								 </tr>
								  <tr>
									<td align="right"><label for="tasks_page_id">Title: </label></td>
									<td><input type="text" aria-required="true" value="" class="form-required" id="tasks_newname" name="tasks_newname"></td>
								</tr>';							
				} else {
					$pageslist = '';
				}	
			} else {
				$pageslist	= wp_dropdown_pages(array('id' => 'sel_tasks_page_id', 'name' => 'tasks_page_id', 'sort_column'=> 'menu_order, post_title', 'echo' => 0, 'show_option_none' => 'Select a page', 'selected' => $tasks_post_id));			
				$pageslist = '<tr>
								<td align="right"><label title="Page"><input type="radio"'.(($tasks_post_type == 'page') ? ' checked="checked"' : '').' value="page" name="tasks_post_type">Page: </label></td>
								<td>'.$pageslist.'</td>
							</tr>';
				$postlist	= wp_dropdown_posts(array('id' => 'sel_tasks_post_id', 'name' => 'tasks_post_id', 'sort_column'=> 'menu_order, post_title', 'echo' => 0, 'show_option_none' => 'Select a post', 'selected' => $tasks_post_id, 'jump_to' => 0));			
				$postlist = '<tr>
								<td align="right"><label title="Post"><input type="radio" '.(($tasks_post_type == 'post') ? ' checked="checked"' : '').' value="post" name="tasks_post_type">Post: </label></td>
								<td>'.$postlist.'</td>
							</tr>';
				$pageslist .= $postlist;							
			}

			$output_html = str_replace('<$TypeList$>', $pageslist, $output_html);								  													
			$prioritieslist = '';
			foreach($this->priorities as $key => $value) {
				if ($tasks_priority == $key) {
					$selected = ' selected="selected"';
				} else {
					$selected = '';			
				}
				$prioritieslist .= '<option class="'.strtolower($this->priorities[$key]).'" value="' . $key . '" '.$selected.'>' . $value . '</option>';
			}
			$output_html = str_replace('<$PrioritiesListClass$>', strtolower($this->priorities[$tasks_priority]), $output_html);								  			
			$output_html = str_replace('<$PrioritiesList$>', $prioritieslist, $output_html);								  

			$pageslist = wp_dropdown_pages(array('id' => 'sel_tasks_page_id', 'name' => 'tasks_page_id', 'sort_column'=> 'menu_order, post_title', 'echo' => 0, 'show_option_none' => 'Select a page', 'selected' => $tasks_page_id));			
			$output_html = str_replace('<$PagesList$>', $pageslist, $output_html);								  			
			if ($echo) {
				echo ($output_html);
			} else {
				return $output_html;
			}
		}		
		
		// When a post is inserted or updated
		function site_tasks_wp_insert_post($post_id, $post = null) {
			if ($post->post_type == self::POSTTYPE) {
				// Loop through the POST data
				foreach ($this->metaFields as $key) {
					switch ( $key ) {
						case 'tasks_date_due':			
							if (isset($_POST['tasks_year']) && isset($_POST['tasks_month']) && isset($_POST['tasks_day'])) {
								$value = mktime(0, 0, 0, $_POST['tasks_month'], $_POST['tasks_day'], $_POST['tasks_year']);																
							} else {
								$value = @$_POST[$key];									
							}
						break;			
						case 'tasks_post_id':			
							if ($_POST['tasks_post_type'] == 'page') {
								$value = $_POST['tasks_page_id'];																
							} else {
								$value = $_POST['tasks_post_id'];																							
							}
						break;
						default:
						$value = @$_POST[$key];																
					}				

					if (empty($value))	{
						// delete_post_meta($post_id, $key);
						continue;
					}
					// If value is a string it should be unique
					if (!is_array($value)) {
						// Update meta
						if (!update_post_meta($post_id, $key, $value)) {
							// Or add the meta data
							add_post_meta($post_id, $key, $value);
						}
					}
					else {
						// If passed along is an array, we should remove all previous data
						delete_post_meta($post_id, $key);

						// Loop through the array adding new values to the post meta as different entries with the same name
						foreach ($value as $entry)
							add_post_meta($post_id, $key, $entry);
					}
				}
			}
		}
		

//------------------------------------------------------
//-------------- DASHBOARD PAGE -------------------------
    //Registers the dashboard widget
	function site_tasks_dashboard_setup(){
        wp_add_dashboard_widget('site_tasks_dashboard', 'Site Tasks List',  array( &$this, 'site_tasks_dashboard'));
    }

    //Displays the dashboard UI
	function site_tasks_dashboard(){

		$args = array(
			'numberposts' => 3
		); 

        $site_tasks_list = $this->site_tasks_get_list($args);
		
        if(sizeof($site_tasks_list) > 0){
            ?>
            <table class="widefat post fixed" cellspacing="0">
                <thead>
					<th class="manage-column column-title" id="title" scope="col"><i>Title</i></th>				
					<th class="manage-column column-tasks_page_id" id="tasks_page_id" scope="col"><i>Post/Page</i></th>					
                </thead>
                <tbody class="list:user user-list">
                    <?php
					$theme = '';	
                    foreach($site_tasks_list as $site_tasks){
						$theme = ('class="alternate"' == $theme) ? '' : 'class="alternate"';
						$custom = get_post_custom($site_tasks->ID);
						$tasks_page_id = intval($custom["tasks_post_id"][0]);
						$page = get_post($tasks_page_id);
						$tasks_priority = intval($custom["tasks_priority"][0]);
                        ?>
						<tr valign="top" <?php echo($theme); ?>>						
							<td class="post-title column-title">
								<strong>
									<a title="Edit <?php echo esc_html($site_tasks->post_title) ?>" href="post.php?action=edit&amp;post=<?php echo absint($site_tasks->ID) ?>" class="row-title <?php echo(strtolower($this->priorities[$tasks_priority])); ?>"><?php echo esc_html($site_tasks->post_title) ?></a>
								</strong>
                            </td>
							<td class="post-title column-title">
								<strong>
									<a title="Edit <?php echo esc_html($page->post_title) ?>" href="post.php?action=edit&amp;post=<?php echo absint($page->ID) ?>" class="row-title"><?php echo esc_html($page->post_title) ?></a>
								</strong>
                            </td>							
                        </tr>
                        <?php
                    }
                    ?>
                </tbody>
            </table>
			<?php $this->site_tasks_get_summary(); ?>
            <p class="textright">
				<a class="button" href="edit.php?post_type=site_task">Goto Site Tasks List</a>
			</p>
            <?php
        }
        else{
            ?>
            <div>
                <?php echo sprintf("You don't have any Site Tasks items. Let's go %s create one %s!", '<a href="post-new.php?post_type=site_task">', '</a>'); ?>
            </div>
            <?php
        }
    }
	
	function site_tasks_get_list($args = '') {
		$args['post_type'] = 'site_task';
		return get_posts($args);
    }	

	function site_tasks_get_summary() {
		$args = array(
			'meta_key' => 'tasks_priority',
			'meta_value' => 1
		); 
		$low = count($this->site_tasks_get_list($args));
		$args['meta_value'] = 2;
		$normal = count($this->site_tasks_get_list($args));
		$args['meta_value'] = 3;
		$urgent = count($this->site_tasks_get_list($args));
		$output_html = "<ul class='subsubsub'>";
		if ($urgent > 0) {
			$output_html .= "<li><a class='urgent' href='edit.php?meta_key=tasks_priority&amp;meta_value=3&amp;post_type=site_task'>Urgent <span>(".$urgent.")</span></a> |</li>";		
		} else {
			$output_html .= "<li><span class='urgent'>Urgent <span>(".$urgent.")</span></span> |</li>";				
		}	
		if ($normal > 0) {
			$output_html .= "<li><a class='normal' href='edit.php?meta_key=tasks_priority&amp;meta_value=2&amp;post_type=site_task'>Normal <span>(".$normal.")</span></a> |</li>";		
		} else {
			$output_html .= "<li><span class='normal'>Normal <span>(".$normal.")</span></span> |</li>";				
		}	
		if ($low > 0) {
			$output_html .= "<li><a class='low' href='edit.php?meta_key=tasks_priority&amp;meta_value=1&amp;post_type=site_task'>Low <span>(".$low.")</span></a></li>";		
		} else {
			$output_html .= "<li><span class='low'>Low <span>(".$low.")</span></span></li>";				
		}			
		$output_html .= "</ul>";				
		echo $output_html;				
    }		
	}

	global $site_tasks;
	$site_tasks = new The_Site_Tasks();	
}
