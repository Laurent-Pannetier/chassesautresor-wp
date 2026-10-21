<?php
/**
 * Dashboard edition pages for hunts and riddles.
 *
 * The screen reuses the frontal editor. It is not the WordPress post editor.
 *
 * @package chassesautresor
 */

defined('ABSPATH') || exit;

/**
 * Sections available on a dashboard editor.
 *
 * @return string[]
 */
function cta_shell_edition_sections(string $type): array
{
    if ($type === 'chasse') {
        return ['parametres', 'stats', 'animation'];
    }

    return ['parametres', 'stats', 'animation', 'tentatives'];
}

/**
 * Map a requested section, including the legacy overlay tab names.
 */
function cta_shell_normalize_edition_section(string $type, string $section): string
{
    $section = strtolower($section);
    $legacy = [
        'param' => 'parametres',
        'soumission' => 'tentatives',
    ];
    if (isset($legacy[$section])) {
        $section = $legacy[$section];
    }

    $allowed = cta_shell_edition_sections($type === 'chasse' ? 'chasse' : 'enigme');

    return in_array($section, $allowed, true) ? $section : 'parametres';
}

/**
 * Public URL of a dashboard editor screen.
 */
function cta_shell_edition_url(string $type, int $id, string $section = 'parametres'): string
{
    $type = $type === 'chasse' ? 'chasse' : 'enigme';
    $section = cta_shell_normalize_edition_section($type, $section);
    $base = function_exists('cta_shell_location_url')
        ? cta_shell_location_url('outils')
        : home_url('/outils/');

    return add_query_arg(
        [
            $type => max(0, $id),
            'section' => $section,
        ],
        $base
    );
}

/**
 * @param mixed $url
 */
function cta_shell_filter_edition_url($url, string $type, int $id, string $section): string
{
    unset($url);

    return cta_shell_edition_url($type, $id, $section);
}
add_filter('chassesautresor_edition_url', 'cta_shell_filter_edition_url', 10, 4);

/**
 * Editor requested on the Outils screen, when the current user may open it.
 *
 * @return array{type:string,id:int,section:string}|null
 */
function cta_shell_edition_target(): ?array
{
    if (!function_exists('cta_shell_request_slug') || cta_shell_request_slug() !== 'outils') {
        return null;
    }

    $riddleId = isset($_GET['enigme']) ? absint(wp_unslash($_GET['enigme'])) : 0;
    $huntId = isset($_GET['chasse']) ? absint(wp_unslash($_GET['chasse'])) : 0;
    if ($riddleId > 0) {
        return cta_shell_accept_edition_target('enigme', $riddleId);
    }
    if ($huntId > 0) {
        return cta_shell_accept_edition_target('chasse', $huntId);
    }

    return null;
}

/**
 * @return array{type:string,id:int,section:string}|null
 */
function cta_shell_accept_edition_target(string $type, int $id): ?array
{
    if ($id <= 0 || !function_exists('get_post_type') || get_post_type($id) !== $type) {
        return null;
    }
    if (function_exists('utilisateur_peut_voir_panneau') && !utilisateur_peut_voir_panneau($id)) {
        return null;
    }

    $requested = isset($_GET['section']) ? sanitize_key(wp_unslash($_GET['section'])) : 'parametres';
    $section = cta_shell_normalize_edition_section($type, $requested);
    if ($type === 'enigme' && $section === 'tentatives' && function_exists('get_field')) {
        $mode = (string) get_field('enigme_mode_validation', $id);
        if ($mode === '' || $mode === 'aucune') {
            $section = 'parametres';
        }
    }

    return [
        'type' => $type,
        'id' => $id,
        'section' => $section,
    ];
}

/**
 * Post edited on the current Outils screen, or 0.
 */
function cta_shell_edition_post_id(string $type): int
{
    $target = cta_shell_edition_target();
    if ($target === null || $target['type'] !== $type) {
        return 0;
    }

    return (int) $target['id'];
}

/**
 * @param array<int, string> $classes
 * @return array<int, string>
 */
function cta_shell_edition_body_class(array $classes): array
{
    $target = cta_shell_edition_target();
    if ($target === null) {
        return $classes;
    }

    $classes[] = 'shell-edition';
    $classes[] = 'mode-edition';
    $classes[] = $target['type'] === 'chasse' ? 'edition-active-chasse' : 'edition-active-enigme';

    return $classes;
}
add_filter('body_class', 'cta_shell_edition_body_class');

/**
 * Send legacy overlay links to the dashboard editor.
 */
function cta_shell_redirect_legacy_edition(): void
{
    if (!function_exists('is_singular') || !is_singular(['enigme', 'chasse'])) {
        return;
    }

    $edition = isset($_GET['edition']) ? sanitize_text_field(wp_unslash($_GET['edition'])) : '';
    if ($edition !== 'open') {
        return;
    }

    $id = function_exists('get_queried_object_id') ? (int) get_queried_object_id() : 0;
    $type = $id > 0 && function_exists('get_post_type') ? (string) get_post_type($id) : '';
    if (!in_array($type, ['enigme', 'chasse'], true)) {
        return;
    }

    $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'param';
    wp_safe_redirect(cta_shell_edition_url($type, $id, cta_shell_normalize_edition_section($type, $tab)));
    exit;
}
add_action('template_redirect', 'cta_shell_redirect_legacy_edition');

