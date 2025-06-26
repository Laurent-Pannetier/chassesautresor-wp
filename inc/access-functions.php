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

    return in_array(ROLE_ORGANISATEUR, $roles, true)
        || in_array(ROLE_ORGANISATEUR_CREATION, $roles, true);
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
    if (!is_user_logged_in()) {
        return false;
    }

    if (current_user_can('manage_options')) {
        return true;
    }

    $user_id = get_current_user_id();
    $user_roles = wp_get_current_user()->roles;

    switch ($post_type) {
        case 'organisateur':
            // 🔍 Vérifie si l'utilisateur a déjà un CPT "organisateur"
            $organisateur_id = get_organisateur_from_user($user_id);
            if ($organisateur_id) {
                return false; // ❌ Refus si un organisateur existe déjà
            }

            // ✅ Un abonné sans organisateur peut en créer un
            return true;

        case 'chasse':
            // 🔍 Vérifie si l'utilisateur est rattaché à un CPT "organisateur"
            if (!get_organisateur_from_user($user_id)) {
                return false; // ❌ Refus si l'utilisateur n'a pas de CPT "organisateur"
            }

            if (in_array(ROLE_ORGANISATEUR, $user_roles, true)) {
                return true; // ✅ Un organisateur peut créer plusieurs chasses
            }

            // 🔍 Vérifier si l'abonné a déjà une chasse en cours
            $user_chasses = get_posts([
                'post_type'   => 'chasse',
                'post_status' => 'any',
                'author'      => $user_id,
                'fields'      => 'ids',
            ]);

            return empty($user_chasses); // ❌ Refus si l'utilisateur a déjà une chasse

        case 'enigme':
            // 🔍 Déterminer l'ID de la chasse :
            // - Priorité à `$chasse_id` s'il est passé en argument
            // - Sinon, récupération depuis l'URL via $_GET
            if (!$chasse_id) {
                $chasse_id = filter_input(INPUT_GET, 'chasse_associee', FILTER_VALIDATE_INT);
            }

            // 🔍 Vérifier que l'ID est valide et que c'est bien un CPT "chasse"
            if (!$chasse_id || get_post_type($chasse_id) !== 'chasse') {
                return false;
            }

            // 🔍 Vérifier que l'utilisateur est bien rattaché à cette chasse
            $organisateur_chasse = get_organisateur_from_chasse($chasse_id);
            $organisateur_user = get_organisateur_from_user($user_id);

            if (!$organisateur_chasse || !$organisateur_user || $organisateur_chasse !== $organisateur_user) {
                return false;
            }

            // ✅ Vérifier que la chasse est en "création"
            $champs_caches = get_field('champs_caches', $chasse_id);
            return trim($champs_caches['statut_validation'] ?? '') === 'creation';
    }

    return false;
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
    if (!is_user_logged_in() || !$post_id) {
        error_log('❌ utilisateur_peut_modifier_post: utilisateur non connecté ou post_id invalide');
        return false;
    }

    $user_id = get_current_user_id();
    $post_type = get_post_type($post_id);

    switch ($post_type) {
        case 'organisateur':
            $associes = get_field('utilisateurs_associes', $post_id);
            $associes = is_array($associes) ? array_map('strval', $associes) : [];

            $match = in_array((string) $user_id, $associes, true);

            // Autoriser également l'auteur du post à modifier
            $auteur = (int) get_post_field('post_author', $post_id);

            return $match || $auteur === $user_id;

        case 'chasse':
            $organisateur_id = get_organisateur_from_chasse($post_id);
            return $organisateur_id ? utilisateur_peut_modifier_post($organisateur_id) : false;

        case 'enigme':
            $chasse_id = recuperer_id_chasse_associee($post_id);
            $organisateur_id = $chasse_id ? get_organisateur_from_chasse($chasse_id) : null;
            return $organisateur_id ? utilisateur_peut_modifier_post($organisateur_id) : false;

        default:
            error_log("❌ utilisateur_peut_modifier_post: post_type inconnu ($post_type)");
            return false;
    }
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
        error_log("❌ [voir énigme] post #$enigme_id n'est pas une énigme.");
        return false;
    }

    $post_status   = get_post_status($enigme_id);
    $etat_systeme  = get_field('enigme_cache_etat_systeme', $enigme_id);
    $user_id       = $user_id ?? get_current_user_id();

    error_log("🔎 [voir énigme] #$enigme_id | statut = $post_status | etat = $etat_systeme | user_id = $user_id");

    // 🔓 Administrateur → accès total
    if (current_user_can('administrator')) {
        error_log("✅ [voir énigme] accès admin");
        return true;
    }

    // 🔍 Anonyme ou abonné : uniquement publish + accessible
    if (!is_user_logged_in() || in_array('abonne', wp_get_current_user()->roles, true)) {
        $autorise = ($post_status === 'publish') && ($etat_systeme === 'accessible');
        error_log("👤 [voir énigme] visiteur/abonné → accès " . ($autorise ? 'OK' : 'REFUSÉ'));
        return $autorise;
    }

    if ($post_status === 'draft') {
        error_log("❌ [voir énigme] brouillon interdit pour utilisateur #$user_id");
        return false;
    }

    // 🎯 Chasse liée
    $chasse_id = recuperer_id_chasse_associee($enigme_id);
    if (!$chasse_id) {
        error_log("❌ [voir énigme] pas de chasse associée");
        return false;
    }

    // 🔐 L’utilisateur doit être lié à l’organisateur de la chasse
    if (!utilisateur_est_organisateur_associe_a_chasse($user_id, $chasse_id)) {
        error_log("❌ [voir énigme] user #$user_id n'est pas lié à la chasse #$chasse_id");
        return false;
    }

    // ✅ Exception organisateur : accès si chasse en création, correction
    //    ou en attente de validation
    $champs_caches = get_field('champs_caches', $chasse_id);
    $statut_validation = $champs_caches['chasse_cache_statut_validation'] ?? null;
    error_log("🧪 [voir énigme] chasse #$chasse_id → statut_validation = $statut_validation");

    if (in_array($statut_validation, ['creation', 'correction', 'en_attente'], true)) {
        $autorise = in_array($post_status, ['publish', 'pending'], true);
        error_log("🟡 [voir énigme] organisateur → chasse = $statut_validation → accès " . ($autorise ? 'OK' : 'REFUSÉ'));
        return $autorise;
    }

    // ✅ Cas standard : uniquement publish + accessible
    $autorise = ($post_status === 'publish') && ($etat_systeme === 'accessible');
    error_log("🟠 [voir énigme] cas standard → accès " . ($autorise ? 'OK' : 'REFUSÉ'));
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
    if (get_post_type($chasse_id) !== 'chasse') {
        error_log("❌ [ajout énigme] ID $chasse_id n'est pas une chasse.");
        return false;
    }

    $user_id = $user_id ?? get_current_user_id();
    if (!$user_id || !is_user_logged_in()) {
        error_log("❌ [ajout énigme] utilisateur non connecté.");
        return false;
    }

    if (!est_organisateur($user_id)) {
        error_log("❌ [ajout énigme] rôle utilisateur #$user_id invalide");
        return false;
    }

    $cache = get_field('champs_caches', $chasse_id);
    $statut_validation = $cache['chasse_cache_statut_validation'] ?? null;
    $statut_metier     = $cache['chasse_cache_statut'] ?? null;

    if ($statut_metier !== 'revision') {
        error_log("❌ [ajout énigme] chasse #$chasse_id statut metier : $statut_metier");
        return false;
    }

    if (!in_array($statut_validation, ['creation', 'correction'], true)) {
        error_log("❌ [ajout énigme] chasse #$chasse_id statut validation : $statut_validation");
        return false;
    }

    $est_associe = utilisateur_est_organisateur_associe_a_chasse($user_id, $chasse_id);
    if (!$est_associe) {
        error_log("❌ [ajout énigme] utilisateur #$user_id non associé à la chasse #$chasse_id");
        return false;
    }

    $ids = recuperer_ids_enigmes_pour_chasse($chasse_id);
    $nb = count($ids);

    if ($nb >= 40) {
        error_log("❌ [ajout énigme] chasse #$chasse_id a déjà $nb énigmes (limite 40)");
        return false;
    }

    error_log("✅ [ajout énigme] autorisé pour user #$user_id sur chasse #$chasse_id ($nb / 40)");
    return true;
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
    if (get_post_type($enigme_id) !== 'enigme') return false;
    $user_id = $user_id ?? get_current_user_id();

    // Admin → accès total
    if (user_can($user_id, 'administrator')) return true;

    // Récupérer la chasse associée
    $chasse_id = recuperer_id_chasse_associee($enigme_id);
    if (!$chasse_id || get_post_type($chasse_id) !== 'chasse') return false;

    // Récupérer l'état de validation de la chasse
    $champs_caches = get_field('champs_caches', $chasse_id);
    $statut_validation = $champs_caches['chasse_cache_statut_validation'] ?? null;


    // L'utilisateur doit être associé à l'organisateur de la chasse
    return utilisateur_est_organisateur_associe_a_chasse($user_id, $chasse_id);
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
    if (get_post_type($enigme_id) !== 'enigme') {
        return false;
    }

    $user_id = $user_id ?? get_current_user_id();
    if (!$user_id) {
        return false;
    }

    if (!est_organisateur($user_id)) {
        return false;
    }

    $chasse_id = recuperer_id_chasse_associee($enigme_id);
    if (!$chasse_id || get_post_type($chasse_id) !== 'chasse') {
        return false;
    }

    $cache = get_field('champs_caches', $chasse_id);
    $statut_validation = $cache['chasse_cache_statut_validation'] ?? null;
    $statut_metier     = $cache['chasse_cache_statut'] ?? null;

    if ($statut_metier !== 'revision') {
        return false;
    }

    if (!in_array($statut_validation, ['creation', 'correction'], true)) {
        return false;
    }

    return utilisateur_est_organisateur_associe_a_chasse($user_id, $chasse_id);
}


