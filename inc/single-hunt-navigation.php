<?php

defined('ABSPATH') || exit;

/**
 * Whether the current request should expose the unique single-hunt top-bar nav.
 */
function cta_is_single_hunt_topbar_nav_enabled(): bool
{
    return function_exists('cat_is_single_hunt_mode') && cat_is_single_hunt_mode();
}

/**
 * Public or preview URL of a hunt page.
 */
function cta_get_hunt_page_url(int $huntId): string
{
    if ($huntId <= 0) {
        return home_url('/');
    }

    $status = (string) get_post_status($huntId);
    if ($status === 'publish') {
        $permalink = get_permalink($huntId);

        return $permalink ? (string) $permalink : home_url('/');
    }

    $preview = get_preview_post_link($huntId);

    return $preview ? (string) $preview : home_url('/');
}

/**
 * Whether the visitor is an administrator or the organizer linked to the hunt.
 */
function cta_is_primary_hunt_staff(int $huntId, int $userId): bool
{
    if ($userId <= 0 || $huntId <= 0) {
        return false;
    }

    if (function_exists('user_can') && user_can($userId, 'administrator')) {
        return true;
    }

    return function_exists('utilisateur_est_organisateur_associe_a_chasse')
        && utilisateur_est_organisateur_associe_a_chasse($userId, $huntId);
}

/**
 * Returns the chasse page URL used by the unique "Énigmes" top-bar link.
 */
function cta_get_single_hunt_enigmes_nav_url(): string
{
    $huntId = function_exists('cat_get_primary_hunt_id') ? cat_get_primary_hunt_id() : 0;
    if ($huntId <= 0) {
        return home_url('/');
    }

    return function_exists('cat_single_hunt_enigmas_url')
        ? cat_single_hunt_enigmas_url()
        : home_url('/#enigmes');
}

/**
 * Whether the unique "Énigmes" top-bar item should appear as the current page.
 */
function cta_is_single_hunt_enigmes_nav_current(): bool
{
    $huntId = function_exists('cat_get_primary_hunt_id') ? cat_get_primary_hunt_id() : 0;
    if ($huntId <= 0) {
        return false;
    }

    if (
        function_exists('is_front_page')
        && is_front_page()
        && function_exists('cat_single_hunt_home_audience')
        && cat_single_hunt_home_audience() !== 'presentation'
    ) {
        return true;
    }

    if (is_singular('enigme') && function_exists('recuperer_id_chasse_associee')) {
        return (int) recuperer_id_chasse_associee((int) get_queried_object_id()) === $huntId;
    }

    return false;
}

/**
 * Empties Astra's primary/mobile menus in single-hunt mode.
 *
 * The unique "Énigmes" entry is rendered outside wp_nav_menu so it stays visible
 * on small screens (not buried in the hamburger panel).
 *
 * Other menus still lose obsolete platform/organizer entries.
 *
 * @param array<int, WP_Post> $items Menu items.
 * @param stdClass            $args  Menu arguments.
 *
 * @return array<int, WP_Post>
 */
function cta_filter_single_hunt_menu_objects(array $items, $args): array
{
    if (!cta_is_single_hunt_topbar_nav_enabled()) {
        return $items;
    }

    $location = (string) ($args->theme_location ?? '');
    if (in_array($location, ['primary', 'mobile_menu'], true)) {
        return [];
    }

    $blockedPaths = [
        '/devenir-organisateur',
        '/creer-mon-profil',
        '/confirmation-organisateur',
    ];

    $blockedIds = [];
    foreach ($items as $item) {
        $object = (string) ($item->object ?? '');
        $blocked = in_array($object, ['organisateur', 'chasse'], true);
        $path = (string) wp_parse_url((string) ($item->url ?? ''), PHP_URL_PATH);

        foreach ($blockedPaths as $blockedPath) {
            if (strpos(untrailingslashit($path), $blockedPath) === 0) {
                $blocked = true;
                break;
            }
        }

        if ($blocked) {
            $blockedIds[] = (int) $item->ID;
        }
    }

    do {
        $previousCount = count($blockedIds);
        foreach ($items as $item) {
            if (in_array((int) ($item->menu_item_parent ?? 0), $blockedIds, true)) {
                $blockedIds[] = (int) $item->ID;
            }
        }
        $blockedIds = array_values(array_unique($blockedIds));
    } while (count($blockedIds) > $previousCount);

    return array_values(array_filter(
        $items,
        static fn($item): bool => !in_array((int) $item->ID, $blockedIds, true)
    ));
}
add_filter('wp_nav_menu_objects', 'cta_filter_single_hunt_menu_objects', 20, 2);

