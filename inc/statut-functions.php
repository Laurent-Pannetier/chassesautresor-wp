<?php

// 🚀 Empêcher l'accès direct au fichier
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/badge-functions.php';

if (!class_exists(ChassesAuTresor\Core\Progress\HuntStatusAjaxHandler::class, false)) {
    require_once dirname(__DIR__, 3)
        . '/plugins/chassesautresor-core/src/Progress/HuntStatusAjaxHandler.php';
}

if (!class_exists(ChassesAuTresor\Core\Progress\HuntStatusScheduler::class, false)) {
    require_once dirname(__DIR__, 3)
        . '/plugins/chassesautresor-core/src/Progress/HuntStatusScheduler.php';
}

if (!class_exists(ChassesAuTresor\Core\Progress\HuntStatusUpdater::class, false)) {
    require_once dirname(__DIR__, 3)
        . '/plugins/chassesautresor-core/src/Progress/HuntStatusUpdater.php';
}

if (!class_exists(ChassesAuTresor\Core\Progress\RiddleStatusAjaxHandler::class, false)) {
    require_once dirname(__DIR__, 3)
        . '/plugins/chassesautresor-core/src/Progress/RiddleStatusAjaxHandler.php';
}

if (!class_exists(ChassesAuTresor\Core\Progress\RiddleSystemStateUpdater::class, false)) {
    require_once dirname(__DIR__, 3)
        . '/plugins/chassesautresor-core/src/Progress/RiddleAnswerService.php';
    require_once dirname(__DIR__, 3)
        . '/plugins/chassesautresor-core/src/Progress/RiddleSystemStateService.php';
    require_once dirname(__DIR__, 3)
        . '/plugins/chassesautresor-core/src/Progress/RiddleSystemStateUpdater.php';
}

if (!class_exists(ChassesAuTresor\Core\Progress\RiddleParticipationPolicyService::class, false)) {
    require_once dirname(__DIR__, 3)
        . '/plugins/chassesautresor-core/src/Progress/RiddleParticipationPolicyService.php';
}

if (!class_exists(ChassesAuTresor\Core\Progress\HuntProgressService::class, false)) {
    require_once dirname(__DIR__, 3)
        . '/plugins/chassesautresor-core/src/Progress/HuntProgressRepository.php';
    require_once dirname(__DIR__, 3)
        . '/plugins/chassesautresor-core/src/Progress/HuntProgressService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\RiddleCompletionService::class, false)) {
    require_once dirname(__DIR__, 3)
        . '/plugins/chassesautresor-core/src/Content/RiddleCompletionService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\OrganizerCompletionService::class, false)) {
    require_once dirname(__DIR__, 3)
        . '/plugins/chassesautresor-core/src/Content/OrganizerCompletionService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\HuntCompletionService::class, false)) {
    require_once dirname(__DIR__, 3)
        . '/plugins/chassesautresor-core/src/Content/HuntCompletionService.php';
}

if (!class_exists(ChassesAuTresor\Core\Relationships\RelationshipService::class, false)) {
    require_once dirname(__DIR__, 3)
        . '/plugins/chassesautresor-core/src/Relationships/RelationshipService.php';
}

if (!class_exists(ChassesAuTresor\Core\Relationships\HuntRiddleQueryService::class, false)) {
    require_once dirname(__DIR__, 3)
        . '/plugins/chassesautresor-core/src/Relationships/HuntRiddleQueryService.php';
}

if (!class_exists(ChassesAuTresor\Core\Relationships\HuntRiddleCacheSynchronizer::class, false)) {
    require_once dirname(__DIR__, 3)
        . '/plugins/chassesautresor-core/src/Relationships/HuntRiddleCacheService.php';
    require_once dirname(__DIR__, 3)
        . '/plugins/chassesautresor-core/src/Content/AcfRelationshipMutationService.php';
    require_once dirname(__DIR__, 3)
        . '/plugins/chassesautresor-core/src/Relationships/HuntRiddleCacheSynchronizer.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\CompletionCacheManager::class, false)) {
    require_once dirname(__DIR__, 3)
        . '/plugins/chassesautresor-core/src/Content/CompletionCacheManager.php';
}

