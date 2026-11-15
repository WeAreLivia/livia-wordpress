<?php
/**
 * Livia_Rules, without WordPress.
 *
 * @package Livia
 */

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Signatures travel in base64.

final class RulesTest extends TestCase {

	private const SITE = array( 'wdg_site', 'wdg_site' );

	private const NONE = array( '', '' );

	private const BUBBLE = array(
		'widget' => 'wdg_site',
		'phone'  => 'wdg_site',
		'rule'   => 'all',
		'pages'  => array( 12 ),
	);

	/**
	 * The footer of a page with one restaurant's bubble, the section on, page 99 being the group page.
	 */
	private function footer( $bubble, $post_id, $mode = 'site', $other = '', $placed = false ) {
		return Livia_Rules::footer_widget( true, array( 'r1' => $bubble ), $post_id, $mode, $other, array( 99 ), $placed );
	}

	public function test_read_response_keeps_the_known_errors_and_calls_the_rest_unavailable() {
		$this->assertSame(
			array(
				'status' => 'ok',
				'data'   => array( 'a' => 1 ),
			),
			Livia_Rules::read_response( 200, '{"a":1}' )
		);
		$this->assertSame( 'unavailable', Livia_Rules::read_response( 200, 'not json' )['status'] );
		$this->assertSame( 'unknown_key', Livia_Rules::read_response( 404, '{"error":"unknown_key"}' )['status'] );
		$this->assertSame( 'unknown_widget', Livia_Rules::read_response( 404, '{"error":"unknown_widget"}' )['status'] );
		$this->assertSame( 'connector_disabled', Livia_Rules::read_response( 403, '{"error":"connector_disabled"}' )['status'] );
		$this->assertSame( 'unavailable', Livia_Rules::read_response( 403, '{"error":"unknown_key"}' )['status'] );
		$this->assertSame( 'unavailable', Livia_Rules::read_response( 503, '{"error":"unavailable"}' )['status'] );
		$this->assertSame( 'unavailable', Livia_Rules::read_response( 429, 'Too Many Requests' )['status'] );
		$this->assertSame( 'unavailable', Livia_Rules::read_response( 429, '{"error":"unknown_key"}' )['status'] );
		$this->assertSame( 'unavailable', Livia_Rules::read_response( 0, '' )['status'] );
	}

	public function test_key_format() {
		$this->assertTrue( Livia_Rules::is_key_format( 'lvwp_' . str_repeat( 'a2', 13 ) ) );
		$this->assertFalse( Livia_Rules::is_key_format( 'lvwp_9F2kQ7mX' ) );
		$this->assertFalse( Livia_Rules::is_key_format( 'lvxx_' . str_repeat( 'a', 26 ) ) );
		$this->assertFalse( Livia_Rules::is_key_format( ' lvwp_' . str_repeat( 'a', 26 ) ) );
		$this->assertFalse( Livia_Rules::is_key_format( 'lvwp_' . str_repeat( 'a', 26 ) . "\n" ) );
		$this->assertSame( 'lvwp_…7mxq', Livia_Rules::mask_key( 'lvwp_abcdefghijklmnopqrstuv7mxq' ) );
	}

	public function test_usable_widgets_leave_out_full_screen_and_unknown_formats() {
		$widgets = array(
			array(
				'id'         => 'a',
				'format'     => 'bubble',
				'fullscreen' => false,
			),
			array(
				'id'         => 'b',
				'format'     => 'bubble',
				'fullscreen' => true,
			),
			array(
				'id'         => 'c',
				'format'     => 'inline',
				'fullscreen' => false,
			),
			array(
				'id'     => 'd',
				'format' => 'banner',
			),
			array( 'format' => 'button' ),
		);
		$this->assertSame( array( 'a', 'c' ), Livia_Rules::widget_ids( Livia_Rules::usable_widgets( $widgets ) ) );
		$this->assertSame( array( 'a' ), Livia_Rules::widget_ids( Livia_Rules::usable_widgets( $widgets, array( 'bubble' ) ) ) );
	}

