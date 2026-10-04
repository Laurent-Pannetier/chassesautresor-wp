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
    $plainContent = html_entity_decode(wp_strip_all_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $hasTextContent = preg_replace('/[\s\x{00A0}]+/u', '', $plainContent) !== '';
    $hasEmbeddedContent = preg_match(
        '/<(?:img|picture|video|audio|iframe|canvas|svg)\b/i',
        $content
    ) === 1;
    if ($completed && $imageId <= 0 && !$hasTextContent && !$hasEmbeddedContent) {
        continue;
    }
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
        ?>
        <form
          class="<?= esc_attr($widgetView['form_class']); ?>"
          data-widget-action="<?= esc_attr($widgetView['action']); ?>"
          data-retry-state="<?= esc_attr(wp_json_encode($retryState)); ?>"
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
                <svg class="riddle-safe__dial" viewBox="0 0 100 100" aria-hidden="true">
                  <defs>
                    <radialGradient id="safe-center-gradient-<?= esc_attr($stepId); ?>" cx="50%" cy="50%" r="50%">
                      <stop offset="0%" stop-color="#dedede"></stop>
                      <stop offset="60%" stop-color="#b8b8b8"></stop>
                      <stop offset="61%" stop-color="#8b8b8b"></stop>
                      <stop offset="100%" stop-color="#565656"></stop>
                    </radialGradient>
                  </defs>
                  <g class="riddle-safe__scale">
                    <?php foreach (range(0, 99) as $number) : ?>
                      <?php $tickStart = $number % 5 === 0 ? 9 : 6; ?>
                      <line x1="50" y1="<?= esc_attr($tickStart); ?>" x2="50" y2="0"
                        transform="rotate(<?= esc_attr($number * 3.6); ?> 50 50)"></line>
                    <?php endforeach; ?>
                    <?php foreach (range(0, 90, 10) as $number) : ?>
                      <text x="50" y="15" transform="rotate(<?= esc_attr($number * 3.6); ?> 50 50)">
                        <?= esc_html($number); ?>
                      </text>
                    <?php endforeach; ?>
                  </g>
                  <g class="riddle-safe__grip">
                    <path class="riddle-safe__gear" d="
                      M12.9046 3.06005C12.6988 3 12.4659 3 12 3C11.5341 3 11.3012 3 11.0954 3.06005C10.7942 3.14794
                      10.5281 3.32808 10.3346 3.57511C10.2024 3.74388 10.1159 3.96016 9.94291 4.39272C9.69419
                      5.01452 9.00393 5.33471 8.36857 5.123L7.79779 4.93281C7.3929 4.79785 7.19045 4.73036 6.99196
                      4.7188C6.70039 4.70181 6.4102 4.77032 6.15701 4.9159C5.98465 5.01501 5.83376 5.16591 5.53197
                      5.4677C5.21122 5.78845 5.05084 5.94882 4.94896 6.13189C4.79927 6.40084 4.73595 6.70934 4.76759
                      7.01551C4.78912 7.2239 4.87335 7.43449 5.04182 7.85566C5.30565 8.51523 5.05184 9.26878 4.44272
                      9.63433L4.16521 9.80087C3.74031 10.0558 3.52786 10.1833 3.37354 10.3588C3.23698 10.5141
                      3.13401 10.696 3.07109 10.893C3 11.1156 3 11.3658 3 11.8663C3 12.4589 3 12.7551 3.09462
                      13.0088C3.17823 13.2329 3.31422 13.4337 3.49124 13.5946C3.69158 13.7766 3.96395 13.8856
                      4.50866 14.1035C5.06534 14.3261 5.35196 14.9441 5.16236 15.5129L4.94721 16.1584C4.79819
                      16.6054 4.72367 16.829 4.7169 17.0486C4.70875 17.3127 4.77049 17.5742 4.89587 17.8067C5.00015
                      18.0002 5.16678 18.1668 5.5 18.5C5.83323 18.8332 5.99985 18.9998 6.19325 19.1041C6.4258
                      19.2295 6.68733 19.2913 6.9514 19.2831C7.17102 19.2763 7.39456 19.2018 7.84164 19.0528L8.36862
                      18.8771C9.00393 18.6654 9.6942 18.9855 9.94291 19.6073C10.1159 20.0398 10.2024 20.2561 10.3346
                      20.4249C10.5281 20.6719 10.7942 20.8521 11.0954 20.94C11.3012 21 11.5341 21 12 21C12.4659 21
                      12.6988 21 12.9046 20.94C13.2058 20.8521 13.4719 20.6719 13.6654 20.4249C13.7976 20.2561
                      13.8841 20.0398 14.0571 19.6073C14.3058 18.9855 14.9961 18.6654 15.6313 18.8773L16.1579
                      19.0529C16.605 19.2019 16.8286 19.2764 17.0482 19.2832C17.3123 19.2913 17.5738 19.2296 17.8063
                      19.1042C17.9997 18.9999 18.1664 18.8333 18.4996 18.5001C18.8328 18.1669 18.9994 18.0002
                      19.1037 17.8068C19.2291 17.5743 19.2908 17.3127 19.2827 17.0487C19.2759 16.8291 19.2014
                      16.6055 19.0524 16.1584L18.8374 15.5134C18.6477 14.9444 18.9344 14.3262 19.4913 14.1035C20.036
                      13.8856 20.3084 13.7766 20.5088 13.5946C20.6858 13.4337 20.8218 13.2329 20.9054 13.0088C21
                      12.7551 21 12.4589 21 11.8663C21 11.3658 21 11.1156 20.9289 10.893C20.866 10.696 20.763
                      10.5141 20.6265 10.3588C20.4721 10.1833 20.2597 10.0558 19.8348 9.80087L19.5569
                      9.63416C18.9478 9.26867 18.6939 8.51514 18.9578 7.85558C19.1262 7.43443 19.2105 7.22383 19.232
                      7.01543C19.2636 6.70926 19.2003 6.40077 19.0506 6.13181C18.9487 5.94875 18.7884 5.78837
                      18.4676 5.46762C18.1658 5.16584 18.0149 5.01494 17.8426 4.91583C17.5894 4.77024 17.2992
                      4.70174 17.0076 4.71872C16.8091 4.73029 16.6067 4.79777 16.2018 4.93273L15.6314
                      5.12287C14.9961 5.33464 14.3058 5.0145 14.0571 4.39272C13.8841 3.96016 13.7976 3.74388 13.6654
                      3.57511C13.4719 3.32808 13.2058 3.14794 12.9046 3.06005Z"
                      transform="translate(9.8 9.8) scale(3.35)"></path>
                    <circle cx="50" cy="50" r="20.5"
                      fill="url(#safe-center-gradient-<?= esc_attr($stepId); ?>)"></circle>
                  </g>
                  <text class="riddle-safe__display" x="50" y="51">
                    <tspan class="riddle-safe__direction">*</tspan><tspan class="riddle-safe__value">0</tspan>
                  </text>
                </svg>
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
          <?php elseif ($widgetView['type'] === 'piano') : ?>
            <?php if ($widgetView['limit_reached']) : ?>
              <p class="message-limite"><?= esc_html__('Limite quotidienne atteinte.', 'chassesautresor-com'); ?></p>
            <?php else : ?>
              <p class="riddle-piano__title"><?= esc_html__('Séquence de déverrouillage', 'chassesautresor-com'); ?></p>
              <input type="hidden" name="reponse" value="">
              <div class="riddle-piano" role="group" aria-label="<?= esc_attr__('Clavier de piano à deux octaves', 'chassesautresor-com'); ?>">
                <?php foreach ([1, 2] as $octave) : ?>
                  <div class="riddle-piano__octave">
                    <?php foreach (['C', 'D', 'E', 'F', 'G', 'A', 'B'] as $note) : ?>
                      <?php $noteId = $note . $octave; ?>
                      <button type="button" class="riddle-piano__key riddle-piano__key--white"
                        data-note="<?= esc_attr($noteId); ?>"
                        aria-label="<?= esc_attr(sprintf(__('Note %s', 'chassesautresor-com'), $noteId)); ?>">
                        <span aria-hidden="true"><?= esc_html($note); ?></span>
                      </button>
                    <?php endforeach; ?>
                    <?php foreach (['C' => 1, 'D' => 2, 'F' => 4, 'G' => 5, 'A' => 6] as $note => $position) : ?>
                      <?php $noteId = $note . '#' . $octave; ?>
                      <button type="button" class="riddle-piano__key riddle-piano__key--black"
                        data-position="<?= esc_attr($position); ?>" data-note="<?= esc_attr($noteId); ?>"
                        aria-label="<?= esc_attr(sprintf(__('Note %s', 'chassesautresor-com'), $noteId)); ?>">
                        <span aria-hidden="true"><?= esc_html($note . '#'); ?></span>
                      </button>
                    <?php endforeach; ?>
                    <span class="riddle-piano__octave-label" aria-hidden="true">
                      <?= esc_html(sprintf(__('Octave %d', 'chassesautresor-com'), $octave)); ?>
                    </span>
                  </div>
                <?php endforeach; ?>
              </div>
              <div class="riddle-piano__controls">
                <output class="riddle-piano__sequence" aria-live="polite"
                  aria-label="<?= esc_attr__('Séquence saisie : vide', 'chassesautresor-com'); ?>"></output>
                <button type="button" class="riddle-piano__play" disabled>
                  <?= esc_html__('Jouer la séquence', 'chassesautresor-com'); ?>
                </button>
                <div class="riddle-piano__actions riddle-widget-actions">
                  <button type="button" class="riddle-widget-reset" aria-label="<?= esc_attr__('Recommencer', 'chassesautresor-com'); ?>">↻</button>
                  <button type="submit" class="bouton-cta bouton-cta--color riddle-piano__submit" disabled>
                    <?= esc_html($widgetView['button_label']); ?>
                  </button>
                </div>
              </div>
            <?php endif; ?>
          <?php elseif ($widgetView['type'] === 'gps') : ?>
            <?php if ($widgetView['limit_reached']) : ?>
              <p class="message-limite"><?= esc_html__('Limite quotidienne atteinte.', 'chassesautresor-com'); ?></p>
            <?php else : ?>
              <p class="riddle-gps__title">
                <?= esc_html__('Placez le repère ou saisissez les coordonnées', 'chassesautresor-com'); ?>
              </p>
              <div
                class="riddle-gps__map"
                role="application"
                aria-label="<?= esc_attr__('Carte de sélection des coordonnées', 'chassesautresor-com'); ?>"
              ></div>
              <div class="riddle-gps__fields">
                <label>
                  <span><?= esc_html__('Latitude', 'chassesautresor-com'); ?></span>
                  <input type="number" class="riddle-gps__latitude" step="any" min="-90" max="90" required>
                </label>
                <label>
                  <span><?= esc_html__('Longitude', 'chassesautresor-com'); ?></span>
                  <input type="number" class="riddle-gps__longitude" step="any" min="-180" max="180" required>
                </label>
              </div>
              <input type="hidden" name="reponse" value="">
              <div class="riddle-widget-actions">
                <button type="button" class="riddle-gps-reset" aria-label="<?= esc_attr__('Recommencer', 'chassesautresor-com'); ?>">↻</button>
                <button type="submit" class="bouton-cta bouton-cta--color" disabled>
                  <?= esc_html($widgetView['button_label']); ?>
                </button>
              </div>
              <p class="txt-small riddle-gps__privacy">
                <?= esc_html__('Aucune position personnelle n’est demandée ni partagée.', 'chassesautresor-com'); ?>
              </p>
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