/**
 * @param array{type:string,id:int,section:string} $target
 */
function cta_shell_render_edition(array $target): void
{
    $post = function_exists('get_post') ? get_post((int) $target['id']) : null;
    if ($post instanceof WP_Post && function_exists('setup_postdata')) {
        $GLOBALS['post'] = $post;
        setup_postdata($post);
    }

    $back = function_exists('cta_shell_location_url')
        ? cta_shell_location_url('outils')
        : home_url('/outils/');

    echo '<div class="shell-edition" data-edition-section="' . esc_attr((string) $target['section']) . '"';
    echo ' data-edition-back="' . esc_url($back) . '">';
    if ($target['type'] === 'chasse') {
        get_template_part(
            'template-parts/chasse/chasse-edition-main',
            null,
            ['chasse_id' => (int) $target['id']]
        );
    } else {
        get_template_part(
            'template-parts/enigme/enigme-edition-main',
            null,
            ['enigme_id' => (int) $target['id']]
        );
    }
    echo '</div>';

    if (function_exists('wp_reset_postdata')) {
        wp_reset_postdata();
    }
}

/**
 * Address used after an editor form is saved, staying on the dashboard page.
 */
function cta_shell_edition_return_url(
    string $type,
    int $id,
    string $section = 'parametres',
    string $fragment = ''
): string {
    $url = cta_shell_edition_url($type, $id, $section);
    $fragment = ltrim($fragment, '#');
    if ($fragment !== '') {
        $url .= '#' . $fragment;
    }

    return $url;
}

/**
 * Riddles of the managed hunt, in the sommaire order (drag-and-drop).
 *
 * @return WP_Post[]
 */
function cta_shell_ordered_riddle_posts(int $huntId): array
{
    if ($huntId <= 0 || !function_exists('get_posts') || !function_exists('recuperer_enigmes_associees')) {
        return [];
    }

    $ids = array_values(array_filter(
        array_map('intval', recuperer_enigmes_associees($huntId)),
        static fn (int $id): bool => $id > 0
    ));
    if ($ids === []) {
        return [];
    }

    $posts = get_posts([
        'post_type' => 'enigme',
        'post_status' => ['publish', 'pending', 'draft', 'private'],
        'posts_per_page' => count($ids),
        'post__in' => $ids,
        'orderby' => 'post__in',
    ]);
    if (!is_array($posts)) {
        return [];
    }

    return array_values(array_filter(
        $posts,
        static fn ($post): bool => $post instanceof WP_Post
    ));
}

/**
 * Riddle list under Outils, including the add action when it is allowed.
 */
function cta_shell_render_outils_submenu(): void
{
    if (!function_exists('cta_shell_user_sees_outils') || !cta_shell_user_sees_outils()) {
        return;
    }

    $huntId = function_exists('cat_get_managed_hunt_id_for_user')
        ? (int) cat_get_managed_hunt_id_for_user()
        : 0;
    $riddles = cta_shell_ordered_riddle_posts($huntId);
    $current = cta_shell_edition_target();
    $currentId = ($current['type'] ?? '') === 'enigme' ? (int) $current['id'] : 0;
    $hasIncomplete = false;
    $userId = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
    if ($riddles === []) {
        $canAddNow = $huntId > 0
            && function_exists('utilisateur_peut_ajouter_enigme')
            && utilisateur_peut_ajouter_enigme($huntId, $userId);
        if (!$canAddNow) {
            return;
        }
    }

    echo '<ul class="app-nav__sub" id="app-nav-outils-enigmes">';
    foreach ($riddles as $riddle) {
        $riddleId = (int) $riddle->ID;
        $missing = function_exists('cat_riddle_missing_labels')
            ? cat_riddle_missing_labels($riddleId)
            : [];
        if ($missing !== []) {
            $hasIncomplete = true;
        }
        $isDraft = (string) $riddle->post_status !== 'publish';
        $isCurrent = $riddleId === $currentId;
        $title = get_the_title($riddleId);
        if (!is_string($title) || $title === '') {
            $title = __('Énigme', 'chassesautresor-com');
        }

        echo '<li class="app-nav__subitem' . ($isCurrent ? ' is-current' : '') . '">';
        echo '<a class="app-nav__sublink' . ($isCurrent ? ' is-active' : '') . '" href="';
        echo esc_url(cta_shell_edition_url('enigme', $riddleId, 'parametres')) . '"';
        if ($isCurrent) {
            echo ' aria-current="page"';
        }
        echo '><span class="app-nav__sublabel">' . esc_html($title) . '</span></a>';
        echo '<span class="app-nav__markers">';
        if ($missing !== []) {
            $popoverId = 'enigme-gaps-' . $riddleId;
            echo '<button type="button" class="app-nav__marker app-nav__marker--incomplete"';
            echo ' popovertarget="' . esc_attr($popoverId) . '"';
            echo ' aria-label="' . esc_attr__('Éléments manquants', 'chassesautresor-com') . '">';
            echo '<i class="fa-solid fa-exclamation" aria-hidden="true"></i></button>';
            echo '<div id="' . esc_attr($popoverId) . '" popover class="app-nav__status-pop"><ul>';
            foreach ($missing as $label) {
                echo '<li>' . esc_html($label) . '</li>';
            }
            echo '</ul></div>';
        }
        if ($isDraft) {
            echo '<span class="app-nav__marker app-nav__marker--draft"';
            echo ' aria-label="' . esc_attr__('Brouillon', 'chassesautresor-com') . '">';
            echo '<i class="fa-regular fa-file" aria-hidden="true"></i></span>';
        }
        echo '</span></li>';
    }

    $canAdd = $huntId > 0
        && !$hasIncomplete
        && function_exists('utilisateur_peut_ajouter_enigme')
        && utilisateur_peut_ajouter_enigme($huntId, $userId);
    if ($canAdd) {
        $addUrl = add_query_arg(
            [
                'chasse_id' => $huntId,
                'nonce' => wp_create_nonce('creer_enigme'),
            ],
            home_url('/creer-enigme/')
        );
        echo '<li class="app-nav__subitem app-nav__subitem--add">';
        echo '<a class="app-nav__add" href="' . esc_url($addUrl) . '">';
        echo '<i class="fa-solid fa-circle-plus" aria-hidden="true"></i>';
        echo '<span>' . esc_html__('Ajouter une énigme', 'chassesautresor-com') . '</span>';
        echo '</a></li>';
    }
    echo '</ul>';
}

