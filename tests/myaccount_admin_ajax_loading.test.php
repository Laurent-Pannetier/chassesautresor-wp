<?php
use PHPUnit\Framework\TestCase;

// Simulate WordPress functions
if (!function_exists('current_user_can')) {
    function current_user_can($cap) {
        global $current_user_capabilities;
        return in_array($cap, $current_user_capabilities, true);
    }
}
if (!function_exists('__')) {
    function __($text, $domain = null) { return $text; }
}
if (!function_exists('sanitize_key')) {
    function sanitize_key($key) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower($key)); }
}
if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($data, $status_code = 200) {
        global $last_response;
        $last_response = ['success' => true, 'data' => $data, 'status' => $status_code];
        return $last_response;
    }
}
if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($data, $status_code = 400) {
        global $last_response;
        $last_response = ['success' => false, 'data' => $data, 'status' => $status_code];
        return $last_response;
    }
}
if (!function_exists('get_stylesheet_directory')) {
    function get_stylesheet_directory() {
        return __DIR__ . '/fixtures';
    }
}

require_once __DIR__ . '/../inc/user-functions.php';

class MyAccountAdminAjaxLoadingTest extends TestCase
{
    protected function setUp(): void
    {
        global $current_user_capabilities, $last_response;
        $current_user_capabilities = [];
        $last_response = null;
        $_GET = [];
    }

    /**
     * @dataProvider sectionsProvider
     */
    public function test_admin_can_load_sections($section, $expected)
    {
        global $current_user_capabilities, $last_response;
        $current_user_capabilities = ['administrator'];
        $_GET['section'] = $section;
        ca_load_admin_section();
        $this->assertTrue($last_response['success']);
        $this->assertSame(200, $last_response['status']);
        $this->assertStringContainsString($expected, $last_response['data']['html']);
    }

    public function sectionsProvider()
    {
        return [
            ['organisateurs', 'Organisateurs'],
            ['statistiques', 'Statistiques'],
            ['outils', 'Outils'],
        ];
    }

    public function test_non_admin_cannot_load_section()
    {
        global $last_response;
        $_GET['section'] = 'organisateurs';
        ca_load_admin_section();
        $this->assertFalse($last_response['success']);
        $this->assertSame(403, $last_response['status']);
    }

    public function test_invalid_section_returns_404()
    {
        global $current_user_capabilities, $last_response;
        $current_user_capabilities = ['administrator'];
        $_GET['section'] = 'invalid';
        ca_load_admin_section();
        $this->assertFalse($last_response['success']);
        $this->assertSame(404, $last_response['status']);
    }
}
