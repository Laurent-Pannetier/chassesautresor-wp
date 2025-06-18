<?php
defined('ABSPATH') || exit;


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

  $theme_uri = get_stylesheet_directory_uri();
  $theme_dir = get_stylesheet_directory();

  // 📦 Modules JS partagés
  enqueue_core_edit_scripts();

  // 📤 Header organisateur
  $path_org = '/assets/js/organisateur-edit.js';
  $version_org = file_exists($theme_dir . $path_org) ? filemtime($theme_dir . $path_org) : null;

  wp_enqueue_script(
    'organisateur-edit',
    $theme_uri . $path_org,
    ['champ-init', 'helpers', 'ajax', 'ui'],
    $version_org,
    true
  );

  // 📤 Panneau énigme
  $path_enigme = '/assets/js/enigme-edit.js';
  $version_enigme = file_exists($theme_dir . $path_enigme) ? filemtime($theme_dir . $path_enigme) : null;

  wp_enqueue_script(
    'enigme-edit',
    $theme_uri . $path_enigme,
    ['champ-init', 'helpers', 'ajax', 'ui'],
    $version_enigme,
    true
  );

  // Localisation JS si besoin (ex : valeurs par défaut)
  wp_localize_script('champ-init', 'CHP_ENIGME_DEFAUT', [
    'titre' => 'nouvelle énigme',
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
    return new WP_Error('chasse_invalide', 'ID de chasse invalide.');
  }

  if (is_null($user_id)) {
    $user_id = get_current_user_id();
  }

  if (!$user_id || !get_userdata($user_id)) {
    return new WP_Error('utilisateur_invalide', 'Utilisateur non connecté.');
  }

  $organisateur_id = get_organisateur_from_chasse($chasse_id);
  if (!$organisateur_id) {
    return new WP_Error('organisateur_introuvable', 'Organisateur non lié à cette chasse.');
  }

  $enigme_id = wp_insert_post([
    'post_type'   => 'enigme',
    'post_status' => 'pending',
    'post_title'  => 'Nouvelle énigme',
    'post_author' => $user_id,
  ]);

  if (is_wp_error($enigme_id)) {
    return $enigme_id;
  }

  if (get_option('chasse_associee_temp')) {
    delete_option('chasse_associee_temp');
  }

  // 🧩 Champs ACF de base
  update_field('enigme_chasse_associee', $chasse_id, $enigme_id);
  update_field('enigme_organisateur_associe', $organisateur_id, $enigme_id);

  update_field('enigme_tentative', [
    'enigme_tentative_cout_points' => 0,
    'enigme_tentative_max' => 5,
  ], $enigme_id);

  update_field('enigme_reponse_casse', true, $enigme_id);
  update_field('enigme_acces_condition', 'immediat', $enigme_id);
  update_field('enigme_acces_pre_requis', [], $enigme_id);

  $date_deblocage = (new DateTime('+1 month'))->format('Y-m-d');
  update_field('enigme_acces_date', $date_deblocage, $enigme_id);

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

  // Vérification de l’utilisateur
  if (!is_user_logged_in()) {
    wp_redirect(wp_login_url());
    exit;
  }

  $user_id = get_current_user_id();
  $chasse_id = isset($_GET['chasse_id']) ? absint($_GET['chasse_id']) : 0;

  if (!$chasse_id || get_post_type($chasse_id) !== 'chasse') {
    wp_die('Chasse non spécifiée ou invalide.', 'Erreur', ['response' => 400]);
  }

  $enigme_id = creer_enigme_pour_chasse($chasse_id, $user_id);

  if (is_wp_error($enigme_id)) {
    wp_die($enigme_id->get_error_message(), 'Erreur', ['response' => 500]);
  }

  // Redirige vers la nouvelle énigme
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
  $valeur = wp_kses_post($_POST['valeur'] ?? '');
  $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;

  if (!$champ || !$post_id || get_post_type($post_id) !== 'enigme') {
    wp_send_json_error('⚠️ donnees_invalides');
  }

  $auteur = (int) get_post_field('post_author', $post_id);
  if ($auteur !== $user_id) {
    wp_send_json_error('⚠️ acces_refuse');
  }

  $champ_valide = false;
  $reponse = ['champ' => $champ, 'valeur' => $valeur];

  // 🔹 Bloc interdit (pre_requis manuel)
  if ($champ === 'enigme_acces_condition' && $valeur === 'pre_requis') {
    wp_send_json_error('⚠️ Interdit : cette valeur est gérée automatiquement.');
  }

  // 🔹 Titre natif
  if ($champ === 'post_title') {
    $ok = wp_update_post(['ID' => $post_id, 'post_title' => $valeur], true);
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

  // 🔹 Réponse attendue
  if ($champ === 'enigme_reponse_bonne') {
    if (strlen($valeur) > 75) {
      wp_send_json_error('⚠️ La réponse ne peut dépasser 75 caractères.');
    }
    $ok = update_field($champ, sanitize_text_field($valeur), $post_id);
    if ($ok) $champ_valide = true;
    enigme_mettre_a_jour_etat_systeme($post_id);
  }

  // 🔹 Casse
  if ($champ === 'enigme_reponse_casse') {
    $ok = update_field($champ, (int) $valeur, $post_id);
    if ($ok) $champ_valide = true;
  }

  // 🔹 Variantes
  if ($champ === 'enigme_reponse_variantes') {
    $donnees = json_decode(stripslashes($valeur), true);
    if (!is_array($donnees)) {
      wp_send_json_error('⚠️ format_invalide_variantes');
    }
    $formatees = [];
    foreach ($donnees as $cle => $sous) {
      $index = (int) filter_var($cle, FILTER_SANITIZE_NUMBER_INT);
      $formatees["variante_{$index}"] = [
        "texte_{$index}" => sanitize_text_field($sous["texte_{$index}"] ?? ''),
        "message_{$index}" => sanitize_text_field($sous["message_{$index}"] ?? ''),
        "respecter_casse_{$index}" => (int) ($sous["respecter_casse_{$index}"] ?? 0)
      ];
    }
    delete_field($champ, $post_id);
    update_field($champ, $formatees, $post_id);
    $champ_valide = true;
  }

  // 🔹 Tentatives (coût et max)
  if ($champ === 'enigme_tentative.enigme_tentative_cout_points') {
    $champ_valide = mettre_a_jour_sous_champ_group($post_id, 'enigme_tentative', 'enigme_tentative_cout_points', (int) $valeur);
  }

  if ($champ === 'enigme_tentative.enigme_tentative_max') {
    $champ_valide = mettre_a_jour_sous_champ_group($post_id, 'enigme_tentative', 'enigme_tentative_max', (int) $valeur);
  }

  // 🔹 Accès : condition (immédiat, date_programmee uniquement)
  if ($champ === 'enigme_acces_condition' && in_array($valeur, ['immediat', 'date_programmee'])) {
    $ok = update_field($champ, $valeur, $post_id);
    if ($ok) $champ_valide = true;
  }

  // 🔹 Accès : date
  if ($champ === 'enigme_acces_date') {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $valeur)) {
      wp_send_json_error('⚠️ format_date_invalide');
    }

    $timestamp = strtotime($valeur);
    $today = strtotime(date('Y-m-d'));
    $mode = get_field('enigme_acces_condition', $post_id);

    if ($timestamp && $timestamp < $today && $mode === 'date_programmee') {
      update_field('enigme_acces_condition', 'immediat', $post_id);
    }

    $ok = update_field($champ, $valeur, $post_id);
    $relue = get_field($champ, $post_id);
    if ($ok || substr($relue, 0, 10) === $valeur) {
      $champ_valide = true;
    }

    enigme_mettre_a_jour_etat_systeme($post_id);
  }

  // 🔹 Style visuel
  if ($champ === 'enigme_style_affichage') {
    $ok = update_field($champ, sanitize_text_field($valeur), $post_id);
    if ($ok) $champ_valide = true;
  }

  // 🔹 Fallback
  if (!$champ_valide) {
    $ok = update_field($champ, is_numeric($valeur) ? (int) $valeur : $valeur, $post_id);
    $valeur_meta = get_post_meta($post_id, $champ, true);
    if ($ok || trim((string) $valeur_meta) === trim((string) $valeur)) {
      $champ_valide = true;
    } else {
      wp_send_json_error('⚠️ echec_mise_a_jour_final');
    }
  }

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
  if (!current_user_can('edit_posts')) {
    wp_send_json_error("Non autorisé.");
  }

  $post_id = intval($_POST['post_id'] ?? 0);
  if (!$post_id || get_post_type($post_id) !== 'enigme') {
    wp_send_json_error("ID de post invalide.");
  }

  if (empty($_FILES['fichier_pdf']) || $_FILES['fichier_pdf']['error'] !== 0) {
    wp_send_json_error("Fichier manquant ou erreur de transfert.");
  }

  $fichier = $_FILES['fichier_pdf'];

  // 🔒 Contrôle taille max : 5 Mo
  if ($fichier['size'] > 5 * 1024 * 1024) {
    wp_send_json_error("Fichier trop volumineux (5 Mo maximum).");
  }

  // 🔒 Vérification réelle du type MIME
  $filetype = wp_check_filetype($fichier['name']);
  if ($filetype['ext'] !== 'pdf' || $filetype['type'] !== 'application/pdf') {
    wp_send_json_error("Seuls les fichiers PDF sont autorisés.");
  }

  require_once ABSPATH . 'wp-admin/includes/file.php';

  $overrides = ['test_form' => false];

  add_filter('upload_dir', 'rediriger_upload_fichier_solution');
  $uploaded = wp_handle_upload($fichier, $overrides);
  remove_filter('upload_dir', 'rediriger_upload_fichier_solution');


  if (!isset($uploaded['url']) || !isset($uploaded['file'])) {
    wp_send_json_error("Échec de l’upload.");
  }

  // 📝 Création de la pièce jointe
  $attachment = [
    'post_mime_type' => $filetype['type'],
    'post_title'     => sanitize_file_name($fichier['name']),
    'post_content'   => '',
    'post_status'    => 'inherit'
  ];

  $attach_id = wp_insert_attachment($attachment, $uploaded['file'], $post_id);
  if (strpos($filetype['type'], 'image/') === 0) {
    require_once ABSPATH . 'wp-admin/includes/image.php';
    wp_generate_attachment_metadata($attach_id, $uploaded['file']);
  }

  // 💾 Enregistrement dans le champ ACF
  update_field('enigme_solution_fichier', $attach_id, $post_id);

  wp_send_json_success([
    'fichier' => $uploaded['url']
  ]);
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
  $custom = WP_CONTENT_DIR . '/protected/solutions';

  if (!file_exists($custom)) {
    wp_mkdir_p($custom);
  }

  $dirs['path']     = $custom;
  $dirs['basedir']  = $custom;
  $dirs['subdir']   = '';

  // 🔐 Empêche WordPress de construire une URL publique
  $dirs['url']      = '';
  $dirs['baseurl']  = '';

  return $dirs;
}


