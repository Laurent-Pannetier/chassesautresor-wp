<?php
defined('ABSPATH') || exit;

$post_id = $args['post_id'] ?? null;
if (!$post_id) {
    cat_debug('[images] ❌ post_id manquant dans partial');
    return;
}

$user_id = isset($args['user_id']) ? (int) $args['user_id'] : (int) get_current_user_id();

cat_debug('[images] ✅ Galerie active pour #' . $post_id);

afficher_visuels_enigme((int) $post_id, $user_id);

