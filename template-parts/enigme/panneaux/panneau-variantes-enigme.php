<?php
defined('ABSPATH') || exit;

$enigme_id = $args['enigme_id'] ?? null;
if (!$enigme_id || get_post_type($enigme_id) !== 'enigme') return;

$variantes = get_field('enigme_reponse_variantes', $enigme_id) ?? [];
?>

<div id="panneau-variantes-enigme" class="panneau-lateral-liens panneau-lateral-large" aria-hidden="true">
  <div class="panneau-lateral__contenu">

    <div class="panneau-lateral__header">
      <button type="button" class="panneau-fermer" aria-label="Fermer les variantes">✖</button>
      <h2>Configurer les variantes de réponse</h2>
    </div>

    <?php if (empty($variantes)) : ?>
      <p class="champ-aide champ-variantes-aucune">Aucune variante définie pour l’instant. Commencez à saisir votre première variante ci-dessous.</p>
    <?php endif; ?>

    <form id="formulaire-variantes-enigme" data-post-id="<?= esc_attr($enigme_id); ?>" class="formulaire-variantes">

      <div class="liste-variantes-wrapper">

        <?php for ($i = 1; $i <= 4; $i++) :
          $champ = "variante_$i";
          $v = $variantes[$champ] ?? [];

          $texte     = $v["texte_$i"] ?? '';
          $message   = $v["message_$i"] ?? '';
          $casse     = $v["respecter_casse_$i"] ?? false;

          // Identifiants
          $prefix = "variante-$i";
          $inputTexte = "champ-$prefix-texte";
          $inputMessage = "champ-$prefix-message";
          $inputCasse = "champ-$prefix-casse";

          // Ne pas afficher de variantes vides SAUF la 1re ligne
          if ($i > 1 && empty($texte) && empty($message)) continue;
        ?>

          <div class="ligne-variante" data-index="<?= $i; ?>">
            <input type="text" name="<?= $inputTexte; ?>" class="champ-input input-texte" maxlength="75" placeholder="Texte de la variante" value="<?= esc_attr($texte); ?>">
            <input type="text" name="<?= $inputMessage; ?>" class="champ-input input-message" maxlength="100" placeholder="Message affiché au joueur" value="<?= esc_attr($message); ?>">

            <label class="label-casse">
              <input type="checkbox" name="<?= $inputCasse; ?>" <?= $casse ? 'checked' : ''; ?>>
              Respecter la casse
            </label>

            <button type="button" class="bouton-supprimer-ligne" aria-label="Supprimer cette variante">❌</button>
          </div>

        <?php endfor; ?>

      </div>

      <div class="ajout-variante-controls" style="margin-top: 20px; display: flex; flex-direction: column; gap: 8px;">
        <button type="button" id="bouton-ajouter-variante" class="bouton-enregistrer-description secondaire" style="align-self: start;">
          ➕ Ajouter une variante
        </button>
        <p class="message-limite-variantes txt-small" style="display: none; color: var(--color-editor-error); font-size: 0.9em;">
          4 variantes maximum
        </p>
      </div>


      <div class="panneau-lateral__actions">
        <button type="submit" class="bouton-enregistrer-description bouton-enregistrer-liens">💾 Enregistrer les variantes</button>
      </div>

      <div class="champ-feedback champ-feedback-variantes" style="display:none;"></div>

    </form>
  </div>
</div>