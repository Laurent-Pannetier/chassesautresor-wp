<?php

defined('ABSPATH') || exit;

/**
 * Audience of the single-hunt homepage.
 *
 * Priority: administrator, organizer linked to the hunt, engaged player, presentation.
 */
function cat_single_hunt_home_audience(): string
{
    static $audience = null;
    if (is_string($audience)) {
        return $audience;
    }

    $audience = 'presentation';
    if (!function_exists('cat_is_single_hunt_mode') || !cat_is_single_hunt_mode()) {
        return $audience;
    }
    if (!is_user_logged_in()) {
        return $audience;
    }

    $user = wp_get_current_user();
    $roles = (array) $user->roles;
    if (in_array('administrator', $roles, true)) {
        $audience = 'admin';

        return $audience;
    }

    $userId = (int) $user->ID;
    $huntId = function_exists('cat_get_primary_hunt_id') ? (int) cat_get_primary_hunt_id() : 0;
    $managesHunt = $huntId > 0
        && function_exists('utilisateur_est_organisateur_associe_a_chasse')
        && utilisateur_est_organisateur_associe_a_chasse($userId, $huntId);

    $organizerRoles = ['organisateur', 'organisateur_creation'];
    if (defined('ROLE_ORGANISATEUR')) {
        $organizerRoles[] = ROLE_ORGANISATEUR;
    }
    if (defined('ROLE_ORGANISATEUR_CREATION')) {
        $organizerRoles[] = ROLE_ORGANISATEUR_CREATION;
    }

    if ($managesHunt && array_intersect($organizerRoles, $roles)) {
        $audience = 'organisateur';

        return $audience;
    }

    if (
        $huntId > 0
        && function_exists('utilisateur_est_engage_dans_chasse')
        && utilisateur_est_engage_dans_chasse($userId, $huntId)
    ) {
        $audience = 'joueur';

        return $audience;
    }

    return $audience;
}

/**
 * Whether the current front page renders a role cockpit instead of the presentation.
 */
function cat_single_hunt_front_is_cockpit(): bool
{
    if (!function_exists('is_front_page') || !is_front_page()) {
        return false;
    }

    return cat_single_hunt_home_audience() !== 'presentation';
}

/**
 * Single line shown over the hunt visual. Uses only the start date.
 */
function cat_primary_hunt_start_line(int $huntId): string
{
    $fallback = __('Date de départ bientôt annoncée', 'chassesautresor-com');
    if ($huntId <= 0) {
        return $fallback;
    }

    $raw = '';
    if (function_exists('preparer_infos_affichage_chasse')) {
        $info = preparer_infos_affichage_chasse($huntId, (int) get_current_user_id());
        $raw = (string) ($info['champs']['date_debut'] ?? '');
    } elseif (function_exists('get_field')) {
        $raw = (string) get_field('chasse_infos_date_debut', $huntId);
    }

    if ($raw === '') {
        return $fallback;
    }

    $formatted = function_exists('formater_date') ? formater_date($raw) : $raw;
    if ($formatted === '') {
        return $fallback;
    }

    return sprintf(
        /* translators: %s: formatted hunt start date. */
        __('Départ le %s', 'chassesautresor-com'),
        $formatted
    );
}

/**
 * Anchor used by enigmas links once the hunt page redirects home.
 */
function cat_single_hunt_enigmas_url(): string
{
    return home_url('/#enigmes');
}

/**
 * Whether a sommaire row is shown.
 *
 * Players only see an enigma whose accessibility state, already resolved by
 * get_cta_enigme() from the prerequisites, is `accessible`.
 * Organizers and administrators keep the whole album on the same homepage.
 */
function cat_sommaire_enigme_est_visible(string $etatSysteme, bool $privileged): bool
{
    if ($privileged) {
        return true;
    }

    return $etatSysteme === 'accessible';
}

/**
 * Whether a player home should explain that the hunt is being edited.
 *
 * Organizers and administrators keep the album. A validated hunt that is not
 * in revision stays on the sommaire. Every other revision state (creation,
 * correction, waiting for validation) hides that empty canvas.
 */
