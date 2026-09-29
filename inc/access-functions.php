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
 * 🔹 restreindre_media_library_tous_non_admins → Restreint l’accès à la médiathèque aux seuls fichiers de l’utilisateur connecté.
 * 🔹 disable_gutenberg_for_non_admins → Force l’éditeur classique pour tous les rôles sauf administrateur.
 * 🔹 filtre_capacites_admin_user_has_cap → Bloque les capacités critiques dans l’admin pour les non-admins.
 */

/**
 * Restreint l'accès à la médiathèque WordPress pour tous les rôles sauf administrateurs.
 *
 * Cette fonction empêche les utilisateurs non-administrateurs de voir les fichiers médias des autres utilisateurs.
 * Ils ne peuvent accéder qu'aux fichiers qu'ils ont eux-mêmes téléversés.
 *
 * - Vérifie que l'utilisateur est connecté.
 * - Exclut les administrateurs (`administrator`) de la restriction.
 * - Applique un filtre sur la requête de la médiathèque (`ajax_query_attachments_args`).
 *
 * @param array $query Les arguments de la requête de la médiathèque.
 * @return array Les arguments modifiés avec restriction d'affichage des médias.
 */
function restreindre_media_library_tous_non_admins($query)
{
    $user = wp_get_current_user();

    // Vérifie si l'utilisateur est connecté et N'EST PAS administrateur
    if ($user->exists() && !in_array('administrator', (array) $user->roles)) {
        $query['author'] = $user->ID; // Limite l'affichage aux fichiers de l'utilisateur connecté
    }

    return $query;
}
add_filter('ajax_query_attachments_args', 'restreindre_media_library_tous_non_admins');
add_filter('rest_attachment_query', 'restreindre_media_library_tous_non_admins');

/**
 * Filtre les fichiers visibles dans la médiathèque selon le post en cours.
 *
 * - Pour un post de type "enigme", seuls les fichiers du dossier
 *   `/uploads/_enigmes/enigme-{ID}/` sont listés.
 * - Pour tous les autres posts, les fichiers issus de `/uploads/_enigmes/`
 *   sont exclus pour éviter leur sélection.
 *
 * @param array $query Arguments de la requête AJAX.
 * @return array Arguments éventuellement modifiés.
 */
function filtrer_media_library_par_cpt($query)
{
    if (!isset($_REQUEST['post_id'])) {
        return $query;
    }

    $post_id   = (int) $_REQUEST['post_id'];
    $post_type = get_post_type($post_id);

    if ($post_type === 'enigme') {
        $query['meta_query'] = [
            'relation' => 'AND',
            [
                'key'     => '_wp_attached_file',
                'value'   => '_enigmes/enigme-' . $post_id . '/',
                'compare' => 'LIKE',
            ],
        ];
    } elseif ($post_type) {
        $query['meta_query'] = [
            'relation' => 'AND',
            [
                'key'     => '_wp_attached_file',
                'value'   => '_enigmes/',
                'compare' => 'NOT LIKE',
            ],

        ];
    }

    return $query;
}
add_filter('ajax_query_attachments_args', 'filtrer_media_library_par_cpt', 15);


/**
 * Désactive l'éditeur Gutenberg pour tous les rôles sauf l'administrateur.
 *
 * Cette fonction force l'utilisation de l'éditeur classique pour tous les utilisateurs,
 * à l'exception de ceux ayant le rôle "administrator".
 *
 * @param bool   $use_block_editor Indique si Gutenberg doit être utilisé.
 * @param WP_Post $post L'objet post en cours d'édition.
 * @return bool Retourne false pour désactiver Gutenberg, true sinon.
 */
function disable_gutenberg_for_non_admins($use_block_editor, $post)
{
    // Récupération de l'utilisateur connecté
    $current_user = wp_get_current_user();

    // Vérification du rôle : seul l'administrateur peut utiliser Gutenberg
    if (!in_array('administrator', $current_user->roles)) {
        return false; // Désactive Gutenberg et force l'éditeur classique
    }

    return $use_block_editor;
}
add_filter('use_block_editor_for_post', 'disable_gutenberg_for_non_admins', 10, 2);


