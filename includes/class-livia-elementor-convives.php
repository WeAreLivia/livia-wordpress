<?php
/**
 * The "How many of you?" element of Elementor.
 *
 * @package Livia
 */

defined( 'ABSPATH' ) || exit;

/**
 * The guest selector, as a band or a card; rendered as the block and the shortcode.
 */
final class Livia_Elementor_Convives extends \Elementor\Widget_Base {

	/**
	 * Element name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'livia-convives';
	}

	/**
	 * Element title in the panel.
	 *
	 * @return string
	 */
	public function get_title() {
		return esc_html__( 'How many of you?', 'livia' );
	}

	/**
	 * Element icon in the panel.
	 *
	 * @return string
	 */
	public function get_icon() {
		return Livia_Elementor::ICON;
	}

	/**
	 * Panel categories.
	 *
	 * @return string[]
	 */
	public function get_categories() {
		return array( Livia_Elementor::CATEGORY );
	}

	/**
	 * Search keywords.
	 *
	 * @return string[]
	 */
	public function get_keywords() {
		return array( 'livia', 'guests', 'booking', 'reservation' );
	}

	/**
	 * The layout, and the restaurant when the site has several.
	 */
	protected function register_controls() {
		$this->start_controls_section( 'livia', array( 'label' => esc_html__( 'How many of you?', 'livia' ) ) );
		$this->add_control(
			'variant',
			array(
				'label'   => esc_html__( 'Layout', 'livia' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'card',
				'options' => array(
					'card' => esc_html__( 'Card', 'livia' ),
					'band' => esc_html__( 'Band, over a photo', 'livia' ),
				),
			)
		);
		Livia_Elementor::restaurant_control( $this );
		$this->end_controls_section();
	}

	/**
	 * Renders the selector, nothing while the booking experience is off.
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();
		echo Livia_Experience::selector( (string) $settings['variant'], (string) ( $settings['restaurant'] ?? '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- selector() escapes its values.
	}
}
