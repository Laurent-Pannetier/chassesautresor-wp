<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * Theme handles that used to print on every screen.
 *
 * @return array<string, bool> Handle => whether this request needs it.
 */
function cta_script_loading_decisions(array $context): array
{
    $loggedIn = !empty($context['logged_in']);
    $isAdministrator = !empty($context['is_administrator']);
    $isAccount = !empty($context['is_account_page']);
    $isFront = !empty($context['is_front_page']);
    $singleHunt = !empty($context['single_hunt_mode']);
    $audience = (string) ($context['single_hunt_audience'] ?? '');
    $adminCockpit = $isFront && $audience === 'admin';
    $reorderScreen = !empty($context['is_singular_chasse']) || ($isFront && $singleHunt);

    return [
        'validation-admin' => $isAdministrator && ($isAccount || $adminCockpit),
        'validation-chasse' => !empty($context['has_dismissible_site_message'])
            || ($loggedIn && $isAccount),
        'tri-organisateurs' => $isAdministrator && $isAccount,
        'organisateurs-pager' => $isAdministrator && $isAccount,
        'enigme-cards-reorder' => !empty($context['can_reorder_hunt']) && $reorderScreen,
        'table-search' => ($isFront && !$singleHunt) || !empty($context['is_tentatives']),
        'badge-validation-tooltip' => !empty($context['shows_validation_badge']),
    ];
}

/**
 * WooCommerce cart, fragments and order-attribution handles limited to the store.
 *
 * @return list<string>
 */
function cta_commerce_script_handles(): array
{
    return [
        'wc-cart-fragments',
        'wc-add-to-cart',
        'astra-mobile-cart',
        'sourcebuster-js',
        'wc-order-attribution',
    ];
}

/**
 * Shop archives, product pages, cart, checkout and the account area.
 *
 * @param array<string, bool> $flags
 */
function cta_screen_keeps_commerce_scripts(array $flags): bool
{
    foreach (['is_woocommerce', 'is_cart', 'is_checkout', 'is_account_page'] as $flag) {
        if (!empty($flags[$flag])) {
            return true;
        }
    }

    return false;
}

/**
 * Whether the current request is a store screen that still needs cart scripts.
 */
function cta_is_store_screen(): bool
{
    $flags = [];
    foreach (['is_woocommerce', 'is_cart', 'is_checkout', 'is_account_page'] as $check) {
        $flags[$check] = function_exists($check) && $check();
    }

    return cta_screen_keeps_commerce_scripts($flags);
}

/**
 * Drop cart, fragment and attribution scripts outside the store.
 */
function cta_limit_commerce_scripts(): void
{
    if (!function_exists('wp_dequeue_script') || cta_is_store_screen()) {
        return;
    }

    foreach (cta_commerce_script_handles() as $handle) {
        wp_dequeue_script($handle);
    }
}

/**
 * Register the storefront dequeue. Called from functions.php after this file loads.
 */
function cta_register_script_loading_hooks(): void
{
    add_action('wp_enqueue_scripts', 'cta_limit_commerce_scripts', 100);
    // Footer scripts can still be queued by the cart widget while the header renders.
    add_action('wp_footer', 'cta_limit_commerce_scripts', 19);
}

/**
 * Screen and role facts used to decide which theme scripts to print.
 *
 * @return array<string, bool|string>
 */
function cta_script_loading_context(): array
{
    $loggedIn = function_exists('is_user_logged_in') && is_user_logged_in();
    $isFront = function_exists('is_front_page') && is_front_page();
    $singleHunt = function_exists('cat_is_single_hunt_mode') && cat_is_single_hunt_mode();
    $audience = 'presentation';
    if ($isFront && $singleHunt && function_exists('cat_single_hunt_home_audience')) {
        $audience = cat_single_hunt_home_audience();
    }

    $isAccount = function_exists('is_account_page') && is_account_page();
    $isTentatives = $loggedIn
        && function_exists('is_wc_endpoint_url')
        && is_wc_endpoint_url('tentatives');

    return [
        'logged_in' => $loggedIn,
        'is_administrator' => cta_script_loading_user_is_administrator(),
        'is_account_page' => $isAccount,
        'is_front_page' => $isFront,
        'is_singular_chasse' => function_exists('is_singular') && is_singular('chasse'),
        'single_hunt_mode' => $singleHunt,
        'single_hunt_audience' => $audience,
        'can_reorder_hunt' => cta_script_loading_user_can_reorder_hunt(),
        'has_dismissible_site_message' => cta_script_loading_has_dismissible_site_message(),
        'is_tentatives' => $isTentatives,
        'shows_validation_badge' => cta_script_loading_enigme_shows_validation_badge(),
    ];
}