	public function test_footer_widget_follows_the_rule() {
		$this->assertSame( self::SITE, $this->footer( self::BUBBLE, 12 ) );
		$this->assertSame( self::SITE, $this->footer( self::BUBBLE, 0 ) );

		$except = array_merge( self::BUBBLE, array( 'rule' => 'except' ) );
		$this->assertSame( self::NONE, $this->footer( $except, 12 ) );
		$this->assertSame( self::SITE, $this->footer( $except, 13 ) );
		$this->assertSame( self::SITE, $this->footer( $except, 0 ) );

		$only = array_merge( self::BUBBLE, array( 'rule' => 'only' ) );
		$this->assertSame( self::SITE, $this->footer( $only, 12 ) );
		$this->assertSame( self::NONE, $this->footer( $only, 13 ) );
		$this->assertSame( self::NONE, $this->footer( $only, 0 ) );
	}

	public function test_footer_widget_never_on_a_group_page_and_one_bubble_per_page() {
		$this->assertSame( self::NONE, $this->footer( self::BUBBLE, 99 ) );
		$this->assertSame( self::NONE, $this->footer( self::BUBBLE, 99, 'other', 'wdg_other' ) );
		$this->assertSame( self::NONE, $this->footer( self::BUBBLE, 12, 'site', '', true ) );
	}

	public function test_footer_widget_follows_the_page_choice() {
		$only = array_merge( self::BUBBLE, array( 'rule' => 'only' ) );
		$this->assertSame( self::NONE, $this->footer( self::BUBBLE, 12, 'none' ) );
		$this->assertSame( array( 'wdg_other', 'wdg_other' ), $this->footer( $only, 13, 'other', 'wdg_other' ) );
		$this->assertSame( self::NONE, $this->footer( $only, 13, 'other', '' ), 'another bubble no restaurant has' );
		$this->assertSame( self::NONE, Livia_Rules::footer_widget( true, array(), 12, 'site', '', array(), false ) );
	}

	public function test_a_bubble_on_one_screen_only_keeps_its_pages() {
		$phone_only = array_merge(
			self::BUBBLE,
			array(
				'widget' => '',
				'phone'  => 'wdg_phone',
				'rule'   => 'only',
			)
		);
		$this->assertSame( array( '', 'wdg_phone' ), $this->footer( $phone_only, 12 ) );
		$this->assertSame( self::NONE, $this->footer( $phone_only, 13 ) );
		$this->assertTrue( Livia_Rules::shows_bubble( $phone_only ) );
		$this->assertSame( array( 12 => 'r1' ), Livia_Rules::taken_pages( array( 'r1' => $phone_only ) ) );
		$this->assertSame( 1, Livia_Rules::bubble_page_count( array( 'r1' => $phone_only ), 'r1', array( 12, 13 ) ) );
		$this->assertFalse( Livia_Rules::shows_bubble( array_merge( $phone_only, array( 'phone' => '' ) ) ) );
	}

	public function test_a_screen_without_the_first_restaurant_s_bubble_goes_to_the_next_rule() {
		$bubbles = array(
			'r1' => array(
				'widget' => '',
				'phone'  => 'wdg_phone',
				'rule'   => 'all',
				'pages'  => array(),
			),
			'r2' => array(
				'widget' => 'wdg_b',
				'phone'  => 'wdg_b',
				'rule'   => 'all',
				'pages'  => array(),
			),
		);
		$this->assertSame( array( 'wdg_b', 'wdg_phone' ), Livia_Rules::footer_widget( true, $bubbles, 12, 'site', '', array(), false ) );
		$this->assertSame( array( 'wdg_b', 'wdg_phone' ), Livia_Rules::footer_widget( true, $bubbles, 0, 'site', '', array(), false ) );
		$this->assertSame( 'r2', Livia_Rules::bubble_owner( $bubbles, 12, 'widget' ) );
		$this->assertSame( 'r1', Livia_Rules::bubble_owner( $bubbles, 12, 'phone' ) );
		$this->assertSame( 2, Livia_Rules::bubble_page_count( $bubbles, 'r1', array( 12, 13 ) ) );
		$this->assertSame( 2, Livia_Rules::bubble_page_count( $bubbles, 'r2', array( 12, 13 ) ) );
	}

