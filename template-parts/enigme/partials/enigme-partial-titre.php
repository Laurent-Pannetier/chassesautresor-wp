<?php
defined('ABSPATH') || exit;

$post_id = $args['post_id'] ?? null;
if (!$post_id) return;

$titre = get_the_title($post_id);
$legende = get_field('enigme_visuel_legende', $post_id);
?>

<?php if ($titre) : ?>
  <h1 class="titre-objet enigme-titre" data-cpt="enigme" data-post-id="<?= esc_attr($post_id); ?>">
    <?= esc_html($titre); ?>
  </h1>
<?php endif; ?>

<?php if ($legende) : ?>
  <div class="enigme-soustitre"><?= esc_html($legende); ?></div>
<?php endif; ?>