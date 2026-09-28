<?php
/**
 * Compact card format for hunts (CART).
 * Image on top, title, and meta footer.
 *
 * @package chassesautresor
 */

defined('ABSPATH') || exit;

if (!isset($args['chasse_id']) || empty($args['chasse_id'])) {
    return;
}

$chasse_id       = (int) $args['chasse_id'];
$completion_class = $args['completion_class'] ?? '';
$infos           = preparer_infos_affichage_carte_chasse($chasse_id);

if (empty($infos)) {
    return;
}

$badge_tooltip = $infos['badge_tooltip'] ?? '';
$badge_icon_html = $infos['statut_icon'] ?? '';
$badge_label = $infos['statut_label'] ?? ($infos['badge_content'] ?? '');
$has_badge_icon = $badge_icon_html !== '';
$badge_classes = $infos['badge_class'] ?? '';
$image_size = $infos['image_size'] ?? 'medium_large';
$image_ratio = $infos['image_ratio'] ?? '';
$image_ratio_padding = $infos['image_ratio_padding'] ?? '';
$wrapper_style = '';

if ($image_ratio !== '') {
    $style_parts = [];

    if ($image_ratio !== '') {
        $style_parts[] = '--carte-cart-aspect-ratio:' . $image_ratio;
    }

    if ($image_ratio_padding !== '') {
        $style_parts[] = '--carte-cart-padding:' . $image_ratio_padding;
    }

    if (!empty($style_parts)) {
        $wrapper_style = implode(';', $style_parts) . ';';
    }
}
$wrapper_attributes = 'class="carte-cart__image-wrapper"';

if ($wrapper_style !== '') {
    $wrapper_attributes .= ' style="' . esc_attr($wrapper_style) . '"';
}

$image_html = '';

if (!empty($infos['image_id'])) {
    $image_attributes = [
        'class'   => 'carte-cart__image',
        'alt'     => $infos['titre'],
        'loading' => 'lazy',
        'sizes'   => '(max-width: 320px) 100vw, 300px',
    ];

    $image_html = wp_get_attachment_image(
        (int) $infos['image_id'],
        $image_size,
        false,
        $image_attributes
    );
}

if ($image_html === '') {
    $image_html = sprintf(
        '<img src="%1$s" alt="%2$s" class="carte-cart__image" loading="lazy">',
        esc_url($infos['image']),
        esc_attr($infos['titre'])
    );
}

if ($has_badge_icon) {
    $badge_classes = trim($badge_classes . ' badge-statut--responsive');
}

$badge_has_interaction = $has_badge_icon && $badge_tooltip !== '';
$badge_attributes = '';

if ($badge_has_interaction && $badge_tooltip !== '') {
    $tooltip_attr = esc_attr($badge_tooltip);
    $badge_attributes .= ' aria-label="' . $tooltip_attr . '"';
    $badge_attributes .= ' title="' . $tooltip_attr . '"';
    $badge_attributes .= ' data-tooltip="' . $tooltip_attr . '"';
    $badge_attributes .= ' role="img" tabindex="0"';
}

$is_demo = !empty($infos['is_demo']);
$demo_badge = is_array($infos['demo_badge'] ?? null) ? $infos['demo_badge'] : null;
$demo_badge_description_id = $is_demo && $demo_badge ? wp_unique_id('badge-demo-desc-') : '';

?>
<div class="carte carte-chasse carte-cart <?php echo esc_attr(trim($infos['classe_statut'] . ' ' . $completion_class)); ?>">
    <a href="<?php echo esc_url($infos['permalink']); ?>" class="carte-cart__lien">
        <div <?php echo $wrapper_attributes; ?>>
            <div class="carte-badges-stack">
                <?php if ($is_demo && $demo_badge) : ?>
                    <span
                        class="badge-demo"
                        role="img"
                        aria-label="<?php echo esc_attr($demo_badge['aria_label'] ?? $demo_badge['screen_text'] ?? ''); ?>"
                        <?php if ($demo_badge_description_id) : ?>aria-describedby="<?php echo esc_attr($demo_badge_description_id); ?>"<?php endif; ?>
                        title="<?php echo esc_attr($demo_badge['title'] ?? ''); ?>"
                    >
                        <?php if (!empty($demo_badge['icon_html'])) : ?>
                            <span class="badge-demo__icon" aria-hidden="true">
                                <?php echo $demo_badge['icon_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icône préparée. ?>
                            </span>
                        <?php endif; ?>
                        <span class="badge-demo__label"><?php echo esc_html($demo_badge['label'] ?? ''); ?></span>
                        <?php if ($demo_badge_description_id) : ?>
                            <span id="<?php echo esc_attr($demo_badge_description_id); ?>" class="screen-reader-text"><?php echo esc_html($demo_badge['screen_text'] ?? ''); ?></span>
                        <?php endif; ?>
                    </span>
                <?php else : ?>
                    <span class="badge-statut <?php echo esc_attr($badge_classes); ?>" data-post-id="<?php echo esc_attr($chasse_id); ?>"<?= $badge_attributes; ?>>
                        <span class="badge-statut__label"><?php echo esc_html($badge_label); ?></span>
                        <?php if ($has_badge_icon) : ?>
                            <span class="badge-statut__icon" aria-hidden="true">
                                <?php echo $badge_icon_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- contenu préparé et sécurisé en amont. ?>
                            </span>
                        <?php endif; ?>
                    </span>
                <?php endif; ?>
            </div>
            <?php echo $image_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- contenu préparé ci-dessus. ?>
        </div>
        <div class="carte-cart__contenu">
            <h3 class="carte-cart__titre"><?php echo esc_html($infos['titre']); ?></h3>
            <?php echo $infos['lot_html']; ?>
        </div>
    </a>
</div>
