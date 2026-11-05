<?php
defined('ABSPATH') || exit;

$organisateur_id = $args['organisateur_id'] ?? null;
$has_chasses     = $args['has_chasses'] ?? false;
$highlight_pulse = $args['highlight_pulse'] ?? false;


if (!$organisateur_id || get_post_type($organisateur_id) !== 'organisateur') {
    return;
}

$classes = [
    'carte',
    'carte-chasse',
    'carte-ajout-chasse',
    $has_chasses ? 'etat-suivante' : 'etat-vide',
    'disabled',
];

if ($highlight_pulse) {
    $classes[] = 'pulsation';
}

$ajout_url = esc_url(site_url('/creer-chasse/'));
?>
<button
    type="button"
    id="carte-ajout-chasse"
    class="<?php echo esc_attr(implode(' ', $classes)); ?>"
    data-post-id="0"
    aria-label="<?php echo esc_attr__('Ajouter une chasse', 'chassesautresor-com'); ?>"
    onclick="if(!this.classList.contains('disabled')){window.location.href='<?php echo $ajout_url; ?>';}"
>
    <div class="carte-core">
        <i class="fa-solid fa-circle-plus" aria-hidden="true"></i>
        <span class="carte-ajout-libelle">
            <?php echo $has_chasses
                ? esc_html__('Ajouter une nouvelle chasse', 'chassesautresor-com')
                : esc_html__('Créer ma première chasse', 'chassesautresor-com'); ?>
        </span>
        <span class="carte-ajout-message" aria-live="polite">
            <?php esc_html_e('Complétez d\u2019abord : titre, logo, description', 'chassesautresor-com'); ?>
        </span>
    </div>
</button>
