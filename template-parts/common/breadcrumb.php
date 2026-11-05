<?php
/**
 * Template part: Breadcrumb navigation.
 *
 * Expected $args['items'] as array of:
 * [
 *   'label'      => string,
 *   'url'        => string|null,
 *   'current'    => bool,
 *   'label_html' => string Optional raw HTML for the label.
 * ].
 */

defined('ABSPATH') || exit;

$items = isset($args['items']) && is_array($args['items']) ? $args['items'] : [];

if (empty($items)) {
    return;
}
?>
<nav class="breadcrumb" aria-label="<?php echo esc_attr__('Fil d\'Ariane', 'chassesautresor-com'); ?>">
    <ol itemscope itemtype="https://schema.org/BreadcrumbList">
        <?php foreach ($items as $index => $item) :
            $label      = isset($item['label']) ? $item['label'] : '';
            $label_html = isset($item['label_html']) ? $item['label_html'] : '';
            $allowed    = [
                'i'    => [
                    'class'       => [],
                    'aria-hidden' => [],
                ],
                'span' => [
                    'class' => [],
                ],
            ];
            $label_output = $label_html ? wp_kses($label_html, $allowed) : esc_html($label);
            ?>
            <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                <?php if (!empty($item['url']) && empty($item['current'])) : ?>
                    <a itemprop="item" href="<?= esc_url($item['url']); ?>">
                        <span itemprop="name"><?= $label_output; ?></span>
                    </a>
                <?php else : ?>
                    <span itemprop="name"><?= $label_output; ?></span>
                <?php endif; ?>
                <meta itemprop="position" content="<?= (int) ($index + 1); ?>" />
            </li>
        <?php endforeach; ?>
    </ol>
</nav>
