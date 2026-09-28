<?php
/**
 * Hero section highlighting the latest validated hunt on the homepage.
 */

defined('ABSPATH') || exit;

if (!isset($args['chasse_id'])) {
    return;
}

$chasse_id   = (int) $args['chasse_id'];
$titre       = isset($args['titre']) ? sanitize_text_field($args['titre']) : get_the_title($chasse_id);
$image_fond  = isset($args['image_fond']) ? esc_url($args['image_fond']) : get_the_post_thumbnail_url($chasse_id, 'chasse-fiche');
$description = isset($args['description']) ? sanitize_text_field($args['description']) : '';
$cta_html    = $args['cta_html'] ?? '';
$cta_message = $args['cta_message'] ?? '';
?>
<section class="bandeau-hero bandeau-hero--latest-chasse" data-home-hero="latest" aria-hidden="true">
  <div class="hero-overlay"<?php if ($image_fond) : ?> style="background-image: url('<?php echo esc_url($image_fond); ?>');"<?php endif; ?>>
    <div class="contenu-hero">
      <p class="hero-eyebrow"><?php esc_html_e('à la une', 'chassesautresor-com'); ?></p>
      <h2 class="hero-title"><?php echo esc_html($titre); ?></h2>
      <?php if ($description) : ?>
        <p class="hero-description"><?php echo esc_html($description); ?></p>
      <?php endif; ?>
      <div class="hero-cta">
        <?php if (!empty($cta_html)) : ?>
          <?php echo $cta_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <?php endif; ?>
        <?php if (!empty($cta_message)) : ?>
          <div class="hero-cta__message"><?php echo wp_kses_post($cta_message); ?></div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
