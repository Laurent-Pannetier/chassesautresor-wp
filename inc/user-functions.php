<?php
defined( 'ABSPATH' ) || exit;

function cat_get_user_attempt_statistics_service(): ChassesAuTresor\Core\Progress\UserAttemptStatisticsService
{
    global $wpdb;
    return ChassesAuTresor\Core\Support\CoreServiceFactory::userAttemptStatistics($wpdb);
}

// ==================================================
// 📚 SOMMAIRE DU FICHIER
// ==================================================
//
// 1. 📦 TEMPLATES UTILISATEURS
//    - Routage personnalisé de /mon-compte/ vers des templates dédiés
//
// 2. 📦 MODIFICATION AVATAR EN FRONT
//    - Gestion complète de l’upload, affichage et remplacement de l’avatar utilisateur
//
// 3. 📦 TUILES UTILISATEUR
//    - Affichage dynamique des éléments liés à l’utilisateur (commandes WooCommerce)
//
// 4. 📦 ATTRIBUTION DE RÔLE
//     - Attribution des rôles oragnisateurs (création)
//
// 5. 📣 MESSAGES IMPORTANTS
//    - Affichage centralisé des messages clés de l'espace "Mon Compte"

// 6. 📡 AJAX ADMIN SECTIONS
//    - Chargement dynamique des pages d'administration dans "Mon Compte"
//
// ==================================================
// 📦 TEMPLATES UTILISATEURS
// ==================================================
/**
 * 🔹 modifier_titre_onglet → Modifier dynamiquement le titre de la page dans l'onglet du navigateur.
 * 🔹 is_woocommerce_account_page → Vérifier si la page actuelle est une sous-page WooCommerce dans "Mon Compte".
 */


/**
 * Modifier dynamiquement le titre de la page dans l'onglet du navigateur
 *
 * @param string $title Le titre actuel.
 * @return string Le titre modifié.
 */
function modifier_titre_onglet($title) {
    global $wp;
    $current_url = trim($wp->request, '/');

    // Définition des titres pour chaque page
    $page_titles = [
        'mon-compte/statistiques'  => __('Statistiques - Chasses au Trésor', 'chassesautresor-com'),
        'mon-compte/outils'        => __('Outils - Chasses au Trésor', 'chassesautresor-com'),
        'mon-compte/organisateurs' => __('Organisateur - Chasses au Trésor', 'chassesautresor-com'),
    ];

    // Si l’URL correspond à une page définie, modifier le titre
    if (isset($page_titles[$current_url])) {
        return $page_titles[$current_url];
    }

    return $title; // Conserver le titre par défaut si l'URL ne correspond pas
}
add_filter('pre_get_document_title', 'modifier_titre_onglet');

/**
 * Vérifie si la page actuelle est une page WooCommerce spécifique dans "Mon Compte".
 *
 * Cette fonction analyse l'URL actuelle et détermine si elle correspond à l'une
 * des pages WooCommerce spécifiques où le contenu du compte WooCommerce doit être affiché.
 *
 * Liste des pages WooCommerce prises en compte :

 *
 * @return bool True si la page actuelle est une page WooCommerce autorisée, False sinon.
 */
function is_woocommerce_account_page() {
    // Récupérer l'URL actuelle
    $current_url = $_SERVER['REQUEST_URI'];

    // Liste des pages WooCommerce où afficher woocommerce_account_content()
    $pages_woocommerce = [
        '/mon-compte/commandes/',
        '/mon-compte/voir-commandes/',
        '/mon-compte/modifier-adresse/',
        '/mon-compte/modifier-compte/',
        '/mon-compte/telechargements/',
        '/mon-compte/moyens-paiement/',
        '/mon-compte/lost-password/',
        '/mon-compte/customer-logout/'
    ];

    // Vérifier si l'URL actuelle correspond à une page WooCommerce autorisée
    foreach ($pages_woocommerce as $page) {
        if (strpos($current_url, $page) === 0) {
            return true;
        }
    }

    return false;
}

/**
 * Rename WooCommerce "orders" endpoint title to "Vos commandes".
 *
 * @param string $title Original title.
 * @return string Modified title.
 */
function ca_orders_endpoint_title($title)
{
    return __('Vos commandes', 'chassesautresor-com');
}
add_filter('woocommerce_endpoint_orders_title', 'ca_orders_endpoint_title');

/**
 * Rename "edit-account" endpoint title to "Profil".
 *
 * @param string $title Original title.
 * @return string Modified title.
 */
