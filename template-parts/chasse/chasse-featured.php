<?php
/**
 * Template part for displaying a featured chasse card.
 *
 * @package ChassesAuTresor
 */

defined('ABSPATH') || exit;

if (empty($args['chasse_id'])) {
    return;
}

$chasse_id       = (int) $args['chasse_id'];
$highlight_label = $args['highlight_label'] ?? '';

$title     = get_the_title($chasse_id);
$permalink = get_permalink($chasse_id);

$description = get_field('chasse_principale_description', $chasse_id);
$excerpt     = wp_trim_words(wp_strip_all_tags($description), 25, '…');

$image = get_the_post_thumbnail_url($chasse_id, 'large');
if (!$image) {
    $image = get_the_post_thumbnail_url($chasse_id, 'full');
}
?>

<div class="carte carte-featured">
    <?php if (!empty($highlight_label)) : ?>
        <span class="carte-featured__label"><?php echo esc_html($highlight_label); ?></span>
    <?php endif; ?>

    <?php if ($image) : ?>
        <img class="carte-featured__image" src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr($title); ?>">
    <?php endif; ?>

    <div class="carte-featured__content">
        <h3 class="carte-featured__title">
            <a href="<?php echo esc_url($permalink); ?>"><?php echo esc_html($title); ?></a>
        </h3>
        <p class="carte-featured__excerpt"><?php echo esc_html($excerpt); ?></p>
        <a href="<?php echo esc_url($permalink); ?>" class="bouton-secondaire carte-featured__cta"><?php echo esc_html__('Découvrir', 'chassesautresor-com'); ?></a>
    </div>
</div>
