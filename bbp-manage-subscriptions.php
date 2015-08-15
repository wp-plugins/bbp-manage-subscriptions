<?php
/*
 * Plugin Name: BBP Manage Subscriptions
 * Description: A table to manage BBP subscriptions in your WordPress Admin area.
 * Plugin URI: http://www.casier.eu/dev/bbp-manage-subscriptions
 * Author: Pascal Casier
 * Author URI: https://www.facebook.com/pascal.casier
 * Version: 1.1
 * License: GPL2
 */
 
if (!is_admin()) {
	//echo 'Cheating ? You need to be admin to view this !';
	return;
} // is_admin

// Check if bbpress is installed and running
// Check if get_plugins() function exists. This is required on the front end of the
// site, since it is in a file that is normally only loaded in the admin.
if ( ! function_exists( 'get_plugins' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}
if ( !is_plugin_active( 'bbpress/bbpress.php' ) ) {
	//plugin is not active
	echo 'bbpress plugin is not active (or not found in ' . ABSPATH . PLUGINDIR . '/bbpress/bbpress.php) !';
	return;
} 

// Check if action needs to be done without loading the rest of the page
if ( isset($_GET['action']) ) {
	if ($_GET['action'] == "add_subscr") {
		if ( !function_exists( 'bbp_add_user_subscription' ) ) {
			require_once ABSPATH . WPINC . '/pluggable.php';
			require_once ABSPATH . PLUGINDIR . '/bbpress/bbpress.php';
			require_once ABSPATH . PLUGINDIR . '/bbpress/includes/users/functions.php';
		}
		bbp_add_user_subscription($_GET['userid'], $_GET['forumid']);
		$new_header = $_GET;
		unset($new_header['action']);
		unset($new_header['userid']);
		unset($new_header['forumid']);
		$QS = http_build_query($new_header);
		header('Location: ?' . $QS);
	}
	if ($_GET['action'] == "del_subscr") {
		if ( !function_exists( 'bbp_remove_user_subscription' ) ) {
			require_once ABSPATH . WPINC . '/pluggable.php';
			require_once ABSPATH . PLUGINDIR . '/bbpress/bbpress.php';
			require_once ABSPATH . PLUGINDIR . '/bbpress/includes/users/functions.php';
		}
		bbp_remove_user_subscription($_GET['userid'], $_GET['forumid']);
		$new_header = $_GET;
		unset($new_header['action']);
		unset($new_header['userid']);
		unset($new_header['forumid']);
		$QS = http_build_query($new_header);
		header('Location: ?' . $QS);
	}
}

// WP_List_Table is not loaded automatically so we need to load it in our application
if( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}
add_action( 'admin_menu', 'add_menu_BBPMS_list_table_page' );

class BBPMS_List_Table extends WP_List_Table
{
    /**
     * Prepare the items for the table to process
     *
     * @return Void
     */
    function prepare_items()
    {
	global $wp_roles;
	
	// Get roles on this system and remove Pending
	$all_roles = $wp_roles->roles;
	unset($all_roles['pending']);
	$roles_to_show = array_keys($all_roles);
	
	$forums_all_data = get_forum_data();
	$forums_to_show = get_forum_ids_with_prefix();
    		
        $columns = $this->get_columns($roles_to_show);
        $hidden = $this->get_hidden_columns();
        $sortable = $this->get_sortable_columns($roles_to_show, $forums_all_data['all_ids_with_prefix_array']);

	$userid = get_current_user_id();
        $data = $this->table_data($roles_to_show, $userid);
        usort( $data, array( &$this, 'sort_data' ) );

	// get the options set by the current user
        $perPage = get_user_meta($userid, 'bbpms-perpage', true);
        // if no value set, use the default
        if ( empty ( $perPage ) || $perPage < 1 || !is_numeric($perPage)) {
		$perPage = 15;
	}

        $currentPage = $this->get_pagenum();
        $totalItems = count($data);
 
        $this->set_pagination_args( array(
            'total_items' => $totalItems,
            'per_page'    => $perPage
        ) );
 
        $data = array_slice($data,(($currentPage-1)*$perPage),$perPage);
 
        $this->_column_headers = array($columns, $hidden, $sortable);
        $this->items = $data;
    }

    /**
     * Override the parent columns method. Defines the columns to use in your listing table
     *
     * @return Array
     */
    function get_columns($roles_to_show)
    {
        $columns = array(
            'display_name'	=> 'Name'
        );

	foreach ($roles_to_show as $myrole) {
		$newarray = array ( $myrole => $myrole );
		$columns = array_merge($columns, $newarray);
	}

	if ( bbp_has_forums() ) {
		while ( bbp_forums() ) {
			bbp_the_forum();
			$forum_id = bbp_get_forum_id();
			$forum_id_with_prefix = 'F' . $forum_id;
			$newarray = array ( $forum_id_with_prefix => bbp_get_forum_title($forum_id) );
        		$columns = array_merge($columns, $newarray);
		} // while()
	} // if()

        return $columns;
    }
 
    /**
     * Define which columns are hidden
     *
     * @return Array
     */
    function get_hidden_columns()
    {
	// At least hide the ID and role=pending
	$columns_to_hide = array('ID', 'pending');
	
	// Get the columns to hide from the user's option
	$userid = get_current_user_id();

        $colstohide = get_user_meta($userid, 'bbpms-hidden-roles', true);
        if ( !empty ( $colstohide ) ) {
	        $array_roles = explode(",",$colstohide);
		foreach ($array_roles as $coltohide) {
			array_push ($columns_to_hide, $coltohide);
		}
	}

        $colstohide = get_user_meta($userid, 'bbpms-hidden-forum-ids', true);
        if ( !empty ( $colstohide ) ) {
	        $array_forum_ids = explode(",",$colstohide);
		foreach ($array_forum_ids as $coltohide) {
			array_push ($columns_to_hide, 'F'.$coltohide);
		}
	}
	
        return $columns_to_hide;
    }
 
    /**
     * Define the sortable columns
     *
     * @return Array
     */
    function get_sortable_columns($roles_to_show, $forums_to_show)
    {
    
	$columns = array(
		'display_name'	=> array('display_name', false),
	);

	foreach ($roles_to_show as $myrole) {
		$newarray = array ( $myrole => array ($myrole,true) );
		$columns = array_merge($columns, $newarray);
	}
	foreach ($forums_to_show as $myforum) {
		$newarray = array ( $myforum => array ($myforum,true) );
		$columns = array_merge($columns, $newarray);
	}
	return $columns;
    }
 
    /**
     * Get the table data
     *
     * @return Array
     */
    function table_data($roles_to_show, $userid)
    {
	global $wpdb;
	$cap_with_prefix = $wpdb->prefix . 'capabilities';
	$all_data = array();
	$all_users = get_users( array( 'fields' => array( 'ID', 'display_name' ), 'orderby' => 'display_name', ) );
	$i = 0;
	foreach ($all_users as $a_user) {
		$caps = get_user_meta($a_user->ID, $cap_with_prefix, true);
		$roles = array_keys((array)$caps);
		$subscriptions = bbp_get_user_subscribed_forum_ids($a_user->ID);

		// Only show the visible roles ?
		$what_to_show = get_user_meta($userid, 'bbpms-showusers', true);
		if ($what_to_show == 'nohidden') {
			$hidden_roles = explode(",",get_user_meta($userid, 'bbpms-hidden-roles', true));
			$roles_to_show = array_diff($roles_to_show, $hidden_roles);
		}

		$show_user = false;
		foreach ($roles as $r) {
			if (in_array($r, $roles_to_show)) {
				$show_user = true;
			}
		}
		if ($show_user) {
			$all_data[$i]['display_name'] = $a_user->display_name;
			$all_data[$i]['ID'] = $a_user->ID;
			foreach ($roles as $r) {
				if (in_array($r, $roles_to_show)) {
					$all_data[$i][$r] = '<button class="role-button">HasRole</button>';
				}
			}
			// Get all forums and fill with default value
			if ( bbp_has_forums() ) {
				while ( bbp_forums() ) {
					bbp_the_forum();
					$forum_id = bbp_get_forum_id();
					$fname = 'F' . $forum_id;
					$QS = http_build_query(array_merge($_GET, array("action"=>"add_subscr", "forumid"=>$forum_id, "userid"=>($a_user->ID))));
					$all_data[$i][$fname] = '<a href="?'.$QS.'"><button class="forum-no-button">No subscr</button></a>';
				} // while()
			} // if()
			//Overwrite the fields with the subscriptions of this user
			if ( !empty( $subscriptions ) ) {
				foreach ($subscriptions as $subscr) {
					$fname = 'F' . $subscr;
					$QS = http_build_query(array_merge($_GET, array("action"=>"del_subscr", "forumid"=>$forum_id, "userid"=>($a_user->ID))));
					$all_data[$i][$fname] = '<a href="?'.$QS.'"><button class="forum-button">Subscribed</button></a>';
				}
				
			}
			$i++;
		}
	}
        return $all_data;
    }
 
    /**
     * Define what data to show on each column of the table
     *
     * @param  Array $item        Data
     * @param  String $column_name - Current column name
     *
     * @return Mixed
     */
    function column_default( $item, $column_name )
    {
        switch( $column_name ) {
            default:
                return $item[ $column_name ];
        }
    }
 
    /**
     * Allows you to sort the data by the variables set in the $_GET
     *
     * @return Mixed
     */
    function sort_data( $a, $b )
    {
        // Set defaults
        $orderby = 'display_name';
        $order = 'desc';
 
        // If orderby is set, use this as the sort column
        if(!empty($_GET['orderby']))
        {
            $orderby = $_GET['orderby'];
        }
 
        // If order is set use this as the order
        if(!empty($_GET['order']))
        {
            $order = $_GET['order'];
        }
 
 
        $result = strnatcmp( $a[$orderby], $b[$orderby] );
 
        if($order === 'asc')
        {
            return $result;
        }
 
        return -$result;
    }

} //class

function add_menu_BBPMS_list_table_page() {
	$hook = add_menu_page( 'BBP Manage Subscriptions', 'BBP Manage Subscriptions', 'manage_options', 'bbp-manage-subscriptions.php', 'list_table_page' );
	add_action( 'admin_head-'.$hook, 'admin_header' );
	$confHook = add_submenu_page( 'bbp-manage-subscriptions.php', 'Settings', 'Settings', 'manage_options', 'bbp-manage-subscriptions-settings.php', 'show_settings' );
	add_action("admin_head-$confHook", 'admin_header');
} //add_menu_BBPMS_list_table_page


function admin_header() {
	global $wp_roles;
	$all_roles = $wp_roles->roles;
	unset($all_roles['pending']);
	$roles_to_show = array_keys($all_roles);
	$forums_to_show = get_forum_ids_with_prefix();
	
	echo '<style type="text/css">';
	echo '.wp-list-table { width:auto !important; }';
	echo '.wp-list-table .column-display_name { width: 30px; white-space: nowrap; padding: 3px 5px;}';
	
	foreach ($roles_to_show as $myrole) {
		echo '.wp-list-table tr:nth-child(odd) .column-' . $myrole . ' { text-align: center; background-color: #D0D0D0; }';
		echo '.wp-list-table tr:nth-child(even) .column-' . $myrole . ' { text-align: center; background-color: #D6EBFF; }';
	}
	foreach ($forums_to_show as $myforum) {
		echo '.wp-list-table tr:nth-child(odd) .column-' . $myforum . ' { text-align: center; background-color: #F0E68C; }';
		echo '.wp-list-table tr:nth-child(even) .column-' . $myforum . ' { text-align: center; background-color: #FFFFB8; }';
	}
	echo '.wp-list-table .forum-button {
	  -webkit-border-radius: 28;
	  -moz-border-radius: 28;
	  border-radius: 28px;
	  font-family: Arial;
	  color: #ffffff;
	  font-size: 12px;
	  background: #3498db;
	  padding: 3px 7px 3px 7px;
	  text-decoration: none;
	}';
	echo '.wp-list-table .role-button {
	  -webkit-border-radius: 28;
	  -moz-border-radius: 28;
	  border-radius: 28px;
	  font-family: Arial;
	  color: #ffffff;
	  font-size: 12px;
	  background: #34d981;
	  padding: 3px 7px 3px 7px;
	  text-decoration: none;
	}';
	echo '.wp-list-table .forum-no-button {
	  -webkit-border-radius: 28;
	  -moz-border-radius: 28;
	  border-radius: 28px;
	  font-family: Arial;
	  color: #ffffff;
	  font-size: 10px;
	  background: #D8D8D8;
	  padding: 3px 7px 3px 7px;
	  text-decoration: none;
	}';
	
	echo '</style>';
}    
	
