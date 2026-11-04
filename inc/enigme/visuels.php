<?php
defined('ABSPATH') || exit;

// ==================================================
// 🖼️ AFFICHAGE DES VISUELS D’ÉNIGMES
/**
 * 🔹 define('ID_IMAGE_PLACEHOLDER_ENIGME', 3925) → Définit l’identifiant de l’image placeholder utilisée pour les énigmes.
 * 🔹 enigme_image_display_url() → Retourne l’URL du fichier d’une image d’énigme.
 * 🔹 afficher_visuels_enigme() → Affiche la galerie (slideshow + lightbox) si l’utilisateur y a droit.
 * 🔹 get_image_enigme() → Renvoie l’URL de l’image principale d’une énigme ou un placeholder.
 * 🔹 enigme_a_une_image() → Vérifie si l’énigme a une image définie.
 * 🔹 get_url_vignette_enigme() → Retourne l’URL proxy de la première vignette d’une énigme.
 * 🔹 enigme_sommaire_vignette_html() → Inclut la miniature du sommaire dans le HTML.
 * 🔹 afficher_picture_vignette_enigme() → Affiche un bloc <picture> responsive pour une énigme.
 */

/**
 * Définit l'identifiant de l'image placeholder utilisée pour les énigmes.
 * 
 * Constante : ID_IMAGE_PLACEHOLDER_ENIGME
 * Valeur : 3925
 * 
 * Cette constante est utilisée comme identifiant de l'image par défaut (placeholder)
 * pour les énigmes dans le site WordPress.
 */
define('ID_IMAGE_PLACEHOLDER_ENIGME', 3925);


/**
 * Largeur déclarée dans srcset. La largeur réelle du fichier prime.
 *
 * @param int    $image_id ID du média.
 * @param string $size     Taille WordPress.
 * @return int
 */
function enigme_picture_size_width(int $image_id, string $size): int
{
    if ($image_id > 0 && function_exists('wp_get_attachment_image_src')) {
        $data = wp_get_attachment_image_src($image_id, $size);
        $width = is_array($data) ? (int) ($data[1] ?? 0) : 0;
        if ($width > 0) {
            return $width;
        }
    }

    return match ($size) {
        'thumbnail' => 150,
        'medium' => 300,
        'medium_large' => 768,
        'large' => 1024,
        'enigme-720' => 720,
        'enigme-1000' => 1000,
        'enigme-1380' => 1380,
        'full' => 1380,
        default => 300,
    };
}

/**
 * Dérivés de la bande, limités par la largeur, hauteur libre.
 *
 * @return list<string>
 */
function enigme_planche_size_names(): array
{
    return ['enigme-720', 'enigme-1000', 'enigme-1380'];
}

/**
 * @return list<string>
 */
function enigme_picture_size_order(): array
{
    return ['thumbnail', 'medium', 'large', 'enigme-720', 'enigme-1000', 'enigme-1380', 'full'];
}

/**
 * Assemble un srcset en descripteurs de largeur (w), lus avec l'attribut sizes.
 *
 * @param array<int, array{url:string,webp:string,width:int}> $candidates
 * @param bool                                                $webp
 * @return string
 */
function enigme_picture_srcset(array $candidates, bool $webp): string
{
    $parts = [];
    foreach ($candidates as $candidate) {
        $url = $webp ? $candidate['webp'] : $candidate['url'];
        if ($url === '') {
            continue;
        }
        $parts[] = $url . ' ' . $candidate['width'] . 'w';
    }

    return implode(', ', $parts);
}

/**
 * Srcset WebP. Un trou au milieu est sauté vers la taille supérieure.
 * Sans WebP sur la plus grande taille, aucune source WebP : le JPEG peut
 * encore monter jusqu’à l’original.
 *
 * @param array<int, array{url:string,webp:string,width:int}> $candidates
 */
function enigme_picture_webp_srcset(array $candidates): string
{
    if ($candidates === []) {
        return '';
    }

    $largest = $candidates[array_key_last($candidates)];
    if (($largest['webp'] ?? '') === '') {
        return '';
    }

    $ready = [];
    foreach ($candidates as $candidate) {
        if (($candidate['webp'] ?? '') !== '') {
            $ready[] = $candidate;
        }
    }

    return enigme_picture_srcset($ready, true);
}

/**
 * Colonne de lecture : pleine largeur jusqu’à 1380 px, sans agrandir.
 */
function enigme_hero_picture_sizes(): string
{
    return 'min(100vw, 1380px)';
}

/**
 * Tranches statiques d’une planche. Une image courte reste un seul fichier.
 *
 * @return list<array{url:string,width:int,height:int}>
 */
