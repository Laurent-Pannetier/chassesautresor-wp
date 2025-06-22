<?php
defined('ABSPATH') || exit;

$organisateur_id = $args['organisateur_id'] ?? null;
if (!$organisateur_id || get_post_type($organisateur_id) !== 'organisateur') {
  return;
}

$query = get_chasses_de_organisateur($organisateur_id);
$posts = is_a($query, 'WP_Query') ? $query->posts : (array) $query;
?>

<div class="grille-liste">
  <?php foreach ($posts as $post) : ?>
    <?php
    $chasse_id = $post->ID;
    get_template_part('template-parts/organisateur/organisateur-partial-chasse-card', null, [
      'chasse_id' => $chasse_id,
    ]);
    ?>
  <?php endforeach; ?>
</div>
