<?php
/**
 * Plugin Name:       HappyFiles Pro
 * Plugin URI:        https://happyfiles.io
 * Description:       Organize your WordPress media files in folders/categories via drag and drop.
 * Version:           1.5
 * Author:            Codeer
 * Author URI:        https://codeer.io
 * Text Domain:       happyfiles
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// First: Try to deactivate free version of HappyFiles (if active)
register_activation_hook( __FILE__, function() {
	if ( is_plugin_active( 'happyfiles/happyfiles.php' ) ) {
		add_action( 'update_option_active_plugins', function() {
			deactivate_plugins( 'happyfiles/happyfiles.php' );
		} );
	 }
} );

if ( ! defined( 'HAPPYFILES_VERSION' ) ) {
	define( 'HAPPYFILES_VERSION', '1.5' );
	define( 'HAPPYFILES_FILE', __FILE__ );
	define( 'HAPPYFILES_PATH', plugin_dir_path( __FILE__ ) );
	define( 'HAPPYFILES_URL', plugin_dir_url( __FILE__ ) );
	define( 'HAPPYFILES_BASENAME', plugin_basename( __FILE__ ) );
	define( 'HAPPYFILES_ASSETS_URL', plugin_dir_url( __FILE__ ) . 'assets' );
	define( 'HAPPYFILES_ASSETS_PATH', plugin_dir_path( __FILE__ ) . 'assets' );
	define( 'HAPPYFILES_TAXONOMY', 'happyfiles_category' );
	define( 'HAPPYFILES_POSITION', 'happyfiles_position' );
	define( 'HAPPYFILES_DB_OPTION_WIDTH', 'happyfiles_width' );
	define( 'HAPPYFILES_SETTINGS_GROUP', 'happyfiles_settings' );
	define( 'HAPPYFILES_SETTING_USER_ROLES', 'happyfiles_user_roles' );
	define( 'HAPPYFILES_SETTING_MULTIPLE_CATEGORIES', 'happyfiles_multiple_categories' );
	define( 'HAPPYFILES_SETTING_REMOVE_FROM_ALL_CATEGORIES', 'happyfiles_remove_from_all_categories' );
	define( 'HAPPYFILES_SETTING_LIST_VIEW_DISABLE_AJAX', 'happyfiles_list_view_disable_ajax' );
	define( 'HAPPYFILES_SETTING_ALTERNATIVE_COUNT', 'happyfiles_alternative_count' );
}

require_once HAPPYFILES_PATH . 'includes/init.php';

// To avoid fatal error on plugin activation when free version of HappyFiles is still active
if ( file_exists( HAPPYFILES_PATH . 'includes/pro.php' ) ) {
	require_once HAPPYFILES_PATH . 'includes/pro.php';
}
