<?php
defined( 'ABSPATH' ) || exit;

// ==================================================
// 📚 SOMMAIRE DU FICHIER : access-functions.php
// ==================================================
//
//  📦 RESTRICTIONS GLOBALES
//    - Limiter l’accès à la médiathèque et à l’éditeur Gutenberg pour les non-admins.
//
//  📦 RESTRICTIONS CHAMPS META
//    - Masquer ou rendre en lecture seule certains champs ACF selon le rôle utilisateur.
//
//  📦 RESTRICTIONS POSTS
//    - Restreindre la création ou la modification de certains CPT selon le rôle utilisateur.
//
//  🔓 CONTRÔLE D’ACCÈS AU CONTENU DES ÉNIGMES
//
//  🔒 GESTION DES CONDITIONS D’ACCÈS – PRÉREQUIS


// ==================================================
// 📦 RESTRICTIONS GLOBALES & ENDPOINTS SÉCURISÉS
// ==================================================
/**
 * 🔹 restreindre_media_library_tous_non_admins → Restreint l’accès à la médiathèque aux seuls fichiers de l’utilisateur connecté.
 * 🔹 disable_gutenberg_for_non_admins → Force l’éditeur classique pour tous les rôles sauf administrateur.
 * 🔹 (rewrite) /voir-fichier/ → Point d’entrée sécurisé pour servir un fichier PDF de solution via un script PHP (handlers/voir-fichier.php).
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
function restreindre_media_library_tous_non_admins($query) {
    $user = wp_get_current_user();

    // Vérifie si l'utilisateur est connecté et N'EST PAS administrateur
    if ($user->exists() && !in_array('administrator', (array) $user->roles)) {
        $query['author'] = $user->ID; // Limite l'affichage aux fichiers de l'utilisateur connecté
    }

    return $query;
}
add_filter('ajax_query_attachments_args', 'restreindre_media_library_tous_non_admins');


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
function disable_gutenberg_for_non_admins($use_block_editor, $post) {
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




// ==================================================
// 📦 RESTRICTIONS CHAMPS META
// ==================================================
/**
 * 
 * 🔹 champ_est_editable
 * 🔹 filtrer_acf_pour_non_admins_globales → Restriction d'accès aux métadonnées ACF Globales
 * 🔹 filtrer_acf_pour_non_admins_joueurs → Masquer complètement un champ ACF pour tous les rôles sauf administrateur.
 * 🔹 filtrer_acf_pour_non_admins_chasses → Restreindre l’accès aux métadonnées ACF des chasses.
 * 🔹 rendre_champs_acf_chasse_readonly → Rendre les champs ACF des chasses en lecture seule.
 * 🔹 rendre_champs_acf_joueur_readonly → Rendre certains champs ACF en lecture seule pour les joueurs.
 * 🔹 $champs_restrict_acf → Liste des clés de champs ACF à masquer pour les non-administrateurs.
 * 🔹 filtrer_acf_pour_non_admins_enigmes → Filtrer l’affichage des champs ACF des énigmes pour qu’ils soient visibles uniquement par les administrateurs.
 * 🔹 rendre_champs_acf_enigme_readonly → Rendre les champs ACF des énigmes en lecture seule pour éviter toute modification manuelle.
 * 🔹 cacher_champs_acf_enigme_non_admins → Masquer les champs ACF des énigmes pour les non-admins.
 */


/**
 * Vérifie si un champ donné est éditable pour un utilisateur donné sur un post donné.
 *
 * @param string $champ Nom du champ ACF ou champ natif (ex : post_title)
 * @param int $post_id ID du post (CPT organisateur, chasse, etc.)
 * @param int|null $user_id ID utilisateur (par défaut : utilisateur connecté)
 * @return bool True si le champ est éditable, False sinon
 */
function champ_est_editable($champ, $post_id, $user_id = null) {
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

    // ⚠️ Autres règles spécifiques à définir manuellement ensuite
    // Exemple :
    // if ($champ === 'caracteristiques.chasse_infos_date_debut') {
    //     return in_array($status, ['draft', 'pending']);
    // }

    return true; // Par défaut : champ éditable
}




/**
 * 📌 Restriction d'accès aux métadonnées ACF Globales.
 * 🔹 Seuls les administrateurs peuvent voir et modifier ces champs.
 */
function filtrer_acf_pour_non_admins_globales($field) { 

    // Vérifie si l'utilisateur N'EST PAS administrateur
    if (!current_user_can('administrator')) {
        return false; // 🚫 Masque complètement le champ pour tous sauf admin
    }
    return $field; // ✅ Affiche normalement le champ pour les administrateurs
}