/**
 * Déplace un fichier PDF de solution vers un répertoire public,
 * uniquement si la chasse est terminée et que le fichier n’a pas encore été déplacé.
 *
 * @param int $enigme_id ID du post de type "enigme"
 */
function deplacer_pdf_solution($enigme_id)
{
  if (!$enigme_id || get_post_type($enigme_id) !== 'enigme') return;

  $fichier_id = get_field('enigme_solution_fichier', $enigme_id, false);
  if (!$fichier_id || !is_numeric($fichier_id)) return;

  $chemin_source = get_attached_file($fichier_id);
  if (!$chemin_source || !file_exists($chemin_source)) return;

  $chasse_id = recuperer_id_chasse_associee($enigme_id);
  if (!$chasse_id || get_post_type($chasse_id) !== 'chasse') return;

  $cache = get_field('champs_caches', $chasse_id);
  $statut = $cache['chasse_cache_statut'] ?? '';
  if (trim(strtolower($statut)) !== 'termine') return;

  $dossier_public = WP_CONTENT_DIR . '/uploads/solutions-publiques';
  if (!file_exists($dossier_public)) {
    if (!wp_mkdir_p($dossier_public)) return;
  }

  $nom_fichier = basename($chemin_source);
  $chemin_cible = $dossier_public . '/' . $nom_fichier;

  if (file_exists($chemin_cible)) return;

  $deplacement = @rename($chemin_source, $chemin_cible);
  if (!$deplacement) {
    $copie = @copy($chemin_source, $chemin_cible);
    if (!$copie || !@unlink($chemin_source)) return;
  }

  update_attached_file($fichier_id, $chemin_cible);
}
add_action('publier_solution_enigme', 'deplacer_pdf_solution');


