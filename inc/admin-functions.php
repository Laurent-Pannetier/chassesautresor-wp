<?php
defined( 'ABSPATH' ) || exit;

const HISTORIQUE_PAIEMENTS_ADMIN_PER_PAGE = 20;

// ==================================================
// 📚 SOMMAIRE DU FICHIER
// ==================================================
//
// 1. 📦 FONCTIONNALITÉS ADMINISTRATEUR
// 2. 📦 TAUX DE CONVERSION & PAIEMENT
// 3. 📦 RÉINITIALISATION
// 4. 🛠️ DÉVELOPPEMENT
//


// ==================================================
// 📦 FONCTIONNALITÉS ADMINISTRATEUR
// ==================================================
/**
 * 🔹 rechercher_utilisateur_ajax → Rechercher des utilisateurs en AJAX pour l’autocomplétion.
 * 🔹 charger_script_autocomplete_utilisateurs → Charger la gestion des points dans "Mon Compte".
 */

 
/**
 * Enregistre et charge le script de gestion des points pour les administrateurs sur la page "Mon Compte".
 *
 * Cette fonction :
 * - Charge le script `gestion-points.js` uniquement sur la page "Mon Compte".
 * - S'assure que l'utilisateur est un administrateur avant d'ajouter le script.
 * - Utilise `wp_localize_script()` pour rendre l'URL d'AJAX accessible au script JS.
 *
 * @return void
 */
function charger_script_autocomplete_utilisateurs() {
    // Vérifier si l'on est sur la page Mon Compte (y compris ses sous-pages) et que l'utilisateur est administrateur
    if (function_exists('is_account_page') && is_account_page() && current_user_can('administrator')) {
        wp_enqueue_script(
            'autocomplete-utilisateurs', // Nouveau nom du script
            get_stylesheet_directory_uri() . '/assets/js/autocomplete-utilisateurs.js',
            [], // Pas de dépendances spécifiques
            filemtime(get_stylesheet_directory() . '/assets/js/autocomplete-utilisateurs.js'),
            true // Chargement en footer
        );

        // Rendre l'URL AJAX disponible pour le script
        wp_localize_script('autocomplete-utilisateurs', 'ajax_object', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cta_admin_tools'),
        ]);
    }
}
add_action('wp_enqueue_scripts', 'charger_script_autocomplete_utilisateurs');

// ==================================================
// 📦 TAUX DE CONVERSION & PAIEMENT
// ==================================================
/**
 * 🔹 acf_add_local_field_group (conditionnelle) → Ajouter dynamiquement le champ ACF pour le taux de conversion.
 * 🔹 charger_script_taux_conversion → Charger `taux-conversion.js` pour les administrateurs.
 * 🔹 afficher_tableau_paiements_admin → Afficher les demandes de paiement pour les administrateurs.
 * 🔹 regler_paiement_admin → Traiter le règlement d’une demande de paiement depuis l’admin.
 * 🔹 Les requêtes POST mettent à jour le statut des demandes de paiement.
 */

/**
 * 📌 Charge `taux-conversion.js` pour les administrateurs sur "Mon Compte" et ses sous-pages.
 *
 * - Vérifie si l'URL commence par "/mon-compte/" pour inclure toutes les pages et templates associés.
 * - Vérifie si l'utilisateur a le rôle d'administrateur (`current_user_can('administrator')`).
 * - Si les deux conditions sont remplies, charge le script `taux-conversion.js`.
 */
function charger_script_taux_conversion() {
    if (is_page('mon-compte') && current_user_can('administrator')) {
        wp_enqueue_script(
            'taux-conversion',
            get_stylesheet_directory_uri() . '/assets/js/taux-conversion.js',
            [],
            filemtime(get_stylesheet_directory() . '/assets/js/taux-conversion.js'),
            true
        );
    }
}
add_action('wp_enqueue_scripts', 'charger_script_taux_conversion');

/**
 * Load admin payment management script on account pages.
 */
function charger_script_paiements_admin(): void
{
    if (!current_user_can('administrator') || !is_account_page()) {
        return;
    }

    $script_path = get_stylesheet_directory() . '/assets/js/paiements-admin.js';
    wp_enqueue_script(
        'paiements-admin',
        get_stylesheet_directory_uri() . '/assets/js/paiements-admin.js',
        [],
        filemtime($script_path),
        true
    );

    $history_path = get_stylesheet_directory() . '/assets/js/paiements-historique.js';
    wp_enqueue_script(
        'paiements-historique',
        get_stylesheet_directory_uri() . '/assets/js/paiements-historique.js',
        [],
        filemtime($history_path),
        true
    );

    $adminNonce = wp_create_nonce('cta_admin_tools');
    wp_localize_script('paiements-admin', 'ctaAdminTools', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => $adminNonce,
    ]);
    wp_localize_script('paiements-historique', 'ctaAdminTools', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => $adminNonce,
    ]);
}
add_action('wp_enqueue_scripts', 'charger_script_paiements_admin');

