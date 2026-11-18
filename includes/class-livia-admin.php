<?php
/**
 * The Livia settings screen.
 *
 * @package Livia
 */

defined( 'ABSPATH' ) || exit;

/**
 * The "Livia" menu entry and its settings screen: the connected restaurants, then one tab per section.
 */
final class Livia_Admin {

	const SLUG       = 'livia';
	const HOOK       = 'toplevel_page_livia';
	const CAPABILITY = 'manage_options';

	/**
	 * Longest headline of the group page, in characters.
	 */
	const HEADLINE_MAX = 120;

	/**
	 * Livia's admin, where a restaurant finds its key and its widgets.
	 */
	const LIVIA_ADMIN_URL = 'https://admin.wearelivia.com';

	/**
	 * The screen's tabs, the first one shown by default.
	 */
	const TABS = array( 'widget', 'page', 'experience', 'advanced' );

	/**
	 * Hooks the screen.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_head', array( __CLASS__, 'menu_icon_style' ) );
		add_action( 'admin_post_livia_connect', array( __CLASS__, 'handle_connect' ) );
		add_action( 'admin_post_livia_remove', array( __CLASS__, 'handle_remove' ) );
		add_action( 'admin_post_livia_save_widget', array( __CLASS__, 'handle_save_widget' ) );
		add_action( 'admin_post_livia_save_page', array( __CLASS__, 'handle_save_page' ) );
		add_action( 'admin_post_livia_trash', array( __CLASS__, 'handle_trash' ) );
		add_action( 'admin_post_livia_save_experience', array( __CLASS__, 'handle_save_experience' ) );
		add_action( 'admin_post_livia_add_menu', array( __CLASS__, 'handle_add_menu' ) );
		add_action( 'admin_post_livia_save_advanced', array( __CLASS__, 'handle_save_advanced' ) );
		add_action( 'admin_post_livia_purge_cache', array( __CLASS__, 'handle_purge_cache' ) );
		add_filter( 'plugin_action_links_' . LIVIA_BASENAME, array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Adds the top-level "Livia" entry.
	 */
	public static function menu() {
		$hook = add_menu_page( 'Livia', 'Livia', self::CAPABILITY, self::SLUG, array( __CLASS__, 'render' ), LIVIA_URL . 'assets/img/menu-icon.svg', 81 );
		add_action( 'load-' . $hook, array( __CLASS__, 'on_load' ) );
	}

	/**
	 * Shows the menu picto in full colour, as WordPress dims image icons.
	 */
	public static function menu_icon_style() {
		echo '<style>#adminmenu .toplevel_page_livia .wp-menu-image img{opacity:1;padding-top:7px;width:20px;height:20px}</style>';
	}

	/**
	 * Adds "Settings" to the plugin's row.
	 *
	 * @param string[] $links Row links.
	 * @return string[]
	 */
	public static function action_links( $links ) {
		array_unshift( $links, sprintf( '<a href="%s">%s</a>', esc_url( self::url() ), esc_html__( 'Settings', 'livia' ) ) );
		return $links;
	}

	/**
	 * Puts the copyright and the version in the footer of the screen.
	 */
	public static function on_load() {
		add_filter(
			'admin_footer_text',
			static function () {
				return sprintf(
					/* translators: 1: year, 2: "Livia". */
					esc_html__( '© %1$s %2$s. All rights reserved.', 'livia' ),
					esc_html( gmdate( 'Y' ) ),
					'<b>Livia</b>'
				) . sprintf( ' <a class="livia-help" href="%s">%s</a>', esc_url( 'mailto:support@wearelivia.com' ), esc_html__( 'Help', 'livia' ) );
			}
		);
		add_filter(
			'update_footer',
			static function () {
				/* translators: %s: plugin version. */
				return esc_html( sprintf( __( 'Version %s', 'livia' ), LIVIA_VERSION ) );
			},
			11
		);
	}

