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

$chasse_raw = get_field('enigme_chasse_associee', $enigme_id, false);
// Log pour debug
error_log('DEBUG $chasse_raw: ' . print_r($chasse_raw, true));
if (is_array($chasse_raw)) {
  $first      = reset($chasse_raw);
  error_log('DEBUG $first: ' . print_r($first, true));
  $chasse_id  = is_object($first) ? (int) $first->ID : (int) $first;
} elseif (is_object($chasse_raw)) {
  $chasse_id  = (int) $chasse_raw->ID;
} else {
  $chasse_id  = (int) $chasse_raw;
}

$organisateur_id    = $chasse_id ? get_organisateur_from_chasse($chasse_id) : null;
$organisateur_user_ids_raw = $organisateur_id ? get_field('utilisateurs_associes', $organisateur_id) : [];
$organisateur_user_ids = [];
if (is_array($organisateur_user_ids_raw)) {
  foreach ($organisateur_user_ids_raw as $item) {
    $organisateur_user_ids[] = is_object($item) ? (int) $item->ID : (int) $item;
  }
}
error_log('DEBUG $organisateur_user_ids: ' . print_r($organisateur_user_ids, true));

if (
  !current_user_can('manage_options') &&
  (!is_array($organisateur_user_ids) || !in_array($current_user_id, $organisateur_user_ids))
) {
  wp_die('Accès interdit : vous ne pouvez pas traiter cette tentative.');
}


// ✅ Mettre à jour le statut utilisateur si nécessaire
$statuts_table = $wpdb->prefix . 'enigme_statuts_utilisateur';

// Déterminer le nouveau statut à appliquer
$new_statut = ($resultat === 'bon') ? 'resolue' : 'abandonnee';

// Vérifie si un statut existe déjà pour ce user/enigme
$exists = $wpdb->get_var($wpdb->prepare(
  "SELECT COUNT(*) FROM $statuts_table WHERE user_id = %d AND enigme_id = %d",
  $user_id,
  $enigme_id
));

if ($exists) {
  $statut_actuel = $wpdb->get_var($wpdb->prepare(
    "SELECT statut FROM $statuts_table WHERE user_id = %d AND enigme_id = %d",
    $user_id,
    $enigme_id
  ));

  if ($statut_actuel !== 'resolue') {
    // Ici, vous pouvez mettre à jour le statut si besoin, par exemple :
    $wpdb->update(
      $statuts_table,
      ['statut' => $new_statut],
      ['user_id' => $user_id, 'enigme_id' => $enigme_id],
      ['%s'],
      ['%d', '%d']
    );
  }
}

$total_user = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE user_id = %d AND enigme_id = %d", $user_id, $enigme_id));
$total_enigme = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE enigme_id = %d", $enigme_id));

$chasse_raw = get_field('enigme_chasse_associee', $enigme_id, false);
if (is_array($chasse_raw)) {
  $first     = reset($chasse_raw);
  $chasse_id = is_object($first) ? (int) $first->ID : (int) $first;
} elseif (is_object($chasse_raw)) {
  $chasse_id = (int) $chasse_raw->ID;
} else {
  $chasse_id = (int) $chasse_raw;
}
$total_chasse = 0;

if ($chasse_id) {
  $nom_user = get_userdata($user_id)?->display_name ?? "Utilisateur inconnu";
  error_log('DEBUG $nom_user: ' . print_r($nom_user, true));
  $titre_enigme = get_the_title($enigme_id) ?? '';
  if (!is_string($titre_enigme)) {
    $titre_enigme = '';
  }
  error_log('DEBUG $titre_enigme: ' . print_r($titre_enigme, true));

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