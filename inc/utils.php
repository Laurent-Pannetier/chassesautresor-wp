<?php
defined('ABSPATH') || exit;

/**
 * Logs debug messages conditionally.
 *
 * Output is controlled by the `WP_DEBUG` constant or the `cat_debug_enabled`
 * filter.
 *
 * @param mixed $message Message to log. Arrays/objects are exported.
 * @param bool $force    Force logging regardless of settings.
 * @return void
 */
function cat_debug($message, bool $force = false): void
{
    $enabled = defined('WP_DEBUG') && WP_DEBUG;
    $enabled = apply_filters('cat_debug_enabled', $enabled);

    if ($enabled || $force) {
        if (is_array($message) || is_object($message)) {
            $message = print_r($message, true);
        }
        error_log($message);
    }
}
