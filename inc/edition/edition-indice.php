<?php
defined('ABSPATH') || exit;

if (!class_exists(ChassesAuTresor\Core\Content\HintQueryService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/HintQueryService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\HintStatusService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/HintStatusService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\HintCacheService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/HintCacheService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\HintCacheUpdater::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/HintCacheUpdater.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\HintScheduler::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/HintScheduler.php';
}

if (!class_exists(ChassesAuTresor\Core\Relationships\RelationshipService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Relationships/RelationshipService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\HintTitleService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/HintTitleService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\HintOrderingService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/HintOrderingService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\HintOrderingUpdater::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/HintOrderingUpdater.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\HintOrderingApplicationService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/HintOrderingApplicationService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\HintOrderingLifecycleHookHandler::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/HintOrderingLifecycleHookHandler.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\HintCreationService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/HintCreationService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\HintCreationRequestService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/HintCreationRequestService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\HintPostFactory::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/HintPostFactory.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\HintRouteRegistrar::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/HintRouteRegistrar.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\HintCreationRouteHandler::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/HintCreationRouteHandler.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\HintFieldPolicyService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/HintFieldPolicyService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\HintMutationService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/HintMutationService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\HintModalAjaxHandler::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/HintModalAjaxHandler.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\HintFieldMutationService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/HintFieldMutationService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\HintDeletionService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/HintDeletionService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\HintDeletionAjaxHandler::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/HintDeletionAjaxHandler.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\HintRelationshipService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/HintRelationshipService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\HintManagementService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/HintManagementService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\HintRedirectHandler::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/HintRedirectHandler.php';
}

function cat_get_hint_query_service(): ChassesAuTresor\Core\Content\HintQueryService
{
    return new ChassesAuTresor\Core\Content\HintQueryService();
}

function cat_get_hint_status_service(): ChassesAuTresor\Core\Content\HintStatusService
{
    return new ChassesAuTresor\Core\Content\HintStatusService();
}

function cat_get_hint_cache_service(): ChassesAuTresor\Core\Content\HintCacheService
{
    return new ChassesAuTresor\Core\Content\HintCacheService(cat_get_hint_status_service());
}



function cat_get_hint_title_service(): ChassesAuTresor\Core\Content\HintTitleService
{
    return new ChassesAuTresor\Core\Content\HintTitleService();
}



function cat_get_hint_field_policy_service(): ChassesAuTresor\Core\Content\HintFieldPolicyService
{
    return new ChassesAuTresor\Core\Content\HintFieldPolicyService();
}







function cat_get_hint_relationship_service(): ChassesAuTresor\Core\Content\HintRelationshipService
{
    return new ChassesAuTresor\Core\Content\HintRelationshipService(
        new ChassesAuTresor\Core\Relationships\RelationshipService()
    );
}



// ==================================================
// 💡 GESTION DES INDICES
// ==================================================
// 🔹 register_endpoint_creer_indice() → Enregistre /creer-indice
// 🔹 creer_indice_pour_objet() → Crée un indice lié à une chasse ou une énigme
// 🔹 creer_indice_et_rediriger_si_appel() → Crée un indice et redirige
// 🔹 rediriger_si_affichage_indice() → Redirige toute page indice vers sa cible
// 🔹 modifier_champ_indice() → Mise à jour AJAX (champ ACF ou natif)

/**
 * Generate a placeholder title for an indice based on its chasse.
 *
 * @param int $chasse_id Related hunt ID.
 * @return string
 */
function build_indice_placeholder_title(int $chasse_id): string
{
    $prefix = defined('INDICE_DEFAULT_PREFIX') ? INDICE_DEFAULT_PREFIX : 'clue-';
    $slug = (string) get_post_field('post_name', $chasse_id);
    $generatedSlug = '';

    if ($slug === '') {
        $chasseName = (string) get_post_field('post_title', $chasse_id);
        $generatedSlug = $chasseName !== ''
            ? (function_exists('sanitize_title')
                ? sanitize_title($chasseName)
                : strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $chasseName), '-')))
            : '';
    }

    return cat_get_hint_title_service()->buildPlaceholder($prefix, $slug, $generatedSlug);
}

/**
 * Redirige l’affichage d’un indice vers sa chasse ou son énigme liée.
 *
 * @return void
 */
function rediriger_si_affichage_indice(): void
{
    ChassesAuTresor\Core\Content\HintRedirectHandler::redirectIfViewingHint();
}
add_action('template_redirect', 'rediriger_si_affichage_indice');


/**
 * Calcule le rang du prochain indice pour une chasse ou une énigme.
 *
 * @param int    $objet_id   ID de la chasse ou de l’énigme.
 * @param string $objet_type Type de cible ('chasse' ou 'enigme').
 * @return int
 */
function prochain_rang_indice(int $objet_id, string $objet_type): int
{
    $queryArgs = cat_get_hint_query_service()->getRankedHintIdsQueryArgs($objet_id, $objet_type);
    if ($queryArgs === []) {
        return 1;
    }

    $existing_indices = function_exists('get_posts')
        ? get_posts($queryArgs)
        : [];

    return count($existing_indices) + 1;
}

/**
 * Crée un indice lié à une chasse ou une énigme.
 *
 * @param int      $objet_id   ID de la chasse ou de l’énigme.
 * @param string   $objet_type Type de cible ('chasse' ou 'enigme').
 * @param int|null $user_id    ID utilisateur (null = courant).
 * @return int|WP_Error
 */
