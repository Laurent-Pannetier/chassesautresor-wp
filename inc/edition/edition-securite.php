<?php
defined('ABSPATH') || exit;


// ==================================================
// 🔐 PROTECTION DES VISUELS (.htaccess)
// ==================================================
// 🔹 rediriger_upload_image_enigme() → Force l’upload dans /_enigmes/enigme-ID/
// 🔹 injecter_htaccess_protection_images_enigme() → Écrit le .htaccess de protection
// 🔹 verrouiller_visuels_enigme_si_nouveau_upload() → Ajoute le .htaccess après upload
// 🔹 filtrer_visuels_enigme_front() → Proxy visuel en front pour galerie
// Les contrôleurs AJAX et le cycle de restauration sont fournis par chassesautresor-core.


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
function injecter_htaccess_protection_images_enigme($post_id, bool $forcer = false)
{
    return (new ChassesAuTresor\Core\Media\RiddleImageProtectionService())->protect(
        (int) $post_id,
        $forcer
    );
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
function filtrer_visuels_enigme_front($images, $post_id, $field)
{
    cat_debug('[DEBUG] filtre gallery appelé pour post ID : ' . $post_id);
    cat_debug('[✔️ filtre ACF gallery actif] post_id = ' . $post_id . ' | champ = ' . ($field['name'] ?? 'inconnu'));

    if (is_admin()) {
        return $images;
    }
    if (!is_array($images)) {
        return $images;
    }

    $taille = 'medium'; // peut être 'full', 'thumbnail', etc.

    foreach ($images as &$image) {
        if (!isset($image['ID'])) {
            continue;
        }

        $image_id = $image['ID'];
        $version  = null;
        if (function_exists('trouver_chemin_image')) {
            $finfo    = trouver_chemin_image($image_id, $taille);
            $img_path = $finfo['path'] ?? null;
            if ($img_path && file_exists($img_path)) {
                $version = filemtime($img_path);
            }
        }

        $url = '/voir-image-enigme?id=' . $image_id . '&taille=' . $taille;
        if ($version) {
            $url .= '&v=' . $version;
        }
        $image['url'] = site_url($url);
    }

    return $images;
}
add_filter('acf/format_value/type=gallery', 'filtrer_visuels_enigme_front', 20, 3);



function autoriser_gestion_images_enigme(bool $allowed, int $riddleId): bool
{
  return utilisateur_peut_modifier_post($riddleId);
}
add_filter('chassesautresor_can_manage_riddle_images', 'autoriser_gestion_images_enigme', 10, 2);
