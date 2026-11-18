<?php
/**
 * Settings of the plugin and calls to the Livia service.
 *
 * @package Livia
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings, restaurant connection and group page received from Livia.
 */
final class Livia_Service {

	const SETTINGS    = 'livia_settings';
	const RESTAURANTS = 'livia_restaurants';
	const CONNECTIONS = 'livia_connections';

	/**
	 * Prefix of the options holding the last group page received for a key, one per key.
	 */
	const PAGE_LAST = 'livia_last_';

	/**
	 * The settings of a connected restaurant, every value present.
	 */
	const RESTAURANT = array(
		'key'          => '',
		'bubble_on'    => true,
		'site_widget'  => '',
		'phone_widget' => '',
		'site_rule'    => 'all',
		'site_pages'   => array(),
		'slug'         => '',
		'title'        => '',
		'headline'     => '',
		'page_widget'  => '',
		'page_layout'  => 'theme',
		'page_id'      => 0,
		'replaced_id'  => 0,
	) + self::EXPERIENCE;

	/**
	 * A restaurant's booking experience: its thresholds, then its three paths (below the group threshold, the group
	 * tier, above the event threshold), each with a photo of the media library (attachment id) and its caption, then
	 * its card in the restaurant choice of a site with several restaurants.
	 */
	const EXPERIENCE = array(
		'xp_from'          => 15,
		'xp_above'         => 40,
		'xp_tool'          => 'zenchef',
		'xp_url'           => '',
		'xp_open'          => 'window',
		'xp_table_photo'   => 0,
		'xp_table_caption' => '',
		'xp_group_dest'    => 'window',
		'xp_group_widget'  => '',
		'xp_group_title'   => '',
		'xp_group_photo'   => 0,
		'xp_group_caption' => '',
		'xp_event_dest'    => 'contact',
		'xp_email'         => '',
		'xp_phone'         => '',
		'xp_address'       => '',
		'xp_event_url'     => '',
		'xp_event_photo'   => 0,
		'xp_event_caption' => '',
		'xp_photo'         => 0,
		'xp_phrase'        => '',
	);

	/**
	 * Seconds a failed call to Livia is not retried for, the last page received being served meanwhile.
	 */
	const RETRY_AFTER = 60;

	/**
	 * Longest time, in seconds, a page received is served before asking Livia again, whatever Livia answers.
	 */
	const CACHE_MAX = 3600;

	/**
	 * The group pages received during this request, by cache key.
	 *
	 * @var array
	 */
	private static $pages = array();

	/**
	 * The settings of the whole site.
	 *
	 * @return array{site_on: bool, next_id: int, xp_on: bool, xp_bar: bool, xp_order: string[], page_styles: string, purge_due: bool}
	 *         Whether the site widgets section is on, the number the next restaurant connected takes in its id, never
	 *         reused, whether the booking experience is on (off until the webmaster sets it), whether it shows its bar
	 *         on phones, the order of the restaurant choice, kept apart from the restaurants' order, which decides the
	 *         bubbles, the styles of the group pages, `inherit` (the site's apply) or `isolated`, and whether the
	 *         cache tools' pages predate a change, which the screen asks to clear them for: from an update to 1.1 on,
	 *         until the screen's button clears them.
	 */
	public static function settings() {
		$saved = get_option( self::SETTINGS, array() );
		return array_merge(
			array(
				'site_on'     => true,
				'next_id'     => 1,
				'xp_on'       => false,
				'xp_bar'      => true,
				'xp_order'    => array(),
				'page_styles' => 'inherit',
				'purge_due'   => true,
			),
			is_array( $saved ) ? $saved : array()
		);
	}

	/**
	 * Saves some settings of the whole site, the others kept.
	 *
	 * @param array $changes Settings to change.
	 */
	public static function save_settings( $changes ) {
		update_option( self::SETTINGS, array_merge( self::settings(), $changes ) );
	}

