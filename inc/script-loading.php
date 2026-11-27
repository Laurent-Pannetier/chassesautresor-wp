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
 * Register the storefront dequeue and the AddToAny screen gate.
 * Called from functions.php after this file loads.
 */
function cta_register_script_loading_hooks(): void
{
    add_action('wp_enqueue_scripts', 'cta_limit_commerce_scripts', 100);
    // Footer scripts can still be queued by the cart widget while the header renders.
    add_action('wp_footer', 'cta_limit_commerce_scripts', 19);
    add_filter('addtoany_script_disabled', 'cta_addtoany_script_disabled');
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

/**
 * Page templates that print post content, where AddToAny can append its kit.
 */
function cta_page_template_prints_post_content(string $template): bool
{
    return in_array($template, ['', 'default', 'templates/page-devenir-organisateur.php'], true);
}

/**
 * Which content filter this request will run, if any.
 *
 * Custom hunt, riddle and organiser templates never call the_content().
 * The front page template replaces page.php. Shop loops do not print excerpts
 * unless a theme option asks for a short description.
 *
 * @param array<string, mixed> $request
 */
function cta_addtoany_content_filter(array $request): string
{
    if (!empty($request['is_front_page'])) {
        return '';
    }
    if (!empty($request['is_home']) || !empty($request['is_archive']) || !empty($request['is_search'])) {
        return 'the_excerpt';
    }
    if (!empty($request['is_feed'])) {
        return 'the_content';
    }

    $postType = (string) ($request['post_type'] ?? '');
    if ($postType === 'page') {
        $template = (string) ($request['page_template'] ?? '');

        return cta_page_template_prints_post_content($template) ? 'the_content' : '';
    }
    if (in_array($postType, ['chasse', 'organisateur', 'enigme'], true)) {
        return '';
    }
    if (!empty($request['is_singular']) && $postType !== '') {
        return 'the_content';
    }

    return '';
}

/**
 * AddToAny stores "-1" when a placement checkbox is saved off.
 *
 * @param array<string, mixed> $options
 */
function cta_addtoany_option_is_off(array $options, string $key): bool
{
    return isset($options[$key]) && (string) $options[$key] === '-1';
}

/**
 * Mirrors the early return of A2A_SHARE_SAVE_add_to_content().
 *
 * @param array<string, mixed> $context
 */
function cta_addtoany_placement_skips(array $context): bool
{
    $options = is_array($context['options'] ?? null) ? $context['options'] : [];
    $off = static function (string $key) use ($options): bool {
        return cta_addtoany_option_is_off($options, $key);
    };
    $postType = (string) ($context['post_type'] ?? '');
    $filter = (string) ($context['content_filter'] ?? '');

    if (!empty($context['is_singular_post']) && $off('display_in_posts')) {
        return true;
    }
    if (!empty($context['is_home']) && $off('display_in_posts_on_front_page')) {
        return true;
    }
    if (
        (!empty($context['is_archive']) || !empty($context['is_search']))
        && $off('display_in_posts_on_archive_pages')
    ) {
        return true;
    }
    if ($filter === 'the_excerpt' && $off('display_in_excerpts')) {
        return true;
    }
    if (!empty($context['is_feed']) && $off('display_in_feed')) {
        return true;
    }
    if ($postType !== '' && $off('display_in_cpt_' . $postType)) {
        return true;
    }
    if (!empty($context['is_page']) && $off('display_in_pages')) {
        return true;
    }
    if (!empty($context['is_attachment']) && $off('display_in_attachments')) {
        return true;
    }

    return false;
}

/**
 * Whether the plugin itself will print a share kit on this request.
 *
 * @param array<string, mixed> $context
 */
function cta_addtoany_plugin_prints_buttons(array $context): bool
{
    $filter = (string) ($context['content_filter'] ?? '');
    if ($filter !== 'the_content' && $filter !== 'the_excerpt') {
        return false;
    }

    $content = (string) ($context['post_content'] ?? '');
    if ($filter === 'the_content' && str_contains($content, '[addtoany')) {
        return true;
    }
    if (!empty($context['sharing_disabled']) || ($context['post_status'] ?? '') === 'private') {
        return false;
    }

    $legacyGate = !str_contains($content, '<!--sharesave-->')
        || str_contains($content, '<!--nosharesave-->');
    if ($legacyGate && cta_addtoany_placement_skips($context)) {
        return false;
    }

    return true;
}

/**
 * @param array<string, mixed> $options
 */
function cta_addtoany_floating_is_enabled(array $options): bool
{
    foreach (['floating_vertical', 'floating_horizontal'] as $key) {
        $value = (string) ($options[$key] ?? 'none');
        if ($value !== '' && $value !== 'none') {
            return true;
        }
    }

    return false;
}

/**
 * Theme share control: hunt sheet, organiser header, and the contact hero.
 *
 * organisateur-header.php is also used for an organiser's /contact URL
 * (still a singular organiser) and for the contact page when the email
 * query resolves to an organiser. The contact hero is skipped on the front
 * page and when the account shell hides heroes.
 *
 * @param array<string, mixed> $context
 */
function cta_screen_shows_theme_share_button(array $context): bool
{
    if (!empty($context['is_singular_chasse']) || !empty($context['is_singular_organisateur'])) {
        return true;
    }
    if (!empty($context['is_front_page']) || !empty($context['hero_hidden'])) {
        return false;
    }

    return !empty($context['is_contact_page'])
        && (int) ($context['contact_organisateur_id'] ?? 0) > 0;
}

/**
 * Whether addtoany-core (static.addtoany.com) is needed for a visible button.
 *
 * @param array<string, mixed> $context
 */
function cta_screen_needs_addtoany_script(array $context): bool
{
    if (cta_screen_shows_theme_share_button($context)) {
        return true;
    }
    if (!empty($context['widget_active'])) {
        return true;
    }

    $options = is_array($context['options'] ?? null) ? $context['options'] : [];
    if (cta_addtoany_floating_is_enabled($options) && empty($context['sharing_disabled'])) {
        return true;
    }

    return cta_addtoany_plugin_prints_buttons($context);
}

/**
 * Query facts used to decide if AddToAny's external script should print.
 *
 * @return array<string, mixed>
 */
function cta_addtoany_screen_context(): array
{
    $postId = function_exists('get_queried_object_id') ? (int) get_queried_object_id() : 0;
    $postType = '';
    if ($postId > 0 && function_exists('get_post_type')) {
        $typed = get_post_type($postId);
        $postType = is_string($typed) ? $typed : '';
    }

    $isFront = function_exists('is_front_page') && is_front_page();
    $isPage = function_exists('is_page') && is_page();
    $isSingular = function_exists('is_singular') && is_singular();
    $isWoo = function_exists('is_woocommerce') && is_woocommerce();
    $template = ($isPage && function_exists('get_page_template_slug'))
        ? (string) get_page_template_slug($postId)
        : '';
    $contentFilter = cta_addtoany_content_filter([
        'is_front_page' => $isFront,
        'is_home' => function_exists('is_home') && is_home() && !$isFront,
        'is_archive' => function_exists('is_archive') && is_archive() && !$isFront && !$isWoo,
        'is_search' => function_exists('is_search') && is_search() && !$isWoo,
        'is_feed' => function_exists('is_feed') && is_feed(),
        'is_singular' => $isSingular,
        'post_type' => $postType,
        'page_template' => $template,
    ]);

    $content = '';
    $status = '';
    $sharingDisabled = false;
    if ($postId > 0 && $isSingular) {
        if (function_exists('get_post_field')) {
            $content = (string) get_post_field('post_content', $postId);
            $status = (string) get_post_field('post_status', $postId);
        }
        if (function_exists('get_post_meta')) {
            $sharingDisabled = (bool) get_post_meta($postId, 'sharing_disabled', true);
        }
    }

    $options = [];
    if (function_exists('get_option')) {
        $stored = get_option('addtoany_options', []);
        $options = is_array($stored) ? $stored : [];
    }

    $isContact = function_exists('is_page') && is_page('contact');
    $organisateurId = 0;
    if (
        $isContact
        && isset($_GET['email_organisateur'])
        && is_string($_GET['email_organisateur'])
    ) {
        $raw = $_GET['email_organisateur'];
        if (function_exists('wp_unslash')) {
            $raw = wp_unslash($raw);
        }
        $email = function_exists('sanitize_email') ? sanitize_email((string) $raw) : trim((string) $raw);
        if ($email !== '' && function_exists('get_organisateur_id_by_contact_email')) {
            $organisateurId = (int) get_organisateur_id_by_contact_email($email);
        }
    }

    $widget = false;
    if (function_exists('is_active_widget')) {
        $widget = (bool) is_active_widget(false, false, 'a2a_share_save_widget', true)
            || (bool) is_active_widget(false, false, 'a2a_follow_widget', true);
    }

    $heroHidden = (function_exists('is_user_account_area') && is_user_account_area())
        || (function_exists('myaccount_is_organisation_page') && myaccount_is_organisation_page());

    return [
        'is_singular_chasse' => function_exists('is_singular') && is_singular('chasse'),
        'is_singular_organisateur' => function_exists('is_singular') && is_singular('organisateur'),
        'is_contact_page' => $isContact,
        'contact_organisateur_id' => $organisateurId,
        'is_front_page' => $isFront,
        'hero_hidden' => $heroHidden,
        'is_singular_post' => function_exists('is_singular') && is_singular('post'),
        'is_page' => $isPage && !$isFront,
        'is_attachment' => function_exists('is_attachment') && is_attachment(),
        'is_home' => function_exists('is_home') && is_home() && !$isFront,
        'is_archive' => function_exists('is_archive') && is_archive() && !$isFront && !$isWoo,
        'is_search' => function_exists('is_search') && is_search() && !$isWoo,
        'is_feed' => function_exists('is_feed') && is_feed(),
        'post_type' => $postType,
        'post_status' => $status,
        'post_content' => $content,
        'sharing_disabled' => $sharingDisabled,
        'options' => $options,
        'content_filter' => $contentFilter,
        'widget_active' => $widget,
    ];
}

/**
 * Disable AddToAny's external script and stylesheet when no share button is printed.
 *
 * The plugin calls this filter with false. An empty value is treated as "not yet disabled"
 * so a hook fired without arguments cannot raise a type error.
 *
 * @param mixed $disabled
 */
function cta_addtoany_script_disabled(mixed $disabled): bool
{
    if ($disabled) {
        return true;
    }

    return !cta_screen_needs_addtoany_script(cta_addtoany_screen_context());
}
