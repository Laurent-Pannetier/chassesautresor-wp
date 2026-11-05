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
$profile_state = cat_is_user_profile_complete($current_user_id);

if (!$profile_state['complete']) {
    $message = cat_get_missing_profile_fields_message($profile_state['missing']);

    myaccount_add_persistent_message(
        $current_user_id,
        'profil_incomplet',
        $message,
        'error',
        false,
        0,
        false,
        null,
        get_user_locale($current_user_id)
    );

    $redirect_url = function_exists('wc_get_account_endpoint_url')
        ? wc_get_account_endpoint_url('edit-account')
        : home_url('/mon-compte/edit-account/');

    wp_safe_redirect($redirect_url);
    exit;
}

myaccount_remove_persistent_message($current_user_id, 'profil_incomplet');
$request_status = cat_get_organisateur_request_status($current_user_id);
$verification_message = __(
    'Un email de confirmation vous a été envoyé. '
    . "Cliquez sur le lien qu'il contient pour valider la création de votre profil organisateur.",
    'chassesautresor-com'
);

if ($request_status['expired']) {
    $verification_message = sprintf(
        '%s %s',
        __('Votre précédente demande avait expiré.', 'chassesautresor-com'),
        $verification_message
    );
}

// 2. Si un profil existe déjà, on n'effectue plus de redirection automatique
// vers l'espace organisateur. Le bouton principal se charge de guider
// l'utilisateur selon l'état de son profil.

// 3. Gestion de la demande en cours
if (isset($_GET['resend'])) {
    renvoyer_email_confirmation_organisateur($current_user_id);
    myaccount_add_persistent_message(
        $current_user_id,
        'profil_verification',
        $verification_message,
        'info',
        true,
        0,
        false,
        null,
        get_user_locale($current_user_id),
        2 * DAY_IN_SECONDS
    );
    $redirect_url = add_query_arg('nocache', (string) time(), home_url('/devenir-organisateur/'));
    wp_redirect($redirect_url);
    exit;
}

if (!empty($request_status['token'])) {
    myaccount_add_persistent_message(
        $current_user_id,
        'profil_verification',
        $verification_message,
        'info',
        true,
        0,
        false,
        'profil_verification',
        get_user_locale($current_user_id),
        2 * DAY_IN_SECONDS
    );
    get_header();
    echo '<p>'
        . esc_html__(
            '⚠️ Une demande de création de profil organisateur est déjà en cours pour ce compte.',
            'chassesautresor-com'
        )
        . '</p>';
    echo '<p><a href="?resend=1">'
        . esc_html__(
            "Renvoyer l'email de confirmation",
            'chassesautresor-com'
        )
        . '</a></p>';
    get_footer();
    exit;
}

// 4. Nouvelle demande
lancer_demande_organisateur($current_user_id);
myaccount_add_persistent_message(
    $current_user_id,
    'profil_verification',
    $verification_message,
    'info',
    true,
    0,
    false,
    null,
    get_user_locale($current_user_id),
    2 * DAY_IN_SECONDS
);
$redirect_url = add_query_arg('nocache', (string) time(), home_url('/devenir-organisateur/'));
wp_redirect($redirect_url);
exit;