function enigme_reading_bands(int $imageId): array
{
    $full = enigme_image_display_url($imageId, 'full');
    $dimensions = function_exists('wp_get_attachment_image_src')
        ? wp_get_attachment_image_src($imageId, 'full')
        : null;
    $width = is_array($dimensions) ? (int) ($dimensions[1] ?? 0) : 0;
    $height = is_array($dimensions) ? (int) ($dimensions[2] ?? 0) : 0;
    $single = [[
        'url' => $full,
        'width' => $width > 0 ? $width : 1380,
        'height' => $height > 0 ? $height : 4000,
    ]];
    if (
        $full === ''
        || !class_exists(\ChassesAuTresor\Core\Media\RiddleReadingBandService::class)
        || !function_exists('get_attached_file')
    ) {
        return $single;
    }

    $file = get_attached_file($imageId);
    if (!is_string($file) || $file === '' || !is_file($file)) {
        return $single;
    }

    $bands = (new \ChassesAuTresor\Core\Media\RiddleReadingBandService())->ensure($file);
    if (count($bands) <= 1) {
        return $single;
    }

    $mapped = [];
    foreach ($bands as $band) {
        $url = enigme_public_file_url((string) $band['path'], $full, $file);
        if ($url === '') {
            return $single;
        }
        $mapped[] = [
            'url' => $url,
            'width' => (int) $band['width'],
            'height' => (int) $band['height'],
        ];
    }

    return $mapped;
}

function enigme_public_file_url(string $path, string $fullUrl, string $originalFile): string
{
    $path = str_replace('\\', '/', $path);
    $originalFile = str_replace('\\', '/', $originalFile);
    if ($path === $originalFile) {
        return $fullUrl;
    }

    $originalName = basename($originalFile);
    $bandName = basename($path);
    if ($originalName === '' || !str_contains($fullUrl, $originalName)) {
        return '';
    }

    return str_replace($originalName, $bandName, $fullUrl);
}

/**
 * HTML des tranches. Seule la première de la planche de tête a un src.
 *
 * @param list<array{url:string,width:int,height:int}> $bands
 */
function enigme_webtoon_band_html(array $bands, string $alt, bool $prime): string
{
    $html = '<span class="webtoon-bands">';
    foreach ($bands as $index => $band) {
        $url = (string) ($band['url'] ?? '');
        if ($url === '') {
            continue;
        }
        $width = (int) ($band['width'] ?? 0);
        $height = (int) ($band['height'] ?? 0);
        $html .= '<img class="webtoon-band enigme-image--limited"';
        if ($prime && $index === 0) {
            $html .= ' id="image-enigme-active" src="' . esc_url($url) . '"';
        }
        $html .= ' data-webtoon-band data-band-src="' . esc_url($url) . '"';
        $html .= ' alt="' . esc_attr($index === 0 ? $alt : '') . '"';
        if ($width > 0) {
            $html .= ' width="' . $width . '"';
        }
        if ($height > 0) {
            $html .= ' height="' . $height . '"';
        }
        $html .= ' sizes="' . esc_attr(enigme_hero_picture_sizes()) . '"';
        $html .= ' decoding="async" referrerpolicy="no-referrer">';
    }
    $html .= '</span>';

    return $html;
}

/**
 * Crée une taille de planche manquante à côté du fichier original.
 * Les images déjà en ligne n’ont pas ces dérivés tant qu’elles ne sont pas revues.
 */
function enigme_ensure_planche_size(int $image_id, string $size): void
{
    if ($image_id <= 0 || !in_array($size, enigme_planche_size_names(), true)) {
        return;
    }
    if (!function_exists('wp_get_attachment_metadata') || !function_exists('get_attached_file')) {
        return;
    }

    $meta = wp_get_attachment_metadata($image_id);
    $attached = get_attached_file($image_id);
    if (!is_array($meta) || !is_string($attached) || $attached === '' || !is_file($attached)) {
        return;
    }

    $existing = isset($meta['sizes'][$size]['file']) ? (string) $meta['sizes'][$size]['file'] : '';
    if ($existing !== '') {
        $existing_path = rtrim(dirname($attached), '/\\') . '/' . $existing;
        if (is_file($existing_path)) {
            enigme_write_webp_sibling($existing_path);
            return;
        }
    }

    if (!function_exists('image_make_intermediate_size')) {
        $library = ABSPATH . 'wp-admin/includes/image.php';
        if (is_file($library)) {
            require_once $library;
        }
    }
    if (
        !function_exists('image_make_intermediate_size')
        || !function_exists('wp_get_additional_image_sizes')
    ) {
        return;
    }

    $registered = wp_get_additional_image_sizes();
    if (!isset($registered[$size]['width'], $registered[$size]['height'])) {
        return;
    }

    $target_width = (int) $registered[$size]['width'];
    $target_height = (int) $registered[$size]['height'];
    $original_width = (int) ($meta['width'] ?? 0);
    $original_height = (int) ($meta['height'] ?? 0);
    $fits_width = $original_width > 0 && $original_width <= $target_width;
    $fits_height = $target_height <= 0
        || ($original_height > 0 && $original_height <= $target_height);
    if ($fits_width && $fits_height) {
        return;
    }

    $lock_key = 'enigme_planche_' . $image_id . '_' . $size;
    if (function_exists('get_transient') && get_transient($lock_key)) {
        return;
    }
    if (function_exists('set_transient')) {
        set_transient($lock_key, 1, 60);
    }

    $resized = image_make_intermediate_size(
        $attached,
        $target_width,
        $target_height,
        !empty($registered[$size]['crop'])
    );
    if (!is_array($resized) || empty($resized['file'])) {
        return;
    }

    $meta = wp_get_attachment_metadata($image_id);
    if (!is_array($meta)) {
        $meta = [];
    }
    if (!isset($meta['sizes']) || !is_array($meta['sizes'])) {
        $meta['sizes'] = [];
    }
    $meta['sizes'][$size] = [
        'file' => (string) $resized['file'],
        'width' => (int) ($resized['width'] ?? 0),
        'height' => (int) ($resized['height'] ?? 0),
        'mime-type' => (string) ($resized['mime-type'] ?? ''),
    ];
    if (function_exists('wp_update_attachment_metadata')) {
        wp_update_attachment_metadata($image_id, $meta);
    }
    enigme_write_webp_sibling(rtrim(dirname($attached), '/\\') . '/' . $resized['file']);
    if (function_exists('delete_transient')) {
        delete_transient($lock_key);
    }
}

