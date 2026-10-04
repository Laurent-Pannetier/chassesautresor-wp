<?php
/**
 * Base layout for "Mon Compte" pages.
 *
 * This template defines the common structure and injects dynamic content
 * provided via the global variable `$myaccount_content_template`.
 *
 * @package chassesautresor
 */

defined('ABSPATH') || exit;

$content_template      = $GLOBALS['myaccount_content_template'] ?? null;
$current_user          = wp_get_current_user();
$display_name          = $current_user->ID ? $current_user->display_name : get_bloginfo('name');
$show_nav              = is_user_logged_in();
$last_active_formatted = '';

if ($current_user->ID) {
    $last_active_raw = get_user_meta($current_user->ID, 'wc_last_active', true);
    if ($last_active_raw) {
        if (is_numeric($last_active_raw)) {
            $last_active_timestamp = absint($last_active_raw);
        } else {
            $last_active_timestamp = strtotime($last_active_raw);
        }

        if (!empty($last_active_timestamp)) {
            $locale = function_exists('determine_locale') ? determine_locale() : get_locale();
            $date_format = strpos($locale, 'fr_') === 0 ? 'd/m/Y' : 'n/j/Y';

            if (function_exists('wp_date')) {
                $last_active_formatted = wp_date($date_format, $last_active_timestamp);
            } else {
                $last_active_formatted = date_i18n($date_format, $last_active_timestamp);
            }
        }
    }
}

