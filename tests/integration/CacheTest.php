<?php
/**
 * The group page from Livia: cache, last version kept, unavailable notice.
 *
 * @package Livia
 */

final class CacheTest extends Livia_TestCase {

	public function set_up() {
		parent::set_up();
		$this->connect();
		$this->new_request();
	}

	private function page_calls() {
		return count( array_filter( $this->calls, static fn( $url ) => false !== strpos( $url, '/wordpress/v1/page' ) ) );
	}

	public function test_the_page_is_cached_for_the_time_livia_gives() {
		$this->answers['/wordpress/v1/page'] = $this->page_answer();
		$page                                = Livia_Service::group_page( 'r1' );
		$this->assertSame( '<div class="livia-rp">Livia</div>', $page['html'] );
		$this->assertSame( array( LIVIA_SERVICE_URL . '/wordpress/v1/assets/page.css?v=1' ), $page['styles'] );
		$this->assertStringContainsString( 'widget=wdg_page', $this->calls[0] );
		$this->assertStringContainsString( 'key=' . self::KEY, $this->calls[0] );

		Livia_Service::forget_page( 'r1' );
		set_transient( 'livia_page_' . md5( self::KEY . '|wdg_page' ), array( 'page' => $page ), 900 );
		Livia_Service::group_page( 'r1' );
		$this->assertSame( 1, $this->page_calls() );
	}

	public function test_the_headline_is_sent_when_set_and_has_its_own_cache() {
		$this->answers['/wordpress/v1/page'] = $this->page_answer();
		Livia_Service::group_page( 'r1' );
		$this->assertStringNotContainsString( 'headline', $this->calls[0] );

		Livia_Service::save_restaurant( 'r1', array( 'headline' => 'Vos événements à la Brasserie' ) );
		$this->new_request();
		Livia_Service::group_page( 'r1' );
		$this->assertSame( 2, $this->page_calls() );
		$this->assertStringContainsString( 'headline=Vos%20%C3%A9v%C3%A9nements%20%C3%A0%20la%20Brasserie', $this->calls[1] );
	}

	public function test_livia_down_serves_the_last_version_and_waits_before_asking_again() {
		$this->answers['/wordpress/v1/page'] = $this->page_answer( '<div class="livia-rp">Version 1</div>' );
		Livia_Service::group_page( 'r1' );
		Livia_Service::forget_page( 'r1' );

		unset( $this->answers['/wordpress/v1/page'] );
		$this->assertSame( '<div class="livia-rp">Version 1</div>', Livia_Service::group_page( 'r1' )['html'] );
		$this->assertSame( 2, $this->page_calls() );

		$this->new_request();
		Livia_Service::group_page( 'r1' );
		$this->assertSame( 2, $this->page_calls() );
	}

	public function test_a_new_headline_or_widget_keeps_the_last_version_while_livia_is_down() {
		$this->answers['/wordpress/v1/page'] = $this->page_answer( '<div class="livia-rp">Version 1</div>' );
		Livia_Service::group_page( 'r1' );
		unset( $this->answers['/wordpress/v1/page'] );

		Livia_Service::save_restaurant( 'r1', array( 'headline' => 'Vos événements' ) );
		$this->new_request();
		$this->assertSame( '<div class="livia-rp">Version 1</div>', Livia_Service::group_page( 'r1' )['html'] );

		Livia_Service::save_restaurant( 'r1', array( 'page_widget' => 'wdg_other' ) );
		$this->new_request();
		$this->assertSame( '<div class="livia-rp">Version 1</div>', Livia_Service::group_page( 'r1' )['html'] );
	}

