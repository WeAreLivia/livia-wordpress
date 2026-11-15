<?php
/**
 * The group page through publication, deactivation, reactivation and uninstall.
 *
 * @package Livia
 */

final class LifecycleTest extends Livia_TestCase {

	public function test_publish_creates_a_real_page_carrying_the_block_then_updates_it() {
		$this->connect();
		$first = Livia_Group_Page::publish( 'r1', 'reservation-groupe', 'Réservation de groupe' );
		$this->assertTrue( $first['created'] );
		$page = $first['page'];
		$this->assertSame( 'publish', $page->post_status );
		$this->assertSame( 'page', $page->post_type );
		$this->assertTrue( has_block( 'livia/group-page', $page ) );
		$this->assertSame( home_url( '/reservation-groupe/' ), get_permalink( $page ) );

		$second = Livia_Group_Page::publish( 'r1', 'groupes', 'Groupes' );
		$this->assertFalse( $second['created'] );
		$this->assertSame( $page->ID, $second['page']->ID );
		$this->assertSame( 'groupes', $second['page']->post_name );
		$this->assertSame( 'Groupes', $second['page']->post_title );
	}

	public function test_a_trashed_group_page_comes_back_on_save() {
		$this->connect();
		$page = Livia_Group_Page::publish( 'r1', 'reservation-groupe', 'Réservation de groupe' )['page'];
		wp_trash_post( $page->ID );
		$again = Livia_Group_Page::publish( 'r1', 'reservation-groupe', 'Réservation de groupe' );
		$this->assertSame( $page->ID, $again['page']->ID );
		$this->assertSame( 'publish', $again['page']->post_status );
		$this->assertSame( 'reservation-groupe', $again['page']->post_name );
	}

	public function test_deactivation_unpublishes_and_reactivation_republishes() {
		$this->connect();
		$page = Livia_Group_Page::publish( 'r1', 'reservation-groupe', 'Réservation de groupe' )['page'];

		Livia_Group_Page::on_deactivation();
		$this->assertSame( 'draft', get_post_status( $page->ID ) );
		$this->assertSame( 'reservation-groupe', get_post( $page->ID )->post_name );

		Livia_Group_Page::on_activation();
		$this->assertSame( 'publish', get_post_status( $page->ID ) );
		$this->assertSame( 'reservation-groupe', get_post( $page->ID )->post_name );
	}

	public function test_every_restaurant_s_page_follows_deactivation_but_one_turned_off_stays_off() {
		$this->connect();
		$this->connect_second();
		$brasserie = Livia_Group_Page::publish( 'r1', 'reservation-groupe', 'Brasserie' )['page'];
		$terrasse  = Livia_Group_Page::publish( 'r2', 'groupe-terrasse', 'Terrasse' )['page'];
		$off       = self::factory()->post->create( array( 'post_type' => 'page' ) );
		Livia_Group_Page::unpublish( 'r2' );

		Livia_Group_Page::on_deactivation();
		$this->assertSame( 'draft', get_post_status( $brasserie->ID ) );
		$this->assertSame( 'draft', get_post_status( $terrasse->ID ) );
		Livia_Group_Page::on_activation();
		$this->assertSame( 'publish', get_post_status( $brasserie->ID ) );
		$this->assertSame( 'draft', get_post_status( $terrasse->ID ), 'turned off on the screen, it stays off' );
		$this->assertSame( 'publish', get_post_status( $off ) );
	}

	public function test_reactivation_leaves_a_page_the_webmaster_unpublished() {
		$this->connect();
		$page = Livia_Group_Page::publish( 'r1', 'reservation-groupe', 'Réservation de groupe' )['page'];
		wp_update_post(
			array(
				'ID'          => $page->ID,
				'post_status' => 'draft',
			)
		);
		Livia_Group_Page::on_deactivation();
		Livia_Group_Page::on_activation();
		$this->assertSame( 'draft', get_post_status( $page->ID ) );
	}

