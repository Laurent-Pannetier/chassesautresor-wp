<?php
defined( 'ABSPATH' ) || exit;

const HISTORIQUE_PAIEMENTS_ADMIN_PER_PAGE = 20;
const ORGANISATEURS_PENDING_PER_PAGE     = 20;

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
 * 🔹 charger_script_autocomplete_utilisateurs → Enregistrer et charger le script de gestion des points dans l’admin (page "Mon Compte").
 */

 
/**
 * 📌 Recherche d'utilisateurs en AJAX pour l'autocomplétion.
 *
 * - Recherche sur `user_login`, `display_name`, et `user_email`.
 * - Aucun filtre par rôle : tous les utilisateurs sont inclus.
 * - Vérification des permissions (`administrator` requis).
 * - Retour JSON des résultats.
 */
function rechercher_utilisateur_ajax(): void
{
    \ChassesAuTresor\Core\Admin\AdminAjaxHandler::searchUsers();
}

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
            'ajax_url' => admin_url('admin-ajax.php')
        ]);
    }
}
add_action('wp_enqueue_scripts', 'charger_script_autocomplete_utilisateurs');

// ==================================================
// 📦 TAUX DE CONVERSION & PAIEMENT
// ==================================================
/**
 * 🔹 acf_add_local_field_group (conditionnelle) → Ajouter dynamiquement le champ ACF pour le taux de conversion.
 * 🔹 get_taux_conversion_actuel → Récupérer le taux de conversion actuel.
 * 🔹 update_taux_conversion → Mettre à jour le taux de conversion et enregistrer l’historique.
 * 🔹 charger_script_taux_conversion → Charger le script `taux-conversion.js` uniquement pour les administrateurs sur "Mon Compte".
 * 🔹 afficher_tableau_paiements_admin → Afficher les demandes de paiement (en attente ou réglées) pour les administrateurs.
 * 🔹 regler_paiement_admin → Traiter le règlement d’une demande de paiement depuis l’admin.
 * 🔹 $_SERVER['REQUEST_METHOD'] === 'POST' && isset(...) → Mettre à jour le statut des demandes de paiement (admin).
 */

/**
 * 📌 Valeur minimale de points requise pour demander une conversion.
 */
function get_points_conversion_min(): int {
    return (int) apply_filters('points_conversion_min', 500);
}

/**
 * 📌 Ajout du champ d'administration pour le taux de conversion
 */
add_action('acf/init', function () {
    acf_add_local_field_group([
        'key' => 'group_taux_conversion',
        'title' => 'Paramètres de Conversion',
        'fields' => array(
            array(
                'key' => 'field_taux_conversion',
                'label' => 'Taux de conversion actuel',
                'name' => 'taux_conversion',
                'type' => 'number',
                'instructions' => 'Indiquez le taux de conversion des points en euros (ex : 0.05 pour 1 point = 0.05€).',
                'default_value' => 0.05,
                'step' => 0.001,
                'required' => true,
            ),
        ),
        'location' => array(
            array(
                array(
                    'param' => 'options_page',
                    'operator' => '==',
                    'value' => 'options_taux_conversion',
                ),
            ),
        ),
    ]);
});


/**
 * 📌 Récupère le taux de conversion actuel.
 *
 * @return float Le dernier taux enregistré, 85 par défaut.
 */
function get_taux_conversion_actuel() {
    return (new ChassesAuTresor\Core\Points\ConversionSettingsService())->getRate();
}

/**
 * 📌 Met à jour le taux de conversion et enregistre l'historique.
 *
 * @param float $nouveau_taux Nouvelle valeur du taux de conversion.
 */
function update_taux_conversion($nouveau_taux) {
    (new ChassesAuTresor\Core\Points\ConversionSettingsService())->updateRate((float) $nouveau_taux);
}

/**
 * 📌 Charge le script `taux-conversion.js` uniquement pour les administrateurs sur "Mon Compte" et ses sous-pages (y compris les templates redirigés).
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
}
add_action('wp_enqueue_scripts', 'charger_script_paiements_admin');

/**
 * 📌 Affiche les demandes de paiement en attente et réglées pour les administrateurs.
 */
