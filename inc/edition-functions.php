<?php
defined('ABSPATH') || exit;

//
//  📦 CHARGEMENT ET CONTROLE DONNÉES
//  🧩 APPARENCE EN MODE ÉDITION
//  🧩 FONCTIONS GLOBALES DE FORMATAGE
//  🧩 GESTION DE CHAMPS ACF PARTICULIERS
//  🏗️ CRÉATION & ÉDITION ORGANISATEUR
//  🏗️ CRÉATION & ÉDITION CHASSE
//  🧩 CRÉATION & ÉDITION ÉNIGME
//


// ==================================================
// 📦 CHARGEMENT ET CONTROLE DONNÉES
// ==================================================
/**
 * 🔹 forcer_chargement_acf_scripts_chasse → Force le chargement des scripts ACF sur les fiches chasse pour activer les WYSIWYG.
 * 🔹 personnaliser_acf_wysiwyg_toolbars → options minimales en "basic"
 * 🔹 enqueue_core_edit_scripts → chargement des modules JS mutualisés (édition front)
 * 🔹 verifier_et_synchroniser_cache_enigmes_si_autorise → vérifie la synchronisation entre les énigmes et le cache de la chasse (toutes les 30 min par post)
 */



/**
 * Force le chargement des scripts ACF sur les fiches chasse (is_singular('chasse')).
 *
 * Cela garantit que tous les composants nécessaires à l'affichage et à l'édition des champs
 * ACF (notamment les WYSIWYG) sont disponibles même dans des panneaux latéraux ou zones AJAX.
 *
 * À utiliser uniquement si acf_form() est utilisé en front hors de la boucle principale.
 *
 * @hook wp_enqueue_scripts
 */
add_action('wp_enqueue_scripts', 'forcer_chargement_acf_scripts_chasse');
function forcer_chargement_acf_scripts_chasse()
{
  if (is_singular('chasse')) {
    acf_enqueue_scripts();
  }
}

/**
 * 🎛️ Personnalise la barre d'outils ACF WYSIWYG.
 *
 * Ajoute un menu "Format" avec uniquement le choix de "Titre 3",
 * en plus des options de base : gras, souligné, liste à puces, lien.
 *
 * @param array $toolbars Les barres d'outils WYSIWYG existantes.
 * @return array Les barres d'outils modifiées.
 */
function personnaliser_acf_wysiwyg_toolbars($toolbars)
{
  $toolbars['Basic'] = array();
  $toolbars['Basic'][1] = array('formatselect', 'bold', 'underline', 'bullist', 'link', 'unlink');
  return $toolbars;
}
add_filter('acf/fields/wysiwyg/toolbars', 'personnaliser_acf_wysiwyg_toolbars');

add_filter('tiny_mce_before_init', function ($init) {
  $init['block_formats'] = 'Paragraphe=p;Titre 3=h3';
  return $init;
});


/**
 * Charge les scripts JS partagés pour l’édition frontale (texte, image, liens, etc.).
 *
 * @return void
 */
function enqueue_core_edit_scripts()
{
  $theme_uri = get_stylesheet_directory_uri();
  $theme_dir = get_stylesheet_directory();

  // Déclare les fichiers dans l’ordre des dépendances internes
  $core_scripts = [
    'helpers'            => 'helpers.js',
    'ajax'               => 'ajax.js',
    'ui'                 => 'ui.js',
    'champ-init'         => 'champ-init.js',
    'champ-date-hooks'   => 'champ-date-hooks.js', // 🆕 nouveau fichier
  ];

  $previous_handle = null;

  foreach ($core_scripts as $handle => $filename) {
    $path = "/assets/js/core/{$filename}";
    $file = $theme_dir . $path;
    $version = file_exists($file) ? filemtime($file) : null;

    wp_enqueue_script(
      $handle,
      $theme_uri . $path,
      $previous_handle ? [$previous_handle] : [], // le script dépend du précédent
      $version,
      true
    );

    $previous_handle = $handle; // pour chaîner la dépendance
  }
}


/**
 * 🔄 Vérifie et synchronise le cache des énigmes liées à une chasse.
 *
 * @param int $chasse_id ID du post de type chasse
 */
function verifier_et_synchroniser_cache_enigmes_si_autorise($chasse_id)
{
    if (!current_user_can('administrator') && !current_user_can('organisateur') && !current_user_can('organisateur_creation')) {
        return;
    }
    $cache = get_field('chasse_cache_enigmes', $chasse_id);
    error_log(print_r($cache, true));


    $transient_key = 'verif_sync_chasse_' . $chasse_id;

    if (!get_transient($transient_key)) {
        // 🔁 Synchronisation réelle du cache
        $resultat = synchroniser_cache_enigmes_chasse($chasse_id, true, true);
        set_transient($transient_key, 'done', 30 * MINUTE_IN_SECONDS);

        // 🔍 Log des énigmes détectées
        if (!empty($resultat['liste_attendue'])) {
            $liste = implode(', ', $resultat['liste_attendue']);
            error_log("[ChassesAuTresor] Énigmes détectées pour chasse #$chasse_id : [$liste]");
        } else {
            error_log("[ChassesAuTresor] Aucune énigme détectée pour chasse #$chasse_id");
        }

        // ✅ Correction effectuée
        if (!empty($resultat['correction_effectuee']) && !empty($resultat['valide'])) {
            $titre = get_the_title($chasse_id);
            $ids = implode(', ', $resultat['liste_attendue']);
            error_log("[ChassesAuTresor] Correction cache enigmes pour chasse #$chasse_id ($titre) → Nouveaux IDs : [$ids]");
        }
        
        $cache_final = get_field('chasse_cache_enigmes', $chasse_id);
        error_log('[DEBUG] chasse_cache_enigmes après sync : ' . print_r($cache_final, true));

    }
}




// ==================================================
// 🧩 APPARENCE EN MODE ÉDITION
// ==================================================
/**
 * 🔹 organisateur_get_liens_actifs -> Retourne un tableau des liens publics actifs pour un organisateur donné
 * 🔹 masquer_widgets_footer → Masque certains widgets du footer selon le rôle de l'utilisateur connecté.
 * 🔹 ajouter_classe_post_edit_non_admin → Ajoute une classe CSS dans le <body> admin uniquement pour les non-admins.
 * 🔹 acf_restreindre_utilisateurs_associes → Limite les choix du champ ACF "utilisateurs_associes" à l'auteur du post.
 * 🔹 restreindre_admin_menu_pour_roles_non_admins → Cache certains menus admin pour tous les rôles sauf administrateurs.
 * 🔹 masquer_admin_interface_pour_non_admins → Masque la top bar et le menu admin pour tous les rôles sauf administrateurs.
 * 🔹 reset_disposition_tous_utilisateurs_non_admins → Réinitialise la disposition des méta-boxes
 * 🔹 ajouter_barre_progression_top → Ajoute une barre de progression en haut des pages d'édition
 */


/**
 * Retourne un tableau des liens publics actifs pour un organisateur donné.
 *
 * @param int $organisateur_id ID du post organisateur.
 * @return array Tableau associatif [type => url] uniquement pour les entrées valides.
 */
function organisateur_get_liens_actifs(int $organisateur_id): array
{
  $liens_publics = get_field('liens_publics', $organisateur_id);
  $liens_actifs = [];

  if (!empty($liens_publics) && is_array($liens_publics)) {
    foreach ($liens_publics as $entree) {
      $type = $entree['type_de_lien'] ?? null;
      $url  = $entree['url_lien'] ?? null;

      if (is_string($type) && trim($type) !== '' && is_string($url) && trim($url) !== '') {
        $liens_actifs[$type] = esc_url($url);
      }
    }
  }

  return $liens_actifs;
}


/**
 * 🚫 Masque certains widgets du footer selon le rôle de l'utilisateur connecté.
 *
 * Cette fonction filtre les paramètres des widgets dynamiques pour empêcher
 * l'affichage de certains blocs de navigation selon le rôle du visiteur :
 *
 * - Si l'utilisateur n'est pas **administrateur**, le widget `nav_menu-5` est masqué
 * - Si l'utilisateur n'est pas **organisateur**, le widget `nav_menu-3` est masqué
 *
 * Le masquage se fait en remplaçant `before_widget`/`after_widget` par une balise
 * `<div style="display:none">`, ce qui évite d'altérer la structure HTML globale.
 *
 * @param array $params Paramètres du widget en cours d'affichage (contenu, ID, balises...).
 * @return array Les paramètres éventuellement modifiés si le widget doit être masqué.
 *
 * @hook dynamic_sidebar_params
 */
function masquer_widgets_footer($params)
{
  if (is_admin()) {
    return $params; // Ne rien filtrer dans l'admin WP
  }

  $user = wp_get_current_user();
  $roles = (array) $user->roles;

  // Liste des IDs des widgets à masquer
  $widgets_a_masquer = [];

  if (!in_array('administrator', $roles)) {
    $widgets_a_masquer[] = 'nav_menu-5';
  }

  if (!in_array('organisateur', $roles)) {
    $widgets_a_masquer[] = 'nav_menu-3';
  }

  if (isset($params[0]['widget_id']) && in_array($params[0]['widget_id'], $widgets_a_masquer)) {
    $params[0]['before_widget'] = '<div style="display:none">';
    $params[0]['after_widget'] = '</div>';
  }

  return $params;
}
add_filter('dynamic_sidebar_params', 'masquer_widgets_footer');


/**
 * 🏷️ Ajoute une classe CSS personnalisée dans le <body> de l'admin pour les non-admins.
 *
 * Cette fonction ajoute la classe `cat-post-edit-custom` uniquement :
 * - si l'utilisateur n'est pas administrateur
 * - ET si l'on se trouve sur une page d'édition ou de création de contenu (`post.php`, `post-new.php`)
 *
 * Utile pour cibler des styles spécifiques via CSS dans l'administration.
 *
 * @param string $classes Les classes CSS actuelles du <body> dans l'administration.
 * @return string Les classes CSS modifiées.
 *
 * @hook admin_body_class
 */