if (!function_exists('cat_get_hunt_progress_service')) {
    function cat_get_hunt_progress_service(): ChassesAuTresor\Core\Progress\HuntProgressService
    {
        global $wpdb;

        return new ChassesAuTresor\Core\Progress\HuntProgressService(
            new ChassesAuTresor\Core\Progress\HuntProgressRepository($wpdb)
        );
    }
}

if (!function_exists('enigme_get_bonnes_reponses')) {
    function enigme_get_bonnes_reponses(int $enigme_id): array
    {
        return (new ChassesAuTresor\Core\Progress\RiddleAnswerService())->get($enigme_id);
    }
}

//
// 🧩 GESTION DES STATUTS ET DE L’ACCESSIBILITÉ DES ÉNIGMES
// 🧠 GESTION DES STATUTS DES CHASSES
// 🧭 CALCUL DU STATUT D’UN ORGANISATEUR
// 🧑‍💻 GESTION DES STATUTS DES JOUEURS (UTILISATEUR ↔ ÉNIGME)
//

// ==================================================
// 🧩 GESTION DES STATUTS ET DE L’ACCESSIBILITÉ DES ÉNIGMES
// ==================================================
/**
 * 
 * 🔹 enigme_get_statut_utilisateur        → Retourne le statut actuel de l’utilisateur pour une énigme.
 * 🔹 enigme_mettre_a_jour_statut_utilisateur() → Met à jour le statut d'un joueur dans la table personnalisée.
 * 🔹 enigme_pre_requis_remplis            → Vérifie les prérequis d’une énigme pour un utilisateur.
 * 🔹 enigme_verifier_verrouillage         → Détaille le verrouillage éventuel d’une énigme.
 * 🔹 traiter_statut_enigme                → Détermine le comportement global à adopter (formulaire, redirection…).
 * 🔹 enigme_est_visible_pour              → Vérifie si un utilisateur peut voir une énigme.
 * 🔹 mettre_a_jour_statuts_enigmes_de_la_chasse → Recalcule tous les statuts des énigmes liées à une chasse.
 * 🔹 enigme_mettre_a_jour_etat_systeme    → Calcule ou met à jour le champ `enigme_cache_etat_systeme`.
 * 🔹 enigme_mettre_a_jour_etat_systeme_automatiquement → Hook ACF (enregistrement admin ou front).
 * 🔹 forcer_recalcul_statut_enigme        → Recalcul AJAX côté front (édition directe).
 * 🔹 enigme_get_etat_systeme              → Retourne l’état système de l’énigme (champ ACF cache).
 * 🔹 utilisateur_peut_engager_enigme      → Vérifie si un joueur peut engager une énigme.
 */

/**
 * Récupère le statut actuel de l’utilisateur pour une énigme.
 *
 * Statuts possibles :
 * - non_souscrite : le joueur n'a jamais interagi avec l’énigme
 * - en_cours      : le joueur a commencé l’énigme
 * - resolue       : le joueur a trouvé la bonne réponse
 * - terminee      : l’énigme a été finalisée dans un autre contexte
 * - echouee       : le joueur a tenté et échoué
 * - abandonnee    : le joueur a abandonné explicitement ou par expiration
 *
 * @param int $enigme_id ID de l’énigme.
 * @param int $user_id   ID de l’utilisateur.
 * @return string Statut actuel (par défaut : 'non_souscrite').
 */
function enigme_get_statut_utilisateur(int $enigme_id, int $user_id): string
{
    if (!$enigme_id || !$user_id) {
        return 'non_commencee';
    }

    $statut = cat_get_hunt_progress_service()->getRiddleStatus($user_id, $enigme_id);

    if ($statut) {
        $statut = strtolower(remove_accents($statut));
    }

    return $statut ?: 'non_commencee';
}


/**
 * Met à jour le statut d'un joueur pour une énigme dans la table personnalisée `wp_enigme_statuts_utilisateur`.
 * La mise à jour ne s'effectue que si le nouveau statut est plus avancé que l'ancien.
 *
 * @param int $enigme_id ID de l'énigme.
 * @param int $user_id   ID de l'utilisateur.
 * @param string $nouveau_statut Nouveau statut ('non_commencee', 'en_cours', 'abandonnee', 'echouee', 'resolue', 'terminee').
 * @return bool True si la mise à jour est faite, false sinon.
 */