function ca_profile_endpoint_title($title)
{
    return __('Profil', 'chassesautresor');
}
add_filter('woocommerce_endpoint_edit-account_title', 'ca_profile_endpoint_title');

// ==================================================
// 👤 USER PROFILE UTILITIES
// ==================================================
/**
 * Check whether the mandatory WooCommerce account fields are filled in.
 *
 * @param int $user_id Target user identifier.
 *
 * @return array{complete:bool,missing:array<int,string>} Tuple containing the completion status and the list of missing field labels.
 */
function cat_is_user_profile_complete(int $user_id): array
{
    $user = get_userdata($user_id);

    if (!$user) {
        return [
            'complete' => false,
            'missing'  => [__('Profil utilisateur introuvable', 'chassesautresor-com')],
        ];
    }

    $default_fields = [
        'first_name'   => [
            'label'  => __('Prénom', 'chassesautresor-com'),
            'source' => 'meta',
        ],
        'last_name'    => [
            'label'  => __('Nom', 'chassesautresor-com'),
            'source' => 'meta',
        ],
        'display_name' => [
            'label'  => __('Nom d’affichage', 'chassesautresor-com'),
            'source' => 'property',
        ],
        'user_email'   => [
            'label'  => __('Adresse e-mail', 'chassesautresor-com'),
            'source' => 'property',
        ],
    ];

    /** @var array<string, array{label:string,source?:string,callback?:callable}|string> $required_fields */
    $required_fields = apply_filters('cat_required_user_profile_fields', $default_fields, $user_id, $user);

    $missing = [];

    foreach ($required_fields as $field_key => $config) {
        if (is_string($config)) {
            $config = [
                'label'  => $config,
                'source' => 'meta',
            ];
        }

        if (empty($config['label'])) {
            continue;
        }

        $label = (string) $config['label'];

        $value = null;
        if (!empty($config['callback']) && is_callable($config['callback'])) {
            $value = call_user_func($config['callback'], $user_id, $user, $field_key, $config);
        } elseif (($config['source'] ?? 'meta') === 'property') {
            $value = $user->{$field_key} ?? '';
        } else {
            $value = get_user_meta($user_id, $field_key, true);
        }

        if (is_scalar($value) || $value === null) {
            $value = trim((string) $value);
        } elseif (is_array($value)) {
            $value = implode('', array_map('trim', array_map('strval', $value)));
        } else {
            $value = '';
        }

        if ($value === '') {
            $missing[] = $label;
        }
    }

    return [
        'complete' => $missing === [],
        'missing'  => $missing,
    ];
}

/**
 * Build a translated message listing missing profile fields.
 *
 * @param array<int, string> $missing_fields Missing field labels.
 *
 * @return string
 */
function cat_get_missing_profile_fields_message(array $missing_fields): string
{
    if ($missing_fields === []) {
        return __('Veuillez compléter votre profil utilisateur.', 'chassesautresor-com');
    }

    $fields_list = wp_sprintf_l('%l', $missing_fields);

    return sprintf(
        /* translators: %s: comma-separated list of missing profile fields */
        __('Veuillez compléter votre profil utilisateur : %s.', 'chassesautresor-com'),
        $fields_list
    );
}

// ==================================================
// 🎯 CHASSES ENGAGÉES & 📊 TENTATIVES UTILISATEUR
// ==================================================
/**
 * Get the query parameter used to paginate engaged hunts.
 *
 * @return string
 */
function ca_get_engaged_hunts_page_param(): string
{
    return 'engaged-page';
}

/**
 * Retrieve all hunt IDs engaged by the given user.
 *
 * @param int $user_id User identifier.
 *
 * @return int[]
 */
function ca_get_user_engaged_hunt_ids(int $user_id): array
{
    $engaged_hunt_ids = cat_get_hunt_engagement_service()->findHuntIdsForUser($user_id);
    if ($engaged_hunt_ids === []) {
        return [];
    }

    $chasse_ids = [];

    foreach ($engaged_hunt_ids as $chasse_id) {
        if (
            function_exists('chasse_est_visible_pour_utilisateur')
            && !chasse_est_visible_pour_utilisateur($chasse_id, $user_id)
        ) {
            continue;
        }

        $chasse_ids[] = $chasse_id;
    }

    return $chasse_ids;
}

/**
 * Prepare pagination data for engaged hunts.
 *
 * @param int[] $chasse_ids List of hunt identifiers.
 * @param int   $page       Requested page (1-indexed).
 * @param int   $per_page   Number of hunts per page.
 *
 * @return array{ids:int[],page:int,total_pages:int,total_items:int}
 */
