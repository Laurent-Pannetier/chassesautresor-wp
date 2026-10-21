<?php
/**
 * Account profile: identity, password, addresses and recent orders.
 *
 * @package chassesautresor
 */

defined('ABSPATH') || exit;

$user = wp_get_current_user();

if (function_exists('woocommerce_output_all_notices')) {
    woocommerce_output_all_notices();
}

if (function_exists('wc_get_template')) {
    wc_get_template('myaccount/form-edit-account.php', ['user' => $user]);
    return;
}

include get_stylesheet_directory() . '/woocommerce/myaccount/form-edit-account.php';
