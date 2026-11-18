<?php
/**
 * Livia's scripts, styles and group pages kept out of the cache and optimisation tools.
 *
 * @package Livia
 */

defined( 'ABSPATH' ) || exit;

/**
 * Exclusions applied through each tool's own hooks and the attributes it reads on a script or style tag, nothing
 * written in the tools' settings: they stop with the plugin. The hooks are added whether a tool runs or not, as most
 * tools load after the plugin; a hook of a tool that does not run is never called.
 *
 * The tools delay, defer, minify or combine the scripts of a page: Livia's must run as they are printed, or the bubble
 * and its opening, the booking window and the number of guests of `?couverts` wait for the visitor's first gesture.
 * Their style optimisations (combination, deferred loading, unused CSS removal) see neither the booking window, drawn
 * later, nor an isolated group page, whose content and stylesheet sit in a shadow root; their image lazy loading cannot
 * reach that shadow root either. Their page cache would keep a group page well beyond the time Livia gives it, see
 * Livia_Service::CACHE_MAX.
 */
final class Livia_Cache {

	/**
	 * Attributes put on every Livia script tag, each read by the tools named: values as strings, the tools test them.
	 */
	const ATTRIBUTES = array(
		// LiteSpeed Cache: no minify, combine or defer.
		'data-no-optimize'   => '1',
		// LiteSpeed Cache: no defer or delay.
		'data-no-defer'      => '1',
		// WP Fastest Cache: no minify or combine.
		'data-no-minify'     => '1',
		// Autoptimize: no aggregation or defer.
		'data-noptimize'     => '1',
		// Cloudflare Rocket Loader, LiteSpeed Cache.
		'data-cfasync'       => 'false',
		// Jetpack Boost: no defer.
		'data-jetpack-boost' => 'ignore',
		// NitroPack, as on its own scripts.
		'nitro-exclude'      => true,
	);

	/**
	 * Attributes put on every Livia stylesheet link: LiteSpeed Cache (no combination or unused CSS removal), WP Rocket
	 * (no minification) and Autoptimize (no aggregation).
	 */
	const STYLE_ATTRIBUTES = ' data-no-optimize="1" data-no-minify="1" data-noptimize="1"';