function ca_prepare_engaged_hunts_pagination(array $chasse_ids, int $page, int $per_page): array
{
    $per_page = max(1, $per_page);
    $total_items = count($chasse_ids);
    $total_pages = max(1, (int) ceil($total_items / $per_page));

    $page = max(1, min($page, $total_pages));
    $offset = ($page - 1) * $per_page;

    return [
        'ids'         => array_slice($chasse_ids, $offset, $per_page),
        'page'        => $page,
        'total_pages' => $total_pages,
        'total_items' => $total_items,
    ];
}

/**
 * Build the HTML markup for engaged hunts and the related pager.
 *
 * @param int[]  $chasse_ids  Hunt identifiers to render.
 * @param int    $page        Current page.
 * @param int    $total_pages Total amount of pages.
 * @param string $grid_class  Additional grid classes for the template part.
 * @param string $mode        Display mode for the template part.
 * @param string $page_param  Query parameter used for pagination.
 *
 * @return string
 */
function ca_get_engaged_hunts_content_html(
    array $chasse_ids,
    int $page,
    int $total_pages,
    string $grid_class,
    string $mode,
    string $page_param
): string {
    ob_start();

    if (!empty($chasse_ids)) {
        get_template_part(
            'template-parts/chasse/boucle-chasses',
            null,
            [
                'show_header' => false,
                'mode'        => $mode,
                'grid_class'  => $grid_class,
                'chasse_ids'  => $chasse_ids,
            ]
        );

        if ($total_pages > 1) {
            echo cta_render_pager(
                $page,
                $total_pages,
                'engaged-hunts-pager',
                [
                    'data-param' => $page_param,
                ]
            );
        }
    } else {
        echo ca_render_recommended_hunts_empty_state();
    }

    return ob_get_clean();
}

/**
 * Render the empty state for engaged hunts with recommended public hunts.
 *
 * The helper displays a short message, a curated selection of public hunts using the
 * standard "carte" grid, and a call-to-action pointing to the full catalogue.
 *
 * @return string
 */
