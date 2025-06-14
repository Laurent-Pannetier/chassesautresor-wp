<?php
defined( 'ABSPATH' ) || exit;

// ==================================================
// 📚 SOMMAIRE DU FICHIER : stats-functions.php
// ==================================================
//
// 1. 📊 RÉCUPÉRATION DES STATISTIQUES  
//    - Fonctions de récupération centralisée ou spécifique : énigme, chasse, utilisateur, organisateur.
//
// 2. 📊 AFFICHAGE DES STATISTIQUES  
//    - Fonctions d’affichage HTML des statistiques selon le type d’entité.
//
// 3. 📊 MISE À JOUR DES STATISTIQUES  
//    - Incrémentation ou mise à jour des compteurs (joueurs, énigmes, chasses, revenus...).
//
// 4. 📆 CLÔTURE ET PURGE DES STATISTIQUES MENSUELLES  
//    - Sauvegarde, réinitialisation, et nettoyage automatique des statistiques mensuelles.
//
// 5. 📦 DÉCLARATION DES GROUPES ACF – MÉTADONNÉES  
//    - Définition des groupes ACF pour stocker les données statistiques selon le contexte.
//

// ==================================================
// 📊 RÉCUPÉRATION DES STATISTIQUES
// ==================================================
/**
 * 🔹 acf_add_options_page (function) → Ajouter une page d’options pour les métadonnées globales du site.
 * 🔹 recuperer_stats → Récupérer les statistiques d’une entité : énigme, chasse, utilisateur ou organisateur.
 * 🔹 recuperer_stats_enigme → Alias vers `recuperer_stats('enigme', $id)`.
 * 🔹 recuperer_stats_chasse → Alias vers `recuperer_stats('chasse', $id)`.
 * 🔹 recuperer_stats_utilisateur → Alias vers `recuperer_stats('user', $id)`.
 * 🔹 recuperer_stats_organisateur → Alias vers `recuperer_stats('organisateur', $id)`.
 */

/**
 * 📌 Ajout d'une page d'options pour les métadonnées globales du site.
 * 
 * 🔹 Cette page permet aux administrateurs d'afficher et modifier les valeurs globales
 * des statistiques et récompenses liées aux chasses et énigmes.
 * 
 * 🔹 Accessible uniquement aux administrateurs depuis l'admin WordPress.
 * 
 * 📌 Où la trouver ? 
 * Elle apparaît dans l'admin sous le menu "Réglages" ou directement dans le menu principal WP.
 * 
 * 📌 À quoi ça sert ?
 * - Afficher/modifier les métadonnées globales du site.
 * - Gérer les statistiques globales (points, revenus, paiements, etc.).
 * - Remplacer l'utilisation de `get_field('...', 'option')` par un accès plus visuel.
 */

add_action('init', function() {
    if (function_exists('acf_add_options_page')) {
        acf_add_options_page([
            'page_title'  => 'Réglages des Méta Globales',
            'menu_title'  => 'Méta Globales',
            'menu_slug'   => 'acf-options-metas-globales',
            'capability'  => 'manage_options',
            'redirect'    => false
        ]);
    }
});


/**
 * 📊 Récupérer les statistiques d’une entité (énigme, chasse, utilisateur ou organisateur).
 *
 * Cette fonction unifie les méthodes de récupération des statistiques tout en conservant
 * la logique spécifique de chaque entité (post_meta, user_meta, clés dynamiques...).
 *
 * 🔍 En cas d'erreur (ID manquant, type inconnu, JSON invalide), un `error_log()` est déclenché.
 *
 * @param string $type Type de l'entité : 'enigme', 'chasse', 'user', 'organisateur'.
 * @param int    $id   ID de l’entité ciblée.
 * @param array  $stats (optionnel) Clés spécifiques à récupérer. Si vide, tout est récupéré.
 * @return array|false Tableau associatif des statistiques, ou false si aucune donnée (cas utilisateur uniquement).
 */
function recuperer_stats(string $type, int $id, array $stats = []) {
    if (!$id || !in_array($type, ['enigme', 'chasse', 'user', 'organisateur'], true)) {
        error_log("❌ [recuperer_stats] Paramètres invalides : type={$type}, id={$id}");
        return false;
    }

    switch ($type) {

        case 'enigme':
            $meta_keys = [
                "total_tentatives_enigme_{$id}",
                "total_indices_debloques_enigme_{$id}",
                "total_points_depenses_enigme_{$id}",
                "total_joueurs_ayant_resolu_enigme_{$id}",
                "total_joueurs_souscription_enigme_{$id}"
            ];
            break;

        case 'chasse':
            $meta_keys = [
                "total_tentatives_chasse_{$id}",
                "total_indices_debloques_chasse_{$id}",
                "total_points_depenses_chasse_{$id}",
                "progression_chasse"
            ];
            break;

        case 'user':
            $meta_keys = [
                'total_enigmes_jouees',
                'total_enigmes_trouvees',
                'total_chasses_terminees',
                'total_indices_debloques',
                'total_points_depenses'
            ];
            break;

        case 'organisateur':
            $meta_keys = ['total_points_percus_organisateur'];
            break;
    }

    if (empty($stats)) {
        $stats = $meta_keys;
    }

    $result = [];
    $all_zero = true;

    foreach ($meta_keys as $meta_key) {
        if (!in_array($meta_key, $stats, true)) {
            continue;
        }

        if ($type === 'user' || $type === 'organisateur') {
            $value = get_user_meta($id, $meta_key, true);
        } else {
            $value = get_post_meta($id, $meta_key, true);
        }

        // Cas spécial JSON pour progression_chasse
        if ($meta_key === 'progression_chasse') {
            $decoded = is_string($value) ? json_decode($value, true) : [];
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("❌ [recuperer_stats] JSON invalide pour progression_chasse (ID {$id}) : " . json_last_error_msg());
                $value = [];
            } else {
                $value = $decoded;
            }
        }

        // Traitement des valeurs
        $result[$meta_key] = is_array($value) ? $value : intval($value);

        if ($result[$meta_key] !== 0 && $meta_key !== 'progression_chasse') {
            $all_zero = false;
        }
    }

    // Pour les utilisateurs, retourne false si toutes les stats sont nulles
    if ($type === 'user' && $all_zero) {
        return false;
    }

    return $result;
}
function recuperer_stats_enigme($enigme_id, $stats = []) {
    return recuperer_stats('enigme', $enigme_id, $stats);
}

function recuperer_stats_chasse($chasse_id, $stats = []) {
    return recuperer_stats('chasse', $chasse_id, $stats);
}

function recuperer_stats_utilisateur($user_id, $stats = []) {
    return recuperer_stats('user', $user_id, $stats);
}

function recuperer_stats_organisateur($organisateur_id) {
    return recuperer_stats('organisateur', $organisateur_id);
}



// ==================================================
// 📊 AFFICHAGE DES STATISTIQUES
// ==================================================
/**
 * 🔹 afficher_stats_enigme → Afficher les statistiques d’une énigme sous forme de liste.
 * 🔹 afficher_stats_chasse → Afficher les statistiques d’une chasse, incluant la progression du joueur.
 * 🔹 afficher_stats_utilisateur → Afficher les statistiques d’un utilisateur sous forme de tableau.
 * 🔹 afficher_stats_organisateur → Afficher les statistiques d’un organisateur sous forme de liste.
 */

/**
 * 🏆 Afficher les statistiques d’une énigme.
 *
 * @param int   $enigme_id ID de l’énigme.
 * @param array $stats     (optionnel) Liste de clés à afficher. Si vide, affiche tout.
 * @return void
 */
function afficher_stats_enigme($enigme_id, $stats = []) {
    $stats_enigme = recuperer_stats('enigme', $enigme_id, $stats);
    if (empty($stats_enigme)) return;

    echo '<div class="statistiques-enigme">';
    echo '<h3>📊 STATISTIQUES DE L\'ÉNIGME</h3>';
    echo '<ul>';
    foreach ($stats_enigme as $key => $value) {
        echo '<li>🔹 ' . ucfirst(str_replace('_', ' ', $key)) . ' : ' . esc_html($value) . '</li>';
    }
    echo '</ul></div>';
}


/**
 * 🏆 Afficher les statistiques d’une chasse.
 *
 * @param int   $chasse_id ID de la chasse.
 * @param array $stats     (optionnel) Liste de clés à afficher. Si vide, affiche tout.
 * @return void
 */
