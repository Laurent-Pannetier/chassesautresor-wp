<?php
/**
 * Cards displayed on the dashboard for organizer roles.
 *
 * @package chassesautresor
 */

defined('ABSPATH') || exit;

$orders_output = $args['orders_output'] ?? '';
?>

<div class="dashboard-grid">
    <?php if (!empty($orders_output)) : ?>
    <a href="<?php echo esc_url(wc_get_account_endpoint_url('orders')); ?>" class="dashboard-card">
        <div class="dashboard-card-header">
            <i class="fas fa-shopping-cart"></i>
            <h4><?php esc_html_e('Mes Commandes', 'chassesautresor'); ?></h4>
        </div>
        <div class="dashboard-card-content">
            <?php echo $orders_output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>
    </a>
    <?php endif; ?>
</div>

