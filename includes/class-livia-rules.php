<?php
/**
 * Decisions that need no WordPress: read by the plugin's classes, tested on their own.
 *
 * @package Livia
 */

defined( 'ABSPATH' ) || exit;

/**
 * Pure rules of the plugin.
 */
final class Livia_Rules {

	/**
	 * Formats a widget can take, as the Livia service names them.
	 */
	const FORMATS = array( 'bubble', 'button', 'inline' );

	/**
	 * Addresses WordPress serves itself, which a page must not take.
	 */
	const RESERVED_SLUGS = array(
		'admin',
		'atom',
		'attachment',
		'author',
		'category',
		'comments',
		'dashboard',
		'embed',
		'favicon',
		'feed',
		'login',
		'page',
		'rdf',
		'robots',
		'rss',
		'rss2',
		'search',
		'sitemap',
		'tag',
		'trackback',
		'type',
		'wp-activate',
		'wp-admin',
		'wp-content',
		'wp-cron',
		'wp-includes',
		'wp-json',
		'wp-login',
		'wp-register',
		'wp-signup',
		'wp-sitemap',
		'xmlrpc',
	);

	/**
	 * Reads a response of the Livia service.
	 *
	 * @param int    $code HTTP status, 0 when the service could not be reached.
	 * @param string $body Response body.
	 * @return array{status: string, data: array|null} Status `ok`, `unknown_key`, `connector_disabled`,
	 *                                                 `unknown_widget` or `unavailable`, and the decoded body on `ok`.
	 */
	public static function read_response( $code, $body ) {
		$data = json_decode( (string) $body, true );
		if ( 200 === $code ) {
			return is_array( $data )
				? array(
					'status' => 'ok',
					'data'   => $data,
				)
				: array(
					'status' => 'unavailable',
					'data'   => null,
				);
		}
		$error  = is_array( $data ) && isset( $data['error'] ) ? (string) $data['error'] : '';
		$known  = array(
			404 => array( 'unknown_key', 'unknown_widget' ),
			403 => array( 'connector_disabled' ),
		);
		$status = isset( $known[ $code ] ) && in_array( $error, $known[ $code ], true ) ? $error : 'unavailable';
		return array(
			'status' => $status,
			'data'   => null,
		);
	}

	/**
	 * Whether a text has the shape of a WordPress key of a Livia restaurant.
	 *
	 * @param string $key Key as pasted.
	 * @return bool
	 */
	public static function is_key_format( $key ) {
		return 1 === preg_match( '/\Alvwp_[a-z0-9]{26,}\z/', (string) $key );
	}

	/**
	 * A key shown without its middle: its prefix and its last four characters.
	 *
	 * @param string $key Restaurant key.
	 * @return string
	 */
	public static function mask_key( $key ) {
		return 'lvwp_…' . substr( (string) $key, -4 );
	}

	/**
	 * The widgets the plugin may place: never a full screen one, and of the formats asked for.
	 *
	 * @param array    $widgets Widgets as the service lists them.
	 * @param string[] $formats Formats kept; all when empty.
	 * @return array[] Widgets kept, in the service's order.
	 */
	public static function usable_widgets( $widgets, $formats = array() ) {
		$kept = array();
		foreach ( (array) $widgets as $widget ) {
			if ( ! is_array( $widget ) || empty( $widget['id'] ) || ! empty( $widget['fullscreen'] ) ) {
				continue;
			}
			$format = isset( $widget['format'] ) ? $widget['format'] : '';
			if ( ! in_array( $format, self::FORMATS, true ) || ( $formats && ! in_array( $format, $formats, true ) ) ) {
				continue;
			}
			$kept[] = $widget;
		}
		return $kept;
	}

	/**
	 * The ids of a widget list.
	 *
	 * @param array[] $widgets Widgets.
	 * @return string[]
	 */
	public static function widget_ids( $widgets ) {
		return array_map( 'strval', array_column( $widgets, 'id' ) );
	}