get_header();
?>
<div class="myaccount-layout">
    <button
        type="button"
        class="myaccount-sidebar-toggle"
        aria-controls="myaccount-sidebar"
        aria-expanded="false"
    >
        <span class="myaccount-sidebar-toggle-icon" aria-hidden="true">
            <span></span>
            <span></span>
            <span></span>
        </span>
        <span class="myaccount-sidebar-toggle-text">
            <?php esc_html_e('Menu', 'chassesautresor-com'); ?>
        </span>
    </button>
    <div class="myaccount-sidebar-backdrop" aria-hidden="true"></div>
    <aside id="myaccount-sidebar" class="myaccount-sidebar">
        <div class="myaccount-brand">
            <a href="<?php echo esc_url(home_url('/mon-compte/')); ?>">
                <?php echo esc_html($display_name); ?>
            </a>
            <button
                type="button"
                class="myaccount-sidebar-close"
                aria-label="<?php esc_attr_e('Fermer le menu', 'chassesautresor-com'); ?>"
            >
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <?php if ($show_nav) : ?>
        <nav class="dashboard-nav" aria-label="<?php esc_attr_e('Navigation du compte', 'chassesautresor-com'); ?>">
            <?php
            $nav_items = myaccount_get_sidebar_nav_items($current_user);

            foreach ($nav_items as $item) {
                $classes = 'dashboard-nav-link';
                if (!empty($item['active'])) {
                    $classes .= ' active';
                }

                echo '<a href="' . esc_url($item['url']) . '" class="' . esc_attr($classes) . '">';
                echo '<i class="' . esc_attr($item['icon']) . '" aria-hidden="true"></i>';
                echo '<span>' . esc_html($item['label']) . '</span>';
                echo '</a>';
            }
            ?>
        </nav>
        <?php
        $organizer_roles = array('organisateur', 'organisateur_creation');
        if (defined('ROLE_ORGANISATEUR')) {
            $organizer_roles[] = ROLE_ORGANISATEUR;
        }
        if (defined('ROLE_ORGANISATEUR_CREATION')) {
            $organizer_roles[] = ROLE_ORGANISATEUR_CREATION;
        }

        if (array_intersect($organizer_roles, (array) $current_user->roles)) {
            $organizer_nav = myaccount_get_organizer_nav((int) $current_user->ID);
            if ($organizer_nav) {
                echo myaccount_render_organizer_nav($organizer_nav); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            } else {
                $organizer_id = function_exists('get_organisateur_from_user')
                    ? (int) get_organisateur_from_user((int) $current_user->ID)
                    : 0;

                if ($organizer_id && function_exists('get_the_title')) {
                    $show_organizer_link = !function_exists('cat_is_platform_mode')
                        || cat_is_platform_mode();
                    $organisation_title = get_the_title($organizer_id);
                    $organisation_url   = function_exists('get_permalink')
                        ? get_permalink($organizer_id)
                        : '';
                    $organizer_hunt_ids = array();
                    if (function_exists('get_chasses_de_organisateur')) {
                        $hunts_query = get_chasses_de_organisateur($organizer_id);
                        if ($hunts_query instanceof WP_Query) {
                            $organizer_hunt_ids = array_map('intval', $hunts_query->posts);
                        } elseif (is_array($hunts_query)) {
                            $organizer_hunt_ids = array_map('intval', $hunts_query);
                        }
                    }

                    if (function_exists('chasse_est_visible_pour_utilisateur')) {
                        $organizer_hunt_ids = array_values(array_filter(
                            $organizer_hunt_ids,
                            static function ($hunt_id) use ($current_user) {
                                return chasse_est_visible_pour_utilisateur((int) $hunt_id, (int) $current_user->ID);
                            }
                        ));
                    }

                    $organizer_hunts = array();
                    if (!empty($organizer_hunt_ids) && function_exists('get_permalink')) {
                        foreach ($organizer_hunt_ids as $hunt_id) {
                            $hunt_title = get_the_title($hunt_id);
                            $hunt_link  = get_permalink($hunt_id);
                            if ($hunt_title && $hunt_link) {
                                $organizer_hunts[] = array(
                                    'title' => $hunt_title,
                                    'url'   => $hunt_link,
                                );
                            }
                        }
                    }

                    if (($show_organizer_link && $organisation_title && $organisation_url) || !empty($organizer_hunts)) :
                        ?>
                        <nav
                            class="dashboard-nav organizer-organisation-nav<?php echo $show_organizer_link ? '' : ' organizer-nav--hunt-first'; ?>"
                            aria-label="<?php esc_attr_e('Organisation', 'chassesautresor-com'); ?>"
                        >
                            <ul class="organizer-nav-tree">
                                <?php if ($show_organizer_link && $organisation_title && $organisation_url) : ?>
                                    <li class="organizer-nav-item organizer-nav-root">
                                        <a href="<?php echo esc_url($organisation_url); ?>" class="dashboard-nav-link organizer-nav-link">
                                            <i class="fas fa-landmark" aria-hidden="true"></i>
                                            <span><?php echo esc_html($organisation_title); ?></span>
                                        </a>
                                        <?php if (!empty($organizer_hunts)) : ?>
                                            <ul class="organizer-nav-children">
                                                <?php foreach ($organizer_hunts as $organizer_hunt) : ?>
                                                    <li class="organizer-nav-item">
                                                        <a href="<?php echo esc_url($organizer_hunt['url']); ?>" class="dashboard-nav-link organizer-nav-link organizer-nav-hunt">
                                                            <span class="nav-title"><?php echo esc_html($organizer_hunt['title']); ?></span>
                                                        </a>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </li>
                                <?php else : ?>
                                    <?php foreach ($organizer_hunts as $organizer_hunt) : ?>
                                        <li class="organizer-nav-item">
                                            <a href="<?php echo esc_url($organizer_hunt['url']); ?>" class="dashboard-nav-link organizer-nav-link organizer-nav-hunt">
                                                <span class="nav-title"><?php echo esc_html($organizer_hunt['title']); ?></span>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>
                        </nav>
                        <?php
                    endif;
                }
            }
        }
        ?>
        <?php endif; ?>
    </aside>
    <div class="myaccount-main">
        <header class="myaccount-header">
            <?php
            $page_title    = '';
            $page_greeting = '';
            if (is_wc_endpoint_url('edit-account') || is_wc_endpoint_url('orders') || is_wc_endpoint_url('edit-address')) {
                $page_title = __('Profil', 'chassesautresor-com');
            } elseif (is_wc_endpoint_url('tentatives')) {
                $page_title = __('Tentatives', 'chassesautresor-com');
            } elseif (is_account_page() && empty($_GET['section'])) {
                $page_greeting = __('Bienvenue', 'chassesautresor-com');
                $page_title    = $display_name;
            }
            if ($page_title) :
                ?>
                <h1 class="myaccount-title">
                    <?php if ($page_greeting) : ?>
                        <span class="myaccount-title-greeting"><?php echo esc_html($page_greeting); ?></span>
                    <?php endif; ?>
                    <span class="myaccount-title-text"><?php echo esc_html($page_title); ?></span>
                </h1>
                <?php
            endif;
            ?>
            <?php if ($last_active_formatted) : ?>
                <p class="myaccount-last-active">
                    <span class="meta-label"><?php esc_html_e('Dernière connexion :', 'chassesautresor-com'); ?></span>
                    <span class="meta-value"><?php echo esc_html($last_active_formatted); ?></span>
                </p>
            <?php endif; ?>
        </header>
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
