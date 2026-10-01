<?php
defined('ABSPATH') || exit;


// ==================================================
// 🔐 PROTECTION DES VISUELS (.htaccess)
// ==================================================
// 🔹 filtrer_visuels_enigme_front() → Proxy visuel en front pour galerie
// L’upload, la protection, les contrôleurs AJAX et leur cycle de vie sont fournis par chassesautresor-core.


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