function afficher_tableau_paiements_admin(): void
{
    if (!current_user_can('administrator')) {
        return;
    }

    $requests = cat_get_conversion_service()->getRequests();

    if (empty($requests)) {
        echo '<p>Aucune demande de paiement en attente.</p>';
        return;
    }

    echo render_tableau_paiements_admin($requests);
}



// ==================================================
// 🛠️ DÉVELOPPEMENT
// ==================================================
/**
 * 🔹 acf_inspect_field_group → Affiche les détails d’un groupe ACF pour documentation manuelle.
 */


/**
 * Affiche de manière lisible les détails d’un groupe de champs ACF dans le navigateur.
 *
 * @param int|string $group_id_or_key L'ID ou la key du groupe de champs ACF.
 */
function acf_inspect_field_group($group_id_or_key) {
    if (!function_exists('acf_get_fields')) {
        echo '<pre>ACF non disponible.</pre>';
        return;
    }

    $group = null;
    $group_key = '';

    // Cas : ID numérique
    if (is_numeric($group_id_or_key)) {
        $group = get_post((int)$group_id_or_key);
        if (!$group || $group->post_type !== 'acf-field-group') {
            echo "<pre>❌ Aucun groupe ACF trouvé pour l’ID {$group_id_or_key}.</pre>";
            return;
        }
        $group_key = get_post_meta($group->ID, '_acf_field_group_key', true);
        if (empty($group_key)) {
            echo "<pre>❌ La clé du groupe ACF est introuvable pour l’ID {$group->ID}.</pre>";
            return;
        }
    }

    // Cas : clé fournie directement
    if (!is_numeric($group_id_or_key)) {
        $group_key = $group_id_or_key;
        $group = acf_get_field_group($group_key);
        if (!$group) {
            echo "<pre>❌ Aucun groupe ACF trouvé pour la key {$group_key}.</pre>";
            return;
        }
    }

    // Récupération des champs
    $fields = acf_get_fields($group_key);
    if (empty($fields)) {
        echo "<pre>⚠️ Aucun champ trouvé pour le groupe « {$group->title} » (Key : {$group_key})</pre>";
        return;
    }

    // Affichage
    echo '<pre>';
    $title = is_array($group) ? $group['title'] : $group->post_title;
    $id    = is_array($group) ? $group['ID']    : $group->ID;
    
    echo "🔹 Groupe : {$title}\n";
    echo "🆔 ID : {$id}\n";

    echo "🔑 Key : {$group_key}\n";
    echo "📦 Champs trouvés : " . count($fields) . "\n\n";
    afficher_champs_acf_recursifs($fields);
    echo '</pre>';
}


/**
 * Fonction récursive pour afficher les champs ACF avec indentation.
 *
 * @param array $fields Tableau de champs ACF.
 * @param int $indent Niveau d'indentation.
 */
function afficher_champs_acf_recursifs($fields, $indent = 0) {
    $prefix = str_repeat('  ', $indent);
    foreach ($fields as $field) {
        echo $prefix . "— " . $field['name'] . " —\n";
        echo $prefix . "Type : " . $field['type'] . "\n";
        echo $prefix . "Label : " . $field['label'] . "\n";
        echo $prefix . "Instructions : " . (!empty($field['instructions']) ? $field['instructions'] : '(vide)') . "\n";
        echo $prefix . "Requis : " . ($field['required'] ? 'oui' : 'non') . "\n";

        // Options spécifiques selon le type
        if (!empty($field['choices'])) {
            echo $prefix . "Choices :\n";
            foreach ($field['choices'] as $key => $label) {
                echo $prefix . "  - {$key} : {$label}\n";
            }
        }

        if (in_array($field['type'], ['repeater', 'group', 'flexible_content']) && !empty($field['sub_fields'])) {
            echo $prefix . "Contenu imbriqué :\n";
            afficher_champs_acf_recursifs($field['sub_fields'], $indent + 1);
        }

        echo $prefix . str_repeat('-', 40) . "\n";
    }
}
/*
| 💡 Ce bloc est désactivé par défaut. Il sert uniquement à afficher
| temporairement le détail d’un groupe de champs ACF dans l’interface admin.
|
| 📋 Pour l’utiliser :
|   1. Décommente les lignes ci-dessous
|   2. Remplace l’ID (ex. : 9) par celui du groupe souhaité
|   3. Recharge une page de l’admin (ex : Tableau de bord)
|   4. Copie le résultat affiché et re-commente le bloc après usage
|
| ❌ À ne jamais laisser actif en production.
*/

