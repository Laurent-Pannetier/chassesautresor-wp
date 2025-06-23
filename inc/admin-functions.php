<?php
defined( 'ABSPATH' ) || exit;

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
 * 🔹 traiter_gestion_points → Gérer l’ajout ou le retrait de points à un utilisateur.
 * 🔹 charger_script_autocomplete_utilisateurs → Enregistrer et charger le script de gestion des points dans l’admin (page "Mon Compte").
 * 🔹 gerer_organisateur → Gérer l’acceptation ou le refus d’un organisateur (demande modération).
 */

 
/**
 * 📌 Recherche d'utilisateurs en AJAX pour l'autocomplétion.
 *
 * - Recherche sur `user_login`, `display_name`, et `user_email`.
 * - Aucun filtre par rôle : tous les utilisateurs sont inclus.
 * - Vérification des permissions (`administrator` requis).
 * - Retour JSON des résultats.
 */
function rechercher_utilisateur_ajax() {
    // ✅ Vérifier que la requête est bien envoyée par un administrateur
    if (!current_user_can('administrator')) {
        wp_send_json_error(['message' => '⛔ Accès refusé.']);
    }

    // ✅ Vérifier la présence du paramètre de recherche
    $search = isset($_GET['term']) ? sanitize_text_field($_GET['term']) : '';

    if (empty($search)) {
        wp_send_json_error(['message' => '❌ Requête vide.']);
    }

    // ✅ Requête pour récupérer tous les utilisateurs sans restriction de rôle
    $users = get_users([
        'search'         => '*' . esc_attr($search) . '*',
        'search_columns' => ['user_login', 'display_name', 'user_email']
    ]);

    // ✅ Vérifier que des utilisateurs sont trouvés
    if (empty($users)) {
        wp_send_json_error(['message' => '❌ Aucun utilisateur trouvé.']);
    }

    // ✅ Formatage des résultats en JSON
    $results = [];
    foreach ($users as $user) {
        $results[] = [
            'id'   => $user->ID,
            'text' => esc_html($user->display_name) . ' (' . esc_html($user->user_login) . ')'
        ];
    }

    wp_send_json_success($results);
}
add_action('wp_ajax_rechercher_utilisateur', 'rechercher_utilisateur_ajax');

/**
 * 📌 Gère l'ajout ou le retrait de points à un utilisateur.
 */
function traiter_gestion_points() {
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['modifier_points'])) {
        return;
    }
    
    // ✅ Vérification du nonce pour la sécurité
    if (!isset($_POST['gestion_points_nonce']) || !wp_verify_nonce($_POST['gestion_points_nonce'], 'gestion_points_action')) {
        wp_die('❌ Vérification du nonce échouée.');
    }

    // ✅ Vérification que l'utilisateur est administrateur
    if (!current_user_can('administrator')) {
        wp_die('❌ Accès refusé.');
    }

    // ✅ Vérification et assainissement des données
    $utilisateur = sanitize_text_field($_POST['utilisateur']);
    $type_modification = sanitize_text_field($_POST['type_modification']);
    $nombre_points = intval($_POST['nombre_points']);

    if (!$utilisateur || !$type_modification || $nombre_points <= 0) {
        wp_die('❌ Données invalides.');
    }

    // Récupérer l'ID de l'utilisateur
    $user = get_user_by('ID', intval($utilisateur));
    if (!$user) {
        wp_die('❌ Utilisateur introuvable.');
    }

    $user_id = $user->ID;
    $solde_actuel = get_user_points($user_id) ?: 0;

    // Modification des points selon l’action choisie
    if ($type_modification === "ajouter") {
        $nouveau_solde = $solde_actuel + $nombre_points;
    } elseif ($type_modification === "retirer") {
        if ($nombre_points > $solde_actuel) {
            wp_die('❌ Impossible de retirer plus de points que l’utilisateur en possède.');
        }
        $nouveau_solde = $solde_actuel - $nombre_points;
    } else {
        wp_die('❌ Action invalide.');
    }

    // Mettre à jour les points de l'utilisateur
    update_user_points($user_id, $nouveau_solde);

    error_log("✅ Points modifiés : $nombre_points $type_modification pour l'utilisateur $utilisateur");

    // ✅ Redirection après soumission
    wp_redirect(add_query_arg('points_modifies', '1', wp_get_referer()));
    exit;
}
add_action('init', 'traiter_gestion_points');


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
    // Vérifier si l'on est sur la page "Mon Compte" et que l'utilisateur est administrateur
    if (is_page('mon-compte') && current_user_can('administrator')) {
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

// Fonction principale pour gérer l'acceptation ou le refus
function gerer_organisateur() {
    
    // Vérification des permissions et nonce
    check_ajax_referer('gerer_organisateur_nonce', 'security');
    

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array("message" => "Permission refusée."));
        exit;
    }

    $post_id = intval($_POST['post_id']);
    $type = sanitize_text_field($_POST['type']);

    if (!$post_id || empty($type)) {
        wp_send_json_error(array("message" => "Requête invalide."));
        exit;
    }

    if ($type === "accepter") {
        // Mise à jour du statut de l'organisateur
        wp_update_post(array(
            'ID'          => $post_id,
            'post_status' => 'publish'
        ));

        // Attribution du rôle "Organisateur" à l'auteur de la demande
        $user_id = get_post_field('post_author', $post_id);
        if ($user_id) {
            $user = new WP_User($user_id);
            $user->set_role('organisateur'); // Assurez-vous que ce rôle existe
        }

        // Envoi d'un email de confirmation
        $email = get_post_meta($post_id, 'email_organisateur', true);
        if (!empty($email)) {
            wp_mail($email, "Validation de votre inscription", "Votre demande d'organisateur a été validée !");
        }

        wp_send_json_success(array("message" => "Organisateur accepté."));
    }

    if ($type === "refuser") {
        // Suppression de la demande
        wp_delete_post($post_id, true);

        // Envoi d'un email de refus
        $email = get_post_meta($post_id, 'email_organisateur', true);
        if (!empty($email)) {
            wp_mail($email, "Refus de votre demande", "Votre demande d'organisateur a été refusée.");
        }

        wp_send_json_success(array("message" => "Demande refusée et supprimée."));
    }

    wp_send_json_error(array("message" => "Action inconnue."));
}



