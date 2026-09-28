<?php
defined('ABSPATH') || exit;

// ==================================================
// 💡 GESTION DES INDICES
// ==================================================
// 🔹 register_endpoint_creer_indice() → Enregistre /creer-indice
// 🔹 creer_indice_pour_objet() → Crée un indice lié à une chasse ou une énigme
// 🔹 creer_indice_et_rediriger_si_appel() → Crée un indice et redirige
// 🔹 rediriger_si_affichage_indice() → Redirige toute page indice vers sa cible
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
    $slug   = get_post_field('post_name', $chasse_id);

    if ($slug === '') {
        $chasse_name = get_post_field('post_title', $chasse_id);
        $slug        = $chasse_name !== ''
            ? (function_exists('sanitize_title')
                ? sanitize_title($chasse_name)
                : strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $chasse_name), '-')))
            : '';
    }

    return $prefix . $slug;
}

/**
 * Redirige l’affichage d’un indice vers sa chasse ou son énigme liée.
 *
 * @return void
 */
function rediriger_si_affichage_indice(): void
{
    if (!is_singular('indice')) {
        return;
    }

    $indice_id   = get_the_ID();
    $cible_type  = get_field('indice_cible_type', $indice_id);
    $redirect_id = 0;

    if ($cible_type === 'chasse') {
        $redirect_id = (int) get_field('indice_chasse_linked', $indice_id);
    } elseif ($cible_type === 'enigme') {
        $redirect_id = (int) get_field('indice_enigme_linked', $indice_id);
    }

    if ($redirect_id) {
        wp_safe_redirect(get_permalink($redirect_id));
        exit;
    }
}
add_action('template_redirect', 'rediriger_si_affichage_indice');


/**
 * Calcule le rang du prochain indice pour une chasse ou une énigme.
 *
 * @param int    $objet_id   ID de la chasse ou de l’énigme.
 * @param string $objet_type Type de cible ('chasse' ou 'enigme').
 * @return int
 */
function prochain_rang_indice(int $objet_id, string $objet_type): int
{
    if (!in_array($objet_type, ['chasse', 'enigme'], true)) {
        return 1;
    }

    if ($objet_type === 'chasse') {
        $meta_query = [
            [
                'key'     => 'indice_chasse_linked',
                'value'   => $objet_id,
                'compare' => '=',
            ],
            [
                'key'     => 'indice_cache_etat_systeme',
                'value'   => ['programme', 'accessible', 'desactive'],
                'compare' => 'IN',
            ],
        ];
    } else {
        $meta_query = [
            [
                'key'     => 'indice_cible_type',
                'value'   => 'enigme',
                'compare' => '=',
            ],
            [
                'key'     => 'indice_enigme_linked',
                'value'   => $objet_id,
                'compare' => '=',
            ],
            [
                'key'     => 'indice_cache_etat_systeme',
                'value'   => ['programme', 'accessible', 'desactive'],
                'compare' => 'IN',
            ],
        ];
    }

    $existing_indices = function_exists('get_posts')
        ? get_posts([
            'post_type'      => 'indice',
            'post_status'    => ['publish', 'pending', 'draft', 'private', 'future'],
            'meta_query'     => $meta_query,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'posts_per_page' => -1,
        ])
        : [];

    return count($existing_indices) + 1;
}

/**
 * Renomme les indices d'une chasse ou d'une énigme en séquence.
 *
 * @param int    $objet_id   ID de la chasse ou de l'énigme.
 * @param string $objet_type Type de cible ('chasse' ou 'enigme').
 * @return void
 */
function reordonner_indices(int $objet_id, string $objet_type): void
{
    static $processing = false;
    if (!in_array($objet_type, ['chasse', 'enigme'], true) || $processing) {
        return;
    }

    $processing = true;

    if ($objet_type === 'chasse') {
        $meta_query = [
            [
                'key'     => 'indice_chasse_linked',
                'value'   => $objet_id,
                'compare' => '=',
            ],
            [
                'key'     => 'indice_cache_etat_systeme',
                'value'   => ['programme', 'accessible', 'desactive'],
                'compare' => 'IN',
            ],
        ];
    } else {
        $meta_query = [
            [
                'key'     => 'indice_cible_type',
                'value'   => 'enigme',
                'compare' => '=',
            ],
            [
                'key'     => 'indice_enigme_linked',
                'value'   => $objet_id,
                'compare' => '=',
            ],
            [
                'key'     => 'indice_cache_etat_systeme',
                'value'   => ['programme', 'accessible', 'desactive'],
                'compare' => 'IN',
            ],
        ];
    }

    $indices = get_posts([
        'post_type'      => 'indice',
        'post_status'    => ['publish', 'pending', 'draft', 'private', 'future'],
        'meta_query'     => $meta_query,
        'orderby'        => 'date',
        'order'          => 'ASC',
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'posts_per_page' => -1,
    ]);

    $i = 1;
    foreach ($indices as $indice_id) {
        $current_title = get_post_field('post_title', $indice_id);
        $prefix        = defined('INDICE_DEFAULT_PREFIX') ? INDICE_DEFAULT_PREFIX : '';
        $should_update = (
            $current_title === ''
            || (defined('TITRE_DEFAUT_INDICE') && $current_title === TITRE_DEFAUT_INDICE)
            || preg_match('/^Indice #\d+$/', $current_title)
            || ($prefix !== '' && strpos($current_title, $prefix) === 0)
        );

        if ($should_update) {
            $chasse_linked = get_field('indice_chasse_linked', $indice_id);
            if (is_array($chasse_linked)) {
                $first     = $chasse_linked[0] ?? null;
                $chasse_id = is_array($first) ? (int) ($first['ID'] ?? 0) : (int) $first;
            } else {
                $chasse_id = (int) $chasse_linked;
            }

            if (!$chasse_id && $objet_type === 'chasse') {
                $chasse_id = $objet_id;
            }

            $placeholder = build_indice_placeholder_title($chasse_id);
            wp_update_post([
                'ID'         => $indice_id,
                'post_title' => $placeholder,
            ]);
        }

        update_post_meta($indice_id, 'indice_rank', $i);
        $i++;
    }

    $processing = false;
}

