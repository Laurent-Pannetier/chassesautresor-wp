<?php
defined('ABSPATH') || exit;

if (!class_exists(ChassesAuTresor\Core\Relationships\OrganizerService::class, false)) {
    require_once dirname(__DIR__, 3)
        . '/plugins/chassesautresor-core/src/Relationships/OrganizerRepository.php';
    require_once dirname(__DIR__, 3)
        . '/plugins/chassesautresor-core/src/Relationships/OrganizerService.php';
}

if (!class_exists(ChassesAuTresor\Core\Relationships\RelationshipService::class, false)) {
    require_once dirname(__DIR__, 3)
        . '/plugins/chassesautresor-core/src/Relationships/RelationshipService.php';
}

if (!class_exists(ChassesAuTresor\Core\Relationships\HuntRiddleQueryService::class, false)) {
    require_once dirname(__DIR__, 3)
        . '/plugins/chassesautresor-core/src/Relationships/HuntRiddleQueryService.php';
}

if (!class_exists(ChassesAuTresor\Core\Relationships\OrganizerHuntQueryService::class, false)) {
    require_once dirname(__DIR__, 3)
        . '/plugins/chassesautresor-core/src/Relationships/OrganizerHuntQueryService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\HuntManagementService::class, false)) {
    require_once dirname(__DIR__, 3)
        . '/plugins/chassesautresor-core/src/Content/HuntManagementService.php';
}

if (!class_exists(ChassesAuTresor\Core\Relationships\HuntRiddleCacheService::class, false)) {
    require_once dirname(__DIR__, 3)
        . '/plugins/chassesautresor-core/src/Relationships/HuntRiddleCacheService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\HuntFeatureService::class, false)) {
    require_once dirname(__DIR__, 3)
        . '/plugins/chassesautresor-core/src/Content/HuntFeatureService.php';
}

function cat_get_organizer_service(): ChassesAuTresor\Core\Relationships\OrganizerService
{
    global $wpdb;

    return new ChassesAuTresor\Core\Relationships\OrganizerService(
        new ChassesAuTresor\Core\Relationships\OrganizerRepository($wpdb)
    );
}

function cat_get_relationship_service(): ChassesAuTresor\Core\Relationships\RelationshipService
{
    return new ChassesAuTresor\Core\Relationships\RelationshipService();
}

function cat_get_hunt_riddle_query_service(): ChassesAuTresor\Core\Relationships\HuntRiddleQueryService
{
    return new ChassesAuTresor\Core\Relationships\HuntRiddleQueryService();
}

function cat_get_organizer_hunt_query_service(): ChassesAuTresor\Core\Relationships\OrganizerHuntQueryService
{
    return new ChassesAuTresor\Core\Relationships\OrganizerHuntQueryService();
}

function cat_get_hunt_management_service(): ChassesAuTresor\Core\Content\HuntManagementService
{
    return new ChassesAuTresor\Core\Content\HuntManagementService();
}

function cat_get_hunt_riddle_cache_service(): ChassesAuTresor\Core\Relationships\HuntRiddleCacheService
{
    return new ChassesAuTresor\Core\Relationships\HuntRiddleCacheService();
}

function cat_get_hunt_feature_service(): ChassesAuTresor\Core\Content\HuntFeatureService
{
    return new ChassesAuTresor\Core\Content\HuntFeatureService();
}

// 📚 SOMMAIRE DU FICHIER : relations-functions.php
//  📦 RÉCUPÉRATION CPT ORGANISATEUR
//  📦 RÉCUPÉRATION CPT CHASSE
//  📦 RÉCUPÉRATION CPT ÉNIGME
//  📦 RECUPERATION TROPHEE
//  📦 ASSIGNATION AUTOMATIQUES
//  🔁 SYNCHRONISATION CHASSE ↔ ÉNIGMES



// ==================================================
// 📦 RÉCUPÉRATION CPT ORGANISATEUR
// ==================================================
/**
 * 🔹 get_organisateur_from_user → Récupérer l’ID du CPT "organisateur" associé à un utilisateur.
 * 🔹 get_organisateur_chasse → Récupérer directement le champ ACF `organisateur_id` d’une chasse.
 * 🔹 get_organisateur_from_chasse → Récupérer l’ID du CPT "organisateur" associé à une chasse (fallback intelligent).
 * 🔹 get_organisateur_id_from_context → Déterminer l’ID organisateur à partir du contexte d’un template.
 * 🔹 utilisateur_est_organisateur_associe_a_chasse → Vérifie si un utilisateur est lié à l’organisateur d’une chasse.
 */


