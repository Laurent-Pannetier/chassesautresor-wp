<?php
defined('ABSPATH') || exit;

// ==================================================
// 🖼️ AFFICHAGE DES VISUELS D’ÉNIGMES
/**
 * 🔹 define('ID_IMAGE_PLACEHOLDER_ENIGME', 3925) → Définit l’identifiant de l’image placeholder utilisée pour les énigmes.
 * 🔹 enigme_image_display_url() → Retourne l’URL proxy (ou attachment) d’une image d’énigme.
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
        'enigme-1280' => 1280,
        'enigme-1600' => 1600,
        'full' => 1920,
        default => 300,
    };
}

/**
 * Marches de la planche, entre la taille large et l’original.
 *
 * @return list<string>
 */
function enigme_planche_size_names(): array
{
    return ['enigme-1280', 'enigme-1600'];
}

/**
 * @return list<string>
 */
function enigme_picture_size_order(): array
{
    return ['thumbnail', 'medium', 'large', 'enigme-1280', 'enigme-1600', 'full'];
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
 * Largeur peinte d’une planche 16:9 dans le lecteur plein écran.
 * Une fenêtre plus large que 16:9 est limitée par la hauteur.
 */
function enigme_hero_picture_sizes(): string
{
    return '(min-aspect-ratio: 16/9) calc(100vh * 16 / 9), 100vw';
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
    if (
        $original_width > 0
        && $original_height > 0
        && $original_width <= $target_width
        && $original_height <= $target_height
    ) {
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
            if (function_exists('cta_voir_image_enigme_url')) {
                return esc_url(cta_voir_image_enigme_url($id, $size, null, $format));
            }
            $args = [
                'id'     => $id,
                'taille' => $size,
            ];
            if ($format === 'webp') {
                $args['fmt'] = 'webp';
            }
            return esc_url(add_query_arg($args, site_url('/voir-image-enigme')));
        };
        // WebP is a distinct signed URL. Clients that cannot decode it keep the original source.
        $webp_builder = static function (int $id, string $size) use ($can_webp, $url_builder): string {
            if (!$can_webp) {
                return '';
            }
            $data = wp_get_attachment_image_src($id, $size);
            $url  = $data[0] ?? '';
            if (!$url) {
                return '';
            }
            $upload_dir = wp_get_upload_dir();
            $path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $url);
            $webp_path = preg_replace('/\.(jpe?g|png|gif)$/i', '.webp', $path);
            if (!is_string($webp_path) || $webp_path === $path || !file_exists($webp_path)) {
                return '';
            }
            return $url_builder($id, $size, 'webp');
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
    $candidates = enigme_picture_candidates($image_id, ['large', 'full'], 'large');
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
 * Retourne l’URL d’affichage d’une image d’énigme (proxy signé ou attachment).
 *
 * @param int    $image_id ID du média.
 * @param string $size     Taille WordPress.
 * @return string
 */
function enigme_image_display_url(int $image_id, string $size = 'full'): string
{
    if ($image_id === ID_IMAGE_PLACEHOLDER_ENIGME) {
        $url = wp_get_attachment_image_url($image_id, $size);
        return is_string($url) ? $url : '';
    }

    if (function_exists('cta_voir_image_enigme_url')) {
        return cta_voir_image_enigme_url($image_id, $size);
    }

    return add_query_arg(
        [
            'id'     => $image_id,
            'taille' => $size,
        ],
        site_url('/voir-image-enigme')
    );
}

/**
 * Collecte les pages BD débloquées issues des étapes intermédiaires.
 *
 * Les images d’étapes visibles (ou toutes les étapes pour un organisateur)
 * s’ajoutent aux pages de l’énigme dans la galerie principale.
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
    $visibleStepIds = $orderedStepIds;

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
        $visibleStepIds = array_map('intval', $state['visible_step_ids'] ?? []);
    }

    $pages = [];
    $storage = class_exists(\ChassesAuTresor\Core\Media\RiddleStepImageStorageService::class)
        ? new \ChassesAuTresor\Core\Media\RiddleStepImageStorageService()
        : null;
    foreach ($visibleStepIds as $stepId) {
        $imageId = (int) get_field('etape_image', $stepId);
        if ($imageId <= 0 || $imageId === ID_IMAGE_PLACEHOLDER_ENIGME) {
            continue;
        }
        // Lazily move legacy public uploads into protected storage (same as save).
        if ($storage !== null) {
            $securedId = $storage->ensureProtected($imageId, $enigme_id, (int) $stepId);
            if ($securedId > 0 && $securedId !== $imageId) {
                update_field('etape_image', $securedId, $stepId);
                $imageId = $securedId;
            }
        }
        $pages[] = [
            'image_id' => $imageId,
            'step_id' => (int) $stepId,
        ];
    }

    return $pages;
}

/**
 * Affiche une galerie d’images d’une énigme si l’utilisateur y a droit.
 *
 * Une seule page 16:9 (1920 × 1080) est visible à la fois, plein cadre.
 * Le feuilletage se fait au glissement ou au clavier. Un tap affiche le
 * bandeau (Répondre, pastilles de page, flèches).
 * Un double tap ou un pincement ouvre l’image en taille d’origine.
 * Les images d’étapes débloquées s’ajoutent comme pages suivantes.
 *
 * Les images sont servies via proxy (/voir-image-enigme) avec tailles adaptées.
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
    $has_multiple = $pageCount > 1;
    // Toujours ouvrir sur la première page (visuel d’énigme), pas sur la dernière étape.
    $activeIndex = 0;
    $gallery_id = 'galerie-enigme-' . $enigme_id;

    echo '<div class="galerie-enigme-wrapper" data-enigme-gallery data-comic-reader'
        . ' id="' . esc_attr($gallery_id) . '"'
        . ' data-gallery-page-count="' . esc_attr((string) $pageCount) . '">';
    echo '<div class="galerie-enigme__stage">';

    if ($has_multiple) {
        echo '<button type="button" class="galerie-enigme__nav galerie-enigme__nav--prev"'
            . ' data-gallery-step="-1"'
            . ' aria-label="' . esc_attr__('Page précédente', 'chassesautresor-com') . '">'
            . '<span aria-hidden="true">&lsaquo;</span></button>';
    }

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
            . ($is_active ? '' : ' hidden') . '>';
        echo '<button type="button" class="enigme-media-zoom"'
            . ' data-enigme-lightbox-src="' . esc_url($full_url) . '"'
            . ' data-enigme-lightbox-alt="' . esc_attr($alt) . '"'
            . ' aria-label="' . esc_attr__('Agrandir l’image en taille originale', 'chassesautresor-com') . '">';
        echo build_picture_enigme($image_id, $alt, ['large', 'full'], $attrs, 'large');
        echo '<span class="enigme-media-zoom__hint" aria-hidden="true">'
            . esc_html__('Agrandir', 'chassesautresor-com') . '</span>';
        echo '</button>';
        echo '</figure>';
    }

    if ($has_multiple) {
        echo '<button type="button" class="galerie-enigme__nav galerie-enigme__nav--next"'
            . ' data-gallery-step="1"'
            . ' aria-label="' . esc_attr__('Page suivante', 'chassesautresor-com') . '">'
            . '<span aria-hidden="true">&rsaquo;</span></button>';
    }

    echo '</div>';

    if ($has_multiple) {
        echo '<div class="galerie-enigme__pager" aria-live="polite">'
            . '<span class="galerie-enigme__page-label">'
            . esc_html(
                sprintf(
                    /* translators: 1: current page number, 2: total pages */
                    __('Page %1$d / %2$d', 'chassesautresor-com'),
                    $activeIndex + 1,
                    $pageCount
                )
            )
            . '</span></div>';

        echo '<div class="galerie-enigme__thumbs" role="tablist"'
            . ' aria-label="' . esc_attr__('Pages de l’énigme', 'chassesautresor-com') . '">';
        foreach ($pages as $index => $page) {
            $image_id = (int) $page['image_id'];
            $step_id = (int) $page['step_id'];
            $alt = trim((string) get_post_meta($image_id, '_wp_attachment_image_alt', true));
            if (!$alt) {
                $alt = $step_id > 0
                    ? __('Page débloquée', 'chassesautresor-com')
                    : ($caption ?: __('Visuel énigme', 'chassesautresor-com'));
            }
            $thumb_url = enigme_image_display_url($image_id, 'thumbnail');
            $is_active = $index === $activeIndex;
            $slide_id = $gallery_id . '-slide-' . $index;

            echo '<button type="button" class="galerie-enigme__thumb'
                . ($is_active ? ' is-active' : '')
                . ($step_id > 0 ? ' galerie-enigme__thumb--step' : '') . '"'
                . ' role="tab"'
                . ' aria-selected="' . ($is_active ? 'true' : 'false') . '"'
                . ' aria-controls="' . esc_attr($slide_id) . '"'
                . ' data-gallery-goto="' . esc_attr((string) $index) . '"'
                . ($step_id > 0 ? ' data-gallery-step-id="' . esc_attr((string) $step_id) . '"' : '')
                . ' aria-label="' . esc_attr(
                    sprintf(
                        /* translators: %d: page number starting at 1 */
                        __('Afficher la page %d', 'chassesautresor-com'),
                        $index + 1
                    )
                ) . '">';
            if ($thumb_url !== '') {
                echo '<img src="' . esc_url($thumb_url) . '" alt="" loading="lazy" decoding="async"'
                    . ' referrerpolicy="no-referrer" width="72" height="72">';
            }
            echo '</button>';
        }
        echo '</div>';
    }

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

    return esc_url(
        function_exists('cta_voir_image_enigme_url')
            ? cta_voir_image_enigme_url($image_id, $size)
            : add_query_arg([
                'id'     => $image_id,
                'taille' => $size,
            ], site_url('/voir-image-enigme'))
    );
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
 * Retourne l'URL proxy pour une vignette d’énigme à la taille souhaitée.
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

    return esc_url(
        function_exists('cta_voir_image_enigme_url')
            ? cta_voir_image_enigme_url((int) $image_id, $taille)
            : add_query_arg([
                'id'     => $image_id,
                'taille' => $taille,
            ], site_url('/voir-image-enigme'))
    );
}


/**
 * Vignette du sommaire, peinte avec le HTML de la page.
 *
 * Le fichier miniature voyage dans la réponse déjà limitée à ce visiteur.
 * Le proxy /voir-image-enigme garde sa vérification : son URL signée se
 * partage, ces octets non. Un fichier absent ou trop lourd revient au proxy,
 * demandé tout de suite.
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
 * Génère un élément <picture> HTML avec différentes sources pour les tailles d’image spécifiées,
 * en utilisant le proxy /voir-image-enigme. Si aucune image n’est définie, utilise le placeholder.
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
