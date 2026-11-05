<?php

defined('ABSPATH') || exit;

$message = trim((string) ($args['message'] ?? ''));
$compact = !empty($args['compact']);
$hidden = !empty($args['hidden']);
$extraClass = trim((string) ($args['class'] ?? ''));
$id = (string) ($args['id'] ?? '');
if ($id === '' && function_exists('wp_unique_id')) {
    $id = wp_unique_id('answer-gap-');
}
if ($id === '') {
    $id = 'answer-gap-' . substr(md5($message . $extraClass), 0, 8);
}

$classes = 'answer-gap-marker' . ($compact ? ' answer-gap-marker--legend' : '');
if ($extraClass !== '') {
    $classes .= ' ' . $extraClass;
}
?>
<span
  class="<?= esc_attr($classes); ?>"
  tabindex="0"
  aria-describedby="<?= esc_attr($id); ?>"
  <?php if ($hidden) : ?>hidden<?php endif; ?>
  <?php if ($message !== '') : ?>
    data-message="<?= esc_attr($message); ?>"
    title="<?= esc_attr($message); ?>"
  <?php endif; ?>
>
  <i class="fa-solid fa-exclamation" aria-hidden="true"></i>
  <?php if (!$compact) : ?>
    <span class="answer-gap-marker__label">
      <?= esc_html__('configuration incomplète', 'chassesautresor-com'); ?>
    </span>
  <?php endif; ?>
  <span class="answer-gap-marker__tip" id="<?= esc_attr($id); ?>" role="tooltip">
    <?= esc_html($message); ?>
  </span>
</span>
