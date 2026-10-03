<?php
/**
 * Front page router for the configured public site experience.
 */

defined('ABSPATH') || exit;

if (!function_exists('cat_is_single_hunt_mode') || !cat_is_single_hunt_mode()) {
    get_template_part('template-parts/home/platform');
    return;
}

get_template_part('template-parts/home/single-hunt');