/**
 * Filtre les capacités critiques dans l’admin pour les non-admins.
 *
 * Cette fonction bloque certaines actions sensibles (édition, suppression, publication)
 * sur les types de posts personnalisés "organisateur", "chasse" et "enigme" pour tous
 * les utilisateurs sauf les administrateurs, uniquement dans l’interface d’administration.
 *
 * - Autorise tout pour les administrateurs.
 * - Cible uniquement les capacités critiques : edit_post, delete_post, publish_post.
 * - Ne bloque que dans l’admin (is_admin()).
 * - Autorise l’édition uniquement si l’utilisateur est l’auteur du post.
 * - Bloque la création dans l’admin pour ces types de posts.
 *
 * @param array    $allcaps Capacités de l’utilisateur.
 * @param array    $cap     Capacité demandée.
 * @param array    $args    Arguments supplémentaires (dont post ID).
 * @param WP_User  $user    Objet utilisateur courant.
 * @return array   Capacités éventuellement modifiées.
 */
add_filter('user_has_cap', function ($allcaps, $cap, $args, $user) {
    // ✅ Autorise tout pour les administrateurs
    if (in_array('administrator', $user->roles, true)) {
        return $allcaps;
    }

    // Cibler uniquement certaines capacités critiques
    $actions_sensibles = ['edit_post', 'delete_post', 'publish_post'];
    if (!is_array($cap) || empty($cap) || !in_array($cap[0], $actions_sensibles, true)) {
        return $allcaps;
    }

    // ✅ Autorise les actions en front (ne bloque que l'admin)
    if (!is_admin()) {
        return $allcaps;
    }

    // 🔒 Si on édite un post existant dans l'admin
    $post_id = $args[2] ?? null;
    if ($post_id && is_numeric($post_id)) {
        $post_type   = get_post_type($post_id);
        $post_author = (int) get_post_field('post_author', $post_id);

        if (in_array($post_type, ['organisateur', 'chasse', 'enigme'], true)) {
            if ((int) $user->ID !== $post_author) {
                $allcaps[$cap[0]] = false;
            }
        }
    }

    // 🔒 Création via l'admin (pas de post ID)
    if ($post_id === null && isset($_GET['post_type'])) {
        $pt = sanitize_text_field($_GET['post_type']);
        if (in_array($pt, ['organisateur', 'chasse', 'enigme'], true)) {
            $allcaps[$cap[0]] = false;
        }
    }

    return $allcaps;
}, 10, 4);

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
 * 🔹 redirection_si_acces_refuse → Redirige si l’accès est refusé.
 * 🔹 blocage_acces_admin_non_admins (admin_init) → Empêche certains rôles d’accéder à wp-admin.
 * 🔹 Hooks load-post.php / load-post-new.php / admin_init
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


/**
 * Vérifie si un utilisateur peut créer ou modifier un post et redirige si l'accès est refusé.
 *
 * - Vérifie les permissions de création via `utilisateur_peut_creer_post()`.
 * - Vérifie les permissions de modification via `utilisateur_peut_modifier_post()`.
 * - Applique la redirection si l'accès est refusé.
 *
 * @param int|null $post_id ID du post (null si création d'un nouveau post).
 * @param string   $post_type Type de post concerné.
 * @param string   $redirect_url URL de redirection en cas d'accès refusé.
 */
function redirection_si_acces_refuse($post_id, $post_type, $redirect_url)
{
    if (!$post_type) {
        return;
    }

    $post_type = sanitize_text_field($post_type);
    $redirect_url = esc_url_raw($redirect_url);

    if (current_user_can('manage_options')) {
        return;
    }

    if (
        !is_user_logged_in() || ($post_id === null && !utilisateur_peut_creer_post($post_type)) ||
        ($post_id !== null && !utilisateur_peut_modifier_post($post_id))
    ) {

        wp_redirect(home_url($redirect_url));
        exit;
    }
}


/**
 * 🔐 Bloque l'accès complet au back-office pour les rôles organisateur et organisateur_creation,
 * sauf pour les appels AJAX et les accès à la médiathèque (wp-admin/upload.php via wp.media).
 *
 * @hook admin_init
 */
