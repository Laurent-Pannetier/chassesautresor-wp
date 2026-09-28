<?php
/**
 * Dynamic content for the "Statistiques" section.
 *
 * @package chassesautresor
 */

defined('ABSPATH') || exit;

if (!current_user_can('administrator')) {
    wp_redirect(home_url('/mon-compte/'));
    exit;
}

$user_id = get_current_user_id();
$wins    = compter_chasses_gagnees($user_id);

global $wpdb;
$repo               = new PointsRepository($wpdb);
$used_points        = $repo->getTotalPointsUsed();
$circulation_points = $repo->getTotalPointsInCirculation();
?>
<section>
    <h1 class="mb-4 text-xl font-semibold"><?php esc_html_e('Statistiques', 'chassesautresor'); ?></h1>
    <div class="dashboard-grid stats-cards myaccount-points-cards">
        <div class="dashboard-card" data-stat="points-used">
            <i class="fa-solid fa-hand-holding-dollar" aria-hidden="true"></i>
            <h3><?php esc_html_e('Points utilisés', 'chassesautresor-com'); ?></h3>
            <p class="stat-value"><?php echo esc_html($used_points); ?></p>
        </div>
        <div class="dashboard-card" data-stat="points-bought">
            <i class="fa-solid fa-cart-shopping" aria-hidden="true"></i>
            <h3><?php esc_html_e('Points achetés', 'chassesautresor-com'); ?></h3>
            <p class="stat-value"><?php esc_html_e('À implémenter', 'chassesautresor-com'); ?></p>
        </div>
        <div class="dashboard-card" data-stat="points-circulation">
            <i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i>
            <h3><?php esc_html_e('Points en circulation', 'chassesautresor-com'); ?></h3>
            <p class="stat-value"><?php echo esc_html($circulation_points); ?></p>
        </div>
    </div>
    <p><?php echo esc_html(sprintf(__('Chasses gagnées : %d', 'chassesautresor'), $wins)); ?></p>
</section>
