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
    $status_whitelist = ['tous', 'en_cours', 'a_venir', 'termine'];
    $cost_whitelist   = ['gratuit', 'points'];

    $default_filters = [
        'statut' => 'tous',
        'cout'   => $cost_whitelist,
    ];

    $raw_search = '';
    if (array_key_exists('search', $args)) {
        $raw_search = $args['search'];
        if (is_array($raw_search)) {
            $raw_search = reset($raw_search);
        }
    }

    $raw_search = is_scalar($raw_search) ? (string) $raw_search : '';
    $search_term = trim($raw_search);
    $sanitized_search_term = sanitize_text_field($search_term);

    $to_lowercase = static function (string $value): string {
        return function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value);
    };

    $normalize_text = static function ($value) use ($to_lowercase): string {
        if (!is_string($value) || '' === $value) {
            return '';
        }

        $sanitized = wp_strip_all_tags($value);

        if (function_exists('remove_accents')) {
            $sanitized = remove_accents($sanitized);
        }

        $sanitized = $to_lowercase($sanitized);

        return trim($sanitized);
    };

    $normalized_search_term = $normalize_text($search_term);

    $normalized_status = $default_filters['statut'];
    if (isset($args['statut']) && is_string($args['statut']) && in_array($args['statut'], $status_whitelist, true)) {
        $normalized_status = $args['statut'];
    }

    $cost_filter_provided = array_key_exists('cout', $args);
    $raw_cost_values      = $args['cout'] ?? $default_filters['cout'];

    if (is_string($raw_cost_values)) {
        $raw_cost_values = [$raw_cost_values];
    }

    if (!is_array($raw_cost_values)) {
        $raw_cost_values = [];
    }

    $raw_cost_values = array_map('strval', $raw_cost_values);
    $normalized_cost = array_values(array_intersect($cost_whitelist, $raw_cost_values));

    if (!$cost_filter_provided) {
        $normalized_cost = $default_filters['cout'];
    }

    $query = new WP_Query([
        'post_type'      => 'chasse',
        'post_status'    => 'publish',
        'meta_query'     => [
            [
                'key'   => 'chasse_cache_statut_validation',
                'value' => 'valide',
            ],
        ],
        'fields'         => 'ids',
        'posts_per_page' => -1,
    ]);

    $chasse_ids = is_array($query->posts) ? array_map('intval', $query->posts) : [];
    wp_reset_postdata();

    $user_id = (int) get_current_user_id();

    if (function_exists('chasse_est_visible_pour_utilisateur')) {
        $chasse_ids = array_values(array_filter(
            $chasse_ids,
            static function (int $chasse_id) use ($user_id): bool {
                return chasse_est_visible_pour_utilisateur($chasse_id, $user_id);
            }
        ));
    }

    $status_map = [
        'en_cours' => ['en_cours', 'payante'],
        'a_venir'  => ['a_venir'],
        'termine'  => ['termine'],
    ];

    $extract_taxonomy_names = static function ($terms): array {
        if (!is_array($terms)) {
            return [];
        }

        $names = [];

        foreach ($terms as $term) {
            $name = '';

            if (is_array($term)) {
                $candidates = [
                    $term['nom'] ?? null,
                    $term['name'] ?? null,
                    $term['label'] ?? null,
                    $term['title'] ?? null,
                ];

                foreach ($candidates as $candidate) {
                    if (!is_string($candidate) && !is_numeric($candidate)) {
                        continue;
                    }

                    $candidate_value = trim((string) $candidate);

                    if ('' === $candidate_value) {
                        continue;
                    }

                    $name = $candidate_value;
                    break;
                }
            } elseif ($term instanceof \WP_Term) {
                $name = trim((string) $term->name);
            } elseif (is_string($term) || is_numeric($term)) {
                $name = trim((string) $term);
            }

            if ('' === $name) {
                continue;
            }

            $names[] = $name;
        }

        if (empty($names)) {
            return [];
        }

        return array_values(array_unique($names));
    };

    $resolve_taxonomy_terms = static function (int $hunt_id, string $taxonomy) use ($extract_taxonomy_names): array {
        $terms = [];

        if (function_exists('chasse_preparer_termes_affichage')) {
            $terms = chasse_preparer_termes_affichage($hunt_id, $taxonomy);
        } elseif (function_exists('wp_get_post_terms')) {
            $terms = wp_get_post_terms($hunt_id, $taxonomy, ['orderby' => 'term_order']);

            if (function_exists('is_wp_error') && is_wp_error($terms)) {
                $terms = [];
            }
        }

        if (empty($terms)) {
            return [];
        }

        if (!is_array($terms)) {
            $terms = [$terms];
        }

        return $extract_taxonomy_names($terms);
    };

    $hunts_data = [];

    foreach ($chasse_ids as $chasse_id) {
        $infos_chasse = function_exists('preparer_infos_affichage_chasse')
            ? preparer_infos_affichage_chasse($chasse_id, $user_id)
            : [];

        $statut_metier = $infos_chasse['statut'] ?? get_field('chasse_cache_statut', $chasse_id);
        $cout_points = (int) ($infos_chasse['champs']['cout_points'] ?? get_field('chasse_infos_cout_points', $chasse_id));
        $nb_enigmes_payantes = (int) ($infos_chasse['nb_enigmes_payantes'] ?? 0);

        $is_free = ($cout_points <= 0) && ($nb_enigmes_payantes <= 0);
        $cost_key = $is_free ? 'gratuit' : 'points';

        $title = get_the_title($chasse_id);
        $post_excerpt = get_post_field('post_excerpt', $chasse_id);
        $description_field = get_field('chasse_principale_description', $chasse_id);
        $description_segments = [];

        if (is_string($post_excerpt) && '' !== trim($post_excerpt)) {
            $description_segments[] = $post_excerpt;
        }

        if (is_string($description_field) && '' !== trim($description_field)) {
            $description_segments[] = $description_field;
        }

        $description_text = trim(implode(' ', $description_segments));

        $organizer_name = '';
        if (function_exists('get_organisateur_from_chasse')) {
            $organizer_id = get_organisateur_from_chasse($chasse_id);
            if ($organizer_id) {
                $organizer_title = get_the_title($organizer_id);
                if (is_string($organizer_title)) {
                    $organizer_name = $organizer_title;
                }
            }
        }

        $region_terms = $resolve_taxonomy_terms($chasse_id, 'chasse_region');
        $theme_terms  = $resolve_taxonomy_terms($chasse_id, 'theme_chasse');

        $taxonomy_terms = array_merge($region_terms, $theme_terms);

        if (!empty($taxonomy_terms)) {
            $taxonomy_terms = array_values(array_unique($taxonomy_terms));
        }

        $hunts_data[] = [
            'id'     => (int) $chasse_id,
            'status' => is_string($statut_metier) ? $statut_metier : '',
            'cost'   => $cost_key,
            'title'  => is_string($title) ? $title : '',
            'description' => $description_text,
            'organizer'   => $organizer_name,
            'taxonomy_terms' => $taxonomy_terms,
        ];
    }

    $search_filtered_hunts = $hunts_data;

    if ('' !== $normalized_search_term) {
        $search_filtered_hunts = array_values(array_filter(
            $hunts_data,
            static function (array $hunt) use ($normalize_text, $normalized_search_term): bool {
                $haystacks = [
                    $hunt['title'] ?? '',
                    $hunt['description'] ?? '',
                    $hunt['organizer'] ?? '',
                ];

                if (!empty($hunt['taxonomy_terms']) && is_array($hunt['taxonomy_terms'])) {
                    foreach ($hunt['taxonomy_terms'] as $term_name) {
                        $haystacks[] = (string) $term_name;
                    }
                }

                foreach ($haystacks as $haystack) {
                    $normalized_value = $normalize_text((string) $haystack);

                    if ('' === $normalized_value) {
                        continue;
                    }

                    if (false !== strpos($normalized_value, $normalized_search_term)) {
                        return true;
                    }
                }

                return false;
            }
        ));
    }

    $filtered_ids = [];

    foreach ($search_filtered_hunts as $hunt) {
        if ('tous' !== $normalized_status) {
            $allowed_statuses = $status_map[$normalized_status] ?? [];

            if (empty($allowed_statuses) || !in_array($hunt['status'], $allowed_statuses, true)) {
                continue;
            }
        }

        if ($cost_filter_provided) {
            if (empty($normalized_cost)) {
                continue;
            }

            if ('gratuit' === $hunt['cost'] && !in_array('gratuit', $normalized_cost, true)) {
                continue;
            }

            if ('points' === $hunt['cost'] && !in_array('points', $normalized_cost, true)) {
                continue;
            }
        }

        $filtered_ids[] = $hunt['id'];
    }

    $status_counts = [
        'tous'     => 0,
        'en_cours' => 0,
        'a_venir'  => 0,
        'termine'  => 0,
    ];

    foreach ($search_filtered_hunts as $hunt) {
        $status_counts['tous']++;

        foreach ($status_map as $status_filter => $expected_statuses) {
            if (in_array($hunt['status'], $expected_statuses, true)) {
                $status_counts[$status_filter]++;
            }
        }
    }

    $cost_counts = [
        'gratuit' => 0,
        'points'  => 0,
    ];

    foreach ($search_filtered_hunts as $hunt) {
        if (array_key_exists($hunt['cost'], $cost_counts)) {
            $cost_counts[$hunt['cost']]++;
        }
    }

    $results_message = '';
    if (count($filtered_ids) <= 0) {
        $results_message = __('Aucune chasse trouvée', 'chassesautresor-com');
    }

    return [
        'ids'                => $filtered_ids,
        'total'              => count($filtered_ids),
        'filters_normalises' => [
            'statut' => $normalized_status,
            'cout'   => $normalized_cost,
            'search' => $sanitized_search_term,
        ],
        'available_filters'  => [
            'statut' => $status_counts,
            'cout'   => $cost_counts,
        ],
        'message'            => $results_message,
    ];
}