/**
 * Renomme les indices liés à l'indice donné.
 *
 * @param int $indice_id ID de l'indice.
 * @return void
 */
function reordonner_indices_pour_indice(int $indice_id): void
{
    $cible_type = get_field('indice_cible_type', $indice_id) === 'enigme' ? 'enigme' : 'chasse';

    if ($cible_type === 'enigme') {
        $linked = get_field('indice_enigme_linked', $indice_id);
        if (is_array($linked)) {
            $first     = $linked[0] ?? null;
            $objet_id = is_array($first) ? (int) ($first['ID'] ?? 0) : (int) $first;
        } else {
            $objet_id = (int) $linked;
        }

        if ($objet_id) {
            reordonner_indices($objet_id, 'enigme');
        }

        $chasse_linked = get_field('indice_chasse_linked', $indice_id);
        if (is_array($chasse_linked)) {
            $first     = $chasse_linked[0] ?? null;
            $chasse_id = is_array($first) ? (int) ($first['ID'] ?? 0) : (int) $first;
        } else {
            $chasse_id = (int) $chasse_linked;
        }

        if (!$chasse_id && isset($objet_id)) {
            $chasse_id = (int) recuperer_id_chasse_associee($objet_id);
        }

        if ($chasse_id) {
            reordonner_indices($chasse_id, 'chasse');
        }
    } else {
        $linked = get_field('indice_chasse_linked', $indice_id);
        if (is_array($linked)) {
            $first     = $linked[0] ?? null;
            $chasse_id = is_array($first) ? (int) ($first['ID'] ?? 0) : (int) $first;
        } else {
            $chasse_id = (int) $linked;
        }

        if ($chasse_id) {
            reordonner_indices($chasse_id, 'chasse');
        }
    }
}

/**
 * Réordonne les indices après sauvegarde d'un indice.
 *
 * @param int $post_id ID de l'indice.
 * @return void
 */
function reordonner_indices_apres_enregistrement(int $post_id): void
{
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    reordonner_indices_pour_indice($post_id);
}

add_action('save_post_indice', 'reordonner_indices_apres_enregistrement', 20, 1);

/**
 * Crée un indice lié à une chasse ou une énigme.
 *
 * @param int      $objet_id   ID de la chasse ou de l’énigme.
 * @param string   $objet_type Type de cible ('chasse' ou 'enigme').
 * @param int|null $user_id    ID utilisateur (null = courant).
 * @return int|WP_Error
 */
function creer_indice_pour_objet(int $objet_id, string $objet_type, ?int $user_id = null)
{
    if (!in_array($objet_type, ['chasse', 'enigme'], true)) {
        return new WP_Error('type_invalide', __('Type de cible invalide.', 'chassesautresor-com'));
    }

    if (get_post_type($objet_id) !== $objet_type) {
        return new WP_Error('cible_invalide', __('ID cible invalide.', 'chassesautresor-com'));
    }

    if (!is_user_logged_in()) {
        return new WP_Error('non_connecte', __('Utilisateur non connecté.', 'chassesautresor-com'));
    }

    if (!utilisateur_peut_modifier_post($objet_id)) {
        return new WP_Error('permission_refusee', __('Droits insuffisants.', 'chassesautresor-com'));
    }

    $chasse_id = $objet_type === 'chasse'
        ? $objet_id
        : recuperer_id_chasse_associee($objet_id);

    if (!$chasse_id || !utilisateur_peut_modifier_post($chasse_id)) {
        return new WP_Error('permission_refusee', __('Droits insuffisants.', 'chassesautresor-com'));
    }

    $user_id     = $user_id ?? get_current_user_id();
    $indice_rank   = prochain_rang_indice($chasse_id, 'chasse');
    $default_title = build_indice_placeholder_title($chasse_id);

    $indice_id = wp_insert_post([
        'post_type'   => 'indice',
        'post_status' => 'pending',
        'post_title'  => $default_title,
        'post_author' => $user_id,
    ]);

    if (is_wp_error($indice_id)) {
        return $indice_id;
    }

    update_post_meta($indice_id, 'indice_rank', $indice_rank);

    update_field('indice_cible_type', $objet_type, $indice_id);
    update_field('indice_chasse_linked', $chasse_id, $indice_id);
    if ($objet_type === 'enigme') {
        update_field('indice_enigme_linked', $objet_id, $indice_id);
    }
    update_field('indice_disponibilite', 'immediate', $indice_id);

    $date_disponibilite = wp_date('Y-m-d H:i:s', (int) current_time('timestamp') + DAY_IN_SECONDS);
    update_field('indice_date_disponibilite', $date_disponibilite, $indice_id);

    update_field('indice_cout_points', 0, $indice_id);
    update_field('indice_cache_complet', false, $indice_id);
    update_field('indice_cache_etat_systeme', 'desactive', $indice_id);

    reordonner_indices($objet_id, $objet_type);

    return $indice_id;
}