	public function test_a_page_under_only_is_held_screen_by_screen() {
		$phone_only = array(
			'widget' => '',
			'phone'  => 'wdg_a',
			'rule'   => 'only',
			'pages'  => array( 12 ),
		);
		$both       = array(
			'widget' => 'wdg_b',
			'phone'  => 'wdg_b',
			'rule'   => 'only',
			'pages'  => array( 12 ),
		);
		$bubbles    = array(
			'r1' => $phone_only,
			'r2' => $both,
		);
		$this->assertSame( array(), Livia_Rules::taken_from( $bubbles, 'r1' ) );
		$this->assertSame( array(), Livia_Rules::taken_from( $bubbles, 'r2' ), 'the desktop is still free for it' );
		$this->assertSame( array( 'wdg_b', 'wdg_a' ), Livia_Rules::footer_widget( true, $bubbles, 12, 'site', '', array(), false ) );
		$this->assertSame( 1, Livia_Rules::bubble_page_count( $bubbles, 'r1', array( 12, 13 ) ) );
		$this->assertSame( 1, Livia_Rules::bubble_page_count( $bubbles, 'r2', array( 12, 13 ) ) );

		$bubbles = array(
			'r2' => $both,
			'r1' => $phone_only,
		);
		$this->assertSame( array( 12 => 'r2' ), Livia_Rules::taken_from( $bubbles, 'r1' ), 'the first holds both screens' );
		$this->assertSame( array(), Livia_Rules::taken_from( $bubbles, 'r2' ) );

		$bubbles['r1']['phone'] = '';
		$this->assertSame( array( 12 => 'r2' ), Livia_Rules::taken_from( $bubbles, 'r1' ), 'a bubble turned off cannot hold the page either' );
	}

	public function test_plugin_version_reads_the_header_as_wordpress_does() {
		$this->assertSame( '1.2.3', Livia_Rules::plugin_version( "<?php\n/**\n * Plugin Name: Livia\n * Version:           1.2.3\n */" ) );
		$this->assertSame( '1.2.3', Livia_Rules::plugin_version( "<?php\n/* Version: 1.2.3 */" ) );
		$this->assertSame( '', Livia_Rules::plugin_version( "<?php\n/**\n * Plugin Name: Livia\n */" ) );
		$this->assertSame( '', Livia_Rules::plugin_version( '' ) );
		$this->assertSame( '1.2.3', Livia_Rules::plugin_version( "<?php\r/**\r * Plugin Name: Livia\r * Version: 1.2.3\r */" ), 'lone \r line ends' );
	}

	public function test_the_section_switch_takes_every_bubble_off() {
		$this->assertSame( self::NONE, Livia_Rules::footer_widget( false, array( 'r1' => self::BUBBLE ), 12, 'site', '', array(), false ) );
		$this->assertSame( self::NONE, Livia_Rules::footer_widget( false, array( 'r1' => self::BUBBLE ), 12, 'other', 'wdg_other', array(), false ) );
	}

