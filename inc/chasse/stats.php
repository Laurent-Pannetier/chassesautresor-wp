<?php
/**
 * Statistics helpers for hunts.
 */

defined('ABSPATH') || exit;

require_once __DIR__ . '/../enigme/stats.php';

if (!function_exists('cat_get_hunt_engagement_service')) {
    function cat_get_hunt_engagement_service(): ChassesAuTresor\Core\Progress\HuntEngagementService
    {
        global $wpdb;
        return ChassesAuTresor\Core\Support\CoreServiceFactory::huntEngagement($wpdb);
    }
}

function cat_get_hunt_statistics_service(): ChassesAuTresor\Core\Progress\HuntStatisticsService
{
    global $wpdb;
    return ChassesAuTresor\Core\Support\CoreServiceFactory::huntStatistics($wpdb);
}

function chasse_stats_excluded_user_ids(int $chasse_id): array
{
    $excluded = function_exists('get_users') ? get_users(['role' => 'administrator', 'fields' => 'ids']) : [];
    if (function_exists('get_organisateur_from_chasse') && function_exists('get_field')) {
        $organizerId = get_organisateur_from_chasse($chasse_id);
        $excluded = array_merge($excluded, $organizerId ? (array) get_field('utilisateurs_associes', $organizerId) : []);
    }
    return array_values(array_unique(array_filter(array_map('intval', $excluded))));
}

/**
 * Count distinct participants engaged in a hunt.
 */
function chasse_compter_participants(int $chasse_id, string $periode = 'total'): int
{
    $debut = null;
    $fin = null;
    if ($periode !== 'total') {
        [$debut, $fin] = enigme_stats_date_range($periode);
    }

    return cat_get_hunt_engagement_service()->countParticipants(
        $chasse_id,
        $debut,
        $fin,
        chasse_stats_excluded_user_ids($chasse_id)
    );
}

/**
 * Sum attempts for all riddles of a hunt.
 */
function chasse_compter_tentatives(int $chasse_id, string $periode = 'total'): int
{
    $enigme_ids = recuperer_ids_enigmes_pour_chasse($chasse_id);
    [$debut, $fin] = $periode === 'total' ? [null, null] : enigme_stats_date_range($periode);

    return cat_get_hunt_statistics_service()->countAttempts($enigme_ids, $debut, $fin);
}

/**
 * Sum collected points for all riddles of a hunt.
 */
function chasse_compter_points_collectes(int $chasse_id, string $periode = 'total'): int
{
    $enigme_ids = recuperer_ids_enigmes_pour_chasse($chasse_id);
    [$debut, $fin] = $periode === 'total' ? [null, null] : enigme_stats_date_range($periode);

    return cat_get_hunt_statistics_service()->sumCollectedPoints($enigme_ids, $debut, $fin);
}

/**
 * Count total engagements (hunt and riddles) for a hunt.
 */
function chasse_compter_engagements(int $chasse_id): int
{
    return cat_get_hunt_statistics_service()->countEngagements($chasse_id, chasse_stats_excluded_user_ids($chasse_id));
}

/**
 * Calculate engagement rate for a hunt.
 */
function chasse_calculer_taux_engagement(int $chasse_id, string $periode = 'total'): float
{
    $participants  = chasse_compter_participants($chasse_id, $periode);
    $enigme_ids    = recuperer_ids_enigmes_pour_chasse($chasse_id);
    [$debut, $fin] = $periode === 'total' ? [null, null] : enigme_stats_date_range($periode);

    return cat_get_hunt_statistics_service()->calculateEngagementRate(
        $participants,
        $enigme_ids,
        $debut,
        $fin,
        chasse_stats_excluded_user_ids($chasse_id)
    );
}

/**
 * Calculate resolution rate for a hunt.
 */
function chasse_calculer_taux_progression(int $chasse_id, string $periode = 'total'): float
{
    $enigme_ids = recuperer_ids_enigmes_pour_chasse($chasse_id);
    if (!$enigme_ids) {
        return 0.0;
    }

    $validables = array_filter($enigme_ids, function ($id) {
        return get_field('enigme_mode_validation', $id) !== 'aucune';
    });
    if (!$validables) {
        return 0.0;
    }

    [$debut, $fin] = $periode === 'total' ? [null, null] : enigme_stats_date_range($periode);
    return cat_get_hunt_statistics_service()->calculateResolutionRate(
        array_values($validables),
        $debut,
        $fin,
        chasse_stats_excluded_user_ids($chasse_id)
    );
}

