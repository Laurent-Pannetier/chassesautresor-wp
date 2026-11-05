<?php
defined('ABSPATH') || exit;


// ==================================================
// 🔐 PROTECTION DES VISUELS (.htaccess)
// ==================================================
// 🔹 filtrer_visuels_enigme_front() → Proxy visuel en front pour galerie
// L’upload, la protection, les contrôleurs AJAX et leur cycle de vie sont fournis par chassesautresor-core.


/**
 * Remplace l’URL de galerie par le fichier statique quand le visiteur peut le voir.
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
    if (!is_array($images) || !function_exists('cta_voir_image_enigme_url')) {
        return $images;
    }

    $taille = 'medium';

    foreach ($images as &$image) {
        if (!isset($image['ID'])) {
            continue;
        }

        $url = cta_voir_image_enigme_url((int) $image['ID'], $taille);
        $image['url'] = $url;
        if ($url === '' && isset($image['sizes']) && is_array($image['sizes'])) {
            foreach ($image['sizes'] as $size_name => $size) {
                if (is_array($size)) {
                    $image['sizes'][$size_name]['url'] = '';
                }
            }
        }
    }

    return $images;
}
add_filter('acf/format_value/type=gallery', 'filtrer_visuels_enigme_front', 20, 3);
