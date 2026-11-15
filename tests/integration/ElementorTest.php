<?php
/**
 * The "Livia" element of Elementor; the Elementor tests run with LIVIA_TEST_ELEMENTOR=1, the others without it.
 *
 * @package Livia
 */

final class ElementorTest extends Livia_TestCase {

	private function needs_elementor( $loaded ) {
		if ( (bool) did_action( 'elementor/loaded' ) !== $loaded ) {
			$this->markTestSkipped( $loaded ? 'Elementor only: LIVIA_TEST_ELEMENTOR=1.' : 'Without Elementor only.' );
		}
	}

	private function new_page_view() {
		$bubble = new ReflectionProperty( Livia_Widgets::class, 'bubble_placed' );
		$bubble->setAccessible( true );
		$bubble->setValue( null, false );
	}

	private function element( $widget ) {
		return new Livia_Elementor_Widget(
			array(
				'id'         => 'a1b2c3d',
				'elType'     => 'widget',
				'widgetType' => 'livia',
				'settings'   => array( 'widget' => $widget ),
			),
			array()
		);
	}

	public function test_without_elementor_the_plugin_loads_nothing_of_it() {
		$this->needs_elementor( false );
		$this->connect();
		$this->connect_second();
		$page = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_content' => '[livia_widget widget="wdg_t_button"]',
			)
		);
		$this->go_to( get_permalink( $page ) );
		do_action( 'wp_enqueue_scripts' );
		$this->assertStringContainsString( 'wdg_t_button', apply_filters( 'the_content', get_post( $page )->post_content ) );

		$this->assertFalse( class_exists( 'Livia_Elementor_Widget', false ) );
		$this->assertFalse( wp_style_is( 'livia-elementor', 'registered' ) );
	}

	public function test_the_element_is_registered_in_the_livia_category() {
		$this->needs_elementor( true );
		$elementor = \Elementor\Plugin::$instance;

		$this->assertArrayHasKey( 'livia', $elementor->elements_manager->get_categories() );
		$element = $elementor->widgets_manager->get_widget_types( 'livia' );
		$this->assertInstanceOf( Livia_Elementor_Widget::class, $element );
		$this->assertSame( array( 'livia' ), $element->get_categories() );
		$this->assertSame( 'livia-elementor-icon', $element->get_icon() );
	}

	public function test_the_one_setting_lists_buttons_and_embedded_conversations_by_restaurant() {
		$this->needs_elementor( true );
		$this->connect();
		$this->connect_second();
		$connections                             = get_option( 'livia_connections' );
		$connections['r1']['widgets'][]          = array(
			'id'         => 'wdg_full',
			'name'       => 'Plein écran',
			'format'     => 'inline',
			'fullscreen' => true,
		);
		$connections['r2']['widgets'][0]['name'] = '<b>Groupe</b>';
		update_option( 'livia_connections', $connections );
		$element = \Elementor\Plugin::$instance->widgets_manager->get_widget_types( 'livia' );
		\Elementor\Plugin::$instance->controls_manager->delete_stack( $element );

		$controls = $element->get_controls();
		$section  = array_filter(
			$controls,
			static function ( $control ) {
				return 'livia' === ( $control['section'] ?? '' );
			}
		);
		$this->assertSame( array( 'widget' ), array_keys( $section ) );
		$this->assertArrayNotHasKey( 'style', $element->get_tabs_controls() );
		$groups = $controls['widget']['groups'];
		$this->assertSame( array( '', 'r1', 'r2' ), array_keys( $groups ) );
		$this->assertSame( 'Brasserie Lumière', $groups['r1']['label'] );
		$this->assertSame( array( 'wdg_page' ), array_keys( $groups['r1']['options'] ) );
		$this->assertSame( array( 'wdg_t_page', 'wdg_t_button' ), array_keys( $groups['r2']['options'] ) );
		// Elementor prints option titles as HTML.
		$this->assertStringStartsWith( '&lt;b&gt;Groupe&lt;/b&gt; · ', $groups['r2']['options']['wdg_t_page'] );
	}

	public function test_the_element_renders_as_the_block_and_the_shortcode() {
		$this->needs_elementor( true );
		$this->connect();
		$this->connect_second();
		$shortcode = do_shortcode( '[livia_widget widget="wdg_t_button"]' );
		$this->assertStringContainsString( '<livia-chat widget="wdg_t_button" start-when-visible', $shortcode );
		$block = render_block(
			array(
				'blockName'    => 'livia/widget',
				'attrs'        => array( 'widget' => 'wdg_t_button' ),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);
		$this->assertStringContainsString( $shortcode, $block );

		ob_start();
		$this->element( 'wdg_t_button' )->print_element();
		$this->assertStringContainsString( $shortcode, ob_get_clean() );

		// The editor's preview loads every element's scripts up front, as it renders elements over ajax.
		$handle = 'livia-' . md5( Livia_Service::public_url( Livia_Widgets::BUNDLE ) );
		wp_dequeue_script( $handle );
		\Elementor\Plugin::$instance->widgets_manager->enqueue_widgets_scripts();
		$this->assertTrue( wp_script_is( $handle, 'enqueued' ) );
	}

	private function booking_element( $type, $settings ) {
		$class = 'livia-reserver' === $type ? Livia_Elementor_Reserver::class : Livia_Elementor_Convives::class;
		return new $class(
			array(
				'id'         => 'e1f2a3b',
				'elType'     => 'widget',
				'widgetType' => $type,
				'settings'   => $settings,
			),
			array()
		);
	}

	public function test_a_translated_element_title_reaches_the_panel_as_text() {
		$this->needs_elementor( true );
		add_filter(
			'gettext_livia',
			static function ( $translation ) {
				return '<b>' . $translation . '</b>';
			}
		);
		foreach ( array( 'livia-reserver', 'livia-convives' ) as $type ) {
			$this->assertStringStartsWith( '&lt;b&gt;', \Elementor\Plugin::$instance->widgets_manager->get_widget_types( $type )->get_title(), $type );
		}
	}

	public function test_the_booking_elements_render_as_the_blocks_and_only_while_the_section_is_on() {
		$this->needs_elementor( true );
		$set = array(
			'xp_url'   => 'https://bookings.zenchef.test/results?rid=1&pax={n}',
			'xp_email' => 'evenements@brasserie-lumiere.test',
		);
		$this->connect( $set );
		$manager  = \Elementor\Plugin::$instance->widgets_manager;
		$controls = static function ( $element ) {
			\Elementor\Plugin::$instance->controls_manager->delete_stack( $element );
			return $element->get_controls();
		};
		foreach ( array( 'livia-reserver', 'livia-convives' ) as $type ) {
			$element = $manager->get_widget_types( $type );
			$this->assertSame( array( 'livia' ), $element->get_categories(), $type );
			$this->assertArrayNotHasKey( 'restaurant', $controls( $element ), 'one restaurant: nothing to choose' );
		}
		$this->connect_second( $set );
		foreach ( array( 'livia-reserver', 'livia-convives' ) as $type ) {
			$this->assertArrayHasKey( 'restaurant', $controls( $manager->get_widget_types( $type ) ), $type );
		}
		$print  = function ( $type, $settings ) {
			ob_start();
			$this->booking_element( $type, $settings )->print_element();
			return ob_get_clean();
		};
		$button = array(
			'label'      => 'Réserver pour le déjeuner',
			'target'     => 'event',
			'restaurant' => 'r2',
		);
		$this->assertStringNotContainsString( 'livia-reserver', $print( 'livia-reserver', $button ), 'the section is off' );
		$this->assertStringNotContainsString( 'data-livia-convives', $print( 'livia-convives', array( 'variant' => 'band' ) ) );

		Livia_Service::save_settings( array( 'xp_on' => true ) );
		$html = $print( 'livia-reserver', $button );
		$this->assertStringContainsString( '<div class="elementor-button-wrapper"><a class="elementor-button elementor-button-link elementor-size-sm livia-reserver" href="#reserver" data-livia-restaurant="r2" data-livia-event>Réserver pour le déjeuner</a></div>', $html, 'in the style of the kit\'s buttons' );
		$this->assertStringContainsString(
			do_shortcode( '[livia_convives variant="band" restaurant="r1"]' ),
			$print(
				'livia-convives',
				array(
					'variant'    => 'band',
					'restaurant' => 'r1',
				)
			)
		);
	}
}
