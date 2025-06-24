<?php
defined('ABSPATH') || exit;


/**
 * ==================================================
 * 🧱 Fichier : edition-core.php
 * Description : Initialisation globale, réglages admin, formatage et outils ACF partagés.
 * ==================================================
 */
defined('ABSPATH') || exit;

//
// 🧭 SOMMAIRE
// --------------------------------------------------
// 🧱 CHARGEMENT GLOBAL & INIT ÉDITION
// 🎨 APPARENCE & RÉGLAGES ADMIN (NON-ADMIN)
// 🧮 OUTILS DE FORMATAGE GÉNÉRIQUES
// 🔧 OUTILS ACF (RELATIONS & CHAMPS GROUPE)
//


// ==================================================
// 🧱 CHARGEMENT GLOBAL & INIT ÉDITION
// ==================================================
// 🔹 forcer_chargement_acf_scripts_chasse() → Force le chargement ACF en front
// 🔹 personnaliser_acf_wysiwyg_toolbars() → Barre WYSIWYG personnalisée
// 🔹 enqueue_core_edit_scripts() → Enfile les JS mutualisés

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
    'helpers'          => 'helpers.js',
    'ajax'             => 'ajax.js',
    'ui'               => 'ui.js',
    'resume'           => 'resume.js',
    'image-utils'      => 'image-utils.js',
    'date-fields'      => 'date-fields.js',
    'champ-init'       => 'champ-init.js',
    'champ-date-hooks' => 'champ-date-hooks.js',
    'modal-tabs'       => 'modal-tabs.js',
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



