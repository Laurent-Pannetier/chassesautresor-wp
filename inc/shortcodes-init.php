<?php
defined( 'ABSPATH' ) || exit;

// ----------------------------------------------------------
// 📝 Filtres globaux
// ----------------------------------------------------------

/**
 * 📝 Interprétation des shortcodes dans le contenu.
 *
 * 🔎 Ce filtre permet d'exécuter les shortcodes insérés dans les éditeurs WordPress classiques.
 *
 * @hook the_content
 * @param string $content Contenu original du post.
 * @return string Contenu avec les shortcodes interprétés.
 */
add_filter('the_content', function($content) {
    return do_shortcode($content); // ✅ Active les shortcodes dans le contenu WordPress
});



add_action('init', function() {
    // ----------------------------------------------------------
    // 🏆 points utilisateur
    // ----------------------------------------------------------

    /**
     * 💎 Affiche le solde de points de l’utilisateur connecté.
     * @shortcode [afficher_points_utilisateur]
     */
    add_shortcode('afficher_points_utilisateur', 'afficher_points_utilisateur_callback');
});
