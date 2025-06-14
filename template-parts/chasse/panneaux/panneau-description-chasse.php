<?php
/**
 * Template Part: Panneau d'édition de la description d'une chasse (WYSIWYG)
 * Reprend exactement le modèle organisateur
 * Appelé depuis n'importe quel template affichant une chasse.
 * Requiert : $args['chasse_id']
 */

defined('ABSPATH') || exit;

$chasse_id = $args['chasse_id'] ?? null;
if (!$chasse_id || get_post_type($chasse_id) !== 'chasse') return;
?>

<div id="panneau-description-chasse" class="panneau-lateral-liens panneau-lateral-large" aria-hidden="true">
  <div class="panneau-lateral__contenu">

    <div class="panneau-lateral__header">
      <button type="button" class="panneau-fermer" aria-label="Fermer le panneau">✖</button>
      <h2>Modifier la description de la chasse</h2>
    </div>

    <?php
    acf_form([
      'post_id'             => $chasse_id,
      'fields'              => ['chasse_principale_description'],
      'form'                => true,
      'submit_value'        => '💾 Enregistrer',
      'html_submit_button'  => '<div class="panneau-lateral__actions"><button type="submit" class="bouton-enregistrer-description bouton-enregistrer-liens">%s</button></div>',
      'html_before_fields'  => '<div class="champ-wrapper">',
      'html_after_fields'   => '</div>',
      'return'              => get_permalink() . '#chasse-description',
      'updated_message'     => __('Description mise à jour.', 'chassesautresor')
    ]);
    ?>

  </div>
</div>