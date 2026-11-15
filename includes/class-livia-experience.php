<?php
/**
 * The booking experience: a window that asks for the number of guests and leads the visitor to the right path.
 *
 * @package Livia
 */

defined( 'ABSPATH' ) || exit;

/**
 * Its blocks, shortcodes, phone bar and menu link, its settings as the window reads them, and its assets, loaded only
 * while the section is on.
 */
final class Livia_Experience {

	const HANDLE = 'livia-experience';

	/**
	 * The address every trigger points to, and the class the plugin puts on the menu link it adds.
	 */
	const ANCHOR     = '#reserver';
	const MENU_CLASS = 'livia-reserver';

	/**
	 * The class that marks the menu link as the plugin's own: the screen invites the restaurateur to put MENU_CLASS
	 * on links of their own, which the plugin leaves in place when it goes.
	 */
	const ADDED_CLASS = 'livia-added';

	/**
	 * The booking tools offered, first the default; `page` stands for a page of the site.
	 */
	const TOOLS = array( 'zenchef', 'thefork', 'sevenrooms', 'guestonline', 'tourbiz', 'other', 'page' );

	/**
	 * Hooks the experience.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'wp_footer', array( __CLASS__, 'footer' ), 20 );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
		add_filter( 'wp_nav_menu_objects', array( __CLASS__, 'menu_items' ) );
		add_filter( 'render_block_core/navigation-link', array( __CLASS__, 'navigation_link' ), 10, 3 );
		add_filter( 'render_block_core/navigation-submenu', array( __CLASS__, 'navigation_link' ), 10, 3 );
	}

	/**
	 * Whether the section is on, with a restaurant to book and every restaurant set: the window offers them all, and
	 * would otherwise lead a visitor to an empty path.
	 *
	 * @return bool
	 */
	public static function is_on() {
		return Livia_Service::settings()['xp_on'] && Livia_Service::restaurants() && ! self::to_complete();
	}

	/**
	 * The fields each restaurant's paths still lack for the window to show it.
	 *
	 * @return array<string, string[]> Fields to fill by restaurant id, only for the restaurants not set yet.
	 */
	public static function to_complete() {
		$missing = array();
		foreach ( Livia_Service::restaurants() as $id => $restaurant ) {
			$event  = 'tool' === $restaurant['xp_event_dest'] ? 'xp_event_url' : 'xp_email';
			$fields = array_keys(
				array(
					'xp_url' => $restaurant['xp_url'],
					$event   => $restaurant[ $event ],
				),
				'',
				true
			);
			if ( $fields ) {
				$missing[ $id ] = $fields;
			}
		}
		return $missing;
	}

	/**
	 * Registers the two blocks and the two shortcodes.
	 */
	public static function register() {
		register_block_type( LIVIA_DIR . 'build/reserver', array( 'render_callback' => array( __CLASS__, 'render_button_block' ) ) );
		register_block_type( LIVIA_DIR . 'build/convives', array( 'render_callback' => array( __CLASS__, 'render_selector_block' ) ) );
		wp_set_script_translations( 'livia-reserver-editor-script', 'livia', LIVIA_DIR . 'languages' );
		wp_set_script_translations( 'livia-convives-editor-script', 'livia', LIVIA_DIR . 'languages' );
		add_shortcode( 'livia_reserver', array( __CLASS__, 'render_button_shortcode' ) );
		add_shortcode( 'livia_convives', array( __CLASS__, 'render_selector_shortcode' ) );
	}

