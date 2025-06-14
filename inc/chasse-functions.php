<?php
defined( 'ABSPATH' ) || exit;


//
// 1. 📦 FONCTIONS LIÉES À UNE CHASSE
// 2. 📦 AFFICHAGE
//


// ==================================================
// 📦 FONCTIONS LIÉES À UNE CHASSE
// ==================================================
/**
 * 🔹 recuperer_infos_chasse → Récupérer les informations essentielles d’une chasse.
 * 🔹 chasse_get_champs → Récupérer les champs principaux et cachés structurés d'une chasse
 * 🔹 verifier_souscription_chasse → Vérifier si un utilisateur souscrit à une chasse pour la première fois en souscrivant à une énigme.
 * 🔹 acf/validate_value/name=date_de_fin (function) → Valider les incohérences de dates dans les chasses.
 * 🔹 gerer_chasse_terminee → Déclencher toutes les actions nécessaires lorsqu’une chasse est terminée.
 */



/**
 * Récupère les informations essentielles d'une chasse.
 *
 * @param int $chasse_id ID de la chasse.
 * @return array Associatif avec 'lot', 'date_de_debut', 'date_de_fin'.
 */
function recuperer_infos_chasse($chasse_id) {
    $champs = get_fields($chasse_id);
    return [
        'lot' => $champs['lot'] ?? 'Non spécifié',
        'date_de_debut' => $champs['date_de_debut'] ?? 'Non spécifiée',
        'date_de_fin' => $champs['date_de_fin'] ?? 'Non spécifiée',
    ];
}



/**
 * Récupère les champs principaux et cachés d'une chasse.
 *
 * @param int $chasse_id ID de la chasse.
 * @return array
 */
function chasse_get_champs($chasse_id) {
    $caracteristiques = get_field('caracteristiques', $chasse_id) ?? [];
    $champs_caches = get_field('champs_caches', $chasse_id) ?? [];

    return [
        'lot' => $caracteristiques['chasse_infos_recompense_texte'] ?? '',
        'titre_recompense' => $caracteristiques['chasse_infos_recompense_titre'] ?? '',
        'valeur_recompense' => $caracteristiques['chasse_infos_recompense_valeur'] ?? '',
        'cout_points' => $caracteristiques['chasse_infos_cout_points'] ?? 0,
        'date_debut' => $caracteristiques['chasse_infos_date_debut'] ?? null,
        'date_fin' => $caracteristiques['chasse_infos_date_fin'] ?? null,
        'illimitee' => $caracteristiques['chasse_infos_duree_illimitee'] ?? false,
        'nb_max' => $caracteristiques['chasse_infos_nb_max_gagants'] ?? 0,
        'date_decouverte' => $champs_caches['chasse_cache_date_decouverte'] ?? null,
        'current_stored_statut' => $champs_caches['chasse_cache_statut'] ?? null,
    ];
}



/**
 * Vérifie si un utilisateur souscrit à une chasse pour la première fois en souscrivant à une énigme.
 *
 * @param int $user_id ID de l'utilisateur
 * @param int $enigme_id ID de l'énigme souscrite
 */
function verifier_souscription_chasse($user_id, $enigme_id) {

    if (!$user_id || !$enigme_id) {
        error_log("🚨 ERREUR : ID utilisateur ou énigme manquant.");
        return;
    }

    // 🏴‍☠️ Récupération de la chasse associée à l’énigme
    $chasse_id = get_field('chasse_associee', $enigme_id);
    if (!$chasse_id) {
        error_log("⚠️ Aucune chasse associée à l'énigme ID {$enigme_id}");
        return;
    }

    // 🔍 Vérification si l'utilisateur a déjà joué une énigme de cette chasse
    $enigmes_associees = get_field('enigmes_associees', $chasse_id);
    if (!$enigmes_associees || !is_array($enigmes_associees)) {
        error_log("⚠️ Pas d'énigmes associées à la chasse ID {$chasse_id}");
        return;
    }

    foreach ($enigmes_associees as $eid) {
        $statut = get_user_meta($user_id, "statut_enigme_{$eid}", true);

        // 🚫 Si une énigme a déjà été souscrite, tentée ou trouvée, la chasse est déjà souscrite
        if ($statut && $statut !== 'non_souscrit') {
            error_log("🔄 L'utilisateur ID {$user_id} a déjà interagi avec l'énigme ID {$eid}. Chasse ID {$chasse_id} déjà souscrite.");
            return;
        }
    }
    
    error_log("🔍 Vérification avant mise à jour souscription chasse ID {$chasse_id} : Utilisateur ID {$user_id}");

    // ✅ Première souscription à une énigme de cette chasse => Marquer la chasse comme souscrite
    update_user_meta($user_id, "souscription_chasse_{$chasse_id}", true);
    
    // 🔄 Mise à jour du compteur global de souscriptions à la chasse
    $meta_key = "total_joueurs_souscription_chasse_{$chasse_id}";
    $total_souscriptions = get_post_meta($chasse_id, $meta_key, true) ?: 0;
    update_post_meta($chasse_id, $meta_key, $total_souscriptions + 1);
    error_log("✅ Nouvelle valeur souscription chasse {$chasse_id} : " . get_post_meta($chasse_id, $meta_key, true));


    error_log("✅ Nouvelle souscription à la chasse ID {$chasse_id} par l'utilisateur ID {$user_id}");
}

