<?php
/**
 * Updates through WordPress's standard mechanism, from the Livia update service.
 *
 * @package Livia
 */

defined( 'ABSPATH' ) || exit;

/**
 * Offers the plugin's new versions to WordPress.
 */
final class Livia_Updater {

	/**
	 * Host of the plugin's `Update URI` header, which names the filter WordPress calls.
	 */
	const UPDATE_HOST = 'chat.booking.wearelivia.com';

	const TRANSIENT = 'livia_update';

	/**
	 * The scheduled task that asks Livia for its latest version off any page load.
	 */
	const FETCH = 'livia_fetch_update';

	/**
	 * Ed25519 public key of the releases, base64: a package installs only with its signature by the matching private
	 * key.
	 */
	const SIGNING_KEY = 'yxIp/wef3E3FvHPD/9RpXeZqCxQRks4xxpT98WtK32I=';

	/**
	 * The package WordPress's update list names: not an address, and a path under /dev/null, which no file can have,
	 * so that WordPress installs nothing without download(), which resolves it to Livia's package and checks its
	 * signature. The real address stays out of that list, which WP-CLI with --skip-plugins, a network's main site and
	 * the recovery mode read without the plugin.
	 */
	const PACKAGE = '/dev/null/livia-signed-package';

	/**
	 * Hooks the update filters.
	 */
	public static function init() {
		add_filter( 'update_plugins_' . self::UPDATE_HOST, array( __CLASS__, 'offer' ), 10, 3 );
		add_filter( 'plugins_api', array( __CLASS__, 'details' ), 10, 3 );
		// At the highest priority, so that only a filter added later at that same priority can replace the file checked.
		add_filter( 'upgrader_pre_download', array( __CLASS__, 'download' ), PHP_INT_MAX, 4 );
		add_action( 'admin_init', array( __CLASS__, 'share_with_network' ) );
		add_action( self::FETCH, array( __CLASS__, 'latest' ) );
	}

	/**
	 * Downloads the plugin's package for WordPress's upgrader and lets it through only when its signature matches
	 * SIGNING_KEY; a file an earlier filter already supplied is checked the same way. Other packages are left to
	 * WordPress.
	 *
	 * @param false|string|WP_Error $reply      Result of an earlier filter, false for none.
	 * @param string                $package    Package address.
	 * @param WP_Upgrader           $upgrader   Upgrader.
	 * @param array                 $hook_extra What is being updated.
	 * @return false|string|WP_Error The checked file, or why it is refused.
	 */
	public static function download( $reply, $package, $upgrader, $hook_extra = array() ) {
		if ( is_wp_error( $reply ) || ! isset( $hook_extra['plugin'] ) || LIVIA_BASENAME !== $hook_extra['plugin'] ) {
			return $reply;
		}
		return self::signed_download( $package, $upgrader, self::SIGNING_KEY, $reply );
	}

	/**
	 * Downloads the offered package, or takes the file an earlier filter supplied, and returns it only when its
	 * signature verifies against the key and it carries the version Livia announces, newer than the installed one.
	 *
	 * @param string       $package  Package WordPress names, see PACKAGE.
	 * @param WP_Upgrader  $upgrader Upgrader.
	 * @param string       $key      Public key, base64.
	 * @param false|string $file     The file an earlier filter supplied, false to download it.
	 * @return string|WP_Error The checked file, or why it is refused; a refused file is deleted.
	 */
	public static function signed_download( $package, $upgrader, $key, $file = false ) {
		$offer = self::latest();
		if ( ! $offer || self::PACKAGE !== $package ) {
			self::discard( $file );
			return new WP_Error( 'livia_unsigned', wp_strip_all_tags( __( 'This update is no longer the one Livia offers. Check for updates again, then retry.', 'livia' ) ) );
		}
		if ( false === $file ) {
			// The host only: the package's address carries the restaurant's key, which the screen and WP-CLI's log would show.
			$upgrader->skin->feedback( 'downloading_package', esc_html( (string) wp_parse_url( $offer['package'], PHP_URL_HOST ) ) );
			$file = download_url( $offer['package'] );
			if ( is_wp_error( $file ) ) {
				return $file;
			}
		}
		$error = self::refusal( $file, $offer, $key );
		if ( $error ) {
			self::discard( $file );
			return $error;
		}
		return $file;
	}

