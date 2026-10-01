<?php
defined('ABSPATH') || exit;

/**
 * Sidebar component rendering functions.
 */

if (!function_exists('sidebar_get_section_html')) {
    /**
     * Retrieve HTML for a sidebar section.
     *
     * @param string $section Section identifier.
     * @param array  $args    Data passed to the template.
     *
     * @return string
     */
    function sidebar_get_section_html(string $section, array $args): string
    {
        $args['section'] = $section;
        ob_start();
        get_template_part('template-parts/common/sidebar-section', null, $args);
        return (string) ob_get_clean();
    }
}


if (!function_exists('ajax_chasse_recuperer_navigation')) {
    /**
     * AJAX handler to refresh hunt navigation items.
     */
    function ajax_chasse_recuperer_navigation(): void
    {
        ChassesAuTresor\Core\Progress\HuntNavigationAjaxHandler::handle();
    }

}

if (!function_exists('render_sidebar')) {
    /**
     * Render the sidebar component and return its sections.
     *
     * @param string   $context              Rendering context ('enigme' or 'chasse').
     * @param int      $enigme_id            Enigma identifier.
     * @param int|null $chasse_id            Associated hunt ID.
     * @param array    $menu_items           Menu items to display.
     * @param bool     $peut_ajouter_enigme  Whether a new enigma can be added.
     * @param int      $total_enigmes        Total number of enigmas.
     * @param bool     $has_incomplete_enigme Whether there is an incomplete enigma.
     *
     * @return array{navigation:string,stats:string}
     */
    function render_sidebar(
        string $context,
        int $enigme_id,
        ?int $chasse_id,
        array $menu_items,
        bool $peut_ajouter_enigme = false,
        int $total_enigmes = 0,
        bool $has_incomplete_enigme = false
    ): array {
        $user_id = get_current_user_id();
        if ($context === 'enigme') {
            $mode = get_field('enigme_mode_validation', $enigme_id);

            $stats_html = enigme_sidebar_progression_html($chasse_id, $user_id)
                . enigme_sidebar_resolution_html($enigme_id);
            $meta_html   = enigme_sidebar_metas_html($enigme_id);
            $winners_html = $mode === 'aucune'
                ? ''
                : enigme_sidebar_gagnants_html($enigme_id, $user_id);
        } else {
            $stats_html   = '';
            $meta_html    = '';
            $winners_html = '';
        }

        $ajout_html = '';
        if (
            $chasse_id
            && $peut_ajouter_enigme
            && $total_enigmes > 0
            && !$has_incomplete_enigme
        ) {
            ob_start();
            get_template_part('template-parts/enigme/chasse-partial-ajout-enigme', null, [
                'has_enigmes' => true,
                'chasse_id'   => $chasse_id,
                'use_button'  => true,
            ]);
            $ajout_html = ob_get_clean();
        }

        $max_visible   = function_exists('apply_filters')
            ? (int) apply_filters('enigme_menu_max_visible', 10)
            : 10;
        $visible_items = array_slice($menu_items, 0, $max_visible);
        $hidden_items  = array_slice($menu_items, $max_visible);

        $navigation_html = sidebar_get_section_html('navigation', [
            'visible_items' => $visible_items,
            'hidden_items'  => $hidden_items,
            'chasse_id'     => $chasse_id,
            'ajout_html'    => $ajout_html,
            'context'       => $context,
        ]);
        if ($navigation_html === '') {
            $navigation_html = '<nav class="enigme-navigation" aria-label="'
                . esc_attr__('Navigation des énigmes', 'chassesautresor-com')
                . '"><h3>'
                . esc_html__('Énigmes', 'chassesautresor-com')
                . '</h3><ul class="enigme-menu"></ul></nav>';
        }

        $stats_section_html = sidebar_get_section_html('stats', [
            'meta_html'    => $meta_html,
            'stats_html'   => $stats_html,
            'winners_html' => $winners_html,
            'enigme_id'    => $enigme_id,
            'context'      => $context,
        ]);

        $chasse_validation = $chasse_id ? get_field('chasse_cache_statut_validation', $chasse_id) : '';
        $aside_classes     = ['menu-lateral'];
        $is_player         = !(
            current_user_can('manage_options')
            || (
                $chasse_id
                && function_exists('utilisateur_est_organisateur_associe_a_chasse')
                && utilisateur_est_organisateur_associe_a_chasse($user_id, $chasse_id)
            )
        );
        if ($is_player) {
            $aside_classes[] = 'joueur';
        }
        if ($chasse_validation === 'en_attente') {
            $aside_classes[] = 'chasse-en-attente';
        }

        echo '<aside class="' . esc_attr(implode(' ', $aside_classes)) . '" data-context="' . esc_attr($context) . '">';

        echo '<div class="menu-lateral__header">';
        if ($chasse_id) {
            $url_chasse = get_permalink($chasse_id);
            $titre      = get_the_title($chasse_id);
            $can_edit   = function_exists('utilisateur_peut_voir_panneau')
                ? utilisateur_peut_voir_panneau($chasse_id)
                : false;

            $titre_html = '<a href="' . esc_url($url_chasse) . '">' . esc_html($titre) . '</a>';
            if ($chasse_validation === 'en_attente') {
                $titre_html = '<i class="fa-solid fa-hourglass" aria-hidden="true"></i>' . $titre_html;
            }
            echo '<h2 class="menu-lateral__title">' . $titre_html . '</h2>';

            if ($can_edit) {
                $edit_url = function_exists('add_query_arg')
                    ? add_query_arg(
                        ['edition' => 'open', 'tab' => 'param'],
                        $url_chasse
                    )
                    : $url_chasse . '?edition=open&tab=param';
                echo '<a class="menu-lateral__edition-toggle enigme-menu__edit" href="'
                    . esc_url($edit_url)
                    . '" aria-label="'
                    . esc_attr__('Paramètres', 'chassesautresor-com')
                    . '"><i class="fa-solid fa-gear"></i></a>';
            }
        }
        echo '<button class="menu-lateral__close" type="button">'
            . '<i class="fa-solid fa-xmark" aria-hidden="true"></i>'
            . '<span class="screen-reader-text">'
            . esc_html__('Fermer le panneau', 'chassesautresor-com')
            . '</span></button>';
        echo '</div>';

        echo '<div class="menu-lateral__content">' . $navigation_html . '</div>';
        echo '<div class="menu-lateral__accordeons">';
        echo '<div class="accordeon-bloc">';
        echo '<div class="accordeon-contenu accordeon-ferme">' . $stats_section_html . '</div>';
        echo '<button class="accordeon-toggle" type="button" aria-expanded="false">'
            . '<i class="fa-solid fa-chevron-down" aria-hidden="true"></i>'
            . '<span class="screen-reader-text">'
            . esc_html__('Afficher les statistiques', 'chassesautresor-com')
            . '</span></button>';
        echo '</div>';
        echo '</div>';
        echo '</aside>';

        return [
            'navigation' => $navigation_html,
            'stats'      => $stats_section_html,
        ];
    }
}
