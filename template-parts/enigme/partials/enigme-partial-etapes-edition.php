<?php

defined('ABSPATH') || exit;

$riddleId = isset($args['enigme_id']) ? (int) $args['enigme_id'] : 0;
$editable = !empty($args['editable']);
$stepIds = $riddleId > 0
    ? (new ChassesAuTresor\Core\Content\RiddleStepQueryService())->findOrderedIds($riddleId)
    : [];
$structureLocked = false;
if ($riddleId > 0) {
    global $wpdb;
    $structureLocked = (new ChassesAuTresor\Core\Content\RiddleStepStructureLockService())->isLocked(
        $riddleId,
        static fn (string $field, int $postId) => get_field($field, $postId),
        static fn (int $id): bool => ChassesAuTresor\Core\Support\CoreServiceFactory::riddleStepProgress($wpdb)
            ->hasProgressForRiddle($id)
    );
}
?>
<section
  class="riddle-steps-editor"
  data-riddle-id="<?= esc_attr($riddleId); ?>"
  data-structure-locked="<?= $structureLocked ? '1' : '0'; ?>"
>
  <div class="riddle-steps-editor__overview">
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
    <?php if ($editable && !$structureLocked) : ?>
      <button type="button" class="bouton-secondaire riddle-step-add">
        <i class="fa-solid fa-plus" aria-hidden="true"></i>
        <?= esc_html__('Ajouter une étape', 'chassesautresor-com'); ?>
      </button>
    <?php endif; ?>
  </div>

  <?php if ($structureLocked) : ?>
    <p class="riddle-steps-editor__lock-notice">
      <i class="fa-solid fa-lock" aria-hidden="true"></i>
      <?= esc_html__(
          'Le parcours a commencé : les étapes sont figées, mais leur contenu reste modifiable.',
          'chassesautresor-com'
      ); ?>
    </p>
  <?php endif; ?>

  <ol
    class="riddle-steps-editor__list"
    data-empty="<?= esc_attr__('Aucune étape intermédiaire.', 'chassesautresor-com'); ?>"
  >
    <?php foreach ($stepIds as $index => $stepId) : ?>
      <?php
      $label = get_the_title($stepId);
      ?>
      <li
        class="riddle-step-card"
        data-step-id="<?= esc_attr($stepId); ?>"
        draggable="<?= $editable && !$structureLocked ? 'true' : 'false'; ?>"
      >
        <?php if ($editable && !$structureLocked) : ?>
          <span class="riddle-step-card__handle" aria-hidden="true">
            <i class="fa-solid fa-grip-vertical"></i>
          </span>
        <?php endif; ?>
        <span class="riddle-step-card__rank"><?= esc_html((string) ($index + 1)); ?></span>
        <span class="riddle-step-card__content">
          <strong><?= esc_html($label); ?></strong>
        </span>
        <?php if ($editable) : ?>
          <span class="riddle-step-card__actions">
            <button type="button" class="bouton-tertiaire riddle-step-edit">
              <?= esc_html__('Modifier', 'chassesautresor-com'); ?>
            </button>
            <?php if (!$structureLocked) : ?>
              <button type="button" class="bouton-texte secondaire riddle-step-delete">
                <?= esc_html__('Supprimer', 'chassesautresor-com'); ?>
              </button>
            <?php endif; ?>
          </span>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ol>
  <p class="riddle-steps-editor__feedback" role="status" aria-live="polite"></p>
  </div>

  <?php if ($editable) : ?>
    <form class="riddle-step-form" hidden>
      <div class="riddle-step-form__header">
        <button type="button" class="bouton-texte riddle-step-cancel">
          <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
          <?= esc_html__('Retour aux étapes', 'chassesautresor-com'); ?>
        </button>
        <h3 class="riddle-step-form__heading"><?= esc_html__('Nouvelle étape', 'chassesautresor-com'); ?></h3>
      </div>
      <input type="hidden" name="etape_id" value="">
      <div class="riddle-step-form__field">
        <label for="riddle-step-title"><?= esc_html__('Nom interne de l’étape', 'chassesautresor-com'); ?></label>
        <input id="riddle-step-title" name="titre" type="text" maxlength="120" required>
        <p class="txt-small"><?= esc_html__('Ce nom n’est jamais affiché aux joueurs.', 'chassesautresor-com'); ?></p>
      </div>
      <div class="riddle-step-form__field">
        <label id="riddle-step-content-label" for="riddle-step-content">
          <?= esc_html__('Texte de l’étape', 'chassesautresor-com'); ?>
        </label>
        <div
          class="riddle-step-form__content-editor"
          contenteditable="true"
          role="textbox"
          aria-labelledby="riddle-step-content-label"
          aria-multiline="true"
        ></div>
        <textarea id="riddle-step-content" name="contenu" hidden></textarea>
      </div>
      <div class="riddle-step-form__field">
        <label><?= esc_html__('Image de l’étape', 'chassesautresor-com'); ?></label>
        <input type="hidden" name="image_id" value="">
        <div class="riddle-step-form__image-preview"></div>
        <div class="riddle-step-form__image-actions">
          <button type="button" class="bouton-secondaire riddle-step-image-select">
            <?= esc_html__('Choisir une image', 'chassesautresor-com'); ?>
          </button>
          <button type="button" class="bouton-texte riddle-step-image-remove" hidden>
            <?= esc_html__('Retirer l’image', 'chassesautresor-com'); ?>
          </button>
        </div>
        <p class="txt-small">
          <?= esc_html__('Une étape doit contenir au moins un texte ou une image.', 'chassesautresor-com'); ?>
        </p>
      </div>
      <?php if (!$structureLocked) : ?>
        <fieldset class="riddle-step-form__field">
          <legend><?= esc_html__('Réponse de l’étape', 'chassesautresor-com'); ?></legend>
          <label for="riddle-step-widget"><?= esc_html__('Mode de réponse', 'chassesautresor-com'); ?></label>
          <select id="riddle-step-widget" name="widget">
            <option value="click"><?= esc_html__('Simple clic', 'chassesautresor-com'); ?></option>
          </select>
          <label for="riddle-step-button-label">
            <?= esc_html__('Libellé du bouton', 'chassesautresor-com'); ?>
          </label>
          <input
            id="riddle-step-button-label"
            name="button_label"
            type="text"
            maxlength="80"
            value="<?= esc_attr__('Continuer', 'chassesautresor-com'); ?>"
            required
          >
          <p class="txt-small">
            <?= esc_html__(
                'Le clic valide l’étape sans consommer de tentative.',
                'chassesautresor-com'
            ); ?>
          </p>
        </fieldset>
      <?php endif; ?>
      <p class="riddle-step-form__feedback" role="alert" aria-live="assertive"></p>
      <div class="riddle-step-form__actions">
        <button type="button" class="bouton-secondaire riddle-step-cancel">
          <?= esc_html__('Annuler', 'chassesautresor-com'); ?>
        </button>
        <button type="submit" class="bouton-principal">
          <?= esc_html__('Enregistrer l’étape', 'chassesautresor-com'); ?>
        </button>
      </div>
    </form>
  <?php endif; ?>
</section>