	/**
	 * The bubble the footer of a page shows, if any.
	 *
	 * @param bool    $site_on        Whether the site widgets section is on: off, no bubble at all.
	 * @param array[] $bubbles        Bubbles of the connected restaurants, see bubble_owner().
	 * @param int     $post_id        The page or post shown, 0 for an archive or a search.
	 * @param string  $mode           The page's own choice: `site`, `none` or `other`.
	 * @param string  $other_id       The bubble the page chose when `other`, empty when it is no connected restaurant's.
	 * @param int[]   $group_page_ids The group booking pages, which have their own conversation.
	 * @param bool    $bubble_placed  Whether the content already placed a bubble.
	 * @return string[] The widget ids on desktop and on phone, empty for none.
	 */
	public static function footer_widget( $site_on, $bubbles, $post_id, $mode, $other_id, $group_page_ids, $bubble_placed ) {
		if ( $bubble_placed || ! $site_on || ( $post_id && in_array( $post_id, $group_page_ids, true ) ) || 'none' === $mode ) {
			return array( '', '' );
		}
		if ( 'other' === $mode ) {
			return array( (string) $other_id, (string) $other_id );
		}
		// Each screen goes to its own restaurant: one without a bubble on a screen leaves that screen to the next rule.
		$desktop = self::bubble_owner( $bubbles, $post_id, 'widget' );
		$phone   = self::bubble_owner( $bubbles, $post_id, 'phone' );
		return array(
			null === $desktop ? '' : (string) $bubbles[ $desktop ]['widget'],
			null === $phone ? '' : (string) $bubbles[ $phone ]['phone'],
		);
	}

