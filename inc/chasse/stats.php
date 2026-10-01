<?php
/**
 * Statistics helpers for hunts.
 */

defined('ABSPATH') || exit;

require_once __DIR__ . '/../enigme/stats.php';

if (!class_exists(ChassesAuTresor\Core\Progress\HuntEngagementService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Progress/HuntEngagementRepository.php';
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Progress/HuntEngagementService.php';
}

if (!class_exists(ChassesAuTresor\Core\Progress\HuntStatisticsService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Progress/HuntStatisticsRepository.php';
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Progress/HuntStatisticsService.php';
}

if (!function_exists('cat_get_hunt_engagement_service')) {
    function cat_get_hunt_engagement_service(): ChassesAuTresor\Core\Progress\HuntEngagementService
    {
        global $wpdb;

        return new ChassesAuTresor\Core\Progress\HuntEngagementService(
            new ChassesAuTresor\Core\Progress\HuntEngagementRepository($wpdb)
        );
    }
}

function cat_get_hunt_statistics_service(): ChassesAuTresor\Core\Progress\HuntStatisticsService
{
    global $wpdb;

    return new ChassesAuTresor\Core\Progress\HuntStatisticsService(
        new ChassesAuTresor\Core\Progress\HuntStatisticsRepository($wpdb)
    );
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
function ajax_chasse_recuperer_stats()
{
    if (!wp_verify_nonce((string) ($_POST['nonce'] ?? ''), 'statistics_management')) {
        wp_send_json_error('invalid_nonce', 403);
    }

    $chasse_id = isset($_POST['chasse_id']) ? (int) $_POST['chasse_id'] : 0;
    if ($chasse_id <= 0) {
        wp_send_json_error('missing_chasse', 400);
    }

    if (!utilisateur_est_organisateur_associe_a_chasse(get_current_user_id(), $chasse_id)) {
        wp_send_json_error('forbidden', 403);
    }

    $periodService = new ChassesAuTresor\Core\Progress\StatisticsPeriodService();
    $periode = $periodService->normalize(sanitize_text_field($_POST['periode'] ?? 'total'));
    $cache = new ChassesAuTresor\Core\Progress\StatisticsCacheService();
    $stats = $cache->get('chasse', $chasse_id, $periode);

    if ($stats === false) {
        $stats = [
            'participants'   => chasse_compter_participants($chasse_id, $periode),
            'tentatives'     => chasse_compter_tentatives($chasse_id, $periode),
            'points'         => chasse_compter_points_collectes($chasse_id, $periode),
            'engagement_rate' => (int) round(chasse_calculer_taux_engagement($chasse_id, $periode)),
        ];
        $cache->put('chasse', $chasse_id, $periode, $stats, HOUR_IN_SECONDS);
    }

    wp_send_json_success($stats);
}
add_action('wp_ajax_chasse_recuperer_stats', 'ajax_chasse_recuperer_stats');

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
function ajax_chasse_lister_participants()
{
    if (!is_user_logged_in()) {
        wp_send_json_error('non_connecte');
    }
    if (!wp_verify_nonce((string) ($_POST['nonce'] ?? ''), 'statistics_management')) {
        wp_send_json_error('invalid_nonce', 403);
    }

    $chasse_id = isset($_POST['chasse_id']) ? (int) $_POST['chasse_id'] : 0;
    $page      = max(1, (int) ($_POST['page'] ?? 1));
    $order     = isset($_POST['order']) ? sanitize_text_field($_POST['order']) : 'ASC';
    $orderby   = isset($_POST['orderby']) ? sanitize_text_field($_POST['orderby']) : 'inscription';
    $order     = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';
    $orderby   = in_array($orderby, ['inscription', 'username', 'participation', 'resolution'], true) ? $orderby : 'inscription';

    if (!$chasse_id || get_post_type($chasse_id) !== 'chasse') {
        wp_send_json_error('post_invalide');
    }

    if (!utilisateur_est_organisateur_associe_a_chasse(get_current_user_id(), $chasse_id)) {
        wp_send_json_error('acces_refuse');
    }

    $par_page = 25;
    $offset = ($page - 1) * $par_page;
    $participants = chasse_lister_participants($chasse_id, $par_page, $offset, $orderby, $order);
    $total = chasse_compter_participants($chasse_id);
    $pages = (int) ceil($total / $par_page);

    ob_start();
    get_template_part('template-parts/chasse/partials/chasse-partial-participants', null, [
        'participants' => $participants,
        'page' => $page,
        'par_page' => $par_page,
        'total' => $total,
        'pages' => $pages,
        'chasse_titre' => get_the_title($chasse_id),
        'total_enigmes' => count(recuperer_ids_enigmes_pour_chasse($chasse_id)),
        'orderby' => $orderby,
        'order' => $order,
    ]);
    $html = ob_get_clean();

    wp_send_json_success([
        'html' => $html,
        'total' => $total,
        'page' => $page,
        'pages' => $pages,
    ]);
}
add_action('wp_ajax_chasse_lister_participants', 'ajax_chasse_lister_participants');