// ==================================================
// 📦 TAUX DE CONVERSION & PAIEMENT
// ==================================================
/**
 * 🔹 acf_add_local_field_group (conditionnelle) → Ajouter dynamiquement le champ ACF pour le taux de conversion.
 * 🔹 init_taux_conversion → Initialiser le taux de conversion par défaut s’il n’existe pas.
 * 🔹 get_taux_conversion_actuel → Récupérer le taux de conversion actuel.
 * 🔹 update_taux_conversion → Mettre à jour le taux de conversion et enregistrer l’historique.
 * 🔹 charger_script_taux_conversion → Charger le script `taux-conversion.js` uniquement pour les administrateurs sur "Mon Compte".
 * 🔹 traiter_mise_a_jour_taux_conversion → Mettre à jour le taux de conversion depuis l’administration.
 * 🔹 afficher_tableau_paiements_admin → Afficher les demandes de paiement (en attente ou réglées) pour les administrateurs.
 * 🔹 regler_paiement_admin → Traiter le règlement d’une demande de paiement depuis l’admin.
 * 🔹 traiter_demande_paiement → Traiter la demande de conversion de points en euros pour un organisateur.
 * 🔹 $_SERVER['REQUEST_METHOD'] === 'POST' && isset(...) → Mettre à jour le statut des demandes de paiement (admin).
 */

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
 * 📌 Initialise le taux de conversion par défaut s'il n'existe pas.
 */
function init_taux_conversion() {
    if (get_option('taux_conversion') === false) {
        update_option('taux_conversion', 85);
    }
}
add_action('init', 'init_taux_conversion');

/**
 * 📌 Récupère le taux de conversion actuel.
 *
 * @return float Le dernier taux enregistré, 85 par défaut.
 */
function get_taux_conversion_actuel() {
    return floatval(get_option('taux_conversion', 85));
}

/**
 * 📌 Met à jour le taux de conversion et enregistre l'historique.
 *
 * @param float $nouveau_taux Nouvelle valeur du taux de conversion.
 */
function update_taux_conversion($nouveau_taux) {
    $historique = get_option('historique_taux_conversion', []);

    // Ajouter la nouvelle entrée dans l'historique
    $historique[] = [
        'date_taux_conversion' => current_time('mysql'),
        'valeur_taux_conversion' => floatval($nouveau_taux)
    ];

    // Limiter l'historique à 10 entrées pour éviter une surcharge inutile
    if (count($historique) > 10) {
        array_shift($historique);
    }

    update_option('taux_conversion', floatval($nouveau_taux));
    update_option('historique_taux_conversion', $historique);
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
 * 📌 Met à jour le taux de conversion depuis l'administration.
 */
function traiter_mise_a_jour_taux_conversion() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enregistrer_taux'])) {
        
        // Vérifier le nonce pour la sécurité
        if (!isset($_POST['modifier_taux_conversion_nonce']) || !wp_verify_nonce($_POST['modifier_taux_conversion_nonce'], 'modifier_taux_conversion_action')) {
            wp_die('❌ Vérification du nonce échouée.');
        }

        // Vérifier que l'utilisateur est bien un administrateur
        if (!current_user_can('administrator')) {
            wp_die('❌ Accès refusé.');
        }

        // Vérifier et assainir la valeur entrée
        $nouveau_taux = isset($_POST['nouveau_taux']) ? floatval($_POST['nouveau_taux']) : null;
        if ($nouveau_taux === null || $nouveau_taux <= 0) {
            wp_die('❌ Veuillez entrer un taux de conversion valide.');
        }

        // Mettre à jour le taux dans les options WordPress
        update_option('taux_conversion', $nouveau_taux);

        // Ajouter l'ancien taux à l'historique
        $historique = get_option('historique_taux_conversion', []);
        $historique[] = [
            'date_taux_conversion' => current_time('mysql'),
            'valeur_taux_conversion' => $nouveau_taux
        ];
        
        // Limiter l'historique à 10 entrées pour éviter une surcharge
        if (count($historique) > 10) {
            array_shift($historique);
        }

        update_option('historique_taux_conversion', $historique);

        // Rediriger avec un message de confirmation
        wp_redirect(add_query_arg('taux_mis_a_jour', '1', wp_get_referer()));
        exit;
    }
}

/**
 * 📌 Affiche les demandes de paiement en attente et réglées pour les administrateurs.
 */
