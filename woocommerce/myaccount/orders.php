<?php
/**
 * Orders
 *
 * Shows orders on the account page.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/myaccount/orders.php.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 9.5.0
 */

defined('ABSPATH') || exit;

do_action('woocommerce_before_account_orders', $has_orders);

?>
<?php /* Title moved to layout header for consistency */ ?>
<?php if ($has_orders) : ?>
<div class="stats-table-wrapper">
    <table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_orders account-orders-table">
<thead>
<tr>
<?php foreach (wc_get_account_orders_columns() as $column_id => $column_name) : ?>
<th scope="col" class="woocommerce-orders-table__header woocommerce-orders-table__header-<?php echo esc_attr($column_id); ?>"><span class="nobr"><?php echo esc_html($column_name); ?></span></th>
<?php endforeach; ?>
</tr>
</thead>

<tbody>
<?php
foreach ($customer_orders->orders as $customer_order) {
$order      = wc_get_order($customer_order); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
$item_count = $order->get_item_count() - $order->get_item_count_refunded();
?>
<tr class="woocommerce-orders-table__row woocommerce-orders-table__row--status-<?php echo esc_attr($order->get_status()); ?> order">
<?php foreach (wc_get_account_orders_columns() as $column_id => $column_name) :
$is_order_number = 'order-number' === $column_id;
?>
<?php if ($is_order_number) : ?>
<th class="woocommerce-orders-table__cell woocommerce-orders-table__cell-<?php echo esc_attr($column_id); ?>" data-title="<?php echo esc_attr($column_name); ?>" scope="row">
<?php else : ?>
<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-<?php echo esc_attr($column_id); ?>" data-title="<?php echo esc_attr($column_name); ?>">
<?php endif; ?>
<?php if (has_action('woocommerce_my_account_my_orders_column_' . $column_id)) : ?>
<?php do_action('woocommerce_my_account_my_orders_column_' . $column_id, $order); ?>
<?php elseif ('order-number' === $column_id) : ?>
<a href="<?php echo esc_url($order->get_view_order_url()); ?>">
<?php echo _x('#', 'hash before order number', 'woocommerce') . $order->get_order_number(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</a>
<?php elseif ('order-date' === $column_id) : ?>
<time datetime="<?php echo esc_attr($order->get_date_created()->date('c')); ?>"><?php echo esc_html(wc_format_datetime($order->get_date_created())); ?></time>
<?php elseif ('order-status' === $column_id) : ?>
<?php echo esc_html(wc_get_order_status_name($order->get_status())); ?>
<?php elseif ('order-total' === $column_id) : ?>
<?php echo wp_kses_post($order->get_formatted_order_total()); ?>
<?php elseif ('order-actions' === $column_id) : ?>
<?php $actions = wc_get_account_orders_actions($order); ?>
<?php if (!empty($actions)) : ?>
<?php foreach ($actions as $key => $action) : ?>
<a href="<?php echo esc_url($action['url']); ?>" class="woocommerce-button button <?php echo sanitize_html_class($key); ?>"><?php echo esc_html($action['name']); ?></a>
<?php endforeach; ?>
<?php endif; ?>
<?php endif; ?>
<?php if ($is_order_number) : ?>
</th>
<?php else : ?>
</td>
<?php endif; ?>
<?php endforeach; ?>
</tr>
<?php
}
?>
</tbody>
</table>

    <?php do_action('woocommerce_after_account_orders', $has_orders); ?>
</div>

<?php else : ?>
<div class="woocommerce-message woocommerce-message--info woocommerce-MyAccount-orders--no-orders">
<?php echo esc_html__('No order has been made yet.', 'woocommerce') . ' '; ?>
<a class="woocommerce-Button button" href="<?php echo esc_url(apply_filters('woocommerce_return_to_shop_redirect', wc_get_page_permalink('shop'))); ?>">
<?php esc_html_e('Browse products', 'woocommerce'); ?>
</a>
</div>
<?php endif; ?>
