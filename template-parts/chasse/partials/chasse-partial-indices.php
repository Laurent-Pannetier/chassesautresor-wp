<?php
/**
 * Displays indices add card for a hunt or riddle.
 *
 * Variables:
 * - $objet_id   (int)
 * - $objet_type ('chasse'|'enigme')
 */

defined('ABSPATH') || exit;

$args       = $args ?? [];
$objet_id   = $args['objet_id'] ?? $chasse_id ?? 0;
$objet_type = $args['objet_type'] ?? 'chasse';
$default_enigme = $args['default_enigme'] ?? null;

$objet_titre = get_the_title($objet_id);
$indice_rang = prochain_rang_indice($objet_id, $objet_type);
$has_indices = $indice_rang > 1;
$state_class = $has_indices ? 'champ-rempli' : 'champ-vide';

$peut_ajouter = indice_action_autorisee('create', $objet_type, $objet_id);
$enigmes_disponibles = $objet_type === 'chasse' ? recuperer_enigmes_pour_chasse($objet_id) : [];
$has_enigmes = !empty($enigmes_disponibles);

if ($objet_type === 'enigme') {
    $chasse_id           = (int) recuperer_id_chasse_associee($objet_id);
    $chasse_titre        = get_the_title($chasse_id);
    $chasse_indice_rang  = prochain_rang_indice($chasse_id, 'chasse');
}
?>
<div class="dashboard-card carte-orgy champ-<?= esc_attr($objet_type); ?> champ-indices<?= $peut_ajouter ? '' : ' disabled'; ?> <?= esc_attr($state_class); ?>">
    <span class="carte-check" aria-hidden="true"><i class="fa-solid fa-check"></i></span>
    <i class="fa-solid fa-kit-medical icone-defaut" aria-hidden="true"></i>
    <h3><?= esc_html__('Ajouter un indice', 'chassesautresor-com'); ?></h3>
<?php if ($peut_ajouter) : ?>
    <div class="stat-value">
        <button
            type="button"
            class="bouton-cta cta-indice-pour"
        >
            <?= esc_html__('Pour…', 'chassesautresor-com'); ?>
        </button>
        <div class="cta-indice-options">
            <?php if ($objet_type === 'enigme') : ?>
                <button
                    type="button"
                    class="bouton-cta cta-indice-enigme"
                    data-objet-type="enigme"
                    data-objet-id="<?= esc_attr($objet_id); ?>"
                    data-objet-titre="<?= esc_attr($objet_titre); ?>"
                    data-chasse-id="<?= esc_attr($chasse_id); ?>"
                    data-default-enigme="<?= esc_attr($objet_id); ?>"
                    data-indice-rang="<?= esc_attr($indice_rang); ?>"
                >
                    <?= esc_html__('Énigme', 'chassesautresor-com'); ?>
                </button>
                <button
                    type="button"
                    class="bouton-cta cta-creer-indice cta-indice-chasse"
                    data-objet-type="chasse"
                    data-objet-id="<?= esc_attr($chasse_id); ?>"
                    data-objet-titre="<?= esc_attr($chasse_titre); ?>"
                    data-indice-rang="<?= esc_attr($chasse_indice_rang); ?>"
                >
                    <?= esc_html__('La chasse entière', 'chassesautresor-com'); ?>
                </button>
            <?php else : ?>
                <button
                    type="button"
                    class="bouton-cta cta-creer-indice cta-indice-chasse"
                    data-objet-type="<?= esc_attr($objet_type); ?>"
                    data-objet-id="<?= esc_attr($objet_id); ?>"
                    data-objet-titre="<?= esc_attr($objet_titre); ?>"
                    data-indice-rang="<?= esc_attr($indice_rang); ?>"
                >
                    <?= esc_html__('La chasse entière', 'chassesautresor-com'); ?>
                </button>
                <?php if ($has_enigmes) : ?>
                    <button
                        type="button"
                        class="bouton-cta cta-indice-enigme"
                        data-objet-type="enigme"
                        data-chasse-id="<?= esc_attr($objet_id); ?>"
                        <?php if ($default_enigme) : ?>
                            data-default-enigme="<?= esc_attr($default_enigme); ?>"
                        <?php endif; ?>
                    >
                        <?= esc_html__('Une énigme de la chasse', 'chassesautresor-com'); ?>
                    </button>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
<?php else : ?>
    <span class="stat-value">
        <?= esc_html__('Ajouter', 'chassesautresor-com'); ?>
    </span>
<?php endif; ?>
</div>
