<?php
/**
 * Authenticated notification center.
 *
 * @package chassesautresor
 */

defined('ABSPATH') || exit;

if (!is_user_logged_in()) {
    $login = wp_login_url(home_url('/notifications/'));
    echo '<p class="notifications-center__empty">';
    echo esc_html__('Connectez-vous pour consulter vos notifications.', 'chassesautresor-com');
    echo ' <a href="' . esc_url($login) . '">' . esc_html__('Connexion', 'chassesautresor-com') . '</a>';
    echo '</p>';
    return;
}

if (
    !function_exists('cat_user_notifications')
    || !class_exists(ChassesAuTresor\Core\Notifications\NotificationsPageRenderer::class)
) {
    echo '<p class="notifications-center__empty">';
    echo esc_html__('Les notifications ne sont pas disponibles pour le moment.', 'chassesautresor-com');
    echo '</p>';
    return;
}

$user_id = get_current_user_id();
$per_page = 20;
$page = isset($_GET['notification_page'])
    ? max(1, absint(wp_unslash((string) $_GET['notification_page'])))
    : 1;
$total = function_exists('cat_user_notification_count') ? cat_user_notification_count($user_id) : 0;
$total_pages = max(1, (int) ceil($total / $per_page));
$page = min($page, $total_pages);

echo (new ChassesAuTresor\Core\Notifications\NotificationsPageRenderer())->render(
    cat_user_notifications($user_id, $per_page, ($page - 1) * $per_page),
    $page,
    $total_pages,
    home_url('/notifications/'),
    cat_notification_unread_count($user_id) > 0
); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
