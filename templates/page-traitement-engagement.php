<?php

/**
 * Template Name: Traitement Engagement
 * Route d’engagement – appelée uniquement via POST
 */

defined('ABSPATH') || exit;

$current_user_id = (int) get_current_user_id();
$chasse_id = isset($_POST['chasse_id']) ? intval($_POST['chasse_id']) : 0;

// --------------------------------------------------
// 🎯 Traitement engagement chasse
// --------------------------------------------------
if ($current_user_id > 0 && $chasse_id > 0) {
    require_once get_theme_file_path('inc/chasse-functions.php');

    $cout_points = (int) get_field('chasse_infos_cout_points', $chasse_id);
    $nonce = isset($_POST['engager_chasse_nonce']) ? (string) $_POST['engager_chasse_nonce'] : '';
    $result = (new ChassesAuTresor\Core\Progress\HuntEngagementApplicationService())->engage(
        $current_user_id,
        $chasse_id,
        (string) get_post_type($chasse_id),
        $nonce !== '' && wp_verify_nonce($nonce, 'engager_chasse_' . $chasse_id) !== false,
        current_user_can('administrator'),
        utilisateur_est_organisateur_associe_a_chasse($current_user_id, $chasse_id),
        $cout_points,
        get_user_points($current_user_id),
        'enregistrer_engagement_chasse',
        static function (int $user_id, int $hunt_id, int $cost): void {
            $reason = sprintf(
                __('Déblocage de la chasse #%d', 'chassesautresor-com'),
                $hunt_id
            );
            deduire_points_utilisateur($user_id, $cost, $reason, 'chasse', $hunt_id);
        }
    );

    if ($result['status'] === 'invalid_nonce') {
        wp_die(__('Échec de vérification de sécurité', 'chassesautresor-com'));
    }
    if ($result['status'] === 'points_insuffisants') {
        wp_safe_redirect(
            add_query_arg('erreur', 'points_insuffisants', get_permalink($chasse_id))
        );
        exit;
    }
    if ($result['status'] === 'engagement_failed') {
        wp_safe_redirect(
            add_query_arg('erreur', 'engagement', get_permalink($chasse_id))
        );
        exit;
    }
    if ($result['status'] === 'success') {
        wp_safe_redirect(get_permalink($chasse_id));
        exit;
    }
}

wp_redirect(home_url());
exit;
