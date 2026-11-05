<?php
/**
 * Dynamic content for the "Statistiques" section.
 *
 * @package chassesautresor
 */

defined('ABSPATH') || exit;

if (!current_user_can('administrator')) {
    wp_redirect(home_url('/mon-compte/'));
    exit;
}

$renderer = new ChassesAuTresor\Core\Messages\AccountStatisticsRenderer();
echo $renderer->render(get_current_user_id()); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
