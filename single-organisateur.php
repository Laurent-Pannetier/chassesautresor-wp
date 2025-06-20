<?php
defined('ABSPATH') || exit;
/**
 * Template pour les pages des organisateurs (CPT "organisateur").
 */

// ==================================================
// 📩 GESTION DE L’ENDPOINT /contact
// ==================================================

// Si l’URL actuelle correspond à /contact, on affiche uniquement le formulaire
if (preg_match('#/contact($|[/?])#', $_SERVER['REQUEST_URI'])) {
    get_template_part('template-parts/organisateur/organisateur-partial-contact-form');
    return;
}



acf_form_head(); // <-- doit être ici, AVANT get_header() !

global $post;
$organisateur_id = $post->ID;
$peut_modifier = utilisateur_peut_modifier_post($organisateur_id);
$image_logo = get_the_post_thumbnail_url($organisateur_id, 'medium_large');
$nom_organisateur = get_the_title($organisateur_id);
$description = get_field('parlez_de_vous_presentation', $organisateur_id);
$site_internet = get_field('communication_site_internet', $organisateur_id);
$reseaux_sociaux = get_field('communication_reseaux_sociaux', $organisateur_id);
$statut_organisateur = get_post_status($organisateur_id);
$chasses_query = get_chasses_de_organisateur($organisateur_id);
$coordonnees = get_field('coordonnees_bancaires', $organisateur_id);
$iban = $coordonnees['iban'] ?? '';
$bic  = $coordonnees['bic'] ?? '';

get_header();

?>

<div id="primary" class="content-area">
    <main id="organisateur-page">
        <!-- Header de l'organisateur -->
        <?php
        // Évite de doubler le header si on est sur /contact
        if (!preg_match('#/contact/?$#', $_SERVER['REQUEST_URI'])) : ?>
            <?php
            get_template_part('template-parts/organisateur/organisateur-header', null, [
                'organisateur_id' => $organisateur_id
            ]);
            ?>
        <?php endif; ?>





        <!-- Présentation -->
        <section class="presentation">
            <div class="conteneur">
                <div id="alerte-incomplete" class="alerte-discret" style="display: none;">
                    ⚠️ test
                </div>
                <?php if (utilisateur_peut_creer_post('chasse')) : ?>

                    <div id="cta-creer-chasse" class="cta-chasse" style="display: none;">
                        <p>✅ Tous les champs obligatoires sont remplis.</p>
                        <a href="<?= esc_url(site_url('/creer-chasse/')); ?>" class="bouton-cta">Créer ma première chasse</a>
                    </div>
                <?php endif; ?>


                <?php if ($description) : ?>
                    <?php echo limiter_texte_avec_toggle($description, 300); ?>
                <?php endif; ?>

            </div>
        </section>
        <!-- Section Chasses -->
        <section class="chasses">
            <div class="conteneur">
                <div class="titre-chasses-wrapper">
                    <div class="ligne-chasses"></div>
                    <div class="ligne-chasses"></div>
                </div>

                <div class="liste-chasses">
                    <?php if ($chasses_query->have_posts()) : ?>
                        <?php while ($chasses_query->have_posts()) : $chasses_query->the_post(); ?>
                            <?php
                            $chasse_id = get_the_ID();
                            get_template_part('template-parts/organisateur-partial-chasse-card', null, ['chasse_id' => $chasse_id]);
                            ?>
                        <?php endwhile; ?>
                        <?php wp_reset_postdata(); ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>
</div>



<?php
if (
    is_user_logged_in() &&
    get_current_user_id() === (int) get_post_field('post_author', $organisateur_id) &&
    $statut_organisateur === 'pending' &&
    !organisateur_a_des_chasses($organisateur_id)
) :

    $bienvenue_post = get_page_by_path('bienvenue-page-organisateur', OBJECT, 'section_editoriale');
    if ($bienvenue_post) :
        $contenu_html = apply_filters('the_content', $bienvenue_post->post_content);
?>
        <div id="modal-bienvenue" class="modal-bienvenue" style="display: none;">
            <div class="modal-contenu">
                <?php echo $contenu_html; ?>
                <div class="boutons-modal">
                    <button id="fermer-modal-bienvenue" class="btn-fermer">C'est parti !</button>
                </div>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const modal = document.getElementById('modal-bienvenue');
                const fermer = document.getElementById('fermer-modal-bienvenue');

                if (!localStorage.getItem('modalBienvenueAffiche')) {
                    modal.style.display = 'flex';
                }

                fermer.addEventListener('click', function() {
                    modal.style.display = 'none';
                    localStorage.setItem('modalBienvenueAffiche', 'true');
                });

                // Ne pas permettre la fermeture en cliquant à l'extérieur
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) {
                        e.stopPropagation();
                    }
                });
                const fermerHaut = document.querySelector('.modal-close-top');

                fermerHaut.addEventListener('click', function() {
                    modal.style.display = 'none';
                    localStorage.setItem('modalBienvenueAffiche', 'true');
                });

            });
        </script>
<?php
    endif;
endif;
?>


<?php get_footer(); ?>