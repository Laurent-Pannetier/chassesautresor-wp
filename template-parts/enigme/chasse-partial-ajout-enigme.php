<?php
defined('ABSPATH') || exit;
/**
 * Template part : Carte d’ajout d’énigme
 *
 * Contexte attendu :
 * - $args['has_enigmes'] (bool) : indique s’il y a déjà des énigmes
 * - $args['chasse_id'] (int) : identifiant de la chasse
 * - $args['disabled'] (bool) : désactive la carte si true
 * - $args['highlight_pulse'] (bool) : affiche un effet de pulsation
 * - $args['show_help_icon'] (bool) : affiche un badge d’information
 * - $args['use_button'] (bool) : rend un bouton dans la navigation latérale
 */

$has_enigmes     = $args['has_enigmes'] ?? false;
$chasse_id       = $args['chasse_id'] ?? null;
$disabled        = $args['disabled'] ?? false;
$highlight_pulse = $args['highlight_pulse'] ?? false;
$show_help_icon  = $args['show_help_icon'] ?? false;
$use_button      = $args['use_button'] ?? false;

if (!$chasse_id || get_post_type($chasse_id) !== 'chasse') return;

$ajout_url = esc_url(add_query_arg('chasse_id', $chasse_id, home_url('/creer-enigme/')));

if ($use_button) : ?>
<div class="enigme-navigation__ajout">
    <a
        href="<?php echo $ajout_url; ?>"
        id="carte-ajout-enigme"
        class="enigme-navigation__ajout-bouton"
        data-post-id="0"
    >
        <i class="fa-solid fa-circle-plus fa-lg" aria-hidden="true"></i>
        <span class="screen-reader-text"><?php echo esc_html__('Ajouter une énigme', 'chassesautresor-com'); ?></span>
    </a>
    <a href="<?php echo $ajout_url; ?>" class="enigme-navigation__ajout-lien">
        <?php echo esc_html__('Ajouter une énigme', 'chassesautresor-com'); ?>
    </a>
</div>
<?php
    return;
endif;

?>
<?php
$classes = [
    'carte',
    'carte-enigme',
    'carte-ajout-enigme',
    $has_enigmes ? 'etat-suivante' : 'etat-vide',
];
if ($disabled) {
    $classes[] = 'disabled';
}
if ($highlight_pulse) {
    $classes[] = 'pulsation';
}
?>
<button
    type="button"
    id="carte-ajout-enigme"
    class="<?php echo esc_attr(implode(' ', $classes)); ?>"
    data-post-id="0"
    aria-label="<?php echo esc_attr__('Ajouter une énigme', 'chassesautresor-com'); ?>"
    <?php echo $disabled ? 'disabled' : ''; ?>
    onclick="if(!this.hasAttribute('disabled')){window.location.href='<?php echo $ajout_url; ?>';}"
>
    <?php if ($show_help_icon) : ?>
        <span class="warning-icon" aria-label="<?php echo esc_attr__('Validation en ligne nécessaire', 'chassesautresor-com'); ?>" title="<?php echo esc_attr__('Validation en ligne nécessaire', 'chassesautresor-com'); ?>">
            <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
        </span>
    <?php endif; ?>
    <div class="carte-core">
        <i class="fa-solid fa-circle-plus" aria-hidden="true"></i>
        <span class="carte-ajout-libelle"><?php echo esc_html__('Ajouter une énigme', 'chassesautresor-com'); ?></span>
    </div>
</button>

