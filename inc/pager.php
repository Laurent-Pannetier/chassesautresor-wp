<?php
/**
 * Default pager component for tables.
 *
 * Provides controls to navigate between pages: first, previous, direct page selection,
 * next and last. Elements are styled using the `etiquette` class and rely on the global
 * `pager.js` script which dispatches a `pager:change` event with the selected page.
 *
 * Usage:
 * echo cta_render_pager(1, 5, 'my-pager');
 *
 * @param int    $current    Current page number (1-indexed).
 * @param int    $total      Total number of pages.
 * @param string $class      Optional additional CSS class for the nav element.
 * @param array  $attributes Optional HTML attributes to append to the nav element.
*
 * @return string HTML markup for the pager.
 */
function cta_render_pager(int $current, int $total, string $class = '', array $attributes = []): string
{
    if ($total <= 1) {
        return '';
    }

    $classes = trim('pager ' . $class);

    $attr_string = '';
    foreach ($attributes as $key => $value) {
        if ($value === null) {
            continue;
        }

        $sanitized_key = preg_replace('/[^a-z0-9_-]+/i', '', (string) $key);
        if ($sanitized_key === '') {
            continue;
        }

        $attr_string .= sprintf(' %s="%s"', esc_attr($sanitized_key), esc_attr($value));
    }

    ob_start();
    ?>
    <nav class="<?php echo esc_attr($classes); ?>" data-current="<?php echo esc_attr($current); ?>" data-total="<?php echo esc_attr($total); ?>"<?php echo $attr_string; ?>>
        <button type="button" class="etiquette pager-first" aria-label="<?php esc_attr_e('First page', 'chassesautresor-com'); ?>">&laquo;</button>
        <button type="button" class="etiquette pager-prev" aria-label="<?php esc_attr_e('Previous page', 'chassesautresor-com'); ?>">&lsaquo;</button>
        <span class="pager-info">
            <select class="etiquette pager-select" aria-label="<?php esc_attr_e('Go to page', 'chassesautresor-com'); ?>">
                <?php for ($i = 1; $i <= $total; $i++) : ?>
                    <option value="<?php echo esc_attr($i); ?>"<?php selected($i, $current); ?>><?php echo esc_html($i); ?></option>
                <?php endfor; ?>
            </select>
            / <?php echo esc_html($total); ?>
        </span>
        <button type="button" class="etiquette pager-next" aria-label="<?php esc_attr_e('Next page', 'chassesautresor-com'); ?>">&rsaquo;</button>
        <button type="button" class="etiquette pager-last" aria-label="<?php esc_attr_e('Last page', 'chassesautresor-com'); ?>">&raquo;</button>
    </nav>
    <?php
    return ob_get_clean();
}
