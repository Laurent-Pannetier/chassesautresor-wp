<?php
/**
 * Search context registry.
 *
 * @package chassesautresor.com
 */

defined('ABSPATH') || exit;

/**
 * Returns the static storage used to keep the search registry.
 *
 * @return array<string, array<string, mixed>>
 */
function &ca_get_search_registry_storage(): array
{
    static $storage = [];

    return $storage;
}

/**
 * Registers a new search context describing how lookups should behave.
 *
 * @param string $key  Context identifier (e.g. "tentatives").
 * @param array  $args Context configuration.
 *
 * @return void
 */
function ca_register_search_context(string $key, array $args): void
{
    $normalized_key = sanitize_key($key);

    if ('' === $normalized_key) {
        return;
    }

    $defaults = [
        'fields'            => [
            'sql'      => [],
            'wp_query' => [],
        ],
        'count_callback'   => null,
        'ui'               => [
            'label'         => '',
            'placeholder'   => '',
            'capability'    => '',
            'nonce_action'  => '',
            'nonce_name'    => 'nonce',
            'hidden_fields' => [],
        ],
        'hidden_fields'     => [],
        'parameter'         => $normalized_key,
        'pagination_params' => [],
    ];

    $context = wp_parse_args($args, $defaults);

    if (!is_array($context['fields'])) {
        $context['fields'] = [];
    }

    $context['fields'] = array_merge(
        [
            'sql'      => [],
            'wp_query' => [],
        ],
        $context['fields']
    );

    $context['fields']['sql'] = array_values(
        array_filter(
            (array) $context['fields']['sql'],
            static function ($value): bool {
                return is_string($value) && $value !== '';
            }
        )
    );

    $context['fields']['wp_query'] = array_values(
        array_filter(
            (array) $context['fields']['wp_query'],
            static function ($value): bool {
                return is_string($value) || is_array($value);
            }
        )
    );

    if (!is_array($context['ui'])) {
        $context['ui'] = [];
    }

    $context['ui'] = array_merge(
        [
            'label'         => '',
            'placeholder'   => '',
            'capability'    => '',
            'nonce_action'  => '',
            'nonce_name'    => 'nonce',
            'hidden_fields' => [],
        ],
        $context['ui']
    );

    $context['ui']['hidden_fields'] = is_array($context['ui']['hidden_fields'])
        ? $context['ui']['hidden_fields']
        : [];

    $context['hidden_fields'] = is_array($context['hidden_fields'])
        ? $context['hidden_fields']
        : [];

    $context['parameter'] = is_string($context['parameter']) && $context['parameter'] !== ''
        ? sanitize_key($context['parameter'])
        : $normalized_key;

    $context['pagination_params'] = array_values(
        array_filter(
            array_map('trim', (array) ($context['pagination_params'] ?? [])),
            static function ($value): bool {
                return is_string($value) && $value !== '';
            }
        )
    );

    if (!empty($context['count_callback']) && !is_callable($context['count_callback'])) {
        _doing_it_wrong(
            __FUNCTION__,
            esc_html__('Search context count callback must be callable.', 'chassesautresor-com'),
            '1.0.0'
        );
        $context['count_callback'] = null;
    }

    $context['key'] = $normalized_key;

    $storage = &ca_get_search_registry_storage();
    $storage[$normalized_key] = $context;
}

/**
 * Retrieves a previously registered search context.
 *
 * @param string $key Context identifier.
 *
 * @return array<string, mixed>
 */
function ca_resolve_search_context(string $key): array
{
    $storage        = &ca_get_search_registry_storage();
    $normalized_key = sanitize_key($key);
    $context        = $storage[$normalized_key] ?? [];

    /**
     * Filters the resolved search context before it is returned.
     *
     * @param array  $context Registered context arguments.
     * @param string $key     Context identifier.
     */
    return apply_filters('ca_resolve_search_context', $context, $normalized_key);
}