/**
 * Vérifie si un utilisateur peut ajouter une nouvelle chasse à un organisateur donné.
 *
 * @param int $organisateur_id
 * @return bool
 */
function utilisateur_peut_ajouter_chasse(int $organisateur_id): bool
{
    if (!is_user_logged_in()) {
        return false;
    }

    $user       = wp_get_current_user();
    $roles      = (array) $user->roles;
    $user_id    = (int) $user->ID;

    // Administrateur → accès total
    if (in_array('administrator', $roles, true)) {
        return true;
    }

    // L'utilisateur doit être lié à l'organisateur
    if (!utilisateur_peut_modifier_post($organisateur_id)) {
        return false;
    }

    // Organisateur : aucune limite
    if (in_array(ROLE_ORGANISATEUR, $roles, true)) {
        return true;
    }

    // Organisateur en cours de création : uniquement si aucune chasse existante
    if (in_array(ROLE_ORGANISATEUR_CREATION, $roles, true)) {
        return !organisateur_a_des_chasses($organisateur_id);
    }

    return false;
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
    if (!is_user_logged_in()) {
        return false;
    }

    $user  = wp_get_current_user();

    if (!est_organisateur($user->ID)) {
        return false;
    }

    if (!utilisateur_peut_modifier_post($post_id)) {
        return false; // Vérifie la liaison utilisateur ↔ CPT
    }

    $type   = get_post_type($post_id);
    $status = get_post_status($post_id);

    switch ($type) {
        case 'organisateur':
            return in_array($status, ['publish', 'pending'], true);

        case 'chasse':
            $cache = get_field('champs_caches', $post_id);
            $val   = $cache['chasse_cache_statut_validation'] ?? '';

            return in_array($status, ['publish', 'pending'], true) && $val !== 'banni';

        case 'enigme':
            $etat = get_field('enigme_cache_etat_systeme', $post_id);

            return in_array($status, ['publish', 'pending'], true) && $etat !== 'cache_invalide';
    }

    return false;
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
    if (!utilisateur_peut_voir_panneau($post_id)) {
        return false;
    }

    $type   = get_post_type($post_id);
    $status = get_post_status($post_id);

    $user  = wp_get_current_user();
    $roles = (array) $user->roles;

    switch ($type) {
        case 'organisateur':
            return in_array(ROLE_ORGANISATEUR_CREATION, $roles, true) && $status === 'pending';

        case 'chasse':
            $cache   = get_field('champs_caches', $post_id);
            $val     = $cache['chasse_cache_statut_validation'] ?? '';
            $stat    = $cache['chasse_cache_statut'] ?? '';
            $complet = (bool) get_field('chasse_cache_complet', $post_id);

            // Les organisateurs en cours de création peuvent éditer toute chasse
            // incomplète tant qu'elle est en attente de validation.
            if (in_array(ROLE_ORGANISATEUR_CREATION, $roles, true)) {
                return $status === 'pending' && !$complet;
            }

            return $status === 'pending'
                && $stat === 'revision'
                && in_array($val, ['creation', 'correction'], true);

        case 'enigme':
            $chasse_id = recuperer_id_chasse_associee($post_id);
            if (!$chasse_id) {
                return false;
            }

            $chasse_status = get_post_status($chasse_id);
            $cache         = get_field('champs_caches', $chasse_id);
            $val           = $cache['chasse_cache_statut_validation'] ?? '';
            $stat          = $cache['chasse_cache_statut'] ?? '';
            $etat          = get_field('enigme_cache_etat_systeme', $post_id);

            return $chasse_status === 'pending'
                && $stat === 'revision'
                && in_array($val, ['creation', 'correction'], true)
                && $etat === 'bloquee_chasse';
    }

    return false;
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
    if (!$post_id || !is_user_logged_in()) return false;

    if (!$user_id) {
        $user_id = get_current_user_id();
    }

    $post_type = get_post_type($post_id);
    $status = get_post_status($post_id);
    $roles = wp_get_current_user()->roles;

    // 🔐 L'utilisateur doit être autorisé à modifier le post
    if (!utilisateur_peut_modifier_post($post_id)) {
        return false;
    }

    // 💡 Exemple : titre de chasse non modifiable après publication
    if ($post_type === 'chasse' && $champ === 'post_title') {
        return $status !== 'publish';
    }

    // 🔒 Le nom d'organisateur est verrouillé sauf pour certaines étapes de création
    if ($post_type === 'organisateur' && $champ === 'post_title') {
        // Administrateurs : accès illimité
        if (current_user_can('manage_options')) {
            return true;
        }

        // Rôle organisateur_creation : titre éditable si l'organisateur est en cours de création
        if (in_array(ROLE_ORGANISATEUR_CREATION, $roles, true) && $status === 'pending') {
            $chasses_query = get_chasses_de_organisateur($post_id);
            $nb_chasses    = is_a($chasses_query, 'WP_Query') ? $chasses_query->post_count : 0;

            // Aucune chasse ou une seule chasse en cours de création
            if ($nb_chasses === 0) {
                return true;
            }

            if ($nb_chasses === 1) {
                $en_creation = get_chasses_en_creation($post_id);
                if (count($en_creation) === 1) {
                    return true;
                }
            }
        }

        return false;
    }

    // 🔓 Le titre d'une énigme est éditable tant que l'utilisateur peut
    // modifier l'énigme via l'édition frontale.
    if ($post_type === 'enigme' && $champ === 'post_title') {
        return true;
    }

    // ⚠️ Autres règles spécifiques à définir manuellement ensuite
    // Exemple :
    // if ($champ === 'caracteristiques.chasse_infos_date_debut') {
    //     return in_array($status, ['draft', 'pending']);
    // }

    return true; // Par défaut : champ éditable
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
        error_log('[🔍 DEBUG] $_GET[voir_fichier] = ' . $_GET['voir_fichier']);
    }
});


