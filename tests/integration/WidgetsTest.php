<?php
/**
 * The site bubble in the footer, the block and the shortcode.
 *
 * @package Livia
 */

final class WidgetsTest extends Livia_TestCase {

	public function set_up() {
		parent::set_up();
		$this->new_page_view();
	}

	/**
	 * Forgets the bubble the previous page placed: each page is its own request outside tests.
	 */
	private function new_page_view() {
		$bubble = new ReflectionProperty( Livia_Widgets::class, 'bubble_placed' );
		$bubble->setAccessible( true );
		$bubble->setValue( null, false );
	}

	private function footer_at( $post_id ) {
		$this->go_to( $post_id ? get_permalink( $post_id ) : home_url( '/' ) );
		$this->new_page_view();
		ob_start();
		Livia_Widgets::footer();
		return ob_get_clean();
	}

	public function test_the_site_bubble_shows_on_every_page_but_the_group_page() {
		$this->connect( array( 'site_widget' => 'wdg_site' ) );
		$page  = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$group = Livia_Group_Page::publish( 'r1', 'reservation-groupe', 'Réservation de groupe' )['page'];

		$this->assertStringContainsString( '<livia-chat widget="wdg_site"', $this->footer_at( $page ) );
		$this->assertStringContainsString( '<livia-chat widget="wdg_site"', $this->footer_at( 0 ) );
		$this->assertSame( '', $this->footer_at( $group->ID ) );
		$this->assertTrue( wp_script_is( 'livia-' . md5( Livia_Service::public_url( '/livia-chat.standalone.js' ) ), 'enqueued' ) );
	}

	public function test_one_element_unless_the_phone_has_its_own_widget() {
		$this->connect( array( 'site_widget' => 'wdg_site' ) );
		$footer = $this->footer_at( 0 );
		$this->assertSame( 1, substr_count( $footer, '<livia-chat' ) );
		$this->assertStringNotContainsString( 'data-livia-screen', $footer );
		$this->assertStringNotContainsString( '<style', $footer );

		$this->connect(
			array(
				'site_widget'  => 'wdg_site',
				'phone_widget' => 'wdg_site',
			)
		);
		$this->assertSame( 1, substr_count( $this->footer_at( 0 ), '<livia-chat' ), 'the same widget on both screens is one element' );

		$this->connect(
			array(
				'site_widget'  => 'wdg_site',
				'phone_widget' => 'wdg_other',
			)
		);
		$footer = $this->footer_at( 0 );
		// The bubble of the other screen is hidden by the rule, and starts only if a resize shows it.
		$this->assertMatchesRegularExpression( '#<livia-chat widget="wdg_site" start-when-visible[^>]* data-livia-bubble data-livia-screen="desktop"></livia-chat>#', $footer );
		$this->assertMatchesRegularExpression( '#<livia-chat widget="wdg_other" start-when-visible[^>]* data-livia-bubble data-livia-screen="phone"></livia-chat>#', $footer );
		$this->assertStringContainsString( '(max-width:760px){livia-chat[data-livia-screen="desktop"]{display:none}}', $footer );
		$this->assertLessThan( strpos( $footer, '<livia-chat' ), strpos( $footer, '<style>' ), 'the rule is in the page before the first element exists' );

		$other = self::factory()->post->create();
		update_post_meta( $other, Livia_Widgets::META_MODE, 'other' );
		update_post_meta( $other, Livia_Widgets::META_WIDGET, 'wdg_other' );
		$this->assertSame( 1, substr_count( $this->footer_at( $other ), '<livia-chat' ), 'a page that chose its widget keeps one for both screens' );
	}