function afficher_stats_chasse($chasse_id, $stats = []) {
    $stats_chasse = recuperer_stats('chasse', $chasse_id, $stats);
    if (empty($stats_chasse)) return;

    $current_user_id = get_current_user_id();

    echo '<div class="statistiques-chasse">';
    echo '<h3>📊 STATISTIQUES DE LA CHASSE</h3>';
    echo '<ul>';

    foreach ($stats_chasse as $key => $value) {
        if ($key === 'progression_chasse' && is_array($value) && isset($value[$current_user_id])) {
            echo '<li>🔹 Progression chasse : <strong>' . intval($value[$current_user_id]) . '</strong> énigmes résolues</li>';
        } elseif ($key !== 'progression_chasse') {
            echo '<li>🔹 ' . ucfirst(str_replace('_', ' ', $key)) . ' : <strong>' . esc_html($value) . '</strong></li>';
        }
    }

    echo '</ul></div>';
}

/**
 * 🧍 Afficher les statistiques d’un utilisateur sous forme de tableau.
 *
 * @param int   $user_id ID de l’utilisateur.
 * @param array $stats   (optionnel) Liste des statistiques spécifiques à récupérer.
 * @return void
 */
function afficher_stats_utilisateur($user_id, $stats = []) {
    // Liste complète des statistiques avec leurs libellés lisibles
    $meta_keys = [
        'total_enigmes_jouees'    => 'Énigmes jouées',
        'total_enigmes_trouvees'  => 'Énigmes trouvées',
        'total_chasses_terminees' => 'Chasses terminées',
        'total_indices_debloques' => 'Indices débloqués',
        'total_points_depenses'   => 'Points dépensés'
    ];

    $stats_utilisateur = recuperer_stats('user', $user_id, array_keys($meta_keys));

    echo '<div class="statistiques-joueur">';
    echo '<table class="stats-table">';

    foreach ($meta_keys as $key => $label) {
        $value = ($stats_utilisateur !== false && isset($stats_utilisateur[$key])) ? $stats_utilisateur[$key] : 0;
        echo '<tr>';
        echo '<td class="stats-label">' . esc_html($label) . '</td>';
        echo '<td class="stats-value">' . esc_html($value) . '</td>';
        echo '</tr>';
    }

    echo '</table>';
    echo '</div>';
}

/**
 * 🧑‍💼 Afficher les statistiques d’un organisateur.
 *
 * @param int   $organisateur_id ID de l’organisateur.
 * @param array $stats           (optionnel) Liste des statistiques spécifiques à récupérer.
 * @return void
 */
function afficher_stats_organisateur($organisateur_id, $stats = []) {
    $stats_organisateur = recuperer_stats('organisateur', $organisateur_id, $stats);
    if (empty($stats_organisateur)) return;

    echo '<div class="organisateur-stats">';
    echo '<ul>';
    foreach ($stats_organisateur as $key => $value) {
        echo '<li>🔹 ' . ucfirst(str_replace('_', ' ', $key)) . ' : ' . esc_html($value) . '</li>';
    }
    echo '</ul></div>';
}


// ==================================================
// 📊 MISE À JOUR DES STATISTIQUES
// ==================================================
/**
 * 🔹 incrementer_tentatives_enigme → Incrémenter le nombre total de tentatives pour une énigme.
 * 🔹 incrementer_indices_debloques_enigme → Incrémenter le nombre d’indices débloqués pour une énigme.
 * 🔹 incrementer_points_depenses_enigme → Incrémenter les points dépensés pour une énigme.
 * 🔹 incrementer_joueurs_ayant_resolu_enigme → Incrémenter le nombre de joueurs ayant résolu une énigme.
 * 🔹 incrementer_joueurs_souscription_enigme → Incrémenter le nombre de joueurs ayant souscrit à une énigme.
 * 🔹 incrementer_tentatives_chasse → Incrémenter le nombre de tentatives sur une chasse.
 * 🔹 incrementer_indices_debloques_chasse → Incrémenter le nombre d’indices débloqués pour une chasse.
 * 🔹 incrementer_points_depenses_chasse → Incrémenter les points dépensés pour une chasse.
 * 🔹 incrementer_souscriptions_chasse → Incrémenter le nombre de joueurs ayant souscrit à une chasse.
 * 🔹 mettre_a_jour_progression_chasse → Mettre à jour la progression d’un joueur dans une chasse.
 * 🔹 maj_total_enigmes_trouvees → Mettre à jour la liste des énigmes trouvées par un utilisateur.
 * 🔹 maj_total_enigmes_jouees → Mettre à jour la liste des énigmes jouées par un utilisateur.
 * 🔹 incrementer_total_indices_debloques_utilisateur → Incrémenter le total des indices débloqués par un utilisateur.
 * 🔹 incrementer_total_points_depenses_utilisateur → Incrémenter le total des points dépensés par un utilisateur.
 * 🔹 incrementer_total_chasses_terminees_utilisateur → Incrémenter le total des chasses terminées par un utilisateur.
 * 🔹 incrementer_points_percus_organisateur → Incrémenter le total des points perçus par un organisateur.
 * 🔹 mettre_a_jour_points_depenses → Mettre à jour le total de points dépensés pour le mois en cours.
 * 🔹 mettre_a_jour_points_achetes → Mettre à jour le total de points achetés pour le mois en cours.
 * 🔹 mettre_a_jour_paiements_organisateurs → Mettre à jour le total des paiements effectués aux organisateurs pour le mois en cours.
 * 🔹 get_revenu_total_organisateur → Récupérer le revenu total en euros d’un organisateur.
 * 🔹 update_revenu_total_organisateur → Mettre à jour le revenu total d’un organisateur après conversion.
 * 🔹 ajouter_revenu_lors_creditation_points → Mettre à jour automatiquement le revenu lors d’un crédit de points.
 */

/**
 * Incrémente le compteur de tentatives pour une énigme.
 *
 * @param int $user_id ID de l'utilisateur
 * @param int $enigme_id ID de l'énigme
 */
function incrementer_tentatives_enigme($user_id, $enigme_id) {
    if (!$user_id || !$enigme_id) {
        error_log("🚨 ERREUR : ID utilisateur ou ID énigme manquant");
        return;
    }

    // Vérification et log
    error_log("🟢 Tentative détectée pour l'énigme ID: " . $enigme_id . " par l'utilisateur ID: " . $user_id);

    // Récupérer la clé et la valeur actuelle
    $cle_meta = 'total_tentatives_enigme_' . $enigme_id;
    $tentatives = get_post_meta($enigme_id, $cle_meta, true);

    // Suppression forcée pour éviter un problème de cache ou de méta corrompue
    delete_post_meta($enigme_id, $cle_meta);

    // Si aucune valeur n'existe, initialiser à 0
    if ($tentatives === '' || $tentatives === false) {
        error_log("🔍 Aucune méta trouvée, initialisation à 0 : " . $cle_meta);
        $tentatives = 0;
    }

    // Incrémentation et mise à jour forcée
    $tentatives++;
    update_post_meta($enigme_id, $cle_meta, $tentatives);

    // Vérification après mise à jour
    $tentatives_apres = get_post_meta($enigme_id, $cle_meta, true);
    error_log("✅ Nouvelle valeur forcée de $cle_meta : " . $tentatives_apres);
}
/**
 * Forcer ACF à récupérer la vraie valeur du champ total_tentatives_enigme.
 */
function acf_total_tentatives_enigme_value($value, $post_id, $field) {
    // Vérifier que l'on est bien sur une énigme
    if (get_post_type($post_id) !== 'enigme') {
        return $value;
    }

    // Récupérer la valeur stockée avec l'ID
    $meta_key = 'total_tentatives_enigme_' . $post_id;
    $tentatives = get_post_meta($post_id, $meta_key, true);

    // Si une valeur existe, la retourner, sinon garder celle d'ACF
    return (!empty($tentatives)) ? $tentatives : $value;
}
add_filter('acf/load_value/name=total_tentatives_enigme', 'acf_total_tentatives_enigme_value', 10, 3);

/**
 * Incrémente le compteur d'indices débloqués pour une énigme.
 *
 * @param int $user_id ID de l'utilisateur
 * @param int $enigme_id ID de l'énigme
 */
