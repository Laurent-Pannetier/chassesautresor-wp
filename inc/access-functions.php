<?php
defined('ABSPATH') || exit;

/**
 * Determine whether the current user may view statistics for a hunt.
 */
function utilisateur_peut_voir_statistiques_chasse(int $chasse_id): bool
{
    $has_valid_hunt = $chasse_id > 0;
    $is_administrator = $has_valid_hunt && current_user_can('manage_options');
    $service = new ChassesAuTresor\Core\Content\HuntAccessService();

    return $service->canViewStatistics(
        $has_valid_hunt,
        $is_administrator,
        $has_valid_hunt
            && !$is_administrator
            && utilisateur_est_organisateur_associe_a_chasse(get_current_user_id(), $chasse_id)
    );
}

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

/**
 * Vérifie si un utilisateur possède un rôle d'organisateur.
 *
 * L'utilisateur peut être organisateur confirmé ou en cours de création.
 * Si aucun ID n'est fourni, l'utilisateur courant est utilisé.
 *
 * @param int|null $user_id ID de l'utilisateur ou null pour courant.
 * @return bool True si l'utilisateur a un rôle d'organisateur.
 */
function est_organisateur($user_id = null)
{
    if (!$user_id) {
        $user_id = get_current_user_id();
    }

    if (!$user_id) {
        return false;
    }

    $user = get_userdata($user_id);
    if (!$user) {
        return false;
    }

    $roles = (array) $user->roles;
    $service = new ChassesAuTresor\Core\Content\OrganizerRoleService();

    return $service->isOrganizer(
        $roles,
        ROLE_ORGANISATEUR,
        ROLE_ORGANISATEUR_CREATION
    );
}


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
 * Vérifie si un utilisateur peut créer un post d'un type spécifique.
 *
 * @param string $post_type Type de post (CPT).
 * @param int|null $chasse_id (Optionnel) ID de la chasse si déjà connu.
 * @return bool True si l'utilisateur peut créer ce post, sinon false.
 */
function utilisateur_peut_creer_post($post_type, $chasse_id = null)
{
    $is_authenticated = is_user_logged_in();
    $is_administrator = $is_authenticated && current_user_can('manage_options');
    $user_id = $is_authenticated && !$is_administrator ? get_current_user_id() : 0;
    $roles = $user_id > 0 ? (array) wp_get_current_user()->roles : [];
    $organizer_id = $user_id > 0 && in_array($post_type, ['organisateur', 'chasse', 'enigme'], true)
        ? (int) get_organisateur_from_user($user_id)
        : 0;
    $has_organizer_role = $user_id > 0 && in_array(ROLE_ORGANISATEUR, $roles, true);
    $has_existing_hunt = false;

    if ($post_type === 'chasse' && $organizer_id > 0 && !$has_organizer_role) {
        $user_hunts = get_posts([
            'post_type' => 'chasse',
            'post_status' => 'any',
            'author' => $user_id,
            'fields' => 'ids',
        ]);
        $has_existing_hunt = !empty($user_hunts);
    }

    if ($user_id > 0 && $post_type === 'enigme' && !$chasse_id) {
        $chasse_id = filter_input(INPUT_GET, 'chasse_associee', FILTER_VALIDATE_INT);
    }

    $has_valid_hunt = $user_id > 0
        && $post_type === 'enigme'
        && (int) $chasse_id > 0
        && get_post_type($chasse_id) === 'chasse';
    $hunt_organizer_id = $has_valid_hunt ? (int) get_organisateur_from_chasse($chasse_id) : 0;
    $service = new ChassesAuTresor\Core\Content\ContentCreationService();

    return $service->canCreate(
        $is_authenticated,
        $is_administrator,
        (string) $post_type,
        $organizer_id > 0,
        $has_organizer_role,
        $has_existing_hunt,
        $has_valid_hunt,
        $hunt_organizer_id > 0 && $hunt_organizer_id === $organizer_id,
        $has_valid_hunt ? (string) get_field('chasse_cache_statut_validation', $chasse_id) : ''
    );
}


/**
 * Vérifie si l’utilisateur connecté peut modifier un post (organisateur, chasse, énigme),
 * en se basant uniquement sur la relation ACF `utilisateurs_associes`.
 *
 * @param int $post_id ID du post à vérifier.
 * @return bool True si l’utilisateur est associé au post, False sinon.
 */
function utilisateur_peut_modifier_post($post_id)
{
    $has_valid_context = is_user_logged_in() && (bool) $post_id;
    if (!$has_valid_context) {
        cat_debug('❌ utilisateur_peut_modifier_post: utilisateur non connecté ou post_id invalide');
    }

    $is_administrator = $has_valid_context && current_user_can('manage_options');
    $user_id = $has_valid_context && !$is_administrator ? get_current_user_id() : 0;
    $post_type = $has_valid_context && !$is_administrator ? (string) get_post_type($post_id) : '';
    $is_associated_user = false;
    $is_author = false;
    $owner_id = 0;

    if ($post_type === 'organisateur') {
        $associated_users = get_field('utilisateurs_associes', $post_id);
        $associated_users = is_array($associated_users) ? array_map('strval', $associated_users) : [];
        $is_associated_user = in_array((string) $user_id, $associated_users, true);
        $is_author = (int) get_post_field('post_author', $post_id) === $user_id;
    } elseif ($post_type === 'chasse') {
        $owner_id = (int) get_organisateur_from_chasse($post_id);
    } elseif ($post_type === 'enigme') {
        $hunt_id = (int) recuperer_id_chasse_associee($post_id);
        $owner_id = $hunt_id > 0 ? (int) get_organisateur_from_chasse($hunt_id) : 0;
    } elseif ($post_type === 'indice') {
        $hunt_id = get_field('indice_chasse_linked', $post_id);
        if (is_array($hunt_id)) {
            $hunt_id = $hunt_id['ID'] ?? $hunt_id[0] ?? null;
        }

        if (!$hunt_id) {
            $target = get_field('indice_enigme_linked', $post_id);
            $first_target = is_array($target) ? ($target[0] ?? null) : $target;
            $target_id = is_array($first_target) ? ($first_target['ID'] ?? null) : $first_target;
            $hunt_id = $target_id ? recuperer_id_chasse_associee($target_id) : 0;
        }

        $owner_id = (int) $hunt_id;
    } elseif ($post_type !== '') {
        cat_debug("❌ utilisateur_peut_modifier_post: post_type inconnu ($post_type)");
    }

    $service = new ChassesAuTresor\Core\Content\ContentModificationService();

    return $service->canModify(
        $has_valid_context,
        $is_administrator,
        $post_type,
        $is_associated_user,
        $is_author,
        $owner_id > 0,
        $owner_id > 0 && utilisateur_peut_modifier_post($owner_id)
    );
}

