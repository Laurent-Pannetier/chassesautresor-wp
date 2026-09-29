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

    $excluded = function_exists('get_users') ? get_users(['role' => 'administrator', 'fields' => 'ids']) : [];
    if (function_exists('get_field')) {
        $excluded = array_merge($excluded, (array) get_field('utilisateurs_associes', $organisateur_id));
    }
    $excluded = array_values(array_unique(array_filter(array_map('intval', $excluded))));

    return cat_get_hunt_engagement_service()->countUniquePlayersForHunts($ids, $excluded);
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
        $total += chasse_compter_points_collectes($chasse_id);
    }

    return $total;
}
