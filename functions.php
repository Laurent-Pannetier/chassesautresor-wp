<?php
/**
 * chassesautresor.com Theme functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package chassesautresor.com
 * @since 1.0.0
 */
defined( 'ABSPATH' ) || exit;

$autoloader = __DIR__ . '/../../../vendor/autoload.php';
if ( file_exists( $autoloader ) ) {
    require_once $autoloader;
}

/**
 * Define Constants
 */
define( 'CHILD_THEME_CHASSESAUTRESOR_COM_VERSION', '1.0.0' );

/**
 * Charge le domaine de traduction du thème enfant.
 */
function cta_load_textdomain() {
    load_child_theme_textdomain( 'chassesautresor-com', get_stylesheet_directory() . '/languages' );
}
add_action( 'after_setup_theme', 'cta_load_textdomain' );


/**
 * Adds custom image sizes.
 *
 * @return void
 */
function cta_register_image_sizes() {
    // Allow taller hunt visuals so CSS can scale up to 800px height
    add_image_size( 'chasse-fiche', 1024, 800, false );
}
add_action( 'after_setup_theme', 'cta_register_image_sizes' );


/**
 * Retrieves the locale from the cookie.
 *
 * @return string
 */
function cta_get_locale_from_cookie() {
    $locale = isset( $_COOKIE['cta_lang'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['cta_lang'] ) ) : '';

    return apply_filters( 'locale', $locale );
}

/**
 * Handles language switching via query parameter and cookie.
 *
 * @return void
 */
function cta_handle_language() {
    $locale = '';

    if ( isset( $_GET['lang'] ) ) {
        $lang   = sanitize_text_field( wp_unslash( $_GET['lang'] ) );
        $locale = 'fr' === $lang ? 'fr_FR' : ( 'en' === $lang ? 'en_US' : '' );

        if ( $locale ) {
            setcookie( 'cta_lang', $locale, time() + MONTH_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
        }
    } else {
        $locale = cta_get_locale_from_cookie();

        if ( ! $locale ) {
            $accept       = sanitize_text_field( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '' );
            $browser_lang = substr( $accept, 0, 2 );
            $locale       = 'fr' === $browser_lang ? 'fr_FR' : ( 'en' === $browser_lang ? 'en_US' : '' );

            if ( $locale ) {
                setcookie( 'cta_lang', $locale, time() + MONTH_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
            }
        }
    }

    if ( $locale ) {
        switch_to_locale( $locale );
    }
}
add_action( 'init', 'cta_handle_language' );

/**
 * Registers the search context used on the homepage hunts listing.
 *
 * @return void
 */
function ca_register_home_hunts_search_context(): void {
    ca_register_search_context('home-hunts', [
        'fields' => [
            'sql' => [
                'p.post_title',
                'pm_short_description.meta_value',
                'organisateur.post_title',
            ],
        ],
        'ui' => [
            'label'             => __('Rechercher une chasse', 'chassesautresor-com'),
            'placeholder'       => __('Rechercher une chasse', 'chassesautresor-com'),
            'submit_icon'       => 'search',
            'submit_icon_only'  => true,
            'show_reset_button' => true,
            'no_results_message' => __('Aucune chasse trouvée', 'chassesautresor-com'),
        ],
    ]);
}
add_action('init', 'ca_register_home_hunts_search_context');

/**
 * Redirects non-logged-in users requesting `/mon-compte` to the login page.
 *
 * @return void
 */
function cta_redirect_account_page_for_guests() {
    if ( is_user_logged_in() ) {
        return;
    }

    $request_path = wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH );

    if ( 0 === strpos( $request_path, '/mon-compte' ) ) {
        $current_url = home_url( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ) );
        wp_safe_redirect( wp_login_url( $current_url ) );
        exit;
    }
}
add_action( 'init', 'cta_redirect_account_page_for_guests' );

/**
 * Renders the language switcher in the header.
 *
 * @param string $row    Header builder row.
 * @param string $column Header builder column.
 *
 * @return void
 */
