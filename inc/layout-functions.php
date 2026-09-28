<?php
defined( 'ABSPATH' ) || exit;

//
// 🧩 TEMPLATES DE PAGE PERSONNALISÉS
// 📦 MISE EN PAGE
// 🧩 HEADERS
// 🎯 AFFICHAGES SPÉCIFIQUES
//





// ==================================================
// 🧩 TEMPLATES DE PAGE PERSONNALISÉS
// ==================================================
/**
 *🔹 add_filter('theme_page_templates', ... ) → Ajouter des modèles de page personnalisés au thème WordPress.
 */

/**
 * Ce filtre étend la liste des templates de page disponibles avec :
 * - Créer mon profil
 * - Devenir organisateur
 * - Traitement de réponse
 *
 * @param array $templates Liste des templates de page existants.
 * @return array Liste mise à jour des templates de page.
 */
add_filter('theme_page_templates', function ($templates) {
    return array_merge($templates, [
        'templates/page-creer-profil.php'          => 'Créer mon profil',
        'templates/page-devenir-organisateur.php'  => 'Devenir organisateur',
        'templates/page-traitement-reponse.php'    => 'Traitement de réponse',
        'templates/page-valider-reponse.php'       => 'Valider la réponse',
        'templates/page-invalider-reponse.php'     => 'Invalider la réponse',
    ]);
});




// ==================================================
// 📦 MISE EN PAGE
// ==================================================
/**
 * 🔹 ajouter_ajaxurl_script → Injecter la variable JavaScript `ajaxurl` dans le `<head>` du front-end.
 * 🔹 charger_fontawesome → FontAwesome.
 * 🔹 ajouter_classes_roles_admin → Ajouter une classe CSS "role-xx" au body.
 * 🔹 imagify_get_webp_url → Convertir une URL d’image vers son équivalent WebP.
 * 🔹 charger_scripts_personnalises → Charger les scripts nécessaires au fonctionnement du site.
 * 🔹 get_svg_icon → Récupère un SVG inline spécifique (trophée, joueur, etc.).
 */

/**
 * 🔗 Injecter la variable JavaScript `ajaxurl` dans le `<head>` du front-end.
 *
 * Cette fonction permet de rendre accessible la variable globale `ajaxurl` en JavaScript,
 * pointant vers l’URL de traitement des requêtes AJAX côté WordPress (`admin-ajax.php`).
 *
 * Cela est nécessaire si tes scripts front-end effectuent des appels AJAX sans utiliser
 * de système d’enqueue ou de localisation via `wp_localize_script`.
 *
 * 💡 Remarque :
 * - Cette méthode est simple, mais un peu ancienne.
 * - Pour un projet structuré, on privilégiera `wp_localize_script()` ou `wp_add_inline_script()`
 *   dans un contexte d’enqueue de scripts front-end.
 *
 * @return void
 *
 * @hook wp_head
 */
function ajouter_ajaxurl_script() {
    ?>
    <script type="text/javascript">
        var ajaxurl = "<?php echo admin_url('admin-ajax.php'); ?>";
    </script>
    <?php
}
add_action('wp_head', 'ajouter_ajaxurl_script');


/**
 * Charge FontAwesome pour les icônes réseaux sociaux.
 */
function charger_fontawesome() {
    wp_enqueue_style('fontawesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.css', [], null);
}
add_action('wp_enqueue_scripts', 'charger_fontawesome');

/**
 * Ajoute une classe CSS "role-xx" au body de l'interface admin en fonction des rôles de l'utilisateur.
 *
 * Cette fonction applique une classe CSS dynamique correspondant aux rôles de l'utilisateur
 * dans l'interface admin de WordPress. Un utilisateur ayant plusieurs rôles cumulera plusieurs classes.
 *
 * @param string $classes Les classes CSS existantes du body.
 * @return string Les classes CSS modifiées avec l'ajout des rôles de l'utilisateur.
 */