function incrementer_indices_debloques_enigme($user_id, $enigme_id) {
    if (!$user_id || !$enigme_id) {
        error_log("🚨 ERREUR : ID utilisateur ou ID énigme manquant");
        return;
    }

    // Log de la détection du déblocage
    error_log("🟢 Indice débloqué pour l'énigme ID: " . $enigme_id . " par l'utilisateur ID: " . $user_id);

    // Définition de la clé de méta
    $meta_key = 'total_indices_debloques_enigme_' . $enigme_id;
    $indices = get_post_meta($enigme_id, $meta_key, true);

    // Suppression pour éviter tout problème de cache ou d'absence initiale
    delete_post_meta($enigme_id, $meta_key);

    // Initialisation à 0 si inexistant
    if ($indices === '' || $indices === false) {
        error_log("🔍 Aucune méta trouvée, initialisation à 0 : " . $meta_key);
        $indices = 0;
    }

    // Incrémentation et mise à jour forcée
    $indices++;
    update_post_meta($enigme_id, $meta_key, $indices);

    // Vérification après mise à jour
    $indices_apres = get_post_meta($enigme_id, $meta_key, true);
    error_log("✅ Nouvelle valeur forcée de $meta_key : " . $indices_apres);
}
/**
 * Forcer ACF à récupérer la vraie valeur du champ total_indices_debloques_enigme.
 */
function acf_total_indices_debloques_enigme_value($value, $post_id, $field) {
    if (get_post_type($post_id) !== 'enigme') {
        return $value;
    }

    $meta_key = 'total_indices_debloques_enigme_' . $post_id;
    $indices = get_post_meta($post_id, $meta_key, true);

    return (!empty($indices)) ? $indices : $value;
}
add_filter('acf/load_value/name=total_indices_debloques_enigme', 'acf_total_indices_debloques_enigme_value', 10, 3);
/**
 * Incrémente le total des points dépensés pour débloquer des indices sur une énigme.
 *
 * @param int $enigme_id ID de l'énigme
 * @param int $points Montant des points dépensés
 */
function incrementer_points_depenses_enigme($enigme_id, $points) {
    if (!$enigme_id || $points <= 0) {
        error_log("🚨 ERREUR : ID énigme manquant ou montant invalide ({$points})");
        return;
    }

    // Log de la transaction
    error_log("💰 Dépense de {$points} points pour débloquer un indice sur l'énigme ID: " . $enigme_id);

    // Définition de la clé de méta
    $meta_key = 'total_points_depenses_enigme_' . $enigme_id;
    $total_depenses = get_post_meta($enigme_id, $meta_key, true);

    // Suppression de la méta pour éviter les problèmes de cache ou de valeur inexistante
    delete_post_meta($enigme_id, $meta_key);

    // Initialisation si inexistant
    if ($total_depenses === '' || $total_depenses === false) {
        error_log("🔍 Aucune méta trouvée, initialisation à 0 : " . $meta_key);
        $total_depenses = 0;
    }

    // Incrémentation et mise à jour
    $total_depenses += $points;
    update_post_meta($enigme_id, $meta_key, $total_depenses);

    // Vérification après mise à jour
    $total_apres = get_post_meta($enigme_id, $meta_key, true);
    error_log("✅ Nouvelle valeur forcée de $meta_key : " . $total_apres);
}
/**
 * Forcer ACF à récupérer la vraie valeur du champ total_points_depenses_enigme.
 */
function acf_total_points_depenses_enigme_value($value, $post_id, $field) {
    if (get_post_type($post_id) !== 'enigme') {
        return $value;
    }

    $meta_key = 'total_points_depenses_enigme_' . $post_id;
    $points = get_post_meta($post_id, $meta_key, true);

    return (!empty($points)) ? $points : $value;
}
add_filter('acf/load_value/name=total_points_depenses_enigme', 'acf_total_points_depenses_enigme_value', 10, 3);

/**
 * Incrémente le nombre de joueurs distincts ayant trouvé la solution d'une énigme.
 *
 * @param int $user_id ID de l'utilisateur
 * @param int $enigme_id ID de l'énigme
 */
function incrementer_joueurs_ayant_resolu_enigme($user_id, $enigme_id) {
    if (!$user_id || !$enigme_id) {
        error_log("🚨 ERREUR : ID utilisateur ou énigme manquant");
        return;
    }

    // Vérifie si l'utilisateur a déjà résolu cette énigme
    $deja_resolu = get_user_meta($user_id, "enigme_{$enigme_id}_resolue", true);
    
    if ($deja_resolu) {
        error_log("⚠️ L'utilisateur ID {$user_id} a déjà résolu l'énigme ID {$enigme_id}. Pas d'incrémentation.");
        return;
    }

    // Marquer l'énigme comme résolue pour cet utilisateur
    update_user_meta($user_id, "enigme_{$enigme_id}_resolue", true);

    // Incrémenter le compteur global de joueurs ayant résolu cette énigme
    $meta_key = 'total_joueurs_ayant_resolu_enigme_' . $enigme_id;
    $total_joueurs = get_post_meta($enigme_id, $meta_key, true);

    delete_post_meta($enigme_id, $meta_key); // Suppression pour éviter un cache persistant

    if ($total_joueurs === '' || $total_joueurs === false) {
        error_log("🔍 Aucune méta trouvée, initialisation à 0 : " . $meta_key);
        $total_joueurs = 0;
    }

    $total_joueurs++;
    update_post_meta($enigme_id, $meta_key, $total_joueurs);

    error_log("✅ Nouvelle valeur de $meta_key : " . $total_joueurs);
}
/**
 * Forcer ACF à récupérer la vraie valeur du champ total_joueurs_ayant_resolu_enigme.
 */
function acf_total_joueurs_ayant_resolu_enigme_value($value, $post_id, $field) {
    if (get_post_type($post_id) !== 'enigme') {
        return $value;
    }

    $meta_key = 'total_joueurs_ayant_resolu_enigme_' . $post_id;
    $total_joueurs = get_post_meta($post_id, $meta_key, true);

    return (!empty($total_joueurs)) ? $total_joueurs : $value;
}
add_filter('acf/load_value/name=total_joueurs_ayant_resolu_enigme', 'acf_total_joueurs_ayant_resolu_enigme_value', 10, 3);
/**
 * Incrémente le nombre de joueurs distincts ayant souscrit à une énigme.
 *
 * @param int $user_id ID de l'utilisateur
 * @param int $enigme_id ID de l'énigme
 */
function incrementer_joueurs_souscription_enigme($user_id, $enigme_id) {
    if (!$user_id || !$enigme_id) {
        error_log("🚨 ERREUR : ID utilisateur ou énigme manquant");
        return;
    }

    // Vérifie si l'utilisateur a déjà souscrit à cette énigme
    $deja_souscrit = get_user_meta($user_id, "enigme_{$enigme_id}_souscrit", true);
    
    if ($deja_souscrit) {
        error_log("⚠️ L'utilisateur ID {$user_id} a déjà souscrit à l'énigme ID {$enigme_id}. Pas d'incrémentation.");
        return;
    }

    // Marquer l'énigme comme souscrite pour cet utilisateur
    update_user_meta($user_id, "enigme_{$enigme_id}_souscrit", true);

    // Incrémenter le compteur global de joueurs ayant souscrit à cette énigme
    $meta_key = 'total_joueurs_souscription_enigme_' . $enigme_id;
    $total_souscriptions = get_post_meta($enigme_id, $meta_key, true);

    delete_post_meta($enigme_id, $meta_key); // Suppression pour éviter un cache persistant

    if ($total_souscriptions === '' || $total_souscriptions === false) {
        error_log("🔍 Aucune méta trouvée, initialisation à 0 : " . $meta_key);
        $total_souscriptions = 0;
    }

    $total_souscriptions++;
    update_post_meta($enigme_id, $meta_key, $total_souscriptions);

    error_log("✅ Nouvelle valeur de $meta_key : " . $total_souscriptions);
}
/**
 * Forcer ACF à récupérer la vraie valeur du champ total_joueurs_souscription_enigme.
 */
