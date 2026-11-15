<?php
/**
 * Livia widgets on the site: the site bubble, the "Livia widget" block, the shortcode and the editor panel.
 *
 * @package Livia
 */

defined( 'ABSPATH' ) || exit;

/**
 * Places Livia widgets on the site's pages.
 */
final class Livia_Widgets {

	const META_MODE   = '_livia_widget_mode';
	const META_WIDGET = '_livia_widget_id';
	const BLOCK       = 'livia/widget';

	/**
	 * Address of the widget bundle the Livia service serves.
	 */
	const BUNDLE = '/livia-chat.standalone.js';

	/**
	 * The two bubbles of a site with a phone widget: each shows on its own screens, from the experience bar's breakpoint
	 * (760px). Printed with the elements, so it does not depend on the experience being on.
	 */
	const SCREENS_CSS = '@media (min-width:761px){livia-chat[data-livia-screen="phone"]{display:none}}@media (max-width:760px){livia-chat[data-livia-screen="desktop"]{display:none}}';

	/**
	 * Whether the page already carries a bubble.
	 *
	 * @var bool
	 */
	private static $bubble_placed = false;

	/**
	 * Hooks the widgets.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'wp_footer', array( __CLASS__, 'footer' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'editor_assets' ) );
	}

	/**
	 * Registers the page meta of the editor panel, the block and the shortcode.
	 */
	public static function register() {
		foreach ( array( 'post', 'page' ) as $type ) {
			foreach ( array( self::META_MODE, self::META_WIDGET ) as $key ) {
				register_post_meta(
					$type,
					$key,
					array(
						'type'              => 'string',
						'single'            => true,
						'default'           => self::META_MODE === $key ? 'site' : '',
						'show_in_rest'      => true,
						'sanitize_callback' => self::META_MODE === $key ? array( __CLASS__, 'sanitize_mode' ) : 'sanitize_text_field',
						'auth_callback'     => static function ( $allowed, $meta_key, $post_id ) {
							return current_user_can( 'edit_post', $post_id );
						},
					)
				);
			}
		}
		register_block_type( LIVIA_DIR . 'build/widget', array( 'render_callback' => array( __CLASS__, 'render_block' ) ) );
		wp_set_script_translations( 'livia-widget-editor-script', 'livia', LIVIA_DIR . 'languages' );
		add_shortcode( 'livia_widget', array( __CLASS__, 'render_shortcode' ) );
	}

	/**
	 * Keeps a page's widget choice among the known ones.
	 *
	 * @param string $mode Choice.
	 * @return string
	 */
	public static function sanitize_mode( $mode ) {
		return in_array( $mode, array( 'site', 'none', 'other' ), true ) ? $mode : 'site';
	}

	/**
	 * Registers a script of the Livia service, loaded without holding the page up.
	 *
	 * @param string $url Script address.
	 * @return string The script's handle.
	 */
	public static function register_script( $url ) {
		$handle = 'livia-' . md5( $url );
		wp_register_script(
			$handle,
			$url,
			array(),
			null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Livia serves its bundle's latest version at a fixed address.
			array(
				'in_footer' => true,
				'strategy'  => 'async',
			)
		);
		return $handle;
	}

	/**
	 * Loads a script of the Livia service once per page.
	 *
	 * @param string $url Script address.
	 */
	public static function enqueue_script( $url ) {
		wp_enqueue_script( self::register_script( $url ) );
	}

	/**
	 * The markup of a widget, its bundle loaded.
	 *
	 * The widget reads its settings from the production service unless told otherwise, as the code of Livia's admin.
	 *
	 * @param string $id     Widget id.
	 * @param string $screen `desktop` or `phone` for a site bubble shown on one kind of screen only, empty for all.
	 * @return string
	 */
	public static function markup( $id, $screen = '' ) {
		self::enqueue_script( Livia_Service::public_url( self::BUNDLE ) );
		$backend = self::backend();
		$widget  = Livia_Service::widget( $id );
		// `start-when-visible`: a widget the page hides (the other screen's bubble, a bubble under the phone bar of the
		// experience) starts only once shown. `data-livia-bubble`: the mark that lets that bar hide a bubble and leave
		// the embedded conversations.
		return sprintf(
			'<livia-chat widget="%s" start-when-visible%s%s%s></livia-chat>',
			esc_attr( $id ),
			'' === $backend ? '' : ' backend-url="' . esc_url( $backend ) . '"',
			$widget && 'bubble' === ( $widget['format'] ?? '' ) ? ' data-livia-bubble' : '',
			'' === $screen ? '' : ' data-livia-screen="' . esc_attr( $screen ) . '"'
		);
	}

