<?php
defined('ABSPATH') || exit;

$post_id = $args['post_id'] ?? null;
if (!$post_id) return;

// Préparer les données
$mode = get_field('enigme_solution_mode', $post_id) ?? 'pdf';
$delai = (int) get_field('enigme_solution_delai', $post_id);
$heure = get_field('enigme_solution_heure', $post_id);
$texte = get_field('enigme_solution_explication', $post_id);
$fichier = get_field('enigme_solution_fichier', $post_id);
$fichier_url = is_array($fichier) ? $fichier['url'] ?? '' : '';

// 🚧 Placeholder pour l’instant – conditions à implémenter plus tard
$conditions_ok = true; // TODO: vérifier chasse terminée + délai dépassé

if (!$conditions_ok) return;

// Si rien à afficher malgré conditions OK
if ($mode === 'pdf' && !$fichier_url) return;
if ($mode === 'texte' && !$texte) return;

echo '<div class="bloc-solution">';
echo '<h2>🧠 Solution de l’énigme</h2>';

if ($mode === 'pdf' && $fichier_url) {
  echo '<p><a href="' . esc_url($fichier_url) . '" target="_blank" class="lien-solution-pdf">📄 Télécharger la solution (PDF)</a></p>';
} elseif ($mode === 'texte' && $texte) {
  echo '<div class="contenu-solution">';
  echo wp_kses_post($texte);
  echo '</div>';
} else {
  echo '<p class="placeholder-solution">❌ Aucune solution disponible pour cette énigme.</p>';
}

echo '</div>';
?>