function ajouter_classes_roles_admin($classes) {
    $user = wp_get_current_user();

    if (!empty($user->roles)) {
        foreach ($user->roles as $role) {
            $classes .= ' role-' . sanitize_html_class($role);
        }
    }

    return $classes;
}
add_filter('admin_body_class', 'ajouter_classes_roles_admin');

/**
 * 🖼️ Convertit une URL d'image vers son équivalent WebP.
 *
 * @param string|null $image_url URL de l'image source.
 * @return string URL en .webp (si disponible) ou URL originale.
 */
function imagify_get_webp_url($image_url) {
    if (empty($image_url)) {
        return '';
    }

    $parsed_path = parse_url($image_url, PHP_URL_PATH);

    if (!is_string($parsed_path)) {
        return $image_url;
    }

    $extension = strtolower(pathinfo($parsed_path, PATHINFO_EXTENSION));
    $supported_extensions = ['jpg', 'jpeg', 'png'];

    if (!in_array($extension, $supported_extensions, true)) {
        return $image_url;
    }

    $webp_url = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $image_url);

    if (!$webp_url || $webp_url === $image_url) {
        return $image_url;
    }

    if (!function_exists('wp_get_upload_dir')) {
        return $image_url;
    }

    $upload_dir = wp_get_upload_dir();
    $baseurl = $upload_dir['baseurl'] ?? '';
    $basedir = $upload_dir['basedir'] ?? '';

    if (!$baseurl || !$basedir) {
        return $image_url;
    }

    $base_variants = [$baseurl];

    if (strpos($baseurl, 'https://') === 0) {
        $base_variants[] = 'http://' . substr($baseurl, 8);
    } elseif (strpos($baseurl, 'http://') === 0) {
        $base_variants[] = 'https://' . substr($baseurl, 7);
    }

    foreach ($base_variants as $base_variant) {
        if (0 !== strpos($webp_url, $base_variant)) {
            continue;
        }

        $relative_path = ltrim(substr($webp_url, strlen($base_variant)), '/');
        $file_path = rtrim($basedir, '/\\') . '/' . str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $relative_path);

        if (file_exists($file_path)) {
            return $webp_url;
        }

        break;
    }

    return $image_url;
}


/**
 * 📌 Charge les scripts nécessaires au fonctionnement du site.
 */
