<?php
/**
 * Updates through the `update_plugins_{$hostname}` filter and the "View details" window.
 *
 * @package Livia
 */

// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Signatures and keys travel in base64.

final class UpdaterTest extends Livia_TestCase {

	/**
	 * The filter WordPress calls for the host of the plugin's `Update URI`.
	 */
	const HOOK = 'update_plugins_' . Livia_Updater::UPDATE_HOST;

	private function update_answer( $package_host ) {
		return array(
			200,
			array(
				'version'      => '9.9.9',
				'package'      => $package_host . '/wordpress/v1/download?key=' . self::KEY . '&version=9.9.9',
				'requires'     => '6.5',
				'tested'       => '7.1',
				'requires_php' => '7.4',
				'icons'        => array( '1x' => LIVIA_SERVICE_URL . '/wordpress/v1/assets/icon-128x128.png' ),
				'sections'     => array( 'changelog' => '<p>Nouveautés [gallery ids=1]</p><script>alert(1)</script>' ),
				'last_updated' => '2026-11-13',
				'signature'    => base64_encode( str_repeat( 's', 64 ) ),
			),
		);
	}

	private function check() {
		return apply_filters( self::HOOK, false, array( 'UpdateURI' => 'https://chat.booking.wearelivia.com/wordpress/v1/update' ), LIVIA_BASENAME, array() );
	}

	public function test_the_filter_offers_the_version_livia_publishes() {
		$this->connect();
		$this->answers['/wordpress/v1/update'] = $this->update_answer( LIVIA_SERVICE_URL );
		$offer                                 = $this->check();
		$this->assertSame( '9.9.9', $offer['new_version'] );
		$this->assertSame( LIVIA_BASENAME, $offer['plugin'] );
		$this->assertSame( Livia_Updater::PACKAGE, $offer['package'], 'only the plugin resolves the package, see test_without_the_plugin_loaded_wordpress_finds_no_package_to_install()' );
		$this->assertStringContainsString( 'key=' . self::KEY, $this->calls[0] );

		$this->check();
		$this->assertCount( 1, $this->calls, 'the answer is cached' );
	}

	public function test_the_offer_is_tested_with_the_version_of_the_site_within_the_declared_minor() {
		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited -- get_bloginfo( 'version' ) reads this global and has no filter.
		global $wp_version;
		$saved      = $wp_version;
		$wp_version = '7.1.5-RC1';
		try {
			$this->connect();
			$this->answers['/wordpress/v1/update'] = $this->update_answer( LIVIA_SERVICE_URL );
			$this->assertSame( '7.1.5', $this->check()['tested'] );
		} finally {
			$wp_version = $saved;
		}
		// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited
	}

	public function test_the_offer_is_tested_with_the_pending_core_update_within_the_declared_minor() {
		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited -- get_bloginfo( 'version' ) reads this global and has no filter.
		global $wp_version;
		$saved      = $wp_version;
		$wp_version = '7.1.3';
		try {
			$this->connect();
			$this->answers['/wordpress/v1/update'] = $this->update_answer( LIVIA_SERVICE_URL );
			delete_site_transient( 'update_core' );
			$this->assertSame( '7.1.3', $this->check()['tested'], 'no core update pending' );
			$pending = static function ( $version ) {
				return (object) array(
					'updates' => array(
						(object) array(
							'response' => 'upgrade',
							'current'  => $version,
						),
					),
				);
			};
			set_site_transient( 'update_core', $pending( '7.1.4' ) );
			$this->assertSame( '7.1.4', $this->check()['tested'] );
			set_site_transient( 'update_core', $pending( '7.2.0' ) );
			$this->assertSame( '7.1.3', $this->check()['tested'] );
		} finally {
			$wp_version = $saved;
			delete_site_transient( 'update_core' );
		}
		// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited
	}

	public function test_other_plugins_are_left_alone() {
		$this->connect();
		$this->answers['/wordpress/v1/update'] = $this->update_answer( LIVIA_SERVICE_URL );
		$this->assertFalse( apply_filters( self::HOOK, false, array(), 'other/other.php', array() ) );
	}

