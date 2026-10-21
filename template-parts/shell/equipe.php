<?php
/**
 * Team page: create, join, leave, and staff exclusion.
 *
 * @package chassesautresor
 */

defined('ABSPATH') || exit;

if (!function_exists('is_user_logged_in') || !is_user_logged_in()) {
    $login = function_exists('wp_login_url') && function_exists('home_url')
        ? wp_login_url(home_url('/equipe/'))
        : (function_exists('wp_login_url') ? wp_login_url() : '/wp-login.php');
    echo '<p class="equipe-note">';
    echo esc_html__('Connectez-vous pour créer ou rejoindre une équipe.', 'chassesautresor-com');
    echo ' <a href="' . esc_url($login) . '">' . esc_html__('Connexion', 'chassesautresor-com') . '</a>';
    echo '</p>';
    return;
}

if (!function_exists('cat_team_service') || !class_exists(ChassesAuTresor\Core\Teams\TeamPageRenderer::class)) {
    echo '<p class="equipe-note">';
    echo esc_html__('Les équipes ne sont pas disponibles pour le moment.', 'chassesautresor-com');
    echo '</p>';
    return;
}

$notice = isset($_GET['equipe_notice']) ? sanitize_key(wp_unslash((string) $_GET['equipe_notice'])) : '';
$action = function_exists('home_url') ? home_url('/equipe/') : '/equipe/';
$icon = function_exists('cta_shell_icon') ? cta_shell_icon('team') : '';
$imageUrl = static function (int $id): string {
    if ($id <= 0 || !function_exists('wp_get_attachment_image_url')) {
        return '';
    }
    $url = wp_get_attachment_image_url($id, 'medium');

    return is_string($url) ? $url : '';
};

echo (new ChassesAuTresor\Core\Teams\TeamPageRenderer())->render(
    cat_team_service()->describe(cat_team_actor()),
    $notice,
    $action,
    $imageUrl,
    $icon
);
?>
<script>
(function () {
    document.querySelectorAll('[data-equipe-copy]').forEach(function (button) {
        button.addEventListener('click', function () {
            var code = button.getAttribute('data-equipe-copy') || '';
            var done = function () {
                var previous = button.textContent;
                button.textContent = button.getAttribute('data-copied-label') || previous;
                window.setTimeout(function () {
                    button.textContent = previous;
                }, 1600);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(code).then(done);
                return;
            }
            done();
        });
    });
    document.querySelectorAll('form[data-equipe-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            var message = form.getAttribute('data-equipe-confirm') || '';
            if (message !== '' && !window.confirm(message)) {
                event.preventDefault();
            }
        });
    });
})();
</script>
