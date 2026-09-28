<?php
/**
 * Dashboard content for administrator role.
 *
 * @package chassesautresor
 */

defined('ABSPATH') || exit;

if (function_exists('woocommerce_account_content')) {
    woocommerce_account_content();
}

get_template_part('template-parts/myaccount/dashboard-admin');
