<?php
/**
 * The group page in landing mode: its template and the booking tools' scripts left out.
 *
 * @package Livia
 */

// phpcs:disable WordPress.WP.EnqueuedResources,WordPress.WP.EnqueuedResourceParameters,WordPress.WP.GlobalVariablesOverride -- Scripts of other tools, as a site prints them; each test starts from WordPress's empty script queue.

final class LandingTest extends Livia_TestCase {

	private $page;

	public function set_up() {
		parent::set_up();
		$GLOBALS['wp_scripts'] = null;
		$this->connect();
		$this->new_request();
		$this->answers['/wordpress/v1/page'] = $this->page_answer( '<div class="livia-rp"><livia-chat widget="wdg_page"></livia-chat></div>' );
		$this->page                          = Livia_Group_Page::publish( 'r1', 'reservation-groupe', 'Réservation de groupe' )['page'];
	}

	public function tear_down() {
		$GLOBALS['wp_scripts'] = null;
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

	public function test_a_page_in_the_theme_keeps_every_script() {
		$this->go_to( get_permalink( $this->page ) );
		wp_enqueue_script( 'zenchef-sdk', 'https://sdk.zenchef.com/v1/sdk.min.js', array(), null, false );
		$this->assertStringContainsString( 'sdk.zenchef.com', get_echo( 'wp_head' ) );
	}
}
