<?php
/**
 * Front-end shell: top bar, sidebar, day and night themes, placeholder pages.
 *
 * @package chassesautresor
 */

defined('ABSPATH') || exit;

/**
 * Active visual theme. Night is the default.
 */
function cta_shell_theme(): string
{
    $theme = 'night';
    if (!isset($_COOKIE['cta_theme'])) {
        return $theme;
    }

    $raw = (string) $_COOKIE['cta_theme'];
    if (function_exists('wp_unslash')) {
        $raw = (string) wp_unslash($raw);
    }
    if (function_exists('sanitize_key')) {
        $raw = sanitize_key($raw);
    }

    return $raw === 'day' ? 'day' : 'night';
}

/**
 * Shared inline icons. The trophy markup is the one reused by the top bar.
 */
function cta_shell_icon(string $name): string
{
    $icons = [
        'home' => '<path fill="currentColor" d="M12 3.2 3 11h2v9h5.5v-5.5h3V20H19v-9h2L12 3.2z"/>',
        'progression' => '<path fill="currentColor" d="M4 13h3.2v7H4v-7zm6.4-4h3.2v11h-3.2V9zM16.8 5H20v15h-3.2V5z"/>',
        'rules' => '<path fill="currentColor" d="M7 2.5h7.2L19 7.3V21.5H7V2.5zm7 1.6V8h3.9'
            . 'L14 4.1zM9.2 12h6.2v1.6H9.2V12zm0 3.4h6.2v1.6H9.2v-1.6z"/>',
        'trophy' => '<path fill="currentColor" d="'
            . 'M6.37 20.40L6.37 18.46 L11.97 18.46L17.57 18.46 L17.57 20.40L17.57 22.34 L11.97 22.34L6.37'
            . ' 22.34 L6.37 20.40ZM8.52 16.76C8.63 16.52 9.13 16.01 9.60 15.60C10.33 14.97 10.46 14.74 10'
            . '.46 14.02L10.46 13.16 L11.97 13.16L13.48 13.16 L13.48 14.02C13.48 14.74 13.61 14.97 14.34 '
            . '15.60C16.15 17.15 16.12 17.17 11.97 17.17C8.42 17.17 8.31 17.15 8.52 16.76ZM10.25 11.81C9.'
            . '13 11.47 7.92 10.71 7.21 9.91C6.76 9.42 6.11 8.96 5.59 8.81C2.98 8.04 1.20 5.54 1.87 3.60C'
            . '2.16 2.76 3.05 2.31 4.43 2.31C5.25 2.31 5.51 2.22 5.51 1.99C5.51 1.70 6.40 1.66 12.07 1.66'
            . 'C17.77 1.66 18.65 1.70 18.65 1.99C18.65 2.22 18.91 2.31 19.59 2.31C21.00 2.31 21.65 2.63 2'
            . '1.98 3.47C22.80 5.43 21.17 7.91 18.45 8.79C18.02 8.94 17.12 9.57 16.43 10.19C14.73 11.78 1'
            . '2.27 12.40 10.25 11.81ZM11.24 8.31L11.97 7.86 L12.70 8.31C13.67 8.92 13.72 8.92 13.54 7.80'
            . 'C13.43 6.93 13.48 6.79 13.99 6.37C14.74 5.78 14.71 5.62 13.69 5.45C12.96 5.35 12.77 5.19 1'
            . '2.46 4.57C12.03 3.64 11.88 3.62 11.54 4.46C11.24 5.21 10.74 5.54 9.95 5.54C9.21 5.54 9.23 '
            . '5.84 10.03 6.40C10.70 6.87 10.70 6.98 10.31 8.38C10.19 8.88 10.35 8.88 11.24 8.31ZM5.55 5.'
            . '58L5.46 3.81 L4.30 3.81L3.14 3.81 L3.14 4.50C3.14 5.43 3.77 6.36 4.79 6.98C5.22 7.24 5.59 '
            . '7.43 5.61 7.41C5.61 7.39 5.59 6.57 5.55 5.58ZM20.41 5.87C20.63 5.51 20.80 4.89 20.80 4.50C'
            . '20.80 3.81 20.80 3.81 19.72 3.81L18.65 3.81 L18.65 4.72C18.63 5.24 18.54 6.06 18.45 6.55L1'
            . '8.28 7.48 L19.14 6.98C19.62 6.73 20.17 6.21 20.41 5.87Z"/>',
        'profile' => '<path fill="currentColor" d="M12 12.2a4 4 0 1 0-4-4 4 4 0 0 0 4 4zm0 2c-3.4'
            . ' 0-8 1.7-8 5v1.3h16V19.2c0-3.3-4.6-5-8-5z"/>',
        'tools' => '<path fill="currentColor" d="M14.2 3.2a4.4 4.4 0 0 0-4.1 6.1L4.4 15a2.2 2.2 0 0 0'
            . ' 3.1 3.1l5.7-5.7a4.4 4.4 0 0 0 6.1-4.1l-2.6 2.6-2.3-2.3 2.6-2.6a4.4 4.4 0 0 0-2.8-.8z"/>',
        'points' => '<path fill="currentColor" d="M12 2.5a9.5 9.5 0 1 0 9.5 9.5A9.5 9.5 0 0 0 12'
            . ' 2.5zm0 4.1 1.5 3.1 3.4.5-2.5 2.4.6 3.4L12 14.4 8.9 16l.6-3.4-2.4-2.4'
            . ' 3.4-.5L12 6.6z"/>',
        'ranking' => '<circle cx="5.2" cy="6" r="1.45" fill="currentColor"/>'
            . '<circle cx="5.2" cy="12" r="1.45" fill="currentColor"/>'
            . '<circle cx="5.2" cy="18" r="1.45" fill="currentColor"/>'
            . '<path fill="currentColor" d="M8.7 4.8h11v2.4h-11V4.8zm0 6H16v2.4H8.7v-2.4zm0 6h6.2v2.4H8.7V16.8z"/>',
        'team' => '<path fill="currentColor" d="M8 11.1a2.6 2.6 0 1 0-2.6-2.6A2.6 2.6 0 0 0 8 11.1zm8 0a2.6'
            . ' 2.6 0 1 0-2.6-2.6A2.6 2.6 0 0 0 16 11.1zM4.1 18.8c.25-2.15 2.05-3.5 3.9-3.5s3.65 1.35 3.9'
            . ' 3.5H4.1zm8.15 0c.2-1.15.75-2.1 1.55-2.8.85.35 1.85.55 2.85.55 1.85 0 3.65 1.35 3.9 3.5h-8.3z"/>',
        'notifications' => '<path fill="currentColor" d="M12 22a2.3 2.3 0 0 0 2.2-1.8H9.8A2.3 2.3'
            . ' 0 0 0 12 22zm6.5-6.2V11a6.5 6.5 0 0 0-5-6.3V3.5a1.5 1.5 0 0 0-3 0v1.2'
            . 'a6.5 6.5 0 0 0-5 6.3v4.8L4 18v1.2h16V18l-1.5-2.2z"/>',
        'logout' => '<path fill="currentColor" d="'
            . 'M11.81 21.78C4.32 20.83 1.20 11.19 6.61 5.79C9.06 3.32 14.04 1.98 14.04 3.76C14.04 4.37 13'
            . '.64 4.58 12.22 4.72C9.82 4.96 7.31 6.77 6.15 9.14C5.26 11.01 5.29 13.98 6.26 15.87C7.47 18'
            . '.28 9.72 19.87 12.35 20.19C13.86 20.37 14.04 20.47 14.04 21.09C14.04 21.53 13.50 22.02 13.'
            . '10 21.97C13.02 21.94 12.42 21.86 11.81 21.78ZM18.06 16.17C17.64 15.76 17.78 15.41 18.83 14'
            . '.32L19.87 13.22 L15.23 13.18C11.08 13.13 10.57 13.08 10.38 12.78C10.22 12.52 10.22 12.33 1'
            . '0.38 12.08C10.57 11.77 11.08 11.73 15.23 11.67L19.87 11.63 L18.83 10.53C17.58 9.20 17.54 8'
            . '.44 18.72 8.44C19.18 8.44 22.80 11.99 22.80 12.43C22.80 12.57 21.99 13.53 20.97 14.54C19.1'
            . '6 16.39 18.60 16.71 18.06 16.17Z"/>',
        'sun' => '<circle cx="12" cy="12" r="3.2" fill="currentColor"/>'
            . '<path fill="currentColor" d="M11.1 2h1.8v2.6h-1.8V2zm0 17.4h1.8V22h-1.8v-2.6z'
            . 'M2 11.1h2.6v1.8H2v-1.8zm17.4 0H22v1.8h-2.6v-1.8zM4.4 5.6l1.3-1.3'
            . ' 1.8 1.8-1.3 1.3-1.8-1.8zm12.1 12.1 1.3-1.3 1.8 1.8-1.3 1.3-1.8-1.8z'
            . 'M4.4 18.4l1.8-1.8 1.3 1.3-1.8 1.8-1.3-1.3zm12.1-12.1 1.8-1.8'
            . ' 1.3 1.3-1.8 1.8-1.3-1.3z"/>',
        'moon' => '<path fill="currentColor" d="M14.2 2.4a8.2 8.2 0 1 0 7.1 12.2A6.7 6.7 0 0 1 14.2 2.4z"/>',
        'stat' => '',
    ];

    if ($name === 'stat') {
        return '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false">'
            . '<circle cx="12" cy="12" r="7.25" stroke="currentColor" stroke-width="1.5"/>'
            . '</svg>';
    }

    if (!isset($icons[$name])) {
        return '';
    }

    return '<svg viewBox="0 0 24 24" fill-rule="evenodd" aria-hidden="true" focusable="false">'
        . $icons[$name]
        . '</svg>';
}

