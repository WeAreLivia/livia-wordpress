<?php
/**
 * The settings screen's actions: who may run them, and the trash.
 *
 * @package Livia
 */

final class AdminTest extends Livia_TestCase {

	public function set_up() {
		parent::set_up();
		$this->connect();
		add_filter( 'wp_redirect', array( $this, 'stop_at_redirect' ) );
	}

	public function tear_down() {
		remove_filter( 'wp_redirect', array( $this, 'stop_at_redirect' ) );
		$_GET     = array();
		$_POST    = array();
		$_REQUEST = array();
		parent::tear_down();
	}

	/**
	 * The screen goes back to itself then exits: the test stops there instead.
	 */
	public function stop_at_redirect( $location ) {
		unset( $location );
		throw new RuntimeException( 'redirect' );
	}

	private function as_role( $role ) {
		$user = self::factory()->user->create( array( 'role' => $role ) );
		if ( is_multisite() && 'administrator' === $role ) {
			add_user_to_blog( get_current_blog_id(), $user, 'administrator' );
		}
		wp_set_current_user( $user );
		return $user;
	}

	private function request( $args, $nonce_action = null ) {
		if ( $nonce_action ) {
			$args['_wpnonce'] = wp_create_nonce( $nonce_action );
		}
		$_GET     = $args;
		$_POST    = $args;
		$_REQUEST = $args;
	}

	/**
	 * Runs an action and tells how it ended: `die` or `redirect`.
	 */
	private function run_action( $handler ) {
		try {
			call_user_func( array( 'Livia_Admin', $handler ) );
		} catch ( WPDieException $e ) {
			return 'die';
		} catch ( RuntimeException $e ) {
			return 'redirect';
		}
		return 'returned';
	}

	/**
	 * Each action of the screen, with its request and its own nonce.
	 */
	private function actions( $post ) {
		return array(
			'handle_connect'         => array( array( 'livia_key' => 'lvwp_bbbbbbbbbbbbbbbbbbbbbbbbbbbbbb' ), 'livia_connect' ),
			'handle_remove'          => array( array( 'livia_restaurant' => 'r1' ), 'livia_remove_r1' ),
			'handle_save_widget'     => array( array( 'livia' => array( 'r1' => array( 'shown' => 1 ) ) ), 'livia_save_widget' ),
			'handle_save_page'       => array(
				array(
					'livia' => array(
						'r1' => array(
							'shown'   => 1,
							'title'   => 'Autre',
							'slug'    => 'autre',
							'page_on' => 1,
						),
					),
				),
				'livia_save_page',
			),
			'handle_trash'           => array(
				array(
					'post'             => $post,
					'livia_restaurant' => 'r1',
				),
				'livia_trash_r1_' . $post,
			),
			'handle_save_experience' => array(
				array(
					'livia_xp_on' => 1,
					'livia'       => array(
						'r1' => array(
							'shown'   => 1,
							'xp_from' => '20',
						),
					),
				),
				'livia_save_experience',
			),
			'handle_add_menu'        => array( array(), 'livia_add_menu' ),
			'handle_save_advanced'   => array( array( 'livia_page_styles' => 'isolated' ), 'livia_save_advanced' ),
			'handle_purge_cache'     => array( array(), 'livia_purge_cache' ),
		);
	}

	private function assert_nothing_changed( $post ) {
		$this->assertCount( 0, $this->calls );
		$this->assertSame( array( 'r1' ), array_keys( Livia_Service::restaurants() ) );
		$this->assertSame( '', Livia_Service::restaurant( 'r1' )['title'] );
		$this->assertTrue( Livia_Service::settings()['site_on'] );
		$this->assertFalse( Livia_Service::settings()['xp_on'] );
		$this->assertSame( 'inherit', Livia_Service::settings()['page_styles'] );
		$this->assertSame( 15, Livia_Service::restaurant( 'r1' )['xp_from'] );
		$this->assertSame( 'publish', get_post_status( $post ) );
	}

	public function test_a_user_without_manage_options_is_refused() {
		$this->as_role( 'editor' );
		$post = self::factory()->post->create( array( 'post_type' => 'page' ) );
		foreach ( $this->actions( $post ) as $handler => $action ) {
			$this->request( $action[0], $action[1] );
			$this->assertSame( 'die', $this->run_action( $handler ), $handler );
		}
		$this->assert_nothing_changed( $post );
	}

