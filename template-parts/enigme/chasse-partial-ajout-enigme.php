<?php
defined('ABSPATH') || exit;
/**
 * Template part : Carte d’ajout d’énigme
 *
 * Contexte attendu :
 * - $args['has_enigmes'] (bool) : indique s’il y a déjà des énigmes
 */

$has_enigmes = $args['has_enigmes'] ?? false;
$chasse_id   = $args['chasse_id'] ?? null;
$disabled    = $args['disabled'] ?? true;
if (!$chasse_id || get_post_type($chasse_id) !== 'chasse') return;

$ajout_url = esc_url(add_query_arg('chasse_id', $chasse_id, home_url('/creer-enigme/')));

?>

<a
  href="<?php echo $ajout_url; ?>"
  id="carte-ajout-enigme"
  class="carte-ajout-enigme <?php echo $has_enigmes ? 'etat-suivante' : 'etat-vide'; ?> <?php echo $disabled ? 'disabled' : ''; ?>"
  data-post-id="0">
  <div class="contenu-carte">
    ➕ <?php echo $has_enigmes ? 'Ajouter une énigme' : 'Créer la première énigme'; ?>
  </div>
  <div class="overlay-message">
    <i class="fa-solid fa-circle-info"></i>
    <p>Complétez d’abord : titre, image, description</p>
  </div>
</a>