function render_tableau_paiements_admin(array $requests): string
{
    ob_start();
    echo '<table class="widefat fixed">';
    echo '<thead><tr><th>Organisateur</th><th>Montant / Points</th><th>Date demande</th><th>IBAN / BIC</th><th>Statut</th><th>Action</th></tr></thead>';
    echo '<tbody>';

    foreach ($requests as $request) {
        $user = get_userdata((int) $request['user_id']);

        $organisateur_id = get_organisateur_from_user($request['user_id']);
        $iban            = $organisateur_id ? get_field('iban', $organisateur_id) : '';
        $bic             = $organisateur_id ? get_field('bic', $organisateur_id) : '';
        if ($organisateur_id && (empty($iban) || empty($bic))) {
            $iban = get_field('gagnez_de_largent_iban', $organisateur_id);
            $bic  = get_field('gagnez_de_largent_bic', $organisateur_id);
        }
        $iban = $iban ?: 'Non renseigné';

        switch ($request['request_status']) {
            case 'paid':
                $statut = '✅ Réglé';
                break;
            case 'cancelled':
                $statut = '❌ Annulé';
                break;
            case 'refused':
                $statut = '🚫 Refusé';
                break;
            default:
                $statut = '🟡 En attente';
        }

        $action = '-';
        if ($request['request_status'] === 'pending') {
            $action  = '<form class="js-update-request" data-id="' . esc_attr($request['id']) . '">';
            $action .= '<select name="statut">';
            $action .= '<option value="regle" selected>' . esc_html__('Régler', 'chassesautresor-com') . '</option>';
            $action .= '<option value="annule">' . esc_html__('Annuler', 'chassesautresor-com') . '</option>';
            $action .= '<option value="refuse">' . esc_html__('Refuser', 'chassesautresor-com') . '</option>';
            $action .= '</select>';
            $action .= '<button type="submit" class="button">OK</button>';
            $action .= '</form>';
        }

        $points_utilises = esc_html(abs((int) $request['points']));

        echo '<tr>';
        echo '<td>' . esc_html($user->display_name ?? '') . '</td>';
        echo '<td>' . esc_html($request['amount_eur']) . ' €<br><small>(' . $points_utilises . ' points)</small></td>';
        echo '<td>' . esc_html(date('Y-m-d H:i', strtotime($request['request_date']))) . '</td>';
        echo '<td><strong>' . esc_html($iban) . '</strong><br><small>' . esc_html($bic) . '</small></td>';
        echo '<td class="col-status">' . esc_html($statut) . '</td>';
        echo '<td>' . $action . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
    return ob_get_clean();
}

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



function ajax_lister_historique_paiements_admin(): void
{
    \ChassesAuTresor\Core\Admin\AdminAjaxHandler::listPayments();
}

// ----------------------------------------------------------
// 🎛️ Mise à jour du statut des demandes de paiement (Admin)
// ----------------------------------------------------------
/**
 * Handle AJAX status updates for payment requests.
 */
function ajax_update_request_status(): void
{
    \ChassesAuTresor\Core\Admin\AdminAjaxHandler::updateConversionStatus();
}



// ==================================================
// 🛠️ DÉVELOPPEMENT
// ==================================================
/**
 * 🔹 acf_inspect_field_group → Affiche les détails d’un groupe de champs ACF dans le navigateur pour documentation manuelle.
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

// =============================================
// AJAX : récupérer les détails des groupes ACF
// =============================================
function recuperer_details_acf(): void
{
    \ChassesAuTresor\Core\Admin\AdminAjaxHandler::inspectAcf();
}

function cta_reset_stats(): void
{
    \ChassesAuTresor\Core\Admin\AdminAjaxHandler::resetStatistics();
}

function cta_toggle_site_protection(): void
{
    \ChassesAuTresor\Core\Admin\AdminAjaxHandler::toggleSiteProtection();
}

\ChassesAuTresor\Core\Admin\AdminAjaxHandler::configure(
    static fn(array $requests): string => render_tableau_paiements_admin($requests)
);


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
    if (preg_match('#^/mon-compte(?:/|$|\\?)#', $_SERVER['REQUEST_URI'] ?? '')) {
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
        ]);
    }
}
add_action('wp_enqueue_scripts', 'charger_script_reset_stats_card');

// ==================================================
// 📦 TABLEAU ORGANISATEURS EN CRÉATION
// ==================================================
/**
 * Récupère la liste des organisateurs en cours de création.
 *
 * @return array[] Tableau des données trié du plus récent au plus ancien.
 */
function recuperer_organisateurs_en_creation() {
    if (!current_user_can('administrator')) {
        return [];
    }

    $users   = get_users(['role' => ROLE_ORGANISATEUR_CREATION]);
    $entries = [];

    foreach ($users as $user) {
        $organisateur_id = get_organisateur_from_user($user->ID);
        if (!$organisateur_id) {
            continue;
        }

        $date_creation = get_post_field('post_date', $organisateur_id);
        $chasses       = get_chasses_en_creation($organisateur_id);
        if (empty($chasses)) {
            continue;
        }

        $chasse_id  = (int) $chasses[0];
        $nb_enigmes = count(recuperer_enigmes_associees($chasse_id));

        $entries[] = [
            'date_creation'      => $date_creation,
            'organisateur_titre' => get_the_title($organisateur_id),
            'chasse_id'          => $chasse_id,
            'chasse_titre'       => get_the_title($chasse_id),
            'nb_enigmes'         => $nb_enigmes,
        ];
    }

    usort($entries, function ($a, $b) {
        return strtotime($b['date_creation']) <=> strtotime($a['date_creation']);
    });

    return $entries;
}

/**
 * Affiche les tableaux des organisateurs en création.
 */
function afficher_tableau_organisateurs_en_creation() {
    $liste = recuperer_organisateurs_en_creation();
    if (empty($liste)) {
        echo '<p>Aucun organisateur en création.</p>';
        return;
    }

    echo '<table class="stats-table"><tbody>';

    foreach ($liste as $entry) {
        echo '<tr>';
        echo '<td>' . esc_html($entry['organisateur_titre']) . '</td>';
        echo '<td><a href="' . esc_url(get_permalink($entry['chasse_id'])) . '">' . esc_html($entry['chasse_titre']) . '</a></td>';
        echo '<td>' . intval($entry['nb_enigmes']) . ' énigmes</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';

    $oldest = end($liste);
    echo '<table class="stats-table">';
    echo '<caption>+ Ancienne création</caption><tbody>';
    echo '<tr>';
    echo '<td>' . esc_html($oldest['organisateur_titre']) . '</td>';
    echo '<td><a href="' . esc_url(get_permalink($oldest['chasse_id'])) . '">' . esc_html($oldest['chasse_titre']) . '</a></td>';
    echo '<td>' . intval($oldest['nb_enigmes']) . ' énigmes</td>';
    echo '</tr></tbody></table>';
}

/**
 * Récupère les organisateurs avec statut pending.
 *
 * @return array[] Liste des données des organisateurs en attente.
 */
function recuperer_organisateurs_pending()
{
    if (!current_user_can('administrator')) {
        return [];
    }

    $query = new WP_Query([
        'post_type'      => 'organisateur',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'fields'         => 'ids',
    ]);

    $resultats = [];

    foreach ($query->posts as $organisateur_id) {
        $titre     = get_the_title($organisateur_id);
        $permalink = get_permalink($organisateur_id);

        $users     = (array) get_field('utilisateurs_associes', $organisateur_id);
        $user_id   = $users ? intval(reset($users)) : null;
        $user_name = '';
        $user_link = '';
        if ($user_id) {
            $user = get_userdata($user_id);
            if ($user) {
                $user_name = $user->display_name;
                $user_link = get_edit_user_link($user_id);
            }
        }

        verifier_ou_mettre_a_jour_cache_complet($organisateur_id);

        $chasses = new WP_Query([
            'post_type'      => 'chasse',
            'posts_per_page' => -1,
            'post_status'    => ['publish', 'pending', 'draft'],
            'meta_query'     => [
                [
                    'key'     => 'chasse_cache_organisateur',
                    'value'   => '"' . strval($organisateur_id) . '"',
                    'compare' => 'LIKE',
                ],
            ],
            'fields'         => 'ids',
        ]);

        if ($chasses->have_posts()) {
            foreach ($chasses->posts as $chasse_id) {
                verifier_ou_mettre_a_jour_cache_complet($chasse_id);

                $date_creation = get_post_field('post_date', $chasse_id);
                $chasse_titre  = get_the_title($chasse_id);
                $chasse_link   = get_permalink($chasse_id);
                $enigmes       = recuperer_enigmes_associees($chasse_id);
                $nb_enigmes    = count($enigmes);
                $statut        = get_field('chasse_cache_statut_validation', $chasse_id);

                $pending_validation = ($statut === 'en_attente');
                $pending_attempts   = false;
                foreach ($enigmes as $enigme_id) {
                    $mode = enigme_normaliser_mode_validation(get_field('enigme_mode_validation', $enigme_id));
                    if ($mode === 'manuelle' && compter_tentatives_en_attente($enigme_id) > 0) {
                        $pending_attempts = true;
                        break;
                    }
                }

                $resultats[] = [
                    'organisateur_id'        => $organisateur_id,
                    'organisateur_titre'     => $titre,
                    'organisateur_permalink' => $permalink,
                    'user_id'                => $user_id,
                    'user_name'              => $user_name,
                    'user_link'              => $user_link,
                    'chasse_id'              => $chasse_id,
                    'chasse_titre'           => $chasse_titre,
                    'chasse_permalink'       => $chasse_link,
                    'nb_enigmes'             => $nb_enigmes,
                    'statut'                 => $statut,
                    'validation'             => $statut,
                    'pending_validation'     => $pending_validation,
                    'pending_attempts'       => $pending_attempts,
                    'date_creation'          => $date_creation,
                ];
            }
        } else {
            $date_creation = get_post_field('post_date', $organisateur_id);
            $resultats[]   = [
                'organisateur_id'        => $organisateur_id,
                'organisateur_titre'     => $titre,
                'organisateur_permalink' => $permalink,
                'user_id'                => $user_id,
                'user_name'              => $user_name,
                'user_link'              => $user_link,
                'chasse_id'              => null,
                'chasse_titre'           => '',
                'chasse_permalink'       => '',
                'nb_enigmes'             => 0,
                'statut'                 => '',
                'validation'             => '',
                'pending_validation'     => false,
                'pending_attempts'       => false,
                'date_creation'          => $date_creation,
            ];
        }
    }

    usort($resultats, function ($a, $b) {
        $timeA = strtotime($a['date_creation']);
        $timeB = strtotime($b['date_creation']);
        return $timeA === $timeB ? 0 : ($timeA < $timeB ? 1 : -1);
    });

    return $resultats;
}

/**
 * Affiche la liste des organisateurs et leurs chasses dans un tableau.
 *
 * @param array|null $liste Données pré-calculées.
 * @param int        $page  Page courante.
 * @param int        $per_page Nombre d'organisateurs par page.
 */
function afficher_tableau_organisateurs_pending(?array $liste = null, int $page = 1, int $per_page = ORGANISATEURS_PENDING_PER_PAGE): void
{
    if (null === $liste) {
        $liste = recuperer_organisateurs_pending();
    }
    if (empty($liste)) {
        echo '<p>' . esc_html__('Aucun organisateur.', 'chassesautresor-com') . '</p>';
        return;
    }

    $grouped = [];
    foreach ($liste as $entry) {
        $oid = $entry['organisateur_id'];
        if (!isset($grouped[$oid])) {
            $grouped[$oid] = [
                'organisateur_titre'     => $entry['organisateur_titre'],
                'organisateur_permalink' => $entry['organisateur_permalink'],
                'user_id'                => $entry['user_id'],
                'user_name'              => $entry['user_name'],
                'user_link'              => $entry['user_link'],
                'rows'                   => [],
            ];
        }
        $grouped[$oid]['rows'][] = $entry;
    }

    $total  = count($grouped);
    $pages  = max(1, (int) ceil($total / $per_page));
    $page   = max(1, min($page, $pages));
    $offset = ($page - 1) * $per_page;
    $grouped = array_slice($grouped, $offset, $per_page, true);

    echo '<div class="stats-table-wrapper" data-per-page="' . intval($per_page) . '">';
    echo '<table class="stats-table table-organisateurs">';
    echo '<thead><tr>';
    echo '<th scope="col">' . esc_html__('Organisateur', 'chassesautresor-com') . '</th>';
    echo '<th scope="col">' . esc_html__('Chasse', 'chassesautresor-com') . '</th>';
    echo '<th scope="col" data-format="etiquette"><span class="etiquette">' . esc_html__('Nb énigmes', 'chassesautresor-com') . '</span></th>';
    echo '<th scope="col">' . esc_html__('État', 'chassesautresor-com') . '</th>';
    echo '<th scope="col">' . esc_html__('Utilisateur', 'chassesautresor-com') . '</th>';
    echo '<th scope="col" data-col="date">' . esc_html__('Créé le', 'chassesautresor-com') . ' <span class="tri-date">&#9650;&#9660;</span></th>';
    echo '</tr></thead><tbody>';

    foreach ($grouped as $org) {
        $rows    = $org['rows'];
        $rowspan = count($rows);
        $first   = true;
        foreach ($rows as $row) {
            echo '<tr data-etat="' . esc_attr($row['statut']) . '" data-date="' . esc_attr($row['date_creation']) . '">';
            if ($first) {
                echo '<td rowspan="' . intval($rowspan) . '"><a href="' . esc_url($org['organisateur_permalink']) . '" target="_blank">' . esc_html($org['organisateur_titre']) . '</a></td>';
            }

            if ($row['chasse_id']) {
                $statut      = $row['statut'];
                $badge_class = 'statut-revision';

                switch ($statut) {
                    case 'valide':
                        $badge_class  = 'statut-en_cours';
                        $statut_label = __('valide', 'chassesautresor-com');
                        break;
                    case 'correction':
                        $statut_label = __('correction', 'chassesautresor-com');
                        break;
                    case 'en_attente':
                        $statut_label = __('en attente', 'chassesautresor-com');
                        break;
                    case 'creation':
                        $statut_label = __('création', 'chassesautresor-com');
                        break;
                    case 'banni':
                        $badge_class  = 'statut-termine';
                        $statut_label = __('banni', 'chassesautresor-com');
                        break;
                    default:
                        $statut_label = $statut;
                        break;
                }

                echo '<td class="col-chasse"><a href="' . esc_url($row['chasse_permalink']) . '">'
                    . esc_html($row['chasse_titre'])
                    . '</a></td>';
                echo '<td class="col-enigmes"><span class="etiquette">' . intval($row['nb_enigmes']) . '</span></td>';

                $warning   = $row['pending_validation'] || $row['pending_attempts'];
                $tooltip   = '';
                if ($warning) {
                    if ($row['pending_validation'] && $row['pending_attempts']) {
                        $tooltip = __('Demande de validation et tentatives manuelles en attente', 'chassesautresor-com');
                    } elseif ($row['pending_validation']) {
                        $tooltip = __('Demande de validation en attente', 'chassesautresor-com');
                    } else {
                        $tooltip = __('Tentatives manuelles en attente de réponse', 'chassesautresor-com');
                    }
                }

                echo '<td data-col="etat"><span class="badge-statut ' . esc_attr($badge_class) . '">' . esc_html($statut_label) . '</span>';
                if ($warning) {
                    echo '<span class="required" aria-hidden="true" title="' . esc_attr($tooltip) . '">*</span>';
                }
                echo '</td>';
            } else {
                echo '<td class="col-chasse">-</td><td class="col-enigmes"><span class="etiquette">-</span></td><td data-col="etat"></td>';
            }

            if ($first) {
                if ($org['user_id']) {
                    echo '<td rowspan="' . intval($rowspan) . '"><a href="' . esc_url($org['user_link']) . '" target="_blank">' . esc_html($org['user_name']) . '</a></td>';
                } else {
                    echo '<td rowspan="' . intval($rowspan) . '">-</td>';
                }
            }

            echo '<td>' . esc_html(date_i18n('d/m/y', strtotime($row['date_creation']))) . '</td>';
            echo '</tr>';
            $first = false;
        }
    }

    echo '</tbody></table>';
    echo cta_render_pager($page, $pages, 'organisateurs-pager');
    echo '</div>';
}
