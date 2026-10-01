<?php
defined('ABSPATH') || exit;

if (!class_exists(ChassesAuTresor\Core\Progress\RiddleStatisticsService::class, false)) {
    require_once dirname(__DIR__, 4) . '/plugins/chassesautresor-core/src/Progress/RiddleStatisticsRepository.php';
    require_once dirname(__DIR__, 4) . '/plugins/chassesautresor-core/src/Progress/RiddleStatisticsService.php';
}

if (!class_exists(ChassesAuTresor\Core\Progress\StatisticsCacheService::class, false)) {
    require_once dirname(__DIR__, 4) . '/plugins/chassesautresor-core/src/Progress/StatisticsPeriodService.php';
    require_once dirname(__DIR__, 4) . '/plugins/chassesautresor-core/src/Progress/StatisticsCacheService.php';
}

if (!function_exists('cat_get_riddle_statistics_service')) {
    function cat_get_riddle_statistics_service(): ChassesAuTresor\Core\Progress\RiddleStatisticsService
    {
        global $wpdb;
        return new ChassesAuTresor\Core\Progress\RiddleStatisticsService(
            new ChassesAuTresor\Core\Progress\RiddleStatisticsRepository($wpdb)
        );
    }
}

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
function ajax_enigme_recuperer_stats()
{
    if (!wp_verify_nonce((string) ($_POST['nonce'] ?? ''), 'statistics_management')) {
        wp_send_json_error('invalid_nonce', 403);
    }

    $enigme_id = isset($_POST['enigme_id']) ? (int) $_POST['enigme_id'] : 0;
    if ($enigme_id <= 0) {
        wp_send_json_error('missing_enigme', 400);
    }

    if (!utilisateur_peut_voir_panneau($enigme_id)) {
        wp_send_json_error('forbidden', 403);
    }

    $periodService = new ChassesAuTresor\Core\Progress\StatisticsPeriodService();
    $periode = $periodService->normalize(sanitize_text_field($_POST['periode'] ?? 'total'));
    $cache = new ChassesAuTresor\Core\Progress\StatisticsCacheService();
    $stats = $cache->get('enigme', $enigme_id, $periode);

    if ($stats === false) {
        $mode = get_field('enigme_mode_validation', $enigme_id) ?? 'automatique';
        $cout = (int) get_field('enigme_tentative_cout_points', $enigme_id);

        $stats = [
            'participants' => enigme_compter_joueurs_engages($enigme_id, $periode),
        ];

        if ($mode !== 'aucune') {
            $stats['tentatives'] = enigme_compter_tentatives($enigme_id, $mode, $periode);
            $stats['solutions'] = enigme_compter_bonnes_solutions($enigme_id, $mode, $periode);
        }

        if ($cout > 0) {
            $stats['points'] = enigme_compter_points_depenses($enigme_id, $mode, $periode);
        }

        $cache->put('enigme', $enigme_id, $periode, $stats, HOUR_IN_SECONDS);
    }

    wp_send_json_success($stats);
}
add_action('wp_ajax_enigme_recuperer_stats', 'ajax_enigme_recuperer_stats');

function enigme_stats_cache_key(int $enigme_id, string $periode): string
{
    return "enigme_stats_{$enigme_id}_{$periode}";
}

function enigme_clear_stats_cache(int $enigme_id): void
{
    (new ChassesAuTresor\Core\Progress\StatisticsCacheService())->clear('enigme', $enigme_id);
}

function ajax_enigme_lister_participants() {
    if (!is_user_logged_in()) {
        wp_send_json_error('non_connecte');
    }
    if (!wp_verify_nonce((string) ($_POST['nonce'] ?? ''), 'statistics_management')) {
        wp_send_json_error('invalid_nonce', 403);
    }

    $enigme_id = isset($_POST['enigme_id']) ? (int) $_POST['enigme_id'] : 0;
    $page = max(1, (int) ($_POST['page'] ?? 1));
    $orderby = isset($_POST['orderby']) ? sanitize_text_field($_POST['orderby']) : 'date';
    $order = isset($_POST['order']) ? sanitize_text_field($_POST['order']) : 'ASC';

    if (!$enigme_id || get_post_type($enigme_id) !== 'enigme') {
        wp_send_json_error('post_invalide');
    }

    if (!utilisateur_peut_modifier_post($enigme_id)) {
        wp_send_json_error('acces_refuse');
    }

    $mode = get_field('enigme_mode_validation', $enigme_id) ?? 'aucune';
    $par_page = 25;
    $offset = ($page - 1) * $par_page;
    $participants = enigme_lister_participants($enigme_id, $mode, $par_page, $offset, $orderby, $order);
    $total = enigme_compter_joueurs_engages($enigme_id);
    $pages = (int) ceil($total / $par_page);

    ob_start();
    get_template_part('template-parts/enigme/partials/enigme-partial-participants', null, [
        'participants' => $participants,
        'page' => $page,
        'par_page' => $par_page,
        'total' => $total,
        'pages' => $pages,
        'mode_validation' => $mode,
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
add_action('wp_ajax_enigme_lister_participants', 'ajax_enigme_lister_participants');
