<?php
/**
 * Reusable help icon button component.
 *
 * @param string $aria_label ARIA label for the button.
 * @param string $title      Optional title shown in the modal.
 * @param string $message    Optional help message.
 * @param string $classes    CSS classes to apply on the button.
 * @param string $variant    Visual variant (aide, info, aide-small).
 * @param string $icon       Font Awesome classes for the icon.
 * @param string $background Optional background tone (light, dark) to adjust icon color.
 * @param array  $attributes Additional HTML attributes for the button.
 */

defined('ABSPATH') || exit;

$args = wp_parse_args(
    $args ?? [],
    [
        'aria_label' => '',
        'title'      => '',
        'message'    => '',
        'classes'    => '',
        'variant'    => 'aide',
        'icon'       => '',
        'background' => '',
        'attributes' => [],
    ]
);

$aria_label = $args['aria_label'];
$title      = $args['title'];
$message    = $args['message'];
$classes    = $args['classes'];
$variant    = $args['variant'];
$icon       = $args['icon'];
$background = $args['background'];
$attributes = $args['attributes'];

if ($icon === '') {
    $icon = ($variant === 'info') ? 'fa-solid fa-circle-info' : 'fa-regular fa-circle-question';
}

if ($message !== '') {
    $attributes['data-message'] = $message;
}
if ($title !== '') {
    $attributes['data-title'] = $title;
}
if ($variant !== '') {
    $attributes['data-variant'] = $variant;
    $classes = trim('help-icon-button help-icon--' . $variant . ' ' . $classes);
} else {
    $classes = trim('help-icon-button ' . $classes);
}
if ($background !== '') {
    $classes = trim($classes . ' help-icon--bg-' . $background);
}
if ($icon !== '') {
    $attributes['data-icon'] = $icon;
}

$attr_string = '';
if ($aria_label !== '') {
    $attr_string .= ' aria-label="' . esc_attr($aria_label) . '"';
}
foreach ($attributes as $attr => $value) {
    $attr_string .= ' ' . esc_attr($attr) . '="' . esc_attr($value) . '"';
}
?>
<button type="button" class="<?= esc_attr($classes); ?>"<?= $attr_string; ?>>
    <i class="<?= esc_attr($icon); ?>" aria-hidden="true"></i>
</button>