	public function test_only_wins_over_all_then_the_first_restaurant_wins() {
		$bubbles = array(
			'r1' => self::BUBBLE,
			'r2' => array(
				'widget' => 'wdg_t',
				'rule'   => 'only',
				'pages'  => array( 13 ),
			),
			'r3' => array(
				'widget' => 'wdg_3',
				'rule'   => 'only',
				'pages'  => array( 13, 14 ),
			),
		);
		$this->assertSame( 'r2', Livia_Rules::bubble_owner( $bubbles, 13 ) );
		$this->assertSame( 'r3', Livia_Rules::bubble_owner( $bubbles, 14 ) );
		$this->assertSame( 'r1', Livia_Rules::bubble_owner( $bubbles, 15 ) );
		$this->assertSame( 'r1', Livia_Rules::bubble_owner( $bubbles, 0 ) );
		$this->assertSame(
			array(
				13 => 'r2',
				14 => 'r3',
			),
			Livia_Rules::taken_pages( $bubbles )
		);

		$bubbles['r1']['rule']  = 'except';
		$bubbles['r1']['pages'] = array( 15 );
		$bubbles['r4']          = array(
			'widget' => 'wdg_4',
			'rule'   => 'all',
			'pages'  => array(),
		);
		$this->assertSame( 'r4', Livia_Rules::bubble_owner( $bubbles, 15 ), 'the next "All" covers a page the first one excepts' );

		$bubbles['r2']['widget'] = '';
		$this->assertSame( 'r3', Livia_Rules::bubble_owner( $bubbles, 13 ), 'a bubble turned off holds no page' );
		$this->assertArrayNotHasKey( 'r2', array_flip( Livia_Rules::taken_pages( $bubbles ) ) );
	}

	public function test_bubble_page_count() {
		$pages = array( 1, 2, 3, 4, 5 );
		$all   = array(
			'widget' => 'a',
			'rule'   => 'all',
			'pages'  => array( 5 ),
		);
		$this->assertSame( 5, Livia_Rules::bubble_page_count( array( 'r1' => $all ), 'r1', $pages ) );
		$this->assertSame( 4, Livia_Rules::bubble_page_count( array( 'r1' => array_merge( $all, array( 'rule' => 'except' ) ) ), 'r1', array( 1, 2, 3, 4, 5 ) ) );
		$only    = array(
			'widget' => 'b',
			'rule'   => 'only',
			'pages'  => array( 5, 77 ),
		);
		$bubbles = array(
			'r1' => $all,
			'r2' => $only,
		);
		$this->assertSame( 4, Livia_Rules::bubble_page_count( $bubbles, 'r1', $pages ) );
		$this->assertSame( 1, Livia_Rules::bubble_page_count( $bubbles, 'r2', $pages ) );
	}

	public function test_reserved_slugs() {
		$this->assertTrue( Livia_Rules::is_reserved_slug( 'wp-admin' ) );
		$this->assertTrue( Livia_Rules::is_reserved_slug( 'feed' ) );
		$this->assertTrue( Livia_Rules::is_reserved_slug( '2024' ) );
		$this->assertTrue( Livia_Rules::is_reserved_slug( 'page2' ) );
		$this->assertTrue( Livia_Rules::is_reserved_slug( 'rubrique', array( 'rubrique', '' ) ) );
		$this->assertFalse( Livia_Rules::is_reserved_slug( 'reservation-groupe', array( '' ) ) );
	}

	public function test_rewrite_conflict_takes_the_first_matching_rule() {
		$page_rule = array( '(.?.+?)(?:/([0-9]+))?/?$' => 'index.php?pagename=$matches[1]&page=$matches[2]' );
		$this->assertNull( Livia_Rules::rewrite_conflict( 'reservation-groupe', $page_rule ) );

		$rules = array_merge(
			array(
				'search/(.+)/?$'         => 'index.php?s=$matches[1]',
				'([0-9]{4})/?$'          => 'index.php?year=$matches[1]',
				'^reservation-groupe/?$' => 'index.php?other_plugin=1',
			),
			$page_rule
		);
		$this->assertSame( '^reservation-groupe/?$', Livia_Rules::rewrite_conflict( 'reservation-groupe', $rules ) );
		$this->assertSame( '([0-9]{4})/?$', Livia_Rules::rewrite_conflict( '2024', $rules ) );
		$this->assertNull( Livia_Rules::rewrite_conflict( 'search', $rules ) );
		$this->assertNull( Livia_Rules::rewrite_conflict( 'privatisation', array( '([^/]+)(?:/([0-9]+))?/?$' => 'index.php?name=$matches[1]&page=$matches[2]' ) ) );
		$this->assertNull( Livia_Rules::rewrite_conflict( 'x', array( '(' => 'index.php?broken=1' ) ) );
	}