function creer_indice_pour_objet(int $objet_id, string $objet_type, ?int $user_id = null)
{
    return ChassesAuTresor\Core\Content\HintCreationRouteHandler::create(
        $objet_id,
        $objet_type,
        $user_id,
        static fn (string $type, int $id): bool => utilisateur_peut_modifier_post($id),
        static fn (int $riddleId): ?int => recuperer_id_chasse_associee($riddleId)
    );
}

/**
 * Enregistre l’URL personnalisée /creer-indice/
 *
 * @return void
 */
function register_endpoint_creer_indice(): void
{
    ChassesAuTresor\Core\Content\HintRouteRegistrar::register();
}

/**
 * S'assure que les règles de réécriture prennent en compte /creer-indice/.
 *
 * Cette fonction est exécutée lors de l'activation du thème ou
 * automatiquement une fois si les règles n'ont pas encore été mises à jour.
 *
 * @return void
 */
function flush_rewrite_rules_creer_indice(): void
{
    ChassesAuTresor\Core\Content\HintRouteRegistrar::flush();
}



function autoriser_gestion_indice(
    bool $allowed,
    string $action,
    string $targetType,
    int $targetId
): bool {
    return indice_action_autorisee($action, $targetType, $targetId);
}
add_filter('chassesautresor_can_manage_hint', 'autoriser_gestion_indice', 10, 4);

function rendre_carte_indices(string $html, int $huntId): string
{
    ob_start();
    get_template_part(
        'template-parts/chasse/partials/chasse-partial-indices',
        null,
        ['objet_id' => $huntId, 'objet_type' => 'chasse']
    );

    return (string) ob_get_clean();
}
add_filter('chassesautresor_render_hint_card', 'rendre_carte_indices', 10, 2);

/** @return int[] */
function fournir_ids_enigmes_table_indice(array $riddleIds, int $huntId): array
{
    return recuperer_ids_enigmes_pour_chasse($huntId);
}
add_filter('chassesautresor_hint_hunt_riddle_ids', 'fournir_ids_enigmes_table_indice', 10, 2);

function fournir_chasse_liee_table_indice(int $huntId, int $riddleId): int
{
    return (int) recuperer_id_chasse_associee($riddleId);
}
add_filter('chassesautresor_hint_related_hunt_id', 'fournir_chasse_liee_table_indice', 10, 2);

/**
 * @param object[] $hints
 * @param array{total:int,hunt:int,riddle:int} $counts
 * @param array<string, mixed>|null $toggle
 */
function rendre_table_indices(
    string $html,
    array $hints,
    int $page,
    int $pages,
    string $targetType,
    int $targetId,
    array $counts,
    ?array $toggle
): string {
    ob_start();
    get_template_part('template-parts/common/indices-table', null, [
        'indices' => $hints,
        'page' => $page,
        'pages' => $pages,
        'objet_type' => $targetType,
        'objet_id' => $targetId,
        'count_total' => $counts['total'],
        'count_chasse' => $counts['hunt'],
        'count_enigme' => $counts['riddle'],
        'toggle' => $toggle,
    ]);

    return (string) ob_get_clean();
}
add_filter('chassesautresor_render_hint_table', 'rendre_table_indices', 10, 9);

/** @return array<int, object> */
function fournir_enigmes_cibles_indice(array $riddles, int $huntId): array
{
    return recuperer_enigmes_pour_chasse($huntId);
}
add_filter('chassesautresor_hint_target_riddles', 'fournir_enigmes_cibles_indice', 10, 2);

function fournir_prochain_rang_indice(
    int $rank,
    int $targetId,
    string $targetType
): int {
    return prochain_rang_indice($targetId, $targetType);
}
add_filter('chassesautresor_next_hint_rank', 'fournir_prochain_rang_indice', 10, 3);

function indiquer_solution_cible_indice(bool $exists, int $targetId, string $targetType): bool
{
    return solution_existe_pour_objet($targetId, $targetType);
}
add_filter('chassesautresor_hint_target_has_solution', 'indiquer_solution_cible_indice', 10, 3);




function autoriser_modification_indice(bool $allowed, int $hintId): bool
{
    return utilisateur_peut_modifier_post($hintId);
}
add_filter('chassesautresor_can_modify_hint', 'autoriser_modification_indice', 10, 2);

function autoriser_modification_champs_indice(bool $allowed, int $hintId): bool
{
    return utilisateur_peut_editer_champs($hintId);
}
add_filter('chassesautresor_can_edit_hint_fields', 'autoriser_modification_champs_indice', 10, 2);

/**
 * Pré-remplit automatiquement la chasse liée d'un indice lors de sa création.
 *
 * @param array $field Paramètres du champ ACF.
 * @return array Champ modifié.
 */
function pre_remplir_indice_chasse_linked(array $field): array
{
    global $post;

    if (!$post || get_post_type($post->ID) !== 'indice') {
        return $field;
    }

    $existing = get_post_meta($post->ID, 'indice_chasse_linked', true);
    if (!empty($existing)) {
        return $field;
    }

    $chasse_id = cat_get_hint_relationship_service()->resolveLinkedHuntId(
        (string) get_field('indice_cible_type', $post->ID),
        get_field('indice_enigme_linked', $post->ID),
        isset($_GET['chasse_id']) ? (int) $_GET['chasse_id'] : null,
        static fn (int $riddleId) => recuperer_id_chasse_associee($riddleId)
    );

    if ($chasse_id) {
        $field['value'] = $chasse_id;
    }

    return $field;
}
add_filter('acf/load_field/name=indice_chasse_linked', 'pre_remplir_indice_chasse_linked');
