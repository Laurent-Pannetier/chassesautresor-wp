<?php

/**
 * The plugin registers the shortcodes. This theme replaces their fallback HTML
 * with the screens defined in the presentation layer.
 */

defined('ABSPATH') || exit;

add_filter(
    'chassesautresor_points_shortcode',
    static function ($html) {
        if (function_exists('afficher_points_utilisateur_callback')) {
            return (string) afficher_points_utilisateur_callback();
        }

        return $html;
    }
);

add_filter(
    'chassesautresor_manual_answer_shortcode',
    static function ($html, $riddleId) {
        if (function_exists('afficher_formulaire_reponse_manuelle')) {
            return (string) afficher_formulaire_reponse_manuelle((int) $riddleId);
        }

        return $html;
    },
    10,
    2
);