// 📌 Appliquer la restriction d'accès aux méta globales
add_filter('acf/prepare_field/key=field_prix_moyen_point_mensuel', 'filtrer_acf_pour_non_admins_globales');
add_filter('acf/prepare_field/key=field_total_points_depenses_mois', 'filtrer_acf_pour_non_admins_globales');
add_filter('acf/prepare_field/key=field_total_points_vendus_mensuel', 'filtrer_acf_pour_non_admins_globales');
add_filter('acf/prepare_field/key=field_total_points_generes_mensuel', 'filtrer_acf_pour_non_admins_globales');
add_filter('acf/prepare_field/key=field_revenu_total_site', 'filtrer_acf_pour_non_admins_globales');
add_filter('acf/prepare_field/key=field_total_points_en_circulation', 'filtrer_acf_pour_non_admins_globales');
add_filter('acf/prepare_field/key=field_total_paiements_effectues_mensuel', 'filtrer_acf_pour_non_admins_globales');


/**
 * 🚫 Masquer complètement un champ ACF pour tous les rôles sauf administrateur.
 *
 * Cette fonction utilise le filtre `acf/load_field` pour **retourner `null`** si l’utilisateur
 * n’a pas le rôle `administrator`, ce qui empêche le champ de s’afficher dans l’interface ACF.
 *
 * ✔️ Usage adapté si le champ ne doit **jamais** être vu ni édité par un non-admin.
 * ⚠️ Attention :
 * - Si le champ est requis dans le groupe ACF, cela peut provoquer une erreur de validation.
 * - Si la fonction est appliquée à tous les champs via `acf/load_field` sans ciblage précis,
 *   elle peut affecter plus que prévu.
 *
 * @param array $field Le champ ACF en cours de chargement.
 * @return array|null Le champ original s’il est affiché, ou `null` pour le masquer.
 *
 * @hook acf/load_field
 */
function filtrer_acf_pour_non_admins_joueurs($field) {
    if (!current_user_can('administrator')) {
        return null;
    }
    return $field;
}
 
 /**
 * 📌 Restriction d'accès aux métadonnées ACF des chasses.
 * 🔹 Seuls les administrateurs peuvent voir et modifier ces champs.
 */
function filtrer_acf_pour_non_admins_chasses($field) { 

    // Vérifie si l'utilisateur N'EST PAS administrateur
    if (!current_user_can('administrator')) {
        return false; // 🚫 Masque complètement le champ pour tous sauf admin
    }
    return $field; // ✅ Affiche normalement le champ pour les administrateurs
}
// 📌 Appliquer la restriction d'accès aux méta des chasses
add_filter('acf/prepare_field/key=field_total_tentatives_chasse', 'filtrer_acf_pour_non_admins_chasses');
add_filter('acf/prepare_field/key=field_total_indices_debloques_chasse', 'filtrer_acf_pour_non_admins_chasses');
add_filter('acf/prepare_field/key=field_total_points_depenses_chasse', 'filtrer_acf_pour_non_admins_chasses');
add_filter('acf/prepare_field/key=field_total_joueurs_ayant_resolu_chasse', 'filtrer_acf_pour_non_admins_chasses');
add_filter('acf/prepare_field/key=field_total_joueurs_souscription_chasse', 'filtrer_acf_pour_non_admins_chasses');

/**
 * 📌 Rend les champs ACF des chasses en lecture seule pour éviter toute modification manuelle.
 */
function rendre_champs_acf_chasse_readonly($field) {
    $champs_readonly = [
        'total_tentatives_chasse',
        'total_indices_debloques_chasse',
        'total_points_depenses_chasse',
        'total_points_gagnes_chasse',
        'progression_chasse',
        'total_joueurs_souscription_chasse'
    ];

    if (in_array($field['name'], $champs_readonly)) {
        $field['readonly'] = 1; // 🛑 Désactive la modification dans l'admin
    }

    return $field;
}
// Applique la restriction de modification aux champs des chasses
add_filter('acf/load_field', 'rendre_champs_acf_chasse_readonly');