function cta_render_lang_switcher( $row, $column ) {
    if ( 'above' !== $row || 'right' !== $column ) {
        return;
    }

    $active_locale = '';

    if ( isset( $_GET['lang'] ) ) {
        $lang         = sanitize_text_field( wp_unslash( $_GET['lang'] ) );
        $active_locale = 'fr' === $lang ? 'fr_FR' : ( 'en' === $lang ? 'en_US' : '' );
    }

    if ( ! $active_locale ) {
        $active_locale = cta_get_locale_from_cookie();
    }

    if ( ! $active_locale ) {
        $active_locale = get_locale();
    }

    $available_langs = [
        'fr_FR' => [
            'code'  => 'fr',
            'label' => __( 'Français', 'chassesautresor-com' ),
            'flag'  => '🇫🇷',
        ],
        'en_US' => [
            'code'  => 'en',
            'label' => __( 'English', 'chassesautresor-com' ),
            'flag'  => '🇬🇧',
        ],
    ];

    $current_url = home_url( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ) );
    $current_url = remove_query_arg( 'lang', $current_url );
    ?>
    <div class="lang-switcher ast-builder-layout-element site-header-focus-item">
        <button class="lang-switcher__toggle" aria-haspopup="true" aria-expanded="false" aria-label="<?php esc_attr_e( 'Change language', 'chassesautresor-com' ); ?>">
            <span class="lang-switcher__flag">
                <?php echo esc_html( $available_langs[ $active_locale ]['flag'] ?? '🇫🇷' ); ?>
            </span>
            <span class="lang-switcher__icon">▼</span>
        </button>
        <ul class="lang-switcher__options">
            <?php foreach ( $available_langs as $locale => $data ) : ?>
                <?php $url = add_query_arg( 'lang', $data['code'], $current_url ); ?>
                <li class="<?php echo $locale === $active_locale ? 'active' : ''; ?>">
                    <?php if ( $locale === $active_locale ) : ?>
                        <span>
                            <span class="lang-switcher__flag"><?php echo esc_html( $data['flag'] ); ?></span>
                            <span class="lang-switcher__label"><?php echo esc_html( $data['label'] ); ?></span>
                        </span>
                    <?php else : ?>
                        <a href="<?php echo esc_url( $url ); ?>">
                            <span class="lang-switcher__flag"><?php echo esc_html( $data['flag'] ); ?></span>
                            <span class="lang-switcher__label"><?php echo esc_html( $data['label'] ); ?></span>
                        </a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php
}
add_action( 'astra_render_header_column', 'cta_render_lang_switcher', 999, 2 );

/**
 * Loads custom account state SVG icons for Astra.
 *
 * @return array<string, string>
 */
function cta_get_account_state_icons() {
    static $icons = null;

    if ( null !== $icons ) {
        return $icons;
    }

    $icons     = [];
    $base_path = trailingslashit( get_stylesheet_directory() ) . 'assets/svg/';
    $files     = [
        'cta-account-guest' => 'user-anonyme.svg',
        'cta-account-user'  => 'user-connecte.svg',
    ];

    foreach ( $files as $key => $file ) {
        $path = $base_path . $file;

        if ( ! file_exists( $path ) || ! is_readable( $path ) ) {
            continue;
        }

        $content = file_get_contents( $path );

        if ( false !== $content ) {
            $icons[ $key ] = $content;
        }
    }

    return $icons;
}

/**
 * Registers custom account icons with Astra.
 *
 * @param array<string, string> $icons Default Astra icons.
 *
 * @return array<string, string>
 */
function cta_register_account_state_icons( $icons ) {
    $custom_icons = cta_get_account_state_icons();

    if ( empty( $custom_icons ) ) {
        return $icons;
    }

    return array_merge( $icons, $custom_icons );
}
add_filter( 'astra_svg_icons', 'cta_register_account_state_icons' );

