<?php

/**
 * Template Name: Traitement Engagement Énigme
 * Route d’engagement – appelée uniquement via POST
 */

defined('ABSPATH') || exit;

$current_user_id = get_current_user_id();
if (!$current_user_id) {
  wp_redirect(home_url());
  exit;
}

$enigme_id = isset($_POST['enigme_id']) ? intval($_POST['enigme_id']) : 0;

if (!$enigme_id || get_post_type($enigme_id) !== 'enigme') {
  wp_redirect(home_url());
  exit;
}

// Vérification du nonce
if (
  !isset($_POST['engager_enigme_nonce']) ||
  !wp_verify_nonce($_POST['engager_enigme_nonce'], 'engager_enigme_' . $enigme_id)
) {
  wp_die('Échec de vérification de sécurité');
}

// Chargement des fonctions critiques
require_once get_theme_file_path('inc/statut-functions.php');

// Vérifier si l’énigme est engageable
$etat_systeme = enigme_get_etat_systeme($enigme_id);
$statut_utilisateur = enigme_get_statut_utilisateur($enigme_id, $current_user_id);

if ($etat_systeme !== 'accessible' || $statut_utilisateur !== 'non_commencee') {
  wp_redirect(get_permalink($enigme_id)); // Redirection silencieuse
  exit;
}

// Lecture du coût
$groupe_tentative = get_field('enigme_tentative', $enigme_id);
$cout_points = intval($groupe_tentative['enigme_tentative_cout_points'] ?? 0);

// Vérification des points
if (!utilisateur_a_assez_de_points($current_user_id, $cout_points)) {
  $chasse_id = recuperer_id_chasse_associee($enigme_id);
  $url = $chasse_id ? get_permalink($chasse_id) : home_url('/');
  $url = add_query_arg('erreur', 'points_insuffisants', $url);
  wp_redirect($url);
  exit;
}


// Déduction + enregistrement du statut
deduire_points_utilisateur($current_user_id, $cout_points);
marquer_enigme_comme_engagee($current_user_id, $enigme_id);

// Redirection vers la page de l’énigme
wp_redirect(get_permalink($enigme_id));
exit;