/**
 * Récupère l'ID du CPT "organisateur" associé à un utilisateur.
 *
 * @param int $user_id ID de l'utilisateur recherché.
 * @return int|null ID du post organisateur ou null si aucun trouvé.
 */
function get_organisateur_from_user($user_id)
{
    return cat_get_organizer_service()->findIdForUser((int) $user_id);
}

function get_organisateur_chasse($chasse_id)
{
    return cat_get_relationship_service()->normalizeId(get_field('organisateur_id', $chasse_id));
}

/**
 * 📌 Récupère l'ID du CPT "organisateur" associé à une chasse.
 *
 * @param int $chasse_id ID du CPT "chasse".
 * @return int|null ID du post organisateur ou null si non trouvé.
 */
function get_organisateur_from_chasse($chasse_id)
{
    return cat_get_relationship_service()->normalizeId(
        get_field('chasse_cache_organisateur', $chasse_id)
    );
}


/**
 * Récupère l’ID d’un organisateur à partir du contexte actuel.
 *
 * Cette fonction unifie les différents cas de figure :
 * – Si un ID est fourni dans $args['organisateur_id'], il est utilisé en priorité.
 * – Si on est sur une page de type "organisateur", l’ID du post est utilisé.
 * – Sinon, on récupère l’organisateur lié à l’utilisateur connecté.
 *
 * @param array $args Arguments optionnels passés au template.
 * @return int|null L’ID du CPT organisateur ou null si introuvable.
 */
function get_organisateur_id_from_context(array $args = []): ?int
{
  if (isset($args['organisateur_id'])) return (int) $args['organisateur_id'];
  global $post;
  if ($post && get_post_type($post) === 'organisateur') return (int) $post->ID;
  return get_organisateur_from_user(get_current_user_id());
}


/**
 * Vérifie si un utilisateur est associé à l’organisateur lié à une chasse donnée.
 *
 * @param int $user_id ID de l'utilisateur à tester.
 * @param int $chasse_id ID de la chasse concernée.
 * @return bool True si l’utilisateur est lié à l’organisateur de la chasse.
 */
function utilisateur_est_organisateur_associe_a_chasse(int $user_id, int $chasse_id): bool
{
    if ($user_id <= 0 || $chasse_id <= 0) {
        return false;
    }

    $organisateur_id = get_organisateur_from_chasse($chasse_id);
    if ($organisateur_id === null) {
        return false;
    }

    $utilisateurs = get_field('utilisateurs_associes', $organisateur_id);

    return is_array($utilisateurs)
        && cat_get_organizer_service()->isUserAssociated($user_id, $utilisateurs);
}




// ==================================================
// 📦 RÉCUPÉRATION CPT CHASSE
// ==================================================
/**
 * 🔹 recuperer_chasse_associee → Récupérer la chasse associée à une énigme.
 * 🔹 recuperer_id_chasse_associee → Récupérer l’ID de la chasse associée à une énigme.
 * 🔹 organisateur_a_des_chasses → Vérifier si un organisateur a au moins une chasse associée.
 * 🔹 get_chasses_de_organisateur → Récupérer les chasses associées à un organisateur.
 * 🔹 get_chasses_en_creation() → Récupère les chasses d’un organisateur en cours de création (statuts spécifiques).
 */

/**
 * Récupère la chasse associée à une énigme.
 *
 * @param int $enigme_id ID de l'énigme.
 * @return WP_Post|null Chasse associée ou null si aucune trouvée.
 */
function recuperer_chasse_associee($enigme_id)
{
  $chasse = get_field('chasse_associee', $enigme_id);

  // 📌 ACF peut retourner un tableau (relation multiple) ou un objet unique
  if (is_array($chasse) && !empty($chasse)) {
    return get_post($chasse[0]);
  } elseif ($chasse instanceof WP_Post) {
    return $chasse;
  }

  return null;
}

/**
 * Récupère l'ID de la chasse associée à une énigme.
 *
 * @param int|null $post_id ID du post énigme.
 * @return int|null ID de la chasse ou null si non trouvé.
 */
