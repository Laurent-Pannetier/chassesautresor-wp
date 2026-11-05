<?php
/**
 * Search form helper.
 *
 * @package chassesautresor.com
 */

defined('ABSPATH') || exit;

/**
 * Returns the SVG markup for supported submit icons.
 *
 * @param string $icon Identifier of the icon to render.
 *
 * @return string
 */
function cta_get_table_search_submit_icon_markup(string $icon): string
{
    switch ($icon) {
        case 'search':
            return '<svg aria-hidden="true" focusable="false" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">'
                . '<path fill="currentColor" d="M10.5 3a7.5 7.5 0 015.88 12.04l4.79 4.79-1.42 1.42-4.79-4.79A7.5 7.5 0 1110.5 3zm0 2a5.5 5.5 0 100 11 5.5 5.5 0 000-11z" />'
                . '</svg>';
        default:
            return '';
    }
}

if (!function_exists('cta_get_reset_icon_markup')) {
    /**
     * Returns the SVG markup for reset buttons.
     */
    function cta_get_reset_icon_markup(): string
    {
        return '<svg aria-hidden="true" focusable="false" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">'
            . '<path fill="currentColor" fill-rule="evenodd" clip-rule="evenodd"'
            . ' d="M4.755 10.059a7.5 7.5 0 0 1 12.548-3.364l1.903 1.903h-3.183a.75.75 0 1 0 0 1.5h4.992a.75.75 0 0 0 .75-.75V4.356a.75.75 0 0 0-1.5 0v3.18l-1.9-1.9A9 9 0 0 0 3.306 9.67a.75.75 0 1 0 1.45.388Zm15.408 3.352a.75.75 0 0 0-.919.53 7.5 7.5 0 0 1-12.548 3.364l-1.902-1.903h3.183a.75.75 0 0 0 0-1.5H2.984a.75.75 0 0 0-.75.75v4.992a.75.75 0 0 0 1.5 0v-3.18l1.9 1.9a9 9 0 0 0 15.059-4.035.75.75 0 0 0-.53-.918Z" />'
            . '</svg>';
    }
}

/**
 * Renders the HTML markup for a table search form.
 *
 * @param string $key       Context identifier.
 * @param array  $overrides Optional overrides (label, placeholder, etc.).
 *
 * @return string
 */
