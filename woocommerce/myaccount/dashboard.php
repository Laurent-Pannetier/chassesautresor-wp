<?php
/**
 * My Account Dashboard
 *
 * Custom template without default WooCommerce greeting or description.
 *
 * @package WooCommerce\\Templates
 * @version 4.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

do_action('woocommerce_account_dashboard');

do_action('woocommerce_before_my_account');

do_action('woocommerce_after_my_account');