/**
 * Adds a body class used to keep the unique top-bar nav visible on small screens.
 *
 * @param array<int, string> $classes Body classes.
 *
 * @return array<int, string>
 */
function cta_add_single_hunt_body_class(array $classes): array
{
    if (cta_is_single_hunt_topbar_nav_enabled()) {
        $classes[] = 'cat-single-hunt';
    }

    return $classes;
}
add_filter('body_class', 'cta_add_single_hunt_body_class');

/**
 * Renders the unique "Énigmes" top-bar link in Astra's visible header row.
 *
 * The production header keeps logo / account / cart / language in the `above`
 * row (same place as the language switcher). We therefore prefer `above/right`,
 * with a fallback to `primary/right` when the above row is absent. Desktop and
 * mobile builders each render once so the link stays outside the hamburger.
 *
 * @param string $row    Header builder row.
 * @param string $column Header builder column.
 */
function cta_render_single_hunt_enigmes_topbar_link(string $row, string $column): void
{
    if (!cta_is_single_hunt_topbar_nav_enabled()) {
        return;
    }

    if ('right' !== $column || !in_array($row, ['above', 'primary'], true)) {
        return;
    }

    if (!cta_single_hunt_enigmas_menu_visible()) {
        return;
    }

    static $rendered = [
        'desktop' => false,
        'mobile'  => false,
    ];

    $device = current_action() === 'astra_render_mobile_header_column' ? 'mobile' : 'desktop';
    if (!empty($rendered[$device])) {
        return;
    }

    $rendered[$device] = true;

    $url = cta_get_single_hunt_enigmes_nav_url();
    $isCurrent = cta_is_single_hunt_enigmes_nav_current();
    $classes = 'cta-topbar-enigmes ast-builder-layout-element site-header-focus-item';

    if ($isCurrent) {
        $classes .= ' is-current';
    }

    printf(
        '<nav class="%1$s" aria-label="%2$s"><a class="cta-topbar-enigmes__link" href="%3$s"%4$s>%5$s</a></nav>',
        esc_attr($classes),
        esc_attr__('Navigation principale', 'chassesautresor-com'),
        esc_url($url),
        $isCurrent ? ' aria-current="page"' : '',
        esc_html__('Énigmes', 'chassesautresor-com')
    );
}
add_action('astra_render_header_column', 'cta_render_single_hunt_enigmes_topbar_link', 5, 2);
add_action('astra_render_mobile_header_column', 'cta_render_single_hunt_enigmes_topbar_link', 5, 2);

/**
 * Whether the unique "Énigmes" top-bar item is part of the current visitor's path.
 */
function cta_single_hunt_enigmas_menu_visible(): bool
{
    if (!class_exists(\ChassesAuTresor\Core\Site\PlayerEnigmasAccessHandler::class)) {
        return true;
    }

    $huntId = function_exists('cat_get_primary_hunt_id') ? cat_get_primary_hunt_id() : 0;

    return \ChassesAuTresor\Core\Site\PlayerEnigmasAccessHandler::menuVisible(
        $huntId,
        (int) get_current_user_id()
    );
}

/**
 * Destination of the primary-hunt enigmas gate for the given visitor.
 */
function cta_primary_enigmas_destination(int $huntId, int $userId): string
{
    if (!class_exists(\ChassesAuTresor\Core\Site\PlayerEnigmasAccessHandler::class)) {
        return 'allow';
    }

    return \ChassesAuTresor\Core\Site\PlayerEnigmasAccessHandler::destination($huntId, $userId);
}

/**
 * Whether the homepage should explain that enigmas require joining the hunt.
 */