function recuperer_id_chasse_associee($post_id = null)
{
  static $cached_chasse_id = null;

  if ($cached_chasse_id !== null && $cached_chasse_id > 0) {
    return $cached_chasse_id;
  }

  // 🔹 Option temporaire (création automatique)
  $temp = (int) get_option('chasse_associee_temp');
  if ($temp > 0) {
    delete_option('chasse_associee_temp');
    return $cached_chasse_id = $temp;
  }

  // 🔹 Lecture du champ ACF
  if ($post_id) {
    $champ = get_field('enigme_chasse_associee', $post_id);

    if (is_array($champ)) {
      $chasse_id = is_object($champ[0]) ? (int) $champ[0]->ID : (int) $champ[0];
    } elseif (is_object($champ)) {
      $chasse_id = (int) $champ->ID;
    } else {
      $chasse_id = (int) $champ;
    }

    if ($chasse_id > 0) {
      return $cached_chasse_id = $chasse_id;
    }
  }

  return null;
}


/**
 * Vérifie si un organisateur a au moins une chasse associée.
 *
 * @param int $organisateur_id ID de l'organisateur.
 * @return bool True si au moins une chasse existe, False sinon.
 */
function organisateur_a_des_chasses($organisateur_id)
{
    $organisateur_id = (int) $organisateur_id;
    if ($organisateur_id <= 0) {
        return false;
    }

    $query = new WP_Query(
        cat_get_organizer_hunt_query_service()->getExistingHuntQueryArgs($organisateur_id)
    );

    return $query->have_posts();
}

/**
 * Vérifie si un organisateur possède au moins une chasse en attente (status "pending").
 *
 * @param int $organisateur_id ID de l'organisateur.
 * @return bool True si une chasse en attente existe, False sinon.
 */
function organisateur_a_chasse_pending(int $organisateur_id): bool
{
    if ($organisateur_id <= 0) {
        return false;
    }

    $query = new WP_Query(
        cat_get_organizer_hunt_query_service()->getExistingHuntQueryArgs($organisateur_id, true)
    );

    return $query->have_posts();
}

/**
 * Récupère les chasses associées à un organisateur.
 *
 * @param int $organisateur_id ID de l'organisateur.
 * @return WP_Query Objet WP_Query contenant les chasses associées.
 */
function get_chasses_de_organisateur($organisateur_id)
{
    static $cache = [];

    $organisateur_id = (int) $organisateur_id;
    if ($organisateur_id <= 0) {
        return new WP_Query();
    }

    if (isset($cache[$organisateur_id])) {
        return $cache[$organisateur_id];
    }

    $query = new WP_Query(
        cat_get_organizer_hunt_query_service()->getHuntIdsQueryArgs($organisateur_id)
    );

    $cache[$organisateur_id] = $query;

    return $query;
}

/**
 * Retourne le nombre de chasses publiées pour un organisateur donné.
 *
 * Utilise un cache statique pour éviter des requêtes répétées.
 *
 * @param int $organisateur_id ID de l'organisateur.
 * @return int Nombre de chasses publiées.
 */
function organisateur_get_nb_chasses_publiees(int $organisateur_id): int
{
    static $cache = [];

    if (isset($cache[$organisateur_id])) {
        return $cache[$organisateur_id];
    }

    if ($organisateur_id <= 0) {
        return $cache[$organisateur_id] = 0;
    }

    $query = new WP_Query(
        cat_get_organizer_hunt_query_service()->getPublishedHuntCountQueryArgs($organisateur_id)
    );

    $count = (int) $query->found_posts;
    $cache[$organisateur_id] = $count;

    return $count;
}

/**
 * 🔹 get_chasses_en_creation() → Récupère les chasses en création pour un organisateur donné.
 *
 * @param int $organisateur_id
 * @return int[]
 */
function get_chasses_en_creation($organisateur_id)
{
    if (!is_numeric($organisateur_id)) {
        cat_debug("⛔ get_chasses_en_creation : ID non numérique : " . print_r($organisateur_id, true));
        return [];
    }

    $chasses_query = get_chasses_de_organisateur($organisateur_id);
    $chasse_ids = is_a($chasses_query, 'WP_Query') ? $chasses_query->posts : (array) $chasses_query;

    if (empty($chasse_ids)) {
        cat_debug("🔍 Aucune chasse liée à l’organisateur $organisateur_id");
        return [];
    }

    $service = cat_get_hunt_management_service();
    $filtered = array_filter($chasse_ids, function ($id) use ($service): bool {
        $id = (int) $id;
        $publicationStatus = (string) get_post_status($id);
        $validationStatus = (string) get_field('chasse_cache_statut_validation', $id);
        $businessStatus = (string) get_field('chasse_cache_statut', $id);

        cat_debug(
            "🧪 #$id | statut=$publicationStatus | validation=$validationStatus | metier=$businessStatus"
        );

        return $service->isInCreation($publicationStatus, $validationStatus, $businessStatus);
    });

    cat_debug("📦 Chasses en création retrouvées : " . count($filtered));

    return array_values($filtered);
}


