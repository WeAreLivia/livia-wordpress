<?php
/**
 * Integration tests: the plugin loaded in WordPress's test suite (wp-env sets WP_TESTS_DIR).
 *
 * @package Livia
 */

$livia_tests_dir = getenv( 'WP_TESTS_DIR' ) ? getenv( 'WP_TESTS_DIR' ) : '/wordpress-phpunit';

define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__, 2 ) . '/vendor/yoast/phpunit-polyfills' );

require_once $livia_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () {
		require dirname( __DIR__, 2 ) . '/livia.php';
		// Elementor, installed beside the plugin by wp-env, is loaded on demand: LIVIA_TEST_ELEMENTOR=1.
		if ( getenv( 'LIVIA_TEST_ELEMENTOR' ) ) {
			require dirname( __DIR__, 3 ) . '/elementor/elementor.php';
		}
	}
);

require $livia_tests_dir . '/includes/bootstrap.php';
require __DIR__ . '/class-livia-testcase.php';
