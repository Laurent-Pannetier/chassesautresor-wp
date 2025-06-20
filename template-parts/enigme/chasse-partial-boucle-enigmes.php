<?php
defined('ABSPATH') || exit;

$chasse_id = $args['chasse_id'] ?? null;
if (!$chasse_id || get_post_type($chasse_id) !== 'chasse') return;

$current_user_id = get_current_user_id();
$current_user = wp_get_current_user();
$is_owner = get_post_field('post_author', $chasse_id) == $current_user_id;
$is_orga_creation = in_array('organisateur_creation', $current_user->roles, true);

// Statuts autorisés selon rôle
$post_status = ['publish'];
if ($is_orga_creation && $is_owner) {
  $post_status = ['publish', 'pending', 'draft'];
}

// Requête WP
$query = new WP_Query([
  'post_type'      => 'enigme',
  'posts_per_page' => -1,
  'orderby'        => 'menu_order',
  'order'          => 'ASC',
  'post_status'    => $post_status,
  'meta_query'     => [[
    'key'     => 'chasse_associee',
    'value'   => $chasse_id,
    'compare' => '='
  ]],
  'author' => ($is_orga_creation ? $current_user_id : '')
]);

$has_enigmes = $query->have_posts();
?>

<div class="bloc-enigmes-chasse">
  <?php if ($has_enigmes): ?>
    <?php while ($query->have_posts()): $query->the_post(); ?>
      <?php
      $enigme_id = get_the_ID();
      if (get_post_type($enigme_id) !== 'enigme') continue;

      $titre = get_the_title($enigme_id);
      $etat_systeme = enigme_get_etat_systeme($enigme_id);
      $statut_utilisateur = enigme_get_statut_utilisateur($enigme_id, $current_user_id);
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
    <?php endwhile; wp_reset_postdata(); ?>
  <?php else: ?>
    <p>Aucune énigme pour cette chasse pour le moment.</p>
  <?php endif; ?>

  <?php
  // Carte d’ajout si modifiable
  if (utilisateur_peut_modifier_post($chasse_id)) {
    get_template_part('template-parts/enigme/chasse-partial-ajout-enigme', null, [
      'has_enigmes' => $has_enigmes,
      'chasse_id'   => $chasse_id,
    ]);
  }
  ?>
</div>