	/**
	 * The connected restaurants, in the screen's order, by id.
	 *
	 * @return array[] See RESTAURANT.
	 */
	public static function restaurants() {
		$saved = get_option( self::RESTAURANTS, array() );
		$list  = array();
		foreach ( is_array( $saved ) ? $saved : array() as $id => $restaurant ) {
			if ( is_array( $restaurant ) && ! empty( $restaurant['key'] ) ) {
				$list[ (string) $id ] = array_merge( self::RESTAURANT, $restaurant );
			}
		}
		return $list;
	}

	/**
	 * A connected restaurant.
	 *
	 * @param string $id Restaurant id.
	 * @return array|null See RESTAURANT.
	 */
	public static function restaurant( $id ) {
		$all = self::restaurants();
		return isset( $all[ $id ] ) ? $all[ $id ] : null;
	}

	/**
	 * Saves some settings of a connected restaurant, the others kept.
	 *
	 * @param string $id      Restaurant id.
	 * @param array  $changes Settings to change.
	 */
	public static function save_restaurant( $id, $changes ) {
		$all = self::restaurants();
		if ( isset( $all[ $id ] ) ) {
			$all[ $id ] = array_merge( $all[ $id ], $changes );
			update_option( self::RESTAURANTS, $all );
		}
	}

	/**
	 * Connects a restaurant, after the others.
	 *
	 * @param string $key        Key Livia accepted.
	 * @param array  $connection The restaurant as Livia answered, see connection().
	 * @return string The restaurant's id.
	 */
	public static function add_restaurant( $key, $connection ) {
		$next = (int) self::settings()['next_id'];
		$id   = 'r' . $next;
		self::save_settings( array( 'next_id' => $next + 1 ) );
		$all        = self::restaurants();
		$all[ $id ] = array_merge( self::RESTAURANT, array( 'key' => $key ) );
		update_option( self::RESTAURANTS, $all );
		self::store_connection( $id, $connection );
		return $id;
	}

	/**
	 * Disconnects a restaurant: its page goes back to draft, as a restaurant that leaves must not keep a page taking
	 * requests, and nothing it received stays.
	 *
	 * @param string $id Restaurant id.
	 */
	public static function remove_restaurant( $id ) {
		$restaurant = self::restaurant( $id );
		if ( ! $restaurant ) {
			return;
		}
		self::forget_page( $id );
		delete_option( self::PAGE_LAST . md5( $restaurant['key'] ) );
		Livia_Group_Page::unpublish( $id );
		$all = self::restaurants();
		unset( $all[ $id ] );
		update_option( self::RESTAURANTS, $all );
		$connections = get_option( self::CONNECTIONS, array() );
		if ( is_array( $connections ) ) {
			unset( $connections[ $id ] );
			update_option( self::CONNECTIONS, $connections );
		}
	}

	/**
	 * Connects a restaurant's place to the restaurant of another key: its page and its bubble stay, nothing of the
	 * previous restaurant does, neither its widgets, nor its page, which the group page would otherwise show while
	 * Livia does not answer.
	 *
	 * @param string $id         Restaurant id.
	 * @param string $key        Key Livia accepted.
	 * @param array  $connection The restaurant as Livia answered, see connection().
	 */
	public static function use_key( $id, $key, $connection ) {
		$restaurant = self::restaurant( $id );
		if ( ! $restaurant ) {
			return;
		}
		if ( $restaurant['key'] !== $key ) {
			$previous = (string) ( self::connection( $id )['restaurant']['id'] ?? '' );
			self::forget_page( $id );
			delete_option( self::PAGE_LAST . md5( $restaurant['key'] ) );
			$changes = array( 'key' => $key );
			if ( '' === $previous || (string) ( $connection['restaurant']['id'] ?? '' ) !== $previous ) {
				$changes += array(
					'page_widget'     => '',
					'site_widget'     => '',
					'phone_widget'    => '',
					'xp_group_widget' => '',
				);
			}
			self::save_restaurant( $id, $changes );
		}
		self::store_connection( $id, $connection );
	}

	/**
	 * The key the plugin's updates are asked with: the first one Livia accepted at the last connection.
	 *
	 * @return string Empty when none.
	 */
	public static function update_key() {
		foreach ( self::restaurants() as $id => $restaurant ) {
			if ( 'ok' === self::connection( $id )['state'] ) {
				return $restaurant['key'];
			}
		}
		return '';
	}

