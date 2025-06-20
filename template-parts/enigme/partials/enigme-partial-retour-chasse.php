<?php
defined( 'ABSPATH' ) || exit;

$post_id = $args['post_id'] ?? null;
if (!$post_id) return;

$chasse = get_field('enigme_chasse_associee', $post_id);
$chasse_id = is_array($chasse) ? $chasse[0] ?? null : $chasse;
if (!$chasse_id) return;

$url = get_permalink($chasse_id);
$titre = get_the_title($chasse_id);
?>

<div class="retour-chasse">
  <a href="<?= esc_url($url); ?>" class="bouton-retour-chasse">← Retour à la chasse : <?= esc_html($titre); ?></a>
</div>