function enigme_mettre_a_jour_statut_utilisateur(int $enigme_id, int $user_id, string $nouveau_statut, bool $forcer = false): bool
{
    if (!$enigme_id || !$user_id || !$nouveau_statut) {
        return false;
    }

    $nouveau_statut = strtolower(remove_accents($nouveau_statut));

    return cat_get_hunt_progress_service()->advanceRiddleStatus(
        $user_id,
        $enigme_id,
        $nouveau_statut,
        current_time('mysql'),
        $forcer
    );
}



/**
 * 🔍 Vérifie si les prérequis d'une énigme sont remplis pour un utilisateur donné.
 *
 * @param int $enigme_id ID de l'énigme à vérifier.
 * @param int $user_id   ID de l'utilisateur.
 * @return bool True si tous les prérequis sont remplis ou inexistants, false sinon.
 */
function enigme_pre_requis_remplis(int $enigme_id, int $user_id): bool
{
    $pre_requis = get_field('enigme_acces_pre_requis', $enigme_id);

    $condition = get_field('enigme_acces_condition', $enigme_id) ?? 'immediat';

    $prerequisiteIds = [];
    foreach (is_array($pre_requis) ? $pre_requis : [] as $requiredRiddle) {
        if (is_object($requiredRiddle) && isset($requiredRiddle->ID)) {
            $prerequisiteIds[] = (int) $requiredRiddle->ID;
        } elseif (is_numeric($requiredRiddle)) {
            $prerequisiteIds[] = (int) $requiredRiddle;
        }
    }

    return cat_get_hunt_progress_service()->areRiddlePrerequisitesMet(
        $user_id,
        $prerequisiteIds,
        (string) $condition
    );
}

/**
 * ✅ Vérifie si l’énigme est verrouillée et retourne le motif.
 *
 * @param int $enigme_id ID de l'énigme.
 * @param int $user_id ID de l'utilisateur.
 * @return array Résultat avec :
 *  - 'est_verrouillee' (bool) : Statut de verrouillage.
 *  - 'motif' (string) : Raison du verrouillage.
 *  - 'date_deblocage' (string|null) : Date formatée si future.
 *  - 'timestamp_restant' (int|null) : Secondes restantes si applicable.
 *  - 'cout_points' (int|null) : Coût en points si concerné.
 *  - 'message_variante' (string|null) : Message en cas de variante.
 */
function enigme_verifier_verrouillage(int $enigme_id, int $user_id): array
{
    $statut = $user_id > 0 ? enigme_get_statut_utilisateur($enigme_id, $user_id) : '';

    $unlockTimestamp = null;
    $formattedUnlockDate = null;
    if ($statut === 'bloquee_date') {
        $access = get_field('enigme_acces', $enigme_id);
        $dateString = is_array($access) ? ($access['enigme_acces_date'] ?? null) : null;
        $date = $dateString ? convertir_en_datetime($dateString) : null;
        if ($date) {
            $unlockTimestamp = $date->getTimestamp();
            $formattedUnlockDate = $date->format('d/m/Y à H\hi');
        }
    }

    return cat_get_hunt_progress_service()->getRiddleLockState(
        $user_id,
        $statut,
        $unlockTimestamp,
        $formattedUnlockDate
    );
}



/**
 * Analyse le statut d’une énigme pour un utilisateur et détermine le comportement à adopter :
 * - redirection
 * - affichage ou non du formulaire
 * - affichage d’un message explicatif
 *
 * @param int $enigme_id
 * @param int|null $user_id
 *
 * @return array{
 *   etat: string,
 *   rediriger: bool,
 *   url: string|null,
 *   afficher_formulaire: bool,
 *   afficher_message: bool,
 *   message_html: string
 * }
 */