	public function test_network_deactivation_unpublishes_on_every_site_and_network_activation_republishes() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite only.' );
		}
		$site = self::factory()->blog->create();
		switch_to_blog( $site );
		$this->connect();
		$page = Livia_Group_Page::publish( 'r1', 'reservation-groupe', 'Réservation de groupe' )['page'];
		restore_current_blog();
		$own = self::factory()->blog->create();
		switch_to_blog( $own );
		$this->connect();
		update_option( 'active_plugins', array( LIVIA_BASENAME ) );
		$kept = Livia_Group_Page::publish( 'r1', 'reservation-groupe', 'Réservation de groupe' )['page'];
		restore_current_blog();

		Livia_Group_Page::on_deactivation( true );
		$this->assertSame( get_current_blog_id(), get_main_site_id(), 'back on the site it started from' );
		switch_to_blog( $site );
		$this->assertSame( 'draft', get_post_status( $page->ID ) );
		restore_current_blog();
		switch_to_blog( $own );
		$this->assertSame( 'publish', get_post_status( $kept->ID ), 'still active on that site on its own' );
		restore_current_blog();

		Livia_Group_Page::on_activation( true );
		switch_to_blog( $site );
		$this->assertSame( 'publish', get_post_status( $page->ID ) );
		restore_current_blog();
	}

	/**
	 * A classic menu and a Navigation menu of the current site, each with the link the plugin adds and one set by hand.
	 *
	 * @return array{0: int, 1: int, 2: int} The plugin's menu item, the hand-set one, the Navigation menu.
	 */
	private function menu_links() {
		$menu       = wp_create_nav_menu( 'Principal ' . get_current_blog_id() );
		$item       = static function ( $classes ) use ( $menu ) {
			return wp_update_nav_menu_item(
				$menu,
				0,
				array(
					'menu-item-title'   => 'Réserver',
					'menu-item-url'     => Livia_Experience::ANCHOR,
					'menu-item-classes' => $classes,
					'menu-item-status'  => 'publish',
				)
			);
		};
		$navigation = self::factory()->post->create(
			array(
				'post_type'    => 'wp_navigation',
				'post_content' => '<!-- wp:page-list /--><!-- wp:navigation-link {"label":"Book a table","url":"#reserver","kind":"custom","className":"livia-reserver livia-added"} /-->',
			)
		);
		return array( $item( 'livia-reserver livia-added' ), $item( '' ), $navigation );
	}

	public function test_network_deactivation_removes_the_menu_links_on_every_site_where_it_stops() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite only.' );
		}
		$site = self::factory()->blog->create();
		switch_to_blog( $site );
		list( $ours, $hand, $navigation ) = $this->menu_links();
		restore_current_blog();
		$own = self::factory()->blog->create();
		switch_to_blog( $own );
		update_option( 'active_plugins', array( LIVIA_BASENAME ) );
		list( $kept ) = $this->menu_links();
		restore_current_blog();

		Livia_Group_Page::on_deactivation( true );
		switch_to_blog( $site );
		$this->assertNull( get_post( $ours ) );
		$this->assertNotNull( get_post( $hand ) );
		$this->assertSame( '<!-- wp:page-list /-->', get_post( $navigation )->post_content );
		restore_current_blog();
		switch_to_blog( $own );
		$this->assertNotNull( get_post( $kept ), 'still active on that site on its own' );
		restore_current_blog();
	}

	public function test_network_deactivation_leaves_the_other_networks_alone() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite only.' );
		}
		$network = self::factory()->network->create(
			array(
				'domain' => 'other-network.test',
				'path'   => '/',
			)
		);
		$site    = self::factory()->blog->create( array( 'network_id' => $network ) );
		switch_to_blog( $site );
		$this->connect();
		$page = Livia_Group_Page::publish( 'r1', 'reservation-groupe', 'Réservation de groupe' )['page'];
		restore_current_blog();

		Livia_Group_Page::on_deactivation( true );
		switch_to_blog( $site );
		$this->assertSame( 'publish', get_post_status( $page->ID ), 'another network keeps its own activation' );
		restore_current_blog();
	}

	public function test_an_interrupted_deactivation_is_undone_at_the_next_admin_load() {
		$this->connect();
		$page = Livia_Group_Page::publish( 'r1', 'reservation-groupe', 'Réservation de groupe' )['page'];
		$this->assertSame( 10, has_action( 'admin_init', array( 'Livia_Group_Page', 'resume' ) ) );

		Livia_Group_Page::resume();
		$this->assertSame( 'publish', get_post_status( $page->ID ), 'nothing to do with an empty list' );

		Livia_Group_Page::unpublish_all();
		$this->assertSame( 'draft', get_post_status( $page->ID ) );
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );
		Livia_Group_Page::resume();
		$this->assertSame( 'draft', get_post_status( $page->ID ), 'a list changed under ten minutes ago may belong to a deactivation still running' );

		update_option( Livia_Group_Page::REPUBLISH_AT, time() - 10 * MINUTE_IN_SECONDS );
		Livia_Group_Page::unpublish_all();
		Livia_Group_Page::resume();
		$this->assertSame( 'draft', get_post_status( $page->ID ), 'a run again over pages already drafts dates the list again' );

		update_option( Livia_Group_Page::REPUBLISH_AT, time() - 10 * MINUTE_IN_SECONDS );
		wp_set_current_user( 0 );
		Livia_Group_Page::resume();
		$this->assertSame( 'draft', get_post_status( $page->ID ), 'never for an anonymous admin-ajax.php request' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		Livia_Group_Page::resume();
		$this->assertSame( 'draft', get_post_status( $page->ID ), 'nor for who does not administer the site' );

		wp_set_current_user( $admin );
		Livia_Group_Page::resume();
		$this->assertSame( 'publish', get_post_status( $page->ID ) );
		$this->assertFalse( get_option( Livia_Group_Page::REPUBLISH ) );
		$this->assertFalse( get_option( Livia_Group_Page::REPUBLISH_AT ) );

		wp_update_post(
			array(
				'ID'          => $page->ID,
				'post_status' => 'draft',
			)
		);
		Livia_Group_Page::resume();
		$this->assertSame( 'draft', get_post_status( $page->ID ), 'a page the webmaster unpublished stays so' );
	}

	public function test_a_second_deactivation_keeps_the_pages_the_first_one_unpublished() {
		$this->connect();
		$page = Livia_Group_Page::publish( 'r1', 'reservation-groupe', 'Réservation de groupe' )['page'];
		Livia_Group_Page::unpublish_all();
		Livia_Group_Page::unpublish_all();
		Livia_Group_Page::republish();
		$this->assertSame( 'publish', get_post_status( $page->ID ) );
	}

	public function test_uninstall_keeps_the_page_as_draft_and_removes_the_settings() {
		$this->connect();
		$page = Livia_Group_Page::publish( 'r1', 'reservation-groupe', 'Réservation de groupe' )['page'];
		$post = self::factory()->post->create();
		update_post_meta( $post, '_livia_widget_mode', 'none' );
		set_transient( 'livia_update', array( 'remote' => null ), 60 );
		wp_schedule_single_event( time(), Livia_Updater::FETCH );
		update_option( 'livia_last_' . md5( self::KEY ), array( 'html' => '' ), false );
		list( $ours, $hand, $navigation ) = $this->menu_links();

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'livia/livia.php' );
		}
		include dirname( __DIR__, 2 ) . '/uninstall.php';

		$this->assertSame( 'draft', get_post_status( $page->ID ) );
		$this->assertFalse( get_option( 'livia_settings' ) );
		$this->assertFalse( get_option( 'livia_restaurants' ) );
		$this->assertFalse( get_option( 'livia_connections' ) );
		$this->assertFalse( metadata_exists( 'post', $post, '_livia_widget_mode' ) );
		wp_cache_flush();
		$this->assertFalse( get_option( '_transient_livia_update' ) );
		$this->assertFalse( get_option( 'livia_last_' . md5( self::KEY ) ) );
		$this->assertFalse( wp_next_scheduled( Livia_Updater::FETCH ) );
		$this->assertNull( get_post( $ours ) );
		$this->assertNotNull( get_post( $hand ) );
		$this->assertSame( '<!-- wp:page-list /-->', get_post( $navigation )->post_content );
	}

	public function test_uninstall_cleans_every_site_of_the_network() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite only.' );
		}
		$site = self::factory()->blog->create();
		switch_to_blog( $site );
		$this->connect();
		$page = Livia_Group_Page::publish( 'r1', 'reservation-groupe', 'Réservation de groupe' )['page'];

		list( $ours ) = $this->menu_links();
		restore_current_blog();
		$elsewhere = self::factory()->blog->create( array( 'network_id' => self::factory()->network->create( array( 'domain' => 'other-network.test' ) ) ) );
		switch_to_blog( $elsewhere );
		$this->connect();
		restore_current_blog();

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'livia/livia.php' );
		}
		include dirname( __DIR__, 2 ) . '/uninstall.php';

		$this->assertSame( get_current_blog_id(), get_main_site_id(), 'back on the site it started from' );
		switch_to_blog( $site );
		$this->assertSame( 'draft', get_post_status( $page->ID ) );
		$this->assertFalse( get_option( 'livia_settings' ) );
		$this->assertNull( get_post( $ours ) );
		restore_current_blog();
		switch_to_blog( $elsewhere );
		$this->assertFalse( get_option( 'livia_settings' ), 'deleting the plugin removes it from every network' );
		restore_current_blog();
	}
}
