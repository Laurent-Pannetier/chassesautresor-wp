<?php
defined( 'ABSPATH' ) || exit;

// ==================================================
// 📚 SOMMAIRE DU FICHIER : organisateur-functions.php
// ==================================================
//
//  📦 CHARGEMENT DES DONNEES
//  📦 GESTION DEMANDES DE CONVERSION
//  📩 FORMULAIRE DE CONTACT ORGANISATEUR (WPForms)
//




// ==================================================
// 📦 CHARGEMENT DES DONNEES
// ==================================================
/**
 * 🔹 enqueue_script_header_organisateur_ui
 */
 
/**
 * 🧭 Enfile le script UI de navigation pour le header organisateur.
 *
 * Ce script gère :
 * – l’affichage dynamique de la section #presentation
 * – l’activation du lien actif dans le menu nav
 *
 * Chargé uniquement sur les CPT `organisateur` et `chasse`, quel que soit l’utilisateur.
 *
 * @hook wp_enqueue_scripts
 * @return void
 */
function enqueue_script_header_organisateur_ui() {
    if (is_singular(['organisateur', 'chasse', 'enigme'])) {
        $path = '/assets/js/header-organisateur-ui.js';
        wp_enqueue_script(
            'header-organisateur-ui',
            get_stylesheet_directory_uri() . $path,
            [],
            filemtime(get_stylesheet_directory() . $path),
            true
        );
    }
}
add_action('wp_enqueue_scripts', 'enqueue_script_header_organisateur_ui');




// ==================================================
// 📦 GESTION DEMANDES DE CONVERSION
// ==================================================
/**
 * 🔹 charger_script_conversion → Charger le script `conversion.js` uniquement sur les pages liées à l’espace "Mon Compte".
 * 🔹 verifier_acces_conversion → Vérifier si un utilisateur peut soumettre une demande de conversion.
 * 🔹 afficher_tableau_paiements_organisateur → Afficher le tableau des demandes de paiement d’un organisateur.
 */

/**
 * 📦 Charger le script `conversion.js` uniquement sur les pages liées à l’espace "Mon Compte".
 *
 * Cette fonction enfile le script JavaScript `/assets/js/conversion.js` uniquement si l’utilisateur visite :
 * - une page native WooCommerce de type "Mon Compte" (`is_account_page()`)
 * - ou une page personnalisée définie manuellement sous `/mon-compte/*` (ex: `/mon-compte/outils`)
 *
 * 🔎 Le fichier est versionné dynamiquement via `filemtime()` pour éviter le cache.
 *
 * @hook wp_enqueue_scripts
 * @return void
 */
function charger_script_conversion() {
    // Inclure les pages WooCommerce natives
    if (is_account_page()) {
        $inclure = true;
    } else {
        // Inclure aussi les pages customisées que tu as créées sous /mon-compte/*
        $request_uri = trim($_SERVER['REQUEST_URI'], '/');

        $autorises = [
            'mon-compte/outils',
            'mon-compte/statistiques',
            'mon-compte/organisateurs',
            'mon-compte/organisateurs/paiements',
            'mon-compte/organisateurs/inscription',
        ];

        $inclure = in_array($request_uri, $autorises);
    }

    if (!$inclure) return;

    $script_path = get_stylesheet_directory() . '/assets/js/conversion.js';
    $version = file_exists($script_path) ? filemtime($script_path) : false;

    wp_enqueue_script(
        'conversion',
        get_stylesheet_directory_uri() . '/assets/js/conversion.js',
        [],
        $version,
        true
    );
}
add_action('wp_enqueue_scripts', 'charger_script_conversion');

/**
 * Vérifie si un utilisateur peut soumettre une demande de conversion.
 *
 * Cette fonction applique plusieurs contrôles d'accès avant qu'un utilisateur puisse demander une conversion :
 * 1. Vérifie que l'utilisateur possède bien le rôle "organisateur".
 * 2. Vérifie qu'il n'a pas déjà une demande en attente.
 * 3. Vérifie que sa dernière demande réglée date de plus de 30 jours.
 * 4. Vérifie qu'il dispose d'au moins 500 points.
 *
 * @param int $user_id L'ID de l'utilisateur à vérifier.
 * 
 * @return string|bool Retourne `true` si l'accès est autorisé, sinon un message d'erreur expliquant la raison du blocage.
 */
