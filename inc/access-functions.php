<?php
defined('ABSPATH') || exit;

// ==================================================
// 📘 SOMMAIRE DU FICHIER : access-functions.php
// ==================================================
//  🔐 CONTRÔLES GÉNÉRAUX : rôle, statut global
//  📄 ACCÈS À UN POST (voir, modifier, créer, voir)
//  📂 ACCÈS AUX FICHIERS PROTÉGÉS
//  🔒 CONTRÔLES SPÉCIFIQUES : ACF, conditions, prérequis
// 📌 VISIBILITÉ ET AFFICHAGE (RÉSERVÉ FUTUR)


// ==================================================
// 🔐 CONTRÔLES GÉNÉRAUX : rôle, statut global
// ==================================================
/**
 */

// ==================================================
// 📄 ACCÈS À UN POST (voir, modifier, créer, voir)
// ==================================================
/**
 * 🔹 utilisateur_peut_creer_post → Vérifie si l’utilisateur peut créer un post (organisateur, chasse, énigme).
 * 🔹 utilisateur_peut_modifier_post → Vérifie si l’utilisateur peut modifier un post via ACF.
 * 🔹 utilisateur_peut_voir_enigme → Vérifie si un utilisateur peut voir une énigme.
 * 🔹 utilisateur_peut_ajouter_enigme → Vérifie si un utilisateur peut ajouter une énigme à une chasse.
 * 🔹 utilisateur_peut_modifier_enigme → Vérifie si un utilisateur peut modifier une énigme.
 * 🔹 utilisateur_peut_ajouter_chasse → Vérifie si l’utilisateur peut ajouter une chasse à un organisateur donné.
 * 🔹 champ_est_editable → Vérifie si un champ est éditable pour un utilisateur donné.
 * 🔹 blocage_acces_admin_non_admins (admin_init) → Empêche certains rôles d’accéder à wp-admin.
 */


/**
 * Détermine si un utilisateur peut voir une énigme donnée.
 *
 * @param int $enigme_id ID du post de type 'enigme'
 * @param int|null $user_id ID utilisateur (null = utilisateur courant)
 * @return bool
 */
function utilisateur_peut_voir_enigme(int $enigme_id, ?int $user_id = null): bool
{
    if (get_post_type($enigme_id) !== 'enigme') {
        cat_debug("❌ [voir énigme] post #$enigme_id n'est pas une énigme.");
        return false;
    }

    $post_status  = get_post_status($enigme_id);
    $etat_systeme = get_field('enigme_cache_etat_systeme', $enigme_id);
    $user_id      = $user_id ?? get_current_user_id();
    $chasse_id    = recuperer_id_chasse_associee($enigme_id);

    cat_debug("🔎 [voir énigme] #$enigme_id | statut = $post_status | etat = $etat_systeme | user_id = $user_id");

    $service = new ChassesAuTresor\Core\Content\RiddleAccessService();
    $is_administrator = current_user_can('administrator');
    if ($is_administrator) {
        cat_debug("✅ [voir énigme] accès admin");
        return $service->canView(true, false, false, '', '', '', false, false, false);
    }

    if (!$chasse_id) {
        cat_debug("❌ [voir énigme] pas de chasse associée");
        return $service->canView(false, false, false, '', '', '', false, false, false);
    }

    $statut_validation = get_field('chasse_cache_statut_validation', $chasse_id) ?? '';
    $chasse_terminee = get_field('chasse_cache_statut', $chasse_id) === 'termine';
    if ($chasse_terminee && $post_status === 'publish') {
        return $service->canView(false, true, true, 'publish', '', '', false, false, false);
    }

    $est_engage = utilisateur_est_engage_dans_chasse($user_id, $chasse_id);
    $est_abonne = is_user_logged_in() && in_array('abonne', wp_get_current_user()->roles, true);
    $est_organisateur = (!$est_abonne || $est_engage)
        ? utilisateur_est_organisateur_associe_a_chasse($user_id, $chasse_id)
        : false;
    $autorise = $service->canView(
        false,
        true,
        $chasse_terminee,
        (string) $post_status,
        (string) $etat_systeme,
        (string) $statut_validation,
        $est_organisateur,
        $est_engage,
        $est_abonne
    );

    cat_debug("🔎 [voir énigme] décision métier → accès " . ($autorise ? 'OK' : 'REFUSÉ'));
    return $autorise;
}




