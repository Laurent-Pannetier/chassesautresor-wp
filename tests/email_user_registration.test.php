<?php
use PHPUnit\Framework\TestCase;

if (!function_exists('wp_kses_post')) {
    function wp_kses_post($content) {
        return $content;
    }
}
if (!function_exists('esc_html')) {
    function esc_html($text) {
        return $text;
    }
}
if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return $text;
    }
}
if (!function_exists('esc_url')) {
    function esc_url($text) {
        return $text;
    }
}
if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = null) {
        return $text;
    }
}
if (!function_exists('add_filter')) {
    function add_filter($tag, $function_to_add, $priority = 10, $accepted_args = 1) {}
}
if (!function_exists('get_theme_mod')) {
    function get_theme_mod($name) {
        return null;
    }
}
if (!function_exists('wp_get_attachment_image_url')) {
    function wp_get_attachment_image_url($id, $size = 'full') {
        return '';
    }
}
if (!function_exists('get_theme_file_uri')) {
    function get_theme_file_uri($path) {
        return 'https://example.com/' . ltrim($path, '/');
    }
}
if (!function_exists('get_bloginfo')) {
    function get_bloginfo($show = '', $filter = 'raw') {
        return 'Site';
    }
}
if (!function_exists('get_password_reset_key')) {
    function get_password_reset_key($user) {
        return 'abc123';
    }
}
if (!function_exists('network_site_url')) {
    function network_site_url($path = '', $scheme = null) {
        return 'https://example.com/' . ltrim($path, '/');
    }
}

require_once __DIR__ . '/../inc/emails/template.php';
require_once __DIR__ . '/../inc/emails/user-registration.php';

class EmailUserRegistrationTest extends TestCase
{
    public function test_custom_registration_email(): void
    {
        $user = (object) [
            'user_login'   => 'test4',
            'display_name' => 'test4',
            'user_email'   => 'test4@example.com',
        ];

        $email = cta_new_user_notification_email([], $user, 'chassesautresor.com');

        $this->assertSame('Bienvenue chez chassesautresor.com', $email['subject']);
        $this->assertStringContainsString('Configurer mon mot de passe', $email['message']);
        $this->assertStringContainsString('abc123', $email['message']);
        $this->assertStringContainsString('margin-top:20px', $email['message']);
        $this->assertIsArray($email['headers']);
        $this->assertContains('Content-Type: text/html; charset=UTF-8', $email['headers']);
    }

    public function test_registration_email_handles_string_headers(): void
    {
        $user = (object) [
            'user_login'   => 'test4',
            'display_name' => 'test4',
            'user_email'   => 'test4@example.com',
        ];

        $email = [ 'headers' => "From: admin@example.com" ];
        $email = cta_new_user_notification_email($email, $user, 'chassesautresor.com');

        $this->assertContains('From: admin@example.com', $email['headers']);
        $this->assertContains('Content-Type: text/html; charset=UTF-8', $email['headers']);
    }
}
