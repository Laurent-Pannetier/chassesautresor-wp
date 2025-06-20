<?php
/**
 * Template Part: Panneau d'édition du texte principal d'une énigme (WYSIWYG)
 * Basé sur le modèle chasse / organisateur
 * Requiert : $args['enigme_id']
 */

defined('ABSPATH') || exit;

$enigme_id = $args['enigme_id'] ?? null;
if (!$enigme_id || get_post_type($enigme_id) !== 'enigme') return;
?>

<div id="panneau-description-enigme" class="panneau-lateral-liens panneau-lateral-large" aria-hidden="true">
  <div class="panneau-lateral__contenu">

    <div class="panneau-lateral__header">
      <button type="button" class="panneau-fermer" aria-label="Fermer le panneau">✖</button>
      <h2>Modifier le texte de l’énigme</h2>
    </div>

    <?php
    acf_form([
      'post_id'             => $enigme_id,
      'fields'              => ['enigme_visuel_texte'],
      'form'                => true,
      'submit_value'        => '💾 Enregistrer',
      'html_submit_button'  => '<div class="panneau-lateral__actions"><button type="submit" class="bouton-enregistrer-description bouton-enregistrer-liens">%s</button></div>',
      'html_before_fields'  => '<div class="champ-wrapper">',
      'html_after_fields'   => '</div>',
      'return' => add_query_arg('panneau', 'description-enigme', get_permalink()),
      'updated_message'     => __('Texte de l’énigme mis à jour.', 'chassesautresor')
    ]);
    ?>

  </div>
</div>