function ajouter_classe_post_edit_non_admin($classes)
{
  $user = wp_get_current_user();

  if (in_array('administrator', (array) $user->roles)) {
    return $classes;
  }

  global $pagenow;

  if ($pagenow === 'post.php' || $pagenow === 'post-new.php') {
    $classes .= ' cat-post-edit-custom';
  }

  return $classes;
}
add_filter('admin_body_class', 'ajouter_classe_post_edit_non_admin');

/**
 * Charge uniquement l’auteur du post dans le champ ACF "utilisateurs_associes".
 * SUSCEPTIBLE D ETRE SUPPRIMEE SI PLUSIEURS UTILISATEURS SUR UN CPT organisateur
 * @param array $field Le champ ACF.
 * @return array Le champ avec les choix filtrés.
 */
function acf_restreindre_utilisateurs_associes($field)
{
  global $post;

  if (!$post || get_post_type($post->ID) !== 'organisateur') {
    return $field;
  }

  $auteur_id = get_post_field('post_author', $post->ID);

  // Réinitialiser les choix et n'afficher que l'auteur du post
  $field['choices'] = [];
  $field['choices'][(string) $auteur_id] = get_the_author_meta('display_name', $auteur_id);

  return $field;
}
add_filter('acf/load_field/name=utilisateurs_associes', 'acf_restreindre_utilisateurs_associes');


/**
 * Restreint l'affichage des menus admin pour tous les rôles sauf les administrateurs.
 *
 * Cette fonction supprime les menus et sous-menus non autorisés pour les utilisateurs 
 * qui ne sont pas administrateurs. Elle conserve uniquement l'accès aux CPTs spécifiés.
 *
 * @return void
 */
function restreindre_admin_menu_pour_roles_non_admins()
{
  $user = wp_get_current_user();

  // Vérifie si l'utilisateur n'est PAS administrateur
  if (!in_array('administrator', (array) $user->roles)) {
    global $menu, $submenu;

    // Liste des menus autorisés (CPTs et leurs sous-menus)
    $menus_autorises = array(
      'edit.php?post_type=enigme',      // CPT "énigme"
      'edit.php?post_type=chasse',      // CPT "chasse"
      'edit.php?post_type=organisateur' // CPT "organisateur"
    );

    // Supprime les menus non autorisés
    foreach ($menu as $key => $item) {
      if (!in_array($item[2], $menus_autorises)) {
        unset($menu[$key]);
      }
    }

    // Supprime les sous-menus des CPTs non autorisés
    foreach ($submenu as $parent => $items) {
      if (!in_array($parent, $menus_autorises)) {
        unset($submenu[$parent]);
      }
    }

    // Supprime le menu "Tableau de bord"
    remove_menu_page('index.php');
  }
}
add_action('admin_menu', 'restreindre_admin_menu_pour_roles_non_admins', 999);

/**
 * Masque la top bar et le menu admin pour tous les rôles sauf administrateurs.
 * Autorise uniquement l'accès aux pages d'édition et d'ajout de post.
 *
 * @return void
 */
function masquer_admin_interface_pour_non_admins()
{
  $user = wp_get_current_user();

  // Vérifie si l'utilisateur N'EST PAS administrateur
  if (!in_array('administrator', (array) $user->roles)) {

    // 🔹 Cache la barre d'administration (backend et frontend)
    add_filter('show_admin_bar', '__return_false');

    // 🔹 Supprime le menu admin via CSS
    add_action('admin_head', function () {
      echo '<style>
                #adminmenumain, #wpadminbar, #wpfooter { display: none !important; }
                #wpcontent, #wpbody-content { margin-left: 0 !important; padding-top: 0 !important; }
                html.wp-toolbar { padding-top: 0 !important; }
            </style>';
    });

    // 🔹 Supprime aussi le menu WordPress en vidant $menu et $submenu
    add_action('admin_menu', function () {
      global $menu, $submenu;
      $menu = [];
      $submenu = [];
    }, 999);

    // 🔹 Liste des pages autorisées + AJAX WordPress (ajout de async-upload.php)
    $pages_autorisees = ['post.php', 'post-new.php', 'edit.php', 'edit.php?post_type=trophee', 'admin-ajax.php', 'async-upload.php'];

    // 🔹 Redirige les utilisateurs non-admins s'ils essaient d'aller ailleurs
    add_action('admin_init', function () use ($pages_autorisees) {
      global $pagenow;

      // ✅ Laisse passer les requêtes AJAX pour éviter de bloquer ACF + async-upload.php pour l'upload
      if (
        !in_array($pagenow, $pages_autorisees)
        && strpos($_SERVER['REQUEST_URI'], 'admin-ajax.php') === false
        && strpos($_SERVER['REQUEST_URI'], 'async-upload.php') === false
      ) {

        wp_redirect(admin_url('post-new.php'));
        exit;
      }
    });
  }
}
add_action('init', 'masquer_admin_interface_pour_non_admins');

/**
 * Ajoute une barre de progression en haut des pages d'édition des CPTs "organisateur", "chasse" et "énigme",
 * sauf pour les administrateurs.
 *
 * @return void
 */
function ajouter_barre_progression_top()
{
  global $post;

  // Récupérer l'utilisateur actuel
  $user = wp_get_current_user();

  // Vérifier si l'utilisateur est administrateur, et ne pas afficher la barre si c'est le cas
  if (in_array('administrator', (array) $user->roles)) {
    return;
  }

  // Vérifier si le CPT est "organisateur", "chasse" ou "enigme"
  if ($post && in_array(get_post_type($post), ['organisateur', 'chasse', 'enigme'])) {
?>
    <script>
      document.addEventListener("DOMContentLoaded", function() {
        let cpt = "<?php echo get_post_type($post); ?>";
        let barreProgression = `
                    <div class="barre-progression">
                        <div class="etape etape-1 ${cpt === 'organisateur' ? 'active-etape' : ''}">
                            ${cpt === 'organisateur' ? '<span class="rond-vert"></span>' : ''} Création Organisateur
                        </div>
                        <div class="etape etape-2 ${cpt === 'chasse' ? 'active-etape' : ''}">
                            ${cpt === 'chasse' ? '<span class="rond-vert"></span>' : ''} Création Chasse
                        </div>
                        <div class="etape etape-3 ${cpt === 'enigme' ? 'active-etape' : ''}">
                            ${cpt === 'enigme' ? '<span class="rond-vert"></span>' : ''} Création Énigme
                        </div>
                        <div class="etape etape-4">Validation</div>
                    </div>
                `;

        let adminPage = document.getElementById("wpbody-content");
        if (adminPage && barreProgression) {
          adminPage.insertAdjacentHTML("afterbegin", barreProgression);
        }
      });
    </script>
  <?php
  }
}
add_action('admin_head', 'ajouter_barre_progression_top');


// ==================================================
// 🧩 FONCTIONS GLOBALES DE FORMATAGE
// ==================================================
/**
 * 🔹 injection_classe_edition_active → Ajoute automatiquement la classe "edition-active" au <body> si l’édition doit être activée.
 * 🔹 mettre_a_jour_relation_acf → Mettre à jour un champ ACF de type relation en s'assurant que le format est correct.
 * 🔹 modifier_relation_acf → Ajouter ou retirer une valeur dans un champ ACF de type relation sans écraser les autres.
 * 🔹 formater_date → Formate une date au format `d/m/Y` ou retourne "Non spécifiée".
 * 🔹 convertir_en_datetime → Convertit une date string en objet DateTime.
 * 🔹 convertir_en_timestamp(?string $date) → Convertit une date au format d/m/Y en timestamp.
 * 🔹 normaliser_chaine → Normalise une chaîne (minuscules + suppression des accents).
 */



/**
 * Injecte automatiquement la classe CSS "edition-active" dans le <body> si l'utilisateur peut éditer le contenu.
 *
 * Cette classe active l'interface d'édition frontale (boutons, panneaux...).
 * Elle s'applique uniquement :
 * - au CPT "organisateur" en mode "pending"
 * - si l'utilisateur connecté est l'auteur
 * - et possède le rôle "organisateur_creation"
 *
 * Cette logique est centralisée ici pour éviter les appels manuels dans les templates.
 *
 * @hook body_class
 * @param array $classes Liste actuelle des classes du <body>
 * @return array Liste modifiée avec ou sans "edition-active"
 */
add_filter('body_class', 'injection_classe_edition_active');
function injection_classe_edition_active(array $classes): array
{

  if (!is_user_logged_in()) return $classes;

  global $post;
  if (!$post || !isset($post->post_type)) return $classes;

  $user_id = get_current_user_id();
  $roles = wp_get_current_user()->roles;

  // === ORGANISATEUR ===
  if (
    $post->post_type === 'organisateur' &&
    get_post_status($post) === 'pending' &&
    (int) get_post_field('post_author', $post->ID) === $user_id &&
    in_array('organisateur_creation', $roles, true)
  ) {
    $classes[] = 'edition-active';
  }

  // === CHASSE ===
  if (
    $post->post_type === 'chasse' &&
    get_post_status($post) === 'pending' &&
    in_array('organisateur_creation', $roles, true)
  ) {
    $organisateur_id = get_organisateur_from_chasse($post->ID);
    $associes = get_field('utilisateurs_associes', $organisateur_id, false);
    $associes = is_array($associes) ? array_map('strval', $associes) : [];

    if (in_array((string) $user_id, $associes, true)) {
      $classes[] = 'edition-active-chasse';
    }
  }

  return $classes;
}



/**
 * Met à jour un champ relation ACF en s'assurant que le format est correct et compatible avec l'interface d'admin.
 *
 * @param int    $post_id          ID du post sur lequel mettre à jour la relation.
 * @param string $relation_field    Nom du champ ACF qui stocke la relation.
 * @param int    $related_post_id   ID du post cible à relier.
 * @param string $acf_key           (Optionnel) Clé ACF du champ relation (nécessaire pour `_meta_key`).
 * @param string $group_prefix      (Optionnel) Si le champ est dans un groupe ACF, préciser le préfixe (ex: 'champs_caches_').
 *
 * @return bool True si la mise à jour a été effectuée avec succès, False sinon.
 */
