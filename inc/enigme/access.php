<?php
/**
 * Legacy compatibility loader for the core riddle access controller.
 *
 * @deprecated Riddle access redirects are owned by chassesautresor-core.
 */

defined('ABSPATH') || exit;

require_once dirname(__DIR__, 4) . '/plugins/chassesautresor-core/src/Content/RiddleAccessRedirectHandler.php';
