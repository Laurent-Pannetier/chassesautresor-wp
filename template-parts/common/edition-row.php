<?php
/**
 * Template Part: Edition row with icon, label and content.
 *
 * Expected args:
 * - string $icon       FontAwesome classes for the icon (optional).
 * - string|callable $label   Label HTML or callback printing it.
 * - string|callable $content Content HTML or callback printing it.
 * - string $class      Additional CSS classes for <li>.
 * - array  $attributes Additional HTML attributes for <li> (key => value).
 * - bool   $no_icon    True to disable the completion icon.
 *
 * @package chassesautresor
 */

$icon       = $args['icon'] ?? '';
$label      = $args['label'] ?? '';
$content    = $args['content'] ?? '';
$class      = $args['class'] ?? '';
$attributes = $args['attributes'] ?? [];
$no_icon    = !empty($args['no_icon']);

$attr_strings = [];
foreach ($attributes as $key => $value) {
    $attr_strings[] = sprintf('%s="%s"', esc_attr($key), esc_attr($value));
}
?>
<li class="edition-row <?php echo esc_attr($class); ?>" <?php echo implode(' ', $attr_strings); ?><?php echo $no_icon ? ' data-no-icon="1"' : ''; ?>>
    <div class="edition-row-label">
        <?php if (!$no_icon) : ?>
            <span class="edition-row-icon">
                <?php if (!empty($icon)) : ?>
                    <i class="<?php echo esc_attr($icon); ?>" aria-hidden="true"></i>
                <?php endif; ?>
            </span>
        <?php endif; ?>
        <?php
        if (is_callable($label)) {
            $label();
        } else {
            echo $label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
        ?>
    </div>
    <div class="edition-row-content">
        <?php
        if (is_callable($content)) {
            $content();
        } else {
            echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
        ?>
    </div>
</li>