	public function test_service_urls() {
		$hosts = array( 'chat.booking.wearelivia.com', 'localhost' );
		$this->assertTrue( Livia_Rules::is_service_url( 'https://chat.booking.wearelivia.com/a.css?v=1', $hosts ) );
		$this->assertTrue( Livia_Rules::is_service_url( 'HTTPS://Chat.Booking.WeAreLivia.com/a.css', $hosts ) );
		$this->assertFalse( Livia_Rules::is_service_url( 'http://chat.booking.wearelivia.com/a.css', $hosts ), 'https only' );
		$this->assertFalse( Livia_Rules::is_service_url( 'http://localhost:3000/a.js', $hosts ) );
		$this->assertTrue( Livia_Rules::is_service_url( 'http://localhost:3000/a.js', $hosts, true ), 'http when the service is http' );
		$this->assertFalse( Livia_Rules::is_service_url( 'https://chat.booking.wearelivia.com.evil.test/a.js', $hosts ) );
		$this->assertFalse( Livia_Rules::is_service_url( '//chat.booking.wearelivia.com/a.js', $hosts ) );
		$this->assertFalse( Livia_Rules::is_service_url( 'javascript:alert(1)', $hosts ) );
		$this->assertFalse( Livia_Rules::is_service_url( 'https://user:pass@chat.booking.wearelivia.com/a.js', $hosts ) );
		$this->assertFalse( Livia_Rules::is_service_url( 'https://evil.test@chat.booking.wearelivia.com/a.js', $hosts ) );
		$this->assertFalse( Livia_Rules::is_service_url( 'https://chat.booking.wearelivia.com\\@evil.test/a.js', $hosts ) );
		$this->assertFalse( Livia_Rules::is_service_url( 'https://evil.test\\@chat.booking.wearelivia.com/a.js', $hosts ) );
		$this->assertSame( 'host.docker.internal', Livia_Rules::host_of( 'http://host.docker.internal:3000', true ) );
		$this->assertSame( '', Livia_Rules::host_of( 'http://host.docker.internal:3000' ) );
	}

	public function test_square_brackets_are_encoded() {
		$this->assertSame( '<p>Menu &#91;Nouveau&#93; &#91;gallery ids=1&#93;</p>', Livia_Rules::without_shortcodes( '<p>Menu [Nouveau] [gallery ids=1]</p>' ) );
	}

	public function test_the_trash_keeps_a_content_only_when_it_can_be_restored() {
		$this->assertTrue( Livia_Rules::trash_keeps( 'page', 30, false ) );
		$this->assertFalse( Livia_Rules::trash_keeps( 'page', 0, false ), 'deleted for good' );
		$this->assertFalse( Livia_Rules::trash_keeps( 'attachment', 30, false ), 'no trash for media files' );
		$this->assertTrue( Livia_Rules::trash_keeps( 'attachment', 30, true ) );
		$this->assertFalse( Livia_Rules::trash_keeps( 'attachment', 0, true ) );
	}