/**
 * @return array<string, string>
 */
function cta_shell_page_titles(): array
{
    return [
        'progression'    => __('Progression', 'chassesautresor-com'),
        'reglement'      => __('Règlement', 'chassesautresor-com'),
        'trophees'       => __('Trophées', 'chassesautresor-com'),
        'outils'         => __('Outils', 'chassesautresor-com'),
        'points'         => __('Points', 'chassesautresor-com'),
        'equipe'         => __('Équipe', 'chassesautresor-com'),
        'classement'     => __('Classement', 'chassesautresor-com'),
        'notifications'  => __('Notifications', 'chassesautresor-com'),
    ];
}

/**
 * Placeholder routes that are not backed by an existing published page.
 */
function cta_shell_is_virtual_request(): bool
{
    if (!function_exists('cta_shell_request_slug')) {
        return false;
    }

    $slug = cta_shell_request_slug();
    $titles = cta_shell_page_titles();
    if (!isset($titles[$slug])) {
        return false;
    }

    if (in_array($slug, ['progression', 'reglement'], true) && function_exists('cta_shell_published_page_url')) {
        if (cta_shell_published_page_url($slug) !== '') {
            return false;
        }
    }

    return true;
}

/**
 * The home navigation stays on the cockpit. An enigma page has its own menu.
 */