function charger_scripts_personnalises() {
    $theme_dir = get_stylesheet_directory_uri() . '/assets/js/';

    // 📌 Chargement des scripts JS personnalisés
    wp_enqueue_script('toggle-text', $theme_dir . 'toggle-text.js', ['jquery'], null, true);
    wp_enqueue_script('toggle-tooltip', $theme_dir . 'toggle-tooltip.js', [], null, true);
    wp_enqueue_script(
        'badge-validation-tooltip',
        $theme_dir . 'badge-validation-tooltip.js',
        [],
        filemtime(get_stylesheet_directory() . '/assets/js/badge-validation-tooltip.js'),
        true
    );
    wp_enqueue_script(
        'badge-statut-tooltips',
        $theme_dir . 'badge-statut-tooltips.js',
        [],
        filemtime(get_stylesheet_directory() . '/assets/js/badge-statut-tooltips.js'),
        true
    );
    wp_enqueue_script(
        'meta-tap-info',
        $theme_dir . 'meta-tap-info.js',
        [],
        filemtime(get_stylesheet_directory() . '/assets/js/meta-tap-info.js'),
        true
    );
    wp_enqueue_script(
        'chasse-description-toggle',
        $theme_dir . 'chasse-description-toggle.js',
        [],
        filemtime(get_stylesheet_directory() . '/assets/js/chasse-description-toggle.js'),
        true
    );
    
    wp_enqueue_script(
      'encodage-morse',
      $theme_dir . 'encodage-morse.js',
      [],
      filemtime(get_stylesheet_directory() . '/assets/js/encodage-morse.js'),
      true
    );

    wp_enqueue_script(
      'validation-chasse',
      $theme_dir . 'validation-chasse.js',
      [],
      filemtime(get_stylesheet_directory() . '/assets/js/validation-chasse.js'),
      true
    );

    wp_enqueue_script(
      'validation-admin',
      $theme_dir . 'validation-admin.js',
      [],
      filemtime(get_stylesheet_directory() . '/assets/js/validation-admin.js'),
      true
    );
    if (is_singular('chasse')) {
      wp_enqueue_script(
        'enigme-cards-reorder',
        $theme_dir . 'enigme-cards-reorder.js',
        ['wp-i18n'],
        filemtime(get_stylesheet_directory() . '/assets/js/enigme-cards-reorder.js'),
        true
      );
      wp_set_script_translations('enigme-cards-reorder', 'chassesautresor-com');
    }
    wp_enqueue_script(
      'tri-organisateurs',
      $theme_dir . 'tri-organisateurs.js',
      [],
      filemtime(get_stylesheet_directory() . '/assets/js/tri-organisateurs.js'),
      true
    );

    wp_enqueue_script(
      'pager',
      $theme_dir . 'core/pager.js',
      [],
      filemtime(get_stylesheet_directory() . '/assets/js/core/pager.js'),
      true
    );
    wp_enqueue_script(
      'table-search',
      $theme_dir . 'core/table-search.js',
      [],
      filemtime(get_stylesheet_directory() . '/assets/js/core/table-search.js'),
      true
    );
    wp_enqueue_script(
      'list-skeleton',
      $theme_dir . 'core/skeleton.js',
      ['pager'],
      filemtime(get_stylesheet_directory() . '/assets/js/core/skeleton.js'),
      true
    );
    wp_enqueue_script(
      'organisateurs-pager',
      $theme_dir . 'organisateurs-pager.js',
      ['pager'],
      filemtime(get_stylesheet_directory() . '/assets/js/organisateurs-pager.js'),
      true
    );
}
// ✅ Ajout des scripts au chargement de WordPress
add_action('wp_enqueue_scripts', 'charger_scripts_personnalises');


/**
 * 🔹 get_svg_icon → Récupère un SVG inline spécifique (trophée, joueur, etc.).
 *
 * @param string $icone Le nom de l’icône à récupérer.
 * @return string SVG inline.
 */