/**
 * Enregistre l’URL personnalisée /creer-indice/
 *
 * @return void
 */
function register_endpoint_creer_indice(): void
{
    add_rewrite_rule('^creer-indice/?$', 'index.php?creer_indice=1', 'top');
    add_rewrite_tag('%creer_indice%', '1');
}
add_action('init', 'register_endpoint_creer_indice');

/**
 * S'assure que les règles de réécriture prennent en compte /creer-indice/.
 *
 * Cette fonction est exécutée lors de l'activation du thème ou
 * automatiquement une fois si les règles n'ont pas encore été mises à jour.
 *
 * @return void
 */
function flush_rewrite_rules_creer_indice(): void
{
    register_endpoint_creer_indice();
    flush_rewrite_rules();
    update_option('creer_indice_rewrite_flushed', 1);
}

add_action('after_switch_theme', 'flush_rewrite_rules_creer_indice');

add_action('init', function (): void {
    if (!get_option('creer_indice_rewrite_flushed')) {
        flush_rewrite_rules_creer_indice();
    }
}, 20);

/**
 * Détecte l’appel à /creer-indice/ et redirige vers l’indice créé.
 *
 * @return void
 */
function creer_indice_et_rediriger_si_appel(): void
{
    if (get_query_var('creer_indice') !== '1') {
        return;
    }

    $nonce = $_GET['nonce'] ?? '';
    if (!wp_verify_nonce($nonce, 'creer_indice')) {
        wp_die(__('Action non autorisée.', 'chassesautresor-com'), 'Erreur', ['response' => 403]);
    }

    if (!is_user_logged_in()) {
        wp_redirect(wp_login_url());
        exit;
    }

    $cible_id = isset($_GET['chasse_id']) ? absint($_GET['chasse_id']) : 0;
    $cible_type = 'chasse';
    if (!$cible_id) {
        $cible_id = isset($_GET['enigme_id']) ? absint($_GET['enigme_id']) : 0;
        $cible_type = 'enigme';
    }

    if (!$cible_id) {
        wp_die(__('ID cible manquant.', 'chassesautresor-com'), 'Erreur', ['response' => 400]);
    }

    $indice_id = creer_indice_pour_objet($cible_id, $cible_type);
    if (is_wp_error($indice_id)) {
        $error_message = sanitize_text_field($indice_id->get_error_message());
        $referer       = wp_get_referer() ?: get_permalink($cible_id);
        $redirect_url  = add_query_arg('erreur', $error_message, $referer);
        wp_safe_redirect($redirect_url);
        exit;
    }

    wp_safe_redirect(get_permalink($cible_id));
    exit;
}
add_action('template_redirect', 'creer_indice_et_rediriger_si_appel');

/**
 * AJAX handler listing enigmas available for a hunt.
 *
 * @return void
 */
function ajax_chasse_lister_enigmes(): void
{
    if (!is_user_logged_in()) {
        wp_send_json_error('non_connecte');
    }

    $chasse_id = isset($_POST['chasse_id']) ? (int) $_POST['chasse_id'] : 0;

    if (!$chasse_id || get_post_type($chasse_id) !== 'chasse') {
        wp_send_json_error('post_invalide');
    }

    if (!indice_action_autorisee('create', 'chasse', $chasse_id)) {
        wp_send_json_error('acces_refuse');
    }

    $posts = recuperer_enigmes_pour_chasse($chasse_id);
    if (!empty($_POST['sans_solution'])) {
        $posts = array_filter(
            $posts,
            static fn($p) => !solution_existe_pour_objet($p->ID, 'enigme')
        );
    }

    $enigmes = array_map(
        static fn($p) => [
            'id'          => $p->ID,
            'title'       => html_entity_decode(get_the_title($p), ENT_QUOTES, 'UTF-8'),
            'indice_rang' => prochain_rang_indice($chasse_id, 'chasse'),
        ],
        $posts
    );

    wp_send_json_success(['enigmes' => $enigmes]);
}
add_action('wp_ajax_chasse_lister_enigmes', 'ajax_chasse_lister_enigmes');

/**
 * AJAX handler returning indices card HTML for a hunt.
 *
 * @return void
 */
