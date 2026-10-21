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
    echo '</a>';
};
?>
<aside id="app-sidebar" class="app-sidebar" aria-label="<?php esc_attr_e('Navigation', 'chassesautresor-com'); ?>">
    <nav class="app-nav" aria-label="<?php esc_attr_e('Navigation principale', 'chassesautresor-com'); ?>">
        <?php foreach ($primary as $item) : ?>
            <?php $render_link($item); ?>
            <?php if (($item['key'] ?? '') === 'outils' && (!empty($item['active']) || !empty($item['section']))) : ?>
                <?php
                if (function_exists('cta_shell_render_outils_submenu')) {
                    cta_shell_render_outils_submenu();
                }
                ?>
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