function mettre_a_jour_relation_acf($post_id, $relation_field, $related_post_id, $acf_key = '', $group_prefix = '')
{
  // Vérifier les paramètres requis
  if (empty($post_id) || empty($relation_field) || empty($related_post_id)) {
    error_log("🛑 ERREUR : Paramètres manquants pour mettre à jour la relation ACF.");
    return false;
  }

  // Construction du nom complet du champ si un préfixe est utilisé
  $full_field_name = !empty($group_prefix) ? $group_prefix . $relation_field : $relation_field;

  // Vérifier si la relation est déjà correcte
  $current_value = get_post_meta($post_id, $full_field_name, true);
  if (is_array($current_value) && in_array((string)$related_post_id, $current_value)) {
    return true;
  }

  // Stocker l'ID sous forme de tableau (format attendu par ACF)
  $acf_value = [(string) $related_post_id];

  // Mise à jour de la relation
  update_post_meta($post_id, $full_field_name, $acf_value);

  // Si une clé ACF est fournie, mettre à jour aussi le champ clé
  if (!empty($acf_key)) {
    update_post_meta($post_id, "_{$full_field_name}", $acf_key);
  }

  // Vérification après mise à jour
  $test_value = get_post_meta($post_id, $full_field_name, true);
  if (is_array($test_value) && in_array((string)$related_post_id, $test_value)) {
    return true;
  }

  error_log("🛑 ERREUR : La mise à jour de {$full_field_name} a échoué dans mettre_a_jour_relation_acf().");
  return false;
}

/**
 * 📌 Ajoute ou supprime une relation dans un champ ACF sans écraser les autres valeurs existantes.
 *
 * - Permet d'ajouter une relation (ex: associer une énigme à une chasse).
 * - Permet de supprimer une relation (ex: retirer une énigme supprimée de la chasse associée).
 * - Vérifie si la relation existe avant d'ajouter ou de supprimer.
 * - Met à jour la clé `_meta_key` pour assurer la compatibilité avec ACF.
 *
 * @param int    $post_id         ID du post cible (ex: ID de la chasse).
 * @param string $relation_field  Nom du champ relation (ex: 'champs_caches_enigmes_associees').
 * @param int    $related_post_id ID du post à ajouter ou supprimer (ex: ID de l’énigme).
 * @param string $acf_key         Clé ACF du champ relation.
 * @param string $action          Action à effectuer : 'add' pour ajouter, 'remove' pour supprimer.
 *
 * @return bool True si succès, False sinon.
 */

function modifier_relation_acf($post_id, $relation_field, $related_post_id, $acf_key, $action = 'add')
{
  if (empty($post_id) || empty($relation_field) || empty($related_post_id)) {
    error_log("🛑 ERREUR : Paramètres manquants pour modifier la relation ACF.");
    return false;
  }

  // 🔹 Récupérer les valeurs existantes
  $current_value = get_post_meta($post_id, $relation_field, true);
  if (!is_array($current_value)) {
    $current_value = [];
  }

  if ($action === 'add') {
    // 📌 Ajouter uniquement si l'ID n'est pas déjà présent
    if (!in_array($related_post_id, $current_value)) {
      $current_value[] = $related_post_id;
      update_post_meta($post_id, $relation_field, $current_value);
      update_post_meta($post_id, "_{$relation_field}", $acf_key);

      error_log("✅ Relation ajoutée avec succès : {$relation_field} → {$related_post_id}.");
      return true;
    }
    error_log("ℹ️ Relation déjà existante : {$relation_field} → {$related_post_id}. Aucune modification.");
  } elseif ($action === 'remove') {
    // 📌 Supprimer uniquement si l'ID est présent
    if (in_array($related_post_id, $current_value)) {
      $current_value = array_diff($current_value, [$related_post_id]);
      update_post_meta($post_id, $relation_field, $current_value);
      update_post_meta($post_id, "_{$relation_field}", $acf_key);

      error_log("✅ Relation supprimée avec succès : {$relation_field} → {$related_post_id}.");
      return true;
    }
    error_log("ℹ️ La relation à supprimer n'existait pas : {$relation_field} → {$related_post_id}.");
  } else {
    error_log("🛑 ERREUR : Action inconnue '{$action}' pour modifier_relation_acf().");
  }

  return false;
}

/**
 * 📅 Formate une date au format `d/m/Y` ou retourne "Non spécifiée".
 *
 * @param string|null $date La date à formater.
 * @return string La date formatée ou "Non spécifiée" si invalide.
 */
function formater_date(?string $date): string
{
  if (!$date) return 'Non spécifiée';
  if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $date)) return $date; // Déjà formatée

  $timestamp = strtotime($date);
  return ($timestamp !== false) ? date_i18n('d/m/Y', $timestamp) : 'Non spécifiée';
}
/**
 * 🗓️ Convertit une date string en objet DateTime en testant plusieurs formats.
 *
 * @param string|null $date_string La date à convertir.
 * @param array $formats Liste des formats à tester.
 * @return DateTime|null Objet DateTime si conversion réussie, sinon null.
 */
function convertir_en_datetime(?string $date_string, array $formats = [
  'd/m/Y H:i:s',    // ✅ Correction : Ajout du format avec les secondes
  'd/m/Y H:i',
  'd/m/Y h:i a',
  'd/m/Y',
  'Y-m-d H:i:s',
  'Y-m-d'
]): ?DateTime
{
  if (empty($date_string)) {
    error_log("🚫 Date vide ou non fournie.");
    return null;
  }

  foreach ($formats as $format) {
    $date_obj = DateTime::createFromFormat($format, $date_string);
    if ($date_obj) {
      error_log("✅ Date '{$date_string}' convertie avec le format : {$format}");
      return $date_obj;
    }
  }

  // 🚨 Ajout d'un fallback pour éviter le crash
  error_log("⚠️ Échec de conversion pour la date : '{$date_string}'. Formats testés : " . implode(', ', $formats));
  return new DateTime('now', new DateTimeZone('UTC')); // Retourne la date actuelle au lieu de `null`
}

/**
 * Convertit une date au format d/m/Y en timestamp.
 *
 * @param string|null $date Date au format 'd/m/Y' ou null.
 * @return int|false Timestamp ou false si invalide.
 */
function convertir_en_timestamp(?string $date)
{
  return $date ? strtotime(str_replace('/', '-', $date)) : false;
}

/**
 * 📝 Normalise une chaîne (minuscules + suppression des accents).
 *
 * @param string $chaine La chaîne à normaliser.
 * @return string La chaîne normalisée sans accents et en minuscules.
 */
function normaliser_chaine($chaine)
{
  $chaine = strtolower($chaine); // Mise en minuscules
  $chaine = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $chaine); // Supprime les accents
  return trim($chaine); // Supprime les espaces superflus
}




// ==================================================
// 🧩 GESTION DE CHAMPS ACF PARTICULIERS
// ==================================================
/**
 * 🔹 assigner_organisateur_a_chasse → Assigner automatiquement un organisateur à une chasse lors de sa création.
 * 🔹 modifier_bouton_soumission_cpts → Modifier l'intitulé du bouton de soumission pour les CPTs "chasse", "énigme" et "organisateur".
 * 🔹 acf/input/admin_footer (function) → Supprimer certains champs ACF selon le type de post et le rôle utilisateur.
 * 🔹 pre_remplir_utilisateur_associe → Pré-remplir le champ ACF "utilisateurs_associes" avec l'auteur du CPT "organisateur".
 * 🔹 admin_init (function) → Stocker temporairement l'ID de la chasse en cours de création.
 * 🔹 acf/fields/relationship/query (function) → Filtrer les énigmes affichées dans le champ ACF "pre_requis".
 * 🔹 acf/load_field/name=chasse_associee (function) → Pré-remplir le champ "chasse_associee" uniquement lors de la création.
 * 🔹 acf/save_post (function) → À la création d’une énigme, mettre à jour les relations ACF `énigmes_associees` de la chasse associée.
 * 🔹 nettoyer_relations_orphelines → Nettoyer les relations ACF orphelines dans le champ `champs_caches_enigmes_associees`.
 * 🔹 before_delete_post (function) → Gérer la suppression d'une énigme : mise à jour des relations dans la chasse associée.
 * 🔹 mettre_a_jour_sous_champ_group → Mettre à jour un sous-champ dans un groupe ACF sans écraser les autres sous-champs.
 * 🔹 mettre_a_jour_sous_champ_par_cle → Mettre à jour un sous-champ d'un groupe ACF via field_key (champ imbriqué, ACF strict).
 */

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
      error_log("🛑 Échec de la mise à jour de organisateur_chasse pour la chasse $post_id");
    }
  } else {
    error_log("🛑 Aucun organisateur trouvé pour la chasse $post_id (aucune mise à jour)");
  }
}
// Ajout du hook pour exécuter la fonction à la sauvegarde du CPT chasse
add_action('save_post_chasse', 'assigner_organisateur_a_chasse', 20, 2);

/**
 * Modifie l'intitulé du bouton de soumission pour les CPTs "chasse", "énigme" et "organisateur".
 * - En création (`post-new.php`) : "Créer X"
 * - En modification (`post.php`) : "Modifier X"
 */
function modifier_bouton_soumission_cpts()
{
  global $post, $pagenow;

  // Déterminer si on est en création ou en modification
  $is_creation = ($pagenow === 'post-new.php' && isset($_GET['post_type']) && in_array($_GET['post_type'], ['chasse', 'enigme', 'organisateur']));
  $is_modification = ($pagenow === 'post.php' && isset($post) && in_array(get_post_type($post), ['chasse', 'enigme', 'organisateur']));

  if ($is_creation || $is_modification) {
  ?>
    <script>
      document.addEventListener("DOMContentLoaded", function() {
        let boutonSoumission = document.querySelector("#publish");
        if (boutonSoumission) {
          let cptLabel = {
            'chasse': "<?php echo $is_creation ? 'Créer' : 'Modifier'; ?> chasse",
            'enigme': "<?php echo $is_creation ? 'Créer' : 'Modifier'; ?> énigme",
            'organisateur': "<?php echo $is_creation ? 'Créer' : 'Modifier'; ?> organisateur"
          };

          let postType = "<?php echo esc_js($is_creation ? $_GET['post_type'] : get_post_type($post)); ?>";
          boutonSoumission.value = cptLabel[postType] || "<?php echo $is_creation ? 'Créer' : 'Modifier'; ?> publication";
        }
      });
    </script>
  <?php
  }
}
add_action('admin_footer', 'modifier_bouton_soumission_cpts');

