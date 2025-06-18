<?php

/**
 * Template : single-enigme.php (version minimale)
 * Affiche uniquement le header organisateur et le panneau d'édition
 */

defined('ABSPATH') || exit;

acf_form_head();

$enigme_id = get_the_ID();
$user_id   = get_current_user_id();

// 🔄 Récupération et synchronisation de la chasse liée
$chasse_id = recuperer_id_chasse_associee($enigme_id);
if ($chasse_id) {
  verifier_et_synchroniser_cache_enigmes_si_autorise($chasse_id);
}

if (!enigme_est_visible_pour($user_id, $enigme_id)) {
  $fallback_url = $chasse_id ? get_permalink($chasse_id) : home_url('/');
  wp_redirect($fallback_url);
  exit;
}


// 🛠️ Vérifie si l'utilisateur peut modifier ce post
$edition_active = utilisateur_peut_modifier_post($enigme_id);
if (
  $edition_active &&
  current_user_can('organisateur_creation') &&
  !isset($_GET['edition'])
) {
  $preview_url = add_query_arg('edition', 'open', get_permalink());
  wp_redirect($preview_url);
  exit;
}

// 🌝 Récupération du statut complet (après filtrage d'accès)
$statut_data     = traiter_statut_enigme($enigme_id, $user_id);
$statut_enigme   = $statut_data['etat'];
$verrouillage    = enigme_verifier_verrouillage($enigme_id, $user_id);
$pre_requis_ok   = enigme_pre_requis_remplis($enigme_id, $user_id);


// 🔧 Chargement du header organisateur
get_header();
get_template_part('template-parts/organisateur/header-organisateur', null, [
  'chasse_id' => $chasse_id,
]);


$titre = get_the_title($enigme_id);
$titre_defaut = 'nouvelle énigme';
$isTitreParDefaut = strtolower(trim($titre)) === strtolower($titre_defaut);
$images = get_field('enigme_visuel_image', $enigme_id);
$image_url = '';

if (is_array($images) && count($images) > 0) {
    $image_id = $images[0]['ID'] ?? null;
    if ($image_id) {
        $image_url = add_query_arg('id', $image_id, site_url('/wp-content/themes/chassesautresor/inc/handlers/voir-image-enigme.php'));
    }
}

if (!$image_url) {
    $image_url = wp_get_attachment_image_url(3925, 'large'); // fallback image publique
}

$legende = get_field('enigme_visuel_legende', $enigme_id);

if (is_singular('enigme')) {
  forcer_relation_enigme_dans_chasse_si_absente(get_the_ID());
}
afficher_enigme_stylisee($enigme_id, $statut_data);
?>

<main id="primary" class="site-main single-enigme-main statut-<?= esc_attr($statut_enigme); ?>">

  <?php if (enigme_est_visible_pour($user_id, $enigme_id)) : ?>
    <section class="enigme-wrapper">
      <!-- 🔧 Bouton panneau édition -->
      <?php if ($edition_active) : ?>
        <div class="header-actions-droite">
          <button id="toggle-mode-edition-enigme" type="button"
            class="bouton-edition-toggle"
            data-cpt="enigme"
            aria-label="Activer le mode édition">
            <i class="fa-solid fa-sliders"></i>
          </button>
        </div>
      <?php endif; ?>

      <header class="enigme-header">
        <div class="champ-enigme champ-txt-editable champ-titre <?= $isTitreParDefaut ? 'champ-vide' : 'champ-rempli'; ?>"
          data-champ="post_title"
          data-cpt="enigme"
          data-post-id="<?= esc_attr($enigme_id); ?>">

          <div class="champ-affichage">
            <h1 class="enigme-titre"><?= esc_html($titre); ?></h1>
            <?php if ($edition_active) : ?>
              <button type="button" class="champ-modifier" aria-label="Modifier le titre">✏️</button>
            <?php endif; ?>
          </div>

          <div class="champ-edition" style="display: none;">
            <input type="text" maxlength="80" value="<?= esc_attr($titre); ?>" class="champ-input">
            <button type="button" class="champ-enregistrer">✓</button>
            <button type="button" class="champ-annuler">✖</button>
          </div>

          <div class="champ-feedback"></div>
        </div>
      </header>
    </section>
  <?php endif; ?>

  <?php get_template_part('template-parts/enigme/edition-enigme', null, [
    'enigme_id' => $enigme_id,
    'user_id'   => $user_id,
  ]); ?>

</main>

<?php
if ($edition_active) {
  // 📝 Panneau d’édition du texte
  get_template_part('template-parts/enigme/panneaux/panneau-description-enigme', null, [
    'enigme_id' => $enigme_id,
  ]);

  // 🖼️ Panneau d’édition des images
  get_template_part('template-parts/enigme/panneaux/panneau-images-enigme', null, [
    'enigme_id' => $enigme_id,
  ]);

  // 🎭 Panneau d’édition des variantes
  get_template_part('template-parts/enigme/panneaux/panneau-variantes-enigme', null, [
    'enigme_id' => $enigme_id,
  ]);
  // 📘 Panneau d’édition de la solution
  get_template_part('template-parts/enigme/panneaux/panneau-solution-enigme', null, [
    'enigme_id' => $enigme_id,
  ]);
}
?>


<?php get_footer(); ?>