function acf_total_joueurs_souscription_enigme_value($value, $post_id, $field) {
    if (get_post_type($post_id) !== 'enigme') {
        return $value;
    }

    $meta_key = 'total_joueurs_souscription_enigme_' . $post_id;
    $total_souscriptions = get_post_meta($post_id, $meta_key, true);

    return (!empty($total_souscriptions)) ? $total_souscriptions : $value;
}
add_filter('acf/load_value/name=total_joueurs_souscription_enigme', 'acf_total_joueurs_souscription_enigme_value', 10, 3);



/**
 * Incrémente le nombre total de tentatives sur une chasse.
 *
 * @param int $chasse_id ID de la chasse
 */
function incrementer_tentatives_chasse($chasse_id) {
    if (!$chasse_id) {
        error_log("🚨 ERREUR : ID de la chasse manquant pour l'incrémentation des tentatives.");
        return;
    }

    $meta_key = "total_tentatives_chasse_{$chasse_id}";
    $total_tentatives = get_post_meta($chasse_id, $meta_key, true) ?: 0;
    
    error_log("🔍 Vérification avant mise à jour - Chasse ID : {$chasse_id}, Tentatives actuelles : {$total_tentatives}");
    
    update_post_meta($chasse_id, $meta_key, $total_tentatives + 1);

    error_log("✅ Tentative enregistrée pour la chasse ID {$chasse_id}. Nouveau total : " . ($total_tentatives + 1));
}

/**
 * Forcer ACF à récupérer la vraie valeur du champ total_tentatives_chasse.
 */
function acf_total_tentatives_chasse_value($value, $post_id, $field) {
    if (get_post_type($post_id) !== 'chasse') {
        return $value;
    }

    $meta_key = 'total_tentatives_chasse_' . $post_id;
    $total_tentatives = get_post_meta($post_id, $meta_key, true);

    return (!empty($total_tentatives)) ? $total_tentatives : $value;
}
add_filter('acf/load_value/name=total_tentatives_chasse', 'acf_total_tentatives_chasse_value', 10, 3);
/**
 * Incrémente le nombre total d’indices débloqués pour une chasse.
 *
 * @param int $chasse_id ID de la chasse concernée.
 */
function incrementer_indices_debloques_chasse($chasse_id) {
    if (!$chasse_id) {
        error_log("🚨 ERREUR : ID de la chasse manquant pour l'incrémentation des indices.");
        return;
    }

    $meta_key = "total_indices_debloques_chasse_{$chasse_id}";
    $total_indices = get_post_meta($chasse_id, $meta_key, true) ?: 0;
    update_post_meta($chasse_id, $meta_key, $total_indices + 1);

    error_log("✅ Indice débloqué enregistré pour la chasse ID {$chasse_id}. Nouveau total : " . ($total_indices + 1));
}
/**
 * Forcer ACF à récupérer la vraie valeur du champ total_indices_debloques_chasse.
 */
function acf_total_indices_debloques_chasse_value($value, $post_id, $field) {
    if (get_post_type($post_id) !== 'chasse') {
        return $value;
    }

    $meta_key = 'total_indices_debloques_chasse_' . $post_id;
    $total_indices = get_post_meta($post_id, $meta_key, true);

    return (!empty($total_indices)) ? $total_indices : $value;
}
add_filter('acf/load_value/name=total_indices_debloques_chasse', 'acf_total_indices_debloques_chasse_value', 10, 3);
/**
 * Incrémente le nombre total de points dépensés pour une chasse.
 *
 * @param int $chasse_id ID de la chasse concernée.
 * @param int $montant Nombre de points dépensés.
 */
function incrementer_points_depenses_chasse($chasse_id, $montant) {
    if (!$chasse_id || $montant <= 0) {
        error_log("🚨 ERREUR : ID de la chasse invalide ou montant incorrect pour l'incrémentation des points dépensés.");
        return;
    }

    $meta_key = "total_points_depenses_chasse_{$chasse_id}";
    $total_points = get_post_meta($chasse_id, $meta_key, true) ?: 0;
    update_post_meta($chasse_id, $meta_key, $total_points + $montant);

    error_log("✅ Points dépensés mis à jour pour la chasse ID {$chasse_id}. Nouveau total : " . ($total_points + $montant));
}
/**
 * Forcer ACF à récupérer la vraie valeur du champ total_points_depenses_chasse.
 */
function acf_total_points_depenses_chasse_value($value, $post_id, $field) {
    if (get_post_type($post_id) !== 'chasse') {
        return $value;
    }

    $meta_key = 'total_points_depenses_chasse_' . $post_id;
    $total_points = get_post_meta($post_id, $meta_key, true);

    return (!empty($total_points)) ? $total_points : $value;
}
add_filter('acf/load_value/name=total_points_depenses_chasse', 'acf_total_points_depenses_chasse_value', 10, 3);

/**
 * Met à jour la progression des joueurs dans une chasse en fonction des énigmes résolues.
 *
 * @param int $chasse_id ID de la chasse
 * @param int $user_id ID de l'utilisateur
 */
function mettre_a_jour_progression_chasse($chasse_id, $user_id) {
    if (!$chasse_id || !$user_id) {
        error_log("🚨 ERREUR : ID chasse ou utilisateur manquant.");
        return;
    }
    error_log("🔍 Appel de mettre_a_jour_progression_chasse() - Chasse ID : {$chasse_id}, Utilisateur ID : {$user_id}");

    // 🔍 Récupération et désérialisation de la progression actuelle
    $progression_json = get_post_meta($chasse_id, 'progression_chasse', true);
    $progression = !empty($progression_json) ? json_decode($progression_json, true) : [];

    if (!is_array($progression)) {
        $progression = []; // Sécurisation si la donnée récupérée est corrompue
    }

    // 🔢 Calcul du nombre d’énigmes résolues par cet utilisateur
    $nombre_resolues = compter_enigmes_resolues($chasse_id, $user_id);

    // 📝 Mise à jour de la progression
    $progression[$user_id] = $nombre_resolues;
    
    // 🔍 Vérification avant mise à jour
    error_log("📄 Données actuelles de progression_chasse AVANT mise à jour : " . print_r($progression, true));

    // 🔄 Sauvegarde en format JSON
    update_post_meta($chasse_id, 'progression_chasse', json_encode($progression));
    error_log("🔎 Nouvelle valeur de progression_chasse : " . get_post_meta($chasse_id, 'progression_chasse', true));


    error_log("✅ Progression mise à jour pour l'utilisateur {$user_id} dans la chasse {$chasse_id} : {$nombre_resolues} énigmes résolues.");
}

/**
 * Forcer ACF à récupérer la progression des joueurs dans la chasse.
 */
function acf_progression_chasse_value($value, $post_id, $field) {
    if (get_post_type($post_id) !== 'chasse') {
        return $value;
    }

    $meta_key = 'progression_chasse';
    $progression = get_post_meta($post_id, $meta_key, true);

    return (!empty($progression)) ? $progression : [];
}
add_filter('acf/load_value/name=progression_chasse', 'acf_progression_chasse_value', 10, 3);
/**
 * Met à jour le nombre total de joueurs ayant souscrit à une chasse.
 *
 * @param int $user_id ID de l'utilisateur.
 * @param int $enigme_id ID de l'énigme souscrite.
 */
function incrementer_souscriptions_chasse($user_id, $enigme_id) {
    if (!$user_id || !$enigme_id) {
        error_log("🚨 ERREUR : ID utilisateur ou énigme manquant.");
        return;
    }
    error_log("🟠 Appel de incrementer_souscriptions_chasse() - Utilisateur ID: {$user_id}, Énigme ID: {$enigme_id}");

    // 🏴‍☠️ Récupération de la chasse associée à l’énigme
    // ✅ Utilisation de la méthode correcte pour récupérer la chasse associée
    $chasse = recuperer_chasse_associee($enigme_id);
    $chasse_id = $chasse ? $chasse->ID : null;

    if (!$chasse_id) {
        error_log("⚠️ Aucune chasse valide trouvée pour l'énigme ID {$enigme_id}");
        return;
    }

    // 🔍 Vérification si l'utilisateur a déjà souscrit à une énigme de cette chasse
    $a_deja_souscrit = get_user_meta($user_id, "souscription_chasse_{$chasse_id}", true);

    if (!$a_deja_souscrit) {
        // ✅ Première souscription à cette chasse
        update_user_meta($user_id, "souscription_chasse_{$chasse_id}", true);
        
        // 🔄 Mise à jour du compteur global de souscriptions à la chasse
        $meta_key = "total_joueurs_souscription_chasse";
        $total_souscriptions = intval(get_post_meta($chasse_id, $meta_key, true)) ?: 0;
        update_post_meta($chasse_id, $meta_key, $total_souscriptions + 1);

        error_log("✅ Nouvelle souscription à la chasse ID {$chasse_id} par l'utilisateur ID {$user_id}. Total : " . ($total_souscriptions + 1));
    } else {
        error_log("🔄 L'utilisateur ID {$user_id} avait déjà souscrit à la chasse ID {$chasse_id}. Pas de mise à jour.");
    }
}
function acf_total_joueurs_souscription_chasse($value, $post_id, $field) {
    if (get_post_type($post_id) !== 'chasse') {
        return $value;
    }

    return get_post_meta($post_id, 'total_joueurs_souscription_chasse', true) ?: 0;
}
add_filter('acf/load_value/name=total_joueurs_souscription_chasse', 'acf_total_joueurs_souscription_chasse', 10, 3);