/**
 * Determines the account icon key to use depending on the user state.
 *
 * @param string $default Default Astra icon key.
 *
 * @return string
 */
function cta_account_icon_by_state( $default ) {
    $icons = cta_get_account_state_icons();

    if ( is_user_logged_in() ) {
        return isset( $icons['cta-account-user'] ) ? 'cta-account-user' : $default;
    }

    return isset( $icons['cta-account-guest'] ) ? 'cta-account-guest' : $default;
}
add_filter( 'astra_get_option_header-account-icon-type', 'cta_account_icon_by_state' );

/**
 * Removes the "Continue Shopping" button from Astra's flyout cart when empty.
 *
 * @return void
 */
add_action(
    'wp',
    static function (): void {
        if ( ! class_exists( 'Astra_Woocommerce' ) || ! function_exists( 'WC' ) ) {
            return;
        }

        remove_action(
            'woocommerce_after_mini_cart',
            [ Astra_Woocommerce::get_instance(), 'astra_update_flyout_cart_layout' ]
        );

        add_action(
            'woocommerce_after_mini_cart',
            static function (): void {
                if ( ! function_exists( 'WC' ) ) {
                    return;
                }

                $cart = WC()->cart;

                if ( ! $cart || ! $cart->is_empty() ) {
                    return;
                }

                /**
                 * Preserve Astra's extensibility hooks around the empty mini-cart.
                 */
                do_action( 'astra_empty_cart_before' );

                $message = apply_filters(
                    'astra_mini_cart_empty_msg',
                    __( 'No products in the cart.', 'chassesautresor-com' )
                );
                ?>
                <div class="ast-mini-cart-empty">
                    <div class="ast-mini-cart-message">
                        <p class="woocommerce-mini-cart__empty-message">
                            <?php echo esc_html( $message ); ?>
                        </p>
                    </div>
                    <?php do_action( 'astra_empty_cart_content' ); ?>
                </div>
                <?php
                do_action( 'astra_empty_cart_after' );
            }
        );
    },
    5
);

/**
 * Chargement des styles du thème parent et enfant avec prise en charge d'Astra.
 */
