<?php
defined('ABSPATH') || exit;

$post_id = $args['post_id'] ?? null;
if (!$post_id) {
  error_log("[images] ❌ post_id manquant dans partial");
  return;
}

// Récupération standard des images (format tableau ACF avec clés 'ID', etc.)
$images = get_field('enigme_visuel_image', $post_id);
error_log("[images] 🔍 Énigme #$post_id → images récupérées : " . print_r($images, true));

// Test : au moins une image != placeholder
$has_valid_images = is_array($images) && array_filter($images, function ($img) {
  return isset($img['ID']) && (int) $img['ID'] !== ID_IMAGE_PLACEHOLDER_ENIGME;
});

if ($has_valid_images && function_exists('afficher_visuels_enigme')) {
  error_log("[images] ✅ Galerie active pour #$post_id");
  ?>
  <div class="galerie-enigme-wrapper">
    <?php afficher_visuels_enigme($post_id); ?>
  </div>
  <?php
} else {
  error_log("[images] 🟡 Aucune image valide → fallback picture");
  ?>
  <div class="image-principale">
    <?php afficher_picture_vignette_enigme($post_id, 'Image par défaut de l’énigme'); ?>
  </div>
  <?php
}
