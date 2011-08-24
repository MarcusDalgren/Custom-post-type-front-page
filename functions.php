<?php
/*
Plugin Name: Custom Post Type Front Page and no CPT slugs
Plugin URI: http://www.overlay.se
Description: Pick a custom post type as your front page and removal of slugs
Version: 1.0
Author:  Marcus Dalgren
Author URI: http://www.overlay.se
License: GPL2
*/
function startpage_dropdown($output) {
	static $called = false;
	if (is_admin() && strpos($output, "page_on_front") !== false && $called == false) {
		$called = true;
		$output= wp_dropdown_pages( array( 'name' => 'page_on_front', 'echo' => 0, 'show_option_none' => __( '&mdash; Select &mdash;' ), 'option_none_value' => '0', 'selected' => get_option( 'page_on_front' ), 'post_type' => 'faq_page' ) );
	}
	return $output;
}
//add_filter('wp_dropdown_pages', 'startpage_dropdown');

function custom_page_redirect($redirect_url, $requested_url) {
	return (rtrim(array_shift(explode("?",$requested_url)), "/") != home_url());
}
//add_filter("redirect_canonical", "custom_page_redirect", 10, 2);

add_filter('request', 'root_cpt_request');  // parse_request function
 
function root_cpt_request($q) {
	if (is_admin()) return $q;
	/*
	if (empty($q) && !isset($q['p']) && !isset($q['page_id']) && !isset($q['name']) && !isset($q['pagename']) && !isset($q['post_type'])) {
		$q['post_type'] = 'faq_page';
		$q['p'] = get_option("page_on_front");
	}
	*/
	
	
	$special_pages = get_option("root_cpts", array());
	if (isset($q['page']) && isset($q['pagename']) && key_exists($q['pagename'], $special_pages)) {
		$page_name = array_pop(explode("/",$q['pagename']));
		$q['post_type'] = $special_pages[$q['pagename']];
		$q[$special_pages[$q['pagename']]] = $page_name;
		$q['name'] = $page_name;
		unset($q['pagename']);
	}
	/*
		'page' => string '' (length=0)
		'faq_page' => string 'faq' (length=3)
		'post_type' => string 'faq_page' (length=8)
		'name' => string 'faq' (length=3)
	*/
	return $q;
}

function get_cpt_post_parents_slug($parent_id) {
	global $wpdb;
	
	$slug = "";
	while($parent_id > 0) {
		$results = $wpdb->get_row("SELECT post_name, post_parent FROM {$wpdb->posts} WHERE ID = ".$parent_id);
		if (!$results) break;
		$slug = $slug."/";	
	}	
	return $slug;
}

function check_cpt_slug($post_id) {
	global $wpdb;
	
	$results = $wpdb->get_results("SELECT post_name, post_parent FROM {$wpdb->posts} WHERE post_parent = ".$post_id);
	if ($results) {
		foreach ($results as $result) {
			
		}
	}	
}

function check_root_cpt($cpt) {
	$name = $cpt;
	$cpt = get_post_type_object($cpt);
	return ($cpt && $cpt->_builtin === false && is_array($cpt->rewrite) && $cpt->rewrite['slug'] == "/");
}

function save_root_cpt_data($post_id) {
	global $wpdb;
	
	$ignore = array('post','revision','attachment','nav_menu_item','mediapage');
	if ($post_id != "activate") {
		$type = get_post_type_object($_POST["post_type"]);
		if (in_array($_POST["post_type"], $ignore) || !is_object($type)) return;
		//if (!$type->hierarchical) return;
	}
	$map = array();
	$content = $wpdb->get_results("SELECT ID, post_name, post_parent, post_type FROM {$wpdb->posts} WHERE post_type NOT IN('post','revision','attachment','nav_menu_item','mediapage')", OBJECT_K);
	if ($post_id != "activate") {
		if (key_exists($post_id, $content)) unset($content[$post_id]);
		$content[$post_id] = (object)array("ID" => $post_id, "post_name" => $_POST["post_name"], "post_parent" => $_POST["post_parent"], "post_type" => $_POST["post_type"]);
	}
	foreach ($content as $row) {
		if (!check_root_cpt($row->post_type)) continue;
		$slug = $row->post_name;
		$post_parent = $row->post_parent;
		while($post_parent > 0) {
			$slug = $content[$post_parent]->post_name."/".$slug;
			$post_parent = $content[$post_parent]->post_parent;
		}
		$map[$slug] = $row->post_type;
	}
	update_option("root_cpts", $map);
}

