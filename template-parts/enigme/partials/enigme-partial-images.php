<?php
defined('ABSPATH') || exit;

$post_id = $args['post_id'] ?? null;
if (!$post_id) return;

$images = get_field('enigme_visuel_image', $post_id);

// Si aucune image ou si toutes sont filtrées
$has_valid_images = is_array($images) && array_filter($images, fn($img) => (int) ($img['ID'] ?? 0) !== 3925);

if ($has_valid_images && function_exists('afficher_visuels_enigme')) {
  afficher_visuels_enigme($post_id);
} else {
  echo '<div class="image-placeholder">';
  echo wp_get_attachment_image(get_image_enigme($post_id, 'large'), 'large', false, [
    'alt' => 'Image principale de l’énigme',
  ]);
  echo '</div>';
}
