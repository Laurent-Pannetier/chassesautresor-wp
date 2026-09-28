<?php
/**
 * Displays solutions add card for a hunt or riddle.
 *
 * Variables:
 * - $objet_id   (int)
 * - $objet_type ('chasse'|'enigme')
 */

defined('ABSPATH') || exit;

$args = $args ?? [];
$objet_id = $args['objet_id'] ?? $chasse_id ?? 0;
$objet_type = $args['objet_type'] ?? 'chasse';
$default_enigme = $args['default_enigme'] ?? null;

$peut_ajouter = solution_action_autorisee('create', $objet_type, $objet_id);

if ($objet_type === 'enigme') {
    $chasse_id            = (int) recuperer_id_chasse_associee($objet_id);
    $chasse_titre         = get_the_title($chasse_id);
    $has_solution_enigme  = solution_existe_pour_objet($objet_id, 'enigme');
    $has_solution_chasse  = solution_existe_pour_objet($chasse_id, 'chasse');
    $has_solutions        = $has_solution_enigme || $has_solution_chasse;
    $state_class          = $has_solutions ? 'champ-rempli' : 'champ-vide';
} else {
    $has_solution_chasse = solution_existe_pour_objet($objet_id, 'chasse');
    $toutes_enigmes      = recuperer_enigmes_pour_chasse($objet_id);
    $enigmes_disponibles = array_filter(
        $toutes_enigmes,
        static fn($e) => !solution_existe_pour_objet($e->ID, 'enigme')
    );
    $has_enigmes         = !empty($enigmes_disponibles);
    $has_enigme_solution = count($toutes_enigmes) > count($enigmes_disponibles);
    $has_solutions       = $has_solution_chasse || $has_enigme_solution;
    $state_class         = $has_solutions ? 'champ-rempli' : 'champ-vide';
}
?>
<div class='dashboard-card carte-orgy champ-<?= esc_attr($objet_type); ?> champ-solutions<?= $peut_ajouter ? '' : ' disabled'; ?> <?= esc_attr($state_class); ?>'
>
    <span class="carte-check" aria-hidden='true'><i class='fa-solid fa-check'></i></span>
    <i class='fa-solid fa-lightbulb icone-defaut' aria-hidden='true'></i>
    <h3><?= esc_html__('Ajouter une solution', 'chassesautresor-com'); ?></h3>
<?php if ($peut_ajouter) : ?>
    <div class='stat-value'>
        <button
            type='button'
            class='bouton-cta cta-solution-pour'
        >
            <?= esc_html__('Pour…', 'chassesautresor-com'); ?>
        </button>
        <div class='cta-solution-options'>
            <?php if ($objet_type === 'enigme') : ?>
                <button
                    type='button'
                    class='bouton-cta cta-solution-enigme ajouter-solution<?= $has_solution_enigme ? ' disabled' : ''; ?>'
                    data-objet-type='enigme'
                    data-objet-id='<?= esc_attr($objet_id); ?>'
                    data-objet-titre='<?= esc_attr(get_the_title($objet_id)); ?>'
                    data-chasse-id='<?= esc_attr($chasse_id); ?>'
                    <?php if ($has_solution_enigme) : ?>
                        aria-disabled="true"
                        title="<?= esc_attr__('Il existe déjà une solution pour cette énigme', 'chassesautresor-com'); ?>"
                    <?php endif; ?>
                >
                    <?= esc_html__('Cette énigme', 'chassesautresor-com'); ?>
                </button>
                <button
                    type='button'
                    class='bouton-cta cta-solution-chasse ajouter-solution<?= $has_solution_chasse ? ' disabled' : ''; ?>'
                    data-objet-type='chasse'
                    data-objet-id='<?= esc_attr($chasse_id); ?>'
                    data-objet-titre='<?= esc_attr($chasse_titre); ?>'
                    <?php if ($has_solution_chasse) : ?>
                        aria-disabled="true"
                        title="<?= esc_attr__('Il existe déjà une solution pour cette chasse', 'chassesautresor-com'); ?>"
                    <?php endif; ?>
                >
                    <?= esc_html__('La chasse entière', 'chassesautresor-com'); ?>
                </button>
            <?php else : ?>
                <button
                    type='button'
                    class='bouton-cta cta-solution-chasse ajouter-solution<?= $has_solution_chasse ? ' disabled' : ''; ?>'
                    data-objet-type='chasse'
                    data-objet-id='<?= esc_attr($objet_id); ?>'
                    data-objet-titre='<?= esc_attr(get_the_title($objet_id)); ?>'
                    <?php if ($has_solution_chasse) : ?>
                        aria-disabled="true"
                        title="<?= esc_attr__('Il existe déjà une solution pour cette chasse', 'chassesautresor-com'); ?>"
                    <?php endif; ?>
                >
                    <?= esc_html__('La chasse entière', 'chassesautresor-com'); ?>
                </button>
                <button
                    type='button'
                    class='bouton-cta cta-solution-enigme ajouter-solution<?= $has_enigmes ? '' : ' disabled'; ?>'
                    data-objet-type='enigme'
                    data-chasse-id='<?= esc_attr($objet_id); ?>'
                    <?php if ($default_enigme) : ?>
                        data-default-enigme='<?= esc_attr($default_enigme); ?>'
                    <?php endif; ?>
                    <?php if (!$has_enigmes) : ?>
                        aria-disabled="true"
                        title="<?= esc_attr__('Toutes les énigmes de la chasse ont déjà une solution', 'chassesautresor-com'); ?>"
                    <?php endif; ?>
                >
                    <?= esc_html__('Une énigme de la chasse', 'chassesautresor-com'); ?>
                </button>
            <?php endif; ?>
        </div>
    </div>
<?php else : ?>
    <span class='stat-value'>
        <?= esc_html__('Ajouter', 'chassesautresor-com'); ?>
    </span>
<?php endif; ?>
</div>
