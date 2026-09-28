<?php
defined('ABSPATH') || exit;


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

  // 📎 Récupération de l'organisateur lié
  $organisateur_id = get_organisateur_from_user($user_id);
  if (!$organisateur_id) {
    cat_debug("🛑 Aucun organisateur trouvé pour l'utilisateur {$user_id}");
    wp_die( __( 'Aucun organisateur associé.', 'chassesautresor-com' ) );
  }
  cat_debug("✅ Organisateur trouvé : {$organisateur_id}");

  // 🔒 Vérification des droits de création
  if (!current_user_can('administrator') && !current_user_can(ROLE_ORGANISATEUR)) {
    if (in_array(ROLE_ORGANISATEUR_CREATION, $roles, true)) {
      if (organisateur_a_des_chasses($organisateur_id)) {
        wp_die( __( 'Limite atteinte', 'chassesautresor-com' ) );
      }
    } else {
      wp_die( __( 'Accès refusé', 'chassesautresor-com' ) );
    }
  }

  // 🔒 Organisateur publié : une seule chasse en attente à la fois
  if (
    !current_user_can('manage_options') &&
    get_post_status($organisateur_id) === 'publish' &&
    organisateur_a_chasse_pending($organisateur_id)
  ) {
    wp_die( __( 'Une chasse est déjà en attente de validation.', 'chassesautresor-com' ) );
  }

  // 📝 Création du post "chasse"
  $post_id = wp_insert_post([
    'post_type'   => 'chasse',
    'post_status' => 'pending',
    'post_title'  => TITRE_DEFAUT_CHASSE,
    'post_author' => $user_id,
  ]);

  if (is_wp_error($post_id)) {
    cat_debug("🛑 Erreur création post : " . $post_id->get_error_message());
    wp_die( __( 'Erreur lors de la création de la chasse.', 'chassesautresor-com' ) );
  }

  cat_debug("✅ Chasse créée avec l’ID : {$post_id}");

  update_field('chasse_principale_image', 3902, $post_id);


  // 📅 Préparation des valeurs
  $today = current_time('Y-m-d H:i:s');
  $in_two_years = date('Y-m-d', strtotime('+2 years'));

  // ✅ Initialisation des champs ACF
  update_field('chasse_infos_date_debut', $today, $post_id);
  update_field('chasse_infos_date_fin', $in_two_years, $post_id);
  update_field('chasse_infos_duree_illimitee', false, $post_id);
  update_post_meta($post_id, 'chasse_infos_date_debut_differee', 0);
  // Coût par défaut à 0 (mode gratuit)
  update_field('chasse_infos_cout_points', 0, $post_id);

  update_field('chasse_cache_statut', 'revision', $post_id);
  update_field('chasse_cache_statut_validation', 'creation', $post_id);
  update_field('chasse_cache_organisateur', [$organisateur_id], $post_id);

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

  $post_id         = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
  $date_debut      = sanitize_text_field($_POST['date_debut'] ?? '');
  $date_fin        = sanitize_text_field($_POST['date_fin'] ?? '');
  $illimitee       = isset($_POST['illimitee']) ? (int) $_POST['illimitee'] : 0;
  $debut_differee  = isset($_POST['debut_differee']) ? (int) $_POST['debut_differee'] : 0;

  cat_debug("[modifier_dates_chasse] post_id={$post_id} date_debut={$date_debut} date_fin={$date_fin} illimitee={$illimitee} differee={$debut_differee}");

  // 📦 Valeurs existantes avant mise à jour (pour debug)
  $old_debut    = get_post_meta($post_id, 'chasse_infos_date_debut', true);
  $old_fin      = get_post_meta($post_id, 'chasse_infos_date_fin', true);
  $old_illim    = get_post_meta($post_id, 'chasse_infos_duree_illimitee', true);
  $old_differee = get_post_meta($post_id, 'chasse_infos_date_debut_differee', true);
  cat_debug("[modifier_dates_chasse] metas_avant: debut={$old_debut} fin={$old_fin} illim={$old_illim} differee={$old_differee}");

  if (!$post_id || get_post_type($post_id) !== 'chasse') {
    wp_send_json_error('post_invalide');
  }

  if (!utilisateur_peut_modifier_post($post_id)) {
    wp_send_json_error('acces_refuse');
  }

  if (!utilisateur_peut_editer_champs($post_id)) {
    wp_send_json_error('acces_refuse');
  }

  $dt_debut = convertir_en_datetime($date_debut, [
    'Y-m-d\TH:i',
    'Y-m-d H:i:s',
    'Y-m-d H:i',
    'Y-m-d'
  ]);
  if (!$dt_debut) {
    wp_send_json_error('format_debut_invalide');
  }
  cat_debug('[modifier_dates_chasse] dt_debut=' . $dt_debut->format('c'));

  $dt_fin = null;
  if (!$illimitee) {
    $dt_fin = convertir_en_datetime($date_fin, [
      'Y-m-d',
      'Y-m-d H:i:s',
      'Y-m-d\TH:i'
    ]);
    if (!$dt_fin) {
      wp_send_json_error('format_fin_invalide');
    }
    // Uniformise l'heure pour faciliter la comparaison
    $dt_fin->setTime(0, 0, 0);
    if ($dt_fin->getTimestamp() <= $dt_debut->getTimestamp()) {
      wp_send_json_error('date_fin_avant_debut');
    }
    cat_debug('[modifier_dates_chasse] dt_fin=' . $dt_fin->format('c'));
  }

  $ok1 = update_field('chasse_infos_date_debut', $dt_debut->format('Y-m-d H:i:s'), $post_id);
  cat_debug('[modifier_dates_chasse] update chasse_infos_date_debut=' . var_export($ok1, true));

  $ok2 = update_field('chasse_infos_duree_illimitee', $illimitee ? 1 : 0, $post_id);
  cat_debug('[modifier_dates_chasse] update chasse_infos_duree_illimitee=' . var_export($ok2, true));

  if ($illimitee) {
    // Ne pas modifier la date de fin en base
    $ok3 = true;
  } else {
    $ok3 = update_field('chasse_infos_date_fin', $dt_fin->format('Y-m-d'), $post_id);
  }
  cat_debug('[modifier_dates_chasse] update chasse_infos_date_fin=' . var_export($ok3, true));
  update_post_meta($post_id, 'chasse_infos_date_debut_differee', $debut_differee ? 1 : 0);

  // 🔎 Métas après mise à jour
  $new_debut    = get_post_meta($post_id, 'chasse_infos_date_debut', true);
  $new_fin      = get_post_meta($post_id, 'chasse_infos_date_fin', true);
  $new_illim    = get_post_meta($post_id, 'chasse_infos_duree_illimitee', true);
  $new_differee = get_post_meta($post_id, 'chasse_infos_date_debut_differee', true);
  cat_debug("[modifier_dates_chasse] metas_apres: debut={$new_debut} fin={$new_fin} illim={$new_illim} differee={$new_differee}");

  // Lecture directe pour éviter un cache ACF éventuel
  $saved_debut_raw = get_post_meta($post_id, 'chasse_infos_date_debut', true);
  $saved_fin_raw   = get_post_meta($post_id, 'chasse_infos_date_fin', true);
  $saved_illim     = get_post_meta($post_id, 'chasse_infos_duree_illimitee', true);

  $saved_debut_dt = convertir_en_datetime($saved_debut_raw, ['Y-m-d H:i:s', 'Y-m-d\TH:i', 'Y-m-d', 'YmdHis', 'Ymd']);
  $saved_fin_dt   = convertir_en_datetime($saved_fin_raw, ['Y-m-d', 'Ymd', 'Y-m-d H:i:s', 'Y-m-d\TH:i']);
  if ($saved_fin_dt) {
    $saved_fin_dt->setTime(0, 0, 0);
  }

  $debut_ok     = $saved_debut_dt && $saved_debut_dt->format('Y-m-d H:i:s') === $dt_debut->format('Y-m-d H:i:s');
  $fin_ok       = $illimitee ? true : ($saved_fin_dt && $saved_fin_dt->format('Y-m-d H:i:s') === $dt_fin->format('Y-m-d H:i:s'));
  $illim_ok     = (int) $saved_illim === ($illimitee ? 1 : 0);
  $differee_ok  = (int) $new_differee === ($debut_differee ? 1 : 0);

  cat_debug("[modifier_dates_chasse] verifs: debut_ok=" . var_export($debut_ok, true) . ' fin_ok=' . var_export($fin_ok, true) . ' illim_ok=' . var_export($illim_ok, true) . ' differee_ok=' . var_export($differee_ok, true));

  if (($ok1 || $debut_ok) && ($ok2 || $illim_ok) && ($ok3 || $fin_ok)) {
    mettre_a_jour_statuts_chasse($post_id);
    cat_debug('[modifier_dates_chasse] mise a jour reussie');
    wp_send_json_success([
      'date_debut' => $dt_debut->format('Y-m-d H:i:s'),
      'date_fin'   => $illimitee ? '' : $dt_fin->format('Y-m-d'),
      'illimitee'  => $illimitee ? 1 : 0,
    ]);
  }

  cat_debug('[modifier_dates_chasse] conditions: ok1=' . var_export($ok1, true) . ' ok2=' . var_export($ok2, true) . ' ok3=' . var_export($ok3, true));
  cat_debug('[modifier_dates_chasse] echec mise a jour');
  wp_send_json_error('echec_mise_a_jour');
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
    $champ_fin = in_array($champ, ['champs_caches.chasse_cache_gagnants', 'champs_caches.chasse_cache_date_decouverte'], true);
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
    $tableau = json_decode(stripslashes($valeur), true);
    if (!is_array($tableau)) {
      wp_send_json_error(__('⚠️ format_invalide', 'chassesautresor-com'));
    }
    $repetitions = [];
    foreach ($tableau as $ligne) {
      $type = sanitize_text_field($ligne['type_de_lien'] ?? '');
      $url  = esc_url_raw($ligne['url_lien'] ?? '');
      if ($type && $url) {
        $repetitions[] = [
          'chasse_principale_liens_type' => $type,
          'chasse_principale_liens_url'  => $url,
        ];
      }
    }

    $reponse = ['champ' => $champ, 'valeur' => $repetitions];

    $normaliser = static function (array $items): array {
      $out = [];
      foreach ($items as $row) {
        $type = sanitize_text_field($row['chasse_principale_liens_type'] ?? '');
        $url  = esc_url_raw($row['chasse_principale_liens_url'] ?? '');
        if ($type && $url) {
          $out[] = [
            'chasse_principale_liens_type' => $type,
            'chasse_principale_liens_url'  => $url,
          ];
        }
      }
      return $out;
    };

    $actuels = get_field('chasse_principale_liens', $post_id);
    $actuels = is_array($actuels) ? array_values($actuels) : [];
    if (wp_json_encode($normaliser($actuels)) === wp_json_encode($repetitions)) {
      wp_send_json_success($reponse);
    }

    $ok = update_field('chasse_principale_liens', $repetitions, $post_id);

    $enregistre = get_field('chasse_principale_liens', $post_id);
    $enregistre = is_array($enregistre) ? array_values($enregistre) : [];
    $equivalent = wp_json_encode($normaliser($enregistre)) === wp_json_encode($repetitions);

    if ($ok !== false || $equivalent) {
      wp_send_json_success($reponse);
    }

    wp_send_json_error(__('⚠️ echec_mise_a_jour_liens', 'chassesautresor-com'));
  }

  // 🔹 Dates (début / fin)
  if ($champ === 'caracteristiques.chasse_infos_date_debut') {
    $dt = convertir_en_datetime($valeur, [
      'Y-m-d\TH:i',
      'Y-m-d H:i:s',
      'Y-m-d H:i'
    ]);
    if (!$dt) {
      wp_send_json_error('⚠️ format_date_invalide');
    }
    $valeur = $dt->format('Y-m-d H:i:s');
    $ok = update_field('chasse_infos_date_debut', $valeur, $post_id);
    if ($ok !== false) {
      $champ_valide = true;
      $doit_recalculer_statut = true;
    }
  }

  if ($champ === 'caracteristiques.chasse_infos_date_fin') {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $valeur)) {
      wp_send_json_error('⚠️ format_date_invalide');
    }
    $ok = update_field('chasse_infos_date_fin', $valeur, $post_id);
    if ($ok !== false) {
      $champ_valide = true;
      $doit_recalculer_statut = true;
    }
  }

  // 🔹 Durée illimitée (true_false)
  if ($champ === 'caracteristiques.chasse_infos_duree_illimitee') {
    $ok = update_field('chasse_infos_duree_illimitee', (int) $valeur, $post_id);
    $mode_continue = empty(get_field('chasse_infos_duree_illimitee', $post_id));
    cat_debug("🧪 Illimitée (après MAJ) = " . var_export(!$mode_continue, true));


    if ($ok !== false) {
      $champ_valide = true;
      $doit_recalculer_statut = true;
    }
  }

  // 🔹 Champs récompense (texte / valeur)
  $champs_recompense = [
    'caracteristiques.chasse_infos_recompense_valeur',
    'caracteristiques.chasse_infos_recompense_texte'
  ];
  if (in_array($champ, $champs_recompense, true)) {
    $sous_champ = str_replace('caracteristiques.', '', $champ);

    // Validation spécifique pour la valeur monétaire
    if ($sous_champ === 'chasse_infos_recompense_valeur') {
      if (!is_numeric($valeur) || $valeur <= 0 || $valeur > 5000000) {
        wp_send_json_error('valeur_invalide');
      }
    }

    $ok = update_field($sous_champ, $valeur, $post_id);
    if ($ok !== false) $champ_valide = true;
    $doit_recalculer_statut = true;
  }

  if ($champ === 'caracteristiques.chasse_infos_cout_points') {
    cat_debug("🧪 Correction tentative : MAJ cout_points → valeur = {$valeur}");
    $ok = update_field('chasse_infos_cout_points', (int) $valeur, $post_id);
    if ($ok !== false) {
      cat_debug("✅ MAJ réussie pour chasse_infos_cout_points");
      $champ_valide = true;
      $doit_recalculer_statut = true;
    } else {
      cat_debug("❌ MAJ échouée malgré nom exact");
    }
  }

  // 🔹 Déclenchement de la publication différée des solutions
  if ($champ === 'champs_caches.chasse_cache_statut' && $valeur === 'termine') {
    $ok = update_field('chasse_cache_statut', 'termine', $post_id);
    if ($ok !== false) {
      // ✅ Marque la chasse comme complète sans déclencher de recalcul automatique
      update_field('chasse_cache_complet', 1, $post_id);
      $champ_valide = true;

      $liste_enigmes = recuperer_enigmes_associees($post_id);
      if (!empty($liste_enigmes)) {
        foreach ($liste_enigmes as $enigme_id) {
          cat_debug("🧩 Planification/déplacement : énigme #$enigme_id");
          planifier_ou_deplacer_pdf_solution_immediatement($enigme_id);
          $sol = solution_recuperer_par_objet((int) $enigme_id, 'enigme');
          if ($sol) {
            solution_planifier_publication($sol->ID);
          }
        }
      }

      $sol_chasse = solution_recuperer_par_objet((int) $post_id, 'chasse');
      if ($sol_chasse) {
        solution_planifier_publication($sol_chasse->ID);
      }

      // 🏁 Mise à jour des statuts joueurs
      gerer_chasse_terminee($post_id);
    }
  }

    // 🔹 Gagnants (texte libre)
    if ($champ === 'champs_caches.chasse_cache_gagnants') {
        $ok = update_field('chasse_cache_gagnants', $valeur, $post_id);
        if ($ok !== false) {
            $champ_valide = true;
        }
    }

    // 🔹 Date de découverte
    if ($champ === 'champs_caches.chasse_cache_date_decouverte') {
        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $valeur)) {
            wp_send_json_error('⚠️ format_date_invalide');
        }

        $date_obj = DateTime::createFromFormat('Y-m-d H:i:s', $valeur) ?: DateTime::createFromFormat('Y-m-d H:i', $valeur);
        if (!$date_obj) {
            wp_send_json_error('⚠️ format_date_invalide');
        }

        $valeur = $date_obj->format('Y-m-d H:i:s');
        $ok = update_field('chasse_cache_date_decouverte', $valeur, $post_id);
        if ($ok !== false) {
            $champ_valide = true;
            $doit_recalculer_statut = true;
        }
    }

  // 🔹 Nb gagnants
  if ($champ === 'caracteristiques.chasse_infos_nb_max_gagants') {
    $sous_champ = 'chasse_infos_nb_max_gagants';
    $ok = update_field($sous_champ, (int) $valeur, $post_id);
    if ($ok !== false) $champ_valide = true;
  }

  // 🔹 Titre récompense
  if ($champ === 'caracteristiques.chasse_infos_recompense_titre') {
    $sous_champ = 'chasse_infos_recompense_titre';
    $ok = update_field($sous_champ, $valeur, $post_id);
    if ($ok !== false) $champ_valide = true;
  }

  // 🔹 Validation manuelle (par admin)
  if ($champ === 'champs_caches.chasse_cache_statut_validation' || $champ === 'chasse_cache_statut_validation') {
    $ok = update_field('chasse_cache_statut_validation', sanitize_text_field($valeur), $post_id);
    if ($ok !== false) $champ_valide = true;
  }

  // 🔹 Cas générique (fallback)
  if (!$champ_valide) {
    $ok = update_field($champ, is_numeric($valeur) ? (int) $valeur : $valeur, $post_id);
    $valeur_meta = get_post_meta($post_id, $champ, true);
    $valeur_comparee = stripslashes_deep($valeur);
    if ($ok || trim((string) $valeur_meta) === trim((string) $valeur_comparee)) {
      $champ_valide = true;
    } else {
      wp_send_json_error('⚠️ echec_mise_a_jour_final');
    }
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

    foreach ($enigme_ids as $enigme_id) {
        if ($enigme_id <= 0) {
            continue;
        }

        wp_trash_post($enigme_id);

        if (function_exists('supprimer_dossier_enigme')) {
            supprimer_dossier_enigme($enigme_id);
        }
    }

    if (function_exists('synchroniser_cache_enigmes_chasse')) {
        synchroniser_cache_enigmes_chasse($chasse_id, true, true);
    }

    if (function_exists('get_attached_media')) {
        $attachments = get_attached_media('image', $chasse_id);
        foreach ($attachments as $attachment) {
            if (is_object($attachment) && isset($attachment->ID)) {
                wp_trash_post((int) $attachment->ID);
            }
        }
    }

    return (bool) wp_trash_post($chasse_id);
}