function get_svg_icon($icone) {
    switch ($icone) {
        case 'trophee':
            return '
              <svg class="icone-lot svg-icone" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" aria-hidden="true" focusable="false">
                <path d="M348.375,384.758c-12.811-25.137-32.785-44.594-56.582-54.492v-38.5l0.047-9.133c-0.016,0-0.031,0-0.047,0.004v-0.242c-11.588,2.262-23.551,3.481-35.791,3.481c-11.369,0-22.476-1.094-33.291-3.055c-0.752-0.152-1.516-0.262-2.264-0.426v0.043c-0.08-0.016-0.16-0.028-0.24-0.043v47.871c-12.209,5.078-23.393,12.695-33.137,22.293c-0.348,0.34-0.705,0.66-1.049,1.004c-1.072,1.082-2.1,2.219-3.133,3.348c-0.705,0.77-1.426,1.512-2.115,2.305c-0.61,0.703-1.184,1.442-1.78,2.156c-1.07,1.289-2.14,2.574-3.168,3.918c-0.088,0.117-0.17,0.238-0.26,0.355c-4.392,5.789-8.406,12.078-11.939,18.875h0.131c-0.043,0.082-0.09,0.16-0.131,0.238H348.375z" fill="currentColor"/>
                  <polygon points="115.046,416 115.046,511.371 115.044,511.758 115.046,511.758 115.046,512 396.957,512 396.957,416" fill="currentColor"/>
                  <path d="M498.331,29.387c-8.027-9.094-19.447-14.312-31.328-14.312h-47.744V0.27V0.242l0,0V0H92.742v15.074H44.999c-11.887,0-23.306,5.218-31.336,14.312C3.906,40.442-0.305,56.43,1.775,74.465c0.369,7.922,4.367,49.316,47.211,78.77c24.732,17.008,48.424,24.629,69.44,27.938c29.008,45.328,79.76,75.398,137.576,75.398c57.805,0,108.558-30.07,137.568-75.398c21.016-3.305,44.709-10.93,69.445-27.938c42.84-29.453,46.842-70.848,47.211-78.77C512.304,56.43,508.093,40.442,498.331,29.387zM476.238,71.016l-0.125,0.852l-0.002,1.031c-0.029,1.246-1.115,30.656-32.447,52.195c-8.976,6.172-17.635,10.719-26.041,14.184c-1.836,0.711-3.668,1.43-5.553,2.043c4.664-15.184,7.19-31.297,7.19-48.008V49.226h47.744c1.498,0,3.711,0.481,5.726,2.758C476.009,55.703,477.288,62.637,476.238,71.016zM253.964,155.219l-33.658,18.73c-1.422,0.793-3.174,0.688-4.49-0.274c-1.312-0.949-1.959-2.586-1.644-4.18l7.412-37.801c0.279-1.418-0.193-2.883-1.254-3.863l-28.213-26.23c-1.191-1.106-1.633-2.805-1.129-4.352s1.859-2.664,3.474-2.859l38.236-4.633c1.436-0.172,2.678-1.078,3.291-2.391l16.219-34.93c0.687-1.477,2.162-2.422,3.795-2.422c1.625,0,3.102,0.945,3.787,2.422l16.22,34.93c0.612,1.312,1.854,2.219,3.289,2.391l38.236,4.633c1.615,0.195,2.971,1.312,3.474,2.859c0.504,1.547,0.063,3.246-1.127,4.352l-28.215,26.23c-1.059,0.98-1.541,2.445-1.26,3.863l7.418,37.801c0.313,1.594-0.328,3.23-1.648,4.18c-1.316,0.961-3.06,1.066-4.486,0.274l-33.664-18.73C256.769,154.52,255.23,154.52,253.964,155.219zM68.331,125.094c-31.326-21.539-32.41-50.949-32.438-52.016l-0.006-1.035l-0.131-1.027c-1.043-8.379,0.232-15.312,3.516-19.031c2.01-2.277,4.222-2.758,5.726-2.758h47.742v44.086c0,14.246,1.928,28.02,5.357,41.192c0.559,2.308,1.076,4.629,1.725,6.926C89.732,137.801,79.257,132.602,68.331,125.094z" fill="currentColor"/>
              </svg>
            ';
              
        case 'enigme':
            return '
                <svg class="icone-enigme svg-icone" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" version="1.2" baseProfile="tiny" aria-hidden="true" focusable="false">
                  <path  fill="currentColor" d="M16.25 11.25c.364 0 .704.145.984.391.549.332.766-.034.766-.391v-1.75c0-.825-.675-1.5-1.5-1.5h-2.75c-.356 0-.724-.216-.391-.766.246-.28.391-.619.391-.984 0-.967-1.007-1.75-2.25-1.75s-2.25.783-2.25 1.75c0 .3.095.576.255.823.507.673.136.927-.255.927h-2.75c-.825 0-1.5.675-1.5 1.5v1.75c0 .391.254.762.928.244.246-.149.522-.244.822-.244.966 0 1.75 1.008 1.75 2.25s-.784 2.25-1.75 2.25c-.364 0-.704-.145-.984-.391-.549-.332-.766.034-.766.391v2.75c0 .825.675 1.5 1.5 1.5h2.75c.391 0 .762-.254.243-.927-.148-.247-.243-.523-.243-.823 0-.967 1.007-1.75 2.25-1.75s2.25.783 2.25 1.75c0 .365-.145.704-.391.984-.333.55.035.766.391.766h2.75c.825 0 1.5-.675 1.5-1.5v-2.75c0-.391-.254-.762-.928-.244-.246.149-.522.244-.822.244-.966 0-1.75-1.008-1.75-2.25s.784-2.25 1.75-2.25z"></path>
                </svg>
            ';

        case 'participants':
            return '
                <svg class="icone-participants svg-icone" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" version="1.2" baseProfile="tiny" aria-hidden="true" focusable="false">
                  <path fill="currentColor" clip-rule="evenodd" d="M3 18C3 15.3945 4.66081 13.1768 6.98156 12.348C7.61232 12.1227 8.29183 12 9 12C9.70817 12 10.3877 12.1227 11.0184 12.348C11.3611 12.4703 11.6893 12.623 12 12.8027C12.3107 12.623 12.6389 12.4703 12.9816 12.348C13.6123 12.1227 14.2918 12 15 12C15.7082 12 16.3877 12.1227 17.0184 12.348C19.3392 13.1768 21 15.3945 21 18V21H15.75V19.5H19.5V18C19.5 15.5147 17.4853 13.5 15 13.5C14.4029 13.5 13.833 13.6163 13.3116 13.8275C14.3568 14.9073 15 16.3785 15 18V21H3V18ZM9 11.25C8.31104 11.25 7.66548 11.0642 7.11068 10.74C5.9977 10.0896 5.25 8.88211 5.25 7.5C5.25 5.42893 6.92893 3.75 9 3.75C10.2267 3.75 11.3158 4.33901 12 5.24963C12.6842 4.33901 13.7733 3.75 15 3.75C17.0711 3.75 18.75 5.42893 18.75 7.5C18.75 8.88211 18.0023 10.0896 16.8893 10.74C16.3345 11.0642 15.689 11.25 15 11.25C14.311 11.25 13.6655 11.0642 13.1107 10.74C12.6776 10.4869 12.2999 10.1495 12 9.75036C11.7001 10.1496 11.3224 10.4869 10.8893 10.74C10.3345 11.0642 9.68896 11.25 9 11.25ZM13.5 18V19.5H4.5V18C4.5 15.5147 6.51472 13.5 9 13.5C11.4853 13.5 13.5 15.5147 13.5 18ZM11.25 7.5C11.25 8.74264 10.2426 9.75 9 9.75C7.75736 9.75 6.75 8.74264 6.75 7.5C6.75 6.25736 7.75736 5.25 9 5.25C10.2426 5.25 11.25 6.25736 11.25 7.5ZM15 5.25C13.7574 5.25 12.75 6.25736 12.75 7.5C12.75 8.74264 13.7574 9.75 15 9.75C16.2426 9.75 17.25 8.74264 17.25 7.5C17.25 6.25736 16.2426 5.25 15 5.25Z" fill="#080341"></path>
                </svg>
            ';
            
        case 'calendar':
            return '
                <svg class="icone-calendar svg-icone" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" version="1.2" baseProfile="tiny" aria-hidden="true" focusable="false">
                 <path fill="currentColor" fill-rule="evenodd" d="M61,154.006845 C61,153.45078 61.4499488,153 62.0068455,153 L73.9931545,153 C74.5492199,153 75,153.449949 75,154.006845 L75,165.993155 C75,166.54922 74.5500512,167 73.9931545,167 L62.0068455,167 C61.4507801,167 61,166.550051 61,165.993155 L61,154.006845 Z M62,157 L74,157 L74,166 L62,166 L62,157 Z M64,152.5 C64,152.223858 64.214035,152 64.5046844,152 L65.4953156,152 C65.7740451,152 66,152.231934 66,152.5 L66,153 L64,153 L64,152.5 Z M70,152.5 C70,152.223858 70.214035,152 70.5046844,152 L71.4953156,152 C71.7740451,152 72,152.231934 72,152.5 L72,153 L70,153 L70,152.5 Z" transform="translate(-61 -152)"></path>
                </svg>
            ';
        
         case 'unlock':
            return '
                <svg class="icone-unlock svg-icone" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" version="1.2" baseProfile="tiny" aria-hidden="true" focusable="false">
                 <path fill="currentColor" clip-rule="evenodd" d="M6.75 8C6.75 5.10051 9.10051 2.75 12 2.75C14.4453 2.75 16.5018 4.42242 17.0846 6.68694C17.1879 7.08808 17.5968 7.32957 17.9979 7.22633C18.3991 7.12308 18.6405 6.7142 18.5373 6.31306C17.788 3.4019 15.1463 1.25 12 1.25C8.27208 1.25 5.25 4.27208 5.25 8V10.0546C4.13525 10.1379 3.40931 10.348 2.87868 10.8787C2 11.7574 2 13.1716 2 16C2 18.8284 2 20.2426 2.87868 21.1213C3.75736 22 5.17157 22 8 22H16C18.8284 22 20.2426 22 21.1213 21.1213C22 20.2426 22 18.8284 22 16C22 13.1716 22 11.7574 21.1213 10.8787C20.2426 10 18.8284 10 16 10H8C7.54849 10 7.13301 10 6.75 10.0036V8ZM14 16C14 17.1046 13.1046 18 12 18C10.8954 18 10 17.1046 10 16C10 14.8954 10.8954 14 12 14C13.1046 14 14 14.8954 14 16Z" fill="#1C274C"></path>
                </svg>
            ';
            
         case 'free':
            return '
                <svg class="icone-free svg-icone" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" version="1.2" baseProfile="tiny" aria-hidden="true" focusable="false">
                 <path fill="currentColor" d="M13.916,14.824c0,0.554-0.423,0.91-1.137,0.91h-0.562v-1.747c0.104-0.019,0.301-0.047,0.647-0.047 C13.521,13.951,13.916,14.242,13.916,14.824z M30.999,15.5c0,1.202-0.47,2.333-1.317,3.182l-1.904,1.904v2.691 c0,2.479-2.02,4.5-4.5,4.5h-2.691l-1.902,1.904C17.833,30.531,16.703,31,15.5,31s-2.333-0.469-3.184-1.32l-1.901-1.9H7.723 c-1.203,0-2.332-0.469-3.183-1.317c-0.851-0.853-1.317-1.979-1.317-3.183v-2.694l-1.904-1.901c-0.85-0.851-1.317-1.98-1.317-3.183 s0.47-2.333,1.317-3.182l1.904-1.903V7.725c0-2.481,2.02-4.5,4.5-4.5h2.691l1.904-1.905C13.167,0.47,14.296,0,15.5,0 s2.333,0.469,3.184,1.32l1.901,1.901h2.692c1.201,0,2.332,0.468,3.182,1.317c0.852,0.852,1.318,1.981,1.318,3.183v2.692 l1.904,1.904C30.529,13.167,30.999,14.297,30.999,15.5z M9.723,12.928H5.857v6.324h1.436v-2.543h2.271v-1.164H7.293V14.1h2.432 v-1.173L9.723,12.928L9.723,12.928z M15.576,19.252c-0.122-0.244-0.318-1.068-0.516-1.782c-0.16-0.581-0.403-1.005-0.846-1.183 V16.26c0.544-0.197,1.117-0.75,1.117-1.558c0-0.582-0.207-1.023-0.582-1.323c-0.45-0.356-1.107-0.498-2.046-0.498 c-0.761,0-1.445,0.057-1.905,0.132v6.239h1.417v-2.486h0.433c0.582,0.01,0.854,0.226,1.022,1.015 c0.188,0.778,0.338,1.295,0.44,1.473L15.576,19.252L15.576,19.252z M20.396,18.08h-2.602v-1.502h2.328v-1.164h-2.328V14.1h2.471 v-1.173H16.36v6.324h4.036V18.08L20.396,18.08z M25.404,18.08h-2.6v-1.502h2.327v-1.164h-2.327V14.1h2.469v-1.173H21.37v6.324 h4.034V18.08L25.404,18.08z"></path>
                </svg>
            ';

        default:
            $icone = preg_replace('/\.svg$/', '', $icone);
            $path = get_stylesheet_directory() . '/assets/svg/' . $icone . '.svg';
            if (file_exists($path)) {
                return file_get_contents($path);
            }
            return '';
    }
}