/**
 * 🔒 Rendre certains champs ACF en lecture seule pour les joueurs.
 *
 * Cette fonction rend non modifiables certains champs spécifiques liés aux statistiques
 * des joueurs en les marquant comme `readonly` et `disabled` dans l'interface ACF.
 *
 * Elle agit lors du chargement de **tous les champs** (`acf/load_field`), et cible uniquement
 * ceux dont le nom correspond à une des clés listées dans `$champs_proteges`.
 *
 * Liste des champs protégés :
 * - total_enigmes_jouees
 * - total_enigmes_trouvees
 * - total_chasses_terminees
 * - total_indices_debloques
 * - total_points_depenses
 *
 * @param array $field Le tableau de configuration du champ ACF en cours de chargement.
 * @return array Le tableau modifié avec les attributs `readonly` et `disabled` si applicable.
 *
 * @hook acf/load_field
 */
function rendre_champs_acf_joueur_readonly($field) {
    $champs_proteges = [
        'total_enigmes_jouees',
        'total_enigmes_trouvees',
        'total_chasses_terminees',
        'total_indices_debloques',
        'total_points_depenses',
    ];

    if (in_array($field['name'], $champs_proteges)) {
        $field['readonly'] = 1; // Rend le champ non modifiable
        $field['disabled'] = 1; // Désactive aussi la modification côté HTML
    }

    return $field;
}
add_filter('acf/load_field', 'rendre_champs_acf_joueur_readonly');

/**
 * 🔒 Masquer sélectivement certains champs ACF pour les non-administrateurs.
 *
 * Cette section permet de restreindre l'affichage de plusieurs champs ACF sensibles
 * (notamment liés aux statistiques) uniquement aux administrateurs.
 *
 * Le tableau `$champs_restrict_acf` contient une liste de clés ACF (pas les noms, mais les `field_key`)
 * pour lesquelles le filtre `acf/prepare_field` est appliqué.
 *
 * 🔁 Chaque clé du tableau est associée au filtre :
 *     acf/prepare_field/key={field_key}
 * ce qui permet de cibler précisément un champ, et de le masquer dynamiquement
 * via la fonction `filtrer_acf_pour_non_admins_joueurs()` si l’utilisateur n’est pas admin.
 *
 * 🛡️ Cela permet :
 * - d’éviter les risques de suppression de données sensibles
 * - de garder certains champs invisibles aux profils éditeurs, organisateurs, etc.
 *
 * @see filtrer_acf_pour_non_admins_joueurs()
 */
$champs_restrict_acf = [
    'field_total_enigmes_jouees',
    'field_total_enigmes_trouvees',
    'field_total_chasses_terminees',
    'field_total_indices_debloques',
    'field_total_points_depenses',
    'field_67c236843d164', // total_tentatives_enigme
    'field_67c24e7dc6463', //total_indices_debloques_enigme
    'field_67c2557a1dc0c', //total_points_depenses_enigme
    'field_67c259e147a06', //total_joueurs_ayant_resolu_enigme
    'field_67c25adbf1f53', //total_joueurs_souscription_enigme
    'field_67b58e000b389', // chasse_associee dans cpt enigme
    'field_67da9d7167acc', //groupe cache avec shortcodes dedans
];

// 📌 Appliquer la restriction d'accès à tous les champs listés
foreach ($champs_restrict_acf as $champ_key) {
    add_filter("acf/prepare_field/key={$champ_key}", 'filtrer_acf_pour_non_admins_joueurs');
}

/**
 * 📌 Filtre l'affichage des champs ACF des énigmes pour qu'ils soient visibles UNIQUEMENT par les administrateurs.
 *
 * 🔹 Vérifie si l'utilisateur a le rôle "administrator".
 * 🔹 Si ce n'est pas un administrateur, il ne pourra pas voir ni modifier les champs ACF des méta d'énigmes.
 * 🔹 Seuls les administrateurs conservent l'accès.
 *
 * @param array $field Les données du champ ACF en cours d'affichage.
 * @return array|false Retourne le champ normalement pour les administrateurs, sinon false (masque le champ).
 */
function filtrer_acf_pour_non_admins_enigmes($field) { 

    // Vérifie si l'utilisateur N'EST PAS administrateur
    if (!current_user_can('administrator')) {
        return false; // 🚫 Masque complètement le champ pour tous sauf admin
    }
    return $field; // ✅ Affiche normalement le champ pour les administrateurs
}

// 📌 Applique le filtre à chaque champ méta des énigmes pour restreindre l'accès aux seuls admins
add_filter('acf/prepare_field/key=field_total_tentatives_enigme', 'filtrer_acf_pour_non_admins_enigmes');
add_filter('acf/prepare_field/key=field_total_indices_debloques_enigme', 'filtrer_acf_pour_non_admins_enigmes');
add_filter('acf/prepare_field/key=field_total_points_depenses_enigme', 'filtrer_acf_pour_non_admins_enigmes');
add_filter('acf/prepare_field/key=field_total_joueurs_ayant_resolu_enigme', 'filtrer_acf_pour_non_admins_enigmes');
add_filter('acf/prepare_field/key=field_total_joueurs_souscription_enigme', 'filtrer_acf_pour_non_admins_enigmes');