function cta_shell_shows_sidebar(): bool
{
    return !function_exists('is_singular') || !is_singular('enigme');
}

/**
 * Opens the shell around the theme markup.
 */
function cta_shell_open(): void
{
    if (!empty($GLOBALS['cta_shell_opened'])) {
        return;
    }

    $GLOBALS['cta_shell_opened'] = true;
    $theme = cta_shell_theme();
    $showNav = cta_shell_shows_sidebar();
    $shellClass = $showNav ? 'app-shell' : 'app-shell app-shell--no-nav';

    echo '<div class="' . esc_attr($shellClass) . '" data-theme="' . esc_attr($theme) . '">';
    get_template_part('template-parts/shell/topbar');
    echo '<div class="app-frame">';
    if ($showNav) {
        echo '<div class="app-nav-backdrop" data-app-nav-close hidden></div>';
        get_template_part('template-parts/shell/sidebar');
    }
    echo '<div class="app-main">';
}

/**
 * Closes the shell opened by cta_shell_open().
 */
function cta_shell_close(): void
{
    if (empty($GLOBALS['cta_shell_opened'])) {
        return;
    }

    echo '</div></div></div>';
    $GLOBALS['cta_shell_opened'] = false;
}

/**
 * Option that turns the rolling home-banner sphere on. Off is the normal mode.
 */
function cta_shell_hunt_sphere_option(): string
{
    return 'cta_hunt_banner_sphere_enabled';
}

/**
 * The sphere is opt-in. Absent or any value other than "1" keeps the still image.
 */
function cta_shell_hunt_sphere_enabled(): bool
{
    if (!function_exists('get_option')) {
        return false;
    }

    return (string) get_option(cta_shell_hunt_sphere_option(), '0') === '1';
}

/**
 * Reads an ACF image or file value into an attachment source.
 *
 * @param mixed $imageData
 * @return array{id:int,url:string,mime:string}
 */
function cta_shell_image_value_source($imageData): array
{
    $source = ['id' => 0, 'url' => '', 'mime' => ''];
    if (is_array($imageData)) {
        if (!empty($imageData['ID'])) {
            $source['id'] = (int) $imageData['ID'];
        } elseif (!empty($imageData['id'])) {
            $source['id'] = (int) $imageData['id'];
        }
        if (!empty($imageData['url']) && is_string($imageData['url'])) {
            $source['url'] = $imageData['url'];
        }
        if (!empty($imageData['mime_type']) && is_string($imageData['mime_type'])) {
            $source['mime'] = $imageData['mime_type'];
        }
    } elseif (is_numeric($imageData)) {
        $source['id'] = (int) $imageData;
    } elseif (is_string($imageData) && $imageData !== '') {
        $source['url'] = $imageData;
    }

    return $source;
}

/**
 * Whether the attachment should play in the home strip instead of being cropped as a picture.
 */
function cta_shell_attachment_is_video(string $mime, string $url = ''): bool
{
    if (strncmp($mime, 'video/', 6) === 0) {
        return true;
    }

    $path = strtolower((string) parse_url($url, PHP_URL_PATH));

    return (bool) preg_match('/\.(mp4|webm|ogg)$/', $path);
}

/**
 * Fills a missing MIME type and URL from the attachment id.
 *
 * @param array{id:int,url:string,mime?:string} $source
 * @return array{id:int,url:string,mime:string}
 */
function cta_shell_resolve_image_source(array $source): array
{
    if (!isset($source['mime'])) {
        $source['mime'] = '';
    }

    if ($source['mime'] === '' && $source['id'] > 0 && function_exists('get_post_mime_type')) {
        $mime = get_post_mime_type($source['id']);
        $source['mime'] = is_string($mime) ? $mime : '';
    }

    $isVideo = cta_shell_attachment_is_video($source['mime'], $source['url']);
    if (
        $source['url'] === ''
        && $source['id'] > 0
        && !$isVideo
        && function_exists('wp_get_attachment_image_url')
    ) {
        $resolved = wp_get_attachment_image_url($source['id'], 'full');
        $source['url'] = is_string($resolved) ? $resolved : '';
    }

    if (
        $source['url'] === ''
        && $source['id'] > 0
        && $isVideo
        && function_exists('wp_get_attachment_url')
    ) {
        $resolved = wp_get_attachment_url($source['id']);
        $source['url'] = is_string($resolved) ? $resolved : '';
    }

    return $source;
}

/**
 * @param array{id:int,url:string} $source
 */
function cta_shell_image_source_is_empty(array $source): bool
{
    return $source['id'] <= 0 && $source['url'] === '';
}

/**
 * Hunt visual used by the login screen, the cards and the sphere texture.
 *
 * @return array{id:int,url:string}
 */
function cta_shell_hunt_image_source(int $huntId): array
{
    $source = ['id' => 0, 'url' => '', 'mime' => ''];
    if ($huntId <= 0) {
        return $source;
    }

    if (function_exists('get_field')) {
        $source = cta_shell_image_value_source(get_field('chasse_principale_image', $huntId));
    }

    if ($source['id'] <= 0 && function_exists('get_post_thumbnail_id')) {
        $source['id'] = (int) get_post_thumbnail_id($huntId);
    }

    $source = cta_shell_resolve_image_source($source);

    if ($source['url'] === '' && function_exists('get_the_post_thumbnail_url')) {
        $thumb = get_the_post_thumbnail_url($huntId, 'full');
        $source['url'] = is_string($thumb) ? $thumb : '';
    }

    return $source;
}

