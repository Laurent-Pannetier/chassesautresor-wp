<?php
defined('ABSPATH') || exit;

$organisateur_id = $args['organisateur_id'] ?? null;
if (!$organisateur_id || get_post_type($organisateur_id) !== 'organisateur') {
  return;
}

$query = get_chasses_de_organisateur($organisateur_id);
$posts = is_a($query, 'WP_Query') ? $query->posts : (array) $query;

// 🔒 Filtrer les chasses visibles selon leur statut et l'utilisateur courant
$user_id = get_current_user_id();
$posts   = array_values(array_filter($posts, function ($post) use ($user_id) {
  return chasse_est_visible_pour_utilisateur($post->ID, $user_id);
}));

?>

<h2>Ses chasses</h2>
<div class="separateur-2"></div>
<div class="grille-liste">
  <?php foreach ($posts as $post) : ?>
    <?php
    $chasse_id = $post->ID;
    $est_orga = est_organisateur();
    $voir_bordure = $est_orga && utilisateur_est_organisateur_associe_a_chasse(get_current_user_id(), $chasse_id);
    $classe_completion = '';
    if ($voir_bordure) {
      verifier_ou_mettre_a_jour_cache_complet($chasse_id);
      $complet = (bool) get_field('chasse_cache_complet', $chasse_id);
      $classe_completion = $complet ? 'carte-complete' : 'carte-incomplete';
    }
    get_template_part('template-parts/organisateur/organisateur-partial-chasse-card', null, [
      'chasse_id' => $chasse_id,
      'completion_class' => $classe_completion,
    ]);
    ?>
  <?php endforeach; ?>
</div>
