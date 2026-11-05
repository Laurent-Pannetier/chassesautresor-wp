<?php
defined('ABSPATH') || exit;

function cat_get_hint_query_service(): ChassesAuTresor\Core\Content\HintQueryService
{
    return new ChassesAuTresor\Core\Content\HintQueryService();
}

function cat_get_hint_status_service(): ChassesAuTresor\Core\Content\HintStatusService
{
    return new ChassesAuTresor\Core\Content\HintStatusService();
}

function cat_get_hint_cache_service(): ChassesAuTresor\Core\Content\HintCacheService
{
    return new ChassesAuTresor\Core\Content\HintCacheService(cat_get_hint_status_service());
}



function cat_get_hint_title_service(): ChassesAuTresor\Core\Content\HintTitleService
{
    return new ChassesAuTresor\Core\Content\HintTitleService();
}



function cat_get_hint_field_policy_service(): ChassesAuTresor\Core\Content\HintFieldPolicyService
{
    return new ChassesAuTresor\Core\Content\HintFieldPolicyService();
}







function cat_get_hint_relationship_service(): ChassesAuTresor\Core\Content\HintRelationshipService
{
    return new ChassesAuTresor\Core\Content\HintRelationshipService(
        new ChassesAuTresor\Core\Relationships\RelationshipService()
    );
}



// ==================================================
// 💡 GESTION DES INDICES
// ==================================================
// 🔹 modifier_champ_indice() → Mise à jour AJAX (champ ACF ou natif)

/**
 * Generate a placeholder title for an indice based on its chasse.
 *
 * @param int $chasse_id Related hunt ID.
 * @return string
 */
function build_indice_placeholder_title(int $chasse_id): string
{
    $prefix = defined('INDICE_DEFAULT_PREFIX') ? INDICE_DEFAULT_PREFIX : 'clue-';
    $slug = (string) get_post_field('post_name', $chasse_id);
    $generatedSlug = '';

    if ($slug === '') {
        $chasseName = (string) get_post_field('post_title', $chasse_id);
        $generatedSlug = $chasseName !== ''
            ? (function_exists('sanitize_title')
                ? sanitize_title($chasseName)
                : strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $chasseName), '-')))
            : '';
    }

    return cat_get_hint_title_service()->buildPlaceholder($prefix, $slug, $generatedSlug);
}

/**
 * Calcule le rang du prochain indice pour une chasse ou une énigme.
 *
 * @param int    $objet_id   ID de la chasse ou de l’énigme.
 * @param string $objet_type Type de cible ('chasse' ou 'enigme').
 * @return int
 */
function prochain_rang_indice(int $objet_id, string $objet_type): int
{
    $queryArgs = cat_get_hint_query_service()->getRankedHintIdsQueryArgs($objet_id, $objet_type);
    if ($queryArgs === []) {
        return 1;
    }

    $existing_indices = function_exists('get_posts')
        ? get_posts($queryArgs)
        : [];

    return count($existing_indices) + 1;
}

function rendre_carte_indices(string $html, int $huntId): string
{
    ob_start();
    get_template_part(
        'template-parts/chasse/partials/chasse-partial-indices',
        null,
        ['objet_id' => $huntId, 'objet_type' => 'chasse']
    );

    return (string) ob_get_clean();
}
add_filter('chassesautresor_render_hint_card', 'rendre_carte_indices', 10, 2);

/**
 * @param object[] $hints
 * @param array{total:int,hunt:int,riddle:int} $counts
 * @param array<string, mixed>|null $toggle
 */
function rendre_table_indices(
    string $html,
    array $hints,
    int $page,
    int $pages,
    string $targetType,
    int $targetId,
    array $counts,
    ?array $toggle
): string {
    ob_start();
    get_template_part('template-parts/common/indices-table', null, [
        'indices' => $hints,
        'page' => $page,
        'pages' => $pages,
        'objet_type' => $targetType,
        'objet_id' => $targetId,
        'count_total' => $counts['total'],
        'count_chasse' => $counts['hunt'],
        'count_enigme' => $counts['riddle'],
        'toggle' => $toggle,
    ]);

    return (string) ob_get_clean();
}
add_filter('chassesautresor_render_hint_table', 'rendre_table_indices', 10, 9);