/**
 * Fichier .webp à côté du JPEG, lu par la source WebP du <picture>.
 */
function enigme_write_webp_sibling(string $path): void
{
    if (
        !function_exists('wp_image_editor_supports')
        || !wp_image_editor_supports(['mime_type' => 'image/webp'])
        || !function_exists('wp_get_image_editor')
        || !is_file($path)
    ) {
        return;
    }

    $webp_path = preg_replace('/\.(jpe?g|png|gif)$/i', '.webp', $path);
    if (!is_string($webp_path) || $webp_path === $path || is_file($webp_path)) {
        return;
    }

    $editor = wp_get_image_editor($path);
    if (is_wp_error($editor)) {
        return;
    }
    $saved = $editor->save($webp_path, 'image/webp');
    if (is_wp_error($saved) && is_file($webp_path)) {
        unlink($webp_path);
    }
}

/**
 * @param array<int, string> $requested
 * @return list<string>
 */
function enigme_picture_resolved_sizes(array $requested, string $floor): array
{
    $order = enigme_picture_size_order();
    $valid_sizes = function_exists('get_intermediate_image_sizes')
        ? get_intermediate_image_sizes()
        : ['thumbnail', 'medium', 'large'];
    $valid_sizes[] = 'full';
    $valid_sizes = array_intersect($valid_sizes, $order);
    $sizes = array_values(array_intersect($requested, $valid_sizes));
    if (!$sizes) {
        $sizes = ['full'];
    }

    $floor_index = 0;
    if ($floor !== '' && in_array($floor, $order, true)) {
        $floor_index = (int) array_search($floor, $order, true);
    }

    $largest_index = max(array_map(
        static fn (string $size): int => (int) array_search($size, $order, true),
        $sizes
    ));
    if ($largest_index < $floor_index) {
        $largest_index = $floor_index;
    }

    $sizes = array_values(array_intersect(
        array_slice($order, $floor_index, $largest_index - $floor_index + 1),
        $valid_sizes
    ));

    return $sizes !== [] ? $sizes : ['full'];
}

/**
 * @param array<int, string> $requested
 * @return list<array{url:string,webp:string,width:int,size:string}>
 */
function enigme_picture_candidates(int $image_id, array $requested, string $floor = ''): array
{
    $sizes = enigme_picture_resolved_sizes($requested, $floor);
    $can_webp = function_exists('wp_image_editor_supports')
        && wp_image_editor_supports(['mime_type' => 'image/webp']);

    if ($image_id === ID_IMAGE_PLACEHOLDER_ENIGME) {
        $url_builder = static function (int $id, string $size): string {
            $url = wp_get_attachment_image_url($id, $size);
            return $url ? esc_url($url) : '';
        };
        $webp_builder = static function (int $id, string $size) use ($can_webp): string {
            if (!$can_webp) {
                return '';
            }
            $data = wp_get_attachment_image_src($id, $size);
            $url  = $data[0] ?? '';
            if (!$url) {
                return '';
            }
            $webp_url = preg_replace('/\.(jpe?g|png)$/i', '.webp', $url);
            if ($webp_url === $url) {
                return '';
            }
            $upload_dir = wp_get_upload_dir();
            $webp_path  = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $webp_url);
            return file_exists($webp_path) ? esc_url($webp_url) : '';
        };
    } else {
        $url_builder = static function (int $id, string $size, string $format = ''): string {
            return esc_url(enigme_direct_image_url($id, $size, $format));
        };
        // WebP is a sibling file. Clients that cannot decode it keep the original source.
        $webp_builder = static function (int $id, string $size) use ($can_webp): string {
            if (!$can_webp) {
                return '';
            }

            return esc_url(enigme_direct_image_url($id, $size, 'webp'));
        };
    }

    $candidates = [];
    $seen_widths = [];
    foreach ($sizes as $size) {
        enigme_ensure_planche_size($image_id, $size);
        $url = $url_builder($image_id, $size);
        if ($url === '') {
            continue;
        }
        $width = enigme_picture_size_width($image_id, $size);
        if (isset($seen_widths[$width])) {
            continue;
        }
        $seen_widths[$width] = true;
        $candidates[] = [
            'url' => $url,
            'webp' => $webp_builder($image_id, $size),
            'width' => $width,
            'size' => $size,
        ];
    }

    return $candidates;
}

