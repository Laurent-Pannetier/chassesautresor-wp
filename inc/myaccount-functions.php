<?php
/**
 * Helper functions for "Mon Compte" area.
 *
 * @package chassesautresor
 */

defined('ABSPATH') || exit;

if (!class_exists(ChassesAuTresor\Core\Content\OrganizerNavigationService::class, false)) {
    require_once dirname(__DIR__, 3)
        . '/plugins/chassesautresor-core/src/Content/OrganizerNavigationService.php';
}

if (!class_exists(ChassesAuTresor\Core\Relationships\OrganizerHuntQueryService::class, false)) {
    require_once dirname(__DIR__, 3)
        . '/plugins/chassesautresor-core/src/Relationships/OrganizerHuntQueryService.php';
}

/**
 * Retrieve organizer navigation data for the sidebar.
 *
 * @param int $user_id User ID.
 * @return array|null Navigation data or null if no organizer.
 */
function myaccount_get_organizer_nav(int $user_id): ?array
{
    $organizer_id = get_organisateur_from_user($user_id);
    if (!$organizer_id) {
        return null;
    }

    $organizer_post_status = get_post_status($organizer_id);
    $organizer_complete    = (bool) get_field('organisateur_cache_complet', $organizer_id);
    $navigationService = new ChassesAuTresor\Core\Content\OrganizerNavigationService();
    $queryService = new ChassesAuTresor\Core\Relationships\OrganizerHuntQueryService();
    $organizer_classes = $navigationService->getOrganizerClasses(
        $organizer_complete,
        (string) $organizer_post_status
    );
    $chasses = get_posts($queryService->getNavigationHuntsQueryArgs((int) $organizer_id));

    $pending_enigmes = recuperer_enigmes_tentatives_en_attente($organizer_id);

    $data = [
        'organizer' => [
            'url'     => get_permalink($organizer_id),
            'title'   => get_the_title($organizer_id),
            'classes' => $organizer_classes,
        ],
        'chasses' => [],
    ];

    foreach ($chasses as $chasse) {
        $status_validation = get_field('chasse_cache_statut_validation', $chasse->ID);
        $complet           = get_field('chasse_cache_complet', $chasse->ID);
        $post_status       = get_post_status($chasse->ID);
        $presentation = $navigationService->getHuntPresentation(
            (bool) $complet,
            (string) $post_status,
            (string) $status_validation,
            function_exists('peut_valider_chasse') && peut_valider_chasse($chasse->ID, $user_id)
        );
        if ($presentation === null) {
            continue;
        }

        $chasse_item = [
            'title'        => get_the_title($chasse->ID),
            'url'          => get_permalink($chasse->ID),
            'classes'      => $presentation['classes'],
            'pending_icon' => $presentation['pending_icon'],
            'enigmes'      => [],
        ];

        $enigme_ids = recuperer_ids_enigmes_pour_chasse($chasse->ID);
        foreach ($enigme_ids as $enigme_id) {
            $url              = get_permalink($enigme_id);
            $enigme_complete  = get_field('enigme_cache_complet', $enigme_id);
            $post_status      = get_post_status($enigme_id);
            $etat_enigme      = get_field('enigme_cache_etat_systeme', $enigme_id);

            $sub_classes = $navigationService->getRiddleClasses(
                (bool) $enigme_complete,
                (string) $post_status,
                (string) $etat_enigme,
                in_array($enigme_id, $pending_enigmes, true)
            );
            if ($sub_classes === null) {
                continue;
            }

            $chasse_item['enigmes'][] = [
                'title'   => get_the_title($enigme_id),
                'url'     => $url,
                'classes' => $sub_classes,
            ];
        }

        $data['chasses'][] = $chasse_item;
    }

    return $data;
}

/**
 * Render organizer navigation HTML for the sidebar.
 *
 * @param array $data Navigation data from myaccount_get_organizer_nav().
 * @return string HTML output.
 */
function myaccount_render_organizer_nav(array $data): string
{
    ob_start();
    ?>
    <nav class="dashboard-nav organizer-nav">
        <a href="<?php echo esc_url($data['organizer']['url']); ?>" class="<?php echo esc_attr($data['organizer']['classes']); ?>">
            <i class="fas fa-landmark"></i>
            <span class="nav-title"><?php echo esc_html($data['organizer']['title']); ?></span>
        </a>
        <?php foreach ($data['chasses'] as $chasse) : ?>
            <?php
            $tag  = $chasse['url'] ? 'a' : 'span';
            $attr = $chasse['url'] ? ' href="' . esc_url($chasse['url']) . '"' : '';
            ?>
            <<?php echo $tag . $attr; ?> class="<?php echo esc_attr($chasse['classes']); ?>">
                <span class="nav-title"><?php echo esc_html($chasse['title']); ?></span>
                <?php if ($chasse['pending_icon']) : ?>
                    <i class="fas fa-hourglass-half"></i>
                <?php endif; ?>
            </<?php echo $tag; ?>>
            <?php foreach ($chasse['enigmes'] as $enigme) : ?>
                <?php
                $sub_tag  = $enigme['url'] ? 'a' : 'span';
                $sub_attr = $enigme['url'] ? ' href="' . esc_url($enigme['url']) . '"' : '';
                ?>
                <<?php echo $sub_tag . $sub_attr; ?> class="<?php echo esc_attr($enigme['classes']); ?>">
                    <span class="nav-title"><?php echo esc_html($enigme['title']); ?></span>
                </<?php echo $sub_tag; ?>>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>
    <?php
    return ob_get_clean();
}
