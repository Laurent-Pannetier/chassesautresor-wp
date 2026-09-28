<?php
defined('ABSPATH') || exit;

$post_id = $args['post_id'] ?? null;
if (!$post_id) {
    cat_debug('[images] ❌ post_id manquant dans partial');
    return;
}

cat_debug('[images] ✅ Galerie active pour #' . $post_id);

afficher_visuels_enigme($post_id);

