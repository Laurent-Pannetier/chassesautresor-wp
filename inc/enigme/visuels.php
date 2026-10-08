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
 * Retourne le mapping des tailles d'image vers les requêtes media.
 *
 * @return array<string, string>
 */
function get_enigme_picture_breakpoints(): array
{
    return [
        'full'      => '(min-width: 1025px)',
        'large'     => '(min-width: 769px)',
        'medium'    => '(min-width: 481px)',
        'thumbnail' => '',
    ];
}

/**
 * Génère le HTML d'un bloc <picture> pour un ID d'image donné.
 *
 * @param int   $image_id     ID de l'image.
 * @param string $alt         Texte alternatif.
 * @param array $sizes        Tailles WordPress à utiliser (la plus grande en dernier).
 * @param array $img_attrs    Attributs supplémentaires pour la balise <img> finale.
 * @return string
 */
function build_picture_enigme(int $image_id, string $alt, array $sizes, array $img_attrs = []): string
{
    $breakpoints = get_enigme_picture_breakpoints();
    $order = ['thumbnail', 'medium', 'large', 'full'];

    $valid_sizes = function_exists('get_intermediate_image_sizes')
        ? get_intermediate_image_sizes()
        : ['thumbnail', 'medium', 'large'];
    $valid_sizes[] = 'full';
    $valid_sizes = array_intersect($valid_sizes, $order);
    $sizes       = array_values(array_intersect($sizes, $valid_sizes));
    if (!$sizes) {
        $sizes = ['full'];
    }

    $largest_index = max(array_map(
        static fn (string $size): int => (int) array_search($size, $order, true),
        $sizes
    ));
    $sizes = array_values(array_intersect(array_slice($order, 0, $largest_index + 1), $valid_sizes));

    usort(
        $sizes,
        static fn (string $a, string $b): int => array_search($a, $order, true) <=> array_search($b, $order, true)
    );

    $used_sizes = $sizes;

    $html = "<picture>\n";
    $size_2x_map = [
        'thumbnail' => 'medium',
        'medium'    => 'large',
        'large'     => 'full',
        'full'      => 'full',
    ];

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
        $url_builder = static function (int $id, string $size): string {
            if (function_exists('cta_voir_image_enigme_url')) {
                return esc_url(cta_voir_image_enigme_url($id, $size));
            }
            return esc_url(add_query_arg([
                'id'     => $id,
                'taille' => $size,
            ], site_url('/voir-image-enigme')));
        };
        // WebP is served by the proxy when a sibling .webp exists: never expose uploads/ URLs.
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
            return $url_builder($id, $size);
        };
    }

    for ($i = count($used_sizes) - 1; $i > 0; $i--) {
        $size       = $used_sizes[$i];
        $media      = $breakpoints[$size];
        $media_attr = $media ? ' media="' . $media . '"' : '';

        $src_1x       = $url_builder($image_id, $size);
        $src_2x       = $url_builder($image_id, $size_2x_map[$size] ?? $size);
        $srcset_parts = [$src_1x . ' 1x'];
        if ($src_2x !== '' && $src_2x !== $src_1x) {
            $srcset_parts[] = $src_2x . ' 2x';
        }
        $srcset = implode(', ', $srcset_parts);

        $webp_1x = $webp_builder($image_id, $size);
        $webp_2x = $webp_builder($image_id, $size_2x_map[$size] ?? $size);
        if ($webp_1x !== '') {
            $webp_parts = [$webp_1x . ' 1x'];
            if ($webp_2x !== '' && $webp_2x !== $webp_1x) {
                $webp_parts[] = $webp_2x . ' 2x';
            }
            $webp_srcset = implode(', ', $webp_parts);
            $html       .= '  <source type="image/webp" srcset="' . $webp_srcset
                . '" data-size="' . $size . '"' . $media_attr . ">\n";
        }

        $html .= '  <source srcset="' . $srcset . '" data-size="' . $size . '"'
            . $media_attr . ">\n";
    }

    $fallback_size   = $used_sizes[0];
    $src_fallback_1x = $url_builder($image_id, $fallback_size);
    $src_fallback_2x = $url_builder($image_id, $size_2x_map[$fallback_size] ?? $fallback_size);

    $dimensions = function_exists('wp_get_attachment_image_src')
        ? wp_get_attachment_image_src($image_id, $fallback_size)
        : null;
    if (is_array($dimensions)) {
        $img_attrs['width']  = $dimensions[1];
        $img_attrs['height'] = $dimensions[2];
    }

    $img_attrs['loading'] = 'lazy';
    if (!isset($img_attrs['sizes'])) {
        $img_attrs['sizes'] = '(min-width:1025px) 400px, (min-width:769px) 300px, (min-width:481px) 200px, 100vw';
    }

    $attr_str = '';
    foreach ($img_attrs as $key => $value) {
        $attr_str .= ' ' . $key . '="' . esc_attr($value) . '"';
    }

    $img_srcset_parts = [$src_fallback_1x . ' 1x'];
    if ($src_fallback_2x !== '' && $src_fallback_2x !== $src_fallback_1x) {
        $img_srcset_parts[] = $src_fallback_2x . ' 2x';
    }
    $img_srcset = implode(', ', $img_srcset_parts);

    $html .= '  <img src="' . $src_fallback_1x . '" srcset="' . $img_srcset . '" alt="'
        . esc_attr($alt) . '"' . $attr_str . ">\n";
    $html .= "</picture>\n";

    return $html;
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
 * Étape courante éligible au point & click (widget révélé depuis la lightbox).
 */
