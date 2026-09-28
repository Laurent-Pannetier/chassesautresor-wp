<?php
use PHPUnit\Framework\TestCase;

if (!function_exists('current_user_can')) {
    function current_user_can($cap) {
        return true;
    }
}
if (!function_exists('get_userdata')) {
    function get_userdata($id) {
        return (object) ['display_name' => 'Admin'];
    }
}
if (!function_exists('get_organisateur_from_user')) {
    function get_organisateur_from_user($id) {
        return null;
    }
}
if (!function_exists('get_field')) {
    function get_field($key, $id = null) {
        return '';
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
if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = null) {
        return $text;
    }
}

global $wpdb;
$wpdb = new class {
    public $prefix = '';
    public function get_results($sql, $type) {
        return [
            [
                'id' => 1,
                'user_id' => 1,
                'amount_eur' => 10,
                'points' => -1000,
                'request_date' => '2024-01-01 10:00:00',
                'request_status' => 'pending',
            ],
        ];
    }
};

require_once __DIR__ . '/../inc/PointsRepository.php';
require_once __DIR__ . '/../inc/admin-functions.php';

class AdminPaymentWidgetTest extends TestCase
{
    public function test_table_displays_select_options()
    {
        ob_start();
        afficher_tableau_paiements_admin();
        $html = ob_get_clean();
        $this->assertStringContainsString('<select name="statut">', $html);
        $this->assertStringContainsString('<option value="regle" selected>', $html);
        $this->assertStringContainsString('<option value="annule">', $html);
        $this->assertStringContainsString('<option value="refuse">', $html);
    }
}