	/**
	 * Forgets the update WordPress keeps, whose download address carries a key.
	 */
	public static function forget_update() {
		delete_transient( Livia_Updater::TRANSIENT );
		delete_site_transient( 'update_plugins' );
	}

	/**
	 * The hosts of the Livia service, as the server and as the browser reach it.
	 *
	 * @return string[]
	 */
	public static function hosts() {
		$http = self::allows_http();
		return array_values( array_unique( array_filter( array( Livia_Rules::host_of( LIVIA_SERVICE_URL, $http ), Livia_Rules::host_of( LIVIA_PUBLIC_URL, $http ) ) ) ) );
	}

	/**
	 * Whether addresses of the service may be plain http: only when the service itself is, as when run locally.
	 *
	 * @return bool
	 */
	public static function allows_http() {
		return 'http' === strtolower( (string) wp_parse_url( LIVIA_SERVICE_URL, PHP_URL_SCHEME ) );
	}

	/**
	 * An address of the service as the visitor's browser reaches it.
	 *
	 * @param string $path  Path, from the root of the service.
	 * @param array  $query Query arguments.
	 * @return string
	 */
	public static function public_url( $path, $query = array() ) {
		$url = untrailingslashit( LIVIA_PUBLIC_URL ) . $path;
		return $query ? $url . '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 ) : $url;
	}

	/**
	 * Calls the service, server to server.
	 *
	 * @param string $path    Path under the service root.
	 * @param array  $query   Query arguments.
	 * @param int    $timeout Seconds.
	 * @return array{status: string, data: array|null} See Livia_Rules::read_response().
	 */
	public static function call( $path, $query, $timeout = 3 ) {
		$url      = untrailingslashit( LIVIA_SERVICE_URL ) . $path . '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => $timeout,
				'redirection' => 0,
				'headers'     => array( 'Accept' => 'application/json' ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return Livia_Rules::read_response( 0, '' );
		}
		return Livia_Rules::read_response( (int) wp_remote_retrieve_response_code( $response ), wp_remote_retrieve_body( $response ) );
	}

	/**
	 * A restaurant and its widgets as last received, with the state of its key then.
	 *
	 * @param string $id Restaurant id.
	 * @return array{state: string, restaurant: array, pageWidgetId: string|null, widgets: array}
	 */
	public static function connection( $id ) {
		$saved = get_option( self::CONNECTIONS, array() );
		return array_merge(
			array(
				'state'        => '',
				'restaurant'   => array(),
				'pageWidgetId' => null,
				'widgets'      => array(),
			),
			is_array( $saved ) && isset( $saved[ $id ] ) && is_array( $saved[ $id ] ) ? $saved[ $id ] : array()
		);
	}

	/**
	 * The name of a connected restaurant, as last received.
	 *
	 * @param string $id Restaurant id.
	 * @return string
	 */
	public static function name( $id ) {
		$restaurant = self::connection( $id )['restaurant'];
		return isset( $restaurant['name'] ) ? (string) $restaurant['name'] : '';
	}

	/**
	 * Keeps a restaurant as received; kept apart from the settings, which the screen saves meanwhile.
	 *
	 * @param string $id         Restaurant id.
	 * @param array  $connection See connection().
	 */
	private static function store_connection( $id, $connection ) {
		$saved        = get_option( self::CONNECTIONS, array() );
		$saved        = is_array( $saved ) ? $saved : array();
		$saved[ $id ] = $connection;
		update_option( self::CONNECTIONS, $saved );
	}

	/**
	 * Asks Livia for the restaurant of a key.
	 *
	 * @param string $key Restaurant key.
	 * @return array{status: string, connection: array|null} The answer's status, see Livia_Rules::read_response(),
	 *                                                        and the restaurant on `ok`, see connection().
	 */
	public static function ask_connection( $key ) {
		$answer = self::call( '/wordpress/v1/connection', array( 'key' => $key ), 5 );
		$data   = $answer['data'];
		return array(
			'status'     => $answer['status'],
			'connection' => 'ok' === $answer['status'] ? array(
				'state'        => 'ok',
				'restaurant'   => isset( $data['restaurant'] ) && is_array( $data['restaurant'] ) ? $data['restaurant'] : array(),
				'pageWidgetId' => isset( $data['pageWidgetId'] ) ? (string) $data['pageWidgetId'] : null,
				'widgets'      => isset( $data['widgets'] ) && is_array( $data['widgets'] ) ? $data['widgets'] : array(),
			) : null,
		);
	}

