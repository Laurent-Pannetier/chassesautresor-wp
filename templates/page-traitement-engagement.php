<?php

/**
 * Template Name: Traitement Engagement
 * Route d’engagement – appelée uniquement via POST
 */

defined('ABSPATH') || exit;

$current_user_id = get_current_user_id();
if (!$current_user_id) {
    wp_redirect(home_url());
    exit;
}

$chasse_id = isset($_POST['chasse_id']) ? intval($_POST['chasse_id']) : 0;

// --------------------------------------------------
// 🎯 Traitement engagement chasse
// --------------------------------------------------
if ($chasse_id) {
    if (get_post_type($chasse_id) !== 'chasse') {
        wp_redirect(home_url());
        exit;
    }

    if (
        !isset($_POST['engager_chasse_nonce']) ||
        !wp_verify_nonce($_POST['engager_chasse_nonce'], 'engager_chasse_' . $chasse_id)
    ) {
        wp_die(__('Échec de vérification de sécurité', 'chassesautresor-com'));
    }

    require_once get_theme_file_path('inc/chasse-functions.php');

    $cout_points = (int) get_field('chasse_infos_cout_points', $chasse_id);

    if ($cout_points > 0 && !utilisateur_a_assez_de_points($current_user_id, $cout_points)) {
        wp_safe_redirect(
            add_query_arg('erreur', 'points_insuffisants', get_permalink($chasse_id))
        );
        exit;
    }

    $engagement_saved = enregistrer_engagement_chasse($current_user_id, $chasse_id);

    if (!$engagement_saved) {
        wp_safe_redirect(
            add_query_arg('erreur', 'engagement', get_permalink($chasse_id))
        );
        exit;
    }

    if ($cout_points > 0) {
        $reason = sprintf(
            __('Déblocage de la chasse #%d', 'chassesautresor-com'),
            $chasse_id
        );
        deduire_points_utilisateur(
            $current_user_id,
            $cout_points,
            $reason,
            'chasse',
            $chasse_id
        );
    }

    wp_safe_redirect(get_permalink($chasse_id));
    exit;
}

wp_redirect(home_url());
exit;
