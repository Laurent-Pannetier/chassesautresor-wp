<?php
/**
 * Generic dashboard stat card.
 *
 * Variables required:
 * - $icon  (string) Font Awesome classes for the icon.
 * - $label (string) Card title.
 * - $value (int|string) Value to display.
 * - $stat  (string) Data attribute identifier.
 * - $style (string, optional) Inline style attribute.
 * - $class (string, optional) Additional CSS classes.
 */

defined('ABSPATH') || exit;

$args         = $args ?? [];
$icon         = $args['icon'] ?? $icon ?? '';
$label        = $args['label'] ?? $label ?? '';
$value        = $args['value'] ?? $value ?? '';
$stat         = $args['stat'] ?? $stat ?? '';
$style        = $args['style'] ?? $style ?? '';
$help         = $args['help'] ?? $help ?? '';
$help_label   = $args['help_label'] ?? $help_label ?? '';
$help_title   = $args['help_title'] ?? $help_title ?? $label;
$help_variant = $args['help_variant'] ?? $help_variant ?? 'aide';
$class        = $args['class'] ?? $class ?? '';
?>
<div class="dashboard-card<?= $class ? ' ' . esc_attr($class) : ''; ?>" data-stat="<?= esc_attr($stat); ?>"<?php echo $style ? ' style="' . esc_attr($style) . '"' : ''; ?>>
  <i class="<?= esc_attr($icon); ?>"></i>
  <h3>
    <?= esc_html($label); ?>
    <?php if ($help) : ?>
      <?php
      get_template_part(
          'template-parts/common/help-icon',
          null,
          [
              'aria_label' => $help_label,
              'classes'    => 'stat-help',
              'title'      => $help_title,
              'message'    => $help,
              'variant'    => $help_variant,
          ]
      );
      ?>
    <?php endif; ?>
  </h3>
  <p class="stat-value"><?= esc_html($value); ?></p>
</div>