add_action('wp_enqueue_scripts', function () {
    $theme_uri  = get_stylesheet_directory_uri();
    $theme_path = get_stylesheet_directory();

    // 🎨 Chargement du style du thème parent (Astra)
    wp_enqueue_style('astra-style', get_template_directory_uri() . '/style.css');

    wp_enqueue_style(
        'chassesautresor-style',
        $theme_uri . '/dist/style.css',
        ['astra-style'],
        filemtime($theme_path . '/dist/style.css')
    );

    $script_dir = $theme_uri . '/assets/js/';

    wp_enqueue_script(
        'lang-switcher',
        $script_dir . 'lang-switcher.js',
        [],
        filemtime($theme_path . '/assets/js/lang-switcher.js'),
        true
    );

    wp_enqueue_script(
        'help-modal',
        $script_dir . 'help-modal.js',
        ['wp-i18n'],
        filemtime($theme_path . '/assets/js/help-modal.js'),
        true
    );
    wp_set_script_translations('help-modal', 'chassesautresor-com');

    if (is_front_page()) {
        wp_enqueue_script(
            'home-hero',
            $script_dir . 'home-hero.js',
            [],
            filemtime($theme_path . '/assets/js/home-hero.js'),
            true
        );

        wp_enqueue_script(
            'home-hunts-filters',
            $script_dir . 'home-hunts-filters.js',
            [],
            filemtime($theme_path . '/assets/js/home-hunts-filters.js'),
            true
        );

        $home_hunts_context = ca_resolve_search_context('home-hunts');
        $home_hunts_ui      = is_array($home_hunts_context['ui'] ?? null) ? $home_hunts_context['ui'] : [];
        $no_results_label   = isset($home_hunts_ui['no_results_message']) && $home_hunts_ui['no_results_message'] !== ''
            ? (string) $home_hunts_ui['no_results_message']
            : __('Aucune chasse trouvée', 'chassesautresor-com');

        wp_localize_script(
            'home-hunts-filters',
            'homeHuntsFilters',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('ca-filter-chasses'),
                'labels'  => [
                    'error' => __('Impossible de charger les chasses.', 'chassesautresor-com'),
                    'reset' => __('Réinitialiser', 'chassesautresor-com'),
                    'empty' => $no_results_label,
                ],
            ]
        );
    }

    if (is_account_page() && is_user_logged_in()) {
        wp_enqueue_script(
            'recommended-hunts-slider',
            $script_dir . 'recommended-hunts-slider.js',
            [],
            filemtime($theme_path . '/assets/js/recommended-hunts-slider.js'),
            true
        );

        wp_enqueue_script(
            'myaccount',
            $script_dir . 'myaccount.js',
            ['recommended-hunts-slider'],
            filemtime($theme_path . '/assets/js/myaccount.js'),
            true
        );
        wp_localize_script('myaccount', 'ctaMyAccount', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
        ]);
        wp_enqueue_script(
            'tentatives-toggle',
            $script_dir . 'tentatives-toggle.js',
            [],
            filemtime($theme_path . '/assets/js/tentatives-toggle.js'),
            true
        );
    }

    if (is_singular('enigme')) {
        wp_enqueue_script(
            'accordeon',
            $script_dir . 'accordeon.js',
            ['wp-i18n'],
            filemtime($theme_path . '/assets/js/accordeon.js'),
            true
        );
        wp_set_script_translations('accordeon', 'chassesautresor-com');
    }

    if (is_singular(['enigme', 'chasse'])) {
        wp_enqueue_script(
            'enigme-panel',
            $script_dir . 'enigme-panel.js',
            [],
            filemtime($theme_path . '/assets/js/enigme-panel.js'),
            true
        );
    }
    $sidebar_dir = $theme_uri . '/assets/sidebar/';
    if (is_singular(['enigme', 'chasse'])) {
        wp_enqueue_script(
            'sidebar',
            $sidebar_dir . 'sidebar.js',
            [],
            filemtime($theme_path . '/assets/sidebar/sidebar.js'),
            true
        );
        wp_localize_script('sidebar', 'sidebarData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
        ]);
        wp_enqueue_script(
            'sidebar-menu-toggle',
            $sidebar_dir . 'menu-toggle.js',
            [],
            filemtime($theme_path . '/assets/sidebar/menu-toggle.js'),
            true
        );
    }

    if (is_singular('chasse')) {
        wp_enqueue_script(
            'chasse-engagement',
            $script_dir . 'chasse-engagement.js',
            [],
            filemtime($theme_path . '/assets/js/chasse-engagement.js'),
            true
        );

        $current_chasse_id = get_queried_object_id();
        if (
            $current_chasse_id
            && function_exists('ca_demo_is_demo_hunt')
            && ca_demo_is_demo_hunt((int) $current_chasse_id)
        ) {
            wp_enqueue_script(
                'chasse-demo-reset',
                $script_dir . 'chasse-demo-reset.js',
                [],
                filemtime($theme_path . '/assets/js/chasse-demo-reset.js'),
                true
            );

            wp_localize_script('chasse-demo-reset', 'caDemoReset', [
                'ajaxUrl'    => admin_url('admin-ajax.php'),
                'confirm'    => __('Voulez-vous vraiment réinitialiser votre progression de démonstration ?', 'chassesautresor-com'),
                'success'    => __('Votre progression a été réinitialisée.', 'chassesautresor-com'),
                'error'      => __('Impossible de réinitialiser votre progression pour le moment.', 'chassesautresor-com'),
                'nonceError' => __('Votre session a expiré. Merci de recharger la page.', 'chassesautresor-com'),
            ]);
        }
    }
});

add_action('wp_enqueue_scripts', function () {
    if (!is_singular('chasse')) {
        wp_dequeue_script('jquery-fancybox');
        wp_dequeue_script('jquery-metadata');
        wp_dequeue_script('jquery-easing');
        wp_dequeue_script('jquery-mousewheel');
        wp_dequeue_style('fancybox');
        wp_dequeue_style('fancybox-ie');
    }
}, 20);