// ==================================================
//  📦 RÉCUPÉRATION CPT ÉNIGME
// ==================================================
/**
 * 🔹 recuperer_enigmes_associees() → Récupère les énigmes associées à une chasse.
 * 🔹 recuperer_enigmes_pour_chasse() → Retourne la liste des énigmes liées à une chasse via WP_Query.
 * 🔹 recuperer_ids_enigmes_pour_chasse() → Retourne les IDs des énigmes liées à une chasse (requête directe).
 */

/**
 * 🔍 Récupère les énigmes associées à une chasse via le champ ACF `chasse_cache_enigmes`.
 *
 * Gère proprement les cas où ACF retourne des objets ou des IDs.
 * Ajoute des logs de débogage si des doublons sont présents.
 *
 * @param int $chasse_id ID de la chasse.
 * @return array Liste unique d’IDs d’énigmes (int).
 */
function recuperer_enigmes_associees(int $chasse_id): array
{
    if (!$chasse_id || get_post_type($chasse_id) !== 'chasse') {
        cat_debug("❌ [recuperer_enigmes_associees] Appel invalide pour ID $chasse_id");
        return [];
    }

    $rawRelationships = get_field('chasse_cache_enigmes', $chasse_id);
    $ids = cat_get_relationship_service()->normalizeIds(
        is_array($rawRelationships) ? $rawRelationships : []
    );
    $uniqueIds = array_values(array_unique($ids));

    if (count($ids) !== count($uniqueIds)) {
        cat_debug("⚠️ [recuperer_enigmes_associees] Doublons détectés pour la chasse #$chasse_id");
    }

    return array_values(array_filter($uniqueIds, function (int $id): bool {
        return get_post_type($id) === 'enigme';
    }));
}


/** *
 * ⚠️ Contrairement à `chasse_cache_enigmes`, cette fonction interroge la base en direct.
 *
 * @param int $chasse_id
 * @return WP_Post[] Liste d’objets WP_Post
 */
function recuperer_enigmes_pour_chasse(int $chasse_id): array
{
    if ($chasse_id <= 0 || get_post_type($chasse_id) !== 'chasse') {
        return [];
    }

    $query = new WP_Query(
        cat_get_hunt_riddle_query_service()->getVisibleRiddlesQueryArgs($chasse_id)
    );

    return $query->have_posts() ? $query->posts : [];
}


/**
 * @param int $chasse_id
 * @return int[] Liste d’IDs (int)
 */
function recuperer_ids_enigmes_pour_chasse(int $chasse_id): array
{
    if ($chasse_id <= 0 || get_post_type($chasse_id) !== 'chasse') {
        return [];
    }

    $query = new WP_Query(
        cat_get_hunt_riddle_query_service()->getRiddleIdsQueryArgs($chasse_id)
    );

    return $query->posts;
}


/**
 * Clear the cached enigme list for a chasse when an enigme is saved.
 *
 * @param int $post_id Post ID of the enigme being saved.
 */
function clear_enigmes_chasse_cache(int $post_id): void
{
    if (get_post_type($post_id) !== 'enigme') {
        return;
    }

    $chasse_id = (int) get_field('enigme_chasse_associee', $post_id);
    if (!$chasse_id) {
        return;
    }

    wp_cache_delete('enigmes_chasse_' . $chasse_id, 'chassesautresor');
}
add_action('save_post_enigme', 'clear_enigmes_chasse_cache', 20, 1);