/**
 * 📌 Validation des incohérences de dates dans les chasses.
 */
add_filter('acf/validate_value/name=date_de_fin', function ($valid, $value, $field, $input) {
    if (!$valid) {
        return $valid; // 🚫 Ne pas écraser d'autres erreurs
    }

    if (get_post_type($_POST['post_ID'] ?? 0) !== 'chasse') {
        return $valid;
    }

    // 🔄 Reformater `date_de_fin` si nécessaire
    if (preg_match('/^\d{8}$/', $value)) {
        $value = substr($value, 0, 4) . '-' . substr($value, 4, 2) . '-' . substr($value, 6, 2);
    }

    // 🔍 Récupération de la date de début à partir des données du formulaire
    $caracteristiques_key = 'field_67ca7fd7f5117'; // ID du groupe "caracteristiques"
    $date_debut_key = 'field_67b58c6fd98ec'; // ID du champ "date_de_debut"
    $date_debut = $_POST['acf'][$caracteristiques_key][$date_debut_key] ?? null;


    // ✅ Vérification : La date de fin ne peut pas être avant la date de début
    if (!empty($date_debut) && !empty($value) && strtotime($value) < strtotime($date_debut)) {
        return __('⚠️ Erreur : La date de fin ne peut pas être antérieure à la date de début.', 'textdomain');
    }

    // ✅ Vérification : Si "maintenant" est sélectionné, date_de_fin ne peut pas être antérieure à aujourd'hui
    if ($_POST['acf'][$caracteristiques_key]['field_67ca858935c21'] === 'maintenant' &&
        !empty($value) && strtotime($value) < strtotime(date('Y-m-d'))) {
        return __('⚠️ Erreur : La date de fin ne peut pas être antérieure à la date du jour si la chasse commence maintenant.', 'textdomain');
    }

    return $valid;
}, 10, 4);

/**
 * 📌 Déclenche toutes les actions nécessaires lorsqu'une chasse est terminée.
 *
 * @param int $chasse_id ID de la chasse concernée.
 * @return void
 */
function gerer_chasse_terminee($chasse_id) {
    // ✅ Vérification que la chasse est bien "Terminée"
    $statut_chasse = get_field('statut_chasse', $chasse_id);
    if ($statut_chasse !== 'Terminée') {
        return;
    }

    // 🎯 Afficher le trophée si applicable
    afficher_trophee_chasse($chasse_id, $statut_chasse);

    // 🏆 Gérer l'attribution des récompenses (ex: points, trophées, médailles)
    //attribuer_recompenses_chasse($chasse_id);

    // 📩 Envoyer une notification aux participants
    //notifier_fin_chasse($chasse_id);

    // 🔄 Autres actions futures...
}



// ==================================================
// 📦 AFFICHAGE
// ==================================================
/**
 * 🔹 afficher_chasse_associee_callback → ffiche les informations principales de la chasse associée à l’énigme.


/**
 * 🏴‍☠️ Affiche les informations principales de la chasse associée à l’énigme.
 *
 * Informations affichées (sauf si l'énigme est souscrite/en cours) :
 * - Titre de la chasse
 * - Lot
 * - Durée
 * - Icône Discord cliquable (si lien ACF disponible)
 *
 * @return string HTML des informations de la chasse ou chaîne vide si aucune chasse associée ou énigme en cours.
 */
function afficher_chasse_associee_callback() {
    if (!is_singular('enigme')) return '';

    $enigme_id = get_the_ID();
    $user_id = get_current_user_id();

    // ✅ Si l’énigme est souscrite (en cours), on n'affiche pas la chasse associée
    $statut = enigme_get_statut_light($user_id, $enigme_id);
    if ($statut === 'en_cours') return '';

    $chasse = recuperer_chasse_associee($enigme_id);
    if (!$chasse) return ''; // 🚫 Pas de chasse associée

    $infos_chasse = recuperer_infos_chasse($chasse->ID) ?: [
        'lot' => 'Non spécifié',
        'date_de_debut' => 'Non spécifiée',
        'date_de_fin' => 'Non spécifiée',
    ];

    $lien_discord = get_field('lien_discord', $chasse->ID);
    $icone_discord = esc_url(get_stylesheet_directory_uri() . '/assets/images/discord-icon.png');
    $titre = esc_html(get_the_title($chasse->ID));
    $url = esc_url(get_permalink($chasse->ID));

    ob_start(); ?>
    <section class="chasse-associee">
        <h3>Chasse au Trésor</h3>
        <h2><strong><a href="<?= $url; ?>" class="lien-chasse-associee"><?= $titre; ?></a></strong></h2>
        <p>🏆 <strong>Lot :</strong> <?= esc_html($infos_chasse['lot']); ?></p>
        <p>📅 <strong>Durée :</strong> <?= esc_html($infos_chasse['date_de_debut']); ?> au <?= esc_html($infos_chasse['date_de_fin']); ?></p>

        <?php if (!empty($lien_discord)) : ?>
            <p>
                <a href="<?= esc_url($lien_discord); ?>" target="_blank" rel="noopener noreferrer" aria-label="Rejoindre le Discord">
                    <img src="<?= $icone_discord; ?>" alt="Discord" class="discord-icon">
                </a>
            </p>
        <?php endif; ?>
    </section>
    <?php
    return ob_get_clean();
}

