<?php
defined('ABSPATH') || exit;

$chasse_id = $args['chasse_id'] ?? null;
if (!$chasse_id || get_post_type($chasse_id) !== 'chasse') return;


$utilisateur_id = get_current_user_id();

if (!chasse_est_visible_pour_utilisateur($chasse_id, $utilisateur_id)) {
  return;

}

// 🔎 Récupération des énigmes associées à la chasse
$posts = get_posts([
  'post_type'      => 'enigme',
  'posts_per_page' => -1,
  'orderby'        => 'menu_order',
  'order'          => 'ASC',
  'post_status'    => ['publish', 'pending', 'draft'],
  'meta_query'     => [[
    'key'     => 'enigme_chasse_associee',
    'value'   => $chasse_id,
    'compare' => '='
  ]]
]);

// 🔒 Ne garder que les énigmes visibles pour l'utilisateur courant
$posts_visibles = array_filter($posts, fn($post) => utilisateur_peut_voir_enigme($post->ID, $utilisateur_id));
$has_enigmes = !empty($posts_visibles);
?>

<div class="bloc-enigmes-chasse">
  <div class="grille-3">
  <?php foreach ($posts_visibles as $post): ?>
    <?php
    $enigme_id = $post->ID;
    $titre = get_the_title($enigme_id);
    $etat_systeme = enigme_get_etat_systeme($enigme_id);
    $statut_utilisateur = enigme_get_statut_utilisateur($enigme_id, $utilisateur_id);
    $cta = get_cta_enigme($enigme_id);

    $roles = wp_get_current_user()->roles;
    $est_orga = array_intersect($roles, [ROLE_ORGANISATEUR, ROLE_ORGANISATEUR_CREATION]);
    $voir_bordure = !empty($est_orga) && utilisateur_est_organisateur_associe_a_chasse($utilisateur_id, $chasse_id);
    $classe_completion = '';
    if ($voir_bordure) {
      verifier_ou_mettre_a_jour_cache_complet($enigme_id);
      $complet = (bool) get_field('enigme_cache_complet', $enigme_id);
      $classe_completion = $complet ? 'carte-complete' : 'carte-incomplete';
    }
    ?>
    <article class="carte carte-enigme <?= esc_attr($classe_completion); ?>">
      <div class="carte-core">
        <div class="carte-enigme-image">
          <?php afficher_picture_vignette_enigme($enigme_id, 'Vignette de l’énigme'); ?>
          <div class="carte-enigme-cta">
            <?php render_cta_enigme($cta, $enigme_id); ?>
          </div>
        </div>
        <h3><?= esc_html($titre); ?></h3>
      </div>
    </article>

  <?php endforeach; ?>

  <?php
  if (utilisateur_peut_ajouter_enigme($chasse_id, $utilisateur_id)) {
    verifier_ou_mettre_a_jour_cache_complet($chasse_id);
    $complete = (bool) get_field('chasse_cache_complet', $chasse_id);
    get_template_part('template-parts/enigme/chasse-partial-ajout-enigme', null, [
      'has_enigmes' => $has_enigmes,
      'chasse_id'   => $chasse_id,
      'disabled'    => !$complete,
    ]);
  }
  ?>
</div>
  </div>