/**
 * Génère le HTML d'un bloc <picture> pour un ID d'image donné.
 * Le srcset utilise la largeur réelle de chaque taille : l'attribut sizes choisit le fichier.
 *
 * @param int    $image_id  ID de l'image.
 * @param string $alt       Texte alternatif.
 * @param array  $sizes     Tailles WordPress à utiliser (la plus grande en dernier).
 * @param array  $img_attrs Attributs supplémentaires pour la balise <img> finale.
 * @param string $floor     Plus petite taille à inclure. Vide = vignette (comportement historique).
 * @return string
 */
function build_picture_enigme(
    int $image_id,
    string $alt,
    array $sizes,
    array $img_attrs = [],
    string $floor = ''
): string {
    $candidates = enigme_picture_candidates($image_id, $sizes, $floor);
    if ($candidates === []) {
        return '';
    }

    $fallback = $candidates[0];
    if (!isset($img_attrs['sizes'])) {
        $img_attrs['sizes'] = '(min-width:1025px) 400px, (min-width:769px) 300px, (min-width:481px) 200px, 100vw';
    }
    $sizes_value = (string) $img_attrs['sizes'];

    $html = "<picture>\n";
    $webp_srcset = enigme_picture_webp_srcset($candidates);
    if ($webp_srcset !== '') {
        $html .= '  <source type="image/webp" sizes="' . esc_attr($sizes_value)
            . '" srcset="' . $webp_srcset . "\">\n";
    }

    $dimensions = function_exists('wp_get_attachment_image_src')
        ? wp_get_attachment_image_src($image_id, $fallback['size'])
        : null;
    if (is_array($dimensions)) {
        $img_attrs['width'] = $dimensions[1];
        $img_attrs['height'] = $dimensions[2];
    }

    $img_attrs['loading'] = ($img_attrs['loading'] ?? '') === 'eager' ? 'eager' : 'lazy';
    if (!isset($img_attrs['decoding'])) {
        $img_attrs['decoding'] = 'async';
    }
    if (!isset($img_attrs['referrerpolicy'])) {
        $img_attrs['referrerpolicy'] = 'no-referrer';
    }

    $attr_str = '';
    foreach ($img_attrs as $key => $value) {
        $attr_str .= ' ' . $key . '="' . esc_attr($value) . '"';
    }

    $html .= '  <img src="' . $fallback['url'] . '" srcset="'
        . enigme_picture_srcset($candidates, false) . '" alt="'
        . esc_attr($alt) . '"' . $attr_str . ">\n";
    $html .= "</picture>\n";

    return $html;
}

/**
 * Données du même <picture> que la planche, pour une page ajoutée sans rechargement.
 *
 * Les URLs sont déjà échappées pour un attribut HTML.
 *
 * @return array{src:string,srcset:string,webp_srcset:string,sizes:string,width:int,height:int}
 */
function enigme_hero_picture_payload(int $image_id): array
{
    $sizes = enigme_hero_picture_sizes();
    $empty = [
        'src' => '',
        'srcset' => '',
        'webp_srcset' => '',
        'sizes' => $sizes,
        'width' => 0,
        'height' => 0,
    ];
    $candidates = enigme_picture_candidates($image_id, ['enigme-720', 'full'], 'enigme-720');
    if ($candidates === []) {
        return $empty;
    }

    $fallback = $candidates[0];
    $dimensions = function_exists('wp_get_attachment_image_src')
        ? wp_get_attachment_image_src($image_id, $fallback['size'])
        : null;

    return [
        'src' => $fallback['url'],
        'srcset' => enigme_picture_srcset($candidates, false),
        'webp_srcset' => enigme_picture_webp_srcset($candidates),
        'sizes' => $sizes,
        'width' => is_array($dimensions) ? (int) ($dimensions[1] ?? 0) : 0,
        'height' => is_array($dimensions) ? (int) ($dimensions[2] ?? 0) : 0,
    ];
}

/**
 * URL du fichier réel. Vide si le joueur ne doit pas recevoir cette image.
 *
 * @param int    $image_id ID du média.
 * @param string $size     Taille WordPress.
 * @return string
 */
function enigme_direct_image_url(int $image_id, string $size = 'full', string $format = ''): string
{
    if ($image_id <= 0) {
        return '';
    }
    if (function_exists('cta_voir_image_enigme_url')) {
        return (string) cta_voir_image_enigme_url($image_id, $size, null, $format);
    }
    if (!function_exists('wp_get_attachment_image_src')) {
        return '';
    }

    $data = wp_get_attachment_image_src($image_id, $size);
    $url = is_array($data) ? (string) ($data[0] ?? '') : '';
    if ($url === '' || $format !== 'webp') {
        return $url;
    }

    $webp_url = (string) preg_replace('/\.(jpe?g|png|gif)$/i', '.webp', $url);
    if ($webp_url === $url || !function_exists('wp_get_upload_dir')) {
        return '';
    }
    $upload_dir = wp_get_upload_dir();
    $webp_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $webp_url);

    return is_file($webp_path) ? $webp_url : '';
}

/**
 * Retourne l’URL d’affichage d’une image d’énigme.
 *
 * @param int    $image_id ID du média.
 * @param string $size     Taille WordPress.
 * @return string
 */
