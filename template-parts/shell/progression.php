<?php
/**
 * Progression page: statistics moved off the home pages.
 *
 * Players see their own hunt progress. Organizers see participation,
 * attempt yield and the step drop-off. The figures stay in the Core renderer.
 *
 * @package chassesautresor
 */

defined('ABSPATH') || exit;

$logged_in = function_exists('is_user_logged_in') && is_user_logged_in();
if (!$logged_in) {
    echo '<p class="app-placeholder__note">';
    esc_html_e('Connectez-vous pour consulter votre progression.', 'chassesautresor-com');
    echo '</p>';
    return;
}

$is_player = function_exists('myaccount_user_is_player') && myaccount_user_is_player();
if ($is_player) {
    if (function_exists('cta_shell_render_team_stats')) {
        cta_shell_render_team_stats();
    }
    return;
}

$hunt_id = function_exists('cat_get_managed_hunt_id_for_user')
    ? (int) cat_get_managed_hunt_id_for_user()
    : 0;
$is_admin = function_exists('cta_shell_user_is_administrator') && cta_shell_user_is_administrator();
$intro = $is_admin
    ? __('Indicateurs par énigme pour suivre la participation.', 'chassesautresor-com')
    : __('Vue détaillée de la participation à votre chasse.', 'chassesautresor-com');
$period = 'total';
if (isset($_GET['periode'])) {
    $requested_period = (string) $_GET['periode'];
    if (function_exists('wp_unslash')) {
        $requested_period = (string) wp_unslash($requested_period);
    }
    $requested_period = function_exists('sanitize_key')
        ? sanitize_key($requested_period)
        : strtolower($requested_period);
    if (in_array($requested_period, ['jour', 'semaine', 'mois', 'total'], true)) {
        $period = $requested_period;
    }
}
$page_url = function_exists('cta_shell_location_url') ? cta_shell_location_url('progression') : '';
?>
<p class="progression-intro"><?php echo esc_html($intro); ?></p>
<?php
$dashboard_provider = static function (int $hunt_id, string $period): array {
    return (new ChassesAuTresor\Core\Progress\OrganizerProgressionApplicationService())
        ->build($hunt_id, $period);
};
$stats_renderer = new ChassesAuTresor\Core\Messages\AccountHuntRiddleStatisticsRenderer(
    null,
    $dashboard_provider
);
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo $stats_renderer->render($hunt_id, $period, $page_url);