function afficher_tableau_paiements_admin() {
    if (!current_user_can('administrator')) {
        return;
    }

    // Récupérer tous les utilisateurs ayant une demande de paiement
    $users = get_users(['meta_key' => 'demande_paiement', 'meta_compare' => 'EXISTS']);

    if (empty($users)) {
        echo '<p>Aucune demande de paiement en attente.</p>';
        return;
    }

    echo '<table class="widefat fixed">';
    echo '<thead><tr><th>Organisateur</th><th>Montant / Points</th><th>Date demande</th><th>IBAN / BIC</th><th>Statut</th><th>Action</th></tr></thead>';
    echo '<tbody>';

    foreach ($users as $user) {
        $paiements = get_user_meta($user->ID, 'demande_paiement', true);
        $paiements = maybe_unserialize($paiements);

        // Récupérer l'ID du CPT "organisateur" associé à l'utilisateur
        $organisateur_id = get_organisateur_from_user($user->ID);
        $iban = $organisateur_id ? get_field('gagnez_de_largent_iban', $organisateur_id) : 'Non renseigné';
        $bic = $organisateur_id ? get_field('gagnez_de_largent_bic', $organisateur_id) : '';

        foreach ($paiements as $index => $paiement) {
            $statut = $paiement['statut'] === 'reglé' ? '✅ Réglé' : '🟡 En attente';
            $action = $paiement['statut'] === 'en attente' 
                ? '<a href="' . add_query_arg(['regler_paiement' => $index, 'user_id' => $user->ID]) . '" class="button">✅ Régler</a>' 
                : '-';

            $points_utilises = isset($paiement['paiement_points_utilises']) ? esc_html($paiement['paiement_points_utilises']) : 'N/A';

            echo '<tr>';
            echo '<td>' . esc_html($user->display_name) . '</td>';
            echo '<td>' . esc_html($paiement['paiement_demande_montant']) . ' €<br><small>(' . $points_utilises . ' points)</small></td>';
            echo '<td>' . esc_html(date('Y-m-d H:i', strtotime($paiement['paiement_date_demande']))) . '</td>';
            echo '<td><strong>' . esc_html($iban) . '</strong><br><small>' . esc_html($bic) . '</small></td>';
            echo '<td>' . esc_html($statut) . '</td>';
            echo '<td>' . $action . '</td>';
            echo '</tr>';
        }
    }

    echo '</tbody></table>';
}

/**
 * 📌 Traite le règlement d'une demande de paiement depuis l'admin.
 */
function regler_paiement_admin() {
    // Vérifier que l'utilisateur est administrateur et que les paramètres sont présents
    if (!current_user_can('administrator') || !isset($_GET['regler_paiement']) || !isset($_GET['user_id'])) {
        return; // Sécurité : seul un admin peut traiter un paiement
    }

    $user_id = intval($_GET['user_id']);
    $index = intval($_GET['regler_paiement']);

    // Récupérer les paiements
    $paiements = get_user_meta($user_id, 'demande_paiement', true);
    if (empty($paiements) || !isset($paiements[$index])) {
        error_log("❌ Erreur : Paiement non trouvé pour user_id=$user_id, index=$index");
        return;
    }

    error_log("🛠️ Paiements AVANT mise à jour : " . print_r($paiements, true));

    // Mise à jour du statut du paiement
    $paiements[$index]['statut'] = 'reglé';
    $paiements[$index]['paiement_date_reglement'] = current_time('mysql');

    // Enregistrement de la mise à jour
    $update_success = update_user_meta($user_id, 'demande_paiement', $paiements);

    error_log("✅ Paiement réglé pour user_id=$user_id, index=$index");

    // Vérification après mise à jour
    $paiements_apres = get_user_meta($user_id, 'demande_paiement', true);
    error_log("🛠️ Paiements APRÈS mise à jour : " . print_r($paiements_apres, true));

    if (!$update_success) {
        error_log("❌ ERREUR : La mise à jour des paiements a échoué !");
    }

    // Rediriger pour éviter de re-traiter la requête en cas de rechargement
    wp_redirect(remove_query_arg(['regler_paiement', 'user_id']));
    exit;
}
// Enregistrement de la fonction sur le hook admin
add_action('template_redirect', 'regler_paiement_admin');

/**
 * 💶 Traiter la demande de conversion de points en euros pour un organisateur.
 *
 * Cette fonction s'exécute lors de l'envoi d'un formulaire en POST contenant le champ `demander_paiement`.
 * Elle permet à un utilisateur connecté de :
 * - Vérifier un nonce de sécurité (`demande_paiement_nonce`).
 * - Vérifier qu’il a suffisamment de points pour effectuer la conversion.
 * - Calculer le montant en euros selon le taux de conversion courant.
 * - Enregistrer la demande dans sa méta `demande_paiement`.
 * - Déduire les points convertis de son solde.
 * - Envoyer une notification par email à l’administrateur.
 * - Rediriger l’utilisateur vers la page précédente avec un paramètre de confirmation.
 *
 * 💡 Le seuil minimal de conversion est de 500 points.
 * 💡 Le taux de conversion est récupéré via `get_taux_conversion_actuel()`.
 *
 * @return void
 *
 * @hook init
 */
