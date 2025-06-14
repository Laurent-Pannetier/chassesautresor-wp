<?php
defined('ABSPATH') || exit;

$post_id = $args['post_id'] ?? null;
if (!$post_id) return;

// Récupération du mode de validation
$mode_validation = get_field('enigme_mode_validation', $post_id);
if (!in_array($mode_validation, ['automatique', 'manuelle'])) return;

// Préparer les infos sur les tentatives
$tentative = get_field('enigme_tentative', $post_id);
$cout = (int) ($tentative['enigme_tentative_cout_points'] ?? 0);
$max = (int) ($tentative['enigme_tentative_max'] ?? 0);

// Préparer le label selon le mode
$label = $mode_validation === 'automatique' ? 'Réponse attendue :' : 'Votre réponse :';
?>

<div class="bloc-reponse">
  <?= do_shortcode('[formulaire_reponse_manuelle id="' . esc_attr($post_id) . '"]'); ?>
</div>