function ajax_chasse_lister_indices(): void
{
    if (!is_user_logged_in()) {
        wp_send_json_error('non_connecte');
    }

    $chasse_id = isset($_POST['chasse_id']) ? (int) $_POST['chasse_id'] : 0;

    if (!$chasse_id || get_post_type($chasse_id) !== 'chasse') {
        wp_send_json_error('post_invalide');
    }

    if (!indice_action_autorisee('edit', 'chasse', $chasse_id)) {
        wp_send_json_error('acces_refuse');
    }

    ob_start();
    get_template_part(
        'template-parts/chasse/partials/chasse-partial-indices',
        null,
        [
            'objet_id'   => $chasse_id,
            'objet_type' => 'chasse',
        ]
    );
    $html = ob_get_clean();

    wp_send_json_success(['html' => $html]);
}
add_action('wp_ajax_chasse_lister_indices', 'ajax_chasse_lister_indices');

/**
 * AJAX handler returning indices table HTML.
 *
 * @return void
 */
function ajax_indices_lister_table(): void
{
    if (!is_user_logged_in()) {
        wp_send_json_error('non_connecte');
    }

    $objet_id   = isset($_POST['objet_id']) ? (int) $_POST['objet_id'] : 0;
    $objet_type = sanitize_key($_POST['objet_type'] ?? '');
    $page       = isset($_POST['page']) ? (int) $_POST['page'] : 1;
    $chasse_id  = isset($_POST['chasse_id']) ? (int) $_POST['chasse_id'] : 0;
    $enigme_id  = isset($_POST['enigme_id']) ? (int) $_POST['enigme_id'] : 0;

    if (!$objet_id || !in_array($objet_type, ['chasse', 'enigme'], true)
        || get_post_type($objet_id) !== $objet_type
    ) {
        wp_send_json_error('post_invalide');
    }

    if (!indice_action_autorisee('edit', $objet_type, $objet_id)) {
        wp_send_json_error('acces_refuse');
    }

    if ($objet_type === 'enigme') {
        $enigme_id = $objet_id;
        if (!$chasse_id) {
            $chasse_id = (int) recuperer_id_chasse_associee($enigme_id);
        }
    } elseif ($objet_type === 'chasse') {
        $chasse_id = $objet_id;
    }

    $per_page = $objet_type === 'chasse' ? 5 : 8;
    if ($objet_type === 'chasse') {
        $enigme_ids = recuperer_ids_enigmes_pour_chasse($objet_id);
        $meta       = [
            'relation' => 'OR',
            [
                'relation' => 'AND',
                [
                    'key'   => 'indice_cible_type',
                    'value' => 'chasse',
                ],
                [
                    'key'   => 'indice_chasse_linked',
                    'value' => $objet_id,
                ],
            ],
        ];
        if (!empty($enigme_ids)) {
            $meta[] = [
                'relation' => 'AND',
                [
                    'key'   => 'indice_cible_type',
                    'value' => 'enigme',
                ],
                [
                    'key'     => 'indice_enigme_linked',
                    'value'   => $enigme_ids,
                    'compare' => 'IN',
                ],
            ];
        }
    } else {
        $meta = [
            [
                'key'   => 'indice_cible_type',
                'value' => 'enigme',
            ],
            [
                'key'   => 'indice_enigme_linked',
                'value' => $objet_id,
            ],
        ];
    }

    $page = max(1, $page);

    $ids = [];
    if (function_exists('get_posts')) {
        $ids = get_posts([
            'post_type'   => 'indice',
            'post_status' => ['publish', 'pending', 'draft'],
            'fields'      => 'ids',
            'nopaging'    => true,
            'meta_query'  => $meta,
        ]);
    }

    $count_total = is_countable($ids) ? count($ids) : 0;
    $total_pages = (int) ceil($count_total / $per_page);
    if ($total_pages > 0 && $page > $total_pages) {
        $page = $total_pages;
    }

    $query_args = [
        'post_type'      => 'indice',
        'post_status'    => ['publish', 'pending', 'draft'],
        'orderby'        => 'date',
        'order'          => 'DESC',
        'posts_per_page' => $per_page,
        'paged'          => $page,
        'meta_query'     => $meta,
    ];
    $query      = new WP_Query($query_args);

    $count_chasse = 0;
    $count_enigme = 0;
    if (function_exists('get_post_meta')) {
        foreach ($ids as $indice_id) {
            $type = get_post_meta($indice_id, 'indice_cible_type', true);
            if ($type === 'chasse') {
                ++$count_chasse;
            } elseif ($type === 'enigme') {
                ++$count_enigme;
            }
        }
        $count_total = $count_chasse + $count_enigme;
    }

    $has_enigme_indices = false;
    if ($enigme_id) {
        $has_enigme_indices = function_exists('get_posts') ? count(get_posts([
            'post_type'   => 'indice',
            'post_status' => ['publish', 'pending', 'draft'],
            'fields'      => 'ids',
            'nopaging'    => true,
            'meta_query'  => [
                [
                    'key'   => 'indice_cible_type',
                    'value' => 'enigme',
                ],
                [
                    'key'   => 'indice_enigme_linked',
                    'value' => $enigme_id,
                ],
            ],
        ])) > 0 : false;
    }

    $toggle_args = null;
    if ($has_enigme_indices && $chasse_id && $enigme_id) {
        $toggle_args = [
            'chasse_id' => $chasse_id,
            'enigme_id' => $enigme_id,
            'label'     => $objet_type === 'enigme'
                ? __('Voir tous les indices de la chasse', 'chassesautresor-com')
                : __('Voir les indices de cette énigme', 'chassesautresor-com'),
        ];
    }

    ob_start();
    get_template_part('template-parts/common/indices-table', null, [
        'indices'      => $query->posts,
        'page'         => $page,
        'pages'        => $total_pages,
        'objet_type'   => $objet_type,
        'objet_id'     => $objet_id,
        'count_total'  => $count_total,
        'count_chasse' => $count_chasse,
        'count_enigme' => $count_enigme,
        'toggle'       => $toggle_args,
    ]);
    $html = ob_get_clean();

    wp_send_json_success([
        'html'  => $html,
        'page'  => $page,
        'pages' => $total_pages,
    ]);
}
add_action('wp_ajax_indices_lister_table', 'ajax_indices_lister_table');