/**
 * 📌 Rend les champs ACF des énigmes en lecture seule pour éviter toute modification manuelle.
 */
function rendre_champs_acf_enigme_readonly($field) {
    $champs_readonly = [
        'total_tentatives_enigme',
        'total_indices_debloques_enigme',
        'total_points_depenses_enigme',
        'total_joueurs_ayant_resolu_enigme',
        'total_joueurs_souscription_enigme'
    ];

    if (in_array($field['name'], $champs_readonly)) {
        $field['readonly'] = 1; // 🛑 Désactive la modification dans l'admin
    }

    return $field;
}
// Applique la restriction de modification aux champs des énigmes
add_filter('acf/load_field', 'rendre_champs_acf_enigme_readonly');


/**
 * 📌 Masque les champs ACF des énigmes pour les non-admins.
 */
function cacher_champs_acf_enigme_non_admins($field) {
    if (!current_user_can('administrator')) {
        return false; // 🚫 Cache le champ pour les rôles non-admin
    }
    return $field;
}

// Applique la restriction de visibilité aux champs ACF des énigmes
add_filter('acf/prepare_field/key=field_total_tentatives_enigme', 'cacher_champs_acf_enigme_non_admins');
add_filter('acf/prepare_field/key=field_total_indices_debloques_enigme', 'cacher_champs_acf_enigme_non_admins');
add_filter('acf/prepare_field/key=field_total_points_depenses_enigme', 'cacher_champs_acf_enigme_non_admins');
add_filter('acf/prepare_field/key=field_total_joueurs_ayant_resolu_enigme', 'cacher_champs_acf_enigme_non_admins');
add_filter('acf/prepare_field/key=field_total_joueurs_souscription_enigme', 'cacher_champs_acf_enigme_non_admins');



// ==================================================
// 📦 RESTRICTIONS POSTS
// ==================================================
/**
 * 🔹 utilisateur_peut_creer_post → Vérifie si l’utilisateur peut créer un post d’un type spécifique.
 * 🔹 utilisateur_peut_modifier_post → Vérifie si un utilisateur peut modifier un post.
 * 🔹 redirection_si_acces_refuse → Redirige si l’utilisateur ne peut pas créer ou modifier un post.
 * 🔹 filtre_capacites_admin_user_has_cap → Bloque l’édition/création/suppression depuis l’admin pour les rôles non-admin.
 * 🔹 blocage_acces_admin_non_admins → Redirige les rôles organisateur/organisateur_creation hors du back-office (hors AJAX/media).
 * 🔹 load-post-new.php (hook) → Empêche les utilisateurs non autorisés d’accéder à l’écran de création d’un CPT.
 * 🔹 load-post.php (hook) → Empêche les utilisateurs non autorisés d’accéder à l’écran de modification d’un post.
 */


/**
 * Vérifie si un utilisateur peut créer un post d'un type spécifique.
 *
 * @param string $post_type Type de post (CPT).
 * @param int|null $chasse_id (Optionnel) ID de la chasse si déjà connu.
 * @return bool True si l'utilisateur peut créer ce post, sinon false.
 */
