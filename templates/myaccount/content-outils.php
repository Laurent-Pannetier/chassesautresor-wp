<?php
/**
 * Dynamic content for the "Outils" section.
 *
 * @package chassesautresor
 */

defined('ABSPATH') || exit;

if (!current_user_can('administrator')) {
    wp_redirect(home_url('/mon-compte/'));
    exit;
}

$renderer = new ChassesAuTresor\Core\Messages\AccountToolsRenderer();
echo $renderer->render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
