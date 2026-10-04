<?php
/**
 * Organizer account home shell.
 *
 * @package chassesautresor
 */

defined('ABSPATH') || exit;

$hunt_id = function_exists('cat_get_managed_hunt_id_for_user')
    ? cat_get_managed_hunt_id_for_user()
    : 0;
$can_reset = function_exists('cat_is_demo_mode')
    && cat_is_demo_mode()
    && is_user_logged_in();

myaccount_render_dashboard_section(
    __('Pilotage de la chasse', 'chassesautresor-com'),
    __('Éditez vos entités et pilotez le cycle de vie depuis cet accueil.', 'chassesautresor-com')
);
?>
<div class="dashboard-grid">
    <?php
    if (function_exists('cat_render_hunt_quick_edit_card')) {
        echo cat_render_hunt_quick_edit_card($hunt_id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
    if (function_exists('cat_render_hunt_lifecycle_switch')) {
        echo cat_render_hunt_lifecycle_switch($hunt_id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
    if ($can_reset) :
        ?>
        <div class="dashboard-card">
            <div class="dashboard-card-header">
                <i class="fas fa-undo" aria-hidden="true"></i>
                <h3><?php esc_html_e('Reset stats', 'chassesautresor-com'); ?></h3>
            </div>
            <div class="dashboard-card-content">
                <button type="button" class="btn-danger" data-reset-stats>
                    <?php esc_html_e('Effacer', 'chassesautresor-com'); ?>
                </button>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
myaccount_render_dashboard_section(
    __('Statistiques', 'chassesautresor-com'),
    __('Vue détaillée de la participation à votre chasse.', 'chassesautresor-com')
);
?>
<div class="dashboard-grid dashboard-grid--wide">
    <?php
    $stats_renderer = new ChassesAuTresor\Core\Messages\AccountHuntRiddleStatisticsRenderer();
    echo $stats_renderer->render($hunt_id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    ?>
</div>