function recalculate_chasse_cached_flags(int $chasse_id): void
{
    $huntHasSolution = function_exists('solution_existe_pour_objet')
        && solution_existe_pour_objet($chasse_id, 'chasse');
    $huntHasHints = function_exists('prochain_rang_indice')
        && prochain_rang_indice($chasse_id, 'chasse') > 1;
    $features = cat_get_hunt_feature_service()->summarize(
        $huntHasSolution,
        $huntHasHints,
        recuperer_enigmes_associees($chasse_id),
        static function (int $riddleId): bool {
            return function_exists('solution_existe_pour_objet')
                && solution_existe_pour_objet($riddleId, 'enigme');
        },
        static function (int $riddleId): bool {
            return function_exists('prochain_rang_indice')
                && prochain_rang_indice($riddleId, 'enigme') > 1;
        }
    );

    update_field('chasse_cache_has_solutions', $features['has_solutions'] ? 1 : 0, $chasse_id);
    update_field('chasse_cache_has_indices', $features['has_indices'] ? 1 : 0, $chasse_id);
}

/**
 * Met à jour les indicateurs mis en cache lors de la sauvegarde d'une énigme.
 *
 * @param int $post_id ID de l'énigme.
 * @return void
 */
function update_chasse_cached_flags_on_enigme_save(int $post_id): void
{
    $chasse_id = (int) get_field('enigme_chasse_associee', $post_id);
    if ($chasse_id) {
        recalculate_chasse_cached_flags($chasse_id);
    }
}
add_action('save_post_enigme', 'update_chasse_cached_flags_on_enigme_save', 20, 1);

/**
 * Met à jour les indicateurs mis en cache lors de la sauvegarde d'un indice.
 *
 * @param int $post_id ID de l'indice.
 * @return void
 */
function update_chasse_cached_flags_on_indice_save(int $post_id): void
{
    $targetType = (string) get_field('indice_cible_type', $post_id);
    $riddleId = $targetType === 'enigme'
        ? (int) get_field('indice_enigme_linked', $post_id)
        : 0;
    $directHunt = $targetType === 'chasse'
        ? get_field('indice_chasse_linked', $post_id)
        : null;
    $riddleHunt = $riddleId > 0 ? recuperer_chasse_associee($riddleId) : null;
    $chasse_id = cat_get_relationship_service()->resolveTargetHuntId(
        $targetType,
        $directHunt,
        $riddleHunt
    );

    if ($chasse_id !== null) {
        recalculate_chasse_cached_flags($chasse_id);
    }
}
add_action('save_post_indice', 'update_chasse_cached_flags_on_indice_save', 20, 1);

/**
 * Met à jour les indicateurs mis en cache lors de la sauvegarde d'une solution.
 *
 * @param int $post_id ID de la solution.
 * @return void
 */
function update_chasse_cached_flags_on_solution_save(int $post_id): void
{
    $targetType = (string) get_field('solution_cible_type', $post_id);
    $riddleId = $targetType === 'enigme'
        ? (int) get_field('solution_enigme_linked', $post_id)
        : 0;
    $directHunt = $targetType === 'chasse'
        ? get_field('solution_chasse_linked', $post_id)
        : null;
    $riddleHunt = $riddleId > 0 ? recuperer_chasse_associee($riddleId) : null;
    $chasse_id = cat_get_relationship_service()->resolveTargetHuntId(
        $targetType,
        $directHunt,
        $riddleHunt
    );

    if ($chasse_id !== null) {
        recalculate_chasse_cached_flags($chasse_id);
    }
}
add_action('save_post_solution', 'update_chasse_cached_flags_on_solution_save', 20, 1);


// ==================================================
// 📦 ASSIGNATION AUTOMATIQUES
// ==================================================
/**
 * 🔹 assigner_organisateur_automatiquement() → Assigne automatiquement l'organisateur d'une chasse lors de sa création.
 */

/**
 * 📌 Assigne automatiquement l'organisateur d'une chasse lors de sa création.
 *
 * 🔹 Vérifie si l'auteur de la chasse a le rôle "organisateur".
 * 🔹 Si oui, enregistre son ID dans le champ ACF "organisateur_id".
 * 🔹 Fonctionne uniquement à la création (pas à l'édition).
 *
 * @param int $post_id ID de la chasse en cours de sauvegarde.
 * @param WP_Post $post Objet du post.
 */
function assigner_organisateur_automatiquement($post_id, $post)
{
  if ($post->post_type !== 'chasse') {
    return;
  }

  $auteur_id = $post->post_author;

  if (est_organisateur($auteur_id)) {
    update_field('organisateur_id', $auteur_id, $post_id);
  } else {
    cat_debug("⚠️ Avertissement : L'auteur {$auteur_id} n'a pas un rôle valide (organisateur ou organisateur_creation).");
  }
}
add_action('save_post', 'assigner_organisateur_automatiquement', 10, 2);



