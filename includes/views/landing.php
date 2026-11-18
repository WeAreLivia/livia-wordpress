<?php
/**
 * A group page in landing mode: the page Livia renders on its own, with the site's head and footer hooks (audience
 * measure, cookie banner, SEO tags) but neither the theme's header nor its footer, the booking tools' scripts left out.
 * Its own viewport and title give way to wp_head's: a block theme's template loader hooks both there, and a theme with
 * title-tag support prints the title there.
 *
 * @package Livia
 */

defined( 'ABSPATH' ) || exit;
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<?php if ( false === has_action( 'wp_head', '_block_template_viewport_meta_tag' ) ) : ?>
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php endif; ?>
<?php if ( ! current_theme_supports( 'title-tag' ) && false === has_action( 'wp_head', '_block_template_render_title_tag' ) ) : ?>
<title><?php echo esc_html( wp_get_document_title() ); ?></title>
<?php endif; ?>
<?php Livia_Group_Page::print_without_booking_tools( 'wp_head' ); ?>
</head>
<body <?php body_class( 'livia-landing' ); ?>>
<?php
Livia_Group_Page::print_without_booking_tools( 'wp_body_open' );
while ( have_posts() ) {
	the_post();
	the_content();
}
Livia_Group_Page::print_without_booking_tools( 'wp_footer' );
?>
</body>
</html>