/**
 * Wide home strip. The panoramic field wins; the hunt poster is the fallback.
 *
 * @return array{id:int,url:string}
 */
function cta_shell_hunt_banner_image_source(int $huntId): array
{
    if ($huntId > 0 && function_exists('get_field')) {
        $banner = cta_shell_resolve_image_source(
            cta_shell_image_value_source(get_field('chasse_bandeau_accueil', $huntId))
        );
        if (!cta_shell_image_source_is_empty($banner)) {
            return $banner;
        }
    }

    return cta_shell_hunt_image_source($huntId);
}

/**
 * Direct image URL for the sphere texture. WebP is preferred when Imagify has one.
 */
function cta_shell_hunt_image_url(int $huntId): string
{
    $image = cta_shell_hunt_image_source($huntId)['url'];
    if ($image !== '' && function_exists('imagify_get_webp_url')) {
        $webp = imagify_get_webp_url($image);
        if (is_string($webp) && $webp !== '') {
            $image = $webp;
        }
    }

    return $image;
}

/**
 * Loads the rolling hunt sphere used by the home banner.
 */
function cta_shell_enqueue_hunt_sphere(): void
{
    if (
        !function_exists('wp_enqueue_script')
        || !function_exists('get_stylesheet_directory')
        || !function_exists('get_stylesheet_directory_uri')
    ) {
        return;
    }

    $relative = '/assets/js/hunt-banner-sphere.js';
    $path = get_stylesheet_directory() . $relative;
    if (!is_readable($path)) {
        return;
    }

    wp_enqueue_script(
        'cta-hunt-sphere',
        get_stylesheet_directory_uri() . $relative,
        [],
        (string) filemtime($path),
        true
    );
}

/**
 * Still banner image. WordPress prints the attachment so Imagify can rewrite it.
 */
function cta_shell_hunt_banner_photo_markup(int $attachmentId, string $url, string $alt): string
{
    if ($attachmentId > 0 && function_exists('wp_get_attachment_image')) {
        $attributes = [
            'class' => 'hunt-banner__photo',
            'loading' => 'eager',
            'decoding' => 'async',
            'fetchpriority' => 'high',
        ];
        if ($alt !== '') {
            $attributes['alt'] = $alt;
        }
        $html = wp_get_attachment_image($attachmentId, 'large', false, $attributes);
        if (is_string($html) && $html !== '') {
            return $html;
        }
    }

    if ($url === '' || !function_exists('esc_url') || !function_exists('esc_attr')) {
        return '';
    }

    return '<img class="hunt-banner__photo" src="' . esc_url($url) . '" alt="' . esc_attr($alt)
        . '" loading="eager" decoding="async" fetchpriority="high">';
}

/**
 * Renders the hunt header.
 * A video in the home strip plays for every role, even when the sphere is on.
 * Otherwise the sphere is loaded only when an administrator has switched it on,
 * and the hunt image stays a normal picture Imagify can optimize.
 */
function cta_shell_render_hunt_banner(): void
{
    $huntId = function_exists('cat_get_primary_hunt_id') ? (int) cat_get_primary_hunt_id() : 0;
    $alt = '';
    if ($huntId > 0 && function_exists('get_the_title')) {
        $alt = (string) get_the_title($huntId);
    }

    $label = esc_attr__('Image de la chasse', 'chassesautresor-com');
    echo '<section class="hunt-banner" aria-label="' . $label . '">';
    if (cta_shell_hunt_sphere_enabled() && !cta_shell_hunt_banner_is_video($huntId)) {
        cta_shell_render_hunt_banner_sphere($huntId, $alt);
    } else {
        cta_shell_render_hunt_banner_photo($huntId, $alt);
    }
    echo '</section>';
}

/**
 * Whether the home strip field currently stores a video.
 */
function cta_shell_hunt_banner_is_video(int $huntId): bool
{
    $source = cta_shell_hunt_banner_image_source($huntId);

    return cta_shell_attachment_is_video(
        (string) ($source['mime'] ?? ''),
        (string) ($source['url'] ?? '')
    );
}

/**
 * Rolling sphere. This is the only path that prints the canvas and loads its script.
 */
function cta_shell_render_hunt_banner_sphere(int $huntId, string $alt): void
{
    $url = cta_shell_hunt_image_url($huntId);
    if ($url === '') {
        cta_shell_render_hunt_banner_empty();
        return;
    }

    cta_shell_enqueue_hunt_sphere();
    $src = esc_url($url);
    echo '<div class="hunt-banner__sphere" data-hunt-sphere data-image="' . $src . '">';
    echo '<canvas class="hunt-banner__canvas" aria-hidden="true"></canvas>';
    echo '<img class="hunt-banner__fallback" src="' . $src . '" alt="' . esc_attr($alt) . '">';
    echo '</div>';
    echo '<script>(function(){var s=document.currentScript,n=s&&s.previousElementSibling;';
    echo 'if(n){n.classList.add("is-pending");}})();</script>';
}

/**
 * Muted strip, played once. The file is on the element itself so playback
 * does not depend on a deferred script or a reduced-motion check.
 * A late script must not restart a clip that already began.
 */
