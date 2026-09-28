<?php
/**
 * Statistics helpers for hunts.
 */

defined('ABSPATH') || exit;

require_once __DIR__ . '/../enigme/stats.php';

/**
 * Count distinct participants engaged in a hunt.
 */
function chasse_compter_participants(int $chasse_id, string $periode = 'total'): int
{
    if (
        function_exists('ca_demo_is_demo_hunt')
        && ca_demo_is_demo_hunt($chasse_id)
    ) {
        return 0;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'engagements';
    $where = 'chasse_id = %d AND enigme_id IS NULL';
    $params = [$chasse_id];
    if ($periode !== 'total') {
        [$debut, $fin] = enigme_stats_date_range($periode);
        if ($debut && $fin) {
            $where .= ' AND date_engagement BETWEEN %s AND %s';
            $params[] = $debut;
            $params[] = $fin;
        }
    }
    $sql = $wpdb->prepare("SELECT COUNT(DISTINCT user_id) FROM $table WHERE $where", ...$params);
    return (int) $wpdb->get_var($sql);
}

/**
 * Sum attempts for all riddles of a hunt.
 */
function chasse_compter_tentatives(int $chasse_id, string $periode = 'total'): int
{
    if (
        function_exists('ca_demo_is_demo_hunt')
        && ca_demo_is_demo_hunt($chasse_id)
    ) {
        return 0;
    }

    $enigme_ids = recuperer_ids_enigmes_pour_chasse($chasse_id);
    if (!$enigme_ids) {
        return 0;
    }

    global $wpdb;
    $table        = $wpdb->prefix . 'enigme_tentatives';
    $placeholders = implode(',', array_fill(0, count($enigme_ids), '%d'));
    $where        = "enigme_id IN ({$placeholders})";
    $params       = $enigme_ids;

    if ($periode !== 'total') {
        [$debut, $fin] = enigme_stats_date_range($periode);
        if ($debut && $fin) {
            $where   .= ' AND date_tentative BETWEEN %s AND %s';
            $params[] = $debut;
            $params[] = $fin;
        }
    }

    $sql = $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE {$where}", ...$params);

    return (int) $wpdb->get_var($sql);
}

/**
 * Sum collected points for all riddles of a hunt.
 */
function chasse_compter_points_collectes(int $chasse_id, string $periode = 'total'): int
{
    if (
        function_exists('ca_demo_is_demo_hunt')
        && ca_demo_is_demo_hunt($chasse_id)
    ) {
        return 0;
    }

    $enigme_ids = recuperer_ids_enigmes_pour_chasse($chasse_id);
    if (!$enigme_ids) {
        return 0;
    }

    global $wpdb;
    $table        = $wpdb->prefix . 'enigme_tentatives';
    $placeholders = implode(',', array_fill(0, count($enigme_ids), '%d'));
    $where        = "enigme_id IN ({$placeholders})";
    $params       = $enigme_ids;

    if ($periode !== 'total') {
        [$debut, $fin] = enigme_stats_date_range($periode);
        if ($debut && $fin) {
            $where   .= ' AND date_tentative BETWEEN %s AND %s';
            $params[] = $debut;
            $params[] = $fin;
        }
    }

    $sql = $wpdb->prepare("SELECT SUM(points_utilises) FROM {$table} WHERE {$where}", ...$params);
    $res = $wpdb->get_var($sql);

    return $res ? (int) $res : 0;
}

/**
 * Count total engagements (hunt and riddles) for a hunt.
 */
function chasse_compter_engagements(int $chasse_id): int
{
    if (
        function_exists('ca_demo_is_demo_hunt')
        && ca_demo_is_demo_hunt($chasse_id)
    ) {
        return 0;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'engagements';
    $sql = $wpdb->prepare("SELECT COUNT(*) FROM $table WHERE chasse_id = %d", $chasse_id);
    return (int) $wpdb->get_var($sql);
}

/**
 * Calculate engagement rate for a hunt.
 */
function chasse_calculer_taux_engagement(int $chasse_id, string $periode = 'total'): float
{
    if (
        function_exists('ca_demo_is_demo_hunt')
        && ca_demo_is_demo_hunt($chasse_id)
    ) {
        return 0.0;
    }

    $participants  = chasse_compter_participants($chasse_id, $periode);
    $enigme_ids    = recuperer_ids_enigmes_pour_chasse($chasse_id);
    $total_enigmes = count($enigme_ids);
    if ($participants === 0 || $total_enigmes === 0) {
        return 0.0;
    }

    global $wpdb;
    $table        = $wpdb->prefix . 'engagements';
    $placeholders = implode(',', array_fill(0, count($enigme_ids), '%d'));
    $where        = "enigme_id IN ({$placeholders})";
    $params       = $enigme_ids;

    if ($periode !== 'total') {
        [$debut, $fin] = enigme_stats_date_range($periode);
        if ($debut && $fin) {
            $where   .= ' AND date_engagement BETWEEN %s AND %s';
            $params[] = $debut;
            $params[] = $fin;
        }
    }

    $sql = $wpdb->prepare(
        "SELECT SUM(cnt) FROM (SELECT COUNT(DISTINCT user_id) AS cnt FROM {$table} WHERE {$where} GROUP BY enigme_id) t",
        ...$params
    );
    $total = (int) $wpdb->get_var($sql);

    return (100 * $total) / ($participants * $total_enigmes);
}

/**
 * Calculate resolution rate for a hunt.
 */
function chasse_calculer_taux_progression(int $chasse_id, string $periode = 'total'): float
{
    if (
        function_exists('ca_demo_is_demo_hunt')
        && ca_demo_is_demo_hunt($chasse_id)
    ) {
        return 0.0;
    }

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

    global $wpdb;
    $placeholders = implode(',', array_fill(0, count($validables), '%d'));

    $where_eng = "enigme_id IN ({$placeholders})";
    $where_res = "enigme_id IN ({$placeholders}) AND statut IN ('resolue','terminee','terminée')";
    $params_eng = $validables;
    $params_res = $validables;

    if ($periode !== 'total') {
        [$debut, $fin] = enigme_stats_date_range($periode);
        if ($debut && $fin) {
            $where_eng .= ' AND date_engagement BETWEEN %s AND %s';
            $where_res .= ' AND date_mise_a_jour BETWEEN %s AND %s';
            $params_eng[] = $debut;
            $params_eng[] = $fin;
            $params_res[] = $debut;
            $params_res[] = $fin;
        }
    }

    $table_eng = $wpdb->prefix . 'engagements';
    $sql_eng   = $wpdb->prepare(
        "SELECT SUM(cnt) FROM (SELECT COUNT(DISTINCT user_id) AS cnt FROM {$table_eng} WHERE {$where_eng} GROUP BY enigme_id) t",
        ...$params_eng
    );
    $total_engages = (int) $wpdb->get_var($sql_eng);
    if ($total_engages === 0) {
        return 0.0;
    }

    $table_res = $wpdb->prefix . 'enigme_statuts_utilisateur';
    $sql_res   = $wpdb->prepare(
        "SELECT SUM(cnt) FROM (SELECT COUNT(DISTINCT user_id) AS cnt FROM {$table_res} WHERE {$where_res} GROUP BY enigme_id) t",
        ...$params_res
    );
    $total_resolus = (int) $wpdb->get_var($sql_res);

    return (100 * $total_resolus) / $total_engages;
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
    global $wpdb;
    $table_eng  = $wpdb->prefix . 'engagements';
    $table_stat = $wpdb->prefix . 'enigme_statuts_utilisateur';

    $enigme_ids = recuperer_ids_enigmes_pour_chasse($chasse_id);
    if ($enigme_ids) {
        $placeholders = implode(',', array_fill(0, count($enigme_ids), '%d'));
        $join_filter  = " AND e2.enigme_id IN ({$placeholders})";
        $params_join  = $enigme_ids;
    } else {
        $join_filter = ' AND 1=0';
        $params_join = [];
    }

    $order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';
    $orderby_map = [
        'username' => 'username',
        'participation' => 'nb_engagees',
        'resolution' => 'nb_resolues',
        'inscription' => 'date_inscription',
    ];
    $orderby = $orderby_map[$orderby] ?? 'date_inscription';

    $query =
        "SELECT e.user_id, u.user_login AS username, MIN(e.date_engagement) AS date_inscription," .
        " COUNT(DISTINCT e2.enigme_id) AS nb_engagees," .
        " COUNT(DISTINCT CASE WHEN s.statut IN ('resolue','terminee') THEN s.enigme_id END) AS nb_resolues" .
        " FROM {$table_eng} e" .
        " JOIN {$wpdb->users} u ON u.ID = e.user_id" .
        " LEFT JOIN {$table_eng} e2 ON e2.user_id = e.user_id{$join_filter}" .
        " LEFT JOIN {$table_stat} s ON s.user_id = e.user_id" .
        " AND s.enigme_id = e2.enigme_id AND s.statut IN ('resolue','terminee')" .
        " WHERE e.chasse_id = %d AND e.enigme_id IS NULL" .
        " GROUP BY e.user_id" .
        " ORDER BY {$orderby} {$order}" .
        " LIMIT %d OFFSET %d";
    $params = array_merge($params_join, [$chasse_id, $limit, $offset]);
    $sql    = $wpdb->prepare($query, $params);

    $rows = $wpdb->get_results($sql, ARRAY_A);
    if (!$rows) {
        return [];
    }

    $participants = [];
    foreach ($rows as $row) {
        $user_id = (int) $row['user_id'];
        if ($enigme_ids) {
            $placeholders = implode(',', array_fill(0, count($enigme_ids), '%d'));
            $params_ids   = array_merge([$user_id], $enigme_ids);
            $ids          = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT DISTINCT enigme_id FROM {$table_eng}" .
                    " WHERE user_id = %d AND enigme_id IN ({$placeholders})",
                    $params_ids
                )
            );
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
    $chasse_id = isset($_POST['chasse_id']) ? (int) $_POST['chasse_id'] : 0;
    if ($chasse_id <= 0) {
        wp_send_json_error('missing_chasse', 400);
    }

    if (!utilisateur_est_organisateur_associe_a_chasse(get_current_user_id(), $chasse_id)) {
        wp_send_json_error('forbidden', 403);
    }

    $periode = isset($_POST['periode']) ? sanitize_text_field($_POST['periode']) : 'total';
    $periode = in_array($periode, ['jour', 'semaine', 'mois', 'total'], true) ? $periode : 'total';

    $cache_key = chasse_stats_cache_key($chasse_id, $periode);
    $stats = wp_cache_get($cache_key, 'chasse_stats');
    if ($stats === false) {
        $stats = get_transient($cache_key);
    }

    if ($stats === false) {
        $stats = [
            'participants'   => chasse_compter_participants($chasse_id, $periode),
            'tentatives'     => chasse_compter_tentatives($chasse_id, $periode),
            'points'         => chasse_compter_points_collectes($chasse_id, $periode),
            'engagement_rate' => (int) round(chasse_calculer_taux_engagement($chasse_id, $periode)),
        ];
        $ttl = HOUR_IN_SECONDS;
        wp_cache_set($cache_key, $stats, 'chasse_stats', $ttl);
        set_transient($cache_key, $stats, $ttl);
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
    foreach (['jour', 'semaine', 'mois', 'total'] as $p) {
        $key = chasse_stats_cache_key($chasse_id, $p);
        wp_cache_delete($key, 'chasse_stats');
        delete_transient($key);
    }
}

function chasse_invalidate_cache_from_enigme(int $enigme_id): void
{
    $chasse_id = recuperer_id_chasse_associee($enigme_id);
    if ($chasse_id) {
        chasse_clear_stats_cache((int) $chasse_id);
    }
}

add_action('chasse_engagement_created', 'chasse_clear_stats_cache');
add_action('enigme_engagement_created', 'chasse_invalidate_cache_from_enigme');
add_action('enigme_tentative_created', 'chasse_invalidate_cache_from_enigme');

/**
 * AJAX handler listing hunt participants.
 */
function ajax_chasse_lister_participants()
{
    if (!is_user_logged_in()) {
        wp_send_json_error('non_connecte');
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
