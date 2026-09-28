<?php
/**
 * Statistics helpers for organizers.
 */

defined('ABSPATH') || exit;

require_once __DIR__ . '/../chasse/stats.php';

/**
 * Count distinct players engaged in all hunts of an organizer.
 */
function organisateur_compter_joueurs_uniques(int $organisateur_id): int
{
    $query = get_chasses_de_organisateur($organisateur_id);
    if (!$query || empty($query->posts)) {
        return 0;
    }

    $ids = array_map('intval', $query->posts);
    if (empty($ids)) {
        return 0;
    }

    $ids = array_values(array_filter(
        $ids,
        static function ($chasse_id) {
            return !function_exists('ca_demo_is_demo_hunt')
                || !ca_demo_is_demo_hunt((int) $chasse_id);
        }
    ));

    if (empty($ids)) {
        return 0;
    }

    global $wpdb;
    $table        = $wpdb->prefix . 'engagements';
    $placeholders = implode(',', array_fill(0, count($ids), '%d'));

    $sql = $wpdb->prepare(
        "SELECT DISTINCT user_id FROM {$table} WHERE enigme_id IS NULL AND chasse_id IN ($placeholders)",
        $ids
    );

    return count($wpdb->get_col($sql));
}

/**
 * Sum collected points for all hunts of an organizer.
 */
function organisateur_compter_points_collectes(int $organisateur_id): int
{
    $query = get_chasses_de_organisateur($organisateur_id);
    if (!$query || empty($query->posts)) {
        return 0;
    }

    $total = 0;
    foreach ($query->posts as $chasse_id) {
        $chasse_id = (int) $chasse_id;
        if (
            function_exists('ca_demo_is_demo_hunt')
            && ca_demo_is_demo_hunt($chasse_id)
        ) {
            continue;
        }

        $total += chasse_compter_points_collectes($chasse_id);
    }

    return $total;
}