/**
 * Vérifie si un utilisateur a le droit de consulter la solution (PDF ou texte) d'une énigme
 *
 * @param int $enigme_id ID du post énigme
 * @param int $user_id   ID de l'utilisateur connecté
 * @return bool
 */
function utilisateur_peut_voir_solution_enigme(int $enigme_id, int $user_id): bool
{
    if (!$enigme_id || !$user_id) return false;

    // 🔐 Autorisation admin
    if (user_can($user_id, 'manage_options')) return true;

    // 🔍 Récupère la chasse liée
    $chasse_id = recuperer_id_chasse_associee($enigme_id);
    if (!$chasse_id) return false;

    // 🔒 Organisateur lié à la chasse
    if (utilisateur_est_organisateur_associe_a_chasse($user_id, $chasse_id)) {
        return true;
    }

    // 🧩 Joueur ayant résolu l’énigme (statut stocké en base)
    $statut = get_statut_utilisateur_enigme($user_id, $enigme_id);
    if ($statut) {
        return in_array($statut, ['resolue', 'terminee'], true);
    }

    return false;
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
    if (get_query_var('voir_image_enigme') !== '1') return;

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
    if (!$chasse_id) return [];

    $associees = recuperer_enigmes_associees($chasse_id);
    $filtrees = [];

    foreach ($associees as $id) {
        if ((int) $id === (int) $enigme_id) continue;

        $mode = get_field('enigme_mode_validation', $id);
        if (in_array($mode, ['manuelle', 'automatique'])) {
            $filtrees[] = $id;
        }
    }

    return $filtrees;
}


