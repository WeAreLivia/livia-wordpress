<?php
/**
 * Plugin Name:       Livia
 * Plugin URI:        https://www.wearelivia.com
 * Description:       Publishes your restaurant's Livia group booking page on your site, with its booking assistant, and places Livia widgets on your pages.
 * Version:           1.1.0
 * Requires at least: 6.5
 * Tested up to:      7.1
 * Requires PHP:      7.4
 * Author:            Livia
 * Author URI:        https://www.wearelivia.com
 * License:           Proprietary
 * Text Domain:       livia
 * Domain Path:       /languages
 * Update URI:        https://chat.booking.wearelivia.com/wordpress/v1/update
 *
 * @package Livia
 */

defined( 'ABSPATH' ) || exit;

define( 'LIVIA_VERSION', '1.1.0' );
define( 'LIVIA_FILE', __FILE__ );
define( 'LIVIA_DIR', plugin_dir_path( __FILE__ ) );
define( 'LIVIA_URL', plugin_dir_url( __FILE__ ) );
define( 'LIVIA_BASENAME', plugin_basename( __FILE__ ) );

// The Livia service the plugin calls server to server; a site overrides it in wp-config.php.
if ( ! defined( 'LIVIA_SERVICE_URL' ) ) {
	define( 'LIVIA_SERVICE_URL', 'https://chat.booking.wearelivia.com' );
}
// The same service as the visitor's browser reaches it (scripts, widget, previews), when it differs from the server's.
if ( ! defined( 'LIVIA_PUBLIC_URL' ) ) {
	define( 'LIVIA_PUBLIC_URL', LIVIA_SERVICE_URL );
}

require_once LIVIA_DIR . 'includes/class-livia-rules.php';
require_once LIVIA_DIR . 'includes/class-livia-service.php';
require_once LIVIA_DIR . 'includes/class-livia-group-page.php';
require_once LIVIA_DIR . 'includes/class-livia-widgets.php';
require_once LIVIA_DIR . 'includes/class-livia-experience.php';
require_once LIVIA_DIR . 'includes/class-livia-cache.php';
require_once LIVIA_DIR . 'includes/class-livia-elementor.php';
require_once LIVIA_DIR . 'includes/class-livia-updater.php';
require_once LIVIA_DIR . 'includes/class-livia-admin.php';

register_activation_hook( __FILE__, array( 'Livia_Group_Page', 'on_activation' ) );
register_deactivation_hook( __FILE__, array( 'Livia_Group_Page', 'on_deactivation' ) );
register_deactivation_hook( __FILE__, array( 'Livia_Updater', 'forget' ) );

add_action(
	'init',
	static function () {
		load_plugin_textdomain( 'livia', false, dirname( LIVIA_BASENAME ) . '/languages' );
	}
);
// WordPress prefers a livia-<locale> file in WP_LANG_DIR/plugins, which a third-party language pack sets: ours always win.
add_filter(
	'load_textdomain_mofile',
	static function ( $mofile, $domain ) {
		return 'livia' === $domain ? LIVIA_DIR . 'languages/' . basename( $mofile ) : $mofile;
	},
	10,
	2
);

Livia_Group_Page::init();
Livia_Widgets::init();
Livia_Experience::init();
Livia_Cache::init();
Livia_Elementor::init();
Livia_Updater::init();
Livia_Admin::init();
