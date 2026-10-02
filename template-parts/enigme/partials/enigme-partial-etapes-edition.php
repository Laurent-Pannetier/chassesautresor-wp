<?php

defined('ABSPATH') || exit;

$riddleId = isset($args['enigme_id']) ? (int) $args['enigme_id'] : 0;
$editable = !empty($args['editable']);
$stepIds = $riddleId > 0
    ? (new ChassesAuTresor\Core\Content\RiddleStepQueryService())->findOrderedIds($riddleId)
    : [];
?>
<section class="riddle-steps-editor" data-riddle-id="<?= esc_attr($riddleId); ?>">
  <div class="riddle-steps-editor__header">
    <div>
      <h3><?= esc_html__('Étapes intermédiaires', 'chassesautresor-com'); ?></h3>
      <p class="txt-small">
        <?= esc_html__(
            'Les étapes sont affichées dans cet ordre et débloquent ensuite la réponse finale.',
            'chassesautresor-com'
        ); ?>
      </p>
    </div>
    <?php if ($editable) : ?>
      <button type="button" class="bouton-secondaire riddle-step-add">
        <i class="fa-solid fa-plus" aria-hidden="true"></i>
        <?= esc_html__('Ajouter une étape', 'chassesautresor-com'); ?>
      </button>
    <?php endif; ?>
  </div>

  <ol
    class="riddle-steps-editor__list"
    data-empty="<?= esc_attr__('Aucune étape intermédiaire.', 'chassesautresor-com'); ?>"
  >
    <?php foreach ($stepIds as $index => $stepId) : ?>
      <?php
      $label = trim((string) get_field('etape_libelle', $stepId));
      $label = $label !== '' ? $label : get_the_title($stepId);
      $widget = (string) get_field('etape_widget_type', $stepId);
      $editLink = get_edit_post_link($stepId, 'raw');
      ?>
      <li
        class="riddle-step-card"
        data-step-id="<?= esc_attr($stepId); ?>"
        draggable="<?= $editable ? 'true' : 'false'; ?>"
      >
        <span class="riddle-step-card__handle" aria-hidden="true">
          <i class="fa-solid fa-grip-vertical"></i>
        </span>
        <span class="riddle-step-card__rank"><?= esc_html((string) ($index + 1)); ?></span>
        <span class="riddle-step-card__content">
          <strong><?= esc_html($label); ?></strong>
          <span class="txt-small">
            <?= esc_html(
                $widget === 'directions_8'
                    ? __('8 directions', 'chassesautresor-com')
                    : __('Texte', 'chassesautresor-com')
            ); ?>
          </span>
        </span>
        <?php if ($editable) : ?>
          <span class="riddle-step-card__actions">
            <?php if ($editLink) : ?>
              <a class="bouton-tertiaire" href="<?= esc_url($editLink); ?>">
                <?= esc_html__('Configurer', 'chassesautresor-com'); ?>
              </a>
            <?php endif; ?>
            <button type="button" class="bouton-texte secondaire riddle-step-delete">
              <?= esc_html__('Supprimer', 'chassesautresor-com'); ?>
            </button>
          </span>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ol>
  <p class="riddle-steps-editor__feedback" role="status" aria-live="polite"></p>
</section>
