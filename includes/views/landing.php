<?php
/**
 * A group page in landing mode: the page Livia renders on its own, with the site's head and footer hooks (audience
 * measure, cookie banner, SEO tags) but neither the theme's header nor its footer, the booking tools' scripts left out.
 *
 * @package Livia
 */

defined( 'ABSPATH' ) || exit;
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php if ( ! current_theme_supports( 'title-tag' ) ) : ?>
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
