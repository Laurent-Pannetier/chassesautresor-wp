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

if (!class_exists(ChassesAuTresor\Core\Media\RiddleUploadDirectoryService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Media/RiddleUploadDirectoryService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\RiddleManagementService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/RiddleManagementService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\RiddleDeletionService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/RiddleDeletionService.php';
}

if (!class_exists(ChassesAuTresor\Core\Content\RiddleCompletionService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/RiddleCompletionService.php';
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
  if (get_post_type($chasse_id) !== 'chasse') {
    return new WP_Error(
      'chasse_invalide',
      __('ID de chasse invalide.', 'chassesautresor-com')
    );
  }

  if (is_null($user_id)) {
    $user_id = get_current_user_id();
  }

  if (!$user_id || !get_userdata($user_id)) {
    return new WP_Error(
      'utilisateur_invalide',
      __('Utilisateur non connecté.', 'chassesautresor-com')
    );
  }

  $organisateur_id = get_organisateur_from_chasse($chasse_id);
  if (!$organisateur_id) {
    return new WP_Error(
      'organisateur_introuvable',
      __('Organisateur non lié à cette chasse.', 'chassesautresor-com')
    );
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
  add_rewrite_rule(
    '^creer-enigme/?',
    'index.php?creer_enigme=1',
    'top'
  );
  add_rewrite_tag('%creer_enigme%', '1');
}
add_action('init', 'register_endpoint_creer_enigme');


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

    // Vérification de l’utilisateur
    if (!is_user_logged_in()) {
        wp_redirect(wp_login_url());
        exit;
    }

    $user_id = get_current_user_id();
    $chasse_id = isset($_GET['chasse_id']) ? absint($_GET['chasse_id']) : 0;

    if (!$chasse_id || get_post_type($chasse_id) !== 'chasse') {
        wp_die( __( 'Chasse non spécifiée ou invalide.', 'chassesautresor-com' ), 'Erreur', ['response' => 400] );
    }

    $enigme_id = creer_enigme_pour_chasse($chasse_id, $user_id);

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

  $champ_valide    = false;
  $reponse         = ['champ' => $champ, 'valeur' => $valeur];
  $ancien_complet  = (bool) get_field('enigme_cache_complet', $post_id);
  $field_policy    = new ChassesAuTresor\Core\Content\RiddleFieldPolicyService();

  // 🔹 Bloc interdit (pre_requis manuel)
  if ($champ === 'enigme_acces_condition' && $field_policy->isForbiddenAccessCondition((string) $valeur)) {
    wp_send_json_error('⚠️ Interdit : cette valeur est gérée automatiquement.');
  }

  // 🔹 Titre natif
  if ($champ === 'post_title') {
    $ok = wp_update_post(['ID' => $post_id, 'post_title' => sanitize_text_field($valeur)], true);
    if (is_wp_error($ok)) {
      wp_send_json_error('⚠️ echec_update_post_title');
    }
    wp_send_json_success($reponse);
  }

  // 🔹 Mode de validation
  if ($champ === 'enigme_mode_validation') {
    $ok = update_field($champ, sanitize_text_field($valeur), $post_id);
    if ($ok) $champ_valide = true;
    enigme_mettre_a_jour_etat_systeme($post_id);
  }

  // 🔹 Réponse attendue (liste JSON)
  if ($champ === 'enigme_reponse_bonne') {
    $liste = json_decode(wp_unslash($valeur), true);
    if (!is_array($liste)) {
      wp_send_json_error('⚠️ format_invalide');
    }

    $liste = $field_policy->normalizeAnswers($liste, 'sanitize_text_field');
    $answer_error = $field_policy->getAnswersError($liste);
    if ($answer_error !== null) {
      wp_send_json_error('⚠️ ' . $answer_error);
    }

    $ok = update_field($champ, wp_json_encode($liste), $post_id);
    if ($ok) {
      $champ_valide = true;
    }
    enigme_mettre_a_jour_etat_systeme($post_id);
  }

  // 🔹 Casse
  if ($champ === 'enigme_reponse_casse') {
    $ok = update_field($champ, (int) $valeur, $post_id);
    if ($ok) $champ_valide = true;
  }


  // 🔹 Tentatives (coût et max)
  $attempt_field = $field_policy->getAttemptStorageField($champ);
  if ($attempt_field !== null) {
    $champ_valide = update_field($attempt_field, (int) $valeur, $post_id) !== false;
  }

  // 🔹 Accès : condition (immédiat, date_programmee uniquement)
  if (
    $champ === 'enigme_acces_condition'
    && $field_policy->isAllowedManualAccessCondition(sanitize_text_field($valeur))
  ) {
    $ok = update_field($champ, sanitize_text_field($valeur), $post_id);
    if ($ok) $champ_valide = true;
  }

  // 🔹 Accès : date
  if ($champ === 'enigme_acces_date') {
    $dt = convertir_en_datetime(sanitize_text_field($valeur), [
      'Y-m-d\TH:i',
      'Y-m-d H:i:s',
      'Y-m-d H:i'
    ]);
    if (!$dt) {
      wp_send_json_error('⚠️ format_date_invalide');
    }

    $timestamp = $dt->getTimestamp();
    $valeur_mysql = $dt->format('Y-m-d H:i:s');
    $today = strtotime(date('Y-m-d'));
    $mode = get_field('enigme_acces_condition', $post_id);

    if ($field_policy->shouldResetScheduledAccess($timestamp, $today, (string) $mode)) {
      update_field('enigme_acces_condition', 'immediat', $post_id);
    }

    $ok = update_field($champ, $valeur_mysql, $post_id);
    if ($ok) {
      $champ_valide = true;
    }

    enigme_mettre_a_jour_etat_systeme($post_id);
  }

    // 🔹 Accès : pré-requis (liste d'IDs)
    if ($champ === 'enigme_acces_pre_requis') {
        $ids = $field_policy->normalizePrerequisiteIds($valeur);

        $ok = update_field($champ, $ids, $post_id);
        if ($ok) {
            $champ_valide = true;
            $condition = $field_policy->getAccessConditionForPrerequisites($ids);
            update_field('enigme_acces_condition', $condition, $post_id);
            enigme_mettre_a_jour_etat_systeme($post_id);
        }
    }

  // 🔹 Style visuel
  if ($champ === 'enigme_style_affichage') {
    $ok = update_field($champ, sanitize_text_field($valeur), $post_id);
    if ($ok) $champ_valide = true;
  }

  // 🔹 Fallback
  if (!$champ_valide) {
    $valeur_saine = is_numeric($valeur) ? (int) $valeur : sanitize_text_field($valeur);
    $ok = update_field($champ, $valeur_saine, $post_id);
    $valeur_meta = get_post_meta($post_id, $champ, true);
    if ($ok || trim((string) $valeur_meta) === trim((string) $valeur_saine)) {
      $champ_valide = true;
    } else {
      wp_send_json_error('⚠️ echec_mise_a_jour_final');
    }
  }

  if (function_exists('verifier_ou_mettre_a_jour_cache_complet')) {
    verifier_ou_mettre_a_jour_cache_complet($post_id);
  }
  $nouveau_complet              = (bool) get_field('enigme_cache_complet', $post_id);
  $reponse['complet']           = $nouveau_complet;
  $reponse['complet_changed']   = $ancien_complet !== $nouveau_complet;
  $reponse['chasse_id']         = function_exists('recuperer_id_chasse_associee')
    ? (int) recuperer_id_chasse_associee($post_id)
    : 0;

  wp_send_json_success($reponse);
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
  if (!is_user_logged_in()) {
    wp_send_json_error(__('Non autorisé.', 'chassesautresor-com'));
  }

  $post_id = intval($_POST['post_id'] ?? 0);
  if (!$post_id || get_post_type($post_id) !== 'enigme') {
    wp_send_json_error(__('ID de post invalide.', 'chassesautresor-com'));
  }

  if (!utilisateur_peut_modifier_post($post_id)) {
    wp_send_json_error(__('Non autorisé.', 'chassesautresor-com'));
  }

  if (empty($_FILES['fichier_pdf']) || $_FILES['fichier_pdf']['error'] !== 0) {
    wp_send_json_error(__('Fichier manquant ou erreur de transfert.', 'chassesautresor-com'));
  }

  $fichier = $_FILES['fichier_pdf'];

  $filetype = wp_check_filetype($fichier['name']);
  $filePolicy = new ChassesAuTresor\Core\Content\RiddleSolutionFilePolicyService();
  $uploadError = $filePolicy->getUploadError(
      (int) $fichier['size'],
      (string) ($filetype['ext'] ?? ''),
      (string) ($filetype['type'] ?? '')
  );
  if ($uploadError !== null) {
    $messages = [
      'file_too_large' => __('Fichier trop volumineux (5 Mo maximum).', 'chassesautresor-com'),
      'invalid_file_type' => __('Seuls les fichiers PDF sont autorisés.', 'chassesautresor-com'),
    ];
    wp_send_json_error($messages[$uploadError]);
  }

  require_once ABSPATH . 'wp-admin/includes/file.php';

  $overrides = ['test_form' => false];

  add_filter('upload_dir', 'rediriger_upload_fichier_solution');
  $uploaded = wp_handle_upload($fichier, $overrides);
  remove_filter('upload_dir', 'rediriger_upload_fichier_solution');


  if (!isset($uploaded['url']) || !isset($uploaded['file'])) {
    wp_send_json_error(
      $uploaded['error'] ?? __('Échec de l’upload.', 'chassesautresor-com')
    );
  }

  $attach_id = (new ChassesAuTresor\Core\Content\RiddleSolutionAttachmentService())->attach(
    $post_id,
    $uploaded['file'],
    $fichier['name'],
    $filetype['type']
  );
  if (is_wp_error($attach_id)) {
    wp_send_json_error($attach_id->get_error_message());
  }

  wp_send_json_success([
    'fichier' => $uploaded['url']
  ]);
}

/**
 * Supprime le fichier PDF de solution via AJAX.
 *
 * @return void (JSON)
 */
add_action('wp_ajax_supprimer_fichier_solution_enigme', 'supprimer_fichier_solution_enigme');
function supprimer_fichier_solution_enigme()
{
  if (!is_user_logged_in()) {
    wp_send_json_error(__('Non autorisé.', 'chassesautresor-com'));
  }

  $post_id = intval($_POST['post_id'] ?? 0);
  if (!$post_id || get_post_type($post_id) !== 'enigme') {
    wp_send_json_error(__('ID de post invalide.', 'chassesautresor-com'));
  }

  if (!utilisateur_peut_modifier_post($post_id)) {
    wp_send_json_error(__('Non autorisé.', 'chassesautresor-com'));
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
  if (!is_user_logged_in()) {
    wp_send_json_error('non_connecte');
  }

  $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
  if (!$post_id || get_post_type($post_id) !== 'enigme') {
    wp_send_json_error('id_invalide');
  }

  $user_id = get_current_user_id();
  if (!utilisateur_peut_supprimer_enigme($post_id, $user_id)) {
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

    $ids = recuperer_enigmes_associees($chasse_id);
    $completionFlags = [];
    foreach ($ids as $eid) {
        verifier_ou_mettre_a_jour_cache_complet($eid);
        $isComplete = (bool) get_field('enigme_cache_complet', $eid);
        $completionFlags[] = $isComplete;
        if (!$isComplete) {
            break;
        }
    }

    $can_add = function_exists('utilisateur_peut_ajouter_enigme')
        ? utilisateur_peut_ajouter_enigme($chasse_id)
        : false;

    wp_send_json_success(
        (new ChassesAuTresor\Core\Content\RiddleCompletionService())->getManagementStatus(
            $completionFlags,
            $can_add
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
    if (!is_user_logged_in()) {
        wp_send_json_error('non_connecte');
    }

    $chasse_id = isset($_POST['chasse_id']) ? (int) $_POST['chasse_id'] : 0;
    $ordre     = isset($_POST['ordre']) ? array_map('intval', (array) $_POST['ordre']) : [];

    if (!$chasse_id || get_post_type($chasse_id) !== 'chasse') {
        wp_send_json_error('id_invalide');
    }

    if (!utilisateur_est_organisateur_associe_a_chasse(get_current_user_id(), $chasse_id)) {
        wp_send_json_error('non_autorise');
    }

    $allowedOrder = (new ChassesAuTresor\Core\Content\RiddleManagementService())->getReorderUpdates(
        $ordre,
        recuperer_enigmes_associees($chasse_id)
    );
    foreach ($allowedOrder as $index => $enigme_id) {
        wp_update_post([
            'ID'         => $enigme_id,
            'menu_order' => $index,
        ]);
    }

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

  // 🔎 Récupère la chasse associée à l’énigme
  $chasse = get_field('enigme_chasse_associee', $post_id);

  $chasse_id = (new ChassesAuTresor\Core\Content\RiddleRelationshipService())
    ->resolveHuntId($chasse);

  if (!$chasse_id || get_post_type($chasse_id) !== 'chasse') return;

  // ✅ Ajoute l’ID de l’énigme à la relation "chasse_cache_enigmes"
  $success = modifier_relation_acf(
    $chasse_id,
    'chasse_cache_enigmes',
    $post_id,
    'field_67b740025aae0',
    'add'
  );

  if ($success) {
    cat_debug("✅ Énigme $post_id ajoutée à la chasse $chasse_id");
  } else {
    cat_debug("❌ Échec ajout énigme $post_id à la chasse $chasse_id");
  }
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

  // 🔹 Récupérer la chasse associée
  $chasse_id = get_field('chasse_associee', $post_id);
  if (!$chasse_id) {
    return;
  }

  // 🔹 Supprimer proprement la relation avec l’énigme supprimée
  $acf_key = 'field_67b740025aae0'; // Clé exacte du champ `chasse_cache_enigmes`
  modifier_relation_acf($chasse_id, 'chasse_cache_enigmes', $post_id, $acf_key, 'remove');

  // 🔹 Nettoyer les relations orphelines (toutes les chasses)
  nettoyer_relations_orphelines();
});
