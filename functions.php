<?php
/*
Plugin Name: Custom Post Type Front Page
Plugin URI: http://www.overlay.se
Description: Pick a custom post type as your front page 
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
add_filter('wp_dropdown_pages', 'startpage_dropdown');

function custom_page_redirect($redirect_url, $requested_url) {
	return (rtrim(array_shift(explode("?",$requested_url)), "/") != home_url());
}
add_filter("redirect_canonical", "custom_page_redirect", 10, 2);

add_filter('request', 'blog_art_request');  // parse_request function
 
function blog_art_request($q) {
	if (is_admin()) return $q;
	if (empty($q) && !isset($q['p']) && !isset($q['page_id']) && !isset($q['name']) && !isset($q['pagename']) && !isset($q['post_type'])) {
		$q['post_type'] = 'faq_page';
		$q['p'] = get_option("page_on_front");
	}	
	return $q;
}