<?php
/**
 * The booking experience: its settings by restaurant, its blocks, shortcodes and phone bar, its assets and its menu
 * link, all gone while the section is off.
 *
 * @package Livia
 */

final class ExperienceTest extends Livia_TestCase {

	const URL = 'https://bookings.zenchef.test/results?rid=123456&pax={n}';

	/**
	 * The theme before the test.
	 *
	 * @var string
	 */
	private $theme;

	public function set_up() {
		parent::set_up();
		$this->theme = get_stylesheet();
		$this->connect();
		add_filter( 'wp_redirect', array( $this, 'stop_at_redirect' ) );
		$this->new_assets();
	}

	public function tear_down() {
		remove_filter( 'wp_redirect', array( $this, 'stop_at_redirect' ) );
		switch_theme( $this->theme );
		$_GET     = array();
		$_POST    = array();
		$_REQUEST = array();
		$this->new_assets();
		parent::tear_down();
	}

	public function stop_at_redirect( $location ) {
		unset( $location );
		throw new RuntimeException( 'redirect' );
	}

	/**
	 * Forgets the scripts and styles of the previous page.
	 */
	private function new_assets() {
		$GLOBALS['wp_scripts'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- A new page.
		$GLOBALS['wp_styles']  = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- A new page.
		$bubble                = new ReflectionProperty( Livia_Widgets::class, 'bubble_placed' );
		$bubble->setAccessible( true );
		$bubble->setValue( null, false );
	}

	/**
	 * A restaurant's panel, valid, with some fields changed.
	 */
	private function fields( $changes = array() ) {
		return array_merge(
			array(
				'shown'          => '1',
				'xp_from'        => '15',
				'xp_above'       => '40',
				'xp_tool'        => 'zenchef',
				'xp_url'         => self::URL,
				'xp_open'        => 'window',
				'xp_group_dest'  => 'window',
				'xp_event_dest'  => 'contact',
				'xp_email'       => 'evenements@brasserie-lumiere.test',
				'xp_group_title' => 'Préparons votre table de groupe',
			),
			$changes
		);
	}

	/**
	 * Saves the tab as an administrator; returns what it leaves for the next display.
	 */
	private function save( $restaurants, $settings = array( 'livia_xp_on' => '1' ) ) {
		$user = self::factory()->user->create( array( 'role' => 'administrator' ) );
		if ( is_multisite() ) {
			add_user_to_blog( get_current_blog_id(), $user, 'administrator' );
		}
		wp_set_current_user( $user );
		$_POST    = array_merge(
			$settings,
			array(
				'tab'      => 'experience',
				'livia'    => $restaurants,
				'_wpnonce' => wp_create_nonce( 'livia_save_experience' ),
			)
		);
		$_REQUEST = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- The request the handler checks.
		try {
			Livia_Admin::handle_save_experience();
		} catch ( RuntimeException $e ) {
			unset( $e );
		}
		return get_transient( 'livia_notice_' . $user );
	}

	/**
	 * Saves the tab as the restaurateur does, with the section's switch as the screen shows it.
	 */
	private function save_as_shown( $restaurants ) {
		$on = (bool) preg_match( '#name="livia_xp_on" value="1"\s+checked=#', $this->screen( 'experience' ) );
		return $this->save( $restaurants, $on ? array( 'livia_xp_on' => '1' ) : array() );
	}

	private function turn_on( $restaurant = array() ) {
		Livia_Service::save_settings( array( 'xp_on' => true ) );
		Livia_Service::save_restaurant(
			'r1',
			array_merge(
				array(
					'xp_url'   => self::URL,
					'xp_email' => 'evenements@brasserie-lumiere.test',
				),
				$restaurant
			)
		);
	}

	public function test_each_restaurant_keeps_its_own_settings_and_the_number_of_guests_in_its_address() {
		$this->connect_second();
		$this->save(
			array(
				'r1' => $this->fields( array( 'xp_url' => '/nos-tables/#reserver' ) ),
				'r2' => $this->fields(
					array(
						'xp_from'       => '10',
						'xp_above'      => '30',
						'xp_open'       => 'tab',
						'xp_event_dest' => 'tool',
						'xp_event_url'  => 'https://events.example.test/?pax={n}&lang=fr',
						'xp_phone'      => '01 23 45 67 89',
					)
				),
			)
		);
		$r1 = Livia_Service::restaurant( 'r1' );
		$r2 = Livia_Service::restaurant( 'r2' );
		$this->assertTrue( Livia_Service::settings()['xp_on'] );
		$this->assertSame( home_url( '/nos-tables/#reserver' ), $r1['xp_url'], 'a path of the site becomes its address' );
		$this->assertSame( array( 15, 40 ), array( $r1['xp_from'], $r1['xp_above'] ) );
		$this->assertSame( array( 10, 30 ), array( $r2['xp_from'], $r2['xp_above'] ) );
		$this->assertSame( 'https://events.example.test/?pax={n}&lang=fr', $r2['xp_event_url'], '{n} keeps its braces' );
		$this->assertSame( 'tab', $r2['xp_open'] );
		$this->assertSame( '01 23 45 67 89', $r2['xp_phone'] );
		$this->assertSame( 'Préparons votre table de groupe', $r1['xp_group_title'] );

		$this->save( array( 'r1' => $this->fields() ) );
		$this->assertSame( self::URL, Livia_Service::restaurant( 'r1' )['xp_url'] );
		$this->assertSame( 'tab', Livia_Service::restaurant( 'r2' )['xp_open'], 'a restaurant the tab did not show keeps its settings' );
	}

	public function test_an_address_may_have_an_upper_case_scheme_or_be_a_path_with_the_number_of_guests() {
		$this->save( array( 'r1' => $this->fields( array( 'xp_url' => 'HTTPS://Bookings.Example.test/?pax={n}' ) ) ) );
		$this->assertSame( 'https://Bookings.Example.test/?pax={n}', Livia_Service::restaurant( 'r1' )['xp_url'], 'the scheme is cleaned, the address is kept' );
		$this->save( array( 'r1' => $this->fields( array( 'xp_url' => '/carte/?convives={n}' ) ) ) );
		$this->assertSame( home_url( '/carte/?convives={n}' ), Livia_Service::restaurant( 'r1' )['xp_url'] );
	}

	public function test_an_address_keeps_its_percent_sequences() {
		$url = 'https://tool.example.test/book?venues=a%2Cb&name=caf%C3%A9&pax={n}';
		$this->save( array( 'r1' => $this->fields( array( 'xp_url' => $url ) ) ) );
		$this->assertSame( $url, Livia_Service::restaurant( 'r1' )['xp_url'] );
	}

	public function test_refused_values_keep_the_saved_ones_and_say_why() {
		$this->save( array( 'r1' => $this->fields() ) );
		$text   = self::factory()->post->create();
		$notice = $this->save(
			array(
				'r1' => $this->fields(
					array(
						'xp_from'         => '41',
						'xp_above'        => '40',
						'xp_url'          => 'javascript:alert(1)',
						'xp_email'        => 'pas-un-email',
						'xp_group_widget' => 'wdg_site',
						'xp_table_photo'  => (string) $text,
						'xp_open'         => 'popup',
					)
				),
			)
		);
		$saved  = Livia_Service::restaurant( 'r1' );
		$this->assertSame( array( 15, 40 ), array( $saved['xp_from'], $saved['xp_above'] ) );
		$this->assertSame( self::URL, $saved['xp_url'] );
		$this->assertSame( 'evenements@brasserie-lumiere.test', $saved['xp_email'] );
		$this->assertSame( '', $saved['xp_group_widget'], 'a bubble is no embedded conversation' );
		$this->assertSame( 0, $saved['xp_table_photo'], 'a post is no image' );
		$this->assertSame( 'window', $saved['xp_open'] );
		$this->assertSame( array( 'xp_from', 'xp_url', 'xp_email' ), array_keys( $notice['restaurants']['r1']['xp_errors'] ) );
		$this->assertSame( 'javascript:alert(1)', $notice['restaurants']['r1']['xp_typed']['xp_url'], 'what was typed is shown again' );
		$this->assertSame( '40', $notice['restaurants']['r1']['xp_typed']['xp_above'] );

		$notice = $this->save( array( 'r1' => $this->fields( array( 'xp_url' => '' ) ) ) );
		$this->assertArrayHasKey( 'xp_url', $notice['restaurants']['r1']['xp_errors'] );
		$this->assertSame( self::URL, Livia_Service::restaurant( 'r1' )['xp_url'], 'an empty address is refused and the saved one kept' );
		$this->save(
			array(
				'r1' => $this->fields(
					array(
						'xp_event_dest' => 'tool',
						'xp_event_url'  => 'https://events.example.test/',
					)
				),
			)
		);
		$notice = $this->save(
			array(
				'r1' => $this->fields(
					array(
						'xp_event_dest' => 'tool',
						'xp_event_url'  => '',
					)
				),
			)
		);
		$this->assertArrayHasKey( 'xp_event_url', $notice['restaurants']['r1']['xp_errors'] );
		$this->assertSame( 'https://events.example.test/', Livia_Service::restaurant( 'r1' )['xp_event_url'], 'an empty private hire address is refused and the saved one kept' );

		foreach ( array( 'bookings.zenchef.test', 'pas une adresse' ) as $typed ) {
			$notice = $this->save( array( 'r1' => $this->fields( array( 'xp_url' => $typed ) ) ) );
			$this->assertArrayHasKey( 'xp_url', $notice['restaurants']['r1']['xp_errors'], $typed );
			$this->assertSame( self::URL, Livia_Service::restaurant( 'r1' )['xp_url'], $typed );
		}
		$notice = $this->save(
			array(
				'r1' => $this->fields(
					array(
						'xp_event_dest' => 'tool',
						'xp_event_url'  => 'events.example.test',
					)
				),
			)
		);
		$this->assertArrayHasKey( 'xp_event_url', $notice['restaurants']['r1']['xp_errors'] );
		$this->assertFalse( $notice['saved'], 'a refused value is no saved tab' );

		foreach ( array( array( '1', '40' ), array( '15', '999' ), array( '', '40' ) ) as $thresholds ) {
			$notice = $this->save(
				array(
					'r1' => $this->fields(
						array(
							'xp_from'  => $thresholds[0],
							'xp_above' => $thresholds[1],
						)
					),
				)
			);
			$this->assertArrayHasKey( 'xp_from', $notice['restaurants']['r1']['xp_errors'], implode( '/', $thresholds ) );
		}
	}

	public function test_each_path_needs_its_address() {
		$notice = $this->save(
			array(
				'r1' => $this->fields(
					array(
						'xp_url'        => '',
						'xp_event_dest' => 'tool',
						'xp_email'      => '',
					)
				),
			)
		);
		$this->assertSame( array( 'xp_url', 'xp_event_url' ), array_keys( $notice['restaurants']['r1']['xp_errors'] ), 'the e-mail is asked only to contact the restaurant' );
		$notice = $this->save( array( 'r1' => $this->fields( array( 'xp_email' => '' ) ) ) );
		$this->assertSame( array( 'xp_email' ), array_keys( $notice['restaurants']['r1']['xp_errors'] ) );
		$this->assertSame( array(), $this->save( array( 'r1' => $this->fields() ) )['restaurants'] );
	}

	public function test_the_section_turns_on_only_once_every_restaurant_is_set() {
		$this->save( array( 'r1' => $this->fields( array( 'xp_url' => '' ) ) ) );
		$this->assertTrue( Livia_Service::settings()['xp_on'], 'the switch keeps what was asked' );
		$this->assertFalse( Livia_Experience::is_on(), 'a refused booking address leaves no tool to open' );
		$this->assertSame( array( 'r1' => array( 'xp_url' ) ), Livia_Experience::to_complete() );

		$this->assertTrue( $this->save( array( 'r1' => $this->fields() ) )['saved'] );
		$this->assertTrue( Livia_Experience::is_on() );

		$this->connect_second();
		$this->assertFalse( Livia_Experience::is_on(), 'a restaurant connected later is not set yet' );
		$this->assertSame( '', do_shortcode( '[livia_reserver]' ) );
		$this->assertTrue( $this->save( array( 'r1' => $this->fields() ) )['saved'], 'the tab says what is left to fill' );
		$this->assertFalse( Livia_Experience::is_on() );
		$this->assertSame( array( 'r2' => array( 'xp_url', 'xp_email' ) ), Livia_Experience::to_complete() );

		$this->save(
			array(
				'r2' => $this->fields(
					array(
						'xp_event_dest' => 'tool',
						'xp_event_url'  => '',
					)
				),
			)
		);
		$this->assertFalse( Livia_Experience::is_on() );
		$this->assertSame( array( 'r2' => array( 'xp_event_url' ) ), Livia_Experience::to_complete() );
	}

	public function test_a_section_off_for_a_restaurant_to_fill_comes_back_on_its_own_once_it_is_filled() {
		$this->turn_on();
		$this->assertTrue( Livia_Experience::is_on() );
		$this->connect_second();
		$this->assertFalse( Livia_Experience::is_on(), 'a restaurant connected later is not set yet' );
		$this->assertSame( '', do_shortcode( '[livia_reserver]' ) );

		$html = $this->screen( 'experience' );
		$this->assertMatchesRegularExpression( '#name="livia_xp_on" value="1"\s+checked=#', $html, 'the switch shows what was asked' );
		$this->assertStringContainsString( 'lv-tag t-wait', $html, 'the status says the experience is off' );
		$this->assertStringContainsString( 'The experience stays off until you fill in La Terrasse Lumière: ', $html );

		$this->assertTrue( $this->save_as_shown( array( 'r2' => $this->fields() ) )['saved'] );
		$this->assertTrue( Livia_Experience::is_on(), 'every restaurant set, the section is on again' );
		$this->assertStringContainsString( 'lv-tag t-ok', $this->screen( 'experience' ) );

		$this->save( array( 'r2' => $this->fields() ), array() );
		$this->assertFalse( Livia_Experience::is_on(), 'turned off, it stays off' );
		$this->assertDoesNotMatchRegularExpression( '#name="livia_xp_on" value="1"\s+checked=#', $this->screen( 'experience' ) );
	}

	public function test_the_choice_order_lists_connected_restaurants_and_leaves_the_bubbles_order() {
		$this->connect_second();
		$this->save(
			array(),
			array(
				'livia_xp_on'    => '1',
				'livia_xp_order' => array( 'r2', 'r9', 'r1' ),
			)
		);
		$this->assertSame( array( 'r2', 'r1' ), Livia_Service::settings()['xp_order'] );
		$this->assertSame( array( 'r1', 'r2' ), array_keys( Livia_Service::restaurants() ), 'the first restaurant still wins a page\'s bubble' );
		$this->assertSame( array( 'r2', 'r1' ), wp_list_pluck( Livia_Experience::config()['restaurants'], 'id' ) );
	}

	public function test_the_window_reads_the_group_page_only_while_published_and_a_conversation_livia_offers() {
		$this->turn_on( array( 'xp_group_dest' => 'page' ) );
		$config = Livia_Experience::restaurant_config( 'r1' );
		$this->assertSame( '', $config['page'] );
		$this->assertSame( 'wdg_page', $config['xp_group_widget'], 'the group page\'s widget when none is chosen' );
		$this->assertArrayNotHasKey( 'key', $config );

		$page = Livia_Group_Page::publish( 'r1', 'reservation-groupe', 'Réservation de groupe' )['page'];
		$this->assertSame( get_permalink( $page ), Livia_Experience::restaurant_config( 'r1' )['page'] );
		Livia_Group_Page::unpublish( 'r1' );
		$this->assertSame( '', Livia_Experience::restaurant_config( 'r1' )['page'] );

		Livia_Service::save_restaurant( 'r1', array( 'xp_group_widget' => 'wdg_gone' ) );
		$this->assertSame( 'wdg_page', Livia_Experience::group_widget( 'r1' ) );
	}

	public function test_the_group_path_names_the_assistant_and_the_restaurant() {
		$this->turn_on();
		$text = 'Brasserie Lumière\'s assistant, prepares your table with you: date, menus, space. She answers you right away, 7 days a week.';
		$this->assertSame( 'Livia, ' . $text, Livia_Experience::restaurant_config( 'r1' )['group_text'] );

		$connections                                      = get_option( 'livia_connections' );
		$connections['r1']['widgets'][0]['assistantName'] = 'Camille';
		update_option( 'livia_connections', $connections );
		$this->assertSame( 'Camille, ' . $text, Livia_Experience::restaurant_config( 'r1' )['group_text'], 'the conversation\'s own assistant' );

		load_textdomain( 'livia', LIVIA_DIR . 'languages/livia-fr_FR.mo' );
		$french = static function () {
			return 'fr_FR';
		};
		add_filter( 'locale', $french );
		try {
			$this->assertSame( 'Camille, l\'assistante de Brasserie Lumière, prépare votre table avec vous : date, menus, espace. Elle vous répond tout de suite, 7j/7.', Livia_Experience::restaurant_config( 'r1' )['group_text'] );
		} finally {
			remove_filter( 'locale', $french );
			unload_textdomain( 'livia' );
		}
	}

	public function test_the_assistant_is_the_one_of_the_conversation_the_window_opens() {
		$this->turn_on(
			array(
				'xp_group_dest'   => 'page',
				'xp_group_widget' => 'wdg_chat',
			)
		);
		$connections                                      = get_option( 'livia_connections' );
		$connections['r1']['widgets'][0]['assistantName'] = 'Camille';
		$connections['r1']['widgets'][]                   = array(
			'id'            => 'wdg_chat',
			'name'          => 'Conversation',
			'format'        => 'inline',
			'fullscreen'    => false,
			'assistantName' => 'Alix',
		);
		update_option( 'livia_connections', $connections );

		$this->assertStringStartsWith( 'Alix, ', Livia_Experience::restaurant_config( 'r1' )['group_text'], 'page not published: the window opens the conversation' );
		Livia_Group_Page::publish( 'r1', 'reservation-groupe', 'Réservation de groupe' );
		$this->assertStringStartsWith( 'Camille, ', Livia_Experience::restaurant_config( 'r1' )['group_text'], 'page published: the page opens' );
	}

	public function test_without_an_embedded_conversation_the_window_path_names_the_assistant_of_the_page_it_opens() {
		$this->turn_on( array( 'xp_group_dest' => 'window' ) );
		$connections                                      = get_option( 'livia_connections' );
		$connections['r1']['widgets'][0]['format']        = 'bubble';
		$connections['r1']['widgets'][0]['assistantName'] = 'Camille';
		update_option( 'livia_connections', $connections );
		$this->assertSame( '', Livia_Experience::group_widget( 'r1' ) );

		Livia_Group_Page::publish( 'r1', 'reservation-groupe', 'Réservation de groupe' );
		$this->assertStringStartsWith( 'Camille, ', Livia_Experience::restaurant_config( 'r1' )['group_text'] );
	}

	public function test_a_value_refused_for_a_restaurant_whose_panel_is_hidden_shows_in_a_strip_naming_it() {
		$this->connect_second();
		$this->turn_on();
		$this->save(
			array(
				'r1' => $this->fields(),
				'r2' => $this->fields( array( 'xp_url' => 'bookings.zenchef.test' ) ),
			)
		);
		$html = $this->screen( 'experience' );
		$this->assertStringContainsString( 'La Terrasse Lumière: a value was refused', $html );
		$this->assertStringNotContainsString( 'Brasserie Lumière: a value was refused', $html );
		$this->assertStringContainsString( 'value="bookings.zenchef.test"', $html, 'the refused value stays in its panel' );
	}

	public function test_the_widget_tab_says_the_phone_bar_takes_the_bubble_s_place_while_it_is_on() {
		$this->turn_on();
		$html = $this->screen( 'widget' );
		$this->assertStringContainsString( 'the bubble gives way to the bar', $html );
		$this->assertStringContainsString( 'data-livia-under-bar', $html, 'the phone preview hides the bubble' );
		Livia_Service::save_settings( array( 'xp_bar' => false ) );
		$html = $this->screen( 'widget' );
		$this->assertStringNotContainsString( 'the bubble gives way to the bar', $html );
		$this->assertStringNotContainsString( 'data-livia-under-bar', $html );
	}

	public function test_the_group_hint_before_a_choice_names_no_restaurant() {
		$this->turn_on();
		$this->assertSame(
			'Livia, the restaurant\'s assistant, prepares your table with you: date, menus, space. She answers you right away, 7 days a week.',
			Livia_Experience::strings()['groupAny']
		);
	}

	public function test_the_group_hint_before_a_choice_uses_the_assistant_the_restaurants_share() {
		$this->turn_on();
		$this->connect_second();
		$name = static function ( $id, $assistant ) {
			$connections                                       = get_option( 'livia_connections' );
			$connections[ $id ]['widgets'][0]['assistantName'] = $assistant;
			update_option( 'livia_connections', $connections );
		};
		$name( 'r1', 'Camille' );
		$name( 'r2', 'Camille' );
		$this->assertStringStartsWith( 'Camille, the restaurant', Livia_Experience::strings()['groupAny'] );
		$name( 'r2', 'Alix' );
		$this->assertStringStartsWith( 'Livia, the restaurant', Livia_Experience::strings()['groupAny'], 'different assistants' );
	}

	public function test_blocks_and_shortcodes_show_only_while_the_section_is_on() {
		$block = static function ( $name, $attrs ) {
			return render_block(
				array(
					'blockName'    => $name,
					'attrs'        => $attrs,
					'innerBlocks'  => array(),
					'innerHTML'    => '',
					'innerContent' => array(),
				)
			);
		};
		$this->assertSame( '', do_shortcode( '[livia_reserver]' ) );
		$this->assertSame( '', do_shortcode( '[livia_convives]' ) );
		$this->assertSame( '', $block( 'livia/reserver', array() ) );
		$this->assertSame( '', $block( 'livia/convives', array() ) );

		$this->turn_on();
		$this->connect_second(
			array(
				'xp_url'   => self::URL,
				'xp_email' => 'contact@terrasse-lumiere.test',
			)
		);
		$button = $block(
			'livia/reserver',
			array(
				'label'      => 'Réserver pour le <b>déjeuner</b>',
				'restaurant' => 'r2',
			)
		);
		$this->assertStringContainsString( '<div class="wp-block-button"><a class="wp-block-button__link wp-element-button livia-reserver" href="#reserver" data-livia-restaurant="r2">Réserver pour le &lt;b&gt;déjeuner&lt;/b&gt;</a></div>', $button );
		$this->assertStringContainsString( 'wp-block-livia-reserver', $button );
		$this->assertStringContainsString( 'data-livia-event', do_shortcode( '[livia_reserver target="event"]' ) );
		$this->assertStringContainsString( '>Book a table</a>', do_shortcode( '[livia_reserver]' ) );
		$this->assertMatchesRegularExpression( '#^<div class="wp-block-button"><a [^>]*livia-reserver[^>]*>Book a table</a></div>$#', do_shortcode( '[livia_reserver]' ), 'the button stays in the theme column' );
		$this->assertStringNotContainsString( 'data-livia-restaurant', do_shortcode( '[livia_reserver restaurant="r9"]' ), 'only a connected restaurant' );

		$this->assertSame( '<div class="livia-convives" data-livia-convives="band" data-livia-restaurant="r1"></div>', do_shortcode( '[livia_convives variant="band" restaurant="r1"]' ) );
		$this->assertStringContainsString( 'data-livia-convives="card"', do_shortcode( '[livia_convives variant="other"]' ) );
		$this->assertStringContainsString( 'data-livia-convives="card"', $block( 'livia/convives', array() ) );
		$this->assertStringContainsString( 'class="livia-convives wp-block-livia-convives"', $block( 'livia/convives', array() ) );
	}

	public function test_the_window_s_files_load_only_while_the_section_is_on() {
		Livia_Experience::enqueue();
		$this->assertFalse( wp_script_is( Livia_Experience::HANDLE, 'registered' ) );
		$this->assertFalse( wp_style_is( Livia_Experience::HANDLE, 'registered' ) );

		$this->turn_on();
		$this->new_assets();
		Livia_Experience::enqueue();
		$this->assertTrue( wp_script_is( Livia_Experience::HANDLE, 'enqueued' ) );
		$this->assertTrue( wp_style_is( Livia_Experience::HANDLE, 'enqueued' ) );
		$this->assertSame( 'defer', wp_scripts()->get_data( Livia_Experience::HANDLE, 'strategy' ) );
		$this->assertTrue( wp_scripts()->get_data( Livia_Experience::HANDLE, 'group' ) > 0, 'in the footer' );
		$this->assertSame( array(), wp_scripts()->registered[ Livia_Experience::HANDLE ]->deps, 'no library of WordPress on the visitor\'s page' );
		$before = implode( '', wp_scripts()->get_data( Livia_Experience::HANDLE, 'before' ) );
		$this->assertStringContainsString( 'var liviaExperience = ', $before );
		$this->assertStringContainsString( 'bookings.zenchef.test', $before );
		$this->assertStringNotContainsString( self::KEY, $before, 'the restaurant key stays on the server' );
		$this->assertFalse( wp_script_is( 'livia-' . md5( Livia_Service::public_url( Livia_Widgets::BUNDLE ) ), 'enqueued' ), 'the conversation loads only when the window opens it' );

		delete_option( 'livia_restaurants' );
		$this->new_assets();
		Livia_Experience::enqueue();
		$this->assertFalse( wp_script_is( Livia_Experience::HANDLE, 'registered' ), 'nothing to book without a restaurant' );
	}

	public function test_a_name_with_markup_cannot_end_the_window_s_inline_script() {
		$this->turn_on();
		$connections                             = get_option( 'livia_connections' );
		$connections['r1']['restaurant']['name'] = 'Brasserie <!--<script ';
		update_option( 'livia_connections', $connections );
		$this->new_assets();
		Livia_Experience::enqueue();
		$before = implode( '', wp_scripts()->get_data( Livia_Experience::HANDLE, 'before' ) );
		$this->assertStringContainsString( 'Brasserie \u003C!--\u003Cscript ', $before );
		$this->assertStringNotContainsString( '<', $before );
	}

	public function test_the_theme_s_colours_and_fonts_reach_the_window() {
		$css = Livia_Experience::theme_css();
		$this->assertStringNotContainsString( '<', $css );
		$filter = static function ( $data ) {
			return $data->update_with(
				array(
					'version' => 2,
					'styles'  => array(
						'color'      => array(
							'background' => '#fbf8f2',
							'text'       => 'red;}body{display:none',
						),
						'typography' => array( 'fontFamily' => 'var:preset|font-family|body' ),
					),
				)
			);
		};
		add_filter( 'wp_theme_json_data_theme', $filter );
		wp_clean_theme_json_cache();
		$css = Livia_Experience::theme_css();
		remove_filter( 'wp_theme_json_data_theme', $filter );
		wp_clean_theme_json_cache();
		$this->assertStringContainsString( '--livia-bg:#fbf8f2;', $css );
		$this->assertStringContainsString( '--livia-font:var(--wp--preset--font-family--body);', $css );
		$this->assertStringNotContainsString( 'display:none', $css, 'a value that is no plain CSS value is left out' );
	}

	public function test_the_phone_bar_replaces_the_bubble_and_never_shows_on_a_group_page() {
		// The footer as WordPress runs it: the bubble, then the bar.
		$footer = static function () {
			ob_start();
			Livia_Widgets::footer();
			Livia_Experience::footer();
			return ob_get_clean();
		};
		$page   = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$this->go_to( get_permalink( $page ) );
		$this->assertStringNotContainsString( 'livia-xp-mbar', $footer() );

		$this->turn_on( array( 'site_widget' => 'wdg_site' ) );
		$this->new_assets();
		$html = $footer();
		$this->assertStringContainsString( '<div class="livia-xp-mbar"><a class="wp-element-button livia-reserver" href="#reserver">Book a table</a></div>', $html );
		$this->assertStringContainsString( ' data-livia-bubble></livia-chat>', $html, 'the mark the bar hides' );
		$this->assertContains( 'livia-has-mbar', get_body_class() );

		Livia_Service::save_restaurant( 'r1', array( 'bubble_on' => false ) );
		$this->new_assets();
		$this->assertStringContainsString( '<div class="livia-xp-mbar">', $footer() );
		$this->assertStringNotContainsString( 'data-livia-bubble', Livia_Widgets::markup( 'wdg_page' ), 'an embedded conversation stays' );

		$group = Livia_Group_Page::publish( 'r1', 'reservation-groupe', 'Réservation de groupe' )['page'];
		$this->go_to( get_permalink( $group ) );
		$this->assertStringNotContainsString( 'livia-xp-mbar', $footer() );
		$this->assertNotContains( 'livia-has-mbar', get_body_class() );

		Livia_Service::save_settings( array( 'xp_bar' => false ) );
		$this->go_to( get_permalink( $page ) );
		$this->assertStringNotContainsString( 'livia-xp-mbar', $footer() );
	}

	/**
	 * Switches to the first of these themes the site has.
	 */
	private function use_theme( $themes ) {
		foreach ( $themes as $theme ) {
			if ( wp_get_theme( $theme )->exists() ) {
				switch_theme( $theme );
				// WordPress registers the theme's patterns at init, before the test switches theme.
				_register_theme_block_patterns();
				return;
			}
		}
		$this->markTestSkipped( 'Needs one of ' . implode( ', ', $themes ) . '.' );
	}

	public function test_book_a_table_is_added_once_at_the_end_of_the_main_menu_and_leaves_with_the_section() {
		$this->use_theme( array( 'twentytwentyone', 'twentytwenty', 'twentynineteen' ) );
		$this->assertFalse( wp_is_block_theme() );
		$this->assertSame( 'none', Livia_Experience::menu_state() );
		register_nav_menus(
			array(
				'footer'  => 'Footer',
				'primary' => 'Primary',
			)
		);
		$footer = wp_create_nav_menu( 'Pied de page' );
		$main   = wp_create_nav_menu( 'Principal' );
		wp_update_nav_menu_item(
			$main,
			0,
			array(
				'menu-item-title'  => 'La carte',
				'menu-item-url'    => home_url( '/la-carte/' ),
				'menu-item-status' => 'publish',
			)
		);
		set_theme_mod(
			'nav_menu_locations',
			array(
				'footer'  => $footer,
				'primary' => $main,
			)
		);
		$this->assertSame( $main, Livia_Experience::main_menu(), 'the main location, not the first one' );

		$this->assertSame( 'added', Livia_Experience::add_to_menu() );
		$items = wp_get_nav_menu_items( $main );
		$this->assertSame( array( 'La carte', 'Book a table' ), wp_list_pluck( $items, 'title' ) );
		$this->assertSame( '#reserver', end( $items )->url );
		$this->assertSame( array( 'livia-reserver', 'livia-added' ), end( $items )->classes );
		$this->assertSame( 'present', Livia_Experience::add_to_menu() );
		$this->assertCount( 2, wp_get_nav_menu_items( $main ) );
		$this->assertCount( 0, wp_get_nav_menu_items( $footer ) );

		$shown = static function () use ( $main ) {
			return wp_list_pluck( apply_filters( 'wp_nav_menu_objects', wp_get_nav_menu_items( $main ), new stdClass() ), 'title' );
		};
		$own   = wp_update_nav_menu_item(
			$main,
			0,
			array(
				'menu-item-title'   => 'Réserver',
				'menu-item-url'     => self::URL,
				'menu-item-classes' => 'livia-reserver livia-added',
				'menu-item-status'  => 'publish',
			)
		);
		$this->assertSame( array( 'La carte', 'Réserver' ), array_values( $shown() ), 'the section is off: the restaurateur\'s link to their own tool stays' );
		$this->turn_on();
		$this->assertSame( array( 'La carte', 'Book a table', 'Réserver' ), array_values( $shown() ) );
		wp_delete_post( $own, true );
		unregister_nav_menu( 'footer' );
		unregister_nav_menu( 'primary' );
	}

	public function test_the_classic_menu_link_keeps_no_markup_from_a_translation() {
		$this->use_theme( array( 'twentytwentyone', 'twentytwenty', 'twentynineteen' ) );
		register_nav_menus( array( 'primary' => 'Primary' ) );
		$main = wp_create_nav_menu( 'Principal' );
		set_theme_mod( 'nav_menu_locations', array( 'primary' => $main ) );
		// Who may post unfiltered HTML: WordPress itself keeps the title as given.
		$user = self::factory()->user->create( array( 'role' => 'administrator' ) );
		if ( is_multisite() ) {
			grant_super_admin( $user );
		}
		wp_set_current_user( $user );
		$hostile = static function ( $translation, $text, $domain ) {
			return 'livia' === $domain && 'Book a table' === $text ? 'Réserver <img src=x onerror=alert(1)>' : $translation;
		};
		add_filter( 'gettext', $hostile, 10, 3 );
		try {
			$this->assertSame( 'added', Livia_Experience::add_to_menu() );
		} finally {
			remove_filter( 'gettext', $hostile, 10 );
			wp_set_current_user( 0 );
			unregister_nav_menu( 'primary' );
		}
		$this->assertSame( 'Réserver', wp_get_nav_menu_items( $main )[0]->title );
	}

	public function test_the_plugin_s_own_translations_win_over_a_file_of_the_same_name_in_the_languages_folder() {
		global $wp_textdomain_registry, $l10n_unloaded;
		$dir     = WP_LANG_DIR . '/plugins';
		$file    = $dir . '/livia-fr_FR.l10n.php';
		$created = ! is_dir( WP_LANG_DIR );
		$this->assertFalse( file_exists( $file ) );
		wp_mkdir_p( $dir );
		file_put_contents( $file, "<?php\nreturn array( 'messages' => array( 'Book a table' => 'Traduction tierce' ) );\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$registry               = $wp_textdomain_registry;
		$wp_textdomain_registry = new WP_Textdomain_Registry();
		wp_cache_delete( md5( $dir . '/' ), 'translation_files' );
		load_plugin_textdomain( 'livia', false, dirname( LIVIA_BASENAME ) . '/languages' );
		unload_textdomain( 'livia', true );
		unset( $l10n_unloaded['livia'] );
		$french = static function () {
			return 'fr_FR';
		};
		add_filter( 'locale', $french );
		try {
			$this->assertSame( $dir . '/', $wp_textdomain_registry->get( 'livia', 'fr_FR' ), 'WordPress looks in its own folder first' );
			$this->assertSame( 'Réserver une table', __( 'Book a table', 'livia' ) );
		} finally {
			remove_filter( 'locale', $french );
			unload_textdomain( 'livia', true );
			$wp_textdomain_registry = $registry;
			wp_delete_file( $file );
			wp_cache_delete( md5( $dir . '/' ), 'translation_files' );
			if ( $created ) {
				rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
				rmdir( WP_LANG_DIR ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
			}
		}
	}

	public function test_a_location_whose_menu_was_deleted_under_another_theme_is_skipped() {
		$this->use_theme( array( 'twentytwentyone', 'twentytwenty', 'twentynineteen' ) );
		register_nav_menus(
			array(
				'footer'  => 'Footer',
				'primary' => 'Primary',
			)
		);
		try {
			$gone = wp_create_nav_menu( 'Principal' );
			wp_delete_nav_menu( $gone );
			// WordPress clears the deleted menu from the active theme's locations only: another theme's keep its id.
			set_theme_mod( 'nav_menu_locations', array( 'primary' => $gone ) );
			$this->assertSame( 'none', Livia_Experience::menu_state() );
			$this->assertSame( 'none', Livia_Experience::add_to_menu() );

			$footer = wp_create_nav_menu( 'Pied de page' );
			set_theme_mod(
				'nav_menu_locations',
				array(
					'footer'  => $footer,
					'primary' => $gone,
				)
			);
			$this->assertSame( $footer, Livia_Experience::main_menu() );
		} finally {
			unregister_nav_menu( 'footer' );
			unregister_nav_menu( 'primary' );
		}
	}

	public function test_book_a_table_is_added_once_at_the_end_of_a_block_theme_header_menu_and_leaves_with_the_section() {
		$this->use_theme( array( 'twentytwentyfive' ) );
		self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'La carte',
			)
		);
		$menus = static function () {
			return get_posts(
				array(
					'post_type'   => 'wp_navigation',
					'post_status' => 'any',
					'numberposts' => -1,
				)
			);
		};
		// An administrator of a network, whose saves go through kses, sees the screen.
		$screen = $this->screen( 'experience' );
		$this->assertStringContainsString( 'Add “Book a table” to the main menu', $screen );
		$this->assertStringNotContainsString( 'has no menu the plugin can add to', $screen );
		$this->assertSame( array(), $menus(), 'the screen creates no menu' );

		$this->assertSame( 'added', Livia_Experience::add_to_menu() );
		$this->assertCount( 1, $menus() );
		$this->assertSame( '<!-- wp:page-list /--><!-- wp:navigation-link {"label":"Book a table","url":"#reserver","kind":"custom","className":"livia-reserver livia-added"} /-->', $menus()[0]->post_content, 'the menu WordPress falls back to, as the header shows it' );
		$this->assertSame( 'present', Livia_Experience::add_to_menu() );
		$this->assertCount( 1, $menus() );

		$header = static function () {
			return do_blocks( '<!-- wp:template-part {"slug":"header"} /-->' );
		};
		$this->assertStringContainsString( 'La carte', $header() );
		$this->assertStringNotContainsString( '#reserver', $header(), 'the section is off' );
		$own = '<!-- wp:navigation-link {"label":"Réserver","url":"https://bookings.zenchef.test/","kind":"custom","className":"livia-reserver livia-added"} /-->';
		$this->assertStringContainsString( 'https://bookings.zenchef.test/', do_blocks( $own ), 'the restaurateur\'s link to their own tool stays' );
		$this->assertSame( '', do_blocks( str_replace( 'https://bookings.zenchef.test/', '/#reserver', $own ) ), 'a link to #reserver opens nothing' );
		$this->turn_on();
		$this->assertMatchesRegularExpression( '#La carte.*<li class="wp-block-navigation-item livia-reserver livia-added wp-block-navigation-link"><a class="wp-block-navigation-item__content"\s+href="\#reserver"><span class="wp-block-navigation-item__label">Book a table</span></a></li>#s', $header() );
	}

	public function test_deactivation_removes_the_link_the_plugin_added_to_a_classic_menu_wherever_it_moved_and_only_it() {
		$this->use_theme( array( 'twentytwentyone', 'twentytwenty', 'twentynineteen' ) );
		register_nav_menus( array( 'primary' => 'Primary' ) );
		$main  = wp_create_nav_menu( 'Principal' );
		$item  = static function ( $title, $url, $classes = '', $under = 0 ) use ( $main ) {
			return wp_update_nav_menu_item(
				$main,
				0,
				array(
					'menu-item-title'     => $title,
					'menu-item-url'       => $url,
					'menu-item-classes'   => $classes,
					'menu-item-parent-id' => $under,
					'menu-item-status'    => 'publish',
				)
			);
		};
		$carte = $item( 'La carte', home_url( '/la-carte/' ) );
		set_theme_mod( 'nav_menu_locations', array( 'primary' => $main ) );
		$this->assertSame( 'added', Livia_Experience::add_to_menu() );
		$items = wp_get_nav_menu_items( $main );
		$ours  = end( $items );
		// Moved under La carte, as the restaurateur may.
		wp_update_nav_menu_item(
			$main,
			$ours->ID,
			array(
				'menu-item-title'     => $ours->title,
				'menu-item-url'       => $ours->url,
				'menu-item-classes'   => implode( ' ', $ours->classes ),
				'menu-item-parent-id' => $carte,
				'menu-item-status'    => 'publish',
			)
		);
		$item( 'Réserver', '#reserver', '', $carte );
		$item( 'Une table', home_url( '/#reserver' ), 'livia-reserver' );
		$child  = $item( 'Groupes', home_url( '/groupes/' ), '', $ours->ID );
		$rest   = static function () use ( $main, $ours ) {
			$items = wp_list_filter( wp_get_nav_menu_items( $main ), array( 'ID' => $ours->ID ), 'NOT' );
			return array_map(
				static function ( $item ) {
					return array( $item->ID, $item->title, $item->url, $item->classes, $item->menu_item_parent, get_post( $item->ID )->menu_order );
				},
				array_values( $items )
			);
		};
		$before = $rest();
		$this->assertCount( 4, $before );

		Livia_Group_Page::on_deactivation();
		$this->assertNull( get_post( $ours->ID ) );
		$this->assertSame( array( $child, (string) $ours->ID ), array( $before[3][0], $before[3][4] ) );
		// The link's own child moves up a level, under La carte.
		$before[3][4] = (string) $carte;
		$this->assertSame( $before, $rest(), 'the links set by hand stay, the rest of the menu as it was' );

		Livia_Group_Page::on_activation();
		$this->assertSame( $before, $rest(), 'reactivation puts nothing back' );
		unregister_nav_menu( 'primary' );
	}

	public function test_deactivation_moves_the_links_under_two_nested_links_the_plugin_added_up_to_their_parent() {
		$main  = wp_create_nav_menu( 'Principal' );
		$item  = static function ( $title, $url, $classes = '', $under = 0 ) use ( $main ) {
			return wp_update_nav_menu_item(
				$main,
				0,
				array(
					'menu-item-title'     => $title,
					'menu-item-url'       => $url,
					'menu-item-classes'   => $classes,
					'menu-item-parent-id' => $under,
					'menu-item-status'    => 'publish',
				)
			);
		};
		$carte = $item( 'La carte', home_url( '/la-carte/' ) );
		$outer = $item( 'Book a table', '#reserver', 'livia-reserver livia-added', $carte );
		$inner = $item( 'Book a table', '#reserver', 'livia-reserver livia-added', $outer );
		$child = $item( 'Groupes', home_url( '/groupes/' ), '', $inner );

		Livia_Group_Page::on_deactivation();
		$this->assertSame( array( 'La carte', 'Groupes' ), wp_list_pluck( wp_get_nav_menu_items( $main ), 'title' ) );
		$this->assertSame( (string) $carte, get_post_meta( $child, '_menu_item_menu_item_parent', true ), 'under the parent of the outer link' );
	}

	public function test_deactivation_removes_the_link_the_plugin_added_to_a_block_theme_menu_wherever_it_moved_and_only_it() {
		$this->use_theme( array( 'twentytwentyfive' ) );
		// An administrator of a network, whose saves go through kses.
		$this->screen( 'experience' );
		$menu = self::factory()->post->create(
			array(
				'post_type'    => 'wp_navigation',
				'post_content' => '<!-- wp:navigation-link {"label":"La carte","url":"/la-carte/","kind":"custom"} /-->',
			)
		);
		$this->assertSame( 'added', Livia_Experience::add_to_menu() );
		$ours = substr( get_post( $menu )->post_content, strlen( '<!-- wp:navigation-link {"label":"La carte","url":"/la-carte/","kind":"custom"} /-->' ) );
		$this->assertStringContainsString( 'livia-added', $ours );

		// Moved into a submenu by the editor, next to links set by hand.
		$start = "<!-- wp:navigation-link {\"label\":\"La carte\",\"url\":\"/la-carte/\",\"kind\":\"custom\"} /-->\n\n<!-- wp:navigation-submenu {\"label\":\"Venir\",\"url\":\"/venir/\"} -->";
		$end   = "<!-- wp:navigation-link {\"label\":\"Réserver\",\"url\":\"#reserver\",\"kind\":\"custom\"} /-->\n<!-- wp:navigation-link {\"label\":\"Une table\",\"url\":\"/#reserver\",\"className\":\"livia-reserver\"} /-->\n<!-- /wp:navigation-submenu -->";
		wp_update_post(
			wp_slash(
				array(
					'ID'           => $menu,
					'post_content' => $start . "\n" . $ours . $end,
				)
			)
		);
		$this->assertSame( $start . "\n" . $ours . $end, get_post( $menu )->post_content );

		Livia_Group_Page::on_deactivation();
		$this->assertSame( $start . $end, get_post( $menu )->post_content, 'only the link the plugin added goes' );

		Livia_Group_Page::on_activation();
		$this->assertSame( $start . $end, get_post( $menu )->post_content, 'reactivation puts nothing back' );
	}

	public function test_a_block_theme_menu_made_from_a_classic_menu_keeps_its_link_once_and_an_empty_one_its_pages() {
		$this->use_theme( array( 'twentytwentyfive' ) );
		$this->screen( 'experience' );
		$classic = wp_create_nav_menu( 'Principal' );
		wp_update_nav_menu_item(
			$classic,
			0,
			array(
				'menu-item-title'   => 'Réserver une table',
				'menu-item-url'     => '#reserver',
				'menu-item-classes' => 'livia-reserver',
				'menu-item-status'  => 'publish',
			)
		);
		$this->assertSame( 'ready', Livia_Experience::menu_state(), 'WordPress has not made its menu yet' );
		$this->assertSame( 'present', Livia_Experience::add_to_menu() );
		$made = get_posts( array( 'post_type' => 'wp_navigation' ) );
		$this->assertCount( 1, $made );
		$this->assertSame( 1, substr_count( $made[0]->post_content, '#reserver' ), 'made from the classic menu, with its link' );

		wp_update_post(
			array(
				'ID'        => $made[0]->ID,
				'post_date' => '2026-01-01 10:00:00',
			)
		);
		$empty = self::factory()->post->create(
			array(
				'post_type'    => 'wp_navigation',
				'post_content' => '',
			)
		);
		$this->assertSame( 'added', Livia_Experience::add_to_menu() );
		$this->assertSame( '<!-- wp:page-list /--><!-- wp:navigation-link {"label":"Book a table","url":"#reserver","kind":"custom","className":"livia-reserver livia-added"} /-->', get_post( $empty )->post_content, 'the pages the header showed stay' );
	}

	public function test_book_a_table_goes_to_the_menu_the_edited_header_shows_and_never_where_only_the_header_could_change() {
		$this->use_theme( array( 'twentytwentyfour' ) );
		$this->screen( 'experience' );
		$navigation = static function ( $content, $date ) {
			return self::factory()->post->create(
				array(
					'post_type'    => 'wp_navigation',
					'post_content' => $content,
					'post_date'    => $date,
				)
			);
		};
		$shown      = $navigation( '<!-- wp:navigation-link {"label":"La carte","url":"/la-carte/","kind":"custom"} /-->', '2026-01-01 10:00:00' );
		$latest     = $navigation( '<!-- wp:navigation-link {"label":"Mentions","url":"/mentions/","kind":"custom"} /-->', '2026-06-01 10:00:00' );
		$part       = self::factory()->post->create(
			array(
				'post_type'    => 'wp_template_part',
				'post_name'    => 'header',
				'post_content' => '<!-- wp:group --><div class="wp-block-group"><!-- wp:navigation {"ref":' . $shown . '} /--></div><!-- /wp:group -->',
			)
		);
		wp_set_post_terms( $part, get_stylesheet(), 'wp_theme' );
		$this->assertSame( 'added', Livia_Experience::add_to_menu() );
		$this->assertStringEndsWith( '"url":"/la-carte/","kind":"custom"} /--><!-- wp:navigation-link {"label":"Book a table","url":"#reserver","kind":"custom","className":"livia-reserver livia-added"} /-->', get_post( $shown )->post_content );
		$this->assertStringNotContainsString( 'reserver', get_post( $latest )->post_content, 'not the menu WordPress would fall back to' );

		wp_update_post(
			array(
				'ID'           => $latest,
				'post_content' => '<!-- wp:navigation-submenu {"label":"Venir","url":"/venir/"} --><!-- wp:navigation-link {"label":"Une table","url":"/carte/#reserver"} /--><!-- /wp:navigation-submenu -->',
			)
		);
		$header = static function ( $content ) use ( $part ) {
			wp_update_post(
				array(
					'ID'           => $part,
					'post_content' => $content,
				)
			);
		};
		$header( '<!-- wp:navigation {"ref":' . $latest . '} /-->' );
		$this->assertSame( 'present', Livia_Experience::menu_state(), 'a link to #reserver in a submenu' );

		$header( '<!-- wp:navigation {"ref":' . $latest . ',"overlay":"menu-telephone"} /-->' );
		$this->assertSame( 'block', Livia_Experience::menu_state(), 'phones show a template part' );
		$header( '<!-- wp:navigation --><!-- wp:navigation-link {"label":"La carte","url":"/la-carte/"} /--><!-- /wp:navigation -->' );
		$this->assertSame( 'block', Livia_Experience::menu_state(), 'links written in the header' );
		$this->assertSame( 'block', Livia_Experience::add_to_menu() );
		$this->assertStringContainsString( 'has no menu the plugin can add to', $this->screen( 'experience' ) );
		$header( '<!-- wp:site-title /-->' );
		$this->assertSame( 'block', Livia_Experience::menu_state(), 'no navigation in the header' );
	}

	/**
	 * A Navigation menu, published.
	 */
	private function navigation( $content, $date = '2026-01-01 10:00:00' ) {
		return self::factory()->post->create(
			array(
				'post_type'    => 'wp_navigation',
				'post_content' => $content,
				'post_date'    => $date,
			)
		);
	}

	/**
	 * A template or template part of the active theme as the Site Editor saves it, with its area.
	 */
	private function template( $type, $slug, $content, $area = 'header' ) {
		$id = self::factory()->post->create(
			array(
				'post_type'    => $type,
				'post_name'    => $slug,
				'post_content' => $content,
			)
		);
		wp_set_post_terms( $id, get_stylesheet(), 'wp_theme' );
		if ( 'wp_template_part' === $type ) {
			wp_set_post_terms( $id, $area, 'wp_template_part_area' );
		}
		return $id;
	}

	public function test_a_header_menu_shown_empty_takes_the_link_and_a_menu_gone_takes_nothing() {
		$this->use_theme( array( 'twentytwentyfour' ) );
		$this->screen( 'experience' );
		self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'La carte',
			)
		);
		$empty = $this->navigation( '' );
		$this->template( 'wp_template_part', 'header', '<!-- wp:navigation {"ref":' . $empty . '} /-->' );
		$header = static function () {
			return do_blocks( '<!-- wp:template-part {"slug":"header"} /-->' );
		};
		$this->assertStringNotContainsString( 'La carte', $header(), 'WordPress shows an empty menu as it is, not the pages' );
		$this->assertSame( 'added', Livia_Experience::add_to_menu() );
		$this->turn_on();
		$this->assertStringContainsString( 'href="#reserver"', $header() );