	/**
	 * Loads the screen's styles and script, on this screen only.
	 *
	 * @param string $hook Screen hook.
	 */
	public static function assets( $hook ) {
		if ( self::HOOK !== $hook ) {
			return;
		}
		wp_enqueue_style( 'livia-admin', LIVIA_URL . 'assets/admin.css', array(), LIVIA_VERSION );
		wp_enqueue_script( 'livia-admin', LIVIA_URL . 'assets/admin.js', array(), LIVIA_VERSION, true );
		// The photos of the booking experience are chosen in the media library.
		if ( 'experience' === self::tab_of( $_GET ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only picks what the screen loads.
			wp_enqueue_media();
		}
	}

	/**
	 * The screen's address.
	 *
	 * @param array $args Query arguments.
	 * @return string
	 */
	public static function url( $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::SLUG ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * A tab of the screen, read from a request.
	 *
	 * @param array $request `$_GET` or `$_POST`.
	 * @return string
	 */
	private static function tab_of( $request ) {
		$tab = isset( $request['tab'] ) ? sanitize_key( wp_unslash( $request['tab'] ) ) : '';
		return in_array( $tab, self::TABS, true ) ? $tab : self::TABS[0];
	}

	/**
	 * What the last action left for the next display of the screen.
	 *
	 * @return array
	 */
	private static function take_notice() {
		$key    = 'livia_notice_' . get_current_user_id();
		$notice = get_transient( $key );
		delete_transient( $key );
		return is_array( $notice ) ? $notice : array();
	}

	/**
	 * Leaves a notice for the screen and goes back to the tab the action came from.
	 *
	 * @param array $notice Notice.
	 */
	private static function back( $notice ) {
		set_transient( 'livia_notice_' . get_current_user_id(), $notice, 5 * MINUTE_IN_SECONDS );
		wp_safe_redirect( self::url( array( 'tab' => self::tab_of( $_REQUEST ) ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified by guard(), and only picks a tab.
		exit;
	}

	/**
	 * Refuses an action the user may not do.
	 *
	 * @param string $action Nonce action.
	 */
	private static function guard( $action ) {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'livia' ), 403 );
		}
		check_admin_referer( $action );
	}

	/**
	 * The restaurant an action is about, as posted.
	 *
	 * @return string Restaurant id, empty for none.
	 */
	private static function posted_restaurant() {
		return isset( $_REQUEST['livia_restaurant'] ) ? sanitize_key( wp_unslash( $_REQUEST['livia_restaurant'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified by guard().
	}

	/**
	 * The fields a tab posted for each restaurant it shows; a restaurant the tab did not show posts nothing.
	 *
	 * @return array[] By restaurant id.
	 */
	private static function posted_restaurants() {
		$posted = isset( $_POST['livia'] ) && is_array( $_POST['livia'] ) ? wp_unslash( $_POST['livia'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Verified by guard(); each field is sanitized where it is read.
		return array_filter(
			array_intersect_key( $posted, Livia_Service::restaurants() ),
			static function ( $fields ) {
				return is_array( $fields ) && isset( $fields['shown'] );
			}
		);
	}

	/**
	 * The message a refused key shows.
	 *
	 * @param string $status Answer status.
	 * @return string
	 */
	public static function key_message( $status ) {
		$messages = array(
			'unknown_key'        => __( 'Livia does not recognise this key. Check that you copied all of it from your Livia space.', 'livia' ),
			'connector_disabled' => __( 'The WordPress connector of this restaurant is turned off in Livia: the plugin no longer receives anything. Turn it on again in your Livia space, Connectors section.', 'livia' ),
			'already_connected'  => __( 'This restaurant is already connected.', 'livia' ),
			'unavailable'        => __( 'Livia is not answering right now. Please try again in a few minutes.', 'livia' ),
		);
		return isset( $messages[ $status ] ) ? $messages[ $status ] : $messages['unavailable'];
	}

	/**
	 * Connects a restaurant, or another key in the place of a connected one.
	 */
	public static function handle_connect() {
		self::guard( 'livia_connect' );
		$key = isset( $_POST['livia_key'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['livia_key'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by guard().
		$id  = self::posted_restaurant();
		if ( '' !== $id && ! Livia_Service::restaurant( $id ) ) {
			self::back( array() );
		}
		$others = Livia_Service::restaurants();
		unset( $others[ $id ] );

		$answer = array( 'status' => Livia_Rules::is_key_format( $key ) ? '' : 'unknown_key' );
		if ( in_array( $key, wp_list_pluck( $others, 'key' ), true ) ) {
			$answer['status'] = 'already_connected';
		} elseif ( '' === $answer['status'] ) {
			$answer = Livia_Service::ask_connection( $key );
		}
		$restaurant = 'ok' === $answer['status'] ? (string) ( $answer['connection']['restaurant']['id'] ?? '' ) : '';
		foreach ( array_keys( $others ) as $other ) {
			if ( '' !== $restaurant && (string) ( Livia_Service::connection( $other )['restaurant']['id'] ?? '' ) === $restaurant ) {
				$answer['status'] = 'already_connected';
			}
		}
		if ( 'ok' !== $answer['status'] ) {
			self::back(
				array(
					'key_error' => self::key_message( $answer['status'] ),
					'key_value' => $key,
					'key_for'   => $id,
				)
			);
		}

		$update_key = Livia_Service::update_key();
		if ( '' === $id ) {
			Livia_Service::add_restaurant( $key, $answer['connection'] );
		} else {
			Livia_Service::use_key( $id, $key, $answer['connection'] );
		}
		if ( Livia_Service::update_key() !== $update_key ) {
			Livia_Service::forget_update();
		}
		self::back( array() );
	}

	/**
	 * Disconnects a restaurant.
	 */
	public static function handle_remove() {
		$id = self::posted_restaurant();
		self::guard( 'livia_remove_' . $id );
		$update_key = Livia_Service::update_key();
		Livia_Service::remove_restaurant( $id );
		if ( Livia_Service::update_key() !== $update_key ) {
			Livia_Service::forget_update();
		}
		self::back( array() );
	}

	/**
	 * Saves the site widgets tab: the section's switch, then each restaurant's bubble and its pages.
	 */
	public static function handle_save_widget() {
		self::guard( 'livia_save_widget' );
		Livia_Service::save_settings( array( 'site_on' => ! empty( $_POST['livia_site_on'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by guard().
		$pages   = self::site_page_ids();
		$bubbles = Livia_Widgets::bubbles();
		$changes = array();
		foreach ( self::posted_restaurants() as $id => $fields ) {
			$widget         = isset( $fields['site_widget'] ) ? sanitize_text_field( $fields['site_widget'] ) : '';
			$phone          = isset( $fields['phone_widget'] ) ? sanitize_text_field( $fields['phone_widget'] ) : '';
			$offered        = Livia_Rules::widget_ids( Livia_Service::widgets( $id, array( 'bubble' ) ) );
			$rule           = isset( $fields['site_rule'] ) ? sanitize_key( $fields['site_rule'] ) : 'all';
			$checked        = isset( $fields['site_pages'] ) ? array_map( 'absint', (array) $fields['site_pages'] ) : array();
			$changes[ $id ] = array(
				'bubble_on'    => ! empty( $fields['bubble_on'] ),
				'site_widget'  => in_array( $widget, $offered, true ) ? $widget : '',
				'phone_widget' => in_array( $phone, $offered, true ) ? $phone : '',
				'site_rule'    => in_array( $rule, array( 'all', 'except', 'only' ), true ) ? $rule : 'all',
				'site_pages'   => array_values( array_intersect( $checked, $pages ) ),
			);
			$bubbles[ $id ] = Livia_Widgets::bubble( $changes[ $id ], $offered );
		}
		// A screen of a page listed under "Only" by two restaurants stays with the first one showing a bubble on that
		// screen: a page never shows two bubbles on a screen, and a restaurant keeps a page while it holds a screen of it.
		foreach ( $changes as $id => $change ) {
			if ( 'only' === $change['site_rule'] ) {
				$taken                = Livia_Rules::taken_from( $bubbles, $id );
				$change['site_pages'] = array_values(
					array_filter(
						$change['site_pages'],
						static function ( $page ) use ( $taken ) {
							return ! isset( $taken[ $page ] );
						}
					)
				);
			}
			Livia_Service::save_restaurant( $id, $change );
		}
		self::back( array( 'saved' => true ) );
	}

	/**
	 * Saves the group pages tab: each restaurant's page is published, or taken off the site, on its own.
	 */
	public static function handle_save_page() {
		self::guard( 'livia_save_page' );
		$notice  = array( 'restaurants' => array() );
		$several = count( Livia_Service::restaurants() ) > 1;
		$layouts = false;
		foreach ( self::posted_restaurants() as $id => $fields ) {
			$slug    = isset( $fields['slug'] ) ? sanitize_title( $fields['slug'] ) : '';
			$title   = isset( $fields['title'] ) ? sanitize_text_field( $fields['title'] ) : '';
			$accro   = isset( $fields['headline'] ) ? sanitize_text_field( $fields['headline'] ) : '';
			$widget  = isset( $fields['page_widget'] ) ? sanitize_text_field( $fields['page_widget'] ) : '';
			$changes = array(
				'title'       => '' !== $title ? $title : self::default_title( $several ? Livia_Service::name( $id ) : '' ),
				'headline'    => mb_substr( $accro, 0, self::HEADLINE_MAX ),
				'page_widget' => in_array( $widget, Livia_Rules::widget_ids( Livia_Service::widgets( $id, array( 'inline' ) ) ), true ) ? $widget : '',
				'page_layout' => isset( $fields['page_layout'] ) && 'landing' === $fields['page_layout'] ? 'landing' : 'theme',
			);
			$layouts = $layouts || Livia_Service::restaurant( $id )['page_layout'] !== $changes['page_layout'];
			// The page cached for the previous settings and for the new ones: both may be served otherwise.
			Livia_Service::forget_page( $id );
			Livia_Service::save_restaurant( $id, $changes );
			Livia_Service::forget_page( $id );

			$off = empty( $fields['page_on'] );
			if ( $off && ! isset( $fields['slug'] ) ) {
				Livia_Group_Page::unpublish( $id );
				continue;
			}
			// A refused address keeps the saved one, whether the page is going online or not.
			if ( Livia_Group_Page::check_address( $slug, $id ) ) {
				$notice['restaurants'][ $id ] = array( 'slug_value' => $slug );
				if ( $off ) {
					Livia_Group_Page::unpublish( $id );
				}
				continue;
			}
			// The address is kept for a page taken off the site: it comes back to it.
			Livia_Service::save_restaurant( $id, array( 'slug' => $slug ) );
			if ( $off ) {
				Livia_Group_Page::unpublish( $id );
				continue;
			}
			$result = Livia_Group_Page::publish( $id, $slug, $changes['title'] );
			if ( ! $result['page'] ) {
				$notice['restaurants'][ $id ] = array(
					'slug_value' => $slug,
					'page_error' => __( 'WordPress could not create the page. Please try again.', 'livia' ),
				);
			} elseif ( $result['created'] && Livia_Service::restaurant( $id )['replaced_id'] ) {
				$notice['restaurants'][ $id ] = array( 'menu_info' => true );
			}
		}//end foreach
		// A page cache keeps serving the page in its former layout: the screen asks to clear it.
		if ( $layouts ) {
			Livia_Service::save_settings( array( 'purge_due' => true ) );
		}
		// A restaurant whose only notice is menu_info was saved.
		$notice['saved'] = ! array_filter(
			$notice['restaurants'],
			static function ( $restaurant ) {
				return isset( $restaurant['slug_value'] ) || isset( $restaurant['page_error'] );
			}
		);
		self::back( $notice );
	}

	/**
	 * Saves the booking experience tab: the section's switch, its phone bar and the order of the restaurant choice,
	 * then each restaurant's thresholds and paths; a refused value keeps the one saved and says why.
	 */
	public static function handle_save_experience() {
		self::guard( 'livia_save_experience' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified by guard().
		$settings = array(
			'xp_on'  => ! empty( $_POST['livia_xp_on'] ),
			'xp_bar' => ! empty( $_POST['livia_xp_bar'] ),
		);
		if ( isset( $_POST['livia_xp_order'] ) ) {
			$order                = array_map( 'sanitize_key', (array) wp_unslash( $_POST['livia_xp_order'] ) );
			$settings['xp_order'] = array_values( array_intersect( $order, array_keys( Livia_Service::restaurants() ) ) );
		}
		// phpcs:enable
		$notice = array( 'restaurants' => array() );
		foreach ( self::posted_restaurants() as $id => $fields ) {
			list( $changes, $errors, $typed ) = Livia_Experience::read_posted( $id, $fields );
			Livia_Service::save_restaurant( $id, $changes );
			if ( $errors ) {
				$notice['restaurants'][ $id ] = array(
					'xp_errors' => $errors,
					'xp_typed'  => $typed,
				);
			}
		}
		// The switch keeps what was asked: the section shows once every restaurant is set, see is_on(); the tab says what to fill.
		Livia_Service::save_settings( $settings );
		$notice['saved'] = ! $notice['restaurants'];
		self::back( $notice );
	}

	/**
	 * Saves the advanced settings tab: the styles of the group pages.
	 */
	public static function handle_save_advanced() {
		self::guard( 'livia_save_advanced' );
		$styles = isset( $_POST['livia_page_styles'] ) && 'isolated' === $_POST['livia_page_styles'] ? 'isolated' : 'inherit'; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by guard().
		if ( Livia_Service::settings()['page_styles'] !== $styles ) {
			// A page cache keeps serving the pages with their former styles: the screen asks to clear it.
			Livia_Service::save_settings(
				array(
					'page_styles' => $styles,
					'purge_due'   => true,
				)
			);
		}
		self::back( array( 'saved' => true ) );
	}

	/**
	 * Empties the cache of the cache and optimisation tools detected.
	 */
	public static function handle_purge_cache() {
		self::guard( 'livia_purge_cache' );
		$purged = Livia_Cache::purge();
		Livia_Service::save_settings( array( 'purge_due' => false ) );
		self::back( array( 'purged' => $purged ) );
	}

	/**
	 * Adds "Book a table" at the end of the theme's main menu.
	 */
	public static function handle_add_menu() {
		self::guard( 'livia_add_menu' );
		self::back( array( 'menu' => Livia_Experience::add_to_menu() ) );
	}

	/**
	 * Puts in the trash the old page that holds an address, so that a restaurant's group page takes it.
	 */
	public static function handle_trash() {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified by guard().
		$id      = self::posted_restaurant();
		self::guard( 'livia_trash_' . $id . '_' . $post_id );
		$post = get_post( $post_id );
		if ( ! $post || ! current_user_can( 'delete_post', $post_id ) ) {
			wp_die( esc_html__( 'You are not allowed to move this content to the trash.', 'livia' ), 403 );
		}
		if ( ! Livia_Service::restaurant( $id ) || null !== Livia_Group_Page::restaurant_of( $post_id ) ) {
			self::back( array() );
		}
		$slug = $post->post_name;
		if ( ! Livia_Rules::trash_keeps( $post->post_type, (int) EMPTY_TRASH_DAYS, (bool) MEDIA_TRASH ) ) {
			self::back(
				array(
					'restaurants' => array(
						$id => array(
							'slug_value'  => $slug,
							'trash_error' => EMPTY_TRASH_DAYS > 0
								? __( 'This site has no trash for media files: the file would be hidden with no way to restore it. Choose another address, or delete the file yourself.', 'livia' )
								: __( 'This site empties its trash at once: the old content would be deleted for good. Choose another address, or delete the content yourself.', 'livia' ),
						),
					),
				)
			);
		}
		if ( wp_trash_post( $post_id ) ) {
			Livia_Service::save_restaurant( $id, array( 'replaced_id' => $post_id ) );
		}
		self::back( array( 'restaurants' => array( $id => array( 'slug_value' => $slug ) ) ) );
	}

	/**
	 * The title a new group page takes.
	 *
	 * @param string $name The restaurant's name, when the site has several, empty otherwise.
	 * @return string
	 */
	public static function default_title( $name = '' ) {
		/* translators: %s: restaurant name. */
		$title = '' === $name ? __( 'Group booking', 'livia' ) : sprintf( __( 'Group booking, %s', 'livia' ), $name );
		// A translation installed from elsewhere becomes the page title: the same cleaning as a title typed in.
		return sanitize_text_field( $title );
	}

	/**
	 * The published pages the bubble rules can list, the group pages left out.
	 *
	 * @return int[]
	 */
	public static function site_page_ids() {
		return array_map(
			'intval',
			get_posts(
				array(
					'post_type'      => 'page',
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'orderby'        => 'menu_order title',
					'order'          => 'ASC',
					'exclude'        => array_values( Livia_Group_Page::page_ids() ), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- A few ids on a settings screen.
				)
			)
		);
	}

	/**
	 * A widget's format as the screen names it.
	 *
	 * @param string $format Format.
	 * @return string
	 */
	public static function format_label( $format ) {
		$labels = array(
			'bubble' => __( 'Floating bubble', 'livia' ),
			'button' => __( 'Button in the page', 'livia' ),
			'inline' => __( 'Embedded conversation', 'livia' ),
		);
		return isset( $labels[ $format ] ) ? $labels[ $format ] : '';
	}

	/**
	 * A widget's description in the site's language.
	 *
	 * @param array $widget Widget.
	 * @return string
	 */
	public static function description( $widget ) {
		$lang = 'fr' === substr( determine_locale(), 0, 2 ) ? 'fr' : 'en';
		return isset( $widget['description'][ $lang ] ) ? (string) $widget['description'][ $lang ] : '';
	}

	/**
	 * Renders the screen.
	 */
	public static function render() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		$notice = self::take_notice();
		// One restaurant Livia does not answer for means it is down: the others keep what they last received.
		$stale = false;
		foreach ( array_keys( Livia_Service::restaurants() ) as $id ) {
			if ( 'unavailable' === Livia_Service::refresh_connection( $id ) ) {
				$stale = true;
				break;
			}
		}
		$view = self::view( $notice, $stale );
		include LIVIA_DIR . 'includes/views/settings.php';
	}

	/**
	 * Everything the screen shows, computed once.
	 *
	 * @param array $notice What the last action left.
	 * @param bool  $stale  Whether Livia did not answer to this display.
	 * @return array
	 */
	private static function view( $notice, $stale ) {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Only picks what the screen shows.
		$tab        = self::tab_of( $_GET );
		$change_key = isset( $_GET['change-key'] ) ? sanitize_key( wp_unslash( $_GET['change-key'] ) ) : '';
		$add        = isset( $_GET['add'] );
		// phpcs:enable
		$bubbles     = Livia_Widgets::bubbles();
		$settings    = Livia_Service::settings();
		$several     = count( $bubbles ) > 1;
		$restaurants = array();
		foreach ( Livia_Service::restaurants() as $id => $restaurant ) {
			$connection         = Livia_Service::connection( $id );
			$refused            = in_array( $connection['state'], array( 'unknown_key', 'connector_disabled' ), true );
			$restaurants[ $id ] = array(
				'settings'   => $restaurant,
				'connection' => $connection,
				'name'       => Livia_Service::name( $id ),
				'mask'       => Livia_Rules::mask_key( $restaurant['key'] ),
				'show_key'   => $change_key === $id || ( isset( $notice['key_for'] ) && $notice['key_for'] === $id ),
				'key_error'  => isset( $notice['key_for'] ) && $notice['key_for'] === $id ? $notice['key_error'] : ( $refused ? self::key_message( $connection['state'] ) . ' ' . __( 'Meanwhile, the group booking page shows a short notice instead of its content.', 'livia' ) : '' ),
				'notice'     => isset( $notice['restaurants'][ $id ] ) ? $notice['restaurants'][ $id ] : array(),
				'preview'    => array(
					'widget' => Livia_Service::public_url( '/wordpress/v1/preview/widget', array( 'key' => $restaurant['key'] ) ),
					// The styles are a setting of the site, saved in another tab; the layout follows the form.
					'page'   => Livia_Service::public_url(
						'/wordpress/v1/preview/page',
						array_filter(
							array(
								'key'    => $restaurant['key'],
								'styles' => 'isolated' === $settings['page_styles'] ? 'isolated' : '',
							)
						)
					),
				),
			);
		}//end foreach
		$online = count( array_filter( array_keys( Livia_Service::restaurants() ), static fn( $id ) => null !== Livia_Group_Page::published( (string) $id ) ) );

		$view = array(
			'notice'      => $notice,
			'tab'         => $tab,
			'stale'       => $stale && $restaurants,
			'restaurants' => $restaurants,
			'several'     => $several,
			'add'         => $add || ( isset( $notice['key_for'] ) && '' === $notice['key_for'] ),
			'key_error'   => isset( $notice['key_for'] ) && '' === $notice['key_for'] ? $notice['key_error'] : '',
			'key_value'   => isset( $notice['key_value'] ) ? $notice['key_value'] : '',
			'site_on'     => $settings['site_on'],
			'widget_on'   => $settings['site_on'] && array_filter( $bubbles, array( 'Livia_Rules', 'shows_bubble' ) ),
			'xp_on'       => Livia_Experience::is_on(),
			'xp_switch'   => $settings['xp_on'],
			'xp_bar'      => $settings['xp_bar'],
			'online'      => $online,
			'home_host'   => untrailingslashit( preg_replace( '#^https?://#', '', home_url( '/' ) ) ),
			// Only a cache tool keeps pages from before a change.
			'purge_due'   => $settings['purge_due'] && Livia_Cache::detected(),
		);
		if ( ! $restaurants ) {
			return $view;
		}
		if ( 'experience' === $tab ) {
			return array_merge( $view, self::experience_view( $restaurants ) );
		}
		if ( 'advanced' === $tab ) {
			return array_merge( $view, self::advanced_view() );
		}
		return 'page' === $tab ? array_merge( $view, self::page_view( $restaurants, $several ) ) : array_merge( $view, self::widget_view( $restaurants, $bubbles ) );
	}

	/**
	 * What the booking experience tab shows: each restaurant's settings, what a refused save typed, its photos and
	 * conversations, in the order of the restaurant choice; then the menu and the preview.
	 *
	 * @param array $restaurants See view().
	 * @return array
	 */
	private static function experience_view( $restaurants ) {
		$ordered = array();
		foreach ( Livia_Experience::ordered_ids() as $id ) {
			$view      = $restaurants[ $id ];
			$errors    = isset( $view['notice']['xp_errors'] ) ? $view['notice']['xp_errors'] : array();
			$xp        = array_merge( array_intersect_key( $view['settings'], Livia_Service::EXPERIENCE ), isset( $view['notice']['xp_typed'] ) ? $view['notice']['xp_typed'] : array() );
			$page      = Livia_Group_Page::published( $id );
			$photos    = array();
			$photo_ids = array( 'xp_table_photo', 'xp_group_photo', 'xp_event_photo', 'xp_photo' );
			foreach ( $photo_ids as $key ) {
				$photos[ $key ] = Livia_Experience::photo_url( $view['settings'][ $key ] );
			}
			$ordered[ $id ] = array_merge(
				$view,
				array(
					'id'            => $id,
					'xp'            => $xp,
					'xp_errors'     => $errors,
					'photos'        => $photos,
					'conversations' => Livia_Service::widgets( $id, array( 'inline' ) ),
					'group_widget'  => Livia_Experience::group_widget( $id ),
					'page_url'      => $page ? (string) get_permalink( $page ) : '',
				)
			);
		}//end foreach
		$config = Livia_Experience::config();
		// The preview opens the window at once and creates no conversation.
		$config['autoOpen'] = true;
		$config['preview']  = true;
		$asset              = include LIVIA_DIR . 'build/experience.asset.php';
		return array(
			'restaurants' => $ordered,
			'xp_missing'  => Livia_Experience::to_complete(),
			'menu_state'  => Livia_Experience::menu_state(),
			'xp_preview'  => array(
				'config' => $config,
				'css'    => add_query_arg( 'ver', $asset['version'], LIVIA_URL . 'build/style-experience.css' ),
				'js'     => add_query_arg( 'ver', $asset['version'], LIVIA_URL . 'build/experience.js' ),
				'theme'  => wp_get_global_stylesheet() . Livia_Experience::theme_css(),
				'site'   => get_bloginfo( 'name' ),
			),
		);
	}

	/**
	 * What the advanced settings tab shows: the cache and optimisation tools detected, with what to copy into those
	 * that offer no hook, and the styles of the group pages.
	 *
	 * @return array
	 */
	private static function advanced_view() {
		$patterns = implode( "\n", Livia_Cache::patterns() );
		$pages    = implode( "\n", Livia_Cache::group_page_urls() );
		$copy     = array(
			'delay'   => $patterns,
			'defer'   => $patterns,
			'minify'  => $patterns,
			'combine' => $patterns,
			'page'    => $pages,
			'css'     => implode( "\n", Livia_Cache::style_patterns() ),
		);
		// The image lazy loading exclusions of these tools read the image tag, not the page: they get the class Livia
		// puts on the images of its group pages, each as the tool expects it, and where to paste it.
		$lazy = array(
			'flyingpress' => array(
				'copy'  => 'skip-lazy',
				'where' => __( 'Image lazy loading: in FlyingPress, add the copied keyword to the images excluded from lazy loading; FlyingPress looks for it anywhere in the image tag.', 'livia' ),
			),
			'nitropack'   => array(
				'copy'  => 'img.skip-lazy',
				'where' => __( 'Image lazy loading: paste the copied rule in NitroPack, Settings > Images & Media > Images lazy loading > Additional options, field “Specify CSS selectors to disable image lazy loading for matching elements”.', 'livia' ),
			),
		);
		return array(
			'tools'       => Livia_Cache::detected(),
			'copy'        => $copy,
			'lazy'        => $lazy,
			'page_styles' => Livia_Service::settings()['page_styles'],
		);
	}

	/**
	 * What the site widgets tab shows of each restaurant.
	 *
	 * @param array   $restaurants See view().
	 * @param array[] $bubbles     See Livia_Widgets::bubbles().
	 * @return array
	 */
	private static function widget_view( $restaurants, $bubbles ) {
		$pages   = self::site_page_ids();
		$site_on = Livia_Service::settings()['site_on'];
		foreach ( array_keys( $restaurants ) as $id ) {
			$restaurants[ $id ]['bubbles']    = Livia_Service::widgets( $id, array( 'bubble' ) );
			$restaurants[ $id ]['in_content'] = Livia_Service::widgets( $id, array( 'button', 'inline' ) );
			$restaurants[ $id ]['count']      = $site_on && Livia_Rules::shows_bubble( $bubbles[ $id ] ) ? Livia_Rules::bubble_page_count( $bubbles, $id, $pages ) : null;
			// The desktop bubble saved, which Livia no longer offers: the screen asks for another one instead of showing one.
			// `both` when the phone has no widget of its own left either, see Livia_Widgets::bubble(); `off` when the
			// switch already keeps the bubble off.
			$known                         = Livia_Rules::widget_ids( $restaurants[ $id ]['bubbles'] );
			$saved                         = $restaurants[ $id ]['settings'];
			$restaurants[ $id ]['missing'] = '';
			if ( '' !== $saved['site_widget'] && ! in_array( $saved['site_widget'], $known, true ) ) {
				$restaurants[ $id ]['missing'] = ! $saved['bubble_on'] ? 'off' : ( in_array( $saved['phone_widget'], $known, true ) ? 'desktop' : 'both' );
			}
			// The pages another restaurant holds under "Only" on this restaurant's screens, with its name.
			$restaurants[ $id ]['taken'] = array_map(
				static fn( $owner ) => $restaurants[ $owner ]['name'],
				Livia_Rules::taken_from( $bubbles, $id )
			);
		}
		return array(
			'restaurants' => $restaurants,
			'site_pages'  => $pages,
		);
	}

	/**
	 * What the group pages tab shows of each restaurant.
	 *
	 * @param array $restaurants See view().
	 * @param bool  $several     Whether the site has several restaurants.
	 * @return array
	 */
	private static function page_view( $restaurants, $several ) {
		$index = 0;
		foreach ( $restaurants as $id => $view ) {
			$settings = $view['settings'];
			$page     = Livia_Group_Page::page( $id );
			$online   = null !== Livia_Group_Page::published( $id );
			if ( isset( $view['notice']['slug_value'] ) ) {
				$slug = $view['notice']['slug_value'];
			} elseif ( $online ) {
				$slug = $page->post_name;
			} elseif ( '' !== $settings['slug'] ) {
				$slug = $settings['slug'];
			} elseif ( $page && 'trash' !== $page->post_status ) {
				$slug = $page->post_name;
			} else {
				$slug = sanitize_title( _x( 'group-booking', 'default address of the group booking page', 'livia' ) . ( $index ? '-' . $view['name'] : '' ) );
			}
			$conflict = Livia_Group_Page::check_address( $slug, $id );
			$widgets  = Livia_Service::widgets( $id, array( 'inline' ) );
			// The list is offered with an embedded conversation only, so its empty choice always names one.
			$auto     = Livia_Service::auto_page_widget( $id );
			$default  = Livia_Service::widget( $auto );
			$follow   = array(
				'id'          => '',
				'name'        => sprintf(
					/* translators: %s: name of the embedded conversation the group page shows when none is chosen. */
					__( 'Automatic: %s', 'livia' ),
					$default['name'] ?? ''
				),
				'format'      => $default['format'] ?? '',
				'description' => $default['description'] ?? array(),
			);
			$shared   = (string) $view['connection']['pageWidgetId'];
			$fallback = '' !== $settings['page_widget'] && ! in_array( $settings['page_widget'], Livia_Rules::widget_ids( $widgets ), true );

			$restaurants[ $id ] = array_merge(
				$view,
				array(
					'page'         => $page,
					'online'       => $online,
					// A site's only restaurant puts its page online at the first save; with several, each page waits for its
					// switch, so that saving one restaurant does not publish another's.
					'page_on'      => $online || ( ! $page && ! $several ),
					'at_home'      => $online && ! $conflict && $page->post_name === $slug,
					'slug'         => $slug,
					'conflict'     => $conflict,
					'title'        => '' !== $settings['title'] ? $settings['title'] : ( $page ? $page->post_title : self::default_title( $several ? $view['name'] : '' ) ),
					'headline'     => sprintf(
						/* translators: %s: restaurant name. */
						__( '%s is with you for your event.', 'livia' ),
						'' !== $view['name'] ? $view['name'] : __( 'Your restaurant', 'livia' )
					),
					'page_widgets' => $widgets ? array_merge( array( $follow ), $widgets ) : array(),
					'page_widget'  => $settings['page_widget'],
					'page_layout'  => $settings['page_layout'],
					// Only a widget chosen here can be missing: the Livia booking page's widget is a default, and may be a bubble.
					'fallback'     => $fallback,
					'auto_widget'  => $auto,
					'shared'       => $shared,
					// The page counts its conversations with the Livia booking page's when it shows that same widget.
					'follow'       => $fallback || Livia_Service::page_widget( $id ) === $shared,
				)
			);
			++$index;
		}//end foreach
		return array( 'restaurants' => $restaurants );
	}

	/**
	 * An icon of the screen, from its fixed set.
	 *
	 * @param string $name Icon name.
	 * @return string SVG markup.
	 */
	public static function icon( $name ) {
		$paths = array(
			'external' => '<path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>',
			'error'    => '<circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/>',
			'ok'       => '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>',
			'lock'     => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
			'check'    => '<path d="M20 6 9 17l-5-5"/>',
			'chevron'  => '<path d="m6 9 6 6 6-6"/>',
			'eye'      => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
			'info'     => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>',
			'plus'     => '<path d="M12 5v14"/><path d="M5 12h14"/>',
			'close'    => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
			'up'       => '<path d="m18 15-6-6-6 6"/>',
			'down'     => '<path d="m6 9 6 6 6-6"/>',
			'copy'     => '<rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
			'button'   => '<rect x="3" y="8" width="18" height="8" rx="4"/>',
			'guests'   => '<circle cx="9" cy="8" r="3"/><path d="M3 20a6 6 0 0 1 12 0"/><path d="M16 4a3 3 0 0 1 0 6"/><path d="M21 20a6 6 0 0 0-4-5.7"/>',
			'link'     => '<path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/>',
			'code'     => '<path d="m16 18 6-6-6-6"/><path d="m8 6-6 6 6 6"/>',
			'menu'     => '<path d="M4 6h16M4 12h16M4 18h10"/>',
		);
		return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="' . ( 'check' === $name ? '2.4' : '2' ) . '" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
	}
}