/**
 * Crée un indice via une requête AJAX depuis une modale.
 *
 * @return void
 */
function ajax_creer_indice_modal(): void
{
    if (!is_user_logged_in()) {
        wp_send_json_error('non_connecte');
    }

    $objet_id   = isset($_POST['objet_id']) ? (int) $_POST['objet_id'] : 0;
    $objet_type = sanitize_key($_POST['objet_type'] ?? '');

    if (!$objet_id || !in_array($objet_type, ['chasse', 'enigme'], true) || get_post_type($objet_id) !== $objet_type) {
        wp_send_json_error('post_invalide');
    }

    if ($objet_type === 'enigme') {
        $linked = isset($_POST['indice_enigme_linked']) ? (int) $_POST['indice_enigme_linked'] : 0;
        if (!$linked || $linked !== $objet_id) {
            wp_send_json_error('post_invalide');
        }
    }

    if (!indice_action_autorisee('create', $objet_type, $objet_id)) {
        wp_send_json_error('acces_refuse');
    }

    $indice_id = creer_indice_pour_objet($objet_id, $objet_type);
    if (is_wp_error($indice_id)) {
        wp_send_json_error($indice_id->get_error_message());
    }

    $image   = isset($_POST['indice_image']) ? (int) $_POST['indice_image'] : 0;
    $contenu = wp_kses_post($_POST['indice_contenu'] ?? '');
    $dispo   = sanitize_key($_POST['indice_disponibilite'] ?? 'immediate');
    $date    = sanitize_text_field($_POST['indice_date_disponibilite'] ?? '');

    if ($image) {
        update_field('indice_image', $image, $indice_id);
    }
    if ($contenu !== '') {
        update_field('indice_contenu', $contenu, $indice_id);
    }

    $dispo = $dispo === 'differe' ? 'differe' : 'immediate';
    update_field('indice_disponibilite', $dispo, $indice_id);

    $date_to_save = $date ?: get_field('indice_date_disponibilite', $indice_id);
    if (!$date_to_save) {
        $date_to_save = wp_date('Y-m-d H:i:s', (int) current_time('timestamp'));
    }
    update_field('indice_date_disponibilite', $date_to_save, $indice_id);

    mettre_a_jour_cache_indice($indice_id);

    wp_send_json_success(['indice_id' => $indice_id]);
}
add_action('wp_ajax_creer_indice_modal', 'ajax_creer_indice_modal');

/**
 * Met à jour un indice existant via le modal d'édition.
 *
 * @return void
 */
function ajax_modifier_indice_modal(): void
{
    if (!is_user_logged_in()) {
        wp_send_json_error('non_connecte');
    }

    $indice_id  = isset($_POST['indice_id']) ? (int) $_POST['indice_id'] : 0;
    $objet_id   = isset($_POST['objet_id']) ? (int) $_POST['objet_id'] : 0;
    $objet_type = sanitize_key($_POST['objet_type'] ?? '');

    if (!$indice_id || get_post_type($indice_id) !== 'indice') {
        wp_send_json_error('indice_invalide');
    }
    if (!$objet_id || !in_array($objet_type, ['chasse', 'enigme'], true) || get_post_type($objet_id) !== $objet_type) {
        wp_send_json_error('post_invalide');
    }
    if (!indice_action_autorisee('edit', $objet_type, $objet_id)) {
        wp_send_json_error('acces_refuse');
    }

    $image   = isset($_POST['indice_image']) ? (int) $_POST['indice_image'] : 0;
    $contenu = wp_kses_post($_POST['indice_contenu'] ?? '');
    $dispo   = sanitize_key($_POST['indice_disponibilite'] ?? 'immediate');
    $date    = sanitize_text_field($_POST['indice_date_disponibilite'] ?? '');

    if ($image) {
        update_field('indice_image', $image, $indice_id);
    } else {
        delete_field('indice_image', $indice_id);
    }
    update_field('indice_contenu', $contenu, $indice_id);

    $dispo = $dispo === 'differe' ? 'differe' : 'immediate';
    update_field('indice_disponibilite', $dispo, $indice_id);

    $date_to_save = $date ?: get_field('indice_date_disponibilite', $indice_id);
    if (!$date_to_save) {
        $date_to_save = wp_date('Y-m-d H:i:s', (int) current_time('timestamp'));
    }
    update_field('indice_date_disponibilite', $date_to_save, $indice_id);

    mettre_a_jour_cache_indice($indice_id);

    wp_send_json_success(['indice_id' => $indice_id]);
}
add_action('wp_ajax_modifier_indice_modal', 'ajax_modifier_indice_modal');