		wp_delete_post( $empty, true );
		$other = $this->navigation( '<!-- wp:navigation-link {"label":"Mentions","url":"/mentions/","kind":"custom"} /-->' );
		$this->assertSame( 'block', Livia_Experience::menu_state(), 'the header shows nothing in place of a deleted menu' );
		$this->assertSame( 'block', Livia_Experience::add_to_menu() );
		$this->assertStringNotContainsString( 'reserver', get_post( $other )->post_content );
	}

	public function test_book_a_table_goes_to_the_menu_of_the_former_attribute() {
		$this->use_theme( array( 'twentytwentyfour' ) );
		$this->screen( 'experience' );
		$shown  = $this->navigation( '<!-- wp:navigation-link {"label":"La carte","url":"/la-carte/","kind":"custom"} /-->' );
		$latest = $this->navigation( '<!-- wp:navigation-link {"label":"Mentions","url":"/mentions/","kind":"custom"} /-->', '2026-06-01 10:00:00' );
		$this->template( 'wp_template_part', 'header', '<!-- wp:navigation {"navigationMenuId":' . $shown . '} /-->' );
		$this->assertSame( 'added', Livia_Experience::add_to_menu() );
		$this->assertStringContainsString( '#reserver', get_post( $shown )->post_content );
		$this->assertStringNotContainsString( 'reserver', get_post( $latest )->post_content );
	}

	public function test_book_a_table_goes_to_the_header_of_the_front_page_template() {
		$this->use_theme( array( 'twentytwentyfive' ) );
		$this->screen( 'experience' );
		$header   = $this->navigation( '<!-- wp:navigation-link {"label":"Accueil","url":"/","kind":"custom"} /-->' );
		$vertical = $this->navigation( '<!-- wp:navigation-link {"label":"La carte","url":"/la-carte/","kind":"custom"} /-->' );
		$large    = $this->navigation( '<!-- wp:navigation-link {"label":"Venir","url":"/venir/","kind":"custom"} /-->' );
		// Left in the general area, the header is the part so named.
		$this->template( 'wp_template_part', 'header', '<!-- wp:navigation {"ref":' . $header . '} /-->', 'uncategorized' );
		$this->template( 'wp_template_part', 'vertical-header', '<!-- wp:navigation {"ref":' . $vertical . '} /-->' );
		$this->template( 'wp_template_part', 'header-large-title', '<!-- wp:navigation {"ref":' . $large . '} /-->' );
		$this->template( 'wp_template', 'home', '<!-- wp:template-part {"slug":"vertical-header"} /--><!-- wp:template-part {"slug":"footer"} /-->' );
		$this->assertSame( $vertical, Livia_Experience::main_navigation()->ID, 'the latest posts: the home template' );

		$front = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Accueil',
			)
		);
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $front );
		$this->assertSame( $header, Livia_Experience::main_navigation()->ID, 'a page: the page template' );
		update_post_meta( $front, '_wp_page_template', 'page-no-title' );
		$this->template( 'wp_template', 'page-no-title', '<!-- wp:template-part {"slug":"header-large-title"} /-->' );
		$this->assertSame( $large, Livia_Experience::main_navigation()->ID, 'the template the page chose' );

		// Two headers: only the first, which lists its links, never the next one.
		$this->template( 'wp_template', 'front-page', '<!-- wp:template-part {"slug":"footer"} /--><!-- wp:template-part {"slug":"header-large-title","theme":"autre"} /--><!-- wp:template-part {"slug":"livia-links"} /--><!-- wp:template-part {"slug":"vertical-header"} /-->' );
		$this->template( 'wp_template_part', 'livia-links', '<!-- wp:navigation --><!-- wp:navigation-link {"label":"La carte","url":"/la-carte/"} /--><!-- /wp:navigation -->' );
		$this->assertNull( Livia_Experience::main_navigation(), 'not the footer nor a part of another theme: the first header, which lists its links' );
		$this->assertSame( 'block', Livia_Experience::add_to_menu() );
		$this->assertStringNotContainsString( 'reserver', get_post( $header )->post_content . get_post( $large )->post_content . get_post( $vertical )->post_content );
	}

	public function test_a_front_page_template_without_a_header_takes_the_header_of_the_theme() {
		$this->use_theme( array( 'twentytwentyfour' ) );
		$this->screen( 'experience' );
		$menu = $this->navigation( '<!-- wp:navigation-link {"label":"La carte","url":"/la-carte/","kind":"custom"} /-->' );
		$this->template( 'wp_template_part', 'header', '<!-- wp:navigation {"ref":' . $menu . '} /-->' );
		$this->template( 'wp_template', 'front-page', '<!-- wp:post-content /-->' );
		$this->assertSame( 'added', Livia_Experience::add_to_menu() );
		$this->assertStringContainsString( '#reserver', get_post( $menu )->post_content );
	}

	public function test_a_header_part_in_draft_leaves_the_header_of_the_theme_file() {
		$this->use_theme( array( 'twentytwentyfour' ) );
		$this->screen( 'experience' );
		$kept   = $this->navigation( '<!-- wp:navigation-link {"label":"La carte","url":"/la-carte/","kind":"custom"} /-->' );
		$latest = $this->navigation( '<!-- wp:navigation-link {"label":"Mentions","url":"/mentions/","kind":"custom"} /-->', '2026-06-01 10:00:00' );
		$part   = $this->template( 'wp_template_part', 'header', '<!-- wp:navigation {"ref":' . $kept . '} /-->' );
		wp_update_post(
			array(
				'ID'          => $part,
				'post_status' => 'draft',
			)
		);
		$this->assertSame( $latest, Livia_Experience::main_navigation()->ID, 'the theme file, whose menu falls back to the latest' );
	}

	public function test_the_front_page_template_follows_the_template_loader_and_its_filters() {
		$this->use_theme( array( 'twentytwentyfour' ) );
		$this->screen( 'experience' );
		$menus = array();
		foreach ( array( 'front-page', 'home', 'livia-accueil' ) as $slug ) {
			$menus[ $slug ] = $this->navigation( '<!-- wp:navigation-link {"label":"' . $slug . '","url":"/","kind":"custom"} /-->' );
			$this->template( 'wp_template_part', $slug . '-header', '<!-- wp:navigation {"ref":' . $menus[ $slug ] . '} /-->' );
			$this->template( 'wp_template', $slug, '<!-- wp:template-part {"slug":"' . $slug . '-header"} /-->' );
		}
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', 0 );
		$this->assertSame( $menus['home'], Livia_Experience::main_navigation()->ID, 'no page chosen: the latest posts, without the front page template' );
		$filter = static function ( $templates ) {
			return array_merge( array( 'livia-accueil.php' ), $templates );
		};
		add_filter( 'home_template_hierarchy', $filter );
		$found = Livia_Experience::main_navigation()->ID;
		remove_filter( 'home_template_hierarchy', $filter );
		$this->assertSame( $menus['livia-accueil'], $found, 'the hierarchy as a filter changes it' );
		$filter = static function () {
			return array( 'accueil' => 'livia-accueil.php' );
		};
		add_filter( 'home_template_hierarchy', $filter );
		$found = Livia_Experience::main_navigation()->ID;
		remove_filter( 'home_template_hierarchy', $filter );
		$this->assertSame( $menus['livia-accueil'], $found, 'a filter that keys its templates' );
	}

	public function test_book_a_table_goes_to_the_next_header_when_the_first_has_no_navigation() {
		$this->use_theme( array( 'twentytwentyfour' ) );
		$this->screen( 'experience' );
		$menu = $this->navigation( '<!-- wp:navigation-link {"label":"La carte","url":"/la-carte/","kind":"custom"} /-->' );
		$this->template( 'wp_template_part', 'header', '<!-- wp:navigation {"ref":' . $menu . '} /-->' );
		$this->template( 'wp_template_part', 'livia-top-bar', '<!-- wp:group --><!-- wp:paragraph --><p>01 23 45 67 89</p><!-- /wp:paragraph --><!-- /wp:group -->', 'header' );
		$front = $this->template( 'wp_template', 'front-page', '<!-- wp:template-part {"slug":"livia-top-bar"} /-->' );
		$this->assertNull( Livia_Experience::main_navigation(), 'a header without a menu alone: the warning, not the header of the theme' );
		wp_update_post(
			array(
				'ID'           => $front,
				'post_content' => '<!-- wp:template-part {"slug":"livia-top-bar"} /--><!-- wp:template-part {"slug":"header"} /-->',
			)
		);
		$this->assertSame( 'added', Livia_Experience::add_to_menu() );
		$this->assertStringContainsString( '#reserver', get_post( $menu )->post_content );
	}

	public function test_book_a_table_goes_to_the_navigation_of_a_synced_pattern_or_a_template_part_the_header_holds() {
		$this->use_theme( array( 'twentytwentyfour' ) );
		$this->screen( 'experience' );
		$held  = $this->navigation( '<!-- wp:navigation-link {"label":"La carte","url":"/la-carte/","kind":"custom"} /-->' );
		$after = $this->navigation( '<!-- wp:navigation-link {"label":"Mentions","url":"/mentions/","kind":"custom"} /-->' );
		$block = self::factory()->post->create(
			array(
				'post_type'    => 'wp_block',
				'post_content' => '<!-- wp:navigation {"ref":' . $held . '} /-->',
			)
		);
		$this->template( 'wp_template_part', 'livia-nav', '<!-- wp:navigation {"ref":' . $held . '} /-->', 'uncategorized' );
		$part   = $this->template( 'wp_template_part', 'header', '' );
		$header = static function ( $content ) use ( $part ) {
			wp_update_post(
				array(
					'ID'           => $part,
					'post_content' => $content,
				)
			);
			return Livia_Experience::main_navigation()->ID;
		};
		foreach ( array( '<!-- wp:block {"ref":' . $block . '} /-->', '<!-- wp:template-part {"slug":"livia-nav"} /-->' ) as $top_bar ) {
			$this->assertSame( $after, $header( '<!-- wp:group -->' . $top_bar . '<!-- /wp:group --><!-- wp:navigation {"ref":' . $after . '} /-->' ), 'the menu of the header itself, not that of a bar it holds before' );
			$this->assertSame( $held, $header( $top_bar ), 'without one, the menu of what it holds' );
		}
	}

	public function test_a_header_whose_menu_comes_from_elsewhere_gets_the_warning() {
		$this->use_theme( array( 'twentytwentyfive' ) );
		$this->screen( 'experience' );
		$fallback = static function () {
			return array(
				array(
					'blockName'    => 'core/navigation-link',
					'attrs'        => array(
						'label' => 'La carte',
						'url'   => '/la-carte/',
					),
					'innerBlocks'  => array(),
					'innerHTML'    => '',
					'innerContent' => array(),
				),
			);
		};
		add_filter( 'block_core_navigation_render_fallback', $fallback );
		$state = array( Livia_Experience::menu_state(), Livia_Experience::add_to_menu() );
		remove_filter( 'block_core_navigation_render_fallback', $fallback );
		$this->assertSame( array( 'block', 'block' ), $state, 'a filter chooses the menu the header shows' );
		$this->assertSame( array(), get_posts( array( 'post_type' => 'wp_navigation' ) ) );
	}

	public function test_a_link_made_a_submenu_leaves_with_the_section_and_its_links_stay() {
		$this->use_theme( array( 'twentytwentyfour' ) );
		$this->screen( 'experience' );
		$ours = '<!-- wp:navigation-submenu {"label":"Book a table","url":"#reserver","kind":"custom","className":"livia-reserver livia-added"} /-->';
		$menu = $this->navigation( '<!-- wp:navigation-link {"label":"La carte","url":"/la-carte/","kind":"custom"} /--><!-- wp:navigation-submenu {"label":"Réserver","url":"#reserver","kind":"custom","className":"livia-reserver"} --><!-- wp:navigation-link {"label":"Groupes","url":"/groupes/","kind":"custom"} /--><!-- /wp:navigation-submenu -->' . $ours );
		$this->template( 'wp_template_part', 'header', '<!-- wp:navigation {"ref":' . $menu . '} /-->' );
		$header = static function () {
			return do_blocks( '<!-- wp:template-part {"slug":"header"} /-->' );
		};

		$off = $header();
		$this->assertStringNotContainsString( 'reserver', $off, 'the section is off' );
		$this->assertMatchesRegularExpression( '#La carte.*Groupes#s', $off, 'the links under it stay' );
		$this->turn_on();
		$this->assertSame( 2, substr_count( $header(), 'href="#reserver"' ) );

		Livia_Group_Page::on_deactivation();
		$this->assertStringNotContainsString( $ours, get_post( $menu )->post_content, 'the link the plugin added goes' );
		$this->assertStringContainsString( '"label":"Réserver"', get_post( $menu )->post_content );
	}

	public function test_a_submenu_wordpress_hides_keeps_its_links_hidden_while_the_section_is_off() {
		$this->use_theme( array( 'twentytwentyfour' ) );
		$menu = $this->navigation( '<!-- wp:navigation-link {"label":"La carte","url":"/la-carte/","kind":"custom"} /--><!-- wp:navigation-submenu {"url":"#reserver","className":"livia-reserver livia-added"} --><!-- wp:navigation-link {"label":"Groupes","url":"/groupes/","kind":"custom"} /--><!-- /wp:navigation-submenu -->' );
		$this->template( 'wp_template_part', 'header', '<!-- wp:navigation {"ref":' . $menu . '} /-->' );
		$html = do_blocks( '<!-- wp:template-part {"slug":"header"} /-->' );
		$this->assertStringContainsString( 'La carte', $html );
		$this->assertStringNotContainsString( 'Groupes', $html, 'WordPress hides a submenu without a label, and its links' );
	}

	public function test_deactivation_takes_out_the_submenu_the_plugin_added_and_its_links_take_its_place() {
		$this->use_theme( array( 'twentytwentyfour' ) );
		// An administrator of a network, whose saves go through kses.
		$this->screen( 'experience' );
		$start = "<!-- wp:navigation-link {\"label\":\"La carte\",\"url\":\"/la-carte/\",\"kind\":\"custom\"} /-->\n";
		$inner = "\n<!-- wp:navigation-link {\"label\":\"Groupes\",\"url\":\"/groupes/\",\"kind\":\"custom\"} /-->\n<!-- wp:navigation-submenu {\"label\":\"Venir\",\"url\":\"/venir/\"} --><!-- wp:navigation-link {\"label\":\"Plan\",\"url\":\"/plan/\"} /--><!-- wp:navigation-link {\"label\":\"Book a table\",\"url\":\"#reserver\",\"className\":\"livia-reserver livia-added\"} /--><!-- /wp:navigation-submenu -->";
		$end   = "\n<!-- wp:navigation-submenu {\"label\":\"Réserver\",\"url\":\"#reserver\",\"className\":\"livia-reserver\"} --><!-- wp:navigation-link {\"label\":\"Une table\",\"url\":\"/#reserver\"} /--><!-- /wp:navigation-submenu -->";
		$menu  = $this->navigation( $start . '<!-- wp:navigation-submenu {"label":"Book a table","url":"#reserver","kind":"custom","className":"livia-reserver livia-added"} -->' . $inner . "\n<!-- /wp:navigation-submenu -->" . $end );

		Livia_Group_Page::on_deactivation();
		$expected = rtrim( $start ) . str_replace( '<!-- wp:navigation-link {"label":"Book a table","url":"#reserver","className":"livia-reserver livia-added"} /-->', '', $inner ) . $end;
		$this->assertSame( $expected, get_post( $menu )->post_content, 'its links a level up, the rest of the menu as it was' );
	}

	public function test_a_pattern_that_holds_itself_leaves_the_screen_shown() {
		$this->use_theme( array( 'twentytwentyfour' ) );
		$menu = $this->navigation( '<!-- wp:navigation-link {"label":"La carte","url":"/la-carte/","kind":"custom"} /-->' );
		register_block_pattern(
			'livia-test/boucle',
			array(
				'title'   => 'Boucle',
				'content' => '<!-- wp:pattern {"slug":"livia-test/boucle"} /--><!-- wp:navigation {"ref":' . $menu . '} /-->',
			)
		);
		$this->template( 'wp_template_part', 'header', '<!-- wp:pattern {"slug":"livia-test/boucle"} /-->' );
		$screen = $this->screen( 'experience' );
		$found  = Livia_Experience::main_navigation();
		unregister_block_pattern( 'livia-test/boucle' );
		$this->assertStringContainsString( 'Add “Book a table” to the main menu', $screen );
		$this->assertSame( $menu, $found->ID );
	}

	public function test_the_screen_shows_the_experience_of_each_restaurant() {
		$user = self::factory()->user->create( array( 'role' => 'administrator' ) );
		if ( is_multisite() ) {
			add_user_to_blog( get_current_blog_id(), $user, 'administrator' );
		}
		wp_set_current_user( $user );
		$screen = static function ( $tab ) {
			$_GET = array(
				'page' => 'livia',
				'tab'  => $tab,
			);
			ob_start();
			Livia_Admin::render();
			return ob_get_clean();
		};

		$html = $screen( 'experience' );
		$this->assertStringContainsString( 'The experience stays off until you fill in the address of your booking tool, the restaurant&#039;s e-mail address.', $html );
		$this->assertStringNotContainsString( 'livia_reserver restaurant=', $html, 'one restaurant: the shortcodes need no restaurant' );
		$this->assertStringContainsString( 'Preview of the booking experience', $html );
		$this->assertStringContainsString( 'The preview follows the settings in progress, even before saving.', $html );
		$this->assertStringContainsString( 'name="action" value="livia_save_experience"', $html );
		$this->assertStringContainsString( 'name="livia[r1][xp_url]"', $html );
		$this->assertStringContainsString( 'name="livia[r1][xp_from]"', $html );
		$this->assertStringContainsString( '<form id="livia-add-menu"', $html );
		$this->assertStringContainsString( 'data-livia-xp-preview', $html );
		$this->assertMatchesRegularExpression( '#name="livia\[r1\]\[xp_group_dest\]" value="page"\s+disabled#', $html, 'without a published group page, only the window' );
		$this->assertStringNotContainsString( 'data-livia-order', $html, 'one restaurant: no choice to order' );

		$this->connect_second();
		$html = $screen( 'experience' );
		$this->assertStringContainsString( 'data-livia-order', $html );
		$this->assertStringContainsString( 'name="livia[r2][xp_phrase]"', $html );
		$this->assertStringContainsString( 'data-livia-panel="r2"', $html );
		$this->assertStringContainsString( 'data-livia-copy="[livia_reserver restaurant=&quot;r2&quot;]"', $html );
		$this->assertStringContainsString( 'data-livia-copy="[livia_convives restaurant=&quot;r1&quot;]"', $html );
		$this->assertStringContainsString( 'Brasserie Lumière: the address of your booking tool, the restaurant&#039;s e-mail address; La Terrasse Lumière: the address', $html );

		$widget = $screen( 'widget' );
		$this->assertStringContainsString( 'data-livia-copy="[livia_widget widget=&quot;wdg_t_button&quot;]"', $widget );
		$this->assertStringContainsString( 'data-livia-copy="[livia_widget widget=&quot;wdg_page&quot;]"', $widget );
		$this->assertStringNotContainsString( 'data-livia-copy="[livia_widget widget=&quot;wdg_site&quot;]"', $widget, 'bubbles are set for the whole site' );
	}

	public function test_a_restaurant_s_photo_and_sentence_outlive_a_save_while_it_is_alone() {
		$photo = self::factory()->attachment->create_object(
			array(
				'file'           => 'brasserie.jpg',
				'post_mime_type' => 'image/jpeg',
			)
		);
		$this->connect_second();
		$this->save(
			array(
				'r1' => $this->fields(
					array(
						'xp_photo'  => (string) $photo,
						'xp_phrase' => 'Sur les quais',
					)
				),
				'r2' => $this->fields(),
			)
		);
		Livia_Service::remove_restaurant( 'r2' );
		$this->assertStringNotContainsString( 'name="livia[r1][xp_phrase]"', $this->screen( 'experience' ), 'alone, the restaurant shows neither' );
		$alone = Livia_Experience::config()['restaurants'][0];
		$this->assertSame( array( '', '' ), array( $alone['xp_photo'], $alone['xp_phrase'] ), 'alone, the window gets neither' );
		$this->connect_second();
		$pair = Livia_Experience::config()['restaurants'][0];
		$this->assertNotSame( '', $pair['xp_photo'], 'with two restaurants the window gets the photo' );
		$this->assertSame( 'Sur les quais', $pair['xp_phrase'] );
		Livia_Service::remove_restaurant( 'r2' );
		$this->save( array( 'r1' => $this->fields( array( 'xp_url' => '/nos-tables/' ) ) ) );
		$this->connect_second();

		$r1 = Livia_Service::restaurant( 'r1' );
		$this->assertSame( home_url( '/nos-tables/' ), $r1['xp_url'], 'the fields shown are saved' );
		$this->assertSame( array( $photo, 'Sur les quais' ), array( $r1['xp_photo'], $r1['xp_phrase'] ) );
		$this->assertStringContainsString( 'value="Sur les quais"', $this->screen( 'experience' ) );

		$this->save(
			array(
				'r1' => $this->fields( array( 'xp_photo' => '' ) ),
				'r2' => $this->fields(),
			)
		);
		$this->assertSame( 0, Livia_Service::restaurant( 'r1' )['xp_photo'], 'Remove sends the field empty' );
	}
}