/**
 * Met à jour le nombre total d'énigmes trouvées par un joueur.
 *
 * @param int $user_id ID de l'utilisateur.
 * @param int $enigme_id ID de l'énigme trouvée.
 */
function maj_total_enigmes_trouvees($user_id, $enigme_id) {
    if (!$user_id || !$enigme_id) {
        return;
    }

    // 🔍 Log pour vérifier si cette fonction est bien appelée
    error_log("🔄 maj_total_enigmes_trouvees() exécutée pour user $user_id et énigme $enigme_id");

    // Récupération et initialisation si vide
    $trouvees = get_user_meta($user_id, 'total_enigmes_trouvees', true);
    if (!is_array($trouvees)) {
        $trouvees = [];
    }

    if (!in_array($enigme_id, $trouvees)) {
        $trouvees[] = $enigme_id;
        update_user_meta($user_id, 'total_enigmes_trouvees', $trouvees);
    }
}

/**
 * Récupère le nombre total d'énigmes trouvées par un utilisateur.
 *
 * @param int $user_id ID de l'utilisateur.
 * @return int Nombre total d'énigmes trouvées.
 */
function get_total_enigmes_trouvees($user_id) {
    error_log("🔄 Fonction get_total_enigmes_trouvees() exécutée pour user $user_id");
    $trouvees = get_user_meta($user_id, 'total_enigmes_trouvees', true);
    
    // 🔍 Log pour voir la valeur brute récupérée
    error_log("🔍 Contenu brut récupéré par get_user_meta pour user $user_id : " . print_r($trouvees, true));

    // 🔄 Forcer la désérialisation si nécessaire
    $trouvees = maybe_unserialize($trouvees);

    // 🔍 Log après désérialisation
    error_log("✅ Contenu après désérialisation : " . print_r($trouvees, true));

    // 🏗️ Vérifier si c'est bien un tableau et retourner le nombre d'énigmes trouvées
    return is_array($trouvees) ? count($trouvees) : 0;
}

/**
 * 🔄 Met à jour le compteur des énigmes jouées par l'utilisateur lors de la souscription.
 *
 * @param int $user_id L'ID de l'utilisateur.
 * @param int $enigme_id L'ID de l'énigme souscrite.
 */
function maj_total_enigmes_jouees($user_id, $enigme_id) {
    if (!$user_id || !$enigme_id) {
        return;
    }

    // Récupération et initialisation si vide
    $jouees = get_user_meta($user_id, 'total_enigmes_jouees', true);
    if (!is_array($jouees)) {
        $jouees = [];
    }

    if (!in_array($enigme_id, $jouees)) {
        $jouees[] = $enigme_id;
        update_user_meta($user_id, 'total_enigmes_jouees', $jouees);
    }
}
/**
 * 🔄 Incrémente le nombre total d'indices débloqués pour un utilisateur.
 *
 * @param int $user_id ID de l'utilisateur.
 */
function incrementer_total_indices_debloques_utilisateur($user_id) {
    if (!$user_id) return;

    // Récupération de la valeur actuelle
    $total_indices = get_user_meta($user_id, 'total_indices_debloques', true);
    $total_indices = (!empty($total_indices) && is_numeric($total_indices)) ? intval($total_indices) : 0;

    // Incrémentation
    $total_indices++;

    // Mise à jour de la méta
    update_user_meta($user_id, 'total_indices_debloques', $total_indices);
    error_log("✅ Mise à jour du total d'indices débloqués pour l'utilisateur {$user_id} : {$total_indices}");
}
/**
 * 🔄 Incrémente le total des points dépensés par un utilisateur.
 *
 * @param int $user_id ID de l'utilisateur.
 * @param int $montant Nombre de points dépensés.
 */
function incrementer_total_points_depenses_utilisateur($user_id, $montant) {
    if (!$user_id || $montant <= 0) return;

    // Récupération de la valeur actuelle
    $total_depenses = get_user_meta($user_id, 'total_points_depenses', true);
    $total_depenses = (!empty($total_depenses) && is_numeric($total_depenses)) ? intval($total_depenses) : 0;

    // Incrémentation
    $total_depenses += $montant;

    // Mise à jour de la méta
    update_user_meta($user_id, 'total_points_depenses', $total_depenses);
    error_log("✅ Mise à jour du total des points dépensés pour l'utilisateur {$user_id} : {$total_depenses} points");
}

/**
 * 📌 Incrémente le nombre total de chasses terminées par un utilisateur.
 *
 * @param int $user_id ID de l'utilisateur.
 */
function incrementer_total_chasses_terminees_utilisateur($user_id) {
    if (!$user_id) {
        error_log("🚨 ERREUR : ID utilisateur invalide pour l'incrémentation des chasses terminées.");
        return;
    }

    // 🔄 Récupération de la valeur actuelle
    $total_chasses = get_user_meta($user_id, 'total_chasses_terminees', true);

    // ✅ Si la valeur est vide ou non numérique, initialiser à 0
    if (!is_numeric($total_chasses)) {
        $total_chasses = 0;
    }

    // ➕ Incrémentation
    $total_chasses++;

    // 💾 Mise à jour en BDD
    update_user_meta($user_id, 'total_chasses_terminees', $total_chasses);

    error_log("✅ Mise à jour : total_chasses_terminees pour user {$user_id} = {$total_chasses}");
}

/**
 * 📌 Incrémente le total des points perçus par un organisateur.
 *
 * @param int $organisateur_id L'ID de l'organisateur.
 * @param int $points Le nombre de points à ajouter.
 */
function incrementer_points_percus_organisateur($organisateur_id, $points) {
    if (!$organisateur_id || $points <= 0) {
        error_log("⚠️ Tentative d'incrémentation invalide des points perçus par l'organisateur (ID: {$organisateur_id}, Points: {$points})");
        return;
    }

    // 🔢 Récupération du total avant mise à jour
    $ancien_total = get_user_points($organisateur_id);

    // ➕ Mise à jour du solde (Crédit immédiat)
    update_user_points($organisateur_id, $points);
    $nouveau_total = get_user_points($organisateur_id);

    // 🔍 Vérification après mise à jour
    if ($nouveau_total === ($ancien_total + $points)) {
        error_log("✅ Points mis à jour pour l'organisateur (ID: {$organisateur_id}, Ancien total: {$ancien_total}, Ajouté: {$points}, Nouveau total: {$nouveau_total})");
    } else {
        error_log("⚠️ Problème lors de la mise à jour des points (ID: {$organisateur_id}, Attendu: " . ($ancien_total + $points) . ", Actuel: {$nouveau_total})");
    }

    // 🔄 Mise à jour de la méta `total_points_percus_organisateur`
    $meta_key = 'total_points_percus_organisateur';
    $ancien_total_meta = get_user_meta($organisateur_id, $meta_key, true) ?: 0;
    $nouveau_total_meta = $ancien_total_meta + $points;

    update_user_meta($organisateur_id, $meta_key, $nouveau_total_meta);

    error_log("✅ Mise à jour de la méta total_points_percus_organisateur pour l'organisateur {$organisateur_id} : Ancien : {$ancien_total_meta}, Ajouté : {$points}, Nouveau : {$nouveau_total_meta}");
}

/**
 * 📌 Met à jour le total de points dépensés pour le mois en cours.
 *
 * @param int $points Nombre de points dépensés.
 */
