<?php
defined('ABSPATH') || exit;

if (!isset($args['chasse_id']) || empty($args['chasse_id'])) {
    return;
}

$chasse_id       = (int) $args['chasse_id'];
$completion_class = $args['completion_class'] ?? '';
$word_limit      = isset($args['word_limit']) ? (int) $args['word_limit'] : 300;
$infos           = preparer_infos_affichage_carte_chasse($chasse_id, $word_limit);
$mode_fin        = $infos['mode_fin'] ?? 'automatique';
$title_mode      = $mode_fin === 'automatique'
    ? esc_html__('mode de fin de chasse : automatique', 'chassesautresor-com')
    : esc_html__('mode de fin de chasse : manuelle', 'chassesautresor-com');

$orga_id = get_organisateur_from_chasse($chasse_id);

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

$regions_links = chasse_format_meta_terms($infos['regions'] ?? null);
$themes_links = chasse_format_meta_terms($infos['themes'] ?? null);
$reward_title = get_field('chasse_infos_recompense_titre', $chasse_id);
$reward_value = get_field('chasse_infos_recompense_valeur', $chasse_id);
$has_reward = !empty($reward_title) && (float) $reward_value > 0;
?>
<div class="carte carte-chasse carte-wide <?php echo esc_attr(trim($infos['classe_statut'] . ' ' . $completion_class)); ?>">
    <div class="carte-wide__image">
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
                <span class="badge-statut <?php echo esc_attr($badge_classes); ?>"
                    data-post-id="<?php echo esc_attr($chasse_id); ?>"<?= $badge_attributes; ?>>
                    <span class="badge-statut__label"><?php echo esc_html($badge_label); ?></span>
                    <?php if ($has_badge_icon) : ?>
                        <span class="badge-statut__icon" aria-hidden="true">
                            <?php echo $badge_icon_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- contenu préparé et sécurisé en amont. ?>
                        </span>
                    <?php endif; ?>
                </span>
            <?php endif; ?>
        </div>
        <?php if ((int) $infos['cout_points'] > 0) : ?>
        <span
            class="badge-cout"
            data-post-id="<?php echo esc_attr($chasse_id); ?>"
            aria-label="<?php echo esc_attr(
                sprintf(
                    __('Coût de participation : %d points.', 'chassesautresor-com'),
                    $infos['cout_points']
                )
            ); ?>"
        >
            <?php echo esc_html($infos['cout_points'] . ' ' . __('pts', 'chassesautresor-com')); ?>
        </span>
        <?php endif; ?>

        <span class="mode-fin-icone" title="<?php echo esc_attr($title_mode); ?>" aria-label="<?php echo esc_attr($title_mode); ?>">
            <?php if ($mode_fin === 'automatique') : ?>
                <?= trim(get_svg_icon('automatic')); ?>
            <?php else : ?>
                <?= trim(get_svg_icon('hand')); ?>
            <?php endif; ?>
        </span>
        <?php
        $image_id   = $infos['image_id'] ?? 0;
        if ($image_id) {
            $image_html = wp_get_attachment_image(
                $image_id,
                [300, 300],
                false,
                [
                    'alt'     => $infos['titre'],
                    'loading' => 'lazy',
                ]
            );
        } else {
            $image_html = sprintf(
                '<img src="%s" alt="%s" loading="lazy">',
                esc_url($infos['image']),
                esc_attr($infos['titre'])
            );
        }
        ?>
        <a class="carte-wide__image-link" href="<?php echo esc_url($infos['permalink']); ?>">
            <?php echo $image_html; ?>
        </a>
        </div>

    <div class="carte-wide__contenu">
        <div class="carte-wide__header">
            <h3 class="carte-wide__titre">
                <a href="<?php echo esc_url($infos['permalink']); ?>"><?php echo esc_html($infos['titre']); ?></a>
            </h3>
            <?php if ($orga_id) : ?>
                <div class="carte-wide__organisateur">
                    <a class="carte-wide__organisateur-nom" href="<?php echo esc_url(get_permalink($orga_id)); ?>">
                        <?php echo esc_html(get_the_title($orga_id)); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <div class="carte-wide__content">
            <?php if ($has_reward) : ?>
                <div class="carte-wide__reward">
                    <i class="fa-solid fa-trophy" aria-hidden="true"></i>
                    <?php
                    echo wp_kses(
                        sprintf(
                            esc_html__('%1$s — %2$s%3$s', 'chassesautresor-com'),
                            esc_html($reward_title),
                            esc_html(number_format_i18n(round((float) $reward_value), 0)),
                            '<span class="prix-devise">' . esc_html__('€', 'chassesautresor-com') . '</span>'
                        ),
                        [
                            'span' => [
                                'class' => [],
                            ],
                        ]
                    );
                    ?>
                </div>
            <?php endif; ?>

            <div class="carte-wide__description">
                <?php echo $infos['extrait_html']; ?>
            </div>

            <div class="carte-wide__metas meta-row svg-xsmall">
                <div class="meta-regular meta-regular--enigmes">
                    <?php echo get_svg_icon('enigme'); ?>
                    <span class="meta-label">
                        <?php
                        echo esc_html(
                            sprintf(
                                _n('%d énigme', '%d énigmes', $infos['total_enigmes'], 'chassesautresor-com'),
                                $infos['total_enigmes']
                            )
                        );
                        ?>
                    </span>
                </div>
                <div class="meta-regular meta-regular--participants">
                    <?php echo get_svg_icon('participants'); ?>
                    <span class="meta-label"><?php echo esc_html($infos['nb_joueurs_label']); ?></span>
                </div>
                <div class="meta-etiquette meta-etiquette--dates">
                    <?php echo get_svg_icon('calendar'); ?>
                    <span class="chasse-date-plage">
                        <span class="date-debut"><?php echo esc_html($infos['date_debut']); ?></span> –
                        <span class="date-fin"><?php echo esc_html($infos['date_fin']); ?></span>
                    </span>
                </div>
                <?php if (!empty($regions_links)) : ?>
                    <?php
                    $regions_label = _n('Région :', 'Régions :', count($regions_links), 'chassesautresor-com');
                    ?>
                    <div class="meta-etiquette meta-etiquette--regions">
                        <span><?php echo esc_html($regions_label); ?></span>
                        <?php echo wp_kses_post(implode(', ', $regions_links)); ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($themes_links)) : ?>
                    <?php
                    $themes_label = _n('Thème :', 'Thèmes :', count($themes_links), 'chassesautresor-com');
                    ?>
                    <div class="meta-etiquette meta-etiquette--themes">
                        <span><?php echo esc_html($themes_label); ?></span>
                        <?php echo wp_kses_post(implode(', ', $themes_links)); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
