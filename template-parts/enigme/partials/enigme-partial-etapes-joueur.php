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
<section
  class="riddle-steps-player"
  aria-label="<?= esc_attr__('Étapes intermédiaires', 'chassesautresor-com'); ?>"
>
  <?php foreach ($visibleIds as $stepId) : ?>
    <?php
    $completed = in_array($stepId, $completedIds, true);
    $content = (string) get_field('etape_contenu', $stepId);
    $plainContent = html_entity_decode(wp_strip_all_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $hasTextContent = preg_replace('/[\s\x{00A0}]+/u', '', $plainContent) !== '';
    $hasEmbeddedContent = preg_match(
        '/<(?:img|picture|video|audio|iframe|canvas|svg)\b/i',
        $content
    ) === 1;

    $hasText = $hasTextContent || $hasEmbeddedContent;
    // Les pages ne sortent qu’une fois l’étape réussie. L’étape en cours
    // ne les embarque pas : elles servent la question suivante.
    $revealedPages = $completed && function_exists('enigme_step_image_ids')
        ? enigme_step_image_ids((int) $stepId)
        : [];
    if ($revealedPages !== []) :
        ?>
    <div class="riddle-step-revealed-pages" data-revealed-step-id="<?= esc_attr((string) $stepId); ?>" hidden>
        <?php foreach ($revealedPages as $imageId) : ?>
            <?php
            $page = enigme_step_comic_page_view((int) $imageId);
            if ($page['preview'] === '' || $page['full'] === '') {
                continue;
            }
            ?>
      <span
        data-step-page-image-id="<?= esc_attr((string) $imageId); ?>"
        data-step-page-preview="<?= $page['preview']; ?>"
        data-step-page-full="<?= esc_url($page['full']); ?>"
        data-step-page-thumb="<?= esc_url($page['thumb']); ?>"
        data-step-page-alt="<?= esc_attr($page['alt']); ?>"
            <?php if ($page['srcset'] !== '') : ?>
        data-step-page-srcset="<?= $page['srcset']; ?>"
            <?php endif; ?>
            <?php if ($page['webp_srcset'] !== '') : ?>
        data-step-page-srcset-webp="<?= $page['webp_srcset']; ?>"
            <?php endif; ?>
            <?php if ($page['sizes'] !== '') : ?>
        data-step-page-sizes="<?= esc_attr($page['sizes']); ?>"
            <?php endif; ?>
            <?php if ($page['width'] > 0) : ?>
        data-step-page-width="<?= esc_attr((string) $page['width']); ?>"
        data-step-page-height="<?= esc_attr((string) $page['height']); ?>"
            <?php endif; ?>
      ></span>
        <?php endforeach; ?>
    </div>
        <?php
    endif;
    // Une étape terminée sans texte n’a plus rien à montrer hors de la BD.
    if ($completed && !$hasText) {
        continue;
    }
    $isWidgetOnly = !$completed && !$hasText;
    $stepClasses = 'riddle-player-step';
    $stepClasses .= $completed ? ' is-completed' : ' is-current';
    if ($hasText) {
        $stepClasses .= ' has-text';
    }
    if ($isWidgetOnly) {
        $stepClasses .= ' is-widget-only';
    }
    ?>
    <article
      class="<?= esc_attr($stepClasses); ?>"
      data-player-step-id="<?= esc_attr((string) $stepId); ?>"
    >
      <?php if (trim($content) !== '') : ?>
        <div class="riddle-player-step__content"><?= wp_kses_post($content); ?></div>
      <?php endif; ?>
      <?php if (!$completed && $stepId === $currentId) : ?>
        <?php
        $configuration = (new ChassesAuTresor\Core\Progress\AnswerWidgetConfigurationService())->forStep($stepId);
        $maxFailures = 0;
        $usedFailures = 0;
        global $wpdb;
        $retryState = ChassesAuTresor\Core\Support\CoreServiceFactory::riddleRetry($wpdb)->getState(
            (int) get_current_user_id(),
            $riddleId
        );
        $widgetView = (new ChassesAuTresor\Core\Progress\AnswerWidgetPlayerViewService())->build(
            $configuration,
            $maxFailures,
            $usedFailures
        );
        $replyKey = 'step-' . $stepId;
        echo cat_reponse_launcher_open($replyKey, cat_reponse_launcher_label());
        ?>
        <form
          class="<?= esc_attr($widgetView['form_class']); ?> is-reply-widget"
          data-riddle-reply-key="<?= esc_attr($replyKey); ?>"
          data-widget-action="<?= esc_attr($widgetView['action']); ?>"
          data-retry-state="<?= esc_attr(wp_json_encode($retryState)); ?>"
          aria-busy="false"
          hidden
        >
          <?= cat_reponse_widget_chrome(); ?>
          <input type="hidden" name="enigme_id" value="<?= esc_attr($riddleId); ?>">
          <input type="hidden" name="etape_id" value="<?= esc_attr($stepId); ?>">
          <input
            type="hidden"
            name="nonce"
            value="<?= esc_attr(wp_create_nonce($widgetView['nonce_action'])); ?>"
          >
          <?php
          get_template_part(
              'template-parts/enigme/partials/enigme-partial-answer-widget-controls',
              null,
              [
                  'widget_view' => $widgetView,
                  'field_suffix' => (string) $stepId,
                  'submit_label' => (string) $widgetView['button_label'],
              ]
          );
          ?>
          <p class="riddle-step-click-form__feedback" role="status" aria-live="polite"></p>
        </form>
        <?php echo cat_reponse_launcher_close(); ?>
      <?php endif; ?>
    </article>
  <?php endforeach; ?>
</section>