add_action('admin_init', function () {
    if (!is_user_logged_in()) {
        return;
    }

    $user = wp_get_current_user();
    $roles_bloques = [ROLE_ORGANISATEUR, ROLE_ORGANISATEUR_CREATION];

    // Autoriser AJAX, REST et média
    if (defined('DOING_AJAX') && DOING_AJAX) return;
    if (defined('REST_REQUEST') && REST_REQUEST) return;
    if (wp_doing_cron()) return;

    // Autoriser les admins
    if (in_array('administrator', (array) $user->roles, true)) {
        return;
    }

    // Si le rôle est bloqué et qu'on est dans wp-admin, redirection
    if (est_organisateur($user->ID)) {
        // Exception possible : autoriser upload.php pour wp.media
        $current_screen = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($current_screen, '/upload.php') !== false) {
            return;
        }

        wp_redirect(home_url('/'));
        exit;
    }
});


/**
 * Empêche les utilisateurs non autorisés de créer ou modifier un CPT.
 *
 * - Vérifie l'accès avec `redirection_si_acces_refuse()`.
 * - Gère les actions `load-post.php` (modification) et `load-post-new.php` (création).
 *
 * @action load-post.php, load-post-new.php
 */
add_action('load-post-new.php', function () {
    if (!is_admin() || !isset($_GET['post_type'])) {
        return;
    }

    $post_type = sanitize_text_field($_GET['post_type']);
    if (!$post_type) {
        return;
    }

    redirection_si_acces_refuse(null, $post_type, '/mon-compte/');
});

/**
 * Empêche les utilisateurs non autorisés d'accéder à l'écran de modification d'un post via l'admin (`post.php`).
 *
 * - Récupère l'ID du post via `$_GET['post']`
 * - Vérifie le type du post
 * - Utilise la fonction `redirection_si_acces_refuse()` pour appliquer les règles d'accès
 * - Redirige vers `/mon-compte/` si l'accès est refusé
 *
 * @hook load-post.php
 */
add_action('load-post.php', function () {
    if (!is_admin() || !isset($_GET['post'])) {
        return;
    }

    $post_id = (int) $_GET['post'];
    $post_type = get_post_type($post_id);

    redirection_si_acces_refuse($post_id, $post_type, '/mon-compte/');
});



// ==================================================
// 📂 ACCÈS AUX FICHIERS PROTÉGÉS
// ==================================================
/**
 * 🔹 (rewrite) /voir-fichier/ + handler voir-fichier.php → Point d’entrée sécurisé pour consulter les fichiers de solution.
 * 🔹 utilisateur_peut_voir_solution_enigme → Vérifie si l’utilisateur peut consulter la solution d’une énigme (PDF ou texte).
 * 🔹 (rewrite) /voir-image-enigme/ + handler voir-image-enigme.php → Sert les images protégées d’une énigme via proxy PHP.
 */


/**
 * Déclare un endpoint personnalisé `/voir-fichier/?id=1234`
 * 
 * Cet endpoint permet de sécuriser la consultation de fichiers PDF protégés
 * via un script PHP situé dans le thème (`inc/handlers/voir-fichier.php`).
 * 
 * Le fichier est servi uniquement si l’utilisateur est autorisé (admin, organisateur lié ou joueur ayant résolu).
 * 
 * 🔒 Le fichier réel n’est jamais exposé en URL publique. L’accès passe exclusivement par ce point.
 */
add_action('init', function () {
    add_rewrite_rule('^voir-fichier/?$', 'index.php?voir_fichier=1', 'top');
}, 1);

add_filter('query_vars', function ($vars) {
    $vars[] = 'voir_fichier';
    return $vars;
});

add_action('template_redirect', function () {
    if (get_query_var('voir_fichier') !== '1') return;

    $handler = get_stylesheet_directory() . '/inc/handlers/voir-fichier.php';
    if (file_exists($handler)) {
        require_once $handler;
        exit;
    }

    status_header(404);
    exit('Fichier de traitement non trouvé.');
});

