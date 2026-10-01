<?php
defined('ABSPATH') || exit;

function enigme_stats_excluded_user_ids(int $enigme_id): array
{
    $excluded = function_exists('get_users') ? get_users(['role' => 'administrator', 'fields' => 'ids']) : [];
    if (
        function_exists('recuperer_id_chasse_associee')
        && function_exists('get_organisateur_from_chasse')
        && function_exists('get_field')
    ) {
        $huntId = (int) recuperer_id_chasse_associee($enigme_id);
        $organizerId = $huntId ? get_organisateur_from_chasse($huntId) : 0;
        $excluded = array_merge($excluded, $organizerId ? (array) get_field('utilisateurs_associes', $organizerId) : []);
    }
    return array_values(array_unique(array_filter(array_map('intval', $excluded))));
}

function enigme_stats_date_range(string $periode): array {
    return (new ChassesAuTresor\Core\Progress\StatisticsPeriodService())->range($periode);
}

function enigme_compter_joueurs_engages(int $enigme_id, string $periode = 'total'): int {
    [$debut, $fin] = $periode === 'total' ? [null, null] : enigme_stats_date_range($periode);
    return cat_get_riddle_statistics_service()->countEngagedPlayers(
        $enigme_id,
        $debut,
        $fin,
        enigme_stats_excluded_user_ids($enigme_id)
    );
}

function enigme_compter_tentatives(int $enigme_id, string $mode = 'automatique', string $periode = 'total'): int {
    [$debut, $fin] = $periode === 'total' ? [null, null] : enigme_stats_date_range($periode);
    return cat_get_riddle_statistics_service()->countAttempts($enigme_id, $debut, $fin);
}

function enigme_compter_points_depenses(int $enigme_id, string $mode = 'automatique', string $periode = 'total'): int {
    [$debut, $fin] = $periode === 'total' ? [null, null] : enigme_stats_date_range($periode);
    return cat_get_riddle_statistics_service()->sumSpentPoints($enigme_id, $debut, $fin);
}

function enigme_compter_bonnes_solutions(int $enigme_id, string $mode = 'automatique', string $periode = 'total'): int {
    [$debut, $fin] = $periode === 'total' ? [null, null] : enigme_stats_date_range($periode);
    return cat_get_riddle_statistics_service()->countCorrectSolutions($enigme_id, $debut, $fin);
}

function enigme_lister_resolveurs(int $enigme_id): array
{
    return cat_get_riddle_statistics_service()->listSolvers(
        $enigme_id,
        enigme_stats_excluded_user_ids($enigme_id)
    );
}

function enigme_lister_participants(
    int $enigme_id,
    string $mode = 'automatique',
    int $limit = 25,
    int $offset = 0,
    string $orderby = 'date',
    string $order = 'ASC'
): array {
    return cat_get_riddle_statistics_service()->listParticipants(
        $enigme_id,
        enigme_stats_excluded_user_ids($enigme_id),
        $limit,
        $offset,
        $orderby,
        $order
    );
}

/**
 * AJAX handler retrieving statistics for a riddle.
 *
 * Expects the following POST parameters:
 * - `enigme_id` (int, required) The ID of the riddle to inspect.
 * - `periode` (string, optional) One of `jour`, `semaine`, `mois` or `total`.
 *   Defaults to `total`.
 *
 * Sends a JSON success response containing at least:
 * - `participants` (int) Number of engaged players for the selected period.
 * - `tentatives` (int) Number of attempts. Present only when the validation
 *   mode is not `aucune`.
 * - `solutions` (int) Number of correct answers. Present only when the
 *   validation mode is not `aucune`.
 * - `points` (int) Total points collected. Present only when the cost per attempt
 *   is greater than zero.
 *
 * @return void
 */
function ajax_enigme_recuperer_stats(): void
{
    ChassesAuTresor\Core\Progress\RiddleStatisticsAjaxHandler::summary();
}


function enigme_stats_cache_key(int $enigme_id, string $periode): string
{
    return "enigme_stats_{$enigme_id}_{$periode}";
}

function enigme_clear_stats_cache(int $enigme_id): void
{
    (new ChassesAuTresor\Core\Progress\StatisticsCacheService())->clear('enigme', $enigme_id);
}

function ajax_enigme_lister_participants(): void
{
    ChassesAuTresor\Core\Progress\RiddleStatisticsAjaxHandler::participants();
}