function enigme_image_display_url(int $image_id, string $size = 'full'): string
{
    if ($image_id === ID_IMAGE_PLACEHOLDER_ENIGME && function_exists('wp_get_attachment_image_url')) {
        $url = wp_get_attachment_image_url($image_id, $size);

        return is_string($url) ? $url : '';
    }

    return enigme_direct_image_url($image_id, $size);
}

/**
 * Identifiants de pages d’une étape, dans l’ordre de lecture.
 *
 * Une ancienne étape ne stocke qu’un seul identifiant : il devient la page 1.
 *
 * @param mixed $value
 * @return int[]
 */
function enigme_normalize_step_image_ids($value): array
{
    if (class_exists(\ChassesAuTresor\Core\Content\RiddleStepGallery::class)) {
        return \ChassesAuTresor\Core\Content\RiddleStepGallery::normalize($value);
    }
    if (is_numeric($value)) {
        $id = (int) $value;

        return $id > 0 ? [$id] : [];
    }
    if (!is_array($value)) {
        return [];
    }

    $ids = [];
    foreach ($value as $item) {
        if (is_array($item)) {
            $id = (int) ($item['ID'] ?? $item['id'] ?? 0);
        } elseif (is_numeric($item)) {
            $id = (int) $item;
        } else {
            continue;
        }
        if ($id <= 0 || in_array($id, $ids, true)) {
            continue;
        }
        $ids[] = $id;
    }

    return $ids;
}

/**
 * Pages BD d’une étape. Le repli lit aussi l’ancien champ image unique.
 *
 * @return int[]
 */
function enigme_step_image_ids(int $stepId): array
{
    if ($stepId <= 0 || !function_exists('get_field')) {
        return [];
    }
    if (class_exists(\ChassesAuTresor\Core\Content\RiddleStepGallery::class)) {
        return \ChassesAuTresor\Core\Content\RiddleStepGallery::idsForStep($stepId);
    }

    return enigme_normalize_step_image_ids(get_field('etape_image', $stepId));
}

/**
 * Données d’affichage d’une page BD d’étape.
 *
 * Les URL issues du hero sont déjà échappées. `full` et `thumb` restent brutes.
 *
 * @return array{
 *     alt:string,
 *     preview:string,
 *     full:string,
 *     thumb:string,
 *     srcset:string,
 *     webp_srcset:string,
 *     sizes:string,
 *     width:int,
 *     height:int,
 *     bands:list<array{url:string,width:int,height:int}>
 * }
 */
function enigme_step_comic_page_view(int $imageId): array
{
    $alt = trim((string) get_post_meta($imageId, '_wp_attachment_image_alt', true));
    if ($alt === '') {
        $alt = __('Page débloquée', 'chassesautresor-com');
    }

    $preview = '';
    $srcset = '';
    $webpSrcset = '';
    $sizes = '';
    $width = 0;
    $height = 0;
    $payload = enigme_hero_picture_payload($imageId);
    if ($payload['src'] !== '') {
        $preview = $payload['src'];
        $srcset = $payload['srcset'];
        $webpSrcset = $payload['webp_srcset'];
        $sizes = $payload['sizes'];
        $width = (int) $payload['width'];
        $height = (int) $payload['height'];
    } else {
        $preview = esc_url(enigme_direct_image_url($imageId, 'large'));
    }

    $full = enigme_direct_image_url($imageId, 'full');
    $thumb = enigme_direct_image_url($imageId, 'thumbnail');

    return [
        'alt' => $alt,
        'preview' => $preview,
        'full' => $full,
        'thumb' => $thumb,
        'srcset' => $srcset,
        'webp_srcset' => $webpSrcset,
        'sizes' => $sizes,
        'width' => $width,
        'height' => $height,
        'bands' => enigme_reading_bands($imageId),
    ];
}

/**
 * Collecte les pages BD gagnées en réussissant une étape.
 *
 * Le joueur ne reçoit que les pages des étapes déjà résolues. L’organisateur
 * voit toute la suite, pour relire le parcours. Les pages de l’étape en cours
 * restent cachées : elles apparaissent après la bonne réponse.
 *
 * @param int $enigme_id ID de l’énigme.
 * @param int $user_id   Joueur ou organisateur courant.
 * @return array<int, array{image_id:int, step_id:int}>
 */
function enigme_collect_step_comic_pages(int $enigme_id, int $user_id): array
{
    if ($enigme_id <= 0 || $user_id <= 0) {
        return [];
    }

    if (!class_exists(\ChassesAuTresor\Core\Content\RiddleStepQueryService::class)) {
        return [];
    }

    $orderedStepIds = (new \ChassesAuTresor\Core\Content\RiddleStepQueryService())
        ->findOrderedIds($enigme_id);
    if ($orderedStepIds === []) {
        return [];
    }

    $canModify = function_exists('utilisateur_peut_modifier_post')
        && utilisateur_peut_modifier_post($enigme_id);
    $revealedStepIds = $orderedStepIds;

    if (!$canModify) {
        if (
            !class_exists(\ChassesAuTresor\Core\Support\CoreServiceFactory::class)
            || !isset($GLOBALS['wpdb'])
        ) {
            return [];
        }

        global $wpdb;
        $state = \ChassesAuTresor\Core\Support\CoreServiceFactory::riddleStepProgress($wpdb)
            ->getState($user_id, $enigme_id, $orderedStepIds);
        $revealedStepIds = array_map('intval', $state['completed_step_ids'] ?? []);
    }

    $pages = [];
    foreach ($revealedStepIds as $stepId) {
        $stepId = (int) $stepId;
        foreach (enigme_step_image_ids($stepId) as $imageId) {
            if ($imageId <= 0 || $imageId === ID_IMAGE_PLACEHOLDER_ENIGME) {
                continue;
            }
            $pages[] = [
                'image_id' => $imageId,
                'step_id' => $stepId,
            ];
        }
    }

    return $pages;
}

