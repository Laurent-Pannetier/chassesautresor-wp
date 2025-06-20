<?php
defined('ABSPATH') || exit;

$post_id = $args['post_id'] ?? null;
if (!$post_id) return;

$texte = get_field('enigme_visuel_texte', $post_id);
?>

<?php if ($texte) : ?>
  <div class="enigme-texte"><?= wp_kses_post($texte); ?></div>
<?php endif; ?>