function cta_shell_hunt_banner_video_markup(string $url, string $poster): string
{
    if ($url === '' || !function_exists('esc_url')) {
        return '';
    }

    $posterAttr = $poster !== '' ? ' poster="' . esc_url($poster) . '"' : '';

    return '<video class="hunt-banner__video" src="' . esc_url($url) . '"'
        . $posterAttr
        . ' autoplay muted playsinline preload="auto" aria-hidden="true"></video>'
        . '<script>(function(){var s=document.currentScript;'
        . 'var v=(s&&s.previousElementSibling)||document.querySelector(".hunt-banner__video");'
        . 'if(!v||!v.play||v.ended||v.currentTime>0){return;}'
        . 'v.muted=true;v.loop=false;'
        . 'var p=v.play();if(p&&p.catch){p.catch(function(){});}'
        . '})();</script>';
}

/**
 * Normal picture, or the MP4 stored in the same banner field.
 * No canvas and no sphere script.
 */
function cta_shell_render_hunt_banner_photo(int $huntId, string $alt): void
{
    $source = cta_shell_hunt_banner_image_source($huntId);
    $mime = (string) ($source['mime'] ?? '');
    if (cta_shell_attachment_is_video($mime, (string) $source['url'])) {
        $poster = (string) cta_shell_hunt_image_source($huntId)['url'];
        if ($poster === $source['url']) {
            $poster = '';
        }
        $html = cta_shell_hunt_banner_video_markup((string) $source['url'], $poster);
    } else {
        $html = cta_shell_hunt_banner_photo_markup($source['id'], $source['url'], $alt);
    }
    if ($html === '') {
        cta_shell_render_hunt_banner_empty();
        return;
    }

    echo '<div class="hunt-banner__frame">';
    echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo '</div>';
}

/**
 * Empty frame used when the hunt has no image yet.
 */
function cta_shell_render_hunt_banner_empty(): void
{
    echo '<p class="hunt-banner__empty">';
    echo esc_html__('Emplacement de l’image de la chasse', 'chassesautresor-com');
    echo '</p>';
}

/**
 * The home strip was created as an ACF image field, which rejects an MP4
 * and drops a video id the next time the post is saved.
 *
 * @param mixed $field
 * @return mixed
 */
function cta_shell_prepare_home_banner_field($field)
{
    if (!is_array($field)) {
        return $field;
    }
    if (function_exists('acf_is_screen') && acf_is_screen('acf-field-group')) {
        return $field;
    }

    $field['type'] = 'file';
    $field['return_format'] = 'array';
    $field['mime_types'] = 'jpg,jpeg,png,gif,webp,mp4,webm';
    if (empty($field['library'])) {
        $field['library'] = 'all';
    }

    return $field;
}
add_filter('acf/load_field/name=chasse_bandeau_accueil', 'cta_shell_prepare_home_banner_field');

/**
 * Keeps image-only attachment checks from blocking this field.
 *
 * @param mixed $isImageField
 * @param mixed $field
 */
function cta_shell_banner_field_allows_video($isImageField, $field = null): bool
{
    if (is_array($field)) {
        $name = (string) ($field['_name'] ?? $field['name'] ?? '');
        if ($name === 'chasse_bandeau_accueil') {
            return false;
        }
    }

    return (bool) $isImageField;
}
add_filter(
    'acf/is_image_field/name=chasse_bandeau_accueil',
    'cta_shell_banner_field_allows_video',
    10,
    2
);

/**
 * A video attachment is valid in the home strip. Other failures stay in place.
 *
 * @param mixed $valid
 * @param mixed $value
 * @param mixed $field
 * @param mixed $input
 * @return mixed
 */
function cta_shell_banner_value_is_allowed($valid, $value, $field = null, $input = null)
{
    unset($field, $input);
    if (!is_numeric($value) || !function_exists('get_post_mime_type')) {
        return $valid;
    }

    $mime = get_post_mime_type((int) $value);
    if (is_string($mime) && strncmp($mime, 'video/', 6) === 0) {
        return true;
    }

    return $valid;
}
add_filter(
    'acf/validate_value/name=chasse_bandeau_accueil',
    'cta_shell_banner_value_is_allowed',
    20,
    4
);

/**
 * Administrator tile that turns the home-banner sphere on or off.
 */
function cta_shell_render_hunt_sphere_switch(): string
{
    $isAdmin = function_exists('cta_shell_user_is_administrator') && cta_shell_user_is_administrator();
    if (!$isAdmin) {
        return '';
    }

    $enabled = cta_shell_hunt_sphere_enabled();
    $status = $enabled
        ? __('Activée', 'chassesautresor-com')
        : __('Désactivée', 'chassesautresor-com');

    ob_start();
    ?>
    <div class="dashboard-card hunt-banner-sphere-card" data-hunt-banner-sphere>
        <div class="dashboard-card-header">
            <i class="fas fa-globe" aria-hidden="true"></i>
            <h3><?php esc_html_e('Sphère du bandeau', 'chassesautresor-com'); ?></h3>
        </div>
        <div class="dashboard-card-content">
            <label class="switch-control">
                <input
                    type="checkbox"
                    id="hunt-banner-sphere-toggle"
                    <?php checked($enabled); ?>
                    aria-label="<?php esc_attr_e('Activer la sphère du bandeau', 'chassesautresor-com'); ?>"
                >
                <span class="switch-slider"></span>
            </label>
            <span id="hunt-banner-sphere-status"><?php echo esc_html($status); ?></span>
            <p class="hunt-banner-sphere-card__note">
                <?php esc_html_e(
                    'Désactivée, le bandeau sert l’image sans charger le script de la sphère.',
                    'chassesautresor-com'
                ); ?>
            </p>
        </div>
    </div>
    <?php

    return (string) ob_get_clean();
}

