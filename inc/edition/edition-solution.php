<?php
require_once __DIR__ . '/../constants.php';

/**
 * Renders solution rows requested by the core controller.
 *
 * @param object[] $solutions
 */
function rendre_table_solutions(
    string $html,
    array $solutions,
    int $page,
    int $pages,
    string $targetType,
    int $targetId
): string {
    ob_start();
    get_template_part('template-parts/common/solutions-table', null, [
        'solutions' => $solutions,
        'page' => $page,
        'pages' => $pages,
        'objet_type' => $targetType,
        'objet_id' => $targetId,
    ]);

    return (string) ob_get_clean();
}
add_filter('chassesautresor_render_solutions_table', 'rendre_table_solutions', 10, 7);