function mettre_a_jour_points_depenses($points) {
    if ($points <= 0) return;

    $mois_actuel = date('Y_m');
    $total_actuel = get_option("total_points_depenses_mois_$mois_actuel", 0);
    update_option("total_points_depenses_mois_$mois_actuel", $total_actuel + $points);

    error_log("✅ Points dépensés mis à jour : $points ajoutés. Total : " . ($total_actuel + $points));
}

/**
 * 📌 Met à jour le total de points achetés pour le mois en cours.
 *
 * @param int $points Nombre de points achetés.
 */
function mettre_a_jour_points_achetes($points) {
    if ($points <= 0) return;

    $mois_actuel = date('Y_m');
    $total_actuel = get_option("total_points_vendus_mensuel_$mois_actuel", 0);
    update_option("total_points_vendus_mensuel_$mois_actuel", $total_actuel + $points);

    error_log("✅ Points achetés mis à jour : $points ajoutés. Total : " . ($total_actuel + $points));
}

/**
 * 📌 Met à jour le total des paiements effectués aux organisateurs pour le mois en cours.
 *
 * @param float $montant Montant du paiement en euros.
 */
function mettre_a_jour_paiements_organisateurs($montant) {
    if ($montant <= 0) return;

    $mois_actuel = date('Y_m');
    $total_actuel = get_option("total_paiements_effectues_mensuel_$mois_actuel", 0);
    update_option("total_paiements_effectues_mensuel_$mois_actuel", $total_actuel + $montant);

    error_log("✅ Paiement aux organisateurs mis à jour : $montant € ajoutés. Total : " . ($total_actuel + $montant) . " €");
}

/**
 * 📌 Récupère le revenu total d'un organisateur.
 *
 * @param int $organisateur_id ID de l'organisateur.
 * @return float Revenu total en euros.
 */
function get_revenu_total_organisateur($organisateur_id) {
    if (!$organisateur_id) return 0;
    
    return floatval(get_user_meta($organisateur_id, 'revenu_total_organisateur', true)) ?: 0;
}

/**
 * 📌 Met à jour le revenu total d'un organisateur.
 *
 * @param int $organisateur_id ID de l'organisateur.
 * @param int $points Nombre de points perçus.
 */
function update_revenu_total_organisateur($organisateur_id, $points) {
    if (!$organisateur_id || $points <= 0) return;

    // 🔢 Récupération du taux de conversion (ex: 1 point = 0.10€)
    $prix_du_point = get_option('prix_point_verse_organisateur', 0.10);

    // 🔢 Récupération du revenu actuel
    $revenu_actuel = get_revenu_total_organisateur($organisateur_id);

    // ➕ Calcul du nouveau revenu
    $nouveau_revenu = $revenu_actuel + ($points * $prix_du_point);

    // 🔄 Mise à jour en base de données
    update_user_meta($organisateur_id, 'revenu_total_organisateur', $nouveau_revenu);

    // 📌 Log pour debug
    error_log("✅ Revenu total mis à jour pour l'organisateur ID {$organisateur_id}. Nouveau total : {$nouveau_revenu}€");
}

/**
 * 📌 Mise à jour automatique du revenu lors de la perception de points.
 *
 * @param int $organisateur_id ID de l'organisateur.
 * @param int $points Nombre de points perçus.
 */
function ajouter_revenu_lors_creditation_points($organisateur_id, $points) {
    if (!$organisateur_id || $points <= 0) return;

    // 🔄 Mise à jour du revenu total de l'organisateur
    update_revenu_total_organisateur($organisateur_id, $points);
}
// 🔗 Lier à la fonction qui crédite les points
add_action('organisateur_points_credites', 'ajouter_revenu_lors_creditation_points', 10, 2);



// ==================================================
// 📆 CLÔTURE ET PURGE DES STATISTIQUES MENSUELLES
// ==================================================
/**
 * 🔹 cloturer_statistiques_mensuelles → Sauvegarder les statistiques mensuelles et réinitialiser les compteurs pour le nouveau mois.
 * 🔹 planifier_cloture_statistiques → Planifier la clôture automatique via un événement WordPress (wp_schedule_event).
 * 🔹 mettre_a_jour_points_en_circulation → Calculer et mettre à jour le total des points en circulation.
 * 🔹 purger_anciennes_statistiques → Supprimer les options de statistiques de plus de deux ans pour éviter l’accumulation.
 * 🔹 $_POST['verifier_points_circulation'] → Vérifier manuellement les points en circulation via formulaire admin sécurisé.
 */

/**
 * 📌 Clôture mensuelle des statistiques
 *
 * - S’exécute automatiquement chaque 1er du mois via `wp_schedule_event()`.
 * - Archive les statistiques du mois écoulé sous `YYYY_MM` dans `wp_options`.
 * - Réinitialise les compteurs pour le nouveau mois.
 * - Lance la purge des statistiques de plus de 2 ans.
 * - Met à jour `total_points_en_circulation` pour garantir la cohérence des données.
 *
 * 🔍 Processus :
 * 1️⃣ **Sauvegarde des valeurs du mois précédent** :
 *    - `total_points_depenses_mois_{YYYY_MM}`
 *    - `total_points_vendus_mensuel_{YYYY_MM}`
 *    - `revenu_total_site_mensuel_{YYYY_MM}`
 *    - `total_paiements_effectues_mensuel_{YYYY_MM}`
 *
 * 2️⃣ **Réinitialisation des compteurs pour le mois actuel**.
 *
 * 3️⃣ **Exécution de la purge des anciennes statistiques** (> 2 ans).
 *
 * 4️⃣ **Vérification et mise à jour des points en circulation**.
 *
 * 🔍 Vérification :
 * - Un log est généré après exécution (`debug.log`).
 * - Les nouvelles valeurs sont stockées correctement pour le mois en cours.
 */
function cloturer_statistiques_mensuelles() {
    $mois_precedent = date('Y_m', strtotime('last month'));
    $mois_actuel = date('Y_m');

    // 🔹 Sauvegarde des stats du mois écoulé
    $stats = [
        'total_points_depenses_mois_' . $mois_precedent => get_option('total_points_depenses_mois_' . $mois_actuel, 0),
        'total_points_vendus_mensuel_' . $mois_precedent => get_option('total_points_vendus_mensuel_' . $mois_actuel, 0),
        'revenu_total_site_mensuel_' . $mois_precedent => get_option('revenu_total_site_mensuel_' . $mois_actuel, 0),
        'total_paiements_effectues_mensuel_' . $mois_precedent => get_option('total_paiements_effectues_mensuel_' . $mois_actuel, 0),
    ];

    foreach ($stats as $key => $value) {
        update_option($key, $value);
    }

    // 🔄 Réinitialisation des compteurs pour le nouveau mois
    update_option("total_points_depenses_mois_$mois_actuel", 0);
    update_option("total_points_vendus_mensuel_$mois_actuel", 0);
    update_option("revenu_total_site_mensuel_$mois_actuel", 0);
    update_option("total_paiements_effectues_mensuel_$mois_actuel", 0);

    // 🧹 Exécuter la purge des anciennes statistiques (évite l'accumulation en BDD)
    purger_anciennes_statistiques();

    // 🔍 Vérification et correction des points en circulation
    mettre_a_jour_points_en_circulation();

    error_log("✅ Clôture mensuelle effectuée pour $mois_precedent. Stats réinitialisées pour $mois_actuel.");
}

/**
 * 📌 Planifie la clôture mensuelle des statistiques
 */
function planifier_cloture_statistiques() {
    if (!wp_next_scheduled('cloture_statistiques_event')) {
        wp_schedule_event(strtotime('first day of next month midnight'), 'monthly', 'cloture_statistiques_event');
    }
}
add_action('wp', 'planifier_cloture_statistiques');
add_action('cloture_statistiques_event', 'cloturer_statistiques_mensuelles');


/**
 * 📌 Met à jour le total des points en circulation.
 * - Calcule : (Points achetés - Points dépensés)
 * - Stocke la valeur dans `total_points_en_circulation`
 */
function mettre_a_jour_points_en_circulation() {
    $points_vendus = get_option("total_points_vendus_mensuel_" . date('Y_m'), 0);
    $points_depenses = get_option("total_points_depenses_mois_" . date('Y_m'), 0);

    $total_points = max(0, $points_vendus - $points_depenses);
    update_option('total_points_en_circulation', $total_points);

    error_log("✅ Points en circulation mis à jour : $total_points points.");
}