/**
 * Player ranking or team ranking for the current hunt.
 * An empty note is shown when no hunt applies.
 */
function cta_shell_render_player_ranking(): void
{
    $user_id = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
    $hunts = cta_shell_player_hunts($user_id, 'classement');
    if ($hunts === [] && $user_id > 0 && function_exists('cat_get_managed_hunt_id_for_user')) {
        $managed = (int) cat_get_managed_hunt_id_for_user($user_id);
        if ($managed > 0) {
            $base = function_exists('cta_shell_location_url') ? cta_shell_location_url('classement') : '';
            $url = $base;
            if ($base !== '' && function_exists('add_query_arg')) {
                $url = (string) add_query_arg('chasse', (string) $managed, $base);
            }
            $title = function_exists('get_the_title') ? (string) get_the_title($managed) : '';
            $hunts = [['id' => $managed, 'title' => $title, 'url' => $url]];
        }
    }
    $requested = isset($_GET['chasse']) ? (int) $_GET['chasse'] : 0;
    $hunt_id = 0;
    foreach ($hunts as $hunt) {
        if ((int) $hunt['id'] === $requested) {
            $hunt_id = $requested;
            break;
        }
    }
    if ($hunt_id <= 0 && $hunts !== []) {
        $hunt_id = (int) $hunts[0]['id'];
    }

    if (
        $user_id <= 0
        || $hunt_id <= 0
        || !class_exists(\ChassesAuTresor\Core\Messages\PlayerProgressionRenderer::class)
        || !class_exists(\ChassesAuTresor\Core\Progress\PlayerProgressionApplicationService::class)
    ) {
        echo '<p class="app-placeholder__note">';
        echo esc_html__('Aucune chasse engagée pour le moment.', 'chassesautresor-com');
        echo '</p>';
        return;
    }

    $board = 'joueurs';
    if (isset($_GET['vue'])) {
        $requestedBoard = function_exists('sanitize_key')
            ? sanitize_key(wp_unslash((string) $_GET['vue']))
            : (string) $_GET['vue'];
        if ($requestedBoard === 'equipes') {
            $board = 'equipes';
        }
    }
    $currentHuntUrl = '';
    foreach ($hunts as $hunt) {
        if ((int) $hunt['id'] === $hunt_id) {
            $currentHuntUrl = (string) $hunt['url'];
            break;
        }
    }
    $joueursUrl = function_exists('remove_query_arg')
        ? (string) remove_query_arg('vue', $currentHuntUrl)
        : $currentHuntUrl;
    $equipesUrl = function_exists('add_query_arg')
        ? (string) add_query_arg('vue', 'equipes', $joueursUrl)
        : $joueursUrl;
    if ($board === 'equipes') {
        foreach ($hunts as $index => $hunt) {
            $hunts[$index]['url'] = function_exists('add_query_arg')
                ? (string) add_query_arg('vue', 'equipes', (string) $hunt['url'])
                : (string) $hunt['url'];
        }
    }

    global $wpdb;
    $views = [
        [
            'id' => 'joueurs',
            'title' => __('Joueurs', 'chassesautresor-com'),
            'url' => $joueursUrl,
        ],
        [
            'id' => 'equipes',
            'title' => __('Équipes', 'chassesautresor-com'),
            'url' => $equipesUrl,
        ],
    ];
    if ($board === 'equipes') {
        $fragment = cta_shell_team_ranking_fragment($user_id, $hunt_id);
        $view = [
            'hunt_id' => $hunt_id,
            'board' => 'equipes',
            'views' => $views,
            'team_ranking_html' => $fragment['html'],
            'team_ajax' => $fragment['ajax'],
        ];
    } else {
        $view = (new \ChassesAuTresor\Core\Progress\PlayerProgressionApplicationService(
            new \ChassesAuTresor\Core\Progress\ProgressionOverviewRepository($wpdb),
            new \ChassesAuTresor\Core\Progress\PlayerProgressionRepository($wpdb)
        ))->rankingPage($user_id, $hunt_id);
        $view['board'] = 'joueurs';
        $view['views'] = $views;
    }
    echo (new \ChassesAuTresor\Core\Messages\PlayerProgressionRenderer())->renderRanking(
        $view,
        $hunts,
        $hunt_id,
        [
            'url' => function_exists('admin_url') ? admin_url('admin-ajax.php') : '',
            'nonce' => function_exists('wp_create_nonce') ? wp_create_nonce('cat_player_progression') : '',
        ]
    );
}

/**
 * Team leaderboard shown on the Équipes tab of Classement.
 *
 * @return array{html:string,ajax:array{url:string,nonce:string}}
 */