	/**
	 * The tools detected, by a constant, class or function each one defines, and the state of each rule: `auto` applied
	 * by the plugin, `copy` to copy into the tool's settings, `markup` left to the attributes Livia puts on its images,
	 * `none` when the tool does not do it, or only to the scripts its user lists.
	 */
	const TOOLS = array(
		'wp-rocket'        => array(
			'name'   => 'WP Rocket',
			'detect' => 'WP_ROCKET_VERSION',
			'rules'  => array(
				'delay'   => 'auto',
				'defer'   => 'auto',
				'minify'  => 'auto',
				'combine' => 'auto',
				'page'    => 'auto',
				'css'     => 'auto',
				'lazy'    => 'auto',
			),
		),
		'litespeed'        => array(
			'name'   => 'LiteSpeed Cache',
			'detect' => 'LSCWP_V',
			'rules'  => array(
				'delay'   => 'auto',
				'defer'   => 'auto',
				'minify'  => 'auto',
				'combine' => 'auto',
				'page'    => 'auto',
				'css'     => 'auto',
				'lazy'    => 'auto',
			),
		),
		'w3tc'             => array(
			'name'   => 'W3 Total Cache',
			'detect' => 'W3TC',
			'rules'  => array(
				'delay'   => 'none',
				'defer'   => 'none',
				'minify'  => 'auto',
				'combine' => 'auto',
				'page'    => 'auto',
				'css'     => 'auto',
				'lazy'    => 'auto',
			),
		),
		'wp-super-cache'   => array(
			'name'   => 'WP Super Cache',
			'detect' => 'wp_cache_clear_cache',
			'rules'  => array(
				'delay'   => 'none',
				'defer'   => 'none',
				'minify'  => 'none',
				'combine' => 'none',
				'page'    => 'auto',
				'css'     => 'none',
				'lazy'    => 'none',
			),
		),
		// Its lazy loading runs only on a page it caches, never on the group page, see group_page_uncached().
		'wp-fastest-cache' => array(
			'name'   => 'WP Fastest Cache',
			'detect' => 'WpFastestCache',
			'rules'  => array(
				'delay'   => 'none',
				'defer'   => 'none',
				'minify'  => 'auto',
				'combine' => 'auto',
				'page'    => 'auto',
				'css'     => 'copy',
				'lazy'    => 'auto',
			),
		),
		'autoptimize'      => array(
			'name'   => 'Autoptimize',
			'detect' => 'AUTOPTIMIZE_PLUGIN_VERSION',
			'rules'  => array(
				'delay'   => 'none',
				'defer'   => 'auto',
				'minify'  => 'auto',
				'combine' => 'auto',
				'page'    => 'none',
				'css'     => 'auto',
				'lazy'    => 'auto',
			),
		),
		'siteground'       => array(
			'name'   => 'SiteGround Optimizer',
			'detect' => 'SiteGround_Optimizer\VERSION',
			'rules'  => array(
				'delay'   => 'none',
				'defer'   => 'auto',
				'minify'  => 'auto',
				'combine' => 'auto',
				'page'    => 'auto',
				'css'     => 'auto',
				'lazy'    => 'auto',
			),
		),
		'perfmatters'      => array(
			'name'   => 'Perfmatters',
			'detect' => 'PERFMATTERS_VERSION',
			'rules'  => array(
				'delay'   => 'auto',
				'defer'   => 'auto',
				'minify'  => 'auto',
				'combine' => 'none',
				'page'    => 'none',
				'css'     => 'auto',
				'lazy'    => 'auto',
			),
		),
		'flyingpress'      => array(
			'name'   => 'FlyingPress',
			'detect' => 'FLYING_PRESS_VERSION',
			'rules'  => array(
				'delay'   => 'auto',
				'defer'   => 'auto',
				'minify'  => 'auto',
				'combine' => 'none',
				'page'    => 'auto',
				'css'     => 'copy',
				'lazy'    => 'copy',
			),
		),
		'hummingbird'      => array(
			'name'   => 'Hummingbird',
			'detect' => 'WPHB_VERSION',
			'rules'  => array(
				'delay'   => 'auto',
				'defer'   => 'auto',
				'minify'  => 'auto',
				'combine' => 'auto',
				'page'    => 'auto',
				'css'     => 'auto',
				'lazy'    => 'none',
			),
		),
		// Its delay and its file exclusions read only its own settings.
		'breeze'           => array(
			'name'   => 'Breeze',
			'detect' => 'BREEZE_VERSION',
			'rules'  => array(
				'delay'   => 'copy',
				'defer'   => 'none',
				'minify'  => 'copy',
				'combine' => 'copy',
				'page'    => 'auto',
				'css'     => 'auto',
				'lazy'    => 'markup',
			),
		),
		'jetpack-boost'    => array(
			'name'   => 'Jetpack Boost',
			'detect' => 'JETPACK_BOOST_VERSION',
			'rules'  => array(
				'delay'   => 'none',
				'defer'   => 'auto',
				'minify'  => 'auto',
				'combine' => 'auto',
				'page'    => 'auto',
				'css'     => 'auto',
				'lazy'    => 'none',
			),
		),
		// It optimises the pages on its own servers and decides its cache before WordPress knows the page.
		'nitropack'        => array(
			'name'   => 'NitroPack',
			'detect' => 'NITROPACK_VERSION',
			'rules'  => array(
				'delay'   => 'copy',
				'defer'   => 'copy',
				'minify'  => 'copy',
				'combine' => 'copy',
				'page'    => 'copy',
				'css'     => 'copy',
				'lazy'    => 'copy',
			),
		),
	);

	/**
	 * Filters receiving a list of patterns, each matched as a substring (or a regular expression of plain characters)
	 * against a script's address, tag or content.
	 */
	const PATTERN_FILTERS = array(
		'rocket_delay_js_exclusions',
		'rocket_exclude_defer_js',
		'rocket_defer_inline_exclusions',
		'rocket_exclude_js',
		'rocket_minify_excluded_external_js',
		'rocket_excluded_inline_js_content',
		'litespeed_optimize_js_excludes',
		'litespeed_optm_js_defer_exc',
		'litespeed_optm_gm_js_exc',
		'sgo_javascript_combine_excluded_inline_content',
		'sgo_javascript_combine_excluded_external_paths',
		'sgo_javascript_combine_excluded_internal_paths',
		'perfmatters_delay_js_exclusions',
		'perfmatters_defer_js_exclusions',
		'perfmatters_minify_js_exclusions',
		'flying_press_exclude_from_delay:js',
		'flying_press_exclude_from_defer:js',
		'flying_press_exclude_from_minify:js',
		'wphb_delay_js_exclusions',
	);