/**
 * 📌 Vérification manuelle des points en circulation
 *
 * - Accessible uniquement aux administrateurs depuis /administration/statistiques/
 * - Permet de recalculer `total_points_en_circulation` à tout moment.
 * - Sécurisé avec un nonce pour éviter les requêtes frauduleuses.
 * - Redirige vers la page d'administration après exécution.
 *
 * 🔍 Vérification :
 * - Les permissions sont contrôlées (`current_user_can('administrator')`).
 * - La requête POST doit inclure un nonce valide (`check_admin_referer`).
 * - Un log est ajouté pour suivre l’exécution dans `debug.log`.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verifier_points_circulation'])) {
    if (!current_user_can('administrator') || !check_admin_referer('verifier_points_circulation_action', 'verifier_points_circulation_nonce')) {
        wp_die(__('Accès non autorisé.'));
    }

    mettre_a_jour_points_en_circulation();
    error_log("✅ Vérification manuelle des points en circulation effectuée.");

    wp_redirect(add_query_arg('updated', 'points_circulation', admin_url('administration/statistiques')));
    exit;
}

/**
 * 📌 Purge les statistiques de plus de 2 ans pour éviter l'accumulation de données inutiles.
 */
function purger_anciennes_statistiques() {
    global $wpdb;
    
    $date_limite = date('Y_m', strtotime('-2 years'));

    $metas_a_supprimer = [
        "total_points_depenses_mois_",
        "total_points_vendus_mensuel_",
        "revenu_total_site_mensuel_",
        "total_paiements_effectues_mensuel_"
    ];

    foreach ($metas_a_supprimer as $meta) {
        for ($i = 24; $i <= 60; $i++) { // On purge les 2+ ans
            $mois_a_supprimer = date('Y_m', strtotime("-$i months"));
            delete_option($meta . $mois_a_supprimer);
            error_log("✅ Purge de la méta : {$meta}{$mois_a_supprimer}");
        }
    }
}


// ==================================================
// 📦 DÉCLARATION DES GROUPES ACF – MÉTADONNÉES
// ==================================================
/**
 * 🔹 Métadonnées globales → Champs d’options stockés dans la page "Méta Globales".
 * 🔹 Métadonnées organisateurs → Champs utilisateur (rôle "organisateur").
 * 🔹 Métadonnées énigmes → Champs post_meta appliqués au CPT "énigme".
 * 🔹 Métadonnées chasses → Champs post_meta appliqués au CPT "chasse".
 * 🔹 Métadonnées joueurs → Champs utilisateur accessibles à tous les rôles.
 *
 * 📌 Tous les champs sont stockés sous forme d’options, de metas utilisateur ou de metas post selon leur type,
 * et sont utilisés pour le suivi des statistiques, des conversions, et des interactions joueurs / organisateurs.
 *
 * 💡 Les groupes sont séparés par contexte (site, user, CPT) pour garder la lisibilité et la maintenance simple.
 */


/**
 * 📌 Enregistrement des champs ACF pour les Méta Globales.
 * Ces champs sont stockés sous forme d'options globales pour tout le site.
 *
 * - `prix_moyen_point_mensuel` : Prix moyen du point mis à jour chaque mois.
 * - `total_points_depenses_mois` : Total des points dépensés sur le site chaque mois.
 * - `total_points_gagnes_mois` : Total des points gagnés sur le site chaque mois.
 * - `total_points_vendus_mensuel` : Nombre total de points achetés par mois.
 * - `total_points_generes_mensuel` : Points générés par les joueurs (hors achat).
 * - `revenu_total_site` : Revenu total généré par le site.
 * - `revenu_total_site_mensuel` : Revenu généré par mois.
 * - `total_points_en_circulation` : Nombre total de points en circulation.
 * - `total_paiements_effectues_mensuel` : Total des paiements effectués aux organisateurs.
 */
add_action('acf/init', function () {
    acf_add_local_field_group(array(
        'key' => 'group_meta_globales',
        'title' => 'Métadonnées Globales du Site',
        'fields' => array(

            // 🔹 Prix moyen du point (mis à jour mensuellement)
            array(
                'key' => 'field_prix_moyen_point_mensuel',
                'label' => 'Prix moyen du point (mensuel)',
                'name' => 'prix_moyen_point_mensuel',
                'type' => 'number',
                'default_value' => 0,
            ),

            // 🔹 Nombre total de points dépensés chaque mois
            array(
                'key' => 'field_total_points_depenses_mois',
                'label' => 'Total points dépensés (mensuel)',
                'name' => 'total_points_depenses_mois',
                'type' => 'number',
                'default_value' => 0,
            ),
            // 🔹 Nombre total de points achetés chaque mois
            array(
                'key' => 'field_total_points_vendus_mensuel',
                'label' => 'Total points vendus (mensuel)',
                'name' => 'total_points_vendus_mensuel',
                'type' => 'number',
                'default_value' => 0,
            ),

            // 🔹 Nombre total de points générés par les joueurs (hors achat)
            array(
                'key' => 'field_total_points_generes_mensuel',
                'label' => 'Total points générés (mensuel)',
                'name' => 'total_points_generes_mensuel',
                'type' => 'number',
                'default_value' => 0,
            ),

            // 🔹 Revenu total généré par le site (hors paiements aux organisateurs)
            array(
                'key' => 'field_revenu_total_site',
                'label' => 'Revenu total du site',
                'name' => 'revenu_total_site',
                'type' => 'number',
                'default_value' => 0,
            ),

            // 🔹 Revenu total généré par mois
            array(
                'key' => 'field_revenu_total_site_mensuel',
                'label' => 'Revenu total du site (mensuel)',
                'name' => 'revenu_total_site_mensuel',
                'type' => 'number',
                'default_value' => 0,
            ),

            // 🔹 Nombre total de points en circulation
            array(
                'key' => 'field_total_points_en_circulation',
                'label' => 'Total points en circulation',
                'name' => 'total_points_en_circulation',
                'type' => 'number',
                'default_value' => 0,
            ),

            // 🔹 Nombre total des paiements effectués aux organisateurs
            array(
                'key' => 'field_total_paiements_effectues_mensuel',
                'label' => 'Total paiements aux organisateurs (mensuel)',
                'name' => 'total_paiements_effectues_mensuel',
                'type' => 'number',
                'default_value' => 0,
            ),

        ),
        'location' => array(
            array(
                array(
                    'param' => 'options_page',  // 🔹 Ces champs sont enregistrés en tant qu'options globales
                    'operator' => '==',
                    'value' => 'acf-options-metas-globales',
                ),
            ),
        ),
    ));
});


/**
 * 📌 Métadonnées des Organisateurs
 * 🔹 Stocke des informations financières et statistiques sur les organisateurs.
 * 🔹 Les champs sont accessibles uniquement aux administrateurs.
 */
add_action('acf/init', function () {
    acf_add_local_field_group(array(
        'key' => 'group_organisateur_metadonnees',
        'title' => 'Métadonnées des Organisateurs',
        'fields' => array(
            array(
                'key' => 'field_total_points_percus_organisateur',
                'label' => 'Total points perçus',
                'name' => 'total_points_percus_organisateur',
                'type' => 'number',
                'default_value' => 0,
            ),
            array(
                'key' => 'field_revenu_total_organisateur',
                'label' => 'Revenu total',
                'name' => 'revenu_total_organisateur',
                'type' => 'number',
                'default_value' => 0,
            ),
            array(
                'key' => 'field_historique_paiements',
                'label' => 'Historique des paiements',
                'name' => 'historique_paiements',
                'type' => 'repeater',
                'layout' => 'table',
                'button_label' => 'Ajouter un paiement',
                'sub_fields' => array(
                    array(
                        'key' => 'field_paiement_statut',
                        'label' => 'Statut du paiement',
                        'name' => 'paiement_statut',
                        'type' => 'select',
                        'choices' => array(
                            'attente' => 'En attente',
                            'valide' => 'Validé',
                            'rejete' => 'Rejeté'
                        ),
                        'default_value' => 'attente',
                        'instructions' => 'Sélectionnez le statut du paiement.',
                    ),
                    array(
                        'key' => 'field_paiement_montant',
                        'label' => 'Montant payé',
                        'name' => 'paiement_montant',
                        'type' => 'number',
                        'default_value' => 0,
                    ),
                    array(
                        'key' => 'field_paiement_date',
                        'label' => 'Date réelle du paiement',
                        'name' => 'paiement_date',
                        'type' => 'date_picker',
                        'instructions' => 'Sélectionner la date réelle du paiement.',
                    ),
                ),
            ),
        ),
        'location' => array(
            array(
                array(
                    'param' => 'user_role',
                    'operator' => '==',
                    'value' => 'organisateur',
                ),
            ),
        ),
    ));
});

