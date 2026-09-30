<?php
defined('ABSPATH') || exit;

if (!class_exists(ChassesAuTresor\Core\Content\RiddleSolutionAttachmentService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Relationships/RelationshipService.php';
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/RiddleSolutionAttachmentService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\RiddleSolutionFilePolicyService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/RiddleSolutionFilePolicyService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\RiddleSolutionFilePublicationService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Relationships/RelationshipService.php';
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/RiddleSolutionFilePublicationService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\RiddleSolutionFileScheduler::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/RiddleSolutionFileScheduler.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\RiddleSolutionFileStorageService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/RiddleSolutionFileStorageService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\RiddleSolutionUploadService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/RiddleSolutionUploadService.php';
}

if (!class_exists(ChassesAuTresor\Core\Media\RiddleUploadDirectoryService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Media/RiddleUploadDirectoryService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\RiddleManagementService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/RiddleManagementService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\RiddleMutationService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/RiddleMutationService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\RiddleOrderingService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/RiddleOrderingService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\RiddleDeletionService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/RiddleDeletionService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\RiddleCompletionService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/RiddleCompletionService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\RiddleActionPolicyService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/RiddleActionPolicyService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\RiddleCreationService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/RiddleCreationService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\RiddleCreationRequestService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/RiddleCreationRequestService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\RiddleFieldPolicyService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/RiddleFieldPolicyService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\RiddlePostFactory::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/RiddlePostFactory.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\RiddleRelationshipService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/RiddleRelationshipService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\RiddleRelationshipCleanupService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/RiddleRelationshipCleanupService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\RiddleRelationshipLifecycleService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/RiddleRelationshipLifecycleService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\RiddleRouteRegistrar::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/RiddleRouteRegistrar.php';
}


// ==================================================
// 🧩 CRÉATION & ÉDITION D’UNE ÉNIGME
// ==================================================
// 🔹 enqueue_script_enigme_edit() → Charge JS sur single énigme
// 🔹 creer_enigme_pour_chasse() → Crée une énigme liée à une chasse
// 🔹 register_endpoint_creer_enigme() → Enregistre /creer-enigme
// 🔹 creer_enigme_et_rediriger_si_appel() → Crée une énigme et redirige
// 🔹 modifier_champ_enigme() (AJAX) → Mise à jour champs ACF ou natifs


/**
 * Charge les scripts JS nécessaires à l’édition frontale d’une énigme :
 * – Modules partagés (core)
 * – Header organisateur
 * – Panneau latéral d’édition de l’énigme
 *
 * Le script est chargé uniquement sur les pages single du CPT "enigme",
 * si l’utilisateur a les droits de modification sur ce post.
 *
 * @hook wp_enqueue_scripts
 * @return void
 */
function enqueue_script_enigme_edit()
{
  if (!is_singular('enigme')) return;

  $enigme_id = get_the_ID();
  if (!utilisateur_peut_modifier_post($enigme_id)) return;

  // 📦 Modules JS partagés + scripts spécifiques
  enqueue_core_edit_scripts([
    'edition-animation-options',
    'organisateur-edit',
    'enigme-edit',
    'enigme-stats',
    'table-etiquette',
    'tentatives-toggle',
    'solutions-pager',
    'solutions-create',
    'indices-pager',
    'indices-create',
  ]);

  wp_localize_script(
    'enigme-stats',
    'EnigmeStats',
    [
      'ajaxUrl'   => admin_url('admin-ajax.php'),
      'enigmeId'  => $enigme_id,
    ]
  );

  wp_localize_script(
    'enigme-edit',
    'ChasseSolutions',
    [
      'scrollTarget'  => '#enigme-section-solutions',
      'tooltipChasse' => __('Il existe déjà une solution pour cette chasse', 'chassesautresor-com'),
      'tooltipEnigme' => __('Il existe déjà une solution pour cette énigme', 'chassesautresor-com'),
      'toggleChasse'  => __('Voir toutes les solutions de la chasse', 'chassesautresor-com'),
      'toggleEnigme'  => __('Voir la solution de cette énigme', 'chassesautresor-com'),
    ]
  );

  // Localisation JS si besoin (ex : valeurs par défaut)
  wp_localize_script('champ-init', 'CHP_ENIGME_DEFAUT', [
    'titre' => strtolower(TITRE_DEFAUT_ENIGME),
    'image_slug' => 'defaut-enigme',
    'nonce' => wp_create_nonce('modifier_champ_enigme'),
    'deleteNonce' => wp_create_nonce('supprimer_enigme'),
  ]);

  wp_enqueue_media();
}
add_action('wp_enqueue_scripts', 'enqueue_script_enigme_edit');


/**
 * 🔹 creer_enigme_pour_chasse() → Crée une énigme liée à une chasse, avec champs ACF par défaut.
 *
 * @param int $chasse_id
 * @param int|null $user_id
 * @return int|WP_Error
 */
function creer_enigme_pour_chasse($chasse_id, $user_id = null)
{
  if (is_null($user_id)) {
    $user_id = get_current_user_id();
  }

  $has_valid_hunt = get_post_type($chasse_id) === 'chasse';
  $has_valid_user = $has_valid_hunt && $user_id && get_userdata($user_id);
  $organisateur_id = $has_valid_user ? get_organisateur_from_chasse($chasse_id) : 0;
  $creation_error = (new ChassesAuTresor\Core\Content\RiddleCreationService())->getCreationError(
    $has_valid_hunt,
    (bool) $has_valid_user,
    (bool) $organisateur_id
  );
  if ($creation_error !== null) {
    $errors = [
      'invalid_hunt' => ['chasse_invalide', __('ID de chasse invalide.', 'chassesautresor-com')],
      'invalid_user' => ['utilisateur_invalide', __('Utilisateur non connecté.', 'chassesautresor-com')],
      'missing_organizer' => [
        'organisateur_introuvable',
        __('Organisateur non lié à cette chasse.', 'chassesautresor-com'),
      ],
    ];

    return new WP_Error($errors[$creation_error][0], $errors[$creation_error][1]);
  }

  $factory = new ChassesAuTresor\Core\Content\RiddlePostFactory();
  $enigme_id = $factory->create(
    (int) $chasse_id,
    (int) $organisateur_id,
    (int) $user_id,
    TITRE_DEFAUT_ENIGME,
    (new DateTime('+1 month'))->format('Y-m-d H:i:s')
  );

  if (is_wp_error($enigme_id)) {
    return $enigme_id;
  }

  // Calcule l\'état système initial pour permettre l\'édition complète
  enigme_mettre_a_jour_etat_systeme($enigme_id);

  return $enigme_id;
}


/**
 * Enregistre l’URL personnalisée /creer-enigme/
 *
 * Permet de détecter les visites à /creer-enigme/?chasse_id=XXX
 * et de déclencher la création automatique d’une énigme.
 *
 * @return void
 */
function register_endpoint_creer_enigme()
{
  ChassesAuTresor\Core\Content\RiddleRouteRegistrar::register();
}


/**
 * Détecte l’appel à l’endpoint /creer-enigme/?chasse_id=XXX
 * Crée une énigme liée à la chasse spécifiée, puis redirige vers sa page.
 *
 * Conditions :
 * - L’utilisateur doit être connecté
 * - L’ID de chasse doit être valide et exister
 *
 * @return void
 */
function creer_enigme_et_rediriger_si_appel()
{
    if (get_query_var('creer_enigme') !== '1') {
        return;
    }

    if (!defined('DONOTCACHEPAGE')) {
        define('DONOTCACHEPAGE', true); // Indique aux plugins cache de ne pas mettre en cache
    }
    do_action('litespeed_control_set_nocache'); // Spécifique à LiteSpeed
    nocache_headers();

    $chasse_id = isset($_GET['chasse_id']) ? absint($_GET['chasse_id']) : 0;
    $has_valid_nonce = (bool) wp_verify_nonce(
        sanitize_text_field(wp_unslash($_GET['nonce'] ?? '')),
        'creer_enigme'
    );
    $is_logged_in = $has_valid_nonce && is_user_logged_in();
    $has_valid_hunt = $is_logged_in && $chasse_id > 0 && get_post_type($chasse_id) === 'chasse';
    $request_error = (new ChassesAuTresor\Core\Content\RiddleCreationRequestService())->getRequestError(
        $has_valid_nonce,
        $is_logged_in,
        $has_valid_hunt
    );

    if ($request_error === 'invalid_nonce') {
        wp_die(__('Action non autorisée.', 'chassesautresor-com'), 'Erreur', ['response' => 403]);
    }

    if ($request_error === 'authentication_required') {
        wp_redirect(wp_login_url());
        exit;
    }

    if ($request_error === 'invalid_hunt') {
        wp_die( __( 'Chasse non spécifiée ou invalide.', 'chassesautresor-com' ), 'Erreur', ['response' => 400] );
    }

    $enigme_id = creer_enigme_pour_chasse($chasse_id, get_current_user_id());

    if (is_wp_error($enigme_id)) {
        wp_die($enigme_id->get_error_message(), 'Erreur', ['response' => 500]);
    }

    // Redirige vers l’énigme en création
    $preview_url = add_query_arg('edition', 'open', get_preview_post_link($enigme_id));
    wp_redirect($preview_url);

    exit;
}
add_action('template_redirect', 'creer_enigme_et_rediriger_si_appel');


/**
 * 🔹 modifier_champ_enigme() → Gère l’enregistrement AJAX des champs ACF ou natifs du CPT énigme (post_title inclus).
 */
add_action('wp_ajax_modifier_champ_enigme', 'modifier_champ_enigme');


/**
 * @hook wp_ajax_modifier_champ_enigme
 */
function modifier_champ_enigme()
{
  check_ajax_referer('modifier_champ_enigme', 'nonce');

  if (!is_user_logged_in()) {
    wp_send_json_error('non_connecte');
  }

  $user_id = get_current_user_id();
  $champ = sanitize_text_field($_POST['champ'] ?? '');
  $valeur = $_POST['valeur'] ?? '';
  $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;

  if (!$champ || !$post_id || get_post_type($post_id) !== 'enigme') {
    wp_send_json_error('⚠️ donnees_invalides');
  }

  if (!utilisateur_peut_modifier_post($post_id)) {
    wp_send_json_error('⚠️ acces_refuse');
  }

  if (!utilisateur_peut_editer_champs($post_id)) {
    wp_send_json_error('⚠️ acces_refuse');
  }

  $ancien_complet = (bool) get_field('enigme_cache_complet', $post_id);
  $field_policy   = new ChassesAuTresor\Core\Content\RiddleFieldPolicyService();

  if (!$field_policy->isEditableField($champ)) {
    wp_send_json_error('⚠️ champ_interdit');
  }

  // 🔹 Bloc interdit (pre_requis manuel)
  if ($champ === 'enigme_acces_condition' && $field_policy->isForbiddenAccessCondition((string) $valeur)) {
    wp_send_json_error('⚠️ Interdit : cette valeur est gérée automatiquement.');
  }

  $mutation = (new ChassesAuTresor\Core\Content\RiddleMutationService())->apply(
    $post_id,
    $champ,
    $valeur,
    static function (string $date) {
      return convertir_en_datetime($date, [
        'Y-m-d\TH:i',
        'Y-m-d H:i:s',
        'Y-m-d H:i',
      ]);
    },
    strtotime(date('Y-m-d'))
  );

  if ($mutation['error'] !== null) {
    wp_send_json_error('⚠️ ' . $mutation['error']);
  }

  if ($mutation['refresh_state']) {
    enigme_mettre_a_jour_etat_systeme($post_id);
  }

  if ($mutation['terminal']) {
    wp_send_json_success(['champ' => $champ, 'valeur' => $valeur]);
  }

  if (function_exists('verifier_ou_mettre_a_jour_cache_complet')) {
    verifier_ou_mettre_a_jour_cache_complet($post_id);
  }
  $nouveau_complet = (bool) get_field('enigme_cache_complet', $post_id);
  $chasse_id = function_exists('recuperer_id_chasse_associee')
    ? (int) recuperer_id_chasse_associee($post_id)
    : 0;

  wp_send_json_success(
    (new ChassesAuTresor\Core\Content\RiddleManagementService())->getFieldUpdateResponse(
      $champ,
      $valeur,
      $ancien_complet,
      $nouveau_complet,
      $chasse_id
    )
  );
}


// ==================================================
// 📄 GESTION DU FICHIER DE SOLUTION (PDF)
// ==================================================
// 🔹 enregistrer_fichier_solution_enigme() → Enregistre un fichier PDF via AJAX
// 🔹 rediriger_upload_fichier_solution() → Redirige l’upload dans /protected/solutions/
// 🔹 deplacer_pdf_solution() → Déplace le PDF vers le dossier public si la chasse est terminée
// 🔹 planifier_ou_deplacer_pdf_solution_immediatement() → Programme le déplacement différé si nécessaire


/**
 * Enregistre un fichier PDF de solution transmis via AJAX (inline)
 *
 * @return void (JSON)
 */
add_action('wp_ajax_enregistrer_fichier_solution_enigme', 'enregistrer_fichier_solution_enigme');
function enregistrer_fichier_solution_enigme()
{
  $is_authenticated = is_user_logged_in();
  $post_id = $is_authenticated ? intval($_POST['post_id'] ?? 0) : 0;
  $has_valid_target = $post_id > 0 && get_post_type($post_id) === 'enigme';
  $is_authorized = $has_valid_target && utilisateur_peut_modifier_post($post_id);
  $action_error = (new ChassesAuTresor\Core\Content\RiddleActionPolicyService())->getError(
    $is_authenticated,
    $has_valid_target,
    $is_authorized
  );

  if ($action_error === 'authentication_required' || $action_error === 'forbidden') {
    wp_send_json_error(__('Non autorisé.', 'chassesautresor-com'));
  }

  if ($action_error === 'invalid_target') {
    wp_send_json_error(__('ID de post invalide.', 'chassesautresor-com'));
  }

  $result = (new ChassesAuTresor\Core\Content\RiddleSolutionUploadService())->process(
    $post_id,
    isset($_FILES['fichier_pdf']) ? (array) $_FILES['fichier_pdf'] : [],
    static function (array $file): array {
      return wp_check_filetype_and_ext(
        (string) ($file['tmp_name'] ?? ''),
        (string) ($file['name'] ?? '')
      );
    },
    static function (array $file): array {
      require_once ABSPATH . 'wp-admin/includes/file.php';
      add_filter('upload_dir', 'rediriger_upload_fichier_solution');
      $uploaded = wp_handle_upload($file, ['test_form' => false]);
      remove_filter('upload_dir', 'rediriger_upload_fichier_solution');

      return $uploaded;
    },
    static function (int $enigme_id, string $path, string $name, string $mime_type) {
      return (new ChassesAuTresor\Core\Content\RiddleSolutionAttachmentService())->attach(
        $enigme_id,
        $path,
        $name,
        $mime_type
      );
    },
    'is_wp_error',
    static fn ($error): string => $error->get_error_message()
  );

  if ($result['error'] !== null) {
    $messages = [
      'missing_file' => __('Fichier manquant ou erreur de transfert.', 'chassesautresor-com'),
      'file_too_large' => __('Fichier trop volumineux (5 Mo maximum).', 'chassesautresor-com'),
      'invalid_file_type' => __('Seuls les fichiers PDF sont autorisés.', 'chassesautresor-com'),
      'upload_failed' => __('Échec de l’upload.', 'chassesautresor-com'),
      'attachment_failed' => __('Échec de la création de la pièce jointe.', 'chassesautresor-com'),
    ];
    wp_send_json_error($result['message'] ?: $messages[$result['error']]);
  }

  wp_send_json_success(['fichier' => $result['url']]);
}

/**
 * Supprime le fichier PDF de solution via AJAX.
 *
 * @return void (JSON)
 */
add_action('wp_ajax_supprimer_fichier_solution_enigme', 'supprimer_fichier_solution_enigme');
function supprimer_fichier_solution_enigme()
{
  $is_authenticated = is_user_logged_in();
  $post_id = $is_authenticated ? intval($_POST['post_id'] ?? 0) : 0;
  $has_valid_target = $post_id > 0 && get_post_type($post_id) === 'enigme';
  $is_authorized = $has_valid_target && utilisateur_peut_modifier_post($post_id);
  $action_error = (new ChassesAuTresor\Core\Content\RiddleActionPolicyService())->getError(
    $is_authenticated,
    $has_valid_target,
    $is_authorized
  );

  if ($action_error === 'authentication_required' || $action_error === 'forbidden') {
    wp_send_json_error(__('Non autorisé.', 'chassesautresor-com'));
  }

  if ($action_error === 'invalid_target') {
    wp_send_json_error(__('ID de post invalide.', 'chassesautresor-com'));
  }

  (new ChassesAuTresor\Core\Content\RiddleSolutionAttachmentService())->remove($post_id);

  wp_send_json_success();
}

/**
 * Redirige temporairement les fichiers uploadés vers /wp-content/protected/solutions/
 *
 * Ce filtre est utilisé uniquement lors de l’upload d’un fichier PDF de solution,
 * afin de l’enregistrer dans un dossier non public.
 *
 * @param array $dirs Les chemins d’upload par défaut
 * @return array Les chemins modifiés
 */
function rediriger_upload_fichier_solution($dirs)
{
    return (new ChassesAuTresor\Core\Content\RiddleSolutionFileStorageService())
        ->prepareUploadDirectory($dirs, WP_CONTENT_DIR);
}


/**
 * Déplace un fichier PDF de solution vers un répertoire public,
 * uniquement si la chasse est terminée et que le fichier n’a pas encore été déplacé.
 *
 * @param int $enigme_id ID du post de type "enigme"
 */
function deplacer_pdf_solution($enigme_id)
{
  ChassesAuTresor\Core\Content\RiddleSolutionFilePublicationService::publish((int) $enigme_id);
}


/**
 * Déclenche immédiatement ou planifie le déplacement du PDF selon le délai.
 *
 * Cette fonction est appelée lorsque le statut devient "termine".
 * Le déplacement est différé dans tous les cas (5 secondes minimum).
 */
function planifier_ou_deplacer_pdf_solution_immediatement($enigme_id)
{
  ChassesAuTresor\Core\Content\RiddleSolutionFileScheduler::schedule((int) $enigme_id);
}

/**
 * Supprime récursivement le dossier dédié à une énigme dans /uploads/_enigmes/.
 *
 * @param int $post_id ID de l'énigme.
 * @return void
 */
function supprimer_dossier_enigme($post_id)
{
  $upload_dir = wp_upload_dir();
  (new ChassesAuTresor\Core\Media\RiddleUploadDirectoryService())->delete(
    (int) $post_id,
    (string) ($upload_dir['basedir'] ?? '')
  );
}

/**
 * Gère la suppression d'une énigme via AJAX.
 *
 * @hook wp_ajax_supprimer_enigme
 * @return void
 */
function supprimer_enigme_ajax()
{
  check_ajax_referer('supprimer_enigme', 'nonce');

  $is_authenticated = is_user_logged_in();
  $post_id = $is_authenticated && isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
  $has_valid_target = $post_id > 0 && get_post_type($post_id) === 'enigme';
  $is_authorized = $has_valid_target
    && utilisateur_peut_supprimer_enigme($post_id, get_current_user_id());
  $action_error = (new ChassesAuTresor\Core\Content\RiddleActionPolicyService())->getError(
    $is_authenticated,
    $has_valid_target,
    $is_authorized
  );

  if ($action_error === 'authentication_required') {
    wp_send_json_error('non_connecte');
  }

  if ($action_error === 'invalid_target') {
    wp_send_json_error('id_invalide');
  }

  if ($action_error === 'forbidden') {
    wp_send_json_error('acces_refuse');
  }

  $chasse_id = recuperer_id_chasse_associee($post_id);
  $redirect  = $chasse_id ? get_permalink($chasse_id) : home_url('/');

  $upload_dir = wp_upload_dir();
  $deleted = (new ChassesAuTresor\Core\Content\RiddleDeletionService())->delete(
    $post_id,
    (string) ($upload_dir['basedir'] ?? '')
  );
  if (!$deleted) {
    wp_send_json_error('echec_suppression');
  }

  wp_send_json_success(['redirect' => $redirect]);
}
add_action('wp_ajax_supprimer_enigme', 'supprimer_enigme_ajax');

/**
 * Vérifie s'il reste des énigmes incomplètes pour une chasse.
 *
 * @hook wp_ajax_verifier_enigmes_completes
 * @return void
 */
function verifier_enigmes_completes_ajax()
{
    if (!is_user_logged_in()) {
        wp_send_json_error('non_connecte');
    }

    $chasse_id = isset($_POST['chasse_id']) ? (int) $_POST['chasse_id'] : 0;
    if (!$chasse_id || get_post_type($chasse_id) !== 'chasse') {
        wp_send_json_error('id_invalide');
    }

    $can_add = function_exists('utilisateur_peut_ajouter_enigme')
        ? utilisateur_peut_ajouter_enigme($chasse_id)
        : false;

    wp_send_json_success(
        (new ChassesAuTresor\Core\Content\RiddleCompletionService())->evaluateManagementStatus(
            recuperer_enigmes_associees($chasse_id),
            $can_add,
            static function (int $enigme_id): void {
                verifier_ou_mettre_a_jour_cache_complet($enigme_id);
            },
            static fn (int $enigme_id): bool => (bool) get_field('enigme_cache_complet', $enigme_id)
        )
    );
}
add_action('wp_ajax_verifier_enigmes_completes', 'verifier_enigmes_completes_ajax');

/**
 * Réordonne les énigmes d'une chasse via menu_order.
 *
 * @hook wp_ajax_reordonner_enigmes
 * @return void
 */
function reordonner_enigmes_ajax()
{
    check_ajax_referer('reordonner_enigmes', 'nonce');

    $is_authenticated = is_user_logged_in();
    $chasse_id = $is_authenticated && isset($_POST['chasse_id']) ? (int) $_POST['chasse_id'] : 0;
    $has_valid_target = $chasse_id > 0 && get_post_type($chasse_id) === 'chasse';
    $is_authorized = $has_valid_target
        && utilisateur_est_organisateur_associe_a_chasse(get_current_user_id(), $chasse_id);
    $action_error = (new ChassesAuTresor\Core\Content\RiddleActionPolicyService())->getError(
        $is_authenticated,
        $has_valid_target,
        $is_authorized
    );

    if ($action_error === 'authentication_required') {
        wp_send_json_error('non_connecte');
    }

    $ordre     = isset($_POST['ordre']) ? array_map('intval', (array) $_POST['ordre']) : [];

    if ($action_error === 'invalid_target') {
        wp_send_json_error('id_invalide');
    }

    if ($action_error === 'forbidden') {
        wp_send_json_error('non_autorise');
    }

    (new ChassesAuTresor\Core\Content\RiddleOrderingService())->apply(
        $ordre,
        recuperer_enigmes_associees($chasse_id),
        static function (int $enigme_id, int $menu_order): void {
            wp_update_post([
                'ID' => $enigme_id,
                'menu_order' => $menu_order,
            ]);
        }
    );

    if (function_exists('synchroniser_cache_enigmes_chasse')) {
        synchroniser_cache_enigmes_chasse($chasse_id, true, true);
    }

    wp_send_json_success();
}
add_action('wp_ajax_reordonner_enigmes', 'reordonner_enigmes_ajax');

// ==================================================
// 🧩 PRÉREMPLISSAGE & FILTRES ACF (ÉNIGME)
// ==================================================
// 🔹 acf/load_field/name=chasse_associee → Préremplit le champ à la création
// 🔹 acf/fields/relationship/query → Limite les choix de "pre_requis" à la même chasse
// 🔹 acf/save_post → Lors de la création, ajoute l’énigme à la chasse associée
// 🔹 before_delete_post → Supprime proprement l’énigme de la chasse liée
// 🔹 nettoyer_relations_orphelines() → Supprime les relations ACF vers des énigmes supprimées

/**
 * 📌 Pré-remplit le champ "chasse_associee" uniquement en création.
 *
 * @param array $field Informations du champ ACF.
 * @return array Champ modifié.
 */
add_filter('acf/load_field/name=chasse_associee', function ($field) {
  global $post;

  // Vérifier si on est bien dans une énigme
  if (!$post || get_post_type($post->ID) !== 'enigme') {
    return $field;
  }

  // 🔹 Vérifier si une valeur existe déjà en base sans provoquer de boucle
  $chasse_id_en_base = get_post_meta($post->ID, 'chasse_associee', true);
  if (!empty($chasse_id_en_base)) {
    return $field;
  }

  // 🔹 Récupérer l'ID de la chasse associée uniquement en création
  $chasse_id = recuperer_id_chasse_associee($post->ID);
  if ($chasse_id) {
    $field['value'] = $chasse_id;
  }

  return $field;
});


/**
 * Filtre les énigmes affichées dans le champ ACF "pre_requis" pour n'afficher 
 * que celles de la même chasse (en excluant l’énigme en cours).
 *
 * @param array  $args     Arguments de la requête ACF.
 * @param array  $field    Informations du champ ACF.
 * @param int    $post_id  ID du post en cours d'édition.
 * @return array Arguments modifiés pour ACF.
 */
add_filter('acf/fields/relationship/query', function ($args, $field, $post_id) {
  if ($field['name'] !== 'pre_requis') {
    return $args;
  }

  $chasse_id = recuperer_id_chasse_associee($post_id);
  if (!$chasse_id) {
    return $args;
  }

  $args['post__in'] = (new ChassesAuTresor\Core\Content\RiddleRelationshipService())
    ->getSelectableRiddleIds(recuperer_enigmes_associees($chasse_id), (int) $post_id);

  return $args;
}, 10, 3);

/**
 * 📌 Lors de la création ou modification d'une énigme,
 * ajoute automatiquement cette énigme à la relation ACF "chasse_cache_enigmes"
 * du CPT chasse correspondant.
 *
 * @hook acf/save_post
 *
 * @param int|string $post_id ID du post ACF.
 * @return void
 */
add_action('acf/save_post', function ($post_id) {
  if (!is_numeric($post_id) || get_post_type($post_id) !== 'enigme') return;
  if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return;

  (new ChassesAuTresor\Core\Content\RiddleRelationshipLifecycleService())->attach(
    (int) $post_id,
    get_field('enigme_chasse_associee', $post_id),
    static fn (int $chasse_id): bool => get_post_type($chasse_id) === 'chasse',
    static function (int $chasse_id, int $enigme_id): bool {
      $success = modifier_relation_acf(
        $chasse_id,
        'chasse_cache_enigmes',
        $enigme_id,
        'field_67b740025aae0',
        'add'
      );

      cat_debug(
        $success
          ? "✅ Énigme $enigme_id ajoutée à la chasse $chasse_id"
          : "❌ Échec ajout énigme $enigme_id à la chasse $chasse_id"
      );

      return $success;
    }
  );
}, 20);


/**
 * 🧹 Nettoyer les relations ACF orphelines dans le champ `chasse_cache_enigmes`.
 *
 * Cette fonction parcourt toutes les chasses possédant des valeurs dans le champ ACF
 * `chasse_cache_enigmes`, et supprime les références à des énigmes qui ont été supprimées.
 *
 * ⚠️ Cette vérification est utile notamment lorsqu'on supprime une énigme manuellement
 * ou que la cohérence de la relation ACF est rompue.
 *
 * - Utilise `$wpdb` pour récupérer toutes les valeurs brutes
 * - Applique un `array_filter` pour ne garder que les IDs encore existants
 * - Met à jour le champ uniquement s'il y a eu des suppressions
 *
 * @return void
 */
function nettoyer_relations_orphelines()
{
  global $wpdb;

  // 🔍 Récupérer toutes les chasses ayant des relations
  $chasses = $wpdb->get_results("
        SELECT post_id, meta_value 
        FROM {$wpdb->postmeta} 
        WHERE meta_key = 'chasse_cache_enigmes'
    ");

  (new ChassesAuTresor\Core\Content\RiddleRelationshipCleanupService())->clean(
    $chasses,
    'maybe_unserialize',
    function (int $enigme_id) use ($wpdb): bool {
      return (bool) $wpdb->get_var(
        $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE ID = %d", $enigme_id)
      );
    },
    static function (int $post_id, array $relations): void {
      update_post_meta($post_id, 'chasse_cache_enigmes', $relations);
      cat_debug("✅ Relations nettoyées pour la chasse ID {$post_id} : " . print_r($relations, true));
    }
  );
}

/**
 * 🧩 Gérer la suppression d'une énigme : mise à jour des relations dans la chasse associée.
 *
 * Cette fonction est déclenchée automatiquement **avant la suppression** d’un post.
 * Si le post supprimé est de type `enigme`, elle effectue :
 *
 * 1. 🔄 La suppression de l’ID de l’énigme dans le champ relation ACF
 *    `chasse_cache_enigmes` de la chasse associée, via `modifier_relation_acf()`.
 *
 * 2. 🧹 Un nettoyage global des champs relationnels dans toutes les chasses,
 *    pour supprimer les références à des énigmes qui n’existent plus,
 *    via `nettoyer_relations_orphelines()`.
 *
 * @param int $post_id L’ID du post en cours de suppression.
 * @return void
 *
 * @hook before_delete_post
 */
add_action('before_delete_post', function ($post_id) {
  if (get_post_type($post_id) !== 'enigme') {
    return;
  }

  (new ChassesAuTresor\Core\Content\RiddleRelationshipLifecycleService())->detach(
    (int) $post_id,
    get_field('enigme_chasse_associee', $post_id) ?: get_field('chasse_associee', $post_id),
    static function (int $chasse_id, int $enigme_id): bool {
      return modifier_relation_acf(
        $chasse_id,
        'chasse_cache_enigmes',
        $enigme_id,
        'field_67b740025aae0',
        'remove'
      );
    },
    'nettoyer_relations_orphelines'
  );
});