	/**
	 * The service a widget names in its `backend-url`, empty for the production one it reads by default.
	 *
	 * @return string
	 */
	public static function backend() {
		$backend = untrailingslashit( LIVIA_PUBLIC_URL );
		return 'https://chat.booking.wearelivia.com' === $backend ? '' : $backend;
	}

	/**
	 * A widget placed in the content, by the block, the shortcode or the Elementor element; a second bubble on the
	 * same page is left out.
	 *
	 * @param string $id Widget id.
	 * @return string
	 */
	public static function in_content( $id ) {
		$widget = Livia_Service::widget( $id );
		// A widget no connected restaurant owns is never placed: the id comes from a page author.
		if ( '' === $id || ! $widget || ! empty( $widget['fullscreen'] ) ) {
			return '';
		}
		if ( 'bubble' === ( $widget['format'] ?? '' ) ) {
			if ( self::$bubble_placed ) {
				return '';
			}
			self::$bubble_placed = true;
		}
		return self::markup( $id );
	}

	/**
	 * Renders the "Livia widget" block.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public static function render_block( $attributes ) {
		$markup = self::in_content( isset( $attributes['widget'] ) ? (string) $attributes['widget'] : '' );
		return '' === $markup ? '' : sprintf( '<div %s>%s</div>', get_block_wrapper_attributes(), $markup );
	}

	/**
	 * Renders `[livia_widget widget="…"]`.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function render_shortcode( $atts ) {
		$atts = shortcode_atts( array( 'widget' => '' ), $atts, 'livia_widget' );
		return self::in_content( sanitize_text_field( $atts['widget'] ) );
	}

	/**
	 * The bubbles of the connected restaurants, as Livia_Rules::bubble_owner() reads them.
	 *
	 * @return array[] By restaurant id.
	 */
	public static function bubbles() {
		$bubbles = array();
		foreach ( Livia_Service::restaurants() as $id => $restaurant ) {
			$bubbles[ $id ] = self::bubble( $restaurant, Livia_Rules::widget_ids( Livia_Service::widgets( $id, array( 'bubble' ) ) ) );
		}
		return $bubbles;
	}

	/**
	 * A restaurant's bubble on each kind of screen. A widget the restaurant no longer has, or no longer in the bubble
	 * format, is empty: each screen keeps its own widget while it exists, and the phone without one of its own takes
	 * the desktop's.
	 *
	 * @param array    $restaurant The restaurant's settings, see Livia_Service::RESTAURANT.
	 * @param string[] $known      Ids of the restaurant's bubbles.
	 * @return array See Livia_Rules::bubble_owner().
	 */
	public static function bubble( $restaurant, $known ) {
		$desktop = $restaurant['bubble_on'] && in_array( $restaurant['site_widget'], $known, true ) ? $restaurant['site_widget'] : '';
		return array(
			'widget' => $desktop,
			'phone'  => $restaurant['bubble_on'] && in_array( $restaurant['phone_widget'], $known, true ) ? $restaurant['phone_widget'] : $desktop,
			'rule'   => $restaurant['site_rule'],
			'pages'  => $restaurant['site_pages'],
		);
	}

