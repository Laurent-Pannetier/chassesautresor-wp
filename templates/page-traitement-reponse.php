<?php
/**
 * Template Name: Traitement Réponse (Minimaliste Debug)
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

error_log("🔍 DEBUG $uid = $uid, $resultat = $resultat, user_id = $user_id, enigme_id = $enigme_id");

// --- Bloc 1 : Test get_field enigme_chasse_associee ---
$chasse_raw = get_field('enigme_chasse_associee', $enigme_id, false);
error_log('🧪 Bloc 1: get_field enigme_chasse_associee: ' . print_r($chasse_raw, true));

// --- Bloc 2 : Test get_permalink et get_the_title ---
$titre = get_the_title($enigme_id);
$permalink = get_permalink($enigme_id);
error_log('🧪 Bloc 2: titre = ' . print_r($titre, true));
error_log('🧪 Bloc 2: permalink = ' . print_r($permalink, true));

// --- Bloc 3 : Aucune autre opération ---

?>

<div style="max-width:600px;margin:3em auto;text-align:center;font-family:sans-serif;">
  <p>Test minimaliste terminé. Consultez les logs pour l'analyse.</p>
</div>