	/**
	 * Filters receiving a list of patterns matched against a stylesheet's address or tag, see style_patterns().
	 */
	const STYLE_PATTERN_FILTERS = array(
		'rocket_exclude_css',
		'rocket_rucss_external_exclusions',
		'litespeed_optimize_css_excludes',
		'wphb_critical_css_exclusions',
		'perfmatters_minify_css_exclusions',
		'perfmatters_rucss_excluded_stylesheets',
		'flying_press_exclude_from_minify:css',
	);


	/**
	 * Hooks the exclusions.
	 */
	public static function init() {
		add_filter( 'wp_script_attributes', array( __CLASS__, 'script_attributes' ) );
		add_filter( 'wp_inline_script_attributes', array( __CLASS__, 'script_attributes' ) );
		add_action( 'template_redirect', array( __CLASS__, 'group_page_uncached' ) );
		foreach ( self::PATTERN_FILTERS as $filter ) {
			add_filter( $filter, array( __CLASS__, 'with_patterns' ) );
		}
		add_filter( 'autoptimize_filter_js_exclude', array( __CLASS__, 'autoptimize_exclude' ) );
		add_filter( 'sgo_js_minify_exclude', array( __CLASS__, 'with_handles' ) );
		add_filter( 'sgo_js_async_exclude', array( __CLASS__, 'with_handles' ) );
		add_filter( 'sgo_javascript_combine_exclude_ids', array( __CLASS__, 'with_ids' ) );
		add_filter( 'sgo_exclude_urls_from_cache', array( __CLASS__, 'with_group_paths' ) );
		add_filter( 'w3tc_minify_js_do_tag_minification', array( __CLASS__, 'w3tc_minify' ), 99, 2 );
		add_filter( 'js_do_concat', array( __CLASS__, 'unless_ours' ), 99, 2 );
		// Hummingbird applies its user's choices at priority 10.
		foreach ( array( 'wphb_minify_resource', 'wphb_combine_resource', 'wphb_defer_resource', 'wphb_async_resource' ) as $filter ) {
			add_filter( $filter, array( __CLASS__, 'unless_ours' ), 99, 2 );
		}
		add_filter( 'wphb_dont_combine_handles', array( __CLASS__, 'if_ours' ), 99, 2 );
		add_filter( 'flying_press_is_cacheable', array( __CLASS__, 'unless_group_page' ) );

		add_filter( 'style_loader_tag', array( __CLASS__, 'style_attributes' ), 10, 2 );
		foreach ( self::STYLE_PATTERN_FILTERS as $filter ) {
			add_filter( $filter, array( __CLASS__, 'with_style_patterns' ) );
		}
		add_filter( 'autoptimize_filter_css_exclude', array( __CLASS__, 'with_style_list' ) );
		add_filter( 'breeze_filter_css_exclude', array( __CLASS__, 'with_style_list' ) );
		add_filter( 'rocket_exclude_async_css', array( __CLASS__, 'with_style_paths' ) );
		add_filter( 'rocket_rucss_inline_atts_exclusions', array( __CLASS__, 'with_inline_style_id' ) );
		add_filter( 'sgo_css_minify_exclude', array( __CLASS__, 'with_style_handles' ) );
		add_filter( 'sgo_css_combine_exclude', array( __CLASS__, 'with_style_handles' ) );
		add_filter( 'w3tc_minify_css_do_tag_minification', array( __CLASS__, 'w3tc_minify' ), 99, 2 );
		add_filter( 'css_do_concat', array( __CLASS__, 'unless_ours' ), 99, 2 );
		// Jetpack Boost loads a stylesheet as it is printed for any method but `async` or `deferred`.
		add_filter( 'jetpack_boost_async_style', array( __CLASS__, 'unless_ours' ), 99, 2 );

		// Image lazy loading: off on the group page; LiteSpeed Cache's, by a constant, see group_page_uncached().
		foreach ( array( 'do_rocket_lazyload', 'autoptimize_filter_imgopt_should_lazyload', 'perfmatters_lazyload' ) as $filter ) {
			add_filter( $filter, array( __CLASS__, 'unless_group_page' ), 99 );
		}
		add_filter( 'w3tc_lazyload_can_process', array( __CLASS__, 'w3tc_lazyload' ), 99 );
		add_filter( 'sgo_lazy_load_exclude_urls', array( __CLASS__, 'with_group_page_url' ) );
	}

