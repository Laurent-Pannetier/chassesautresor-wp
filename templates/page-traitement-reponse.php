<?php

/**
 * Template Name: Traitement Réponse (Confirmation + Statistiques)
 */

// Inclure les fonctions WordPress si besoin (utile pour l'exécution hors du contexte WP)
if (!function_exists('get_field')) {
  require_once(ABSPATH . 'wp-load.php');
}

// Activer le rapport d'erreurs pour le debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

defined('ABSPATH') || exit;

$uid = sanitize_text_field($_GET['uid'] ?? '');
$resultat = sanitize_text_field($_GET['resultat'] ?? '');

if (!$uid || !in_array($resultat, ['bon', 'faux'], true)) {
  wp_die('Paramètres manquants ou invalides.');
}

global $wpdb;
$table = $wpdb->prefix . 'enigme_tentatives';

$tentative = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE tentative_uid = %s", $uid));

// Récupérer les IDs nécessaires depuis la tentative
$user_id = isset($tentative->user_id) ? (int)$tentative->user_id : 0;
$enigme_id = isset($tentative->enigme_id) ? (int)$tentative->enigme_id : 0;
$current_user_id = get_current_user_id();

// 🟢 Réutiliser le chasse_id déjà calculé plus haut
} elseif (is_object($chasse_raw)) {
  $chasse_id = (int) $chasse_raw->ID;
} else {
  $chasse_id = (int) $chasse_raw;
}
$total_chasse = 0;

if ($chasse_id) {
  $nom_user = get_userdata($user_id)?->display_name ?? "Utilisateur inconnu";
  $titre_enigme = get_the_title($enigme_id) ?? '';
  if (!is_string($titre_enigme)) {
  $titre_enigme = '';
  }

  // Récupérer les IDs des énigmes associées à la chasse
  $ids_enigmes = get_posts([
  'post_type' => 'enigme',
  'fields' => 'ids',
  'posts_per_page' => -1,
  'meta_query' => [
    [
    'key' => 'enigme_chasse_associee',
    'value' => $chasse_id,
    'compare' => '=',
    ]
  ]
  ]);

  if ($ids_enigmes) {
  $in_clause = implode(',', array_map('absint', $ids_enigmes));
  $total_chasse = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE enigme_id IN ($in_clause)");
  }
}

$nom_user = get_userdata($user_id)?->display_name ?? "Utilisateur inconnu";
$titre_enigme = get_the_title($enigme_id) ?? '';
if (!is_string($titre_enigme)) {
  $titre_enigme = '';
}

$url_enigme = get_permalink($enigme_id) . '?statistiques=1';

envoyer_mail_resultat_joueur($user_id, $enigme_id, $resultat);

?>

<div style="max-width:600px;margin:3em auto;text-align:center;font-family:sans-serif;">
  <?php $site_icon_url = get_site_icon_url(96); ?>
  <img src="<?= esc_url($site_icon_url ?: ''); ?>" alt="Logo" style="margin-bottom:1em; width:48px; height:48px;">

  <p style="font-size:1.3em;">
  <?= $resultat === 'bon' ? '✅' : '❌'; ?> La réponse a bien été <strong><?= $resultat === 'bon' ? 'validée' : 'refusée'; ?></strong>.
  </p>

  <div style="margin-top:2em;font-size:1em;">
  <p>📌 Tentative <strong><?= $total_user; ?></strong> de <strong><?= esc_html($nom_user); ?></strong></p>
  <p>📊 Tentative <strong><?= $total_enigme; ?></strong> sur l’énigme <strong><?= esc_html($titre_enigme); ?></strong></p>
  <?php if ($total_chasse): ?>
    <p>🧩 Tentative <strong><?= $total_chasse; ?></strong> sur l’ensemble de la chasse</p>
  <?php endif; ?>
  </div>

  <div style="margin-top:3em;">
  <a href="#" onclick="window.close();" style="margin-right:1em;">❎ Fermer cette fenêtre</a>
  <a href="<?= esc_url($url_enigme); ?>" style="background:#0073aa;padding:10px 20px;border-radius:5px;color:white;text-decoration:none;">🔍 Voir cette énigme</a>
  </div>
</div>