/** 
 * @hook wp_ajax_verifier_et_enregistrer_condition_pre_requis
 * @return void (JSON)
 */
function verifier_et_enregistrer_condition_pre_requis()
{
    if (!is_user_logged_in()) {
        wp_send_json_error('non_connecte');
    }

    $user_id = get_current_user_id();
    $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;

    if (!$post_id || get_post_type($post_id) !== 'enigme') {
        wp_send_json_error('ID ou type invalide');
    }

    $auteur = (int) get_post_field('post_author', $post_id);
    if ($auteur !== $user_id) {
        wp_send_json_error('Accès refusé');
    }

    $pre_requis = get_field('enigme_acces_pre_requis', $post_id);
    $ids = is_array($pre_requis) ? array_filter($pre_requis) : [];

    if (empty($ids)) {
        wp_send_json_error('Aucun prérequis sélectionné');
    }

    // ✔️ Mise à jour de la condition d'accès si au moins 1 est présent
    update_field('enigme_acces_condition', 'pre_requis', $post_id);

    wp_send_json_success('Condition "pré-requis" enregistrée');
}
add_action('wp_ajax_verifier_et_enregistrer_condition_pre_requis', 'verifier_et_enregistrer_condition_pre_requis');



// ==================================================
// 📌 VISIBILITÉ ET AFFICHAGE (RÉSERVÉ FUTUR)
// ==================================================

