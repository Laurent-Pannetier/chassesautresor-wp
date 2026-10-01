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
        if (!function_exists('est_enigme_resolue_par_utilisateur')) {
            require_once __DIR__ . '/../statut-functions.php';
        }

        $deja_resolue = est_enigme_resolue_par_utilisateur($user_id, $enigme_id);

        if ($deja_resolue) {
            $resolution_date = cat_get_hunt_progress_service()->getRiddleResolutionDate($user_id, $enigme_id);
            if ($resolution_date) {
                $formatted_date = wp_date('d/m/y \\à H:i', strtotime($resolution_date));
                $message        = sprintf(
                    __('Vous avez résolu cette énigme le %s.', 'chassesautresor-com'),
                    $formatted_date
                );
            } else {
                $message = __('Énigme résolue', 'chassesautresor-com');
            }
            $bloc_reponse = '<p class="message-joueur-statut">✅ ' . esc_html($message) . '</p>';
        } else {
            ob_start();
            enigme_get_partial(
                'bloc-reponse',
                $style,
                [
                    'post_id' => $enigme_id,
                    'user_id' => $user_id,
                ]
            );
            $bloc_reponse = trim(ob_get_clean());
        }

        $content = '';

        $hints = (new ChassesAuTresor\Core\Progress\RiddleParticipationService())->hints($enigme_id, $user_id);
        $indices_enigme = $hints['riddle'];
        $indices_chasse = $hints['hunt'];

        if ($bloc_reponse !== '') {
            $content .= '<div class="zone-reponse">' . $bloc_reponse . '</div>';
        }

        if (!empty($indices_enigme) || !empty($indices_chasse)) {
            $content .= '<hr class="reponse-indices-separator" />';
            $build_line = function (array $indices, string $title) {
                $html = '<div class="zone-indices-line"><span class="zone-indices-line__label">'
                    . esc_html($title)
                    . '</span><div class="indice-list">';
                foreach ($indices as $hint) {
                    $indice_id = $hint['id'];
                    $cout_indice = $hint['cost'];
                    $etat_systeme = $hint['state'];
                    $est_debloque = $hint['unlocked'];

                    if ($etat_systeme === 'programme') {
                        $timestamp = $hint['available_at'];

                        $now = current_time('timestamp');
                        if ($timestamp === false || $timestamp > $now) {
                            $date_txt = '';
                            if ($timestamp !== false) {
                                if (wp_date('Y-m-d', $timestamp) === wp_date('Y-m-d', $now)) {
                                    $date_txt = sprintf(
                                        esc_html__("Aujourd’hui à %s", 'chassesautresor-com'),
                                        wp_date('H:i', $timestamp)
                                    );
                                } elseif ($timestamp <= $now + WEEK_IN_SECONDS) {
                                    $date_txt = wp_date('d/m/y \\à H:i', $timestamp);
                                } else {
                                    $date_txt = wp_date('d/m/y', $timestamp);
                                }
                            }
                            if ($date_txt === '') {
                                $date_txt = esc_html__('Bientôt disponible', 'chassesautresor-com');
                            }

                            $html .= '<span class="indice-label indice-link--upcoming etiquette">'
                                . '<i class="fa-solid fa-hourglass" aria-hidden="true"></i> '
                                . esc_html($date_txt)
                                . '</span>';
                            continue;
                        }
                    }
                    if ($est_debloque) {
                        $classes   = 'indice-link indice-link--unlocked etiquette';
                        $etat_icon = 'fa-eye';
                    } else {
                        $classes   = 'indice-link indice-link--locked etiquette';
                        $etat_icon = 'fa-lightbulb';
                    }

                    $label = esc_html($hint['title']);

                    $cout_html = $cout_indice > 0
                        ? ' - ' . $cout_indice . ' <sup>'
                            . esc_html__('pts', 'chassesautresor-com') . '</sup>'
                        : '';

                    $html .= '<a href="#" class="' . esc_attr($classes) . '"'
                        . ' data-indice-id="' . esc_attr($indice_id) . '"'
                        . ' data-cout="' . esc_attr($cout_indice) . '"'
                        . ' data-unlocked="' . ($est_debloque ? '1' : '0') . '">'
                        . '<i class="fa-solid ' . esc_attr($etat_icon) . '" aria-hidden="true"></i> '
                        . $label . $cout_html . '</a>';
                }
                $html .= '</div></div>';
                return $html;
            };

            $content .= '<div class="zone-indices">';
            if (!empty($indices_enigme)) {
                $content .= $build_line($indices_enigme, esc_html__('Indices énigme', 'chassesautresor-com'));
            }
            if (!empty($indices_chasse)) {
                $content .= $build_line($indices_chasse, esc_html__('Indices chasse', 'chassesautresor-com'));
            }
            $content .= '<div class="indice-display"></div></div>';
        }

        $mode_validation = get_field('enigme_mode_validation', $enigme_id);
        $cout            = (int) get_field('enigme_tentative_cout_points', $enigme_id);

        if ($mode_validation === 'aucune') {
            $cout = 0;
        }

        $solde_actuel = ($cout > 0 && function_exists('get_user_points'))
            ? get_user_points($user_id)
            : 0;

        $afficher_tentatives = $mode_validation === 'automatique' && !$deja_resolue;
        $afficher_infos      = $mode_validation !== 'aucune'
            && !$deja_resolue
            && ($cout > 0 || $afficher_tentatives);

        if ($afficher_tentatives && !function_exists('compter_tentatives_du_jour')) {
            require_once __DIR__ . '/tentatives.php';
        }

        if ($afficher_tentatives) {
            $tentatives_utilisees = compter_tentatives_du_jour($user_id, $enigme_id);
            $tentatives_max       = (int) get_field('enigme_tentative_max', $enigme_id);
            $tentatives_max_aff   = $tentatives_max > 0 ? $tentatives_max : '∞';
        }

        if ($afficher_infos) {
            $content .= '<div class="participation-infos txt-small" ';
            $content .= 'style="color:var(--color-text-primary);display:flex;justify-content:space-between;">';

            if ($cout > 0) {
                $content .= '<span class="solde">'
                    . sprintf(esc_html__('Solde : %d pts', 'chassesautresor-com'), $solde_actuel)
                    . '</span>';
            } else {
                $content .= '<span></span>';
            }

            if ($afficher_tentatives) {
                $content .= '<span class="tentatives">'
                    . sprintf(
                        esc_html__('Tentatives quotidiennes : %1$d/%2$s', 'chassesautresor-com'),
                        $tentatives_utilisees,
                        $tentatives_max_aff
                    )
                    . '</span>';
            } elseif ($cout > 0) {
                $content .= '<span></span>';
            }

            $content .= '</div>';
        }

        $cout_badge = '';
        if ($mode_validation !== 'aucune' && $cout > 0) {
            $cout_badge = '<span class="badge-cout" aria-label="'
                . esc_attr(sprintf(
                    esc_html__('Coût par tentative : %d points.', 'chassesautresor-com'),
                    $cout
                ))
                . '">' . esc_html($cout) . ' '
                . esc_html__('pts', 'chassesautresor-com') . '</span>';
        }

        $header = '<div class="participation-header">'
            . '<span></span>'
            . $cout_badge
            . '</div>';

        if ($content !== '') {
            echo '<section class="participation">' . $header . $content . '</section>';
        }
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

        $retour_url   = $chasse_id ? get_permalink($chasse_id) : home_url('/');
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
        echo '<header class="enigme-mobile-header">';
        echo '<a class="enigme-mobile-back" href="' . esc_url($retour_url) . '">';
        echo '<span class="screen-reader-text">' . esc_html__('Retour', 'chassesautresor-com') . '</span>';
        echo '<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>';
        echo '</a>';
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

        echo '<main class="page-enigme enigme-style-' . esc_attr($style) . '">';
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
