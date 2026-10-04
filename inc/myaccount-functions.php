<?php
/**
 * Helper functions for "Mon Compte" area.
 *
 * @package chassesautresor
 */

defined('ABSPATH') || exit;

/**
 * Whether the user is treated as a player for account navigation.
 *
 * Organizers and administrators use their own cockpit menus; Tentatives stays
 * under the player menu for now.
 */
function myaccount_user_is_player(?WP_User $user = null): bool
{
    $user = $user instanceof WP_User ? $user : wp_get_current_user();
    if (!$user instanceof WP_User || (int) $user->ID <= 0) {
        return false;
    }

    $roles = (array) $user->roles;
    if (in_array('administrator', $roles, true)) {
        return false;
    }

    $organizer_roles = ['organisateur', 'organisateur_creation'];
    if (defined('ROLE_ORGANISATEUR')) {
        $organizer_roles[] = ROLE_ORGANISATEUR;
    }
    if (defined('ROLE_ORGANISATEUR_CREATION')) {
        $organizer_roles[] = ROLE_ORGANISATEUR_CREATION;
    }

    return !array_intersect($organizer_roles, $roles);
}

/**
 * Register the Tentatives WooCommerce account endpoint.
 */
function myaccount_register_account_endpoints(): void
{
    add_rewrite_endpoint('tentatives', EP_ROOT | EP_PAGES);
}
add_action('init', 'myaccount_register_account_endpoints');

/**
 * Flush rewrite rules once after introducing account endpoints.
 */
function myaccount_maybe_flush_account_endpoints(): void
{
    $version = 'tentatives-1';
    if (get_option('ca_myaccount_endpoints_version') === $version) {
        return;
    }

    flush_rewrite_rules(false);
    update_option('ca_myaccount_endpoints_version', $version);
}
add_action('init', 'myaccount_maybe_flush_account_endpoints', 99);

/**
 * Expose the Tentatives endpoint to WooCommerce query vars.
 *
 * @param array<string, string> $vars Query vars.
 * @return array<string, string>
 */
function myaccount_register_account_query_vars(array $vars): array
{
    $vars['tentatives'] = 'tentatives';

    return $vars;
}
add_filter('woocommerce_get_query_vars', 'myaccount_register_account_query_vars');

/**
 * Keep Tentatives off the Accueil dashboard; they live on their own page.
 */
function myaccount_unhook_dashboard_attempts(): void
{
    remove_action(
        'woocommerce_account_dashboard',
        [ChassesAuTresor\Core\Users\AccountDashboardHookHandler::class, 'renderAttempts'],
        20
    );
}
add_action('init', 'myaccount_unhook_dashboard_attempts', 20);

/**
 * Build the primary sidebar navigation items for the current user.
 *
 * @param WP_User|null $user Current user.
 * @return array<int, array<string, mixed>>
 */
function myaccount_get_sidebar_nav_items(?WP_User $user = null): array
{
    $user = $user instanceof WP_User ? $user : wp_get_current_user();
    $is_settings = function_exists('is_wc_endpoint_url')
        && (is_wc_endpoint_url('edit-account') || is_wc_endpoint_url('orders') || is_wc_endpoint_url('edit-address'));
    $is_tentatives = function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('tentatives');
    $is_home = function_exists('is_account_page')
        && is_account_page()
        && !is_wc_endpoint_url()
        && empty($_GET['section']);

    $items = [
        [
            'endpoint' => 'dashboard',
            'label'    => __('Accueil', 'chassesautresor-com'),
            'icon'     => 'fas fa-home',
            'url'      => wc_get_account_endpoint_url('dashboard'),
            'active'   => $is_home,
        ],
    ];

    if (myaccount_user_is_player($user)) {
        $items[] = [
            'endpoint' => 'tentatives',
            'label'    => __('Tentatives', 'chassesautresor-com'),
            'icon'     => 'fas fa-list-check',
            'url'      => wc_get_account_endpoint_url('tentatives'),
            'active'   => $is_tentatives,
        ];
    }

    $items[] = [
        'endpoint' => 'edit-account',
        'label'    => __('Profil', 'chassesautresor-com'),
        'icon'     => 'fas fa-user',
        'url'      => wc_get_account_endpoint_url('edit-account'),
        'active'   => $is_settings,
    ];

    return $items;
}

/**
 * Render a dashboard section shell.
 *
 * @param string $title   Section title.
 * @param string $intro   Short supporting sentence.
 * @param string $content Inner HTML.
 */