	public function test_an_action_without_its_own_nonce_is_refused() {
		$this->as_role( 'administrator' );
		$post    = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$actions = $this->actions( $post );
		$nonces  = array_column( $actions, 1 );
		foreach ( $actions as $handler => $action ) {
			$this->request( $action[0] );
			$this->assertSame( 'die', $this->run_action( $handler ), $handler . ' without a nonce' );
			// The nonce of another action, as another tab's form would send it.
			$other = $nonces[ ( array_search( $action[1], $nonces, true ) + 1 ) % count( $nonces ) ];
			$this->request( $action[0], $other );
			$this->assertSame( 'die', $this->run_action( $handler ), $handler . ' with ' . $other );
		}
		$this->request( array( 'livia_restaurant' => 'r2' ), 'livia_remove_r1' );
		$this->assertSame( 'die', $this->run_action( 'handle_remove' ), 'the nonce of another restaurant' );
		$this->assert_nothing_changed( $post );
	}

	public function test_a_second_restaurant_is_added_and_a_key_already_connected_is_refused() {
		$user                                      = $this->as_role( 'administrator' );
		$this->answers['/wordpress/v1/connection'] = function () {
			return array(
				200,
				array(
					'restaurant' => array(
						'id'   => false !== strpos( end( $this->calls ), self::KEY2 ) ? 'rst_2' : 'rst_1',
						'name' => 'La Terrasse Lumière',
					),
					'widgets'    => array(),
				),
			);
		};
		$this->request( array( 'livia_key' => self::KEY2 ), 'livia_connect' );
		$this->assertSame( 'redirect', $this->run_action( 'handle_connect' ) );
		$this->assertSame( array( 'r1', 'r2' ), array_keys( Livia_Service::restaurants() ) );
		$this->assertSame( 'La Terrasse Lumière', Livia_Service::name( 'r2' ) );

		$this->request( array( 'livia_key' => self::KEY2 ), 'livia_connect' );
		$this->assertSame( 'redirect', $this->run_action( 'handle_connect' ) );
		$this->assertStringContainsString( 'already connected', get_transient( 'livia_notice_' . $user )['key_error'] );

		// Another key of a restaurant already connected.
		$this->request( array( 'livia_key' => 'lvwp_dddddddddddddddddddddddddddddd' ), 'livia_connect' );
		$this->assertSame( 'redirect', $this->run_action( 'handle_connect' ) );
		$this->assertStringContainsString( 'already connected', get_transient( 'livia_notice_' . $user )['key_error'] );
		$this->assertCount( 2, Livia_Service::restaurants() );
	}

	public function test_two_restaurants_share_a_page_under_only_one_screen_each() {
		$this->as_role( 'administrator' );
		$this->connect_second();
		$page = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$this->request(
			array(
				'livia_site_on' => 1,
				'livia'         => array(
					'r1' => array(
						'shown'        => 1,
						'bubble_on'    => 1,
						'site_widget'  => '',
						'phone_widget' => 'wdg_other',
						'site_rule'    => 'only',
						'site_pages'   => array( $page ),
					),
					'r2' => array(
						'shown'       => 1,
						'bubble_on'   => 1,
						'site_widget' => 'wdg_t_site',
						'site_rule'   => 'only',
						'site_pages'  => array( $page ),
					),
				),
			),
			'livia_save_widget'
		);
		$this->assertSame( 'redirect', $this->run_action( 'handle_save_widget' ) );
		$this->assertSame( array( $page ), Livia_Service::restaurant( 'r1' )['site_pages'], 'the phone stays with the first' );
		$this->assertSame( array( $page ), Livia_Service::restaurant( 'r2' )['site_pages'], 'the desktop goes to the second' );

		$this->assertSame( array( 'wdg_t_site', 'wdg_other' ), Livia_Rules::footer_widget( true, Livia_Widgets::bubbles(), $page, 'site', '', array(), false ) );
		$html = $this->screen( 'widget' );
		$this->assertStringNotContainsString( 'class="taken"', $html, 'neither restaurant sees the page greyed' );
		$this->assertSame( 2, substr_count( $html, 'Shown on 1 page' ) );
	}

