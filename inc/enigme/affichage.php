<?php
defined('ABSPATH') || exit;
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

        $user_id   = get_current_user_id();
        $style     = get_field('enigme_style_affichage', $enigme_id) ?? 'defaut';
        $chasse_id = recuperer_id_chasse_associee($enigme_id);

        echo '<div class="container container--xl-full enigme-layout">';

        $compact_experience = function_exists('cat_is_single_hunt_mode') && cat_is_single_hunt_mode();
        $can_edit = function_exists('utilisateur_peut_modifier_enigme')
            && utilisateur_peut_modifier_enigme($enigme_id);
        if ($can_edit) {
            $edition_url = function_exists('cta_shell_edition_url')
                ? cta_shell_edition_url('enigme', (int) $enigme_id, 'parametres')
                : home_url('/outils/?enigme=' . (int) $enigme_id . '&section=parametres');
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
            echo '<header class="enigme-mobile-header enigme-mobile-header--no-back">';
            echo '<div class="enigme-mobile-actions">';
            echo '<a class="enigme-mobile-edit" href="' . esc_url($edition_url) . '" aria-label="'
                . esc_attr__('Paramètres', 'chassesautresor-com') . '">';
            echo '<span class="screen-reader-text">' . esc_html__('Paramètres', 'chassesautresor-com') . '</span>';
            echo $settings_icon;
            echo '</a>';
            echo '</div>';
            echo '</header>';
            echo '<a class="bouton-edition-toggle enigme-edit-toggle--desktop" href="'
                . esc_url($edition_url) . '" aria-label="'
                . esc_attr__('Paramètres', 'chassesautresor-com') . '">';
            echo '<span class="screen-reader-text">' . esc_html__('Paramètres', 'chassesautresor-com') . '</span>';
            echo $settings_icon;
            echo '</a>';
        }

        $page_class = 'page-enigme page-enigme--reader enigme-style-' . $style;
        if ($compact_experience) {
            $page_class .= ' page-enigme--compact';
        }
        $step_ids = class_exists(\ChassesAuTresor\Core\Content\RiddleStepQueryService::class)
            ? (new \ChassesAuTresor\Core\Content\RiddleStepQueryService())->findOrderedIds((int) $enigme_id)
            : [];
        if ($step_ids !== []) {
            $page_class .= ' page-enigme--has-steps';
        }

        echo '<main class="' . esc_attr($page_class) . '">';
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

