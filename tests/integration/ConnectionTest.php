<?php
/**
 * The restaurants of the keys, as Livia answers and as the plugin keeps them.
 *
 * @package Livia
 */

final class ConnectionTest extends Livia_TestCase {

	private function connection_answer( $id, $name ) {
		return array(
			200,
			array(
				'restaurant'   => array(
					'id'   => $id,
					'name' => $name,
				),
				'pageWidgetId' => 'wdg_page',
				'widgets'      => array(
					array(
						'id'         => 'wdg_page',
						'name'       => 'Page',
						'format'     => 'inline',
						'fullscreen' => false,
					),
					array(
						'id'         => 'wdg_full',
						'name'       => 'Plein écran',
						'format'     => 'bubble',
						'fullscreen' => true,
					),
				),
			),
		);
	}

	public function test_an_answer_is_kept_and_a_refusal_keeps_the_restaurant() {
		$this->connect();
		$this->answers['/wordpress/v1/connection'] = $this->connection_answer( 'rst_1', 'Brasserie Lumière' );
		$this->assertSame( 'ok', Livia_Service::refresh_connection( 'r1' ) );
		$this->assertSame( 'Brasserie Lumière', Livia_Service::name( 'r1' ) );
		$this->assertSame( array( 'wdg_page' ), Livia_Rules::widget_ids( Livia_Service::widgets( 'r1' ) ) );

		$this->answers['/wordpress/v1/page'] = $this->page_answer();
		Livia_Service::forget_page( 'r1' );
		Livia_Service::group_page( 'r1' );
		$this->assertNotNull( Livia_Service::last_page( 'r1' ) );

		$this->answers['/wordpress/v1/connection'] = array( 403, array( 'error' => 'connector_disabled' ) );
		$this->assertSame( 'connector_disabled', Livia_Service::refresh_connection( 'r1' ) );
		$this->assertNull( Livia_Service::last_page( 'r1' ), 'the screen says the page no longer shows it' );
		$this->assertFalse( get_transient( 'livia_page_' . md5( self::KEY . '|wdg_page' ) ) );
		$this->assertSame( 'connector_disabled', Livia_Service::connection( 'r1' )['state'] );
		$this->assertSame( 'Brasserie Lumière', Livia_Service::name( 'r1' ) );

		unset( $this->answers['/wordpress/v1/connection'] );
		$this->assertSame( 'unavailable', Livia_Service::refresh_connection( 'r1' ) );
		$this->assertSame( 'connector_disabled', Livia_Service::connection( 'r1' )['state'] );
	}

	public function test_an_inactive_account_is_treated_like_a_turned_off_connector() {
		$this->connect();
		$this->answers['/wordpress/v1/page'] = $this->page_answer();
		Livia_Service::group_page( 'r1' );
		$this->assertNotNull( Livia_Service::last_page( 'r1' ) );

		$this->answers['/wordpress/v1/connection'] = array(
			403,
			array(
				'error'  => 'connector_disabled',
				'reason' => 'account_inactive',
			),
		);
		$this->assertSame( 'account_inactive', Livia_Service::refresh_connection( 'r1' ) );
		$this->assertNull( Livia_Service::last_page( 'r1' ) );
		$this->assertSame( 'account_inactive', Livia_Service::connection( 'r1' )['state'] );
		$this->assertStringContainsString( 'no longer active', Livia_Admin::key_message( 'account_inactive' ) );
	}

	public function test_a_429_in_front_of_the_service_is_only_unavailable_for_a_while() {
		$this->connect();
		$this->answers['/wordpress/v1/page'] = $this->page_answer( '<div class="livia-rp">Version 1</div>' );
		Livia_Service::group_page( 'r1' );
		Livia_Service::forget_page( 'r1' );
		$this->new_request();
		$limited = array( 429, 'Too Many Requests' );
		foreach ( array( 'connection', 'page', 'update' ) as $path ) {
			$this->answers[ '/wordpress/v1/' . $path ] = $limited;
		}

		$this->assertSame( 'unavailable', Livia_Service::refresh_connection( 'r1' ) );
		$this->assertSame( 'ok', Livia_Service::connection( 'r1' )['state'] );
		$this->assertSame( self::KEY, Livia_Service::restaurant( 'r1' )['key'] );
		$this->assertNotNull( Livia_Service::widget( 'wdg_site' ), 'the widgets known stay' );
		$this->assertSame( '<div class="livia-rp">Version 1</div>', Livia_Service::group_page( 'r1' )['html'], 'the last version keeps showing' );
		$this->assertNull( Livia_Updater::latest() );
		$this->assertSame( self::KEY, Livia_Service::update_key(), 'the key still asks for updates' );
	}

	public function test_a_refused_update_key_forgets_the_update() {
		$this->connect();
		$this->connect_second();
		set_transient( Livia_Updater::TRANSIENT, array( 'remote' => null ), 60 );
		$this->answers['/wordpress/v1/connection'] = array( 404, array( 'error' => 'unknown_key' ) );
		$this->assertSame( 'unknown_key', Livia_Service::refresh_connection( 'r1' ) );
		$this->assertSame( self::KEY2, Livia_Service::update_key() );
		$this->assertFalse( get_transient( Livia_Updater::TRANSIENT ) );

		set_transient( Livia_Updater::TRANSIENT, array( 'remote' => null ), 60 );
		$this->assertSame( 'unknown_key', Livia_Service::refresh_connection( 'r2' ) );
		$this->assertFalse( get_transient( Livia_Updater::TRANSIENT ), 'no key left' );

		set_transient( Livia_Updater::TRANSIENT, array( 'remote' => null ), 60 );
		$this->assertSame( 'unknown_key', Livia_Service::refresh_connection( 'r2' ) );
		$this->assertNotFalse( get_transient( Livia_Updater::TRANSIENT ), 'a key already refused changes nothing' );
	}

