<?php
/**
 * Template Name: Confirmation Organisateur
 * Description: Présente le parcours de confirmation d'un profil organisateur.
 */

defined('ABSPATH') || exit;

get_header();
?>
<div class="conteneur-confirmation">
    <p>
        <?php
        esc_html_e(
            'La demande de confirmation a été prise en charge. Vous allez être redirigé.',
            'chassesautresor-com'
        );
        ?>
    </p>
</div>
<?php get_footer();