	public function test_the_widget_tab_saves_each_restaurant_and_only_belongs_to_the_first() {
		$this->as_role( 'administrator' );
		$this->connect_second();
		$page = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$free = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$this->request(
			array(
				'tab'   => 'widget',
				'livia' => array(
					'r1' => array(
						'shown'        => 1,
						'bubble_on'    => 1,
						'site_widget'  => 'wdg_other',
						'phone_widget' => 'wdg_site',
						'site_rule'    => 'only',
						'site_pages'   => array( $page ),
					),
					'r2' => array(
						'shown'        => 1,
						'site_widget'  => 'wdg_site',
						'phone_widget' => 'wdg_site',
						'site_rule'    => 'only',
						'site_pages'   => array( $page, $free ),
					),
				),
			),
			'livia_save_widget'
		);
		$this->assertSame( 'redirect', $this->run_action( 'handle_save_widget' ) );

		$r1 = Livia_Service::restaurant( 'r1' );
		$r2 = Livia_Service::restaurant( 'r2' );
		$this->assertFalse( Livia_Service::settings()['site_on'], 'an unchecked switch posts nothing' );
		$this->assertSame( 'wdg_other', $r1['site_widget'] );
		$this->assertSame( 'wdg_site', $r1['phone_widget'] );
		$this->assertSame( '', $r2['phone_widget'], 'a phone widget of another restaurant is refused' );
		$this->assertSame( array( $page ), $r1['site_pages'] );
		$this->assertFalse( $r2['bubble_on'] );
		$this->assertSame( '', $r2['site_widget'], 'a bubble of another restaurant is refused' );
		$this->assertSame( array( $free ), $r2['site_pages'], 'a page another restaurant holds under "Only" is dropped' );

		$this->request(
			array(
				'livia_site_on' => 1,
				'livia'         => array(
					'r2' => array(
						'shown'       => 1,
						'bubble_on'   => 1,
						'site_widget' => 'wdg_t_site',
						'site_rule'   => 'only',
						'site_pages'  => array( $page, $free ),
					),
				),
			),
			'livia_save_widget'
		);
		$this->assertSame( 'redirect', $this->run_action( 'handle_save_widget' ) );
		$this->assertSame( array( $free ), Livia_Service::restaurant( 'r2' )['site_pages'], 'the page the first restaurant holds stays with it' );
		$this->assertSame( 'wdg_other', Livia_Service::restaurant( 'r1' )['site_widget'], 'a restaurant the tab did not post keeps its settings' );
	}

	public function test_the_page_tab_publishes_each_restaurant_s_page_and_turns_one_off() {
		$user = $this->as_role( 'administrator' );
		$this->connect_second();
		$taken = self::factory()->post->create(
			array(
				'post_type' => 'page',
				'post_name' => 'prise',
			)
		);
		$this->request(
			array(
				'tab'   => 'page',
				'livia' => array(
					'r1' => array(
						'shown'       => 1,
						'page_on'     => 1,
						'slug'        => 'reservation-groupe',
						'page_widget' => 'wdg_page',
					),
					'r2' => array(
						'shown'       => 1,
						'page_on'     => 1,
						'slug'        => 'prise',
						'page_widget' => 'wdg_page',
					),
				),
			),
			'livia_save_page'
		);
		$this->assertSame( 'redirect', $this->run_action( 'handle_save_page' ) );
		$brasserie = Livia_Group_Page::page( 'r1' );
		$this->assertSame( 'publish', $brasserie->post_status );
		$this->assertSame( 'Group booking, Brasserie Lumière', $brasserie->post_title );
		$this->assertNull( Livia_Group_Page::page( 'r2' ), 'an address taken stops this page only' );
		$this->assertSame( 'prise', get_transient( 'livia_notice_' . $user )['restaurants']['r2']['slug_value'] );
		$this->assertFalse( get_transient( 'livia_notice_' . $user )['saved'], 'an address taken is no saved tab' );
		$this->assertSame( '', Livia_Service::restaurant( 'r2' )['page_widget'], 'a widget of another restaurant is refused' );
		$this->assertSame( 'publish', get_post_status( $taken ) );

		$this->request(
			array(
				'livia' => array(
					'r1' => array(
						'shown' => 1,
						'slug'  => 'reservation-groupe',
					),
				),
			),
			'livia_save_page'
		);
		$this->assertSame( 'redirect', $this->run_action( 'handle_save_page' ) );
		$this->assertSame( 'draft', get_post_status( $brasserie->ID ) );
		$this->assertSame( 'reservation-groupe', get_post( $brasserie->ID )->post_name );
		$this->assertTrue( get_transient( 'livia_notice_' . $user )['saved'] );
	}

