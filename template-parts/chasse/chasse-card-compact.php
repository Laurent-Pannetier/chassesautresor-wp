<?php
defined('ABSPATH') || exit;

if (!isset($args['chasse_id']) || empty($args['chasse_id'])) {
    return;
}

$chasse_id = (int) $args['chasse_id'];
$show_progression = $args['show_progression'] ?? true;
$infos     = preparer_infos_affichage_carte_chasse(
    $chasse_id,
    300,
    [
        'badge_format' => 'icon',
    ]
);

if (empty($infos)) {
    return;
}

$badge_tooltip = $infos['badge_tooltip'] ?? '';
$badge_has_interaction = !empty($infos['badge_requires_interaction']);
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

$progression = $infos['progression'] ?? null;
$resolvables = is_array($progression) ? (int) ($progression['resolvables'] ?? 0) : 0;
$resolues_validables = isset($infos['resolues_validables']) ? (int) $infos['resolues_validables'] : 0;
$lot_html = $infos['lot_html'] ?? '';
$has_reward = $lot_html !== '';
$lot_wrapper_classes = 'carte-compact__lot';

if ($has_reward) {
    $lot_wrapper_classes .= ' carte-compact__lot--filled';
} else {
    $lot_wrapper_classes .= ' carte-compact__lot--empty';
}
?>
<div class="carte-compact-card">
    <div class="carte carte-chasse carte-compact <?php echo esc_attr($infos['classe_statut']); ?>">
        <a href="<?php echo esc_url($infos['permalink']); ?>" class="carte-compact__lien">
            <div class="carte-compact__image-wrapper">
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
                        <span class="badge-statut <?php echo esc_attr($infos['badge_class']); ?>" data-post-id="<?php echo esc_attr($chasse_id); ?>"<?= $badge_attributes; ?>>
                            <?php echo $infos['badge_content']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- contenu préparé et sécurisé en amont. ?>
                        </span>
                    <?php endif; ?>
                </div>
                <img src="<?php echo esc_url($infos['image']); ?>" alt="<?php echo esc_attr($infos['titre']); ?>" class="carte-compact__image">
            </div>
            <div class="carte-compact__contenu">
                <h3 class="carte-compact__titre"><?php echo esc_html($infos['titre']); ?></h3>
                <div class="<?php echo esc_attr($lot_wrapper_classes); ?>"<?php if (!$has_reward) : ?> aria-hidden="true"<?php endif; ?>>
                    <?php if ($has_reward) : ?>
                        <?php echo $lot_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- contenu préparé et sécurisé en amont. ?>
                    <?php endif; ?>
                </div>
                <?php
                get_template_part(
                    'template-parts/chasse/partials/chasse-meta-row',
                    null,
                    array(
                        'infos'           => $infos,
                        'wrapper_class'   => 'carte-compact__meta meta-row svg-xsmall',
                        'display_mode'    => 'counts',
                        'use_short_dates' => true,
                    )
                );
                ?>
            </div>
        </a>
    </div>
    <?php if ($show_progression && $resolvables > 0) : ?>
        <div class="carte-compact__progression-wrapper">
            <div class="carte-compact__progression">
                <div class="meta-etiquette carte-compact__progression-label">
                    <?php
                    $label = sprintf(
                        __('Résolues %1$s/%2$s', 'chassesautresor-com'),
                        number_format_i18n($resolues_validables),
                        number_format_i18n($resolvables)
                    );
                    echo esc_html($label);
                    ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
