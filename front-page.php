<?php
/**
 * Front page router for the configured public site experience.
 */

defined('ABSPATH') || exit;

if (!function_exists('cat_is_single_hunt_mode') || !cat_is_single_hunt_mode()) {
    get_template_part('template-parts/home/platform');
    return;
}

$audience = function_exists('cat_single_hunt_home_audience')
    ? cat_single_hunt_home_audience()
    : 'presentation';

if ($audience === 'presentation') {
    get_template_part('template-parts/home/single-hunt');
    return;
}

$cockpitTemplates = [
    'admin' => 'content-dashboard-admin.php',
    'organisateur' => 'content-dashboard-organisateur.php',
    'joueur' => 'content-dashboard-joueur.php',
];
$cockpitFile = $cockpitTemplates[$audience] ?? 'content-dashboard-joueur.php';
$GLOBALS['myaccount_content_template'] = get_stylesheet_directory() . '/templates/myaccount/' . $cockpitFile;
$GLOBALS['cat_account_home_on_front'] = true;

include get_stylesheet_directory() . '/templates/myaccount/layout.php';