	private function save_page_tab( $restaurant, $fields ) {
		$this->request(
			array(
				'tab'   => 'page',
				'livia' => array( $restaurant => array_merge( array( 'shown' => 1 ), $fields ) ),
			),
			'livia_save_page'
		);
		$this->assertSame( 'redirect', $this->run_action( 'handle_save_page' ) );
	}

	public function test_a_page_taken_off_the_site_keeps_the_address_typed_for_it() {
		$user = $this->as_role( 'administrator' );
		$this->connect_second();
		self::factory()->post->create(
			array(
				'post_type' => 'page',
				'post_name' => 'prise',
			)
		);
		$this->save_page_tab( 'r2', array( 'slug' => 'ma-terrasse' ) );
		$this->assertSame( 'ma-terrasse', Livia_Service::restaurant( 'r2' )['slug'], 'a page left off still saves its address' );
		$this->assertTrue( get_transient( 'livia_notice_' . $user )['saved'] );
		$this->assertMatchesRegularExpression( '#name="livia\[r2\]\[slug\]"[^>]*value="ma-terrasse"#', $this->screen( 'page' ) );

		$this->save_page_tab( 'r2', array( 'slug' => 'prise' ) );
		$notice = get_transient( 'livia_notice_' . $user );
		$this->assertSame( 'ma-terrasse', Livia_Service::restaurant( 'r2' )['slug'], 'an address taken is not kept' );
		$this->assertSame( 'prise', $notice['restaurants']['r2']['slug_value'] );
		$this->assertFalse( $notice['saved'] );
	}

	public function test_a_draft_page_comes_back_at_the_address_saved_for_it() {
		$this->as_role( 'administrator' );
		$this->connect_second();
		$this->save_page_tab(
			'r1',
			array(
				'page_on' => 1,
				'slug'    => 'ancienne',
			)
		);
		$page = Livia_Group_Page::page( 'r1' );
		$this->save_page_tab( 'r1', array( 'slug' => 'nouvelle' ) );
		$this->assertSame( 'draft', get_post_status( $page->ID ) );
		$this->assertMatchesRegularExpression( '#name="livia\[r1\]\[slug\]"[^>]*value="nouvelle"#', $this->screen( 'page' ), 'the draft shows the address saved after its own' );

		$this->save_page_tab(
			'r1',
			array(
				'page_on' => 1,
				'slug'    => 'nouvelle',
			)
		);
		$this->assertSame( 'publish', get_post_status( $page->ID ) );
		$this->assertSame( 'nouvelle', get_post( $page->ID )->post_name );
	}

	public function test_a_page_taken_off_at_its_own_address_is_no_conflict_and_an_online_page_can_move() {
		$user = $this->as_role( 'administrator' );
		$this->connect_second();
		$this->save_page_tab(
			'r1',
			array(
				'page_on' => 1,
				'slug'    => 'ancienne',
			)
		);
		$page = Livia_Group_Page::page( 'r1' );
		$this->save_page_tab(
			'r1',
			array(
				'page_on' => 1,
				'slug'    => 'nouvelle',
			)
		);
		$this->assertSame( 'nouvelle', get_post( $page->ID )->post_name, 'an online page moves to its new address' );

		$this->save_page_tab( 'r1', array( 'slug' => 'nouvelle' ) );
		$this->assertSame( 'draft', get_post_status( $page->ID ) );
		$this->assertTrue( get_transient( 'livia_notice_' . $user )['saved'], 'the page does not conflict with its own address' );
	}

