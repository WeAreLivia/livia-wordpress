<?php
/**
 * The "Livia" element of Elementor, hooked to Elementor's own actions: nothing runs without Elementor.
 *
 * @package Livia
 */

defined( 'ABSPATH' ) || exit;

/**
 * Adds the Livia category and elements to Elementor (3.5 or later, the first to fire `elementor/widgets/register`).
 */
final class Livia_Elementor {

	const CATEGORY = 'livia';

	/**
	 * CSS class of the Livia icon in Elementor's panel.
	 */
	const ICON = 'livia-elementor-icon';

	/**
	 * Hooks Elementor's actions.
	 */
	public static function init() {
		add_action( 'elementor/elements/categories_registered', array( __CLASS__, 'add_category' ) );
		add_action( 'elementor/widgets/register', array( __CLASS__, 'register' ) );
		add_action( 'elementor/editor/after_enqueue_styles', array( __CLASS__, 'icon_style' ) );
		add_action( 'elementor/editor/before_enqueue_scripts', array( 'Livia_Service', 'refresh_for_editor' ) );
		// The editor shows an element with nothing to render by its icon, inside the preview.
		add_action( 'elementor/preview/enqueue_styles', array( __CLASS__, 'icon_style' ) );
	}

	/**
	 * Adds the Livia category to Elementor's panel.
	 *
	 * @param \Elementor\Elements_Manager $elements Elementor's elements.
	 */
	public static function add_category( $elements ) {
		$elements->add_category(
			self::CATEGORY,
			array(
				'title' => 'Livia',
				'icon'  => self::ICON,
			)
		);
	}

	/**
	 * Registers the element; its class extends Elementor's, so it loads only now.
	 *
	 * @param \Elementor\Widgets_Manager $widgets Elementor's widgets.
	 */
	public static function register( $widgets ) {
		require_once LIVIA_DIR . 'includes/class-livia-elementor-widget.php';
		require_once LIVIA_DIR . 'includes/class-livia-elementor-reserver.php';
		require_once LIVIA_DIR . 'includes/class-livia-elementor-convives.php';
		$widgets->register( new Livia_Elementor_Widget() );
		$widgets->register( new Livia_Elementor_Reserver() );
		$widgets->register( new Livia_Elementor_Convives() );
	}

	/**
	 * Adds the restaurant a booking element opens on, when the site has several.
	 *
	 * @param \Elementor\Widget_Base $element Element.
	 */
	public static function restaurant_control( $element ) {
		$ids = Livia_Experience::ordered_ids();
		if ( count( $ids ) < 2 ) {
			return;
		}
		$options = array( '' => esc_html__( 'Asked to the visitor', 'livia' ) );
		foreach ( $ids as $id ) {
			// Elementor prints option titles as HTML.
			$options[ $id ] = esc_html( Livia_Service::name( $id ) );
		}
		$element->add_control(
			'restaurant',
			array(
				'label'   => esc_html__( 'Restaurant', 'livia' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => '',
				'options' => $options,
			)
		);
	}

	/**
	 * Loads the Livia icon of the panel.
	 */
	public static function icon_style() {
		wp_enqueue_style( 'livia-elementor', LIVIA_URL . 'assets/elementor.css', array(), LIVIA_VERSION );
	}
}
