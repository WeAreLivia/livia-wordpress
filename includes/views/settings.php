<?php
/**
 * The Livia settings screen.
 *
 * @package Livia
 *
 * @var array $view See Livia_Admin::view().
 */

defined( 'ABSPATH' ) || exit;

$livia_n     = $view['notice'];
$livia_rs    = $view['restaurants'];
$livia_count = count( $livia_rs );
$livia_tab   = $view['tab'];
$livia_trash = (int) EMPTY_TRASH_DAYS;

/**
 * Prints a widget menu: a native select under the label the mockup draws.
 *
 * @param string  $id       Field id.
 * @param string  $name     Field name.
 * @param array[] $widgets  Widgets offered.
 * @param string  $selected Selected widget id, the first widget when none of them.
 * @param string  $none     Label of a first choice with no widget (empty value), empty for no such choice.
 */
$livia_select = static function ( $id, $name, $widgets, $selected, $none = '' ) {
	$current = '' !== $none ? array(
		'name'   => $none,
		'format' => '',
	) : ( $widgets ? $widgets[0] : null );
	$format  = static function ( $widget ) {
		$label = Livia_Admin::format_label( $widget['format'] );
		return '' !== $label ? '· ' . $label : '';
	};
	foreach ( $widgets as $widget ) {
		if ( (string) $widget['id'] === $selected ) {
			$current = $widget;
		}
	}
	?>
	<div class="lv-sel">
		<span class="lv-sel-label" aria-hidden="true"><b><?php echo esc_html( $current['name'] ?? '' ); ?></b><span><?php echo esc_html( $format( $current ) ); ?></span></span>
		<?php echo Livia_Admin::icon( 'chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup. ?>
		<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" data-livia-widget>
			<?php if ( '' !== $none ) : ?>
				<option value="" <?php selected( isset( $current['id'] ), false ); ?> data-name="<?php echo esc_attr( $none ); ?>" data-format="" data-description=""><?php echo esc_html( $none ); ?></option>
			<?php endif; ?>
			<?php foreach ( $widgets as $widget ) : ?>
				<option value="<?php echo esc_attr( $widget['id'] ); ?>" <?php selected( $current['id'] ?? '', $widget['id'] ); ?>
					data-name="<?php echo esc_attr( $widget['name'] ?? '' ); ?>"
					data-format="<?php echo esc_attr( $format( $widget ) ); ?>"
					data-description="<?php echo esc_attr( Livia_Admin::description( $widget ) ); ?>"><?php echo esc_html( trim( ( $widget['name'] ?? '' ) . ' ' . $format( $widget ) ) ); ?></option>
			<?php endforeach; ?>
		</select>
	</div>
	<?php
};

/**
 * Prints a switch: a checkbox drawn as a toggle, its label following its state.
 *
 * @param string $name    Field name.
 * @param bool   $checked Whether it is on.
 * @param string $on      Label when on.
 * @param string $off     Label when off.
 */
$livia_switch = static function ( $name, $checked, $on, $off ) {
	?>
	<label class="lv-sw"><input type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( $checked ); ?>><i aria-hidden="true"></i><span class="lv-sw-on"><?php echo esc_html( $on ); ?></span><span class="lv-sw-off"><?php echo esc_html( $off ); ?></span></label>
	<?php
};

/**
 * Prints the head of a restaurant's card: initial, name and the end of its key.
 *
 * @param array $restaurant See Livia_Admin::view().
 */
$livia_head = static function ( $restaurant ) {
	?>
	<span class="lv-ini" aria-hidden="true"><?php echo esc_html( mb_substr( $restaurant['name'], 0, 1 ) ); ?></span>
	<div>
		<h2 class="lv-h2"><?php echo esc_html( $restaurant['name'] ); ?></h2>
		<p class="lv-desc">
			<?php
			/* translators: %s: masked key. */
			printf( esc_html__( 'Key %s', 'livia' ), '<span class="lv-mono">' . esc_html( $restaurant['mask'] ) . '</span>' );
			?>
		</p>
	</div>
	<?php
};

/**
 * Prints the form that connects a key, for a new restaurant or in the place of a connected one.
 *
 * @param string $restaurant_id Restaurant id, empty for a new restaurant.
 * @param string $error         Why the key was refused, empty when it was not.
 * @param string $value         Key shown in the field.
 * @param string $cancel        Address that closes the form, empty when it cannot be closed.
 */
$livia_key_form = static function ( $restaurant_id, $error, $value, $cancel ) use ( $livia_tab ) {
	$field = 'livia-key-' . ( '' !== $restaurant_id ? $restaurant_id : 'new' );
	?>
	<form class="lv-row" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="livia_connect">
		<input type="hidden" name="tab" value="<?php echo esc_attr( $livia_tab ); ?>">
		<input type="hidden" name="livia_restaurant" value="<?php echo esc_attr( $restaurant_id ); ?>">
		<?php wp_nonce_field( 'livia_connect' ); ?>
		<label class="lv-lab" for="<?php echo esc_attr( $field ); ?>"><?php esc_html_e( 'Restaurant key', 'livia' ); ?></label>
		<div>
			<div class="lv-keyrow">
				<div>
					<?php if ( '' !== $error ) : ?>
						<div class="lv-att">
							<input id="<?php echo esc_attr( $field ); ?>" name="livia_key" class="lv-in lv-mono s-err-in" type="text" value="<?php echo esc_attr( $value ); ?>" placeholder="lvwp_..." aria-invalid="true" aria-describedby="<?php echo esc_attr( $field ); ?>-err" autocomplete="off" spellcheck="false" required>
							<div id="<?php echo esc_attr( $field ); ?>-err" class="lv-strip s-err"><?php echo Livia_Admin::icon( 'error' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup. ?><span><?php echo esc_html( $error ); ?></span></div>
						</div>
					<?php else : ?>
						<input id="<?php echo esc_attr( $field ); ?>" name="livia_key" class="lv-in lv-mono" type="text" value="" placeholder="lvwp_..." autocomplete="off" spellcheck="false" required>
					<?php endif; ?>
				</div>
				<button type="submit" class="lv-btn b-p"><?php esc_html_e( 'Connect', 'livia' ); ?></button>
			</div>
			<p class="lv-help"><?php esc_html_e( 'You will find it in your Livia space, Connectors section, on the WordPress connector. It is only used to find your restaurant and its public widgets: it gives access to no data.', 'livia' ); ?></p>
			<?php if ( '' !== $cancel ) : ?>
				<p class="lv-acts"><a href="<?php echo esc_url( $cancel ); ?>"><?php esc_html_e( 'Cancel', 'livia' ); ?></a></p>
			<?php endif; ?>
		</div>
	</form>
	<?php
};
?>
<div class="lv">
	<header class="lv-band">
		<span class="lv-tile"><img src="<?php echo esc_url( LIVIA_URL . 'assets/img/livia-picto.svg' ); ?>" alt=""></span>
		<img class="lv-word" src="<?php echo esc_url( LIVIA_URL . 'assets/img/livia-wordmark-white.svg' ); ?>" alt="Livia">
		<span class="lv-sep"></span>
		<span class="lv-sub"><?php esc_html_e( 'Bookings and widgets', 'livia' ); ?></span>
		<div class="lv-band-r">
			<span class="lv-chip<?php echo $livia_count ? '' : ' off'; ?>"><i></i>
				<?php
				if ( 1 === $livia_count ) {
					echo esc_html( reset( $livia_rs )['name'] );
				} elseif ( $livia_count ) {
					/* translators: %d: number of restaurants. */
					echo esc_html( sprintf( _n( '%d restaurant', '%d restaurants', $livia_count, 'livia' ), $livia_count ) );
				} else {
					esc_html_e( 'No restaurant connected', 'livia' );
				}
				?>
			</span>
			<a class="lv-ext" href="<?php echo esc_url( Livia_Admin::LIVIA_ADMIN_URL ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'My Livia space', 'livia' ); ?> <?php echo Livia_Admin::icon( 'external' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup. ?></a>
		</div>
	</header>
	<div class="lv-wrap">
		<h1 class="lv-h1"><?php esc_html_e( 'Livia on your site', 'livia' ); ?></h1>
		<p class="lv-lead"><?php echo esc_html( _n( 'The widget, the group booking page and the booking experience of your restaurant, without touching any code.', 'The widgets, the group booking pages and the booking experience of your restaurants, without touching any code.', max( 1, $livia_count ), 'livia' ) ); ?></p>
		<hr class="wp-header-end">

		<?php if ( ! $livia_count ) : ?>
			<section class="lv-card" aria-labelledby="livia-restaurant">
				<div class="lv-card-h">
					<div>
						<h2 class="lv-h2" id="livia-restaurant"><?php esc_html_e( 'Your restaurant', 'livia' ); ?></h2>
						<p class="lv-desc"><?php esc_html_e( 'The key links this plugin to your restaurant in Livia.', 'livia' ); ?></p>
					</div>
					<span class="lv-tag t-todo"><i></i><?php esc_html_e( 'To do', 'livia' ); ?></span>
				</div>
				<div class="lv-rows"><?php $livia_key_form( '', $view['key_error'], $view['key_value'], '' ); ?></div>
			</section>
			<nav class="lv-tabs" aria-label="<?php esc_attr_e( 'Livia settings', 'livia' ); ?>">
				<span class="lv-tab" aria-disabled="true"><?php echo Livia_Admin::icon( 'lock' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup. ?><?php esc_html_e( 'Site widget', 'livia' ); ?></span>
				<span class="lv-tab" aria-disabled="true"><?php echo Livia_Admin::icon( 'lock' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup. ?><?php esc_html_e( 'Booking page', 'livia' ); ?></span>
				<span class="lv-tab" aria-disabled="true"><?php echo Livia_Admin::icon( 'lock' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup. ?><?php esc_html_e( 'Booking experience', 'livia' ); ?></span>
				<span class="lv-tab" aria-disabled="true"><?php echo Livia_Admin::icon( 'lock' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup. ?><?php esc_html_e( 'Advanced settings', 'livia' ); ?></span>
			</nav>
			<p class="lv-locked"><?php echo Livia_Admin::icon( 'lock' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup. ?><?php esc_html_e( 'The settings open once the restaurant is connected.', 'livia' ); ?></p>
		<?php else : ?>
			<?php $livia_add_url = add_query_arg( 'add', 1, Livia_Admin::url( array( 'tab' => $livia_tab ) ) ); ?>
			<section class="lv-card lv-restos" aria-labelledby="livia-restaurants">
				<div class="lv-restos-h">
					<b id="livia-restaurants"><?php esc_html_e( 'Connected restaurants', 'livia' ); ?></b>
					<a href="<?php echo esc_url( $livia_add_url ); ?>"><?php echo Livia_Admin::icon( 'plus' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup. ?><?php esc_html_e( 'Add a restaurant', 'livia' ); ?></a>
				</div>
				<?php foreach ( $livia_rs as $livia_id => $livia_r ) : ?>
					<?php $livia_change_url = add_query_arg( 'change-key', $livia_id, Livia_Admin::url( array( 'tab' => $livia_tab ) ) ); ?>
					<div class="lv-resto-l">
						<span class="lv-ini" aria-hidden="true"><?php echo esc_html( mb_substr( $livia_r['name'], 0, 1 ) ); ?></span>
						<div>
							<b class="lv-resto-name"><?php echo esc_html( $livia_r['name'] ); ?></b>
							<div class="lv-desc">
								<?php
								/* translators: %s: masked key. */
								printf( esc_html__( 'Key %s', 'livia' ), '<span class="lv-mono">' . esc_html( $livia_r['mask'] ) . '</span>' );
								?>
							</div>
						</div>
						<?php if ( 'ok' === $livia_r['connection']['state'] ) : ?>
							<span class="lv-tag t-ok"><i></i><?php esc_html_e( 'Connected', 'livia' ); ?></span>
						<?php elseif ( 'connector_disabled' === $livia_r['connection']['state'] ) : ?>
							<span class="lv-tag t-todo"><i></i><?php esc_html_e( 'Connector turned off', 'livia' ); ?></span>
						<?php elseif ( 'account_inactive' === $livia_r['connection']['state'] ) : ?>
							<span class="lv-tag t-todo"><i></i><?php esc_html_e( 'Livia account inactive', 'livia' ); ?></span>
						<?php else : ?>
							<span class="lv-tag t-todo"><i></i><?php esc_html_e( 'Key refused', 'livia' ); ?></span>
						<?php endif; ?>
						<div class="lv-acts">
							<a href="<?php echo esc_url( $livia_change_url ); ?>"><?php esc_html_e( 'Change key', 'livia' ); ?></a>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-livia-confirm="<?php esc_attr_e( 'Remove this restaurant? Its group booking page goes back to draft and its bubble leaves the site.', 'livia' ); ?>">
								<input type="hidden" name="action" value="livia_remove">
								<input type="hidden" name="tab" value="<?php echo esc_attr( $livia_tab ); ?>">
								<input type="hidden" name="livia_restaurant" value="<?php echo esc_attr( $livia_id ); ?>">
								<?php wp_nonce_field( 'livia_remove_' . $livia_id ); ?>
								<button type="submit" class="lv-link danger"><?php esc_html_e( 'Remove', 'livia' ); ?></button>
							</form>
						</div>
					</div>
					<?php if ( $livia_r['show_key'] ) : ?>
						<div class="lv-resto-x"><?php $livia_key_form( $livia_id, $livia_r['key_error'], isset( $livia_n['key_value'] ) ? $livia_n['key_value'] : '', Livia_Admin::url( array( 'tab' => $livia_tab ) ) ); ?></div>
					<?php elseif ( '' !== $livia_r['key_error'] ) : ?>
						<div class="lv-resto-x"><div class="lv-strip lv-alone s-err"><?php echo Livia_Admin::icon( 'error' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup. ?><span><?php echo esc_html( $livia_r['key_error'] ); ?></span></div></div>
					<?php endif; ?>
				<?php endforeach; ?>
				<?php if ( $view['add'] ) : ?>
					<div class="lv-resto-x"><?php $livia_key_form( '', $view['key_error'], $view['key_value'], Livia_Admin::url( array( 'tab' => $livia_tab ) ) ); ?></div>
				<?php endif; ?>
				<?php if ( $view['stale'] ) : ?>
					<p class="lv-help lv-resto-x"><?php esc_html_e( 'Livia is not answering right now: what you see dates from the last connection.', 'livia' ); ?></p>
				<?php endif; ?>
			</section>

			<nav class="lv-tabs" aria-label="<?php esc_attr_e( 'Livia settings', 'livia' ); ?>">
				<?php
				$livia_tabs = array(
					'widget'     => array( __( 'Site widget', 'livia' ), $view['widget_on'], _x( 'turned off', 'site widget tab', 'livia' ) ),
					'page'       => array( __( 'Booking page', 'livia' ), $view['online'] > 0, _x( 'turned off', 'booking page tab', 'livia' ) ),
					'experience' => array( __( 'Booking experience', 'livia' ), $view['xp_on'], _x( 'turned off', 'booking experience tab', 'livia' ) ),
					'advanced'   => array( __( 'Advanced settings', 'livia' ), true, '' ),
				);
				foreach ( $livia_tabs as $livia_key => $livia_t ) :
					?>
					<a class="lv-tab<?php echo $livia_tab === $livia_key ? ' on' : ''; ?>" href="<?php echo esc_url( Livia_Admin::url( array( 'tab' => $livia_key ) ) ); ?>"<?php echo $livia_tab === $livia_key ? ' aria-current="page"' : ''; ?>>
						<i class="st<?php echo $livia_t[1] ? '' : ' off'; ?>" aria-hidden="true"></i><?php echo esc_html( $livia_t[0] ); ?>
						<?php if ( ! $livia_t[1] ) : ?>
							<span class="off-l"><?php echo esc_html( $livia_t[2] ); ?></span>
						<?php endif; ?>
					</a>
				<?php endforeach; ?>
				<?php if ( 'advanced' !== $livia_tab ) : ?>
					<button type="button" class="lv-btn b-s lv-open" data-livia-open><?php echo Livia_Admin::icon( 'eye' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup. ?><?php esc_html_e( 'See the preview', 'livia' ); ?></button>
				<?php endif; ?>
			</nav>

			<?php if ( ! empty( $livia_n['saved'] ) ) : ?>
				<div class="lv-ban" role="status">
					<span class="lv-ban-ico"><?php echo Livia_Admin::icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup. ?></span>
					<div><b><?php esc_html_e( 'Changes saved', 'livia' ); ?></b></div>
				</div>
			<?php endif; ?>
			<?php if ( $view['purge_due'] ) : ?>
				<div class="lv-info"><?php echo Livia_Admin::icon( 'info' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup. ?><span><?php esc_html_e( 'Pages your cache tools already hold do not reflect the latest Livia changes yet: clear their cache to apply them.', 'livia' ); ?> <a href="<?php echo esc_url( Livia_Admin::url( array( 'tab' => 'advanced' ) ) ); ?>"><?php esc_html_e( 'Advanced settings', 'livia' ); ?></a></span></div>
			<?php endif; ?>
			<?php if ( isset( $livia_n['purged'] ) ) : ?>
				<div class="lv-ban" role="status">
					<span class="lv-ban-ico"><?php echo Livia_Admin::icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup. ?></span>
					<div><b>
						<?php
						echo esc_html(
							$livia_n['purged']
								/* translators: %s: names of the cache tools, separated by commas. */
								? sprintf( __( 'Cache cleared: %s.', 'livia' ), implode( ', ', $livia_n['purged'] ) )
								: __( 'No cache to clear.', 'livia' )
						);
						?>
					</b></div>
				</div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-livia-tab="<?php echo esc_attr( $livia_tab ); ?>">
				<input type="hidden" name="tab" value="<?php echo esc_attr( $livia_tab ); ?>">
				<?php if ( 'widget' === $livia_tab ) : ?>
					<input type="hidden" name="action" value="livia_save_widget">
					<?php wp_nonce_field( 'livia_save_widget' ); ?>
					<section class="lv-card" aria-labelledby="livia-site">
						<div class="lv-card-h">
							<div>
								<h2 class="lv-h2" id="livia-site"><?php echo esc_html( _n( 'Site bubble', 'Site widgets', $livia_count, 'livia' ) ); ?></h2>
								<p class="lv-desc"><?php echo esc_html( _n( 'A chat bubble on the pages of your site, without pasting code in the theme.', 'One bubble per restaurant, each on its own pages. A page shows only one bubble.', $livia_count, 'livia' ) ); ?></p>
							</div>
							<?php if ( $view['widget_on'] ) : ?>
								<span class="lv-tag t-ok"><i></i><?php esc_html_e( 'Turned on', 'livia' ); ?></span>
							<?php else : ?>
								<span class="lv-tag t-wait"><i></i><?php esc_html_e( 'Turned off', 'livia' ); ?></span>
							<?php endif; ?>
						</div>
						<div class="lv-rows">
							<div class="lv-row">
								<span class="lv-lab"><?php esc_html_e( 'On the site', 'livia' ); ?></span>
								<div>
									<?php $livia_switch( 'livia_site_on', $view['site_on'], __( 'Turned on', 'livia' ), __( 'Turned off', 'livia' ) ); ?>
									<p class="lv-help"><?php esc_html_e( 'Turned off, no bubble shows. The widgets placed in your pages stay: they are part of their content.', 'livia' ); ?></p>
								</div>
							</div>
						</div>
					</section>
					<?php if ( $view['several'] ) : ?>
						<div class="lv-info"><?php echo Livia_Admin::icon( 'info' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup. ?><span><?php esc_html_e( 'A page a restaurant chose under “Only” shows that restaurant\'s bubble: it appears greyed out for the other restaurants, with its name.', 'livia' ); ?></span></div>
					<?php endif; ?>
					<?php foreach ( $livia_rs as $livia_id => $livia_r ) : ?>
						<?php $livia_s = $livia_r['settings']; ?>
						<section class="lv-card" data-livia-restaurant="<?php echo esc_attr( $livia_id ); ?>" data-preview="<?php echo esc_url( $livia_r['preview']['widget'] ); ?>" data-address="<?php echo esc_attr( $view['home_host'] . '/' ); ?>">
							<input type="hidden" name="livia[<?php echo esc_attr( $livia_id ); ?>][shown]" value="1">
							<div class="lv-rc-h">
								<?php $livia_head( $livia_r ); ?>
								<?php if ( null !== $livia_r['count'] ) : ?>
									<span class="lv-tag t-ok"><i></i>
										<?php
										/* translators: %d: number of pages. */
										echo esc_html( sprintf( _n( 'Shown on %d page', 'Shown on %d pages', $livia_r['count'], 'livia' ), $livia_r['count'] ) );
										?>
									</span>
								<?php endif; ?>
								<?php $livia_switch( 'livia[' . $livia_id . '][bubble_on]', $livia_s['bubble_on'], __( 'Bubble on', 'livia' ), __( 'Bubble off', 'livia' ) ); ?>
							</div>
							<div class="lv-rows">
								<div class="lv-row">
									<label class="lv-lab" for="livia-<?php echo esc_attr( $livia_id ); ?>-bubble"><?php esc_html_e( 'On desktop', 'livia' ); ?></label>
									<div data-livia-field>
										<?php if ( $livia_r['bubbles'] ) : ?>
											<?php
											// Nothing saved, or a widget Livia no longer offers: no bubble shows as chosen.
											$livia_none = '' === $livia_s['site_widget'] ? __( 'No bubble chosen', 'livia' ) : '';
											$livia_select( 'livia-' . $livia_id . '-bubble', 'livia[' . $livia_id . '][site_widget]', $livia_r['bubbles'], $livia_s['site_widget'], $livia_r['missing'] ? __( 'Chosen widget not found', 'livia' ) : $livia_none );
											?>
											<?php if ( $livia_r['missing'] ) : ?>
												<div class="lv-strip s-err"><?php echo Livia_Admin::icon( 'error' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup. ?><span>
													<?php
													$livia_why = array(
														'off'     => __( 'The widget chosen for desktop no longer exists in Livia. Choose another one before you turn this restaurant\'s bubble on, then save.', 'livia' ),
														'desktop' => __( 'The widget chosen for desktop no longer exists in Livia: this restaurant\'s bubble no longer shows on desktop. Choose another one and save.', 'livia' ),
														'both'    => __( 'The widget chosen for desktop no longer exists in Livia: this restaurant\'s bubble no longer shows on desktop or on phone. Choose another one and save.', 'livia' ),
													);
													echo esc_html( $livia_why[ $livia_r['missing'] ] );
													?>
												</span></div>
											<?php endif; ?>
											<p class="lv-help"><?php esc_html_e( 'Among the bubbles of this restaurant.', 'livia' ); ?></p>
										<?php else : ?>
											<p class="lv-help lv-help-row"><?php esc_html_e( 'This restaurant has no bubble widget: create one in your Livia space.', 'livia' ); ?></p>
										<?php endif; ?>
									</div>
								</div>
								<?php if ( $livia_r['bubbles'] ) : ?>
									<div class="lv-row">
										<label class="lv-lab" for="livia-<?php echo esc_attr( $livia_id ); ?>-phone"><?php esc_html_e( 'On phone', 'livia' ); ?></label>
										<div>
											<?php $livia_select( 'livia-' . $livia_id . '-phone', 'livia[' . $livia_id . '][phone_widget]', $livia_r['bubbles'], $livia_s['phone_widget'], __( 'The same widget', 'livia' ) ); ?>
											<p class="lv-help"><?php esc_html_e( 'A more discreet bubble on phone: choose a compact widget here. Its margins are the ones set in Livia.', 'livia' ); ?>
													<?php if ( $view['xp_on'] && $view['xp_bar'] ) : ?>
														<?php esc_html_e( 'On the pages where the “Book a table” bar shows, the bubble gives way to the bar.', 'livia' ); ?>
													<?php endif; ?>
												</p>
										</div>
									</div>
								<?php endif; ?>
								<div class="lv-row">
									<span class="lv-lab" id="livia-<?php echo esc_attr( $livia_id ); ?>-rule"><?php esc_html_e( 'Show on', 'livia' ); ?></span>
									<div>
										<div class="lv-choice" role="radiogroup" aria-labelledby="livia-<?php echo esc_attr( $livia_id ); ?>-rule">
											<?php
											$livia_rules = array(
												'all'    => __( 'All pages', 'livia' ),
												'except' => __( 'All, except', 'livia' ),
												'only'   => __( 'Only', 'livia' ),
											);
											foreach ( $livia_rules as $livia_rule => $livia_label ) :
												?>
												<label><input type="radio" name="livia[<?php echo esc_attr( $livia_id ); ?>][site_rule]" value="<?php echo esc_attr( $livia_rule ); ?>" <?php checked( $livia_s['site_rule'], $livia_rule ); ?>><?php echo esc_html( $livia_label ); ?></label>
											<?php endforeach; ?>
										</div>
										<?php if ( $view['site_pages'] ) : ?>
											<div class="lv-pages" data-livia-pages<?php echo 'all' === $livia_s['site_rule'] ? ' hidden' : ''; ?>>
												<?php foreach ( $view['site_pages'] as $livia_page_id ) : ?>
													<?php if ( isset( $livia_r['taken'][ $livia_page_id ] ) ) : ?>
														<label class="taken"><input type="checkbox" disabled><?php echo esc_html( get_the_title( $livia_page_id ) ); ?><em><?php echo esc_html( '· ' . $livia_r['taken'][ $livia_page_id ] ); ?></em></label>
													<?php else : ?>
														<label><input type="checkbox" name="livia[<?php echo esc_attr( $livia_id ); ?>][site_pages][]" value="<?php echo esc_attr( $livia_page_id ); ?>" <?php checked( in_array( $livia_page_id, array_map( 'intval', $livia_s['site_pages'] ), true ) ); ?>><?php echo esc_html( get_the_title( $livia_page_id ) ); ?></label>
													<?php endif; ?>
												<?php endforeach; ?>
											</div>
										<?php endif; ?>
										<p class="lv-help"><?php esc_html_e( 'The group booking page never shows this bubble: it already has its conversation. Each page can also choose another widget, or none, in the Livia panel of the editor.', 'livia' ); ?></p>
									</div>
								</div>
								<div class="lv-row">
									<span class="lv-lab"><?php esc_html_e( 'In your pages', 'livia' ); ?></span>
									<div>
										<?php if ( $livia_r['in_content'] ) : ?>
											<div class="lv-wlist">
												<?php foreach ( $livia_r['in_content'] as $livia_widget ) : ?>
													<div>
														<b><?php echo esc_html( $livia_widget['name'] ?? '' ); ?></b><span><?php echo esc_html( Livia_Admin::format_label( $livia_widget['format'] ) ); ?></span>
														<button type="button" class="lv-link lv-copy" data-livia-copy="<?php echo esc_attr( '[livia_widget widget="' . $livia_widget['id'] . '"]' ); ?>" data-done="<?php esc_attr_e( 'Copied', 'livia' ); ?>"><?php echo Livia_Admin::icon( 'copy' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup. ?><span><?php esc_html_e( 'Copy the shortcode', 'livia' ); ?></span></button>
													</div>
												<?php endforeach; ?>
											</div>
										<?php endif; ?>
										<p class="lv-help"><?php esc_html_e( 'The buttons and embedded conversations of this restaurant, offered as they are in Elementor, the block editor and the shortcode. Nothing to set here.', 'livia' ); ?></p>
									</div>
								</div>
							</div>
						</section>
					<?php endforeach; ?>
				<?php elseif ( 'experience' === $livia_tab ) : ?>
					<?php include LIVIA_DIR . 'includes/views/experience.php'; ?>
				<?php elseif ( 'advanced' === $livia_tab ) : ?>
					<input type="hidden" name="action" value="livia_save_advanced">
					<?php wp_nonce_field( 'livia_save_advanced' ); ?>
					<?php
					$livia_rule_names  = array(
						'delay'   => __( 'Script delay', 'livia' ),
						'defer'   => __( 'Script defer', 'livia' ),
						'minify'  => __( 'Minification', 'livia' ),
						'combine' => __( 'Combination', 'livia' ),
						'page'    => __( 'Group booking page cache', 'livia' ),
						'css'     => __( 'Stylesheet optimisation', 'livia' ),
						'lazy'    => __( 'Image lazy loading on the group booking page', 'livia' ),
					);
					$livia_rule_states = array(
						'auto'   => __( 'Applied automatically', 'livia' ),
						'copy'   => __( 'To copy into its settings', 'livia' ),
						'none'   => __( 'Not done by this tool', 'livia' ),
						'markup' => __( 'Left to the attributes Livia puts on its images', 'livia' ),
					);
					?>
					<section class="lv-card" aria-labelledby="livia-cache">
						<div class="lv-card-h">
							<div>
								<h2 class="lv-h2" id="livia-cache"><?php esc_html_e( 'Cache and optimisation', 'livia' ); ?></h2>
								<p class="lv-desc">
									<?php
									/* translators: %s: names of the cache and optimisation tools, separated by commas. */
									echo esc_html( sprintf( __( 'Supported tools: %s.', 'livia' ), implode( ', ', array_column( Livia_Cache::TOOLS, 'name' ) ) ) );
									?>
									<?php esc_html_e( 'On the whole site, Livia\'s scripts are excluded from these tools\' delay, defer, minification and combination, and the group booking pages from their page cache. Nothing is written in their settings: it all stops if you deactivate Livia.', 'livia' ); ?></p>
							</div>
							<?php if ( $view['tools'] ) : ?>
								<span class="lv-tag t-ok"><i></i>
									<?php
									/* translators: %d: number of tools. */
									echo esc_html( sprintf( _n( '%d tool detected', '%d tools detected', count( $view['tools'] ), 'livia' ), count( $view['tools'] ) ) );
									?>
								</span>
							<?php else : ?>
								<span class="lv-tag t-wait"><i></i><?php esc_html_e( 'No tool detected', 'livia' ); ?></span>
							<?php endif; ?>
						</div>
						<div class="lv-rows">
							<?php foreach ( $view['tools'] as $livia_tool_id => $livia_tool ) : ?>
								<div class="lv-row">
									<span class="lv-lab"><?php echo esc_html( $livia_tool['name'] ); ?></span>
									<div>
										<div class="lv-wlist">
											<?php foreach ( $livia_tool['rules'] as $livia_rule => $livia_state ) : ?>
												<?php $livia_text = 'lazy' === $livia_rule ? ( $view['lazy'][ $livia_tool_id ]['copy'] ?? '' ) : $view['copy'][ $livia_rule ]; ?>
												<div>
													<b><?php echo esc_html( $livia_rule_names[ $livia_rule ] ); ?></b><span><?php echo esc_html( $livia_rule_states[ $livia_state ] ); ?></span>
													<?php if ( 'copy' === $livia_state && '' !== $livia_text ) : ?>
														<button type="button" class="lv-link lv-copy" data-livia-copy="<?php echo esc_attr( $livia_text ); ?>" data-done="<?php esc_attr_e( 'Copied', 'livia' ); ?>"><?php echo Livia_Admin::icon( 'copy' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup. ?><span><?php esc_html_e( 'Copy the rule', 'livia' ); ?></span></button>
													<?php endif; ?>
												</div>
											<?php endforeach; ?>
										</div>
										<?php if ( in_array( 'copy', $livia_tool['rules'], true ) ) : ?>
											<p class="lv-help">
												<?php
												/* translators: %s: name of the cache tool. */
												echo esc_html( sprintf( __( '%s offers no hook for some of these rules: paste each copied rule into its exclusion settings, one line per entry.', 'livia' ), $livia_tool['name'] ) );
												?>
											</p>
										<?php endif; ?>
										<?php if ( 'copy' === ( $livia_tool['rules']['lazy'] ?? '' ) && isset( $view['lazy'][ $livia_tool_id ] ) ) : ?>
											<p class="lv-help"><?php echo esc_html( $view['lazy'][ $livia_tool_id ]['where'] ); ?></p>
										<?php endif; ?>
									</div>
								</div>
							<?php endforeach; ?>
							<div class="lv-row">
								<span class="lv-lab"><?php esc_html_e( 'Cache', 'livia' ); ?></span>
								<div>
									<button type="submit" form="livia-purge-cache" class="lv-btn b-s" <?php disabled( ! $view['tools'] ); ?>><?php esc_html_e( 'Clear the cache', 'livia' ); ?></button>
									<p class="lv-help"><?php echo esc_html( $view['tools'] ? __( 'Clears the detected tools\' cache so that every page is rebuilt with these rules.', 'livia' ) : __( 'No cache or optimisation tool detected on this site.', 'livia' ) ); ?></p>
								</div>
							</div>
						</div>
					</section>
					<section class="lv-card" aria-labelledby="livia-styles">
						<div class="lv-card-h">
							<div>
								<h2 class="lv-h2" id="livia-styles"><?php esc_html_e( 'Group booking page styles', 'livia' ); ?></h2>
								<p class="lv-desc"><?php esc_html_e( 'For the group booking pages of every restaurant, in both display modes.', 'livia' ); ?></p>
							</div>
						</div>
						<div class="lv-rows">
							<div class="lv-row">
								<span class="lv-lab" id="livia-styles-label"><?php esc_html_e( 'Styles', 'livia' ); ?></span>
								<div>
									<div class="lv-choice lv-choice-2" role="radiogroup" aria-labelledby="livia-styles-label">
										<label><input type="radio" name="livia_page_styles" value="inherit" <?php checked( $view['page_styles'], 'inherit' ); ?>><?php esc_html_e( 'Inherit from the site', 'livia' ); ?></label>
										<label><input type="radio" name="livia_page_styles" value="isolated" <?php checked( $view['page_styles'], 'isolated' ); ?>><?php esc_html_e( 'Isolated', 'livia' ); ?></label>
									</div>
									<p class="lv-help"><?php esc_html_e( 'Inherit from the site: your site\'s styles also apply to the page. Isolated: the page keeps Livia\'s styles, out of reach of your site\'s CSS; the rest of your site, cookie banner included, keeps its own.', 'livia' ); ?></p>
								</div>
							</div>
						</div>
					</section>
				<?php else : ?>
					<input type="hidden" name="action" value="livia_save_page">
					<?php wp_nonce_field( 'livia_save_page' ); ?>
					<section class="lv-card" aria-labelledby="livia-pages">
						<div class="lv-card-h">
							<div>
								<h2 class="lv-h2" id="livia-pages"><?php echo esc_html( _n( 'Group booking page', 'Group booking pages', $livia_count, 'livia' ) ); ?></h2>
								<p class="lv-desc"><?php echo esc_html( _n( 'A real page of your site, in your theme, with the Livia conversation on the right.', 'One page per connected restaurant, each at its own address, set and published separately.', $livia_count, 'livia' ) ); ?></p>
							</div>
							<?php if ( $view['online'] ) : ?>
								<span class="lv-tag t-ok"><i></i>
									<?php
									/* translators: %d: number of pages. */
									echo esc_html( sprintf( _n( '%d page online', '%d pages online', $view['online'], 'livia' ), $view['online'] ) );
									?>
								</span>
							<?php else : ?>
								<span class="lv-tag t-wait"><i></i><?php esc_html_e( 'Not published yet', 'livia' ); ?></span>
							<?php endif; ?>
						</div>
						<p class="lv-help"><?php esc_html_e( 'Turned off, a page goes back to draft: it is no longer visible, and comes back at the same address when you turn it on again.', 'livia' ); ?></p>
					</section>
					<?php foreach ( $livia_rs as $livia_id => $livia_r ) : ?>
						<?php
						$livia_s        = $livia_r['settings'];
						$livia_conflict = $livia_r['conflict'];
						$livia_post     = $livia_conflict && isset( $livia_conflict['post'] ) ? $livia_conflict['post'] : null;
						$livia_trashes  = $livia_post && Livia_Rules::trash_keeps( $livia_post->post_type, $livia_trash, (bool) MEDIA_TRASH );
						$livia_rn       = $livia_r['notice'];
						$livia_field    = 'livia-' . $livia_id;
						?>
						<?php if ( ! empty( $livia_rn['menu_info'] ) ) : ?>
							<div class="lv-info"><?php echo Livia_Admin::icon( 'info' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup. ?><span><?php esc_html_e( 'Add a link to this page in your site\'s menu (Appearance > Menus, or Appearance > Editor for a block theme): the old page, moved to the trash, is no longer in it.', 'livia' ); ?></span></div>
						<?php endif; ?>
						<section class="lv-card" data-livia-restaurant="<?php echo esc_attr( $livia_id ); ?>" data-preview="<?php echo esc_url( $livia_r['preview']['page'] ); ?>" data-address="<?php echo esc_attr( $view['home_host'] . '/' ); ?>" data-home="<?php echo esc_url( home_url( '/' ) ); ?>">
							<input type="hidden" name="livia[<?php echo esc_attr( $livia_id ); ?>][shown]" value="1">
							<div class="lv-rc-h">
								<?php $livia_head( $livia_r ); ?>
								<?php if ( $livia_r['at_home'] ) : ?>
									<span class="lv-tag t-ok"><i></i><?php esc_html_e( 'Online', 'livia' ); ?></span>
								<?php elseif ( $livia_post && 'trash' !== $livia_post->post_status ) : ?>
									<span class="lv-tag t-todo"><i></i><?php esc_html_e( 'Address to free', 'livia' ); ?></span>
								<?php elseif ( $livia_conflict ) : ?>
									<span class="lv-tag t-todo"><i></i><?php esc_html_e( 'Address to change', 'livia' ); ?></span>
								<?php elseif ( $livia_r['page'] && ! $livia_r['online'] ) : ?>
									<span class="lv-tag t-wait"><i></i><?php esc_html_e( 'Draft', 'livia' ); ?></span>
								<?php else : ?>
									<span class="lv-tag t-wait"><i></i><?php esc_html_e( 'Not published yet', 'livia' ); ?></span>
								<?php endif; ?>
								<?php $livia_switch( 'livia[' . $livia_id . '][page_on]', $livia_r['page_on'], __( 'Published', 'livia' ), __( 'Not published', 'livia' ) ); ?>
							</div>
							<div class="lv-rows">
								<div class="lv-row">
									<label class="lv-lab" for="<?php echo esc_attr( $livia_field ); ?>-slug"><?php esc_html_e( 'Address', 'livia' ); ?></label>
									<div>
										<div class="lv-att" data-livia-address>
											<div class="lv-addr">
												<span><?php echo esc_html( home_url( '/' ) ); ?></span>
												<input id="<?php echo esc_attr( $livia_field ); ?>-slug" name="livia[<?php echo esc_attr( $livia_id ); ?>][slug]" class="lv-in <?php echo $livia_conflict || isset( $livia_rn['page_error'] ) ? 's-err-in' : 's-ok-in'; ?>" type="text" value="<?php echo esc_attr( $livia_r['slug'] ); ?>" aria-describedby="<?php echo esc_attr( $livia_field ); ?>-slug-state" <?php echo $livia_conflict ? 'aria-invalid="true"' : ''; ?>>
											</div>
											<?php if ( $livia_conflict || isset( $livia_rn['page_error'] ) ) : ?>
												<div id="<?php echo esc_attr( $livia_field ); ?>-slug-state" class="lv-strip s-err"><?php echo Livia_Admin::icon( 'error' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup. ?><span>
													<?php echo esc_html( $livia_conflict ? $livia_conflict['message'] : $livia_rn['page_error'] ); ?>
													<?php if ( ! empty( $livia_conflict['permalinks'] ) ) : ?>
														<a class="lv-strip-link" href="<?php echo esc_url( admin_url( 'options-permalink.php' ) ); ?>"><?php esc_html_e( 'Open the permalink settings', 'livia' ); ?></a>
													<?php endif; ?>
												</span></div>
											<?php elseif ( $livia_r['at_home'] ) : ?>
												<div id="<?php echo esc_attr( $livia_field ); ?>-slug-state" class="lv-strip s-ok"><?php echo Livia_Admin::icon( 'ok' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup. ?><span><?php esc_html_e( 'Page online at this address.', 'livia' ); ?> <a class="lv-strip-link" href="<?php echo esc_url( get_permalink( $livia_r['page'] ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View the page', 'livia' ); ?></a></span></div>
											<?php else : ?>
												<div id="<?php echo esc_attr( $livia_field ); ?>-slug-state" class="lv-strip s-ok"><?php echo Livia_Admin::icon( 'ok' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup. ?><span><?php echo esc_html( $livia_r['online'] ? __( 'Address available.', 'livia' ) : __( 'Address available. The page goes online when you save.', 'livia' ) ); ?></span></div>
											<?php endif; ?>
										</div>
										<?php if ( $livia_post ) : ?>
											<div class="lv-acts">
												<?php if ( 'trash' === $livia_post->post_status ) : ?>
													<a href="<?php echo esc_url( admin_url( 'edit.php?post_status=trash&post_type=' . $livia_post->post_type ) ); ?>"><?php esc_html_e( 'View the trash', 'livia' ); ?></a>
												<?php else : ?>
													<?php if ( is_post_publicly_viewable( $livia_post ) ) : ?>
														<a href="<?php echo esc_url( get_permalink( $livia_post ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View the page', 'livia' ); ?></a>
													<?php endif; ?>
													<?php if ( get_edit_post_link( $livia_post ) ) : ?>
														<a href="<?php echo esc_url( get_edit_post_link( $livia_post ) ); ?>"><?php esc_html_e( 'Edit the page', 'livia' ); ?></a>
													<?php endif; ?>
													<?php if ( $livia_trashes && current_user_can( 'delete_post', $livia_post->ID ) ) : ?>
														<a class="danger" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=livia_trash&tab=page&livia_restaurant=' . $livia_id . '&post=' . $livia_post->ID ), 'livia_trash_' . $livia_id . '_' . $livia_post->ID ) ); ?>"><?php esc_html_e( 'Move to trash', 'livia' ); ?></a>
													<?php endif; ?>
												<?php endif; ?>
											</div>
											<?php if ( isset( $livia_rn['trash_error'] ) ) : ?>
												<div class="lv-strip lv-alone s-err"><?php echo Livia_Admin::icon( 'error' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup. ?><span><?php echo esc_html( $livia_rn['trash_error'] ); ?></span></div>
											<?php endif; ?>
											<?php if ( 'trash' !== $livia_post->post_status && $livia_trashes ) : ?>
												<p class="lv-help">
													<?php
													echo esc_html(
														sprintf(
															/* translators: %d: number of days the trash keeps a content. */
															_n( 'To keep this address, move the old page to the trash: existing links and search engines will lead to the new page. The trash keeps it %d day.', 'To keep this address, move the old page to the trash: existing links and search engines will lead to the new page. The trash keeps it %d days.', $livia_trash, 'livia' ),
															$livia_trash
														)
													);
													?>
												</p>
											<?php endif; ?>
										<?php endif; ?>
									</div>
								</div>
								<div class="lv-row">
									<label class="lv-lab" for="<?php echo esc_attr( $livia_field ); ?>-title"><?php esc_html_e( 'Page title', 'livia' ); ?></label>
									<div>
										<input id="<?php echo esc_attr( $livia_field ); ?>-title" name="livia[<?php echo esc_attr( $livia_id ); ?>][title]" class="lv-in" type="text" value="<?php echo esc_attr( $livia_r['title'] ); ?>">
										<p class="lv-help"><?php esc_html_e( 'Shown by your theme at the top of the page (inside the theme only), in the browser tab and in search engine results.', 'livia' ); ?></p>
									</div>
								</div>
								<div class="lv-row">
									<label class="lv-lab" for="<?php echo esc_attr( $livia_field ); ?>-headline"><?php esc_html_e( 'Headline', 'livia' ); ?></label>
									<div>
										<input id="<?php echo esc_attr( $livia_field ); ?>-headline" name="livia[<?php echo esc_attr( $livia_id ); ?>][headline]" class="lv-in" type="text" maxlength="<?php echo esc_attr( Livia_Admin::HEADLINE_MAX ); ?>" value="<?php echo esc_attr( $livia_s['headline'] ); ?>" placeholder="<?php echo esc_attr( $livia_r['headline'] ); ?>">
										<p class="lv-help">
											<?php
											echo esc_html(
												sprintf(
													/* translators: %s: default headline. */
													__( 'The title of the page\'s banner. Left empty: “%s”', 'livia' ),
													$livia_r['headline']
												)
											);
											?>
											<?php esc_html_e( 'A landing page shows the title of your Livia booking page instead.', 'livia' ); ?>
										</p>
									</div>
								</div>
								<div class="lv-row">
									<label class="lv-lab" for="<?php echo esc_attr( $livia_field ); ?>-widget"><?php esc_html_e( 'Widget', 'livia' ); ?></label>
									<div data-livia-field data-livia-auto="<?php echo esc_attr( $livia_r['auto_widget'] ); ?>" data-livia-shared="<?php echo esc_attr( $livia_r['shared'] ); ?>">
										<?php if ( $livia_r['page_widgets'] ) : ?>
											<?php $livia_select( $livia_field . '-widget', 'livia[' . $livia_id . '][page_widget]', $livia_r['page_widgets'], $livia_r['page_widget'] ); ?>
										<?php else : ?>
											<p class="lv-help lv-help-row"><?php esc_html_e( 'This restaurant has no embedded conversation: the page shows the widget of your Livia booking page. To choose another one, create an embedded conversation in your Livia space.', 'livia' ); ?></p>
										<?php endif; ?>
										<?php if ( $livia_r['fallback'] ) : ?>
											<div class="lv-strip lv-alone s-warn"><?php echo Livia_Admin::icon( 'info' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup. ?><span><?php esc_html_e( 'The chosen widget no longer exists in Livia: the page shows the widget of your Livia booking page. Choose another widget, then save.', 'livia' ); ?></span></div>
										<?php endif; ?>
										<p class="lv-help"><?php esc_html_e( 'Embedded conversations of this restaurant, created and set in your Livia space; the page uses their settings as they are. Full screen widgets are not offered: they would cover the page.', 'livia' ); ?></p>
										<p class="lv-help" data-livia-follow<?php echo $livia_r['follow'] ? '' : ' hidden'; ?>><?php esc_html_e( 'Conversations from this page are counted together with those of your Livia booking page. To track them separately, create an embedded conversation in your Livia space.', 'livia' ); ?></p>
										<p class="lv-help"><?php esc_html_e( 'On phones, the conversation opens from a button fixed at the bottom of the screen, in your widget\'s colours, whatever its format.', 'livia' ); ?></p>
									</div>
								</div>
								<div class="lv-row">
									<span class="lv-lab" id="<?php echo esc_attr( $livia_field ); ?>-layout"><?php esc_html_e( 'Display mode', 'livia' ); ?></span>
									<div>
										<div class="lv-choice lv-choice-2" role="radiogroup" aria-labelledby="<?php echo esc_attr( $livia_field ); ?>-layout">
											<label><input type="radio" name="livia[<?php echo esc_attr( $livia_id ); ?>][page_layout]" value="theme" <?php checked( $livia_r['page_layout'], 'theme' ); ?>><?php esc_html_e( 'Inside the theme', 'livia' ); ?></label>
											<label><input type="radio" name="livia[<?php echo esc_attr( $livia_id ); ?>][page_layout]" value="landing" <?php checked( $livia_r['page_layout'], 'landing' ); ?>><?php esc_html_e( 'Landing page', 'livia' ); ?></label>
										</div>
										<p class="lv-help"><?php esc_html_e( 'Inside the theme: the page keeps your theme\'s header and footer. Landing page: the page stands alone, like your Livia booking page, with a link back to your site; your site\'s scripts still load, except other booking tools\' widgets.', 'livia' ); ?></p>
									</div>
								</div>
							</div>
						</section>
					<?php endforeach; ?>
				<?php endif; ?>
				<div class="lv-save">
					<span><?php esc_html_e( 'Visible on the site once saved.', 'livia' ); ?></span>
					<button type="submit" class="lv-btn b-p"><?php esc_html_e( 'Save', 'livia' ); ?></button>
				</div>
			</form>
			<?php if ( 'advanced' === $livia_tab ) : ?>
				<form id="livia-purge-cache" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="livia_purge_cache">
					<input type="hidden" name="tab" value="advanced">
					<?php wp_nonce_field( 'livia_purge_cache' ); ?>
				</form>
			<?php endif; ?>
			<?php if ( 'experience' === $livia_tab ) : ?>
				<form id="livia-add-menu" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="livia_add_menu">
					<input type="hidden" name="tab" value="experience">
					<?php wp_nonce_field( 'livia_add_menu' ); ?>
				</form>
			<?php endif; ?>

			<?php if ( 'advanced' !== $livia_tab ) : ?>
			<dialog class="lv-dlg" data-livia-dialog aria-labelledby="livia-preview-title">
				<div class="lv-dlg-h">
					<b id="livia-preview-title"><?php echo esc_html( 'experience' === $livia_tab ? __( 'Preview of the booking experience', 'livia' ) : __( 'Preview', 'livia' ) ); ?></b>
					<?php if ( $view['several'] && 'experience' !== $livia_tab ) : ?>
						<span class="lv-seg" role="group" aria-label="<?php esc_attr_e( 'Restaurant', 'livia' ); ?>">
							<?php foreach ( $livia_rs as $livia_id => $livia_r ) : ?>
								<button type="button" data-livia-pick="<?php echo esc_attr( $livia_id ); ?>"><?php echo esc_html( $livia_r['name'] ); ?></button>
							<?php endforeach; ?>
						</span>
					<?php endif; ?>
					<span class="lv-seg" role="group" aria-label="<?php esc_attr_e( 'Device', 'livia' ); ?>">
						<button type="button" data-livia-device="computer" aria-pressed="true"><?php esc_html_e( 'Computer', 'livia' ); ?></button>
						<button type="button" data-livia-device="phone" aria-pressed="false"><?php esc_html_e( 'Phone', 'livia' ); ?></button>
					</span>
					<button type="button" class="lv-dlg-x" data-livia-close aria-label="<?php esc_attr_e( 'Close', 'livia' ); ?>"><?php echo Livia_Admin::icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup. ?></button>
				</div>
				<div class="lv-pv" data-livia-frame>
					<div class="lv-pv-bar" aria-hidden="true"><i></i><i></i><i></i><span data-livia-address-bar></span></div>
					<div class="lv-pv-page"><iframe title="<?php esc_attr_e( 'Preview', 'livia' ); ?>" tabindex="-1"></iframe></div>
				</div>
				<p class="lv-pv-cap" data-livia-caption><?php echo 'experience' === $livia_tab ? esc_html__( 'The preview follows the settings in progress, even before saving. With several restaurants, the window first shows the choice of the restaurant.', 'livia' ) : ''; ?></p>
				<p class="lv-help" data-livia-empty hidden><?php echo esc_html( 'widget' === $livia_tab ? __( 'No bubble shows with these settings.', 'livia' ) : __( 'No widget can be offered: create one in your Livia space.', 'livia' ) ); ?></p>
				<?php if ( 'widget' === $livia_tab && $view['xp_on'] && $view['xp_bar'] ) : ?>
					<p class="lv-help" data-livia-under-bar hidden><?php esc_html_e( 'On phones, the bubble gives way to the “Book a table” bar on the pages where it shows.', 'livia' ); ?></p>
				<?php endif; ?>
			</dialog>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</div>