/**
 * Déclenche immédiatement ou planifie le déplacement du PDF selon le délai.
 *
 * Cette fonction est appelée lorsque le statut devient "termine".
 * Le déplacement est différé dans tous les cas (5 secondes minimum).
 */
function planifier_ou_deplacer_pdf_solution_immediatement($enigme_id)
{
  if (!$enigme_id || get_post_type($enigme_id) !== 'enigme') return;

  $mode = get_field('enigme_solution_mode', $enigme_id);
  if (!in_array($mode, ['fin_de_chasse', 'delai_fin_chasse', 'date_fin_chasse'])) return;

  $delai = get_field('enigme_solution_delai', $enigme_id);
  $heure = get_field('enigme_solution_heure', $enigme_id);

  if ($delai === null || $heure === null) return;

  // 👉 Remettre "days" en prod
  $timestamp = strtotime("+$delai days $heure");

  if (!$timestamp) return;

  if ($timestamp <= time()) {
    $timestamp = time() + 5;
  }

  wp_schedule_single_event($timestamp, 'publier_solution_enigme', [$enigme_id]);
}



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

  $enigmes_associees = recuperer_enigmes_associees($chasse_id);

  if ($post_id && ($key = array_search($post_id, $enigmes_associees)) !== false) {
    unset($enigmes_associees[$key]); // Exclure l'énigme en cours
  }

  // 📌 Correction : Si aucune énigme ne doit être affichée, forcer un tableau vide pour empêcher ACF d'afficher tout
  $args['post__in'] = !empty($enigmes_associees) ? array_map('intval', $enigmes_associees) : [0];

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

  if (is_array($chasse)) {
    $chasse_id = is_object($chasse[0]) ? (int)$chasse[0]->ID : (int)$chasse[0];
  } elseif (is_object($chasse)) {
    $chasse_id = (int)$chasse->ID;
  } else {
    $chasse_id = (int)$chasse;
  }

  if (!$chasse_id || get_post_type($chasse_id) !== 'chasse') return;

  // ✅ Ajoute l’ID de l’énigme à la relation "chasse_cache_enigmes"
  $success = modifier_relation_acf(
    $chasse_id,
    'champs_caches_enigmes',
    $post_id,
    'field_67b740025aae0',
    'add'
  );

  if ($success) {
    error_log("✅ Énigme $post_id ajoutée à la chasse $chasse_id");
  } else {
    error_log("❌ Échec ajout énigme $post_id à la chasse $chasse_id");
  }
}, 20);