/**
 * Supprime les champs ACF spécifiques en fonction du type de post et du rôle utilisateur.
 *
 * - Supprime le groupe "champs cachés" (`field_67ca86837181a`) sur le CPT "chasse" pour les non-admins.
 * - Supprime le champ "utilisateurs_associes" (`field_67ce8d5a3bed9`) sur le CPT "organisateur" pour les non-admins.
 *
 * @return void
 */
add_action('acf/input/admin_footer', function () {
  $post_type = get_post_type();

  if (!current_user_can('manage_options')) {
  ?>
    <script>
      document.addEventListener("DOMContentLoaded", function() {
        <?php if ($post_type === 'chasse') : ?>
          document.querySelectorAll('.acf-field-group[data-key="field_67ca86837181a"]').forEach(el => el.remove());
        <?php endif; ?>
        <?php if ($post_type === 'organisateur') : ?>
          document.querySelectorAll('.acf-field[data-key="field_67ce8d5a3bed9"]').forEach(el => el.remove());
        <?php endif; ?>
      });
    </script>
<?php
  }
});

/**
 * Pré-remplit le champ ACF "utilisateurs_associes" avec l'auteur du CPT "organisateur".
 *
 * @param int $post_id ID du post en cours de sauvegarde.
 * @return void
 */
function pre_remplir_utilisateur_associe($post_id)
{
  if (get_post_type($post_id) !== 'organisateur') {
    return;
  }

  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
    return;
  }

  $auteur_id = get_post_field('post_author', $post_id);
  if (!$auteur_id) {
    return;
  }

  $utilisateurs_associes = get_post_meta($post_id, 'utilisateurs_associes', true);

  if (empty($utilisateurs_associes) || !is_array($utilisateurs_associes)) {
    // Stocker uniquement l’auteur sous forme de tableau non sérialisé
    update_field('utilisateurs_associes', [strval($auteur_id)], $post_id);
  }
}
add_action('acf/save_post', 'pre_remplir_utilisateur_associe', 20);

/**
 * Stocke temporairement l'ID de la chasse en cours de création.
 *
 * - En création : Stocke `$_GET['chasse_associee']` dans une option WordPress.
 * - En édition : Ne fait rien, car l'ID de la chasse est déjà enregistré en base.
 *
 * @return void
 */