function cat_player_should_see_hunt_edition_notice(
    bool $privileged,
    string $publicationStatus,
    string $validationStatus,
    string $businessStatus
): bool {
    if ($privileged || $validationStatus === 'banni') {
        return false;
    }

    if ($validationStatus === 'valide' && $businessStatus !== 'revision') {
        return false;
    }

    if ($businessStatus === 'revision') {
        return true;
    }

    if (in_array($validationStatus, ['creation', 'correction', 'en_attente'], true)) {
        return true;
    }

    return in_array($publicationStatus, ['pending', 'draft'], true)
        && $validationStatus !== 'valide';
}

/**
 * Dashboard card shown to a player while the hunt is being edited.
 */
function cat_render_hunt_edition_notice(): void
{
    $title = __('Chasse en cours d’édition', 'chassesautresor-com');
    $note = __(
        'Les énigmes seront de nouveau accessibles dès que l’édition sera terminée.',
        'chassesautresor-com'
    );
    $icon = '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false">'
        . '<path d="M4.5 19.5h3.2L19 8.2a1.8 1.8 0 0 0-2.5-2.5L5.2 17v2.5z" '
        . 'stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>'
        . '<path d="M14.2 6.5l3.3 3.3" stroke="currentColor" stroke-width="1.6" '
        . 'stroke-linecap="round"/>'
        . '</svg>';

    echo '<section id="enigmes" class="hunt-edition-notice" role="status"';
    echo ' aria-labelledby="hunt-edition-notice-title">';
    echo '<div class="hunt-edition-notice__head">';
    echo '<h2 id="hunt-edition-notice-title" class="hunt-edition-notice__title">';
    echo esc_html($title);
    echo '</h2>';
    echo '<span class="hunt-edition-notice__icon" aria-hidden="true">' . $icon . '</span>';
    echo '</div>';
    echo '<p class="hunt-edition-notice__text">' . esc_html($note) . '</p>';
    echo '</section>';
}

/**
 * Album table of contents and hunt solutions at the top of engaged, organizer and admin homes.
 */
function cat_render_single_hunt_home_enigmas(): void
{
    if (!function_exists('cat_is_single_hunt_mode') || !cat_is_single_hunt_mode()) {
        return;
    }

    $huntId = function_exists('cat_get_primary_hunt_id') ? (int) cat_get_primary_hunt_id() : 0;
    if ($huntId <= 0 || get_post_type($huntId) !== 'chasse') {
        return;
    }

    $userId = (int) get_current_user_id();
    $infos = function_exists('preparer_infos_affichage_chasse')
        ? preparer_infos_affichage_chasse($huntId, $userId)
        : [];
    $isOrganizer = function_exists('utilisateur_est_organisateur_associe_a_chasse')
        && utilisateur_est_organisateur_associe_a_chasse($userId, $huntId);
    $status = (string) ($infos['statut'] ?? '');
    $privileged = (function_exists('user_can') && user_can($userId, 'manage_options')) || $isOrganizer;
    $publication = function_exists('get_post_status') ? (string) get_post_status($huntId) : '';
    $validation = (string) ($infos['statut_validation'] ?? '');
    if (cat_player_should_see_hunt_edition_notice($privileged, $publication, $validation, $status)) {
        cat_render_hunt_edition_notice();
        return;
    }

    $showHelp = $isOrganizer
        && $status === 'revision'
        && function_exists('chasse_has_validatable_enigme')
        && !chasse_has_validatable_enigme($huntId);

    // Night keeps the paper sommaire on the previous canvas. Day follows the shell background.
    echo '<div class="home-enigmas-preserve">';
    echo '<section id="enigmes" class="home-enigmas" aria-labelledby="home-enigmas-title">';
    echo '<h2 id="home-enigmas-title" class="screen-reader-text">';
    echo esc_html__('Les énigmes', 'chassesautresor-com');
    echo '</h2>';
    echo '<div id="liste-enigmes" class="chasse-enigmes-liste">';
    get_template_part(
        'template-parts/enigme/chasse-partial-sommaire-enigmes',
        null,
        [
            'chasse_id' => $huntId,
            'est_orga_associe' => $isOrganizer,
            'infos_chasse' => $infos,
            'show_help_icon' => $showHelp,
        ]
    );
    echo '</div>';
    if (function_exists('render_chasse_solutions')) {
        render_chasse_solutions($huntId, $userId);
    }
    echo '</section>';
    echo '</div>';
}

/**
 * Whether a direct visit to the hunt page must return home.
 *
 * The public page has no remaining entry point. Edition stays only for a
 * visitor who is allowed to modify that hunt.
 */