function traiter_demande_paiement() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['demander_paiement'])) {
        return; // 🚫 Ne rien faire si ce n'est pas une requête POST valide
    }

    // ✅ Vérification du nonce pour la sécurité
    if (!isset($_POST['demande_paiement_nonce']) || !wp_verify_nonce($_POST['demande_paiement_nonce'], 'demande_paiement_action')) {
        wp_die('❌ Vérification du nonce échouée.');
    }

    // ✅ Vérification de l'utilisateur connecté
    if (!is_user_logged_in()) {
        wp_die('❌ Vous devez être connecté pour effectuer cette action.');
    }

    $user_id = get_current_user_id();
    $solde_actuel = get_user_points($user_id) ?: 0;
    $taux_conversion = get_taux_conversion_actuel();

    // ✅ Vérification du nombre de points demandés
    $points_a_convertir = isset($_POST['points_a_convertir']) ? intval($_POST['points_a_convertir']) : 0;

    if ($points_a_convertir < 500) {
        wp_die('❌ Le minimum pour une conversion est de 500 points.');
    }

    if ($points_a_convertir > $solde_actuel) {
        wp_die('❌ Vous n\'avez pas assez de points pour effectuer cette conversion.');
    }

    // ✅ Calcul du montant en euros
    $montant_euros = round(($points_a_convertir / 1000) * $taux_conversion, 2);

    // 📌 Récupération des demandes existantes et ajout de la nouvelle
    $paiements = get_user_meta($user_id, 'demande_paiement', true) ?: [];
    $paiements = maybe_unserialize($paiements);

    $nouvelle_demande = [
        'paiement_date_demande' => current_time('mysql'),
        'paiement_demande_montant' => $montant_euros,
        'paiement_points_utilises' => $points_a_convertir, // ✅ AJOUT DU STOCKAGE DES POINTS
        'paiement_date_reglement' => '',
        'statut' => 'en attente'
    ];

    $paiements[] = $nouvelle_demande;

    // ✅ Enregistrement de la demande et mise à jour des points de l'utilisateur
    update_user_meta($user_id, 'demande_paiement', $paiements);
    update_user_points($user_id, -$points_a_convertir);

    error_log("✅ Demande enregistrée : " . json_encode($nouvelle_demande));

    // 📧 Notification admin
    $admin_email = get_option('admin_email');
    $subject = "Nouvelle demande de paiement";
    $message = "Une nouvelle demande de paiement a été soumise.\n\n";
    $message .= "Organisateur ID : $user_id\n";
    $message .= "Montant : {$montant_euros} €\n";
    $message .= "Points utilisés : {$points_a_convertir} points\n"; // ✅ AJOUTÉ DANS LE MAIL
    $message .= "Date : " . current_time('mysql') . "\n";
    $message .= "Statut : En attente";

    wp_mail($admin_email, $subject, $message);
    error_log("📧 Notification envoyée à l'administrateur.");

    // ✅ Redirection après soumission
    wp_redirect(add_query_arg('paiement_envoye', '1', wp_get_referer()));
    exit;
}
add_action('init', 'traiter_demande_paiement');

// ----------------------------------------------------------
// 🎛️ Mise à jour du statut des demandes de paiement (Admin)
// ----------------------------------------------------------
//
// - Cette fonction permet à l'administrateur de modifier le statut d'une demande.
// - Le statut peut être mis à "En attente" ou "Réglé".
// - Si le statut passe à "Réglé", la date de règlement est enregistrée.
// - L'enregistrement se fait après un clic sur le bouton "Enregistrer" du formulaire.
//
// 📌 Où est utilisé ce code ?
/*
  - Dans le shortcode [demandes_paiement] (affichage du tableau des demandes)
  - Formulaire avec liste déroulante pour modifier le statut
*/
//
// 🔍 Comment le retrouver rapidement ?
// 👉 Rechercher "🎛️ Mise à jour du statut des demandes de paiement"
//

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['modifier_statut'], $_POST['paiement_id'], $_POST['statut']) && current_user_can('administrator')) {
    $user_id = get_current_user_id();
    $paiements = maybe_unserialize(get_user_meta($user_id, 'demande_paiement', true)) ?: [];
    $paiement_id = intval($_POST['paiement_id']);

    if (isset($paiements[$paiement_id])) {
        $paiements[$paiement_id]['statut'] = sanitize_text_field($_POST['statut']);
        $paiements[$paiement_id]['paiement_date_reglement'] = ($paiements[$paiement_id]['statut'] === 'regle') ? current_time('mysql') : '';
        update_user_meta($user_id, 'demande_paiement', $paiements);
        error_log("✅ Statut mis à jour pour l'entrée $paiement_id : " . $paiements[$paiement_id]['statut']);
        if ($paiements[$paiement_id]['statut'] === 'regle') {
        $montant_paye = floatval($paiements[$paiement_id]['paiement_demande_montant']);
        mettre_a_jour_paiements_organisateurs($montant_paye); // 🔄 Mise à jour des paiements aux organisateurs
        error_log("✅ Paiement ajouté aux statistiques : {$montant_paye} €.");
        }   
    }
}



// ==================================================
// 📦 RÉINITIALISATION
// ==================================================
/**
 * 🔹 traiter_reinitialisation_stats → Réinitialiser les statistiques globales du site.
 * 🔹 ajouter_bouton_reinitialisation_stats → Ajouter une option pour activer ou désactiver la réinitialisation.
 * 🔹 gerer_activation_reinitialisation_stats → Gérer l’activation ou la désactivation de la réinitialisation des stats.
 * 🔹 supprimer_metas_organisateur → Supprimer les métadonnées liées aux organisateurs.
 * 🔹 supprimer_metas_utilisateur → Supprimer les métadonnées des utilisateurs (optimisé).
 * 🔹 supprimer_metas_globales → Supprimer les métadonnées globales stockées dans `option_meta`.
 * 🔹 supprimer_metas_post → Supprimer les métadonnées des énigmes et chasses (optimisé).
 * 🔹 supprimer_souscriptions_utilisateur → Supprimer les souscriptions des joueurs aux énigmes.
 * 🔹 reinitialiser_enigme → Réinitialiser l’état d’une énigme pour un utilisateur donné.
 * 🔹 bouton_reinitialiser_enigme_callback → Afficher le bouton de réinitialisation si l’utilisateur a résolu l’énigme.
 */