	public function test_no_key_no_call_and_a_foreign_package_is_refused() {
		$this->assertFalse( $this->check() );
		$this->assertCount( 0, $this->calls );

		delete_transient( Livia_Updater::TRANSIENT );
		$this->connect();
		$this->answers['/wordpress/v1/update'] = $this->update_answer( 'https://elsewhere.test' );
		$this->assertFalse( $this->check() );
	}

	public function test_the_cached_answer_is_checked_again() {
		$this->connect();
		$remote = $this->update_answer( 'https://elsewhere.test' )[1];
		set_transient( Livia_Updater::TRANSIENT, array( 'remote' => $remote ), HOUR_IN_SECONDS );
		$this->assertFalse( $this->check() );
		$this->assertCount( 0, $this->calls );
	}

	public function test_a_plugin_active_on_sub_sites_only_offers_its_update_to_the_network() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite only.' );
		}
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$empty = (object) array(
			'last_checked' => time(),
			'checked'      => array(),
			'response'     => array(),
		);
		set_site_transient( 'update_plugins', $empty );
		$this->connect();
		$this->answers['/wordpress/v1/update'] = $this->update_answer( LIVIA_SERVICE_URL );
		Livia_Updater::share_with_network();
		$this->assertSame( array(), get_site_transient( 'update_plugins' )->response, 'the main site computes it itself' );

		switch_to_blog( self::factory()->blog->create() );
		$this->connect();
		$calls = count( $this->calls );
		Livia_Updater::share_with_network();
		$this->assertCount( $calls, $this->calls, 'an admin page never waits for Livia' );
		$this->assertNotFalse( wp_next_scheduled( Livia_Updater::FETCH ), 'a scheduled task asks Livia instead' );
		$this->assertArrayNotHasKey( LIVIA_BASENAME, get_site_transient( 'update_plugins' )->response );
		do_action( Livia_Updater::FETCH );
		Livia_Updater::share_with_network();
		restore_current_blog();
		$offer = get_site_transient( 'update_plugins' )->response[ LIVIA_BASENAME ];
		$this->assertSame( '9.9.9', $offer->new_version );
		$this->assertSame( '', $offer->package, 'the network screens run without the plugin: nothing there would check the signature' );
	}

	public function test_a_sub_site_whose_cron_never_runs_asks_livia_itself_once_briefly() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite only.' );
		}
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		set_site_transient(
			'update_plugins',
			(object) array(
				'last_checked' => time(),
				'checked'      => array(),
				'response'     => array(),
			)
		);
		switch_to_blog( self::factory()->blog->create() );
		$this->connect();
		$this->answers['/wordpress/v1/update'] = $this->update_answer( LIVIA_SERVICE_URL );
		wp_schedule_single_event( time() - 30 * MINUTE_IN_SECONDS, Livia_Updater::FETCH );
		Livia_Updater::share_with_network();
		$this->assertCount( 0, $this->calls, 'a task late by less than an hour may still run' );

		wp_clear_scheduled_hook( Livia_Updater::FETCH );
		wp_schedule_single_event( time() - HOUR_IN_SECONDS - 60, Livia_Updater::FETCH );
		Livia_Updater::share_with_network();
		$this->assertCount( 1, $this->calls );
		$this->assertSame( 3, $this->args[0]['timeout'] );
		$this->assertFalse( wp_next_scheduled( Livia_Updater::FETCH ), 'the late task is removed' );
		Livia_Updater::share_with_network();
		$this->assertCount( 1, $this->calls, 'the answer is cached' );
		restore_current_blog();
		$this->assertSame( '9.9.9', get_site_transient( 'update_plugins' )->response[ LIVIA_BASENAME ]->new_version );
	}

	public function test_a_failed_late_ask_is_cached_an_hour() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite only.' );
		}
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		switch_to_blog( self::factory()->blog->create() );
		$this->connect();
		wp_schedule_single_event( time() - HOUR_IN_SECONDS - 60, Livia_Updater::FETCH );
		Livia_Updater::share_with_network();
		$this->assertCount( 1, $this->calls, 'Livia does not answer' );
		$this->assertSame( array( 'remote' => null ), get_transient( Livia_Updater::TRANSIENT ) );
		Livia_Updater::share_with_network();
		$this->assertCount( 1, $this->calls );
		restore_current_blog();
	}

	public function test_an_offer_without_a_signature_is_ignored() {
		$this->connect();
		$answer = $this->update_answer( LIVIA_SERVICE_URL );
		unset( $answer[1]['signature'] );
		$this->answers['/wordpress/v1/update'] = $answer;
		$this->assertFalse( $this->check() );
	}

	/**
	 * The bytes of a release zip whose main file carries a version.
	 *
	 * @param string $version Its `Version` header.
	 * @param string $entry   Path of the main file in the zip.
	 * @return string
	 */
	private function release_zip( $version, $entry = 'livia/livia.php' ) {
		$path = wp_tempnam( 'livia-zip' );
		$zip  = new ZipArchive();
		$zip->open( $path, ZipArchive::OVERWRITE );
		$zip->addFromString( $entry, "<?php\n/**\n * Plugin Name: Livia\n * Version:     $version\n */\n" );
		$zip->close();
		$bytes = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		wp_delete_file( $path );
		return $bytes;
	}

	/**
	 * An upgrader whose skin keeps the download's feedback.
	 *
	 * @return object
	 */
	private function upgrader() {
		return new class() {
			public $skin;

			public function __construct() {
				$this->skin = new class() {
					public $said = array();

					public function feedback( ...$args ) {
						$this->said[] = $args;
					}
				};
			}
		};
	}

	/**
	 * Serves the package's download with the bytes of `$served`, read at each request, and lists the files written.
	 *
	 * @param string $served   Bytes served.
	 * @param array  $received Files written.
	 */
	private function serve_package( &$served, &$received ) {
		$this->answers['/wordpress/v1/download'] = array( 200, '' );
		add_filter(
			'pre_http_request',
			static function ( $preempt, $args, $url ) use ( &$served, &$received ) {
				if ( false !== strpos( $url, '/wordpress/v1/download' ) ) {
					$received[] = $args['filename'];
					file_put_contents( $args['filename'], $served ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				}
				return $preempt;
			},
			5,
			3
		);
	}

	public function test_the_signing_key_is_well_formed_and_other_packages_are_left_to_wordpress() {
		$this->connect();
		$this->answers['/wordpress/v1/update'] = $this->update_answer( LIVIA_SERVICE_URL );
		$package                               = $this->check()['package'];
		$upgrader                              = new stdClass();

		$this->assertSame( 32, strlen( (string) base64_decode( Livia_Updater::SIGNING_KEY, true ) ), 'an Ed25519 public key in base64' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		$this->assertFalse( apply_filters( 'upgrader_pre_download', false, $package, $upgrader, array( 'plugin' => 'other/other.php' ) ), 'another plugin is left to WordPress' );
		$this->assertFalse( apply_filters( 'upgrader_pre_download', false, $package, $upgrader, array( 'theme' => 'livia' ) ) );
	}

	public function test_a_file_an_earlier_filter_supplied_is_checked_too() {
		$this->connect();
		$this->answers['/wordpress/v1/update'] = $this->update_answer( LIVIA_SERVICE_URL );
		$package                               = $this->check()['package'];
		$supplied                              = wp_tempnam( 'livia-supplied' );
		file_put_contents( $supplied, $this->release_zip( '9.9.9' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		$refused = apply_filters( 'upgrader_pre_download', $supplied, $package, $this->upgrader(), array( 'plugin' => LIVIA_BASENAME ) );
		$this->assertWPError( $refused, 'the offer\'s signature is no signature of this file, whatever the key' );
		$this->assertFileDoesNotExist( $supplied, 'the refused file is deleted' );

		$error = new WP_Error( 'earlier', 'Earlier error' );
		$this->assertSame( $error, apply_filters( 'upgrader_pre_download', $error, $package, $this->upgrader(), array( 'plugin' => LIVIA_BASENAME ) ), 'an error stays an error' );
		$this->assertSame( PHP_INT_MAX, has_filter( 'upgrader_pre_download', array( 'Livia_Updater', 'download' ) ), 'no later filter replaces the checked file' );
	}

	public function test_a_package_installs_only_with_the_signature_of_the_key() {
		$pair    = sodium_crypto_sign_keypair();
		$key     = base64_encode( sodium_crypto_sign_publickey( $pair ) );
		$zip     = 'PK livia-9.9.9.zip';
		$signed  = base64_encode( sodium_crypto_sign_detached( $zip, sodium_crypto_sign_secretkey( $pair ) ) );
		$another = base64_encode( sodium_crypto_sign_publickey( sodium_crypto_sign_keypair() ) );

		$this->assertTrue( Livia_Rules::is_signed( $zip, $signed . "\n", $key ) );
		$this->assertFalse( Livia_Rules::is_signed( $zip . ' ', $signed, $key ), 'another zip' );
		$this->assertFalse( Livia_Rules::is_signed( $zip, $signed, $another ), 'another key' );
		$this->assertFalse( Livia_Rules::is_signed( $zip, $signed, '' ), 'no key' );
		$this->assertFalse( Livia_Rules::is_signed( $zip, '', $key ), 'no signature' );
		$this->assertFalse( Livia_Rules::is_signed( $zip, 'not base64!', $key ) );
	}

	/**
	 * Livia's offer of a version whose package is signed by a new key.
	 *
	 * @param string $version Version announced.
	 * @param string $zip     Bytes signed.
	 * @return array{key: string, package: string}
	 */
	private function signed_offer( $version, $zip ) {
		$this->connect();
		$pair   = sodium_crypto_sign_keypair();
		$answer = $this->update_answer( LIVIA_SERVICE_URL );

		$answer[1]['version']                  = $version;
		$answer[1]['signature']                = base64_encode( sodium_crypto_sign_detached( $zip, sodium_crypto_sign_secretkey( $pair ) ) );
		$this->answers['/wordpress/v1/update'] = $answer;
		return array(
			'key'     => base64_encode( sodium_crypto_sign_publickey( $pair ) ),
			'package' => $this->check()['package'],
		);
	}

	public function test_the_download_keeps_only_the_zip_the_key_signed() {
		$zip      = $this->release_zip( '9.9.9' );
		$offer    = $this->signed_offer( '9.9.9', $zip );
		$served   = $zip . ' tampered';
		$received = array();
		$this->serve_package( $served, $received );

		$upgrader = $this->upgrader();
		$refused  = Livia_Updater::signed_download( $offer['package'], $upgrader, $offer['key'] );
		$this->assertWPError( $refused );
		$this->assertSame( 'livia_bad_signature', $refused->get_error_code() );
		$said = wp_json_encode( $upgrader->skin->said );
		$this->assertStringNotContainsString( self::KEY, $said, 'the restaurant\'s key is never shown' );
		$this->assertStringNotContainsString( 'key=', $said );
		$this->assertStringContainsString( wp_parse_url( LIVIA_SERVICE_URL, PHP_URL_HOST ), $said );
		$this->assertFileDoesNotExist( $received[0], 'the refused file is deleted' );

		$served = $zip;
		$file   = Livia_Updater::signed_download( $offer['package'], $this->upgrader(), $offer['key'] );
		$this->assertSame( $zip, file_get_contents( $file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		wp_delete_file( $file );

		$supplied = wp_tempnam( 'livia-supplied' );
		file_put_contents( $supplied, $zip . ' tampered' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$this->assertSame( 'livia_bad_signature', Livia_Updater::signed_download( $offer['package'], $this->upgrader(), $offer['key'], $supplied )->get_error_code(), 'a file an earlier filter supplied' );
		$this->assertFileDoesNotExist( $supplied );
		file_put_contents( $supplied, $zip ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$this->assertSame( $supplied, Livia_Updater::signed_download( $offer['package'], $this->upgrader(), $offer['key'], $supplied ) );
		$this->assertCount( 2, $received, 'a supplied file is never downloaded again' );
		wp_delete_file( $supplied );
	}

	public function test_a_signed_package_of_another_version_is_refused() {
		$zip      = $this->release_zip( '9.9.8' );
		$offer    = $this->signed_offer( '9.9.9', $zip );
		$served   = $zip;
		$received = array();
		$this->serve_package( $served, $received );

		$refused = Livia_Updater::signed_download( $offer['package'], $this->upgrader(), $offer['key'] );
		$this->assertWPError( $refused );
		$this->assertSame( 'livia_wrong_version', $refused->get_error_code() );
		$this->assertFileDoesNotExist( $received[0] );
	}

	public function test_a_signed_package_without_the_plugin_s_main_file_is_refused() {
		$zip      = $this->release_zip( '9.9.9', 'other/livia.php' );
		$offer    = $this->signed_offer( '9.9.9', $zip );
		$served   = $zip;
		$received = array();
		$this->serve_package( $served, $received );

		$this->assertSame( 'livia_wrong_version', Livia_Updater::signed_download( $offer['package'], $this->upgrader(), $offer['key'] )->get_error_code() );
		$this->assertFileDoesNotExist( $received[0] );
	}

	public function test_a_signed_file_that_is_not_a_zip_is_refused_cleanly() {
		$served   = 'PK' . str_repeat( 'x', 64 );
		$offer    = $this->signed_offer( '9.9.9', $served );
		$received = array();
		$this->serve_package( $served, $received );

		$this->assertSame( 'livia_wrong_version', Livia_Updater::signed_download( $offer['package'], $this->upgrader(), $offer['key'] )->get_error_code() );
		$this->assertFileDoesNotExist( $received[0] );
	}

	public function test_a_signed_package_no_newer_than_the_installed_version_is_refused() {
		$zip      = $this->release_zip( LIVIA_VERSION );
		$offer    = $this->signed_offer( LIVIA_VERSION, $zip );
		$served   = $zip;
		$received = array();
		$this->serve_package( $served, $received );

		$refused = Livia_Updater::signed_download( $offer['package'], $this->upgrader(), $offer['key'] );
		$this->assertWPError( $refused );
		$this->assertSame( 'livia_not_newer', $refused->get_error_code() );
		$this->assertFileDoesNotExist( $received[0] );
	}

	public function test_without_the_plugin_loaded_wordpress_finds_no_package_to_install() {
		$this->connect();
		$this->answers['/wordpress/v1/update'] = $this->update_answer( LIVIA_SERVICE_URL );
		$package                               = $this->check()['package'];
		$served                                = 'PK unsigned';
		$received                              = array();
		$this->serve_package( $served, $received );
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		$upgrader = new WP_Upgrader( new Automatic_Upgrader_Skin() );
		$upgrader->init();
		$this->assertSame( 'livia_bad_signature', $upgrader->download_package( $package, false, array( 'plugin' => LIVIA_BASENAME ) )->get_error_code(), 'the plugin, loaded, answers for WordPress' );
		$received = array();

		// WP-CLI with --skip-plugins, a network's main site, the recovery mode: WordPress updates the plugin without it.
		remove_filter( 'upgrader_pre_download', array( 'Livia_Updater', 'download' ), PHP_INT_MAX );
		// WordPress installs as is a package naming an existing file: none can be written at that path.
		@file_put_contents( $package, $served ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- The write must fail.
		try {
			$this->assertWPError( $upgrader->download_package( $package, false, array( 'plugin' => LIVIA_BASENAME ) ) );
		} finally {
			if ( is_file( $package ) ) {
				unlink( $package ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- The file this test wrote.
			}
		}
		$this->assertSame( array(), $received, 'nothing downloaded' );
		$this->assertFalse( wp_http_validate_url( $package ), 'no address WordPress could fetch by itself' );
	}

	public function test_a_translated_refusal_reaches_the_update_screen_as_text() {
		add_filter(
			'gettext_livia',
			static function ( $translation ) {
				return '<b>' . $translation . " l'extension > Plugins</b>";
			}
		);
		$refusals = array( Livia_Updater::signed_download( 'any', $this->upgrader(), 'any' ) );
		$served   = '';
		$received = array();
		$this->serve_package( $served, $received );
		// Zip version, version offered, bytes added after signing.
		foreach ( array( array( '9.9.9', '9.9.9', ' tampered' ), array( '9.9.8', '9.9.9', '' ), array( LIVIA_VERSION, LIVIA_VERSION, '' ) ) as $case ) {
			delete_transient( Livia_Updater::TRANSIENT );
			$zip        = $this->release_zip( $case[0] );
			$offer      = $this->signed_offer( $case[1], $zip );
			$served     = $zip . $case[2];
			$refusals[] = Livia_Updater::signed_download( $offer['package'], $this->upgrader(), $offer['key'] );
		}

		$codes = array();
		foreach ( $refusals as $refusal ) {
			$this->assertWPError( $refusal );
			$codes[] = $refusal->get_error_code();
			$this->assertStringNotContainsString( '<', $refusal->get_error_message(), 'WordPress prints it as HTML' );
			$this->assertStringEndsWith( " l'extension > Plugins", $refusal->get_error_message(), 'WP-CLI and screen readers read it as text' );
		}
		$codes = array_values( array_unique( $codes ) );
		sort( $codes );
		$this->assertSame( array( 'livia_bad_signature', 'livia_not_newer', 'livia_unsigned', 'livia_wrong_version' ), $codes );
	}

	public function test_the_update_route_waits_longer_than_the_others() {
		$this->connect();
		$this->answers['/wordpress/v1/update'] = $this->update_answer( LIVIA_SERVICE_URL );
		$this->check();
		$this->assertSame( 15, $this->args[0]['timeout'] );

		Livia_Service::call( '/wordpress/v1/widgets', array( 'key' => self::KEY ) );
		$this->assertSame( 3, $this->args[1]['timeout'] );
	}

	public function test_deactivation_takes_the_plugin_out_of_the_update_list() {
		set_site_transient(
			'update_plugins',
			(object) array(
				'last_checked' => time(),
				'response'     => array(
					LIVIA_BASENAME    => (object) array( 'new_version' => '9.9.9' ),
					'other/other.php' => (object) array( 'new_version' => '2.0.0' ),
				),
			)
		);
		wp_schedule_single_event( time(), Livia_Updater::FETCH );
		do_action( 'deactivate_' . LIVIA_BASENAME, is_multisite() );
		$response = get_site_transient( 'update_plugins' )->response;
		$this->assertArrayNotHasKey( LIVIA_BASENAME, $response );
		$this->assertArrayHasKey( 'other/other.php', $response );
		$this->assertFalse( wp_next_scheduled( Livia_Updater::FETCH ) );
	}

	public function test_the_details_window() {
		$this->connect();
		$this->answers['/wordpress/v1/update'] = $this->update_answer( LIVIA_SERVICE_URL );
		$details                               = apply_filters( 'plugins_api', false, 'plugin_information', (object) array( 'slug' => 'livia' ) );
		$this->assertSame( 'Livia', $details->name );
		$this->assertSame( '9.9.9', $details->version );
		$this->assertStringNotContainsString( '<script>', $details->sections['changelog'] );
		$this->assertStringNotContainsString( '[gallery', $details->sections['changelog'] );
		$this->assertArrayHasKey( '1x', $details->icons );
		$this->assertSame( Livia_Updater::PACKAGE, $details->download_link );
		$this->assertFalse( apply_filters( 'plugins_api', false, 'plugin_information', (object) array( 'slug' => 'akismet' ) ) );
	}

	public function test_the_first_key_livia_accepts_asks_for_the_update() {
		$this->connect();
		$this->connect_second();
		$connections                = get_option( 'livia_connections' );
		$connections['r1']['state'] = 'unknown_key';
		update_option( 'livia_connections', $connections );
		$this->answers['/wordpress/v1/update'] = $this->update_answer( LIVIA_SERVICE_URL );

		$this->assertSame( '9.9.9', $this->check()['new_version'] );
		$this->assertStringContainsString( 'key=' . self::KEY2, $this->calls[0] );
	}
}
