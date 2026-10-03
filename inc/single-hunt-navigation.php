<?php

defined('ABSPATH') || exit;

/**
 * Removes obsolete platform and organizer entries from public menus.
 *
 * @param array<int, WP_Post> $items Menu items.
 * @param stdClass            $args  Menu arguments.
 *
 * @return array<int, WP_Post>
 */
function cta_filter_single_hunt_menu_objects(array $items, $args): array
{
    if (!function_exists('cat_is_single_hunt_mode') || !cat_is_single_hunt_mode()) {
        return $items;
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
 * Adds canonical player links to Astra's main menus in single-hunt mode.
 *
 * @param string   $items Rendered menu items.
 * @param stdClass $args  Menu arguments.
 */
function cta_add_single_hunt_primary_links(string $items, $args): string
{
    if (!function_exists('cat_is_single_hunt_mode') || !cat_is_single_hunt_mode()) {
        return $items;
    }

    $location = (string) ($args->theme_location ?? '');
    if (!in_array($location, ['primary', 'mobile_menu'], true)) {
        return $items;
    }

    $huntId = function_exists('cat_get_primary_hunt_id') ? cat_get_primary_hunt_id() : 0;
    $homeUrl = home_url('/');
    $storyUrl = $homeUrl . '#single-hunt-story-title';
    $riddlesUrl = $homeUrl . '#home-enigmes';
    $userId = get_current_user_id();

    if (
        $huntId > 0
        && get_post_status($huntId) === 'publish'
        && function_exists('utilisateur_est_engage_dans_chasse')
        && utilisateur_est_engage_dans_chasse($userId, $huntId)
    ) {
        $riddlesUrl = get_permalink($huntId) . '#chasse-enigmes-wrapper';
    }

    $canonicalItems = [
        'single-hunt-story-link' => [__('La chasse', 'chassesautresor-com'), $storyUrl],
        'single-hunt-riddles-link' => [__('Les énigmes', 'chassesautresor-com'), $riddlesUrl],
    ];

    foreach ($canonicalItems as $className => [$label, $url]) {
        if (strpos($items, $className) !== false) {
            continue;
        }

        $items .= sprintf(
            '<li class="menu-item %1$s"><a class="menu-link" href="%2$s">%3$s</a></li>',
            esc_attr($className),
            esc_url($url),
            esc_html($label)
        );
    }

    if (
        $huntId > 0
        && function_exists('utilisateur_est_organisateur_associe_a_chasse')
        && (
            current_user_can('manage_options')
            || utilisateur_est_organisateur_associe_a_chasse($userId, $huntId)
        )
        && strpos($items, 'single-hunt-manage-link') === false
    ) {
        $huntUrl = get_post_status($huntId) === 'publish'
            ? get_permalink($huntId)
            : get_preview_post_link($huntId);
        $huntUrl = $huntUrl ?: home_url('/');
        $manageUrl = add_query_arg(['edition' => 'open', 'tab' => 'param'], $huntUrl);
        $items .= sprintf(
            '<li class="menu-item single-hunt-manage-link"><a class="menu-link" href="%1$s">%2$s</a></li>',
            esc_url($manageUrl),
            esc_html__('Gérer la chasse', 'chassesautresor-com')
        );
    }

    return $items;
}
add_filter('wp_nav_menu_items', 'cta_add_single_hunt_primary_links', 20, 2);

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
                esc_url(home_url('/#home-enigmes')),
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
