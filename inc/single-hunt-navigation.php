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
 * Returns the chasse page URL used by the unique "Énigmes" top-bar link.
 */
function cta_get_single_hunt_enigmes_nav_url(): string
{
    $huntId = function_exists('cat_get_primary_hunt_id') ? cat_get_primary_hunt_id() : 0;
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
 * Whether the unique "Énigmes" top-bar item should appear as the current page.
 */
function cta_is_single_hunt_enigmes_nav_current(): bool
{
    $huntId = function_exists('cat_get_primary_hunt_id') ? cat_get_primary_hunt_id() : 0;
    if ($huntId <= 0) {
        return false;
    }

    if (is_singular('chasse') && (int) get_queried_object_id() === $huntId) {
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

    if (($cta['type'] ?? '') === 'engage') {
        return [
            'cta_html' => sprintf(
                '<a href="%s" class="bouton-cta bouton-cta--color">%s</a>',
                esc_url(get_permalink($huntId) . '#chasse-enigmes-wrapper'),
                esc_html__('Reprendre ma progression', 'chassesautresor-com')
            ),
            'cta_message' => '',
            'type' => 'engage',
        ];
    }

    if (function_exists('cat_is_demo_mode') && cat_is_demo_mode() && empty($cta['cta_html'])) {
        return [
            'cta_html' => sprintf(
                '<a href="%s" class="bouton-cta bouton-cta--color">%s</a>',
                esc_url(home_url('/#single-hunt-story-title')),
                esc_html__('Découvrir l’aperçu', 'chassesautresor-com')
            ),
            'cta_message' => '',
            'type' => 'demo',
        ];
    }

    return [
        'cta_html' => (string) ($cta['cta_html'] ?? ''),
        'cta_message' => (string) ($cta['cta_message'] ?? ''),
        'type' => (string) ($cta['type'] ?? ''),
    ];
}
