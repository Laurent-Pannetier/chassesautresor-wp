<?php
/**
 * Template Name: Créer mon profil
 * Description: Démarre ou renvoie la demande de création d'un profil organisateur.
 */

defined('ABSPATH') || exit;

// 1. Redirection login si non connecté
if (!is_user_logged_in()) {
    wp_redirect(wp_login_url(get_permalink()));
    exit;
}

$current_user_id = get_current_user_id();

// 2. Si un profil existe déjà, redirection automatique
rediriger_selon_etat_organisateur();

// 3. Gestion de la demande en cours
if (isset($_GET['resend'])) {
    renvoyer_email_confirmation_organisateur($current_user_id);
    echo '<p>✉️ Un nouvel email de confirmation a été envoyé.</p>';
    exit;
}

$token = get_user_meta($current_user_id, 'organisateur_demande_token', true);
if ($token) {
    echo '<p>⚠️ Une demande de création de profil organisateur est déjà en cours pour ce compte.</p>';
    echo '<p><a href="?resend=1">Renvoyer l\'email de confirmation</a></p>';
    exit;
}

// 4. Nouvelle demande
lancer_demande_organisateur($current_user_id);
echo '<p>✉️ Un email de vérification vous a été envoyé. Veuillez cliquer sur le lien pour confirmer votre demande.</p>';
exit;
