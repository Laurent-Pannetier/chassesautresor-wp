<?php
defined('ABSPATH') || exit;
/**
 * Template part : Boucle des énigmes liées à une chasse
 *
 * Contexte attendu :
 * - $args['chasse_id'] (int) : ID de la chasse concernée
 */

$chasse_id = $args['chasse_id'] ?? get_the_ID();

$enigmes = get_posts([
  'post_type'      => 'enigme',
  'post_status'    => 'publish',
  'posts_per_page' => -1,
  'orderby'        => 'menu_order',
  'order'          => 'ASC',
  'meta_query'     => [
    [
      'key'     => 'enigme_chasse_associee',
      'value'   => $chasse_id,
      'compare' => '=',
    ]
  ],
]);

$has_enigmes = !empty($enigmes);
?>

<div class="chasse-enigmes-liste">
  <?php if ($has_enigmes): ?>
    <?php foreach ($enigmes as $enigme): ?>
      <div class="carte-enigme">
        <p>Énigme : <?php echo esc_html(get_the_title($enigme)); ?></p>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <?php
  // Carte d’ajout d’énigme (même modèle quelle que soit la situation)
  get_template_part('template-parts/enigme/carte-ajout-enigme', null, [
    'has_enigmes' => $has_enigmes,
  ]);
  ?>
</div>
