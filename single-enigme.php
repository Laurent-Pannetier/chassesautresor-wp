<?php
/**
 * Template : single-enigme.php (version propre et encapsulée)
 * Affiche uniquement le header organisateur et le panneau d'édition
 */

defined('ABSPATH') || exit;
acf_form_head();

// 🔹 Données de base
$enigme_id = get_the_ID();
$user_id   = get_current_user_id();

// 🔹 Chasse associée
$chasse_id = recuperer_id_chasse_associee($enigme_id);
if ($chasse_id) {
  verifier_et_synchroniser_cache_enigmes_si_autorise($chasse_id);
}

// 🔹 Redirection si non visible
if (!enigme_est_visible_pour($user_id, $enigme_id)) {
  $fallback_url = $chasse_id ? get_permalink($chasse_id) : home_url('/');
  wp_redirect($fallback_url);
  exit;
}

// 🔹 Mode édition auto
$edition_active = utilisateur_peut_modifier_post($enigme_id);
if (
  $edition_active &&
  current_user_can('organisateur_creation') &&
  !isset($_GET['edition'])
) {
  wp_redirect(add_query_arg('edition', 'open', get_permalink()));
  exit;
}

// 🔹 Statut logique de l’énigme
$statut_data     = traiter_statut_enigme($enigme_id, $user_id);
$statut_enigme   = $statut_data['etat'];
$verrouillage    = enigme_verifier_verrouillage($enigme_id, $user_id);
$pre_requis_ok   = enigme_pre_requis_remplis($enigme_id, $user_id);

// 🔹 Données affichables
$titre              = get_the_title($enigme_id);
$titre_defaut       = 'nouvelle énigme';
$isTitreParDefaut   = strtolower(trim($titre)) === strtolower($titre_defaut);
$legende            = get_field('enigme_visuel_legende', $enigme_id);
$images             = get_field('enigme_visuel_image', $enigme_id);
$image_url          = '';
$image_id           = null;

if (is_array($images) && count($images) > 0) {
  $image_id = $images[0]['ID'] ?? null;
  if ($image_id) {
    $image_url = add_query_arg('id', $image_id, site_url('/wp-content/themes/chassesautresor/inc/handlers/voir-image-enigme.php'));
  }
}
if (!$image_url) {
  $image_url = wp_get_attachment_image_url(3925, 'large');
}

// 🔹 Vérifie relation chasse <-> énigme
if (is_singular('enigme')) {
  forcer_relation_enigme_dans_chasse_si_absente($enigme_id);
}

$enigmes = get_posts([
  'post_type'      => 'enigme',
  'post_status'    => ['publish', 'pending', 'draft'],
  'numberposts'    => 10
]);

foreach ($enigmes as $post) {
  $val = get_field('enigme_chasse_associee', $post->ID, false); // ⚠️ false = valeur brute (non formatée)
  echo "<pre>";
  echo "Énigme #{$post->ID} ({$post->post_title}) : ";
  var_dump($val);
  echo "</pre>";
}
// 🔹 Vérifie si l’énigme est verrouillée

?>
<?php get_header(); ?>

<div class="ast-container">
  <div id="primary" class="content-area">
    <main id="main" class="site-main single-enigme-main statut-<?= esc_attr($statut_enigme); ?>">

      <?php
      // 🔧 Header organisateur (s'affiche en haut de page)
      get_template_part('template-parts/organisateur/organisateur-header', null, [
        'chasse_id' => $chasse_id,
      ]);
      ?>

      <?php if (enigme_est_visible_pour($user_id, $enigme_id)) : ?>
        <section class="enigme-wrapper">
          <!-- 🔧 Bouton pour ouvrir le panneau d’édition -->
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

          <!-- 🧩 Affichage de l'énigme -->
          <?php afficher_enigme_stylisee($enigme_id, $statut_data); ?>
        </section>
      <?php endif; ?>

      <!-- 🛠 Panneau principal d’édition -->
      <?php get_template_part('template-parts/enigme/enigme-edition-main', null, [
        'enigme_id' => $enigme_id,
        'user_id'   => $user_id,
      ]); ?>

      <?php if ($edition_active) : ?>
        <!-- ✏️ Panneaux complémentaires -->
        <?php
        get_template_part('template-parts/enigme/panneaux/enigme-edition-description', null, ['enigme_id' => $enigme_id]);
        get_template_part('template-parts/enigme/panneaux/enigme-edition-images', null, ['enigme_id' => $enigme_id]);
        get_template_part('template-parts/enigme/panneaux/enigme-edition-variantes', null, ['enigme_id' => $enigme_id]);
        get_template_part('template-parts/enigme/panneaux/enigme-edition-solution', null, ['enigme_id' => $enigme_id]);
        ?>
      <?php endif; ?>

    </main>
  </div>
</div>

<?php get_footer(); ?>