	public function test_update_offer() {
		$hosts  = array( 'chat.booking.wearelivia.com' );
		$remote = array(
			'version'      => '1.1.0',
			'package'      => 'https://chat.booking.wearelivia.com/wordpress/v1/download?key=k&version=1.1.0',
			'requires'     => '6.5',
			'tested'       => '7.1',
			'requires_php' => '7.4',
			'icons'        => array(
				'1x'  => 'https://chat.booking.wearelivia.com/wordpress/v1/assets/icon-128x128.png',
				'svg' => 'https://elsewhere.test/icon.svg',
			),
			'sections'     => array( 'changelog' => '<p>New</p>' ),
			'last_updated' => '2026-11-13',
			'signature'    => base64_encode( str_repeat( 's', 64 ) ) . "\n",
		);
		$offer  = Livia_Rules::update_offer( $remote, 'livia/livia.php', $hosts );
		$this->assertSame( base64_encode( str_repeat( 's', 64 ) ), $offer['signature'] );
		$this->assertNull( Livia_Rules::update_offer( array_diff_key( $remote, array( 'signature' => 1 ) ), 'livia/livia.php', $hosts ), 'no signature, no offer' );
		$this->assertNull( Livia_Rules::update_offer( array_merge( $remote, array( 'signature' => base64_encode( 'short' ) ) ), 'livia/livia.php', $hosts ) );
		$this->assertNull( Livia_Rules::update_offer( array_merge( $remote, array( 'signature' => array( 'x' ) ) ), 'livia/livia.php', $hosts ) );
		$this->assertSame( '1.1.0', $offer['new_version'] );
		$this->assertSame( 'livia/livia.php', $offer['plugin'] );
		$this->assertSame( $remote['package'], $offer['package'] );
		$this->assertSame( array( '1x' => $remote['icons']['1x'] ), $offer['icons'] );
		$this->assertSame( '<p>New</p>', $offer['changelog'] );

		$this->assertNull( Livia_Rules::update_offer( array_merge( $remote, array( 'package' => 'https://elsewhere.test/livia.zip' ) ), 'livia/livia.php', $hosts ) );
		$this->assertNull( Livia_Rules::update_offer( array_merge( $remote, array( 'version' => 'latest' ) ), 'livia/livia.php', $hosts ) );
		$this->assertNull( Livia_Rules::update_offer( array_merge( $remote, array( 'version' => "1.1.0\n" ) ), 'livia/livia.php', $hosts ) );
		$this->assertNull( Livia_Rules::update_offer( array_merge( $remote, array( 'package' => 'http://chat.booking.wearelivia.com/livia.zip' ) ), 'livia/livia.php', $hosts ) );
		$this->assertNull( Livia_Rules::update_offer( array(), 'livia/livia.php', $hosts ) );
	}

	public function test_update_offer_tested_covers_the_site_s_minor_version() {
		$hosts  = array( 'chat.booking.wearelivia.com' );
		$remote = array(
			'version'   => '1.1.0',
			'package'   => 'https://chat.booking.wearelivia.com/wordpress/v1/download?key=k&version=1.1.0',
			'tested'    => '7.1',
			'signature' => base64_encode( str_repeat( 's', 64 ) ),
		);
		$tested = static function ( $tested, $wp_version ) use ( $remote, $hosts ) {
			return Livia_Rules::update_offer( array_merge( $remote, array( 'tested' => $tested ) ), 'livia/livia.php', $hosts, false, $wp_version )['tested'];
		};
		$this->assertSame( '7.1.5', $tested( '7.1', '7.1.5' ) );
		$this->assertSame( '7.1.5', $tested( '7.1', '7.1.5-alpha-65493' ), 'the development suffix is left out, as on the Updates screen' );
		$this->assertSame( '7.1', $tested( '7.1', '7.1' ) );
		$this->assertSame( '7.1', $tested( '7.1', '7.2' ) );
		$this->assertSame( '7.1', $tested( '7.1', '7.0.3' ) );
		$this->assertSame( '7.1', $tested( '7.1', '7.10.2' ) );
		$this->assertSame( '7.1.2', $tested( '7.1.2', '7.1.5' ) );
		$this->assertSame( '7.1', $tested( '7.1', '' ) );
		$this->assertSame( '7.1.6', $tested( '7.1', array( '7.1.5', '7.1.6' ) ), 'a pending 7.1.6 is covered too' );
		$this->assertSame( '7.1.5', $tested( '7.1', array( '7.1.5', '7.2.0' ) ) );
		$this->assertSame( '7.1.1', $tested( '7.1', array( '7.0.3', '7.1.1' ) ) );
		$this->assertSame( '7.1.6', $tested( '7.1', array( '7.1.6', '7.1.5' ) ), 'the order does not matter' );
	}