/**
 * 🔄 Réinitialiser les statistiques globales du site (administrateur uniquement).
 *
 * Cette fonction est déclenchée par le hook `admin_post_reset_stats_action`
 * lorsqu’un formulaire POST est soumis avec le champ `reset_stats`
 * et le nonce `reset_stats_nonce`. Elle permet de :
 *
 * 1. 🧹 Supprimer toutes les métadonnées utilisateurs liées aux statistiques :
 *    - total de chasses terminées, énigmes jouées, points dépensés/gagnés, etc.
 *
 * 2. 🧹 Supprimer toutes les métadonnées des posts (CPT `enigme` et `chasse`) liées
 *    aux tentatives, indices, progression et joueurs associés.
 *
 * 3. 🗑 Supprimer le taux de conversion (ACF) enregistré dans le post `Paiements`.
 *
 * 4. 🧹 Supprimer les métadonnées globales du site et des organisateurs (via fonctions dédiées).
 *
 * 5. 🔧 Supprimer l’option `activer_reinitialisation_stats` pour éviter un double déclenchement.
 *
 * 6. 🚀 Rediriger vers la page d’administration dédiée une fois la suppression terminée.
 *
 * 🔐 La fonction ne s’exécute que :
 * - en contexte admin,
 * - si l’utilisateur est administrateur,
 * - si le nonce est valide,
 * - et si l’option `activer_reinitialisation_stats` est activée.
 *
 * @return void
 *
 * @hook admin_post_reset_stats_action
 */
function traiter_reinitialisation_stats() {
    if (!is_admin() || !current_user_can('administrator')) return;
    if (!isset($_POST['reset_stats']) || !check_admin_referer('reset_stats_action', 'reset_stats_nonce')) return;
    if (!get_option('activer_reinitialisation_stats', false)) return; // Vérification activée

    error_log("🛠 Début de la suppression des statistiques...");

    supprimer_metas_utilisateur([
        'total_enigmes_jouees', 'total_chasses_terminees', 'total_indices_debloques',
        'total_points_depenses', 'total_points_gagnes', 'total_enigmes_trouvees'
    ]);
    supprimer_souscriptions_utilisateur();

    supprimer_metas_post('enigme', [
        'total_tentatives_enigme', 'total_indices_debloques_enigme',
        'total_points_depenses_enigme', 'total_joueurs_ayant_resolu_enigme',
        'total_joueurs_souscription_enigme', 'progression_joueurs'
    ]);

    supprimer_metas_post('chasse', [
        'total_tentatives_chasse', 'total_indices_debloques_chasse',
        'total_points_depenses_chasse', 'total_joueurs_souscription_chasse',
        'progression_joueurs'
    ]);

    // 🚀 SUPPRESSION DES TAUX DE CONVERSION ACF
    $paiements_post = get_posts([
        'post_type'      => 'administration',
        'posts_per_page' => 1,
        'title'          => 'Paiements',
        'post_status'    => 'private'
    ]);

    if (!empty($paiements_post)) {
        $post_id = $paiements_post[0]->ID;
        delete_field('taux_conversion', $post_id);
        error_log("✅ Taux de conversion réinitialisé pour le post ID : {$post_id}");
    } else {
        error_log("⚠️ Aucun post 'Paiements' trouvé, impossible de réinitialiser les taux.");
    }
    supprimer_metas_globales();
    supprimer_metas_organisateur();


    // 🔄 Désactiver l'option après suppression
    delete_option('activer_reinitialisation_stats');

    error_log("✅ Statistiques réinitialisées avec succès.");

    // ✅ Vérification du problème d'écran blanc
    error_log("✅ Fin du script, lancement de la redirection...");
    
    // Vérifier si les headers sont déjà envoyés
    if (!headers_sent()) {
        wp_redirect(home_url('/administration/outils/?updated=true'));
        exit;
    } else {
        error_log("⛔ Problème de redirection : headers déjà envoyés.");
        die("⛔ Problème de redirection. Recharge manuelle nécessaire.");
    }
}
add_action('admin_post_reset_stats_action', 'traiter_reinitialisation_stats');


/**
 * ⚙️ Affiche l'interface d'administration pour activer et déclencher la réinitialisation des statistiques.
 *
 * Cette fonction génère un bloc HTML dans une page d'administration personnalisée,
 * visible uniquement pour les administrateurs.
 *
 * Elle propose deux actions :
 *
 * 1. ✅ Un **checkbox** pour activer ou désactiver la réinitialisation des stats, 
 *    enregistrée dans l'option `activer_reinitialisation_stats`.
 *
 * 2. ⚠️ Un **bouton de réinitialisation** (affiché uniquement si activé), qui soumet une requête POST
 *    vers `admin_post_reset_stats_action` (gérée par la fonction `traiter_reinitialisation_stats()`).
 *
 * 🔐 La fonction est protégée :
 * - par une vérification de rôle (`administrator`)
 * - par un nonce de sécurité (`reset_stats_action`)
 *
 * 📝 L'action est irréversible : elle supprime toutes les métadonnées statistiques
 * liées aux utilisateurs, énigmes, chasses, et réglages globaux.
 *
 * @return void
 */