// ==================================================
// 🔁 SYNCHRONISATION CHASSE ↔ ÉNIGMES
// ==================================================
/**
 * 🔹 synchroniser_cache_enigmes_chasse() → Lecture + correction automatique du champ ACF chasse_cache_enigmes
 * 🔹 verifier_chasse_cache_enigmes() → Compare énigmes attendues vs. cache
 * 🔹 verifier_cache_chasse_enigmes_valides() → Supprime les ID orphelins du cache
 * 🔹 synchroniser_relations_cache_enigmes() → Met à jour le champ relation avec le format attendu par ACF
 * 🔹 forcer_relation_enigme_dans_chasse_si_absente() → Depuis une fiche énigme, vérifie que la chasse associée référence bien cette énigme
 * 🔹 verifier_et_synchroniser_cache_enigmes_si_autorise() → Vérifie et synchronise le cache des énigmes liées à une chasse, avec protection par transient
 */




/**
 * 🔁 Synchronise le champ chasse_cache_enigmes avec la réalité des énigmes liées.
 *
 * @param int  $chasse_id        ID de la chasse concernée.
 * @param bool $forcer_recalcul  Si true, forcer la lecture réelle des énigmes liées.
 * @param bool $nettoyer_cache   Si true, retirer du cache les énigmes qui ne sont plus valides.
 * @return array Résultat de la synchronisation (détail et corrections).
 */
function synchroniser_cache_enigmes_chasse($chasse_id, $forcer_recalcul = false, $nettoyer_cache = false)
{
  cat_debug("🌀 [SYNC] Début de synchronisation pour chasse #$chasse_id");

  $resultat1 = verifier_chasse_cache_enigmes($chasse_id, $forcer_recalcul);
  $resultat2 = verifier_cache_chasse_enigmes_valides($chasse_id, $nettoyer_cache);

  $valide1     = $resultat1['valide']     ?? false;
  $valide2     = $resultat2['valide']     ?? false;
  $synchro1    = $resultat1['synchro']    ?? false;
  $synchro2    = $resultat2['synchro']    ?? false;
  $correction1 = $resultat1['correction'] ?? false;
  $correction2 = $resultat2['correction'] ?? false;
  $attendu     = $resultat1['attendu']    ?? [];
  $cache       = $resultat1['cache']      ?? [];
  $invalides   = $resultat2['invalides']  ?? [];

  cat_debug("📥 [ATTENDU] Énigmes réellement liées à la chasse : " . implode(', ', $attendu));
  cat_debug("📦 [CACHE AVANT] Contenu actuel de chasse_cache_enigmes : " . implode(', ', $cache));
  cat_debug("🗑️ [INVALIDES] Énigmes invalides détectées dans le cache : " . implode(', ', $invalides));

  if (!isset($resultat1['synchro'])) {
    cat_debug("⚠️ [INCOHÉRENCE] Clé 'synchro' manquante dans resultat1 (verifier_chasse_cache_enigmes)");
  }
  if (!isset($resultat2['synchro'])) {
    cat_debug("⚠️ [INCOHÉRENCE] Clé 'synchro' manquante dans resultat2 (verifier_cache_chasse_enigmes_valides)");
  }

  $ok = null;
  if ($correction1 || $correction2) {
    cat_debug("🔧 [ACTION] Mise à jour de chasse_cache_enigmes nécessaire");

    $ok = synchroniser_relations_cache_enigmes($chasse_id);

    if ($ok) {
      cat_debug("✅ [RÉSULTAT] Relations mises à jour proprement via synchroniser_relations_cache_enigmes()");
    } else {
      cat_debug("❌ [ÉCHEC] La synchronisation ACF relation a échoué pour la chasse #$chasse_id");
    }
  }

  cat_debug("🌀 [SYNC] Fin de synchronisation pour chasse #$chasse_id");

  return [
    'valide'                    => $valide1 && $valide2,
    'chasse_id'                 => $chasse_id,
    'synchro_realite_vs_cache'  => $synchro1,
    'synchro_cache_vs_realite'  => $synchro2,
    'correction_effectuee'     => $correction1 || $correction2,
    'liste_attendue'           => $attendu,
    'liste_cache'              => $cache,
    'invalides_dans_cache'     => $invalides,
  ];
}