/**
 * Détermine si un utilisateur peut ajouter une énigme à une chasse.
 *
 * Conditions :
 * - L'utilisateur doit être connecté
 * - Il doit être associé à l'organisateur lié à la chasse
 * - Le statut de validation de la chasse doit être 'creation' ou 'correction'
 *
 * @param int $chasse_id
 * @param int|null $user_id
 * @return bool
 */
function utilisateur_peut_ajouter_enigme(int $chasse_id, ?int $user_id = null): bool
{
    $user_id = $user_id ?? get_current_user_id();
    $is_hunt = get_post_type($chasse_id) === 'chasse';
    $is_authenticated = $user_id > 0 && is_user_logged_in();
    $is_organizer = $is_authenticated && est_organisateur($user_id);
    $is_associated = $is_organizer && $is_hunt
        && utilisateur_est_organisateur_associe_a_chasse($user_id, $chasse_id);
    $riddle_count = $is_associated ? count(recuperer_ids_enigmes_pour_chasse($chasse_id)) : 0;
    $service = new ChassesAuTresor\Core\Content\RiddleManagementService();

    return $service->canAdd(
        $is_hunt,
        $is_authenticated,
        $is_organizer,
        $is_hunt ? (string) get_post_status($chasse_id) : '',
        $is_hunt ? (string) get_field('chasse_cache_statut', $chasse_id) : '',
        $is_hunt ? (string) get_field('chasse_cache_statut_validation', $chasse_id) : '',
        $is_associated,
        $riddle_count
    );
}


/**
 * Détermine si un utilisateur peut modifier une énigme.
 *
 * @param int $enigme_id
 * @param int|null $user_id
 * @return bool
 */
function utilisateur_peut_modifier_enigme(int $enigme_id, ?int $user_id = null): bool
{
    $user_id = $user_id ?? get_current_user_id();
    $is_riddle = get_post_type($enigme_id) === 'enigme';
    $is_administrator = user_can($user_id, 'administrator');
    $hunt_id = $is_riddle && !$is_administrator ? (int) recuperer_id_chasse_associee($enigme_id) : 0;
    $has_hunt = $hunt_id > 0 && get_post_type($hunt_id) === 'chasse';
    $service = new ChassesAuTresor\Core\Content\RiddleManagementService();

    return $service->canEdit(
        $is_riddle,
        $is_administrator,
        $has_hunt,
        $has_hunt && utilisateur_est_organisateur_associe_a_chasse($user_id, $hunt_id)
    );
}

/**
 * Détermine si un utilisateur peut supprimer une énigme.
 *
 * L'utilisateur doit être organisateur ou organisateur en cours de création
 * et lié à la chasse parente. La chasse doit être en statut « revision » et
 * son état de validation doit être « creation » ou « correction ».
 *
 * @param int      $enigme_id ID de l'énigme à supprimer.
 * @param int|null $user_id   ID utilisateur (optionnel, courant par défaut).
 * @return bool True si la suppression est autorisée.
 */
function utilisateur_peut_supprimer_enigme(int $enigme_id, ?int $user_id = null): bool
{
    $user_id = $user_id ?? get_current_user_id();
    $is_riddle = get_post_type($enigme_id) === 'enigme';
    $hunt_id = $is_riddle ? (int) recuperer_id_chasse_associee($enigme_id) : 0;
    $has_hunt = $hunt_id > 0 && get_post_type($hunt_id) === 'chasse';
    $is_authenticated = $user_id > 0;
    $is_organizer = $is_authenticated && est_organisateur($user_id);
    $is_associated = $is_organizer && $has_hunt
        && utilisateur_est_organisateur_associe_a_chasse($user_id, $hunt_id);
    $service = new ChassesAuTresor\Core\Content\RiddleManagementService();

    return $service->canDelete(
        $is_riddle,
        $is_authenticated,
        $is_organizer,
        $has_hunt,
        $has_hunt ? (string) get_field('chasse_cache_statut', $hunt_id) : '',
        $has_hunt ? (string) get_field('chasse_cache_statut_validation', $hunt_id) : '',
        $is_associated
    );
}