function ca_render_recommended_hunts_empty_state(): string
{
    $valid_statuses = ['a_venir', 'en_cours', 'payante'];
    $base_meta_query = [
        'relation' => 'AND',
        [
            'key'     => 'chasse_cache_statut',
            'value'   => $valid_statuses,
            'compare' => 'IN',
        ],
        [
            'key'   => 'chasse_cache_statut_validation',
            'value' => 'valide',
        ],
    ];

    $recent_query_args = apply_filters(
        'ca_recommended_hunts_recent_query_args',
        [
            'post_type'        => 'chasse',
            'post_status'      => 'publish',
            'posts_per_page'   => 2,
            'orderby'          => 'date',
            'order'            => 'DESC',
            'no_found_rows'    => true,
            'fields'           => 'ids',
            'suppress_filters' => false,
            'meta_query'       => $base_meta_query,
        ]
    );

    $recent_ids = array_map('intval', get_posts($recent_query_args));

    $active_meta_query = $base_meta_query;
    $active_meta_query[0]['value'] = ['en_cours', 'payante'];

    $popular_query_args = apply_filters(
        'ca_recommended_hunts_popular_query_args',
        [
            'post_type'        => 'chasse',
            'post_status'      => 'publish',
            'posts_per_page'   => 1,
            'meta_key'         => 'ca_total_engagements',
            'orderby'          => 'meta_value_num',
            'order'            => 'DESC',
            'no_found_rows'    => true,
            'fields'           => 'ids',
            'suppress_filters' => false,
            'meta_query'       => $active_meta_query,
        ]
    );

    $popular_ids = array_map('intval', get_posts($popular_query_args));

    $recommended_ids = array_values(array_unique(array_merge($recent_ids, $popular_ids)));

    if (count($recommended_ids) < 3) {
        $fallback_query_args = apply_filters(
            'ca_recommended_hunts_fallback_query_args',
            [
                'post_type'        => 'chasse',
                'post_status'      => 'publish',
                'posts_per_page'   => 3 - count($recommended_ids),
                'orderby'          => 'date',
                'order'            => 'DESC',
                'no_found_rows'    => true,
                'fields'           => 'ids',
                'suppress_filters' => false,
                'meta_query'       => $base_meta_query,
                'post__not_in'     => $recommended_ids,
            ]
        );

        $additional_ids = array_map('intval', get_posts($fallback_query_args));
        if (!empty($additional_ids)) {
            $recommended_ids = array_values(array_unique(array_merge($recommended_ids, $additional_ids)));
        }
    }

    if (count($recommended_ids) < 3) {
        $completed_query_args = apply_filters(
            'ca_recommended_hunts_completed_query_args',
            [
                'post_type'        => 'chasse',
                'post_status'      => 'publish',
                'posts_per_page'   => 3 - count($recommended_ids),
                'orderby'          => 'date',
                'order'            => 'DESC',
                'no_found_rows'    => true,
                'fields'           => 'ids',
                'suppress_filters' => false,
                'meta_query'       => [
                    [
                        'key'   => 'chasse_cache_statut',
                        'value' => 'termine',
                    ],
                    [
                        'key'   => 'chasse_cache_statut_validation',
                        'value' => 'valide',
                    ],
                ],
                'post__not_in'     => $recommended_ids,
            ]
        );

        $completed_ids = array_map('intval', get_posts($completed_query_args));
        if (!empty($completed_ids)) {
            $recommended_ids = array_values(array_unique(array_merge($recommended_ids, $completed_ids)));
        }
    }

    $recommended_ids = array_slice($recommended_ids, 0, 3);

    /**
     * Allow third-parties to tweak the final recommended hunts selection.
     *
     * @param int[] $recommended_ids Selected hunt identifiers.
     */
    $recommended_ids = apply_filters('ca_recommended_hunts_empty_state_ids', $recommended_ids);
    $recommended_ids = array_values(array_unique(array_filter(array_map('intval', (array) $recommended_ids))));

    $catalog_url = apply_filters(
        'ca_recommended_hunts_catalog_url',
        home_url('/'),
        '/'
    );

    $slider_label = __('Chasses recommandées', 'chassesautresor-com');
    $show_controls = count($recommended_ids) > 1;
    $slider_id = wp_unique_id('recommended-slider-');
    $track_id  = $slider_id . '-track';

    ob_start();
    ?>
    <div class="myaccount-recommended-hunts">
        <p class="myaccount-placeholder">
            <?php esc_html_e('Vous ne participez à aucune chasse pour le moment. Voici quelques idées pour démarrer votre prochaine aventure.', 'chassesautresor-com'); ?>
        </p>

        <?php if (!empty($recommended_ids)) : ?>
            <div
                id="<?php echo esc_attr($slider_id); ?>"
                class="myaccount-recommended-slider"
                data-recommended-slider
                data-slider-label="<?php echo esc_attr($slider_label); ?>"
            >
                <?php if ($show_controls) : ?>
                    <button
                        type="button"
                        class="recommended-slider__control recommended-slider__control--prev"
                        data-recommended-slider-prev
                        aria-controls="<?php echo esc_attr($track_id); ?>"
                        aria-label="<?php esc_attr_e('Afficher la chasse précédente', 'chassesautresor-com'); ?>"
                        disabled
                    >
                        <span aria-hidden="true">&#10094;</span>
                    </button>
                <?php endif; ?>

                <div class="recommended-slider__viewport" data-recommended-slider-viewport>
                    <div
                        id="<?php echo esc_attr($track_id); ?>"
                        class="recommended-slider__track"
                        data-recommended-slider-track
                    >
                        <?php foreach ($recommended_ids as $index => $chasse_id) : ?>
                            <div
                                class="recommended-slider__slide"
                                data-recommended-slide
                                data-slide-index="<?php echo esc_attr((string) $index); ?>"
                            >
                                <div class="recommended-slider__slide-inner">
                                    <?php
                                    get_template_part(
                                        'template-parts/chasse/chasse-card-compact',
                                        null,
                                        [
                                            'chasse_id'        => $chasse_id,
                                            'show_progression' => false,
                                        ]
                                    );
                                    ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php if ($show_controls) : ?>
                    <button
                        type="button"
                        class="recommended-slider__control recommended-slider__control--next"
                        data-recommended-slider-next
                        aria-controls="<?php echo esc_attr($track_id); ?>"
                        aria-label="<?php esc_attr_e('Afficher la chasse suivante', 'chassesautresor-com'); ?>"
                    >
                        <span aria-hidden="true">&#10095;</span>
                    </button>
                <?php endif; ?>
            </div>
        <?php else : ?>
            <p class="myaccount-recommended-hunts-intro">
                <?php esc_html_e('Aucune recommandation disponible pour le moment, mais notre catalogue vous attend.', 'chassesautresor-com'); ?>
            </p>
        <?php endif; ?>

        <a class="bouton-cta myaccount-recommended-hunts-cta" href="<?php echo esc_url($catalog_url); ?>">
            <?php esc_html_e('Explorer toutes nos chasses', 'chassesautresor-com'); ?>
        </a>
    </div>
    <?php

    return ob_get_clean();
}

