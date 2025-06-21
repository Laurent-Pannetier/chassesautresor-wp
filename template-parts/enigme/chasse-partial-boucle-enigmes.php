<?php
defined('ABSPATH') || exit;

$chasse_id = $args['chasse_id'] ?? null;
if (!$chasse_id || get_post_type($chasse_id) !== 'chasse') return;

$utilisateur_id = get_current_user_id();

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
        <?php afficher_picture_vignette_enigme($enigme_id, 'Vignette de l’énigme'); ?>
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
  if (utilisateur_peut_ajouter_enigme($chasse_id, $utilisateur_id)) {
    get_template_part('template-parts/enigme/chasse-partial-ajout-enigme', null, [
      'has_enigmes' => $has_enigmes,
      'chasse_id'   => $chasse_id,
    ]);
  }
  ?>
</div>
