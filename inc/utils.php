<?php
defined('ABSPATH') || exit;

/**
 * Logs debug messages conditionally.
 *
 * Use the CAT_DEBUG_VERBOSE constant or the `cat_debug_enabled` filter to
 * control output.
 *
 * @param mixed $message Message to log. Arrays/objects are exported.
 * @param bool $force    Force logging regardless of settings.
 * @return void
 */
if (!function_exists('cat_debug')) {
function cat_debug($message, bool $force = false): void {
    $enabled = defined('CAT_DEBUG_VERBOSE') && CAT_DEBUG_VERBOSE;
    $enabled = apply_filters('cat_debug_enabled', $enabled);

    if ($enabled || $force) {
        if (is_array($message) || is_object($message)) {
            $message = print_r($message, true);
        }
        error_log($message);
    }
}
}

