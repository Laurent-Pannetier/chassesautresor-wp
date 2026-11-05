<?php
use PHPUnit\Framework\TestCase;

if (!function_exists('wp_kses_post')) {
    function wp_kses_post($content) {
        return $content;
    }
}
if (!function_exists('__')) {
    function __($text, $domain = null) {
        return $text;
    }
}
if (!function_exists('home_url')) {
    function home_url($path = '') {
        return 'https://example.com/' . ltrim($path, '/');
    }
}
if (!function_exists('get_theme_file_uri')) {
    function get_theme_file_uri($path) {
        return 'https://example.com/' . ltrim($path, '/');
    }
}
if (!function_exists('get_site_icon_url')) {
    function get_site_icon_url($size = 512) {
        return 'https://example.com/site-icon.png';
    }
}

require_once dirname(__DIR__, 4) . '/vendor/autoload.php';
require_once __DIR__ . '/../../../plugins/chassesautresor-core/src/Email/template.php';

class EmailTemplateTest extends TestCase
{
    public function test_template_contains_expected_elements(): void
    {
        $title   = 'Titre';
        $content = '<p>Bonjour</p>';

        $html = cta_render_email_template($title, $content);

        $this->assertStringContainsString($title, $html);
        $this->assertStringContainsString($content, $html);
        $this->assertStringContainsString('<header', $html);
        $this->assertStringContainsString('<footer', $html);
        $this->assertStringContainsString('site-icon.png', $html);
        $this->assertStringContainsString('Mentions légales', $html);
        $this->assertGreaterThanOrEqual(2, substr_count($html, '#0B132B'));
    }
}