/*

📋 Liste des groupes ACF disponibles :
========================================

🆔 ID     : 27
🔑 Key    : group_67b58c51b9a49
🏷️  Titre : paramètre de la chasse
----------------------------------------
🆔 ID     : 9
🔑 Key    : group_67b58134d7647
🏷️  Titre : Paramètres de l’énigme
----------------------------------------

🆔 ID     : 657
🔑 Key    : group_67c7dbfea4a39
🏷️  Titre : Paramètres organisateur
----------------------------------------
🆔 ID     : 584
🔑 Key    : group_67c28f6aac4fe
🏷️  Titre : Statistiques des chasses
----------------------------------------
🆔 ID     : 577
🔑 Key    : group_67c2368625fc2
🏷️  Titre : Statistiques des énigmes
----------------------------------------
🆔 ID     : 931
🔑 Key    : group_67cd4a8058510
🏷️  Titre : infos éditions chasse
----------------------------------------

add_action('admin_notices', function() {
    if (current_user_can('administrator')) {
        acf_inspect_field_group('group_67c28f6aac4fe'); // Remplacer  Key
    }
});

*/

/**
 * Charge le script de la carte Développement sur les pages Mon Compte.
 */
function charger_script_developpement_card() {
    if (preg_match('#^/mon-compte(?:/|$|\\?)#', $_SERVER['REQUEST_URI'] ?? '')) {
        wp_enqueue_script(
            'developpement-card',
            get_stylesheet_directory_uri() . '/assets/js/developpement-card.js',
            [],
            filemtime(get_stylesheet_directory() . '/assets/js/developpement-card.js'),
            true
        );
        wp_localize_script('developpement-card', 'ajax_object', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cta_admin_tools'),
        ]);
    }
}
add_action('wp_enqueue_scripts', 'charger_script_developpement_card');

function charger_script_site_protection_card() {
    if (preg_match('#^/mon-compte(?:/|$|\\?)#', $_SERVER['REQUEST_URI'] ?? '')) {
        wp_enqueue_script(
            'site-protection-card',
            get_stylesheet_directory_uri() . '/assets/js/site-protection-card.js',
            [],
            filemtime(get_stylesheet_directory() . '/assets/js/site-protection-card.js'),
            true
        );
        wp_localize_script(
            'site-protection-card',
            'siteProtectionCard',
            [
                'ajax_url'    => admin_url('admin-ajax.php'),
                'nonce'       => wp_create_nonce('cta_site_protection'),
                'activated'   => __('Activé', 'chassesautresor-com'),
                'deactivated' => __('Désactivé', 'chassesautresor-com'),
            ]
        );
    }
}
add_action('wp_enqueue_scripts', 'charger_script_site_protection_card');

function charger_script_reset_stats_card() {
    $is_account_area = preg_match('#^/mon-compte(?:/|$|\\?)#', $_SERVER['REQUEST_URI'] ?? '');
    $is_demo_user = is_user_logged_in()
        && function_exists('cat_is_demo_mode')
        && cat_is_demo_mode();

    if ($is_account_area || $is_demo_user) {
        wp_enqueue_script(
            'reset-stats-card',
            get_stylesheet_directory_uri() . '/assets/js/reset-stats-card.js',
            [],
            filemtime(get_stylesheet_directory() . '/assets/js/reset-stats-card.js'),
            true
        );
        wp_localize_script('reset-stats-card', 'resetStatsCard', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('cta_reset_stats'),
            'confirm'  => __('Confirmez-vous la réinitialisation des statistiques ?', 'chassesautresor-com'),
            'success'  => __('Statistiques effacées.', 'chassesautresor-com'),
            'error'    => __('La réinitialisation a échoué.', 'chassesautresor-com'),
            'ajaxError' => __('Impossible de contacter le serveur.', 'chassesautresor-com'),
        ]);
    }
}
add_action('wp_enqueue_scripts', 'charger_script_reset_stats_card');

/**
 * Floating demo reset pastille removed: reset lives on Mon compte Accueil.
 */
function cta_render_demo_reset_stats_button(): void
{
    // Intentionally empty — kept for backward-compatible hook removal sites.
}