	public function test_thresholds_make_a_group_tier_within_the_widget_s_range() {
		$this->assertSame( array( 15, 40 ), Livia_Rules::thresholds( '15', '40' ) );
		$this->assertSame( array( 2, 2 ), Livia_Rules::thresholds( 2, 2 ) );
		$this->assertSame( array( 10, 998 ), Livia_Rules::thresholds( '10', '998' ) );
		$this->assertNull( Livia_Rules::thresholds( '1', '40' ), 'a group of one' );
		$this->assertNull( Livia_Rules::thresholds( '41', '40' ), 'a group above the event' );
		$this->assertNull( Livia_Rules::thresholds( '15', '999' ), 'no room left for an event' );
		$this->assertNull( Livia_Rules::thresholds( '15.5', '40' ) );
		$this->assertNull( Livia_Rules::thresholds( '', '40' ) );
		$this->assertNull( Livia_Rules::thresholds( 'quinze', '40' ) );
	}

	public function test_booking_urls_are_web_addresses() {
		$this->assertTrue( Livia_Rules::is_booking_url( 'https://bookings.zenchef.test/results?rid=123456&pax={n}' ) );
		$this->assertTrue( Livia_Rules::is_booking_url( 'http://brasserie.test/nos-tables/#reserver' ) );
		$this->assertTrue( Livia_Rules::is_booking_url( 'https://{n}.example.test/' ), '{n} stands for a number, anywhere' );
		$this->assertFalse( Livia_Rules::is_booking_url( 'javascript:alert(1)' ) );
		$this->assertFalse( Livia_Rules::is_booking_url( 'https://user:pass@brasserie.test/' ) );
		$this->assertFalse( Livia_Rules::is_booking_url( '/nos-tables/' ) );
		$this->assertFalse( Livia_Rules::is_booking_url( 'mailto:events@brasserie.test' ) );
		$this->assertFalse( Livia_Rules::is_booking_url( '' ) );
	}

	public function test_css_values_of_the_theme() {
		$this->assertSame( 'var(--wp--preset--color--base)', Livia_Rules::css_value( 'var(--wp--preset--color--base)' ) );
		$this->assertSame( 'var(--wp--preset--color--contrast)', Livia_Rules::css_value( 'var:preset|color|contrast' ) );
		$this->assertSame( '#1F4E5F', Livia_Rules::css_value( '#1F4E5F' ) );
		$this->assertSame( '"Manrope", sans-serif', Livia_Rules::css_value( '"Manrope", sans-serif' ) );
		$this->assertSame( '', Livia_Rules::css_value( 'red;}body{display:none' ) );
		$this->assertSame( '', Livia_Rules::css_value( '</style><script>' ) );
		$this->assertSame( '', Livia_Rules::css_value( array( 'red' ) ) );
		$this->assertSame( '', Livia_Rules::css_value( '' ) );
	}

	public function test_of_name_contracts_the_french_article() {
		$this->assertSame( 'du Grand Palais', Livia_Rules::of_name( 'Le Grand Palais', 'fr_FR' ) );
		$this->assertSame( 'de la Terrasse', Livia_Rules::of_name( 'La Terrasse', 'fr_FR' ) );
		$this->assertSame( 'des Halles', Livia_Rules::of_name( 'Les Halles', 'fr_FR' ) );
		$this->assertSame( 'de l\'Atelier', Livia_Rules::of_name( 'L\'Atelier', 'fr_FR' ) );
		$this->assertSame( 'de l\'Éclair', Livia_Rules::of_name( 'L’Éclair', 'fr_FR' ) );
		$this->assertSame( 'de Brasserie Lumière', Livia_Rules::of_name( 'Brasserie Lumière', 'fr_FR' ) );
		$this->assertSame( 'd\'Auberge du Port', Livia_Rules::of_name( 'Auberge du Port', 'fr_FR' ) );
		$this->assertSame( 'd\'Éden', Livia_Rules::of_name( 'Éden', 'fr_FR' ) );
		$this->assertSame( 'de Laura', Livia_Rules::of_name( 'Laura', 'fr_FR' ) );
		$this->assertSame( 'Le Grand Palais', Livia_Rules::of_name( 'Le Grand Palais', 'en_US' ) );
	}
}
