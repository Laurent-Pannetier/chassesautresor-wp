<?php
/**
 * Template Name: Traitement Réponse (Finalisation Sécurisée)
 */

require_once get_stylesheet_directory() . '/inc/enigme-functions.php';

$uid = sanitize_text_field($_GET['uid'] ?? '');
$resultat_param = sanitize_text_field($_GET['resultat'] ?? '');

$tentative = get_tentative_by_uid($uid);
if (!$tentative) wp_die('Tentative introuvable.');

$enigme_id = (int) $tentative->enigme_id;
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

if (!$uid || !in_array($resultat_param, ['bon', 'faux'], true)) {
  wp_die('Paramètres manquants ou invalides.');
}

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

$traitement = traiter_tentative_manuelle($uid, $resultat_param);

if (!empty($traitement['erreur'])) {
    wp_die($traitement['erreur']);
}

$etat = $traitement['etat_tentative'] ?? 'invalide';

get_template_part('template-parts/traitement/tentative-feedback', null, [
  'etat_tentative'       => $etat,
  'resultat'             => $traitement['resultat'] ?? '',
  'statut_initial'       => $traitement['statut_initial'] ?? '',
  'statut_final'         => $traitement['statut_final'] ?? '',
  'nom_user'             => $traitement['nom_user'] ?? '',
  'permalink'            => $traitement['permalink'] ?? '',
  'statistiques'         => $traitement['statistiques'] ?? [],
  'deja_traitee'         => $traitement['deja_traitee'] ?? false,
]);