	public function test_a_site_bubble_whose_widget_livia_no_longer_offers_as_a_bubble_is_not_printed() {
		$this->connect(
			array(
				'site_widget'  => 'wdg_site',
				'phone_widget' => 'wdg_other',
			)
		);
		$widgets = static function ( $format, $fullscreen ) {
			$connections                  = get_option( 'livia_connections' );
			$connections['r1']['widgets'] = array(
				array(
					'id'         => 'wdg_site',
					'name'       => 'Pages du site',
					'format'     => 'bubble',
					'fullscreen' => false,
				),
				array(
					'id'         => 'wdg_other',
					'name'       => 'Privatisation',
					'format'     => $format,
					'fullscreen' => $fullscreen,
				),
			);
			update_option( 'livia_connections', $connections );
		};

		foreach ( array( array( 'inline', false ), array( 'bubble', true ) ) as $changed ) {
			$widgets( $changed[0], $changed[1] );
			$footer = $this->footer_at( 0 );
			$this->assertSame( 1, substr_count( $footer, '<livia-chat' ), 'the phone follows the computer widget' );
			$this->assertStringContainsString( '<livia-chat widget="wdg_site"', $footer );
			$this->assertStringNotContainsString( 'data-livia-screen', $footer );
		}

		update_option( 'livia_connections', array( 'r1' => array_merge( get_option( 'livia_connections' )['r1'], array( 'widgets' => array() ) ) ) );
		$this->assertSame( '', $this->footer_at( 0 ), 'a computer widget Livia no longer has prints nothing' );
	}

	public function test_a_phone_keeps_its_bubble_when_the_desktop_widget_is_gone() {
		$this->connect(
			array(
				'site_widget'  => 'wdg_gone',
				'phone_widget' => 'wdg_other',
				'site_rule'    => 'only',
				'site_pages'   => array(),
			)
		);
		$page = self::factory()->post->create( array( 'post_type' => 'page' ) );
		Livia_Service::save_restaurant( 'r1', array( 'site_pages' => array( $page ) ) );

		$footer = $this->footer_at( $page );
		$this->assertSame( 1, substr_count( $footer, '<livia-chat' ), 'nothing on desktop' );
		$this->assertMatchesRegularExpression( '#<livia-chat widget="wdg_other" start-when-visible[^>]* data-livia-screen="phone"></livia-chat>#', $footer );
		$this->assertStringContainsString( '(min-width:761px){livia-chat[data-livia-screen="phone"]{display:none}}', $footer );
		$this->assertSame( '', $this->footer_at( self::factory()->post->create( array( 'post_type' => 'page' ) ) ), 'the rule still holds' );

		Livia_Service::save_restaurant( 'r1', array( 'bubble_on' => false ) );
		$this->assertSame( '', $this->footer_at( $page ), 'the bubble switch hides the phone one too' );
	}

	public function test_except_and_only() {
		$listed   = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$unlisted = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$this->connect(
			array(
				'site_widget' => 'wdg_site',
				'site_rule'   => 'except',
				'site_pages'  => array( $listed ),
			)
		);
		$this->assertSame( '', $this->footer_at( $listed ) );
		$this->assertStringContainsString( 'wdg_site', $this->footer_at( $unlisted ) );

		$this->connect(
			array(
				'site_widget' => 'wdg_site',
				'site_rule'   => 'only',
				'site_pages'  => array( $listed ),
			)
		);
		$this->assertStringContainsString( 'wdg_site', $this->footer_at( $listed ) );
		$this->assertSame( '', $this->footer_at( $unlisted ) );
	}

	public function test_the_posts_page_follows_the_rule_like_any_page() {
		$posts = self::factory()->post->create(
			array(
				'post_type' => 'page',
				'post_name' => 'actualites',
			)
		);
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', self::factory()->post->create( array( 'post_type' => 'page' ) ) );
		update_option( 'page_for_posts', $posts );
		$this->connect(
			array(
				'site_widget' => 'wdg_site',
				'site_rule'   => 'except',
				'site_pages'  => array( $posts ),
			)
		);
		$this->go_to( get_permalink( $posts ) );
		$this->assertTrue( is_home() );
		$this->new_page_view();
		ob_start();
		Livia_Widgets::footer();
		$this->assertSame( '', ob_get_clean() );
	}