/**
* Display the list table page
*
* @return Void
*/
function list_table_page() {
	$ListTable = new BBPMS_List_Table();
	$ListTable->prepare_items();
	?>
		<div class="wrap">
		<h1>BBP Manage Subscriptions</h1>
		<?php $ListTable->display(); ?>
		</div>
	<?php
}

/**
* Get all forum_ids with prefix 'F'
*
* @return Array
*/
function get_forum_ids_with_prefix() {
	$newarray = array();
	if ( bbp_has_forums() ) {
		while ( bbp_forums() ) {
			bbp_the_forum();
			$forum_id_with_prefix = 'F' . bbp_get_forum_id();
			array_push( $newarray, $forum_id_with_prefix);
		} // while()
	} // if()
	return $newarray;
}

/**
* Get all forum_data with prefix 'F'
*
* @return Array
*/
function get_forum_data() {
	$all_forums_data = array();
	$all_forums_ids = array();
	$all_forums_ids_with_prefix = array();
	$i = 0;
	if ( bbp_has_forums() ) {
		while ( bbp_forums() ) {
			bbp_the_forum();
			$forum_id = bbp_get_forum_id();
			$all_forums_data['all_data'][$i]['id'] = $forum_id;
			$all_forums_data['all_data'][$i]['title'] = bbp_get_forum_title($forum_id);
			array_push($all_forums_ids, $forum_id);
			array_push($all_forums_ids_with_prefix, 'F'.$forum_id);
			if ($sublist = bbp_forum_get_subforums($forum_id)) {
				$all_subforums = array();
				foreach ( $sublist as $sub_forum ) {
					$mysubforum = array ( 'id' => $sub_forum->ID, 'title' => bbp_get_forum_title( $sub_forum->ID ));
					array_push($all_subforums, $mysubforum);
					array_push($all_forums_ids, $sub_forum->ID);
					array_push($all_forums_ids_with_prefix, 'F'.$sub_forum->ID);
				}
				$all_forums_data['all_data'][$i]['subforums'] = $all_subforums;
			}					
			$i++;
		} // while()
		$all_forums_data['all_ids_string'] = implode(',',$all_forums_ids);
		$all_forums_data['all_ids_with_prefix_string'] = implode(',',$all_forums_ids_with_prefix);
		$all_forums_data['all_ids_array'] = $all_forums_ids;
		$all_forums_data['all_ids_with_prefix_array'] = $all_forums_ids_with_prefix;
	} // if()
	return $all_forums_data;
}

