<?php

defined('ABSPATH') || exit;

$riddleId = isset($args['riddle_id']) ? (int) $args['riddle_id'] : 0;
$state = isset($args['state']) && is_array($args['state']) ? $args['state'] : [];
$visibleIds = array_map('intval', $state['visible_step_ids'] ?? []);
$completedIds = array_map('intval', $state['completed_step_ids'] ?? []);
$currentId = (int) ($state['current_step_id'] ?? 0);
if ($riddleId <= 0 || $visibleIds === []) {
    return;
}
?>
<section class="riddle-steps-player" aria-label="<?= esc_attr__('Étapes intermédiaires', 'chassesautresor-com'); ?>">
  <?php foreach ($visibleIds as $stepId) : ?>
    <?php
    $completed = in_array($stepId, $completedIds, true);
    $imageId = (int) get_field('etape_image', $stepId);
    $content = (string) get_field('etape_contenu', $stepId);
    ?>
    <article class="riddle-player-step<?= $completed ? ' is-completed' : ' is-current'; ?>">
      <?php if ($imageId > 0) : ?>
        <?= wp_get_attachment_image($imageId, 'large', false, ['class' => 'riddle-player-step__image']); ?>
      <?php endif; ?>
      <?php if (trim($content) !== '') : ?>
        <div class="riddle-player-step__content"><?= wp_kses_post($content); ?></div>
      <?php endif; ?>
      <?php if ($completed) : ?>
        <p class="riddle-player-step__status">
          <i class="fa-solid fa-check" aria-hidden="true"></i>
          <?= esc_html__('Étape terminée', 'chassesautresor-com'); ?>
        </p>
      <?php elseif ($stepId === $currentId) : ?>
        <form class="riddle-step-click-form">
          <input type="hidden" name="enigme_id" value="<?= esc_attr($riddleId); ?>">
          <input type="hidden" name="etape_id" value="<?= esc_attr($stepId); ?>">
          <input
            type="hidden"
            name="nonce"
            value="<?= esc_attr(wp_create_nonce('riddle_step_click')); ?>"
          >
          <button type="submit" class="bouton-cta bouton-cta--color">
            <?= esc_html((string) (get_field('etape_reponse_bouton', $stepId)
                ?: __('Continuer', 'chassesautresor-com'))); ?>
          </button>
          <p class="riddle-step-click-form__feedback" role="status" aria-live="polite"></p>
        </form>
      <?php endif; ?>
    </article>
  <?php endforeach; ?>
</section>
