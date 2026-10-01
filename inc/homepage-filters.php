<?php
/**
 * Homepage filters utilities.
 */

defined('ABSPATH') || exit();

/**
 * Retrieves hunt identifiers for the homepage according to optional filters.
 *
 * Filters accept the following values:
 * - statut: "tous", "en_cours", "a_venir", "termine".
 *   The "en_cours" filter groups hunts whose "chasse_cache_statut" is either "en_cours" or "payante".
 * - cout: array of "gratuit" and/or "points". Omit to keep both values by default.
 *   A hunt is considered "gratuit" when both "chasse_infos_cout_points" and "nb_enigmes_payantes" are equal to 0.
 *
 * @param array{statut?:string, cout?:string|string[], search?:string}|array $args Raw filters coming from the frontend.
 *
 * @return array{
 *     ids:int[],
 *     total:int,
 *     filters_normalises:array{statut:string, cout:string[], search:string},
 *     available_filters:array{
 *         statut:array<string,int>,
 *         cout:array<string,int>
 *     },
 *     message?:string
 * }
 */
function ca_home_filter_chasse_ids(array $args): array
{
    return (new \ChassesAuTresor\Core\Content\HuntFilterApplicationService())->filter($args);
}

/**
 * AJAX endpoint returning filtered hunts list markup.
 */
function ca_ajax_filter_chasses(): void
{
    \ChassesAuTresor\Core\Content\HuntFilterAjaxHandler::handle();
}