function cta_shell_team_ranking_fragment(int $user_id, int $hunt_id): array
{
    $ajax = [
        'url' => function_exists('admin_url') ? admin_url('admin-ajax.php') : '',
        'nonce' => function_exists('wp_create_nonce') ? wp_create_nonce('cat_team_performance') : '',
    ];
    $empty = '<p class="equipe-note">'
        . esc_html__('Aucune équipe classée.', 'chassesautresor-com') . '</p>';
    $ready = class_exists(\ChassesAuTresor\Core\Teams\TeamPerformanceApplicationService::class)
        && class_exists(\ChassesAuTresor\Core\Teams\TeamPerformanceRepository::class)
        && class_exists(\ChassesAuTresor\Core\Teams\TeamPageRenderer::class)
        && class_exists(\ChassesAuTresor\Core\Teams\TeamRepository::class);
    if (!$ready) {
        return ['html' => $empty, 'ajax' => $ajax];
    }

    global $wpdb;
    $teamId = (new \ChassesAuTresor\Core\Teams\TeamRepository($wpdb))->activeTeamId($user_id);
    $ranking = (new \ChassesAuTresor\Core\Teams\TeamPerformanceApplicationService(
        new \ChassesAuTresor\Core\Teams\TeamPerformanceRepository($wpdb)
    ))->ranking($teamId, $hunt_id, 1, $user_id);
    $renderer = new \ChassesAuTresor\Core\Teams\TeamPageRenderer(
        static function (int $attachmentId): string {
            if (!function_exists('wp_get_attachment_image_url')) {
                return '';
            }
            $url = wp_get_attachment_image_url($attachmentId, 'thumbnail');

            return is_string($url) ? $url : '';
        },
        function_exists('cta_shell_icon') ? (string) cta_shell_icon('team') : ''
    );

    return ['html' => $renderer->ranking($ranking), 'ajax' => $ajax];
}

/**
 * Player progression for the current hunt. An empty note is shown when no hunt applies.
 */
function cta_shell_render_team_stats(): void
{
    $user_id = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
    $hunts = cta_shell_player_hunts($user_id);
    $requested = isset($_GET['chasse']) ? (int) $_GET['chasse'] : 0;
    $hunt_id = 0;
    foreach ($hunts as $hunt) {
        if ((int) $hunt['id'] === $requested) {
            $hunt_id = $requested;
            break;
        }
    }
    if ($hunt_id <= 0 && $hunts !== []) {
        $hunt_id = (int) $hunts[0]['id'];
    }

    if (
        $user_id <= 0
        || $hunt_id <= 0
        || !class_exists(\ChassesAuTresor\Core\Messages\PlayerProgressionRenderer::class)
        || !class_exists(\ChassesAuTresor\Core\Progress\PlayerProgressionApplicationService::class)
    ) {
        echo '<p class="app-placeholder__note">';
        echo esc_html__('Aucune chasse engagée pour le moment.', 'chassesautresor-com');
        echo '</p>';
        return;
    }

    global $wpdb;
    $overview = (new \ChassesAuTresor\Core\Progress\PlayerProgressionApplicationService(
        new \ChassesAuTresor\Core\Progress\ProgressionOverviewRepository($wpdb),
        new \ChassesAuTresor\Core\Progress\PlayerProgressionRepository($wpdb)
    ))->build($user_id, $hunt_id);
    echo (new \ChassesAuTresor\Core\Messages\PlayerProgressionRenderer())->render(
        $overview,
        $hunts,
        $hunt_id,
        [
            'url' => function_exists('admin_url') ? admin_url('admin-ajax.php') : '',
            'nonce' => function_exists('wp_create_nonce') ? wp_create_nonce('cat_player_progression') : '',
        ]
    );
}

/**
 * Hunts a player can inspect. Single-hunt mode uses the primary hunt.
 *
 * @return array<int, array{id:int,title:string,url:string}>
 */
function cta_shell_player_hunts(int $user_id, string $slug = 'progression'): array
{
    if ($user_id <= 0) {
        return [];
    }

    $ids = [];
    $single_hunt = function_exists('cat_is_single_hunt_mode') && cat_is_single_hunt_mode();
    if ($single_hunt && function_exists('cat_get_primary_hunt_id')) {
        $primary = (int) cat_get_primary_hunt_id();
        $ids = $primary > 0 ? [$primary] : [];
    } elseif (function_exists('ca_get_user_engaged_hunt_ids')) {
        $ids = ca_get_user_engaged_hunt_ids($user_id);
    } elseif (function_exists('cat_get_primary_hunt_id')) {
        $primary = (int) cat_get_primary_hunt_id();
        $ids = $primary > 0 ? [$primary] : [];
    }

    $base = function_exists('cta_shell_location_url') ? cta_shell_location_url($slug) : '';
    $hunts = [];
    foreach ($ids as $id) {
        $id = (int) $id;
        if ($id <= 0) {
            continue;
        }
        $title = function_exists('get_the_title') ? (string) get_the_title($id) : '';
        $url = $base;
        if ($base !== '' && function_exists('add_query_arg')) {
            $url = (string) add_query_arg('chasse', (string) $id, $base);
        }
        $hunts[] = ['id' => $id, 'title' => $title, 'url' => $url];
    }

    return $hunts;
}

/**
 * @param array<int, string> $classes Body classes.
 * @return array<int, string>
 */
function cta_shell_body_class(array $classes): array
{
    $classes[] = 'app-shell-active';

    return $classes;
}
add_filter('body_class', 'cta_shell_body_class');

/**
 * Applies the stored theme before the first paint.
 */
function cta_shell_theme_boot(): void
{
    echo "<script>(function(){try{var m=document.cookie.match(/(?:^|; )cta_theme=([^;]*)/);"
        . "var t=m&&decodeURIComponent(m[1])==='day'?'day':'night';"
        . "document.documentElement.setAttribute('data-theme',t);"
        . "}catch(e){document.documentElement.setAttribute('data-theme','night');}})();</script>\n";
}
add_action('wp_head', 'cta_shell_theme_boot', 0);