// 📌 Appliquer la restriction d'accès aux méta des organisateurs
add_filter('acf/prepare_field/key=field_total_points_percus_organisateur', 'filtrer_acf_pour_non_admins_joueurs');
add_filter('acf/prepare_field/key=field_revenu_total_organisateur', 'filtrer_acf_pour_non_admins_joueurs');




/**
 * 📌 Enregistrement des champs ACF pour les méta des énigmes.
 * Ces champs sont stockés en tant que "post_meta" pour chaque énigme.
 * 
 * - `total_tentatives_enigme` : Nombre total de tentatives effectuées.
 * - `total_indices_debloques_enigme` : Nombre total d'indices débloqués.
 * - `total_points_depenses_enigme` : Nombre total de points dépensés.
 * - `total_joueurs_ayant_resolu_enigme` : Nombre total de joueurs ayant trouvé la solution.
 * - `total_joueurs_souscription_enigme` : Nombre total de joueurs ayant souscrit à l'énigme.
 */
add_action('acf/init', function () {
    acf_add_local_field_group(array(
        'key' => 'group_enigme_metadonnees',
        'title' => 'Métadonnées des Énigmes',
        'fields' => array(

            // 🔹 Nombre total de tentatives effectuées sur l’énigme
            array(
                'key' => 'field_total_tentatives_enigme',
                'label' => 'Total tentatives énigme',
                'name' => 'total_tentatives_enigme',
                'type' => 'number',
                'default_value' => 0,
            ),

            // 🔹 Nombre total d'indices débloqués sur l’énigme
            array(
                'key' => 'field_total_indices_debloques_enigme',
                'label' => 'Total indices débloqués',
                'name' => 'total_indices_debloques_enigme',
                'type' => 'number',
                'default_value' => 0,
            ),

            // 🔹 Nombre total de points dépensés pour cette énigme (tentatives, indices...)
            array(
                'key' => 'field_total_points_depenses_enigme',
                'label' => 'Total points dépensés énigme',
                'name' => 'total_points_depenses_enigme',
                'type' => 'number',
                'default_value' => 0,
            ),
            // 🔹 Nombre total de joueurs ayant résolu l’énigme
            array(
                'key' => 'field_total_joueurs_ayant_resolu_enigme',
                'label' => 'Total joueurs ayant résolu l\'énigme',
                'name' => 'total_joueurs_ayant_resolu_enigme',
                'type' => 'number',
                'default_value' => 0,
            ),

            // 🔹 Nombre total de joueurs ayant souscrit à l’énigme (même sans tentative)
            array(
                'key' => 'field_total_joueurs_souscription_enigme',
                'label' => 'Total joueurs souscription énigme',
                'name' => 'total_joueurs_souscription_enigme',
                'type' => 'number',
                'default_value' => 0,
            ),

        ),
        'location' => array(
            array(
                array(
                    'param' => 'post_type',   // 🔹 Appliquer ces champs uniquement aux CPT "énigme"
                    'operator' => '==',
                    'value' => 'enigme',
                ),
            ),
        ),
    ));


    // ——————————————————————————————————————
    /**
     * 📌 Enregistrement des champs ACF pour les méta des chasses.
     * Ces champs sont stockés en tant que "post_meta" pour chaque chasse.
     *
     * - `total_tentatives_chasse` : Nombre total de tentatives effectuées sur toutes les énigmes d’une chasse.
     * - `total_indices_debloques_chasse` : Nombre total d'indices débloqués sur une chasse.
     * - `total_points_depenses_chasse` : Nombre total de points dépensés dans une chasse.
     * - `total_points_gagnes_chasse` : Nombre total de points gagnés dans une chasse.
     * - `total_joueurs_ayant_resolu_chasse` : Nombre total de joueurs ayant terminé la chasse.
     * - `total_joueurs_souscription_chasse` : Nombre total de joueurs ayant souscrit à au moins une énigme de la chasse.
     */
    acf_add_local_field_group(array(
        'key' => 'group_chasse_metadonnees',
        'title' => 'Métadonnées des Chasses',
        'fields' => array(

            // 🔹 Nombre total de tentatives sur toutes les énigmes d’une chasse
            array(
                'key' => 'field_total_tentatives_chasse',
                'label' => 'Total tentatives chasse',
                'name' => 'total_tentatives_chasse',
                'type' => 'number',
                'default_value' => 0,
            ),

            // 🔹 Nombre total d'indices débloqués sur une chasse
            array(
                'key' => 'field_total_indices_debloques_chasse',
                'label' => 'Total indices débloqués',
                'name' => 'total_indices_debloques_chasse',
                'type' => 'number',
                'default_value' => 0,
            ),

            // 🔹 Nombre total de points dépensés dans une chasse
            array(
                'key' => 'field_total_points_depenses_chasse',
                'label' => 'Total points dépensés chasse',
                'name' => 'total_points_depenses_chasse',
                'type' => 'number',
                'default_value' => 0,
            ),

            // ✅ AJOUT du champ progression_chasse
            array(
                'key' => 'field_progression_chasse',
                'label' => 'Progression des joueurs',
                'name' => 'progression_chasse',
                'type' => 'textarea', // Utilisation d'un champ texte pour stocker un JSON
                'default_value' => '{}', // Valeur par défaut : JSON vide
                'instructions' => 'Stocke un tableau JSON avec le nombre de joueurs à chaque palier de progression (0 à X énigmes résolues).',
                'readonly' => 1, // Empêche l’édition manuelle
            ),

            // 🔹 Nombre total de joueurs ayant souscrit à au moins une énigme de la chasse
            array(
                'key' => 'field_total_joueurs_souscription_chasse',
                'label' => 'Total joueurs souscription chasse',
                'name' => 'total_joueurs_souscription_chasse',
                'type' => 'number',
                'default_value' => 0,
            ),
        ),
        'location' => array(
            array(
                array(
                    'param' => 'post_type',   // 🔹 Appliquer ces champs uniquement aux CPT "chasse"
                    'operator' => '==',
                    'value' => 'chasse',
                ),
            ),
        ),
    ));


    // ——————————————————————————————————————
    /**
     * 📌 Métadonnées des Joueurs
     * 🔹 Stocke des informations statistiques sur les joueurs.
     * 🔹 Les champs sont accessibles uniquement aux administrateurs.
     */
    acf_add_local_field_group(array(
        'key' => 'group_joueur_metadonnees',
        'title' => 'Métadonnées du Joueur',
        'fields' => array(
            array(
                'key' => 'field_total_enigmes_jouees',
                'label' => 'Total des énigmes jouées',
                'name' => 'total_enigmes_jouees',
                'type' => 'number',
                'default_value' => 0,
            ),
            array(
                'key' => 'field_total_enigmes_trouvees',
                'label' => 'Total des énigmes trouvées',
                'name' => 'total_enigmes_trouvees',
                'type' => 'number',
                'default_value' => 0,
            ),
            array(
                'key' => 'field_total_chasses_terminees',
                'label' => 'Total des chasses terminées',
                'name' => 'total_chasses_terminees',
                'type' => 'number',
                'default_value' => 0,
            ),
            array(
                'key' => 'field_total_indices_debloques',
                'label' => 'Total des indices débloqués',
                'name' => 'total_indices_debloques',
                'type' => 'number',
                'default_value' => 0,
            ),
            array(
                'key' => 'field_total_points_depenses',
                'label' => 'Total des points dépensés',
                'name' => 'total_points_depenses',
                'type' => 'number',
                'default_value' => 0,
            ),
        ),
        'location' => array(
            array(
                array(
                    'param' => 'user_role',
                    'operator' => '==',
                    'value' => 'all',
                ),
            ),
        ),
    ));
}); 

