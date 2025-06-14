<?php
/**
 * Template Name: Créer mon profil
 * Description: Redirige l’utilisateur selon l’état de son profil organisateur.
 */

defined('ABSPATH') || exit;

// 1. Redirection login si non connecté
if (!is_user_logged_in()) {
    wp_redirect(wp_login_url(get_permalink()));
    exit;
}

// 2. Création si aucun CPT n’existe
$current_user_id = get_current_user_id();
$organisateur_id = get_organisateur_from_user($current_user_id);

if (!$organisateur_id) {
    $nouvel_id = creer_organisateur_pour_utilisateur($current_user_id);

    if (!$nouvel_id) {
        wp_redirect(home_url('/erreur-creation-organisateur/'));
        exit;
    }

    // Rechargement pour prise en compte du rôle attribué par le hook
    wp_get_current_user();
}

// 3. Redirection automatique selon état (draft / pending / publish)
rediriger_selon_etat_organisateur();
exit;