// ==================================================
// 🧩 HEADERS
// ==================================================
/**
 * 🔹 is_user_account_area → Vérifie si l’URL courante appartient à l’espace "Mon Compte".
 * 🔹 get_header_fallback → Affiche un header alternatif (style hero) pour les pages hors CPT organisateur.
 * 🔹 ajouter_class_has_hero_si_header_fallback → Ajoute la classe CSS "has-hero" au body si le header fallback est actif.
 * 🔹 filtrer_content_sans_titre → Supprime le <h1> du contenu s’il est identique au titre principal (évite les doublons SEO).
 */

/**
 * Vérifie si la requête actuelle cible une URL de l’espace utilisateur "Mon Compte".
 *
 * @return bool
 */
function is_user_account_area(): bool
{
    if (function_exists('is_account_page') && is_account_page()) {
        return true;
    }

    $slugs  = ['mon-compte', 'my-account'];
    $request = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');

    foreach ($slugs as $slug) {
        if (strpos($request, $slug) === 0) {
            return true;
        }
    }

    return false;
}


/**
 * Affiche le header fallback pour les pages non liées à un CPT organisateur.
 *
 * Utilisé notamment sur des pages génériques comme /contact ou /devenir-organisateur,
 * ce header de type "Hero immersif" peut afficher un titre, un sous-titre et un fond d’image.
 *
 * @param array $args {
 *     Paramètres pour personnaliser le header fallback.
 *
 *     @type string $titre       Le titre principal (H1).
 *     @type string $sous_titre  Le sous-titre affiché sous le titre.
 *     @type int|string $image_fond  ID de média WordPress ou URL d'image directe.
 *     @type int $logo_id        ID de l'image du logo affichée au-dessus du titre.
 * }
 */
