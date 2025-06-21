<?php
defined('ABSPATH') || exit;

$post_id = $args['post_id'] ?? null;
if (!$post_id) {
  error_log("[images] ❌ post_id manquant dans partial");
  return;
}

error_log("[images] ✅ appel afficher_picture_vignette_enigme pour post #$post_id");
?>

<div class="image-principale">
  <?php afficher_picture_vignette_enigme($post_id, 'Image principale de l’énigme'); ?>
</div>