function cta_render_search_form(string $key, array $overrides = []): string
{
    $context = ca_resolve_search_context($key);

    if (empty($context)) {
        return '';
    }

    $ui          = $context['ui'] ?? [];
    $capability  = $overrides['capability'] ?? ($ui['capability'] ?? '');
    $has_cap     = true;

    if ($capability && function_exists('current_user_can')) {
        $has_cap = current_user_can($capability);
    }

    if (!$has_cap) {
        return '';
    }

    $parameter = $context['parameter'] ?? sanitize_key($key);
    $form_id   = $overrides['id'] ?? sprintf('table-search-%s', $parameter);

    $defaults = [
        'class'              => 'table-search',
        'method'             => 'get',
        'action'             => '',
        'label'              => $ui['label'] ?? '',
        'placeholder'        => $ui['placeholder'] ?? '',
        'value'              => null,
        'hidden_fields'      => [],
        'nonce_action'       => $ui['nonce_action'] ?? '',
        'nonce_name'         => $ui['nonce_name'] ?? 'nonce',
        'submit_label'       => $ui['submit_label'] ?? esc_html__('Rechercher', 'chassesautresor-com'),
        'submit_icon'        => $ui['submit_icon'] ?? '',
        // Garder `submit_icon_only` à true garantit un bouton visuellement réduit à l'icône.
        'submit_icon_only'   => $ui['submit_icon_only'] ?? false,
        'reset_label'        => $ui['reset_label'] ?? esc_html__('Réinitialiser', 'chassesautresor-com'),
        'show_reset_button'  => $ui['show_reset_button'] ?? false,
        'pagination_params'  => $context['pagination_params'] ?? [],
        'description'        => $ui['description'] ?? '',
        'data_attributes'    => [],
    ];

    $config = array_merge($defaults, $overrides);

    $method = strtolower((string) $config['method']);
    if (!in_array($method, ['get', 'post'], true)) {
        $method = 'get';
    }

    $form_classes = trim((string) $config['class']);
    $class_tokens = preg_split('/\s+/', $form_classes);
    $class_tokens = array_filter(
        is_array($class_tokens) ? $class_tokens : [],
        static function ($class_name): bool {
            return is_string($class_name) && '' !== $class_name;
        }
    );

    if (!in_array('table-search', $class_tokens, true)) {
        array_unshift($class_tokens, 'table-search');
    }

    if ('' === trim((string) ($config['label'] ?? ''))) {
        $class_tokens[] = 'table-search--no-label';
    }

    $form_classes = implode(' ', array_unique($class_tokens));

    $search_value = $config['value'];
    if (null === $search_value) {
        $search_value = ca_get_search_term($key);
    }

    $hidden_fields = array_merge(
        is_array($context['hidden_fields'] ?? null) ? $context['hidden_fields'] : [],
        is_array($ui['hidden_fields'] ?? null) ? $ui['hidden_fields'] : [],
        is_array($config['hidden_fields']) ? $config['hidden_fields'] : []
    );

    if (!array_key_exists('section', $hidden_fields) && isset($_GET['section'])) {
        $hidden_fields['section'] = sanitize_text_field(wp_unslash($_GET['section']));
    }

    $hidden_fields = array_merge(
        ['search[context]' => $context['key'] ?? $key],
        $hidden_fields
    );

    $pagination_params = array_values(
        array_unique(
            array_filter(
                array_map('trim', (array) $config['pagination_params']),
                static function ($value): bool {
                    return is_string($value) && '' !== $value;
                }
            )
        )
    );

    $input_id    = sprintf('%s-input', $form_id);
    $input_name  = sprintf('search[%s]', $parameter);
    $placeholder = (string) $config['placeholder'];
    $label       = (string) $config['label'];
    $desc        = (string) $config['description'];
    $submit      = (string) $config['submit_label'];
    $submit_icon = (string) $config['submit_icon'];
    $reset_label = (string) $config['reset_label'];
    $show_reset  = (bool) $config['show_reset_button'];

    $icon_markup = '';
    if ('' !== $submit_icon) {
        $icon_markup = cta_get_table_search_submit_icon_markup($submit_icon);
    }

    $has_icon   = '' !== $icon_markup;
    $icon_only  = $has_icon && !empty($config['submit_icon_only']);
    $submit_cls = 'table-search__submit';

    $submit_text = $submit;
    if ('' === $submit_text && $icon_only) {
        $submit_text = __('Rechercher', 'chassesautresor-com');
    }

    if ($icon_only) {
        $submit_cls .= ' table-search__submit--icon-only';
    }

    $form_attrs = sprintf(' method="%s"', esc_attr($method));

    if (!empty($config['action'])) {
        $form_attrs .= sprintf(' action="%s"', esc_url($config['action']));
    }

    $form_attrs .= sprintf(' class="%s"', esc_attr($form_classes));
    $form_attrs .= sprintf(' id="%s"', esc_attr($form_id));
    $form_attrs .= sprintf(' data-search-key="%s"', esc_attr($context['key'] ?? $key));
    $form_attrs .= sprintf(' data-search-parameter="%s"', esc_attr($input_name));

    $data_attributes = [];
    $used_data_keys  = [
        'search-key',
        'search-parameter',
    ];

    if ($show_reset) {
        $form_attrs     .= ' data-has-reset="1"';
        $used_data_keys[] = 'has-reset';
    }

    if (!empty($pagination_params)) {
        $form_attrs     .= sprintf(' data-reset-pagination="%s"', esc_attr(implode(',', $pagination_params)));
        $used_data_keys[] = 'reset-pagination';
    }

    if (is_array($config['data_attributes'])) {
        foreach ($config['data_attributes'] as $data_key => $data_value) {
            $sanitized = strtolower((string) $data_key);
            $sanitized = preg_replace('/[^a-z0-9_-]+/', '', $sanitized);

            if ('' === $sanitized) {
                continue;
            }

            if (in_array($sanitized, $used_data_keys, true)) {
                continue;
            }

            $data_attributes[$sanitized] = (string) $data_value;
        }
    }

    foreach ($data_attributes as $data_key => $data_value) {
        $form_attrs .= sprintf(' data-%s="%s"', esc_attr($data_key), esc_attr($data_value));
    }

    $form_attrs .= ' role="search"';

    $input_attrs = [
        'type'        => 'search',
        'id'          => $input_id,
        'name'        => $input_name,
        'value'       => (string) $search_value,
        'placeholder' => $placeholder,
        'class'       => 'table-search__field',
    ];

    if ('' === $label) {
        $input_attrs['aria-label'] = $placeholder !== ''
            ? $placeholder
            : esc_attr__('Rechercher', 'chassesautresor-com');
    }

    if ($desc !== '') {
        $description_id             = sprintf('%s-description', $form_id);
        $input_attrs['aria-describedby'] = $description_id;
    }

    $input_html = '';
    foreach ($input_attrs as $attr => $value) {
        if ('' === $value) {
            continue;
        }

        $input_html .= sprintf(' %s="%s"', esc_attr($attr), esc_attr($value));
    }

    ob_start();
    ?>
    <form<?php echo $form_attrs; ?>>
        <?php if ('' !== $label) : ?>
            <label class="table-search__label" for="<?php echo esc_attr($input_id); ?>">
                <?php echo esc_html($label); ?>
            </label>
        <?php endif; ?>

        <?php if ('' !== $desc) : ?>
            <p class="table-search__description" id="<?php echo esc_attr($description_id); ?>">
                <?php echo esc_html($desc); ?>
            </p>
        <?php endif; ?>

        <div class="table-search__controls">
            <input<?php echo $input_html; ?> />
            <button type="submit" class="<?php echo esc_attr($submit_cls); ?>">
                <?php if ($has_icon) : ?>
                <span class="table-search__submit-icon" aria-hidden="true">
                    <?php echo $icon_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </span>
                <?php endif; ?>
                <?php if ('' !== $submit_text) : ?>
                <span class="table-search__submit-text<?php echo $icon_only ? ' screen-reader-text' : ''; ?>">
                    <?php echo esc_html($submit_text); ?>
                </span>
                <?php endif; ?>
            </button>
            <?php if ($show_reset) : ?>
            <?php
            $reset_icon_markup = function_exists('cta_get_reset_icon_markup')
                ? cta_get_reset_icon_markup()
                : '';
            ?>
            <button
                type="button"
                class="table-search__reset"
                data-table-search-reset<?php echo '' === $search_value ? ' hidden' : ''; ?>
                aria-label="<?php echo esc_attr($reset_label); ?>"
            >
                <?php if ('' !== $reset_icon_markup) : ?>
                <span class="table-search__reset-icon" aria-hidden="true">
                    <?php echo $reset_icon_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </span>
                <?php endif; ?>
            </button>
            <?php endif; ?>
        </div>

        <?php
        if (!empty($config['nonce_action'])) {
            echo wp_nonce_field($config['nonce_action'], $config['nonce_name'], true, false); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }

        foreach ($hidden_fields as $name => $value) :
            if ('' === $name) {
                continue;
            }
            ?>
            <input type="hidden" name="<?php echo esc_attr((string) $name); ?>" value="<?php echo esc_attr((string) $value); ?>" />
        <?php endforeach; ?>
    </form>
    <?php

    return trim((string) ob_get_clean());
}
