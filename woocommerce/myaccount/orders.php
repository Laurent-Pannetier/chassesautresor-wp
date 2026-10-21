<?php
/**
 * Orders list inside a shell card.
 *
 * @package chassesautresor
 * @see https://woocommerce.com/document/template-structure/
 * @version 9.5.0
 */

defined('ABSPATH') || exit;

do_action('woocommerce_before_account_orders', $has_orders);
?>
<div class="account-profile">
    <section class="account-panel account-orders">
        <?php if ($has_orders) : ?>
            <div class="account-orders__list stats-table-wrapper">
                <?php
                $table_class = implode(' ', [
                    'woocommerce-orders-table',
                    'woocommerce-MyAccount-orders',
                    'shop_table',
                    'shop_table_responsive',
                    'my_account_orders',
                    'account-orders-table',
                ]);
                ?>
                <table class="<?php echo esc_attr($table_class); ?>">
                    <thead>
                        <tr>
                            <?php foreach (wc_get_account_orders_columns() as $column_id => $column_name) : ?>
                                <?php
                                $header_class = 'woocommerce-orders-table__header';
                                $header_class .= ' woocommerce-orders-table__header-' . $column_id;
                                ?>
                                <th scope="col" class="<?php echo esc_attr($header_class); ?>">
                                    <span class="nobr"><?php echo esc_html($column_name); ?></span>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customer_orders->orders as $customer_order) : ?>
                            <?php
                            // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
                            $order = wc_get_order($customer_order);
                            $row_class = 'woocommerce-orders-table__row order';
                            $row_class .= ' woocommerce-orders-table__row--status-' . $order->get_status();
                            ?>
                            <tr class="<?php echo esc_attr($row_class); ?>">
                                <?php foreach (wc_get_account_orders_columns() as $column_id => $column_name) : ?>
                                    <?php
                                    $is_order_number = 'order-number' === $column_id;
                                    $cell_tag = $is_order_number ? 'th' : 'td';
                                    $cell_class = 'woocommerce-orders-table__cell';
                                    $cell_class .= ' woocommerce-orders-table__cell-' . $column_id;
                                    $column_hook = 'woocommerce_my_account_my_orders_column_' . $column_id;
                                    $cell_open = '<' . $cell_tag;
                                    $cell_close = '</' . $cell_tag . '>';
                                    ?>
                                    <?php
                                    // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
                                    echo $cell_open;
                                    // phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
                                    ?>
                                        class="<?php echo esc_attr($cell_class); ?>"
                                        data-title="<?php echo esc_attr($column_name); ?>"
                                        <?php echo $is_order_number ? 'scope="row"' : ''; ?>
                                    >
                                    <?php if (has_action($column_hook)) : ?>
                                        <?php do_action($column_hook, $order); ?>
                                    <?php elseif ('order-number' === $column_id) : ?>
                                        <a href="<?php echo esc_url($order->get_view_order_url()); ?>">
                                            <?php
                                            echo esc_html(
                                                _x('#', 'hash before order number', 'woocommerce')
                                                . $order->get_order_number()
                                            );
                                            ?>
                                        </a>
                                    <?php elseif ('order-date' === $column_id) : ?>
                                        <time datetime="<?php echo esc_attr($order->get_date_created()->date('c')); ?>">
                                            <?php echo esc_html(wc_format_datetime($order->get_date_created())); ?>
                                        </time>
                                    <?php elseif ('order-status' === $column_id) : ?>
                                        <?php echo esc_html(wc_get_order_status_name($order->get_status())); ?>
                                    <?php elseif ('order-total' === $column_id) : ?>
                                        <?php echo wp_kses_post($order->get_formatted_order_total()); ?>
                                    <?php elseif ('order-actions' === $column_id) : ?>
                                        <?php $actions = wc_get_account_orders_actions($order); ?>
                                        <?php if (!empty($actions)) : ?>
                                            <?php foreach ($actions as $key => $action) : ?>
                                                <?php
                                                $action_class = 'account-button account-button--ghost ';
                                                $action_class .= sanitize_html_class($key);
                                                ?>
                                                <a
                                                    href="<?php echo esc_url($action['url']); ?>"
                                                    class="<?php echo esc_attr($action_class); ?>"
                                                >
                                                    <?php echo esc_html($action['name']); ?>
                                                </a>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <?php
                                    // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
                                    echo $cell_close;
                                    // phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
                                    ?>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php do_action('woocommerce_after_account_orders', $has_orders); ?>
        <?php else : ?>
            <p class="account-orders__empty">
                <?php esc_html_e('Aucune commande pour le moment.', 'chassesautresor-com'); ?>
            </p>
        <?php endif; ?>
    </section>
</div>
