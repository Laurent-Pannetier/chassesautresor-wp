<?php
/**
 * Dynamic content for the "Organisateurs" section.
 *
 * @package chassesautresor
 */

defined('ABSPATH') || exit;

if (!current_user_can('administrator')) {
    wp_redirect(home_url('/mon-compte/'));
    exit;
}

$page = isset($_GET['page']) ? absint(wp_unslash($_GET['page'])) : 1;
$renderer = new ChassesAuTresor\Core\Messages\AccountOrganizersRenderer();
echo $renderer->render($page); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