function traiter_statut_enigme(int $enigme_id, ?int $user_id = null): array
{
    $user_id = $user_id ?: get_current_user_id();
    $status = enigme_get_statut_utilisateur($enigme_id, $user_id);
    $huntId = (int) recuperer_id_chasse_associee($enigme_id);
    $condition = (string) (get_field('enigme_acces_condition', $enigme_id) ?? 'immediat');
    $prerequisitesMet = $condition !== 'pre_requis'
        || enigme_pre_requis_remplis($enigme_id, $user_id);
    $state = (new ChassesAuTresor\Core\Progress\RiddleParticipationPolicyService())->decide(
        $status,
        current_user_can('manage_options'),
        get_post_status($enigme_id) === 'draft',
        utilisateur_est_organisateur_associe_a_chasse($user_id, $huntId),
        get_field('chasse_cache_statut', $huntId) === 'termine',
        utilisateur_est_engage_dans_chasse($user_id, $huntId),
        utilisateur_est_engage_dans_enigme($user_id, $enigme_id),
        $prerequisitesMet
    );
    $state['url'] = $state['rediriger']
        ? ($huntId > 0 ? get_permalink($huntId) : home_url('/'))
        : null;

    return $state;
}



/**
 * Vérifie si un utilisateur est autorisé à afficher une énigme.
 * Utilise la logique de `traiter_statut_enigme()` pour autoriser ou refuser l’accès.
 *
 * @param int $user_id ID de l’utilisateur
 * @param int $enigme_id ID de l’énigme
 * @return bool True si l’énigme est visible pour cet utilisateur
 */
function enigme_est_visible_pour(int $user_id, int $enigme_id): bool
{
    $data = traiter_statut_enigme($enigme_id, $user_id);
    return !$data['rediriger'];
}



/**
 * 🔁 Recalcule le statut système de toutes les énigmes liées à une chasse.
 *
 * @param int $chasse_id ID de la chasse.
 * @return void
 */
function mettre_a_jour_statuts_enigmes_de_la_chasse(int $chasse_id, ?string $huntStatus = null): void
{
    (new ChassesAuTresor\Core\Progress\RiddleSystemStateUpdater())->refreshHunt(
        $chasse_id,
        $huntStatus
    );
}

function enigme_mettre_a_jour_etat_systeme(
    int $enigme_id,
    bool $mettre_a_jour = true,
    ?string $statut_chasse_forcé = null
): string {
    return (new ChassesAuTresor\Core\Progress\RiddleSystemStateUpdater())->refresh(
        $enigme_id,
        $mettre_a_jour,
        $statut_chasse_forcé
    );
}

function enigme_mettre_a_jour_etat_systeme_automatiquement($post_id): void
{
    if (is_numeric($post_id)) {
        (new ChassesAuTresor\Core\Progress\RiddleSystemStateUpdater())->refresh((int) $post_id);
    }
}


/**
 * 🔁 Recalcule le statut système d’une énigme via appel AJAX sécurisé.
 *
 * Wrapper de compatibilité ; l’endpoint est enregistré par chassesautresor-core.
 * @return void
 */
function forcer_recalcul_statut_enigme(): void
{
    ChassesAuTresor\Core\Progress\RiddleStatusAjaxHandler::handle();
}

/**
 * 🔍 Retourne l'état système de l'énigme (champ ACF cache).
 *
 * @param int $enigme_id ID de l’énigme
 * @return string Valeur du champ (accessible, bloquee_date, etc.)
 */
function enigme_get_etat_systeme(int $enigme_id): string
{
    return get_field('enigme_cache_etat_systeme', $enigme_id) ?: 'invalide';
}

/**
 * ✅ Vérifie si un joueur peut engager une énigme (accès + pas déjà engagé).
 *
 * @param int $enigme_id ID de l’énigme
 * @param int|null $user_id ID du joueur (par défaut : utilisateur courant)
 * @return bool True si engagement possible
 */
function utilisateur_peut_engager_enigme(int $enigme_id, ?int $user_id = null): bool
{
    $user_id = $user_id ?? get_current_user_id();

    $etat_systeme = enigme_get_etat_systeme($enigme_id);
    $prerequisitesMet = $etat_systeme === 'bloquee_pre_requis'
        && function_exists('enigme_pre_requis_remplis')
        && enigme_pre_requis_remplis($enigme_id, $user_id);
    $statut = enigme_get_statut_utilisateur($enigme_id, $user_id);

    return cat_get_hunt_progress_service()->canEngageRiddle(
        $etat_systeme,
        $statut,
        $prerequisitesMet
    );
}