/**
 * Affiche les images d’une énigme si l’utilisateur y a droit.
 *
 * Lecture en défilement : une colonne min(100vw, 1380px), sans joint entre
 * les bandes. Les images des étapes déjà réussies s’ajoutent dessous.
 * La réponse, les indices et le texte viennent après la dernière image.
 * Un double tap ou un pincement ouvre le détail en taille d’origine.
 *
 * Les images autorisées sont des fichiers statiques, avec les tailles adaptées.
 *
 * @param int      $enigme_id  ID du post de type énigme
 * @param int|null $user_id    Utilisateur courant (pages d’étapes débloquées)
 * @param array<int, array{image_id:int, step_id:int}>|null $stepPages
 *                             Pages d’étapes déjà résolues (tests / injection).
 * @return void
 */
function afficher_visuels_enigme(
    int $enigme_id,
    ?int $user_id = null,
    ?array $stepPages = null
): void {
    if (!utilisateur_peut_voir_enigme($enigme_id)) {
        echo '<div class="visuels-proteges">🔒 Les visuels de cette énigme sont protégés.</div>';
        return;
    }

    $images = get_field('enigme_visuel_image', $enigme_id);
    $pages = [];
    if (is_array($images)) {
        foreach ($images as $img) {
            $id = (int) ($img['ID'] ?? 0);
            if ($id && $id !== ID_IMAGE_PLACEHOLDER_ENIGME) {
                $pages[] = [
                    'image_id' => $id,
                    'step_id' => 0,
                ];
            }
        }
    }

    $resolvedUserId = $user_id ?? (int) get_current_user_id();
    if ($stepPages === null) {
        $stepPages = enigme_collect_step_comic_pages($enigme_id, $resolvedUserId);
    }
    foreach ($stepPages as $stepPage) {
        $pages[] = $stepPage;
    }

    if ($pages === []) {
        $pages[] = [
            'image_id' => ID_IMAGE_PLACEHOLDER_ENIGME,
            'step_id' => 0,
        ];
    }

    $caption = (string) get_field('enigme_visuel_legende', $enigme_id);
    $pageCount = count($pages);
    // La première image reste le point de départ ; les suivantes sont déjà visibles.
    $activeIndex = 0;
    $gallery_id = 'galerie-enigme-' . $enigme_id;

    echo '<div class="galerie-enigme-wrapper" data-enigme-gallery data-webtoon'
        . ' id="' . esc_attr($gallery_id) . '"'
        . ' data-gallery-page-count="' . esc_attr((string) $pageCount) . '">';
    echo '<div class="galerie-enigme__stage">';

    foreach ($pages as $index => $page) {
        $image_id = (int) $page['image_id'];
        $step_id = (int) $page['step_id'];
        $alt = trim((string) get_post_meta($image_id, '_wp_attachment_image_alt', true));
        if (!$alt) {
            if ($image_id === ID_IMAGE_PLACEHOLDER_ENIGME) {
                $alt = __('Image par défaut de l’énigme', 'chassesautresor-com');
            } elseif ($step_id > 0) {
                $alt = __('Page débloquée', 'chassesautresor-com');
            } else {
                $alt = $caption ?: __('Visuel énigme', 'chassesautresor-com');
            }
        }

        $full_url = enigme_image_display_url($image_id, 'full');
        $is_active = $index === $activeIndex;
        $slide_id = $gallery_id . '-slide-' . $index;

        $classes = 'enigme-image--limited';
        if ($is_active) {
            $classes .= ' image-active';
        }

        $attrs = [
            'class' => $classes,
            'sizes' => enigme_hero_picture_sizes(),
            'decoding' => 'async',
            'referrerpolicy' => 'no-referrer',
        ];

        if ($is_active) {
            $attrs['id'] = 'image-enigme-active';
            $attrs['loading'] = 'eager';
            $attrs['fetchpriority'] = 'high';
        }

        $figure_classes = 'image-principale galerie-enigme__slide';
        if ($is_active) {
            $figure_classes .= ' is-active';
        }
        if ($step_id > 0) {
            $figure_classes .= ' galerie-enigme__slide--step';
        }

        echo '<figure class="' . esc_attr($figure_classes) . '"'
            . ' id="' . esc_attr($slide_id) . '"'
            . ' data-gallery-index="' . esc_attr((string) $index) . '"'
            . ' data-gallery-image-id="' . esc_attr((string) $image_id) . '"'
            . ($step_id > 0 ? ' data-gallery-step-id="' . esc_attr((string) $step_id) . '"' : '')
            . '>';
        echo '<button type="button" class="enigme-media-zoom"'
            . ' data-enigme-lightbox-src="' . esc_url($full_url) . '"'
            . ' data-enigme-lightbox-alt="' . esc_attr($alt) . '"'
            . ' aria-label="' . esc_attr__('Agrandir l’image en taille originale', 'chassesautresor-com') . '">';
        $bands = enigme_reading_bands($image_id);
        if (count($bands) > 1) {
            echo enigme_webtoon_band_html($bands, $alt, $index === 0);
        } else {
            echo build_picture_enigme($image_id, $alt, ['enigme-720', 'full'], $attrs, 'enigme-720');
        }
        echo '<span class="enigme-media-zoom__hint" aria-hidden="true">'
            . esc_html__('Agrandir', 'chassesautresor-com') . '</span>';
        echo '</button>';
        echo '</figure>';
    }

    $home_url = function_exists('home_url') ? home_url('/') : '/';
    echo '<a class="galerie-enigme__home" href="' . esc_url($home_url) . '">'
        . '<span class="galerie-enigme__home-icon" aria-hidden="true">&larr;</span>'
        . '<span class="galerie-enigme__home-label">'
        . esc_html__('Accueil', 'chassesautresor-com')
        . '</span></a>';

    echo '</div>';
    echo '</div>';
}


