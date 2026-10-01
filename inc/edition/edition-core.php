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
  if (!is_singular('chasse') || !function_exists('acf_enqueue_scripts')) {
    return;
  }

  if (!current_user_can('edit_post', get_the_ID())) {
    return;
  }

  acf_enqueue_scripts();
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
function enqueue_core_edit_scripts(array $additional = [])
{
    static $versions = [];

    $theme_uri = get_stylesheet_directory_uri();
    $theme_dir = get_stylesheet_directory();
    $lang_dir  = get_template_directory() . '/languages';

    $uses_i18n = static function ($file) {
        return file_exists($file) && strpos(file_get_contents($file), 'wp.i18n') !== false;
    };

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
        'pager'            => 'pager.js',
    ];

    $previous_handle = null;

    foreach ($core_scripts as $handle => $filename) {
        $path = "/assets/js/core/{$filename}";
        $file = $theme_dir . $path;

        if (!isset($versions[$handle])) {
            $versions[$handle] = file_exists($file) ? filemtime($file) : null;
        }

        $deps = $previous_handle ? [$previous_handle] : [];
        if ($uses_i18n($file)) {
            $deps[] = 'wp-i18n';
        }

        wp_enqueue_script(
            $handle,
            $theme_uri . $path,
            $deps, // le script dépend du précédent
            $versions[$handle],
            true
        );
        wp_set_script_translations($handle, 'chassesautresor-com', $lang_dir);

        $previous_handle = $handle; // pour chaîner la dépendance
    }

  // Expose locale and common texts to JS (for date formatting, etc.).
  // Use WordPress current locale and convert underscore to hyphen for Intl APIs.
  $locale = function_exists('get_locale') ? get_locale() : 'fr_FR';
  $locale = str_replace('_', '-', $locale);

  if (wp_script_is('date-fields', 'enqueued')) {
      wp_localize_script(
          'date-fields',
          'catI18n',
          [
              'locale' => $locale,
              'texts'  => [
                  'unlimited' => __('Illimitée', 'chassesautresor-com'),
              ],
          ]
      );
  }

  foreach ($additional as $handle) {
    $path = "/assets/js/{$handle}.js";
    $file = $theme_dir . $path;

    if (!isset($versions[$handle])) {
      $versions[$handle] = file_exists($file) ? filemtime($file) : null;
    }

    $deps = ['helpers', 'ajax', 'ui', 'champ-init'];
    if ($uses_i18n($file)) {
      $deps[] = 'wp-i18n';
    }

    wp_enqueue_script(
      $handle,
      $theme_uri . $path,
      $deps,
      $versions[$handle],
      true
    );
    wp_set_script_translations($handle, 'chassesautresor-com', $lang_dir);
    if ($handle === 'indices-create') {
      wp_localize_script(
        'indices-create',
        'indicesCreate',
        [
          'ajaxUrl' => admin_url('admin-ajax.php'),
          'nonce'   => wp_create_nonce('hint_management'),
          'texts'   => [
            'close'     => __('Fermer', 'chassesautresor-com'),
            'image'     => __('Choisir une image', 'chassesautresor-com'),
            'edit'      => __('Modifier', 'chassesautresor-com'),
            'remove'    => __('Supprimer', 'chassesautresor-com'),
            'contenu'   => __('Texte de l’indice', 'chassesautresor-com'),
            'immediate' => __('Immédiate', 'chassesautresor-com'),
            'differe'   => __('Différée', 'chassesautresor-com'),
            'valider'   => __('Valider', 'chassesautresor-com'),
            'mediaTitle'=> __('Sélectionner une image', 'chassesautresor-com'),
            'indiceTitre' => __('Indice #%d', 'chassesautresor-com'),
            'lieeA'            => __('liée à', 'chassesautresor-com'),
            'laChasse'         => __('la chasse', 'chassesautresor-com'),
            'lenigme'          => __('l’énigme', 'chassesautresor-com'),
            'enigmeLabel'      => __('Énigme', 'chassesautresor-com'),
            'enigmePlaceholder'=> __('Sélectionner une énigme', 'chassesautresor-com'),
            'needEnigme'       => __('Sélectionnez une énigme', 'chassesautresor-com'),
            'loading'          => __('Chargement…', 'chassesautresor-com'),
            'needContent' => __('Au moins une image ou un texte nécessaire', 'chassesautresor-com'),
            'needDate'    => __('Date et heure requises', 'chassesautresor-com'),
            'invalidDate' => __('Date invalide', 'chassesautresor-com'),
            'ajaxError'   => __('Erreur réseau', 'chassesautresor-com'),
          ],
        ]
      );
    }
    if ($handle === 'solutions-create') {
      wp_localize_script(
        'solutions-create',
        'solutionsCreate',
        [
          'ajaxUrl' => admin_url('admin-ajax.php'),
          'nonce'   => wp_create_nonce('solution_management'),
          'hintNonce' => wp_create_nonce('hint_management'),
          'texts'   => [
            'close'      => __('Fermer', 'chassesautresor-com'),
            'contenu'    => __('Texte de la solution', 'chassesautresor-com'),
            'fichier'    => __('Fichier', 'chassesautresor-com'),
            'disponibilite' => __('Disponibilité', 'chassesautresor-com'),
            'finChasse'  => __('Fin de la chasse', 'chassesautresor-com'),
            'differee'   => __('Différée', 'chassesautresor-com'),
            'days'       => __('jours', 'chassesautresor-com'),
            'valider'    => __('Valider', 'chassesautresor-com'),
            'addTitre'   => __('Ajouter une solution', 'chassesautresor-com'),
            'editTitre'  => __('Modifier', 'chassesautresor-com'),
            'success'    => __('Solution enregistrée.', 'chassesautresor-com'),
            'ajaxError'  => __('Erreur réseau', 'chassesautresor-com'),
            'enigmeLabel'       => __('Énigme', 'chassesautresor-com'),
            'enigmePlaceholder' => __('Sélectionner une énigme', 'chassesautresor-com'),
            'needEnigme'        => __('Sélectionnez une énigme', 'chassesautresor-com'),
            'loading'           => __('Chargement…', 'chassesautresor-com'),
            'needContent'      => __('Ajoutez un fichier ou un texte', 'chassesautresor-com'),
            'lieeA'            => __('liée à', 'chassesautresor-com'),
            'laChasse'         => __('la chasse', 'chassesautresor-com'),
            'lenigme'          => __('l’énigme', 'chassesautresor-com'),
            'chooseFile'       => __('Choisir un fichier', 'chassesautresor-com'),
            'noFile'           => __('Aucun fichier choisi', 'chassesautresor-com'),
            'removeFile'       => __('Supprimer le fichier', 'chassesautresor-com'),
            'needDate'         => __('Date et heure requises', 'chassesautresor-com'),
          ],
        ]
      );
    }
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
    $pages_autorisees = ['post.php', 'post-new.php', 'edit.php', 'admin-ajax.php', 'async-upload.php'];

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
add_filter( 'body_class', 'injection_classe_edition_active' );
function injection_classe_edition_active( array $classes ): array
{
    if ( ! is_user_logged_in() ) {
        return $classes;
    }

    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if ( preg_match( '#^/mon-compte(?:/|$|\?)#', $uri ) ) {
        $classes[] = 'mode-edition';
    }

    global $post;
    if ( ! $post || ! isset( $post->post_type ) ) {
        return $classes;
    }

    $user_id = get_current_user_id();
    $roles   = wp_get_current_user()->roles;

    // === ORGANISATEUR ===
    if (
        $post->post_type === 'organisateur' &&
        (int) get_post_field( 'post_author', $post->ID ) === $user_id &&
        in_array( ROLE_ORGANISATEUR_CREATION, $roles, true ) &&
        ! get_field( 'organisateur_cache_complet', $post->ID )
    ) {
        verifier_ou_mettre_a_jour_cache_complet( $post->ID );

        if (
            get_post_status( $post ) === 'pending' &&
            ! get_field( 'organisateur_cache_complet', $post->ID )
        ) {
            $classes[] = 'edition-active';
            $classes[] = 'mode-edition';
        }
    }

    // === CHASSE ===
    if (
        $post->post_type === 'chasse' &&
        ( in_array( ROLE_ORGANISATEUR_CREATION, $roles, true ) || in_array( ROLE_ORGANISATEUR, $roles, true ) )
    ) {
        $organisateur_id = get_organisateur_from_chasse( $post->ID );
        $associes        = get_field( 'utilisateurs_associes', $organisateur_id, false );
        $associes        = is_array( $associes ) ? array_map( 'strval', $associes ) : [];

        if ( in_array( (string) $user_id, $associes, true ) ) {
            verifier_ou_mettre_a_jour_cache_complet( $post->ID );

            $validation = get_field( 'chasse_cache_statut_validation', $post->ID );
            $statut     = get_field( 'chasse_cache_statut', $post->ID );

            if (
                $statut === 'revision' &&
                in_array( $validation, [ 'creation', 'correction' ], true ) &&
                ! get_field( 'chasse_cache_complet', $post->ID )
            ) {
                $mode_fin        = get_field( 'chasse_mode_fin', $post->ID ) ?: 'automatique';
                $titre_ok        = trim( get_the_title( $post->ID ) ) !== '';
                $image_ok        = (bool) get_field( 'chasse_principale_image', $post->ID );
                $desc_raw        = get_field( 'chasse_principale_description', $post->ID );
                $desc_ok         = ! empty( trim( (string) $desc_raw ) );
                $has_validatable = chasse_has_validatable_enigme( $post->ID );

                if ( $mode_fin === 'automatique' && $titre_ok && $image_ok && $desc_ok && ! $has_validatable ) {
                    $classes[] = 'scroll-to-enigmes';
                } else {
                    $classes[] = 'edition-active-chasse';
                    $classes[] = 'mode-edition';
                }
            }
        }
    }

    return $classes;
}


/**
 * 📅 Formate une date au format `d/m/Y` ou retourne "Non spécifiée".
 *
 * @param mixed $date La date à formater.
 * @return string La date formatée ou "Non spécifiée" si invalide.
 */
function formater_date($date): string
{
    if (empty($date)) {
        return __('Non spécifiée', 'chassesautresor-com');
    }

    if ($date instanceof DateTimeInterface) {
        $timestamp = $date->getTimestamp();
    } elseif (is_array($date) && isset($date['date'])) {
        $timestamp = convertir_en_timestamp($date['date']);
    } else {
        $timestamp = convertir_en_timestamp((string) $date);
    }

    if ($timestamp === false) {
        return __('Non spécifiée', 'chassesautresor-com');
    }

    $format = _x('j F Y', 'formatting for dates', 'chassesautresor-com');

    return wp_date($format, $timestamp);
}


/**
 * 📅⏰ Formate une date avec l'heure ou retourne "Non spécifiée".
 *
 * @param mixed $date La date à formater.
 * @return string La date/heure formatée ou "Non spécifiée" si invalide.
 */
function formater_date_heure($date): string
{
    if (empty($date)) {
        return __('Non spécifiée', 'chassesautresor-com');
    }

    $original = '';
    if ($date instanceof DateTimeInterface) {
        $timestamp = $date->getTimestamp();
        $original  = $date->format('Y-m-d H:i:s');
    } elseif (is_array($date) && isset($date['date'])) {
        $original  = $date['date'];
        $timestamp = convertir_en_timestamp($original);
    } else {
        $original  = (string) $date;
        $timestamp = convertir_en_timestamp($original);
    }

    if ($timestamp === false) {
        return __('Non spécifiée', 'chassesautresor-com');
    }

    if (!preg_match('/\d{1,2}:\d{2}/', $original)) {
        return formater_date($date);
    }

    $format = _x('j F Y à H:i', 'formatting for datetime', 'chassesautresor-com');

    return wp_date($format, $timestamp);
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
  'Y-m-d\TH:i',
  'Y-m-d',
  'Ymd',
  'YmdHis'
]): ?DateTime
{
  if (empty($date_string)) {
    cat_debug("🚫 Date vide ou non fournie.");
    return null;
  }

  if (preg_match('/^\d{9,10}$/', $date_string)) {
    $timezone = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
    $dt = new DateTime('@' . $date_string);
    $dt->setTimezone($timezone);
    return $dt;
  }

  $timezone = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');

  foreach ($formats as $format) {
    $date_obj = DateTime::createFromFormat($format, $date_string, $timezone);
    if ($date_obj) {
      cat_debug("✅ Date '{$date_string}' convertie avec le format : {$format}");
      return $date_obj;
    }
  }

  // 🚨 Ajout d'un fallback pour éviter le crash
  cat_debug("⚠️ Échec de conversion pour la date : '{$date_string}'. Formats testés : " . implode(', ', $formats));
  return null;
}

/**
 * Convertit une date au format d/m/Y en timestamp.
 *
 * @param string|null $date Date au format 'd/m/Y' ou null.
 * @return int|false Timestamp ou false si invalide.
 */
function convertir_en_timestamp(?string $date)
{
  if (!$date) {
    return false;
  }

  $date = (string) $date;

  if (preg_match('/^\d{9,10}$/', $date)) {
    return (int) $date;
  }

  if (preg_match('/^\d{8}$/', $date)) {
    $dt = DateTime::createFromFormat('Ymd', $date);
    if ($dt) {
      return $dt->getTimestamp();
    }
  }

  return strtotime(str_replace('/', '-', $date));
}