	public function test_another_restaurant_s_group_page_is_never_trashed() {
		$this->as_role( 'administrator' );
		$this->connect_second();
		$page = Livia_Group_Page::publish( 'r2', 'groupe-terrasse', 'Terrasse' )['page'];
		$this->request(
			array(
				'post'             => $page->ID,
				'livia_restaurant' => 'r1',
			),
			'livia_trash_r1_' . $page->ID
		);
		$this->assertSame( 'redirect', $this->run_action( 'handle_trash' ) );
		$this->assertSame( 'publish', get_post_status( $page->ID ) );
	}

	public function test_removing_the_restaurant_of_the_update_key_forgets_the_update() {
		$this->as_role( 'administrator' );
		$this->connect_second();
		set_transient( Livia_Updater::TRANSIENT, array( 'remote' => null ), 60 );
		$this->request( array( 'livia_restaurant' => 'r1' ), 'livia_remove_r1' );
		$this->assertSame( 'redirect', $this->run_action( 'handle_remove' ) );
		$this->assertSame( array( 'r2' ), array_keys( Livia_Service::restaurants() ) );
		$this->assertSame( self::KEY2, Livia_Service::update_key() );
		$this->assertFalse( get_transient( Livia_Updater::TRANSIENT ) );
	}

	public function test_the_trash_needs_the_right_to_delete_the_content() {
		$this->as_role( 'administrator' );
		$post = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$deny = static function ( $caps, $cap, $user_id, $args ) use ( $post ) {
			return 'delete_post' === $cap && (int) $args[0] === $post ? array( 'do_not_allow' ) : $caps;
		};
		add_filter( 'map_meta_cap', $deny, 10, 4 );
		$this->request(
			array(
				'post'             => $post,
				'livia_restaurant' => 'r1',
			),
			'livia_trash_r1_' . $post
		);
		$result = $this->run_action( 'handle_trash' );
		remove_filter( 'map_meta_cap', $deny, 10 );

		$this->assertSame( 'die', $result );
		$this->assertSame( 'publish', get_post_status( $post ) );
	}

	public function test_the_trash_frees_the_address() {
		$this->as_role( 'administrator' );
		$post = self::factory()->post->create(
			array(
				'post_type' => 'page',
				'post_name' => 'reservation-groupe',
			)
		);
		$this->request(
			array(
				'post'             => $post,
				'livia_restaurant' => 'r1',
			),
			'livia_trash_r1_' . $post
		);
		$this->assertSame( 'redirect', $this->run_action( 'handle_trash' ) );
		$this->assertSame( 'trash', get_post_status( $post ) );
		$this->assertSame( $post, Livia_Service::restaurant( 'r1' )['replaced_id'] );
	}

	public function test_a_media_file_is_not_trashed_without_a_media_trash() {
		$this->assertFalse( MEDIA_TRASH );
		$user  = $this->as_role( 'administrator' );
		$media = self::factory()->attachment->create( array( 'post_name' => 'reservation-groupe' ) );
		$state = get_post_status( $media );
		$this->request(
			array(
				'post'             => $media,
				'livia_restaurant' => 'r1',
			),
			'livia_trash_r1_' . $media
		);
		$this->assertSame( 'redirect', $this->run_action( 'handle_trash' ) );

		$this->assertSame( $state, get_post_status( $media ) );
		$this->assertSame( 0, Livia_Service::restaurant( 'r1' )['replaced_id'] );
		$notice = get_transient( 'livia_notice_' . $user );
		$this->assertStringContainsString( 'no trash for media files', $notice['restaurants']['r1']['trash_error'] );
	}

	public function test_a_translated_default_title_is_cleaned_like_a_typed_one() {
		$translate = static function ( $translation, $text ) {
			return 'Group booking' === $text ? '<img src=x onerror=alert(1)>Réservation <b>de groupe</b>' : $translation;
		};
		add_filter( 'gettext', $translate, 10, 2 );
		$title = Livia_Admin::default_title();
		remove_filter( 'gettext', $translate, 10 );
		$this->assertSame( 'Réservation de groupe', $title );
	}