function ajouter_bouton_reinitialisation_stats() {
    if (!current_user_can('administrator')) return;

    $reinit_active = get_option('activer_reinitialisation_stats', false);

    ?>
    <div class="wrap">
        <h2>Réinitialisation des Statistiques</h2>
        <p>⚠️ <strong>Attention :</strong> Cette action est irréversible. Toutes les statistiques des joueurs, énigmes et chasses seront supprimées.</p>

        <form method="post">
            <?php wp_nonce_field('reset_stats_action', 'reset_stats_nonce'); ?>

            <label>
                <input type="checkbox" name="activer_reinit" value="1" <?php checked($reinit_active, true); ?>>
                Activer la réinitialisation des statistiques
            </label>

            <br><br>
            <input type="submit" name="enregistrer_reinit" class="button button-primary" value="Enregistrer">
        </form>

        <?php if ($reinit_active): ?>
            <br>
            <form method="post">
                <?php wp_nonce_field('reset_stats_action', 'reset_stats_nonce'); ?>
                <input type="submit" name="reset_stats" class="button button-danger" value="⚠️ Réinitialiser toutes les statistiques" 
                       onclick="return confirm('⚠️ ATTENTION : Cette action est irréversible. Confirmez-vous la réinitialisation ?');">
            </form>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * 📌 Gestion de l'activation/désactivation de la réinitialisation des stats
 */
function gerer_activation_reinitialisation_stats() {
    error_log("🛠 Début du traitement de l'activation/désactivation");

    // ✅ Vérification des permissions administrateur
    if (!current_user_can('manage_options')) {
        error_log("⛔ Problème de permission : utilisateur non autorisé.");
        wp_die(__('⛔ Accès refusé. Vous n’avez pas la permission d’effectuer cette action.', 'textdomain'));
    }
    error_log("🔎 Permission OK");

    // ✅ Vérification de la requête POST et de la sécurité
    if (!isset($_POST['enregistrer_reinit']) || !check_admin_referer('toggle_reinit_stats_action', 'toggle_reinit_stats_nonce')) {
        error_log("⛔ Problème de nonce ou bouton non soumis.");
        wp_die(__('⛔ Erreur de sécurité. Veuillez réessayer.', 'textdomain'));
    }
    error_log("🔎 Nonce OK");

    // ✅ Mise à jour de l'option d'activation
    $activer = isset($_POST['activer_reinit']) ? 1 : 0;
    update_option('activer_reinitialisation_stats', $activer);
    error_log("✅ Option mise à jour : " . ($activer ? 'Activée' : 'Désactivée'));

    // ✅ Ajout d’un message d’alerte WordPress
    add_action('admin_notices', function() use ($activer) {
        echo '<div class="updated"><p>✅ Réinitialisation des stats ' . ($activer ? 'activée' : 'désactivée') . '.</p></div>';
    });

    // ✅ Vérification de la redirection
    $page_outils = get_page_by_path('administration/outils');
    if ($page_outils) {
        $redirect_url = get_permalink($page_outils) . '?updated=true';
    } else {
        $redirect_url = home_url('/administration/outils/?updated=true');
    }

    error_log("🔄 Redirection vers : " . $redirect_url);
    if (!headers_sent()) {
        wp_redirect($redirect_url);
        exit;
    } else {
        error_log("⛔ Problème de redirection : headers déjà envoyés.");
    }

    exit;
}
add_action('admin_post_toggle_reinit_stats_action', 'gerer_activation_reinitialisation_stats');

/**
 * 📌 Supprime les méta liées aux organisateurs
 * - Points perçus par les organisateurs
 * - Historique des paiements aux organisateurs
 */
function supprimer_metas_organisateur() {
    global $wpdb;

    $meta_keys = [
        'total_points_percus_organisateur',
        'demande_paiement' // Suppression de l'historique des paiements
    ];

    // Récupération des utilisateurs ayant un rôle d'organisateur
    $organisateurs = get_users([
        'role' => 'organisateur',
        'fields' => 'ID'
    ]);

    if (empty($organisateurs)) {
        error_log("ℹ️ Aucun organisateur trouvé. Rien à supprimer.");
        return;
    }

    foreach ($organisateurs as $user_id) {
        foreach ($meta_keys as $meta_key) {
            // Vérifie si la méta existe avant suppression
            $meta_existante = get_user_meta($user_id, $meta_key, true);
            if (!empty($meta_existante)) {
                if ($meta_key === 'demande_paiement') {
                    // Suppression forcée via SQL pour l'historique des paiements
                    $wpdb->delete($wpdb->usermeta, ['user_id' => $user_id, 'meta_key' => $meta_key]);
                    error_log("✅ Suppression forcée via SQL pour : {$meta_key} (user_id {$user_id})");
                } else {
                    // Suppression normale pour les autres méta
                    delete_user_meta($user_id, $meta_key);
                    error_log("✅ Suppression réussie de : {$meta_key} pour user_id {$user_id}");
                }

                // Vérification post-suppression
                $meta_post_suppression = get_user_meta($user_id, $meta_key, true);
                if (!empty($meta_post_suppression)) {
                    error_log("⚠️ Problème : {$meta_key} n'a pas été supprimé pour user_id {$user_id}.");
                } else {
                    error_log("✅ Vérification OK : {$meta_key} a bien été supprimé pour user_id {$user_id}.");
                }
            } else {
                error_log("ℹ️ Aucune méta trouvée pour : {$meta_key} de user_id {$user_id}.");
            }
        }
    }
}


/**
 * 📌 Suppression optimisée des métas utilisateurs
 */
function supprimer_metas_utilisateur($meta_keys) {
    global $wpdb;
    $placeholders = implode(',', array_fill(0, count($meta_keys), '%s'));

    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->usermeta} WHERE meta_key IN ($placeholders)",
        ...$meta_keys
    ));

    // Vérification d'erreur SQL
    if (!empty($wpdb->last_error)) {
        error_log("⚠️ Erreur SQL lors de la suppression des metas utilisateur : " . $wpdb->last_error);
    }
    $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'enigme_%_resolue'");

}

/**
 * 📌 Supprime les méta globales stockées en `option_meta`
 */
