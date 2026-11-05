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

// 👉 l'accès à cette page est désormais autorisé pour les organisateurs ayant
// au moins une chasse non en attente de validation. La redirection automatique
// n'est donc plus nécessaire et est gérée via le CTA dynamique.

// Image par défaut au cas où aucune miniature n'est définie
$image_url = '';

if (has_post_thumbnail()) {
  $image_url = get_the_post_thumbnail_url(null, 'full'); // ou 'large' si besoin
}

$separator_icon_markup = get_svg_icon('separateur-avec-icone');

$inject_separator_icon = static function (string $content) use ($separator_icon_markup): string {
    if ($separator_icon_markup === '') {
        return $content;
    }

    $pattern = '#(<div[^>]*class="[^"]*\\bseparateur-avec-icone\\b[^"]*"[^>]*>.*?<span[^>]*class="[^"]*\\bicone-svg\\b[^"]*"[^>]*>)(.*?)(</span>)#si';
    $updated = preg_replace_callback(
        $pattern,
        static function (array $matches) use ($separator_icon_markup): string {
            return $matches[1] . $separator_icon_markup . $matches[3];
        },
        $content
    );

    if (is_string($updated)) {
        return $updated;
    }

    return $content;
};

get_header(); ?>
<section class="bandeau-hero">
  <div class="hero-overlay" style="background-image: url('<?php echo esc_url($image_url); ?>');">
    <div class="contenu-hero">
      <h1 class="hero-title"><?php the_title(); ?></h1>
      <p class="hero-subtitle">Créez, publiez et partagez vos aventures interactives.</p>
      <?php
      $cta = get_cta_devenir_organisateur();
      $cta_class = 'bouton-cta';
      if (!$cta['disabled']) {
          $cta_class .= ' bouton-cta--color';
      }
      ?>
      <a href="<?php echo $cta['url'] ? esc_url($cta['url']) : '#'; ?>" class="<?php echo esc_attr($cta_class); ?>" id="creer-profil-btn" data-event="clic_creer_profil" <?php echo $cta['disabled'] ? 'style="pointer-events:none;opacity:0.6"' : ''; ?>>
        <?php echo esc_html($cta['label']); ?>
      </a>
    </div>
  </div>
</section>
<section class="msg-important"><?php print_site_messages(); ?></section>
<main id="primary" class="site-main conteneur-devenir-organisateur">
    <?php
      while ( have_posts() ) :
        the_post();
        $content = apply_filters('the_content', get_the_content());
        echo $inject_separator_icon($content); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
      endwhile;

      // Ajout de la section "comment-ca-fonctionne"
      $fonctionnement_post = get_page_by_path('comment-ca-fonctionne', OBJECT, 'section_editoriale');
      if ($fonctionnement_post) {
        $content = apply_filters('the_content', $fonctionnement_post->post_content);
        echo $inject_separator_icon($content); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
      }

      // Ajout de la section "temoignages-organisateurs"
      $temoignages_post = get_page_by_path('temoignages-organisateurs', OBJECT, 'section_editoriale');
      if ($temoignages_post) {
         $content = apply_filters('the_content', $temoignages_post->post_content);
         echo $inject_separator_icon($content); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
      }
      // Ajout de la section "cta-final"
      $cta_final_post = get_page_by_path('cta-final-devenir-organisateur', OBJECT, 'section_editoriale');
      if ($cta_final_post) {
         $content = apply_filters('the_content', $cta_final_post->post_content);
         $cta     = get_cta_devenir_organisateur();
         $content = str_replace('/creer-mon-profil/', $cta['url'], $content);
         $content = str_replace('Créer mon profil', $cta['label'], $content);
         if (!$cta['disabled']) {
             $content = str_replace('bouton-cta"', 'bouton-cta bouton-cta--color"', $content);
         }
         echo $inject_separator_icon($content); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
      }
    ?>
</main>



<?php get_footer(); ?>
