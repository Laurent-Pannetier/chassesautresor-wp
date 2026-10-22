<?php
/**
 * Écran public de connexion : Discord, Google, puis e-mail.
 *
 * @package chassesautresor
 */

defined('ABSPATH') || exit;

/**
 * Vrai lorsque la requête courante est l'écran /connexion/.
 */
function cta_is_auth_screen(): bool
{
    return function_exists('cat_social_auth_is_request') && cat_social_auth_is_request();
}

/**
 * Charge le modèle dédié, sans l'en-tête du site.
 *
 * @param string $template
 */
function cta_auth_screen_template(string $template): string
{
    if (!cta_is_auth_screen()) {
        return $template;
    }

    $custom = get_stylesheet_directory() . '/templates/page-connexion.php';

    return is_readable($custom) ? $custom : $template;
}
add_filter('template_include', 'cta_auth_screen_template');

/**
 * @param array<int, string> $classes
 * @return array<int, string>
 */
function cta_auth_screen_body_class(array $classes): array
{
    if (!cta_is_auth_screen()) {
        return $classes;
    }

    $classes = array_values(array_diff($classes, ['app-shell-active']));
    $classes[] = 'auth-gate';

    return $classes;
}
add_filter('body_class', 'cta_auth_screen_body_class', 20);

/**
 * @param bool $show
 */
function cta_auth_screen_hide_admin_bar(bool $show): bool
{
    if (cta_is_auth_screen()) {
        return false;
    }

    return $show;
}
add_filter('show_admin_bar', 'cta_auth_screen_hide_admin_bar');

/**
 * @param array<string, string> $parts
 * @return array<string, string>
 */
function cta_auth_screen_document_title(array $parts): array
{
    if (cta_is_auth_screen()) {
        $parts['title'] = __('Connexion', 'chassesautresor-com');
    }

    return $parts;
}
add_filter('document_title_parts', 'cta_auth_screen_document_title');

/**
 * @param array<string, bool|string> $robots
 * @return array<string, bool|string>
 */
function cta_auth_screen_robots(array $robots): array
{
    if (cta_is_auth_screen()) {
        $robots['noindex'] = true;
        $robots['nofollow'] = true;
    }

    return $robots;
}
add_filter('wp_robots', 'cta_auth_screen_robots');

/**
 * Script du formulaire e-mail, limité à cet écran.
 */
function cta_auth_screen_assets(): void
{
    if (!cta_is_auth_screen()) {
        return;
    }

    $relative = '/assets/js/auth-screen.js';
    $path = get_stylesheet_directory() . $relative;
    if (!is_readable($path)) {
        return;
    }

    wp_enqueue_script(
        'cta-auth-screen',
        get_stylesheet_directory_uri() . $relative,
        [],
        (string) filemtime($path),
        true
    );
}
add_action('wp_enqueue_scripts', 'cta_auth_screen_assets');

/**
 * @return array<string, mixed>
 */
