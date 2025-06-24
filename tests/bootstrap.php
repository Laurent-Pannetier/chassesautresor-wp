<?php
// Minimal bootstrap for testing access helper
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/..');
}

// Dummy WordPress hook functions
if (!function_exists('add_action')) {
    function add_action(...$args) {}
}
if (!function_exists('add_filter')) {
    function add_filter(...$args) {}
}
if (!function_exists('add_rewrite_rule')) {
    function add_rewrite_rule(...$args) {}
}

// Minimal WP user functions for tests
$mock_users = [];
$current_user_id = 0;

if (!function_exists('get_current_user_id')) {
    function get_current_user_id() {
        global $current_user_id;
        return $current_user_id;
    }
}
if (!function_exists('get_userdata')) {
    function get_userdata($id) {
        global $mock_users;
        return $mock_users[$id] ?? null;
    }
}
if (!function_exists('get_user_by')) {
    function get_user_by($field, $id) {
        return get_userdata($id);
    }
}

require_once __DIR__ . '/../inc/constants.php';
require_once __DIR__ . '/../inc/access-functions.php';
