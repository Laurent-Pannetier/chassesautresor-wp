<?php
/**
 * Gestion de la publication différée des solutions.
 *
 * @package chassesautresor.com
 */

require_once __DIR__ . '/../constants.php';

if (!class_exists(ChassesAuTresor\Core\Content\SolutionAvailabilityService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/SolutionAvailabilityService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\SolutionCacheService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/SolutionCacheService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\SolutionCacheUpdater::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/SolutionCacheUpdater.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\SolutionCreationService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/SolutionCreationService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\SolutionFieldPolicyService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/SolutionFieldPolicyService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\SolutionManagementService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/SolutionManagementService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\SolutionPostFactory::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/SolutionPostFactory.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\SolutionPublicationService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/SolutionPublicationService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\SolutionPublicationPlanner::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/SolutionPublicationPlanner.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\SolutionQueryService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/SolutionQueryService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\SolutionRedirectHandler::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/SolutionRedirectHandler.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\SolutionRouteRegistrar::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/SolutionRouteRegistrar.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\SolutionScheduler::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/SolutionScheduler.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\SolutionSaveHandler::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/SolutionSaveHandler.php';
}

if (!class_exists(ChassesAuTresor\Core\Relationships\RelationshipService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Relationships/RelationshipService.php';
}

/**
 * Planifie la publication d'une solution.
 *
 * Calcule la date cible selon les champs ACF puis programme un événement cron
 * unique pour rendre la solution accessible.
 *
 * @param int $solution_id ID de la solution.
 * @return void
 */
function solution_planifier_publication(int $solution_id): void
{
    ChassesAuTresor\Core\Content\SolutionPublicationPlanner::plan($solution_id);
}

/**
 * Rend une solution accessible immédiatement.
 *
 * @param int $solution_id ID de la solution.
 * @return void
 */
function solution_rendre_accessible(int $solution_id): void
{
    ChassesAuTresor\Core\Content\SolutionPublicationService::makeAccessible($solution_id);
}

/**
 * Basculer les solutions programmées dont la date est atteinte.
 *
 * @return void
 */
function basculer_solutions_programme(): void
{
    ChassesAuTresor\Core\Content\SolutionScheduler::run();
}

/**
 * Planifie la tâche récurrente de basculement des solutions.
 *
 * @return void
 */
function planifier_tache_basculer_solutions_programme(): void
{
    ChassesAuTresor\Core\Content\SolutionScheduler::schedule();
}

/**
 * Met à jour le cache et l'état système d'une solution.
 *
 * @param int $post_id ID de la solution.
 * @return void
 */
function mettre_a_jour_cache_solution(int $post_id): void
{
    ChassesAuTresor\Core\Content\SolutionCacheUpdater::update($post_id);
}

/**
 * Hook ACF pour planifier la publication à la sauvegarde.
 *
 * @param int $post_id ID du post sauvegardé.
 * @return void
 */
function solution_acf_save_post(int $post_id): void
{
    ChassesAuTresor\Core\Content\SolutionSaveHandler::handle($post_id);
}

// ==================================================
// 💡 GESTION DES SOLUTIONS (création, redirection, AJAX)
// ==================================================

/**
 * Redirige l’affichage d’une solution vers sa chasse ou son énigme liée.
 *
 * @return void
 */
function rediriger_si_affichage_solution(): void
{
    ChassesAuTresor\Core\Content\SolutionRedirectHandler::redirectIfViewingSolution();
}

/**
 * Crée une solution liée à une chasse ou une énigme.
 *
 * @param int      $objet_id   ID de la chasse ou de l’énigme.
 * @param string   $objet_type Type de cible ('chasse' ou 'enigme').
 * @param int|null $user_id    ID utilisateur (null = courant).
 * @return int|WP_Error
 */
function creer_solution_pour_objet(int $objet_id, string $objet_type, ?int $user_id = null)
{
    $creationService = new ChassesAuTresor\Core\Content\SolutionCreationService();
    $supportedTarget = $creationService->isSupportedTargetType($objet_type);
    $targetMatches = $supportedTarget && get_post_type($objet_id) === $objet_type;
    $authenticated = is_user_logged_in();
    $canCreate = $authenticated && $targetMatches
        && solution_action_autorisee('create', $objet_type, $objet_id);
    $chasse_id = 0;
    if ($targetMatches) {
        $chasse_id = $objet_type === 'chasse'
            ? $objet_id
            : recuperer_id_chasse_associee($objet_id);
    }
    $queryService = new ChassesAuTresor\Core\Content\SolutionQueryService();
    $existing = $canCreate && $chasse_id
        ? get_posts($queryService->getExistingSolutionIdsQueryArgs($objet_id, $objet_type))
        : [];
    $errorCode = $creationService->getCreationError(
        $supportedTarget,
        $targetMatches,
        $authenticated,
        $canCreate,
        (bool) $chasse_id,
        !empty($existing)
    );
    if ($errorCode !== null) {
        $messages = [
            'type_invalide' => __('Type de cible invalide.', 'chassesautresor-com'),
            'cible_invalide' => __('ID cible invalide.', 'chassesautresor-com'),
            'non_connecte' => __('Utilisateur non connecté.', 'chassesautresor-com'),
            'permission_refusee' => __('Droits insuffisants.', 'chassesautresor-com'),
            'existe_deja' => __('Une solution existe déjà pour cet objet.', 'chassesautresor-com'),
        ];

        return new WP_Error($errorCode, $messages[$errorCode]);
    }

    return (new ChassesAuTresor\Core\Content\SolutionPostFactory())->create(
        $objet_id,
        $objet_type,
        $chasse_id,
        $user_id ?? get_current_user_id(),
        TITRE_DEFAUT_SOLUTION,
        __('Solution | %s', 'chassesautresor-com'),
        SOLUTION_STATE_DESACTIVE
    );
}

/**
 * Enregistre l’URL personnalisée /creer-solution/
 *
 * @return void
 */
function register_endpoint_creer_solution(): void
{
    ChassesAuTresor\Core\Content\SolutionRouteRegistrar::register();
}

/**
 * S'assure que les règles de réécriture prennent en compte /creer-solution/.
 *
 * @return void
 */
function flush_rewrite_rules_creer_solution(): void
{
    ChassesAuTresor\Core\Content\SolutionRouteRegistrar::flush();
}

/**
 * Détecte l’appel à /creer-solution/ et redirige vers la page cible.
 *
 * @return void
 */
function creer_solution_et_rediriger_si_appel(): void
{
    if (get_query_var('creer_solution') !== '1') {
        return;
    }

    $nonce = $_GET['nonce'] ?? '';
    if (!wp_verify_nonce($nonce, 'creer_solution')) {
        wp_die(__('Action non autorisée.', 'chassesautresor-com'), 'Erreur', ['response' => 403]);
    }

    if (!is_user_logged_in()) {
        wp_redirect(wp_login_url());
        exit;
    }

    $target = (new ChassesAuTresor\Core\Content\SolutionCreationService())->resolveRequestedTarget(
        isset($_GET['chasse_id']) ? absint($_GET['chasse_id']) : 0,
        isset($_GET['enigme_id']) ? absint($_GET['enigme_id']) : 0
    );
    if ($target === null) {
        wp_die(__('ID cible manquant.', 'chassesautresor-com'), 'Erreur', ['response' => 400]);
    }
    $cible_id = $target['id'];
    $cible_type = $target['type'];

    $solution_id = creer_solution_pour_objet($cible_id, $cible_type);
    if (is_wp_error($solution_id)) {
        $error_message = sanitize_text_field($solution_id->get_error_message());
        $referer       = wp_get_referer() ?: get_permalink($cible_id);
        $redirect_url  = add_query_arg('erreur', $error_message, $referer);
        wp_safe_redirect($redirect_url);
        exit;
    }

    wp_safe_redirect(get_permalink($cible_id));
    exit;
}
add_action('template_redirect', 'creer_solution_et_rediriger_si_appel');

/**
 * Connects the core solution controllers to the theme permission policy.
 */
function autoriser_gestion_solution(bool $allowed, string $action, string $targetType, int $targetId): bool
{
    return solution_action_autorisee($action, $targetType, $targetId);
}
add_filter('chassesautresor_can_manage_solution', 'autoriser_gestion_solution', 10, 4);

/**
 * Connects the core solution creation controller to the theme creation adapter.
 *
 * @param mixed $solutionId Previous filtered value.
 * @return int|WP_Error
 */
function creer_solution_depuis_core($solutionId, int $targetId, string $targetType)
{
    return creer_solution_pour_objet($targetId, $targetType);
}
add_filter('chassesautresor_create_solution', 'creer_solution_depuis_core', 10, 3);

/**
 * Provides riddle IDs to the core solution management controllers.
 *
 * @return int[]
 */
function fournir_ids_enigmes_solution(array $riddleIds, int $huntId): array
{
    return recuperer_ids_enigmes_pour_chasse($huntId);
}
add_filter('chassesautresor_hunt_riddle_ids', 'fournir_ids_enigmes_solution', 10, 2);

/**
 * Provides hunt riddles to the core solution status controller.
 *
 * @return array<int, object>
 */
function fournir_enigmes_solution(array $riddles, int $huntId): array
{
    return recuperer_enigmes_pour_chasse($huntId);
}
add_filter('chassesautresor_hunt_riddles', 'fournir_enigmes_solution', 10, 2);

function indiquer_existence_solution(bool $exists, int $targetId, string $targetType): bool
{
    return solution_existe_pour_objet($targetId, $targetType);
}
add_filter('chassesautresor_solution_exists', 'indiquer_existence_solution', 10, 3);

/**
 * Renders solution rows requested by the core controller.
 *
 * @param object[] $solutions
 */
function rendre_table_solutions(
    string $html,
    array $solutions,
    int $page,
    int $pages,
    string $targetType,
    int $targetId
): string {
    ob_start();
    get_template_part('template-parts/common/solutions-table', null, [
        'solutions' => $solutions,
        'page' => $page,
        'pages' => $pages,
        'objet_type' => $targetType,
        'objet_id' => $targetId,
    ]);

    return (string) ob_get_clean();
}
add_filter('chassesautresor_render_solutions_table', 'rendre_table_solutions', 10, 7);