/**
 * Vérifie si un utilisateur peut soumettre une demande de conversion.
 *
 * @param int $user_id L'ID de l'utilisateur.
 * @return string|bool Retourne un message d'erreur si une condition bloque l'accès, sinon true.
 */
function verifier_acces_conversion($user_id) {
    // 1️⃣ Vérification du rôle (bloquant immédiat)
    $user = get_userdata($user_id);
    if (!$user || !in_array('organisateur', $user->roles)) {
        return "Inscription en cours";
    }

    // ✅ Récupération de l'ID du CPT "organisateur"
    $organisateur_id = get_organisateur_from_user($user_id);
    if (!$organisateur_id) {
        return "Erreur : Organisateur non trouvé.";
    }

    // 2️⃣ Vérification d’une demande en attente
    $paiements = get_user_meta($user_id, 'demande_paiement', true);
    if (!is_array($paiements)) {
        $paiements = []; // ✅ Force $paiements à être un tableau si vide
    }

    foreach ($paiements as $paiement) {
        if (!empty($paiement['statut']) && $paiement['statut'] === 'en attente') {
            return "Demande déjà en cours";
        }
    }

    // 3️⃣ Vérification du dernier règlement (> 30 jours)
    $dernier_paiement = null;
    foreach ($paiements as $paiement) {
        if (!empty($paiement['statut']) && $paiement['statut'] === 'reglé') {
            $date_paiement = strtotime($paiement['paiement_date_demande']);
            if (!$dernier_paiement || $date_paiement > $dernier_paiement) {
                $dernier_paiement = $date_paiement;
            }
        }
    }

    if ($dernier_paiement && $dernier_paiement > strtotime('-30 days')) {
        $jours_restants = ceil(($dernier_paiement - strtotime('-30 days')) / 86400);
        return "Attendez encore $jours_restants jours";
    }

    // 4️⃣ Vérification du solde de points (500 points minimum)
    $points_actuels = get_user_points($user_id);
    if ($points_actuels < 500) {
        return "Points insuffisants";
    }

    // 5️⃣ Vérification IBAN/BIC
    $iban = get_field('gagnez_de_largent_iban', $organisateur_id);
    $bic = get_field('gagnez_de_largent_bic', $organisateur_id);

    if (empty($iban) || empty($bic)) {
        $lien_edition = get_edit_post_link($organisateur_id);
        if (!$lien_edition) {
            $lien_edition = admin_url('post.php?post=' . $organisateur_id . '&action=edit'); // 🔹 Génération manuelle du lien
        }

        return "IBAN/BIC non remplis - <a href='" . esc_url($lien_edition) . "'>Saisir mes infos</a>";
    }

    return true; // ✅ Toutes les conditions sont remplies
}

/**
 * Affiche le tableau des demandes de paiement d'un organisateur.
 *
 * @param int $user_id L'ID de l'utilisateur organisateur.
 * @param string $filtre_statut Filtrer par statut ('en_attente' pour les demandes en cours, 'toutes' pour l'historique complet).
 */