/**
 * Vérifie la cohérence entre les énigmes liées à une chasse
 * (via leur champ `enigme_chasse_associee`) et le cache ACF
 * `chasse_cache_enigmes` présent sur la chasse.
 *
 * Peut corriger automatiquement le champ si désynchronisé.
 *
 * @param int $chasse_id
 * @param bool $mettre_a_jour Si true, met à jour automatiquement le cache
 * @return array Tableau avec la liste des ID trouvés et l’état de synchro
 */
function verifier_chasse_cache_enigmes($chasse_id, $mettre_a_jour = false)
{
    if (get_post_type($chasse_id) !== 'chasse') {
        return [
            'valide' => false,
            'erreur' => 'ID de chasse invalide.',
            'attendu' => [],
            'cache' => [],
        ];
    }

    $posts = get_posts([
        'post_type' => 'enigme',
        'post_status' => ['draft', 'pending', 'publish'],
        'posts_per_page' => -1,
        'fields' => 'ids',
    ]);
    $expectedIds = [];

    foreach ($posts as $post_id) {
        $association = get_field('enigme_chasse_associee', $post_id, false);
        $associatedIds = cat_get_relationship_service()->normalizeIds(
            is_array($association) ? $association : [$association]
        );
        if (in_array((int) $chasse_id, $associatedIds, true)) {
            $expectedIds[] = (int) $post_id;
        }
    }

    $cache = get_field('chasse_cache_enigmes', $chasse_id);
    $result = cat_get_hunt_riddle_cache_service()->compare(
        $expectedIds,
        is_array($cache) ? array_map('intval', $cache) : [],
        (bool) $mettre_a_jour
    );

    if ($result['correction']) {
        update_field('chasse_cache_enigmes', $result['expected'], $chasse_id);
    }

    return [
        'valide' => true,
        'synchro' => $result['synced'],
        'attendu' => $result['expected'],
        'cache' => $result['cached'],
        'correction' => $result['correction'],
    ];
}


/**
 * Vérifie que chaque énigme listée dans chasse_cache_enigmes
 * pointe bien vers la chasse via le champ enigme_chasse_associee.
 *
 * Cette vérification détecte les ID obsolètes ou erronés dans le cache.
 *
 * @param int $chasse_id
 * @param bool $retirer_si_invalide Si true, supprime les ID invalides du cache
 * @return array Résultat de la vérification (synchro, invalides, correction)
 */
function verifier_cache_chasse_enigmes_valides($chasse_id, $retirer_si_invalide = false)
{
    if (get_post_type($chasse_id) !== 'chasse') {
        return [
            'valide' => false,
            'erreur' => 'ID de chasse invalide.',
            'liste_cache' => [],
            'invalides' => [],
        ];
    }

    $cache = get_field('chasse_cache_enigmes', $chasse_id);
    $cacheIds = is_array($cache) ? array_map('intval', $cache) : [];
    $relatedHuntIds = [];

    foreach ($cacheIds as $riddleId) {
        if (get_post_type($riddleId) !== 'enigme') {
            $relatedHuntIds[$riddleId] = null;
            continue;
        }

        $relatedHuntIds[$riddleId] = cat_get_relationship_service()->normalizeId(
            get_field('enigme_chasse_associee', $riddleId)
        );
    }

    $result = cat_get_hunt_riddle_cache_service()->validate(
        (int) $chasse_id,
        $cacheIds,
        $relatedHuntIds,
        (bool) $retirer_si_invalide
    );

    if ($result['correction']) {
        update_field('chasse_cache_enigmes', $result['corrected'], $chasse_id);
    }

    return [
        'valide' => true,
        'synchro' => $result['synced'],
        'liste_cache' => $result['cached'],
        'invalides' => $result['invalid'],
        'correction' => $result['correction'],
    ];
}


/**
 * 🔁 Synchronise proprement le champ ACF "chasse_cache_enigmes" d'une chasse,
 * en utilisant la fonction centralisée mettre_a_jour_relation_acf() pour garantir le format.
 *
 * @param int $chasse_id ID du post "chasse"
 * @return bool True si au moins une relation a été enregistrée, False sinon.
 */