// ==================================================
// ✅ GESTION DE LA COMPLÉTION DES CPT
// ==================================================

function cat_get_completion_cache_manager(): ChassesAuTresor\Core\Content\CompletionCacheManager
{
    return new ChassesAuTresor\Core\Content\CompletionCacheManager();
}

function organisateur_est_complet(int $organisateur_id): bool
{
    return cat_get_completion_cache_manager()->isOrganizerComplete($organisateur_id, 'titre_est_valide');
}

function organisateur_mettre_a_jour_complet(int $organisateur_id): bool
{
    return cat_get_completion_cache_manager()->refresh($organisateur_id);
}

function chasse_has_validatable_enigme(int $chasse_id): bool
{
    $riddleIds = recuperer_ids_enigmes_pour_chasse($chasse_id);
    $modes = array_map(static fn ($riddleId): string => (string) get_field(
        'enigme_mode_validation',
        (int) $riddleId
    ), $riddleIds);
    return (new ChassesAuTresor\Core\Content\HuntCompletionService())->hasValidatableRiddle($modes);
}

function chasse_est_complet(int $chasse_id): bool
{
    return cat_get_completion_cache_manager()->isHuntComplete(
        $chasse_id,
        chasse_has_validatable_enigme($chasse_id),
        'titre_est_valide'
    );
}

function chasse_mettre_a_jour_complet(int $chasse_id): bool
{
    return cat_get_completion_cache_manager()->refresh($chasse_id);
}

function enigme_est_complet(int $enigme_id): bool
{
    return cat_get_completion_cache_manager()->isRiddleComplete(
        $enigme_id,
        'titre_est_valide',
        static fn (int $riddleId): bool => enigme_get_bonnes_reponses($riddleId) !== []
    );
}

function enigme_mettre_a_jour_complet(int $enigme_id): bool
{
    return cat_get_completion_cache_manager()->refresh($enigme_id);
}

function mettre_a_jour_cache_complet_automatiquement($post_id): void
{
    if (is_numeric($post_id)) {
        cat_get_completion_cache_manager()->refresh((int) $post_id);
    }
}

function verifier_ou_mettre_a_jour_cache_complet(int $post_id): void
{
    cat_get_completion_cache_manager()->ensureFresh($post_id);
}

function cat_clear_hunt_display_cache_after_completion(int $huntId): void
{
    chasse_clear_infos_affichage_cache($huntId);
}
add_action(
    'chassesautresor_hunt_display_cache_clear_requested',
    'cat_clear_hunt_display_cache_after_completion'
);



// ==================================================
// 🧠 GESTION DES STATUTS DES CHASSES
// ==================================================
/**
 * 🔹 verifier_ou_recalculer_statut_chasse() → Vérifie le statut ACF à l'affichage.
 * 🔹 mettre_a_jour_statuts_chasse() → Met à jour les statuts de validation et de visibilité d'une chasse.
 * 🔹 forcer_recalcul_statut_chasse() → Forcer un recalcul du statut via une requête AJAX.
 * 🔹 recuperer_statut_chasse() → Retourne dynamiquement le statut pour mise à jour du badge via JS.
 * 🔹 forcer_statut_selon_validation_chasse() → Contrôle la cohérence du statut WordPress.
 * 🔹 forcer_statut_apres_acf() → Corrige le post_status après sauvegarde ACF pour éviter les incohérences.
 */



/**
 * Vérifie et met à jour le statut d'une chasse si le statut cache semble obsolète.
 *
 * À utiliser lors de l'affichage de la fiche chasse.
 *
 * @param int $chasse_id
 * @return void
 */
function verifier_ou_recalculer_statut_chasse($chasse_id): void
{
    (new ChassesAuTresor\Core\Progress\HuntStatusUpdater())->refreshIfStale((int) $chasse_id);
}

function mettre_a_jour_statuts_chasse($chasse_id)
{
    return (new ChassesAuTresor\Core\Progress\HuntStatusUpdater())->refresh((int) $chasse_id);
}

