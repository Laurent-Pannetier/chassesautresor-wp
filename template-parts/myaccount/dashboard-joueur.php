<?php
/**
 * Player account home shell.
 *
 * @package chassesautresor
 */

defined('ABSPATH') || exit;

if (function_exists('cat_is_single_hunt_mode') && cat_is_single_hunt_mode()) {
    if (function_exists('cat_render_demo_player_progress_reset')) {
        cat_render_demo_player_progress_reset();
    }
    if (function_exists('cta_shell_render_hunt_banner')) {
        cta_shell_render_hunt_banner();
    }
    if (function_exists('cat_render_single_hunt_home_enigmas')) {
        cat_render_single_hunt_home_enigmas();
    }
    return;
} elseif (function_exists('ca_render_dashboard_engaged_hunts')) {
    ca_render_dashboard_engaged_hunts();
}