/**
 * Disables the language dropdown on the login page.
 *
 * @return bool
 */
function cta_disable_login_language_dropdown(): bool
{
    return false;
}
add_filter('login_display_language_dropdown', 'cta_disable_login_language_dropdown');

/**
 * Enqueues custom styles for the login page.
 *
 * @return void
 */
function cta_enqueue_login_styles(): void
{
    $theme_uri  = get_stylesheet_directory_uri();
    $theme_path = get_stylesheet_directory();
    $css_rel    = '/assets/css/login.css';

    wp_enqueue_style(
        'cta-login',
        $theme_uri . $css_rel,
        [],
        filemtime($theme_path . $css_rel)
    );

    $logo_id = get_theme_mod('custom_logo');

    if ($logo_id) {
        $logo_data = wp_get_attachment_image_src($logo_id, 'full');

        if (is_array($logo_data)) {
            $logo_url   = $logo_data[0];
            $logo_width = (int) $logo_data[1];
            $logo_height = (int) $logo_data[2];
            $max_width  = 320;

            if ($logo_width > $max_width) {
                $ratio       = $max_width / $logo_width;
                $logo_width  = (int) $max_width;
                $logo_height = (int) floor($logo_height * $ratio);
            }

            $custom_css = sprintf(
                '#login h1 a {' .
                'background-image:url(%1$s);' .
                'background-size:contain;' .
                'background-repeat:no-repeat;' .
                'width:%2$spx;' .
                'height:%3$spx;' .
                '}',
                esc_url($logo_url),
                $logo_width,
                $logo_height
            );

            wp_add_inline_style('cta-login', $custom_css);
        }
    }

}
add_action( 'login_enqueue_scripts', 'cta_enqueue_login_styles' );

/**
 * Adds custom logo and title on the login page.
 *
 * @param string $message Existing login message.
 * @return string
 */
