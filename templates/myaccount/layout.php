<?php
/**
 * Base layout for "Mon Compte" pages.
 *
 * The shell provides the sidebar. This template only renders the account content.
 *
 * @package chassesautresor
 */

defined('ABSPATH') || exit;

$content_template      = $GLOBALS['myaccount_content_template'] ?? null;
$current_user          = wp_get_current_user();
$last_active_formatted = function_exists('myaccount_format_last_active')
    ? myaccount_format_last_active((int) $current_user->ID)
    : '';

get_header();
?>
<div class="myaccount-layout">
    <div class="myaccount-main">
        <?php
        $page_title = '';
        $single_hunt_home = function_exists('cat_is_single_hunt_mode') && cat_is_single_hunt_mode();
        $is_profile_root = $single_hunt_home
            && function_exists('is_account_page')
            && is_account_page()
            && function_exists('is_wc_endpoint_url')
            && !is_wc_endpoint_url()
            && empty($_GET['section']);
        $is_home_screen = !empty($GLOBALS['cat_account_home_on_front'])
            || (
                function_exists('is_account_page')
                && is_account_page()
                && function_exists('is_wc_endpoint_url')
                && !is_wc_endpoint_url()
                && empty($_GET['section'])
                && !$is_profile_root
            );
        $is_account_settings = $is_profile_root
            || is_wc_endpoint_url('edit-account')
            || is_wc_endpoint_url('orders')
            || is_wc_endpoint_url('edit-address');
        if ($is_profile_root || is_wc_endpoint_url('edit-account') || is_wc_endpoint_url('edit-address')) {
            $page_title = __('Profil', 'chassesautresor-com');
        } elseif (is_wc_endpoint_url('orders')) {
            $page_title = __('Commandes', 'chassesautresor-com');
        } elseif (is_wc_endpoint_url('tentatives')) {
            $page_title = __('Tentatives', 'chassesautresor-com');
        }
        $show_last_active = $last_active_formatted !== '' && !$is_account_settings;
        if (!$is_home_screen && ($page_title || $show_last_active)) :
            ?>
            <header class="myaccount-header">
                <?php if ($page_title) : ?>
                    <h1 class="myaccount-title">
                        <span class="myaccount-title-text"><?php echo esc_html($page_title); ?></span>
                    </h1>
                <?php endif; ?>
                <?php if ($show_last_active) : ?>
                    <p class="myaccount-last-active">
                        <span class="meta-label">
                            <?php esc_html_e('Dernière connexion :', 'chassesautresor-com'); ?>
                        </span>
                        <span class="meta-value"><?php echo esc_html($last_active_formatted); ?></span>
                    </p>
                <?php endif; ?>
            </header>
            <?php
        endif;
        ?>
        <main class="myaccount-content">
            <?php
            if ($content_template && file_exists($content_template)) {
                include $content_template;
            } else {
                if (function_exists('woocommerce_account_content')) {
                    woocommerce_account_content();
                } else {
                    echo '<p>' . esc_html__('Content not found.', 'chassesautresor-com') . '</p>';
                }
            }
            ?>
        </main>
    </div>
</div>

<?php
get_footer();