	/**
	 * Why a package must not install, if it must not: the signature covers the zip's bytes, the version it carries
	 * must be the one announced and newer than the installed one, so that a signed older release is never reinstalled.
	 *
	 * @param mixed  $file  Local path of the package.
	 * @param array  $offer See latest().
	 * @param string $key   Public key, base64.
	 * @return WP_Error|null
	 */
	private static function refusal( $file, $offer, $key ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A local file.
		if ( ! is_string( $file ) || ! is_file( $file ) || ! Livia_Rules::is_signed( (string) file_get_contents( $file ), $offer['signature'], $key ) ) {
			return new WP_Error( 'livia_bad_signature', wp_strip_all_tags( __( 'The downloaded update does not carry the Livia signature: it was not installed. Try again later, or download the new version from your Livia space.', 'livia' ) ) );
		}
		require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';
		// The release zip holds the plugin in a top-level livia/ folder, see bin/build-zip.sh.
		$entries = ( new PclZip( $file ) )->extract( PCLZIP_OPT_BY_NAME, 'livia/livia.php', PCLZIP_OPT_EXTRACT_AS_STRING );
		$version = Livia_Rules::plugin_version( is_array( $entries ) && isset( $entries[0]['content'] ) ? $entries[0]['content'] : '' );
		if ( $version !== $offer['new_version'] ) {
			return new WP_Error( 'livia_wrong_version', wp_strip_all_tags( __( 'The downloaded update is not the version Livia announces: it was not installed. Check for updates again, then retry.', 'livia' ) ) );
		}
		if ( version_compare( $version, LIVIA_VERSION, '<=' ) ) {
			return new WP_Error( 'livia_not_newer', wp_strip_all_tags( __( 'The downloaded update is not newer than the installed version of the Livia plugin: it was not installed.', 'livia' ) ) );
		}
		return null;
	}

	/**
	 * Deletes a package file that will not be installed, as WordPress's upgrader deletes the ones it installs.
	 *
	 * @param mixed $file Local path, or anything else, left alone.
	 */
	private static function discard( $file ) {
		if ( is_string( $file ) && is_file( $file ) ) {
			wp_delete_file( $file );
		}
	}

	/**
	 * Takes the plugin out of WordPress's list of updates when it is deactivated: without the plugin, nothing would
	 * check the signature of the package that list offers.
	 */
	public static function forget() {
		wp_clear_scheduled_hook( self::FETCH );
		$updates = get_site_transient( 'update_plugins' );
		if ( is_object( $updates ) && isset( $updates->response[ LIVIA_BASENAME ] ) ) {
			unset( $updates->response[ LIVIA_BASENAME ] );
			set_site_transient( 'update_plugins', $updates );
		}
	}

	/**
	 * The latest version Livia publishes, Livia's answer cached and checked at each read; null when Livia offers none.
	 *
	 * @param int $timeout Seconds Livia is given to answer when nothing is cached.
	 * @return array|null See Livia_Rules::update_offer().
	 */
	public static function latest( $timeout = 15 ) {
		$cached = get_transient( self::TRANSIENT );
		if ( ! is_array( $cached ) || ! array_key_exists( 'remote', $cached ) ) {
			// The service may read the release, then its signature, from its source before it answers.
			$key    = Livia_Service::update_key();
			$answer = '' !== $key ? Livia_Service::call( '/wordpress/v1/update', array( 'key' => $key ), $timeout ) : null;
			$cached = array( 'remote' => $answer && 'ok' === $answer['status'] ? $answer['data'] : null );
			set_transient( self::TRANSIENT, $cached, $cached['remote'] ? 6 * HOUR_IN_SECONDS : HOUR_IN_SECONDS );
		}
		if ( ! is_array( $cached['remote'] ) ) {
			return null;
		}
		$wp_versions = array( get_bloginfo( 'version' ) );
		// The transient get_core_updates() reads: that function exists in the admin only, this one also runs from cron.
		$core = get_site_transient( 'update_core' );
		if ( isset( $core->updates ) && is_array( $core->updates ) ) {
			foreach ( $core->updates as $update ) {
				$wp_versions[] = isset( $update->current ) ? $update->current : '';
			}
		}
		return Livia_Rules::update_offer( $cached['remote'], LIVIA_BASENAME, Livia_Service::hosts(), Livia_Service::allows_http(), $wp_versions );
	}