/**
 * Determine if the current user can perform an action on indices for a given object.
 *
 * @param string $action      Action to check: 'create', 'edit', or 'delete'.
 * @param string $object_type Target type: 'chasse' or 'enigme'.
 * @param int    $object_id   ID of the target object.
 *
 * @return bool True if allowed, false otherwise.
 */
function indice_action_autorisee(string $action, string $object_type, int $object_id): bool
{
    $is_authenticated = is_user_logged_in();
    $is_valid_object = $is_authenticated && get_post_type($object_id) === $object_type;
    $hunt_id = 0;
    if ($is_valid_object && $object_type === 'enigme') {
        $hunt_id = (int) recuperer_id_chasse_associee($object_id);
    } elseif ($is_valid_object && $object_type === 'chasse') {
        $hunt_id = $object_id;
    }
    $has_hunt = $hunt_id > 0;
    $needs_hunt_permission = $object_type === 'enigme' && in_array($action, ['create', 'edit'], true);
    $service = new ChassesAuTresor\Core\Content\RelatedContentActionService();

    return $service->canPerform(
        $is_authenticated,
        $action,
        $object_type,
        $is_valid_object,
        $is_authenticated && current_user_can('manage_options'),
        $is_valid_object
            && $has_hunt
            && utilisateur_est_organisateur_associe_a_chasse(get_current_user_id(), $hunt_id),
        $is_valid_object ? (string) get_post_status($object_id) : '',
        $object_type === 'chasse' && $is_valid_object
            ? (string) get_field('chasse_cache_statut_validation', $object_id)
            : '',
        $object_type === 'enigme' && $has_hunt,
        $needs_hunt_permission && $has_hunt
            ? indice_action_autorisee($action, 'chasse', $hunt_id)
            : false
    );
}

/**
 * Détermine si une action sur une solution est autorisée.
 *
 * Wrapper autour de indice_action_autorisee afin de réutiliser les
 * règles d'accès existantes pour les chasses et les énigmes.
 *
 * @param string $action      Action souhaitée (create, edit, delete).
 * @param string $object_type Type de cible (chasse ou enigme).
 * @param int    $object_id   ID de la cible.
 * @return bool
 */
function solution_action_autorisee(string $action, string $object_type, int $object_id): bool
{
    return indice_action_autorisee($action, $object_type, $object_id);
}

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


// ==================================================
//  🔒 CONTRÔLES SPÉCIFIQUES : ACF, conditions, prérequis
// ==================================================
/**
 * 🔹 acf/load_field/name=enigme_acces_condition → Supprime l’option "pré-requis" si aucune énigme n’est éligible.
 * 🔹 recuperer_enigmes_possibles_pre_requis → Liste des énigmes valides pouvant servir de prérequis.
 * 🔹 verifier_et_enregistrer_condition_pre_requis → Endpoint AJAX pour valider l’option "pré-requis" après sélection.
 */


/**
 * Filtre le champ radio `enigme_acces_condition` pour cacher "pré-requis" si aucune énigme éligible n’est détectée.
 *
 * @param array $field Le champ ACF complet
 * @return array Champ modifié sans l’option "pré-requis" si vide
 *
 * @hook acf/load_field/name=enigme_acces_condition
 */
add_filter('acf/load_field/name=enigme_acces_condition', function ($field) {
    global $post;

    if (!$post || get_post_type($post) !== 'enigme') return $field;

    $disponibles = recuperer_enigmes_possibles_pre_requis($post->ID);
    if (empty($disponibles)) {
        unset($field['choices']['pre_requis']);
    }

    return $field;
});


/**
 * Retourne les énigmes valides pouvant être utilisées comme prérequis.
 *
 * Critères :
 * - Même chasse que $enigme_id
 * - Statut : post_type = enigme
 * - Validation ≠ "aucune"
 * - Différente de l’énigme en cours
 *
 * @param int $enigme_id ID de l’énigme en cours
 * @return array Liste des ID valides
 */
function recuperer_enigmes_possibles_pre_requis($enigme_id)
{
    $chasse_id = recuperer_id_chasse_associee($enigme_id);
    if (!$chasse_id) {
        return [];
    }

    $associees = recuperer_enigmes_associees($chasse_id);
    $validation_modes = [];

    foreach ($associees as $id) {
        if ((int) $id === (int) $enigme_id) {
            continue;
        }

        $validation_modes[(int) $id] = (string) get_field('enigme_mode_validation', $id);
    }

    $service = new ChassesAuTresor\Core\Content\RiddlePrerequisiteService();

    return $service->getEligibleIds((int) $enigme_id, $validation_modes);
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
