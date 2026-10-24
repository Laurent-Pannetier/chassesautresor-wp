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
    if ($type === 'organisateur') {
        return ['parametres', 'stats', 'animation', 'revenus'];
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
        'points' => 'revenus',
    ];
    if (isset($legacy[$section])) {
        $section = $legacy[$section];
    }

    $sectionType = in_array($type, ['chasse', 'organisateur'], true) ? $type : 'enigme';
    $allowed = cta_shell_edition_sections($sectionType);

    return in_array($section, $allowed, true) ? $section : 'parametres';
}

/**
 * DOM id of an editor tab for a content type and a section name.
 */
function cta_shell_edition_panel_id(string $type, string $section): string
{
    $panels = [
        'enigme' => [
            'parametres' => 'enigme-tab-param',
            'stats' => 'enigme-tab-stats',
            'animation' => 'enigme-tab-animation',
            'tentatives' => 'enigme-tab-soumission',
        ],
        'chasse' => [
            'parametres' => 'chasse-tab-param',
            'stats' => 'chasse-tab-stats',
            'animation' => 'chasse-tab-animation',
        ],
        'organisateur' => [
            'parametres' => 'organisateur-tab-param',
            'stats' => 'organisateur-tab-stats',
            'animation' => 'organisateur-tab-animation',
            'revenus' => 'organisateur-tab-revenus',
        ],
    ];
    $type = in_array($type, ['chasse', 'organisateur'], true) ? $type : 'enigme';
    $section = cta_shell_normalize_edition_section($type, $section);

    return $panels[$type][$section] ?? $panels[$type]['parametres'];
}

/**
 * Whether the current Outils screen should show this tab.
 */
function cta_shell_edition_tab_is_active(string $type, string $panelId): bool
{
    $target = cta_shell_edition_target();
    $section = is_array($target) ? (string) $target['section'] : 'parametres';

    return cta_shell_edition_panel_id($type, $section) === $panelId;
}

/**
 * Class and target attributes for an editor tab button.
 */
function cta_shell_edition_tab_button_attr(string $type, string $panelId): string
{
    $class = 'edition-tab';
    if (cta_shell_edition_tab_is_active($type, $panelId)) {
        $class .= ' active';
    }

    return 'class="' . esc_attr($class) . '" data-target="' . esc_attr($panelId) . '"';
}

/**
 * Id, class and visibility attributes for an editor tab panel.
 */
function cta_shell_edition_tab_panel_attr(string $type, string $panelId): string
{
    $active = cta_shell_edition_tab_is_active($type, $panelId);
    $class = 'edition-tab-content' . ($active ? ' active' : '');
    $html = 'id="' . esc_attr($panelId) . '" class="' . esc_attr($class) . '"';
    if (!$active) {
        $html .= ' style="display:none;"';
    }

    return $html;
}

/**
 * A requested section must be rewritten when it is not the one the screen will show.
 */
function cta_shell_edition_section_needs_canonical_redirect(
    string $rawSection,
    string $acceptedSection,
    bool $hasLegacyParam
): bool {
    if ($hasLegacyParam) {
        return true;
    }
    if ($rawSection === '' && $acceptedSection === 'parametres') {
        return false;
    }

    return $rawSection !== $acceptedSection;
}

/**
 * Public URL of a dashboard editor screen.
 */
