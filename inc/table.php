<?php
/**
 * Helper functions for table rendering.
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * Build default options for a masked proposition cell.
 *
 * @param string|null $uid       Tentative unique identifier.
 * @param array       $overrides Optional values overriding defaults.
 *
 * @return array
 */
function cta_prepare_masked_proposition_options(?string $uid, array $overrides = []): array
{
    $defaults = [
        'mask'            => true,
        'uid'             => $uid ?? '',
        'nonce'           => '',
        'action'          => 'ca_view_tentative_proposition',
        'ajax_url'        => function_exists('admin_url') ? admin_url('admin-ajax.php') : '',
        'placeholder'     => '••••••',
        'button_label'    => __('Voir', 'chassesautresor-com'),
        'button_aria'     => __('Afficher la proposition masquée', 'chassesautresor-com'),
        'prompt_label'    => __('Proposition :', 'chassesautresor-com'),
        'error_message'   => __('Impossible de récupérer la proposition.', 'chassesautresor-com'),
        'loading_label'   => __('Chargement…', 'chassesautresor-com'),
        'sr_text'         => __('Proposition masquée', 'chassesautresor-com'),
    ];

    if ($defaults['uid'] !== '' && function_exists('wp_create_nonce')) {
        $defaults['nonce'] = wp_create_nonce('ca_view_tentative_' . $defaults['uid']);
    }

    return array_merge($defaults, $overrides);
}

/**
 * Render a table cell with excerpt and toggle for long propositions.
 *
 * @param string $text     Full text to display.
 * @param bool   $expanded Whether the cell should be expanded by default.
 * @param int    $limit    Number of characters before truncation.
 * @param array  $options  Additional rendering options.
 *
 * @return string HTML for the table cell.
 */
function cta_render_proposition_cell(string $text, bool $expanded = false, int $limit = 39, array $options = []): string
{
    if (!empty($options['mask'])) {
        $placeholder   = isset($options['placeholder']) ? (string) $options['placeholder'] : '••••••';
        $button_label  = isset($options['button_label']) ? (string) $options['button_label'] : __('Voir', 'chassesautresor-com');
        $button_aria   = isset($options['button_aria']) ? (string) $options['button_aria'] : __('Afficher la proposition masquée', 'chassesautresor-com');
        $prompt_label  = isset($options['prompt_label']) ? (string) $options['prompt_label'] : '';
        $error_label   = isset($options['error_message']) ? (string) $options['error_message'] : '';
        $loading_label = isset($options['loading_label']) ? (string) $options['loading_label'] : '';
        $nonce         = isset($options['nonce']) ? (string) $options['nonce'] : '';
        $uid           = isset($options['uid']) ? (string) $options['uid'] : '';
        $action        = isset($options['action']) ? (string) $options['action'] : 'ca_view_tentative_proposition';
        $ajax_url      = isset($options['ajax_url']) ? (string) $options['ajax_url'] : '';
        $sr_text       = isset($options['sr_text']) ? (string) $options['sr_text'] : __('Proposition masquée', 'chassesautresor-com');

        $attributes = [
            'type="button"',
            'class="toggle-proposition"',
            'data-mode="mask"',
        ];

        if ($uid !== '') {
            $attributes[] = 'data-uid="' . esc_attr($uid) . '"';
        }

        if ($nonce !== '') {
            $attributes[] = 'data-nonce="' . esc_attr($nonce) . '"';
        }

        if ($ajax_url !== '') {
            $attributes[] = 'data-ajax-url="' . esc_url($ajax_url) . '"';
        }

        if ($prompt_label !== '') {
            $attributes[] = 'data-prompt="' . esc_attr($prompt_label) . '"';
        }

        if ($error_label !== '') {
            $attributes[] = 'data-error="' . esc_attr($error_label) . '"';
        }

        if ($loading_label !== '') {
            $attributes[] = 'data-loading="' . esc_attr($loading_label) . '"';
        }

        if ($action !== '') {
            $attributes[] = 'data-action="' . esc_attr($action) . '"';
        }

        if ($button_aria !== '') {
            $attributes[] = 'aria-label="' . esc_attr($button_aria) . '"';
        }

        $button_html = '<button ' . implode(' ', $attributes) . '>' . esc_html($button_label) . '</button>';

        $content_html = '<div class="proposition-content">'
            . '<span class="proposition-mask" aria-hidden="true">' . esc_html($placeholder) . '</span>'
            . '<span class="screen-reader-text">' . esc_html($sr_text) . '</span>'
            . $button_html
            . '</div>';

        return '<td class="proposition-cell proposition-cell--masked">' . $content_html . '</td>';
    }

    $needs_toggle = mb_strlen($text) > $limit;
    $excerpt      = $needs_toggle ? mb_substr($text, 0, $limit) . '…' : $text;

    $excerpt_html = '<span class="proposition-excerpt"' . ($expanded && $needs_toggle ? ' hidden' : '') . '>' .
        esc_html($excerpt) . '</span>';
    $full_html    = '';
    $button_html  = '';
    $class        = 'proposition-cell';

    if ($needs_toggle) {
        $full_html = '<span class="proposition-full"' . ($expanded ? '' : ' hidden') . '>' .
            esc_html($text) . '</span>';

        $label_more = esc_attr__('Voir plus', 'chassesautresor-com');
        $label_less = esc_attr__('Voir moins', 'chassesautresor-com');
        $aria_label = $expanded ? $label_less : $label_more;
        $icon       = $expanded ? 'fa-minus' : 'fa-ellipsis';

        $button_html = '<button type="button" class="toggle-proposition" aria-expanded="' .
            ($expanded ? 'true' : 'false') . '" aria-label="' . $aria_label .
            '" data-more="' . $label_more . '" data-less="' . $label_less .
            '"><i class="fa-solid ' . $icon . '" aria-hidden="true"></i></button>';
    }

    if ($expanded && $needs_toggle) {
        $class .= ' expanded';
    }

    $content_html = '<div class="proposition-content">' . $excerpt_html . $full_html . $button_html . '</div>';

    return '<td class="' . $class . '">' . $content_html . '</td>';
}
