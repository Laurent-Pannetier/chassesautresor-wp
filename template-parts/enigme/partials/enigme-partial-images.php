<?php
defined('ABSPATH') || exit;

$post_id = $args['post_id'] ?? null;
if (!$post_id) {
  error_log("[images] ❌ post_id manquant dans partial");
  return;
}

$images = get_field('enigme_visuel_image', $post_id); // ❌ PAS de troisième paramètre

error_log("[images] 🔍 Énigme #$post_id → images récupérées : " . print_r($images, true));

$has_valid_images = is_array($images) && array_filter($images, fn($img) => (int) ($img['ID'] ?? 0) !== ID_IMAGE_PLACEHOLDER_ENIGME);


if ($has_valid_images) {
  error_log("[images] ✅ images valides détectées pour #$post_id");

  if (function_exists('afficher_visuels_enigme')) {
    error_log("[images] 🧩 appel afficher_visuels_enigme(#$post_id)");
    afficher_visuels_enigme($post_id);
  } else {
    error_log("[images] ⚠️ fonction afficher_visuels_enigme() introuvable");
  }

} else {
  error_log("[images] 🟡 aucune image valide pour #$post_id, fallback placeholder");
  echo '<div class="image-placeholder">';
  echo wp_get_attachment_image(get_image_enigme($post_id, 'large'), 'large', false, [
    'alt' => 'Image principale de l’énigme',
  ]);
  echo '</div>';
}