function show_settings() {
	$userid = get_current_user_id();
	
	// Check if options need to be saved, so if coming from form
	if ( isset($_POST['optssave']) ) {
		update_user_meta($userid, 'bbpms-perpage', $_POST['usersperpage']);
		update_user_meta($userid, 'bbpms-showusers', $_POST['showusers']);
		$array_roles = explode(",",$_POST['all_roles']);
		$roles_to_hide = array();
		foreach ($array_roles as $myrole) {
			if( !empty($_POST["role-$myrole"]) ) {
				// Checkbox was checked
				array_push($roles_to_hide, $myrole);
			}
		}
		update_user_meta($userid, 'bbpms-hidden-roles', implode(",",$roles_to_hide));
		
		$array_forum_ids = explode(",",$_POST['all_forum_ids']);
		$forum_ids_to_hide = array();
		foreach ($array_forum_ids as $myforumid) {
			if( !empty($_POST["forum-$myforumid"]) ) {
				// Checkbox was checked
				array_push($forum_ids_to_hide, $myforumid);
			}
		}
		update_user_meta($userid, 'bbpms-hidden-forum-ids', implode(",",$forum_ids_to_hide));
	}

	// get the per_page options set by the current user
        $perPage = get_user_meta($userid, 'bbpms-perpage', true);
        // if no value set, use the default
        if ( empty ( $perPage ) || $perPage < 1 || !is_numeric($perPage)) {
		$perPage = 15;
	}
        $showwhat = get_user_meta($userid, 'bbpms-showusers', true);
        // if no value set, use the default
        if ( empty ( $showwhat )) {
		$showwhat = 'all';
	}

	// Get array with forum_id and forum_title
	$all_forums = array();
	$all_forum_ids = array();
	$i = 0;
	if ( bbp_has_forums() ) {
		while ( bbp_forums() ) {
			bbp_the_forum();
			$forum_id = bbp_get_forum_id();
			$all_forums[$i]['id'] = $forum_id;
			$all_forums[$i]['title'] = bbp_get_forum_title($forum_id);
			array_push($all_forum_ids, $forum_id);
			if ($sublist = bbp_forum_get_subforums($forum_id)) {
				$all_subforums = array();
				foreach ( $sublist as $sub_forum ) {
					$mysubforum = array ( 'id' => $sub_forum->ID, 'title' => bbp_get_forum_title( $sub_forum->ID ));
					array_push ($all_subforums, $mysubforum);
					array_push($all_forum_ids, $sub_forum->ID);
				}
				$all_forums[$i]['subforums'] = $all_subforums;
			}					
			$i++;
		} // while()
	} // if()

	// Get forums that user already saved to hide
        $hidden_forum_ids = get_user_meta($userid, 'bbpms-hidden-forum-ids', true);

	// Get all roles
	global $wp_roles;	
	$all_roles = $wp_roles->roles;
	unset($all_roles['pending']);
	$all_roles = array_keys($all_roles);
	
	// Get roles that user already saved to hide
        $hidden_roles = get_user_meta($userid, 'bbpms-hidden-roles', true);
	

	?>
		<div class="wrap">
		<h1>BBP Manage Subscriptions Settings</h1>
		<form action="" method="post">
			<h3>Show users</h3>
			<p><input type="text" name="usersperpage" id="usersperpage" value="<?php echo $perPage ?>" maxlength="3" size="3" /><label for="usersperpage">Users per page</label></p>
			<p><select name="showusers" id="showusers">
			<?php
			echo '<option value="all" ';
				if ($showwhat == 'all') {echo 'selected';}
				echo '>Show all users</option>';
			echo '<option value="nohidden" ';
				if ($showwhat == 'nohidden') {echo 'selected';}
				echo '>Show users from visible roles only</option>';
			echo '</select></p>
				';
			echo '<h3>Roles to hide</h3>
				';
			echo '<p><input type="hidden" name="all_roles" value="'.implode(',',$all_roles).'"></p>
				';
			echo '<p><input type="hidden" name="all_forum_ids" value="'.implode(',',$all_forum_ids).'"></p>
				';
				
			foreach ($all_roles as $myrole) {
				echo '<p><input type="checkbox" name="role-'.$myrole.'" id="role-'.$myrole.'" value="'.$myrole.'" ';
				if (strpos($hidden_roles, $myrole) !== FALSE) { echo 'checked'; }
				echo '><label for="role-'.$myrole.'">'.$myrole.'</label></p>
					';
			}
			echo '<h3>Forums to hide</h3>
				';
			foreach ($all_forums as $myforum) {
				echo '<p><input type="checkbox" name="forum-'.$myforum['id'].'" id="forum-'.$myforum['id'].'" value="'.$myforum['id'].'" ';
				if (strpos($hidden_forum_ids, strval($myforum['id'])) !== FALSE) { echo 'checked'; }
				echo '><label for="forum-'.$myforum['id'].'">'.$myforum['title'].'</label></p>
					';
				if ($myforum['subforums']) {
					foreach ($myforum['subforums'] as $mysubforum) {
						echo '<p>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<input type="checkbox" name="forum-'.$mysubforum['id'].'" id="forum-'.$mysubforum['id'].'" value="'.$mysubforum['id'].'" ';
						if (strpos($hidden_forum_ids, strval($mysubforum['id'])) !== FALSE) { echo 'checked'; }
						echo '><label for="forum-'.$mysubforum['id'].'">'.$mysubforum['title'].'</label></p>
							';
					}
				}
			}

			?>

			<p><input type="submit" name="optssave" value="Save settings" /></p>
			
		</form>
		</div>
	<?php
}
?>