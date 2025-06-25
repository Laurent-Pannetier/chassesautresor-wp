<?php
defined('ABSPATH') || exit;


/* page avec bandeau-hero */
add_filter('body_class', function ($classes) {
  $classes[] = 'has-hero';
  return $classes;
});


/**
 * Template Name: Devenir Organisateur
 * Description: Page immersive de création d'un espace organisateur.
 *
 * Comportement :
 * - Si aucun CPT "organisateur" n'existe → création automatique d'un brouillon
 * - Si statut = "draft" → canevas affiché
 * - Si statut = "pending" → redirection vers prévisualisation
 * - Si statut = "publish" → redirection vers la page publique
 */

acf_form_head(); // ✅ nécessaire pour ACF frontend


// 🔄 Initialisation de l’organisateur si nécessaire
$user_id = get_current_user_id();
$organisateur_id = get_organisateur_from_user($user_id);

// 👉 maintenant que le CPT existe (ou pas), on peut rediriger
rediriger_selon_etat_organisateur();

// Image par défaut au cas où aucune miniature n'est définie
$image_url = '';

if (has_post_thumbnail()) {
  $image_url = get_the_post_thumbnail_url(null, 'full'); // ou 'large' si besoin
}

get_header(); ?>
<?php if (isset($_GET['notice']) && $_GET['notice'] === 'profil_verification') : ?>
<div class="woocommerce-message" role="alert">
  ✉️ Un email de vérification vous a été envoyé. Veuillez cliquer sur le lien pour confirmer votre demande.
</div>
<?php endif; ?>
<section class="bandeau-hero">
  <div class="hero-overlay" style="background-image: url('<?php echo esc_url($image_url); ?>');">
    <div class="contenu-hero">
      <h1><?php the_title(); ?></h1>
      <p class="sous-titre">Créez, publiez et partagez vos aventures interactives.</p>
      <?php $cta = get_cta_devenir_organisateur(); ?>
      <a href="<?php echo $cta['url'] ? esc_url($cta['url']) : '#'; ?>" class="bouton-cta" id="creer-profil-btn" data-event="clic_creer_profil" <?php echo $cta['disabled'] ? 'style="pointer-events:none;opacity:0.6"' : ''; ?>>
        <?php echo esc_html($cta['label']); ?>
      </a>
    </div>
  </div>
</section>
<main id="primary" class="site-main conteneur-devenir-organisateur">
    <?php
      while ( have_posts() ) :
        the_post();
        the_content();
      endwhile;

      // Ajout de la section "comment-ca-fonctionne"
      $fonctionnement_post = get_page_by_path('comment-ca-fonctionne', OBJECT, 'section_editoriale');
      if ($fonctionnement_post) {
        echo apply_filters('the_content', $fonctionnement_post->post_content);
      }

      // Ajout de la section "temoignages-organisateurs"
      $temoignages_post = get_page_by_path('temoignages-organisateurs', OBJECT, 'section_editoriale');
      if ($temoignages_post) {
         echo apply_filters('the_content', $temoignages_post->post_content);
      }
      // Ajout de la section "cta-final"
      $cta_final_post = get_page_by_path('cta-final-devenir-organisateur', OBJECT, 'section_editoriale');
      if ($cta_final_post) {
         $content = apply_filters('the_content', $cta_final_post->post_content);
         $cta     = get_cta_devenir_organisateur();
         $content = str_replace('/creer-mon-profil/', $cta['url'], $content);
         $content = str_replace('Créer mon profil', $cta['label'], $content);
         echo $content;
      }
    ?>
</main>



<?php get_footer(); ?>