/**
 * AJAX endpoint returning filtered hunts list markup.
 */
function ca_ajax_filter_chasses(): void
{
    check_ajax_referer('ca-filter-chasses', 'nonce');

    $status_whitelist = ['tous', 'en_cours', 'a_venir', 'termine'];
    $cost_whitelist   = ['gratuit', 'points'];

    $raw_request = wp_unslash($_POST);

    $filters = [];

    if (isset($raw_request['status']) && is_string($raw_request['status'])) {
        $status = sanitize_text_field($raw_request['status']);
        if (in_array($status, $status_whitelist, true)) {
            $filters['statut'] = $status;
        }
    }

    if (array_key_exists('cost', $raw_request)) {
        $raw_cost = $raw_request['cost'];

        if (is_string($raw_cost)) {
            $raw_cost = [$raw_cost];
        }

        if (is_array($raw_cost)) {
            $raw_cost = array_map('strval', $raw_cost);
            $filtered_cost = array_values(array_intersect($cost_whitelist, $raw_cost));
            $filters['cout'] = $filtered_cost;
        }
    }

    if (array_key_exists('search', $raw_request)) {
        $filters['search'] = sanitize_text_field((string) $raw_request['search']);
    }

    $filter_results = ca_home_filter_chasse_ids($filters);

    if (!is_array($filter_results) || !isset($filter_results['ids'])) {
        wp_send_json_error([
            'message' => __('Impossible de charger les chasses.', 'chassesautresor-com'),
        ]);
    }

    $chasse_ids = is_array($filter_results['ids']) ? array_map('intval', $filter_results['ids']) : [];

    ob_start();
    get_template_part('template-parts/organisateur/organisateur-partial-boucle-chasses', null, [
        'chasse_ids'  => $chasse_ids,
        'show_header' => false,
        'grid_class'  => 'organisateur-chasses-grid',
        'before_items' => '',
        'after_items'  => '',
    ]);
    $html = (string) ob_get_clean();

    $available_filters = [];
    if (isset($filter_results['available_filters']) && is_array($filter_results['available_filters'])) {
        $available_filters = $filter_results['available_filters'];
    }

    $normalized_filters = [];
    if (isset($filter_results['filters_normalises']) && is_array($filter_results['filters_normalises'])) {
        $normalized_filters = $filter_results['filters_normalises'];
    }

    $response_message = '';
    if (!empty($filter_results['message']) && is_string($filter_results['message'])) {
        $response_message = $filter_results['message'];
    }

    wp_send_json_success([
        'html'     => $html,
        'total'    => (int) ($filter_results['total'] ?? count($chasse_ids)),
        'nonce'    => wp_create_nonce('ca-filter-chasses'),
        'filters'  => [
            'available'  => $available_filters,
            'normalized' => $normalized_filters,
        ],
        'message'  => $response_message,
    ]);
}

add_action('wp_ajax_ca_filter_chasses', 'ca_ajax_filter_chasses');
add_action('wp_ajax_nopriv_ca_filter_chasses', 'ca_ajax_filter_chasses');