function synchroniser_relations_cache_enigmes(int $chasse_id): bool
{
    cat_debug("🔄 [SYNC] Début de synchronisation pour chasse #$chasse_id");

    if (get_post_type($chasse_id) !== 'chasse') {
        cat_debug("❌ [SYNC] ID $chasse_id n’est pas une chasse");
        return false;
    }

    $detectedIds = get_posts(
        cat_get_hunt_riddle_query_service()->getSynchronizedRiddleIdsQueryArgs($chasse_id)
    );
    $currentCache = get_field('chasse_cache_enigmes', $chasse_id, false);
    $cachedIds = cat_get_relationship_service()->normalizeIds(
        is_array($currentCache) ? $currentCache : []
    );
    $comparison = cat_get_hunt_riddle_cache_service()->compare(
        array_map('intval', $detectedIds),
        $cachedIds,
        false
    );

    if ($comparison['synced']) {
        cat_debug("✅ [SYNC] Aucune mise à jour nécessaire pour chasse #$chasse_id");
        return true;
    }

    cat_debug("🔧 [SYNC] Cache obsolète → écrasement nécessaire pour chasse #$chasse_id");
    cat_debug("🗑️ Ancien cache : " . implode(', ', $comparison['cached']));
    cat_debug("🆕 Nouvel ensemble : " . implode(', ', $comparison['expected']));

    $success = update_field('chasse_cache_enigmes', $comparison['expected'], $chasse_id);

    if ($success) {
        cat_debug("✅ [SYNC] Mise à jour réussie de chasse_cache_enigmes pour chasse #$chasse_id");
    } else {
        cat_debug("❌ [SYNC] Échec de la mise à jour pour chasse #$chasse_id");
    }

    return (bool) $success;
}



/**
 * 🔁 Depuis une fiche énigme, vérifie que la chasse associée référence bien cette énigme.
 *
 * Cette vérification utilise un transient pour éviter toute surcharge répétée.
 * Si la relation est absente dans le champ ACF (champs_caches.chasse_cache_enigmes),
 * elle est ajoutée automatiquement via modifier_relation_acf().
 *
 * @param int $enigme_id ID du post de type "énigme"
 * @return void
 */
function forcer_relation_enigme_dans_chasse_si_absente(int $enigme_id): void
{
  if (get_post_type($enigme_id) !== 'enigme') return;

  $transient_key = "verif_chasse_relation_$enigme_id";
  if (get_transient($transient_key)) return;
  set_transient($transient_key, 'done', 5 * MINUTE_IN_SECONDS);

  $chasse = get_field('enigme_chasse_associee', $enigme_id, false);
  $chasse_id = is_object($chasse) ? $chasse->ID : (int)$chasse;

  if (!$chasse_id || get_post_type($chasse_id) !== 'chasse') {
    cat_debug("❌ [RELATION AUTO] Chasse non valide pour énigme #$enigme_id");
    return;
  }

  $liste = is_array(get_field('chasse_cache_enigmes', $chasse_id) ?? null) ? array_map('intval', get_field('chasse_cache_enigmes', $chasse_id)) : [];

  if (!in_array($enigme_id, $liste, true)) {
    $ok = modifier_relation_acf(
      $chasse_id,
      'chasse_cache_enigmes',
      $enigme_id,
      'field_67b740025aae0',
      'add'
    );

    if ($ok) {
      cat_debug("✅ [RELATION AUTO] Énigme #$enigme_id ajoutée à la chasse #$chasse_id (groupe champs_caches)");
    } else {
      cat_debug("❌ [RELATION AUTO] Échec ajout énigme #$enigme_id → chasse #$chasse_id");
    }
  }
}


/**
 * 🔁 Vérifie et synchronise le cache des énigmes liées à une chasse, avec protection par transient.
 *
 * ⚠️ Peut déclencher une mise à jour si le cache est désynchronisé.
 *
 * @param int $chasse_id
 * @return void
 */
function verifier_et_synchroniser_cache_enigmes_si_autorise(int $chasse_id): void
{
  if (!current_user_can('administrator') && !current_user_can(ROLE_ORGANISATEUR) && !current_user_can(ROLE_ORGANISATEUR_CREATION)) {
    return;
  }

  if (get_post_type($chasse_id) !== 'chasse') return;

  $transient_key = 'verif_sync_chasse_' . $chasse_id;

  if (!get_transient($transient_key)) {
    // Lancer la synchronisation réelle
    synchroniser_cache_enigmes_chasse($chasse_id, true, true);
    set_transient($transient_key, 'done', 30 * MINUTE_IN_SECONDS);
  }
}