function update_cpt_data($post_id) {
	global $wpdb;
	
	$ignore = array('post','revision','attachment','nav_menu_item','mediapage');
	$type = get_post_type_object($_POST["post_type"]);
	if (in_array($_POST["post_type"], $ignore) || !is_object($type)) return;
	$slug = $_POST["post_name"];
	$row = $wpdb->get_row("SELECT ID, post_name, post_parent, post_type FROM {$wpdb->posts} WHERE ID = ".$post_id);
	$to_replace = $row->post_name;
	if ($row && ($slug != $to_replace || $_POST["post_parent"] != $row->post_parent)) {
		$hierarchy = get_cpt_post_parents_slug($_POST["post_parent"]);
		$slug = $hierarchy.$slug;
		$to_replace = ($_POST["post_parent"] != $row->post_parent) ? 
			get_cpt_post_parents_slug($_POST["post_parent"]).$to_replace : 
			$hierarchy.$to_replace
		;
		$slugs = (array)get_option("root_cpts", array());
		foreach ($slugs as $key => $value) {
			if (strpos($key, $to_replace) === 0) {
				$new_key = $slug.substr($key, strlen($to_replace));
				unset($slugs[$key]);
				$slugs[$new_key] = $value;
			}
		}
		update_option("root_cpts", $slugs);
	}
}
add_action( 'save_post', 'save_root_cpt_data' );

function root_cpt_activate() {
	save_root_cpt_data("activate");	
}
register_activation_hook( __FILE__, 'root_cpt_activate' );

function cpt_dropdown_pages($args = '') {
	$defaults = array(
		'depth' => 0, 'child_of' => 0,
		'selected' => 0, 'echo' => 1,
		'name' => 'page_id', 'id' => '',
		'show_option_none' => '', 'show_option_no_change' => '',
		'option_none_value' => '', 'post_type' => array("page")
	);

	$r = wp_parse_args( $args, $defaults );
	extract( $r, EXTR_SKIP );
	foreach ($post_type as $type) {
		$r["post_type"] = $type;
		$pages[$type] = get_pages($r);
	}
	$groups = (count($pages) == 1) ? false : true;
	$output = '';
	$name = esc_attr($name);
	// Back-compat with old system where both id and name were based on $name argument
	if ( empty($id) )
		$id = $name;

	if ( ! empty($pages) ) {
		$output = "<select name=\"$name\" id=\"$id\">\n";
		if ( $show_option_no_change )
			$output .= "\t<option value=\"-1\">$show_option_no_change</option>";
		if ( $show_option_none )
			$output .= "\t<option value=\"" . esc_attr($option_none_value) . "\">$show_option_none</option>\n";
		if ($groups) {
			foreach ($post_type as $type) {
				if (count($pages[$type]) == 0) continue;
				$output .= "<optgroup label=\"{$type}\">\n";
				$output .= walk_page_dropdown_tree($pages[$type], $depth, $r);
				$output .= "</optgroup>\n";
			}
		}	
		else {
			$output .= walk_page_dropdown_tree($pages[$post_type[0]], $depth, $r);
		}		
		$output .= "</select>\n";
	}

	$output = apply_filters('wp_dropdown_pages', $output);

	if ( $echo )
		echo $output;

	return $output;
}

function cpt_parent_dropdown($output) {
	global $post;
	
	static $called = false;
	if (is_admin() && strpos($output, "parent_id") !== false && $called === false) {
		$called = true;
		$output = cpt_dropdown_pages(array('post_type' => array("page",$post->post_type), 'exclude_tree' => $post->ID, 'selected' => $post->post_parent, 'name' => 'parent_id', 'show_option_none' => __('(no parent)'), 'sort_column'=> 'menu_order, post_title', 'echo' => 0));
	}
	return $output;
}
add_filter('wp_dropdown_pages', 'cpt_parent_dropdown');