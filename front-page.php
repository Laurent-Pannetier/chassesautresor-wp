<?php
/**
 * Template Name: Accueil Plein Ecran
 * Description: Page d\'accueil minimaliste avec image plein écran.
 */

defined('ABSPATH') || exit;

$image_url = wp_get_attachment_image_url(8810, 'full');
if (function_exists('imagify_get_webp_url')) {
    $image_url = imagify_get_webp_url($image_url);
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class('accueil-fullscreen'); ?>>
<?php wp_body_open(); ?>
<div class="accueil-bg" style="background-image:url('<?php echo esc_url($image_url); ?>');">
    <a class="login-icon" href="<?php echo esc_url(home_url('/mon-compte/')); ?>">
        <i class="fas fa-user-circle" aria-hidden="true"></i>
    </a>
</div>
<?php wp_footer(); ?>
</body>
</html>
