<?php
defined('ABSPATH') || exit;

if (!class_exists(ChassesAuTresor\Core\Content\HuntDateMutationService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/HuntDateMutationService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\HuntLinkMutationService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/HuntLinkMutationService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\HuntRewardMutationService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/HuntRewardMutationService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\HuntFieldMutationService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/HuntFieldMutationService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\HuntClosureService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/HuntClosureService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\HuntCreationRequestService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/HuntCreationRequestService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\HuntPostFactory::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/HuntPostFactory.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\HuntDeletionService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/HuntDeletionService.php';
}

// ==================================================
// 🗺️ CRÉATION & ÉDITION D’UNE CHASSE
// ==================================================
// 🔹 enqueue_script_chasse_edit() → Charge JS sur single chasse
// 🔹 register_endpoint_creer_chasse() → Enregistre /creer-chasse
// 🔹 creer_chasse_et_rediriger_si_appel() → Crée une chasse et redirige
// 🔹 modifier_champ_chasse() → Mise à jour AJAX (champ ACF ou natif)
// 🔹 assigner_organisateur_a_chasse() → Associe l’organisateur à la chasse en `save_post`


/**
 * Charge les scripts JS frontaux pour l’édition d’une chasse (panneau édition).
 *
 * @hook wp_enqueue_scripts
 */
function enqueue_script_chasse_edit()
{
    if (!is_singular('chasse')) {
        return;
    }

    $chasse_id = get_the_ID();

    if (!utilisateur_peut_modifier_post($chasse_id)) {
        return;
    }

    // Enfile les scripts nécessaires
    enqueue_core_edit_scripts([
        'edition-animation-options',
        'chasse-edit',
        'chasse-stats',
        'table-etiquette',
        'tentatives-toggle',
        'solutions-pager',
        'solutions-create',
        'indices-pager',
        'indices-create',
    ]);
    wp_localize_script(
        'chasse-stats',
        'ChasseStats',
        [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'chasseId' => $chasse_id,
        ]
    );
    wp_localize_script(
        'chasse-edit',
        'ChasseIndices',
        [
            'ajaxUrl'   => admin_url('admin-ajax.php'),
            'chasseId'  => $chasse_id,
            'nonce'     => wp_create_nonce('hunt_management'),
            'errorText' => __('Erreur lors du chargement des indices.', 'chassesautresor-com'),
        ]
    );

    wp_localize_script(
        'chasse-edit',
        'ChasseSolutions',
        [
            'scrollTarget'  => '#chasse-section-solutions',
            'tooltipChasse' => __('Il existe déjà une solution pour cette chasse', 'chassesautresor-com'),
            'tooltipEnigme' => __('Toutes les énigmes de la chasse ont déjà une solution', 'chassesautresor-com'),
        ]
    );

    wp_localize_script(
        'chasse-edit',
        'ChasseNbGagnantsI18n',
        [
            'unlimited'    => __('illimitée', 'chassesautresor-com'),
            'winnersLabel' => __('Gagnants', 'chassesautresor-com'),
            'limitLabel'   => __('Limite', 'chassesautresor-com'),
            'single'       => _n('%d gagnant', '%d gagnants', 1, 'chassesautresor-com'),
            'plural'       => _n('%d gagnant', '%d gagnants', 2, 'chassesautresor-com'),
        ]
    );

    wp_localize_script(
        'chasse-edit',
        'ChasseModeFinI18n',
        [
            'auto'   => __('automatique', 'chassesautresor-com'),
            'manual' => __('manuelle', 'chassesautresor-com'),
        ]
    );

    // Injecte les valeurs par défaut pour JS
    wp_localize_script('champ-init', 'CHP_CHASSE_DEFAUT', [
        'titre' => strtolower(TITRE_DEFAUT_CHASSE),
        'image_slug' => 'defaut-chasse-2',
    ]);

    // Charge les médias pour les champs image
    wp_enqueue_media();
}
add_action('wp_enqueue_scripts', 'enqueue_script_chasse_edit');


/**
 * Charge le script JS dédié à l’édition frontale des chasses.
 *
 * Ce script permet notamment :
 * – le toggle d’affichage du panneau de paramètres
 * – la désactivation automatique du champ date de fin si la durée est illimitée
 *
 * Le script est chargé uniquement sur les pages single du CPT "chasse".
 *
 * @return void
 */
function register_endpoint_creer_chasse()
{
  add_rewrite_rule('^creer-chasse/?$', 'index.php?creer_chasse=1', 'top');
  add_rewrite_tag('%creer_chasse%', '1');
}
add_action('init', 'register_endpoint_creer_chasse');


/**
 * Crée automatiquement une chasse à partir de l’URL frontale /creer-chasse/.
 *
 * Cette fonction est appelée via template_redirect si l’URL personnalisée /creer-chasse/ est visitée.
 * Elle vérifie que l’utilisateur est connecté et lié à un CPT organisateur.
 * Elle crée un post de type "chasse" avec statut "pending" et initialise plusieurs champs ACF,
 * en mettant à jour directement les groupes ACF complets pour compatibilité avec l'interface admin.
 *
 * @return void
 */
function creer_chasse_et_rediriger_si_appel()
{
  if (get_query_var('creer_chasse') !== '1') {
    return;
  }

  // 🔐 Vérification utilisateur
  if (!is_user_logged_in()) {
    wp_redirect(wp_login_url());
    exit;
  }

  $user       = wp_get_current_user();
  $user_id    = (int) $user->ID;
  $roles      = (array) $user->roles;

  cat_debug("👤 Utilisateur connecté : {$user_id}");

  // 📎 Récupération de l'organisateur lié et validation de la demande
  $organisateur_id = get_organisateur_from_user($user_id);
  $requestError = (new ChassesAuTresor\Core\Content\HuntCreationRequestService())->getError(
    (int) $organisateur_id,
    current_user_can('administrator'),
    current_user_can(ROLE_ORGANISATEUR),
    in_array(ROLE_ORGANISATEUR_CREATION, $roles, true),
    $organisateur_id ? organisateur_a_des_chasses($organisateur_id) : false,
    current_user_can('manage_options'),
    $organisateur_id && get_post_status($organisateur_id) === 'publish',
    $organisateur_id ? organisateur_a_chasse_pending($organisateur_id) : false
  );
  if ($requestError !== null) {
    $messages = [
      'missing_organizer' => __('Aucun organisateur associé.', 'chassesautresor-com'),
      'hunt_limit_reached' => __('Limite atteinte', 'chassesautresor-com'),
      'access_denied' => __('Accès refusé', 'chassesautresor-com'),
      'pending_hunt_exists' => __('Une chasse est déjà en attente de validation.', 'chassesautresor-com'),
    ];
    wp_die($messages[$requestError]);
  }

  // 📝 Création du post "chasse"
  $post_id = (new ChassesAuTresor\Core\Content\HuntPostFactory())->create(
    $user_id,
    (int) $organisateur_id,
    TITRE_DEFAUT_CHASSE,
    3902,
    current_time('Y-m-d H:i:s'),
    date('Y-m-d', strtotime('+2 years'))
  );

  if (is_wp_error($post_id)) {
    cat_debug("🛑 Erreur création post : " . $post_id->get_error_message());
    wp_die( __( 'Erreur lors de la création de la chasse.', 'chassesautresor-com' ) );
  }

  cat_debug("✅ Chasse créée avec l’ID : {$post_id}");

  // 🚀 Redirection vers la prévisualisation frontale
  $preview_url = get_preview_post_link($post_id);
  cat_debug("➡️ Redirection vers : {$preview_url}");
  wp_redirect($preview_url);
  exit;
}

add_action('template_redirect', 'creer_chasse_et_rediriger_si_appel');


/**
 * 🔹 modifier_champ_chasse() → Gère l’enregistrement AJAX des champs ACF ou natifs du CPT chasse (post_title inclus).
 */
add_action('wp_ajax_modifier_champ_chasse', 'modifier_champ_chasse');

/**
 * 🔹 modifier_dates_chasse() → Mise à jour groupée des dates et du mode illimité.
 */
add_action('wp_ajax_modifier_dates_chasse', 'modifier_dates_chasse');

function modifier_dates_chasse()
{
    if (!is_user_logged_in()) {
        wp_send_json_error('non_connecte');
    }

    $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
    if (!$post_id || get_post_type($post_id) !== 'chasse') {
        wp_send_json_error('post_invalide');
    }

    if (!utilisateur_peut_modifier_post($post_id) || !utilisateur_peut_editer_champs($post_id)) {
        wp_send_json_error('acces_refuse');
    }

    $mutation = (new ChassesAuTresor\Core\Content\HuntDateMutationService())->apply(
        $post_id,
        sanitize_text_field($_POST['date_debut'] ?? ''),
        sanitize_text_field($_POST['date_fin'] ?? ''),
        !empty($_POST['illimitee']),
        !empty($_POST['debut_differee']),
        static fn (string $date, array $formats) => convertir_en_datetime($date, $formats),
        'update_field',
        'update_post_meta',
        'get_post_meta'
    );

    if ($mutation['error'] !== null) {
        wp_send_json_error($mutation['error']);
    }

    mettre_a_jour_statuts_chasse($post_id);
    wp_send_json_success($mutation['data']);
}

/**
 * 🔸 Enregistrement AJAX d’un champ ACF ou natif du CPT chasse.
 *
 * Autorise :
 * - Le champ natif `post_title`
 * - Les champs ACF simples (text, number, true_false, etc.)
 * - Le répéteur `chasse_principale_liens`
 *
 * Vérifie que :
 * - L'utilisateur est connecté
 * - Il est l'auteur du post
 *
 * Les données sont sécurisées et vérifiées, même si `update_field()` retourne false.
 *
 * @hook wp_ajax_modifier_champ_chasse
 */
function modifier_champ_chasse()
{
  if (!is_user_logged_in()) {
    wp_send_json_error('non_connecte');
  }

  $user_id = get_current_user_id();
  $champ   = sanitize_text_field($_POST['champ'] ?? '');
  $valeur  = wp_kses_post($_POST['valeur'] ?? '');
  $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;

  if (!$champ || !isset($_POST['valeur'])) {
    wp_send_json_error('⚠️ donnees_invalides');
  }

  if (!$post_id || get_post_type($post_id) !== 'chasse') {
    wp_send_json_error('⚠️ post_invalide');
  }

  if (!utilisateur_peut_modifier_post($post_id)) {
    wp_send_json_error('⚠️ acces_refuse');
  }

    $demande_terminer = ($champ === 'champs_caches.chasse_cache_statut' && $valeur === 'termine');
    $champ_fin = in_array(
        $champ,
        ['champs_caches.chasse_cache_gagnants', 'champs_caches.chasse_cache_date_decouverte'],
        true
    );
    $champ_libre = ($champ === 'chasse_principale_liens');

    if (!$demande_terminer && !$champ_fin && !$champ_libre && !utilisateur_peut_editer_champs($post_id)) {
        wp_send_json_error('⚠️ acces_refuse');
    }

    $doit_recalculer_statut = false;
    $champ_valide = false;
    $reponse = ['champ' => $champ, 'valeur' => $valeur];
  // 🛡️ Initialisation sécurisée (champ simple)


  // 🔹 post_title
  if ($champ === 'post_title') {
    $ok = wp_update_post(['ID' => $post_id, 'post_title' => $valeur], true);
    if (is_wp_error($ok)) {
      wp_send_json_error('⚠️ echec_update_post_title');
    }
    wp_send_json_success($reponse);
  }

  // 🔹 chasse_principale_liens (répéteur JSON)
  if ($champ === 'chasse_principale_liens') {
    $mutation = (new ChassesAuTresor\Core\Content\HuntLinkMutationService())->apply(
      $post_id,
      (string) $valeur,
      'sanitize_text_field',
      'esc_url_raw',
      'get_field',
      'update_field'
    );
    if ($mutation['error'] !== null) {
      $message = $mutation['error'] === 'format_invalide'
        ? __('⚠️ format_invalide', 'chassesautresor-com')
        : __('⚠️ echec_mise_a_jour_liens', 'chassesautresor-com');
      wp_send_json_error($message);
    }

    wp_send_json_success(['champ' => $champ, 'valeur' => $mutation['value']]);
  }


  // 🔹 Champs récompense
  $rewardMutation = (new ChassesAuTresor\Core\Content\HuntRewardMutationService())->apply(
    $post_id,
    $champ,
    $valeur,
    'update_field'
  );
  if ($rewardMutation['error'] !== null) {
    wp_send_json_error($rewardMutation['error']);
  }
  if ($rewardMutation['handled']) {
    $champ_valide = true;
    $doit_recalculer_statut = $rewardMutation['recalculate_status'];
  }

  // 🔹 Champs standards
  $fieldMutation = (new ChassesAuTresor\Core\Content\HuntFieldMutationService())->apply(
    $post_id,
    $champ,
    $valeur,
    static fn (string $date, array $formats) => convertir_en_datetime($date, $formats),
    'sanitize_text_field',
    'update_field'
  );
  if ($fieldMutation['error'] !== null) {
    if ($fieldMutation['error'] === 'format_date_invalide') {
      $message = __('⚠️ format_date_invalide', 'chassesautresor-com');
    } elseif ($fieldMutation['error'] === 'valeur_invalide') {
      $message = __('⚠️ valeur_invalide', 'chassesautresor-com');
    } else {
      $message = __('⚠️ echec_mise_a_jour', 'chassesautresor-com');
    }
    wp_send_json_error($message);
  }
  if ($fieldMutation['handled']) {
    $champ_valide = true;
    $doit_recalculer_statut = $fieldMutation['recalculate_status'];
  }


  // 🔹 Déclenchement de la publication différée des solutions
  $completion = (new ChassesAuTresor\Core\Content\HuntClosureService())->apply(
    $post_id,
    $champ,
    $valeur,
    'update_field',
    'recuperer_enigmes_associees',
    'planifier_ou_deplacer_pdf_solution_immediatement',
    'solution_recuperer_par_objet',
    'solution_planifier_publication',
    'gerer_chasse_terminee'
  );
  if ($completion['error'] !== null) {
    wp_send_json_error(__('⚠️ echec_mise_a_jour', 'chassesautresor-com'));
  }
  if ($completion['handled']) {
    $champ_valide = true;
  }



  // 🔹 Refus des champs qui ne sont gérés par aucun service métier
  if (!$champ_valide) {
    wp_send_json_error(__('⚠️ champ_non_autorise', 'chassesautresor-com'));
  }

  // 🔁 Recalcul du statut si le champ fait partie des déclencheurs
  $champs_declencheurs_statut = [
    'caracteristiques.chasse_infos_date_debut',
    'caracteristiques.chasse_infos_date_fin',
    'caracteristiques.chasse_infos_cout_points',
    'caracteristiques.chasse_infos_duree_illimitee',
    'champs_caches.chasse_cache_statut_validation',
    'chasse_cache_statut_validation',
    'champs_caches.chasse_cache_date_decouverte',
    'chasse_cache_date_decouverte',
  ];

  if ($doit_recalculer_statut || in_array($champ, $champs_declencheurs_statut, true)) {
    wp_cache_delete($post_id, 'post');
    sleep(1); // donne une chance au cache + update ACF de se stabiliser
    $caracteristiques = get_field('chasse_infos_date_debut', $post_id);
    cat_debug("[🔁 RELOAD] Relecture avant recalcul : " . json_encode($caracteristiques));
    mettre_a_jour_statuts_chasse($post_id);
  }
  wp_send_json_success($reponse);
}




/**
 * Assigne automatiquement le CPT "organisateur" à une chasse en mettant à jour le champ relation ACF.
 *
 * @param int     $post_id ID du post en cours de sauvegarde.
 * @param WP_Post $post    Objet du post.
 */
function assigner_organisateur_a_chasse($post_id, $post)
{
  // Vérifier que c'est bien un CPT "chasse"
  if ($post->post_type !== 'chasse') {
    return;
  }

  // Éviter les sauvegardes automatiques
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
    return;
  }

  // Récupérer l'ID du CPT organisateur associé
  $organisateur_id = get_organisateur_from_chasse($post_id);

  // Vérifier si l'organisateur existe et mettre à jour le champ via la fonction générique
  if (!empty($organisateur_id)) {
    $resultat = mettre_a_jour_relation_acf(
      $post_id,                       // ID du post (chasse)
      'chasse_cache_organisateur',    // Nom du champ relation
      $organisateur_id,               // ID du post cible (organisateur)
      'field_67cfcba8c3bec'
    );

    // Vérification après mise à jour
    if (!$resultat) {
      cat_debug("🛑 Échec de la mise à jour de organisateur_chasse pour la chasse $post_id");
    }
  } else {
    cat_debug("🛑 Aucun organisateur trouvé pour la chasse $post_id (aucune mise à jour)");
  }
}
add_action('save_post_chasse', 'assigner_organisateur_a_chasse', 20, 2);

