<?php
/**
 * Livia's scripts and group pages kept out of the cache and optimisation tools, each rule checked the way the tool
 * reads it on the tags the plugin prints.
 *
 * @package Livia
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound,WordPress.WP.EnqueuedResources,WordPress.WP.GlobalVariablesOverride -- The hooks of the cache tools and the tags they read; each test starts from WordPress's empty script queue.

final class CacheToolsTest extends Livia_TestCase {

	private $bundle;

	public function set_up() {
		parent::set_up();
		$GLOBALS['wp_scripts'] = null;
		$this->connect();
		$this->new_request();
		$this->bundle = Livia_Service::public_url( Livia_Widgets::BUNDLE );
	}

	public function tear_down() {
		$GLOBALS['wp_scripts'] = null;
		parent::tear_down();
	}

	/**
	 * The tags of the widget bundle and of an inline script attached to it, as WordPress prints them.
	 */
	private function bundle_tags() {
		$handle = Livia_Widgets::register_script( $this->bundle );
		wp_add_inline_script( $handle, 'var liviaExperience = {};', 'before' );
		return get_echo( 'wp_print_scripts', array( $handle ) );
	}

	/**
	 * The script of a group page that hands `?couverts` to the conversation.
	 */
	private function guests_tag() {
		$this->answers['/wordpress/v1/page'] = $this->page_answer();
		$page                                = Livia_Group_Page::publish( 'r1', 'reservation-groupe', 'Réservation de groupe' )['page'];
		$this->go_to( get_permalink( $page ) );
		// Without the theme's `script` support, WordPress wraps an inline script in a CDATA comment.
		preg_match( '#<script\b[^>]*>(?:(?!</script>).)*?liviaGuests.*?</script>#s', do_blocks( $page->post_content ), $tag );
		return $tag[0];
	}

	private function matches_one( $patterns, $subject ) {
		foreach ( $patterns as $pattern ) {
			if ( preg_match( "#{$pattern}#i", $subject ) ) {
				return true;
			}
		}
		return false;
	}

	public function test_wp_rocket_leaves_every_livia_script_as_printed() {
		$tags = $this->bundle_tags();
		preg_match_all( '#<script\b[^>]*>.*?</script>#s', $tags . $this->guests_tag(), $scripts );
		$this->assertCount( 3, $scripts[0] );

		// Delay JS matches each pattern against the whole tag.
		$delay = apply_filters( 'rocket_delay_js_exclusions', array() );
		foreach ( $scripts[0] as $script ) {
			$this->assertTrue( $this->matches_one( $delay, $script ), $script );
		}
		$this->assertFalse( $this->matches_one( $delay, '<script src="https://example.com/app.js"></script>' ) );
		// Defer and minification match the address, inline combination the content.
		$this->assertTrue( $this->matches_one( apply_filters( 'rocket_exclude_defer_js', array() ), $this->bundle ) );
		$this->assertTrue( $this->matches_one( apply_filters( 'rocket_exclude_js', array() ), wp_parse_url( LIVIA_URL . 'build/experience.js', PHP_URL_PATH ) ) );
		$this->assertTrue( $this->matches_one( apply_filters( 'rocket_minify_excluded_external_js', array() ), $this->bundle ) );
		$inline = apply_filters( 'rocket_excluded_inline_js_content', array() );
		$this->assertContains( 'liviaExperience', $inline );
		$this->assertContains( 'liviaGuests', $inline );
	}

	public function test_litespeed_reads_the_attributes_of_each_livia_script() {
		preg_match_all( '#<script\b([^>]*)>#', $this->bundle_tags() . $this->guests_tag(), $openings );
		$this->assertCount( 3, $openings[1] );
		foreach ( $openings[1] as $attributes ) {
			$this->assertStringContainsString( 'data-no-optimize="1"', $attributes );
			$this->assertStringContainsString( 'data-no-defer="1"', $attributes );
			$this->assertStringContainsString( 'data-cfasync="false"', $attributes );
			$this->assertStringContainsString( 'data-jetpack-boost="ignore"', $attributes );
		}
		// Its exclusion settings are substrings of the address.
		$found = array_filter( apply_filters( 'litespeed_optimize_js_excludes', array() ), fn( $pattern ) => false !== strpos( $this->bundle, $pattern ) );
		$this->assertNotEmpty( $found );
		$this->assertNotEmpty( array_filter( apply_filters( 'litespeed_optm_js_defer_exc', array() ), fn( $pattern ) => false !== strpos( $this->bundle, $pattern ) ) );
	}

	public function test_another_script_keeps_its_attributes() {
		wp_register_script( 'site-app', 'https://example.com/app.js', array(), '1', true );
		$this->assertStringNotContainsString( 'data-no-optimize', get_echo( 'wp_print_scripts', array( 'site-app' ) ) );
	}

	public function test_the_tools_working_on_handles_leave_livia_s_alone() {
		Livia_Widgets::register_script( $this->bundle );
		$livia = 'livia-' . md5( $this->bundle );
		foreach ( array( 'wphb_minify_resource', 'wphb_combine_resource', 'wphb_defer_resource', 'wphb_async_resource' ) as $filter ) {
			$this->assertFalse( apply_filters( $filter, true, $livia, 'scripts', $this->bundle ), $filter );
			$this->assertTrue( apply_filters( $filter, true, 'jquery', 'scripts', '' ), $filter );
		}
		$this->assertTrue( apply_filters( 'wphb_dont_combine_handles', false, $livia, 'scripts' ) );
		$this->assertFalse( apply_filters( 'js_do_concat', true, $livia ) );
		$this->assertContains( $livia, apply_filters( 'sgo_js_minify_exclude', array( 'jquery' ) ) );
		$this->assertContains( $livia . '-js-before', apply_filters( 'sgo_javascript_combine_exclude_ids', array() ) );
		$this->assertFalse( apply_filters( 'w3tc_minify_js_do_tag_minification', true, '<script src="' . $this->bundle . '"></script>', '' ) );
		$this->assertTrue( apply_filters( 'w3tc_minify_js_do_tag_minification', true, '<script src="https://example.com/app.js"></script>', '' ) );
		$this->assertStringStartsWith( 'jquery.js, ', apply_filters( 'autoptimize_filter_js_exclude', 'jquery.js', '' ) );
		// Another plugin may hand Autoptimize an array: pattern => '' to exclude, => 'remove' to drop the script.
		$excluded = apply_filters(
			'autoptimize_filter_js_exclude',
			array(
				'jquery.js' => '',
				'ads.js'    => 'remove',
			),
			''
		);
		$this->assertSame( array( 'ads.js' ), array_keys( $excluded, 'remove' ) ); // phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict -- Autoptimize compares loosely.
		$this->assertContains( 'jquery.js', array_keys( $excluded ) );
		$this->assertContains( 'liviaGuests', array_keys( $excluded ) );
	}

	public function test_only_the_group_page_escapes_the_page_cache() {
		$nocache = 0;
		$count   = static function () use ( &$nocache ) {
			++$nocache;
		};
		add_action( 'litespeed_control_set_nocache', $count );
		if ( ! function_exists( 'wpfc_exclude_current_page' ) ) {
			// WP Fastest Cache's public function, as it defines it; it detects the tool by its class.
			function wpfc_exclude_current_page() { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WP Fastest Cache's own function.
				do_action( 'wpfc_exclude_current_page' );
			}
		}
		// WP Fastest Cache excludes the page only when its own function is in the call stack.
		add_action(
			'wpfc_exclude_current_page',
			static function () use ( $count ) {
				if ( in_array( 'wpfc_exclude_current_page', array_column( debug_backtrace(), 'function' ), true ) ) { // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace -- The test WP Fastest Cache makes.
					$count();
				}
			}
		);

		$this->go_to( get_permalink( self::factory()->post->create( array( 'post_type' => 'page' ) ) ) );
		Livia_Cache::group_page_uncached();
		$this->assertSame( 0, $nocache );
		$this->assertTrue( apply_filters( 'flying_press_is_cacheable', true ) );

		$this->guests_tag();
		Livia_Cache::group_page_uncached();
		$this->assertSame( 2, $nocache );
		$this->assertTrue( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE );
		$this->assertTrue( defined( 'LITESPEED_NO_LAZY' ), 'LiteSpeed Cache lazy loads no image of the group page' );
		$this->assertFalse( apply_filters( 'flying_press_is_cacheable', true ) );

		// SiteGround Optimizer anchors each part after the home address and matches the address asked, query included.
		$parts = apply_filters( 'sgo_exclude_urls_from_cache', array() );
		$this->assertSame( array( '/reservation-groupe/*' ), $parts );
		$regex = sprintf( '/%s(%s)$/i', preg_quote( home_url(), '/' ), str_replace( '\*', '.*', preg_quote( $parts[0], '/' ) ) );
		$this->assertMatchesRegularExpression( $regex, home_url( '/reservation-groupe/?couverts=12' ) );
		$this->assertDoesNotMatchRegularExpression( $regex, home_url( '/menu/' ) );
	}

	public function test_siteground_keeps_the_rest_of_the_site_cached_when_the_group_page_is_the_home_page() {
		$this->guests_tag();
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', Livia_Group_Page::published( 'r1' )->ID );

		$parts = apply_filters( 'sgo_exclude_urls_from_cache', array() );
		$this->assertSame( array( '/', '/?*' ), $parts );
		$regex = sprintf( '/%s(%s)$/i', preg_quote( home_url(), '/' ), implode( '|', array_map( static fn( $part ) => str_replace( '\*', '.*', preg_quote( $part, '/' ) ), $parts ) ) );
		$this->assertMatchesRegularExpression( $regex, home_url( '/' ) );
		$this->assertMatchesRegularExpression( $regex, home_url( '/?couverts=12' ) );
		$this->assertDoesNotMatchRegularExpression( $regex, home_url( '/menu/' ) );
	}

	/**
	 * WP Super Cache's function, defined for this process only: it marks the tool as detected.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_wp_super_cache_empties_the_current_site_only() {
		$GLOBALS['livia_test_cleared'] = array();
		function wp_cache_clear_cache( $blog_id = 0 ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WP Super Cache's own function.
			$GLOBALS['livia_test_cleared'][] = $blog_id;
		}

		if ( is_multisite() ) {
			switch_to_blog( self::factory()->blog->create() );
		}
		$this->assertSame( array( 'WP Super Cache' ), Livia_Cache::purge() );
		// 0 empties the cache of every site of a network: a site of a network empties its own.
		$this->assertSame( array( is_multisite() ? get_current_blog_id() : 0 ), $GLOBALS['livia_test_cleared'] );
		if ( is_multisite() ) {
			$this->assertGreaterThan( 1, $GLOBALS['livia_test_cleared'][0] );
			restore_current_blog();
		}
	}

	public function test_no_tool_detected_and_nothing_to_clear_without_one() {
		$this->assertSame( array(), Livia_Cache::detected() );
		$this->assertSame( array(), Livia_Cache::purge() );
	}

	/**
	 * The group page's stylesheet and the booking window's, with its inline style, as WordPress prints them.
	 */
	private function style_tags() {
		$this->guests_tag();
		Livia_Group_Page::enqueue();
		wp_register_style( Livia_Experience::HANDLE, LIVIA_URL . 'build/style-experience.css', array(), '1' );
		wp_add_inline_style( Livia_Experience::HANDLE, ':root{--livia-bg:#fff}' );
		$page = 'livia-' . md5( LIVIA_SERVICE_URL . '/wordpress/v1/assets/page.css?v=1' );
		return get_echo( 'wp_print_styles', array( array( $page, Livia_Experience::HANDLE ) ) );
	}

	public function test_wp_rocket_leaves_livia_s_styles_and_the_group_page_s_images_alone() {
		$GLOBALS['wp_styles'] = null;
		$tags                 = $this->style_tags();
		preg_match_all( '#<link\b[^>]*>#', $tags, $links );
		$this->assertCount( 2, $links[0] );
		foreach ( $links[0] as $link ) {
			$this->assertStringContainsString( 'data-no-minify="1"', $link, 'its minification skips the tag' );
		}
		// Combination matches the path, deferred loading compares paths exactly, unused CSS removal the quoted tag.
		$experience = wp_parse_url( LIVIA_URL . 'build/style-experience.css', PHP_URL_PATH );
		$this->assertTrue( $this->matches_one( apply_filters( 'rocket_exclude_css', array() ), $experience ) );
		$async = apply_filters( 'rocket_exclude_async_css', array() );
		$this->assertContains( '/wordpress/v1/assets/page.css', $async );
		$this->assertContains( $experience, $async );
		$external = array_map( static fn( $pattern ) => preg_quote( $pattern, '/' ), apply_filters( 'rocket_rucss_external_exclusions', array() ) );
		foreach ( $links[0] as $link ) {
			$this->assertMatchesRegularExpression( '/' . implode( '|', $external ) . '/', $link );
		}
		$this->assertDoesNotMatchRegularExpression( '/' . implode( '|', $external ) . '/', '<link rel="stylesheet" href="https://example.com/theme.css">' );
		preg_match( '#<style\b([^>]*)>#', $tags, $inline );
		$this->assertMatchesRegularExpression( '/' . implode( '|', array_map( 'preg_quote', apply_filters( 'rocket_rucss_inline_atts_exclusions', array() ) ) ) . '/', $inline[1] );

		$this->assertFalse( apply_filters( 'do_rocket_lazyload', true ), 'no lazy loading on the group page' );
		$GLOBALS['wp_styles'] = null;
		$this->go_to( get_permalink( self::factory()->post->create( array( 'post_type' => 'page' ) ) ) );
		$this->assertTrue( apply_filters( 'do_rocket_lazyload', true ), 'the other pages keep theirs' );
	}

	public function test_litespeed_leaves_livia_s_styles_alone() {
		$GLOBALS['wp_styles'] = null;
		$tags                 = $this->style_tags();
		preg_match_all( '#<link\b[^>]*>|<style\b[^>]*>.*?</style>#s', $tags, $found );
		$this->assertCount( 3, $found[0] );
		$excludes = apply_filters( 'litespeed_optimize_css_excludes', array() );
		foreach ( $found[0] as $tag ) {
			// Its exclusions are substrings of the whole tag.
			$this->assertNotEmpty( array_filter( $excludes, static fn( $pattern ) => false !== strpos( $tag, $pattern ) ), $tag );
		}
		foreach ( array_slice( $found[0], 0, 2 ) as $link ) {
			$this->assertStringContainsString( 'data-no-optimize="1"', $link );
		}
		$GLOBALS['wp_styles'] = null;
	}

	public function test_lazy_loading_turns_off_on_the_group_page_only() {
		$this->go_to( get_permalink( self::factory()->post->create( array( 'post_type' => 'page' ) ) ) );
		$this->assertSame( array(), apply_filters( 'sgo_lazy_load_exclude_urls', array() ), 'an address on another page would turn it off there' );
		$this->assertTrue( apply_filters( 'w3tc_lazyload_can_process', array( 'enabled' => true ) )['enabled'] );

		$this->guests_tag();
		$this->assertSame( array( home_url( '/reservation-groupe/' ) ), apply_filters( 'sgo_lazy_load_exclude_urls', array() ) );
		$this->assertFalse( apply_filters( 'w3tc_lazyload_can_process', array( 'enabled' => true ) )['enabled'] );
		$this->assertFalse( apply_filters( 'autoptimize_filter_imgopt_should_lazyload', true, '' ) );
	}
}
