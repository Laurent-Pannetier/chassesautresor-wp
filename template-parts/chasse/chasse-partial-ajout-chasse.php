<?php
defined('ABSPATH') || exit;

$organisateur_id = $args['organisateur_id'] ?? null;
$has_chasses = $args['has_chasses'] ?? false;
$description_remplie = $args['description_remplie'] ?? false;


if (!$organisateur_id || get_post_type($organisateur_id) !== 'organisateur') {
  return;
}
?>

<a
  href="<?= esc_url(site_url('/creer-chasse/')); ?>"
  id="carte-ajout-chasse"
  class="carte-chasse carte-ajout-chasse disabled"
  data-post-id="0"
  data-description-remplie="<?= $description_remplie ? '1' : '0'; ?>">


  <div class="carte-chasse-contenu">
    <div class="icone-ajout">
      <i class="fa-solid fa-circle-plus fa-3x"></i>
    </div>
    <h2><?= $has_chasses ? 'Ajouter une nouvelle chasse' : 'Créer ma première chasse'; ?></h2>
  </div>

  <div class="overlay-message">
    <i class="fa-solid fa-circle-info"></i>
    <p>Complétez d’abord : titre, logo, description</p>
  </div>
</a>