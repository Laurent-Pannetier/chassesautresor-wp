<?php
defined('ABSPATH') || exit;

$post_id = $args['post_id'] ?? null;
if (!$post_id) {
  error_log("[images] ❌ post_id manquant dans partial");
  return;
}

if (function_exists('afficher_visuels_enigme')) {
  error_log("[images] ✅ afficher_visuels_enigme appelé pour post #$post_id");
  afficher_visuels_enigme($post_id);
} else {
  error_log("[images] ⚠️ afficher_visuels_enigme non disponible pour #$post_id");
}