	/**
	 * Puts the new version in the network's update list when the plugin is active on sub-sites only: WordPress
	 * computes that list on the main site, where the plugin then does not run. It reads Livia's cached answer only:
	 * without one, a scheduled task asks Livia, so that no admin page waits for the service beyond three seconds once
	 * when that task never runs.
	 */
	public static function share_with_network() {
		if ( ! is_multisite() || is_main_site() || wp_doing_ajax() || is_plugin_active_for_network( LIVIA_BASENAME )
			|| in_array( LIVIA_BASENAME, (array) get_blog_option( get_main_site_id(), 'active_plugins', array() ), true ) ) {
			return;
		}
		if ( ! is_array( get_transient( self::TRANSIENT ) ) ) {
			$due = wp_next_scheduled( self::FETCH );
			if ( false === $due ) {
				wp_schedule_single_event( time(), self::FETCH );
				return;
			}
			if ( $due > time() - HOUR_IN_SECONDS ) {
				return;
			}
			// A task an hour late means this site's WP-Cron does not run: this page asks Livia once, briefly.
			wp_clear_scheduled_hook( self::FETCH );
			self::latest( 3 );
		}
		$updates = get_site_transient( 'update_plugins' );
		$offer   = self::latest();
		if ( ! is_object( $updates ) || ! $offer || version_compare( $offer['new_version'], LIVIA_VERSION, '<=' ) ) {
			return;
		}
		$known = isset( $updates->response[ LIVIA_BASENAME ] ) ? (array) $updates->response[ LIVIA_BASENAME ] : array();
		// The version alone: sub-sites with different keys would otherwise take turns rewriting the list. Without its
		// package: the network screens run without the plugin, so nothing would check the signature there.
		if ( ( $known['new_version'] ?? '' ) !== $offer['new_version'] ) {
			$updates->response[ LIVIA_BASENAME ] = (object) array_merge( $offer, array( 'package' => '' ) );
			set_site_transient( 'update_plugins', $updates );
		}
	}

	/**
	 * Answers WordPress's update check for this plugin.
	 *
	 * @param array|false $update      Update found so far.
	 * @param array       $plugin_data Plugin headers.
	 * @param string      $plugin_file Plugin basename.
	 * @return array|false
	 */
	public static function offer( $update, $plugin_data, $plugin_file ) {
		if ( LIVIA_BASENAME !== $plugin_file ) {
			return $update;
		}
		$offer = self::latest();
		return $offer ? array_merge( $offer, array( 'package' => self::PACKAGE ) ) : $update;
	}

	/**
	 * Fills the "View details" window.
	 *
	 * @param false|object|array $result Result so far.
	 * @param string             $action Requested action.
	 * @param object             $args   Request arguments.
	 * @return false|object|array
	 */
	public static function details( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || ! isset( $args->slug ) || 'livia' !== $args->slug ) {
			return $result;
		}
		$offer = self::latest();
		if ( ! $offer ) {
			return $result;
		}
		return (object) array(
			'name'          => 'Livia',
			'slug'          => 'livia',
			'version'       => $offer['version'],
			'author'        => '<a href="https://www.wearelivia.com">Livia</a>',
			'homepage'      => 'https://www.wearelivia.com',
			'requires'      => $offer['requires'],
			'tested'        => $offer['tested'],
			'requires_php'  => $offer['requires_php'],
			'last_updated'  => $offer['last_updated'],
			'download_link' => self::PACKAGE,
			'icons'         => $offer['icons'],
			'sections'      => array(
				'description' => esc_html__( 'Publishes your restaurant\'s Livia group booking page on your site, with its booking assistant, and places Livia widgets on your pages.', 'livia' ),
				'changelog'   => wp_kses_post( Livia_Rules::without_shortcodes( $offer['changelog'] ) ),
			),
		);
	}
}
