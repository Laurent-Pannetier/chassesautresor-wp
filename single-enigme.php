<?php
/**
 * Template : single-enigme.php (version propre et encapsulée)
 * Affiche la fiche énigme. L'édition se fait dans le dashboard.
 */

defined('ABSPATH') || exit;
// 🔹 Données de base
$enigme_id      = get_the_ID();
$user_id = get_current_user_id();
// 🔹 Statut logique de l’énigme
$statut_data   = traiter_statut_enigme($enigme_id, $user_id);
$statut_enigme = $statut_data['etat'];

// 🔹 Données affichables
$titre              = get_the_title($enigme_id);
$titre_defaut       = TITRE_DEFAUT_ENIGME;
$isTitreParDefaut   = strtolower(trim($titre)) === strtolower($titre_defaut);
$legende            = get_field('enigme_visuel_legende', $enigme_id);
$image_url = get_image_enigme($enigme_id, 'large');

// 🔹 Vérifie relation chasse <-> énigme
if (is_singular('enigme')) {
  forcer_relation_enigme_dans_chasse_si_absente($enigme_id);
}

?>
<?php get_header(); ?>

<div id="primary" class="content-area">
    <main id="main" class="site-main single-enigme-main statut-<?= esc_attr($statut_enigme); ?>">

        <?php if (!empty($_GET['erreur'])) : ?>
            <?php $error_message = sanitize_text_field(wp_unslash($_GET['erreur'])); ?>
            <div class="message-erreur" role="alert" aria-live="assertive">
                <?= esc_html($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if (enigme_est_visible_pour($user_id, $enigme_id)) : ?>
            <section class="enigme-wrapper">
                <!-- 🧩 Affichage de l'énigme -->
                <?php afficher_enigme_stylisee($enigme_id, $statut_data); ?>
            </section>
        <?php endif; ?>

    </main>
  </div>

<?php get_footer('enigme'); ?>