	public function test_a_page_chooses_no_widget_or_another_one() {
		$this->connect( array( 'site_widget' => 'wdg_site' ) );
		$none  = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$other = self::factory()->post->create();
		update_post_meta( $none, Livia_Widgets::META_MODE, 'none' );
		update_post_meta( $other, Livia_Widgets::META_MODE, 'other' );
		update_post_meta( $other, Livia_Widgets::META_WIDGET, 'wdg_other' );

		$this->assertSame( '', $this->footer_at( $none ) );
		$footer = $this->footer_at( $other );
		$this->assertStringContainsString( 'widget="wdg_other"', $footer );
		$this->assertStringNotContainsString( 'wdg_site', $footer );
	}

	public function test_nothing_without_a_restaurant() {
		$page = self::factory()->post->create( array( 'post_type' => 'page' ) );
		update_post_meta( $page, Livia_Widgets::META_MODE, 'other' );
		update_post_meta( $page, Livia_Widgets::META_WIDGET, 'wdg_other' );
		$this->assertSame( '', $this->footer_at( 0 ) );
		$this->assertSame( '', $this->footer_at( $page ) );
	}

	public function test_only_wins_over_all_and_a_page_shows_one_bubble() {
		$this->connect( array( 'site_widget' => 'wdg_site' ) );
		$terrasse = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$other    = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$this->connect_second(
			array(
				'site_widget' => 'wdg_t_site',
				'site_rule'   => 'only',
				'site_pages'  => array( $terrasse ),
			)
		);

		$footer = $this->footer_at( $terrasse );
		$this->assertStringContainsString( 'widget="wdg_t_site"', $footer );
		$this->assertSame( 1, substr_count( $footer, '<livia-chat' ) );
		$this->assertStringContainsString( 'widget="wdg_site"', $this->footer_at( $other ) );

		Livia_Service::save_restaurant( 'r2', array( 'bubble_on' => false ) );
		$this->assertStringContainsString( 'widget="wdg_site"', $this->footer_at( $terrasse ), 'a bubble turned off gives its pages back' );
	}

	public function test_no_bubble_on_any_restaurant_s_group_page() {
		$this->connect( array( 'site_widget' => 'wdg_site' ) );
		$this->connect_second( array( 'site_widget' => 'wdg_t_site' ) );
		$group = Livia_Group_Page::publish( 'r2', 'groupe-terrasse', 'Terrasse' )['page'];
		$this->assertSame( '', $this->footer_at( $group->ID ) );
	}

	public function test_the_section_switch_takes_every_bubble_off_but_not_the_widgets_in_the_content() {
		$this->connect( array( 'site_widget' => 'wdg_site' ), array( 'site_on' => false ) );
		$other = self::factory()->post->create( array( 'post_type' => 'page' ) );
		update_post_meta( $other, Livia_Widgets::META_MODE, 'other' );
		update_post_meta( $other, Livia_Widgets::META_WIDGET, 'wdg_other' );

		$this->assertSame( '', $this->footer_at( 0 ) );
		$this->assertSame( '', $this->footer_at( $other ) );
		$this->assertStringContainsString( '<livia-chat widget="wdg_page"', do_blocks( '<!-- wp:livia/widget {"widget":"wdg_page"} /-->' ) );
	}

	public function test_another_bubble_must_be_one_of_a_connected_restaurant() {
		$this->connect( array( 'site_widget' => 'wdg_site' ) );
		$page = self::factory()->post->create( array( 'post_type' => 'page' ) );
		update_post_meta( $page, Livia_Widgets::META_MODE, 'other' );
		update_post_meta( $page, Livia_Widgets::META_WIDGET, 'wdg_gone' );
		$this->assertSame( '', $this->footer_at( $page ) );
		update_post_meta( $page, Livia_Widgets::META_WIDGET, 'wdg_page' );
		$this->assertSame( '', $this->footer_at( $page ), 'an embedded conversation is no bubble' );
	}

	public function test_one_bubble_per_page() {
		$this->connect( array( 'site_widget' => 'wdg_site' ) );
		$this->go_to( home_url( '/' ) );
		$first  = do_shortcode( '[livia_widget widget="wdg_other"]' );
		$second = do_shortcode( '[livia_widget widget="wdg_other"]' );
		$this->assertStringContainsString( 'widget="wdg_other"', $first );
		$this->assertSame( '', $second );
		ob_start();
		Livia_Widgets::footer();
		$this->assertSame( '', ob_get_clean() );
	}