function cat_single_hunt_request_returns_home(string $edition, bool $canEdit): bool
{
    return $edition === '' || !$canEdit;
}

/**
 * Public hunt pages return home. Edition stays only for an authorized editor.
 */
function cat_redirect_public_single_hunt(): void
{
    if (!function_exists('cat_is_single_hunt_mode') || !cat_is_single_hunt_mode()) {
        return;
    }
    if (!is_singular('chasse')) {
        return;
    }

    $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : 'GET';
    if ($method !== 'GET' && $method !== 'HEAD') {
        return;
    }

    $edition = isset($_GET['edition']) ? (string) wp_unslash($_GET['edition']) : '';
    $huntId = function_exists('get_queried_object_id') ? (int) get_queried_object_id() : 0;
    $canEdit = $edition !== ''
        && $huntId > 0
        && function_exists('utilisateur_peut_modifier_post')
        && utilisateur_peut_modifier_post($huntId);
    if (!cat_single_hunt_request_returns_home($edition, $canEdit)) {
        return;
    }

    wp_safe_redirect(home_url('/'));
    exit;
}
add_action('template_redirect', 'cat_redirect_public_single_hunt', 0);

/**
 * The former account-details endpoint now lives on the account root.
 */
function cat_redirect_edit_account_to_profile(): void
{
    if (!function_exists('cat_is_single_hunt_mode') || !cat_is_single_hunt_mode()) {
        return;
    }
    if (!function_exists('is_wc_endpoint_url') || !is_wc_endpoint_url('edit-account')) {
        return;
    }

    $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : 'GET';
    if ($method !== 'GET' && $method !== 'HEAD') {
        return;
    }

    $target = function_exists('wc_get_account_endpoint_url')
        ? wc_get_account_endpoint_url('dashboard')
        : home_url('/mon-compte/');
    wp_safe_redirect($target);
    exit;
}
add_action('template_redirect', 'cat_redirect_edit_account_to_profile', 20);

/**
 * Send a default account landing to the role homepage.
 */
function cat_single_hunt_prefer_home_over_account(string $redirect): string
{
    if (!function_exists('cat_is_single_hunt_mode') || !cat_is_single_hunt_mode()) {
        return $redirect;
    }
    if (!function_exists('wc_get_page_permalink')) {
        return $redirect;
    }

    $account = (string) wc_get_page_permalink('myaccount');
    if ($account === '') {
        return $redirect;
    }

    $redirectPath = untrailingslashit((string) wp_parse_url($redirect, PHP_URL_PATH));
    $accountPath = untrailingslashit((string) wp_parse_url($account, PHP_URL_PATH));
    if ($redirectPath !== '' && $redirectPath === $accountPath) {
        return home_url('/');
    }

    return $redirect;
}

/**
 * @param string $redirect Destination proposed by WooCommerce.
 * @param mixed  $user     Logged-in user.
 */
function cat_single_hunt_woocommerce_login_redirect($redirect, $user): string
{
    unset($user);

    return cat_single_hunt_prefer_home_over_account(is_string($redirect) ? $redirect : '');
}
add_filter('woocommerce_login_redirect', 'cat_single_hunt_woocommerce_login_redirect', 30, 2);

/**
 * @param string $redirectTo Requested redirect.
 * @param string $requested  Original redirect_to.
 * @param mixed  $user       Logged-in user.
 */
function cat_single_hunt_wp_login_redirect($redirectTo, $requested, $user): string
{
    unset($requested, $user);
    $target = is_string($redirectTo) ? $redirectTo : '';

    return cat_single_hunt_prefer_home_over_account($target);
}
add_filter('login_redirect', 'cat_single_hunt_wp_login_redirect', 30, 3);

/**
 * Drop the marketing hero when the homepage is a cockpit.
 *
 * @param array<int, string> $classes Body classes.
 * @return array<int, string>
 */
function cat_single_hunt_cockpit_body_class(array $classes): array
{
    if (!cat_single_hunt_front_is_cockpit()) {
        return $classes;
    }

    $classes[] = 'cat-single-hunt-cockpit';
    $classes = array_values(array_diff($classes, ['has-hero']));

    return $classes;
}
add_filter('body_class', 'cat_single_hunt_cockpit_body_class', 20);