/**
 * Vérifie si un utilisateur peut ajouter une nouvelle chasse à un organisateur donné.
 *
 * @param int $organisateur_id
 * @return bool
 */
function utilisateur_peut_ajouter_chasse(int $organisateur_id): bool
{
    $service = new ChassesAuTresor\Core\Content\HuntManagementService();

    if (!is_user_logged_in()) {
        return $service->canCreate(false, false, false, false, false, false, false, false);
    }

    $user       = wp_get_current_user();
    $roles      = (array) $user->roles;
    $user_id    = (int) $user->ID;

    // Administrateur → pas d'ajout via l'interface publique
    if (user_can($user_id, 'manage_options')) {
        return $service->canCreate(true, true, false, false, false, false, false, false);
    }

    // L'utilisateur doit être lié à l'organisateur
    if (!utilisateur_peut_modifier_post($organisateur_id)) {
        return $service->canCreate(true, false, false, false, false, false, false, false);
    }

    $has_organizer_role = in_array(ROLE_ORGANISATEUR, $roles, true);
    $has_creation_role = in_array(ROLE_ORGANISATEUR_CREATION, $roles, true);
    $is_organizer_published = $has_organizer_role && get_post_status($organisateur_id) === 'publish';

    return $service->canCreate(
        true,
        false,
        true,
        $has_organizer_role,
        $has_creation_role,
        $is_organizer_published,
        $is_organizer_published && organisateur_a_chasse_pending($organisateur_id),
        !$has_organizer_role && $has_creation_role && organisateur_a_des_chasses($organisateur_id)
    );
}

/**
 * Détermine si l'utilisateur peut afficher le panneau d'édition d'un post.
 *
 * Cette vérification repose sur la relation organisateur ↔ utilisateur et
 * sur différents statuts des CPT.
 *
 * @param int $post_id ID du post concerné.
 * @return bool True si le panneau peut être affiché.
 */
function utilisateur_peut_voir_panneau(int $post_id): bool
{
    $is_authenticated = is_user_logged_in();
    $is_administrator = $is_authenticated && current_user_can('manage_options');
    $user = $is_authenticated ? wp_get_current_user() : null;
    $is_organizer = $user !== null && !$is_administrator && est_organisateur($user->ID);
    $can_modify_content = $is_administrator || ($is_organizer && utilisateur_peut_modifier_post($post_id));
    $content_type = $is_authenticated ? (string) get_post_type($post_id) : '';
    $service = new ChassesAuTresor\Core\Content\ContentPanelAccessService();

    return $service->canView(
        $is_authenticated,
        $is_administrator,
        $is_organizer,
        $can_modify_content,
        $content_type,
        $is_authenticated ? (string) get_post_status($post_id) : '',
        $content_type === 'chasse' ? (string) get_field('chasse_cache_statut_validation', $post_id) : '',
        $content_type === 'enigme' ? (string) get_field('enigme_cache_etat_systeme', $post_id) : ''
    );
}

/**
 * Détermine si l'utilisateur peut éditer les champs désactivés d'un post.
 *
 * Les conditions incluent celles de `utilisateur_peut_voir_panneau()` et des
 * statuts métiers plus stricts selon le type de contenu.
 *
 * @param int $post_id ID du post concerné.
 * @return bool True si l'édition avancée est autorisée.
 */
