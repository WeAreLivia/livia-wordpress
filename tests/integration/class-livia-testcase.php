<?php
/**
 * Base of the integration tests: connected restaurants and a fake Livia service.
 *
 * @package Livia
 */

abstract class Livia_TestCase extends WP_UnitTestCase {

	const KEY  = 'lvwp_abcdefghijklmnopqrstuvwxyz';
	const KEY2 = 'lvwp_terrasseterrasseterrasse12';

	/**
	 * Answers of the fake service, by path: [ HTTP status, body ] or a callable returning them.
	 *
	 * @var array
	 */
	protected $answers = array();

	/**
	 * Addresses the plugin called.
	 *
	 * @var string[]
	 */
	protected $calls = array();

	/**
	 * Arguments of each call, in the same order.
	 *
	 * @var array[]
	 */
	protected $args = array();

	public function set_up() {
		parent::set_up();
		$this->answers = array();
		$this->calls   = array();
		$this->args    = array();
		add_filter( 'pre_http_request', array( $this, 'fake_livia' ), 10, 3 );
		$this->set_permalink_structure( '/%postname%/' );
	}

	public function tear_down() {
		remove_filter( 'pre_http_request', array( $this, 'fake_livia' ), 10 );
		parent::tear_down();
	}

	public function fake_livia( $preempt, $args, $url ) {
		$this->calls[] = $url;
		$this->args[]  = $args;
		$path          = wp_parse_url( $url, PHP_URL_PATH );
		if ( ! isset( $this->answers[ $path ] ) ) {
			return new WP_Error( 'http_request_failed', 'Livia is down' );
		}
		list( $code, $body ) = is_callable( $this->answers[ $path ] ) ? call_user_func( $this->answers[ $path ] ) : $this->answers[ $path ];
		return array(
			'headers'  => array(),
			'body'     => is_string( $body ) ? $body : wp_json_encode( $body ),
			'response' => array(
				'code'    => $code,
				'message' => '',
			),
			'cookies'  => array(),
		);
	}

	/**
	 * Connects one restaurant, `r1`, Brasserie Lumière.
	 *
	 * @param array $restaurant Its settings, see Livia_Service::RESTAURANT.
	 * @param array $settings   Settings of the whole site.
	 */
	protected function connect( $restaurant = array(), $settings = array() ) {
		update_option( 'livia_settings', array_merge( array( 'next_id' => 2 ), $settings ) );
		update_option( 'livia_restaurants', array( 'r1' => array_merge( array( 'key' => self::KEY ), $restaurant ) ) );
		update_option(
			'livia_connections',
			array(
				'r1' => array(
					'state'        => 'ok',
					'restaurant'   => array(
						'id'   => 'rst_1',
						'name' => 'Brasserie Lumière',
					),
					'pageWidgetId' => 'wdg_page',
					'widgets'      => array(
						array(
							'id'         => 'wdg_page',
							'name'       => 'Page de réservation',
							'format'     => 'inline',
							'fullscreen' => false,
						),
						array(
							'id'         => 'wdg_site',
							'name'       => 'Pages du site',
							'format'     => 'bubble',
							'fullscreen' => false,
						),
						array(
							'id'         => 'wdg_other',
							'name'       => 'Privatisation',
							'format'     => 'bubble',
							'fullscreen' => false,
						),
					),
				),
			)
		);
	}

	/**
	 * Connects a second restaurant after the first, `r2`, La Terrasse Lumière.
	 *
	 * @param array $restaurant Its settings, see Livia_Service::RESTAURANT.
	 */
	protected function connect_second( $restaurant = array() ) {
		$all       = get_option( 'livia_restaurants' );
		$all['r2'] = array_merge( array( 'key' => self::KEY2 ), $restaurant );
		update_option( 'livia_restaurants', $all );
		Livia_Service::save_settings( array( 'next_id' => 3 ) );
		$connections       = get_option( 'livia_connections' );
		$connections['r2'] = array(
			'state'        => 'ok',
			'restaurant'   => array(
				'id'   => 'rst_2',
				'name' => 'La Terrasse Lumière',
			),
			'pageWidgetId' => 'wdg_t_page',
			'widgets'      => array(
				array(
					'id'         => 'wdg_t_page',
					'name'       => 'Page de groupe',
					'format'     => 'inline',
					'fullscreen' => false,
				),
				array(
					'id'         => 'wdg_t_site',
					'name'       => 'La Terrasse, site',
					'format'     => 'bubble',
					'fullscreen' => false,
				),
				array(
					'id'         => 'wdg_t_button',
					'name'       => 'Réserver pour un groupe',
					'format'     => 'button',
					'fullscreen' => false,
				),
			),
		);
		update_option( 'livia_connections', $connections );
	}

	/**
	 * Forgets the group pages kept for this request: each visit is its own request outside tests.
	 */
	protected function new_request() {
		$memo = new ReflectionProperty( Livia_Service::class, 'pages' );
		$memo->setAccessible( true );
		$memo->setValue( null, array() );
	}

	/**
	 * A tab of the settings screen, as printed, for the current user or else a new administrator.
	 */
	protected function screen( $tab ) {
		if ( ! get_current_user_id() ) {
			$user = self::factory()->user->create( array( 'role' => 'administrator' ) );
			if ( is_multisite() ) {
				add_user_to_blog( get_current_blog_id(), $user, 'administrator' );
			}
			wp_set_current_user( $user );
		}
		$_GET = array(
			'page' => 'livia',
			'tab'  => $tab,
		);
		ob_start();
		Livia_Admin::render();
		return ob_get_clean();
	}

	protected function page_answer( $html = '<div class="livia-rp">Livia</div>' ) {
		return array(
			200,
			array(
				'html'         => $html,
				'styles'       => array( LIVIA_SERVICE_URL . '/wordpress/v1/assets/page.css?v=1', 'https://elsewhere.test/evil.css' ),
				'scripts'      => array( LIVIA_SERVICE_URL . '/livia-chat.standalone.js' ),
				'title'        => 'Réservation de groupe',
				'version'      => 'v1',
				'cacheSeconds' => 900,
			),
		);
	}
}