	/**
	 * Whether a restaurant shows its bubble on a kind of screen, or on at least one.
	 *
	 * @param array       $bubble See bubble_owner().
	 * @param string|null $screen `widget` (desktop) or `phone`; null for either.
	 * @return bool
	 */
	public static function shows_bubble( $bubble, $screen = null ) {
		$screens = null === $screen ? array( 'widget', 'phone' ) : array( $screen );
		foreach ( $screens as $key ) {
			if ( '' !== (string) ( $bubble[ $key ] ?? '' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * The restaurant whose bubble a page shows: a page listed under "Only" goes to the first restaurant listing it, any
	 * other page to the first restaurant whose "All" or "All, except" covers it.
	 *
	 * @param array[]     $bubbles Bubbles by restaurant, in the screen's order: `widget` and `phone` (the bubble on
	 *                             desktop and on phone, each empty when that screen shows none), `rule` (`all`,
	 *                             `except` or `only`) and `pages`.
	 * @param int         $post_id The page or post, 0 for an archive or a search.
	 * @param string|null $screen  `widget` (desktop) or `phone`: only the restaurants with a bubble on that screen
	 *                             count; null for either screen.
	 * @return int|string|null The restaurant's key in $bubbles, null for none.
	 */
	public static function bubble_owner( $bubbles, $post_id, $screen = null ) {
		$listed = static function ( $bubble ) use ( $post_id ) {
			return $post_id && in_array( (int) $post_id, array_map( 'intval', (array) $bubble['pages'] ), true );
		};
		foreach ( $bubbles as $index => $bubble ) {
			if ( self::shows_bubble( $bubble, $screen ) && 'only' === $bubble['rule'] && $listed( $bubble ) ) {
				return $index;
			}
		}
		foreach ( $bubbles as $index => $bubble ) {
			if ( self::shows_bubble( $bubble, $screen ) && 'only' !== $bubble['rule'] && ! ( 'except' === $bubble['rule'] && $listed( $bubble ) ) ) {
				return $index;
			}
		}
		return null;
	}

	/**
	 * The pages restaurants hold under "Only", each with the first restaurant listing it.
	 *
	 * @param array[]     $bubbles See bubble_owner().
	 * @param string|null $screen  `widget` (desktop) or `phone`: only the restaurants with a bubble on that screen
	 *                             hold a page; null for either screen.
	 * @return array Restaurant key in $bubbles, by page id.
	 */
	public static function taken_pages( $bubbles, $screen = null ) {
		$taken = array();
		foreach ( $bubbles as $index => $bubble ) {
			if ( ! self::shows_bubble( $bubble, $screen ) || 'only' !== $bubble['rule'] ) {
				continue;
			}
			foreach ( array_map( 'intval', (array) $bubble['pages'] ) as $page ) {
				if ( ! isset( $taken[ $page ] ) ) {
					$taken[ $page ] = $index;
				}
			}
		}
		return $taken;
	}

	/**
	 * The pages another restaurant holds under "Only" on every screen a restaurant shows its bubble on (on both
	 * screens when it shows none), which that restaurant cannot list under "Only".
	 *
	 * @param array[]    $bubbles See bubble_owner().
	 * @param int|string $index   The restaurant's key in $bubbles.
	 * @return array Key in $bubbles of the restaurant holding the page, by page id.
	 */
	public static function taken_from( $bubbles, $index ) {
		$screens = array_filter(
			array( 'widget', 'phone' ),
			static fn( $screen ) => self::shows_bubble( $bubbles[ $index ], $screen )
		);
		$taken   = array_map( static fn( $screen ) => self::taken_pages( $bubbles, $screen ), $screens ? $screens : array( 'widget', 'phone' ) );
		$held    = array();
		foreach ( array_keys( array_replace( ...array_values( $taken ) ) ) as $page ) {
			$owners = array_map( static fn( $pages ) => $pages[ $page ] ?? $index, $taken );
			if ( ! in_array( $index, $owners, true ) ) {
				$held[ $page ] = reset( $owners );
			}
		}
		return $held;
	}

	/**
	 * How many of the site's pages show a restaurant's bubble, on desktop or on phone.
	 *
	 * @param array[]    $bubbles  See bubble_owner().
	 * @param int|string $index    The restaurant's key in $bubbles.
	 * @param int[]      $page_ids Published pages of the site.
	 * @return int
	 */
	public static function bubble_page_count( $bubbles, $index, $page_ids ) {
		$screens = array_filter( array( 'widget', 'phone' ), static fn( $screen ) => self::shows_bubble( $bubbles[ $index ], $screen ) );
		$count   = 0;
		foreach ( $page_ids as $page_id ) {
			foreach ( $screens as $screen ) {
				if ( self::bubble_owner( $bubbles, (int) $page_id, $screen ) === $index ) {
					++$count;
					break;
				}
			}
		}
		return $count;
	}

	/**
	 * Whether WordPress serves an address itself.
	 *
	 * @param string   $slug  Address, one segment.
	 * @param string[] $extra The site's own reserved addresses (REST prefix, category and tag bases).
	 * @return bool
	 */
	public static function is_reserved_slug( $slug, $extra = array() ) {
		$reserved = array_merge( self::RESERVED_SLUGS, array_filter( array_map( 'strval', $extra ) ) );
		return in_array( $slug, $reserved, true ) || 1 === preg_match( '/\A(page)?\d+\z/', $slug );
	}

	/**
	 * The rewrite rule that would serve an address instead of a page, if any.
	 *
	 * WordPress takes the first rule that matches the path: the address is the page's only when that rule is one of
	 * its generic content rules.
	 *
	 * @param string $slug  Address, one segment.
	 * @param array  $rules Rewrite rules, regular expression to query, in WordPress's order.
	 * @return string|null The rule's regular expression, null when the address reaches a page.
	 */
	public static function rewrite_conflict( $slug, $rules ) {
		foreach ( (array) $rules as $match => $query ) {
			// Same test as WP::parse_request().
			if ( 1 !== @preg_match( '#^' . $match . '#', $slug ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- A broken rule of another plugin must not stop the check.
				continue;
			}
			return 1 === preg_match( '/^index\.php\?(pagename|name|attachment)=\$matches\[1\]/', (string) $query ) ? null : (string) $match;
		}
		return null;
	}

	/**
	 * Whether an address is served by the Livia service.
	 *
	 * @param string   $url   Address.
	 * @param string[] $hosts Hosts of the service.
	 * @param bool     $http  Whether plain http is accepted, as for a service run locally over http.
	 * @return bool
	 */
	public static function is_service_url( $url, $hosts, $http = false ) {
		$host = self::host_of( $url, $http );
		return '' !== $host && in_array( $host, array_map( 'strtolower', $hosts ), true );
	}

	/**
	 * The host of an address over https, or over http when accepted; an address carrying credentials has none.
	 *
	 * @param string $url  Address.
	 * @param bool   $http Whether plain http is accepted.
	 * @return string Empty when it has none.
	 */
	public static function host_of( $url, $http = false ) {
		$parts  = wp_parse_url( (string) $url );
		$scheme = isset( $parts['scheme'] ) ? strtolower( $parts['scheme'] ) : '';
		if ( ! is_array( $parts ) || empty( $parts['host'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) || ! ( 'https' === $scheme || ( $http && 'http' === $scheme ) ) ) {
			return '';
		}
		return strtolower( $parts['host'] );
	}

	/**
	 * Whether the trash keeps a content WordPress is asked to trash, rather than deleting it for good or hiding it.
	 *
	 * @param string $post_type        Post type.
	 * @param int    $empty_trash_days EMPTY_TRASH_DAYS: 0 deletes at once.
	 * @param bool   $media_trash      MEDIA_TRASH: without it, a trashed media file shows nowhere.
	 * @return bool
	 */
	public static function trash_keeps( $post_type, $empty_trash_days, $media_trash ) {
		return $empty_trash_days > 0 && ( 'attachment' !== $post_type || $media_trash );
	}

	/**
	 * The two thresholds of a restaurant's booking experience, when they make a group tier: a group of 2 at least,
	 * an event tier starting at 999 at most, the widget's largest number of guests.
	 *
	 * @param mixed $from  "Group from", as posted.
	 * @param mixed $above "Event above", as posted.
	 * @return int[]|null The two thresholds, null when they are refused.
	 */
	public static function thresholds( $from, $above ) {
		$from  = filter_var( $from, FILTER_VALIDATE_INT );
		$above = filter_var( $above, FILTER_VALIDATE_INT );
		if ( false === $from || false === $above || $from < 2 || $above > 998 || $from > $above ) {
			return null;
		}
		return array( $from, $above );
	}

	/**
	 * Whether a booking address is a web address the visitor's browser may open: http or https, a host, no credentials.
	 *
	 * @param string $url Address, `{n}` standing for the number of guests.
	 * @return bool
	 */
	public static function is_booking_url( $url ) {
		return '' !== self::host_of( str_replace( '{n}', '1', (string) $url ), true );
	}

	/**
	 * A value of the theme's global styles fit for a CSS custom property, its preset references made CSS ones.
	 *
	 * @param mixed $value Value, as `wp_get_global_styles()` returns it.
	 * @return string Empty when it is not a plain value.
	 */
	public static function css_value( $value ) {
		if ( ! is_string( $value ) ) {
			return '';
		}
		// A reference saved by the site editor: `var:preset|color|base`.
		$value = preg_replace_callback(
			'/\Avar:preset\|([a-z0-9-]+)\|([a-z0-9-]+)\z/',
			static function ( $parts ) {
				return 'var(--wp--preset--' . $parts[1] . '--' . $parts[2] . ')';
			},
			trim( $value )
		);
		return 1 === preg_match( '/\A[\w\s#%.,()\'"-]{1,200}\z/', $value ) ? $value : '';
	}

	/**
	 * HTML received from Livia with its square brackets encoded, so that WordPress runs no shortcode from its text.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function without_shortcodes( $html ) {
		return str_replace( array( '[', ']' ), array( '&#91;', '&#93;' ), (string) $html );
	}

	/**
	 * The update WordPress offers, read from the service's answer.
	 *
	 * @param array    $remote      Answer of `/wordpress/v1/update`.
	 * @param string   $plugin_file Plugin basename.
	 * @param string[] $hosts       Hosts of the service: a package elsewhere is refused.
	 * @param bool     $http        Whether plain http is accepted, see is_service_url().
	 * @param string[] $wp_versions The site's WordPress version and those of the core updates WordPress offers.
	 * @return array|null The update in the shape of the `update_plugins_{$hostname}` filter, with the package's
	 *                    `signature`; null when unusable, an offer without a signature included.
	 */
	public static function update_offer( $remote, $plugin_file, $hosts, $http = false, $wp_versions = array() ) {
		$version   = isset( $remote['version'] ) ? (string) $remote['version'] : '';
		$package   = isset( $remote['package'] ) ? (string) $remote['package'] : '';
		$signature = isset( $remote['signature'] ) && is_string( $remote['signature'] ) ? trim( $remote['signature'] ) : '';
		if ( 1 !== preg_match( '/\A\d+\.\d+\.\d+\z/', $version ) || ! self::is_service_url( $package, $hosts, $http ) || null === self::decode( $signature, 64 ) ) {
			return null;
		}
		// WordPress compares `tested` with its full versions, the site's and the pending core update's: like
		// WordPress.org, a "7.1" covers every 7.1.x, so it becomes the highest of them within that minor.
		$declared = isset( $remote['tested'] ) ? (string) $remote['tested'] : '';
		$tested   = $declared;
		foreach ( (array) $wp_versions as $wp_version ) {
			$wp_version = preg_replace( '/-.*\z/', '', (string) $wp_version );
			if ( 1 === preg_match( '/\A\d+\.\d+\z/', $declared ) && 0 === strpos( $wp_version . '.', $declared . '.' ) && version_compare( $wp_version, $tested, '>' ) ) {
				$tested = $wp_version;
			}
		}
		$icons = array();
		foreach ( array( '1x', '2x', 'svg' ) as $size ) {
			if ( isset( $remote['icons'][ $size ] ) && self::is_service_url( $remote['icons'][ $size ], $hosts, $http ) ) {
				$icons[ $size ] = (string) $remote['icons'][ $size ];
			}
		}
		return array(
			'slug'         => 'livia',
			'plugin'       => $plugin_file,
			'version'      => $version,
			'new_version'  => $version,
			'package'      => $package,
			'url'          => 'https://www.wearelivia.com',
			'requires'     => isset( $remote['requires'] ) ? (string) $remote['requires'] : '',
			'tested'       => $tested,
			'requires_php' => isset( $remote['requires_php'] ) ? (string) $remote['requires_php'] : '',
			'last_updated' => isset( $remote['last_updated'] ) ? (string) $remote['last_updated'] : '',
			'changelog'    => isset( $remote['sections']['changelog'] ) ? (string) $remote['sections']['changelog'] : '',
			'icons'        => $icons,
			'signature'    => $signature,
		);
	}

	/**
	 * Whether a package carries the Ed25519 signature of the key, both in base64.
	 *
	 * @param string $package   The package's bytes.
	 * @param string $signature Detached signature, base64.
	 * @param string $key       Public key, base64; empty refuses every package.
	 * @return bool
	 */
	public static function is_signed( $package, $signature, $key ) {
		// Ed25519: a 64-byte signature, a 32-byte public key.
		$signature = self::decode( trim( (string) $signature ), 64 );
		$key       = self::decode( (string) $key, 32 );
		if ( null === $signature || null === $key || ! function_exists( 'sodium_crypto_sign_verify_detached' ) ) {
			return false;
		}
		try {
			return sodium_crypto_sign_verify_detached( $signature, (string) $package, $key );
		} catch ( SodiumException $e ) {
			return false;
		}
	}

	/**
	 * The `Version` header of a plugin's main file, read as WordPress's get_file_data() reads it.
	 *
	 * @param string $php The file's content.
	 * @return string Empty when it has none.
	 */
	public static function plugin_version( $php ) {
		// A lone \r ends a line too, as get_file_data() reads it.
		if ( ! preg_match( '/^(?:[ \t]*<\?(?:php)?)?[ \t\/*#@]*Version:(.*)$/mi', str_replace( "\r", "\n", substr( (string) $php, 0, 8192 ) ), $match ) ) {
			return '';
		}
		return trim( preg_replace( '/\s*(?:\*\/|\?>).*/', '', $match[1] ) );
	}

	/**
	 * Base64 bytes of an exact length.
	 *
	 * @param string $base64 Base64.
	 * @param int    $length Number of bytes expected.
	 * @return string|null The bytes, null when the text is not that.
	 */
	private static function decode( $base64, $length ) {
		$bytes = base64_decode( $base64, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- The signature and key travel in base64.
		return false !== $bytes && strlen( $bytes ) === $length ? $bytes : null;
	}

	/**
	 * "de" followed by a name, as French contracts it: "du Grand Palais", "de la Terrasse", "des Halles", "de l'Atelier".
	 * Any other locale gets the name unchanged, for the English templates that put it after "'s" or "of".
	 *
	 * @param string $name   Name.
	 * @param string $locale Locale of the text it goes into.
	 * @return string
	 */
	public static function of_name( $name, $locale ) {
		if ( 'fr' !== substr( $locale, 0, 2 ) ) {
			return $name;
		}
		if ( preg_match( '/^(le|la|les)\s+(.+)$/isu', $name, $m ) ) {
			$article = array(
				'le'  => 'du ',
				'la'  => 'de la ',
				'les' => 'des ',
			);
			return $article[ strtolower( $m[1] ) ] . $m[2];
		}
		if ( preg_match( '/^l[\'\x{2019}](.+)$/isu', $name, $m ) ) {
			return 'de l\'' . $m[1];
		}
		// "h" is treated as mute: an aspirated h ("Hamburger") is rare in restaurant names.
		if ( preg_match( '/^[aeiouyhàâäéèêëîïôöùûüœæ]/iu', $name ) ) {
			return 'd\'' . $name;
		}
		return 'de ' . $name;
	}
}
