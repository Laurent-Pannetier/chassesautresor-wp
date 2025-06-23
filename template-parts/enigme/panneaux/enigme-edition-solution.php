<?php
defined('ABSPATH') || exit;

$enigme_id = $args['enigme_id'] ?? null;
if (!$enigme_id || get_post_type($enigme_id) !== 'enigme') return;
?>

<div id="panneau-solution-enigme" class="panneau-lateral-liens panneau-lateral-large" aria-hidden="true" role="dialog">
  <div class="panneau-lateral__contenu">

    <header class="panneau-lateral__header">
      <h2>Rédiger la solution de cette énigme</h2>
      <button type="button" class="panneau-fermer" aria-label="Fermer le panneau">✖</button>
    </header>

    <div class="champ-wrapper">
      <?php
      acf_form([
        'post_id' => $enigme_id,
        'form' => true,
        'field_groups' => false,
        'fields' => [
          'enigme_solution_explication',
        ],
        'submit_value' => '💾 Enregistrer la solution',
        'return' => get_permalink($enigme_id) . '?maj=solution',
        'uploader' => 'basic',
        'label_placement' => 'top',
      ]);
      ?>
    </div>

  </div>
</div>