	/**
	 * Asks Livia again for a connected restaurant and keeps what it answers.
	 *
	 * A refusal keeps the restaurant last received, with the refusal as its state, and takes its page off the site;
	 * a Livia that does not answer changes nothing. The update WordPress keeps is forgotten when the key it was asked
	 * with changes.
	 *
	 * @param string $id Restaurant id.
	 * @return string The answer's status, see Livia_Rules::read_response().
	 */
	public static function refresh_connection( $id ) {
		$restaurant = self::restaurant( $id );
		if ( ! $restaurant ) {
			return 'unavailable';
		}
		$update_key = self::update_key();
		$answer     = self::ask_connection( $restaurant['key'] );
		if ( 'ok' === $answer['status'] ) {
			self::store_connection( $id, $answer['connection'] );
		} elseif ( 'unavailable' !== $answer['status'] ) {
			self::store_connection( $id, array_merge( self::connection( $id ), array( 'state' => $answer['status'] ) ) );
			self::forget_page( $id );
			delete_option( self::PAGE_LAST . md5( $restaurant['key'] ) );
		}
		if ( self::update_key() !== $update_key ) {
			self::forget_update();
		}
		return $answer['status'];
	}

	/**
	 * Refreshes the connected restaurants when an editor opens, so a widget created in Livia since the last refresh is
	 * offered there; at most once per restaurant every five minutes, whatever the answer. Admin side only: rendering a
	 * page never refreshes the connection (a group page asks Livia for its content, not for the connection).
	 */
	public static function refresh_for_editor() {
		foreach ( array_keys( self::restaurants() ) as $id ) {
			$transient = 'livia_checked_' . $id;
			if ( false === get_transient( $transient ) ) {
				set_transient( $transient, 1, 5 * MINUTE_IN_SECONDS );
				self::refresh_connection( $id );
			}
		}
	}

	/**
	 * A restaurant's widgets the plugin may place, as last received.
	 *
	 * @param string   $id      Restaurant id.
	 * @param string[] $formats Formats kept; all when empty.
	 * @return array[]
	 */
	public static function widgets( $id, $formats = array() ) {
		return Livia_Rules::usable_widgets( self::connection( $id )['widgets'], $formats );
	}

	/**
	 * A widget of a connected restaurant, as last received.
	 *
	 * @param string $widget_id Widget id.
	 * @return array|null
	 */
	public static function widget( $widget_id ) {
		foreach ( array_keys( self::restaurants() ) as $id ) {
			foreach ( self::connection( $id )['widgets'] as $widget ) {
				if ( is_array( $widget ) && isset( $widget['id'] ) && (string) $widget['id'] === (string) $widget_id ) {
					return $widget;
				}
			}
		}
		return null;
	}

	/**
	 * The widget of a restaurant's group page: the one chosen, else the one of the restaurant's Livia booking page.
	 *
	 * @param string $id Restaurant id.
	 * @return string
	 */
	public static function page_widget( $id ) {
		$chosen = self::restaurant( $id )['page_widget'];
		return '' !== $chosen ? $chosen : (string) self::connection( $id )['pageWidgetId'];
	}

	/**
	 * What Livia renders a group page for: the key, the widget and the headline when one is set, then the layout, the
	 * styles and the site's home only when they differ from the service's defaults (`theme`, `inherit`), so that a
	 * site that changes neither keeps its cache key, see page_transient().
	 *
	 * @param string $id Restaurant id.
	 * @return array
	 */
	public static function page_query( $id ) {
		$restaurant = self::restaurant( $id );
		$landing    = 'landing' === $restaurant['page_layout'];
		return array_filter(
			array(
				'key'      => $restaurant['key'],
				'widget'   => self::page_widget( $id ),
				'headline' => $restaurant['headline'],
				'layout'   => $landing ? 'landing' : '',
				'styles'   => 'isolated' === self::settings()['page_styles'] ? 'isolated' : '',
				'home'     => $landing ? home_url( '/' ) : '',
			),
			'strlen'
		);
	}