function utilisateur_peut_editer_champs(int $post_id): bool
{
    $can_view_panel = utilisateur_peut_voir_panneau($post_id);
    $is_administrator = $can_view_panel && current_user_can('manage_options');
    $content_type = $can_view_panel && !$is_administrator ? (string) get_post_type($post_id) : '';
    $hunt_id = $content_type === 'enigme' ? (int) recuperer_id_chasse_associee($post_id) : 0;
    $has_hunt = $hunt_id > 0;
    $status_source_id = $content_type === 'enigme' ? $hunt_id : $post_id;
    $has_hunt_status = $content_type === 'chasse' || ($content_type === 'enigme' && $has_hunt);
    $can_modify_content = $content_type === 'organisateur'
        && !$is_administrator
        && utilisateur_peut_modifier_post($post_id);
    $system_status = '';
    if ($content_type === 'enigme') {
        $system_status = (string) get_field('enigme_cache_etat_systeme', $post_id);
    } elseif ($content_type === 'indice') {
        $system_status = (string) get_field('indice_cache_etat_systeme', $post_id);
    }
    $service = new ChassesAuTresor\Core\Content\ContentFieldAccessService();

    return $service->canEdit(
        $can_view_panel,
        $is_administrator,
        $can_modify_content,
        $content_type,
        $can_view_panel ? (string) get_post_status($post_id) : '',
        $has_hunt_status ? (string) get_field('chasse_cache_statut_validation', $status_source_id) : '',
        $has_hunt_status ? (string) get_field('chasse_cache_statut', $status_source_id) : '',
        $system_status,
        $has_hunt,
        $has_hunt ? (string) get_post_status($hunt_id) : ''
    );
}


/**
 * Vérifie si un champ donné est éditable pour un utilisateur donné sur un post donné.
 *
 * @param string $champ Nom du champ ACF ou champ natif (ex : post_title)
 * @param int $post_id ID du post (CPT organisateur, chasse, etc.)
 * @param int|null $user_id ID utilisateur (par défaut : utilisateur connecté)
 * @return bool True si le champ est éditable, False sinon
 */
function champ_est_editable($champ, $post_id, $user_id = null)
{
    $has_valid_context = (bool) $post_id && is_user_logged_in();
    $is_administrator = $has_valid_context && current_user_can('manage_options');
    $post_type = $has_valid_context && !$is_administrator ? (string) get_post_type($post_id) : '';
    $is_organizer_title = $post_type === 'organisateur' && $champ === 'post_title';
    $roles = $has_valid_context && !$is_administrator ? (array) wp_get_current_user()->roles : [];
    $requires_advanced_access = $post_type === 'indice'
        || ($post_type === 'enigme' && $champ === 'post_title')
        || ($post_type === 'chasse'
            && in_array($champ, ['post_title', 'caracteristiques.chasse_infos_cout_points'], true));
    $can_edit_advanced_fields = $requires_advanced_access && utilisateur_peut_editer_champs($post_id);
    $hunt_count = 0;
    $creation_hunt_count = 0;

    if ($is_organizer_title && in_array(ROLE_ORGANISATEUR_CREATION, $roles, true)) {
        $hunts_query = get_chasses_de_organisateur($post_id);
        $hunt_count = is_a($hunts_query, 'WP_Query') ? (int) $hunts_query->post_count : 0;
        $creation_hunt_count = $hunt_count === 1 ? count(get_chasses_en_creation($post_id)) : 0;
    }

    $service = new ChassesAuTresor\Core\Content\ContentFieldPolicyService();

    return $service->canEdit(
        $has_valid_context,
        $is_administrator,
        $has_valid_context && ($is_administrator || utilisateur_peut_modifier_post($post_id)),
        $post_type,
        (string) $champ,
        $can_edit_advanced_fields,
        $has_valid_context && !$is_administrator ? (string) get_post_status($post_id) : '',
        in_array(ROLE_ORGANISATEUR_CREATION, $roles, true),
        $hunt_count,
        $creation_hunt_count
    );
}


// ==================================================
// 📂 ACCÈS AUX FICHIERS PROTÉGÉS
// ==================================================
/**
 * 🔹 (rewrite) /voir-fichier/ + handler voir-fichier.php → Point d’entrée sécurisé pour consulter les fichiers de solution.
 * 🔹 utilisateur_peut_voir_solution_enigme → Vérifie si l’utilisateur peut consulter la solution d’une énigme (PDF ou texte).
 * 🔹 (rewrite) /voir-image-enigme/ + handler voir-image-enigme.php → Sert les images protégées d’une énigme via proxy PHP.
 */


/**
 * Vérifie si un utilisateur a le droit de consulter la solution (PDF ou texte) d'une énigme
 *
 * @param int $post_id ID du post (énigme ou chasse)
 * @param int $user_id ID de l'utilisateur connecté
 * @return bool
 */