function enigme_current_hotspot_step_id(int $enigme_id, int $user_id): int
{
    if (
        $enigme_id <= 0
        || $user_id <= 0
        || !class_exists(\ChassesAuTresor\Core\Content\RiddleStepQueryService::class)
        || !class_exists(\ChassesAuTresor\Core\Support\CoreServiceFactory::class)
        || !isset($GLOBALS['wpdb'])
    ) {
        return 0;
    }

    $orderedStepIds = (new \ChassesAuTresor\Core\Content\RiddleStepQueryService())
        ->findOrderedIds($enigme_id);
    if ($orderedStepIds === []) {
        return 0;
    }

    global $wpdb;
    $state = \ChassesAuTresor\Core\Support\CoreServiceFactory::riddleStepProgress($wpdb)
        ->getState($user_id, $enigme_id, $orderedStepIds);
    $currentId = (int) ($state['current_step_id'] ?? 0);
    if ($currentId <= 0) {
        return 0;
    }

    $hotspot = enigme_step_hotspot_for_gallery($currentId);
    return is_array($hotspot) ? $currentId : 0;
}

/**
 * @return array{zone_raw: string, label: string, zone: array{x: float, y: float, w: float, h: float}}|null
 */
function enigme_step_hotspot_for_gallery(int $stepId): ?array
{
    if (
        $stepId <= 0
        || !class_exists(\ChassesAuTresor\Core\Content\RiddleStepHotspotService::class)
    ) {
        return null;
    }

    $hotspot = (new \ChassesAuTresor\Core\Content\RiddleStepHotspotService())->forStep($stepId);
    if (empty($hotspot['active']) || empty($hotspot['zone']) || ($hotspot['zone_raw'] ?? '') === '') {
        return null;
    }

    return [
        'zone_raw' => (string) $hotspot['zone_raw'],
        'label' => (string) ($hotspot['label'] ?? __('Zone interactive', 'chassesautresor-com')),
        'zone' => $hotspot['zone'],
    ];
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
 * Une seule page est visible à la fois (format BD / A4). Les vignettes et
 * le feuilletage permettent de basculer. Les images d’étapes débloquées
 * s’ajoutent comme pages supplémentaires après celles de l’énigme.
 * Un clic ouvre l’image en taille d’origine.
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
    $currentHotspotStepId = enigme_current_hotspot_step_id($enigme_id, $resolvedUserId);

    echo '<div class="galerie-enigme-wrapper" data-enigme-gallery'
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
            'sizes' => '(min-width:1025px) 920px, 100vw',
        ];

        if ($is_active) {
            $attrs['id'] = 'image-enigme-active';
        }

        $figure_classes = 'image-principale galerie-enigme__slide';
        if ($is_active) {
            $figure_classes .= ' is-active';
        }
        if ($step_id > 0) {
            $figure_classes .= ' galerie-enigme__slide--step';
        }

        $hotspot = ($step_id > 0 && $step_id === $currentHotspotStepId)
            ? enigme_step_hotspot_for_gallery($step_id)
            : null;

        echo '<figure class="' . esc_attr($figure_classes) . '"'
            . ' id="' . esc_attr($slide_id) . '"'
            . ' data-gallery-index="' . esc_attr((string) $index) . '"'
            . ' data-gallery-image-id="' . esc_attr((string) $image_id) . '"'
            . ($step_id > 0 ? ' data-gallery-step-id="' . esc_attr((string) $step_id) . '"' : '')
            . ($is_active ? '' : ' hidden') . '>';
        // Page BD : clic = zoom. Le hotspot n’existe que dans la lightbox 1:1.
        echo '<button type="button" class="enigme-media-zoom"'
            . ' data-enigme-lightbox-src="' . esc_url($full_url) . '"'
            . ' data-enigme-lightbox-alt="' . esc_attr($alt) . '"'
            . (is_array($hotspot)
                ? ' data-riddle-hotspot-zone="' . esc_attr($hotspot['zone_raw']) . '"'
                    . ' data-riddle-hotspot-step="' . esc_attr((string) $step_id) . '"'
                    . ' data-riddle-hotspot-label="' . esc_attr($hotspot['label']) . '"'
                : '')
            . ' aria-label="' . esc_attr__('Agrandir l’image en taille originale', 'chassesautresor-com') . '">';
        echo build_picture_enigme($image_id, $alt, ['large', 'full'], $attrs);
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
                echo '<img src="' . esc_url($thumb_url) . '" alt="" loading="lazy" width="72" height="72">';
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