function get_header_fallback($args = []) {
    $defaults = [
        'titre'      => '',
        'sous_titre' => '',
        'image_fond' => '', // URL déjà optimisée
        'logo_id'    => 0,
    ];
    $args = wp_parse_args($args, $defaults);

    get_template_part('template-parts/headers/fallback-header', null, [
        'titre'      => $args['titre'],
        'sous_titre' => $args['sous_titre'],
        'image_fond' => esc_url( $args['image_fond'] ),
        'logo_id'    => absint( $args['logo_id'] ),
    ]);
}

/**
 * Ajoute la classe CSS "has-hero" au body si le header fallback est actif.
 *
 * @param array $classes Classes actuelles du body.
 * @return array
 */
function ajouter_class_has_hero_si_header_fallback(array $classes): array
{
    $is_account_area = function_exists('is_user_account_area') ? is_user_account_area() : false;
    $is_organisation_page = function_exists('myaccount_is_organisation_page') ? myaccount_is_organisation_page() : false;

    if ($is_account_area || $is_organisation_page) {
        $classes = array_diff($classes, ['has-hero']);

        return array_values(array_unique($classes));
    }

    if (is_front_page() || is_page()) {
        $classes[] = 'has-hero';
    }

    return array_values(array_unique($classes));
}
add_filter( 'body_class', 'ajouter_class_has_hero_si_header_fallback' );


