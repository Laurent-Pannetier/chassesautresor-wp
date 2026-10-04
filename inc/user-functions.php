<?php
defined( 'ABSPATH' ) || exit;

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


// ==================================================
// 👤 USER PROFILE UTILITIES
// ==================================================
// ==================================================
// 🎯 CHASSES ENGAGÉES & 📊 TENTATIVES UTILISATEUR
// ==================================================
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
    if (function_exists('cat_is_single_hunt_mode') && cat_is_single_hunt_mode()) {
        ob_start();
        ?>
        <div class="dashboard-card dashboard-placeholder" aria-disabled="true">
            <div class="dashboard-card-content">
                <p>
                    <?php
                    esc_html_e(
                        'Votre progression apparaîtra ici dès que vous aurez commencé l’aventure.',
                        'chassesautresor-com'
                    );
                    ?>
                </p>
            </div>
        </div>
        <?php

        return ob_get_clean();
    }

    $recommended_ids = (new ChassesAuTresor\Core\Progress\EngagedHuntsRecommendationService())->find(3);

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

    global $wpdb;
    $current_user = wp_get_current_user();
    $user_id = (int) $current_user->ID;

    $page_param = ca_get_engaged_hunts_page_param();
    $requested_page = isset($_GET[$page_param]) ? absint($_GET[$page_param]) : 1;
    if ($requested_page <= 0) {
        $requested_page = 1;
    }

    $per_page = (int) apply_filters('ca_engaged_hunts_per_page', 6);
    if ($per_page <= 0) {
        $per_page = 6;
    }

    $context = (new ChassesAuTresor\Core\Users\AccountDashboardDataService($wpdb))->engagedHunts(
        $current_user,
        $requested_page,
        $per_page
    );
    if (!$context['allowed']) {
        return;
    }
    $pagination = $context['pagination'];

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

    $section_title = (function_exists('cat_is_single_hunt_mode') && cat_is_single_hunt_mode())
        ? esc_html__('Progression de la chasse', 'chassesautresor-com')
        : esc_html__('Vos chasses en cours', 'chassesautresor-com');
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
    global $wpdb;

    $search = ca_get_search_term('tentatives');
    return (new ChassesAuTresor\Core\Users\AccountDashboardDataService($wpdb))->attempts(
        $user_id,
        $page,
        $per_page,
        $search
    );
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
            'nonce'   => wp_create_nonce('ca_fetch_tentatives'),
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
        wp_localize_script('avatar-upload', 'avatarUpload', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('upload_user_avatar'),
        ]);
    }
}
add_action('wp_enqueue_scripts', 'charger_script_avatar_upload');