/**
 * Définit automatiquement une date de fin par défaut lors de la création d'une chasse.
 *
 * Si aucune date n'est encore renseignée, on initialise le champ avec la
 * date du jour + 2 ans, en suivant la même logique que le JavaScript frontal.
 *
 * @param int     $post_id ID de la chasse.
 * @param WP_Post $post    Objet du post courant.
 */
function definir_date_fin_par_defaut($post_id, $post)
{
  if ($post->post_type !== 'chasse') {
    return;
  }

  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
    return;
  }

  $date_fin = get_post_meta($post_id, 'chasse_infos_date_fin', true);
  if ($date_fin) {
    return;
  }

  $timestamp   = current_time('timestamp');
  $in_two_years = date('Y-m-d', strtotime('+2 years', $timestamp));

  $ok = update_field('chasse_infos_date_fin', $in_two_years, $post_id);
  if ($ok === false) {
    update_post_meta($post_id, 'chasse_infos_date_fin', $in_two_years);
  }
}
add_action('save_post_chasse', 'definir_date_fin_par_defaut', 10, 2);

add_action('wp_ajax_supprimer_chasse', 'supprimer_chasse_ajax');

/**
 * Envoie une chasse et ses contenus liés à la corbeille WordPress.
 */
function chasse_trash_with_children(int $chasse_id): bool
{
    $enigme_ids = array_map('intval', recuperer_ids_enigmes_pour_chasse($chasse_id));
    $attachments = function_exists('get_attached_media') ? get_attached_media('image', $chasse_id) : [];

    return (new ChassesAuTresor\Core\Content\HuntDeletionService())->trash(
        $chasse_id,
        $enigme_ids,
        $attachments,
        'wp_trash_post',
        static function (int $enigmeId): void {
            if (function_exists('supprimer_dossier_enigme')) {
                supprimer_dossier_enigme($enigmeId);
            }
        },
        static function (int $chasseId): void {
            if (function_exists('synchroniser_cache_enigmes_chasse')) {
                synchroniser_cache_enigmes_chasse($chasseId, true, true);
            }
        }
    );
}