/**
 * Renvoie l’URL de l’image principale d’une énigme,
 * ou un placeholder si aucune image n’est définie.
 *
 * @param int $post_id
 * @param string $size
 * @return string|null
 */
function get_image_enigme(int $post_id, string $size = 'medium'): ?string
{
    $images   = get_field('enigme_visuel_image', $post_id);
    $image_id = ID_IMAGE_PLACEHOLDER_ENIGME;

    if (is_array($images) && !empty($images[0]['ID'])) {
        $image_id = (int) $images[0]['ID'];
    }

    return esc_url(enigme_direct_image_url($image_id, $size));
}


/**
 * Vérifie si l’énigme a une image définie.
 *
 * @param int $post_id ID du post de type énigme
 * @return bool True si l’énigme a une image, false sinon.
 */
function enigme_a_une_image(int $post_id): bool
{
    $images = get_field('enigme_visuel_image', $post_id);
    return is_array($images) && !empty($images[0]['ID']);
}



/**
 * Retourne l’URL du fichier de vignette d’énigme à la taille souhaitée.
 *
 * @param int $enigme_id
 * @param string $taille  Taille WordPress (ex: 'thumbnail', 'medium', 'full')
 * @return string|null
 */
function get_url_vignette_enigme(int $enigme_id, string $taille = 'thumbnail'): ?string
{
    if (!utilisateur_peut_voir_enigme($enigme_id)) {
        return null;
    }

    $images = get_field('enigme_visuel_image', $enigme_id, false);
    if (!$images || !is_array($images)) {
        return null;
    }

    $image_id = $images[0] ?? null; // on récupère l’ID brut directement
    if (!$image_id) return null;

    return esc_url(enigme_direct_image_url((int) $image_id, $taille));
}


/**
 * Vignette du sommaire, peinte avec le HTML de la page.
 *
 * Le fichier miniature voyage dans la réponse déjà limitée à ce visiteur.
 * Un fichier absent ou trop lourd revient à l’URL statique de la vignette.
 */
function enigme_sommaire_vignette_html(int $image_id, string $alt = ''): string
{
    if (function_exists('cta_inline_riddle_thumbnail')) {
        $inlined = cta_inline_riddle_thumbnail($image_id, $alt);
        if (is_string($inlined) && $inlined !== '') {
            return $inlined;
        }
    }

    return build_picture_enigme(
        $image_id,
        $alt,
        ['thumbnail'],
        [
            'sizes' => '4.25rem',
            'loading' => 'eager',
        ]
    );
}

/**
 * Affiche un bloc <picture> responsive pour une énigme.
 *
 * Génère un élément <picture> HTML avec différentes sources pour les tailles d’image spécifiées.
 * Si aucune image n’est définie, utilise le placeholder.
 *
 * @param int    $enigme_id  ID de l’énigme concernée.
 * @param string $alt        Texte alternatif pour l’image.
 * @param array  $sizes      Liste des tailles WordPress à inclure (ordre croissant).
 * @return void
 */
function afficher_picture_vignette_enigme(int $enigme_id, string $alt = '', array $sizes = ['thumbnail', 'medium']): void
{
    $images = get_field('enigme_visuel_image', $enigme_id, false);
    $image_id = (is_array($images) && !empty($images[0])) ? (int) $images[0] : null;

    if (!$image_id) {
        echo '<div class="enigme-placeholder placeholder-svg">';
        echo file_get_contents(get_stylesheet_directory() . '/assets/svg/creation-enigme.svg');
        echo '</div>';
        return;
    }

    echo build_picture_enigme($image_id, $alt, $sizes);
}



/**
 * 🔍 get_mapping_visuel_enigme() — Retourne les infos visuelles d'une énigme selon son CTA.
 *
 * @param int $enigme_id ID de l'énigme.
 * @return array{
 *     cta: string,
 *     image_reelle: bool,
 *     fallback_svg: ?string,
 *     filtre: ?string,
 *     texte_filtre: ?string,
 *     sens: string
 * }
 */
