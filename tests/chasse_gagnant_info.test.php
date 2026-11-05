<?php
use PHPUnit\Framework\TestCase;

class ChasseGagnantInfoTest extends TestCase
{
    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_displays_info_for_finished_chasse(): void
    {
        if (!defined('ABSPATH')) {
            define('ABSPATH', __DIR__);
        }
        if (!defined('DAY_IN_SECONDS')) {
            define('DAY_IN_SECONDS', 86400);
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
        if (!function_exists('formater_date')) {
            function formater_date($date) { return $date; }
        }
        if (!function_exists('formater_date_heure')) {
            function formater_date_heure($date) { return formater_date($date); }
        }
        if (!function_exists('current_time')) {
            function current_time($type) { return 0; }
        }
        if (!function_exists('get_current_user_id')) {
            function get_current_user_id() { return 1; }
        }
        if (!function_exists('utilisateur_peut_modifier_post')) {
            function utilisateur_peut_modifier_post($id) { return false; }
        }
        if (!function_exists('chasse_est_complet')) {
            function chasse_est_complet($id) { return false; }
        }
        if (!function_exists('get_organisateur_from_chasse')) {
            function get_organisateur_from_chasse($id) { return null; }
        }
        if (!function_exists('get_the_author')) {
            function get_the_author() { return 'Auteur'; }
        }
        if (!function_exists('current_user_can')) {
            function current_user_can($cap) { return false; }
        }
        if (!function_exists('get_field')) {
            function get_field($field, $post_id) {
                global $fields;
                return $fields[$post_id][$field] ?? null;
            }
        }
        if (!function_exists('render_liens_publics')) {
            function render_liens_publics($liens, $type, $opts = []) { return ''; }
        }
        if (!function_exists('get_svg_icon')) {
            function get_svg_icon($name) { return ''; }
        }
        if (!function_exists('esc_html__')) {
            function esc_html__($text, $domain = null) { return $text; }
        }
        if (!function_exists('__')) {
            function __($text, $domain = null) { return $text; }
        }
        if (!function_exists('_n')) {
            function _n($single, $plural, $number, $domain = null) {
                return $number === 1 ? $single : $plural;
            }
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
        if (!function_exists('esc_html_e')) {
            function esc_html_e($text, $domain = null) { echo $text; }
        }
        if (!function_exists('esc_attr__')) {
            function esc_attr__($text, $domain = null) { return $text; }
        }
        if (!function_exists('sanitize_text_field')) {
            function sanitize_text_field($text) { return $text; }
        }
        if (!function_exists('esc_url_raw')) {
            function esc_url_raw($url) { return $url; }
        }
        if (!function_exists('wp_kses_post')) {
            function wp_kses_post($content) { return $content; }
        }
        if (!function_exists('get_template_part')) {
            function get_template_part($slug, $name = null, $args = []) { return ''; }
        }
        if (!function_exists('wp_get_attachment_image_url')) {
            function wp_get_attachment_image_url($id, $size) { return ''; }
        }
        if (!function_exists('wp_get_attachment_image_src')) {
            function wp_get_attachment_image_src($id, $size) { return ['']; }
        }
        if (!function_exists('number_format_i18n')) {
            function number_format_i18n($number, $decimals = 0) {
                return number_format($number, $decimals);
            }
        }

        global $fields;
        $fields = [
            123 => [],
        ];

        $infos_chasse = [
            'champs' => [
                'lot' => '',
                'titre_recompense' => '',
                'valeur_recompense' => '',
                'date_debut' => '',
                'date_fin' => '',
                'illimitee' => false,
                'nb_max' => 0,
                'cout_points' => 0,
                'date_decouverte' => '2024-01-01 00:00:00',
                'gagnants' => 'John Doe',
                'mode_fin' => 'automatique',
                'current_stored_statut' => '',
            ],
            'image_raw' => '',
            'image_id' => null,
            'image_url' => '',
            'liens' => [],
            'enigmes_associees' => [],
            'total_enigmes' => 0,
            'nb_joueurs' => 0,
            'nb_enigmes_payantes' => 0,
            'top_avances' => ['nb' => 0, 'enigmes' => 0],
            'statut' => 'termine',
            'statut_validation' => 'valide',
            'cta_data' => ['cta_message' => '', 'cta_html' => '', 'type' => ''],
            'description' => '',
        ];

        $args = [
            'chasse_id' => 123,
            'infos_chasse' => $infos_chasse,
        ];

        ob_start();
        include __DIR__ . '/../template-parts/chasse/chasse-affichage-complet.php';
        $html = ob_get_clean();

        $this->assertStringContainsString('Chasse gagnée le 2024-01-01 00:00:00 par John Doe', $html);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_displays_link_when_solutions_available(): void
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
        if (!function_exists('formater_date')) {
            function formater_date($date) { return $date; }
        }
        if (!function_exists('formater_date_heure')) {
            function formater_date_heure($date) { return formater_date($date); }
        }
        if (!function_exists('current_time')) {
            function current_time($type) { return 0; }
        }
        if (!function_exists('get_current_user_id')) {
            function get_current_user_id() { return 1; }
        }
        if (!function_exists('utilisateur_peut_modifier_post')) {
            function utilisateur_peut_modifier_post($id) { return false; }
        }
        if (!function_exists('chasse_est_complet')) {
            function chasse_est_complet($id) { return false; }
        }
        if (!function_exists('get_organisateur_from_chasse')) {
            function get_organisateur_from_chasse($id) { return null; }
        }
        if (!function_exists('get_the_author')) {
            function get_the_author() { return 'Auteur'; }
        }
        if (!function_exists('current_user_can')) {
            function current_user_can($cap) { return false; }
        }
        if (!function_exists('get_field')) {
            function get_field($field, $post_id) {
                global $fields;
                return $fields[$post_id][$field] ?? null;
            }
        }
        if (!function_exists('render_liens_publics')) {
            function render_liens_publics($liens, $type, $opts = []) { return ''; }
        }
        if (!function_exists('get_svg_icon')) {
            function get_svg_icon($name) { return ''; }
        }
        if (!function_exists('esc_html__')) {
            function esc_html__($text, $domain = null) { return $text; }
        }
        if (!function_exists('__')) {
            function __($text, $domain = null) { return $text; }
        }
        if (!function_exists('_n')) {
            function _n($single, $plural, $number, $domain = null) {
                return $number === 1 ? $single : $plural;
            }
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
        if (!function_exists('esc_html_e')) {
            function esc_html_e($text, $domain = null) { echo $text; }
        }
        if (!function_exists('esc_attr__')) {
            function esc_attr__($text, $domain = null) { return $text; }
        }
        if (!function_exists('sanitize_text_field')) {
            function sanitize_text_field($text) { return $text; }
        }
        if (!function_exists('esc_url_raw')) {
            function esc_url_raw($url) { return $url; }
        }
        if (!function_exists('wp_kses_post')) {
            function wp_kses_post($content) { return $content; }
        }
        if (!function_exists('get_template_part')) {
            function get_template_part($slug, $name = null, $args = []) { return ''; }
        }
        if (!function_exists('wp_get_attachment_image_url')) {
            function wp_get_attachment_image_url($id, $size) { return ''; }
        }
        if (!function_exists('wp_get_attachment_image_src')) {
            function wp_get_attachment_image_src($id, $size) { return ['']; }
        }
        if (!function_exists('number_format_i18n')) {
            function number_format_i18n($number, $decimals = 0) {
                return number_format($number, $decimals);
            }
        }
        if (!function_exists('solution_chasse_peut_etre_affichee')) {
            function solution_chasse_peut_etre_affichee($id) { return true; }
        }
        if (!function_exists('utilisateur_peut_voir_solution_chasse')) {
            function utilisateur_peut_voir_solution_chasse($id, $user) { return true; }
        }
        if (!function_exists('solution_recuperer_par_objet')) {
            function solution_recuperer_par_objet($id, $type) { return (object) ['ID' => 1]; }
        }
        if (!function_exists('solution_contenu_html')) {
            function solution_contenu_html($sol) { return '<p>solution</p>'; }
        }

        global $fields;
        $fields = [
            123 => [],
        ];

        $infos_chasse = [
            'champs' => [
                'lot' => '',
                'titre_recompense' => '',
                'valeur_recompense' => '',
                'date_debut' => '',
                'date_fin' => '',
                'illimitee' => false,
                'nb_max' => 0,
                'cout_points' => 0,
                'date_decouverte' => '2024-01-01 00:00:00',
                'gagnants' => 'John Doe',
                'mode_fin' => 'automatique',
                'current_stored_statut' => '',
            ],
            'image_raw' => '',
            'image_id' => null,
            'image_url' => '',
            'liens' => [],
            'enigmes_associees' => [],
            'total_enigmes' => 0,
            'nb_joueurs' => 0,
            'nb_enigmes_payantes' => 0,
            'top_avances' => ['nb' => 0, 'enigmes' => 0],
            'statut' => 'termine',
            'statut_validation' => 'valide',
            'cta_data' => ['cta_message' => '', 'cta_html' => '', 'type' => ''],
            'description' => '',
        ];

        $args = [
            'chasse_id' => 123,
            'infos_chasse' => $infos_chasse,
        ];

        ob_start();
        include __DIR__ . '/../template-parts/chasse/chasse-affichage-complet.php';
        $html = ob_get_clean();

        $this->assertStringContainsString('#chasse-solutions', $html);
    }
}