	/**
	 * Whether a script handle is Livia's.
	 *
	 * @param string $handle Handle.
	 * @return bool
	 */
	public static function is_ours( $handle ) {
		return 0 === strpos( (string) $handle, 'livia-' );
	}

	/**
	 * What tells Livia's scripts apart: the hosts of the Livia service, the plugin's built scripts, the variables of
	 * its inline scripts. Letters, digits and `-./` only, so that they work as substrings and as regular expressions.
	 *
	 * @return string[]
	 */
	public static function patterns() {
		return array_values( array_unique( array_merge( Livia_Service::hosts(), array( wp_parse_url( LIVIA_URL, PHP_URL_PATH ) . 'build/', 'liviaExperience', 'liviaGuests' ) ) ) );
	}

	/**
	 * Adds Livia's attributes to its script tags, those with an id `livia-…`, inline ones included.
	 *
	 * @param array $attributes Tag attributes.
	 * @return array
	 */
	public static function script_attributes( $attributes ) {
		return isset( $attributes['id'] ) && self::is_ours( $attributes['id'] ) ? array_merge( $attributes, self::ATTRIBUTES ) : $attributes;
	}

	/**
	 * Adds Livia's patterns to a tool's list.
	 *
	 * @param mixed $entries Patterns.
	 * @return array
	 */
	public static function with_patterns( $entries ) {
		return array_merge( (array) $entries, self::patterns() );
	}

	/**
	 * What tells Livia's stylesheets apart: the hosts of the Livia service, which serves the group page's, the plugin's
	 * built styles, and the id of the booking window's inline style.
	 *
	 * @return string[]
	 */
	public static function style_patterns() {
		return array_values( array_unique( array_merge( Livia_Service::hosts(), array( wp_parse_url( LIVIA_URL, PHP_URL_PATH ) . 'build/', Livia_Experience::HANDLE . '-inline-css' ) ) ) );
	}

	/**
	 * Adds Livia's style patterns to a tool's list.
	 *
	 * @param mixed $entries Patterns.
	 * @return array
	 */
	public static function with_style_patterns( $entries ) {
		return array_merge( (array) $entries, self::style_patterns() );
	}

	/**
	 * Adds Livia's style patterns to a comma-separated list, as Autoptimize and Breeze read it.
	 *
	 * @param string $entries Exclusions.
	 * @return string
	 */
	public static function with_style_list( $entries ) {
		return trim( (string) $entries . ', ' . implode( ', ', self::style_patterns() ), ', ' );
	}

	/**
	 * Adds the paths of Livia's registered stylesheets, which WP Rocket compares exactly to the path of each link.
	 *
	 * @param mixed $entries Paths.
	 * @return array
	 */
	public static function with_style_paths( $entries ) {
		$paths = (array) $entries;
		foreach ( wp_styles()->registered as $handle => $style ) {
			if ( self::is_ours( $handle ) && is_string( $style->src ) && '' !== $style->src ) {
				$paths[] = (string) wp_parse_url( $style->src, PHP_URL_PATH );
			}
		}
		return $paths;
	}

	/**
	 * Adds the id of the booking window's inline style.
	 *
	 * @param mixed $entries Attribute patterns.
	 * @return array
	 */
	public static function with_inline_style_id( $entries ) {
		return array_merge( (array) $entries, array( Livia_Experience::HANDLE . '-inline-css' ) );
	}

	/**
	 * Adds Livia's stylesheet handles to a tool's list.
	 *
	 * @param mixed $entries Handles.
	 * @return array
	 */
	public static function with_style_handles( $entries ) {
		return array_merge( (array) $entries, array_values( array_filter( array_keys( wp_styles()->registered ), array( __CLASS__, 'is_ours' ) ) ) );
	}

	/**
	 * Adds Livia's attributes to its stylesheet links.
	 *
	 * @param string $tag    Link tag.
	 * @param string $handle Handle.
	 * @return string
	 */
	public static function style_attributes( $tag, $handle ) {
		return self::is_ours( $handle ) ? preg_replace( '#<link\b#', '<link' . self::STYLE_ATTRIBUTES, (string) $tag, 1 ) : $tag;
	}