function forcer_recalcul_statut_chasse(): void
{
    ChassesAuTresor\Core\Progress\HuntStatusAjaxHandler::recalculate();
}

function cat_refresh_hunt_status(int $huntId): void
{
    mettre_a_jour_statuts_chasse($huntId);
}
add_action('chassesautresor_hunt_status_refresh_requested', 'cat_refresh_hunt_status');

function cat_check_stale_hunt_status(int $huntId): void
{
    verifier_ou_recalculer_statut_chasse($huntId);
}
add_action('chassesautresor_hunt_status_stale_check_requested', 'cat_check_stale_hunt_status');

function recuperer_statut_chasse(): void
{
    ChassesAuTresor\Core\Progress\HuntStatusAjaxHandler::getStatus();
}

function cat_render_hunt_status_badge(array $badge, string $status, ?string $validation): array
{
    return chasse_preparer_badge_statut($status, $validation);
}
add_filter('chassesautresor_render_hunt_status_badge', 'cat_render_hunt_status_badge', 10, 3);

/**
 * 🔁 Met à jour le champ de validation et force le statut du post en cohérence.
 *
 * Si $nouvelle_validation est fourni, il est appliqué à chasse_cache_statut_validation
 * avant de recalculer et mettre à jour le post_status correspondant.
 *
 * @param int    $post_id              ID du post chasse.
 * @param string|null $nouvelle_validation  Valeur à forcer (valide, banni, etc.). Optionnel.
 * @return void
 */
function forcer_statut_apres_acf($post_id, $nouvelle_validation = null)
{
    (new ChassesAuTresor\Core\Progress\HuntStatusUpdater())->synchronizePublication(
        (int) $post_id,
        is_string($nouvelle_validation) ? $nouvelle_validation : null
    );
}



/**
 * 🔹 is_canevas_creation → Vérifie si l’utilisateur est en train de créer son espace organisateur (aucun CPT associé, et sur la page dédiée).
 */

/**
 * Détermine si l’utilisateur est dans le parcours de création d’un organisateur (canevas).
 *
 * @return bool
 */
function is_canevas_creation()
{
    if (!is_user_logged_in()) {
        return false;
    }

    if (!is_page('devenir-organisateur')) {
        return false;
    }

    return !get_organisateur_from_user(get_current_user_id());
}


function schedule_cat_recalculate_chasse_statuses(): void
{
    ChassesAuTresor\Core\Progress\HuntStatusScheduler::schedule();
}

function cat_recalculate_chasse_statuses(): void
{
    ChassesAuTresor\Core\Progress\HuntStatusScheduler::process();
}


// ==================================================
// 🧑‍💻 GESTION DES STATUTS DES JOUEURS (UTILISATEUR ↔ ÉNIGME)
// ==================================================
/**
 * 🔹 get_statut_utilisateur_enigme() → Retourne le statut du joueur pour une énigme donnée.
 * 🔹 est_enigme_resolue_par_utilisateur() → Booléen : l’utilisateur a-t-il résolu l’énigme ?
 */

/**
 * Retourne le statut du joueur pour une énigme donnée (avec cache interne).
 *
 * @param int $user_id
 * @param int $enigme_id
 * @return string|null Le statut ('non_commencee', 'resolue', etc.) ou null si absent
 */
function get_statut_utilisateur_enigme($user_id, $enigme_id)
{
    static $cache = [];
    $key = $user_id . '-' . $enigme_id;

    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $statut = cat_get_hunt_progress_service()->getRiddleStatus((int) $user_id, (int) $enigme_id);

    if ($statut) {
        $statut = strtolower(remove_accents($statut));
    }

    $cache[$key] = $statut ?: null;
    return $cache[$key];
}

/**
 * Vérifie si l'utilisateur a résolu une énigme.
 *
 * @param int $user_id
 * @param int $enigme_id
 * @return bool
 */
function est_enigme_resolue_par_utilisateur($user_id, $enigme_id)
{
    $statut = get_statut_utilisateur_enigme($user_id, $enigme_id);

    return in_array($statut, ['resolue', 'terminee'], true);
}