	/**
	 * The transient holding a group page rendered for the current settings.
	 *
	 * @param string $id Restaurant id.
	 * @return string
	 */
	private static function page_transient( $id ) {
		return 'livia_page_' . md5( implode( '|', self::page_query( $id ) ) );
	}

	/**
	 * A restaurant's group page rendered by Livia: from the cache, else from Livia, else the last version received
	 * while Livia does not answer; none once Livia refuses the key or its connector is turned off.
	 *
	 * @param string $id Restaurant id.
	 * @return array|null `html`, `styles`, `scripts` and `version`; null when no version was ever received, or the
	 *                    restaurant is not connected.
	 */
	public static function group_page( $id ) {
		$restaurant = self::restaurant( $id );
		if ( ! $restaurant ) {
			return null;
		}
		$transient = self::page_transient( $id );
		if ( array_key_exists( $transient, self::$pages ) ) {
			return self::$pages[ $transient ];
		}
		$cached = get_transient( $transient );
		if ( is_array( $cached ) && array_key_exists( 'page', $cached ) ) {
			self::$pages[ $transient ] = $cached['page'];
			return $cached['page'];
		}
		$answer = self::call( '/wordpress/v1/page', self::page_query( $id ) );
		$page   = 'ok' === $answer['status'] ? self::read_page( $answer['data'] ) : null;
		if ( $page ) {
			update_option( self::PAGE_LAST . md5( $restaurant['key'] ), $page, false );
			$ttl = isset( $answer['data']['cacheSeconds'] ) ? (int) $answer['data']['cacheSeconds'] : 0;
			set_transient( $transient, array( 'page' => $page ), max( self::RETRY_AFTER, min( self::CACHE_MAX, $ttl ) ) );
		} else {
			if ( in_array( $answer['status'], array( 'unknown_key', 'connector_disabled' ), true ) ) {
				delete_option( self::PAGE_LAST . md5( $restaurant['key'] ) );
			}
			$page = self::last_page( $id );
			set_transient( $transient, array( 'page' => $page ), self::RETRY_AFTER );
		}
		self::$pages[ $transient ] = $page;
		return $page;
	}

	/**
	 * Forgets a restaurant's cached group page, so that the next visit asks Livia.
	 *
	 * @param string $id Restaurant id.
	 */
	public static function forget_page( $id ) {
		self::$pages = array();
		if ( self::restaurant( $id ) ) {
			delete_transient( self::page_transient( $id ) );
		}
	}

	/**
	 * The last group page received for a restaurant's key: a visit that asked with a previous key does not count.
	 *
	 * @param string $id Restaurant id.
	 * @return array|null
	 */
	public static function last_page( $id ) {
		$restaurant = self::restaurant( $id );
		$last       = $restaurant ? get_option( self::PAGE_LAST . md5( $restaurant['key'] ) ) : null;
		return is_array( $last ) ? $last : null;
	}

	/**
	 * Keeps what the plugin uses of a page sent by Livia; scripts and styles must come from Livia.
	 *
	 * @param array $data Answer of `/wordpress/v1/page`.
	 * @return array|null
	 */
	private static function read_page( $data ) {
		if ( ! isset( $data['html'] ) || ! is_string( $data['html'] ) ) {
			return null;
		}
		$hosts = self::hosts();
		$http  = self::allows_http();
		$keep  = static function ( $urls ) use ( $hosts, $http ) {
			return array_values(
				array_filter(
					is_array( $urls ) ? $urls : array(),
					static function ( $url ) use ( $hosts, $http ) {
						return is_string( $url ) && Livia_Rules::is_service_url( $url, $hosts, $http );
					}
				)
			);
		};
		return array(
			'html'    => $data['html'],
			'styles'  => $keep( $data['styles'] ?? array() ),
			'scripts' => $keep( $data['scripts'] ?? array() ),
			'version' => isset( $data['version'] ) ? (string) $data['version'] : '',
		);
	}
}
