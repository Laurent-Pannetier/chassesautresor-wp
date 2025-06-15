<?php
/**
 * Template Name: Traitement Réponse (Confirmation + Statistiques)
 */

if (!function_exists('get_field')) {
  require_once(ABSPATH . 'wp-load.php');
}

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

$user_id = isset($tentative->user_id) ? (int)$tentative->user_id : 0;
$enigme_id = isset($tentative->enigme_id) ? (int)$tentative->enigme_id : 0;
$current_user_id = get_current_user_id();

$chasse_raw = get_field('enigme_chasse_associee', $enigme_id, false);
error_log('LOG get_field enigme_chasse_associee (enigme_id=' . $enigme_id . ') [2nd call]: ' . print_r($chasse_raw, true));

if (is_array($chasse_raw)) {
  $first = reset($chasse_raw);
  $chasse_id = is_object($first) ? (int) $first->ID : (int) $first;
} elseif (is_object($chasse_raw)) {
  $chasse_id = (int) $chasse_raw->ID;
} elseif (is_numeric($chasse_raw)) {
  $chasse_id = (int) $chasse_raw;
} else {
  $chasse_id = 0;
}

$organisateur_id = $chasse_id ? get_organisateur_from_chasse($chasse_id) : null;
$organisateur_user_ids_raw = $organisateur_id ? get_field('utilisateurs_associes', $organisateur_id) : [];
if ($organisateur_id) {
  error_log('LOG get_field utilisateurs_associes (organisateur_id=' . $organisateur_id . '): ' . print_r($organisateur_user_ids_raw, true));
}

if (!is_array($organisateur_user_ids_raw)) {
  $organisateur_user_ids_raw = [$organisateur_user_ids_raw];
}

$organisateur_user_ids = [];
foreach ($organisateur_user_ids_raw as $item) {
  $organisateur_user_ids[] = is_object($item) ? (int) $item->ID : (int) $item;
}

if (!current_user_can('manage_options') && (!is_array($organisateur_user_ids) || !in_array($current_user_id, $organisateur_user_ids))) {
  wp_die('Accès interdit : vous ne pouvez pas traiter cette tentative.');
}

$statuts_table = $wpdb->prefix . 'enigme_statuts_utilisateur';
$new_statut = ($resultat === 'bon') ? 'resolue' : 'abandonnee';

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

$total_chasse = 0;
if ($chasse_id) {
  $nom_user = get_userdata($user_id)?->display_name ?? "Utilisateur inconnu";
  $titre_enigme = get_the_title($enigme_id);
  if (!is_string($titre_enigme) || empty($titre_enigme)) {
    $titre_enigme = '';
  }

  $ids_enigmes = get_posts([
    'post_type' => 'enigme',
    'fields' => 'ids',
    'posts_per_page' => -1,
    'meta_query' => [[
      'key' => 'enigme_chasse_associee',
      'value' => $chasse_id,
      'compare' => '=',
    ]]
  ]);

  if ($ids_enigmes) {
    $in_clause = implode(',', array_map('absint', $ids_enigmes));
    $total_chasse = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE enigme_id IN ($in_clause)");
  }
}

$nom_user = get_userdata($user_id)?->display_name ?? "Utilisateur inconnu";
$titre_enigme = get_the_title($enigme_id);
if (!is_string($titre_enigme) || empty($titre_enigme)) {
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
