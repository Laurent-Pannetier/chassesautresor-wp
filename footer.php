<?php
/**
 * The template for displaying the footer.
 *
 * Contains the closing of the #content div and all content after.
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package Astra
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

?>
<?php astra_content_bottom(); ?>
	</div> <!-- ast-container -->
	</div><!-- #content -->
<?php
	astra_content_after();

	astra_footer_before();

	astra_footer();

	astra_footer_after();
?>
	</div><!-- #page -->
<?php
	astra_body_bottom();
	wp_footer();
?>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const bouton = document.querySelector('#creer-profil-btn');
    if (bouton) {
      bouton.addEventListener('click', function () {
        if (typeof gtag === 'function') {
          gtag('event', 'clic_creer_profil', {
            event_category: 'engagement',
            event_label: 'Page devenir organisateur',
            value: 1
          });
          console.log('✅ Événement gtag envoyé : clic_creer_profil');
        }
      });
    }
  });
</script>
	</body>
</html>
