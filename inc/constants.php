<?php
defined( 'ABSPATH' ) || exit;

const ROLE_ORGANISATEUR = 'organisateur';
const ROLE_ORGANISATEUR_CREATION = 'organisateur_creation';

// --------------------------------------------------
// 🔧 Debug / Logging
// --------------------------------------------------
// Change CAT_DEBUG_VERBOSE to true to enable verbose logging.
if (!defined('CAT_DEBUG_VERBOSE')) {
    define('CAT_DEBUG_VERBOSE', false);
}
