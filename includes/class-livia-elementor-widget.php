<?php
/**
 * The "Livia" element of Elementor: a button or an embedded conversation of a connected restaurant.
 *
 * @package Livia
 */

defined( 'ABSPATH' ) || exit;

/**
 * Places a Livia widget, rendered as the "Livia widget" block and the shortcode render it.
 */
final class Livia_Elementor_Widget extends \Elementor\Widget_Base {

	/**
	 * Element name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'livia';
	}

	/**
	 * Element title in the panel.
	 *
	 * @return string
	 */
	public function get_title() {
		return 'Livia';
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
		return array( 'livia', 'chat', 'booking' );
	}

	/**
	 * The widget bundle: the editor renders the element over ajax, where a script enqueued while rendering is never
	 * printed, so the preview loads it beforehand.
	 *
	 * @return string[]
	 */
	public function get_script_depends() {
		return array( Livia_Widgets::register_script( Livia_Service::public_url( Livia_Widgets::BUNDLE ) ) );
	}

	/**
	 * The one setting: the widget, among the buttons and embedded conversations of each connected restaurant.
	 */
	protected function register_controls() {
		$groups = array( '' => esc_html__( 'Choose a widget', 'livia' ) );
		// Keyed by restaurant id: numeric keys would come before the empty choice in the editor's script.
		foreach ( array_keys( Livia_Service::restaurants() ) as $id ) {
			$options = array();
			foreach ( Livia_Service::widgets( $id, array( 'button', 'inline' ) ) as $widget ) {
				// Elementor prints option titles as HTML.
				$options[ (string) $widget['id'] ] = esc_html( ( isset( $widget['name'] ) ? $widget['name'] : $widget['id'] ) . ' · ' . Livia_Admin::format_label( $widget['format'] ) );
			}
			if ( $options ) {
				$groups[ $id ] = array(
					'label'   => Livia_Service::name( $id ),
					'options' => $options,
				);
			}
		}
		$link = sprintf( '<a href="%s" target="_blank" rel="noopener">%s</a>', esc_url( Livia_Admin::LIVIA_ADMIN_URL ), esc_html__( 'edit this widget in Livia', 'livia' ) );

		$this->start_controls_section( 'livia', array( 'label' => esc_html__( 'Widget', 'livia' ) ) );
		$this->add_control(
			'widget',
			array(
				'label'       => esc_html__( 'Livia widget', 'livia' ),
				'type'        => \Elementor\Controls_Manager::SELECT,
				'default'     => '',
				'groups'      => $groups,
				'description' => Livia_Service::restaurants()
					/* translators: %s: link to Livia's admin. */
					? sprintf( esc_html__( 'The buttons and embedded conversations of your connected restaurants. Label, colors and shape: %s.', 'livia' ), $link )
					: esc_html__( 'Connect your restaurant in the Livia menu to choose a widget.', 'livia' ),
			)
		);
		$this->end_controls_section();
	}

	/**
	 * Renders the widget as the block and the shortcode do.
	 */
	protected function render() {
		echo Livia_Widgets::in_content( (string) $this->get_settings_for_display( 'widget' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup() escapes its values.
	}
}