	/**
	 * Keeps W3 Total Cache's lazy loading off the group page shown.
	 *
	 * @param array $state `enabled`, `buffer` and `reason`.
	 * @return array
	 */
	public static function w3tc_lazyload( $state ) {
		if ( is_array( $state ) && null !== Livia_Group_Page::current() ) {
			$state['enabled'] = false;
			$state['reason']  = 'Livia group booking page';
		}
		return $state;
	}

	/**
	 * The address of the group page shown, for SiteGround Optimizer's lazy loading, which turns off on any page once
	 * its list is not empty: the address goes in on the group page only.
	 *
	 * @param mixed $urls The tool's addresses.
	 * @return array
	 */
	public static function with_group_page_url( $urls ) {
		$urls = (array) $urls;
		$id   = Livia_Group_Page::current();
		$page = null !== $id ? Livia_Group_Page::published( $id ) : null;
		if ( $page ) {
			$urls[] = (string) get_permalink( $page );
		}
		return $urls;
	}

	/**
	 * Adds Livia's patterns to Autoptimize's exclusions: a comma-separated list, or an array whose keys are the
	 * patterns and whose value `remove` drops the script.
	 *
	 * @param string|array $entries Exclusions.
	 * @return string|array
	 */
	public static function autoptimize_exclude( $entries ) {
		if ( is_array( $entries ) ) {
			// An empty value: Autoptimize compares each value loosely with `remove`, which `true` would equal.
			return $entries + array_fill_keys( self::patterns(), '' );
		}
		return trim( (string) $entries . ', ' . implode( ', ', self::patterns() ), ', ' );
	}

	/**
	 * Livia's registered script handles.
	 *
	 * @return string[]
	 */
	private static function handles() {
		return array_values( array_filter( array_keys( wp_scripts()->registered ), array( __CLASS__, 'is_ours' ) ) );
	}

	/**
	 * Adds Livia's script handles to a tool's list.
	 *
	 * @param mixed $entries Handles.
	 * @return array
	 */
	public static function with_handles( $entries ) {
		return array_merge( (array) $entries, self::handles() );
	}

	/**
	 * Adds the ids of Livia's script tags, inline ones included, to a tool's list.
	 *
	 * @param mixed $entries Tag ids.
	 * @return array
	 */
	public static function with_ids( $entries ) {
		$ids = (array) $entries;
		foreach ( self::handles() as $handle ) {
			array_push( $ids, $handle . '-js', $handle . '-js-before', $handle . '-js-after' );
		}
		return $ids;
	}

	/**
	 * Refuses a tool's change to a Livia script handle.
	 *
	 * @param mixed  $value  The tool's value, true to change the script.
	 * @param string $handle Handle.
	 * @return mixed
	 */
	public static function unless_ours( $value, $handle ) {
		return self::is_ours( $handle ) ? false : $value;
	}

	/**
	 * Answers yes for a Livia script handle.
	 *
	 * @param mixed  $value  The tool's value.
	 * @param string $handle Handle.
	 * @return mixed
	 */
	public static function if_ours( $value, $handle ) {
		return self::is_ours( $handle ) ? true : $value;
	}

	/**
	 * Keeps W3 Total Cache from minifying a Livia script or style tag.
	 *
	 * @param bool   $minify Whether it minifies the tag.
	 * @param string $tag    Script or style tag.
	 * @return bool
	 */
	public static function w3tc_minify( $minify, $tag ) {
		foreach ( array_merge( self::patterns(), self::style_patterns() ) as $pattern ) {
			if ( false !== strpos( (string) $tag, $pattern ) ) {
				return false;
			}
		}
		return preg_match( '#\sid=["\']?livia-#', (string) $tag ) ? false : $minify;
	}

	/**
	 * Takes the group page shown out of the page caches that decide while WordPress renders it, and out of LiteSpeed
	 * Cache's image lazy loading.
	 */
	public static function group_page_uncached() {
		if ( null === Livia_Group_Page::current() ) {
			return;
		}
		// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- The constant and the actions of the cache tools.
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		do_action( 'litespeed_control_set_nocache', 'Livia group booking page' );
		if ( ! defined( 'LITESPEED_NO_LAZY' ) ) {
			define( 'LITESPEED_NO_LAZY', true );
		}
		// WP Fastest Cache reads DONOTCACHEPAGE only on a few pages of its own, and excludes a page only when its own
		// function is in the call stack: its action, run directly, leaves the page cached.
		if ( function_exists( 'wpfc_exclude_current_page' ) ) {
			wpfc_exclude_current_page();
		}
		// phpcs:enable
	}

