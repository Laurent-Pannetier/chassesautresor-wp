<?php

defined( 'ABSPATH' ) || exit;

// Récupérer l'utilisateur actuel et ses rôles
$current_user = wp_get_current_user();
$roles_utilisateur = $current_user->roles;

if ( in_array('administrator', $roles_utilisateur) ) {
    require 'admin.php';
} elseif ( array_intersect(['organisateur', 'organisateur_creation'], $roles_utilisateur) ) {
    require 'organisateur.php';
} else {
    require 'default.php'; // 🚀 Les abonnés (subscriber) arrivent ici
}