/**
 * Display engaged hunts on the My Account dashboard for player roles.
 *
 * @return void
 */
function ca_render_dashboard_engaged_hunts(): void
{
    if (!is_user_logged_in()) {
        return;
    }

    $current_user = wp_get_current_user();
    $user_id      = (int) $current_user->ID;

    if ($user_id <= 0) {
        return;
    }

    $roles        = (array) $current_user->roles;
    $player_roles = ['subscriber', 'customer'];
    $is_player    = !empty(array_intersect($player_roles, $roles));
    $is_admin     = current_user_can('administrator');
    $is_organizer = function_exists('est_organisateur') && est_organisateur($user_id);

    if (!$is_player || $is_admin) {
        return;
    }

    $page_param = ca_get_engaged_hunts_page_param();
    $requested_page = isset($_GET[$page_param]) ? absint($_GET[$page_param]) : 1;
    if ($requested_page <= 0) {
        $requested_page = 1;
    }

    $per_page = (int) apply_filters('ca_engaged_hunts_per_page', 6);
    if ($per_page <= 0) {
        $per_page = 6;
    }

    $chasse_ids = ca_get_user_engaged_hunt_ids($user_id);
    $pagination = ca_prepare_engaged_hunts_pagination($chasse_ids, $requested_page, $per_page);

    $dir = get_stylesheet_directory();
    $uri = get_stylesheet_directory_uri();

    wp_enqueue_script(
        'pager',
        $uri . '/assets/js/core/pager.js',
        [],
        filemtime($dir . '/assets/js/core/pager.js'),
        true
    );

    wp_enqueue_script(
        'engaged-hunts-pager',
        $uri . '/assets/js/engaged-hunts-pager.js',
        ['pager'],
        filemtime($dir . '/assets/js/engaged-hunts-pager.js'),
        true
    );

    $section_title = esc_html__('Vos chasses en cours', 'chassesautresor-com');
    $error_message = __('Une erreur est survenue lors du chargement des chasses. Veuillez réessayer.', 'chassesautresor-com');
    $content_html  = ca_get_engaged_hunts_content_html(
        $pagination['ids'],
        $pagination['page'],
        $pagination['total_pages'],
        'cards-grid myaccount-chasses-engagees-grid',
        'carte',
        $page_param
    );

    $ajax_url = admin_url('admin-ajax.php');
    $nonce    = wp_create_nonce('ca-engaged-hunts');

    ob_start();
    ?>
    <section
        class="myaccount-chasses-engagees"
        data-engaged-hunts
        data-endpoint="<?php echo esc_url($ajax_url); ?>"
        data-action="ca_get_engaged_hunts"
        data-nonce="<?php echo esc_attr($nonce); ?>"
        data-param="<?php echo esc_attr($page_param); ?>"
        data-error="<?php echo esc_attr($error_message); ?>"
        data-total-count="<?php echo esc_attr($pagination['total_items']); ?>"
        data-total-pages="<?php echo esc_attr($pagination['total_pages']); ?>"
        data-current-page="<?php echo esc_attr($pagination['page']); ?>"
    >
        <div class="myaccount-section-header">
            <h2 class="myaccount-section-title"><?php echo $section_title; ?></h2>
        </div>
        <div
            class="myaccount-chasses-engagees-content"
            data-engaged-hunts-content
            aria-live="polite"
            aria-busy="false"
        >
            <?php echo $content_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>
    </section>
    <?php
    echo ob_get_clean();
}
add_action('woocommerce_account_dashboard', 'ca_render_dashboard_engaged_hunts', 10);

/**
 * AJAX handler for engaged hunts pagination.
 *
 * @return void
 */
function ca_ajax_get_engaged_hunts(): void
{
    ChassesAuTresor\Core\Progress\EngagedHuntsAjaxHandler::handle();
}

function ca_render_engaged_hunts_ajax_content(array $pagination): string
{
    return ca_get_engaged_hunts_content_html(
        $pagination['ids'],
        $pagination['page'],
        $pagination['total_pages'],
        'cards-grid myaccount-chasses-engagees-grid',
        'carte',
        ca_get_engaged_hunts_page_param()
    );
}