	public function test_the_screen_shows_each_tab_for_each_restaurant() {
		$this->as_role( 'administrator' );
		$page = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'La Terrasse',
			)
		);
		Livia_Service::save_restaurant( 'r1', array( 'site_widget' => 'wdg_site' ) );
		$this->connect_second(
			array(
				'site_widget' => 'wdg_t_site',
				'site_rule'   => 'only',
				'site_pages'  => array( $page ),
			)
		);

		$widget = $this->screen( 'widget' );
		$this->assertStringContainsString( 'name="action" value="livia_save_widget"', $widget );
		$this->assertStringNotContainsString( 'livia_save_page', $widget );
		$this->assertStringContainsString( 'name="livia[r1][site_widget]"', $widget );
		$this->assertStringContainsString( 'name="livia[r2][site_widget]"', $widget );
		$this->assertStringContainsString( 'name="livia[r1][phone_widget]"', $widget );
		$this->assertStringContainsString( 'name="livia[r2][phone_widget]"', $widget );
		$this->assertStringContainsString( 'The same widget', $widget );
		$this->assertMatchesRegularExpression( '#<label class="taken"><input type="checkbox" disabled>La Terrasse<em>· La Terrasse Lumière</em>#', $widget, 'greyed for the other restaurant, with its name' );
		$this->assertStringContainsString( 'Réserver pour un groupe', $widget, 'the in-page widgets are listed' );
		$this->assertStringContainsString( 'aria-current="page"', $widget );
		$this->assertStringContainsString( 'data-livia-dialog', $widget );
		$this->assertStringContainsString( 'Livia is not answering right now', $widget );

		$page_tab = $this->screen( 'page' );
		$this->assertStringContainsString( 'name="action" value="livia_save_page"', $page_tab );
		$this->assertStringContainsString( 'name="livia[r2][slug]"', $page_tab );
		$this->assertStringContainsString( 'value="group-booking-la-terrasse-lumiere"', $page_tab );
		$this->assertStringContainsString( 'value="Group booking, La Terrasse Lumière"', $page_tab );
		$this->assertStringContainsString( '<input type="checkbox" name="livia[r2][page_on]" value="1" >', $page_tab, 'with several restaurants, a page never created waits for its switch' );

		delete_option( 'livia_restaurants' );
		$this->connect();
		$this->assertStringContainsString( 'name="livia[r1][page_on]" value="1"  checked=\'checked\'', $this->screen( 'page' ), 'a site\'s only restaurant publishes its page at the first save' );
	}

	public function test_the_widget_tab_shows_no_bubble_as_chosen_when_the_desktop_one_is_gone() {
		$this->as_role( 'administrator' );
		$alert = 'The widget chosen for desktop no longer exists in Livia';
		$this->connect(
			array(
				'site_widget'  => 'wdg_gone',
				'phone_widget' => 'wdg_other',
			)
		);
		$html = $this->screen( 'widget' );
		$this->assertStringContainsString( $alert, $html );
		$this->assertMatchesRegularExpression( '#<option value=""  selected=\'selected\' data-name="Chosen widget not found"#', $html );
		$this->assertDoesNotMatchRegularExpression( '#<option value="wdg_site"\s+selected#', $html );
		$this->assertStringContainsString( 'Shown on', $html, 'the phone bubble still shows' );
		$this->assertStringContainsString( 'this restaurant&#039;s bubble no longer shows on desktop. ', $html );

		$this->connect( array( 'site_widget' => 'wdg_gone' ) );
		$this->assertStringContainsString( 'this restaurant&#039;s bubble no longer shows on desktop or on phone.', $this->screen( 'widget' ), 'the phone took the desktop widget' );

		$this->connect(
			array(
				'site_widget' => 'wdg_gone',
				'bubble_on'   => false,
			)
		);
		$html = $this->screen( 'widget' );
		$this->assertStringContainsString( 'Choose another one before you turn this restaurant&#039;s bubble on', $html, 'the switch keeps it off' );
		$this->assertStringNotContainsString( 'no longer shows', $html );

		$this->connect( array( 'site_widget' => 'wdg_site' ) );
		$html = $this->screen( 'widget' );
		$this->assertStringNotContainsString( $alert, $html );
		$this->assertMatchesRegularExpression( '#<option value="wdg_site"\s+selected#', $html );

		$this->connect();
		$this->assertStringContainsString( 'data-name="No bubble chosen"', $this->screen( 'widget' ), 'nothing saved yet' );
	}

	public function test_the_page_tab_warns_only_of_a_chosen_widget_deleted_in_livia() {
		$this->as_role( 'administrator' );
		$alert = 'The chosen widget no longer exists in Livia';

		$connections                       = get_option( 'livia_connections' );
		$connections['r1']['pageWidgetId'] = 'wdg_site';
		update_option( 'livia_connections', $connections );
		$this->assertStringNotContainsString( $alert, $this->screen( 'page' ), 'the Livia booking page\'s widget is a bubble' );

		$connections['r1']['widgets'] = array_slice( $connections['r1']['widgets'], 1 );
		update_option( 'livia_connections', $connections );
		$html = $this->screen( 'page' );
		$this->assertStringNotContainsString( $alert, $html, 'no embedded conversation, nothing deleted' );
		$this->assertStringContainsString( 'This restaurant has no embedded conversation: the page shows the widget of your Livia booking page.', $html );

		$this->connect( array( 'page_widget' => 'wdg_gone' ) );
		$this->assertStringContainsString( $alert, $this->screen( 'page' ) );
	}

	public function test_without_a_choice_the_page_keeps_the_livia_booking_page_s_widget() {
		$this->as_role( 'administrator' );
		$connections                       = get_option( 'livia_connections' );
		$connections['r1']['pageWidgetId'] = 'wdg_site';
		update_option( 'livia_connections', $connections );

		$html = $this->screen( 'page' );
		$this->assertMatchesRegularExpression( '#<option value=""  selected=\'selected\'\s+data-name="Widget of your Livia booking page"\s+data-format="· Floating bubble"#', $html, 'offered and kept as it is' );
		$this->assertStringContainsString( '<option value="wdg_page"', $html );

		$this->request(
			array(
				'tab'   => 'page',
				'livia' => array(
					'r1' => array(
						'shown'       => 1,
						'slug'        => 'reservation-groupe',
						'page_widget' => '',
					),
				),
			),
			'livia_save_page'
		);
		$this->assertSame( 'redirect', $this->run_action( 'handle_save_page' ) );
		$this->assertSame( '', Livia_Service::restaurant( 'r1' )['page_widget'] );
		$this->assertSame( 'wdg_site', Livia_Service::page_widget( 'r1' ), 'saved unchanged, the page keeps its widget' );
	}

	public function test_the_texts_agree_with_the_bubble_and_the_page_waiting_for_its_save() {
		$this->as_role( 'administrator' );
		self::factory()->post->create( array( 'post_type' => 'page' ) );
		Livia_Service::save_restaurant( 'r1', array( 'site_widget' => 'wdg_site' ) );
		$this->assertStringContainsString( 'Shown on 1 page', $this->screen( 'widget' ) );
		$this->assertStringContainsString( 'Address available. The page goes online when you save.', $this->screen( 'page' ) );

		load_textdomain( 'livia', LIVIA_DIR . 'languages/livia-fr_FR.mo' );
		try {
			/* translators: %d: number of pages. */
			$this->assertSame( 'Affichée sur %d page', _n( 'Shown on %d page', 'Shown on %d pages', 1, 'livia' ) );
			/* translators: %d: number of pages. */
			$this->assertSame( 'Affichée sur %d pages', _n( 'Shown on %d page', 'Shown on %d pages', 4, 'livia' ) );
			$this->assertSame( 'Adresse disponible. La page se publie à l\'enregistrement.', __( 'Address available. The page goes online when you save.', 'livia' ) );
		} finally {
			unload_textdomain( 'livia' );
		}
	}

	public function test_a_disabled_site_section_shows_no_page_count() {
		$this->as_role( 'administrator' );
		self::factory()->post->create( array( 'post_type' => 'page' ) );
		Livia_Service::save_restaurant( 'r1', array( 'site_widget' => 'wdg_site' ) );
		Livia_Service::save_settings( array( 'site_on' => false ) );
		$this->assertStringNotContainsString( 'Shown on', $this->screen( 'widget' ) );
	}

	public function test_without_restaurant_the_tabs_are_locked() {
		$this->as_role( 'administrator' );
		delete_option( 'livia_restaurants' );
		$html = $this->screen( 'page' );
		$this->assertStringContainsString( 'value="livia_connect"', $html );
		$this->assertStringContainsString( 'aria-disabled="true"', $html );
		$this->assertStringNotContainsString( 'livia_save_', $html );
	}

	public function test_the_page_tab_saves_each_restaurant_s_display_mode() {
		$this->as_role( 'administrator' );
		$page_tab = $this->screen( 'page' );
		$this->assertStringContainsString( 'name="livia[r1][page_layout]" value="theme"  checked=\'checked\'', $page_tab, 'inside the theme by default' );
		$this->assertStringContainsString( 'data-home="' . home_url( '/' ) . '"', $page_tab );
		Livia_Service::save_settings( array( 'purge_due' => false ) );

		$this->save_page_tab(
			'r1',
			array(
				'slug'        => 'reservation-groupe',
				'page_on'     => 1,
				'page_layout' => 'landing',
			)
		);
		$this->assertSame( 'landing', Livia_Service::restaurant( 'r1' )['page_layout'] );
		$this->assertTrue( Livia_Service::settings()['purge_due'], 'the screen asks to clear the cache, nothing is cleared' );
		$this->save_page_tab(
			'r1',
			array(
				'slug'        => 'reservation-groupe',
				'page_on'     => 1,
				'page_layout' => 'anything',
			)
		);
		$this->assertSame( 'theme', Livia_Service::restaurant( 'r1' )['page_layout'] );
	}

	public function test_the_page_tab_says_when_conversations_count_with_the_livia_booking_page() {
		$this->as_role( 'administrator' );
		$text = 'Conversations from this page are counted together with those of your Livia booking page.';
		$this->assertMatchesRegularExpression( '#<p class="lv-help" data-livia-follow>' . preg_quote( $text, '#' ) . '#', $this->screen( 'page' ), 'no embedded conversation chosen' );

		Livia_Service::save_restaurant( 'r1', array( 'page_widget' => 'wdg_page' ) );
		$this->assertMatchesRegularExpression( '#<p class="lv-help" data-livia-follow hidden>#', $this->screen( 'page' ), 'its own conversation, the text waits for the empty choice' );
	}

	public function test_the_advanced_tab_saves_the_styles_and_lists_the_tools() {
		$user     = $this->as_role( 'administrator' );
		$advanced = $this->screen( 'advanced' );
		$this->assertStringContainsString( 'name="action" value="livia_save_advanced"', $advanced );
		$this->assertStringContainsString( 'name="livia_page_styles" value="inherit"  checked=\'checked\'', $advanced );
		$this->assertStringContainsString( 'No cache or optimisation tool detected on this site.', $advanced );
		$this->assertStringContainsString( 'form="livia-purge-cache"', $advanced );
		$this->assertStringNotContainsString( 'data-livia-open', $advanced, 'no preview on this tab' );
		$this->assertStringNotContainsString( 'data-livia-dialog', $advanced );
		$this->assertTrue( Livia_Service::settings()['purge_due'], 'a site updated to 1.1 is asked to clear its cache' );
		$this->assertStringNotContainsString( 'do not reflect the latest Livia changes', $advanced, 'only with a cache tool' );

		$this->request( array( 'livia_page_styles' => 'isolated' ), 'livia_save_advanced' );
		$this->assertSame( 'redirect', $this->run_action( 'handle_save_advanced' ) );
		$this->assertSame( 'isolated', Livia_Service::settings()['page_styles'] );
		$this->assertStringContainsString( 'styles=isolated', $this->screen( 'page' ), 'the preview follows the saved styles' );

		$this->request( array(), 'livia_purge_cache' );
		$this->assertSame( 'redirect', $this->run_action( 'handle_purge_cache' ) );
		$this->assertSame( array(), get_transient( 'livia_notice_' . $user )['purged'] );
		$this->assertFalse( Livia_Service::settings()['purge_due'] );

		$this->request( array( 'livia_page_styles' => 'inherit' ), 'livia_save_advanced' );
		$this->run_action( 'handle_save_advanced' );
		$this->assertTrue( Livia_Service::settings()['purge_due'], 'a change of styles asks again' );
	}
}