/**
 * Supprime le premier <h1> du contenu s’il correspond exactement au titre de l’article.
 *
 * @param string $content Contenu brut de the_content().
 * @return string Contenu modifié.
 */
function filtrer_content_sans_titre($content) {
    $post_title = get_the_title();

    // Rechercher le premier <h1> contenant exactement le post_title (sans balises, insensible à la casse)
    if ( preg_match('/<h1[^>]*>(.*?)<\/h1>/i', $content, $matches) ) {
        $titre_dans_contenu = trim( wp_strip_all_tags( $matches[1] ) );

        if ( strcasecmp( $titre_dans_contenu, $post_title ) === 0 ) {
            // Supprime ce <h1> uniquement s'il correspond au titre exact
            $content = str_replace( $matches[0], '', $content );
        }
    }

    return $content;
}

// ==================================================
// 🎯 AFFICHAGES SPÉCIFIQUES
// ==================================================

/**
 * Trim HTML content to a given number of words while preserving tags.
 *
 * @param string $html  HTML to trim.
 * @param int    $limit Number of words to retain.
 * @param string $more  Optional string appended after trimming.
 * @return string Trimmed HTML content.
 */
function cst_trim_html_words($html, $limit = 120, $more = '…') {
    $wordCount = 0;
    $output = '';
    $openTags = [];

    preg_match_all('/(<[^>]+>|\s+|[^<\s]+)/u', $html, $tokens);

    foreach ($tokens[0] as $token) {
        if ($token[0] === '<') {
            if ($token[1] === '/') {
                array_pop($openTags);
            } elseif (substr($token, -2) !== '/>') {
                $tagName = strtolower(preg_replace('/[\s>].*/', '', substr($token, 1)));
                $openTags[] = $tagName;
            }
            $output .= $token;
        } elseif (trim($token) === '') {
            $output .= $token;
        } else {
            $wordCount++;
            $output .= $token;

            if ($wordCount >= $limit) {
                $output = rtrim($output);
                $output .= $more;
                break;
            }
        }
    }

    while (!empty($openTags)) {
        $output .= '</' . array_pop($openTags) . '>';
    }

    return $output;
}
/**
 * Limite l'affichage d'un texte à un certain nombre de caractères, avec un bouton "Lire la suite" pour afficher la totalité.
 *
 * @param string $texte Le texte à afficher.
 * @param int $limite Nombre de caractères à afficher avant le toggle.
 * @param string $label_plus Libellé du bouton pour afficher la suite.
 * @param string $label_moins Libellé du bouton pour masquer la suite.
 * @return string HTML généré avec le texte tronqué et le toggle.
 */
