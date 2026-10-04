<?php
// Template principal pour les pages "Mon Compte".
// Ce fichier délègue l'affichage commun au layout unifié.

defined('ABSPATH') || exit;

// S'assure que la variable globale est définie.
$GLOBALS['myaccount_content_template'] = $GLOBALS['myaccount_content_template'] ?? null;

$current_user = wp_get_current_user();
$theme_dir    = get_stylesheet_directory() . '/templates/myaccount/';

if ($current_user->ID && function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('tentatives')) {
    $GLOBALS['myaccount_content_template'] = $theme_dir . 'content-tentatives.php';
} elseif ($current_user->ID && !is_wc_endpoint_url()) {
    $roles = (array) $current_user->roles;
    if (in_array('administrator', $roles, true)) {
        $GLOBALS['myaccount_content_template'] = $theme_dir . 'content-dashboard-admin.php';
    } elseif (array_intersect(['organisateur', 'organisateur_creation'], $roles)) {
        $GLOBALS['myaccount_content_template'] = $theme_dir . 'content-dashboard-organisateur.php';
    } else {
        $GLOBALS['myaccount_content_template'] = $theme_dir . 'content-dashboard-joueur.php';
    }
}

include get_stylesheet_directory() . '/templates/myaccount/layout.php';
