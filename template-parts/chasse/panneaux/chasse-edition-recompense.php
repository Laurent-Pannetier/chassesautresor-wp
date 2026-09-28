<?php
defined('ABSPATH') || exit;
$chasse_id = $args['chasse_id'] ?? null;
if (!$chasse_id || get_post_type($chasse_id) !== 'chasse') return;

$texte_recompense  = get_field('chasse_infos_recompense_texte', $chasse_id, false);
$valeur_recompense = get_field('chasse_infos_recompense_valeur', $chasse_id);
?>


<div id="panneau-recompense-chasse" class="panneau-lateral-liens panneau-lateral-large" aria-hidden="true" role="dialog">
  <div class="panneau-lateral__contenu">

    <header class="panneau-lateral__header">
      <h2><?= esc_html__('Configurer la récompense', 'chassesautresor-com'); ?></h2>
      <button type="button" class="panneau-fermer" aria-label="<?= esc_attr__('Fermer le panneau', 'chassesautresor-com'); ?>">✖</button>
    </header>

    <div class="champ-wrapper" style="display: flex; flex-direction: column; gap: 20px;">
        
      <label for="champ-recompense-titre"><?= esc_html__('Titre de la récompense', 'chassesautresor-com'); ?> <span class="champ-obligatoire">*</span></label>
      <input id="champ-recompense-titre" type="text" maxlength="40" placeholder="<?= esc_attr__('Ex : Un papillon en cristal...', 'chassesautresor-com'); ?>" value="<?= esc_attr(get_field('chasse_infos_recompense_titre', $chasse_id)); ?>">

      <label for="champ-recompense-texte"><?= esc_html__('Descripton de la récompense', 'chassesautresor-com'); ?> <span class="champ-obligatoire">*</span></label>
      <textarea id="champ-recompense-texte" rows="4" placeholder="<?= esc_attr__('Ex : Un coffret cadeau comprenant...', 'chassesautresor-com'); ?>"><?= esc_textarea(wp_strip_all_tags($texte_recompense)); ?></textarea>


      <label for="champ-recompense-valeur"><?= esc_html__('Valeur en euros (€)', 'chassesautresor-com'); ?> <span class="champ-obligatoire">*</span></label>
      <?php
      $valeur_formatee = $valeur_recompense !== '' && $valeur_recompense !== null
        ? number_format((float) $valeur_recompense, 2, ',', ' ')
        : '';
      ?>
      <input id="champ-recompense-valeur" class="w-175" type="text" inputmode="decimal" placeholder="<?= esc_attr__('Ex : 50', 'chassesautresor-com'); ?>" value="<?= esc_attr($valeur_formatee); ?>">

      <div class="panneau-lateral__actions">
        <button id="bouton-enregistrer-recompense" type="button" class="bouton-enregistrer-description bouton-enregistrer-liens">💾 <?= esc_html__('Enregistrer', 'chassesautresor-com'); ?></button>
      </div>
      <button type="button" id="bouton-supprimer-recompense" class="bouton-texte secondaire">
      ❌ <?= esc_html__('Supprimer la récompense', 'chassesautresor-com'); ?>
    </button>

    </div>

  </div>
</div>
