<?php
defined('ABSPATH') || exit;

if (!class_exists(ChassesAuTresor\Core\Content\HintQueryService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/HintQueryService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\HintStatusService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/HintStatusService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\HintScheduler::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/HintScheduler.php';
}

if (!class_exists(ChassesAuTresor\Core\Relationships\RelationshipService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Relationships/RelationshipService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\HintTitleService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/HintTitleService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\HintCreationService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/HintCreationService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\HintRouteRegistrar::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/HintRouteRegistrar.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\HintFieldPolicyService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/HintFieldPolicyService.php';
}

function cat_get_hint_query_service(): ChassesAuTresor\Core\Content\HintQueryService
{
    return new ChassesAuTresor\Core\Content\HintQueryService();
}

function cat_get_hint_status_service(): ChassesAuTresor\Core\Content\HintStatusService
{
    return new ChassesAuTresor\Core\Content\HintStatusService();
}

function cat_get_hint_title_service(): ChassesAuTresor\Core\Content\HintTitleService
{
    return new ChassesAuTresor\Core\Content\HintTitleService();
}

function cat_get_hint_creation_service(): ChassesAuTresor\Core\Content\HintCreationService
{
    return new ChassesAuTresor\Core\Content\HintCreationService();
}

function cat_get_hint_field_policy_service(): ChassesAuTresor\Core\Content\HintFieldPolicyService
{
    return new ChassesAuTresor\Core\Content\HintFieldPolicyService();
}

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
    $slug = (string) get_post_field('post_name', $chasse_id);
    $generatedSlug = '';

    if ($slug === '') {
        $chasseName = (string) get_post_field('post_title', $chasse_id);
        $generatedSlug = $chasseName !== ''
            ? (function_exists('sanitize_title')
                ? sanitize_title($chasseName)
                : strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $chasseName), '-')))
            : '';
    }

    return cat_get_hint_title_service()->buildPlaceholder($prefix, $slug, $generatedSlug);
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

    $indice_id = get_the_ID();
    $redirect_id = (new ChassesAuTresor\Core\Relationships\RelationshipService())->resolveHintTargetId(
        (string) get_field('indice_cible_type', $indice_id),
        get_field('indice_chasse_linked', $indice_id),
        get_field('indice_enigme_linked', $indice_id)
    );

    if ($redirect_id !== null) {
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
    $queryArgs = cat_get_hint_query_service()->getRankedHintIdsQueryArgs($objet_id, $objet_type);
    if ($queryArgs === []) {
        return 1;
    }

    $existing_indices = function_exists('get_posts')
        ? get_posts($queryArgs)
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
    $queryArgs = cat_get_hint_query_service()->getRankedHintIdsQueryArgs($objet_id, $objet_type, true);
    if ($queryArgs === [] || $processing) {
        return;
    }

    $processing = true;
    $indices = get_posts($queryArgs);

    $i = 1;
    foreach ($indices as $indice_id) {
        $current_title = get_post_field('post_title', $indice_id);
        $prefix        = defined('INDICE_DEFAULT_PREFIX') ? INDICE_DEFAULT_PREFIX : '';
        $should_update = cat_get_hint_title_service()->shouldRegenerate(
            (string) $current_title,
            defined('TITRE_DEFAUT_INDICE') ? TITRE_DEFAUT_INDICE : '',
            $prefix
        );

        if ($should_update) {
            $relationshipService = new ChassesAuTresor\Core\Relationships\RelationshipService();
            $chasse_id = $relationshipService->normalizeId(
                get_field('indice_chasse_linked', $indice_id)
            );

            if ($chasse_id === null && $objet_type === 'chasse') {
                $chasse_id = $objet_id;
            }

            $placeholder = build_indice_placeholder_title($chasse_id ?? 0);
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
    $relationshipService = new ChassesAuTresor\Core\Relationships\RelationshipService();

    if ($cible_type === 'enigme') {
        $objet_id = $relationshipService->normalizeId(get_field('indice_enigme_linked', $indice_id));

        if ($objet_id !== null) {
            reordonner_indices($objet_id, 'enigme');
        }

        $chasse_id = $relationshipService->normalizeId(get_field('indice_chasse_linked', $indice_id));

        if ($chasse_id === null && $objet_id !== null) {
            $chasse_id = $relationshipService->normalizeId(recuperer_id_chasse_associee($objet_id));
        }

        if ($chasse_id !== null) {
            reordonner_indices($chasse_id, 'chasse');
        }
    } else {
        $chasse_id = $relationshipService->normalizeId(get_field('indice_chasse_linked', $indice_id));

        if ($chasse_id !== null) {
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
    $creationService = cat_get_hint_creation_service();
    $supportedTargetType = $creationService->isSupportedTargetType($objet_type);
    $targetMatchesType = $supportedTargetType && get_post_type($objet_id) === $objet_type;
    $isAuthenticated = is_user_logged_in();
    $canModifyTarget = $targetMatchesType
        && $isAuthenticated
        && utilisateur_peut_modifier_post($objet_id);
    $chasse_id = null;

    if ($canModifyTarget) {
        $chasse_id = $objet_type === 'chasse'
            ? $objet_id
            : recuperer_id_chasse_associee($objet_id);
    }

    $canModifyHunt = $chasse_id !== null
        && (int) $chasse_id > 0
        && utilisateur_peut_modifier_post((int) $chasse_id);
    $creationError = $creationService->getCreationError(
        $supportedTargetType,
        $targetMatchesType,
        $isAuthenticated,
        $canModifyTarget,
        $chasse_id !== null && (int) $chasse_id > 0,
        $canModifyHunt
    );

    if ($creationError !== null) {
        $errorMessages = [
            'type_invalide' => __('Type de cible invalide.', 'chassesautresor-com'),
            'cible_invalide' => __('ID cible invalide.', 'chassesautresor-com'),
            'non_connecte' => __('Utilisateur non connecté.', 'chassesautresor-com'),
            'permission_refusee' => __('Droits insuffisants.', 'chassesautresor-com'),
        ];

        return new WP_Error($creationError, $errorMessages[$creationError]);
    }

    $chasse_id = (int) $chasse_id;

    $user_id     = $user_id ?? get_current_user_id();
    $indice_rank   = prochain_rang_indice($chasse_id, 'chasse');
    $default_title = build_indice_placeholder_title($chasse_id);
    $initialState = $creationService->getInitialState(
        (int) current_time('timestamp'),
        DAY_IN_SECONDS
    );

    $indice_id = wp_insert_post([
        'post_type'   => 'indice',
        'post_status' => $initialState['post_status'],
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
    update_field('indice_disponibilite', $initialState['availability'], $indice_id);

    $date_disponibilite = wp_date('Y-m-d H:i:s', $initialState['availability_timestamp']);
    update_field('indice_date_disponibilite', $date_disponibilite, $indice_id);

    update_field('indice_cout_points', $initialState['points_cost'], $indice_id);
    update_field('indice_cache_complet', $initialState['complete'], $indice_id);
    update_field('indice_cache_etat_systeme', $initialState['system_state'], $indice_id);

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
    ChassesAuTresor\Core\Content\HintRouteRegistrar::register();
}

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
    ChassesAuTresor\Core\Content\HintRouteRegistrar::flush();
}

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
    $enigme_ids = $objet_type === 'chasse' ? recuperer_ids_enigmes_pour_chasse($objet_id) : [];
    $query_service = cat_get_hint_query_service();

    $page = max(1, $page);

    $ids = [];
    if (function_exists('get_posts')) {
        $ids = get_posts($query_service->getManagementTableQueryArgs(
            $objet_id,
            $objet_type,
            $enigme_ids,
            $page,
            $per_page,
            true
        ));
    }

    $count_total = is_countable($ids) ? count($ids) : 0;
    $total_pages = (int) ceil($count_total / $per_page);
    if ($total_pages > 0 && $page > $total_pages) {
        $page = $total_pages;
    }

    $query_args = $query_service->getManagementTableQueryArgs(
        $objet_id,
        $objet_type,
        $enigme_ids,
        $page,
        $per_page
    );
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
        $riddle_hint_query = $query_service->getManagementTableQueryArgs(
            $enigme_id,
            'enigme',
            [],
            1,
            1,
            true
        );
        $has_enigme_indices = function_exists('get_posts')
            ? count(get_posts($riddle_hint_query)) > 0
            : false;
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

    $linkedRiddleId = isset($_POST['indice_enigme_linked']) ? (int) $_POST['indice_enigme_linked'] : 0;
    if (!cat_get_hint_creation_service()->hasConsistentRiddleTarget(
        $objet_type,
        $objet_id,
        $linkedRiddleId
    )) {
        wp_send_json_error('post_invalide');
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

    $dispo = cat_get_hint_status_service()->normalizeAvailability($dispo);
    update_field('indice_disponibilite', $dispo, $indice_id);

    $date_to_save = cat_get_hint_status_service()->resolveAvailabilityDate(
        $date,
        (string) get_field('indice_date_disponibilite', $indice_id),
        wp_date('Y-m-d H:i:s', (int) current_time('timestamp'))
    );
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

    $dispo = cat_get_hint_status_service()->normalizeAvailability($dispo);
    update_field('indice_disponibilite', $dispo, $indice_id);

    $date_to_save = cat_get_hint_status_service()->resolveAvailabilityDate(
        $date,
        (string) get_field('indice_date_disponibilite', $indice_id),
        wp_date('Y-m-d H:i:s', (int) current_time('timestamp'))
    );
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

    $fieldPolicy = cat_get_hint_field_policy_service();
    if (!$fieldPolicy->isEditable($champ)) {
        wp_send_json_error('⚠️ champ_inconnu');
    }

    switch ($champ) {
        case 'indice_image':
            $champ_valide = update_field('indice_image', (int) $valeur, $post_id) !== false;
            break;
        case 'indice_contenu':
            $champ_valide = update_field('indice_contenu', wp_kses_post($valeur), $post_id) !== false;
            break;
        case 'indice_cible_type':
            $val = $fieldPolicy->normalizeTargetType((string) $valeur);
            $champ_valide = update_field('indice_cible_type', $val, $post_id) !== false;
            break;
        case 'indice_enigme_linked':
            $ids = $fieldPolicy->normalizeRiddleIds((string) $valeur);
            $champ_valide = update_field('indice_enigme_linked', $ids, $post_id) !== false;
            break;
        case 'indice_disponibilite':
            $val = cat_get_hint_status_service()->normalizeAvailability((string) $valeur);
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
    }

    if ($champ_valide) {
        if ($fieldPolicy->requiresCacheRefresh($champ)) {
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
    $relationshipService = new ChassesAuTresor\Core\Relationships\RelationshipService();
    $linked_hunt = get_field('indice_chasse_linked', $indice_id);
    $linked_riddle = get_field('indice_enigme_linked', $indice_id);
    $objet_id = $relationshipService->resolveHintTargetId($cible_type, $linked_hunt, $linked_riddle);
    $riddle_hunt_id = $relationshipService->normalizeId($linked_hunt);
    if ($cible_type === 'enigme' && $riddle_hunt_id === null && $objet_id !== null) {
        $riddle_hunt_id = recuperer_id_chasse_associee($objet_id);
    }
    $chasse_id = $relationshipService->resolveTargetHuntId($cible_type, $linked_hunt, $riddle_hunt_id);

    if ($objet_id === null || !indice_action_autorisee('delete', $cible_type, $objet_id)) {
        wp_send_json_error('acces_refuse');
    }

    $deleted = wp_delete_post($indice_id, true);
    if (!$deleted) {
        wp_send_json_error('echec_suppression');
    }

    reordonner_indices($objet_id, $cible_type);
    if ($cible_type === 'enigme' && $chasse_id !== null) {
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

    $cible_type = (string) get_field('indice_cible_type', $post->ID);
    $relationshipService = new ChassesAuTresor\Core\Relationships\RelationshipService();
    $enigme_id = $relationshipService->resolveHintTargetId(
        'enigme',
        null,
        get_field('indice_enigme_linked', $post->ID)
    );
    $riddleHuntId = $cible_type === 'enigme' && $enigme_id !== null
        ? recuperer_id_chasse_associee($enigme_id)
        : null;
    $requestedHuntId = isset($_GET['chasse_id']) ? (int) $_GET['chasse_id'] : null;
    $chasse_id = cat_get_hint_creation_service()->resolveLinkedHuntId(
        $cible_type,
        $riddleHuntId,
        $requestedHuntId
    );

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

    $cible_type = (string) get_field('indice_cible_type', $post_id);
    $relationshipService = new ChassesAuTresor\Core\Relationships\RelationshipService();
    $enigme_id = $relationshipService->resolveHintTargetId(
        'enigme',
        null,
        get_field('indice_enigme_linked', $post_id)
    );
    $riddleHuntId = $cible_type === 'enigme' && $enigme_id !== null
        ? recuperer_id_chasse_associee($enigme_id)
        : null;
    $requestedHuntId = isset($_GET['chasse_id']) ? (int) $_GET['chasse_id'] : null;
    $chasse_id = cat_get_hint_creation_service()->resolveLinkedHuntId(
        $cible_type,
        $riddleHuntId,
        $requestedHuntId
    );

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

    $relationshipService = new ChassesAuTresor\Core\Relationships\RelationshipService();
    $chasse_linked = $relationshipService->normalizeId(get_field('indice_chasse_linked', $post_id));
    if ($chasse_linked === null) {
        $cible_type = (string) get_field('indice_cible_type', $post_id);
        $enigme_id = $relationshipService->resolveHintTargetId(
            'enigme',
            null,
            get_field('indice_enigme_linked', $post_id)
        );
        $riddle_hunt_id = $cible_type === 'enigme' && $enigme_id !== null
            ? recuperer_id_chasse_associee($enigme_id)
            : null;
        $chasse_linked = $relationshipService->resolveTargetHuntId(
            $cible_type,
            $chasse_id,
            $riddle_hunt_id
        );

        if ($chasse_linked !== null) {
            update_field('indice_chasse_linked', $chasse_linked, $post_id);
        }
    }

    $content = trim((string) get_field('indice_contenu', $post_id));
    $imageId = get_field('indice_image', $post_id);
    $availability = (string) get_field('indice_disponibilite', $post_id);
    $availabilityDate = null;

    if ($availability === 'differe') {
        $dateRaw = get_field('indice_date_disponibilite', $post_id);
        $availabilityDate = $dateRaw ? convertir_en_datetime($dateRaw) : null;
    }

    $hintStatus = cat_get_hint_status_service()->resolve(
        $content !== '',
        !empty($imageId),
        $availability,
        $availabilityDate ? $availabilityDate->getTimestamp() : null,
        time()
    );
    $complete = $hintStatus['complete'];
    $state = $hintStatus['state'];

    update_field('indice_cache_complet', $complete ? 1 : 0, $post_id);
    update_field('indice_cache_etat_systeme', $state, $post_id);

    $status = get_post_status($post_id);
    $post   = get_post($post_id);

    $publicationStatus = cat_get_hint_status_service()->resolvePublicationStatus(
        $complete,
        $state,
        (string) $status
    );

    if ($publicationStatus !== null) {
        wp_update_post([
            'ID'            => $post_id,
            'post_status'   => $publicationStatus,
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
    ChassesAuTresor\Core\Content\HintScheduler::run();
}

add_action(
    ChassesAuTresor\Core\Content\HintScheduler::PROCESS_HOOK,
    'mettre_a_jour_cache_indice'
);

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
    $relationshipService = new ChassesAuTresor\Core\Relationships\RelationshipService();
    $linked_hunt = get_field('indice_chasse_linked', $post_id);
    $linked_riddle = get_field('indice_enigme_linked', $post_id);
    $objet_id = $relationshipService->resolveHintTargetId($type, $linked_hunt, $linked_riddle);
    $riddle_hunt_id = $relationshipService->normalizeId($linked_hunt);
    if ($type === 'enigme' && $riddle_hunt_id === null && $objet_id !== null) {
        $riddle_hunt_id = recuperer_id_chasse_associee($objet_id);
    }
    $chasse_id = $relationshipService->resolveTargetHuntId($type, $linked_hunt, $riddle_hunt_id);

    if ($objet_id !== null) {
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
