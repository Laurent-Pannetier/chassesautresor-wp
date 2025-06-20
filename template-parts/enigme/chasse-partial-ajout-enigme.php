<?php
defined('ABSPATH') || exit;
/**
 * Template part : Carte d’ajout d’énigme
 *
 * Contexte attendu :
 * - $args['has_enigmes'] (bool) : indique s’il y a déjà des énigmes
 */

$has_enigmes = $args['has_enigmes'] ?? false;
$chasse_id = $args['chasse_id'] ?? null;
if (!$chasse_id || get_post_type($chasse_id) !== 'chasse') return;

$ajout_url = esc_url(add_query_arg('chasse_id', $chasse_id, home_url('/creer-enigme/')));

?>

<div class="carte-ajout-enigme <?php echo $has_enigmes ? 'etat-suivante' : 'etat-vide'; ?>">
  <div class="contenu-carte">
    <p class="texte-appel">
      <?php echo $has_enigmes
        ? 'Ajouter une nouvelle énigme'
        : 'Aucune énigme pour cette chasse pour le moment.'; ?>
    </p>
    <a href="<?php echo $ajout_url; ?>" class="bouton-principal">
      ➕ <?php echo $has_enigmes ? 'Ajouter une énigme' : 'Créer la première énigme'; ?>
    </a>
  </div>
</div>