	public function test_a_refused_key_or_a_turned_off_connector_drops_the_last_version() {
		$refusals = array(
			array( 404, array( 'error' => 'unknown_key' ) ),
			array( 403, array( 'error' => 'connector_disabled' ) ),
			array(
				403,
				array(
					'error'  => 'connector_disabled',
					'reason' => 'account_inactive',
				),
			),
		);
		foreach ( $refusals as $refusal ) {
			$this->answers['/wordpress/v1/page'] = $this->page_answer( '<div class="livia-rp">Version 1</div>' );
			Livia_Service::group_page( 'r1' );
			Livia_Service::forget_page( 'r1' );

			$this->answers['/wordpress/v1/page'] = $refusal;
			$this->assertNull( Livia_Service::group_page( 'r1' ) );
			$this->assertNull( Livia_Service::last_page( 'r1' ) );
			Livia_Service::forget_page( 'r1' );
		}
	}

	public function test_the_last_version_of_another_key_is_not_served() {
		$this->answers['/wordpress/v1/page'] = $this->page_answer( '<div class="livia-rp">Restaurant A</div>' );
		Livia_Service::group_page( 'r1' );
		// A visit that asked with the previous key finishes after the key changed.
		Livia_Service::save_restaurant( 'r1', array( 'key' => 'lvwp_bbbbbbbbbbbbbbbbbbbbbbbbbbbbbb' ) );
		Livia_Service::forget_page( 'r1' );
		unset( $this->answers['/wordpress/v1/page'] );
		$this->assertNull( Livia_Service::last_page( 'r1' ) );
		$this->assertNull( Livia_Service::group_page( 'r1' ) );
	}

	public function test_the_time_livia_gives_is_capped_and_calls_follow_no_redirect() {
		$answer                              = $this->page_answer();
		$answer[1]['cacheSeconds']           = 10 * YEAR_IN_SECONDS;
		$this->answers['/wordpress/v1/page'] = $answer;
		Livia_Service::group_page( 'r1' );
		$expires = (int) get_option( '_transient_timeout_livia_page_' . md5( self::KEY . '|wdg_page' ) );
		$this->assertGreaterThan( time(), $expires );
		$this->assertLessThanOrEqual( time() + Livia_Service::CACHE_MAX, $expires );
		$this->assertSame( 0, $this->args[0]['redirection'] );
	}

