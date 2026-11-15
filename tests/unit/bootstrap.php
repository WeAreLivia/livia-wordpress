<?php
/**
 * Unit tests: the plugin's pure rules, without WordPress.
 *
 * @package Livia
 */

define( 'ABSPATH', __DIR__ . '/' );

require dirname( __DIR__, 2 ) . '/vendor/autoload.php';

/**
 * WordPress's wrapper of parse_url(), which the rules use.
 *
 * @param string $url       Address.
 * @param int    $component Component, -1 for all.
 * @return mixed
 */
function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Stands in for WordPress here.
}
require dirname( __DIR__, 2 ) . '/includes/class-livia-rules.php';
