<?php
/*
Plugin Name: Ad-minister Plus
Version: 0.6
Plugin URI: http://labs.dagensskiva.com/plugins/ad-minister/
Author URI: http://labs.dagensskiva.com/
Description:  A management system for temporary static content (such as ads) on your WordPress website. Ad-minister->All Banners to administer.
Author: Henrik Melin, Kal Ström, Jan Durand

	USAGE:

	See the Help tab in Ad-minister->Help.

	LICENCE:

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
       
*/


require_once ( 'ad-minister-functions.php' );

// Theme action
add_action('ad-minister', administer_template_action, 10, 2);

// XML Export
add_action('rss2_head', 'administer_export');

// Enable translation
add_action('init', 'administer_translate'); 

// Add administration menu
function administer_menu() {
	$page_title = 'Ad-minister';
	$menu_title = 'Ad-minister';
	$capability = !($capability = get_option('administer_user_level')) ? 'manage_options' : $capability;
	$menu_slug = 'ad-minister';
	$function = 'administer_main';
	$icon_url = plugins_url( 'images/money_icon.png', __FILE__ );
	$position = '';
	add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $function, $icon_url );
	
	add_submenu_page( 'ad-minister', 'Ad-minister - All Banners', 'All Banners', $capability, 'ad-minister' );
	add_submenu_page( 'ad-minister', 'Ad-minister - New Banner', 'New Banner', $capability, 'ad-minister-banner', 'administer_page_banner' );
	add_submenu_page( 'ad-minister', 'Ad-minister - Positions/Widgets', 'Positions', $capability, 'ad-minister-positions', 'administer_page_positions' );
	add_submenu_page( 'ad-minister', 'Ad-minister - Settings', 'Settings', $capability, 'ad-minister-settings', 'administer_page_settings' );
	add_submenu_page( 'ad-minister', 'Ad-minister - Help', 'Help', $capability, 'ad-minister-help', 'administer_page_help' );
}
add_action( 'admin_menu', 'administer_menu' );

// Ajax functions
add_action('wp_ajax_administer_save_content', 'administer_save_content');
add_action('wp_ajax_administer_delete_content', 'administer_delete_content');
add_action('wp_ajax_administer_save_position', 'administer_save_position');
add_action('wp_ajax_administer_delete_position', 'administer_delete_position');

// Handle theme widgets
if (get_option('administer_make_widgets') == 'true') {
	add_action('sidebar_admin_page', 'administer_popuplate_widget_controls');
	add_action( 'widgets_init', 'administer_load_widgets' );
}

// Display Ad-minister widget on dashboard
if (get_option('administer_dashboard_show') == 'true') {
	add_action('wp_dashboard_setup', 'administer_register_widgets');
}
	
// Count the number of impressions the content makes
if (get_option('administer_statistics') == 'true' && !is_admin()) {
	add_action('init', 'administer_init_stats');
	add_action('shutdown', 'administer_save_stats');
}
add_action('init', 'administer_do_redirect', 11);

add_action('administer_stats', 'administer_template_stats');

function administer_enqueue_styles () {
	if (ereg('page\=ad\-minister', $_SERVER['REQUEST_URI'])) {
		// Enqueue Ad-minister style sheet 	
		wp_enqueue_style( 'ad-minister', plugins_url( 'css/ad-minister.css', __FILE__ ) );
	}
}
add_action( 'admin_enqueue_scripts', 'administer_enqueue_styles', 20 );

function administer_enqueue_scripts ( $hook ) {
	global $wpdb;
	$page = $_GET['page'] ? $_GET['page'] : 'ad-minister-content';
	
	// Auto install
	if (!get_option('administer_post_id') || !administer_ok_to_go()) {
		$_POST = array();
		
		// Does it exist already?
		$sql = "select count(*) from $wpdb->posts where post_type='administer'";
		$nbr = $wpdb->get_var($sql) + 1;

		// Create a new one		
		$_POST['post_title'] = 'Ad-minister Data Holder ' . $nbr;
		$_POST['post_type'] = 'administer';
		$_POST['content'] = 'This post holds your Ad-minister data';
		$id = wp_write_post();
		update_option('administer_post_id', $id);
	}
	
	$content = administer_get_content();
	$positions = get_post_meta(get_option('administer_post_id'), 'administer_positions', true);

	// Cannot show 'Banners' if there aren't any	
	if ($page == 'ad-minister' && (!is_array($content) || empty($content))) $page = 'ad-minister-banner';

	// Cannot create a new banner if there are no positions
	if ($page == 'ad-minister-banner' && (!is_array($positions) || empty($positions) ) ) $page = 'ad-minister-positions';

	// If we're not installed, go to the settings for the setup.
	if (!administer_ok_to_go() && $page != 'ad-minister-help') $page = 'ad-minister-settings';
	
	$_GET['page'] = $page;

	// Enqueue common functions javascript
	wp_register_script( 'ad-minister', plugins_url( 'js/ad-minister.js', __FILE__ ) );
	wp_enqueue_script( 'ad-minister' );	
	
	// Enqueue Flash Players
	wp_enqueue_script( 'flowplayer', '/../flowplayer/flowplayer-3.2.12.min.js' );
	wp_enqueue_script( 'swfobject', '/../swfobject/swfobject.js' );
	
	if ( $page == 'ad-minister-banner' ) {
		wp_enqueue_script('page');
		wp_enqueue_script('editor');
    wp_enqueue_script('thickbox');
		wp_enqueue_style('thickbox');
		wp_enqueue_script('media-upload');
		wp_enqueue_script('controls');
		
		// Enqueue jquery ui
		/*wp_enqueue_script( 'jquery-ui', get_stylesheet_directory_uri() . '/js/jquery-ui-1.8.24.custom.min.js', array( 'jquery' ) );*/
		wp_enqueue_script( 'jquery-ui', get_stylesheet_directory_uri() . '/js/jquery-ui-1.10.3.custom.min.js', array( 'jquery' ) );	
		
		// Enqueue style sheet for date picker fields
		wp_enqueue_style( 'ui-lightness', get_stylesheet_directory_uri() . '/css/ui-lightness-old/jquery-ui-1.8.24.custom.css' );
		/*wp_enqueue_style( 'ui-lightness', get_stylesheet_directory_uri() . '/css/ui-lightness/jquery-ui-1.10.3.custom.css' );*/
		
		// Enqueue jquery multiselect plugin
		wp_enqueue_script( 'jquery-multiselect', plugins_url('js/jquery.multiselect.min.js', __FILE__), array( 'jquery', 'jquery-ui' ) );
		wp_enqueue_style( 'jquery-multiselect', plugins_url('css/jquery.multiselect.css', __FILE__) );
		wp_enqueue_script( 'jquery-multiselect-filter', plugins_url('js/jquery.multiselect.filter.min.js', __FILE__), array( 'jquery-multiselect' ) );
		wp_enqueue_style( 'jquery-multiselect-filter', plugins_url('css/jquery.multiselect.filter.css', __FILE__) );
				
		// Enqueue script to use media uploader and provide form validation
		wp_enqueue_media();
		wp_enqueue_script('ad-minister-banner', plugins_url('js/ad-minister-banner.js', __FILE__), array('jquery', 'jquery-multiselect', 'media-upload', 'thickbox', 'editor'));
	}
	else if ( $page == 'ad-minister' ) {
		wp_enqueue_script('ad-minister-content', plugins_url( 'js/ad-minister-content.js', __FILE__ ), array('jquery'));
	}	
}
add_action( 'admin_enqueue_scripts', 'administer_enqueue_scripts', 20 ); 
?>