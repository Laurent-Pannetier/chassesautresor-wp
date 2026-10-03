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
    <article
      class="riddle-player-step<?= $completed ? ' is-completed' : ' is-current'; ?>"
      data-player-step-id="<?= esc_attr($stepId); ?>"
    >
      <?php if ($imageId > 0) : ?>
        <?php
        $imageSource = wp_get_attachment_image_src($imageId, 'large');
        $imageAlt = trim((string) get_post_meta($imageId, '_wp_attachment_image_alt', true));
        $imageUrl = add_query_arg(
            ['id' => $imageId, 'taille' => 'large'],
            site_url('/voir-image-enigme')
        );
        ?>
        <img
          class="riddle-player-step__image"
          src="<?= esc_url($imageUrl); ?>"
          alt="<?= esc_attr($imageAlt); ?>"
          loading="lazy"
          <?php if (is_array($imageSource)) : ?>
            width="<?= esc_attr((string) $imageSource[1]); ?>"
            height="<?= esc_attr((string) $imageSource[2]); ?>"
          <?php endif; ?>
        >
      <?php endif; ?>
      <?php if (trim($content) !== '') : ?>
        <div class="riddle-player-step__content"><?= wp_kses_post($content); ?></div>
      <?php endif; ?>
      <?php if (!$completed && $stepId === $currentId) : ?>
        <?php
        $configuration = (new ChassesAuTresor\Core\Progress\AnswerWidgetConfigurationService())->forStep($stepId);
        $maxFailures = (int) get_field('enigme_tentative_max', $riddleId);
        $usedFailures = 0;
        if (in_array($configuration['type'], ['text', 'directions', 'colors', 'numbers', 'safe_dial'], true)) {
            global $wpdb;
            $usedFailures = ChassesAuTresor\Core\Support\CoreServiceFactory::riddleAttempts($wpdb)
                ->countFailuresTodayForUser((int) get_current_user_id(), $riddleId);
        }
        $widgetView = (new ChassesAuTresor\Core\Progress\AnswerWidgetPlayerViewService())->build(
            $configuration,
            $maxFailures,
            $usedFailures
        );
        ?>
        <form
          class="<?= esc_attr($widgetView['form_class']); ?>"
          data-widget-action="<?= esc_attr($widgetView['action']); ?>"
          data-max-failures="<?= esc_attr($maxFailures); ?>"
          aria-busy="false"
        >
          <input type="hidden" name="enigme_id" value="<?= esc_attr($riddleId); ?>">
          <input type="hidden" name="etape_id" value="<?= esc_attr($stepId); ?>">
          <input
            type="hidden"
            name="nonce"
            value="<?= esc_attr(wp_create_nonce($widgetView['nonce_action'])); ?>"
          >
          <?php if ($widgetView['type'] === 'colors') : ?>
            <?php if ($widgetView['limit_reached']) : ?>
              <p class="message-limite"><?= esc_html__('Limite quotidienne atteinte.', 'chassesautresor-com'); ?></p>
            <?php else : ?>
              <p class="riddle-colors__title"><?= esc_html__('Code de déverrouillage', 'chassesautresor-com'); ?></p>
              <output class="riddle-colors__sequence" aria-live="polite"
                aria-label="<?= esc_attr__('Séquence saisie : vide', 'chassesautresor-com'); ?>"></output>
              <input type="hidden" name="reponse" value="">
              <div class="riddle-colors" role="group" aria-label="<?= esc_attr__('Clavier de couleurs', 'chassesautresor-com'); ?>">
                <?php
                $colors = [
                    'red' => __('Rouge', 'chassesautresor-com'),
                    'orange' => __('Orange', 'chassesautresor-com'),
                    'yellow' => __('Jaune', 'chassesautresor-com'),
                    'green' => __('Vert', 'chassesautresor-com'),
                    'blue' => __('Bleu', 'chassesautresor-com'),
                    'purple' => __('Violet', 'chassesautresor-com'),
                    'indigo' => __('Indigo', 'chassesautresor-com'),
                    'pink' => __('Rose', 'chassesautresor-com'),
                    'brown' => __('Marron', 'chassesautresor-com'),
                    'grey' => __('Gris', 'chassesautresor-com'),
                    'black' => __('Noir', 'chassesautresor-com'),
                    'white' => __('Blanc', 'chassesautresor-com'),
                ];
                ?>
                <?php foreach ($colors as $color => $label) : ?>
                  <button type="button" class="riddle-color riddle-color--<?= esc_attr($color); ?>"
                    data-color="<?= esc_attr($color); ?>" data-label="<?= esc_attr($label); ?>">
                    <?= esc_html($label); ?>
                  </button>
                <?php endforeach; ?>
              </div>
              <div class="riddle-colors__actions">
                <button type="button" class="riddle-colors-reset" aria-label="<?= esc_attr__('Recommencer', 'chassesautresor-com'); ?>">↻</button>
                <button type="submit" class="bouton-cta bouton-cta--color"><?= esc_html($widgetView['button_label']); ?></button>
              </div>
            <?php endif; ?>
          <?php elseif ($widgetView['type'] === 'numbers') : ?>
            <?php if ($widgetView['limit_reached']) : ?>
              <p class="message-limite"><?= esc_html__('Limite quotidienne atteinte.', 'chassesautresor-com'); ?></p>
            <?php else : ?>
              <p class="riddle-numbers__title"><?= esc_html__('Code de déverrouillage', 'chassesautresor-com'); ?></p>
              <output class="riddle-numbers__sequence" aria-live="polite"
                aria-label="<?= esc_attr__('Séquence saisie : vide', 'chassesautresor-com'); ?>"></output>
              <input type="hidden" name="reponse" value="">
              <div class="riddle-numbers" role="group" aria-label="<?= esc_attr__('Pavé numérique', 'chassesautresor-com'); ?>">
                <?php foreach ([1, 2, 3, 4, 5, 6, 7, 8, 9, 0] as $number) : ?>
                  <button type="button" class="riddle-number<?= $number === 0 ? ' riddle-number--zero' : ''; ?>"
                    data-number="<?= esc_attr($number); ?>"><?= esc_html($number); ?></button>
                <?php endforeach; ?>
              </div>
              <div class="riddle-widget-actions">
                <button type="button" class="riddle-widget-reset" aria-label="<?= esc_attr__('Recommencer', 'chassesautresor-com'); ?>">↻</button>
                <button type="submit" class="bouton-cta bouton-cta--color"><?= esc_html($widgetView['button_label']); ?></button>
              </div>
            <?php endif; ?>
          <?php elseif ($widgetView['type'] === 'safe_dial') : ?>
            <?php if ($widgetView['limit_reached']) : ?>
              <p class="message-limite"><?= esc_html__('Limite quotidienne atteinte.', 'chassesautresor-com'); ?></p>
            <?php else : ?>
              <p class="riddle-safe__title"><?= esc_html__('Code de déverrouillage', 'chassesautresor-com'); ?></p>
              <div class="riddle-safe" role="slider" tabindex="0" data-value="0" aria-valuemin="0" aria-valuemax="99"
                aria-valuenow="0" aria-valuetext="<?= esc_attr__('Valeur de la molette : 0', 'chassesautresor-com'); ?>"
                aria-label="<?= esc_attr__('Molette de coffre-fort', 'chassesautresor-com'); ?>">
                <span class="riddle-safe__marker" aria-hidden="true"></span>
                <?php foreach (range(0, 90, 10) as $index => $number) : ?>
                  <span class="riddle-safe__number" style="--safe-index: <?= esc_attr($index); ?>" aria-hidden="true">
                    <?= esc_html($number); ?>
                  </span>
                <?php endforeach; ?>
                <span class="riddle-safe__dial" aria-hidden="true">
                  <span class="riddle-safe__value">0</span>
                </span>
              </div>
              <output class="riddle-safe__sequence" aria-live="polite"
                aria-label="<?= esc_attr__('Séquence saisie : vide', 'chassesautresor-com'); ?>"></output>
              <input type="hidden" name="reponse" value="">
              <div class="riddle-widget-actions">
                <button type="button" class="riddle-widget-reset" aria-label="<?= esc_attr__('Recommencer', 'chassesautresor-com'); ?>">↻</button>
                <button type="submit" class="bouton-cta bouton-cta--color"><?= esc_html($widgetView['button_label']); ?></button>
              </div>
            <?php endif; ?>
          <?php elseif ($widgetView['type'] === 'directions') : ?>
            <?php if ($widgetView['limit_reached']) : ?>
              <p class="message-limite"><?= esc_html__('Limite quotidienne atteinte.', 'chassesautresor-com'); ?></p>
            <?php else : ?>
              <p class="riddle-directions__title"><?= esc_html__('Code de déverrouillage', 'chassesautresor-com'); ?></p>
              <output class="riddle-directions__sequence" aria-live="polite"
                aria-label="<?= esc_attr__('Séquence saisie : vide', 'chassesautresor-com'); ?>"></output>
              <input type="hidden" name="reponse" value="">
              <div class="riddle-directions" role="group" aria-label="<?= esc_attr__('Pavé directionnel', 'chassesautresor-com'); ?>">
                <?php
                $directions = [
                    'NW' => ['↖', __('Nord-ouest', 'chassesautresor-com')],
                    'N' => ['↑', __('Nord', 'chassesautresor-com')],
                    'NE' => ['↗', __('Nord-est', 'chassesautresor-com')],
                    'W' => ['←', __('Ouest', 'chassesautresor-com')],
                    '' => ['', ''],
                    'E' => ['→', __('Est', 'chassesautresor-com')],
                    'SW' => ['↙', __('Sud-ouest', 'chassesautresor-com')],
                    'S' => ['↓', __('Sud', 'chassesautresor-com')],
                    'SE' => ['↘', __('Sud-est', 'chassesautresor-com')],
                ];
                ?>
                <?php foreach ($directions as $direction => [$arrow, $label]) : ?>
                  <?php if ($direction === '') : ?><span aria-hidden="true"></span><?php else : ?>
                    <button type="button" class="riddle-direction" data-direction="<?= esc_attr($direction); ?>"
                      data-label="<?= esc_attr($label); ?>" aria-label="<?= esc_attr($label); ?>">
                      <?= esc_html($arrow); ?>
                    </button>
                  <?php endif; ?>
                <?php endforeach; ?>
              </div>
              <div class="riddle-directions__actions">
                <button type="button" class="riddle-directions-reset" aria-label="<?= esc_attr__('Recommencer', 'chassesautresor-com'); ?>">↻</button>
                <button type="submit" class="bouton-cta bouton-cta--color"><?= esc_html($widgetView['button_label']); ?></button>
              </div>
            <?php endif; ?>
          <?php elseif ($widgetView['type'] === 'text') : ?>
            <?php if ($widgetView['limit_reached']) : ?>
              <p class="message-limite"><?= esc_html__('Limite quotidienne atteinte.', 'chassesautresor-com'); ?></p>
            <?php else : ?>
              <label for="riddle-step-answer-<?= esc_attr($stepId); ?>">
                <?= esc_html($widgetView['input_label']); ?>
              </label>
              <input
                id="riddle-step-answer-<?= esc_attr($stepId); ?>"
                type="text"
                name="<?= esc_attr($widgetView['input_name']); ?>"
                required
              >
              <button type="submit" class="bouton-cta bouton-cta--color">
                <?= esc_html($widgetView['button_label']); ?>
              </button>
            <?php endif; ?>
          <?php else : ?>
            <button type="submit" class="bouton-cta bouton-cta--color">
              <?= esc_html($widgetView['button_label']); ?>
            </button>
          <?php endif; ?>
          <p class="riddle-step-click-form__feedback" role="status" aria-live="polite"></p>
        </form>
      <?php endif; ?>
    </article>
  <?php endforeach; ?>
</section>