function cta_auth_screen_context(): array
{
    $view = isset($_GET['vue']) ? sanitize_key((string) wp_unslash($_GET['vue'])) : 'login';
    $allowed = ['login', 'inscription', 'mot-de-passe', 'relier', 'cgu'];
    if (!in_array($view, $allowed, true)) {
        $view = 'login';
    }

    $error = isset($_GET['erreur']) ? sanitize_key((string) wp_unslash($_GET['erreur'])) : '';
    $info = isset($_GET['info']) ? sanitize_key((string) wp_unslash($_GET['info'])) : '';
    $requested = isset($_GET['redirect_to']) ? (string) wp_unslash($_GET['redirect_to']) : '';
    $redirect = function_exists('cat_social_auth_page_url')
        ? (new \ChassesAuTresor\Core\Auth\SocialAuthRedirect())->accept($requested, home_url('/'))
        : home_url('/');
    if ($requested === '') {
        $redirect = home_url('/');
    }

    $pending = [
        'provider' => '',
        'valid' => false,
        'redirect' => $redirect,
    ];
    if ($view === 'relier' && class_exists(\ChassesAuTresor\Core\Auth\SocialAuthService::class)) {
        $pending = (new \ChassesAuTresor\Core\Auth\SocialAuthService())->pendingLink();
        if ($pending['valid']) {
            $redirect = (string) $pending['redirect'];
        }
    }

    $hunt = cta_auth_screen_hunt($redirect);
    $ready = function_exists('cat_social_auth_ready_providers') ? cat_social_auth_ready_providers() : [];
    $emailRequested = isset($_GET['email']) && (string) wp_unslash($_GET['email']) === '1';
    $emailOpen = $ready === [] || $emailRequested || $error === 'identifiants';

    return [
        'view' => $view,
        'error' => $error,
        'info' => $info,
        'redirect' => $redirect,
        'back_url' => $redirect,
        'home_url' => home_url('/'),
        'page_url' => function_exists('cat_social_auth_callback_url')
            ? cat_social_auth_callback_url()
            : home_url('/connexion/'),
        'email_open' => $emailOpen,
        'registration_open' => get_option('users_can_register') === '1',
        'hunt' => $hunt,
        'logo_html' => cta_auth_screen_logo(),
        'link_provider' => (string) ($pending['provider'] ?? ''),
        'link_valid' => !empty($pending['valid']),
        'site_name' => (string) get_bloginfo('name'),
        'privacy_url' => function_exists('get_privacy_policy_url') ? (string) get_privacy_policy_url() : '',
        'login_action' => site_url('wp-login.php', 'login_post'),
        'lost_password_url' => function_exists('cat_social_auth_page_url')
            ? cat_social_auth_page_url($redirect, 'mot-de-passe')
            : '',
        'register_url' => function_exists('cat_social_auth_page_url')
            ? cat_social_auth_page_url($redirect, 'inscription')
            : '',
        'login_url' => function_exists('cat_social_auth_page_url')
            ? cat_social_auth_page_url($redirect)
            : home_url('/'),
    ];
}

/**
 * Image 16/9 de la chasse demandée, sinon celle de la chasse principale.
 *
 * @return array{title:string,description:string,image:string,alt:string}
 */
function cta_auth_screen_hunt(string $redirect): array
{
    $huntId = 0;
    if ($redirect !== '' && function_exists('url_to_postid')) {
        $postId = (int) url_to_postid($redirect);
        if ($postId > 0 && get_post_type($postId) === 'chasse') {
            $huntId = $postId;
        }
    }
    if ($huntId <= 0 && function_exists('cat_get_primary_hunt_id')) {
        $huntId = (int) cat_get_primary_hunt_id();
    }

    $title = $huntId > 0 ? (string) get_the_title($huntId) : (string) get_bloginfo('name');
    $image = '';
    if ($huntId > 0 && function_exists('cta_shell_hunt_image_url')) {
        $image = cta_shell_hunt_image_url($huntId);
    }

    $description = '';
    if ($huntId > 0 && function_exists('get_field')) {
        $raw = get_field('chasse_principale_description', $huntId);
        $description = wp_trim_words(wp_strip_all_tags((string) $raw), 28, '…');
    }
    if ($description === '') {
        $description = (string) get_bloginfo('description');
    }

    return [
        'title' => $title !== '' ? $title : (string) get_bloginfo('name'),
        'description' => $description,
        'image' => $image,
        'alt' => $title !== '' ? $title : __('Image de la chasse', 'chassesautresor-com'),
    ];
}

/**
 * Logo du site, sans le filtre gris de la barre du dashboard.
 */
function cta_auth_screen_logo(): string
{
    $logoId = (int) get_theme_mod('custom_logo');
    if ($logoId <= 0) {
        return '';
    }

    $html = wp_get_attachment_image($logoId, 'medium', false, [
        'class' => 'auth-card__logo-img',
        'alt' => (string) get_bloginfo('name'),
    ]);

    return is_string($html) ? $html : '';
}

/**
 * Marque du fournisseur, découpée pour rester lisible dans le fichier.
 */
