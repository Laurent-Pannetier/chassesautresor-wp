<?php
/**
 * Legacy compatibility loader for core site-password protection.
 *
 * @deprecated Site access protection is owned by chassesautresor-core.
 */

defined('ABSPATH') || exit;

require_once dirname(__DIR__, 3) . '/plugins/chassesautresor-core/src/Security/site-password.php';
