<?php
/**
 * The booking experience tab, inside the settings form.
 *
 * @package Livia
 *
 * @var array    $view         See Livia_Admin::view().
 * @var array    $livia_rs     Restaurants, in the order of the restaurant choice.
 * @var int      $livia_count  Number of restaurants.
 * @var callable $livia_switch See settings.php.
 * @var callable $livia_select See settings.php.
 */

defined( 'ABSPATH' ) || exit;

$livia_several      = $livia_count > 1;
$livia_fields_named = array(
	'xp_url'       => __( 'the address of your booking tool', 'livia' ),
	'xp_email'     => __( 'the restaurant\'s e-mail address', 'livia' ),
	'xp_event_url' => __( 'the address of your private hire tool', 'livia' ),
);
$livia_shortcodes   = array(
	'livia_reserver' => __( 'Book button', 'livia' ),
	'livia_convives' => __( 'Guest selector', 'livia' ),
);
$livia_ok_menu      = array(
	'added'   => __( '“Book a table” was added at the end of your main menu.', 'livia' ),
	'present' => __( 'Your main menu already has a link to #reserver.', 'livia' ),
	'none'    => __( 'Your theme shows no menu at a location yet: create one in Appearance > Menus, then try again.', 'livia' ),
	'error'   => __( 'WordPress could not add the link. Please try again.', 'livia' ),
	'block'   => __( 'The header of your block theme has no menu the plugin can add to. Open Appearance > Editor > Navigation and add a custom link to #reserver, labelled “Book a table”.', 'livia' ),
);

/**
 * Prints the message of a refused field.
 *
 * @param array  $restaurant See Livia_Admin::experience_view().
 * @param string $name       Field.
 */