/**
 * Gère l’enregistrement AJAX des champs ACF ou natifs du CPT indice.
 *
 * @hook wp_ajax_modifier_champ_indice
 * @return void
 */
function modifier_champ_indice(): void
{
    if (!is_user_logged_in()) {
        wp_send_json_error('non_connecte');
    }

    $champ   = sanitize_text_field($_POST['champ'] ?? '');
    $valeur  = $_POST['valeur'] ?? '';
    $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;

    if (!$champ || !$post_id || get_post_type($post_id) !== 'indice') {
        wp_send_json_error('⚠️ donnees_invalides');
    }

    if (!utilisateur_peut_modifier_post($post_id) || !utilisateur_peut_editer_champs($post_id)) {
        wp_send_json_error('⚠️ acces_refuse');
    }

    $champ_valide = false;
    $reponse      = ['champ' => $champ, 'valeur' => $valeur];

    if ($champ === 'post_title') {
        $ok = wp_update_post(['ID' => $post_id, 'post_title' => sanitize_text_field($valeur)], true);
        if (is_wp_error($ok)) {
            wp_send_json_error('⚠️ echec_update_post_title');
        }
        wp_send_json_success($reponse);
    }

    switch ($champ) {
        case 'indice_image':
            $champ_valide = update_field('indice_image', (int) $valeur, $post_id) !== false;
            break;
        case 'indice_contenu':
            $champ_valide = update_field('indice_contenu', wp_kses_post($valeur), $post_id) !== false;
            break;
        case 'indice_cible_type':
            $val = $valeur === 'enigme' ? 'enigme' : 'chasse';
            $champ_valide = update_field('indice_cible_type', $val, $post_id) !== false;
            break;
        case 'indice_enigme_linked':
            $ids = array_filter(array_map('intval', explode(',', (string) $valeur)));
            $champ_valide = update_field('indice_enigme_linked', $ids, $post_id) !== false;
            break;
        case 'indice_disponibilite':
            $val = $valeur === 'differe' ? 'differe' : 'immediate';
            $champ_valide = update_field('indice_disponibilite', $val, $post_id) !== false;
            break;
        case 'indice_date_disponibilite':
            $dt = convertir_en_datetime(sanitize_text_field($valeur), ['Y-m-d\TH:i', 'Y-m-d H:i:s', 'Y-m-d H:i']);
            if (!$dt) {
                wp_send_json_error('⚠️ format_date_invalide');
            }
            $champ_valide = update_field('indice_date_disponibilite', $dt->format('Y-m-d H:i:s'), $post_id) !== false;
            break;
        case 'indice_cout_points':
            $champ_valide = update_field('indice_cout_points', (int) $valeur, $post_id) !== false;
            break;
        default:
            wp_send_json_error('⚠️ champ_inconnu');
    }

    if ($champ_valide) {
        if (in_array($champ, ['indice_image', 'indice_contenu', 'indice_disponibilite', 'indice_date_disponibilite'], true)) {
            mettre_a_jour_cache_indice($post_id);
        }

        wp_send_json_success($reponse);
    }

    wp_send_json_error('⚠️ echec_mise_a_jour');
}
add_action('wp_ajax_modifier_champ_indice', 'modifier_champ_indice');

/**
 * Supprime un indice via requête AJAX.
 *
 * @hook wp_ajax_supprimer_indice
 * @return void
 */