/**
 * Supprime une chasse en attente ainsi que ses énigmes associées.
 */
function supprimer_chasse_ajax(): void
{
    if (!is_user_logged_in()) {
        wp_send_json_error('non_connecte');
    }

    $chasse_id = isset($_POST['chasse_id']) ? (int) $_POST['chasse_id'] : 0;
    if (!$chasse_id || get_post_type($chasse_id) !== 'chasse') {
        wp_send_json_error('id_invalide');
    }

    if (get_post_status($chasse_id) !== 'pending') {
        wp_send_json_error('chasse_ineligible');
    }

    if (get_post_meta($chasse_id, 'chasse_cache_statut', true) !== 'revision') {
        wp_send_json_error('chasse_ineligible');
    }

    $user_id = get_current_user_id();
    if (!$user_id || !utilisateur_est_organisateur_associe_a_chasse($user_id, $chasse_id)) {
        wp_send_json_error('acces_refuse');
    }

    if (!chasse_trash_with_children($chasse_id)) {
        wp_send_json_error('erreur_suppression');
    }

    if (function_exists('myaccount_add_flash_message')) {
        myaccount_add_flash_message(
            $user_id,
            __('Votre chasse a été supprimée. Vous pouvez en créer une nouvelle quand vous le souhaitez.', 'chassesautresor-com'),
            'success',
            true
        );
    }

    wp_send_json_success([
        'redirect' => home_url('/mon-compte/organisateurs/'),
    ]);
}
