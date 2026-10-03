<?php

/**
 * SEO metadata for the single-hunt homepage.
 *
 * @package chassesautresor.com
 */

defined('ABSPATH') || exit;

/**
 * Whether the current request is the single-hunt homepage.
 */
function cta_is_single_hunt_homepage(): bool
{
    return is_front_page()
        && function_exists('cat_is_single_hunt_mode')
        && cat_is_single_hunt_mode();
}

/**
 * Returns the main hunt metadata used by the document head.
 *
 * @return array{id: int, title: string, description: string, url: string, image: string}
 */
function cta_get_single_hunt_seo_data(): array
{
    $hunt_id = function_exists('cat_get_primary_hunt_id') ? cat_get_primary_hunt_id() : 0;

    if ($hunt_id <= 0) {
        return [
            'id'          => 0,
            'title'       => get_bloginfo('name'),
            'description' => get_bloginfo('description'),
            'url'         => home_url('/'),
            'image'       => '',
        ];
    }

    $raw_description = function_exists('get_field')
        ? get_field('chasse_principale_description', $hunt_id)
        : '';

    if (!$raw_description) {
        $raw_description = get_the_excerpt($hunt_id);
    }

    $description = wp_trim_words(wp_strip_all_tags((string) $raw_description), 32, '…');
    $image       = get_the_post_thumbnail_url($hunt_id, 'full');

    if (!$image && function_exists('get_field')) {
        $image_data = get_field('chasse_principale_image', $hunt_id);

        if (is_array($image_data)) {
            $image = (string) ($image_data['url'] ?? '');
        } elseif ($image_data) {
            $image = (string) wp_get_attachment_image_url((int) $image_data, 'full');
        }
    }

    return [
        'id'          => $hunt_id,
        'title'       => get_the_title($hunt_id),
        'description' => $description,
        'url'         => home_url('/'),
        'image'       => (string) $image,
    ];
}

/**
 * Gives the homepage a title centered on the primary hunt.
 */
function cta_filter_single_hunt_document_title(string $title): string
{
    if (!cta_is_single_hunt_homepage()) {
        return $title;
    }

    $seo = cta_get_single_hunt_seo_data();

    return sprintf(
        /* translators: 1: hunt title, 2: website name. */
        __('%1$s — Chasse au trésor | %2$s', 'chassesautresor-com'),
        $seo['title'],
        get_bloginfo('name')
    );
}
add_filter('pre_get_document_title', 'cta_filter_single_hunt_document_title', 20);

/**
 * Prints social, indexing and structured metadata for the single-hunt homepage.
 */
function cta_render_single_hunt_metadata(): void
{
    if (!cta_is_single_hunt_homepage()) {
        return;
    }

    $seo      = cta_get_single_hunt_seo_data();
    $is_demo  = function_exists('cat_is_demo_mode') && cat_is_demo_mode();
    $page_url = home_url('/');

    if ($is_demo) {
        echo '<meta name="robots" content="noindex,nofollow,noarchive">' . "\n";
    }

    if ($seo['description']) {
        printf('<meta name="description" content="%s">' . "\n", esc_attr($seo['description']));
    }

    printf('<meta property="og:type" content="website">' . "\n");
    printf('<meta property="og:title" content="%s">' . "\n", esc_attr($seo['title']));
    printf('<meta property="og:url" content="%s">' . "\n", esc_url($page_url));

    if ($seo['description']) {
        printf('<meta property="og:description" content="%s">' . "\n", esc_attr($seo['description']));
    }

    if ($seo['image']) {
        printf('<meta property="og:image" content="%s">' . "\n", esc_url($seo['image']));
    }

    $structured_data = [
        '@context'   => 'https://schema.org',
        '@type'      => 'WebPage',
        'name'       => $seo['title'],
        'description' => $seo['description'],
        'url'        => $page_url,
        'mainEntity' => [
            '@type' => 'CreativeWork',
            'name'  => $seo['title'],
            'url'   => $seo['id'] > 0 ? get_permalink($seo['id']) : $page_url,
        ],
    ];

    if ($seo['image']) {
        $structured_data['image'] = $seo['image'];
        $structured_data['mainEntity']['image'] = $seo['image'];
    }

    printf(
        '<script type="application/ld+json">%s</script>' . "\n",
        wp_json_encode($structured_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    );
}
add_action('wp_head', 'cta_render_single_hunt_metadata', 2);