function cta_login_branding( string $message ): string
{
    $action = isset( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : '';

    if ( 'confirm_admin_email' === $action ) {
        return $message;
    }

    $custom_logo_id = get_theme_mod( 'custom_logo' );
    $logo           = $custom_logo_id
        ? wp_get_attachment_image( $custom_logo_id, 'full', false, [ 'alt' => get_bloginfo( 'name' ) ] )
        : '';
    $title          = get_bloginfo( 'name' );

    $html  = '<div class="wp-login-logo"><a href="' . esc_url( home_url( '/' ) ) . '">';
    $html .= $logo ? $logo : '<span class="screen-reader-text">' . esc_html( $title ) . '</span>';
    $html .= '</a></div>';
    $html .= '<h1 class="login-title">' . esc_html( $title ) . '</h1>';

    return $html . $message;
}
add_filter( 'login_message', 'cta_login_branding' );

// ----------------------------------------------------------
// 📂 Chargement des fichiers fonctionnels organisés
// ----------------------------------------------------------

$inc_path = get_stylesheet_directory() . '/inc/';

require_once get_stylesheet_directory() . '/inc/site-password.php';
require_once $inc_path . 'constants.php';
require_once $inc_path . 'utils.php';
require_once $inc_path . 'PointsRepository.php';
require_once $inc_path . 'messages.php';
require_once $inc_path . 'messages/class-user-message-repository.php';
require_once $inc_path . 'emails/template.php';
require_once $inc_path . 'emails/user-registration.php';
require_once $inc_path . 'emails/forgot-password.php';
require_once $inc_path . 'emails/woocommerce.php';

if (defined('WP_CLI') && WP_CLI) {
    require_once $inc_path . 'cli/class-cat-cli-command.php';
}

add_action('shutdown', function (): void {
    global $wpdb;

    (new UserMessageRepository($wpdb))->purgeExpired();
});

require_once $inc_path . 'shortcodes-init.php';
require_once $inc_path . 'enigme-functions.php';
require_once $inc_path . 'user-functions.php';
require_once $inc_path . 'chasse-functions.php';
require_once $inc_path . 'gamify-functions.php';
require_once $inc_path . 'utils/titres.php';
require_once $inc_path . 'statut-functions.php';
require_once $inc_path . 'admin-functions.php';
require_once $inc_path . 'organisateur-functions.php';
//require_once $inc_path . 'stat-functions.php';
require_once $inc_path . 'access-functions.php';
require_once $inc_path . 'relations-functions.php';
require_once $inc_path . 'layout-functions.php';
require_once $inc_path . 'sidebar.php';
require_once $inc_path . 'utils/liens.php';
require_once $inc_path . 'chasse/demo.php';
require_once $inc_path . 'chasse/stats.php';
require_once $inc_path . 'organisateur/stats.php';
require_once $inc_path . 'pager.php';
require_once $inc_path . 'table.php';
require_once $inc_path . 'search/registry.php';
require_once $inc_path . 'search/helpers.php';
require_once $inc_path . 'search/form.php';
require_once $inc_path . 'homepage-filters.php';

require_once $inc_path . 'edition/edition-core.php';
require_once $inc_path . 'edition/edition-organisateur.php';
require_once $inc_path . 'edition/edition-chasse.php';
require_once $inc_path . 'edition/edition-enigme.php';
require_once $inc_path . 'edition/edition-indice.php';
require_once $inc_path . 'edition/edition-solution.php';
require_once $inc_path . 'edition/edition-securite.php';



/**
 * Injecte automatiquement `acf_form_head()` pour les fiches chasse.
 *
 * Ce hook est nécessaire pour que les panneaux latéraux basés sur `acf_form()`
 * (notamment la description WYSIWYG) fonctionnent correctement en front-end.
 *
 * - Il doit être exécuté avant toute sortie HTML.
 * - Il active la prise en charge des redirections, messages de succès, et champs ACF dynamiques.
 * - ACF recommande son appel dans le `header.php`, mais ici on l'injecte proprement via `wp_head` uniquement pour les chasses.
 *
 * 💡 À terme, cette fonction pourrait être déplacée dans un fichier dédié (ex : acf-hooks.php)
 *
 * @hook wp_head
 */
add_action('wp_head', 'forcer_acf_form_head_chasse', 0);
function forcer_acf_form_head_chasse()
{
    if (!is_singular('chasse') || !function_exists('acf_form_head')) {
        return;
    }

    $post_id = get_queried_object_id();

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    acf_form_head();
}


/**
 * 🔁 TÂCHES QUOTIDIENNES INTERNES – SYNCHRONISATION DU CACHE DES ÉNIGMES
 *
 * Cette fonction est appelée par le cron quotidien global du site pour assurer la cohérence
 * entre les chasses et les énigmes qui leur sont réellement associées.
 *
 * Elle utilise la fonction `verifier_et_synchroniser_cache_enigmes_si_autorise()` qui déclenche,
 * si nécessaire, une correction du champ ACF `chasse_cache_enigmes` (relation).
 *
 * 🔧 Cette fonction est placée exceptionnellement dans `functions.php` (racine du thème)
 * car elle fait partie du cœur d'exécution automatique du site, mais ne s’intègre à aucun module métier isolé.
 *
 * 🧱 Si d’autres tâches automatiques internes sont ajoutées à terme (purge, maintenance, synchronisation...),
 * cette logique pourra être déplacée dans un fichier dédié (`inc/cron-functions.php`) pour allègement.
 *
 * @return void
 */
function tache_cron_synchroniser_cache_enigmes(): void {
    $chasses = get_posts([
        'post_type'      => 'chasse',
        'post_status'    => ['publish', 'pending', 'draft'],
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ]);

    foreach ($chasses as $chasse_id) {
        verifier_et_synchroniser_cache_enigmes_si_autorise($chasse_id);
    }
}

