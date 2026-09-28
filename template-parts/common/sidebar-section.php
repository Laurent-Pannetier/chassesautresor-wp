<?php
/**
 * Render a section of the reusable sidebar component.
 *
 * @param array $args {
 *     @type string $section       Section identifier: 'navigation' or 'stats'.
 *     @type array  $visible_items Visible menu items.
 *     @type array  $hidden_items  Hidden menu items.
 *     @type int    $chasse_id     Related hunt identifier.
 *     @type string $ajout_html    HTML for the add link.
 *     @type string $meta_html     Meta data HTML.
 *     @type string $stats_html    Statistics HTML.
 *     @type string $winners_html  Winners list HTML.
 *     @type int    $enigme_id     Enigma identifier.
 *     @type string $context       Rendering context ('enigme' or 'chasse').
 * }
 */

if (!isset($args['section'])) {
    return;
}

$section = $args['section'];
$context = $args['context'] ?? 'enigme';

if ($section === 'navigation') {
    if (!empty($args['chasse_id'])) {
        $logo = get_the_post_thumbnail($args['chasse_id'], 'thumbnail');
        if ($logo) {
            echo '<div class="enigme-chasse-logo">' . $logo . '</div>';
        }
    }
    $data_chasse   = $args['chasse_id'] ? ' data-chasse-id="' . intval($args['chasse_id']) . '"' : '';
    $menu_class    = 'enigme-menu';
    $visible_items = $args['visible_items'] ?? [];
    $hidden_items  = $args['hidden_items'] ?? [];

    $aria_label = esc_attr__('Navigation des énigmes', 'chassesautresor-com');
    echo '<nav class="enigme-navigation" aria-label="' . $aria_label . '"' . $data_chasse . ' data-context="' . esc_attr($context) . '">';
    $nav_title = $context === 'chasse'
        ? esc_html__('Énigmes', 'chassesautresor-com')
        : esc_html__('Énigmes', 'chassesautresor-com');
    echo '<h3>' . $nav_title . '</h3>';
    if (!empty($args['ajout_html'])) {
        echo $args['ajout_html'];
    }

    if (empty($visible_items) && empty($hidden_items)) {
        $empty_text = $context === 'chasse'
            ? esc_html__('Aucune énigme disponible', 'chassesautresor-com')
            : esc_html__('Aucune énigme disponible', 'chassesautresor-com');
        echo '<p class="enigme-navigation__empty">' . $empty_text . '</p>';
    } else {
        echo '<ul class="' . esc_attr($menu_class) . '">' . implode('', $visible_items) . '</ul>';
        if (!empty($hidden_items)) {
            echo '<ul class="' . esc_attr($menu_class . ' enigme-menu--overflow') . '" hidden>'
                . implode('', $hidden_items)
                . '</ul>';
            echo '<button class="enigme-menu__toggle" type="button" aria-expanded="false">'
                . esc_html__('Afficher plus', 'chassesautresor-com')
                . '</button>';
        }
    }
    echo '</nav>';
    return;
}

if ($section === 'stats') {
    echo '<section class="enigme-statistiques">';
    echo '<h3>' . esc_html__('Statistiques', 'chassesautresor-com') . '</h3>';
    echo $args['meta_html'] ?? '';
    echo $args['stats_html'] ?? '';
    echo '</section>';
    if (!empty($args['winners_html'])) {
        echo '<section class="enigme-gagnants" data-enigme-id="' . intval($args['enigme_id'] ?? 0) . '">';
        echo $args['winners_html'];
        echo '</section>';
    }
}
