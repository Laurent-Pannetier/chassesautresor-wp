<?php

defined( 'ABSPATH' ) || exit;

// Récupérer l'utilisateur actuel et ses rôles
$current_user = wp_get_current_user();
$roles_utilisateur = $current_user->roles;

if (isset($_GET['notice']) && $_GET['notice'] === 'profil_verification') {
    echo '<div class="woocommerce-message" role="alert">✉️ Un email de vérification vous a été envoyé. Veuillez cliquer sur le lien pour confirmer votre demande.</div>';
}

if ( in_array('administrator', $roles_utilisateur) ) {
    require 'admin.php';
} elseif ( array_intersect([ROLE_ORGANISATEUR, ROLE_ORGANISATEUR_CREATION], $roles_utilisateur) ) {
    require 'organisateur.php';
} else {
    require 'default.php'; // 🚀 Les abonnés (subscriber) arrivent ici
}
