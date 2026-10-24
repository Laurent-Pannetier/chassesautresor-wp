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
    $imageId = (int) get_field('etape_image', $stepId);
    $content = (string) get_field('etape_contenu', $stepId);
    $plainContent = html_entity_decode(wp_strip_all_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $hasTextContent = preg_replace('/[\s\x{00A0}]+/u', '', $plainContent) !== '';
    $hasEmbeddedContent = preg_match(
        '/<(?:img|picture|video|audio|iframe|canvas|svg)\b/i',
        $content
    ) === 1;

    $hasImage = $imageId > 0;
    $hasText = $hasTextContent || $hasEmbeddedContent;
    // Les images d’étapes vivent dans la galerie BD ; une étape terminée
    // sans texte/média embarqué n’a plus rien à afficher ici.
    if ($completed && !$hasText) {
        continue;
    }
    $isWidgetOnly = !$completed && !$hasText;
    $stepClasses = 'riddle-player-step';
    $stepClasses .= $completed ? ' is-completed' : ' is-current';
    if ($hasImage) {
        $stepClasses .= ' has-image';
    }
    if ($hasText) {
        $stepClasses .= ' has-text';
    }
    if ($isWidgetOnly) {
        $stepClasses .= ' is-widget-only';
    }

    $pagePreviewUrl = '';
    $pageFullUrl = '';
    $pageThumbUrl = '';
    $pageAlt = '';
    $pageSrcset = '';
    $pageWebpSrcset = '';
    $pageSizes = '';
    $pageWidth = 0;
    $pageHeight = 0;
    if ($hasImage) {
        $pageAlt = trim((string) get_post_meta($imageId, '_wp_attachment_image_alt', true));
        if ($pageAlt === '') {
            $pageAlt = __('Page débloquée', 'chassesautresor-com');
        }
        $payload = function_exists('enigme_hero_picture_payload')
            ? enigme_hero_picture_payload($imageId)
            : null;
        if (is_array($payload) && $payload['src'] !== '') {
            // Déjà échappé comme le srcset du <picture>.
            $pagePreviewUrl = $payload['src'];
            $pageSrcset = $payload['srcset'];
            $pageWebpSrcset = $payload['webp_srcset'];
            $pageSizes = $payload['sizes'];
            $pageWidth = (int) $payload['width'];
            $pageHeight = (int) $payload['height'];
        } else {
            $pagePreviewUrl = esc_url(
                function_exists('cta_voir_image_enigme_url')
                    ? cta_voir_image_enigme_url($imageId, 'large')
                    : add_query_arg(
                        ['id' => $imageId, 'taille' => 'large'],
                        site_url('/voir-image-enigme')
                    )
            );
        }
        $pageFullUrl = function_exists('cta_voir_image_enigme_url')
            ? cta_voir_image_enigme_url($imageId, 'full')
            : add_query_arg(
                ['id' => $imageId, 'taille' => 'full'],
                site_url('/voir-image-enigme')
            );
        $pageThumbUrl = function_exists('cta_voir_image_enigme_url')
            ? cta_voir_image_enigme_url($imageId, 'thumbnail')
            : add_query_arg(
                ['id' => $imageId, 'taille' => 'thumbnail'],
                site_url('/voir-image-enigme')
            );
    }
    ?>
    <article
      class="<?= esc_attr($stepClasses); ?>"
      data-player-step-id="<?= esc_attr($stepId); ?>"
      <?php if ($hasImage) : ?>
        data-step-page-image-id="<?= esc_attr((string) $imageId); ?>"
        data-step-page-preview="<?= $pagePreviewUrl; ?>"
        data-step-page-full="<?= esc_url($pageFullUrl); ?>"
        data-step-page-thumb="<?= esc_url($pageThumbUrl); ?>"
        data-step-page-alt="<?= esc_attr($pageAlt); ?>"
        <?php if ($pageSrcset !== '') : ?>
          data-step-page-srcset="<?= $pageSrcset; ?>"
        <?php endif; ?>
        <?php if ($pageWebpSrcset !== '') : ?>
          data-step-page-srcset-webp="<?= $pageWebpSrcset; ?>"
        <?php endif; ?>
        <?php if ($pageSizes !== '') : ?>
          data-step-page-sizes="<?= esc_attr($pageSizes); ?>"
        <?php endif; ?>
        <?php if ($pageWidth > 0) : ?>
          data-step-page-width="<?= esc_attr((string) $pageWidth); ?>"
          data-step-page-height="<?= esc_attr((string) $pageHeight); ?>"
        <?php endif; ?>
      <?php endif; ?>
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