// ==================================================
// 🎨 APPARENCE & RÉGLAGES ADMIN (NON-ADMIN)
// ==================================================
// 🔹 masquer_widgets_footer() → Masque certains widgets selon le rôle
// 🔹 ajouter_classe_post_edit_non_admin() → Classe <body> personnalisée en admin
// 🔹 acf_restreindre_utilisateurs_associes() → Filtre les users affichés
// 🔹 restreindre_admin_menu_pour_roles_non_admins() → Nettoie les menus admin
// 🔹 masquer_admin_interface_pour_non_admins() → Masque barre + menu admin
// 🔹 ajouter_barre_progression_top() → Affiche une barre d’étapes dans le back

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

  if (!in_array(ROLE_ORGANISATEUR, $roles)) {
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
// 🧮 OUTILS DE FORMATAGE GÉNÉRIQUES
// ==================================================
// 🔹 injection_classe_edition_active() → Ajoute la classe "edition-active"
// 🔹 formater_date() → Formate une date (ou retourne "Non spécifiée")
// 🔹 convertir_en_datetime() → Convertit une string en DateTime
// 🔹 convertir_en_timestamp() → Date au format d/m/Y → timestamp
// 🔹 normaliser_chaine() → Minuscule + suppression des accents

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
    (int) get_post_field('post_author', $post->ID) === $user_id &&
    in_array(ROLE_ORGANISATEUR_CREATION, $roles, true) &&
    !get_field('organisateur_cache_complet', $post->ID)
  ) {
    verifier_ou_mettre_a_jour_cache_complet($post->ID);

    if (
      get_post_status($post) === 'pending' &&
      !get_field('organisateur_cache_complet', $post->ID)
    ) {
      $classes[] = 'edition-active';
    }
  }

  // === CHASSE ===
  if (
    $post->post_type === 'chasse' &&
    in_array(ROLE_ORGANISATEUR_CREATION, $roles, true)
  ) {
    $organisateur_id = get_organisateur_from_chasse($post->ID);
    $associes = get_field('utilisateurs_associes', $organisateur_id, false);
    $associes = is_array($associes) ? array_map('strval', $associes) : [];

    if (in_array((string) $user_id, $associes, true)) {
      verifier_ou_mettre_a_jour_cache_complet($post->ID);

      if (
        get_post_status($post) === 'pending' &&
        !get_field('chasse_cache_complet', $post->ID)
      ) {
        $classes[] = 'edition-active-chasse';
      }
    }
  }

  return $classes;
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
    cat_debug("🚫 Date vide ou non fournie.");
    return null;
  }

  foreach ($formats as $format) {
    $date_obj = DateTime::createFromFormat($format, $date_string);
    if ($date_obj) {
      cat_debug("✅ Date '{$date_string}' convertie avec le format : {$format}");
      return $date_obj;
    }
  }

  // 🚨 Ajout d'un fallback pour éviter le crash
  cat_debug("⚠️ Échec de conversion pour la date : '{$date_string}'. Formats testés : " . implode(', ', $formats));
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
// 🔧 OUTILS ACF (RELATIONS & CHAMPS GROUPE)
// ==================================================
// 🔹 mettre_a_jour_relation_acf() → Met à jour un champ relation proprement
// 🔹 modifier_relation_acf() → Ajoute ou retire une relation sans écraser
// 🔹 mettre_a_jour_sous_champ_group() → Met à jour un champ dans un groupe ACF

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
    cat_debug("🛑 ERREUR : Paramètres manquants pour mettre à jour la relation ACF.");
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

  cat_debug("🛑 ERREUR : La mise à jour de {$full_field_name} a échoué dans mettre_a_jour_relation_acf().");
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
    cat_debug("🛑 ERREUR : Paramètres manquants pour modifier la relation ACF.");
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

      cat_debug("✅ Relation ajoutée avec succès : {$relation_field} → {$related_post_id}.");
      return true;
    }
    cat_debug("ℹ️ Relation déjà existante : {$relation_field} → {$related_post_id}. Aucune modification.");
  } elseif ($action === 'remove') {
    // 📌 Supprimer uniquement si l'ID est présent
    if (in_array($related_post_id, $current_value)) {
      $current_value = array_diff($current_value, [$related_post_id]);
      update_post_meta($post_id, $relation_field, $current_value);
      update_post_meta($post_id, "_{$relation_field}", $acf_key);

      cat_debug("✅ Relation supprimée avec succès : {$relation_field} → {$related_post_id}.");
      return true;
    }
    cat_debug("ℹ️ La relation à supprimer n'existait pas : {$relation_field} → {$related_post_id}.");
  } else {
    cat_debug("🛑 ERREUR : Action inconnue '{$action}' pour modifier_relation_acf().");
  }

  return false;
}


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
function mettre_a_jour_sous_champ_group(int $post_id, string $group_key_or_name, string $subfield_name, $new_value): bool
{
  if (!$post_id || !$group_key_or_name || !$subfield_name) {
    cat_debug('❌ Paramètres manquants dans mettre_a_jour_sous_champ_group()');
    return false;
  }



  $group_object = get_field_object($group_key_or_name, $post_id);

  if (!$group_object || empty($group_object['sub_fields'])) {
    // Tentative d'initialisation minimale si jamais le groupe n'est pas encore enregistré
    cat_debug("⚠️ Groupe $group_key_or_name vide ou absent — tentative de réinitialisation forcée.");
    update_field($group_key_or_name, [], $post_id);
    $group_object = get_field_object($group_key_or_name, $post_id);
    if (!$group_object || empty($group_object['sub_fields'])) {
      cat_debug("❌ Groupe ACF toujours introuvable après tentative d'initialisation : $group_key_or_name");
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
  cat_debug('[DEBUG] Données envoyées à update_field() pour groupe ' . $group_object['name'] . ' : ' . json_encode($champ_a_enregistrer));

  $ok = update_field($group_object['name'], $champ_a_enregistrer, $post_id);
  clean_post_cache($post_id);

  // 🧪 Vérification lecture après update
  $groupe_verif = get_field($group_object['name'], $post_id);

  $str_valeur = is_array($new_value) ? json_encode($new_value) : $new_value;
  cat_debug("🧪 [DEBUG ACF] Mise à jour demandée : $group_key_or_name.$subfield_name → $str_valeur (post #$post_id)");

  $groupe_verif = get_field($group_key_or_name, $post_id);
  cat_debug("📥 [DEBUG ACF] Relecture après update : " . json_encode($groupe_verif));



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

  cat_debug("❌ Échec de vérification pour $subfield_name dans {$group_object['name']}");
  return false;
}