	public function test_the_restaurant_text_runs_no_shortcode() {
		$ran = false;
		add_shortcode(
			'livia_witness',
			static function () use ( &$ran ) {
				$ran = true;
				return 'WITNESS';
			}
		);
		$this->answers['/wordpress/v1/page'] = $this->page_answer( '<div class="livia-rp"><p>Menu [Nouveau]</p><p>[caption width=1 caption=x]Offert[/caption] [gallery ids=1] [livia_witness]</p></div>' );
		$page                                = Livia_Group_Page::publish( 'r1', 'reservation-groupe', 'Réservation de groupe' )['page'];
		$this->go_to( get_permalink( $page ) );
		$html = apply_filters( 'the_content', $page->post_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress's own filter.
		remove_shortcode( 'livia_witness' );

		$this->assertFalse( $ran );
		$this->assertStringNotContainsString( 'WITNESS', $html );
		$this->assertStringNotContainsString( '<figure', $html );
		$this->assertStringContainsString( '&#91;gallery ids=1&#93;', $html );
		$this->assertStringContainsString( '&#91;caption width=1 caption=x&#93;Offert&#91;/caption&#93;', $html );
		$this->assertStringContainsString( 'Menu &#91;Nouveau&#93;', $html );
		$this->assertStringContainsString( 'Menu [Nouveau]', html_entity_decode( wp_strip_all_tags( $html ) ), 'the visitor reads the same text' );
	}

	public function test_a_page_never_received_shows_a_short_notice() {
		$html = do_blocks( '<!-- wp:livia/group-page {"align":"full"} /-->' );
		$this->assertStringContainsString( 'temporarily unavailable', $html );
		$this->assertStringContainsString( 'alignfull', $html );
	}

	public function test_the_block_renders_the_page_and_its_styles_load_in_the_head() {
		$this->answers['/wordpress/v1/page'] = $this->page_answer();
		$page                                = Livia_Group_Page::publish( 'r1', 'reservation-groupe', 'Réservation de groupe' )['page'];
		$this->go_to( get_permalink( $page ) );
		Livia_Group_Page::enqueue();
		$this->assertTrue( wp_style_is( 'livia-' . md5( LIVIA_SERVICE_URL . '/wordpress/v1/assets/page.css?v=1' ), 'enqueued' ) );
		$this->assertFalse( wp_style_is( 'livia-' . md5( 'https://elsewhere.test/evil.css' ), 'enqueued' ) );
		$this->assertStringContainsString( '<div class="livia-rp">Livia</div>', do_blocks( $page->post_content ) );
	}

	public function test_each_restaurant_has_its_own_page() {
		$this->connect_second();
		$this->answers['/wordpress/v1/page'] = function () {
			$key = false !== strpos( end( $this->calls ), self::KEY2 ) ? 'Terrasse' : 'Brasserie';
			return $this->page_answer( '<div class="livia-rp">' . $key . '</div>' );
		};
		$brasserie                           = Livia_Group_Page::publish( 'r1', 'reservation-groupe', 'Brasserie' )['page'];
		$terrasse                            = Livia_Group_Page::publish( 'r2', 'groupe-terrasse', 'Terrasse' )['page'];

		$this->go_to( get_permalink( $terrasse ) );
		$this->assertSame( 'r2', Livia_Group_Page::current() );
		$this->assertStringContainsString( 'Terrasse</div>', do_blocks( $terrasse->post_content ) );
		$this->go_to( get_permalink( $brasserie ) );
		$this->assertStringContainsString( 'Brasserie</div>', do_blocks( $brasserie->post_content ) );
		$this->assertStringContainsString( 'widget=wdg_t_page', implode( ' ', $this->calls ) );
	}

	public function test_a_page_of_no_connected_restaurant_shows_the_notice() {
		$this->answers['/wordpress/v1/page'] = $this->page_answer();
		$page                                = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_content' => '<!-- wp:livia/group-page {"align":"full"} /-->',
			)
		);
		$this->go_to( get_permalink( $page ) );
		$this->assertStringContainsString( 'temporarily unavailable', apply_filters( 'the_content', get_post( $page )->post_content ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress's own filter.
		$this->assertCount( 0, $this->calls );
	}

	public function test_the_number_of_guests_reaches_the_conversation_without_changing_the_page() {
		$this->answers['/wordpress/v1/page'] = $this->page_answer( '<div class="livia-rp"><livia-chat widget="wdg_page"></livia-chat></div>' );
		$page                                = Livia_Group_Page::publish( 'r1', 'reservation-groupe', 'Réservation de groupe' )['page'];

		$this->go_to( get_permalink( $page ) );
		$plain = apply_filters( 'the_content', $page->post_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress's own filter.
		$this->new_request();
		$this->go_to( add_query_arg( 'couverts', '12', get_permalink( $page ) ) );
		$with = apply_filters( 'the_content', $page->post_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress's own filter.

		$this->assertSame( $plain, $with, 'the same page for every address, so its cache holds' );
		$this->assertSame( 1, $this->page_calls() );
		$this->assertStringContainsString( 'couverts', $with );
		$this->assertStringContainsString( 'setAttribute("guests"', $with );
		$this->assertStringContainsString( 'if(liviaGuests>=1&&999>=liviaGuests&&liviaGuests%1===0){', $with, 'wptexturize leaves the script body intact' );
		$this->assertStringContainsString( '.shadowRoot', $with, 'an isolated page keeps its conversation in a shadow root' );
		$this->assertStringNotContainsString( '&#038;', $with );
		$this->assertLessThan( strpos( $with, '<script' ), strpos( $with, '<livia-chat' ), 'the script follows the conversation it hands the number to' );
	}

	public function test_a_shortcode_of_the_site_leaves_the_number_of_guests_script_intact() {
		$this->answers['/wordpress/v1/page'] = $this->page_answer( '<div class="livia-rp"><livia-chat widget="wdg_page"></livia-chat></div>' );
		$page                                = Livia_Group_Page::publish( 'r1', 'reservation-groupe', 'Réservation de groupe' )['page'];
		$this->go_to( get_permalink( $page ) );
		$plain = apply_filters( 'the_content', $page->post_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress's own filter.
		// A theme or another plugin may declare any shortcode name; `the_content` runs do_shortcode after the block.
		add_shortcode( 'root', '__return_empty_string' );
		$html = apply_filters( 'the_content', $page->post_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress's own filter.
		remove_shortcode( 'root' );

		$this->assertSame( $plain, $html );
		preg_match( '#<script\b[^>]*>(.*?liviaGuests.*?)</script>#s', $html, $script );
		$this->assertNotEmpty( $script );
		$body = preg_replace( '#^\s*/\* <!\[CDATA\[ \*/|/\* \]\]> \*/\s*$#', '', $script[1] ); // WordPress 6.5 wraps an inline script in a CDATA comment.
		$this->assertDoesNotMatchRegularExpression( '/[\[\]<]/', $body, 'no bracket for do_shortcode, no `<` for wptexturize' );
	}

	public function test_a_site_updated_from_1_0_keeps_its_page_and_its_cache_key() {
		// Options as 1.0 saved them: neither the display mode nor the styles.
		$this->assertArrayNotHasKey( 'page_layout', get_option( 'livia_restaurants' )['r1'] );
		$this->assertArrayNotHasKey( 'page_styles', get_option( 'livia_settings' ) );
		$this->assertSame( 'theme', Livia_Service::restaurant( 'r1' )['page_layout'] );
		$this->assertSame( 'inherit', Livia_Service::settings()['page_styles'] );
		$this->assertSame(
			array(
				'key'    => self::KEY,
				'widget' => 'wdg_page',
			),
			Livia_Service::page_query( 'r1' )
		);

		$this->answers['/wordpress/v1/page'] = $this->page_answer();
		Livia_Service::group_page( 'r1' );
		$this->assertIsArray( get_transient( 'livia_page_' . md5( self::KEY . '|wdg_page' ) ), 'the transient 1.0 wrote' );
		foreach ( array( 'layout', 'styles', 'home' ) as $argument ) {
			$this->assertStringNotContainsString( $argument . '=', $this->calls[0] );
		}
	}

	public function test_the_copy_1_0_kept_still_serves_while_livia_does_not_answer() {
		update_option(
			'livia_last_' . md5( self::KEY ),
			array(
				'html'    => '<div class="livia-rp">1.0</div>',
				'styles'  => array(),
				'scripts' => array(),
				'version' => 'v0',
			),
			false
		);
		$this->assertSame( '<div class="livia-rp">1.0</div>', Livia_Service::group_page( 'r1' )['html'] );

		Livia_Service::save_settings( array( 'page_styles' => 'isolated' ) );
		$this->new_request();
		$this->assertNull( Livia_Service::group_page( 'r1' ), 'it was rendered for the inherited styles' );
	}

	public function test_the_landing_mode_and_isolated_styles_are_asked_for_with_their_own_cache() {
		$this->answers['/wordpress/v1/page'] = $this->page_answer();
		Livia_Service::save_restaurant( 'r1', array( 'page_layout' => 'landing' ) );
		Livia_Service::save_settings( array( 'page_styles' => 'isolated' ) );
		Livia_Service::group_page( 'r1' );

		$this->assertStringContainsString( 'layout=landing', $this->calls[0] );
		$this->assertStringContainsString( 'styles=isolated', $this->calls[0] );
		$this->assertStringContainsString( 'home=' . rawurlencode( home_url( '/' ) ), $this->calls[0] );
		$this->assertFalse( get_transient( 'livia_page_' . md5( self::KEY . '|wdg_page' ) ) );

		// A service that ignores the mode answers with the page for the theme: it is served as it comes.
		$this->assertSame( '<div class="livia-rp">Livia</div>', Livia_Service::group_page( 'r1' )['html'] );
	}
}
