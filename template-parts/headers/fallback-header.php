<?php
defined( 'ABSPATH' ) || exit;

/**
 * Template part : Header fallback immersif
 *
 * Reçoit les paramètres via `$args` depuis `get_template_part()`.
 *
 * Variables attendues :
 * - $args['titre'] : Titre principal
 * - $args['sous_titre'] : Sous-titre (optionnel)
 * - $args['image_fond'] : URL d'image de fond (optimisée en .webp par ex.)
 */

if ( ! isset( $args ) || ! is_array( $args ) ) {
    return;
}

$titre       = isset( $args['titre'] ) ? esc_html( $args['titre'] ) : '';
$sous_titre  = isset( $args['sous_titre'] ) ? esc_html( $args['sous_titre'] ) : '';
$image_url   = isset( $args['image_fond'] ) ? esc_url( $args['image_fond'] ) : '';
?>

<section class="bandeau-hero fallback-header">
  <div class="hero-overlay" <?php if ( $image_url ) : ?>style="background-image: url('<?php echo $image_url; ?>');"<?php endif; ?>>
    <div class="contenu-hero">
      <?php if ( $titre ) : ?>
        <h1><?php echo $titre; ?></h1>
      <?php endif; ?>

      <?php if ( $sous_titre ) : ?>
        <p class="sous-titre"><?php echo $sous_titre; ?></p>
      <?php endif; ?>
    </div>
  </div>
</section>
