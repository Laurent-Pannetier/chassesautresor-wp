<?php
/**
 * Template Name: Traitement Réponse (Debug Étape 3)
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

error_log("🔍 DEBUG \$uid = $uid, \$resultat = $resultat, user_id = $user_id, enigme_id = $enigme_id");

// --- Bloc 1 : get_field enigme_chasse_associee ---
$chasse_raw = get_field('enigme_chasse_associee', $enigme_id, false);
error_log('🧪 Bloc 1: get_field enigme_chasse_associee: ' . print_r($chasse_raw, true));

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

// --- Bloc 2 : titre et lien de l’énigme ---
$titre = get_the_title($enigme_id);
$permalink = get_permalink($enigme_id);

if (!is_string($titre)) {
  $titre = '';
}
if (!is_string($permalink)) {
  $permalink = '';
}
$permalink .= '?statistiques=1';

error_log('🧪 Bloc 2: titre = ' . print_r($titre, true));
error_log('🧪 Bloc 2: permalink = ' . print_r($permalink, true));

// --- Bloc 3 : nom utilisateur ---
$nom_user = get_userdata($user_id)?->display_name ?? "Utilisateur inconnu";
error_log('🧪 Bloc 3: nom_user = ' . print_r($nom_user, true));

// --- Bloc 4 : get_field utilisateurs_associes + sécurité ---
$organisateur_id = $chasse_id ? get_organisateur_from_chasse($chasse_id) : null;
$organisateur_user_ids_raw = $organisateur_id ? get_field('utilisateurs_associes', $organisateur_id) : [];
error_log('🧪 Bloc 4: utilisateurs_associes brut: ' . print_r($organisateur_user_ids_raw, true));

if (!is_array($organisateur_user_ids_raw)) {
  $organisateur_user_ids_raw = [$organisateur_user_ids_raw];
}

$organisateur_user_ids = [];
foreach ($organisateur_user_ids_raw as $item) {
  $organisateur_user_ids[] = is_object($item) ? (int) $item->ID : (int) $item;
}

$current_user_id = get_current_user_id();
error_log('🧪 Bloc 4: current_user_id = ' . $current_user_id);
error_log('🧪 Bloc 4: organisateur_user_ids = ' . print_r($organisateur_user_ids, true));

$acces_autorise = current_user_can('manage_options') || in_array($current_user_id, $organisateur_user_ids, true);
if (!$acces_autorise) {
  wp_die('Accès interdit à cette tentative.');
}

?>

<div style="max-width:600px;margin:3em auto;text-align:center;font-family:sans-serif;">
  <p>🧪 Debug Étape 3 terminé.</p>
  <p>Utilisateur : <strong><?= esc_html($nom_user); ?></strong></p>
  <p>Énigme : <strong><?= esc_html($titre); ?></strong></p>
  <p><a href="<?= esc_url($permalink); ?>">🔍 Voir cette énigme</a></p>
</div>