	/**
	 * Places the bubble of the page in the footer.
	 */
	public static function footer() {
		$post_id = 0;
		if ( is_singular() ) {
			$post_id = (int) get_queried_object_id();
		} elseif ( is_home() ) {
			// The posts page is a page the settings list, though WordPress does not show it as singular.
			$post_id = (int) get_option( 'page_for_posts' );
		}
		$other = $post_id ? (string) get_post_meta( $post_id, self::META_WIDGET, true ) : '';
		$found = '' !== $other ? Livia_Service::widget( $other ) : null;
		$mode  = $post_id ? (string) get_post_meta( $post_id, self::META_MODE, true ) : 'site';
		// A page that chose its own widget keeps that one on both screens.
		list( $desktop, $phone ) = Livia_Rules::footer_widget(
			Livia_Service::settings()['site_on'],
			self::bubbles(),
			$post_id,
			$mode,
			$found && 'bubble' === ( $found['format'] ?? '' ) && empty( $found['fullscreen'] ) ? $other : '',
			array_values( Livia_Group_Page::page_ids() ),
			self::$bubble_placed
		);
		if ( '' === $desktop && '' === $phone ) {
			return;
		}
		self::$bubble_placed = true;
		if ( $phone === $desktop ) {
			echo self::markup( $desktop ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup() escapes its values.
			return;
		}
		// Printed right before the elements, in the same output: the async bundle cannot connect them before the rule exists.
		echo '<style>' . self::SCREENS_CSS . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Constant.
		foreach ( array(
			'desktop' => $desktop,
			'phone'   => $phone,
		) as $screen => $id ) {
			if ( '' !== $id ) {
				echo self::markup( $id, $screen ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup() escapes its values.
			}
		}
	}

	/**
	 * Loads the editor panel and hands the restaurants' widgets to the editor.
	 */
	public static function editor_assets() {
		Livia_Service::refresh_for_editor();
		$post    = get_post();
		$bubbles = self::bubbles();
		$screens = Livia_Rules::footer_widget( Livia_Service::settings()['site_on'], $bubbles, $post ? $post->ID : 0, 'site', '', array(), false );
		$names   = array_map(
			static function ( $id ) {
				$widget = '' !== $id ? Livia_Service::widget( $id ) : null;
				return $widget ? (string) ( $widget['name'] ?? '' ) : '';
			},
			$screens
		);
		// Each screen's widget is named when they differ, as when another restaurant holds the phone's bubble.
		$site = '' !== $names[0] ? $names[0] : $names[1];
		if ( '' !== $names[0] && '' !== $names[1] && $screens[0] !== $screens[1] ) {
			/* translators: 1: widget shown on desktop, 2: widget shown on phone. */
			$site = sprintf( __( '%1$s on desktop, %2$s on phone', 'livia' ), $names[0], $names[1] );
		}
		$data = array(
			'connected'    => (bool) $bubbles,
			'siteWidget'   => $site,
			'restaurants'  => array_values(
				array_map(
					static function ( $id ) {
						return array(
							'id'        => (string) $id,
							'name'      => Livia_Service::name( $id ),
							'bubbles'   => self::for_editor( Livia_Service::widgets( $id, array( 'bubble' ) ) ),
							'inContent' => self::for_editor( Livia_Service::widgets( $id, array( 'inline', 'button' ) ) ),
						);
					},
					array_keys( $bubbles )
				)
			),
			'groupPageIds' => array_values( Livia_Group_Page::page_ids() ),
		);
		wp_add_inline_script( 'livia-widget-editor-script', 'window.liviaEditor = ' . wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP ) . ';', 'before' );

		$screen = get_current_screen();
		if ( ! $screen || 'post' !== $screen->base || ! in_array( $screen->post_type, array( 'post', 'page' ), true ) ) {
			return;
		}
		$asset = include LIVIA_DIR . 'build/editor-panel.asset.php';
		wp_enqueue_script( 'livia-editor-panel', LIVIA_URL . 'build/editor-panel.js', $asset['dependencies'], $asset['version'], true );
		wp_enqueue_style( 'livia-editor-panel', LIVIA_URL . 'build/editor-panel.css', array(), $asset['version'] );
		wp_set_script_translations( 'livia-editor-panel', 'livia', LIVIA_DIR . 'languages' );
	}

	/**
	 * Widgets as the editor shows them.
	 *
	 * @param array[] $widgets Widgets.
	 * @return array[]
	 */
	private static function for_editor( $widgets ) {
		return array_map(
			static function ( $widget ) {
				return array(
					'id'     => (string) $widget['id'],
					'name'   => isset( $widget['name'] ) ? (string) $widget['name'] : (string) $widget['id'],
					'format' => (string) $widget['format'],
				);
			},
			$widgets
		);
	}
}
