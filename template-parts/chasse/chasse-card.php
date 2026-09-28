<?php
defined('ABSPATH') || exit;

if (!isset($args['chasse_id']) || empty($args['chasse_id'])) {
    return;
}

$chasse_id = $args['chasse_id'];
$completion_class = $args['completion_class'] ?? '';

$infos = preparer_infos_affichage_carte_chasse($chasse_id);
if (empty($infos)) {
    return;
}

$badge_tooltip = $infos['badge_tooltip'] ?? '';
$badge_icon_html = $infos['statut_icon'] ?? '';
$badge_label = $infos['statut_label'] ?? ($infos['badge_content'] ?? '');
$has_badge_icon = $badge_icon_html !== '';
$badge_classes = $infos['badge_class'] ?? '';
$is_demo = !empty($infos['is_demo']);
$demo_badge = is_array($infos['demo_badge'] ?? null) ? $infos['demo_badge'] : null;
$demo_badge_description_id = $is_demo && $demo_badge ? wp_unique_id('badge-demo-desc-') : '';

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
?>

<div class="carte carte-ligne carte-chasse <?php echo esc_attr(trim($infos['classe_statut'] . ' ' . $completion_class)); ?>">
    <div class="carte-ligne__image">
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
                            <?php echo $demo_badge['icon_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icône SVG préparée. ?>
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
        <img src="<?php echo esc_url($infos['image']); ?>" alt="<?php echo esc_attr($infos['titre']); ?>">
    </div>

    <div class="carte-ligne__contenu">
        <h3 class="carte-ligne__titre">
            <a href="<?php echo esc_url($infos['permalink']); ?>"><?php echo esc_html($infos['titre']); ?></a>
        </h3>

        <?php
        get_template_part(
            'template-parts/chasse/partials/chasse-meta-row',
            null,
            array(
                'infos'           => $infos,
                'wrapper_class'   => 'meta-row svg-xsmall',
                'display_mode'    => 'buttons',
                'use_short_dates' => true,
            )
        );
        ?>

        <?php echo $infos['extrait_html']; ?>
        <?php echo $infos['lot_html']; ?>
        <div class="flex-row cta-div">
            <a href="<?php echo esc_url($infos['permalink']); ?>" class="bouton-secondaire"><?= esc_html__('En savoir plus', 'chassesautresor-com'); ?></a>
        </div>
        <?php echo $infos['footer_html']; ?>
    </div>
</div>