function cta_shell_edition_url(string $type, int $id, string $section = 'parametres'): string
{
    if (!in_array($type, ['chasse', 'enigme', 'organisateur'], true)) {
        $type = 'enigme';
    }
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
    $organizerId = isset($_GET['organisateur']) ? absint(wp_unslash($_GET['organisateur'])) : 0;
    if ($riddleId > 0) {
        return cta_shell_accept_edition_target('enigme', $riddleId);
    }
    if ($huntId > 0) {
        return cta_shell_accept_edition_target('chasse', $huntId);
    }
    if ($organizerId > 0) {
        return cta_shell_accept_edition_target('organisateur', $organizerId);
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
    if ($requested === 'parametres' && isset($_GET['onglet'])) {
        $requested = sanitize_key(wp_unslash($_GET['onglet']));
    } elseif ($requested === 'parametres' && isset($_GET['tab'])) {
        $requested = sanitize_key(wp_unslash($_GET['tab']));
    }
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
    $classes[] = 'shell-edition-tabs-ready';
    $classes[] = 'mode-edition';
    if ($target['type'] === 'chasse') {
        $classes[] = 'edition-active-chasse';
    } elseif ($target['type'] === 'enigme') {
        $classes[] = 'edition-active-enigme';
    } else {
        $classes[] = 'edition-active';
    }

    return $classes;
}
add_filter('body_class', 'cta_shell_edition_body_class');

/**
 * Send legacy overlay links to the dashboard editor.
 */
function cta_shell_redirect_legacy_edition(): void
{
    if (!function_exists('is_singular') || !is_singular(['enigme', 'chasse', 'organisateur'])) {
        return;
    }

    $edition = isset($_GET['edition']) ? sanitize_text_field(wp_unslash($_GET['edition'])) : '';
    if ($edition !== 'open') {
        return;
    }

    $id = function_exists('get_queried_object_id') ? (int) get_queried_object_id() : 0;
    $type = $id > 0 && function_exists('get_post_type') ? (string) get_post_type($id) : '';
    if (!in_array($type, ['enigme', 'chasse', 'organisateur'], true)) {
        return;
    }

    $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : '';
    if ($tab === '' && isset($_GET['onglet'])) {
        $tab = sanitize_key(wp_unslash($_GET['onglet']));
    }
    if ($tab === '') {
        $tab = 'param';
    }
    wp_safe_redirect(cta_shell_edition_url($type, $id, cta_shell_normalize_edition_section($type, $tab)));
    exit;
}
add_action('template_redirect', 'cta_shell_redirect_legacy_edition');

/**
 * Align the address with the section the editor will actually open.
 *
 * Legacy names (soumission, onglet, tab) and a refused section (Tentatives
 * when the riddle has no validation) are replaced. Extra arguments such as
 * uid stay on the URL.
 */
function cta_shell_redirect_canonical_edition_section(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        return;
    }

    $target = cta_shell_edition_target();
    if ($target === null) {
        return;
    }

    $rawSection = isset($_GET['section']) ? sanitize_key((string) wp_unslash($_GET['section'])) : '';
    $hasLegacy = isset($_GET['onglet']) || isset($_GET['tab']);
    if (!cta_shell_edition_section_needs_canonical_redirect(
        $rawSection,
        (string) $target['section'],
        $hasLegacy
    )) {
        return;
    }

    $preserved = [];
    foreach ($_GET as $key => $value) {
        if (!is_string($key)) {
            continue;
        }
        if (in_array($key, ['section', 'onglet', 'tab', 'enigme', 'chasse', 'organisateur'], true)) {
            continue;
        }
        $preserved[$key] = wp_unslash($value);
    }

    $url = cta_shell_edition_url((string) $target['type'], (int) $target['id'], (string) $target['section']);
    if ($preserved !== []) {
        $url = add_query_arg($preserved, $url);
    }

    wp_safe_redirect($url);
    exit;
}
add_action('template_redirect', 'cta_shell_redirect_canonical_edition_section', 11);

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
    } elseif ($target['type'] === 'enigme') {
        get_template_part(
            'template-parts/enigme/enigme-edition-main',
            null,
            ['enigme_id' => (int) $target['id']]
        );
    } else {
        get_template_part(
            'template-parts/organisateur/organisateur-edition-main',
            null,
            ['organisateur_id' => (int) $target['id']]
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
 * Hunt and riddle list under Outils, including the add action when it is allowed.
 *
 * @param bool $expanded False keeps the list out of view and out of tab order.
 */
function cta_shell_render_outils_submenu(bool $expanded = true): void
{
    if (!function_exists('cta_shell_user_sees_outils') || !cta_shell_user_sees_outils()) {
        return;
    }

    $huntId = function_exists('cat_get_managed_hunt_id_for_user')
        ? (int) cat_get_managed_hunt_id_for_user()
        : 0;
    $riddles = cta_shell_ordered_riddle_posts($huntId);
    $current = cta_shell_edition_target();
    $currentHuntId = ($current['type'] ?? '') === 'chasse' ? (int) $current['id'] : 0;
    $currentId = ($current['type'] ?? '') === 'enigme' ? (int) $current['id'] : 0;
    $canEditHunt = $huntId > 0
        && function_exists('utilisateur_peut_voir_panneau')
        && utilisateur_peut_voir_panneau($huntId);
    $hasIncomplete = false;
    $userId = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
    $canAddNow = $huntId > 0
        && function_exists('utilisateur_peut_ajouter_enigme')
        && utilisateur_peut_ajouter_enigme($huntId, $userId);
    if (!$canEditHunt && $riddles === [] && !$canAddNow) {
        return;
    }

    echo '<ul class="app-nav__sub" id="app-nav-outils-enigmes"';
    if (!$expanded) {
        echo ' hidden';
    }
    echo '>';
    if ($canEditHunt) {
        $isCurrentHunt = $huntId === $currentHuntId;
        $huntTitle = get_the_title($huntId);
        if (!is_string($huntTitle) || $huntTitle === '') {
            $huntTitle = __('Chasse', 'chassesautresor-com');
        }

        echo '<li class="app-nav__subitem app-nav__subitem--hunt';
        echo $isCurrentHunt ? ' is-current">' : '">';
        echo '<a class="app-nav__sublink app-nav__sublink--hunt';
        echo $isCurrentHunt ? ' is-active"' : '"';
        echo ' href="' . esc_url(cta_shell_edition_url('chasse', $huntId, 'parametres')) . '"';
        if ($isCurrentHunt) {
            echo ' aria-current="page"';
        }
        echo '><span class="app-nav__subheading">';
        echo '<span class="app-nav__subtype">' . esc_html__('Chasse', 'chassesautresor-com') . '</span>';
        echo '<span class="app-nav__sublabel">' . esc_html($huntTitle) . '</span>';
        echo '</span></a></li>';
    }

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

        echo '<li class="app-nav__subitem app-nav__subitem--riddle';
        echo $isCurrent ? ' is-current">' : '">';
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
 * Organizer the current user may edit from Outils: their own post, or the managed hunt's organizer.
 */
function cta_shell_editable_organizer_id(): int
{
    $userId = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
    $id = 0;
    if ($userId > 0 && function_exists('get_organisateur_from_user')) {
        $id = (int) get_organisateur_from_user($userId);
    }
    if ($id <= 0 && function_exists('cat_get_managed_hunt_id_for_user')) {
        $huntId = (int) cat_get_managed_hunt_id_for_user($userId);
        if ($huntId > 0 && function_exists('get_organisateur_from_chasse')) {
            $id = (int) get_organisateur_from_chasse($huntId);
        }
    }
    if ($id <= 0 || !function_exists('utilisateur_peut_voir_panneau') || !utilisateur_peut_voir_panneau($id)) {
        return 0;
    }

    return $id;
}

/**
 * Card that opens the organizer editor from the Outils grid.
 */
function cta_shell_render_organizer_edit_card(): string
{
    $organizerId = cta_shell_editable_organizer_id();
    if ($organizerId <= 0) {
        return '';
    }

    $title = function_exists('get_the_title') ? (string) get_the_title($organizerId) : '';
    if ($title === '') {
        $title = __('Organisateur', 'chassesautresor-com');
    }
    $url = cta_shell_edition_url('organisateur', $organizerId, 'parametres');

    ob_start();
    ?>
    <div class="dashboard-card">
        <div class="dashboard-card-header">
            <i class="fas fa-id-card" aria-hidden="true"></i>
            <h3><?php echo esc_html($title); ?></h3>
        </div>
        <div class="dashboard-card-content">
            <a class="dashboard-card-action" href="<?php echo esc_url($url); ?>">
                <?php esc_html_e('Éditer l’organisateur', 'chassesautresor-com'); ?>
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
