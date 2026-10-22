<?php
/**
 * Top bar of the shell.
 *
 * @package chassesautresor
 */

defined('ABSPATH') || exit;

$current_user = wp_get_current_user();
$logged_in = $current_user instanceof WP_User && (int) $current_user->ID > 0;
$items = function_exists('myaccount_get_sidebar_nav_items')
    ? myaccount_get_sidebar_nav_items($current_user)
    : [];
$profile_url = '';
$trophy_url = '';
foreach ($items as $item) {
    if (($item['key'] ?? '') === 'profil') {
        $profile_url = (string) $item['url'];
    }
    if (($item['key'] ?? '') === 'trophees') {
        $trophy_url = (string) $item['url'];
    }
}

$display_name = '';
if ($logged_in) {
    $display_name = trim((string) $current_user->display_name);
    if ($display_name === '') {
        $display_name = (string) $current_user->user_login;
    }
}

$home = function_exists('home_url') ? home_url('/') : '/';
$brand = function_exists('get_bloginfo') ? (string) get_bloginfo('name') : 'chassesautresor.com';
if ($brand === '') {
    $brand = 'chassesautresor.com';
}
$logout_url = '';
if ($logged_in) {
    if (function_exists('wc_logout_url')) {
        $logout_url = wc_logout_url();
    } elseif (function_exists('wp_logout_url')) {
        $logout_url = wp_logout_url($home);
    }
}
$login_url = function_exists('wp_login_url') ? wp_login_url($home) : $home;
$trophy_count = $logged_in && function_exists('cat_user_trophy_count')
    ? cat_user_trophy_count((int) $current_user->ID)
    : 0;
?>
<header class="app-topbar">
    <div class="app-topbar__lead">
        <?php if (!function_exists('cta_shell_shows_sidebar') || cta_shell_shows_sidebar()) : ?>
            <button
                type="button"
                class="app-nav-toggle"
                data-app-nav-toggle
                aria-controls="app-sidebar"
                aria-expanded="false"
            >
                <span class="app-nav-toggle__icon" aria-hidden="true">
                    <span></span>
                    <span></span>
                    <span></span>
                </span>
                <span class="screen-reader-text"><?php esc_html_e('Menu', 'chassesautresor-com'); ?></span>
            </button>
        <?php endif; ?>
        <div class="app-topbar__brand">
            <?php if (
                function_exists('has_custom_logo')
                && has_custom_logo()
                && function_exists('the_custom_logo')
            ) : ?>
                <?php the_custom_logo(); ?>
            <?php else : ?>
                <a class="app-topbar__brand-link" href="<?php echo esc_url($home); ?>">
                    <img
                        src="<?php echo esc_url(
                            get_stylesheet_directory_uri() . '/assets/images/logo-cat_hz-txt.png'
                        ); ?>"
                        alt="<?php echo esc_attr($brand); ?>"
                    >
                </a>
            <?php endif; ?>
        </div>
    </div>
    <div class="app-topbar__hunt">
        <span class="app-topbar__hunt-title">
            <?php esc_html_e('Antre 2 feux', 'chassesautresor-com'); ?>
        </span>
    </div>
    <?php if ($logged_in) : ?>
        <div class="app-topbar__meta">
            <span class="app-topbar__team">
                <span class="app-topbar__team-label"><?php esc_html_e('Équipe', 'chassesautresor-com'); ?></span>
                <span class="app-topbar__team-value"><?php esc_html_e('solo', 'chassesautresor-com'); ?></span>
            </span>
            <?php if ($display_name !== '') : ?>
                <span class="app-topbar__player"><?php echo esc_html($display_name); ?></span>
            <?php endif; ?>
            <a class="app-topbar__trophies" href="<?php echo esc_url($trophy_url); ?>">
                <span class="app-topbar__trophy-icon" aria-hidden="true">
                    <?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo cta_shell_icon('trophy');
                    ?>
                </span>
                <span class="app-topbar__trophy-count"><?php echo esc_html((string) $trophy_count); ?></span>
            </a>
            <?php if ($profile_url !== '') : ?>
                <a class="app-topbar__profile" href="<?php echo esc_url($profile_url); ?>">
                    <span class="screen-reader-text"><?php esc_html_e('Profil', 'chassesautresor-com'); ?></span>
                    <?php
                    if (function_exists('get_avatar')) {
                        echo get_avatar(
                            (int) $current_user->ID,
                            64,
                            '',
                            $display_name
                        ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    }
                    ?>
                </a>
            <?php endif; ?>
            <?php if ($logout_url !== '') : ?>
                <a class="app-topbar__logout" href="<?php echo esc_url($logout_url); ?>">
                    <span class="screen-reader-text"><?php esc_html_e('Déconnexion', 'chassesautresor-com'); ?></span>
                    <span aria-hidden="true">
                        <?php
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        echo cta_shell_icon('logout');
                        ?>
                    </span>
                </a>
            <?php endif; ?>
        </div>
    <?php else : ?>
        <div class="app-topbar__meta">
            <a class="app-topbar__login" href="<?php echo esc_url($login_url); ?>">
                <?php esc_html_e('Connexion', 'chassesautresor-com'); ?>
            </a>
        </div>
    <?php endif; ?>
</header>