function get_mapping_visuel_enigme(int $enigme_id): array
{
    $cta_data     = get_cta_enigme($enigme_id);
    $cta_type     = $cta_data['type'] ?? 'erreur';
    $etat_systeme = $cta_data['etat_systeme'] ?? get_field('enigme_cache_etat_systeme', $enigme_id);


    $cle = match (true) {
        $cta_type === 'voir' => 'accessible_voir', // 👈 priorité absolue
        $cta_type === 'revoir'                      => 'revoir',
        $cta_type === 'continuer'                   => 'continuer',
        $cta_type === 'soumis'                      => 'soumis',
        $cta_type === 'terminee'                    => 'terminee',
        $cta_type === 'connexion'                   => 'connexion',
        $etat_systeme === 'accessible' && $cta_type === 'engager' => 'accessible_engager',
        $etat_systeme === 'bloquee_date'            => 'bloquee_date',
        $etat_systeme === 'bloquee_pre_requis'      => 'bloquee_pre_requis',
        $etat_systeme === 'bloquee_chasse'          => 'bloquee_chasse',
        $etat_systeme === 'invalide'                => 'invalide',
        $etat_systeme === 'cache_invalide'          => 'cache_invalide',
        default                                     => 'erreur',
    };



    $mapping = [
        'accessible_voir' => [
            'image_reelle' => true,
            'fallback_svg' => null,
            'filtre'       => null,
            'sens'         => __('L’énigme est accessible et engagée', 'chassesautresor-com'),
        ],
        'accessible_engager' => [
            'image_reelle' => true,
            'fallback_svg' => null,
            'filtre'       => 'grayscale blur-xs',
            'sens'         => __('L’énigme est ouverte, mais pas encore tentée', 'chassesautresor-com'),
        ],
        'revoir' => [
            'image_reelle' => true,
            'fallback_svg' => null,
            'filtre'       => null,
            'sens'         => __('Énigme déjà résolue', 'chassesautresor-com'),
        ],
        'continuer' => [
            'image_reelle' => true,
            'fallback_svg' => null,
            'filtre'       => null,
            'sens'         => __('Énigme en cours', 'chassesautresor-com'),
        ],
        'soumis' => [
            'image_reelle' => true,
            'fallback_svg' => null,
            'filtre'       => null,
            'sens'         => __('Réponse en attente de validation', 'chassesautresor-com'),
        ],
        'terminee' => [
            'image_reelle' => false,
            'fallback_svg' => 'lock.svg',
            'filtre'       => 'opacity-40',
            'sens'         => __('Énigme clôturée', 'chassesautresor-com'),
        ],
        'connexion' => [
            'image_reelle' => false,
            'fallback_svg' => 'lock.svg',
            'filtre'       => 'blur-xs',
            'sens'         => __('Connexion requise', 'chassesautresor-com'),
        ],
        'bloquee_date' => [
            'image_reelle' => false,
            'fallback_svg' => 'hourglass.svg',
            'filtre'       => 'opacity-40',
            'sens'         => __('Énigme disponible plus tard', 'chassesautresor-com'),
        ],
        'bloquee_pre_requis' => [
            'image_reelle' => false,
            'fallback_svg' => 'question.svg',
            'filtre'       => 'blur-xs',
            'sens'         => esc_html__('Pré-requis non remplis', 'chassesautresor-com'),
        ],
        'bloquee_chasse' => [
            'image_reelle' => false,
            'fallback_svg' => 'lock.svg',
            'filtre'       => 'grayscale',
            'sens'         => __('Chasse verrouillée', 'chassesautresor-com'),
        ],
        'invalide' => [
            'image_reelle' => false,
            'fallback_svg' => 'warning.svg',
            'filtre'       => 'rouge-jaune',
            'sens'         => __('Énigme mal configurée', 'chassesautresor-com'),
        ],
        'cache_invalide' => [
            'image_reelle' => false,
            'fallback_svg' => 'warning.svg',
            'filtre'       => 'opacity-20',
            'sens'         => __('Cache technique corrompu', 'chassesautresor-com'),
        ],
        'erreur' => [
            'image_reelle' => false,
            'fallback_svg' => 'warning.svg',
            'filtre'       => 'opacity-20',
            'sens'         => __('Erreur technique', 'chassesautresor-com'),
        ],
    ];

    $disponible_le = null;

    if ($cle === 'bloquee_date') {
        $timestamp = strtotime(get_field('enigme_acces_date', $enigme_id));
        if ($timestamp) {
            $disponible_le = date_i18n('d/m/Y', $timestamp);
        }
    }

    return [
        'cta'           => $cta_type,
        'etat_systeme'  => $etat_systeme,
        'image_reelle'  => $mapping[$cle]['image_reelle'] ?? false,
        'fallback_svg'  => $mapping[$cle]['fallback_svg'] ?? 'warning.svg',
        'filtre'        => $mapping[$cle]['filtre'] ?? null,
        'sens'          => $mapping[$cle]['sens'] ?? __('État inconnu', 'chassesautresor-com'),
        'disponible_le' => $disponible_le,
    ];
}
