<?php

/**
 * Template Name: Traitement Réponse (Finalisation Sécurisée)
 */

require_once get_stylesheet_directory() . '/inc/enigme-functions.php';

$uid = sanitize_text_field($_GET['uid'] ?? '');
$resultat_param = sanitize_text_field($_GET['resultat'] ?? '');

// 🛑 Vérification de base
if (!$uid || !in_array($resultat_param, ['bon', 'faux'], true)) {
  wp_die('Paramètres manquants ou invalides.');
}

// 🧩 Récupération de la tentative
$tentative = get_tentative_by_uid($uid);
if (!$tentative) wp_die('Tentative introuvable.');

$enigme_id = (int) $tentative->enigme_id;

// 🔐 Vérification des droits (admin ou organisateur associé)
$chasse_id = recuperer_id_chasse_associee($enigme_id);
$organisateur_id = get_organisateur_from_chasse($chasse_id);
$organisateur_user_ids = (array) get_field('utilisateurs_associes', $organisateur_id);
$current_user_id = get_current_user_id();

if (
  !current_user_can('manage_options') &&
  !in_array($current_user_id, array_map('intval', $organisateur_user_ids), true)
) {
  wp_die('⛔ Accès refusé : vous n’êtes pas autorisé à traiter cette tentative.');
}

// 🧹 Gestion réinitialisations (facultatif)
global $wpdb;

if (isset($_GET['reset_tentatives'])) {
  $reset = $wpdb->delete(
    $wpdb->prefix . 'enigme_statuts_utilisateur',
    ['enigme_id' => $enigme_id],
    ['%d']
  );
  echo '<p style="text-align:center;">🧹 ' . $reset . ' statut(s) utilisateur supprimé(s).</p>';
  return;
}

if (isset($_GET['reset_tentatives_totales'])) {
  $reset1 = $wpdb->delete($wpdb->prefix . 'enigme_tentatives', ['enigme_id' => $enigme_id], ['%d']);
  $reset2 = $wpdb->delete($wpdb->prefix . 'enigme_statuts_utilisateur', ['enigme_id' => $enigme_id], ['%d']);
  echo '<p style="text-align:center;">🚫 ' . $reset1 . ' tentative(s) et ' . $reset2 . ' statut(s) supprimé(s).</p>';
  return;
}

// 🔁 Traitement réel de la tentative
$traitement_effectue = traiter_tentative_manuelle($uid, $resultat_param);

// 🔎 Lecture post-traitement
$infos = recuperer_infos_tentative($uid);

// ✅ Marque explicitement si le traitement a été fait à l’instant
$infos['vient_d_etre_traitee'] = $traitement_effectue;

// 🐛 Log de contrôle
error_log("🧪 traitement_effectue = " . ($traitement_effectue ? 'true' : 'false'));
error_log("🧪 resultat enregistré = " . ($infos['resultat'] ?? 'null'));
error_log("🧪 etat_tentative = " . ($infos['etat_tentative'] ?? 'null'));
error_log("🧪 vient_d_etre_traitee = " . ($infos['vient_d_etre_traitee'] ? 'true' : 'false'));



get_template_part('template-parts/traitement/tentative-feedback', null, [
  'etat_tentative'       => $infos['etat_tentative'] ?? 'invalide',
  'resultat'             => $infos['resultat'] ?? '',
  'statut_initial'       => $infos['statut_initial'] ?? '',
  'statut_final'         => $infos['statut_final'] ?? '',
  'nom_user'             => $infos['nom_user'] ?? '',
  'permalink'            => $infos['permalink'] ?? '',
  'statistiques'         => $infos['statistiques'] ?? [],
  'deja_traitee'         => $infos['deja_traitee'] ?? false,
  'traitee'              => $infos['traitee'] ?? false,
  'vient_d_etre_traitee' => $infos['vient_d_etre_traitee'] ?? false, // 🟢 LE VOICI !
]);
