<?php

defined('ABSPATH') || exit;

/**
 * Compact site footer helpers (legal bar only).
 */

/**
 * Returns the active public experience mode for the footer.
 *
 * @return string platform|single_hunt|demo
 */
function cta_get_footer_experience_mode(): string
{
    if (function_exists('cat_is_demo_mode') && cat_is_demo_mode()) {
        return 'demo';
    }

    if (function_exists('cat_is_single_hunt_mode') && cat_is_single_hunt_mode()) {
        return 'single_hunt';
    }

    if (function_exists('cat_is_platform_mode') && cat_is_platform_mode()) {
        return 'platform';
    }

    // Fallback when the plugin helpers are unavailable: prefer the compact public chrome.
    return 'single_hunt';
}

/**
 * Resolves a published page URL by path, or returns an empty string.
 */
function cta_get_footer_page_url(string $path): string
{
    $path = trim($path, '/');
    if ($path === '') {
        return home_url('/');
    }

    $page = get_page_by_path($path);
    if ($page instanceof WP_Post && $page->post_status === 'publish') {
        $permalink = get_permalink($page);

        return $permalink ? (string) $permalink : '';
    }

    return '';
}

/**
 * Builds a footer link entry when the URL is usable.
 *
 * @return array{label:string,url:string}|null
 */
function cta_build_footer_link(string $label, string $url): ?array
{
    $url = trim($url);
    if ($url === '') {
        return null;
    }

    return [
        'label' => $label,
        'url'   => $url,
    ];
}

/**
 * Returns the compact legal bar links.
 *
 * @return array<int, array{label:string,url:string}>
 */
function cta_get_footer_legal_links(): array
{
    $links = [];

    $contactUrl = cta_get_footer_page_url('contact');
    if ($contactUrl !== '') {
        $links[] = cta_build_footer_link(__('Contact', 'chassesautresor-com'), $contactUrl);
    }

    $mentionsUrl = cta_get_footer_page_url('mentions-legales');
    if ($mentionsUrl === '') {
        $mentionsUrl = home_url('/mentions-legales/');
    }
    $links[] = cta_build_footer_link(__('Mentions légales', 'chassesautresor-com'), $mentionsUrl);

    $privacyUrl = function_exists('get_privacy_policy_url') ? (string) get_privacy_policy_url() : '';
    if ($privacyUrl !== '') {
        $links[] = cta_build_footer_link(
            __('Confidentialité', 'chassesautresor-com'),
            $privacyUrl
        );
    }

    /**
     * Filters the compact footer legal links.
     *
     * @param array  $links Legal links.
     * @param string $mode  Active experience mode.
     */
    return apply_filters(
        'cta_footer_legal_links',
        array_values(array_filter($links)),
        cta_get_footer_experience_mode()
    );
}

/**
 * Aggregates the data consumed by the footer template.
 *
 * @return array{
 *     mode:string,
 *     legal_links:array<int, array{label:string,url:string}>,
 *     copyright:string
 * }
 */
function cta_get_site_footer_data(): array
{
    $mode = cta_get_footer_experience_mode();
    $year = (string) wp_date('Y');
    $siteName = (string) get_bloginfo('name');
    if ($siteName === '') {
        $siteName = __('Chasses au Trésor', 'chassesautresor-com');
    }

    $data = [
        'mode'        => $mode,
        'legal_links' => cta_get_footer_legal_links(),
        'copyright'   => sprintf(
            /* translators: 1: current year, 2: site name. */
            __('© %1$s %2$s', 'chassesautresor-com'),
            $year,
            $siteName
        ),
    ];

    /**
     * Filters the complete site footer payload.
     *
     * @param array $data Footer data.
     */
    return apply_filters('cta_site_footer_data', $data);
}

/**
 * Renders the experience-aware site footer.
 */
function cta_render_site_footer(): void
{
    $footer = cta_get_site_footer_data();
    get_template_part('template-parts/footer/site-footer', null, ['footer' => $footer]);
}
