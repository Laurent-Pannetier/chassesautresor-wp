<?php
/**
 * Legacy compatibility loader for the core email implementation.
 *
 * @deprecated Email behavior is owned by chassesautresor-core.
 */

defined('ABSPATH') || exit;

require_once dirname(__DIR__, 4) . '/plugins/chassesautresor-core/src/Email/user-registration.php';