function cta_should_prompt_hunt_join(int $huntId, int $userId): bool
{
    return cta_primary_enigmas_destination($huntId, $userId) === 'home';
}

/**
 * Login link labeled like the engagement button, for visitors without an account.
 */
function cta_render_single_hunt_participate_link(): string
{
    $loginUrl = class_exists(\ChassesAuTresor\Core\Site\PlayerEnigmasAccessHandler::class)
        ? \ChassesAuTresor\Core\Site\PlayerEnigmasAccessHandler::joinLoginUrl()
        : wp_login_url(home_url('/'));

    return sprintf(
        '<a href="%s" class="bouton-cta bouton-cta--color cta-chasse-action">%s</a>',
        esc_url($loginUrl),
        esc_html__('Participer', 'chassesautresor-com')
    );
}

/**
 * Engagement form for a logged-in visitor who has not joined the hunt yet.
 */
function cta_render_single_hunt_participate_form(int $huntId): string
{
    $action = esc_url(site_url('/traitement-engagement'));
    $html = '<form method="post" action="' . $action . '" class="cta-chasse-form">';
    $html .= '<input type="hidden" name="chasse_id" value="' . esc_attr((string) $huntId) . '">';
    $html .= wp_nonce_field('engager_chasse_' . $huntId, 'engager_chasse_nonce', true, false);
    $html .= sprintf(
        '<button type="submit" class="bouton-cta bouton-cta--color">%s</button>',
        esc_html__('Participer', 'chassesautresor-com')
    );
    $html .= '</form>';

    return $html;
}

/**
 * Builds the context-aware primary-hunt CTA used on the homepage.
 *
 * @return array{cta_html:string,cta_message:string,type:string}
 */
function cta_get_primary_hunt_cta(int $huntId, ?int $userId = null): array
{
    $userId = $userId ?? get_current_user_id();
    $cta = function_exists('generer_cta_chasse')
        ? generer_cta_chasse($huntId, $userId)
        : ['cta_html' => '', 'cta_message' => '', 'type' => ''];
    $singleHunt = function_exists('cat_is_single_hunt_mode') && cat_is_single_hunt_mode();

    if ($singleHunt && cta_is_primary_hunt_staff($huntId, $userId)) {
        $staffUrl = function_exists('cat_single_hunt_enigmas_url')
            ? cat_single_hunt_enigmas_url()
            : home_url('/#enigmes');

        return [
            'cta_html' => sprintf(
                '<a href="%s" class="bouton-cta bouton-cta--color">%s</a>',
                esc_url($staffUrl),
                esc_html__('Voir', 'chassesautresor-com')
            ),
            'cta_message' => '',
            'type' => 'voir',
        ];
    }

    if ($singleHunt && ($cta['type'] ?? '') !== 'engage') {
        $participateHtml = (int) $userId <= 0
            ? cta_render_single_hunt_participate_link()
            : cta_render_single_hunt_participate_form($huntId);

        return [
            'cta_html' => $participateHtml,
            'cta_message' => '',
            'type' => 'participer',
        ];
    }

    if (($cta['type'] ?? '') === 'engage') {
        $label = $singleHunt
            ? __('Énigmes', 'chassesautresor-com')
            : __('Reprendre ma progression', 'chassesautresor-com');
        if (class_exists(\ChassesAuTresor\Core\Site\PlayerEnigmasAccessHandler::class)) {
            $url = \ChassesAuTresor\Core\Site\PlayerEnigmasAccessHandler::enigmasUrl($huntId);
        } elseif ($singleHunt) {
            $url = home_url('/#enigmes');
        } else {
            $url = get_permalink($huntId) . '#chasse-enigmes-wrapper';
        }

        return [
            'cta_html' => sprintf(
                '<a href="%s" class="bouton-cta bouton-cta--color">%s</a>',
                esc_url($url),
                esc_html($label)
            ),
            'cta_message' => '',
            'type' => 'engage',
        ];
    }

    return [
        'cta_html' => (string) ($cta['cta_html'] ?? ''),
        'cta_message' => (string) ($cta['cta_message'] ?? ''),
        'type' => (string) ($cta['type'] ?? ''),
    ];
}
