<?php
defined('ABSPATH') || exit;

defined('ABSPATH') || exit;

$post_id = $args['post_id'] ?? null;
$user_id = $args['user_id'] ?? get_current_user_id(); // ✅ sécurisation

error_log("👤 STATUT ACTUEL : " . enigme_get_statut_utilisateur($post_id, $user_id));


if (!$post_id || !$user_id) return;

if (!utilisateur_peut_repondre_manuelle($user_id, $post_id)) {
  echo '<p class="message-deja-repondu">Vous avez déjà répondu ou résolu cette énigme.</p>';
  return;
}

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