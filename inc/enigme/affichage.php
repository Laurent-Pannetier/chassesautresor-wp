<?php
defined('ABSPATH') || exit;
require_once __DIR__ . '/../sidebar.php';
require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/indices.php';

    // ==================================================
    // 🎨 AFFICHAGE STYLISÉ DES ÉNIGMES
    // ==================================================
    /**
     * 🔹 afficher_enigme_stylisee() → Affiche l’énigme avec son style d’affichage (structure unique + blocs surchargeables)
     * 🔸 enigme_get_partial() → Charge un partiel adapté au style (ex: pirate/images.php), avec fallback global.
     */

    /**
     * Render the hero section for the enigma.
     *
     * @param int    $enigme_id Enigma identifier.
     * @param string $style     Display style.
     * @param int    $user_id   Current user ID.
     */
    function render_enigme_hero(int $enigme_id, string $style, int $user_id): void
    {
        echo '<section class="hero-visuel">';
        enigme_get_partial(
            'images',
            $style,
            [
                'post_id' => $enigme_id,
                'user_id' => $user_id,
            ]
        );
        echo '</section>';
    }

    /**
     * Render the title and subtitle section of the enigma.
     *
     * @param int    $enigme_id Enigma identifier.
     * @param string $style     Display style.
     * @param int    $user_id   Current user ID.
     */
    function render_enigme_title(int $enigme_id, string $style, int $user_id): void
    {
        echo '<header class="bloc-titre enigme-header">';
        enigme_get_partial(
            'titre',
            $style,
            [
                'post_id' => $enigme_id,
                'user_id' => $user_id,
            ]
        );
        echo '</header>';
    }

    /**
     * Render the textual content section of the enigma.
     *
     * @param int    $enigme_id Enigma identifier.
     * @param string $style     Display style.
     * @param int    $user_id   Current user ID.
     */
    function render_enigme_content(int $enigme_id, string $style, int $user_id): void
    {
        echo '<article class="contenu-principal">';
        enigme_get_partial(
            'texte',
            $style,
            [
                'post_id' => $enigme_id,
                'user_id' => $user_id,
            ]
        );
        echo '</article>';
    }

    /**
     * Render the participation section of the enigma.
     *
     * @param int    $enigme_id Enigma identifier.
     * @param string $style     Display style.
    * @param int    $user_id   Current user ID.
     */
    function render_enigme_participation(int $enigme_id, string $style, int $user_id): void
    {
        $response = '';
        if (!est_enigme_resolue_par_utilisateur($user_id, $enigme_id)) {
            ob_start();
            enigme_get_partial(
                'bloc-reponse',
                $style,
                [
                    'post_id' => $enigme_id,
                    'user_id' => $user_id,
                ]
            );
            $response = trim((string) ob_get_clean());
        }

        echo (new ChassesAuTresor\Core\Progress\RiddlePlayerPanelRenderer())->render(
            $enigme_id,
            $user_id,
            $response
        );
    }

    /**
     * Render the solution section of the enigma.
     *
     * @param int    $enigme_id Enigma identifier.
     * @param string $style     Display style.
     * @param int    $user_id   Current user ID.
     */
    function render_enigme_solution(int $enigme_id, string $style, int $user_id): void
    {
        $cache = ChassesAuTresor\Core\Content\RiddleRenderCacheHookHandler::class;
        $html = $cache::get('enigme_solution', $enigme_id);

        if ($html === null) {
            ob_start();
            ob_start();
            enigme_get_partial(
                'solution',
                $style,
                [
                    'post_id' => $enigme_id,
                    'user_id' => $user_id,
                ]
            );
            $content = trim(ob_get_clean());
            if ($content !== '') {
                echo '<section class="solution">';
                echo '<details><summary>' . esc_html__('Voir la solution', 'chassesautresor-com') . '</summary>';
                echo '<div class="solution-content">'
                    . $content
                    . '</div>';
                echo '</details>';
                echo '</section>';
            }
            $html = ob_get_clean();
            $cache::put('enigme_solution', $enigme_id, $html);
        }

        echo $html;
    }

    /**     
     * Affiche l’énigme avec son style et son état selon le contexte utilisateur.
     *
     * @param int $enigme_id ID de l’énigme à afficher.
     * @param array $statut_data Données de statut retournées par traiter_statut_enigme().
     */
    function afficher_enigme_stylisee(int $enigme_id, array $statut_data = []): void
    {
        if (get_post_type($enigme_id) !== 'enigme') {
            return;
        }

        if (!empty($statut_data)) {
            // statut_data transmis
        } else {
            // Aucune donnée statut_data transmise à afficher_enigme_stylisee()
        }

        $etat = get_field('enigme_cache_etat_systeme', $enigme_id) ?? 'accessible';

        if ($etat !== 'accessible' && !utilisateur_peut_modifier_enigme($enigme_id)) {
            $chasse_id = recuperer_id_chasse_associee($enigme_id);
            $url       = $chasse_id ? get_permalink($chasse_id) : home_url('/');
            wp_safe_redirect($url);
            exit;
        }

        if (!empty($statut_data['afficher_message'])) {
            echo $statut_data['message_html'];
        }

        $user_id        = get_current_user_id();
        $style          = get_field('enigme_style_affichage', $enigme_id) ?? 'defaut';
        $chasse_id      = recuperer_id_chasse_associee($enigme_id);
        $chasse_stat = $chasse_id ? get_field('chasse_cache_statut', $chasse_id) : '';
        $show_menu   = enigme_user_can_see_menu($user_id, $chasse_id, $chasse_stat);

        $menu_items           = [];
        $peut_ajouter_enigme  = false;
        $total_enigmes        = 0;
        $has_incomplete_enigme = false;

        if ($chasse_id && $show_menu) {
            $sidebar_data         = sidebar_prepare_chasse_nav(
                $chasse_id,
                $user_id,
                $enigme_id
            );
            $menu_items           = $sidebar_data['menu_items'];
            $peut_ajouter_enigme  = $sidebar_data['peut_ajouter_enigme'];
            $total_enigmes        = $sidebar_data['total_enigmes'];
            $has_incomplete_enigme = $sidebar_data['has_incomplete_enigme'];
        }

        $peut_voir_aside = $chasse_id && $show_menu;
        $layout_class    = $peut_voir_aside ? 'enigme-layout' : 'enigme-layout enigme-layout--aside-hidden';
        echo '<div class="container container--xl-full ' . esc_attr($layout_class) . '">';
        $sidebar_sections = ['navigation' => '', 'stats' => ''];
        if ($peut_voir_aside) {
            $sidebar_sections = render_sidebar(
                'enigme',
                $enigme_id,
                $chasse_id,
                $menu_items,
                $peut_ajouter_enigme,
                $total_enigmes,
                $has_incomplete_enigme
            );
        }

        $retour_url = $chasse_id ? get_permalink($chasse_id) : home_url('/');
        $compact_experience = function_exists('cat_is_single_hunt_mode') && cat_is_single_hunt_mode();
        // En mode chasse unique, le retour texte (enigme-hunt-context) suffit :
        // on n'affiche pas la pastille image pour éviter le doublon.
        $show_hunt_back_pastille = !$compact_experience;
        $settings_icon = '<svg aria-hidden="true" viewBox="0 0 24 24" fill="none"'
            . ' stroke="currentColor" stroke-width="2" stroke-linecap="round"'
            . ' stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path'
            . ' d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83'
            . ' 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1'
            . ' 1.51V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65'
            . ' 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65'
            . ' 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09'
            . ' a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2'
            . ' 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65'
            . ' 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1'
            . ' 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06'
            . ' a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2'
            . ' 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>';
        $header_class = 'enigme-mobile-header';
        if (!$show_hunt_back_pastille) {
            $header_class .= ' enigme-mobile-header--no-back';
        }
        echo '<header class="' . esc_attr($header_class) . '">';
        if ($show_hunt_back_pastille) {
            $hunt_image = $chasse_id ? get_field('chasse_principale_image', $chasse_id) : null;
            $hunt_image_id = is_array($hunt_image) ? (int) ($hunt_image['ID'] ?? 0) : (int) $hunt_image;
            $hunt_thumbnail = $hunt_image_id > 0
                ? wp_get_attachment_image($hunt_image_id, 'thumbnail', false, [
                    'class' => 'enigme-hunt-back__image',
                    'alt' => '',
                ])
                : '';
            echo '<a class="enigme-mobile-back enigme-hunt-back" href="' . esc_url($retour_url) . '" aria-label="'
                . esc_attr__('Retour à la chasse', 'chassesautresor-com') . '">';
            echo $hunt_thumbnail;
            echo '<span class="enigme-hunt-back__icon"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none"'
                . ' stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">'
                . '<path d="M15 18l-6-6 6-6"/></svg></span>';
            echo '</a>';
        }
        echo '<div class="enigme-mobile-actions">';
        if (function_exists('utilisateur_peut_modifier_enigme') && utilisateur_peut_modifier_enigme($enigme_id)) {
            echo '<button type="button" class="toggle-mode-edition-enigme enigme-mobile-edit" aria-label="'
                . esc_attr__('Paramètres', 'chassesautresor-com') . '">';
            echo '<span class="screen-reader-text">' . esc_html__('Paramètres', 'chassesautresor-com') . '</span>';
            echo $settings_icon;
            echo '</button>';
        }
        if ($peut_voir_aside) {
            echo '<button type="button" class="enigme-mobile-panel-toggle" aria-controls="enigme-mobile-panel" aria-expanded="false" aria-label="' . esc_attr__('Menu énigme', 'chassesautresor-com') . '">';
            echo '<span class="screen-reader-text">' . esc_html__('Menu énigme', 'chassesautresor-com') . '</span>';
            echo '<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>';
            echo '</button>';
        }
        echo '</div>';
        echo '</header>';

        if (function_exists('utilisateur_peut_modifier_enigme') && utilisateur_peut_modifier_enigme($enigme_id)) {
            echo '<button type="button" class="bouton-edition-toggle enigme-edit-toggle--desktop toggle-mode-edition-enigme" aria-label="'
                . esc_attr__('Paramètres', 'chassesautresor-com') . '">';
            echo '<span class="screen-reader-text">' . esc_html__('Paramètres', 'chassesautresor-com') . '</span>';
            echo $settings_icon;
            echo '</button>';
        }

        if ($peut_voir_aside) {
            echo '<div id="enigme-mobile-panel" class="enigme-mobile-panel" hidden>';
            echo '<div class="enigme-mobile-panel__overlay" tabindex="-1"></div>';
            echo '<div class="enigme-mobile-panel__sheet" role="dialog" aria-modal="true" aria-labelledby="enigme-mobile-panel-title">';
            echo '<h2 id="enigme-mobile-panel-title" class="screen-reader-text">' . esc_html__('Panneau d\'énigme', 'chassesautresor-com') . '</h2>';
            echo '<nav class="enigme-mobile-panel__tabs" role="tablist">';
            echo '<button type="button" role="tab" aria-selected="true" class="panel-tab" data-target="panel-enigmes">' . esc_html__('Énigmes', 'chassesautresor-com') . '</button>';
            echo '<button type="button" role="tab" aria-selected="false" class="panel-tab" data-target="panel-stats">' . esc_html__('Statistiques', 'chassesautresor-com') . '</button>';
            echo '</nav>';
            echo '<div class="enigme-mobile-panel__content">';
            echo '<div id="panel-enigmes" class="panel-tab-content">' . ($sidebar_sections['navigation'] ?? '') . '</div>';
            $ajax_url = function_exists('admin_url') ? admin_url('admin-ajax.php') : '';
            echo '<div id="panel-stats" class="panel-tab-content" hidden aria-live="polite" data-ajax-url="'
                . esc_url($ajax_url) . '" data-enigme-id="' . intval($enigme_id) . '" data-nonce="'
                . esc_attr(wp_create_nonce('statistics_management')) . '">'
                . ($sidebar_sections['stats'] ?? '') . '</div>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }

        $page_class = 'page-enigme enigme-style-' . $style;
        if ($compact_experience) {
            $page_class .= ' page-enigme--compact';
        }

        echo '<main class="' . esc_attr($page_class) . '">';
        if ($compact_experience && $chasse_id) {
            echo '<a class="enigme-hunt-context" href="' . esc_url($retour_url) . '">';
            echo '<span aria-hidden="true">&larr;</span> ';
            echo esc_html(get_the_title($chasse_id));
            echo '</a>';
        }
        render_enigme_title($enigme_id, $style, $user_id);
        render_enigme_hero($enigme_id, $style, $user_id);
        render_enigme_content($enigme_id, $style, $user_id);
        if (!(
            est_organisateur($user_id)
            && utilisateur_est_organisateur_associe_a_chasse($user_id, $chasse_id)
        )) {
            render_enigme_participation($enigme_id, $style, $user_id);
        }
        render_enigme_solution($enigme_id, $style, $user_id);
        echo '</main>';
        echo '</div>';
    }


    /**
     * Charge un partiel adapté au style d’énigme (ex: pirate/images.php), avec fallback global.
     *
     * @param string $slug   Nom du bloc (titre, images, etc.)
     * @param string $style  Style d’affichage (ex : 'pirate', 'vintage')
     * @param array  $args   Données à transmettre au partial
     */
    function enigme_get_partial(string $slug, string $style = 'defaut', array $args = []): void
    {
        $base_path = "template-parts/enigme/partials";

        // 🧠 Nouveau : on préfixe tous les fichiers par 'enigme-partial-'
        $slug_final = 'enigme-partial-' . $slug;

        $variant = "{$base_path}/{$style}/{$slug_final}.php";
        $fallback = "{$base_path}/{$slug_final}.php";

        if (locate_template($variant)) {
            get_template_part("{$base_path}/{$style}/{$slug_final}", null, $args);
        } elseif (locate_template($fallback)) {
            get_template_part("{$base_path}/{$slug_final}", null, $args);
        } else {
            cat_debug("❌ Aucun partial trouvé pour $slug (style: $style)");
        }
    }

    /**
     * AJAX handler to fetch winners table.
     */
    function ajax_enigme_recuperer_gagnants(): void
    {
        ChassesAuTresor\Core\Progress\RiddleSidebarAjaxHandler::winners();
    }

    /**
     * AJAX handler to refresh the statistics section.
     */
    function ajax_enigme_recuperer_progression(): void
    {
        ChassesAuTresor\Core\Progress\RiddleSidebarAjaxHandler::progression();
    }

    /**
     * Enqueue scripts for the winners pager.
     */
    function enigme_enqueue_gagnants_scripts(): void
    {
        if (!is_singular('enigme')) {
            return;
        }

        $enigme_id = get_queried_object_id();
        if (get_field('enigme_mode_validation', $enigme_id) === 'aucune') {
            return;
        }

        $dir = get_stylesheet_directory();
        $uri = get_stylesheet_directory_uri();

        wp_enqueue_script(
            'pager',
            $uri . '/assets/js/core/pager.js',
            [],
            filemtime($dir . '/assets/js/core/pager.js'),
            true
        );
        $path = '/assets/js/enigme-gagnants.js';
        wp_enqueue_script(
            'enigme-gagnants',
            $uri . $path,
            ['pager'],
            filemtime($dir . $path),
            true
        );
        wp_localize_script('enigme-gagnants', 'RiddleSidebarAjax', [
            'nonce' => wp_create_nonce('riddle_sidebar'),
        ]);
    }
    add_action('wp_enqueue_scripts', 'enigme_enqueue_gagnants_scripts');
