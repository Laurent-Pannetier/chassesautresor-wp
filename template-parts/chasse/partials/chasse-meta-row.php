<?php
defined('ABSPATH') || exit;

$infos = $args['infos'] ?? [];
if (empty($infos) || !is_array($infos)) {
    return;
}

$wrapper_class   = trim((string) ($args['wrapper_class'] ?? 'meta-row svg-xsmall'));
$display_mode    = $args['display_mode'] ?? 'buttons';
$use_short_dates = !empty($args['use_short_dates']);

$tooltip_enigmes = $args['tooltip_enigmes'] ?? __('nombre d\'énigmes', 'chassesautresor-com');
$tooltip_joueurs = $args['tooltip_joueurs'] ?? __('nombre de joueurs', 'chassesautresor-com');

$date_debut_key = $use_short_dates ? 'date_debut_court' : 'date_debut';
$date_fin_key   = $use_short_dates ? 'date_fin_court' : 'date_fin';

$nombre_enigmes        = isset($infos['total_enigmes']) ? (int) $infos['total_enigmes'] : 0;
$nombre_enigmes_format = number_format_i18n($nombre_enigmes);
$nombre_joueurs        = isset($infos['nb_joueurs']) ? (int) $infos['nb_joueurs'] : 0;
$nombre_joueurs_format = number_format_i18n($nombre_joueurs);

$label_enigmes = sprintf(
    _n('%s énigme', '%s énigmes', $nombre_enigmes === 1 ? 1 : $nombre_enigmes, 'chassesautresor-com'),
    $nombre_enigmes_format
);
$label_joueurs = $infos['nb_joueurs_label'] ?? sprintf(
    _n('%s joueur', '%s joueurs', $nombre_joueurs === 1 ? 1 : $nombre_joueurs, 'chassesautresor-com'),
    $nombre_joueurs_format
);

$date_debut = isset($infos[$date_debut_key]) ? (string) $infos[$date_debut_key] : '';
$date_fin   = isset($infos[$date_fin_key]) ? (string) $infos[$date_fin_key] : '';
?>
<div class="<?php echo esc_attr($wrapper_class); ?>">
    <div class="meta-regular">
        <?php if ($display_mode === 'text') : ?>
            <?php echo get_svg_icon('enigme'); ?>
            <?php echo esc_html($label_enigmes); ?>
            &mdash;
            <?php echo get_svg_icon('participants'); ?>
            <?php echo esc_html($label_joueurs); ?>
        <?php elseif ($display_mode === 'counts') : ?>
            <span
                class="meta-indic meta-indic--static"
                title="<?php echo esc_attr($label_enigmes); ?>"
                aria-label="<?php echo esc_attr($label_enigmes); ?>"
            >
                <?php echo get_svg_icon('enigme'); ?>
                <span class="meta-indic__count"><?php echo esc_html($nombre_enigmes_format); ?></span>
            </span>
            <span
                class="meta-indic meta-indic--static"
                title="<?php echo esc_attr($label_joueurs); ?>"
                aria-label="<?php echo esc_attr($label_joueurs); ?>"
            >
                <?php echo get_svg_icon('participants'); ?>
                <span class="meta-indic__count"><?php echo esc_html($nombre_joueurs_format); ?></span>
            </span>
        <?php else : ?>
            <button
                type="button"
                class="meta-indic"
                data-tap="<?php echo esc_attr($tooltip_enigmes); ?>"
            >
                <?php echo get_svg_icon('enigme'); ?>
                <span class="meta-indic__count"><?php echo esc_html($nombre_enigmes_format); ?></span>
            </button>
            <button
                type="button"
                class="meta-indic"
                data-tap="<?php echo esc_attr($tooltip_joueurs); ?>"
            >
                <?php echo get_svg_icon('participants'); ?>
                <span class="meta-indic__count"><?php echo esc_html($nombre_joueurs_format); ?></span>
            </button>
        <?php endif; ?>
    </div>
    <div class="meta-etiquette">
        <?php echo get_svg_icon('calendar'); ?>
        <span class="chasse-date-plage">
            <span class="date-debut"><?php echo esc_html($date_debut); ?></span>
            &ndash;
            <span class="date-fin"><?php echo esc_html($date_fin); ?></span>
        </span>
    </div>
</div>