function afficher_tableau_paiements_organisateur($user_id, $filtre_statut = 'en_attente') {
    // Récupérer les demandes de paiement de l'utilisateur
    $paiements = get_user_meta($user_id, 'demande_paiement', true);

    // Vérifier si l'utilisateur a des paiements enregistrés
    if (empty($paiements)) {
        return; // Ne rien afficher si aucune demande
    }

    // Filtrer les paiements selon le statut demandé
    $paiements_filtres = [];
    foreach ($paiements as $paiement) {
        if ($filtre_statut === 'en_attente' && $paiement['statut'] !== 'en attente') {
            continue;
        }
        $paiements_filtres[] = $paiement;
    }

    // Si aucun paiement ne correspond au filtre, ne rien afficher
    if (empty($paiements_filtres)) {
        return;
    }

    // Affichage du tableau
    echo '<table class="widefat fixed">';
    echo '<thead><tr><th>Montant (€)</th><th>Points utilisés</th><th>Date demande</th><th>Statut</th></tr></thead>';
    echo '<tbody>';

    foreach ($paiements_filtres as $paiement) {
        $statut_affiche = ($paiement['statut'] === 'reglé') ? '✅ Réglé' : '🟡 En attente';
        $points_utilises = isset($paiement['paiement_points_utilises']) ? esc_html($paiement['paiement_points_utilises']) : 'N/A';

        echo '<tr>';
        echo '<td>' . esc_html($paiement['paiement_demande_montant']) . ' €</td>';
        echo '<td>' . $points_utilises . '</td>';
        echo '<td>' . esc_html(date('Y-m-d H:i', strtotime($paiement['paiement_date_demande']))) . '</td>';
        echo '<td>' . esc_html($statut_affiche) . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
}


// ==================================================
// 📩 FORMULAIRE DE CONTACT ORGANISATEUR (WPForms)
// ==================================================
/**
 * 🔹 filtrer_destinataire_contact_organisateur → modifie le destinataire du mail via WPForms (email ACF ou auteur, BCC admin)
 * 🔹 ajouter_endpoint_contact_organisateur → ajoute l’endpoint `/contact` sur les URLs des organisateurs (détection côté template)
 */


/**
 * Ajoute l'endpoint `contact` aux permaliens des organisateurs.
 *
 * Permet de détecter /contact après un CPT organisateur dans le template.
 *
 * @return void
 */
function ajouter_endpoint_contact_organisateur() {
    add_rewrite_endpoint('contact', EP_PERMALINK);
}
add_action('init', 'ajouter_endpoint_contact_organisateur');

/**
 * Enregistre `contact` comme variable de requête valide.
 *
 * Permet d'utiliser get_query_var('contact') de manière fiable.
 *
 * @param array $vars
 * @return array
 */
function ajouter_query_var_contact($vars) {
    $vars[] = 'contact';
    return $vars;
}
add_filter('query_vars', 'ajouter_query_var_contact');

/**
 * Génére une liste hiérarchique des chasses d'un organisateur.
 *
 * Exemple de sortie :
 * - Organisateur (3 chasses)
 *   - Chasse 1 (4 énigmes)
 *   - Chasse 2 (2 énigmes)
 *
 * @param int $organisateur_id ID de l'organisateur.
 * @return string HTML contenant la liste ou chaîne vide si non valide.
 */
function generer_liste_chasses_hierarchique($organisateur_id) {
    if (!$organisateur_id || get_post_type($organisateur_id) !== 'organisateur') {
        return '';
    }

    $query = get_chasses_de_organisateur($organisateur_id);
    $nombre_chasses = $query->found_posts ?? 0;

    $out  = '<ul class="liste-chasses-hierarchique">';
    $out .= '<li>';
    $out .= '<a href="' . esc_url(get_permalink($organisateur_id)) . '">' . esc_html(get_the_title($organisateur_id)) . '</a> ';
    $out .= '(' . sprintf(_n('%d chasse', '%d chasses', $nombre_chasses, 'text-domain'), $nombre_chasses) . ')';

    if ($nombre_chasses > 0) {
        $out .= '<ul>';
        foreach ($query->posts as $post) {
            $chasse_id = $post->ID;
            $chasse_titre = get_the_title($chasse_id);
            $nb_enigmes = count(recuperer_enigmes_associees($chasse_id));
            $out .= '<li>';
            $out .= '<a href="' . esc_url(get_permalink($chasse_id)) . '">' . esc_html($chasse_titre) . '</a> ';
            $out .= '(' . sprintf(_n('%d énigme', '%d énigmes', $nb_enigmes, 'text-domain'), $nb_enigmes) . ')';
            $out .= '</li>';
        }
        $out .= '</ul>';
    }

    $out .= '</li></ul>';

    return $out;
}

