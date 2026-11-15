<?php
/**
 * The "Book button" element of Elementor.
 *
 * @package Livia
 */

defined( 'ABSPATH' ) || exit;

/**
 * Opens the booking experience, in the style of the site kit's buttons; rendered as the block and the shortcode.
 */
final class Livia_Elementor_Reserver extends \Elementor\Widget_Base {

	/**
	 * Element name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'livia-reserver';
	}

	/**
	 * Element title in the panel.
	 *
	 * @return string
	 */
	public function get_title() {
		return esc_html__( 'Book button', 'livia' );
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
		return array( 'livia', 'book', 'booking', 'reservation' );
	}

	/**
	 * The label, what the window opens on, and the restaurant when the site has several.
	 */
	protected function register_controls() {
		$this->start_controls_section( 'livia', array( 'label' => esc_html__( 'Book button', 'livia' ) ) );
		$this->add_control(
			'label',
			array(
				'label'       => esc_html__( 'Label', 'livia' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'placeholder' => esc_html__( 'Book a table', 'livia' ),
			)
		);
		$this->add_control(
			'target',
			array(
				'label'   => esc_html__( 'Opens the window on', 'livia' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'guests',
				'options' => array(
					'guests' => esc_html__( 'The choice of the number of guests', 'livia' ),
					'event'  => esc_html__( 'The private hire, at once', 'livia' ),
				),
			)
		);
		Livia_Elementor::restaurant_control( $this );
		$this->end_controls_section();
	}

	/**
	 * Renders the button, nothing while the booking experience is off.
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();
		$button   = Livia_Experience::button( (string) $settings['label'], (string) ( $settings['restaurant'] ?? '' ), 'event' === $settings['target'], 'elementor-button elementor-button-link elementor-size-sm' );
		if ( '' !== $button ) {
			echo '<div class="elementor-button-wrapper">' . $button . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- button() escapes its values.
		}
	}
}