/**
 * 🧹 Nettoyer les relations ACF orphelines dans le champ `champs_caches_enigmes_associees`.
 *
 * Cette fonction parcourt toutes les chasses possédant des valeurs dans le champ ACF
 * `champs_caches_enigmes_associees`, et supprime les références à des énigmes qui ont été supprimées.
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
        WHERE meta_key = 'champs_caches_enigmes_associees'
    ");

  foreach ($chasses as $chasse) {
    $post_id = $chasse->post_id;
    $relations = maybe_unserialize($chasse->meta_value);

    if (!is_array($relations)) {
      continue;
    }

    // 📌 Vérifier si les IDs existent toujours
    $relations_nettoyees = array_filter($relations, function ($enigme_id) use ($wpdb) {
      return $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE ID = %d", $enigme_id));
    });

    // 🔥 Si on a supprimé des IDs, mettre à jour la base
    if (count($relations_nettoyees) !== count($relations)) {
      update_post_meta($post_id, 'champs_caches_enigmes_associees', $relations_nettoyees);
      error_log("✅ Relations nettoyées pour la chasse ID {$post_id} : " . print_r($relations_nettoyees, true));
    }
  }
}

/**
 * 🧩 Gérer la suppression d'une énigme : mise à jour des relations dans la chasse associée.
 *
 * Cette fonction est déclenchée automatiquement **avant la suppression** d’un post.
 * Si le post supprimé est de type `enigme`, elle effectue :
 *
 * 1. 🔄 La suppression de l’ID de l’énigme dans le champ relation ACF
 *    `champs_caches_enigmes_associees` de la chasse associée, via `modifier_relation_acf()`.
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
  $acf_key = 'field_67b740025aae0'; // Clé exacte du champ `champs_caches_enigmes_associees`
  modifier_relation_acf($chasse_id, 'champs_caches_enigmes_associees', $post_id, $acf_key, 'remove');

  // 🔹 Nettoyer les relations orphelines (toutes les chasses)
  nettoyer_relations_orphelines();
});