if (class_exists(ChassesAuTresor\Core\Progress\EngagedHuntsAjaxHandler::class)) {
    ChassesAuTresor\Core\Progress\EngagedHuntsAjaxHandler::configure(
        static function (array $pagination): string {
            return ca_render_engaged_hunts_ajax_content($pagination);
        }
    );
}

/**
 * Register the search context used for the tentatives table.
 *
 * @return void
 */
function ca_register_tentatives_search_context(): void
{
    ca_register_search_context('tentatives', [
        'fields' => [
            'sql' => [
                'p.post_title',
                't.reponse_saisie',
                'chasses.post_title',
            ],
        ],
        'ui' => [
            'label'       => __('Rechercher une tentative', 'chassesautresor-com'),
            'placeholder' => __('Que recherchez-vous ?', 'chassesautresor-com'),
            'submit_icon' => 'search',
            'submit_icon_only' => true,
        ],
        'pagination_params' => ['tentatives-page'],
    ]);
}

/**
 * Builds the dataset required to render the tentatives table for a user.
 *
 * @param int $user_id  Target user identifier.
 * @param int $page     Requested page number.
 * @param int $per_page Number of rows per page.
 *
 * @return array<string, mixed>
 */
function ca_get_tentatives_view_model(int $user_id, int $page = 1, int $per_page = 10): array
{
    $per_page  = max(1, $per_page);
    $page      = max(1, $page);
    $search    = ca_get_search_term('tentatives');
    $service    = cat_get_user_attempt_statistics_service();
    $summary    = $service->summarize($user_id);
    $pagination = $service->paginate($user_id, $page, $per_page, $search);

    $message = $search !== ''
        ? __('Aucune tentative ne correspond à votre recherche.', 'chassesautresor-com')
        : __('Vous n\'avez pas encore enregistré de tentative.', 'chassesautresor-com');

    return [
        'pending'            => $summary['pending'],
        'total'              => $summary['total'],
        'success'            => $summary['success'],
        'search_term'        => $search,
        'page'               => $pagination['page'],
        'pages'              => $pagination['pages'],
        'per_page'           => $per_page,
        'filtered_total'     => $pagination['total'],
        'tentatives'         => $pagination['items'],
        'no_results_message' => $message,
    ];
}

/**
 * Renders the table rows for the tentatives table.
 *
 * @param array  $tentatives        Tentative rows.
 * @param int    $filtered_total    Number of filtered rows.
 * @param string $no_results_message Message displayed when there are no rows.
 *
 * @return string
 */
function ca_render_tentatives_rows(array $tentatives, int $filtered_total, string $no_results_message): string
{
    ob_start();

    if ($filtered_total <= 0 || empty($tentatives)) {
        ?>
        <tr class="tentatives-empty">
            <td colspan="5"><?php echo esc_html($no_results_message); ?></td>
        </tr>
        <?php
    } else {
        foreach ($tentatives as $tent) {
            $chasse_id    = isset($tent->chasse_id) ? (int) $tent->chasse_id : 0;
            $chasse_title = isset($tent->chasse_title) ? (string) $tent->chasse_title : '';
            ?>
            <tr>
                <td><?php echo esc_html(mysql2date('d/m/Y H:i', $tent->date_tentative)); ?></td>
                <td>
                    <?php if ($chasse_id > 0 && $chasse_title !== '') : ?>
                    <a href="<?php echo esc_url(get_permalink($chasse_id)); ?>">
                        <?php echo esc_html($chasse_title); ?>
                    </a>
                    <?php elseif ($chasse_title !== '') : ?>
                    <?php echo esc_html($chasse_title); ?>
                    <?php else : ?>
                    &mdash;
                    <?php endif; ?>
                </td>
                <td><?php echo esc_html($tent->enigme_title ?? ''); ?></td>
                <?php
                $uid     = isset($tent->tentative_uid) ? (string) $tent->tentative_uid : '';
                $options = $uid !== '' ? cta_prepare_masked_proposition_options($uid) : [];
                echo cta_render_proposition_cell($uid !== '' ? '' : ($tent->reponse_saisie ?? ''), false, 39, $options);
                ?>
                <?php
                $result = $tent->resultat;
                $class  = 'etiquette-error';
                if ($result === 'bon') {
                    $class = 'etiquette-success';
                } elseif ($result === 'attente') {
                    $class = 'etiquette-pending';
                }
                ?>
                <td>
                    <span class="etiquette <?php echo esc_attr($class); ?>">
                        <?php echo esc_html__($result, 'chassesautresor-com'); ?>
                    </span>
                </td>
            </tr>
            <?php
        }
    }

    return trim((string) ob_get_clean());
}

