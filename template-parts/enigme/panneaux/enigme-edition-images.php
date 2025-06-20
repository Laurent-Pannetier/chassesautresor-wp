<?php
defined('ABSPATH') || exit;

$enigme_id = $args['enigme_id'] ?? null;
if (!$enigme_id || get_post_type($enigme_id) !== 'enigme') return;
?>

<div id="panneau-images-enigme" class="panneau-lateral-liens panneau-lateral-large" aria-hidden="true">
  <div class="panneau-lateral__contenu">
    <div class="panneau-lateral__header">
      <button type="button" class="panneau-fermer" aria-label="Fermer le panneau">✖</button>
      <h2>Modifier les images de l’énigme</h2>
    </div>

    <?php
    acf_form([
      'post_id'             => $enigme_id,
      'fields'              => ['enigme_visuel_image'],
      'form'                => true,
      'submit_value'        => '💾 Enregistrer',
      'html_submit_button'  => '<div class="panneau-lateral__actions"><button type="submit" class="bouton-enregistrer-description bouton-enregistrer-liens">%s</button></div>',
      'html_before_fields'  => '<div class="champ-wrapper">',
      'html_after_fields'   => '</div>',
      'return'              => get_permalink() . '#images-enigme',
      'updated_message'     => __('Images mises à jour.', 'chassesautresor')
    ]);
    ?>
  </div>
</div>