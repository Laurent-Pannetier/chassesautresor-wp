<?php
defined('ABSPATH') || exit;

$enigme_id = $args['enigme_id'] ?? null;
if (!$enigme_id || get_post_type($enigme_id) !== 'enigme') return;

$variantes = [];
for ($i = 1; $i <= 4; $i++) {
    $variantes[$i] = [
        'texte'   => get_field("texte_{$i}", $enigme_id),
        'message' => get_field("message_{$i}", $enigme_id),
        'casse'   => get_field("respecter_casse_{$i}", $enigme_id)
    ];
}
?>

<div id="panneau-variantes-enigme" class="panneau-lateral-liens panneau-lateral-large" aria-hidden="true" role="dialog">
  <div class="panneau-lateral__contenu">

    <header class="panneau-lateral__header">
      <h2><?= esc_html__('Configurer les variantes de réponse', 'chassesautresor-com'); ?></h2>
      <button type="button" class="panneau-fermer" aria-label="Fermer les variantes">✖</button>
    </header>

    <?php if (empty($variantes)) : ?>
      <p class="champ-aide champ-variantes-aucune">Aucune variante définie pour l’instant. Commencez à saisir votre première variante ci-dessous.</p>
    <?php endif; ?>

    <form id="formulaire-variantes-enigme" data-post-id="<?= esc_attr($enigme_id); ?>" class="formulaire-variantes">

      <div class="liste-variantes-wrapper">

        <?php for ($i = 1; $i <= 4; $i++) :
          $v = $variantes[$i] ?? [];
          $texte   = $v['texte'] ?? '';
          $message = $v['message'] ?? '';
          $casse   = $v['casse'] ?? false;

          // Identifiants
          $prefix = "variante-$i";
          $inputTexte = "champ-$prefix-texte";
          $inputMessage = "champ-$prefix-message";
          $inputCasse = "champ-$prefix-casse";

          // Ne pas afficher de variantes vides SAUF la 1re ligne
          if ($i > 1 && empty($texte) && empty($message)) continue;
        ?>

          <div class="ligne-variante" data-index="<?= $i; ?>">
            <input type="text" name="<?= $inputTexte; ?>" class="champ-input input-texte" maxlength="75" placeholder="<?= esc_attr__('réponse déclenchant l\'affichage du message', 'chassesautresor-com'); ?>" value="<?= esc_attr($texte); ?>">
            <input type="text" name="<?= $inputMessage; ?>" class="champ-input input-message" maxlength="100" placeholder="<?= esc_attr__('Message affiché au joueur', 'chassesautresor-com'); ?>" value="<?= esc_attr($message); ?>">

            <label class="label-casse">
              <input type="checkbox" name="<?= $inputCasse; ?>" <?= $casse ? 'checked' : ''; ?>>
              <?= esc_html__('Respecter la casse', 'chassesautresor-com'); ?>
            </label>

            <button type="button" class="bouton-supprimer-ligne" aria-label="Supprimer cette variante">❌</button>
          </div>

        <?php endfor; ?>

      </div>

      <div class="ajout-variante-controls" style="margin-top: 20px; display: flex; flex-direction: column; gap: 8px;">
        <button type="button" id="bouton-ajouter-variante" class="bouton-enregistrer-description secondaire" style="align-self: start;">
          ➕ <?= esc_html__('Ajouter une variante', 'chassesautresor-com'); ?>
        </button>
        <p class="message-limite-variantes txt-small" style="display: none; color: var(--color-editor-error); font-size: 0.9em;">
          4 variantes maximum
        </p>
      </div>


      <div class="panneau-lateral__actions">
        <button type="submit" class="bouton-enregistrer-description bouton-enregistrer-liens">
          💾 <?= esc_html__('Enregistrer les variantes', 'chassesautresor-com'); ?>
        </button>
      </div>

      <div class="champ-feedback champ-feedback-variantes" style="display:none;"></div>

    </form>
  </div>
</div>
