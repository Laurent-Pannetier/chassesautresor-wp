<?php
/**
 * Dashboard card displaying a horizontal bar chart.
 *
 * Expected variables:
 * - $label (string) Card title.
 * - $data  (array)  Each row: ['title' => string, 'url' => string, 'value' => float].
 * - $max   (float)  Maximum value among rows.
 * - $stat  (string) Data attribute identifier.
 */

defined('ABSPATH') || exit;

$args  = $args ?? [];
$label = $args['label'] ?? $label ?? '';
$data  = $args['data'] ?? $data ?? [];
$max   = $args['max'] ?? $max ?? 0;
$stat  = $args['stat'] ?? $stat ?? '';
?>
<div class="dashboard-card graph-card" data-stat="<?= esc_attr($stat); ?>">
  <h3><?= esc_html($label); ?></h3>
  <?php if (!empty($data)) : ?>
    <div class="stats-bar-chart">
      <?php foreach ($data as $row) :
          $width = $max > 0 ? (100 * $row['value']) / $max : 0;
      ?>
        <div class="bar-row">
          <a href="<?= esc_url($row['url']); ?>" class="bar-label"><?= esc_html($row['title']); ?></a>
          <div class="bar-wrapper">
            <div class="bar-fill" style="width:<?= esc_attr($width); ?>%;">
              <span class="bar-value"><?= esc_html(number_format($row['value'], 0, ',', ' ')); ?></span>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else : ?>
    <p><?php esc_html_e('Aucune donnée.', 'chassesautresor-com'); ?></p>
  <?php endif; ?>
</div>