function cta_auth_provider_icon(string $provider): string
{
    if ($provider === 'google') {
        $paths = [
            '#4285F4' => cta_auth_join_path([
        'M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844a4.14 4.14 0 0 1-1',
        '.796 2.716v2.259h2.908c1.702-1.567 2.684-3.875 2.684-6.615z',
        ]),
            '#34A853' => cta_auth_join_path([
        'M9 18c2.43 0 4.467-.806 5.956-2.184l-2.908-2.259c-.806.54-1.837.86-3.0',
        '48.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332A8.997 8.997 0 0 0 9 1',
        '8z',
        ]),
            '#FBBC05' => cta_auth_join_path([
        'M3.964 10.706A5.41 5.41 0 0 1 3.682 9c0-.593.102-1.17.282-1.706V4.962H',
        '.957A8.996 8.996 0 0 0 0 9c0 1.452.348 2.827.957 4.038l3.007-2.332z',
        ]),
            '#EA4335' => cta_auth_join_path([
        'M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 ',
        '0A8.997 8.997 0 0 0 .957 4.962L3.964 7.294C4.672 5.163 6.656 3.58 9 3.',
        '58z',
        ]),
        ];
        $svg = '<svg class="auth-provider__icon" viewBox="0 0 18 18" '
            . 'width="18" height="18" aria-hidden="true">';
        foreach ($paths as $color => $path) {
            $svg .= '<path fill="' . $color . '" d="' . $path . '"/>';
        }

        return $svg . '</svg>';
    }

    $path = cta_auth_join_path([
        'M20.317 4.37a19.79 19.79 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21',
        '.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.6',
        '17-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 ',
        '0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057',
        ' 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028 14.09 14.09 0 0 0 ',
        '1.226-1.994.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077',
        '.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c',
        '3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198',
        '.373.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.892.077.077',
        ' 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 ',
        '19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9',
        '.674-3.549-13.66a.061.061 0 0 0-.031-.03zM8.02 15.33c-1.183 0-2.157-1.',
        '085-2.157-2.419 0-1.333.956-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157',
        ' 2.42 0 1.333-.956 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.1',
        '57-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0',
        ' 1.333-.946 2.418-2.157 2.418z',
    ]);
    $svg = '<svg class="auth-provider__icon" viewBox="0 0 24 24" '
        . 'width="20" height="20" aria-hidden="true">';
    $svg .= '<path fill="currentColor" d="' . $path . '"/>';

    return $svg . '</svg>';
}

/**
 * @param array<int, string> $parts
 */
function cta_auth_join_path(array $parts): string
{
    return implode('', $parts);
}

/**
 * Discord puis Google, visibles avant l'enregistrement des identifiants.
 */
function cta_auth_provider_buttons(string $pageUrl, string $redirect, string $view): void
{
    echo '<div class="auth-providers">';
    foreach (['discord', 'google'] as $provider) {
        cta_auth_provider_button($provider, $pageUrl, $redirect, $view);
    }
    echo '</div>';
}

/**
 * Un bouton de connexion sociale.
 */
function cta_auth_provider_button(string $provider, string $pageUrl, string $redirect, string $view): void
{
    $label = $provider === 'google'
        ? __('Continuer avec Google', 'chassesautresor-com')
        : __('Continuer avec Discord', 'chassesautresor-com');
    $class = 'auth-provider auth-provider--' . $provider;
    ?>
    <form method="post" action="<?php echo esc_url($pageUrl); ?>">
        <?php wp_nonce_field('cat_auth_start'); ?>
        <input type="hidden" name="cat_auth_action" value="start">
        <input type="hidden" name="provider" value="<?php echo esc_attr($provider); ?>">
        <input type="hidden" name="cat_auth_view" value="<?php echo esc_attr($view); ?>">
        <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect); ?>">
        <button class="<?php echo esc_attr($class); ?>" type="submit">
            <?php
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo cta_auth_provider_icon($provider);
            ?>
            <span><?php echo esc_html($label); ?></span>
        </button>
    </form>
    <?php
}

/**
 * Case à cocher des conditions, avec le lien de confidentialité s'il existe.
 */
function cta_auth_terms_html(string $privacyUrl): void
{
    if ($privacyUrl === '') {
        esc_html_e('J’accepte les conditions d’utilisation.', 'chassesautresor-com');

        return;
    }

    $template = __(
        'J’accepte les <a href="%s">conditions et la politique de confidentialité</a>.',
        'chassesautresor-com'
    );
    echo wp_kses(
        sprintf($template, esc_url($privacyUrl)),
        ['a' => ['href' => []]]
    );
}