/**
 * List hunt participants with aggregated statistics.
 *
 * Each participant includes registration date, engaged riddles and counts of
 * engaged and solved riddles.
 *
 * @return array<int, array{
 *     username:string,
 *     date_inscription:string,
 *     enigmes:array<int, array{id:int,title:string,url:string}>,
 *     nb_engagees:int,
 *     nb_resolues:int,
 * }>
 */
function chasse_lister_participants(int $chasse_id, int $limit, int $offset, string $orderby, string $order): array
{
    $enigme_ids = recuperer_ids_enigmes_pour_chasse($chasse_id);
    $excluded_user_ids = chasse_stats_excluded_user_ids($chasse_id);
    $service = cat_get_hunt_statistics_service();
    $rows = $service->listParticipants(
        $chasse_id,
        $enigme_ids,
        $excluded_user_ids,
        $limit,
        $offset,
        $orderby,
        $order
    );
    if (!$rows) {
        return [];
    }

    $participants = [];
    foreach ($rows as $row) {
        $user_id = (int) $row['user_id'];
        if ($enigme_ids) {
            $ids = $service->findEngagedRiddleIds($user_id, $enigme_ids);
        } else {
            $ids = [];
        }
        $engaged_ids = array_map('intval', $ids);
        $enigmes = array_map(
            static fn($eid) => [
                'id'    => $eid,
                'title' => get_the_title($eid),
                'url'   => get_permalink($eid),
            ],
            $engaged_ids
        );
        $participants[] = [
            'username'      => $row['username'],
            'date_inscription' => $row['date_inscription'],
            'enigmes'       => $enigmes,
            'nb_engagees'   => isset($row['nb_engagees']) ? (int) $row['nb_engagees'] : 0,
            'nb_resolues'   => isset($row['nb_resolues']) ? (int) $row['nb_resolues'] : 0,
        ];
    }

    return $participants;
}
/**
 * AJAX handler retrieving hunt statistics.
 */
function ajax_chasse_recuperer_stats(): void
{
    ChassesAuTresor\Core\Progress\HuntStatisticsAjaxHandler::summary();
}


function chasse_stats_cache_key(int $chasse_id, string $periode): string
{
    return "chasse_stats_{$chasse_id}_{$periode}";
}

function chasse_clear_stats_cache(int $chasse_id): void
{
    (new ChassesAuTresor\Core\Progress\StatisticsCacheService())->clear('chasse', $chasse_id);
}

function chasse_invalidate_cache_from_enigme(int $enigme_id): void
{
    $chasse_id = recuperer_id_chasse_associee($enigme_id);
    if ($chasse_id) {
        chasse_clear_stats_cache((int) $chasse_id);
    }
}

/**
 * AJAX handler listing hunt participants.
 */
function ajax_chasse_lister_participants(): void
{
    ChassesAuTresor\Core\Progress\HuntStatisticsAjaxHandler::participants();
}

function cat_render_hunt_statistics_participants(
    int $hunt_id,
    array $participants,
    array $request,
    int $total,
    int $pages,
    string $orderby
): string {
    ob_start();
    get_template_part('template-parts/chasse/partials/chasse-partial-participants', null, [
        'participants' => $participants,
        'page' => $request['page'],
        'par_page' => $request['limit'],
        'total' => $total,
        'pages' => $pages,
        'chasse_titre' => get_the_title($hunt_id),
        'total_enigmes' => count(recuperer_ids_enigmes_pour_chasse($hunt_id)),
        'orderby' => $orderby,
        'order' => $request['order'],
    ]);

    return (string) ob_get_clean();
}

if (class_exists(ChassesAuTresor\Core\Progress\HuntStatisticsAjaxHandler::class)) {
    ChassesAuTresor\Core\Progress\HuntStatisticsAjaxHandler::configure(
        static function (...$arguments): string {
            return cat_render_hunt_statistics_participants(...$arguments);
        }
    );
}
