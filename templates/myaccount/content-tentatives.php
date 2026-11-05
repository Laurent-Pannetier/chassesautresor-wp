<?php
/**
 * Tentatives history for the player account area.
 *
 * @package chassesautresor
 */

defined('ABSPATH') || exit;

if (!myaccount_user_is_player()) {
    wp_safe_redirect(wc_get_account_endpoint_url('dashboard'));
    exit;
}

if (function_exists('ca_render_dashboard_tentatives')) {
    ca_render_dashboard_tentatives();
}