function supprimer_indice_ajax(): void
{
    if (!is_user_logged_in()) {
        wp_send_json_error('non_connecte');
    }

    $indice_id = isset($_POST['indice_id']) ? (int) $_POST['indice_id'] : 0;
    if (!$indice_id || get_post_type($indice_id) !== 'indice') {
        wp_send_json_error('id_invalide');
    }

    $cible_type = get_field('indice_cible_type', $indice_id) === 'enigme' ? 'enigme' : 'chasse';
    $chasse_id  = 0;
    if ($cible_type === 'enigme') {
        $linked = get_field('indice_enigme_linked', $indice_id);
        if (is_array($linked)) {
            $first    = $linked[0] ?? null;
            $objet_id = is_array($first) ? (int) ($first['ID'] ?? 0) : (int) $first;
        } else {
            $objet_id = (int) $linked;
        }

        $chasse_linked = get_field('indice_chasse_linked', $indice_id);
        if (is_array($chasse_linked)) {
            $first     = $chasse_linked[0] ?? null;
            $chasse_id = is_array($first) ? (int) ($first['ID'] ?? 0) : (int) $first;
        } else {
            $chasse_id = (int) $chasse_linked;
        }

        if (!$chasse_id && $objet_id) {
            $chasse_id = (int) recuperer_id_chasse_associee($objet_id);
        }
    } else {
        $linked = get_field('indice_chasse_linked', $indice_id);
        if (is_array($linked)) {
            $first    = $linked[0] ?? null;
            $objet_id = is_array($first) ? (int) ($first['ID'] ?? 0) : (int) $first;
        } else {
            $objet_id = (int) $linked;
        }
    }

    if (!$objet_id || !indice_action_autorisee('delete', $cible_type, $objet_id)) {
        wp_send_json_error('acces_refuse');
    }

    $deleted = wp_delete_post($indice_id, true);
    if (!$deleted) {
        wp_send_json_error('echec_suppression');
    }

    reordonner_indices($objet_id, $cible_type);
    if ($cible_type === 'enigme' && $chasse_id) {
        reordonner_indices($chasse_id, 'chasse');
    }

    wp_send_json_success();
}
add_action('wp_ajax_supprimer_indice', 'supprimer_indice_ajax');

/**
 * Pré-remplit automatiquement la chasse liée d'un indice lors de sa création.
 *
 * @param array $field Paramètres du champ ACF.
 * @return array Champ modifié.
 */
function pre_remplir_indice_chasse_linked(array $field): array
{
    global $post;

    if (!$post || get_post_type($post->ID) !== 'indice') {
        return $field;
    }

    $existing = get_post_meta($post->ID, 'indice_chasse_linked', true);
    if (!empty($existing)) {
        return $field;
    }

    $chasse_id = null;
    $cible_type = get_field('indice_cible_type', $post->ID);
    $enigme_id  = get_field('indice_enigme_linked', $post->ID);

    if ($cible_type === 'enigme' && $enigme_id) {
        $chasse_id = recuperer_id_chasse_associee((int) $enigme_id);
    } elseif (isset($_GET['chasse_id'])) {
        $chasse_id = (int) $_GET['chasse_id'];
    }

    if ($chasse_id) {
        $field['value'] = $chasse_id;
    }

    return $field;
}
add_filter('acf/load_field/name=indice_chasse_linked', 'pre_remplir_indice_chasse_linked');

/**
 * Sauvegarde la chasse liée si le champ est vide lors de l'enregistrement.
 *
 * @hook acf/save_post
 *
 * @param int|string $post_id ID du post ACF.
 * @return void
 */
function sauvegarder_indice_chasse_si_manquant($post_id): void
{
    if (!is_numeric($post_id) || get_post_type((int) $post_id) !== 'indice') {
        return;
    }

    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    $chasse = get_field('indice_chasse_linked', $post_id);
    if ($chasse) {
        return;
    }

    $chasse_id  = null;
    $cible_type = get_field('indice_cible_type', $post_id);
    $enigme_id  = get_field('indice_enigme_linked', $post_id);

    if ($cible_type === 'enigme' && $enigme_id) {
        $chasse_id = recuperer_id_chasse_associee((int) $enigme_id);
    } elseif ($cible_type === 'chasse' && isset($_GET['chasse_id'])) {
        $chasse_id = (int) $_GET['chasse_id'];
    } elseif (isset($_GET['chasse_id'])) {
        $chasse_id = (int) $_GET['chasse_id'];
    }

    if ($chasse_id) {
        update_field('indice_chasse_linked', $chasse_id, $post_id);
    }
}
add_action('acf/save_post', 'sauvegarder_indice_chasse_si_manquant', 20);

/**
 * Met à jour les champs de cache d'un indice.
 *
 * @param int|string $post_id  ID du post ACF.
 * @param int|null   $chasse_id ID de la chasse si connu.
 * @return void
 */
function mettre_a_jour_cache_indice($post_id, ?int $chasse_id = null): void
{
    if (!is_numeric($post_id) || get_post_type((int) $post_id) !== 'indice') {
        return;
    }

    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    $chasse_linked = get_field('indice_chasse_linked', $post_id);
    if (!$chasse_linked) {
        $cible_type = get_field('indice_cible_type', $post_id);
        $enigme_id  = get_field('indice_enigme_linked', $post_id);

        if ($cible_type === 'enigme' && $enigme_id) {
            $chasse_linked = recuperer_id_chasse_associee((int) $enigme_id);
        } elseif ($chasse_id !== null) {
            $chasse_linked = $chasse_id;
        }

        if ($chasse_linked) {
            update_field('indice_chasse_linked', $chasse_linked, $post_id);
        }
    }

    $content  = trim((string) get_field('indice_contenu', $post_id));
    $image_id = get_field('indice_image', $post_id);

    $complete = $content !== '' || !empty($image_id);
    $state    = 'desactive';

    if ($complete) {
        $state        = 'accessible';
        $availability = get_field('indice_disponibilite', $post_id);

        if ($availability === 'differe') {
            $date_raw = get_field('indice_date_disponibilite', $post_id);
            $date     = $date_raw ? convertir_en_datetime($date_raw) : null;

            if (!$date) {
                $complete = false;
                $state    = 'desactive';
            } elseif ($date->getTimestamp() > time()) {
                $state = 'programme';
            }
        }
    }

    update_field('indice_cache_complet', $complete ? 1 : 0, $post_id);
    update_field('indice_cache_etat_systeme', $state, $post_id);

    $status = get_post_status($post_id);
    $post   = get_post($post_id);

    if ($complete && $state === 'accessible') {
        if ($status !== 'publish') {
            wp_update_post([
                'ID'            => $post_id,
                'post_status'   => 'publish',
                'post_date'     => $post->post_date,
                'post_date_gmt' => $post->post_date_gmt,
                'edit_date'     => true,
            ]);
        }
    } elseif ($status === 'publish') {
        wp_update_post([
            'ID'            => $post_id,
            'post_status'   => 'pending',
            'post_date'     => $post->post_date,
            'post_date_gmt' => $post->post_date_gmt,
            'edit_date'     => true,
        ]);
    }

    reordonner_indices_pour_indice((int) $post_id);
}