	/**
	 * Answers no for the group page shown.
	 *
	 * @param bool $cacheable The tool's answer.
	 * @return bool
	 */
	public static function unless_group_page( $cacheable ) {
		return null === Livia_Group_Page::current() ? $cacheable : false;
	}

	/**
	 * The published group pages, relative to the site's home and followed by `*`, as SiteGround Optimizer matches them
	 * against the whole address asked, query included.
	 *
	 * @param mixed $parts The tool's excluded addresses.
	 * @return array
	 */
	public static function with_group_paths( $parts ) {
		$parts = (array) $parts;
		$home  = untrailingslashit( home_url() );
		foreach ( self::group_page_urls() as $url ) {
			if ( 0 === strpos( $url, $home ) ) {
				$path = substr( $url, strlen( $home ) );
				// A group page set as the home page has the path `/`, whose `/*` would match the whole site.
				$parts = array_merge( $parts, '/' === $path ? array( '/', '/?*' ) : array( $path . '*' ) );
			}
		}
		return $parts;
	}

	/**
	 * The addresses of the published group pages.
	 *
	 * @return string[]
	 */
	public static function group_page_urls() {
		$urls = array();
		foreach ( array_keys( Livia_Service::restaurants() ) as $id ) {
			$page = Livia_Group_Page::published( (string) $id );
			if ( $page ) {
				$urls[] = (string) get_permalink( $page );
			}
		}
		return $urls;
	}

	/**
	 * The tools running on the site.
	 *
	 * @return array[] See TOOLS, by tool id.
	 */
	public static function detected() {
		return array_filter(
			self::TOOLS,
			static function ( $tool ) {
				return defined( $tool['detect'] ) || class_exists( $tool['detect'], false ) || function_exists( $tool['detect'] );
			}
		);
	}

	/**
	 * Empties the cache of the tools detected, through their public functions and actions.
	 *
	 * @return string[] Names of the tools emptied.
	 */
	public static function purge() {
		$done = array();
		// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- The actions of the cache tools.
		foreach ( self::detected() as $id => $tool ) {
			$purged = true;
			if ( 'wp-rocket' === $id && function_exists( 'rocket_clean_domain' ) ) {
				rocket_clean_domain();
				if ( function_exists( 'rocket_clean_minify' ) ) {
					rocket_clean_minify();
				}
			} elseif ( 'litespeed' === $id ) {
				do_action( 'litespeed_purge_all' );
			} elseif ( 'w3tc' === $id && function_exists( 'w3tc_flush_all' ) ) {
				w3tc_flush_all();
			} elseif ( 'wp-super-cache' === $id ) {
				// Without a site id, it empties the cache of every site of a network.
				wp_cache_clear_cache( is_multisite() ? get_current_blog_id() : 0 );
			} elseif ( 'wp-fastest-cache' === $id ) {
				do_action( 'wpfc_clear_all_cache', true );
			} elseif ( 'autoptimize' === $id && is_callable( array( 'autoptimizeCache', 'clearall' ) ) ) {
				autoptimizeCache::clearall();
			} elseif ( 'siteground' === $id && function_exists( 'sg_cachepress_purge_everything' ) ) {
				sg_cachepress_purge_everything();
			} elseif ( 'flyingpress' === $id && is_callable( array( 'FlyingPress\Purge', 'purge_everything' ) ) ) {
				call_user_func( array( 'FlyingPress\Purge', 'purge_everything' ) );
			} elseif ( 'hummingbird' === $id ) {
				do_action( 'wphb_clear_page_cache' );
			} elseif ( 'breeze' === $id ) {
				do_action( 'breeze_clear_all_cache' );
			} elseif ( 'jetpack-boost' === $id ) {
				do_action( 'jetpack_boost_clear_page_cache_all' );
			} elseif ( 'nitropack' === $id && function_exists( 'nitropack_sdk_purge' ) ) {
				nitropack_sdk_purge( null, null, 'Livia' );
			} else {
				// Perfmatters keeps no cache of pages.
				$purged = false;
			}//end if
			if ( $purged ) {
				$done[] = $tool['name'];
			}
		}//end foreach
		// phpcs:enable
		return $done;
	}
}