/**
 * Administrators are the only role that sees the moderation queue and organizer table.
 */
function cta_script_loading_user_is_administrator(): bool
{
    if (!function_exists('is_user_logged_in') || !is_user_logged_in()) {
        return false;
    }
    if (!function_exists('wp_get_current_user')) {
        return false;
    }

    $user = wp_get_current_user();
    $roles = is_object($user) ? (array) ($user->roles ?? []) : [];

    return in_array('administrator', $roles, true);
}

/**
 * Drag-and-drop is only rendered for an editor while the hunt is still editable.
 */
function cta_script_loading_user_can_reorder_hunt(): bool
{
    if (!function_exists('is_user_logged_in') || !is_user_logged_in()) {
        return false;
    }

    $huntId = 0;
    if (function_exists('is_singular') && is_singular('chasse')) {
        $huntId = function_exists('get_queried_object_id') ? (int) get_queried_object_id() : 0;
    } elseif (
        function_exists('is_front_page')
        && is_front_page()
        && function_exists('cat_is_single_hunt_mode')
        && cat_is_single_hunt_mode()
        && function_exists('cat_get_primary_hunt_id')
    ) {
        $huntId = (int) cat_get_primary_hunt_id();
    }

    if ($huntId <= 0 || !function_exists('get_field')) {
        return false;
    }

    $userId = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
    $canEdit = (function_exists('current_user_can') && current_user_can('manage_options'))
        || (
            function_exists('utilisateur_est_organisateur_associe_a_chasse')
            && utilisateur_est_organisateur_associe_a_chasse($userId, $huntId)
        );
    if (!$canEdit) {
        return false;
    }

    $status = (string) get_field('chasse_cache_statut_validation', $huntId);

    return in_array($status, ['creation', 'correction'], true);
}

/**
 * The answer-mode badge is the player reply form, not an organizer control.
 */
function cta_script_loading_enigme_shows_validation_badge(): bool
{
    if (!function_exists('is_user_logged_in') || !is_user_logged_in()) {
        return false;
    }
    if (!function_exists('is_singular') || !is_singular('enigme')) {
        return false;
    }
    if (function_exists('current_user_can') && current_user_can('manage_options')) {
        return false;
    }

    $riddleId = function_exists('get_queried_object_id') ? (int) get_queried_object_id() : 0;
    $userId = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
    if ($riddleId <= 0 || $userId <= 0) {
        return false;
    }

    if (
        function_exists('est_enigme_resolue_par_utilisateur')
        && est_enigme_resolue_par_utilisateur($userId, $riddleId)
    ) {
        return false;
    }

    $huntId = function_exists('recuperer_id_chasse_associee')
        ? (int) recuperer_id_chasse_associee($riddleId)
        : 0;
    if (
        $huntId > 0
        && function_exists('utilisateur_est_organisateur_associe_a_chasse')
        && utilisateur_est_organisateur_associe_a_chasse($userId, $huntId)
    ) {
        return false;
    }

    return true;
}

/**
 * Site-wide dismiss buttons are the remaining caller of validation-chasse.js.
 */
function cta_script_loading_has_dismissible_site_message(): bool
{
    if (
        function_exists('session_status')
        && session_status() === PHP_SESSION_ACTIVE
        && !empty($_SESSION['cat_site_messages'])
        && is_array($_SESSION['cat_site_messages'])
        && cta_script_loading_messages_are_dismissible($_SESSION['cat_site_messages'])
    ) {
        return true;
    }

    if (!function_exists('cat_get_site_message_service')) {
        return false;
    }

    try {
        $messages = cat_get_site_message_service()->getActive();
    } catch (Throwable $exception) {
        return false;
    }

    return is_array($messages) && cta_script_loading_messages_are_dismissible($messages);
}

/**
 * @param array<int|string, mixed> $messages
 */
function cta_script_loading_messages_are_dismissible(array $messages): bool
{
    foreach ($messages as $message) {
        if (!is_array($message)) {
            continue;
        }
        $key = (string) ($message['message_key'] ?? $message['key'] ?? '');
        if (!empty($message['dismissible']) && $key !== '') {
            return true;
        }
    }

    return false;
}
