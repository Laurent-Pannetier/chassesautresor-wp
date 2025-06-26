<?php
/**
 * Template Name: Traitement Validation Chasse
 */

defined('ABSPATH') || exit;

require_once get_theme_file_path('inc/chasse-functions.php');
require_once get_theme_file_path('inc/statut-functions.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    wp_redirect(home_url());
    exit;
}

$user_id = get_current_user_id();
$chasse_id = isset($_POST['chasse_id']) ? intval($_POST['chasse_id']) : 0;

if (!$user_id || !$chasse_id || get_post_type($chasse_id) !== 'chasse') {
    wp_redirect(home_url());
    exit;
}

if (
    !isset($_POST['validation_chasse_nonce']) ||
    !wp_verify_nonce($_POST['validation_chasse_nonce'], 'validation_chasse_' . $chasse_id)
) {
    wp_die('Vérification de sécurité échouée.');
}

if (!peut_valider_chasse($chasse_id, $user_id)) {
    wp_die('Conditions non remplies.');
}

forcer_statut_apres_acf($chasse_id, 'en_attente');

// Met à jour le statut métier pour refléter l'attente de validation
update_field('champs_caches_chasse_cache_statut', 'en_attente', $chasse_id);

wp_redirect(add_query_arg('validation_demandee', '1', get_permalink($chasse_id)));
exit;
