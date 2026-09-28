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

require_once dirname(__DIR__, 4) . '/vendor/autoload.php';
require_once __DIR__ . '/../inc/emails/template.php';
require_once __DIR__ . '/../inc/emails/woocommerce.php';

class WooCommerceEmailTemplateTest extends TestCase
{
    public function test_wc_email_content_is_wrapped(): void
    {
        cta_wc_store_email_heading('Titre');
        $html = cta_wc_render_email_content('<p>Bonjour</p>');

        $this->assertStringContainsString('Titre', $html);
        $this->assertStringContainsString('<p>Bonjour</p>', $html);
        $this->assertStringContainsString('<header', $html);
        $this->assertStringContainsString('<footer', $html);
    }
}
