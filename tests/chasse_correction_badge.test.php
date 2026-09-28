<?php
use PHPUnit\Framework\TestCase;

class ChasseCorrectionBadgeTest extends TestCase
{
    /**
     * Simule une chasse en phase de correction et vérifie que
     * l'organisateur associé voit le badge "correction" sur la carte.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_associated_organizer_sees_correction_badge(): void
    {
        if (!defined('ABSPATH')) {
            define('ABSPATH', __DIR__);
        }
        if (!function_exists('get_post_type')) {
            function get_post_type($id) { return 'chasse'; }
        }
        if (!function_exists('get_permalink')) {
            function get_permalink($id) { return 'https://example.com/chasse/' . $id; }
        }
        if (!function_exists('get_the_title')) {
            function get_the_title($id) { return 'Ma Chasse'; }
        }
        if (!function_exists('verifier_ou_recalculer_statut_chasse')) {
            function verifier_ou_recalculer_statut_chasse($id) {}
        }
        if (!function_exists('chasse_get_champs')) {
            function chasse_get_champs($id) {
                return [
                    'titre_recompense' => '',
                    'valeur_recompense' => '',
                    'cout_points' => 0,
                    'date_debut' => null,
                    'date_fin' => null,
                    'illimitee' => false,
                ];
            }
        }
        if (!function_exists('formater_date')) {
            function formater_date($date) { return (string) $date; }
        }
        if (!function_exists('compter_joueurs_engages_chasse')) {
            function compter_joueurs_engages_chasse($id) { return 0; }
        }
        if (!function_exists('formater_nombre_joueurs')) {
            function formater_nombre_joueurs($nb) { return (string) $nb; }
        }
        if (!function_exists('wp_strip_all_tags')) {
            function wp_strip_all_tags($text) { return $text; }
        }
        if (!function_exists('wp_trim_words')) {
            function wp_trim_words($text, $num = 55, $more = '...') { return $text; }
        }
        if (!function_exists('get_field')) {
            function get_field($field, $post_id) {
                global $fields;
                return $fields[$post_id][$field] ?? null;
            }
        }
        if (!function_exists('wp_get_attachment_image_url')) {
            function wp_get_attachment_image_url($id, $size) { return ''; }
        }
        if (!function_exists('get_the_post_thumbnail_url')) {
            function get_the_post_thumbnail_url($id, $size) { return ''; }
        }
        if (!function_exists('get_svg_icon')) {
            function get_svg_icon($name) { return ''; }
        }
        if (!function_exists('esc_html__')) {
            function esc_html__($text, $domain = null) { return $text; }
        }
        if (!function_exists('esc_html')) {
            function esc_html($text) { return $text; }
        }
        if (!function_exists('esc_attr')) {
            function esc_attr($text) { return $text; }
        }
        if (!function_exists('esc_url')) {
            function esc_url($text) { return $text; }
        }
        if (!function_exists('__')) {
            function __($text, $domain = null) { return $text; }
        }
        if (!function_exists('_n')) {
            function _n($single, $plural, $number, $domain = null) {
                return $number === 1 ? $single : $plural;
            }
        }
        if (!function_exists('esc_attr__')) {
            function esc_attr__($text, $domain = null) { return $text; }
        }
        if (!function_exists('wp_kses_post')) {
            function wp_kses_post($content) { return $content; }
        }

        global $fields, $wpdb;
        $wpdb = new class {
            public string $prefix = 'wp_';
            public function prepare($query, ...$args) { return $query; }
            public function get_var($query) { return null; }
        };

        $fields = [
            123 => [
                'chasse_principale_description' => '',
                'chasse_principale_image' => null,
                'chasse_principale_liens' => [],
                'chasse_cache_statut' => 'revision',
                'chasse_cache_statut_validation' => 'correction',
            ],
        ];

        require_once __DIR__ . '/../inc/chasse-functions.php';

        $args = ['chasse_id' => 123];
        ob_start();
        include __DIR__ . '/../template-parts/chasse/chasse-card.php';
        $html = ob_get_clean();

        $this->assertStringContainsString('badge-statut statut-revision', $html);
        $this->assertStringContainsString('correction', $html);
    }
}