add_action('acf/save_post', 'mettre_a_jour_cache_indice', 30);

/**
 * Met à jour les indices programmés dont la date est passée.
 *
 * @return void
 */
function basculer_indices_programmes(): void
{
    $indices = get_posts([
        'post_type'      => 'indice',
        'post_status'    => ['publish', 'pending', 'draft', 'private', 'future'],
        'meta_query'     => [
            [
                'key'   => 'indice_cache_etat_systeme',
                'value' => 'programme',
            ],
            [
                'key'     => 'indice_date_disponibilite',
                'value'   => current_time('mysql'),
                'compare' => '<=',
                'type'    => 'DATETIME',
            ],
        ],
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'posts_per_page' => -1,
    ]);

    foreach ($indices as $indice_id) {
        mettre_a_jour_cache_indice($indice_id);
    }
}
add_action('basculer_indices_programmes', 'basculer_indices_programmes');

/**
 * Planifie l'exécution régulière de la tâche de basculement des indices.
 *
 * @return void
 */
function planifier_tache_basculer_indices_programmes(): void
{
    if (!wp_next_scheduled('basculer_indices_programmes')) {
        wp_schedule_event(time(), 'hourly', 'basculer_indices_programmes');
    }
}
add_action('after_switch_theme', 'planifier_tache_basculer_indices_programmes');

/**
 * Enregistre la cible d'un indice avant suppression définitive.
 *
 * @param int $post_id ID du post.
 * @return void
 */
function memoriser_cible_indice_avant_suppression(int $post_id): void
{
    global $indice_delete_context;

    if (get_post_type($post_id) !== 'indice') {
        return;
    }

    $type = get_field('indice_cible_type', $post_id) === 'enigme' ? 'enigme' : 'chasse';
    $objet_id = 0;
    $chasse_id = 0;

    if ($type === 'enigme') {
        $linked = get_field('indice_enigme_linked', $post_id);
        if (is_array($linked)) {
            $first    = $linked[0] ?? null;
            $objet_id = is_array($first) ? (int) ($first['ID'] ?? 0) : (int) $first;
        } else {
            $objet_id = (int) $linked;
        }

        $chasse_linked = get_field('indice_chasse_linked', $post_id);
        if (is_array($chasse_linked)) {
            $first     = $chasse_linked[0] ?? null;
            $chasse_id = is_array($first) ? (int) ($first['ID'] ?? 0) : (int) $first;
        } else {
            $chasse_id = (int) $chasse_linked;
        }

        if (!$chasse_id && $objet_id) {
            $chasse_id = (int) recuperer_id_chasse_associee($objet_id);
        }
    } else {
        $linked = get_field('indice_chasse_linked', $post_id);
        if (is_array($linked)) {
            $first     = $linked[0] ?? null;
            $objet_id  = is_array($first) ? (int) ($first['ID'] ?? 0) : (int) $first;
        } else {
            $objet_id = (int) $linked;
        }
        $chasse_id = $objet_id;
    }

    if ($objet_id) {
        $indice_delete_context = [
            'objet_id'   => $objet_id,
            'objet_type' => $type,
            'chasse_id'  => $chasse_id,
        ];
    }
}
add_action('before_delete_post', 'memoriser_cible_indice_avant_suppression');

/**
 * Réordonne les indices après suppression définitive.
 *
 * @param int $post_id ID du post.
 * @return void
 */
function reordonner_indices_apres_suppression(int $post_id): void
{
    global $indice_delete_context;
    if (!$indice_delete_context) {
        return;
    }

    $objet_id   = (int) $indice_delete_context['objet_id'];
    $objet_type = $indice_delete_context['objet_type'];
    $chasse_id  = (int) ($indice_delete_context['chasse_id'] ?? 0);

    reordonner_indices($objet_id, $objet_type);
    if ($chasse_id && $chasse_id !== $objet_id) {
        reordonner_indices($chasse_id, 'chasse');
    }

    $indice_delete_context = null;
}
add_action('deleted_post', 'reordonner_indices_apres_suppression');
add_action('trashed_post', 'reordonner_indices_pour_indice');
