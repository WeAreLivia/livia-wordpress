<?php
/**
 * The group page in landing mode: its template and the booking tools' scripts left out.
 *
 * @package Livia
 */

// phpcs:disable WordPress.WP.EnqueuedResources,WordPress.WP.EnqueuedResourceParameters,WordPress.WP.GlobalVariablesOverride,WordPress.NamingConventions.PrefixAllGlobals -- Scripts of other tools, as a site prints them; each test starts from WordPress's empty script queue.

final class LandingTest extends Livia_TestCase {

	private $page;

	private $features;

	public function set_up() {
		parent::set_up();
		$GLOBALS['wp_scripts'] = null;
		$this->features        = $GLOBALS['_wp_theme_features'] ?? array();
		$this->connect();
		$this->new_request();
		$this->answers['/wordpress/v1/page'] = $this->page_answer( '<div class="livia-rp livia-rp--landing"><livia-chat widget="wdg_page"></livia-chat></div>' );
		$this->page                          = Livia_Group_Page::publish( 'r1', 'reservation-groupe', 'Réservation de groupe' )['page'];
	}

	public function tear_down() {
		$GLOBALS['wp_scripts']         = null;
		$GLOBALS['_wp_theme_features'] = $this->features;
		parent::tear_down();
	}

	public function test_only_a_landing_page_takes_the_plugin_s_template() {
		$this->go_to( get_permalink( $this->page ) );
		$this->assertSame( 'theme.php', apply_filters( 'template_include', 'theme.php' ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress's own filter.

		Livia_Service::save_restaurant( 'r1', array( 'page_layout' => 'landing' ) );
		$this->assertSame( LIVIA_DIR . 'includes/views/landing.php', apply_filters( 'template_include', 'theme.php' ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress's own filter.

		$this->go_to( get_permalink( self::factory()->post->create( array( 'post_type' => 'page' ) ) ) );
		$this->assertSame( 'theme.php', apply_filters( 'template_include', 'theme.php' ), 'another page keeps its theme' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress's own filter.
	}

	public function test_a_page_livia_rendered_for_the_theme_stays_in_the_theme_in_landing_mode() {
		$this->answers['/wordpress/v1/page'] = $this->page_answer( '<div class="livia-rp livia-rp--colonne"><p>livia-rp--landing</p><livia-chat widget="wdg_page"></livia-chat></div>' );
		Livia_Service::save_restaurant( 'r1', array( 'page_layout' => 'landing' ) );
		$this->go_to( get_permalink( $this->page ) );
		wp_enqueue_script( 'zenchef-sdk', 'https://sdk.zenchef.com/v1/sdk.min.js', array(), null, false );

		$this->assertNull( Livia_Group_Page::landing() );
		$this->assertSame( 'theme.php', apply_filters( 'template_include', 'theme.php' ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress's own filter.
		Livia_Group_Page::dequeue_booking_tools();
		$this->assertTrue( wp_script_is( 'zenchef-sdk' ), 'a page in the theme keeps the site\'s scripts' );

		$this->answers['/wordpress/v1/page'] = $this->page_answer( '<div class="livia-rp-hote"><template shadowrootmode="open"><div class="livia-rp livia-rp--landing">Livia</div></template></div>' );
		Livia_Service::forget_page( 'r1' );
		$this->new_request();
		$this->assertSame( LIVIA_DIR . 'includes/views/landing.php', apply_filters( 'template_include', 'theme.php' ), 'an isolated landing page' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress's own filter.
	}

	public function test_the_embed_address_of_a_landing_page_keeps_wordpress_s_embed_template() {
		Livia_Service::save_restaurant( 'r1', array( 'page_layout' => 'landing' ) );
		$this->go_to( get_post_embed_url( $this->page ) );
		$this->assertTrue( is_embed() );
		$this->assertSame( 'r1', Livia_Group_Page::landing() );
		$this->assertSame( 'embed.php', apply_filters( 'template_include', 'embed.php' ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress's own filter.
	}

	public function test_the_landing_page_keeps_the_site_s_scripts_and_livia_s_but_not_the_booking_tools() {
		Livia_Service::save_restaurant( 'r1', array( 'page_layout' => 'landing' ) );
		$this->go_to( get_permalink( $this->page ) );
		$bundle = LIVIA_SERVICE_URL . '/livia-chat.standalone.js';
		// Livia's own inline settings may carry the restaurant's booking address.
		$livia = Livia_Widgets::register_script( $bundle );
		wp_add_inline_script( $livia, 'var liviaTool="https://bookings.zenchef.com/results?rid=1";document.createElement("a");', 'before' );
		wp_enqueue_script( 'site-analytics', 'https://www.googletagmanager.com/gtag/js?id=G-1', array(), null, false );
		wp_enqueue_script( 'zenchef-sdk', 'https://sdk.zenchef.com/v1/sdk.min.js', array(), null, false );
		wp_add_inline_script( 'zenchef-sdk', 'var zenchefConfig={};', 'before' );
		$printed = static function () {
			echo '<script src="https://widget.thefork.com/loader.js"></script>';
		};
		add_action( 'wp_head', $printed );
		add_action( 'wp_footer', $printed );

		ob_start();
		include LIVIA_DIR . 'includes/views/landing.php';
		$html = ob_get_clean();
		remove_action( 'wp_head', $printed );
		remove_action( 'wp_footer', $printed );

		$this->assertStringContainsString( '<livia-chat widget="wdg_page"></livia-chat>', $html, 'the fragment' );
		$this->assertStringContainsString( 'liviaGuests', $html, 'the number of guests' );
		$this->assertStringContainsString( 'googletagmanager.com/gtag/js', $html );
		$this->assertStringContainsString( $bundle, $html );
		$this->assertStringContainsString( 'bookings.zenchef.com', $html, 'Livia\'s own settings stay' );
		$this->assertStringContainsString( 'livia-landing', $html );
		$this->assertStringNotContainsString( 'sdk.zenchef.com', $html );
		$this->assertStringNotContainsString( 'zenchefConfig', $html, 'its inline script goes with it' );
		$this->assertStringNotContainsString( 'widget.thefork.com', $html );
	}

	public function test_a_landing_page_never_received_leads_back_to_the_site() {
		Livia_Service::save_restaurant( 'r1', array( 'page_layout' => 'landing' ) );
		Livia_Service::forget_page( 'r1' );
		$this->answers['/wordpress/v1/page'] = array( 403, array( 'error' => 'connector_disabled' ) );
		$this->go_to( get_permalink( $this->page ) );

		ob_start();
		include LIVIA_DIR . 'includes/views/landing.php';
		$html = ob_get_clean();
		$this->assertStringContainsString( 'temporarily unavailable', $html );
		$this->assertStringContainsString( '<a href="' . esc_url( home_url( '/' ) ) . '">Back to the site</a>', $html );

		Livia_Service::save_restaurant( 'r1', array( 'page_layout' => 'theme' ) );
		$this->assertStringNotContainsString( 'Back to the site', do_blocks( $this->page->post_content ), 'the theme leads back itself' );
	}

	public function test_a_page_received_for_the_theme_is_not_shown_as_a_landing_page_while_livia_does_not_answer() {
		$this->go_to( get_permalink( $this->page ) );
		$this->assertStringContainsString( 'wdg_page', do_blocks( $this->page->post_content ) );

		unset( $this->answers['/wordpress/v1/page'] );
		Livia_Service::save_restaurant( 'r1', array( 'page_layout' => 'landing' ) );
		$this->new_request();
		$html = do_blocks( $this->page->post_content );
		$this->assertStringNotContainsString( 'wdg_page', $html, 'the theme\'s page has no way back to the site' );
		$this->assertStringContainsString( 'Back to the site', $html );

		Livia_Service::save_restaurant( 'r1', array( 'page_layout' => 'theme' ) );
		Livia_Service::forget_page( 'r1' );
		$this->assertStringContainsString( 'wdg_page', do_blocks( $this->page->post_content ), 'the theme keeps its copy' );
	}

	public function test_a_landing_page_received_is_served_whatever_the_address_of_the_site_while_livia_does_not_answer() {
		Livia_Service::save_restaurant( 'r1', array( 'page_layout' => 'landing' ) );
		Livia_Service::forget_page( 'r1' );
		$this->go_to( get_permalink( $this->page ) );
		$this->assertStringContainsString( 'wdg_page', do_blocks( $this->page->post_content ) );

		unset( $this->answers['/wordpress/v1/page'] );
		$other = static fn() => 'https://other.example.test';
		add_filter( 'home_url', $other );
		$this->new_request();
		$html = do_blocks( $this->page->post_content );
		remove_filter( 'home_url', $other );
		$this->assertStringContainsString( 'wdg_page', $html );
	}

	private function landing_head() {
		Livia_Service::save_restaurant( 'r1', array( 'page_layout' => 'landing' ) );
		$this->go_to( get_permalink( $this->page ) );
		ob_start();
		include LIVIA_DIR . 'includes/views/landing.php';
		return ob_get_clean();
	}

	public function test_a_landing_page_on_a_block_theme_prints_one_title_and_one_viewport() {
		switch_theme( 'twentytwentyfour' );
		$GLOBALS['_wp_theme_features']['block-templates'] = true; // A block theme declares it when it loads, which switch_theme() does not replay.
		remove_theme_support( 'title-tag' );
		locate_block_template( '', 'page', array( 'page.php' ) ); // The template loader's own step: it hooks the block theme's title and viewport.
		$this->assertNotFalse( has_action( 'wp_head', '_block_template_viewport_meta_tag' ), 'the block theme hooks its viewport' );
		$this->assertNotFalse( has_action( 'wp_head', '_block_template_render_title_tag' ), 'the block theme hooks its title' );
		$html = $this->landing_head();
		$this->assertSame( 1, substr_count( $html, '<title>' ), 'one title' );
		$this->assertSame( 1, substr_count( $html, 'name="viewport"' ), 'one viewport' );
	}

	public function test_a_landing_page_on_a_classic_theme_prints_one_title_and_one_viewport_whether_it_declares_the_title_tag_or_not() {
		$GLOBALS['_wp_theme_features']['title-tag'] = true; // add_theme_support() refuses it once WordPress has loaded.
		$html                                       = $this->landing_head();
		$this->assertSame( 1, substr_count( $html, '<title>' ), 'the theme\'s title only' );
		$this->assertSame( 1, substr_count( $html, 'name="viewport"' ), 'the page\'s viewport' );

		remove_theme_support( 'title-tag' );
		$html = $this->landing_head();
		$this->assertSame( 1, substr_count( $html, '<title>' ), 'the page\'s own title' );
		$this->assertSame( 1, substr_count( $html, 'name="viewport"' ) );
	}

	public function test_a_page_in_the_theme_keeps_every_script() {
		$this->go_to( get_permalink( $this->page ) );
		wp_enqueue_script( 'zenchef-sdk', 'https://sdk.zenchef.com/v1/sdk.min.js', array(), null, false );
		$this->assertStringContainsString( 'sdk.zenchef.com', get_echo( 'wp_head' ) );
	}
}
