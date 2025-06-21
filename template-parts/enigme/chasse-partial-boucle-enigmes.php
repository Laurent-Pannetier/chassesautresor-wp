<?php
defined('ABSPATH') || exit;

$chasse_id = $args['chasse_id'] ?? null;
if (!$chasse_id || get_post_type($chasse_id) !== 'chasse') return;

// 🔄 Relations : organisateur → utilisateurs
$organisateur_id = recuperer_organisateur_de_chasse($chasse_id);
$autorisations = recuperer_utilisateurs_organisateur($organisateur_id);
$utilisateur_id = get_current_user_id();
$peut_modifier = in_array($utilisateur_id, $autorisations, true);

// 🔎 Récupération large : filtrage visuel ensuite
$posts = get_posts([
  'post_type'      => 'enigme',
  'posts_per_page' => -1,
  'orderby'        => 'menu_order',
  'order'          => 'ASC',
  'post_status'    => ['publish', 'pending', 'draft'],
  'meta_query'     => [[
    'key'     => 'chasse_associee',
    'value'   => $chasse_id,
    'compare' => '='
  ]]
]);

$posts_visibles = [];

foreach ($posts as $post) {
  $etat = enigme_get_etat_systeme($post->ID);

  // Filtrage par statut d'accès
  if (in_array($post->post_status, ['pending', 'draft'], true) && !$peut_modifier) {
    continue; // pas autorisé à voir une énigme en création
  }

  $posts_visibles[] = $post;
}

$has_enigmes = !empty($posts_visibles);
?>

<div class="bloc-enigmes-chasse">
  <?php foreach ($posts_visibles as $post): ?>
    <?php
    $enigme_id = $post->ID;
    $titre = get_the_title($enigme_id);
    $etat_systeme = enigme_get_etat_systeme($enigme_id);
    $statut_utilisateur = enigme_get_statut_utilisateur($enigme_id, $utilisateur_id);
    $cta = get_cta_enigme($enigme_id);
    ?>
    <article class="carte-enigme">
      <div class="carte-enigme-image">
        <?php afficher_picture_vignette_enigme($enigme_id, "Vignette de l’énigme"); ?>
      </div>
      <h3><?= esc_html($titre); ?></h3>
      <p>État système : <strong><?= esc_html($etat_systeme); ?></strong></p>
      <p>Statut joueur : <strong><?= esc_html($statut_utilisateur); ?></strong></p>
      <?php render_cta_enigme($cta, $enigme_id); ?>
    </article>
  <?php endforeach; ?>

  <?php if (!$has_enigmes): ?>
    <p>Aucune énigme pour cette chasse pour le moment.</p>
  <?php endif; ?>

  <?php
  // ➕ Carte d'ajout si autorisé
  if ($peut_modifier) {
    get_template_part('template-parts/enigme/chasse-partial-ajout-enigme', null, [
      'has_enigmes' => $has_enigmes,
      'chasse_id'   => $chasse_id,
    ]);
  }
  ?>
</div>
