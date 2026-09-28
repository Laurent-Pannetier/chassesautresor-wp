<?php
defined('ABSPATH') || exit;

/**
 * Determine whether an enigma requires payment to attempt.
 *
 * An enigma is considered paid if it has a positive attempt cost and
 * a validation mode different from "aucune".
 *
 * @param int $enigme_id Enigma post ID.
 * @return bool True when the enigma is paid.
 */
function enigme_is_paid(int $enigme_id): bool
{
    if (!$enigme_id || get_post_type($enigme_id) !== 'enigme') {
        return false;
    }

    $cost = (int) get_field('enigme_tentative_cout_points', $enigme_id);
    $mode = strtolower((string) get_field('enigme_mode_validation', $enigme_id));

    return $cost > 0 && $mode !== 'aucune';
}

/**
 * Filter a list of enigmas to those visible to a user.
 *
 * The list can contain either WP_Post objects or IDs.
 *
 * @param array $enigmes  List of enigmas (WP_Post|int).
 * @param int   $user_id  Current user ID.
 * @return array          Filtered list preserving original items.
 */
function filter_visible_enigmes(array $enigmes, int $user_id): array
{
    return array_values(array_filter($enigmes, static function ($post) use ($user_id) {
        $id = is_object($post) ? (int) $post->ID : (int) $post;

        if (get_post_status($id) !== 'publish') {
            return false;
        }

        if (!get_field('enigme_cache_complet', $id)) {
            return false;
        }

        return enigme_est_visible_pour($user_id, $id);
    }));
}
