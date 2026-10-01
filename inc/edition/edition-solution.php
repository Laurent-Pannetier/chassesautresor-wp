<?php
/**
 * Gestion de la publication différée des solutions.
 *
 * @package chassesautresor.com
 */

require_once __DIR__ . '/../constants.php';

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
    return ChassesAuTresor\Core\Content\SolutionCreationRouteHandler::create(
        $objet_id,
        $objet_type,
        $user_id,
        static fn (string $type, int $id): bool => solution_action_autorisee('create', $type, $id),
        static fn (int $riddleId): ?int => recuperer_id_chasse_associee($riddleId)
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
 * Connects the core solution controllers to the theme permission policy.
 */
function autoriser_gestion_solution(bool $allowed, string $action, string $targetType, int $targetId): bool
{
    return solution_action_autorisee($action, $targetType, $targetId);
}
add_filter('chassesautresor_can_manage_solution', 'autoriser_gestion_solution', 10, 4);



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
