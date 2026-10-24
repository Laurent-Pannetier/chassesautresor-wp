<?php
/**
 * Left navigation of the shell.
 *
 * @package chassesautresor
 */

defined('ABSPATH') || exit;

$items = function_exists('myaccount_get_sidebar_nav_items')
    ? myaccount_get_sidebar_nav_items(wp_get_current_user())
    : [];
$theme = function_exists('cta_shell_theme') ? cta_shell_theme() : 'night';
$primary = [];
$personal = [];

foreach ($items as $item) {
    if (($item['group'] ?? '') === 'personal') {
        $personal[] = $item;
        continue;
    }
    $primary[] = $item;
}

$render_link = static function (array $item): void {
    $classes = 'app-nav__link';
    if (!empty($item['active'])) {
        $classes .= ' is-active';
    }
    if (!empty($item['section'])) {
        $classes .= ' is-section';
    }
    $icon = function_exists('cta_shell_icon') ? cta_shell_icon((string) ($item['icon'] ?? '')) : '';
    echo '<a class="' . esc_attr($classes) . '" href="' . esc_url((string) $item['url']) . '"';
    if (!empty($item['current'])) {
        echo ' aria-current="page"';
    }
    echo '>';
    echo '<span class="app-nav__icon app-nav__icon--' . esc_attr((string) $item['icon']) . '" aria-hidden="true">';
    echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo '</span>';
    echo '<span class="app-nav__label">' . esc_html((string) $item['label']) . '</span>';
    $badge = (int) ($item['badge'] ?? 0);
    if ($badge > 0) {
        echo '<span class="app-nav__badge" aria-label="';
        echo esc_attr(sprintf(
            _n('%d notification non lue', '%d notifications non lues', $badge, 'chassesautresor-com'),
            $badge
        ));
        echo '">' . esc_html($badge > 99 ? '99+' : (string) $badge) . '</span>';
    }
    echo '</a>';
};
?>
<aside id="app-sidebar" class="app-sidebar" aria-label="<?php esc_attr_e('Navigation', 'chassesautresor-com'); ?>">
    <nav class="app-nav" aria-label="<?php esc_attr_e('Navigation principale', 'chassesautresor-com'); ?>">
        <?php foreach ($primary as $item) : ?>
            <?php
            $submenu = '';
            $outils_expanded = false;
            if (($item['key'] ?? '') === 'outils' && function_exists('cta_shell_render_outils_submenu')) {
                $outils_expanded = !empty($item['active']) || !empty($item['section']);
                ob_start();
                cta_shell_render_outils_submenu($outils_expanded);
                $submenu = trim((string) ob_get_clean());
            }
            ?>
            <?php if ($submenu !== '') : ?>
                <?php
                $branch_class = 'app-nav__branch';
                if (!empty($item['active'])) {
                    $branch_class .= ' is-active';
                }
                if (!empty($item['section'])) {
                    $branch_class .= ' is-section';
                }
                ?>
                <div class="<?php echo esc_attr($branch_class); ?>">
                    <?php $render_link($item); ?>
                    <button
                        type="button"
                        class="app-nav__disclosure"
                        data-app-nav-disclosure
                        data-outils-context="<?php echo $outils_expanded ? '1' : '0'; ?>"
                        aria-expanded="<?php echo $outils_expanded ? 'true' : 'false'; ?>"
                        aria-controls="app-nav-outils-enigmes"
                        aria-label="<?php esc_attr_e('Chasse et énigmes', 'chassesautresor-com'); ?>"
                    >
                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2.4"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M9 6l6 6-6 6"
                            />
                        </svg>
                    </button>
                </div>
                <?php
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo $submenu;
                ?>
                <?php if (!$outils_expanded) : ?>
                    <script>
                        (function () {
                            try {
                                if (localStorage.getItem('cta_outils_submenu') !== 'open') {
                                    return;
                                }
                                var list = document.getElementById('app-nav-outils-enigmes');
                                var button = document.querySelector('[data-app-nav-disclosure]');
                                if (list) {
                                    list.removeAttribute('hidden');
                                }
                                if (button) {
                                    button.setAttribute('aria-expanded', 'true');
                                }
                            } catch (error) {
                                return;
                            }
                        })();
                    </script>
                <?php endif; ?>
            <?php else : ?>
                <?php $render_link($item); ?>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>
    <div class="app-sidebar__foot">
        <div class="app-theme" role="group" aria-label="<?php esc_attr_e('Thème', 'chassesautresor-com'); ?>">
            <button
                type="button"
                class="app-theme__option<?php echo $theme === 'day' ? ' is-active' : ''; ?>"
                data-theme-value="day"
                aria-pressed="<?php echo $theme === 'day' ? 'true' : 'false'; ?>"
            >
                <span class="app-theme__icon" aria-hidden="true">
                    <?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo cta_shell_icon('sun');
                    ?>
                </span>
                <span><?php esc_html_e('Jour', 'chassesautresor-com'); ?></span>
            </button>
            <button
                type="button"
                class="app-theme__option<?php echo $theme === 'night' ? ' is-active' : ''; ?>"
                data-theme-value="night"
                aria-pressed="<?php echo $theme === 'night' ? 'true' : 'false'; ?>"
            >
                <span class="app-theme__icon" aria-hidden="true">
                    <?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo cta_shell_icon('moon');
                    ?>
                </span>
                <span><?php esc_html_e('Nuit', 'chassesautresor-com'); ?></span>
            </button>
        </div>
        <?php if ($personal !== []) : ?>
            <nav class="app-personal" aria-label="<?php esc_attr_e('Espace personnel', 'chassesautresor-com'); ?>">
                <?php foreach ($personal as $item) : ?>
                    <?php $render_link($item); ?>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>
    </div>
</aside>