/**
 * Card that opens the hunt editor from the Outils grid.
 */
function cta_shell_render_hunt_edit_card(int $huntId): string
{
    if ($huntId <= 0 || !function_exists('utilisateur_peut_voir_panneau') || !utilisateur_peut_voir_panneau($huntId)) {
        return '';
    }

    $title = function_exists('get_the_title') ? (string) get_the_title($huntId) : '';
    if ($title === '') {
        $title = __('Chasse', 'chassesautresor-com');
    }
    $url = cta_shell_edition_url('chasse', $huntId, 'parametres');

    ob_start();
    ?>
    <div class="dashboard-card">
        <div class="dashboard-card-header">
            <i class="fas fa-pen-to-square" aria-hidden="true"></i>
            <h3><?php echo esc_html($title); ?></h3>
        </div>
        <div class="dashboard-card-content">
            <a class="dashboard-card-action" href="<?php echo esc_url($url); ?>">
                <?php esc_html_e('Éditer la chasse', 'chassesautresor-com'); ?>
            </a>
        </div>
    </div>
    <?php

    return (string) ob_get_clean();
}

/**
 * Hover opens the missing-items popover. Click remains available on touch screens.
 */
function cta_shell_enqueue_edition_nav(): void
{
    if (!function_exists('cta_shell_request_slug') || cta_shell_request_slug() !== 'outils') {
        return;
    }

    $path = get_stylesheet_directory() . '/assets/js/shell-edition-nav.js';
    if (!file_exists($path)) {
        return;
    }

    wp_enqueue_script(
        'cta-shell-edition-nav',
        get_stylesheet_directory_uri() . '/assets/js/shell-edition-nav.js',
        [],
        (string) filemtime($path),
        true
    );
}
add_action('wp_enqueue_scripts', 'cta_shell_enqueue_edition_nav');

/**
 * Loads the explicit save bar on a hunt or riddle edition screen.
 */
function cta_shell_enqueue_edition_save(): void
{
    if (!function_exists('cta_shell_edition_target') || cta_shell_edition_target() === null) {
        return;
    }
    if (!wp_script_is('champ-init', 'enqueued')) {
        return;
    }

    $path = get_stylesheet_directory() . '/assets/js/shell-edition-save.js';
    if (!file_exists($path)) {
        return;
    }

    wp_enqueue_script(
        'cta-shell-edition-save',
        get_stylesheet_directory_uri() . '/assets/js/shell-edition-save.js',
        ['wp-i18n'],
        (string) filemtime($path),
        true
    );
    wp_set_script_translations(
        'cta-shell-edition-save',
        'chassesautresor-com',
        get_template_directory() . '/languages'
    );

    $scripts = wp_scripts();
    $deps = $scripts->registered['champ-init']->deps;
    if (!in_array('cta-shell-edition-save', $deps, true)) {
        $scripts->registered['champ-init']->deps[] = 'cta-shell-edition-save';
    }
}
add_action('wp_enqueue_scripts', 'cta_shell_enqueue_edition_save', 20);