function myaccount_render_dashboard_section(string $title, string $intro = '', string $content = ''): void
{
    echo '<section class="dashboard-section">';
    echo '<header class="dashboard-section-header">';
    echo '<h2 class="dashboard-section-title">' . esc_html($title) . '</h2>';
    if ($intro !== '') {
        echo '<p class="dashboard-section-intro">' . esc_html($intro) . '</p>';
    }
    echo '</header>';
    if ($content !== '') {
        echo '<div class="dashboard-section-body">' . $content . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
    echo '</section>';
}

/**
 * Render a non-interactive placeholder card.
 */
function myaccount_render_dashboard_placeholder(string $title, string $message): void
{
    echo '<div class="dashboard-card dashboard-placeholder" aria-disabled="true">';
    echo '<div class="dashboard-card-header">';
    echo '<h3>' . esc_html($title) . '</h3>';
    echo '</div>';
    echo '<div class="dashboard-card-content">';
    echo '<p>' . esc_html($message) . '</p>';
    echo '</div>';
    echo '</div>';
}

/**
 * Enqueue account-home lifecycle switch assets.
 */
function myaccount_enqueue_hunt_lifecycle_switch(): void
{
    if (!is_account_page() || !is_user_logged_in()) {
        return;
    }

    $path = get_stylesheet_directory() . '/assets/js/hunt-lifecycle-switch.js';
    if (!file_exists($path)) {
        return;
    }

    $hunt_id = function_exists('cat_get_managed_hunt_id_for_user')
        ? cat_get_managed_hunt_id_for_user()
        : 0;
    if ($hunt_id <= 0) {
        return;
    }

    wp_enqueue_script(
        'hunt-lifecycle-switch',
        get_stylesheet_directory_uri() . '/assets/js/hunt-lifecycle-switch.js',
        [],
        filemtime($path),
        true
    );

    wp_localize_script(
        'hunt-lifecycle-switch',
        'ctaHuntLifecycle',
        [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonces' => [
                (string) $hunt_id => wp_create_nonce('cta_toggle_hunt_lifecycle_' . $hunt_id),
            ],
            'errorMessage' => __('Impossible de mettre à jour l’état de la chasse.', 'chassesautresor-com'),
        ]
    );
}
add_action('wp_enqueue_scripts', 'myaccount_enqueue_hunt_lifecycle_switch');

/**
 * Enqueue the top-bar account menu (hover + touch).
 */
function myaccount_enqueue_header_account_menu(): void
{
    if (!is_user_logged_in()) {
        return;
    }

    $path = get_stylesheet_directory() . '/assets/js/header-account-menu.js';
    if (!file_exists($path)) {
        return;
    }

    wp_enqueue_script(
        'header-account-menu',
        get_stylesheet_directory_uri() . '/assets/js/header-account-menu.js',
        [],
        filemtime($path),
        true
    );

    wp_localize_script(
        'header-account-menu',
        'ctaHeaderAccountMenu',
        [
            'accountUrl'  => wc_get_account_endpoint_url('dashboard'),
            'settingsUrl' => wc_get_account_endpoint_url('edit-account'),
            'logoutUrl'   => wc_logout_url(),
            'labels'      => [
                'menu'     => __('Menu du compte', 'chassesautresor-com'),
                'account'  => __('Mon compte', 'chassesautresor-com'),
                'settings' => __('Profil', 'chassesautresor-com'),
                'logout'   => __('Déconnexion', 'chassesautresor-com'),
            ],
        ]
    );
}
add_action('wp_enqueue_scripts', 'myaccount_enqueue_header_account_menu');

/**
 * Retrieve organizer navigation data for the sidebar.
 *
 * @param int $user_id User ID.
 * @return array|null Navigation data or null if no organizer.
 */