/**
 * Enqueues the shell behaviour.
 */
function cta_shell_enqueue(): void
{
    $path = get_stylesheet_directory() . '/assets/js/app-shell.js';
    if (!file_exists($path)) {
        return;
    }

    wp_enqueue_script(
        'cta-app-shell',
        get_stylesheet_directory_uri() . '/assets/js/app-shell.js',
        [],
        (string) filemtime($path),
        true
    );

    $shellSlug = function_exists('cta_shell_request_slug') ? cta_shell_request_slug() : '';
    if (in_array($shellSlug, ['progression', 'classement'], true)) {
        $progressionPath = get_stylesheet_directory() . '/assets/js/player-progression.js';
        if (file_exists($progressionPath)) {
            wp_enqueue_script(
                'cta-player-progression',
                get_stylesheet_directory_uri() . '/assets/js/player-progression.js',
                [],
                (string) filemtime($progressionPath),
                true
            );
        }
    }

    if ($shellSlug === 'classement') {
        $teamPath = get_stylesheet_directory() . '/assets/js/team-performance.js';
        if (file_exists($teamPath)) {
            wp_enqueue_script(
                'cta-team-performance',
                get_stylesheet_directory_uri() . '/assets/js/team-performance.js',
                [],
                (string) filemtime($teamPath),
                true
            );
        }
    }

    if ($shellSlug === 'trophees') {
        $trophyWallPath = get_stylesheet_directory() . '/assets/js/trophy-wall.js';
        if (file_exists($trophyWallPath)) {
            wp_enqueue_script(
                'cta-trophy-wall',
                get_stylesheet_directory_uri() . '/assets/js/trophy-wall.js',
                [],
                (string) filemtime($trophyWallPath),
                true
            );
        }
    }
}
add_action('wp_enqueue_scripts', 'cta_shell_enqueue');

/**
 * @param bool     $preempt  Whether to short-circuit 404 handling.
 * @param WP_Query $wp_query Current query.
 */
function cta_shell_normalize_virtual_query($wp_query): void
{
    if (!is_object($wp_query)) {
        return;
    }

    $flags = [
        'is_404',
        'is_single',
        'is_singular',
        'is_page',
        'is_attachment',
        'is_preview',
        'is_home',
        'is_posts_page',
        'is_archive',
        'is_category',
        'is_tag',
        'is_tax',
        'is_author',
        'is_date',
        'is_search',
        'is_feed',
        'is_embed',
    ];
    foreach ($flags as $flag) {
        $wp_query->{$flag} = false;
    }

    $wp_query->queried_object = null;
    $wp_query->queried_object_id = 0;
    $wp_query->post = null;
    $wp_query->posts = [];
    $wp_query->post_count = 0;
    $GLOBALS['post'] = null;
}

/**
 * @param bool     $preempt  Whether to short-circuit 404 handling.
 * @param WP_Query $wp_query Current query.
 */
function cta_shell_pre_handle_404($preempt, $wp_query)
{
    if (!cta_shell_is_virtual_request()) {
        return $preempt;
    }

    // A missing public path is otherwise left singular, with no post object.
    cta_shell_normalize_virtual_query($wp_query);

    return true;
}
add_filter('pre_handle_404', 'cta_shell_pre_handle_404', 10, 2);

/**
 * @param string|false $redirect Canonical redirect target.
 * @return string|false
 */
function cta_shell_redirect_canonical($redirect)
{
    if (cta_shell_is_virtual_request()) {
        return false;
    }

    return $redirect;
}
add_filter('redirect_canonical', 'cta_shell_redirect_canonical');

/**
 * Serves the placeholder template for virtual shell pages.
 */
function cta_shell_template(string $template): string
{
    if (!cta_shell_is_virtual_request()) {
        return $template;
    }

    status_header(200);
    $GLOBALS['cta_shell_page'] = cta_shell_request_slug();
    $placeholder = get_stylesheet_directory() . '/templates/shell-placeholder.php';

    return file_exists($placeholder) ? $placeholder : $template;
}
add_filter('template_include', 'cta_shell_template');

/**
 * @param array<string, string> $parts Document title parts.
 * @return array<string, string>
 */
function cta_shell_document_title(array $parts): array
{
    if (!cta_shell_is_virtual_request() || !function_exists('cta_shell_request_slug')) {
        return $parts;
    }

    $target = function_exists('cta_shell_edition_target') ? cta_shell_edition_target() : null;
    if (is_array($target) && function_exists('get_the_title')) {
        $editionTitle = (string) get_the_title((int) $target['id']);
        if ($editionTitle !== '') {
            $parts['title'] = $editionTitle;

            return $parts;
        }
    }

    $titles = cta_shell_page_titles();
    $slug = cta_shell_request_slug();
    if (isset($titles[$slug])) {
        $parts['title'] = $titles[$slug];
    }

    return $parts;
}
add_filter('document_title_parts', 'cta_shell_document_title');

/**
 * Logout lives in the top bar, not in the profile menu.
 *
 * @param array<string, string> $items Account menu items.
 * @return array<string, string>
 */
function cta_shell_remove_account_logout(array $items): array
{
    unset($items['customer-logout']);

    return $items;
}
add_filter('woocommerce_account_menu_items', 'cta_shell_remove_account_logout');