	/**
	 * Loads the window's style in the head and its script deferred, with its settings, on every page while the
	 * section is on: any link to #reserver opens it.
	 */
	public static function enqueue() {
		if ( ! self::is_on() ) {
			return;
		}
		$asset = include LIVIA_DIR . 'build/experience.asset.php';
		wp_enqueue_style( self::HANDLE, LIVIA_URL . 'build/style-experience.css', array(), $asset['version'] );
		wp_add_inline_style( self::HANDLE, self::theme_css() );
		wp_enqueue_script(
			self::HANDLE,
			LIVIA_URL . 'build/experience.js',
			$asset['dependencies'],
			$asset['version'],
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		wp_add_inline_script( self::HANDLE, 'var liviaExperience = ' . wp_json_encode( self::config() ) . ';', 'before' );
	}

	/**
	 * The theme's colours and fonts the window and the selector take, from its global styles; each falls back to a
	 * sober value in the stylesheet when the theme gives none.
	 *
	 * @return string CSS.
	 */
	public static function theme_css() {
		$values = array(
			'bg'         => array( 'color', 'background' ),
			'ink'        => array( 'color', 'text' ),
			'button'     => array( 'elements', 'button', 'color', 'background' ),
			'button-ink' => array( 'elements', 'button', 'color', 'text' ),
			'font'       => array( 'typography', 'fontFamily' ),
			'heading'    => array( 'elements', 'heading', 'typography', 'fontFamily' ),
		);
		$css    = '';
		foreach ( $values as $name => $path ) {
			$value = Livia_Rules::css_value( wp_get_global_styles( $path ) );
			$css  .= '' !== $value ? '--livia-' . $name . ':' . $value . ';' : '';
		}
		return '' === $css ? '' : ':root{' . $css . '}';
	}

	/**
	 * Everything the window needs, its texts translated here.
	 *
	 * @return array
	 */
	public static function config() {
		return array(
			'restaurants' => array_map( array( __CLASS__, 'restaurant_config' ), self::ordered_ids() ),
			'bundle'      => Livia_Service::public_url( Livia_Widgets::BUNDLE ),
			'backend'     => Livia_Widgets::backend(),
			'i18n'        => self::strings(),
		);
	}

	/**
	 * The connected restaurants in the order of the restaurant choice, the ones never ordered last.
	 *
	 * @return string[]
	 */
	public static function ordered_ids() {
		$ids   = array_keys( Livia_Service::restaurants() );
		$order = array_values( array_intersect( array_map( 'strval', (array) Livia_Service::settings()['xp_order'] ), $ids ) );
		return array_merge( $order, array_values( array_diff( $ids, $order ) ) );
	}

	/**
	 * A restaurant as the window reads it: its experience settings, each photo as its address, the group page's
	 * address while it is published, and the conversation it opens.
	 *
	 * @param string $id Restaurant id.
	 * @return array
	 */
	public static function restaurant_config( $id ) {
		$restaurant = Livia_Service::restaurant( $id );
		$page       = Livia_Group_Page::published( $id );
		$config     = array(
			'id'   => $id,
			'name' => Livia_Service::name( $id ),
			'page' => $page ? (string) get_permalink( $page ) : '',
		);
		foreach ( array_keys( Livia_Service::EXPERIENCE ) as $key ) {
			$config[ $key ] = '_photo' === substr( $key, -6 ) ? self::photo_url( $restaurant[ $key ] ) : $restaurant[ $key ];
		}
		if ( count( Livia_Service::restaurants() ) < 2 ) {
			// The tab shows these two fields from two restaurants on: alone, they stay stored but unused.
			$config['xp_photo']  = '';
			$config['xp_phrase'] = '';
		}
		$config['xp_group_widget'] = self::group_widget( $id );
		$config['group_text']      = sprintf(
			'' !== $config['name']
				/* translators: 1: name of the conversation's assistant, Livia by default, 2: restaurant name (in French, already preceded by "de" and contracted). */
				? __( '%1$s, %2$s\'s assistant, prepares your table with you: date, menus, space. She answers you right away, 7 days a week.', 'livia' )
				/* translators: %1$s: name of the conversation's assistant, Livia by default. */
				: __( '%1$s, the restaurant\'s assistant, prepares your table with you: date, menus, space. She answers you right away, 7 days a week.', 'livia' ),
			self::assistant_name( $id ),
			Livia_Rules::of_name( $config['name'], determine_locale() )
		);
		return $config;
	}

	/**
	 * The name of the assistant of the conversation the group path opens, on the same branches as `destination()` in
	 * src/experience/logic.js: the published group page when it is the path chosen or when no embedded conversation is
	 * offered, else the embedded conversation.
	 *
	 * @param string $id Restaurant id.
	 * @return string Livia by default.
	 */
	private static function assistant_name( $id ) {
		$group  = self::group_widget( $id );
		$page   = ( 'page' === Livia_Service::restaurant( $id )['xp_group_dest'] || '' === $group ) && Livia_Group_Page::published( $id );
		$widget = Livia_Service::widget( $page ? Livia_Service::page_widget( $id ) : $group );
		return ! empty( $widget['assistantName'] ) && is_string( $widget['assistantName'] ) ? $widget['assistantName'] : 'Livia';
	}

	/**
	 * The address of a photo of the media library.
	 *
	 * @param int $attachment_id Attachment id, 0 for none.
	 * @return string Empty for none.
	 */
	public static function photo_url( $attachment_id ) {
		$url = $attachment_id ? wp_get_attachment_image_url( (int) $attachment_id, 'large' ) : '';
		return $url ? (string) $url : '';
	}

	/**
	 * The conversation the group path opens in the window: the one chosen while Livia still offers it, else the
	 * restaurant's group page widget, else its first embedded conversation.
	 *
	 * @param string $id Restaurant id.
	 * @return string Widget id, empty for none.
	 */
	public static function group_widget( $id ) {
		$offered = Livia_Rules::widget_ids( Livia_Service::widgets( $id, array( 'inline' ) ) );
		foreach ( array( Livia_Service::restaurant( $id )['xp_group_widget'], Livia_Service::page_widget( $id ) ) as $candidate ) {
			if ( in_array( (string) $candidate, $offered, true ) ) {
				return (string) $candidate;
			}
		}
		return $offered ? $offered[0] : '';
	}

	/**
	 * The window's texts.
	 *
	 * @return string[]
	 */
	public static function strings() {
		// The restaurant is not chosen yet: the assistant's name is the one every restaurant shares, else Livia.
		$names = array_unique( array_map( array( __CLASS__, 'assistant_name' ), self::ordered_ids() ) );
		return array(
			'book'         => __( 'Book a table', 'livia' ),
			'howMany'      => __( 'How many of you?', 'livia' ),
			'whereHowMany' => __( 'Where and how many of you?', 'livia' ),
			'empty'        => __( 'Enter the number of guests', 'livia' ),
			'emptyHint'    => __( 'Online table, group with Livia or event: what comes next depends on you.', 'livia' ),
			'pick'         => __( 'Choose the restaurant', 'livia' ),
			'restaurant'   => __( 'Restaurant', 'livia' ),
			'fewer'        => __( 'One guest fewer', 'livia' ),
			'more'         => __( 'One guest more', 'livia' ),
			'count'        => __( 'Number of guests', 'livia' ),
			'guest'        => __( 'guest', 'livia' ),
			'guests'       => __( 'guests', 'livia' ),
			/* translators: 1: smallest number of guests, 2: largest. */
			'range'        => __( '%1$d to %2$d', 'livia' ),
			/* translators: 1: smallest number of guests of a group, 2: largest. */
			'rangeGroup'   => __( '%1$d to %2$d · group', 'livia' ),
			/* translators: %d: smallest number of guests of an event. */
			'rangeEvent'   => __( '%d and over', 'livia' ),
			/* translators: %d: smallest number of guests of an event, on a quick choice button. */
			'chipEvent'    => __( '%d+', 'livia' ),
			'labelTable'   => __( 'Table', 'livia' ),
			'labelGroup'   => __( 'Group', 'livia' ),
			/* translators: %d: event threshold, in guests. */
			'labelEvent'   => __( 'Over %d guests', 'livia' ),
			'tableTitle'   => __( 'Online booking', 'livia' ),
			'tableText'    => __( 'Choose your time slot online, in a few seconds.', 'livia' ),
			'groupTitle'   => __( 'Group table with Livia', 'livia' ),
			// Before a restaurant is chosen, the hint names none.
			/* translators: %1$s: name of the conversation's assistant, Livia by default. */
			'groupAny'     => sprintf( __( '%1$s, the restaurant\'s assistant, prepares your table with you: date, menus, space. She answers you right away, 7 days a week.', 'livia' ), 1 === count( $names ) ? reset( $names ) : 'Livia' ),
			'eventTitle'   => __( 'Private hire or event', 'livia' ),
			/* translators: %d: event threshold, in guests. */
			'eventText'    => __( 'Beyond %d guests, or to hire the place, the team makes you a tailored offer.', 'livia' ),
			'goTable'      => __( 'Book a table', 'livia' ),
			'goGroup'      => __( 'Prepare my group table', 'livia' ),
			'goContact'    => __( 'Contact the restaurant', 'livia' ),
			'goEvent'      => __( 'Request a private hire', 'livia' ),
			'back'         => __( 'Guests', 'livia' ),
			'openTab'      => __( 'Open in a tab', 'livia' ),
			'loading'      => __( 'Loading', 'livia' ),
			'close'        => __( 'Close', 'livia' ),
			'chatTitle'    => __( 'Let us prepare your group table', 'livia' ),
			'eventTool'    => __( 'Private hire', 'livia' ),
			'contactTitle' => __( 'Contact the restaurant', 'livia' ),
			/* translators: %d: event threshold, in guests. */
			'contactText'  => __( 'For more than %d guests or a private hire, contact the restaurant.', 'livia' ),
			'contactAny'   => __( 'Write to the restaurant or drop by: the team will answer you.', 'livia' ),
			'byEmail'      => __( 'By email', 'livia' ),
			'byPhone'      => __( 'By phone', 'livia' ),
			'onSite'       => __( 'On site', 'livia' ),
			'write'        => __( 'Write to the restaurant', 'livia' ),
		);
	}

	/**
	 * The "Book" button: a link to #reserver, which the window's script turns into its trigger.
	 *
	 * @param string $label      Label, empty for "Book a table".
	 * @param string $restaurant Restaurant the window opens on, empty for the choice.
	 * @param bool   $event      Whether it opens the event path at once.
	 * @param string $classes    Classes that give it the style of the theme's (or the page builder's) buttons.
	 * @return string Empty while the section is off.
	 */
	public static function button( $label, $restaurant = '', $event = false, $classes = 'wp-block-button__link wp-element-button' ) {
		if ( ! self::is_on() ) {
			return '';
		}
		$label = trim( (string) $label );
		return sprintf(
			'<a class="%s %s" href="%s"%s%s>%s</a>',
			esc_attr( $classes ),
			esc_attr( self::MENU_CLASS ),
			esc_attr( self::ANCHOR ),
			self::restaurant_attribute( $restaurant ),
			$event ? ' data-livia-event' : '',
			esc_html( '' !== $label ? $label : __( 'Book a table', 'livia' ) )
		);
	}

	/**
	 * The "How many of you?" selector, drawn by the window's script, as a band or a card.
	 *
	 * @param string $variant    `band` or `card`.
	 * @param string $restaurant Restaurant it books, empty for the choice.
	 * @param string $attributes Attributes of the wrapper, already escaped.
	 * @return string Empty while the section is off.
	 */
	public static function selector( $variant, $restaurant = '', $attributes = '' ) {
		if ( ! self::is_on() ) {
			return '';
		}
		$variant = 'band' === $variant ? 'band' : 'card';
		return sprintf(
			'<div %s data-livia-convives="%s"%s></div>',
			'' !== $attributes ? $attributes : 'class="livia-convives"',
			esc_attr( $variant ),
			self::restaurant_attribute( $restaurant )
		);
	}

	/**
	 * The attribute naming the restaurant a trigger books, when it is a connected one.
	 *
	 * @param string $restaurant Restaurant id.
	 * @return string
	 */
	private static function restaurant_attribute( $restaurant ) {
		$restaurant = (string) $restaurant;
		return '' !== $restaurant && Livia_Service::restaurant( $restaurant ) ? sprintf( ' data-livia-restaurant="%s"', esc_attr( $restaurant ) ) : '';
	}

	/**
	 * Renders the "Book button" block.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public static function render_button_block( $attributes ) {
		$target = $attributes['target'] ?? '';
		$button = self::button( $attributes['label'] ?? '', $attributes['restaurant'] ?? '', 'event' === $target );
		return '' === $button ? '' : sprintf( '<div %s><div class="wp-block-button">%s</div></div>', get_block_wrapper_attributes(), $button );
	}

	/**
	 * Renders the "How many of you?" block.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public static function render_selector_block( $attributes ) {
		$variant = 'band' === ( $attributes['variant'] ?? '' ) ? 'band' : 'card';
		return self::selector( $variant, $attributes['restaurant'] ?? '', get_block_wrapper_attributes( array( 'class' => 'livia-convives' ) ) );
	}

	/**
	 * Renders `[livia_reserver label="…" target="event" restaurant="…"]`.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function render_button_shortcode( $atts ) {
		$atts   = shortcode_atts(
			array(
				'label'      => '',
				'target'     => '',
				'restaurant' => '',
			),
			$atts,
			'livia_reserver'
		);
		$button = self::button( sanitize_text_field( $atts['label'] ), sanitize_key( $atts['restaurant'] ), 'event' === $atts['target'] );
		// Like the block: a bare inline link would leave the theme's constrained column.
		return '' === $button ? '' : '<div class="wp-block-button">' . $button . '</div>';
	}

	/**
	 * Renders `[livia_convives variant="band|card" restaurant="…"]`.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function render_selector_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'variant'    => 'card',
				'restaurant' => '',
			),
			$atts,
			'livia_convives'
		);
		return self::selector( sanitize_key( $atts['variant'] ), sanitize_key( $atts['restaurant'] ) );
	}

	/**
	 * Whether the page shows the phone bar: never on a group page, whose conversation takes the bottom of the screen.
	 *
	 * @return bool
	 */
	private static function shows_bar() {
		return self::is_on() && Livia_Service::settings()['xp_bar'] && null === Livia_Group_Page::current();
	}

	/**
	 * Prints the phone bar, which takes the place of the bubbles on phones (see style.css).
	 */
	public static function footer() {
		if ( ! self::shows_bar() ) {
			return;
		}
		printf(
			'<div class="livia-xp-mbar"><a class="wp-element-button %s" href="%s">%s</a></div>',
			esc_attr( self::MENU_CLASS ),
			esc_attr( self::ANCHOR ),
			esc_html__( 'Book a table', 'livia' )
		);
	}

	/**
	 * Marks the pages showing the phone bar, so that it covers none of their end.
	 *
	 * @param string[] $classes Body classes.
	 * @return string[]
	 */
	public static function body_class( $classes ) {
		if ( self::shows_bar() ) {
			$classes[] = 'livia-has-mbar';
		}
		return $classes;
	}

	/**
	 * Takes the menu links the window would open off the site while the section is off: the one the plugin added, and
	 * a link to #reserver, which then opens nothing. A link of the restaurateur's to their own tool stays.
	 *
	 * @param WP_Post[] $items Menu items.
	 * @return WP_Post[]
	 */
	public static function menu_items( $items ) {
		if ( self::is_on() ) {
			return $items;
		}
		return array_filter(
			$items,
			static function ( $item ) {
				return ! self::leaves_when_off( $item->url, (array) $item->classes );
			}
		);
	}

	/**
	 * Whether a menu link leaves the site while the section is off, see menu_items().
	 *
	 * @param string   $url     Link address.
	 * @param string[] $classes Link classes.
	 * @return bool
	 */
	private static function leaves_when_off( $url, $classes ) {
		return in_array( self::MENU_CLASS, $classes, true ) && self::is_anchor( $url );
	}

	/**
	 * Takes a block theme's navigation link off the site while the section is off, as menu_items() does. Made a
	 * submenu, the links under it stay, a level up, as a classic menu shows them, unless WordPress hid the submenu.
	 *
	 * @param string        $html     Rendered link or submenu.
	 * @param array         $block    Parsed block.
	 * @param WP_Block|null $instance Block.
	 * @return string
	 */
	public static function navigation_link( $html, $block, $instance = null ) {
		$classes = explode( ' ', (string) ( $block['attrs']['className'] ?? '' ) );
		if ( '' === $html || ! self::leaves_when_off( (string) ( $block['attrs']['url'] ?? '' ), $classes ) || self::is_on() ) {
			return $html;
		}
		$links = '';
		foreach ( $instance ? $instance->inner_blocks : array() as $link ) {
			$links .= $link->render();
		}
		return $links;
	}

	/**
	 * The classic menu shown at the theme's main location: the one named like a main menu, else the first assigned.
	 * A location whose menu no longer exists shows none, as in wp_nav_menu(): a menu deleted under another theme stays
	 * in this theme's locations.
	 *
	 * @return int Menu id, 0 for none.
	 */
	public static function main_menu() {
		$assigned = array_filter( array_intersect_key( get_nav_menu_locations(), get_registered_nav_menus() ), 'is_nav_menu' );
		foreach ( array( 'primary', 'main', 'menu-1', 'header' ) as $location ) {
			if ( ! empty( $assigned[ $location ] ) ) {
				return (int) $assigned[ $location ];
			}
		}
		return $assigned ? (int) reset( $assigned ) : 0;
	}

	/**
	 * The Navigation menu a block theme shows in the header of the front page: that of the first Navigation block of the
	 * first header its template holds with one, even deep, else of its first header, else of the theme's header. No other
	 * header: the screen warns rather than add the link to a menu the visitor may not see.
	 *
	 * @param bool $create Whether WordPress may create its fallback menu, as it does when the header first shows.
	 * @return WP_Post|false|null The menu; false while WordPress has not created its fallback; null for none.
	 */
	public static function main_navigation( $create = false ) {
		$front  = self::front_page_blocks();
		$header = self::header_blocks( $front, true ) ?? self::header_blocks( $front ) ?? self::header_blocks( parse_blocks( '<!-- wp:template-part {"slug":"header"} /-->' ) ) ?? array();
		// A bar the header holds before its own menu may have one too.
		$nav = self::first_navigation( $header ) ?? self::first_navigation( $header, true );
		return $nav ? self::navigation_menu( $nav, $create ) : null;
	}

	/**
	 * The blocks of the template WordPress shows the front page with: the hierarchies its template loader goes through,
	 * in its order, each through its filter.
	 *
	 * @return array[] Parsed blocks.
	 */
	private static function front_page_blocks() {
		$static = 'page' === get_option( 'show_on_front' );
		// get_post( 0 ) would give the current post.
		$page = $static && get_option( 'page_on_front' ) ? get_post( (int) get_option( 'page_on_front' ) ) : null;
		if ( $page ) {
			$hierarchies = array(
				'frontpage' => array( 'front-page.php' ),
				'page'      => array_filter( array( get_page_template_slug( $page ), 'page-' . urldecode( $page->post_name ) . '.php', 'page-' . $page->post_name . '.php', 'page-' . $page->ID . '.php', 'page.php' ) ),
				'singular'  => array( 'singular.php' ),
			);
		} else {
			// A static front page with no page chosen shows the latest posts as the home, without the front page template.
			$hierarchies = ( $static ? array() : array( 'frontpage' => array( 'front-page.php' ) ) ) + array( 'home' => array( 'home.php', 'index.php' ) );
		}
		$hierarchies['index'] = array( 'index.php' );
		$templates            = array();
		foreach ( $hierarchies as $type => $hierarchy ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- The template loader's own filters.
			array_push( $templates, ...array_values( (array) apply_filters( "{$type}_template_hierarchy", $hierarchy ) ) );
		}
		$template = resolve_block_template( 'frontpage', array_unique( $templates ), '' );
		return $template ? parse_blocks( $template->content ) : array();
	}

	/**
	 * The menu a Navigation block shows, as the block renders it: the one it refers to, else the one WordPress falls
	 * back to. None when the block lists its links itself, shows a classic menu or a filtered fallback, or a template
	 * part on phones: only overriding the theme's header would change those.
	 *
	 * @param array $nav    Parsed block.
	 * @param bool  $create Whether WordPress may create its fallback menu.
	 * @return WP_Post|false|null The menu; false while WordPress has not created its fallback; null for none.
	 */
	private static function navigation_menu( $nav, $create ) {
		$attrs = $nav['attrs'];
		if ( ! empty( $attrs['overlay'] ) ) {
			return null;
		}
		// The block reads its former attribute as `ref`.
		if ( array_key_exists( 'navigationMenuId', $attrs ) ) {
			$attrs['ref'] = $attrs['navigationMenuId'];
		}
		if ( array_key_exists( 'ref', $attrs ) ) {
			$menu = get_post( (int) $attrs['ref'] );
			// A published menu shows as it is, even empty, a missing one shows nothing; any other falls back.
			if ( ! $menu || 'publish' === $menu->post_status ) {
				return $menu && 'wp_navigation' === $menu->post_type ? $menu : null;
			}
		} elseif ( $nav['innerBlocks'] || ( defined( 'IS_GUTENBERG_PLUGIN' ) && IS_GUTENBERG_PLUGIN && isset( $attrs['__unstableLocation'] ) && wp_get_nav_menu_items( get_nav_menu_locations()[ $attrs['__unstableLocation'] ] ?? 0 ) ) ) {
			return null;
		}
		if ( has_filter( 'block_core_navigation_render_fallback' ) ) {
			return null;
		}
		// A closure of its own, so that removing it leaves a site's own __return_false in place.
		$wait = static function () {
			return false;
		};
		if ( ! $create ) {
			add_filter( 'wp_navigation_should_create_fallback', $wait );
		}
		$menu = WP_Navigation_Fallback::get_fallback();
		remove_filter( 'wp_navigation_should_create_fallback', $wait );
		if ( $menu && ! block_core_navigation_filter_out_empty_blocks( parse_blocks( $menu->post_content ) ) ) {
			// The block shows the pages in place of an empty fallback menu.
			$menu               = clone $menu;
			$menu->post_content = '<!-- wp:page-list /-->';
		}
		return $menu ? $menu : false;
	}

	/**
	 * The blocks a pattern, a synced pattern or a template part shows, as the block renders them.
	 *
	 * @param array $block Parsed block.
	 * @return array{0: string, 1: array[], 2: string}|null The key that names it, its blocks and, for a template part,
	 *                                                      its area; null for another block.
	 */
	private static function included( $block ) {
		$attrs = $block['attrs'];
		switch ( $block['blockName'] ) {
			case 'core/pattern':
				$pattern = WP_Block_Patterns_Registry::get_instance()->get_registered( (string) ( $attrs['slug'] ?? '' ) );
				return array( 'pattern:' . ( $attrs['slug'] ?? '' ), $pattern ? parse_blocks( $pattern['content'] ) : array(), '' );
			case 'core/block':
				$post = get_post( (int) ( $attrs['ref'] ?? 0 ) );
				$show = $post && 'wp_block' === $post->post_type && 'publish' === $post->post_status && ! post_password_required( $post );
				return array( 'block:' . ( $post ? $post->ID : 0 ), $show ? parse_blocks( $post->post_content ) : array(), '' );
			case 'core/template-part':
				$theme = $attrs['theme'] ?? get_stylesheet();
				$id    = $theme . '//' . ( $attrs['slug'] ?? '' );
				$part  = isset( $attrs['slug'] ) && get_stylesheet() === $theme ? get_block_template( $id, 'wp_template_part' ) : null;
				// The site shows a part saved in the editor only once published, else the theme's.
				$part = $part && 'publish' !== $part->status ? get_block_file_template( $id, 'wp_template_part' ) : $part;
				$area = $part ? (string) $part->area : '';
				// A part outside the theme's areas takes the one the template gives it, else the header is the one so named.
				if ( in_array( $area, array( '', WP_TEMPLATE_PART_AREA_UNCATEGORIZED ), true ) ) {
					$area = $attrs['area'] ?? ( 'header' === ( $attrs['slug'] ?? '' ) ? WP_TEMPLATE_PART_AREA_HEADER : '' );
				}
				return array( 'part:' . ( $attrs['slug'] ?? '' ), $part ? parse_blocks( $part->content ) : array(), $area );
		}//end switch
		return null;
	}

	/**
	 * The blocks of the first header template part the blocks show.
	 *
	 * @param array[] $blocks   Parsed blocks.
	 * @param bool    $with_nav Whether to pass over a header without a Navigation block, such as a bar above the menu.
	 * @param bool[]  $seen     The patterns and template parts being read, by key: one that holds itself shows once.
	 * @return array[]|null Parsed blocks, null without a header.
	 */
	private static function header_blocks( $blocks, $with_nav = false, $seen = array() ) {
		foreach ( $blocks as $block ) {
			$included = self::included( $block );
			if ( $included && isset( $seen[ $included[0] ] ) ) {
				continue;
			}
			if ( $included && WP_TEMPLATE_PART_AREA_HEADER === $included[2] ) {
				if ( ! $with_nav || self::first_navigation( $included[1], true ) ) {
					return $included[1];
				}
				continue;
			}
			$header = $included ? self::header_blocks( $included[1], $with_nav, $seen + array( $included[0] => true ) ) : self::header_blocks( $block['innerBlocks'], $with_nav, $seen );
			if ( null !== $header ) {
				return $header;
			}
		}
		return null;
	}

	/**
	 * The first Navigation block, patterns included, which show in place.
	 *
	 * @param array[] $blocks Parsed blocks.
	 * @param bool    $deep   Whether to look into synced patterns and template parts too.
	 * @param bool[]  $seen   See header_blocks().
	 * @return array|null Parsed block.
	 */
	private static function first_navigation( $blocks, $deep = false, $seen = array() ) {
		foreach ( $blocks as $block ) {
			if ( 'core/navigation' === $block['blockName'] ) {
				return $block;
			}
			$included = self::included( $block );
			if ( $included && ( isset( $seen[ $included[0] ] ) || ( ! $deep && 'core/pattern' !== $block['blockName'] ) ) ) {
				continue;
			}
			$nav = $included ? self::first_navigation( $included[1], $deep, $seen + array( $included[0] => true ) ) : self::first_navigation( $block['innerBlocks'], $deep, $seen );
			if ( $nav ) {
				return $nav;
			}
		}
		return null;
	}

	/**
	 * Whether an address leads to #reserver, on this page or another.
	 *
	 * @param string $url Address.
	 * @return bool
	 */
	private static function is_anchor( $url ) {
		return ltrim( self::ANCHOR, '#' ) === wp_parse_url( (string) $url, PHP_URL_FRAGMENT );
	}

	/**
	 * Whether blocks hold a link to #reserver, in a submenu too.
	 *
	 * @param array[] $blocks Parsed blocks.
	 * @return bool
	 */
	private static function links_to_anchor( $blocks ) {
		foreach ( $blocks as $block ) {
			if ( self::is_anchor( $block['attrs']['url'] ?? '' ) || self::links_to_anchor( $block['innerBlocks'] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether "Book a table" can be added to the main menu.
	 *
	 * @return string `block` for a block theme whose header has no Navigation menu to add to, see main_navigation();
	 *                `none` without a classic menu at a location; `present` when the menu holds a link to #reserver
	 *                already; `ready` otherwise.
	 */
	public static function menu_state() {
		if ( wp_is_block_theme() ) {
			$menu = self::main_navigation();
			if ( null === $menu ) {
				return 'block';
			}
			return $menu && self::links_to_anchor( parse_blocks( $menu->post_content ) ) ? 'present' : 'ready';
		}
		$menu = self::main_menu();
		if ( ! $menu ) {
			return 'none';
		}
		foreach ( (array) wp_get_nav_menu_items( $menu ) as $item ) {
			if ( self::is_anchor( $item->url ) ) {
				return 'present';
			}
		}
		return 'ready';
	}

	/**
	 * Adds "Book a table" at the end of the main menu, once.
	 *
	 * @return string `added`, `error`, or the state that prevented it, see menu_state().
	 */
	public static function add_to_menu() {
		if ( wp_is_block_theme() ) {
			$menu = self::main_navigation( true );
			if ( null === $menu ) {
				return 'block';
			}
			// WordPress may have just created it from a classic menu that holds the link.
			if ( $menu && self::links_to_anchor( parse_blocks( $menu->post_content ) ) ) {
				return 'present';
			}
			$link = get_comment_delimited_block_content(
				'core/navigation-link',
				array(
					'label'     => __( 'Book a table', 'livia' ),
					'url'       => self::ANCHOR,
					'kind'      => 'custom',
					'className' => self::MENU_CLASS . ' ' . self::ADDED_CLASS,
				),
				''
			);
			// Appended, not re-serialized: the menu keeps the blocks the restaurateur set as they are.
			return $menu && wp_update_post(
				wp_slash(
					array(
						'ID'           => $menu->ID,
						'post_content' => $menu->post_content . $link,
					)
				)
			) ? 'added' : 'error';
		}//end if
		$state = self::menu_state();
		if ( 'ready' !== $state ) {
			return $state;
		}
		$item = wp_update_nav_menu_item(
			self::main_menu(),
			0,
			array(
				'menu-item-title'   => __( 'Book a table', 'livia' ),
				'menu-item-url'     => self::ANCHOR,
				'menu-item-classes' => self::MENU_CLASS . ' ' . self::ADDED_CLASS,
				'menu-item-type'    => 'custom',
				'menu-item-status'  => 'publish',
			)
		);
		return is_wp_error( $item ) ? 'error' : 'added';
	}

	/**
	 * Removes from every menu of the site, classic or block, wherever the restaurateur moved them, the links to
	 * #reserver the plugin added: without the plugin they open nothing. Whatever the active theme, which a network
	 * switching sites does not switch. Needs only WordPress, for the uninstall.
	 */
	public static function remove_from_menus() {
		$ours = static function ( $url, $classes ) {
			return self::is_anchor( $url ) && in_array( self::ADDED_CLASS, $classes, true );
		};
		foreach ( wp_get_nav_menus() as $menu ) {
			$items = (array) wp_get_nav_menu_items( $menu, array( 'post_status' => 'any' ) );
			foreach ( $items as $key => $item ) {
				if ( ! $ours( $item->url, (array) $item->classes ) ) {
					continue;
				}
				// Its children move up a level, as the Menus screen does: an orphan item leaves the menu. Kept up to date, the
				// parent stays right for the children of a link of ours under another.
				foreach ( $items as $child ) {
					if ( (int) $child->menu_item_parent === $item->ID ) {
						$child->menu_item_parent = $item->menu_item_parent;
						update_post_meta( $child->ID, '_menu_item_menu_item_parent', (int) $item->menu_item_parent );
					}
				}
				wp_delete_post( $item->ID, true );
				unset( $items[ $key ] );
			}
		}//end foreach
		$navigations = get_posts(
			array(
				'post_type'   => 'wp_navigation',
				'post_status' => 'any',
				'numberposts' => -1,
			)
		);
		foreach ( $navigations as $navigation ) {
			// Cut out of the text, not re-serialized: the rest of the menu stays as the restaurateur set it. A submenu loses
			// only its delimiters, its links taking its place, a level up. The serialized attributes escape every "-->".
			preg_match_all(
				'#\s*<!--\s+(/?)wp:(?:core/)?navigation-(?:link|submenu)\s+(?:(\{(?:(?!-->).)*?\})\s+)?(/?)-->#s',
				$navigation->post_content,
				$delimiters,
				PREG_SET_ORDER | PREG_OFFSET_CAPTURE
			);
			$open = array();
			$cuts = array();
			foreach ( $delimiters as $delimiter ) {
				$cut = array( $delimiter[0][1], strlen( $delimiter[0][0] ) );
				if ( '/' === $delimiter[1][0] ) {
					// The end of the innermost block still open.
					if ( array_pop( $open ) ) {
						$cuts[] = $cut;
					}
					continue;
				}
				$attrs = json_decode( $delimiter[2][0], true );
				$mine  = is_array( $attrs ) && $ours( $attrs['url'] ?? '', explode( ' ', (string) ( $attrs['className'] ?? '' ) ) );
				if ( '/' !== $delimiter[3][0] ) {
					$open[] = $mine;
				}
				if ( $mine ) {
					$cuts[] = $cut;
				}
			}
			$content = $navigation->post_content;
			foreach ( array_reverse( $cuts ) as $cut ) {
				$content = substr_replace( $content, '', $cut[0], $cut[1] );
			}
			if ( $content !== $navigation->post_content ) {
				wp_update_post(
					wp_slash(
						array(
							'ID'           => $navigation->ID,
							'post_content' => $content,
						)
					)
				);
			}
		}//end foreach
	}

	/**
	 * A booking address as typed: an address of the site may start with `/`; `{n}` survives the cleaning, which would
	 * otherwise drop its braces.
	 *
	 * @param string $raw Address as posted.
	 * @return string|null Empty when none was typed, null when it is refused.
	 */
	public static function booking_url( $raw ) {
		$url = trim( (string) $raw );
		if ( '' === $url ) {
			return '';
		}
		if ( '/' === $url[0] && '/' !== substr( $url, 1, 1 ) ) {
			$url = home_url( $url );
		}
		// esc_url_raw() would prefix any text with http://.
		if ( ! preg_match( '#^https?://#i', $url ) ) {
			return null;
		}
		$url = str_replace( '%7Bn%7D', '{n}', esc_url_raw( str_replace( '{n}', '%7Bn%7D', $url ), array( 'http', 'https' ) ) );
		return Livia_Rules::is_booking_url( $url ) ? $url : null;
	}

	/**
	 * Reads the experience settings a restaurant's panel posted; a refused or absent value keeps the one saved.
	 *
	 * @param string $id     Restaurant id.
	 * @param array  $fields Fields posted for the restaurant, unslashed.
	 * @return array{0: array, 1: string[], 2: string[]} The settings to save, the message of each refused field, and
	 *                                                   what was typed in each refused field.
	 */
	public static function read_posted( $id, $fields ) {
		$changes = array();
		$errors  = array();
		$text    = static function ( $name, $max = 200 ) use ( $fields ) {
			if ( ! isset( $fields[ $name ] ) || ! is_string( $fields[ $name ] ) ) {
				return '';
			}
			// An address keeps its %XX sequences, which sanitize_text_field() would remove; booking_url() cleans it.
			return mb_substr( in_array( $name, array( 'xp_url', 'xp_event_url' ), true ) ? trim( $fields[ $name ] ) : sanitize_text_field( $fields[ $name ] ), 0, $max );
		};
		$choice  = static function ( $name, $allowed ) use ( $fields ) {
			$value = isset( $fields[ $name ] ) && is_string( $fields[ $name ] ) ? sanitize_key( $fields[ $name ] ) : '';
			return in_array( $value, $allowed, true ) ? $value : $allowed[0];
		};

		$thresholds = Livia_Rules::thresholds( $text( 'xp_from', 4 ), $text( 'xp_above', 4 ) );
		if ( $thresholds ) {
			list( $changes['xp_from'], $changes['xp_above'] ) = $thresholds;
		} else {
			$errors['xp_from'] = __( 'A group starts at 2 guests at least, and at the event threshold at most; events start at 999 guests at most.', 'livia' );
		}

		$changes['xp_tool']       = $choice( 'xp_tool', self::TOOLS );
		$changes['xp_open']       = $choice( 'xp_open', array( 'window', 'tab', 'page' ) );
		$changes['xp_group_dest'] = $choice( 'xp_group_dest', array( 'window', 'page' ) );
		$changes['xp_event_dest'] = $choice( 'xp_event_dest', array( 'contact', 'tool' ) );

		foreach ( array( 'xp_url', 'xp_event_url' ) as $name ) {
			$url = self::booking_url( $text( $name, 2000 ) );
			if ( null === $url ) {
				$errors[ $name ] = __( 'Enter a full web address, starting with https://, or a path of your site, starting with /.', 'livia' );
			} else {
				$changes[ $name ] = $url;
			}
		}
		if ( ! isset( $errors['xp_url'] ) && '' === $changes['xp_url'] ) {
			$errors['xp_url'] = __( 'Enter the address of your booking tool: the window opens it for tables below the group threshold.', 'livia' );
		}
		if ( 'tool' === $changes['xp_event_dest'] && ! isset( $errors['xp_event_url'] ) && '' === $changes['xp_event_url'] ) {
			$errors['xp_event_url'] = __( 'Enter the address of your private hire tool.', 'livia' );
		}

		$email = sanitize_email( $text( 'xp_email' ) );
		if ( '' !== $text( 'xp_email' ) && ! is_email( $email ) ) {
			$errors['xp_email'] = __( 'This e-mail address is not valid.', 'livia' );
		} elseif ( 'contact' === $changes['xp_event_dest'] && '' === $email ) {
			$errors['xp_email'] = __( 'Enter the restaurant\'s e-mail address: the window shows a “Write to the restaurant” button.', 'livia' );
		} else {
			$changes['xp_email'] = $email;
		}

		$widget                     = $text( 'xp_group_widget' );
		$changes['xp_group_widget'] = in_array( $widget, Livia_Rules::widget_ids( Livia_Service::widgets( $id, array( 'inline' ) ) ), true ) ? $widget : '';
		foreach ( array( 'xp_group_title', 'xp_table_caption', 'xp_group_caption', 'xp_event_caption', 'xp_phrase', 'xp_address' ) as $name ) {
			$changes[ $name ] = $text( $name );
		}
		$changes['xp_phone'] = $text( 'xp_phone', 40 );
		foreach ( array( 'xp_table_photo', 'xp_group_photo', 'xp_event_photo', 'xp_photo' ) as $name ) {
			$photo            = isset( $fields[ $name ] ) ? absint( $fields[ $name ] ) : 0;
			$changes[ $name ] = $photo && wp_attachment_is_image( $photo ) ? $photo : 0;
		}
		// A refused field keeps its saved value, as does a field the tab did not show: the photo and sentence of a
		// restaurant only show with two.
		$changes = array_diff_key( array_intersect_key( $changes, $fields ), $errors );

		$typed = array();
		foreach ( array_keys( $errors ) as $name ) {
			$typed[ $name ] = $text( $name, 2000 );
		}
		if ( isset( $errors['xp_from'] ) ) {
			$typed['xp_above'] = $text( 'xp_above', 4 );
		}
		return array( $changes, $errors, $typed );
	}
}
