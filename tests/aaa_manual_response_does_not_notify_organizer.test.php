<?php
use PHPUnit\Framework\TestCase;

if (!function_exists('is_user_logged_in')) {
    function is_user_logged_in() { return true; }
}
if (!function_exists('get_current_user_id')) {
    function get_current_user_id() { return 1; }
}
if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($str) { return $str; }
}
if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action) { return true; }
}
if (!function_exists('utilisateur_peut_repondre_manuelle')) {
    function utilisateur_peut_repondre_manuelle($user_id, $enigme_id) { return true; }
}
if (!function_exists('get_field')) {
    function get_field($key, $id = null, $format_value = true) {
        if ('enigme_tentative_cout_points' === $key) {
            return 0;
        }
        if ('utilisateurs_associes' === $key) {
            return [2];
        }
        return '';
    }
}
if (!function_exists('get_user_points')) {
    function get_user_points($user_id) { return 10; }
}
if (!function_exists('deduire_points_utilisateur')) {
    function deduire_points_utilisateur(...$args) {}
}
if (!function_exists('inserer_tentative')) {
    function inserer_tentative($user_id, $enigme_id, $reponse) { return 'abc123'; }
}
if (!function_exists('current_time')) {
    function current_time($type) { return time(); }
}
if (!function_exists('wp_date')) {
    function wp_date($format, $timestamp) { return date($format, $timestamp); }
}
if (!function_exists('enigme_mettre_a_jour_statut_utilisateur')) {
    function enigme_mettre_a_jour_statut_utilisateur(...$args) {}
}
if (!function_exists('get_the_title')) {
    function get_the_title($id) { return 'Énigme'; }
}
if (!function_exists('esc_url')) {
    function esc_url($url) { return $url; }
}
if (!function_exists('esc_html')) {
    function esc_html($text) { return $text; }
}
if (!function_exists('get_permalink')) {
    function get_permalink($id) { return 'https://example.com/enigme'; }
}
if (!function_exists('recuperer_id_chasse_associee')) {
    function recuperer_id_chasse_associee($enigme_id) { return 11; }
}
if (!function_exists('get_organisateur_from_chasse')) {
    function get_organisateur_from_chasse($chasse_id) { return 99; }
}
if (!function_exists('envoyer_mail_reponse_manuelle')) {
    function envoyer_mail_reponse_manuelle(...$args) {}
}
if (!function_exists('myaccount_add_persistent_message')) {
    function myaccount_add_persistent_message($user_id, $key, $message, $type = 'info'): void {
        $messages = get_user_meta($user_id, '_myaccount_messages', true);
        if (!is_array($messages)) {
            $messages = [];
        }
        $messages[$key] = ['text' => $message, 'type' => $type];
        update_user_meta($user_id, '_myaccount_messages', $messages);
    }
}
if (!function_exists('get_user_meta')) {
    function get_user_meta($user_id, $key, $single = false) {
        return $GLOBALS['user_meta'][$user_id][$key] ?? [];
    }
}
if (!function_exists('update_user_meta')) {
    function update_user_meta($user_id, $key, $value) {
        $GLOBALS['user_meta'][$user_id][$key] = $value;
        return true;
    }
}
if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($data) { $GLOBALS['json_success'] = $data; }
}
if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($data) { throw new Exception('error'); }
}

class WpdbStub {
    public $prefix = 'wp_';
    public $insert_id = 1;
    public function get_var($query) { return null; }
    public function prepare($query, ...$args) { return $query; }
}

global $wpdb;
$wpdb = new WpdbStub();

require_once __DIR__ . '/../inc/enigme/reponses.php';

class ManualResponseDoesNotNotifyOrganizerTest extends TestCase
{
    public function test_organizer_does_not_receive_persistent_message(): void
    {
        $_POST = [
            'enigme_id' => 5,
            'reponse_manuelle' => 'foo',
            'reponse_manuelle_nonce' => 'nonce',
        ];
        soumettre_reponse_manuelle();

        $organizer_messages = get_user_meta(2, '_myaccount_messages', true);
        $this->assertArrayNotHasKey('tentative_abc123', $organizer_messages);

        $player_messages = get_user_meta(1, '_myaccount_messages', true);
        $this->assertArrayHasKey('tentative_abc123', $player_messages);
        $this->assertSame('<a href="https://example.com/enigme">Énigme</a>', $player_messages['tentative_abc123']['text']);
    }
}
