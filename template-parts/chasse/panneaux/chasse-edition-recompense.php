<?php
defined('ABSPATH') || exit;
$chasse_id = $args['chasse_id'] ?? null;
if (!$chasse_id || get_post_type($chasse_id) !== 'chasse') return;

$texte_recompense  = get_field('chasse_infos_recompense_texte', $chasse_id);
$valeur_recompense = get_field('chasse_infos_recompense_valeur', $chasse_id);
?>


<div id="panneau-recompense-chasse" class="panneau-lateral-liens panneau-lateral-large" aria-hidden="true" role="dialog">
  <div class="panneau-lateral__contenu">

    <header class="panneau-lateral__header">
      <h2>Configurer la récompense</h2>
      <button type="button" class="panneau-fermer" aria-label="Fermer le panneau">✖</button>
    </header>

    <div class="champ-wrapper" style="display: flex; flex-direction: column; gap: 20px;">
        
      <label for="champ-recompense-titre">Titre de la récompense <span class="champ-obligatoire">*</span></label>
      <input id="champ-recompense-titre" type="text" maxlength="40" placeholder="Ex : Un papillon en cristal..." value="<?= esc_attr(get_field('chasse_infos_recompense_titre', $chasse_id)); ?>">

      <label for="champ-recompense-texte">Descripton de la récompense <span class="champ-obligatoire">*</span></label>
      <textarea id="champ-recompense-texte" rows="4" placeholder="Ex : Un coffret cadeau comprenant..."><?= esc_textarea(wp_strip_all_tags($texte_recompense)); ?></textarea>


      <label for="champ-recompense-valeur">Valeur en euros (€) <span class="champ-obligatoire">*</span></label>
      <input id="champ-recompense-valeur" class="w-175" type="number" min="0" step="0.01" placeholder="Ex : 50" value="<?= esc_attr($valeur_recompense); ?>">

      <div class="panneau-lateral__actions">
        <button id="bouton-enregistrer-recompense" type="button" class="bouton-enregistrer-description bouton-enregistrer-liens">💾 Enregistrer</button>
      </div>
      <button type="button" id="bouton-supprimer-recompense" class="bouton-texte secondaire">
      ❌ Supprimer la récompense
    </button>

    </div>

  </div>
</div>