function utilisateur_peut_voir_solution_enigme(int $post_id, int $user_id): bool
{
    if (!$post_id || !$user_id) {
        return false;
    }

    $type = get_post_type($post_id);
    if ($type === 'chasse') {
        return utilisateur_peut_voir_solution_chasse($post_id, $user_id);
    }

    if ($type !== 'enigme') {
        return false;
    }

    $solution = solution_recuperer_par_objet($post_id, 'enigme');
    if (!$solution) {
        return false;
    }

    $service = new ChassesAuTresor\Core\Content\SolutionAccessService();
    if (user_can($user_id, 'manage_options')) {
        return $service->canViewRiddleSolution(true, false, false, false, false, '');
    }

    $chasse_id = recuperer_id_chasse_associee($post_id);
    $is_hunt_finished = $chasse_id && get_field('chasse_cache_statut', $chasse_id) === 'termine';
    $is_engaged = $is_hunt_finished
        && function_exists('utilisateur_est_engage_dans_enigme')
        && utilisateur_est_engage_dans_enigme($user_id, $post_id);
    $is_organizer = !$is_engaged
        && $chasse_id
        && utilisateur_est_organisateur_associe_a_chasse($user_id, $chasse_id);
    $riddle_status = !$chasse_id || $is_engaged || $is_organizer
        ? ''
        : (string) get_statut_utilisateur_enigme($user_id, $post_id);

    return $service->canViewRiddleSolution(
        false,
        (bool) $chasse_id,
        $is_hunt_finished,
        $is_engaged,
        $is_organizer,
        $riddle_status
    );
}

/**
 * Vérifie si un utilisateur a le droit de consulter la solution d'une chasse.
 *
 * @param int $chasse_id ID de la chasse
 * @param int $user_id   ID de l'utilisateur connecté
 * @return bool
 */
function utilisateur_peut_voir_solution_chasse(int $chasse_id, int $user_id): bool
{
    if (!$chasse_id) {
        return false;
    }

    $solution = solution_recuperer_par_objet($chasse_id, 'chasse');
    if (!$solution) {
        return false;
    }

    $service = new ChassesAuTresor\Core\Content\SolutionAccessService();
    if ($user_id > 0 && user_can($user_id, 'manage_options')) {
        return $service->canViewHuntSolution(true, true, false, false);
    }

    $is_organizer = $user_id > 0
        && utilisateur_est_organisateur_associe_a_chasse($user_id, $chasse_id);

    return $service->canViewHuntSolution(
        $user_id > 0,
        false,
        $is_organizer,
        $user_id > 0 && !$is_organizer && utilisateur_est_engage_dans_chasse($user_id, $chasse_id)
    );
}


/** 
 * @hook wp_ajax_verifier_et_enregistrer_condition_pre_requis
 * @return void (JSON)
 */
function verifier_et_enregistrer_condition_pre_requis()
{
    ChassesAuTresor\Core\Content\RiddlePrerequisiteAjaxHandler::handle();
}



// ==================================================
// 📌 VISIBILITÉ ET AFFICHAGE (RÉSERVÉ FUTUR)
// ==================================================

/**
 * Détermine si une chasse doit être visible pour un utilisateur.
 *
 * Règles de visibilité :
 * - Si statut WP = 'publish' ET 'chasse_cache_statut_validation' = 'valide'
 *     → visible par tous les utilisateurs (y compris anonymes)
 * - Si statut WP = 'pending'
 *     → visible uniquement si user est admin OU lié à la chasse
 * - Tous les autres cas → invisible
 *
 * @param int $chasse_id ID de la chasse.
 * @param int $user_id   ID de l'utilisateur.
 * @return bool          True si visible, false sinon.
 */
function chasse_est_visible_pour_utilisateur(int $chasse_id, int $user_id): bool
{
    $publication_status = (string) get_post_status($chasse_id);
    $is_pending = $publication_status === 'pending';
    $is_administrator = $is_pending && user_can($user_id, 'manage_options');
    $service = new ChassesAuTresor\Core\Content\HuntAccessService();

    return $service->canView(
        $publication_status,
        (string) get_field('chasse_cache_statut_validation', $chasse_id),
        $is_administrator,
        $is_pending
            && !$is_administrator
            && utilisateur_est_organisateur_associe_a_chasse($user_id, $chasse_id)
    );
}