add_action('init', function () {
    if (isset($_GET['voir_fichier'])) {
        cat_debug('[🔍 DEBUG] $_GET[voir_fichier] = ' . $_GET['voir_fichier']);
    }
});


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
 * 🔹 voir-image-enigme → Déclare un endpoint `/voir-image-enigme?id=123` pour servir une image protégée d’énigme.
 *
 * Cette règle permet de contourner la protection .htaccess sur les visuels
 * en servant l’image via un proxy PHP (`inc/handlers/voir-image-enigme.php`)
 * uniquement si l’utilisateur y a droit (via `utilisateur_peut_voir_enigme()`).
 */
add_action('init', function () {
    add_rewrite_rule('^voir-image-enigme/?$', 'index.php?voir_image_enigme=1', 'top');
    add_filter('query_vars', function ($vars) {
        $vars[] = 'voir_image_enigme';
        return $vars;
    });
});
/**
 * 🔁 Redirige les appels vers /voir-image-enigme?id=xxx vers le handler PHP sécurisé
 *
 * Le handler effectue les vérifications d’accès, puis sert le fichier s’il est autorisé.
 */
add_action('template_redirect', function () {
    if ((int) get_query_var('voir_image_enigme') !== 1) {
        return;
    }

    $handler = get_stylesheet_directory() . '/inc/handlers/voir-image-enigme.php';

    if (file_exists($handler)) {
        require_once $handler;
        exit;
    }

    status_header(404);
    exit('Image non trouvée.');
});



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
    $is_authenticated = is_user_logged_in();
    $user_id = $is_authenticated ? get_current_user_id() : 0;
    $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
    $is_riddle = $is_authenticated && $post_id > 0 && get_post_type($post_id) === 'enigme';
    $prerequisites = $is_riddle ? get_field('enigme_acces_pre_requis', $post_id) : [];
    $prerequisite_ids = is_array($prerequisites) ? $prerequisites : [];
    $service = new ChassesAuTresor\Core\Content\RiddlePrerequisiteService();
    $error = $service->getConditionUpdateError(
        $is_authenticated,
        $is_riddle,
        $is_riddle && (int) get_post_field('post_author', $post_id) === $user_id,
        $prerequisite_ids
    );

    if ($error !== null) {
        $messages = [
            ChassesAuTresor\Core\Content\RiddlePrerequisiteService::ERROR_UNAUTHENTICATED =>
                __('Utilisateur non connecté.', 'chassesautresor-com'),
            ChassesAuTresor\Core\Content\RiddlePrerequisiteService::ERROR_INVALID_RIDDLE =>
                __('Identifiant ou type de contenu invalide.', 'chassesautresor-com'),
            ChassesAuTresor\Core\Content\RiddlePrerequisiteService::ERROR_FORBIDDEN =>
                __('Accès refusé.', 'chassesautresor-com'),
            ChassesAuTresor\Core\Content\RiddlePrerequisiteService::ERROR_MISSING_PREREQUISITES =>
                __('Aucun prérequis sélectionné.', 'chassesautresor-com'),
        ];
        wp_send_json_error($messages[$error]);
    }

    update_field('enigme_acces_condition', 'pre_requis', $post_id);

    wp_send_json_success(__('Condition « prérequis » enregistrée.', 'chassesautresor-com'));
}
add_action('wp_ajax_verifier_et_enregistrer_condition_pre_requis', 'verifier_et_enregistrer_condition_pre_requis');



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



/**
 * Autorise la consultation des énigmes non publiées pour les organisateurs
 * associés.
 *
 * Les rôles "organisateur" et "organisateur_creation" peuvent voir les
 * énigmes en statut "pending" ou "draft" sans disposer du lien de prévisualisation.
 *
 * @hook pre_get_posts
 */
add_action('pre_get_posts', function ($query) {
    if (is_admin() || !$query->is_main_query()) {
        return;
    }

    if (!$query->is_singular('enigme') && !$query->is_singular('chasse')) {
        return;
    }

    $is_authenticated = is_user_logged_in();
    $is_administrator = $is_authenticated && current_user_can('manage_options');
    $service = new ChassesAuTresor\Core\Content\ContentQueryAccessService();
    $statuses = $service->getVisibleStatuses(
        $is_authenticated,
        $is_administrator,
        $is_authenticated && !$is_administrator && est_organisateur()
    );

    if ($statuses !== []) {
        $query->set('post_status', $statuses);
    }
});