/**
 * Supprime une chasse en attente ainsi que ses énigmes associées.
 */
function supprimer_chasse_ajax(): void
{
    check_ajax_referer('hunt_management', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error('non_connecte');
    }

    $chasse_id = isset($_POST['chasse_id']) ? (int) $_POST['chasse_id'] : 0;
    $user_id = get_current_user_id();
    $requestError = (new ChassesAuTresor\Core\Content\HuntDeletionService())->getRequestError(
        $chasse_id,
        (string) get_post_type($chasse_id),
        (string) get_post_status($chasse_id),
        (string) get_post_meta($chasse_id, 'chasse_cache_statut', true),
        $user_id > 0 && utilisateur_est_organisateur_associe_a_chasse($user_id, $chasse_id)
    );
    if ($requestError !== null) {
        wp_send_json_error($requestError);
    }

    if (!chasse_trash_with_children($chasse_id)) {
        wp_send_json_error('erreur_suppression');
    }

    if (function_exists('myaccount_add_flash_message')) {
        myaccount_add_flash_message(
            $user_id,
            __(
                'Votre chasse a été supprimée. Vous pouvez en créer une nouvelle quand vous le souhaitez.',
                'chassesautresor-com'
            ),
            'success',
            true
        );
    }

    wp_send_json_success([
        'redirect' => home_url('/mon-compte/organisateurs/'),
    ]);
}