	public function test_the_key_travels_encoded() {
		Livia_Service::ask_connection( 'lvwp_a&b=c' );
		$this->assertStringContainsString( 'key=lvwp_a%26b%3Dc', $this->calls[0] );
		$this->assertStringStartsWith( LIVIA_SERVICE_URL . '/wordpress/v1/connection?', $this->calls[0] );
	}

	public function test_each_restaurant_keeps_its_own_connection() {
		$this->connect();
		$this->connect_second();
		$this->answers['/wordpress/v1/connection'] = function () {
			return false !== strpos( end( $this->calls ), self::KEY2 ) ? array( 404, array( 'error' => 'unknown_key' ) ) : $this->connection_answer( 'rst_1', 'Brasserie Lumière' );
		};
		$this->assertSame( 'ok', Livia_Service::refresh_connection( 'r1' ) );
		$this->assertSame( 'unknown_key', Livia_Service::refresh_connection( 'r2' ) );
		$this->assertSame( 'ok', Livia_Service::connection( 'r1' )['state'] );
		$this->assertSame( 'unknown_key', Livia_Service::connection( 'r2' )['state'] );
		$this->assertSame( 'La Terrasse Lumière', Livia_Service::name( 'r2' ) );
		$this->assertSame( 'Page de groupe', Livia_Service::widget( 'wdg_t_page' )['name'], 'a widget is found among every restaurant' );
	}

	public function test_another_key_keeps_nothing_of_the_previous_restaurant() {
		$this->connect(
			array(
				'page_widget'  => 'wdg_page',
				'site_widget'  => 'wdg_site',
				'phone_widget' => 'wdg_other',
				'slug'         => 'reservation-groupe',
			)
		);
		$this->answers['/wordpress/v1/page'] = $this->page_answer( '<div class="livia-rp">Restaurant A</div>' );
		Livia_Service::group_page( 'r1' );

		Livia_Service::use_key( 'r1', 'lvwp_bbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', $this->connection_answer( 'rst_9', 'Autre' )[1] + array( 'state' => 'ok' ) );
		$restaurant = Livia_Service::restaurant( 'r1' );
		$this->assertSame( 'lvwp_bbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', $restaurant['key'] );
		$this->assertSame( '', $restaurant['page_widget'] );
		$this->assertSame( '', $restaurant['site_widget'] );
		$this->assertSame( '', $restaurant['phone_widget'] );
		$this->assertSame( 'reservation-groupe', $restaurant['slug'], 'its page stays' );
		$this->assertSame( 'Autre', Livia_Service::name( 'r1' ) );
		$this->assertNull( Livia_Service::last_page( 'r1' ) );

		unset( $this->answers['/wordpress/v1/page'] );
		$this->new_request();
		$this->assertNull( Livia_Service::group_page( 'r1' ) );
	}

	public function test_another_key_of_the_same_restaurant_keeps_its_widgets() {
		$this->connect(
			array(
				'page_widget'     => 'wdg_page',
				'site_widget'     => 'wdg_site',
				'phone_widget'    => 'wdg_other',
				'xp_group_widget' => 'wdg_xp',
				'slug'            => 'reservation-groupe',
			)
		);

		Livia_Service::use_key( 'r1', 'lvwp_bbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', $this->connection_answer( 'rst_1', 'Brasserie Lumière' )[1] + array( 'state' => 'ok' ) );
		$restaurant = Livia_Service::restaurant( 'r1' );
		$this->assertSame( 'lvwp_bbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', $restaurant['key'] );
		$this->assertSame( 'wdg_page', $restaurant['page_widget'] );
		$this->assertSame( 'wdg_site', $restaurant['site_widget'] );
		$this->assertSame( 'wdg_other', $restaurant['phone_widget'] );
		$this->assertSame( 'wdg_xp', $restaurant['xp_group_widget'] );
	}

	public function test_a_removed_restaurant_leaves_nothing_and_its_page_goes_back_to_draft() {
		$this->connect();
		$this->connect_second();
		$this->answers['/wordpress/v1/page'] = $this->page_answer();
		$page                                = Livia_Group_Page::publish( 'r2', 'groupe-terrasse', 'Terrasse' )['page'];
		Livia_Service::group_page( 'r2' );

		Livia_Service::remove_restaurant( 'r2' );
		$this->assertSame( array( 'r1' ), array_keys( Livia_Service::restaurants() ) );
		$this->assertArrayNotHasKey( 'r2', get_option( 'livia_connections' ) );
		$this->assertFalse( get_option( 'livia_last_' . md5( self::KEY2 ) ) );
		$this->assertSame( 'draft', get_post_status( $page->ID ) );
		$this->assertSame( 'groupe-terrasse', get_post( $page->ID )->post_name );
		$this->assertNull( Livia_Service::widget( 'wdg_t_site' ) );
	}

	public function test_a_new_restaurant_never_takes_the_id_of_a_removed_one() {
		$this->connect();
		$this->connect_second();
		Livia_Service::remove_restaurant( 'r2' );
		$id = Livia_Service::add_restaurant( 'lvwp_cccccccccccccccccccccccccccccc', array( 'state' => 'ok' ) );
		$this->assertSame( 'r3', $id );
		$this->assertSame( array( 'r1', 'r3' ), array_keys( Livia_Service::restaurants() ) );
	}
}
