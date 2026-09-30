<?php
/**
 * Gestion de la publication différée des solutions.
 *
 * @package chassesautresor.com
 */

require_once __DIR__ . '/../constants.php';

if (!class_exists(ChassesAuTresor\Core\Content\SolutionAvailabilityService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/SolutionAvailabilityService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\SolutionCacheService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/SolutionCacheService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\SolutionQueryService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/SolutionQueryService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\SolutionRouteRegistrar::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/SolutionRouteRegistrar.php';
}

if (!class_exists(ChassesAuTresor\Core\Relationships\RelationshipService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Relationships/RelationshipService.php';
}

/**
 * Planifie la publication d'une solution.
 *
 * Calcule la date cible selon les champs ACF puis programme un événement cron
 * unique pour rendre la solution accessible.
 *
 * @param int $solution_id ID de la solution.
 * @return void
 */
function solution_planifier_publication(int $solution_id): void
{
    if (get_post_type($solution_id) !== 'solution') {
        return;
    }

    $cible     = get_field('solution_cible_type', $solution_id);
    $chasse_id = 0;
    if ($cible === 'chasse') {
        $chasse    = get_field('solution_chasse_linked', $solution_id);
        $chasse_id = is_array($chasse) ? (int) ($chasse[0] ?? 0) : (int) $chasse;
    } elseif ($cible === 'enigme') {
        $enigme    = get_field('solution_enigme_linked', $solution_id);
        $enigme_id = is_array($enigme) ? (int) ($enigme[0] ?? 0) : (int) $enigme;
        $chasse_id = $enigme_id ? recuperer_id_chasse_associee($enigme_id) : 0;
    }

    if (!$chasse_id) {
        update_field('solution_cache_etat_systeme', SOLUTION_STATE_INVALIDE, $solution_id);
        return;
    }

    $statut   = (string) get_field('chasse_cache_statut', $chasse_id);
    $dispo    = get_field('solution_disponibilite', $solution_id) ?: 'fin_chasse';
    $decalage = (int) get_field('solution_decalage_jours', $solution_id);
    $heure    = get_field('solution_heure_publication', $solution_id) ?: '00:00';

    wp_clear_scheduled_hook('publier_solution_programmee', [$solution_id]);
    $service = new ChassesAuTresor\Core\Content\SolutionAvailabilityService();
    $plan = $service->getPublicationPlan(
        $statut,
        (string) $dispo,
        $decalage,
        (string) $heure,
        (int) current_time('timestamp')
    );

    if ($plan['state'] === SOLUTION_STATE_EN_COURS) {
        solution_rendre_accessible($solution_id);
        return;
    }

    update_field('solution_cache_etat_systeme', $plan['state'], $solution_id);
    if ($plan['target_timestamp'] === null) {
        delete_post_meta($solution_id, 'solution_date_disponibilite');
        return;
    }

    update_post_meta(
        $solution_id,
        'solution_date_disponibilite',
        gmdate('Y-m-d H:i:s', $plan['target_timestamp'])
    );
    wp_schedule_single_event($plan['target_timestamp'], 'publier_solution_programmee', [$solution_id]);
}

/**
 * Rend une solution accessible immédiatement.
 *
 * @param int $solution_id ID de la solution.
 * @return void
 */
function solution_rendre_accessible(int $solution_id): void
{
    if (get_post_type($solution_id) !== 'solution') {
        return;
    }

    update_field('solution_cache_etat_systeme', SOLUTION_STATE_EN_COURS, $solution_id);
    delete_post_meta($solution_id, 'solution_date_disponibilite');
    if (get_post_status($solution_id) !== 'publish') {
        $post = get_post($solution_id);
        wp_update_post([
            'ID'            => $solution_id,
            'post_status'   => 'publish',
            'post_date'     => $post->post_date,
            'post_date_gmt' => $post->post_date_gmt,
            'edit_date'     => true,
        ]);
    }
}
add_action('publier_solution_programmee', 'solution_rendre_accessible');

/**
 * Basculer les solutions programmées dont la date est atteinte.
 *
 * @return void
 */
function basculer_solutions_programme(): void
{
    $service = new ChassesAuTresor\Core\Content\SolutionAvailabilityService();
    $solutions = get_posts($service->getDueSolutionIdsQueryArgs((string) current_time('mysql')));

    foreach ($solutions as $sid) {
        solution_rendre_accessible($sid);
    }
}
add_action('basculer_solutions_programme', 'basculer_solutions_programme');

/**
 * Planifie la tâche récurrente de basculement des solutions.
 *
 * @return void
 */
function planifier_tache_basculer_solutions_programme(): void
{
    if (!wp_next_scheduled('basculer_solutions_programme')) {
        wp_schedule_event(time(), 'hourly', 'basculer_solutions_programme');
    }
}
add_action('after_switch_theme', 'planifier_tache_basculer_solutions_programme');

/**
 * Met à jour le cache et l'état système d'une solution.
 *
 * @param int $post_id ID de la solution.
 * @return void
 */
function mettre_a_jour_cache_solution(int $post_id): void
{
    if (get_post_type($post_id) !== 'solution') {
        return;
    }

    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    $cible_type = (string) get_field('solution_cible_type', $post_id);
    $relationshipService = new ChassesAuTresor\Core\Relationships\RelationshipService();
    $target_id = $relationshipService->resolveTargetId(
        $cible_type,
        get_field('solution_chasse_linked', $post_id),
        get_field('solution_enigme_linked', $post_id)
    );

    $explic  = trim((string) get_field('solution_explication', $post_id));
    $fichier = get_field('solution_fichier', $post_id);
    $content = $explic !== '' || !empty($fichier);

    $cacheUpdate = (new ChassesAuTresor\Core\Content\SolutionCacheService())->buildUpdate(
        $content,
        $target_id,
        (string) get_post_status($post_id)
    );
    update_field('solution_cache_complet', $cacheUpdate['complete'], $post_id);
    update_field('solution_cache_etat_systeme', $cacheUpdate['state'], $post_id);

    if ($cacheUpdate['publication_status'] !== null) {
        $post = get_post($post_id);
        wp_update_post([
            'ID'            => $post_id,
            'post_status'   => $cacheUpdate['publication_status'],
            'post_date'     => $post->post_date,
            'post_date_gmt' => $post->post_date_gmt,
            'edit_date'     => true,
        ]);
    }
}

/**
 * Hook ACF pour planifier la publication à la sauvegarde.
 *
 * @param int $post_id ID du post sauvegardé.
 * @return void
 */
function solution_acf_save_post(int $post_id): void
{
    if (get_post_type($post_id) !== 'solution') {
        return;
    }

    mettre_a_jour_cache_solution($post_id);
    solution_planifier_publication($post_id);
}
add_action('acf/save_post', 'solution_acf_save_post', 40);

// ==================================================
// 💡 GESTION DES SOLUTIONS (création, redirection, AJAX)
// ==================================================

/**
 * Redirige l’affichage d’une solution vers sa chasse ou son énigme liée.
 *
 * @return void
 */
function rediriger_si_affichage_solution(): void
{
    if (!is_singular('solution')) {
        return;
    }

    $solution_id = get_the_ID();
    $cible_type  = get_field('solution_cible_type', $solution_id);
    $redirect_id = 0;

    if ($cible_type === 'chasse') {
        $redirect_id = (int) get_field('solution_chasse_linked', $solution_id);
    } elseif ($cible_type === 'enigme') {
        $redirect_id = (int) get_field('solution_enigme_linked', $solution_id);
    }

    if ($redirect_id) {
        wp_safe_redirect(get_permalink($redirect_id));
        exit;
    }
}
add_action('template_redirect', 'rediriger_si_affichage_solution');

/**
 * Crée une solution liée à une chasse ou une énigme.
 *
 * @param int      $objet_id   ID de la chasse ou de l’énigme.
 * @param string   $objet_type Type de cible ('chasse' ou 'enigme').
 * @param int|null $user_id    ID utilisateur (null = courant).
 * @return int|WP_Error
 */
function creer_solution_pour_objet(int $objet_id, string $objet_type, ?int $user_id = null)
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

    if (!solution_action_autorisee('create', $objet_type, $objet_id)) {
        return new WP_Error('permission_refusee', __('Droits insuffisants.', 'chassesautresor-com'));
    }

    $chasse_id = $objet_type === 'chasse'
        ? $objet_id
        : recuperer_id_chasse_associee($objet_id);

    if (!$chasse_id) {
        return new WP_Error('permission_refusee', __('Droits insuffisants.', 'chassesautresor-com'));
    }

    $user_id = $user_id ?? get_current_user_id();

    $queryService = new ChassesAuTresor\Core\Content\SolutionQueryService();
    $existing = get_posts($queryService->getExistingSolutionIdsQueryArgs($objet_id, $objet_type));
    if (!empty($existing)) {
        return new WP_Error('existe_deja', __('Une solution existe déjà pour cet objet.', 'chassesautresor-com'));
    }

    $solution_id = wp_insert_post([
        'post_type'   => 'solution',
        'post_status' => 'pending',
        'post_title'  => TITRE_DEFAUT_SOLUTION,
        'post_author' => $user_id,
    ]);

    if (is_wp_error($solution_id)) {
        return $solution_id;
    }

    $objet_titre   = get_the_title($objet_id);
    $nouveau_titre = sprintf(__('Solution | %s', 'chassesautresor-com'), $objet_titre);
    wp_update_post([
        'ID'         => $solution_id,
        'post_title' => $nouveau_titre,
    ]);

    update_field('solution_cible_type', $objet_type, $solution_id);
    update_field('solution_chasse_linked', $chasse_id, $solution_id);
    if ($objet_type === 'enigme') {
        update_field('solution_enigme_linked', $objet_id, $solution_id);
    }
    update_field('solution_disponibilite', 'fin_chasse', $solution_id);
    update_field('solution_decalage_jours', 0, $solution_id);
    update_field('solution_heure_publication', '00:00', $solution_id);
    update_field('solution_cache_etat_systeme', SOLUTION_STATE_DESACTIVE, $solution_id);

    return $solution_id;
}

/**
 * Enregistre l’URL personnalisée /creer-solution/
 *
 * @return void
 */
function register_endpoint_creer_solution(): void
{
    ChassesAuTresor\Core\Content\SolutionRouteRegistrar::register();
}

/**
 * S'assure que les règles de réécriture prennent en compte /creer-solution/.
 *
 * @return void
 */
function flush_rewrite_rules_creer_solution(): void
{
    ChassesAuTresor\Core\Content\SolutionRouteRegistrar::flush();
}

/**
 * Détecte l’appel à /creer-solution/ et redirige vers la page cible.
 *
 * @return void
 */
function creer_solution_et_rediriger_si_appel(): void
{
    if (get_query_var('creer_solution') !== '1') {
        return;
    }

    $nonce = $_GET['nonce'] ?? '';
    if (!wp_verify_nonce($nonce, 'creer_solution')) {
        wp_die(__('Action non autorisée.', 'chassesautresor-com'), 'Erreur', ['response' => 403]);
    }

    if (!is_user_logged_in()) {
        wp_redirect(wp_login_url());
        exit;
    }

    $cible_id   = isset($_GET['chasse_id']) ? absint($_GET['chasse_id']) : 0;
    $cible_type = 'chasse';
    if (!$cible_id) {
        $cible_id   = isset($_GET['enigme_id']) ? absint($_GET['enigme_id']) : 0;
        $cible_type = 'enigme';
    }

    if (!$cible_id) {
        wp_die(__('ID cible manquant.', 'chassesautresor-com'), 'Erreur', ['response' => 400]);
    }

    $solution_id = creer_solution_pour_objet($cible_id, $cible_type);
    if (is_wp_error($solution_id)) {
        $error_message = sanitize_text_field($solution_id->get_error_message());
        $referer       = wp_get_referer() ?: get_permalink($cible_id);
        $redirect_url  = add_query_arg('erreur', $error_message, $referer);
        wp_safe_redirect($redirect_url);
        exit;
    }

    wp_safe_redirect(get_permalink($cible_id));
    exit;
}
add_action('template_redirect', 'creer_solution_et_rediriger_si_appel');

/**
 * Liste les solutions via AJAX pour un objet donné.
 *
 * @return void
 */
function ajax_solutions_lister_table(): void
{
    if (!is_user_logged_in()) {
        wp_send_json_error('non_connecte');
    }

    $objet_id   = isset($_POST['objet_id']) ? (int) $_POST['objet_id'] : 0;
    $objet_type = sanitize_key($_POST['objet_type'] ?? '');
    $page       = isset($_POST['page']) ? (int) $_POST['page'] : 1;

    if (!$objet_id || !in_array($objet_type, ['chasse', 'enigme'], true)
        || get_post_type($objet_id) !== $objet_type
    ) {
        wp_send_json_error('post_invalide');
    }

    if (!solution_action_autorisee('edit', $objet_type, $objet_id)) {
        wp_send_json_error('acces_refuse');
    }

    $per_page = 5;
    $enigme_ids = $objet_type === 'chasse' ? recuperer_ids_enigmes_pour_chasse($objet_id) : [];
    $queryService = new ChassesAuTresor\Core\Content\SolutionQueryService();
    $page       = max(1, $page);
    $query_args = $queryService->getManagementQueryArgs(
        $objet_id,
        $objet_type,
        $enigme_ids,
        $page,
        $per_page
    );
    $query      = new WP_Query($query_args);
    $total_pages = (int) $query->max_num_pages;
    if ($page > $total_pages && $total_pages > 0) {
        $page                  = $total_pages;
        $query_args['paged']   = $page;
        $query                 = new WP_Query($query_args);
        $total_pages           = (int) $query->max_num_pages;
    }

    ob_start();
    get_template_part('template-parts/common/solutions-table', null, [
        'solutions'  => $query->posts,
        'page'       => $page,
        'pages'      => $total_pages,
        'objet_type' => $objet_type,
        'objet_id'   => $objet_id,
    ]);
    $html = ob_get_clean();

    wp_send_json_success([
        'html'  => $html,
        'page'  => $page,
        'pages' => $total_pages,
    ]);
}
add_action('wp_ajax_solutions_lister_table', 'ajax_solutions_lister_table');

/**
 * Retourne l'état des boutons d'ajout de solutions pour une chasse.
 *
 * @return void
 */
function ajax_chasse_solution_status(): void
{
    if (!is_user_logged_in()) {
        wp_send_json_error('non_connecte');
    }

    $chasse_id = isset($_POST['chasse_id']) ? (int) $_POST['chasse_id'] : 0;
    $enigme_id = isset($_POST['enigme_id']) ? (int) $_POST['enigme_id'] : 0;

    if (!$chasse_id || get_post_type($chasse_id) !== 'chasse') {
        wp_send_json_error('post_invalide');
    }
    if ($enigme_id && get_post_type($enigme_id) !== 'enigme') {
        $enigme_id = 0;
    }

    if (!solution_action_autorisee('create', 'chasse', $chasse_id)) {
        wp_send_json_error('acces_refuse');
    }

    $has_solution_chasse = solution_existe_pour_objet($chasse_id, 'chasse');
    $has_solution_enigme = $enigme_id ? solution_existe_pour_objet($enigme_id, 'enigme') : false;

    $toutes_enigmes = recuperer_enigmes_pour_chasse($chasse_id);
    $enigmes        = array_filter(
        $toutes_enigmes,
        static fn($e) => !solution_existe_pour_objet($e->ID, 'enigme')
    );
    $has_enigmes         = !empty($enigmes);
    $has_enigme_solution = count($toutes_enigmes) > count($enigmes);
    $has_solutions       = $has_solution_chasse || $has_enigme_solution;

    $total_solutions = 0;
    if (function_exists('get_posts')) {
        $enigme_ids = array_map(static fn($e) => (int) $e->ID, $toutes_enigmes);
        $queryService = new ChassesAuTresor\Core\Content\SolutionQueryService();
        $count_posts = get_posts($queryService->getManagementQueryArgs(
            $chasse_id,
            'chasse',
            $enigme_ids,
            1,
            1,
            true
        ));
        $total_solutions = is_array($count_posts) ? count($count_posts) : 0;
    }

    wp_send_json_success([
        'has_solution_chasse' => $has_solution_chasse ? 1 : 0,
        'has_solution_enigme' => $has_solution_enigme ? 1 : 0,
        'has_enigmes'        => $has_enigmes ? 1 : 0,
        'has_solutions'      => $has_solutions ? 1 : 0,
        'total_solutions'    => $total_solutions,
    ]);
}
add_action('wp_ajax_chasse_solution_status', 'ajax_chasse_solution_status');

/**
 * Crée une solution via une requête AJAX depuis une modale.
 *
 * @return void
 */
function ajax_creer_solution_modal(): void
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
        $linked = isset($_POST['solution_enigme_linked']) ? (int) $_POST['solution_enigme_linked'] : 0;
        if (!$linked || $linked !== $objet_id) {
            wp_send_json_error('post_invalide');
        }
    }

    if (!solution_action_autorisee('create', $objet_type, $objet_id)) {
        wp_send_json_error('acces_refuse');
    }

    $has_file   = !empty($_FILES['solution_fichier']['tmp_name']) || !empty($_POST['solution_fichier']);
    $has_explic = isset($_POST['solution_explication']) && trim((string) $_POST['solution_explication']) !== '';
    if (!$has_file && !$has_explic) {
        wp_send_json_error('contenu_manquant');
    }

    $solution_id = creer_solution_pour_objet($objet_id, $objet_type);
    if (is_wp_error($solution_id)) {
        wp_send_json_error($solution_id->get_error_message());
    }

    $fichier = 0;
    if (!empty($_FILES['solution_fichier']) && !empty($_FILES['solution_fichier']['tmp_name'])) {
        if (!function_exists('media_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }
        $fichier = media_handle_upload('solution_fichier', $solution_id);
        if (is_wp_error($fichier)) {
            wp_send_json_error($fichier->get_error_message());
        }
    } elseif (isset($_POST['solution_fichier'])) {
        $fichier = (int) $_POST['solution_fichier'];
    }

    $explic = wp_kses_post($_POST['solution_explication'] ?? '');
    $dispo  = sanitize_key($_POST['solution_disponibilite'] ?? 'fin_chasse');
    $delai  = isset($_POST['solution_decalage_jours']) ? (int) $_POST['solution_decalage_jours'] : 0;
    $heure  = sanitize_text_field($_POST['solution_heure_publication'] ?? '');

    if ($fichier) {
        update_field('solution_fichier', $fichier, $solution_id);
    }
    if ($explic !== '') {
        update_field('solution_explication', $explic, $solution_id);
    }

    $dispo = $dispo === 'differee' ? 'differee' : 'fin_chasse';
    update_field('solution_disponibilite', $dispo, $solution_id);
    update_field('solution_decalage_jours', $delai, $solution_id);
    update_field('solution_heure_publication', $heure ?: '00:00', $solution_id);

    mettre_a_jour_cache_solution($solution_id);
    solution_planifier_publication($solution_id);

    wp_send_json_success(['solution_id' => $solution_id]);
}
add_action('wp_ajax_creer_solution_modal', 'ajax_creer_solution_modal');

/**
 * Met à jour une solution existante via le modal d'édition.
 *
 * @return void
 */
function ajax_modifier_solution_modal(): void
{
    if (!is_user_logged_in()) {
        wp_send_json_error('non_connecte');
    }

    $solution_id = isset($_POST['solution_id']) ? (int) $_POST['solution_id'] : 0;
    $objet_id    = isset($_POST['objet_id']) ? (int) $_POST['objet_id'] : 0;
    $objet_type  = sanitize_key($_POST['objet_type'] ?? '');

    if (!$solution_id || get_post_type($solution_id) !== 'solution') {
        wp_send_json_error('solution_invalide');
    }
    if (!$objet_id || !in_array($objet_type, ['chasse', 'enigme'], true) || get_post_type($objet_id) !== $objet_type) {
        wp_send_json_error('post_invalide');
    }
    if (!solution_action_autorisee('edit', $objet_type, $objet_id)) {
        wp_send_json_error('acces_refuse');
    }

    $has_file   = !empty($_FILES['solution_fichier']['tmp_name']) || !empty($_POST['solution_fichier']);
    $has_explic = isset($_POST['solution_explication']) && trim((string) $_POST['solution_explication']) !== '';
    if (!$has_file && !$has_explic) {
        wp_send_json_error('contenu_manquant');
    }

    $fichier = 0;
    $has_file_input = isset($_POST['solution_fichier']) || (!empty($_FILES['solution_fichier']) && !empty($_FILES['solution_fichier']['tmp_name']));
    if (!empty($_FILES['solution_fichier']) && !empty($_FILES['solution_fichier']['tmp_name'])) {
        if (!function_exists('media_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }
        $fichier = media_handle_upload('solution_fichier', $solution_id);
        if (is_wp_error($fichier)) {
            wp_send_json_error($fichier->get_error_message());
        }
    } elseif (isset($_POST['solution_fichier'])) {
        $fichier = (int) $_POST['solution_fichier'];
    }

    $explic = wp_kses_post($_POST['solution_explication'] ?? '');
    $dispo  = sanitize_key($_POST['solution_disponibilite'] ?? 'fin_chasse');
    $delai  = isset($_POST['solution_decalage_jours']) ? (int) $_POST['solution_decalage_jours'] : 0;
    $heure  = sanitize_text_field($_POST['solution_heure_publication'] ?? '');

    if ($fichier) {
        update_field('solution_fichier', $fichier, $solution_id);
    } elseif ($has_file_input) {
        delete_field('solution_fichier', $solution_id);
    }
    update_field('solution_explication', $explic, $solution_id);

    $dispo = $dispo === 'differee' ? 'differee' : 'fin_chasse';
    update_field('solution_disponibilite', $dispo, $solution_id);
    update_field('solution_decalage_jours', $delai, $solution_id);
    update_field('solution_heure_publication', $heure ?: '00:00', $solution_id);

    mettre_a_jour_cache_solution($solution_id);
    solution_planifier_publication($solution_id);

    wp_send_json_success(['solution_id' => $solution_id]);
}
add_action('wp_ajax_modifier_solution_modal', 'ajax_modifier_solution_modal');

/**
 * Supprime une solution via requête AJAX.
 *
 * @hook wp_ajax_supprimer_solution
 * @return void
 */
function supprimer_solution_ajax(): void
{
    if (!is_user_logged_in()) {
        wp_send_json_error('non_connecte');
    }

    $solution_id = isset($_POST['solution_id']) ? (int) $_POST['solution_id'] : 0;
    if (!$solution_id || get_post_type($solution_id) !== 'solution') {
        wp_send_json_error('id_invalide');
    }

    $cible_type = get_field('solution_cible_type', $solution_id) === 'enigme' ? 'enigme' : 'chasse';
    $relationshipService = new ChassesAuTresor\Core\Relationships\RelationshipService();
    $objet_id = $relationshipService->resolveTargetId(
        $cible_type,
        get_field('solution_chasse_linked', $solution_id),
        get_field('solution_enigme_linked', $solution_id)
    );

    if ($objet_id === null || !solution_action_autorisee('delete', $cible_type, $objet_id)) {
        wp_send_json_error('acces_refuse');
    }

    if (wp_delete_post($solution_id, true) === false) {
        wp_send_json_error('echec_suppression');
    }

    wp_send_json_success();
}
add_action('wp_ajax_supprimer_solution', 'supprimer_solution_ajax');