add_action('admin_init', function () {
  global $post;

  // Vérifie si on est en mode édition (post existant)
  if (!empty($post->ID) && get_post_type($post->ID) === 'enigme') {
    return;
  }

  // Stocke l'ID de la chasse en création si présent dans l'URL
  if (isset($_GET['chasse_associee']) && is_numeric($_GET['chasse_associee'])) {
    update_option('chasse_associee_temp', (int) $_GET['chasse_associee'], false);
  }
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


/**
 * ✅ Met à jour un sous-champ d’un groupe ACF en respectant les formats attendus.
 *
 * Gère correctement les champs de type `date_time_picker`, `relationship`, `taxonomy`, etc.
 * Supprime les champs incompatibles vides avant enregistrement.
 *
 * @param int    $post_id           ID du post concerné
 * @param string $group_key_or_name Nom du groupe ACF (clé ou name)
 * @param string $subfield_name     Nom du sous-champ à mettre à jour
 * @param mixed  $new_value         Nouvelle valeur à enregistrer
 * @return bool True si la mise à jour est réussie, false sinon
 */
function mettre_a_jour_sous_champ_group(int $post_id, string $group_key_or_name, string $subfield_name, $new_value): bool {
  if (!$post_id || !$group_key_or_name || !$subfield_name) {
    error_log('❌ Paramètres manquants dans mettre_a_jour_sous_champ_group()');
    return false;
  }
  
  

  $group_object = get_field_object($group_key_or_name, $post_id);

    if (!$group_object || empty($group_object['sub_fields'])) {
        // Tentative d'initialisation minimale si jamais le groupe n'est pas encore enregistré
        error_log("⚠️ Groupe $group_key_or_name vide ou absent — tentative de réinitialisation forcée.");
        update_field($group_key_or_name, [], $post_id);
        $group_object = get_field_object($group_key_or_name, $post_id);
        if (!$group_object || empty($group_object['sub_fields'])) {
            error_log("❌ Groupe ACF toujours introuvable après tentative d'initialisation : $group_key_or_name");
            return false;
        }
    }


  $groupe = get_field($group_object['name'], $post_id);
  if (!is_array($groupe)) {
    $groupe = [];
  }

  // Injection de la nouvelle valeur
  $groupe[$subfield_name] = $new_value;

  $champ_a_enregistrer = [];

  foreach ($group_object['sub_fields'] as $sub_field) {
    $name = $sub_field['name'];
    $type = $sub_field['type'];

    if (!array_key_exists($name, $groupe) && $name !== $subfield_name) {
      $valeur = ''; // Valeur vide par défaut
    } else {
      $valeur = ($name === $subfield_name) ? $new_value : $groupe[$name];
    }

    $valeur = ($name === $subfield_name) ? $new_value : $groupe[$name];

    if ($type === 'date_time_picker') {
      if (preg_match('/^\d{4}-\d{2}-\d{2}$/', trim((string) $valeur))) {
        $valeur .= ' 00:00:00';
        // Met aussi à jour $new_value si c'est le champ concerné
        if ($name === $subfield_name && !str_contains($new_value, ':')) {
          $new_value .= ' 00:00:00';
        }
      }
    }

    // 🧹 Nettoyage relation vide
    if (
      in_array($type, ['relationship', 'post_object', 'taxonomy'], true) &&
      (is_null($valeur) || $valeur === '' || $valeur === [])
    ) {
      continue; // Ne pas enregistrer de valeur vide
    }
    
    // Forcer la valeur modifiée à passer même si absente du groupe d'origine
    if ($name === $subfield_name) {
        $valeur = $new_value;
    }
    $champ_a_enregistrer[$name] = $valeur;

  }
  
  delete_field($group_object['name'], $post_id);
  error_log('[DEBUG] Données envoyées à update_field() pour groupe ' . $group_object['name'] . ' : ' . json_encode($champ_a_enregistrer));

  $ok = update_field($group_object['name'], $champ_a_enregistrer, $post_id);
  clean_post_cache($post_id);

  // 🧪 Vérification lecture après update
  $groupe_verif = get_field($group_object['name'], $post_id);
  
    $str_valeur = is_array($new_value) ? json_encode($new_value) : $new_value;
    error_log("🧪 [DEBUG ACF] Mise à jour demandée : $group_key_or_name.$subfield_name → $str_valeur (post #$post_id)");

    $groupe_verif = get_field($group_key_or_name, $post_id);
    error_log("📥 [DEBUG ACF] Relecture après update : " . json_encode($groupe_verif));

  
  
  if (isset($groupe_verif[$subfield_name])) {
    $valeur_relue = $groupe_verif[$subfield_name];
    
    // 🕒 Ajoute heure si champ est date_time_picker sans heure
    foreach ($group_object['sub_fields'] as $sub_field) {
        if ($sub_field['name'] === $subfield_name && $sub_field['type'] === 'date_time_picker') {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', trim((string) $new_value))) {
                $new_value .= ' 00:00:00';
            }
        }
    }
    
    $str_new = is_array($new_value) ? implode(',', $new_value) : (string) $new_value;
    $str_relue = is_array($valeur_relue) ? implode(',', $valeur_relue) : (string) $valeur_relue;

    return wp_strip_all_tags($str_new) === wp_strip_all_tags($str_relue);
  }

  error_log("❌ Échec de vérification pour $subfield_name dans {$group_object['name']}");
  return false;
}



// ==================================================
//  🏗️ CRÉATION & ÉDITION ORGANISATEUR
// ==================================================
/**
 * 🔹 creer_organisateur_pour_utilisateur → Crée un post "organisateur" lié à un utilisateur donné, s’il n’en possède pas déjà.
 * 🔹 enqueue_script_organisateur_edit → Charge les scripts JS d’édition frontale si l’utilisateur peut modifier un organisateur.
 * 🔹 modifier_champ_organisateur (AJAX) → Enregistre dynamiquement un champ ACF (liens, email, etc.).
 * 🔹 modifier_titre_organisateur (AJAX) → Met à jour dynamiquement le titre du CPT organisateur.
 * 🔹 rediriger_selon_etat_organisateur → Redirige automatiquement selon l’état du CPT organisateur (draft/pending/publish).
 * 🔹 organisateur_get_liste_liens_publics → Liste tous les types de lien supportés (label + icône).
 * 🔹 organisateur_get_lien_public_infos → Donne les infos (label + icône) pour un type de lien donné.
 */


/**
 * Crée un CPT "organisateur" pour un utilisateur donné, s’il n’en possède pas déjà.
 *
 * - Le post est créé avec le statut "pending"
 * - Le champ ACF "utilisateurs_associes" est rempli
 * - Le champ "profil_public" est prérempli (logo + email)
 *
 * @param int $user_id ID de l’utilisateur.
 * @return int|null ID du post créé ou null si échec ou déjà existant.
 */
function creer_organisateur_pour_utilisateur($user_id)
{
  if (!is_int($user_id) || $user_id <= 0) {
    error_log("❌ ID utilisateur invalide : $user_id");
    return null;
  }

  // Vérifie si un organisateur est déjà lié à cet utilisateur
  $existant = get_organisateur_from_user($user_id);
  if ($existant) {
    error_log("ℹ️ Un organisateur existe déjà pour l'utilisateur $user_id (ID : $existant)");
    return null;
  }

  // Crée le post "organisateur" avec statut pending
  $post_id = wp_insert_post([
    'post_type'   => 'organisateur',
    'post_status' => 'pending',
    'post_title'  => 'Votre nom d’organisateur',
    'post_author' => $user_id,
  ]);

  if (is_wp_error($post_id)) {
    error_log("❌ Erreur création organisateur : " . $post_id->get_error_message());
    return null;
  }

  // Liaison utilisateur (champ relation)
  update_field('utilisateurs_associes', [strval($user_id)], $post_id);

  // Préremplissage logo + email
  $user_data = get_userdata($user_id);
  $email = $user_data ? $user_data->user_email : '';

  $profil_public = get_field('profil_public', $post_id);
  if (!is_array($profil_public)) {
    $profil_public = [];
  }

  $profil_public['logo_organisateur'] = 3927;
  $profil_public['email_contact'] = $email;

  update_field('profil_public', $profil_public, $post_id);

  error_log("✅ Organisateur créé (pending) pour user $user_id : post ID $post_id");

  return $post_id;
}


/**
 * Charge les scripts JS pour l’édition frontale d’un organisateur (header + panneau).
 *
 * Chargé uniquement si l’utilisateur peut modifier l’organisateur lié.
 *
 * @hook wp_enqueue_scripts
 */
function enqueue_script_organisateur_edit()
{
  $cpts = ['organisateur', 'chasse'];

  if (!is_singular($cpts)) return;

  $post_id = get_the_ID();
  $type = get_post_type($post_id);
  $organisateur_id = null;

  if ($type === 'organisateur') {
    $organisateur_id = $post_id;
  } elseif ($type === 'chasse') {
    $organisateur_id = get_organisateur_from_chasse($post_id);

    if (!$organisateur_id && get_post_status($post_id) === 'pending') {
      $organisateur_id = get_organisateur_from_user(get_current_user_id());
    }
  }

  if ($organisateur_id && utilisateur_peut_modifier_post($organisateur_id)) {
    $theme_uri = get_stylesheet_directory_uri();
    $theme_dir = get_stylesheet_directory();

    // 📦 Modules JS partagés
    enqueue_core_edit_scripts();

    // 📤 Script organisateur
    $path = '/assets/js/organisateur-edit.js';
    $version = file_exists($theme_dir . $path) ? filemtime($theme_dir . $path) : null;

    wp_enqueue_script(
      'organisateur-edit',
      $theme_uri . $path,
      ['champ-init', 'helpers', 'ajax', 'ui'],
      $version,
      true
    );

    // ✅ Injection JavaScript APRÈS le enqueue (très important)
    $author_id = (int) get_post_field('post_author', $organisateur_id);
    $default_email = get_the_author_meta('user_email', $author_id);

    wp_localize_script('organisateur-edit', 'organisateurData', [
      'defaultEmail' => esc_js($default_email)
    ]);

    wp_enqueue_media();
  }
}
add_action('wp_enqueue_scripts', 'enqueue_script_organisateur_edit');


/**
 * 🔹 Enregistrement AJAX d’un champ ACF de l’organisateur connecté.
 */
add_action('wp_ajax_modifier_champ_organisateur', 'ajax_modifier_champ_organisateur');
function ajax_modifier_champ_organisateur()
{
  // 🛡️ Sécurité minimale : utilisateur connecté
  if (!is_user_logged_in()) {
    wp_send_json_error('non_connecte');
  }

  $user_id = get_current_user_id();
  $champ   = sanitize_text_field($_POST['champ'] ?? '');
  $valeur  = wp_kses_post($_POST['valeur'] ?? '');
  $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;

  // 🧭 Si appel depuis une chasse, on remonte à l’organisateur
  if ($post_id && get_post_type($post_id) === 'chasse') {
    $post_id = get_organisateur_from_chasse($post_id);
  }

  if (!$champ || !isset($_POST['valeur'])) {
    wp_send_json_error('⚠️ donnees_invalides');
  }

  if (!$post_id) {
    wp_send_json_error('⚠️ organisateur_introuvable');
  }

  // 🔒 Vérifie que l’utilisateur est bien auteur du post
  $auteur = (int) get_post_field('post_author', $post_id);
  if ($auteur !== $user_id) {
    wp_send_json_error('⚠️ acces_refuse');
  }

  // 🗺️ Table de correspondance si champ dans un groupe ACF
  $champ_correspondances = [
    'description_courte'                => 'profil_public_description_courte',
    'email_contact'                     => 'profil_public_email_contact',
    'profil_public_description_courte' => 'profil_public_description_courte',
    'parlez_de_vous_presentation'       => 'description_longue',
  ];

  // 🔁 Corrige le nom du champ si groupé
  $champ_cible = $champ_correspondances[$champ] ?? $champ;

  // ✏️ Titre natif WordPress
  if ($champ === 'post_title') {
    $ok = wp_update_post([
      'ID'         => $post_id,
      'post_title' => $valeur
    ], true);

    if (is_wp_error($ok)) {
      wp_send_json_error('⚠️ echec_update_post_title');
    }

    wp_send_json_success([
      'champ'  => $champ,
      'valeur' => $valeur
    ]);
  }

  // 🔗 Liens publics (répéteur)
  if ($champ === 'liens_publics') {
    $tableau = json_decode(stripslashes($valeur), true);

    if (!is_array($tableau)) {
      wp_send_json_error('⚠️ format_invalide');
    }

    $repetitions = array_values(array_filter(array_map(function ($ligne) {
      $type = sanitize_text_field($ligne['type_de_lien'] ?? '');
      $url  = esc_url_raw($ligne['url_lien'] ?? '');

      if (!$type || !$url) return null;

      return [
        'type_de_lien' => [$type], // 🔁 forcé array (select multiple)
        'url_lien'     => $url
      ];
    }, $tableau)));

    $ok = update_field('liens_publics', $repetitions, $post_id);

    // ✅ ASTUCE MAJEURE : ACF retourne false si même valeur que l’existant → comparer aussi
    $enregistre = get_field('liens_publics', $post_id);
    $equivalent = json_encode($enregistre) === json_encode($repetitions);

    if ($ok || $equivalent) {
      wp_send_json_success([
        'champ'  => $champ,
        'valeur' => $repetitions
      ]);
    }

    wp_send_json_error('⚠️ echec_mise_a_jour_liens');
  }

  // 🏦 Coordonnées bancaires
  if ($champ === 'coordonnees_bancaires') {
    $donnees = json_decode(stripslashes($valeur), true);
    $iban = sanitize_text_field($donnees['iban'] ?? '');
    $bic  = sanitize_text_field($donnees['bic'] ?? '');

    $ok = update_field('field_67d6715f90045', [
      'iban' => $iban,
      'bic'  => $bic,
    ], $post_id);

    // 🔍 Vérifie même si update_field retourne false
    $enregistre = get_field('coordonnees_bancaires', $post_id);
    $sameIban = ($enregistre['iban'] ?? '') === $iban;
    $sameBic  = ($enregistre['bic'] ?? '') === $bic;

    if ($ok || ($sameIban && $sameBic)) {
      wp_send_json_success([
        'champ'  => $champ,
        'valeur' => ['iban' => $iban, 'bic' => $bic]
      ]);
    }

    wp_send_json_error('⚠️ echec_mise_a_jour_coordonnees');
  }

  // ✅ Autres champs ACF simples
  $ok = update_field($champ_cible, is_numeric($valeur) ? (int) $valeur : $valeur, $post_id);

  // 🔍 Vérifie via get_post_meta en fallback
  $valeur_meta = get_post_meta($post_id, $champ_cible, true);
  $valeur_comparee = stripslashes_deep($valeur);

  if ($ok || trim((string) $valeur_meta) === trim((string) $valeur_comparee)) {
    wp_send_json_success([
      'champ'  => $champ,
      'valeur' => $valeur
    ]);
  }

  wp_send_json_error('⚠️ echec_mise_a_jour_final');
}


/**
 * Redirige l’utilisateur connecté selon l’état de son CPT "organisateur".
 *
 * - Si aucun organisateur : ne fait rien
 * - Si statut "draft" ou "pending" : redirige vers la prévisualisation
 * - Si statut "publish" : redirige vers la page publique
 *
 * @return void
 */
function rediriger_selon_etat_organisateur()
{
  if (!is_user_logged_in()) {
    return;
  }

  $user_id = get_current_user_id();
  $organisateur_id = get_organisateur_from_user($user_id);

  if (!$organisateur_id) {
    return; // Aucun organisateur : accès au canevas autorisé
  }

  $post = get_post($organisateur_id);

  switch ($post->post_status) {
    case 'pending':
      $preview_url = add_query_arg([
        'preview' => 'true',
        'preview_id' => $post->ID
      ], get_permalink($post));
      wp_safe_redirect($preview_url);
      exit;

    case 'publish':
      wp_safe_redirect(get_permalink($post));
      exit;
  }
}


add_action('wp_ajax_modifier_titre_organisateur', 'modifier_titre_organisateur');
/**
 * 🔹 modifier_titre_organisateur (AJAX)
 *
 * Met à jour dynamiquement le post_title du CPT organisateur de l’utilisateur connecté.
 *
 * - Ne fonctionne que si l’utilisateur est bien l’auteur du CPT
 * - Refuse les titres vides ou les accès croisés
 * - Retourne une réponse JSON avec la nouvelle valeur ou un message d’erreur
 *
 * @hook wp_ajax_modifier_titre_organisateur
 */
function modifier_titre_organisateur()
{
  error_log('== FICHIER AJAX ORGANISATEUR CHARGÉ ==');
  error_log('== ENTREE AJAX modifier_titre_organisateur ==');

  if (!is_user_logged_in()) {
    wp_send_json_error('non_connecte');
  }

  $user_id = get_current_user_id();
  $titre = sanitize_text_field($_POST['valeur'] ?? '');

  if ($titre === '') {
    wp_send_json_error('titre_vide');
  }

  $organisateur_id = get_organisateur_from_user($user_id);
  if (!$organisateur_id) {
    wp_send_json_error('organisateur_introuvable');
  }

  $auteur = (int) get_post_field('post_author', $organisateur_id);
  if ($auteur !== $user_id) {
    wp_send_json_error('acces_refuse');
  }

  $result = wp_update_post([
    'ID'         => $organisateur_id,
    'post_title' => $titre,
  ], true);

  error_log("=== DEBUG TITRE ===");
  error_log("Résultat : " . print_r($result, true));
  $post = get_post($organisateur_id);
  error_log("Titre réel en base : " . $post->post_title);


  error_log("=== MODIF ORGANISATEUR ===");
  error_log("User ID: " . $user_id);
  error_log("Post ID: " . $organisateur_id);
  error_log("Titre envoyé : " . $titre);



  if (is_wp_error($result)) {
    wp_send_json_error('echec_mise_a_jour');
  }

  wp_send_json_success([
    'valeur' => $titre,
  ]);
}

/**
 * Retourne la liste complète des types de lien public supportés.
 *
 * Chaque type est représenté par un tableau contenant :
 * - 'label' : Nom lisible du lien (ex : "Site Web", "Discord", ...)
 * - 'icone' : Classe FontAwesome correspondant à l’icône à afficher
 *
 * @return array Liste des types de lien public.
 */
function organisateur_get_liste_liens_publics()
{
  return [
    'site_web' => [
      'label' => 'Site Web',
      'icone' => 'fa-solid fa-globe'
    ],
    'discord' => [
      'label' => 'Discord',
      'icone' => 'fa-brands fa-discord'
    ],
    'facebook' => [
      'label' => 'Facebook',
      'icone' => 'fa-brands fa-facebook-f'
    ],
    'twitter' => [
      'label' => 'Twitter/X',
      'icone' => 'fa-brands fa-x-twitter'
    ],
    'instagram' => [
      'label' => 'Instagram',
      'icone' => 'fa-brands fa-instagram'
    ],
  ];
}

/**
 * Retourne les informations associées à un type de lien public donné.
 *
 * Si le type n’est pas reconnu, un fallback est retourné avec :
 * - Label = ucfirst du type
 * - Icône = fa-solid fa-link
 *
 * @param string $type_de_lien Type de lien à interroger (ex : "discord", "site_web").
 * @return array ['label' => string, 'icone' => string]
 */
function organisateur_get_lien_public_infos($type_de_lien)
{
  $liens = organisateur_get_liste_liens_publics();
  $type = strtolower(trim($type_de_lien));

  return $liens[$type] ?? [
    'label' => ucfirst($type),
    'icone' => 'fa-solid fa-link'
  ];
}

// ==================================================
//  🏗️ CRÉATION & ÉDITION CHASSE
// ==================================================
/**
 * 🔹 enqueue_script_chasse_edit() → Charge les scripts JS d’édition frontale pour les chasses (panneau, champs, inline).
 * 🔹 register_endpoint_creer_chasse() → Enregistre l’URL personnalisée /creer-chasse/ pour la création frontale d’une chasse.
 * 🔹 creer_chasse_et_rediriger_si_appel() → Crée automatiquement une chasse si l’URL /creer-chasse/ est visitée, puis redirige vers sa prévisualisation.
 * 🔹 chasse_est_en_phase_initiale() → Détermine si une chasse est en cours de création (rôle, statut, relation ACF).
 * 🔹 modifier_champ_chasse() → Gère l’enregistrement AJAX des champs ACF ou natifs du CPT chasse (post_title inclus).
 */


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

  $theme_uri = get_stylesheet_directory_uri();
  $theme_dir = get_stylesheet_directory();

  // Enfile les scripts partagés (helpers, ui, etc.)
  enqueue_core_edit_scripts();

  // Script spécifique à la chasse
  $path = '/assets/js/chasse-edit.js';
  $file = $theme_dir . $path;
  $version = file_exists($file) ? filemtime($file) : null;

  wp_enqueue_script(
    'chasse-edit',
    $theme_uri . $path,
    ['helpers', 'ajax', 'ui', 'champ-init'],
    $version,
    true
  );

  // Injecte les valeurs par défaut pour JS
  wp_localize_script('champ-init', 'CHP_CHASSE_DEFAUT', [
    'titre' => 'nouvelle chasse',
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

  $user_id = get_current_user_id();
  error_log("👤 Utilisateur connecté : {$user_id}");

  // 📎 Récupération de l'organisateur lié
  $organisateur_id = get_organisateur_from_user($user_id);
  if (!$organisateur_id) {
    error_log("🛑 Aucun organisateur trouvé pour l'utilisateur {$user_id}");
    wp_die('Aucun organisateur associé.');
  }
  error_log("✅ Organisateur trouvé : {$organisateur_id}");

  // 📝 Création du post "chasse"
  $post_id = wp_insert_post([
    'post_type'   => 'chasse',
    'post_status' => 'pending',
    'post_title'  => 'Nouvelle chasse',
    'post_author' => $user_id,
  ]);

  if (is_wp_error($post_id)) {
    error_log("🛑 Erreur création post : " . $post_id->get_error_message());
    wp_die('Erreur lors de la création de la chasse.');
  }

  error_log("✅ Chasse créée avec l’ID : {$post_id}");

  update_field('chasse_principale_image', 3902, $post_id);


  // 📅 Préparation des valeurs
  $today = current_time('Y-m-d');
  $in_two_years = date('Y-m-d', strtotime('+2 years'));

  // ✅ Mise à jour du groupe ACF "caracteristiques"
  update_field('caracteristiques', [
    'chasse_infos_date_debut'        => $today,
    'chasse_infos_date_fin'          => $in_two_years,
    'chasse_infos_duree_illimitee'   => false,
  ], $post_id);

  // ✅ Mise à jour du groupe ACF "champs_caches"
  update_field('champs_caches', [
    'chasse_cache_statut'            => 'revision',
    'chasse_cache_statut_validation' => 'creation',
    'chasse_cache_organisateur'      => [$organisateur_id],
  ], $post_id);

  // 🚀 Redirection vers la prévisualisation frontale avec panneau ouvert
  $preview_url = add_query_arg('edition', 'open', get_preview_post_link($post_id));
  error_log("➡️ Redirection vers : {$preview_url}");
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

  $doit_recalculer_statut = false;
  $champ_valide = false;
  $reponse = ['champ' => $champ, 'valeur' => $valeur];
  // 🛡️ Initialisation sécurisée du groupe caracteristiques
    $groupe_actuel = get_field('caracteristiques', $post_id);
    if (!is_array($groupe_actuel)) {
      error_log("⚠️ Groupe caracteristiques vide ou absent — tentative de réinitialisation forcée.");
    
      $groupe_init = [
        'chasse_infos_date_debut'        => '',
        'chasse_infos_date_fin'          => '',
        'chasse_infos_duree_illimitee'   => 0,
        'chasse_infos_recompense_valeur' => '',
        'chasse_infos_recompense_titre'  => '',
        'chasse_infos_recompense_texte'  => '',
        'chasse_infos_nb_max_gagants'    => 0,
        'chasse_infos_cout_points'       => 0,
      ];
    
      $ok_init = update_field('caracteristiques', $groupe_init, $post_id);
      if (!$ok_init) {
        error_log("❌ Groupe ACF toujours introuvable après tentative d'initialisation : caracteristiques");
      } else {
        error_log("✅ Groupe caracteristiques initialisé manuellement pour post #$post_id");
      }
    }


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
  $champs_dates = [
    'caracteristiques.chasse_infos_date_debut',
    'caracteristiques.chasse_infos_date_fin'
  ];
  if (in_array($champ, $champs_dates, true)) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $valeur)) {
      wp_send_json_error('⚠️ format_date_invalide');
    }
    $sous_champ = str_replace('caracteristiques.', '', $champ);
    $ok = mettre_a_jour_sous_champ_group($post_id, 'caracteristiques', $sous_champ, $valeur);
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
    error_log("🧪 Illimitée (après MAJ) = " . var_export($carac_maj['chasse_infos_duree_illimitee'], true));


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
    error_log("🧪 Correction tentative : MAJ cout_points → valeur = {$valeur}");
    $ok = mettre_a_jour_sous_champ_group($post_id, 'caracteristiques', 'chasse_infos_cout_points', (int) $valeur);
    if ($ok) {
      error_log("✅ MAJ réussie pour chasse_infos_cout_points");
      $champ_valide = true;
      $doit_recalculer_statut = true;
    } else {
      error_log("❌ MAJ échouée malgré nom exact");
    }
  }
  
    // 🔹 Déclenchement de la publication différée des solutions
    if ($champ === 'champs_caches.chasse_cache_statut' && $valeur === 'termine') {
        $champ_valide = true;
    
        $liste_enigmes = recuperer_enigmes_associees($post_id);
        if (!empty($liste_enigmes)) {
            foreach ($liste_enigmes as $enigme_id) {
                error_log("🧩 Planification/déplacement : énigme #$enigme_id");
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
      error_log("[🔁 RELOAD] Relecture avant recalcul : " . json_encode($caracteristiques));
      mettre_a_jour_statuts_chasse($post_id);
    }
  wp_send_json_success($reponse);
}



// ==================================================
//  🧹 CRÉATION & ÉDITION ÉNIGME
// ==================================================
/**
 * 🔹 enqueue_script_enigme_edit() → Charge les scripts JS d’édition frontale pour les énigmes (header + panneau).
 * 🔹 creer_enigme_pour_chasse() → Crée une énigme liée à une chasse, avec champs ACF par défaut.
 * 🔹 register_endpoint_creer_enigme() → Enregistre l’URL personnalisée /creer-enigme/ pour la création frontale d’une énigme.
 * 🔹 creer_enigme_et_rediriger_si_appel() → Crée automatiquement une énigme si l’URL /creer-enigme/?chasse_id= est visitée, puis redirige vers sa page.
 * 🔹 modifier_champ_enigme() → Enregistrement AJAX d’un champ de l’énigme (post_title ou ACF simples).
 * 🔹 enregistrer_fichier_solution_enigme() → Enregistre le fichier PDF de solution envoyé en AJAX (champ ACF enigme_solution_fichier).
 * 🔹 rediriger_upload_fichier_solution() → Redirige l’upload de fichiers vers le dossier protégé /protected/solutions.
 * 🔹 deplacer_pdf_solution() → Déplace un fichier PDF vers le dossier public si les conditions sont remplies.
 * 🔹 planifier_ou_deplacer_pdf_solution_immediatement() → Déclenche immédiatement ou programme le déplacement du PDF selon délai + heure.
 * 🔹 rediriger_upload_image_enigme() → Force l’upload des visuels d’énigmes dans un sous-dossier /uploads/_enigmes/enigme-ID/
 * 🔹 injecter_htaccess_protection_images_enigme() → Injecte un .htaccess bloquant l’accès aux images d’une énigme.
 * 🔹 verrouiller_visuels_enigme_si_nouveau_upload() → Injecte un .htaccess juste après ajout d’image (champ enigme_visuel_image).
 * 🔹 filtrer_visuels_enigme_front() → Transforme les URLs du champ gallery en front uniquement.
 * 🔹 desactiver_htaccess_temporairement_enigme() → Désactive temporairement la protection d’image d’une énigme via AJAX.
 * 🔹 reactiver_htaccess_protection_enigme_apres_save() → Réinjecte la protection après enregistrement de l’énigme.
 * 🔹 verifier_expiration_desactivations_htaccess() → Réactive les protections expirées automatiquement.
 * 🔹 restaurer_htaccess_si_temporairement_desactive() → Restaurateur de .htaccess depuis .tmp si oubli.
 * 🔹 reactiver_htaccess_immediat_enigme() → Réactive immédiatement la protection si le panneau est fermé sans enregistrer.
 * 🔹 get_expiration_htaccess_enigme() → Retourne l’expiration de la désactivation temporaire.
 * 🔹 verrouillage_termine_enigme() → Déclencheur AJAX appelé automatiquement à la fin du compte à rebours d’édition images.
 * 🔹 purger_htaccess_temp_enigmes() → Parcourt les énigmes et restaure automatiquement les protections expirées (.htaccess.tmp).
 * 🔹 enregistrer_cron_purge_htaccess() → Programme une tâche WordPress toutes les 5 minutes pour exécuter la purge.
 */


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


/**
 * Enregistre un fichier PDF de solution transmis via AJAX (inline)
 *
 * @return void (JSON)
 */
add_action('wp_ajax_enregistrer_fichier_solution_enigme', 'enregistrer_fichier_solution_enigme');
function enregistrer_fichier_solution_enigme() {
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
function rediriger_upload_fichier_solution($dirs) {
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
function deplacer_pdf_solution($enigme_id) {
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
function planifier_ou_deplacer_pdf_solution_immediatement($enigme_id) {
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


/**
 * Cette fonction permet de conserver les images visibles via ACF tout en les isolant
 * dans un répertoire structuré. Ce filtre est temporairement activé pendant l’upload
 * du champ `enigme_visuel_image`, pour éviter d’impacter les autres envois.
 *
 * @hook acf/upload_prefilter/name=enigme_visuel_image
 * @hook acf/upload_file/name=enigme_visuel_image
 */
function rediriger_upload_image_enigme($dirs)
{
  if (!isset($_REQUEST['post_id'])) return $dirs;

  $post_id = intval($_REQUEST['post_id']);
  if (get_post_type($post_id) !== 'enigme') return $dirs;

  $sous_dossier = '/_enigmes/enigme-' . $post_id;

  $dirs['subdir'] = $sous_dossier;
  $dirs['path']   = $dirs['basedir'] . $sous_dossier;
  $dirs['url']    = $dirs['baseurl'] . $sous_dossier;

  return $dirs;
}

// 🎯 Activation ciblée uniquement pendant l’upload du champ enigme_visuel_image
add_filter('acf/upload_prefilter/name=enigme_visuel_image', function ($errors, $file, $field) {
  add_filter('upload_dir', 'rediriger_upload_image_enigme');
  return $errors;
}, 10, 3);

add_filter('acf/upload_file/name=enigme_visuel_image', function ($file) {
  remove_filter('upload_dir', 'rediriger_upload_image_enigme');
  return $file;
});


/**
 * Injecte un fichier .htaccess dans le dossier /uploads/_enigmes/enigme-{ID}/
 * pour empêcher l’accès direct aux images, sauf depuis l’administration WordPress.
 *
 * Le fichier est écrit uniquement si :
 * - le post est de type 'enigme'
 * - le dossier existe (ou est créé)
 * - le fichier .htaccess n'existe pas déjà (sauf si $forcer = true)
 *
 * @param int  $post_id  ID de l’énigme
 * @param bool $forcer   Si true, écrase le fichier existant
 * @return bool
 */
function injecter_htaccess_protection_images_enigme($post_id, bool $forcer = false) {
    $post_id = (int) $post_id;
    if ($post_id <= 0 || get_post_type($post_id) !== 'enigme') {
        error_log("❌ Post ID invalide ou type incorrect pour htaccess : {$post_id}");
        return false;
    }

    $upload_dir = wp_upload_dir();
    $base_dir = rtrim($upload_dir['basedir'], '/\\') . '/_enigmes/enigme-' . $post_id;

    if (!is_dir($base_dir)) {
        if (!wp_mkdir_p($base_dir)) {
            error_log("❌ Impossible de créer le dossier {$base_dir}");
            return false;
        }
        error_log("📁 Dossier créé : {$base_dir}");
    }

    $fichier_htaccess = $base_dir . '/.htaccess';
    $fichier_tmp = $fichier_htaccess . '.tmp';

    if (!$forcer && file_exists($fichier_htaccess)) {
        error_log("ℹ️ .htaccess déjà présent pour énigme {$post_id}, pas de réécriture.");
        return true;
    }

    // Supprime le fichier temporaire si présent
    if (file_exists($fichier_tmp)) {
        unlink($fichier_tmp);
        error_log("🧹 Fichier temporaire .htaccess.tmp supprimé");
    }

    $contenu = <<<HTACCESS
# Protection des images de l'énigme {$post_id}
<IfModule mod_rewrite.c>
RewriteEngine On

# ✅ Autorise uniquement l’accès depuis l’administration WordPress
RewriteCond %{REQUEST_URI} ^/wp-admin/ [OR]
RewriteCond %{HTTP_REFERER} ^(/wp-admin/|https?://[^/]+/wp-admin/) [NC]
RewriteRule . - [L]

# ❌ Blocage par défaut
<FilesMatch "\\.(jpg|jpeg|png|gif|webp)\$">
  Require all denied
</FilesMatch>
</IfModule>
HTACCESS;

    if (file_put_contents($fichier_htaccess, $contenu, LOCK_EX) === false) {
        error_log("❌ Échec d’écriture du fichier .htaccess pour énigme {$post_id}");
        return false;
    }

    error_log("✅ .htaccess injecté avec succès pour énigme {$post_id}");
    return true;
}



/**
 * Injecte un .htaccess de protection juste après l’ajout d’un visuel
 * dans le champ `enigme_visuel_image`. S’appuie sur acf/save_post.
 *
 * @hook acf/save_post
 * @param int $post_id
 */
function verrouiller_visuels_enigme_si_nouveau_upload($post_id)
{
  if (get_post_type($post_id) !== 'enigme') return;

  // Récupère les images actuelles (gallerie)
  $images = get_field('enigme_visuel_image', $post_id, false);
  if (!$images || !is_array($images)) return;

  // Vérifie si le .htaccess est déjà en place
  $upload_dir = wp_upload_dir();
  $dossier = $upload_dir['basedir'] . '/_enigmes/enigme-' . $post_id;
  $fichier_htaccess = $dossier . '/.htaccess';

  if (!file_exists($fichier_htaccess)) {
    injecter_htaccess_protection_images_enigme($post_id, true);
  }
}
add_action('acf/save_post', 'verrouiller_visuels_enigme_si_nouveau_upload', 20);


/**
 * pour utiliser le proxy sécurisé /voir-image-enigme
 *
 * @hook acf/format_value/type=gallery
 *
 * @param array|null $images
 * @param string $post_id
 * @param array $field
 * @return array|null
 */
function filtrer_visuels_enigme_front($images, $post_id, $field) {
    error_log('[DEBUG] filtre gallery appelé pour post ID : ' . $post_id);

    if (is_admin()) return $images;
    if (!is_array($images)) return $images;

    $taille = 'medium'; // peut être 'full', 'thumbnail', etc.

    foreach ($images as &$image) {
        if (!isset($image['ID'])) continue;

        $image_id = $image['ID'];
        $image['url'] = site_url('/voir-image-enigme?id=' . $image_id . '&taille=' . $taille);
    }

    return $images;
}
add_filter('acf/format_value/type=gallery', 'filtrer_visuels_enigme_front', 20, 3);



/**
 * @hook wp_ajax_desactiver_htaccess_enigme
 */
function desactiver_htaccess_temporairement_enigme() {
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Non autorisé');
    }

    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

    if (!$post_id || get_post_type($post_id) !== 'enigme') {
        wp_send_json_error('ID invalide');
    }

    if (!utilisateur_peut_modifier_post($post_id)) {
        wp_send_json_error('Droits insuffisants');
    }

    $upload_dir = wp_upload_dir();
    $dossier = $upload_dir['basedir'] . '/_enigmes/enigme-' . $post_id;
    $fichier_htaccess = $dossier . '/.htaccess';
    $fichier_tmp = $fichier_htaccess . '.tmp';

    $message = '';

    // ✅ Si .htaccess existe et pas déjà désactivé
    if (file_exists($fichier_htaccess)) {
        if (!@rename($fichier_htaccess, $fichier_tmp)) {
            error_log("❌ Impossible de renommer .htaccess vers .tmp pour énigme {$post_id}");
            wp_send_json_error("Erreur désactivation");
        }
        error_log("✅ .htaccess désactivé temporairement pour énigme {$post_id}");
        $message = 'Protection désactivée (nouveau .tmp)';
    } elseif (file_exists($fichier_tmp)) {
        error_log("ℹ️ .htaccess déjà désactivé pour énigme {$post_id} – renouvellement délai");
        $message = 'Déjà désactivé – délai renouvelé';
    } else {
        error_log("⚠️ Aucun .htaccess trouvé pour énigme {$post_id}");
        wp_send_json_error("Fichier introuvable");
    }

    // 🔁 Dans tous les cas on prolonge pour 3 minutes
    set_transient('htaccess_timeout_enigme_' . $post_id, time() + 180, 180);

    wp_send_json_success($message);
}
add_action('wp_ajax_desactiver_htaccess_enigme', 'desactiver_htaccess_temporairement_enigme');


/**
 * @hook acf/save_post
 */
function reactiver_htaccess_protection_enigme_apres_save($post_id) {
    if (get_post_type($post_id) !== 'enigme') return;

    // Réinjecte directement
    injecter_htaccess_protection_images_enigme($post_id, true);

    // Supprime le .tmp s’il traîne
    $upload_dir = wp_upload_dir();
    $fichier_tmp = $upload_dir['basedir'] . '/_enigmes/enigme-' . $post_id . '/.htaccess.tmp';
    if (file_exists($fichier_tmp)) {
        unlink($fichier_tmp);
        error_log("🧼 .htaccess.tmp supprimé après enregistrement de l’énigme {$post_id}");
    }

    // Supprime le transient
    delete_transient('htaccess_timeout_enigme_' . $post_id);
}
add_action('acf/save_post', 'reactiver_htaccess_protection_enigme_apres_save', 99);

/**
 *
 * @hook template_redirect
 */
function verifier_expiration_desactivations_htaccess() {
    $all_enigmes = get_posts([
        'post_type'      => 'enigme',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'post_status'    => 'any',
    ]);

    $now = time();

    foreach ($all_enigmes as $post_id) {
        $expiration = get_transient('htaccess_timeout_enigme_' . $post_id);
        if (!$expiration) continue;

        $upload_dir = wp_upload_dir();
        $dossier = $upload_dir['basedir'] . '/_enigmes/enigme-' . $post_id;
        $htaccess     = $dossier . '/.htaccess';
        $htaccess_tmp = $htaccess . '.tmp';

        // ✅ Expiration atteinte : on réactive
        if ($now > $expiration) {
            if (file_exists($htaccess_tmp) && !file_exists($htaccess)) {
                if (rename($htaccess_tmp, $htaccess)) {
                    error_log("⏲️ Fallback restauration .htaccess (expiration dépassée) pour énigme {$post_id}");
                } else {
                    error_log("❌ Échec restauration .htaccess depuis .tmp pour énigme {$post_id}");
                }
            } else {
                // sinon réécrit quand même pour être sûr
                injecter_htaccess_protection_images_enigme($post_id, true);
                error_log("⏱️ Expiration atteinte : .htaccess réinjecté pour énigme {$post_id}");
            }

            // Nettoyage
            delete_transient('htaccess_timeout_enigme_' . $post_id);
        }
    }
}


/**
 * Si un fichier .htaccess.tmp est présent mais qu’aucun .htaccess n’existe,
 * cette fonction le restaure.
 *
 * @param int $post_id
 * @return void
 */
function restaurer_htaccess_si_temporairement_desactive($post_id) {
    $upload_dir = wp_upload_dir();
    $dossier = $upload_dir['basedir'] . '/_enigmes/enigme-' . $post_id;
    $htaccess = $dossier . '/.htaccess';
    $tmp = $htaccess . '.tmp';

    if (!file_exists($tmp)) return; // rien à faire
    if (file_exists($htaccess)) {
        // .htaccess existe déjà, donc on peut supprimer le temporaire s’il traîne
        unlink($tmp);
        error_log("🧹 .htaccess.tmp supprimé (déjà restauré) pour énigme {$post_id}");
        return;
    }

    if (rename($tmp, $htaccess)) {
        error_log("🔁 .htaccess restauré depuis .tmp pour énigme {$post_id}");
    } else {
        error_log("❌ Impossible de restaurer .htaccess depuis .tmp pour énigme {$post_id}");
    }
}

function reactiver_htaccess_immediat_enigme() {
    $post_id = intval($_POST['post_id'] ?? 0);
    if (!$post_id || get_post_type($post_id) !== 'enigme') {
        wp_send_json_error('ID invalide');
    }

    if (!utilisateur_peut_modifier_post($post_id)) {
        wp_send_json_error('Non autorisé');
    }

    injecter_htaccess_protection_images_enigme($post_id, true);

    // Nettoyage
    $upload_dir = wp_upload_dir();
    $tmp = $upload_dir['basedir'] . '/_enigmes/enigme-' . $post_id . '/.htaccess.tmp';
    if (file_exists($tmp)) {
        unlink($tmp);
    }

    delete_transient('htaccess_timeout_enigme_' . $post_id);

    wp_send_json_success('Protection restaurée immédiatement');
}
add_action('wp_ajax_reactiver_htaccess_immediat_enigme', 'reactiver_htaccess_immediat_enigme');



add_action('wp_ajax_get_expiration_htaccess_enigme', 'get_expiration_htaccess_enigme');

/**
 * @hook wp_ajax_get_expiration_htaccess_enigme
 */
function get_expiration_htaccess_enigme() {
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Non autorisé');
    }

    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    if (!$post_id || get_post_type($post_id) !== 'enigme') {
        wp_send_json_error('ID invalide');
    }

    if (!utilisateur_peut_modifier_post($post_id)) {
        wp_send_json_error('Droits insuffisants');
    }

    $upload_dir = wp_upload_dir();
    $dossier = $upload_dir['basedir'] . '/_enigmes/enigme-' . $post_id;
    $fichier_htaccess_tmp = $dossier . '/.htaccess.tmp';
    $fichier_htaccess = $dossier . '/.htaccess';

    // ⛔ Aucun .tmp présent
    if (!file_exists($fichier_htaccess_tmp)) {
        wp_send_json_error('Aucune désactivation active');
    }

    $transient_key = 'htaccess_timeout_enigme_' . $post_id;
    $expiration = get_transient($transient_key);

    // ✅ Transient encore actif → retour normal
    if ($expiration && is_numeric($expiration) && $expiration > time()) {
        wp_send_json_success(['timestamp' => $expiration]);
    }

    // ❌ Transient expiré ou absent, mais .tmp encore là → on restaure
    @unlink($fichier_htaccess); // au cas où résidu
    if (@rename($fichier_htaccess_tmp, $fichier_htaccess)) {
        error_log("🔒 htaccess restauré automatiquement (expiration dépassée) pour énigme {$post_id}");
    } else {
        error_log("⚠️ Échec restauration htaccess expirée pour énigme {$post_id}");
    }

    delete_transient($transient_key);
    wp_send_json_error('Délai expiré');
}
add_action('wp_ajax_get_expiration_htaccess_enigme', 'get_expiration_htaccess_enigme');


 /* @hook wp_ajax_verrouillage_termine_enigme
 * Déclenché automatiquement par JS à la fin du compte à rebours
 */
add_action('wp_ajax_verrouillage_termine_enigme', function () {
  $post_id = intval($_POST['post_id'] ?? 0);
  if (!$post_id || get_post_type($post_id) !== 'enigme') {
    wp_send_json_error('ID invalide');
  }

  $upload_dir = wp_upload_dir();
  $dossier = $upload_dir['basedir'] . '/_enigmes/enigme-' . $post_id;

  if (file_exists($dossier . '/.htaccess.tmp') && !file_exists($dossier . '/.htaccess')) {
    rename($dossier . '/.htaccess.tmp', $dossier . '/.htaccess');
    error_log("🔁 .htaccess restauré depuis .tmp (expiration JS) pour énigme #$post_id");
  }

  delete_transient('htaccess_timeout_enigme_' . $post_id);
  wp_send_json_success('Verrouillage terminé et protection rétablie');
});



//
// 🔁 PURGE AUTOMATIQUE DES PROTECTIONS TEMPORAIRES
//

/**
 *
 * Vérifie chaque dossier /_enigmes/enigme-ID/ :
 * - Si un fichier .htaccess.tmp est présent
 * - Et que le transient associé a expiré
 * Alors le fichier est renommé en .htaccess et la protection est réactivée.
 *
 * Appelée via le cron WordPress toutes les 5 minutes.
 * 
 */
function purger_htaccess_temp_enigmes() {
    error_log('🟡 CRON lancé : purge htaccess');

    $upload_dir = wp_upload_dir();
    $base = $upload_dir['basedir'] . '/_enigmes';

    if (!is_dir($base)) {
        error_log('❌ Base des énigmes introuvable');
        return;
    }

    foreach (glob($base . '/enigme-*') as $dossier) {
        if (!is_dir($dossier)) continue;

        $post_id = intval(basename($dossier, '/'));
        error_log("🔍 Scan énigme $post_id");

        $fichier_tmp = $dossier . '/.htaccess.tmp';
        if (!file_exists($fichier_tmp)) {
            error_log("⏭️ Aucun fichier .tmp pour énigme $post_id");
            continue;
        }

        $transient = get_transient('htaccess_timeout_enigme_' . $post_id);
        error_log("⏱️ Transient actuel pour $post_id : " . var_export($transient, true));

        if (!$transient || $transient < time()) {
            error_log("🟥 Transient expiré ou absent pour $post_id → tentative restauration");

            $fichier_final = $dossier . '/.htaccess';
            if (@rename($fichier_tmp, $fichier_final)) {
                delete_transient('htaccess_timeout_enigme_' . $post_id);
                error_log("🟢 Restauration OK htaccess pour énigme $post_id");
            } else {
                error_log("⚠️ Échec restauration pour énigme $post_id");
            }
        } else {
            error_log("🟩 Transient encore actif pour $post_id");
        }
    }
}


/**
 *
 * Crée la tâche planifiée `tache_purge_htaccess_enigmes` si elle n’existe pas.
 * Reliée à la fonction `purger_htaccess_temp_enigmes()`.
 */
function enregistrer_cron_purge_htaccess() {
    if (!wp_next_scheduled('tache_purge_htaccess_enigmes')) {
        wp_schedule_event(time(), 'every_5_minutes', 'tache_purge_htaccess_enigmes');
    }
}
add_action('wp', 'enregistrer_cron_purge_htaccess');

// 🔁 Tâche réellement exécutée toutes les 5 minutes
add_action('tache_purge_htaccess_enigmes', 'purger_htaccess_temp_enigmes');

// ⏱️ Intervalle personnalisé (5 minutes)
add_filter('cron_schedules', function ($schedules) {
    $schedules['every_5_minutes'] = [
        'interval' => 300,
        'display'  => __('Toutes les 5 minutes')
    ];
    return $schedules;
});