/**
 * Display the Tentatives table on the My Account dashboard.
 *
 * @return void
 */
function ca_render_dashboard_tentatives(): void
{
    if (!is_user_logged_in()) {
        return;
    }

    $user_id = (int) get_current_user_id();
    if ($user_id <= 0) {
        return;
    }

    $dir = get_stylesheet_directory();
    $uri = get_stylesheet_directory_uri();

    wp_enqueue_script(
        'pager',
        $uri . '/assets/js/core/pager.js',
        [],
        filemtime($dir . '/assets/js/core/pager.js'),
        true
    );

    wp_enqueue_script(
        'tentatives-pager',
        $uri . '/assets/js/tentatives-pager.js',
        ['pager'],
        filemtime($dir . '/assets/js/tentatives-pager.js'),
        true
    );

    wp_localize_script(
        'tentatives-pager',
        'caTentativesPager',
        [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'action'  => 'ca_fetch_tentatives',
            'errorMessage' => esc_html__(
                'Unable to load attempts. Please try again.',
                'chassesautresor-com'
            ),
        ]
    );

    if (function_exists('wp_enqueue_script')) {
        wp_enqueue_script('table-search');
    }

    ca_register_tentatives_search_context();

    $per_page = 10;
    $page     = max(1, (int) ($_GET['tentatives-page'] ?? 1));

    $view = ca_get_tentatives_view_model($user_id, $page, $per_page);

    ob_start();
    ?>
    <section class="myaccount-tentatives">
        <h2><?php esc_html_e('Tentatives', 'chassesautresor-com'); ?></h2>
        <div class="table-header">
            <div class="table-header__stats">
                <?php if ($view['pending'] > 0) : ?>
                <div class="meta-etiquette">
                    <span><?php printf(esc_html(_n('%d tentative en attente', '%d tentatives en attente', $view['pending'], 'chassesautresor-com')), $view['pending']); ?></span>
                </div>
                <?php endif; ?>
                <div class="meta-etiquette">
                    <span><?php printf(esc_html(_n('%d tentative', '%d tentatives', $view['total'], 'chassesautresor-com')), $view['total']); ?></span>
                </div>
                <?php if ($view['success'] > 0) : ?>
                <div class="meta-etiquette meta-etiquette--success">
                    <span><?php printf(esc_html(_n('%d bonne réponse', '%d bonnes rponses', $view['success'], 'chassesautresor-com')), $view['success']); ?></span>
                </div>
                <?php endif; ?>
            </div>
            <?php
            echo cta_render_search_form('tentatives', [
                'class'             => 'table-search--inline table-search--compact',
                'label'             => '',
                'show_reset_button' => true,
                'data_attributes'   => [
                    'ajax-action' => 'ca_fetch_tentatives',
                    'ajax-target' => '#tentatives-table-wrapper',
                ],
            ]);
            ?>
        </div>
        <div
            id="tentatives-table-wrapper"
            class="stats-table-wrapper"
            data-per-page="<?php echo esc_attr($view['per_page']); ?>"
            data-page="<?php echo esc_attr($view['page']); ?>"
        >
            <table class="stats-table tentatives-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Date', 'chassesautresor-com'); ?></th>
                        <th><?php esc_html_e('Chasse', 'chassesautresor-com'); ?></th>
                        <th><?php esc_html_e('Énigme', 'chassesautresor-com'); ?></th>
                        <th><?php esc_html_e('Proposition', 'chassesautresor-com'); ?></th>
                        <th><?php esc_html_e('Résultat', 'chassesautresor-com'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php echo ca_render_tentatives_rows($view['tentatives'], $view['filtered_total'], $view['no_results_message']); ?>
                </tbody>
            </table>
            <?php echo cta_render_pager($view['page'], $view['pages'], 'tentatives-pager', ['data-param' => 'tentatives-page', 'data-section' => '', 'data-search-key' => 'tentatives']); ?>
        </div>
    </section>
    <?php
    echo ob_get_clean();
}
add_action('woocommerce_account_dashboard', 'ca_render_dashboard_tentatives', 20);

/**
 * Handle AJAX refreshes for the tentatives table.
 *
 * @return void
 */
function ca_ajax_fetch_tentatives(): void
{
    ChassesAuTresor\Core\Progress\UserAttemptsAjaxHandler::handle();
}

function ca_render_tentatives_ajax_rows(array $view): string
{
    return ca_render_tentatives_rows(
        $view['tentatives'],
        $view['filtered_total'],
        $view['no_results_message']
    );
}

function ca_render_tentatives_ajax_pager(array $view): string
{
    return cta_render_pager(
        $view['page'],
        $view['pages'],
        'tentatives-pager',
        ['data-param' => 'tentatives-page', 'data-section' => '', 'data-search-key' => 'tentatives']
    );
}

if (class_exists(ChassesAuTresor\Core\Progress\UserAttemptsAjaxHandler::class)) {
    ChassesAuTresor\Core\Progress\UserAttemptsAjaxHandler::configure(
        static function (array $view): string {
            return ca_render_tentatives_ajax_rows($view);
        },
        static function (array $view): string {
            return ca_render_tentatives_ajax_pager($view);
        }
    );
}
// ==================================================
/**
 * Load My Account sections via AJAX.
 *
 * @return void
 */
function ca_render_admin_section(string $template_name): string
{
    ob_start();
    $template = get_stylesheet_directory() . '/templates/myaccount/' . basename($template_name);
    if (file_exists($template)) {
        include $template;
    }

    return (string) ob_get_clean();
}

function ca_load_admin_section(): void
{
    ChassesAuTresor\Core\Messages\AccountSectionAjaxHandler::handle();
}

if (class_exists(ChassesAuTresor\Core\Messages\AccountSectionAjaxHandler::class)) {
    ChassesAuTresor\Core\Messages\AccountSectionAjaxHandler::configure(
        static function (string $template_name): string {
            return ca_render_admin_section($template_name);
        }
    );
}

/**
 * Dismiss a persistent message via AJAX.
 *
 * @return void
 */
function ca_dismiss_message(): void
{
    ChassesAuTresor\Core\Messages\AccountMessageDismissalAjaxHandler::handle();
}

// ==================================================
// 📦 MODIFICATION AVATAR EN FRONT
// ==================================================
/**
 * 🔹 charger_script_avatar_upload → Charger le script JS d’upload uniquement sur les pages commençant par "/mon-compte/".
 */


/**
 * 📌 Charge le fichier JavaScript uniquement sur les pages commençant par "/mon-compte/"
 */
function charger_script_avatar_upload() {
    if (strpos($_SERVER['REQUEST_URI'], '/mon-compte/') === 0) {
        wp_enqueue_script(
            'avatar-upload',
            get_stylesheet_directory_uri() . '/assets/js/avatar-upload.js',
            [],
            null,
            true
        );
    }
}
add_action('wp_enqueue_scripts', 'charger_script_avatar_upload');



// ==================================================
// 📦 TUILES UTILISATEUR
// ==================================================
/**
 * 🔹 afficher_commandes_utilisateur → Récupérer et afficher les 4 dernières commandes d’un utilisateur WooCommerce sous forme de tableau.
 */


/**
 * Récupère et affiche les 3 dernières commandes d'un utilisateur WooCommerce sous forme de tableau.
 *
 * @param int $user_id ID de l'utilisateur connecté.
 * @param int $limit Nombre de commandes à afficher (par défaut : 4).
 * @return string HTML du tableau des commandes ou une chaîne vide si aucune commande.
 */
function afficher_commandes_utilisateur($user_id, $limit = 4) {
    if (!$user_id || !class_exists('WooCommerce')) {
        return '';
    }

    $customer_orders = wc_get_orders([
        'limit'    => $limit,
        'customer' => $user_id,
        'status'   => ['wc-completed'], // Commandes valides
        'orderby'  => 'date',
        'order'    => 'DESC'
    ]);
    
    if (empty($customer_orders)) {
        return ''; // Ne rien afficher si aucune commande
    }

    ob_start(); // Capture l'affichage HTML
    ?>
    <table class="stats-table">
        <tbody>
            <?php foreach ($customer_orders as $order) : ?>
                <?php
                $order_id = $order->get_id();
                $order_date = wc_format_datetime($order->get_date_created(), 'd/m/Y');
                $items = $order->get_items();
                $first_item = reset($items); // Récupère le premier produit de la commande
                $product_name = $first_item ? $first_item->get_name() : 'Produit inconnu';
                ?>
                <tr>
                    <td>#<?php echo esc_html($order_id); ?></td>
                    <td><?php echo esc_html($product_name); ?></td>
                    <td><?php echo esc_html($order_date); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
    return ob_get_clean(); // Retourne le HTML capturé
}
