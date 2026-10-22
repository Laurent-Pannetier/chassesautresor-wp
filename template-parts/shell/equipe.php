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

$view = cat_team_service()->describe(cat_team_actor());
$team = is_array($view['team'] ?? null) ? $view['team'] : null;
$userId = (int) get_current_user_id();
$hunts = function_exists('cta_shell_player_hunts') ? cta_shell_player_hunts($userId) : [];
$requestedHuntId = isset($_GET['chasse']) ? (int) $_GET['chasse'] : 0;
$huntId = 0;
foreach ($hunts as $hunt) {
    if ((int) ($hunt['id'] ?? 0) === $requestedHuntId) {
        $huntId = $requestedHuntId;
        break;
    }
}
if ($huntId <= 0 && $hunts !== []) {
    $huntId = (int) ($hunts[0]['id'] ?? 0);
}
$teamId = is_array($team) ? (int) ($team['id'] ?? 0) : 0;
if (
    $huntId > 0
    && class_exists(ChassesAuTresor\Core\Teams\TeamPerformanceApplicationService::class)
) {
    global $wpdb;
    $performance = (new ChassesAuTresor\Core\Teams\TeamPerformanceApplicationService(
        new ChassesAuTresor\Core\Teams\TeamPerformanceRepository($wpdb)
    ))->build($teamId, $huntId, 1, $userId);
    $teamHunts = [];
    foreach ($hunts as $hunt) {
        $id = (int) ($hunt['id'] ?? 0);
        $teamHunts[] = [
            'id' => $id,
            'title' => (string) ($hunt['title'] ?? ''),
            'url' => function_exists('add_query_arg')
                ? (string) add_query_arg('chasse', (string) $id, $action)
                : $action . '?chasse=' . $id,
        ];
    }
    $performance['hunts'] = $teamHunts;
    $performance['ajax'] = [
        'url' => function_exists('admin_url') ? admin_url('admin-ajax.php') : '',
        'nonce' => function_exists('wp_create_nonce') ? wp_create_nonce('cat_team_performance') : '',
    ];
    $view['performance'] = $performance;
}

echo (new ChassesAuTresor\Core\Teams\TeamPageRenderer())->render(
    $view,
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
    document.querySelectorAll('.equipe-image-picker__input').forEach(function (input) {
        input.addEventListener('change', function () {
            var picker = input.closest('.equipe-image-picker');
            var file = input.files && input.files[0];
            if (!picker) {
                return;
            }
            picker.classList.toggle('has-file', !!file);
            if (file) {
                picker.setAttribute('title', file.name);
                return;
            }
            picker.removeAttribute('title');
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
