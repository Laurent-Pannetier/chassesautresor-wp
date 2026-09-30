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

if (!class_exists(ChassesAuTresor\Core\Content\SolutionDeletionService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/SolutionDeletionService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\SolutionFieldPolicyService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/SolutionFieldPolicyService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\SolutionFileInputService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/SolutionFileInputService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\SolutionManagementService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/SolutionManagementService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\SolutionModalPolicyService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/SolutionModalPolicyService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\SolutionMutationService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/SolutionMutationService.php';
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
 * Liste les solutions via AJAX pour un objet donné.
 *
 * @return void
 */
function ajax_solutions_lister_table(): void
{
    check_ajax_referer('solution_management', 'nonce');
    $managementService = new ChassesAuTresor\Core\Content\SolutionManagementService();

    if (!is_user_logged_in()) {
        wp_send_json_error('non_connecte');
    }

    $objet_id   = isset($_POST['objet_id']) ? (int) $_POST['objet_id'] : 0;
    $objet_type = sanitize_key($_POST['objet_type'] ?? '');
    $page       = isset($_POST['page']) ? (int) $_POST['page'] : 1;

    if (!$objet_id || !in_array($objet_type, ['chasse', 'enigme'], true)
        || get_post_type($objet_id) !== $objet_type
    ) {
        wp_send_json_error('post_invalide');
    }

    if (!solution_action_autorisee('edit', $objet_type, $objet_id)) {
        wp_send_json_error('acces_refuse');
    }

    $per_page = 5;
    $enigme_ids = $objet_type === 'chasse' ? recuperer_ids_enigmes_pour_chasse($objet_id) : [];
    $queryService = new ChassesAuTresor\Core\Content\SolutionQueryService();
    $page       = $managementService->normalizePage($page, 0);
    $query_args = $queryService->getManagementQueryArgs(
        $objet_id,
        $objet_type,
        $enigme_ids,
        $page,
        $per_page
    );
    $query      = new WP_Query($query_args);
    $total_pages = (int) $query->max_num_pages;
    $normalizedPage = $managementService->normalizePage($page, $total_pages);
    if ($normalizedPage !== $page) {
        $page                  = $normalizedPage;
        $query_args['paged']   = $page;
        $query                 = new WP_Query($query_args);
        $total_pages           = (int) $query->max_num_pages;
    }

    ob_start();
    get_template_part('template-parts/common/solutions-table', null, [
        'solutions'  => $query->posts,
        'page'       => $page,
        'pages'      => $total_pages,
        'objet_type' => $objet_type,
        'objet_id'   => $objet_id,
    ]);
    $html = ob_get_clean();

    wp_send_json_success([
        'html'  => $html,
        'page'  => $page,
        'pages' => $total_pages,
    ]);
}
add_action('wp_ajax_solutions_lister_table', 'ajax_solutions_lister_table');

/**
 * Retourne l'état des boutons d'ajout de solutions pour une chasse.
 *
 * @return void
 */
function ajax_chasse_solution_status(): void
{
    check_ajax_referer('solution_management', 'nonce');
    $managementService = new ChassesAuTresor\Core\Content\SolutionManagementService();

    if (!is_user_logged_in()) {
        wp_send_json_error('non_connecte');
    }

    $chasse_id = isset($_POST['chasse_id']) ? (int) $_POST['chasse_id'] : 0;
    $enigme_id = isset($_POST['enigme_id']) ? (int) $_POST['enigme_id'] : 0;

    if (!$chasse_id || get_post_type($chasse_id) !== 'chasse') {
        wp_send_json_error('post_invalide');
    }
    if ($enigme_id && get_post_type($enigme_id) !== 'enigme') {
        $enigme_id = 0;
    }

    if (!solution_action_autorisee('create', 'chasse', $chasse_id)) {
        wp_send_json_error('acces_refuse');
    }

    $has_solution_chasse = solution_existe_pour_objet($chasse_id, 'chasse');
    $has_solution_enigme = $enigme_id ? solution_existe_pour_objet($enigme_id, 'enigme') : false;

    $toutes_enigmes = recuperer_enigmes_pour_chasse($chasse_id);
    $enigmes        = array_filter(
        $toutes_enigmes,
        static fn($e) => !solution_existe_pour_objet($e->ID, 'enigme')
    );
    $total_solutions = 0;
    if (function_exists('get_posts')) {
        $enigme_ids = array_map(static fn($e) => (int) $e->ID, $toutes_enigmes);
        $queryService = new ChassesAuTresor\Core\Content\SolutionQueryService();
        $count_posts = get_posts($queryService->getManagementQueryArgs(
            $chasse_id,
            'chasse',
            $enigme_ids,
            1,
            1,
            true
        ));
        $total_solutions = is_array($count_posts) ? count($count_posts) : 0;
    }

    wp_send_json_success($managementService->buildHuntStatus(
        $has_solution_chasse,
        $has_solution_enigme,
        count($toutes_enigmes),
        count($enigmes),
        $total_solutions
    ));
}
add_action('wp_ajax_chasse_solution_status', 'ajax_chasse_solution_status');

/**
 * Résout le fichier soumis par les modales de création et d'édition.
 *
 * @return array{id:int,submitted:bool,error:?string}
 */
function solution_resoudre_fichier_modal(int $solution_id): array
{
    return (new ChassesAuTresor\Core\Content\SolutionFileInputService())->resolve(
        $solution_id,
        isset($_FILES['solution_fichier']) ? (array) $_FILES['solution_fichier'] : [],
        isset($_POST['solution_fichier']),
        isset($_POST['solution_fichier']) ? (int) $_POST['solution_fichier'] : 0,
        static function (int $post_id) {
            if (!function_exists('media_handle_upload')) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                require_once ABSPATH . 'wp-admin/includes/media.php';
                require_once ABSPATH . 'wp-admin/includes/image.php';
            }

            return media_handle_upload('solution_fichier', $post_id);
        },
        'is_wp_error',
        static fn ($error): string => $error->get_error_message()
    );
}

/**
 * Crée une solution via une requête AJAX depuis une modale.
 *
 * @return void
 */
function ajax_creer_solution_modal(): void
{
    check_ajax_referer('solution_management', 'nonce');
    $fieldPolicy = new ChassesAuTresor\Core\Content\SolutionFieldPolicyService();
    $isAuthenticated = is_user_logged_in();
    $objet_id   = $isAuthenticated && isset($_POST['objet_id']) ? (int) $_POST['objet_id'] : 0;
    $objet_type = $isAuthenticated ? sanitize_key($_POST['objet_type'] ?? '') : '';
    $hasValidTarget = $objet_id > 0
        && in_array($objet_type, ['chasse', 'enigme'], true)
        && get_post_type($objet_id) === $objet_type;
    $linked = isset($_POST['solution_enigme_linked']) ? (int) $_POST['solution_enigme_linked'] : 0;
    $hasConsistentTarget = $hasValidTarget
        && $fieldPolicy->hasConsistentRiddleTarget($objet_type, $objet_id, $linked);
    $isAuthorized = $hasConsistentTarget && solution_action_autorisee('create', $objet_type, $objet_id);
    $has_file   = !empty($_FILES['solution_fichier']['tmp_name']) || !empty($_POST['solution_fichier']);
    $rawExplanation = (string) ($_POST['solution_explication'] ?? '');
    $requestError = (new ChassesAuTresor\Core\Content\SolutionModalPolicyService())->getCreationError(
        $isAuthenticated,
        $hasValidTarget,
        $hasConsistentTarget,
        $isAuthorized,
        $fieldPolicy->hasRequiredContent($has_file, $rawExplanation)
    );
    if ($requestError !== null) {
        wp_send_json_error($requestError);
    }

    $solution_id = creer_solution_pour_objet($objet_id, $objet_type);
    if (is_wp_error($solution_id)) {
        wp_send_json_error($solution_id->get_error_message());
    }

    $fileInput = solution_resoudre_fichier_modal($solution_id);
    if ($fileInput['error'] !== null) {
        wp_send_json_error($fileInput['error']);
    }

    $explic = wp_kses_post($rawExplanation);
    $dispo  = sanitize_key($_POST['solution_disponibilite'] ?? 'fin_chasse');
    $delai  = isset($_POST['solution_decalage_jours']) ? (int) $_POST['solution_decalage_jours'] : 0;
    $heure  = sanitize_text_field($_POST['solution_heure_publication'] ?? '');

    $schedule = $fieldPolicy->normalizeSchedule($dispo, $delai, $heure);
    ChassesAuTresor\Core\Content\SolutionMutationService::apply(
        $solution_id,
        $fileInput['id'],
        false,
        $explic,
        $schedule,
        false
    );

    wp_send_json_success(['solution_id' => $solution_id]);
}
add_action('wp_ajax_creer_solution_modal', 'ajax_creer_solution_modal');

/**
 * Met à jour une solution existante via le modal d'édition.
 *
 * @return void
 */
function ajax_modifier_solution_modal(): void
{
    check_ajax_referer('solution_management', 'nonce');
    $fieldPolicy = new ChassesAuTresor\Core\Content\SolutionFieldPolicyService();
    $isAuthenticated = is_user_logged_in();
    $solution_id = $isAuthenticated && isset($_POST['solution_id']) ? (int) $_POST['solution_id'] : 0;
    $hasValidSolution = $solution_id > 0 && get_post_type($solution_id) === 'solution';
    $objet_id = $hasValidSolution && isset($_POST['objet_id']) ? (int) $_POST['objet_id'] : 0;
    $objet_type = $hasValidSolution ? sanitize_key($_POST['objet_type'] ?? '') : '';
    $hasValidTarget = $objet_id > 0
        && in_array($objet_type, ['chasse', 'enigme'], true)
        && get_post_type($objet_id) === $objet_type;
    $isAuthorized = $hasValidTarget && solution_action_autorisee('edit', $objet_type, $objet_id);
    $has_file   = !empty($_FILES['solution_fichier']['tmp_name']) || !empty($_POST['solution_fichier']);
    $rawExplanation = (string) ($_POST['solution_explication'] ?? '');
    $requestError = (new ChassesAuTresor\Core\Content\SolutionModalPolicyService())->getEditionError(
        $isAuthenticated,
        $hasValidSolution,
        $hasValidTarget,
        $isAuthorized,
        $fieldPolicy->hasRequiredContent($has_file, $rawExplanation)
    );
    if ($requestError !== null) {
        wp_send_json_error($requestError);
    }

    $fileInput = solution_resoudre_fichier_modal($solution_id);
    if ($fileInput['error'] !== null) {
        wp_send_json_error($fileInput['error']);
    }

    $explic = wp_kses_post($rawExplanation);
    $dispo  = sanitize_key($_POST['solution_disponibilite'] ?? 'fin_chasse');
    $delai  = isset($_POST['solution_decalage_jours']) ? (int) $_POST['solution_decalage_jours'] : 0;
    $heure  = sanitize_text_field($_POST['solution_heure_publication'] ?? '');

    $schedule = $fieldPolicy->normalizeSchedule($dispo, $delai, $heure);
    ChassesAuTresor\Core\Content\SolutionMutationService::apply(
        $solution_id,
        $fileInput['id'],
        $fileInput['submitted'],
        $explic,
        $schedule,
        true
    );

    wp_send_json_success(['solution_id' => $solution_id]);
}
add_action('wp_ajax_modifier_solution_modal', 'ajax_modifier_solution_modal');

/**
 * Supprime une solution via requête AJAX.
 *
 * @hook wp_ajax_supprimer_solution
 * @return void
 */
function supprimer_solution_ajax(): void
{
    check_ajax_referer('solution_management', 'nonce');
    if (!is_user_logged_in()) {
        wp_send_json_error('non_connecte');
    }

    $solution_id = isset($_POST['solution_id']) ? (int) $_POST['solution_id'] : 0;
    if (!$solution_id || get_post_type($solution_id) !== 'solution') {
        wp_send_json_error('id_invalide');
    }

    $deletionService = new ChassesAuTresor\Core\Content\SolutionDeletionService();
    $target = $deletionService->resolveTarget(
        (string) get_field('solution_cible_type', $solution_id),
        get_field('solution_chasse_linked', $solution_id),
        get_field('solution_enigme_linked', $solution_id)
    );

    if ($target === null || !solution_action_autorisee('delete', $target['type'], $target['id'])) {
        wp_send_json_error('acces_refuse');
    }

    if (!$deletionService->delete($solution_id)) {
        wp_send_json_error('echec_suppression');
    }

    wp_send_json_success();
}
add_action('wp_ajax_supprimer_solution', 'supprimer_solution_ajax');