function limiter_texte_avec_toggle($texte, $limite = 200, $label_plus = 'Lire la suite', $label_moins = 'Réduire') {
    $texte = trim(wp_strip_all_tags($texte));
    if (mb_strlen($texte) <= $limite) {
        return wpautop(esc_html($texte));
    }

    $texte_visible = mb_substr($texte, 0, $limite);
    $texte_cache = mb_substr($texte, $limite);

    ob_start();
    ?>
    <span class="texte-limite">
        <span class="texte-visible"><?php echo esc_html($texte_visible); ?></span>
        <span class="texte-cache" style="display:none;"><?php echo esc_html($texte_cache); ?></span>
        <button type="button" class="toggle-texte" aria-expanded="false"><?php echo esc_html($label_plus); ?></button>
    </span>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.toggle-texte').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var parent = btn.closest('.texte-limite');
                var cache = parent.querySelector('.texte-cache');
                var visible = parent.querySelector('.texte-visible');
                if (cache.style.display === 'none') {
                    cache.style.display = '';
                    btn.textContent = <?php echo json_encode($label_moins); ?>;
                    btn.setAttribute('aria-expanded', 'true');
                } else {
                    cache.style.display = 'none';
                    btn.textContent = <?php echo json_encode($label_plus); ?>;
                    btn.setAttribute('aria-expanded', 'false');
                }
            });
        });
    });
    </script>
    <?php
    return ob_get_clean();
}
/**
 * Previously displayed a banner encouraging organizers to validate their hunt.
 * This feature has been removed and the function now performs no action.
 */
function afficher_bandeau_validation_chasse_global()
{
    // Intentionally left blank.
}