function supprimer_metas_globales() {
    $metas_globales = [
        'total_points_depenses_mois_' . date('Y_m'),
        'total_points_vendus_mensuel_' . date('Y_m'),
        'revenu_total_site',
        'revenu_total_site_mensuel_' . date('Y_m'),
        'total_paiements_effectues_mensuel_' . date('Y_m'),
        'total_points_en_circulation'
    ];

    foreach ($metas_globales as $meta) {
        delete_option($meta);
        error_log("✅ Suppression réussie de l'option : $meta");
    }
}

/**
 * 📌 Suppression optimisée des métas des énigmes et chasses
 */
function supprimer_metas_post($post_type, $meta_keys) {
    global $wpdb;

    $post_ids = get_posts([
        'post_type'      => $post_type,
        'posts_per_page' => -1,
        'fields'         => 'ids'
    ]);

    if (empty($post_ids)) {
        error_log("ℹ️ Aucun post trouvé pour le type : {$post_type}. Rien à supprimer.");
        return;
    }

    foreach ($meta_keys as $meta_key) {
        // 🔍 Vérifier si la méta existe avant suppression
        $existe = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
            $meta_key . '%'
        ));

        if ($existe > 0) {
            // 🚀 Suppression optimisée de toutes les variations de la méta
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
                $meta_key . '%'
            ));
            error_log("✅ Suppression réussie pour : {$meta_key}%");
        } else {
            error_log("ℹ️ Aucune méta trouvée pour : {$meta_key}%");
        }
    }
}

/**
 * 📌 Suppression des souscriptions des joueurs aux énigmes
 */
function supprimer_souscriptions_utilisateur() {
    global $wpdb;

    // 🚀 Suppression de toutes les souscriptions utilisateur pour toutes les énigmes
    $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'enigme_%_souscrit'");

    if (!empty($wpdb->last_error)) {
        error_log("⚠️ Erreur SQL lors de la suppression des souscriptions utilisateur : " . $wpdb->last_error);
    } else {
        error_log("✅ Suppression réussie des souscriptions aux énigmes.");
    }
}

/**
 * 🔄 Réinitialise l’état d’une énigme pour un utilisateur donné :
 * - Supprime le statut et la date de résolution.
 * - Réinitialise les indices débloqués.
 * - Supprime les trophées liés à l’énigme et à la chasse associée.
 * - Réinitialise le statut de la chasse si nécessaire.
 * - Nettoie les caches liés à l’utilisateur et à l’énigme.
 *
 * @param int $user_id ID de l’utilisateur.
 * @param int $enigme_id ID de l’énigme.
 */
function reinitialiser_enigme($user_id, $enigme_id) {
    if (!is_numeric($user_id) || !is_numeric($enigme_id)) {
        error_log("⚠️ Paramètres invalides : user_id={$user_id}, enigme_id={$enigme_id}");
        return;
    }

    error_log("🔄 DÉBUT de la réinitialisation pour l'utilisateur (ID: {$user_id}) sur l'énigme (ID: {$enigme_id})");

    // 🧹 1. Suppression du statut et de la date de résolution
    delete_user_meta($user_id, "statut_enigme_{$enigme_id}");
    delete_user_meta($user_id, "enigme_{$enigme_id}_resolution_date");
    error_log("🧹 Statut et date de résolution supprimés pour l'énigme (ID: {$enigme_id})");

    // 🗑️ 2. Réinitialisation des indices débloqués
    $indices = get_field('indices', $enigme_id); 
    if (!empty($indices) && is_array($indices)) {
        foreach ($indices as $index => $indice) {
            delete_user_meta($user_id, "indice_debloque_{$enigme_id}_{$index}");
        }
        error_log("🧹 Indices débloqués réinitialisés pour l'énigme (ID: {$enigme_id})");
    }

    // 🏆 3. Suppression du trophée associé à l’énigme
    $trophees_utilisateur = get_user_meta($user_id, 'trophees_utilisateur', true);
    $trophees_utilisateur = is_array($trophees_utilisateur) ? $trophees_utilisateur : [];

    $trophee_enigme = get_field('trophee_associe', $enigme_id);
    if ($trophee_enigme) {
        $trophee_enigme_id = is_array($trophee_enigme) ? reset($trophee_enigme) : $trophee_enigme;
        if (($key = array_search($trophee_enigme_id, $trophees_utilisateur)) !== false) {
            unset($trophees_utilisateur[$key]);
            update_user_meta($user_id, 'trophees_utilisateur', array_values($trophees_utilisateur));
            error_log("🏆 Trophée de l'énigme (ID: {$trophee_enigme_id}) supprimé pour l'utilisateur (ID: {$user_id})");
        }
    }

    // 🏴‍☠️ 4. Gestion de la chasse associée
    $chasse_id = get_field('chasse_associee', $enigme_id, false);
    $chasse_id = is_array($chasse_id) ? reset($chasse_id) : $chasse_id;

    if ($chasse_id && is_numeric($chasse_id)) {
        // 🏆 Suppression du trophée associé à la chasse
        $trophee_chasse = get_field('trophee_associe', $chasse_id);
        if ($trophee_chasse) {
            $trophee_chasse_id = is_array($trophee_chasse) ? reset($trophee_chasse) : $trophee_chasse;
            if (($key = array_search($trophee_chasse_id, $trophees_utilisateur)) !== false) {
                unset($trophees_utilisateur[$key]);
                update_user_meta($user_id, 'trophees_utilisateur', array_values($trophees_utilisateur));
                error_log("🏆 Trophée de chasse (ID: {$trophee_chasse_id}) supprimé pour l'utilisateur (ID: {$user_id})");
            }
        }

        // 🔄 Si la chasse est en mode "stop" et terminée, la remettre en cours
        $illimitee = get_field('illimitee', $chasse_id); // Récupère le mode de la chasse (stop / continue)
        $statut_chasse = get_field('statut_chasse', $chasse_id);
        
        // Vérifie si la chasse est en mode "stop" et si elle est terminée
        if ($illimitee === 'stop' && in_array(mb_strtolower($statut_chasse), ['termine', 'terminée', 'terminé'], true)) {
            update_field('statut_chasse', 'en cours', $chasse_id);
            update_field('gagnant', '', $chasse_id);
            update_field('date_de_decouverte', null, $chasse_id);
        
            delete_post_meta($chasse_id, 'statut_chasse');
            delete_post_meta($chasse_id, 'gagnant');
            delete_post_meta($chasse_id, 'date_de_decouverte');
        
            wp_cache_delete($chasse_id, 'post_meta');
            clean_post_cache($chasse_id);
        
            error_log("🔄 Chasse (ID: {$chasse_id}) réinitialisée : statut 'en cours', gagnant et date supprimés.");
        }
    }

    // 🚀 5. (Optionnel) Réinitialisation de la souscription pour permettre de rejouer immédiatement
    // Décommentez la ligne suivante si vous souhaitez que le bouton "JOUER" apparaisse directement après réinitialisation :
    // update_user_meta($user_id, "statut_enigme_{$enigme_id}", 'souscrit');
    // error_log("🔄 Souscription réinitialisée pour l'énigme (ID: {$enigme_id}) → bouton 'JOUER' réactivé.");

    // 🧹 6. Nettoyage des caches
    // 🚀 5. Rafraîchissement des caches WordPress pour garantir l'affichage correct
wp_cache_delete($user_id, 'user_meta'); // Supprime le cache des métas utilisateur
wp_cache_delete("statut_enigme_{$enigme_id}", 'user_meta'); // Supprime le cache spécifique du statut d'énigme
wp_cache_delete($enigme_id, 'post_meta'); // Supprime le cache des métas du post (énigme)

clean_user_cache($user_id); // Nettoie le cache complet de l'utilisateur
clean_post_cache($enigme_id); // Nettoie le cache du post énigme

error_log("🔄 Caches utilisateur et post nettoyés après réinitialisation.");
error_log("✅ Réinitialisation complète terminée pour l'utilisateur (ID: {$user_id}) sur l'énigme (ID: {$enigme_id})");

}