	public function test_every_widget_the_plugin_prints_starts_only_once_shown() {
		$this->connect(
			array(
				'site_widget'  => 'wdg_site',
				'phone_widget' => 'wdg_other',
			)
		);
		$html = $this->footer_at( 0 )
			. do_blocks( '<!-- wp:livia/widget {"widget":"wdg_page"} /-->' )
			. do_shortcode( '[livia_widget widget="wdg_page"]' );
		$this->assertSame( 4, preg_match_all( '#<livia-chat\b[^>]*>#', $html, $tags ) );
		foreach ( $tags[0] as $tag ) {
			$this->assertStringContainsString( ' start-when-visible', $tag );
		}

		// The sources that write the element themselves: the markup above, and the conversation of the booking window.
		$sources = array_merge( glob( LIVIA_DIR . 'includes/*.php' ), glob( LIVIA_DIR . 'includes/views/*.php' ), glob( LIVIA_DIR . 'src/*.js' ), glob( LIVIA_DIR . 'src/*/*.js' ) );
		$found   = array(
			'printed' => 0,
			'created' => 0,
		);
		foreach ( $sources as $source ) {
			$code              = file_get_contents( $source ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local source file.
			$found['printed'] += preg_match_all( '#<livia-chat\b[^>]*>#', $code, $printed );
			foreach ( $printed[0] as $tag ) {
				$this->assertMatchesRegularExpression( '#\sstart-when-visible\b#', $tag, $source );
			}
			$created           = preg_match_all( '#createElement\(\s*[\'"`]livia-chat[\'"`]\s*\)#', $code );
			$found['created'] += $created;
			$this->assertSame( $created, preg_match_all( '#setAttribute\(\s*[\'"`]start-when-visible[\'"`]\s*,\s*([\'"`])\1\s*\)#', $code ), $source );
		}
		// The scan finds what it checks: a change of style must not leave it checking nothing.
		$this->assertGreaterThan( 0, $found['printed'], 'no printed <livia-chat> found in the sources' );
		$this->assertGreaterThan( 0, $found['created'], 'no created livia-chat found in the sources' );
	}

	public function test_the_block_places_an_embedded_conversation() {
		$this->connect();
		$html = do_blocks( '<!-- wp:livia/widget {"widget":"wdg_page"} /-->' );
		$this->assertStringContainsString( '<livia-chat widget="wdg_page"', $html );
		$this->assertStringContainsString( 'wp-block-livia-widget', $html );
		$this->assertSame( '', do_blocks( '<!-- wp:livia/widget /-->' ) );
	}

	public function test_a_widget_livia_lists_without_a_format_is_placed_without_a_warning() {
		$this->connect();
		$connections = get_option( 'livia_connections' );
		unset( $connections['r1']['widgets'][0]['format'] );
		update_option( 'livia_connections', $connections );
		$html = do_shortcode( '[livia_widget widget="wdg_page"]' );
		$this->assertStringContainsString( '<livia-chat widget="wdg_page"', $html );
		$this->assertStringNotContainsString( 'data-livia-bubble', $html );
	}

	public function test_a_widget_livia_lists_without_a_name_shows_in_the_editor_and_the_screen_without_a_warning() {
		$this->connect(
			array(
				'bubble_on'   => true,
				'site_widget' => 'wdg_site',
			),
			array( 'site_on' => true )
		);
		$connections = get_option( 'livia_connections' );
		foreach ( array_keys( $connections['r1']['widgets'] ) as $index ) {
			unset( $connections['r1']['widgets'][ $index ]['name'] );
		}
		update_option( 'livia_connections', $connections );
		set_transient( 'livia_checked_r1', 1, 900 );
		Livia_Widgets::editor_assets();
		$this->assertStringContainsString( 'wdg_page', $this->screen( 'widget' ) );
	}

	public function test_a_widget_of_no_connected_restaurant_is_not_placed() {
		$this->connect();
		$this->assertSame( '', do_shortcode( '[livia_widget widget="wdg_foreign"]' ) );
		$this->assertSame( '', do_blocks( '<!-- wp:livia/widget {"widget":"wdg_foreign"} /-->' ) );
		$this->assertStringContainsString( 'widget="wdg_page"', do_shortcode( '[livia_widget widget="wdg_page"]' ) );
	}

	public function test_opening_an_editor_refreshes_once_per_five_minutes() {
		$this->connect();
		$this->answers['/wordpress/v1/connection'] = array(
			200,
			array(
				'restaurant' => array( 'name' => 'Brasserie Lumière' ),
				'widgets'    => array(
					array(
						'id'         => 'wdg_new',
						'format'     => 'inline',
						'fullscreen' => false,
					),
				),
			),
		);
		$this->assertSame( '', do_shortcode( '[livia_widget widget="wdg_new"]' ), 'the site render never calls Livia' );
		$this->assertSame( array(), $this->calls );

		Livia_Widgets::editor_assets();
		Livia_Widgets::editor_assets();
		$this->assertCount( 1, $this->calls );
		$this->assertStringContainsString( 'widget="wdg_new"', do_shortcode( '[livia_widget widget="wdg_new"]' ) );

		delete_transient( 'livia_checked_r1' );
		Livia_Widgets::editor_assets();
		$this->assertCount( 2, $this->calls );
	}

	public function test_the_editor_names_each_screen_s_widget_when_two_restaurants_share_the_bubble() {
		$this->connect(
			array(
				'bubble_on'    => true,
				'site_widget'  => '',
				'phone_widget' => 'wdg_other',
			),
			array( 'site_on' => true )
		);
		$this->connect_second(
			array(
				'bubble_on'   => true,
				'site_widget' => 'wdg_t_site',
			)
		);
		set_transient( 'livia_checked_r1', 1, 60 );
		set_transient( 'livia_checked_r2', 1, 60 );
		// Another test may have reset the scripts after the block registered its editor script.
		if ( ! wp_script_is( 'livia-widget-editor-script', 'registered' ) ) {
			wp_register_script( 'livia-widget-editor-script', '', array(), LIVIA_VERSION, true );
		}
		Livia_Widgets::editor_assets();
		$before = implode( "\n", (array) wp_scripts()->get_data( 'livia-widget-editor-script', 'before' ) );
		$this->assertStringContainsString( '"siteWidget":"La Terrasse, site on desktop, Privatisation on phone"', $before );
	}

	public function test_a_hostile_widget_id_stays_an_attribute() {
		$markup = Livia_Widgets::markup( '"><script>alert(1)</script>' );
		$this->assertStringNotContainsString( '<script', $markup );
		$this->assertStringStartsWith( '<livia-chat widget="&quot;&gt;&lt;script&gt;', $markup );
	}

	public function test_only_who_may_edit_a_post_sets_its_widget() {
		$post = self::factory()->post->create();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertTrue( current_user_can( 'edit_post_meta', $post, Livia_Widgets::META_MODE ), 'a protected meta, opened by its auth_callback' );
		$this->assertTrue( current_user_can( 'edit_post_meta', $post, Livia_Widgets::META_WIDGET ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'contributor' ) ) );
		$this->assertFalse( current_user_can( 'edit_post_meta', $post, Livia_Widgets::META_MODE ) );
		$this->assertFalse( current_user_can( 'edit_post_meta', $post, Livia_Widgets::META_WIDGET ) );
	}

	public function test_the_widget_names_its_server_unless_it_is_production() {
		$this->connect();
		$markup = Livia_Widgets::markup( 'wdg_page' );
		if ( 'https://chat.booking.wearelivia.com' === LIVIA_PUBLIC_URL ) {
			$this->assertSame( '<livia-chat widget="wdg_page" start-when-visible></livia-chat>', $markup );
		} else {
			$this->assertStringContainsString( 'backend-url="' . LIVIA_PUBLIC_URL . '"', $markup );
		}
	}
}