/*Préparer un bloc vide commenté pour y ajouter par exemple :
enigme_est_affichable_pour_joueur() (à venir)
get_cta_enigme() (à déplacer ici si elle migre du fichier visuel)
tout helper type est_cliquable, affiche_indice, etc. */

/**
 * Détermine si une chasse doit être visible pour un utilisateur.
 *
 * @param int $chasse_id ID de la chasse.
 * @param int $user_id   ID de l'utilisateur.
 * @return bool          True si visible, false sinon.
 */
function chasse_est_visible_pour_utilisateur(int $chasse_id, int $user_id): bool
{
    $status = get_post_status($chasse_id);
    if (!in_array($status, ['pending', 'publish'], true)) {
        return false;
    }

    $cache      = get_field('champs_caches', $chasse_id) ?: [];
    $validation = $cache['chasse_cache_statut_validation'] ?? '';

    if ($status === 'pending') {
        $assoc = utilisateur_est_organisateur_associe_a_chasse($user_id, $chasse_id);

        return $validation !== 'banni'
            && $assoc
            && est_organisateur($user_id);
    }

    return $validation !== 'banni';

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

    if ($query->is_singular('enigme') && is_user_logged_in()) {
        if (current_user_can('manage_options')) {
            $query->set('post_status', ['publish', 'pending', 'draft']);
        } elseif (est_organisateur()) {
            $query->set('post_status', ['publish', 'pending']);
        }
    }

    if ($query->is_singular('chasse') && is_user_logged_in()) {
        if (current_user_can('manage_options')) {
            $query->set('post_status', ['publish', 'pending', 'draft']);
        } elseif (est_organisateur()) {
            $query->set('post_status', ['publish', 'pending']);
        }
    }
});