/**
 * 🔄 Affiche le bouton de réinitialisation si l'utilisateur a résolu l'énigme.
 *
 * Conditions :
 * - Affiche si le statut de l’énigme est "resolue" ou "terminee_resolue".
 *
 * @return string HTML du bouton ou chaîne vide si non applicable.
 */
function bouton_reinitialiser_enigme_callback() {
    if (!is_user_logged_in() || !is_singular('enigme') || !current_user_can('administrator')) return ''; // 🚫 Restreint aux admins

    $user_id = get_current_user_id();
    $enigme_id = get_the_ID();
    $statut = enigme_get_statut($enigme_id, $user_id); // 🔄 Utilisation du statut centralisé

    // ✅ Affiche le bouton uniquement si l'énigme est résolue ou terminée-résolue
    if (!in_array($statut, ['resolue', 'terminee_resolue'])) return '';

    return "
        <form method='post' class='form-reinitialiser-enigme'>
            <button type='submit' name='reinitialiser_enigme' class='bouton-action bouton-reinitialiser dynamique-{$statut}'>
                🔄 Réinitialiser l’énigme
            </button>
        </form>";
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
function recuperer_details_acf() {
    if (!current_user_can('administrator')) {
        wp_send_json_error('Non autorisé');
    }

    // Utilisation des "keys" ACF directement car les IDs ne sont pas fiables
    // lorsque les groupes sont chargés via JSON local.
    $group_keys = [
        'group_67b58c51b9a49', // Paramètre de la chasse (ID 27)
        'group_67b58134d7647', // Paramètres de l’énigme (ID 9)
        'group_67c7dbfea4a39', // Paramètres organisateur (ID 657)
    ];

    ob_start();
    foreach ($group_keys as $key) {
        acf_inspect_field_group($key);
        echo "\n";
    }
    $output = ob_get_clean();
    $output = wp_strip_all_tags($output);
    wp_send_json_success($output);
}
add_action('wp_ajax_recuperer_details_acf', 'recuperer_details_acf');


/**
 * Charge le script de la carte Développement sur la page Mon Compte
 */
function charger_script_developpement_card() {
    if (is_page('mon-compte') && current_user_can('administrator')) {
        wp_enqueue_script(
            'developpement-card',
            get_stylesheet_directory_uri() . '/assets/js/developpement-card.js',
            [],
            filemtime(get_stylesheet_directory() . '/assets/js/developpement-card.js'),
            true
        );
        wp_localize_script('developpement-card', 'ajax_object', [
            'ajax_url' => admin_url('admin-ajax.php')
        ]);
    }
}
add_action('wp_enqueue_scripts', 'charger_script_developpement_card');

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

    $users   = get_users(['role' => 'organisateur_creation']);
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

        $chasse     = $chasses[0];
        $nb_enigmes = count(recuperer_enigmes_associees($chasse->ID));

        $entries[] = [
            'date_creation'      => $date_creation,
            'organisateur_titre' => get_the_title($organisateur_id),
            'chasse_id'          => $chasse->ID,
            'chasse_titre'       => get_the_title($chasse->ID),
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