function utilisateur_peut_creer_post($post_type, $chasse_id = null) {
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
    
            if (in_array('organisateur', $user_roles, true)) {
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
function utilisateur_peut_modifier_post($post_id) {
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

            return $match;

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
function redirection_si_acces_refuse($post_id, $post_type, $redirect_url) {
    if (!$post_type) {
        return;
    }

    $post_type = sanitize_text_field($post_type);
    $redirect_url = esc_url_raw($redirect_url);

    if (current_user_can('manage_options')) {
        return;
    }

    if (!is_user_logged_in() || ($post_id === null && !utilisateur_peut_creer_post($post_type)) || 
        ($post_id !== null && !utilisateur_peut_modifier_post($post_id))) {
        
        wp_redirect(home_url($redirect_url));
        exit;
    }
}


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
    $roles_bloques = ['organisateur', 'organisateur_creation'];

    // Autoriser AJAX, REST et média
    if (defined('DOING_AJAX') && DOING_AJAX) return;
    if (defined('REST_REQUEST') && REST_REQUEST) return;
    if (wp_doing_cron()) return;

    // Autoriser les admins
    if (in_array('administrator', (array) $user->roles, true)) {
        return;
    }

    // Si le rôle est bloqué et qu'on est dans wp-admin, redirection
    if (array_intersect($roles_bloques, (array) $user->roles)) {
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
// 📦 ACCÈS AUX FICHIERS PROTÉGÉS
// ==================================================
/**
 * 🔹 utilisateur_peut_voir_solution_enigme() → Vérifie si un utilisateur peut voir le fichier PDF ou texte de solution.
 */

/**
 * Vérifie si un utilisateur a le droit de consulter la solution (PDF ou texte) d'une énigme
 *
 * @param int $enigme_id ID du post énigme
 * @param int $user_id   ID de l'utilisateur connecté
 * @return bool
 */
function utilisateur_peut_voir_solution_enigme(int $enigme_id, int $user_id): bool {
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

    // 🧩 Joueur ayant résolu l’énigme — à adapter si tu as un suivi précis
    // Ici, on suppose un champ utilisateur ACF du type enigme_statut_utilisateur[ID_ENIGME] = 'resolue'
    $statuts = get_field('enigme_statut_utilisateur', 'user_' . $user_id);
    if (is_array($statuts) && isset($statuts[$enigme_id])) {
        return in_array($statuts[$enigme_id], ['resolue', 'terminee'], true);
    }

    return false;
}


// ==================================================
// 🔓 CONTRÔLE D’ACCÈS AU CONTENU DES ÉNIGMES
// ==================================================
/**
 * 🔹 utilisateur_peut_voir_enigme() → Vérifie si un utilisateur a accès à l’énigme (visuels, texte, indices…).
 * 🔹 voir-image-enigme → Déclare un endpoint sécurisé pour servir les images des énigmes protégées.
 */

/**
 * Détermine si un utilisateur a le droit de consulter une énigme.
 * Cela conditionne l’accès aux visuels, au texte, et à tout le contenu restreint.
 *
 * ⚠️ Cette version de base s’appuie sur le statut logique de l’énigme
 * renvoyé par `enigme_get_statut()`, et n’inclut pas encore la logique de résolution précise.
 *
 * @param int $enigme_id
 * @param int|null $user_id
 * @return bool
 */
function utilisateur_peut_voir_enigme(int $enigme_id, ?int $user_id = null): bool {
    $user_id = $user_id ?? get_current_user_id();
    if (!$user_id) return false;

    $chasse_id = recuperer_id_chasse_associee($enigme_id);

    // 🔐 Admin ou organisateur rattaché à la chasse
    if (current_user_can('manage_options') || utilisateur_est_organisateur_associe_a_chasse($user_id, $chasse_id)) {
        return true;
    }

    // ✅ Si la chasse est terminée, tout devient visible
    $cache_chasse = get_field('champs_caches', $chasse_id);
    if (($cache_chasse['chasse_cache_statut'] ?? null) === 'termine') {
        return true;
    }

    // 📌 Statut utilisateur logique
    $statut = enigme_get_statut_utilisateur($enigme_id, $user_id);

    // Autorisations minimales (à élargir plus tard si nécessaire)
    return in_array($statut, ['en_cours', 'resolue'], true);
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
// 🔒 GESTION DES CONDITIONS D’ACCÈS – PRÉREQUIS
// ==================================================
/**
 * 🔹 acf/load_field/name=enigme_acces_condition → Masque l’option "pré-requis" si aucune énigme valide n’est disponible
 * 🔹 recuperer_enigmes_possibles_pre_requis → Renvoie la liste des énigmes valides pouvant être sélectionnées comme prérequis
 * 🔹 verifier_et_enregistrer_condition_pre_requis() → Endpoint AJAX sécurisé.
 */


/**
 * Filtre le champ radio `enigme_acces_condition` pour cacher "pré-requis" si aucune énigme éligible n’est détectée.
 *
 * @param array $field Le champ ACF complet
 * @return array Champ modifié sans l’option "pré-requis" si vide
 *
 * @hook acf/load_field/name=enigme_acces_condition
 */
add_filter('acf/load_field/name=enigme_acces_condition', function($field) {
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
function recuperer_enigmes_possibles_pre_requis($enigme_id) {
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


 /*
 * @hook wp_ajax_verifier_et_enregistrer_condition_pre_requis
 * @return void (JSON)
 */
function verifier_et_enregistrer_condition_pre_requis() {
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
