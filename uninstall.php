<?php
/**
 * Uninstall: on every site of every network, since deleting the plugin removes its files for all of them, the group
 * pages stay as drafts, the menu links the plugin added and its settings and caches go.
 *
 * @package Livia
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Uninstall runs without the plugin loaded.
require_once __DIR__ . '/includes/class-livia-experience.php';

global $wpdb;

// A hundred site ids at a time, so that a large network is never loaded at once.
$livia_offset = 0;
do {
	$livia_sites   = is_multisite() ? get_sites(
		array(
			'fields' => 'ids',
			'number' => 100,
			'offset' => $livia_offset,
		)
	) : array( get_current_blog_id() );
	$livia_offset += 100;
	$livia_more    = is_multisite() && 100 === count( $livia_sites );

	foreach ( $livia_sites as $livia_site ) {
		if ( is_multisite() ) {
			switch_to_blog( $livia_site );
		}

		$livia_restaurants = get_option( 'livia_restaurants', array() );
		foreach ( is_array( $livia_restaurants ) ? $livia_restaurants : array() as $livia_restaurant ) {
			$livia_page = ! empty( $livia_restaurant['page_id'] ) ? get_post( (int) $livia_restaurant['page_id'] ) : null;
			if ( $livia_page && 'publish' === $livia_page->post_status ) {
				wp_update_post(
					array(
						'ID'          => $livia_page->ID,
						'post_status' => 'draft',
					)
				);
			}
		}

		Livia_Experience::remove_from_menus();

		foreach ( array( 'livia_settings', 'livia_restaurants', 'livia_connections', 'livia_republish', 'livia_republish_at' ) as $livia_option ) {
			delete_option( $livia_option );
		}
		wp_clear_scheduled_hook( 'livia_fetch_update' );
		delete_post_meta_by_key( '_livia_widget_mode' );
		delete_post_meta_by_key( '_livia_widget_id' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Transients and the last pages received, one per key, have no API to list them by prefix.
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_livia\\_%' OR option_name LIKE '\\_transient\\_timeout\\_livia\\_%' OR option_name LIKE 'livia\\_last\\_%'" );

		if ( is_multisite() ) {
			restore_current_blog();
		}
	}//end foreach
} while ( $livia_more );
