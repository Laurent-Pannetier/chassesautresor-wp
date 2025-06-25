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
  enqueue_core_edit_scripts(['chasse-edit']);

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
    wp_die('Aucun organisateur associé.');
  }
  cat_debug("✅ Organisateur trouvé : {$organisateur_id}");

  // 🔒 Vérification des droits de création
  if (!current_user_can('administrator') && !current_user_can(ROLE_ORGANISATEUR)) {
    if (in_array(ROLE_ORGANISATEUR_CREATION, $roles, true)) {
      if (organisateur_a_des_chasses($organisateur_id)) {
        wp_die('Limite atteinte');
      }
    } else {
      wp_die('Accès refusé');
    }
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
    wp_die('Erreur lors de la création de la chasse.');
  }

  cat_debug("✅ Chasse créée avec l’ID : {$post_id}");

  update_field('chasse_principale_image', 3902, $post_id);


  // 📅 Préparation des valeurs
  $today = current_time('Y-m-d H:i:s');
  $in_two_years = date('Y-m-d', strtotime('+2 years'));

  // ✅ Initialisation des groupes ACF
  mettre_a_jour_sous_champ_group(
    $post_id,
    'caracteristiques',
    'chasse_infos_date_debut',
    $today,
    [
      'chasse_infos_date_debut'      => $today,
      'chasse_infos_date_fin'        => $in_two_years,
      'chasse_infos_duree_illimitee' => false,
    ]
  );

  mettre_a_jour_sous_champ_group(
    $post_id,
    'champs_caches',
    'chasse_cache_organisateur',
    [$organisateur_id],
    [
      'chasse_cache_statut'            => 'revision',
      'chasse_cache_statut_validation' => 'creation',
      'chasse_cache_organisateur'      => [$organisateur_id],
    ]
  );

  // 🚀 Redirection vers la prévisualisation frontale avec panneau ouvert
  $preview_url = add_query_arg('edition', 'open', get_preview_post_link($post_id));
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

  $auteur = (int) get_post_field('post_author', $post_id);
  if ($auteur !== $user_id) {
    wp_send_json_error('⚠️ acces_refuse');
  }

  if (!utilisateur_peut_editer_champs($post_id)) {
    wp_send_json_error('⚠️ acces_refuse');
  }

  $doit_recalculer_statut = false;
  $champ_valide = false;
  $reponse = ['champ' => $champ, 'valeur' => $valeur];
  // 🛡️ Initialisation sécurisée du groupe caracteristiques
  mettre_a_jour_sous_champ_group(
    $post_id,
    'caracteristiques',
    '',
    [],
    [
      'chasse_infos_date_debut'        => '',
      'chasse_infos_date_fin'          => '',
      'chasse_infos_duree_illimitee'   => 0,
      'chasse_infos_recompense_valeur' => '',
      'chasse_infos_recompense_titre'  => '',
      'chasse_infos_recompense_texte'  => '',
      'chasse_infos_nb_max_gagants'    => 0,
      'chasse_infos_cout_points'       => 0,
    ]
  );


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
      wp_send_json_error('⚠️ format_invalide');
    }
    $repetitions = array_values(array_filter(array_map(function ($ligne) {
      $type = sanitize_text_field($ligne['type_de_lien'] ?? '');
      $url  = sanitize_text_field($ligne['url_lien'] ?? '');
      return ($type && $url) ? [
        'chasse_principale_liens_type' => [$type],
        'chasse_principale_liens_url'  => $url
      ] : null;
    }, $tableau)));

    $ok = update_field('chasse_principale_liens', $repetitions, $post_id);
    if ($ok) wp_send_json_success($reponse);
    wp_send_json_error('⚠️ echec_mise_a_jour_liens');
  }

  // 🔹 Dates (début / fin)
  if ($champ === 'caracteristiques.chasse_infos_date_debut') {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $valeur)) {
      wp_send_json_error('⚠️ format_date_invalide');
    }
    $dt = DateTime::createFromFormat('Y-m-d\TH:i', $valeur);
    if ($dt) {
      $valeur = $dt->format('Y-m-d H:i:s');
    }
    $ok = mettre_a_jour_sous_champ_group($post_id, 'caracteristiques', 'chasse_infos_date_debut', $valeur);
    if ($ok) {
      $champ_valide = true;
      $doit_recalculer_statut = true;
    }
  }

  if ($champ === 'caracteristiques.chasse_infos_date_fin') {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $valeur)) {
      wp_send_json_error('⚠️ format_date_invalide');
    }
    $ok = mettre_a_jour_sous_champ_group($post_id, 'caracteristiques', 'chasse_infos_date_fin', $valeur);
    if ($ok) {
      $champ_valide = true;
      $doit_recalculer_statut = true;
    }
  }

  // 🔹 Durée illimitée (true_false)
  if ($champ === 'caracteristiques.chasse_infos_duree_illimitee') {
    $groupe = get_field('caracteristiques', $post_id) ?: [];
    $groupe['chasse_infos_duree_illimitee'] = (int) $valeur;
    $ok = update_field('caracteristiques', $groupe, $post_id);
    $carac_maj = get_field('caracteristiques', $post_id);
    $mode_continue = empty($carac_maj['chasse_infos_duree_illimitee']);
    cat_debug("🧪 Illimitée (après MAJ) = " . var_export($carac_maj['chasse_infos_duree_illimitee'], true));


    if ($ok) {
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
    $ok = mettre_a_jour_sous_champ_group($post_id, 'caracteristiques', $sous_champ, $valeur);
    if ($ok) $champ_valide = true;
    $doit_recalculer_statut = true;
  }

  if ($champ === 'caracteristiques.chasse_infos_cout_points') {
    cat_debug("🧪 Correction tentative : MAJ cout_points → valeur = {$valeur}");
    $ok = mettre_a_jour_sous_champ_group($post_id, 'caracteristiques', 'chasse_infos_cout_points', (int) $valeur);
    if ($ok) {
      cat_debug("✅ MAJ réussie pour chasse_infos_cout_points");
      $champ_valide = true;
      $doit_recalculer_statut = true;
    } else {
      cat_debug("❌ MAJ échouée malgré nom exact");
    }
  }

  // 🔹 Déclenchement de la publication différée des solutions
  if ($champ === 'champs_caches.chasse_cache_statut' && $valeur === 'termine') {
    $champ_valide = true;

    $liste_enigmes = recuperer_enigmes_associees($post_id);
    if (!empty($liste_enigmes)) {
      foreach ($liste_enigmes as $enigme_id) {
        cat_debug("🧩 Planification/déplacement : énigme #$enigme_id");
        planifier_ou_deplacer_pdf_solution_immediatement($enigme_id);
      }
    }
  }

  // 🔹 Nb gagnants
  if ($champ === 'caracteristiques.chasse_infos_nb_max_gagants') {
    $sous_champ = 'chasse_infos_nb_max_gagants';
    $ok = mettre_a_jour_sous_champ_group($post_id, 'caracteristiques', $sous_champ, (int) $valeur);
    if ($ok) $champ_valide = true;
  }

  // 🔹 Titre récompense
  if ($champ === 'caracteristiques.chasse_infos_recompense_titre') {
    $sous_champ = 'chasse_infos_recompense_titre';
    $ok = mettre_a_jour_sous_champ_group($post_id, 'caracteristiques', $sous_champ, $valeur);
    if ($ok) $champ_valide = true;
  }

  // 🔹 Validation manuelle (par admin)
  if ($champ === 'champs_caches.chasse_cache_statut_validation' || $champ === 'chasse_cache_statut_validation') {
    $ok = update_field('champs_caches', array_merge(get_field('champs_caches', $post_id), [
      'chasse_cache_statut_validation' => sanitize_text_field($valeur)
    ]), $post_id);
    if ($ok) $champ_valide = true;
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
    $caracteristiques = get_field('caracteristiques', $post_id);
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
      'organisateur_chasse',          // Nom du champ relation
      $organisateur_id,               // ID du post cible (organisateur)
      'field_67cfcba8c3bec',          // Clé ACF du champ
      'champs_caches_'                // Groupe ACF (préfixe)
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