$livia_error = static function ( $restaurant, $name ) {
	if ( isset( $restaurant['xp_errors'][ $name ] ) ) {
		?>
		<div class="lv-strip lv-alone s-err" id="<?php echo esc_attr( 'livia-' . $restaurant['id'] . '-' . $name . '-err' ); ?>"><?php echo Livia_Admin::icon( 'error' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup. ?><span><?php echo esc_html( $restaurant['xp_errors'][ $name ] ); ?></span></div>
		<?php
	}
};

/**
 * The attributes of a field that may be refused.
 *
 * @param array  $restaurant See Livia_Admin::experience_view().
 * @param string $name       Field.
 * @return string Escaped attributes.
 */
$livia_attrs = static function ( $restaurant, $name ) {
	$id = 'livia-' . $restaurant['id'] . '-' . $name;
	return sprintf( 'id="%s" name="%s"', esc_attr( $id ), esc_attr( 'livia[' . $restaurant['id'] . '][' . $name . ']' ) )
		. ( isset( $restaurant['xp_errors'][ $name ] ) ? sprintf( ' aria-invalid="true" aria-describedby="%s-err"', esc_attr( $id ) ) : '' );
};

/**
 * Prints a photo of the media library, with its address for the preview.
 *
 * @param array  $restaurant See Livia_Admin::experience_view().
 * @param string $name       Field.
 */
$livia_photo = static function ( $restaurant, $name ) {
	$url = $restaurant['photos'][ $name ];
	?>
	<div class="lv-media" data-livia-media>
		<span class="lv-thumb"><img src="<?php echo esc_url( $url ); ?>" alt="" <?php echo '' === $url ? 'hidden' : ''; ?>></span>
		<div>
			<button type="button" class="lv-link" data-livia-media-pick><?php esc_html_e( 'Change the image', 'livia' ); ?></button>
			<button type="button" class="lv-link danger" data-livia-media-clear <?php echo '' === $url ? 'hidden' : ''; ?>><?php esc_html_e( 'Remove', 'livia' ); ?></button>
			<div class="lv-desc"><?php esc_html_e( 'Chosen in your media library.', 'livia' ); ?></div>
		</div>
		<input type="hidden" name="<?php echo esc_attr( 'livia[' . $restaurant['id'] . '][' . $name . ']' ); ?>" value="<?php echo esc_attr( $restaurant['settings'][ $name ] ); ?>" data-url="<?php echo esc_url( $url ); ?>">
	</div>
	<?php
};

/**
 * Prints the thresholds of a restaurant.
 *
 * @param array $restaurant See Livia_Admin::experience_view().
 */
$livia_thresholds = static function ( $restaurant ) use ( $livia_error, $livia_attrs ) {
	$x = $restaurant['xp'];
	?>
	<div class="lv-row">
		<span class="lv-lab"><?php esc_html_e( 'Thresholds', 'livia' ); ?></span>
		<div>
			<div class="lv-range">
				<div class="lv-bar" aria-hidden="true"><span class="b1"></span><span class="b2"></span><span class="b3"></span></div>
				<div class="lv-thr">
					<label><?php esc_html_e( 'Group from', 'livia' ); ?> <input class="lv-n" type="number" min="2" max="998" required <?php echo $livia_attrs( $restaurant, 'xp_from' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by $livia_attrs. ?> value="<?php echo esc_attr( $x['xp_from'] ); ?>" data-livia-from> <?php esc_html_e( 'guests', 'livia' ); ?></label>
					<label><?php esc_html_e( 'Event above', 'livia' ); ?> <input class="lv-n" type="number" min="2" max="998" required <?php echo $livia_attrs( $restaurant, 'xp_above' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by $livia_attrs. ?> value="<?php echo esc_attr( $x['xp_above'] ); ?>" data-livia-above> <?php esc_html_e( 'guests', 'livia' ); ?></label>
				</div>
				<?php $livia_error( $restaurant, 'xp_from' ); ?>
				<p class="lv-help"><?php esc_html_e( 'These are your restaurant\'s thresholds. The window shows them to the visitor while choosing.', 'livia' ); ?></p>
			</div>
		</div>
	</div>
	<?php
};

/**
 * Prints a choice among a few values.
 *
 * @param string   $name     Field name.
 * @param string   $current  Value chosen.
 * @param string[] $choices  Label by value.
 * @param string[] $disabled Values not offered now.
 */
$livia_choice = static function ( $name, $current, $choices, $disabled = array() ) {
	?>
	<div class="lv-choice lv-choice-<?php echo count( $choices ); ?>">
		<?php foreach ( $choices as $livia_value => $livia_label ) : ?>
			<label><input type="radio" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $livia_value ); ?>" <?php checked( $current, $livia_value ); ?> <?php disabled( in_array( $livia_value, $disabled, true ) ); ?>><?php echo esc_html( $livia_label ); ?></label>
		<?php endforeach; ?>
	</div>
	<?php
};

$livia_first = reset( $livia_rs );
?>
<input type="hidden" name="action" value="livia_save_experience">
<?php wp_nonce_field( 'livia_save_experience' ); ?>

<?php if ( isset( $livia_n['menu'] ) && isset( $livia_ok_menu[ $livia_n['menu'] ] ) ) : ?>
	<div class="lv-info" role="status"><?php echo Livia_Admin::icon( 'info' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup. ?><span><?php echo esc_html( $livia_ok_menu[ $livia_n['menu'] ] ); ?></span></div>
<?php endif; ?>

<?php
// The first restaurant's panel is the one open: another one's refusal would otherwise sit unseen in a hidden panel.
foreach ( $livia_rs as $livia_r ) :
	?>
	<?php if ( $livia_r !== $livia_first && ! empty( $livia_r['xp_errors'] ) ) : ?>
		<div class="lv-strip lv-alone s-err" role="alert"><?php echo Livia_Admin::icon( 'error' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup. ?><span>
			<?php
			/* translators: %s: restaurant name. */
			echo esc_html( sprintf( __( '%s: a value was refused and the saved one is kept. Open this restaurant below to correct it.', 'livia' ), $livia_r['name'] ) );
			?>
		</span></div>
	<?php endif; ?>
<?php endforeach; ?>

<section class="lv-card" aria-labelledby="livia-xp">
	<div class="lv-card-h">
		<div>
			<h2 class="lv-h2" id="livia-xp"><?php esc_html_e( 'Booking experience', 'livia' ); ?></h2>
			<p class="lv-desc"><?php esc_html_e( 'Your “Book” buttons open a window that asks for the number of guests, then leads the visitor to the right path.', 'livia' ); ?></p>
		</div>
		<?php if ( $view['xp_on'] ) : ?>
			<span class="lv-tag t-ok"><i></i><?php echo esc_html_x( 'On', 'booking experience status', 'livia' ); ?></span>
		<?php else : ?>
			<span class="lv-tag t-wait"><i></i><?php echo esc_html_x( 'Off', 'booking experience status', 'livia' ); ?></span>
		<?php endif; ?>
	</div>
	<div class="lv-rows">
		<div class="lv-row">
			<span class="lv-lab"><?php esc_html_e( 'On the site', 'livia' ); ?></span>
			<div>
				<?php $livia_switch( 'livia_xp_on', $view['xp_switch'], _x( 'On', 'booking experience', 'livia' ), _x( 'Off', 'booking experience', 'livia' ) ); ?>
				<p class="lv-help"><?php esc_html_e( 'Off, the Livia buttons and selector leave your pages; the site bubble and the group page stay.', 'livia' ); ?></p>
				<?php if ( $view['xp_missing'] ) : ?>
					<div class="lv-strip lv-alone s-warn" role="status"><?php echo Livia_Admin::icon( 'info' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup. ?><span>
						<?php
						$livia_missing = array();
						foreach ( $view['xp_missing'] as $livia_id => $livia_fields ) {
							$livia_list      = implode( ', ', array_map( static fn( $field ) => $livia_fields_named[ $field ], $livia_fields ) );
							$livia_missing[] = $livia_several ? sprintf( /* translators: 1: restaurant name, 2: fields to fill. */ __( '%1$s: %2$s', 'livia' ), $livia_rs[ $livia_id ]['name'], $livia_list ) : $livia_list;
						}
						/* translators: %s: fields to fill, by restaurant when there are several. */
						echo esc_html( sprintf( __( 'The experience stays off until you fill in %s.', 'livia' ), implode( '; ', $livia_missing ) ) );
						?>
					</span></div>
				<?php endif; ?>
			</div>
		</div>
		<?php
		if ( ! $livia_several ) {
			$livia_thresholds( $livia_first );
		}
		?>
	</div>
</section>

<?php if ( $livia_several ) : ?>
	<section class="lv-card" aria-labelledby="livia-xp-pick">
		<div class="lv-card-h">
			<div>
				<h2 class="lv-h2" id="livia-xp-pick"><?php esc_html_e( 'Restaurant choice', 'livia' ); ?></h2>
				<p class="lv-desc"><?php esc_html_e( 'With several connected restaurants, the window asks where and how many, on the same screen.', 'livia' ); ?></p>
			</div>
			<span class="lv-tag t-ok"><i></i>
				<?php
				/* translators: %d: number of restaurants. */
				echo esc_html( sprintf( _n( '%d restaurant', '%d restaurants', $livia_count, 'livia' ), $livia_count ) );
				?>
			</span>
		</div>
		<div class="lv-rows">
			<div class="lv-row">
				<span class="lv-lab"><?php esc_html_e( 'Restaurants offered', 'livia' ); ?></span>
				<div>
					<ol class="lv-rlist" data-livia-order>
						<?php foreach ( $livia_rs as $livia_id => $livia_r ) : ?>
							<li draggable="true">
								<input type="hidden" name="livia_xp_order[]" value="<?php echo esc_attr( $livia_id ); ?>">
								<span class="lv-grip" aria-hidden="true">⋮⋮</span>
								<?php $livia_photo( $livia_r, 'xp_photo' ); ?>
								<span>
									<b><?php echo esc_html( $livia_r['name'] ); ?></b>
									<input class="lv-in" type="text" maxlength="200" name="livia[<?php echo esc_attr( $livia_id ); ?>][xp_phrase]" value="<?php echo esc_attr( $livia_r['xp']['xp_phrase'] ); ?>" aria-label="<?php /* translators: %s: restaurant name (in French, already preceded by "de" and contracted). */ echo esc_attr( sprintf( __( 'Sentence of %s', 'livia' ), Livia_Rules::of_name( $livia_r['name'], determine_locale() ) ) ); ?>">
								</span>
								<span class="lv-move">
									<button type="button" class="lv-link" data-livia-move="-1" aria-label="<?php /* translators: %s: restaurant name. */ echo esc_attr( sprintf( __( 'Move %s up', 'livia' ), $livia_r['name'] ) ); ?>"><?php echo Livia_Admin::icon( 'up' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup. ?></button>
									<button type="button" class="lv-link" data-livia-move="1" aria-label="<?php /* translators: %s: restaurant name. */ echo esc_attr( sprintf( __( 'Move %s down', 'livia' ), $livia_r['name'] ) ); ?>"><?php echo Livia_Admin::icon( 'down' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup. ?></button>
								</span>
							</li>
						<?php endforeach; ?>
					</ol>
					<p class="lv-help"><?php esc_html_e( 'A photo and a sentence per restaurant; drag, or use the arrows, to change the order. A button placed in a page can also open the window on a restaurant already chosen.', 'livia' ); ?></p>
				</div>
			</div>
		</div>
	</section>
<?php endif; ?>

<section class="lv-card" aria-labelledby="livia-xp-paths">
	<div class="lv-card-h">
		<div>
			<h2 class="lv-h2" id="livia-xp-paths"><?php esc_html_e( 'The three paths', 'livia' ); ?></h2>
			<p class="lv-desc"><?php echo esc_html( $livia_several ? __( 'Each restaurant has its thresholds, its tools and its photos.', 'livia' ) : __( 'A photo and a destination per path. The visitor sees the photo change with the number of guests.', 'livia' ) ); ?></p>
		</div>
	</div>
	<?php if ( $livia_several ) : ?>
		<span class="lv-seg lv-seg-big" role="group" aria-label="<?php esc_attr_e( 'Restaurant', 'livia' ); ?>">
			<?php foreach ( $livia_rs as $livia_id => $livia_r ) : ?>
				<button type="button" data-livia-panel="<?php echo esc_attr( $livia_id ); ?>" aria-pressed="<?php echo $livia_r === $livia_first ? 'true' : 'false'; ?>"><?php echo esc_html( $livia_r['name'] ); ?></button>
			<?php endforeach; ?>
		</span>
	<?php endif; ?>
	<?php foreach ( $livia_rs as $livia_id => $livia_r ) : ?>
		<?php
		$livia_x    = $livia_r['xp'];
		$livia_base = 'livia[' . $livia_id . ']';
		?>
		<div class="lv-rows" data-livia-restaurant="<?php echo esc_attr( $livia_id ); ?>" data-address="<?php echo esc_attr( $view['home_host'] . '/' ); ?>" data-page="<?php echo esc_url( $livia_r['page_url'] ); ?>" data-tiers="
		<?php
		echo esc_attr(
			wp_json_encode(
				array(
					/* translators: %d: last number of guests below the group threshold. */
					'table' => __( '1 to %d guests', 'livia' ),
					/* translators: 1: group threshold, 2: event threshold, in guests. */ 'group' => __( '%1$d to %2$d guests', 'livia' ),
					/* translators: %d: first number of guests above the event threshold. */ 'event' => __( '%d guests and more', 'livia' ),
				)
			)
		);
		?>
													" <?php echo $livia_r === $livia_first ? '' : 'hidden'; ?>>
			<input type="hidden" name="<?php echo esc_attr( $livia_base ); ?>[shown]" value="1">
			<?php if ( $livia_several ) : ?>
				<?php $livia_thresholds( $livia_r ); ?>
				<div class="lv-row">
					<span class="lv-lab"><?php esc_html_e( 'Shortcodes', 'livia' ); ?></span>
					<div>
						<div class="lv-wlist">
							<?php foreach ( $livia_shortcodes as $livia_tag => $livia_label ) : ?>
								<?php $livia_code = '[' . $livia_tag . ' restaurant="' . $livia_id . '"]'; ?>
								<div>
									<b><?php echo esc_html( $livia_label ); ?></b><span class="lv-code"><?php echo esc_html( $livia_code ); ?></span>
									<button type="button" class="lv-link lv-copy" data-livia-copy="<?php echo esc_attr( $livia_code ); ?>" data-done="<?php esc_attr_e( 'Copied', 'livia' ); ?>"><?php echo Livia_Admin::icon( 'copy' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup. ?><span><?php esc_html_e( 'Copy the shortcode', 'livia' ); ?></span></button>
								</div>
							<?php endforeach; ?>
						</div>
						<p class="lv-help"><?php esc_html_e( 'For the other page builders and the classic editor: they open the window on this restaurant.', 'livia' ); ?></p>
					</div>
				</div>
			<?php endif; ?>

			<div class="lv-pal">
				<div class="lv-pal-h"><i class="b1"></i><b data-livia-tier="table"><?php /* translators: %d: last number of guests below the group threshold. */ echo esc_html( sprintf( __( '1 to %d guests', 'livia' ), $livia_r['settings']['xp_from'] - 1 ) ); ?></b><span><?php esc_html_e( 'Your booking tool', 'livia' ); ?></span></div>
				<div class="lv-pal-b">
					<div class="lv-row">
						<label class="lv-lab" for="livia-<?php echo esc_attr( $livia_id ); ?>-xp_tool"><?php esc_html_e( 'Your tool', 'livia' ); ?></label>
						<div>
							<select class="lv-in lv-short" id="livia-<?php echo esc_attr( $livia_id ); ?>-xp_tool" name="<?php echo esc_attr( $livia_base ); ?>[xp_tool]">
								<?php
								$livia_tools = array(
									'zenchef'     => 'Zenchef',
									'thefork'     => 'TheFork',
									'sevenrooms'  => 'SevenRooms',
									'guestonline' => 'Guestonline',
									'tourbiz'     => 'Tourbiz',
									'other'       => __( 'Another tool', 'livia' ),
									'page'        => __( 'A page of your site', 'livia' ),
								);
								foreach ( $livia_tools as $livia_value => $livia_label ) :
									?>
									<option value="<?php echo esc_attr( $livia_value ); ?>" <?php selected( $livia_x['xp_tool'], $livia_value ); ?>><?php echo esc_html( $livia_label ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="lv-help"><?php esc_html_e( 'What you already use for your tables: Zenchef, TheFork, SevenRooms, Guestonline, Tourbiz, another tool, or “A page of your site”, when booking happens in one of your pages.', 'livia' ); ?></p>
						</div>
					</div>
					<div class="lv-row">
						<label class="lv-lab" for="livia-<?php echo esc_attr( $livia_id ); ?>-xp_url"><?php esc_html_e( 'Booking address', 'livia' ); ?></label>
						<div>
							<input class="lv-in lv-mono lv-wide<?php echo isset( $livia_r['xp_errors']['xp_url'] ) ? ' s-err-in' : ''; ?>" type="text" <?php echo $livia_attrs( $livia_r, 'xp_url' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by $livia_attrs. ?> value="<?php echo esc_attr( $livia_x['xp_url'] ); ?>" placeholder="https://bookings.zenchef.com/results?rid=…&amp;pax={n}" spellcheck="false">
							<?php $livia_error( $livia_r, 'xp_url' ); ?>
							<p class="lv-help">
								<?php
								printf(
									/* translators: 1: Zenchef address, 2: address of a page with an anchor, 3: {n}. */
									esc_html__( 'With Zenchef: %1$s. With a page of your site: its address, with the anchor of the booking module if needed, for instance %2$s. With another tool: the address of its module, given in your space there. %3$s is replaced by the number of guests chosen, when the address allows it.', 'livia' ),
									'<span class="lv-code">https://bookings.zenchef.com/results?rid=<b>' . esc_html__( 'your identifier', 'livia' ) . '</b>&amp;pax={n}</span>',
									'<span class="lv-code">' . esc_html( home_url( '/' ) ) . 'nos-tables/#reserver</span>',
									'<span class="lv-code">{n}</span>'
								);
								?>
							</p>
						</div>
					</div>
					<div class="lv-row">
						<span class="lv-lab"><?php esc_html_e( 'Opening', 'livia' ); ?></span>
						<div>
							<?php
							$livia_choice(
								$livia_base . '[xp_open]',
								$livia_x['xp_open'],
								array(
									'window' => __( 'In the window', 'livia' ),
									'tab'    => __( 'In a new tab', 'livia' ),
									'page'   => __( 'In the current page', 'livia' ),
								)
							);
							?>
							<p class="lv-help"><?php esc_html_e( 'In the window, an “Open in a tab” link is always offered; some tools refuse to show inside it. In the current page, the visitor is taken to the address, without a window: the natural choice for a page of your site.', 'livia' ); ?></p>
						</div>
					</div>
					<div class="lv-row">
						<span class="lv-lab"><?php esc_html_e( 'Photo', 'livia' ); ?></span>
						<div>
							<?php $livia_photo( $livia_r, 'xp_table_photo' ); ?>
							<input class="lv-in lv-gap" type="text" maxlength="200" name="<?php echo esc_attr( $livia_base ); ?>[xp_table_caption]" value="<?php echo esc_attr( $livia_x['xp_table_caption'] ); ?>" aria-label="<?php esc_attr_e( 'Caption', 'livia' ); ?>">
							<p class="lv-help"><?php esc_html_e( 'Caption shown on the photo.', 'livia' ); ?></p>
						</div>
					</div>
				</div>
			</div>

			<div class="lv-pal">
				<div class="lv-pal-h"><i class="b2"></i><b data-livia-tier="group"><?php /* translators: 1: group threshold, 2: event threshold, in guests. */ echo esc_html( sprintf( __( '%1$d to %2$d guests', 'livia' ), $livia_r['settings']['xp_from'], $livia_r['settings']['xp_above'] ) ); ?></b><span><?php esc_html_e( 'A conversation with Livia', 'livia' ); ?></span></div>
				<div class="lv-pal-b">
					<div class="lv-row">
						<span class="lv-lab"><?php esc_html_e( 'Destination', 'livia' ); ?></span>
						<div>
							<?php
							$livia_choice(
								$livia_base . '[xp_group_dest]',
								'' === $livia_r['page_url'] ? 'window' : $livia_x['xp_group_dest'],
								array(
									'window' => __( 'The conversation, in the window', 'livia' ),
									'page'   => __( 'Your group booking page', 'livia' ),
								),
								'' === $livia_r['page_url'] ? array( 'page' ) : array()
							);
							?>
							<p class="lv-help"><?php esc_html_e( 'In the window, the conversation opens at once, like a booking tool. Without a published group page (Booking page tab turned off), only the window is offered.', 'livia' ); ?></p>
						</div>
					</div>
					<div class="lv-row">
						<label class="lv-lab" for="livia-<?php echo esc_attr( $livia_id ); ?>-xp_group_widget"><?php esc_html_e( 'Widget', 'livia' ); ?></label>
						<div data-livia-field>
							<?php if ( $livia_r['conversations'] ) : ?>
								<?php $livia_select( 'livia-' . $livia_id . '-xp_group_widget', $livia_base . '[xp_group_widget]', $livia_r['conversations'], $livia_r['group_widget'] ); ?>
								<p class="lv-help"><?php esc_html_e( 'Among your “Embedded conversation” widgets. The number of guests chosen reaches the conversation: Livia does not ask for it again.', 'livia' ); ?></p>
							<?php else : ?>
								<p class="lv-help lv-help-row"><?php esc_html_e( 'This restaurant has no embedded conversation: create one in your Livia space, or choose the group page.', 'livia' ); ?></p>
							<?php endif; ?>
						</div>
					</div>
					<div class="lv-row">
						<label class="lv-lab" for="livia-<?php echo esc_attr( $livia_id ); ?>-xp_group_title"><?php esc_html_e( 'Title', 'livia' ); ?></label>
						<div>
							<input class="lv-in lv-wide" type="text" maxlength="200" id="livia-<?php echo esc_attr( $livia_id ); ?>-xp_group_title" name="<?php echo esc_attr( $livia_base ); ?>[xp_group_title]" value="<?php echo esc_attr( $livia_x['xp_group_title'] ); ?>" placeholder="<?php esc_attr_e( 'Let us prepare your group table', 'livia' ); ?>">
							<p class="lv-help"><?php esc_html_e( 'Shown above the conversation, so that the visitor knows at once what to do. Left empty: “Let us prepare your group table”.', 'livia' ); ?></p>
						</div>
					</div>
					<div class="lv-row">
						<span class="lv-lab"><?php esc_html_e( 'Photo', 'livia' ); ?></span>
						<div>
							<?php $livia_photo( $livia_r, 'xp_group_photo' ); ?>
							<input class="lv-in lv-gap" type="text" maxlength="200" name="<?php echo esc_attr( $livia_base ); ?>[xp_group_caption]" value="<?php echo esc_attr( $livia_x['xp_group_caption'] ); ?>" aria-label="<?php esc_attr_e( 'Caption', 'livia' ); ?>">
						</div>
					</div>
				</div>
			</div>

			<div class="lv-pal">
				<div class="lv-pal-h"><i class="b3"></i><b data-livia-tier="event"><?php /* translators: %d: first number of guests above the event threshold. */ echo esc_html( sprintf( __( '%d guests and more', 'livia' ), $livia_r['settings']['xp_above'] + 1 ) ); ?></b><span><?php esc_html_e( 'Private hire or event', 'livia' ); ?></span></div>
				<div class="lv-pal-b">
					<div class="lv-row">
						<span class="lv-lab"><?php esc_html_e( 'Destination', 'livia' ); ?></span>
						<div>
							<?php
							$livia_choice(
								$livia_base . '[xp_event_dest]',
								$livia_x['xp_event_dest'],
								array(
									'contact' => __( 'Contact the restaurant', 'livia' ),
									'tool'    => __( 'Your private hire tool', 'livia' ),
								)
							);
							?>
							<p class="lv-help"><?php esc_html_e( 'With a private hire tool, give its address: it opens like the tool of small tables.', 'livia' ); ?></p>
						</div>
					</div>
					<div class="lv-row" data-livia-when="tool">
						<label class="lv-lab" for="livia-<?php echo esc_attr( $livia_id ); ?>-xp_event_url"><?php esc_html_e( 'Tool address', 'livia' ); ?></label>
						<div>
							<input class="lv-in lv-mono lv-wide<?php echo isset( $livia_r['xp_errors']['xp_event_url'] ) ? ' s-err-in' : ''; ?>" type="text" <?php echo $livia_attrs( $livia_r, 'xp_event_url' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by $livia_attrs. ?> value="<?php echo esc_attr( $livia_x['xp_event_url'] ); ?>" spellcheck="false">
							<?php $livia_error( $livia_r, 'xp_event_url' ); ?>
						</div>
					</div>
					<div class="lv-row" data-livia-when="contact">
						<label class="lv-lab" for="livia-<?php echo esc_attr( $livia_id ); ?>-xp_email"><?php esc_html_e( 'E-mail', 'livia' ); ?></label>
						<div>
							<input class="lv-in<?php echo isset( $livia_r['xp_errors']['xp_email'] ) ? ' s-err-in' : ''; ?>" type="email" <?php echo $livia_attrs( $livia_r, 'xp_email' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by $livia_attrs. ?> value="<?php echo esc_attr( $livia_x['xp_email'] ); ?>">
							<?php $livia_error( $livia_r, 'xp_email' ); ?>
							<p class="lv-help"><?php esc_html_e( 'The window shows a “Write to the restaurant” button.', 'livia' ); ?></p>
						</div>
					</div>
					<div class="lv-row" data-livia-when="contact">
						<label class="lv-lab" for="livia-<?php echo esc_attr( $livia_id ); ?>-xp_phone"><?php esc_html_e( 'Phone', 'livia' ); ?></label>
						<div>
							<input class="lv-in" type="tel" maxlength="40" id="livia-<?php echo esc_attr( $livia_id ); ?>-xp_phone" name="<?php echo esc_attr( $livia_base ); ?>[xp_phone]" value="<?php echo esc_attr( $livia_x['xp_phone'] ); ?>" placeholder="01 23 45 67 89">
							<p class="lv-help"><?php esc_html_e( 'Optional. Shown under the e-mail, as an information.', 'livia' ); ?></p>
						</div>
					</div>
					<div class="lv-row" data-livia-when="contact">
						<label class="lv-lab" for="livia-<?php echo esc_attr( $livia_id ); ?>-xp_address"><?php esc_html_e( 'Address', 'livia' ); ?></label>
						<div>
							<input class="lv-in lv-wide" type="text" maxlength="200" id="livia-<?php echo esc_attr( $livia_id ); ?>-xp_address" name="<?php echo esc_attr( $livia_base ); ?>[xp_address]" value="<?php echo esc_attr( $livia_x['xp_address'] ); ?>">
							<p class="lv-help"><?php esc_html_e( 'Optional. To drop by.', 'livia' ); ?></p>
						</div>
					</div>
					<div class="lv-row">
						<span class="lv-lab"><?php esc_html_e( 'Photo', 'livia' ); ?></span>
						<div>
							<?php $livia_photo( $livia_r, 'xp_event_photo' ); ?>
							<input class="lv-in lv-gap" type="text" maxlength="200" name="<?php echo esc_attr( $livia_base ); ?>[xp_event_caption]" value="<?php echo esc_attr( $livia_x['xp_event_caption'] ); ?>" aria-label="<?php esc_attr_e( 'Caption', 'livia' ); ?>">
						</div>
					</div>
				</div>
			</div>
		</div>
	<?php endforeach; ?>
</section>

<section class="lv-card" aria-labelledby="livia-xp-where">
	<div class="lv-card-h">
		<div>
			<h2 class="lv-h2" id="livia-xp-where"><?php esc_html_e( 'Where to open the window', 'livia' ); ?></h2>
			<p class="lv-desc"><?php esc_html_e( 'The buttons and the selector take the style of your theme; the window, its colours and its font.', 'livia' ); ?></p>
		</div>
	</div>
	<div class="lv-rows">
		<div class="lv-row">
			<span class="lv-lab"><?php esc_html_e( 'Automatic', 'livia' ); ?></span>
			<div>
				<label class="lv-chk"><input type="checkbox" name="livia_xp_bar" value="1" <?php checked( $view['xp_bar'] ); ?>><span><?php esc_html_e( 'On phones, a “Book a table” bar fixed at the bottom of the screen', 'livia' ); ?></span></label>
				<p class="lv-help"><?php esc_html_e( 'On phones, the bubble gives way to the “Book a table” bar on the pages where it shows.', 'livia' ); ?></p>
				<?php if ( 'block' === $view['menu_state'] ) : ?>
					<div class="lv-strip lv-alone s-warn"><?php echo Livia_Admin::icon( 'info' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup. ?><span><?php echo esc_html( $livia_ok_menu['block'] ); ?></span></div>
				<?php else : ?>
					<p class="lv-acts-row">
						<button type="submit" form="livia-add-menu" class="lv-btn b-s" <?php disabled( 'ready' !== $view['menu_state'] ); ?>><?php echo Livia_Admin::icon( 'menu' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup. ?><?php esc_html_e( 'Add “Book a table” to the main menu', 'livia' ); ?></button>
					</p>
					<p class="lv-help">
						<?php
						if ( 'present' === $view['menu_state'] ) {
							esc_html_e( 'Your main menu already has a link to #reserver.', 'livia' );
						} elseif ( 'none' === $view['menu_state'] ) {
							echo esc_html( $livia_ok_menu['none'] );
						} else {
							printf(
								/* translators: %s: #reserver. */
								esc_html__( 'Adds in one click a link to %s at the end of your main menu. You then move or remove it like any link of the menu.', 'livia' ),
								'<span class="lv-code">#reserver</span>'
							);
						}
						?>
					</p>
				<?php endif; ?>
			</div>
		</div>
		<div class="lv-row">
			<span class="lv-lab"><?php esc_html_e( 'In your pages', 'livia' ); ?></span>
			<div>
				<div class="lv-place">
					<div><em><?php echo Livia_Admin::icon( 'button' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup. ?></em><span><b><?php esc_html_e( '“Book button” block', 'livia' ); ?></b><p><?php esc_html_e( 'In the block editor or in Elementor (Livia elements), anywhere. It takes the style of your theme\'s buttons; its label changes button by button (“Book for lunch”).', 'livia' ); ?></p></span></div>
					<div><em><?php echo Livia_Admin::icon( 'guests' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup. ?></em><span><b><?php esc_html_e( '“How many of you?” block', 'livia' ); ?></b><p><?php esc_html_e( 'The guest selector, placed in the page: as a band over a photo, or as a card.', 'livia' ); ?></p></span></div>
					<div><em><?php echo Livia_Admin::icon( 'link' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup. ?></em><span><b><?php esc_html_e( 'Your existing buttons', 'livia' ); ?></b><p>
						<?php
						printf(
							/* translators: 1: #reserver, 2: livia-reserver. */
							esc_html__( 'Any link to %1$s, or carrying the class %2$s, opens the window, without touching the theme.', 'livia' ),
							'<span class="lv-code">#reserver</span>',
							'<span class="lv-code">livia-reserver</span>'
						);
						?>
					</p></span></div>
					<div><em><?php echo Livia_Admin::icon( 'code' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup. ?></em><span><b><?php esc_html_e( 'Shortcodes', 'livia' ); ?></b><p>
						<?php
						printf(
							/* translators: 1: first shortcode, 2: second shortcode. */
							esc_html__( '%1$s and %2$s, for the other page builders and the classic editor.', 'livia' ),
							'<span class="lv-code">[livia_reserver]</span>',
							'<span class="lv-code">[livia_convives]</span>'
						);
						?>
					</p></span></div>
				</div>
			</div>
		</div>
	</div>
</section>
<script type="application/json" data-livia-xp-preview><?php echo wp_json_encode( $view['xp_preview'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE ); ?></script>
