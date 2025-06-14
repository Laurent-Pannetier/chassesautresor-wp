<?php
/**
 * chassesautresor.com Theme functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package chassesautresor.com
 * @since 1.0.0
 */
defined( 'ABSPATH' ) || exit;
/**
 * Define Constants
 */
define( 'CHILD_THEME_CHASSESAUTRESOR_COM_VERSION', '1.0.0' );


/**
 * Chargement des styles du thème parent et enfant avec prise en charge d'Astra.
 */
add_action('wp_enqueue_scripts', function () {
    $theme_dir = get_stylesheet_directory_uri() . '/assets/css/';

    // 🎨 Chargement des styles du thème parent (Astra) et enfant
    wp_enqueue_style('astra-style', get_template_directory_uri() . '/style.css');
    wp_enqueue_style('mon-theme-enfant-style', get_stylesheet_directory_uri() . '/style.css', ['astra-style'], filemtime(get_stylesheet_directory() . '/style.css'));

    // 📂 Liste des fichiers CSS organisés
    $styles = [
        'layout'             => 'layout.css',
        'components'         => 'components.css',
        'general-style'      => 'general.css',
        'chasse-style'       => 'chasse.css',
        'enigme-style'       => 'enigme.css',
        'gamification-style' => 'gamification.css',
        'cartes-style'       => 'cartes.css',
        'organisateurs'      => 'organisateurs.css',
        'edition'            => 'edition.css',
        'mon compte'         => 'mon-compte.css',
        'commerce-style'     => 'commerce.css',
    ];

    // 🚀 Chargement dynamique des styles avec gestion du cache
    foreach ($styles as $handle => $file) {
        wp_enqueue_style($handle, $theme_dir . $file, [], filemtime(get_stylesheet_directory() . "/assets/css/{$file}"));
    }
});



// ----------------------------------------------------------
// 📂 Chargement des fichiers fonctionnels organisés
// ----------------------------------------------------------

$inc_path = get_stylesheet_directory() . '/inc/';

require_once $inc_path . 'shortcodes-init.php';
require_once $inc_path . 'enigme-functions.php';
require_once $inc_path . 'user-functions.php';
require_once $inc_path . 'chasse-functions.php';
require_once $inc_path . 'gamify-functions.php';
require_once $inc_path . 'statut-functions.php';
require_once $inc_path . 'admin-functions.php';
require_once $inc_path . 'organisateur-functions.php';
//require_once $inc_path . 'stat-functions.php';
require_once $inc_path . 'edition-functions.php';
require_once $inc_path . 'access-functions.php';
require_once $inc_path . 'relations-functions.php';
require_once $inc_path . 'layout-functions.php';
require_once $inc_path . 'utils/liens.php';



/**
 * Injecte automatiquement `acf_form_head()` pour les fiches chasse.
 *
 * Ce hook est nécessaire pour que les panneaux latéraux basés sur `acf_form()`
 * (notamment la description WYSIWYG) fonctionnent correctement en front-end.
 *
 * - Il doit être exécuté avant toute sortie HTML.
 * - Il active la prise en charge des redirections, messages de succès, et champs ACF dynamiques.
 * - ACF recommande son appel dans le `header.php`, mais ici on l'injecte proprement via `wp_head` uniquement pour les chasses.
 *
 * 💡 À terme, cette fonction pourrait être déplacée dans un fichier dédié (ex : acf-hooks.php)
 *
 * @hook wp_head
 */
add_action('wp_head', 'forcer_acf_form_head_chasse', 0);
function forcer_acf_form_head_chasse() {
    if (is_singular('chasse') && function_exists('acf_form_head')) {
        acf_form_head();
    }
}