function myaccount_get_organizer_nav(int $user_id): ?array
{
    $organizer_id = get_organisateur_from_user($user_id);
    if (!$organizer_id) {
        return null;
    }

    $organizer_post_status = get_post_status($organizer_id);
    $organizer_complete    = (bool) get_field('organisateur_cache_complet', $organizer_id);
    $navigationService = new ChassesAuTresor\Core\Content\OrganizerNavigationService();
    $queryService = new ChassesAuTresor\Core\Relationships\OrganizerHuntQueryService();
    $organizer_classes = $navigationService->getOrganizerClasses(
        $organizer_complete,
        (string) $organizer_post_status
    );
    $chasses = get_posts($queryService->getNavigationHuntsQueryArgs((int) $organizer_id));

    $pending_enigmes = recuperer_enigmes_tentatives_en_attente($organizer_id);

    $show_organizer = !function_exists('cat_is_platform_mode') || cat_is_platform_mode();

    $data = [
        'show_organizer' => $show_organizer,
        'organizer' => [
            'url'     => get_permalink($organizer_id),
            'title'   => get_the_title($organizer_id),
            'classes' => $organizer_classes,
        ],
        'chasses' => [],
    ];

    foreach ($chasses as $chasse) {
        $status_validation = get_field('chasse_cache_statut_validation', $chasse->ID);
        $complet           = get_field('chasse_cache_complet', $chasse->ID);
        $post_status       = get_post_status($chasse->ID);
        $presentation = $navigationService->getHuntPresentation(
            (bool) $complet,
            (string) $post_status,
            (string) $status_validation,
            function_exists('peut_valider_chasse') && peut_valider_chasse($chasse->ID, $user_id)
        );
        if ($presentation === null) {
            continue;
        }

        $chasse_item = [
            'title'        => get_the_title($chasse->ID),
            'url'          => get_permalink($chasse->ID),
            'classes'      => $presentation['classes'],
            'pending_icon' => $presentation['pending_icon'],
            'enigmes'      => [],
        ];

        $enigme_ids = recuperer_ids_enigmes_pour_chasse($chasse->ID);
        foreach ($enigme_ids as $enigme_id) {
            $url              = get_permalink($enigme_id);
            $enigme_complete  = get_field('enigme_cache_complet', $enigme_id);
            $post_status      = get_post_status($enigme_id);
            $etat_enigme      = get_field('enigme_cache_etat_systeme', $enigme_id);

            $sub_classes = $navigationService->getRiddleClasses(
                (bool) $enigme_complete,
                (string) $post_status,
                (string) $etat_enigme,
                in_array($enigme_id, $pending_enigmes, true)
            );
            if ($sub_classes === null) {
                continue;
            }

            $chasse_item['enigmes'][] = [
                'title'   => get_the_title($enigme_id),
                'url'     => $url,
                'classes' => $sub_classes,
            ];
        }

        $data['chasses'][] = $chasse_item;
    }

    return $data;
}

/**
 * Render organizer navigation HTML for the sidebar.
 *
 * @param array $data Navigation data from myaccount_get_organizer_nav().
 * @return string HTML output.
 */
function myaccount_render_organizer_nav(array $data): string
{
    $show_organizer = !empty($data['show_organizer']);
    ob_start();
    ?>
    <nav
        class="dashboard-nav organizer-nav<?php echo $show_organizer ? '' : ' organizer-nav--hunt-first'; ?>"
        aria-label="<?php esc_attr_e('Organisation', 'chassesautresor-com'); ?>"
    >
        <?php if ($show_organizer) : ?>
            <a href="<?php echo esc_url($data['organizer']['url']); ?>" class="<?php echo esc_attr($data['organizer']['classes']); ?>">
                <i class="fas fa-landmark" aria-hidden="true"></i>
                <span class="nav-title"><?php echo esc_html($data['organizer']['title']); ?></span>
            </a>
        <?php endif; ?>
        <?php foreach ($data['chasses'] as $chasse) : ?>
            <?php
            $tag  = $chasse['url'] ? 'a' : 'span';
            $attr = $chasse['url'] ? ' href="' . esc_url($chasse['url']) . '"' : '';
            ?>
            <<?php echo $tag . $attr; ?> class="<?php echo esc_attr($chasse['classes']); ?>">
                <span class="nav-title"><?php echo esc_html($chasse['title']); ?></span>
                <?php if ($chasse['pending_icon']) : ?>
                    <i class="fas fa-hourglass-half" aria-hidden="true"></i>
                <?php endif; ?>
            </<?php echo $tag; ?>>
            <?php foreach ($chasse['enigmes'] as $enigme) : ?>
                <?php
                $sub_tag  = $enigme['url'] ? 'a' : 'span';
                $sub_attr = $enigme['url'] ? ' href="' . esc_url($enigme['url']) . '"' : '';
                ?>
                <<?php echo $sub_tag . $sub_attr; ?> class="<?php echo esc_attr($enigme['classes']); ?>">
                    <span class="nav-title"><?php echo esc_html($enigme['title']); ?></span>
                </<?php echo $sub_tag; ?>>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>
    <?php
    return ob_get_clean();
}
