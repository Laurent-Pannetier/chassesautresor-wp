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
$user_id = get_current_user_id();
$roles = (array) wp_get_current_user()->roles;
$is_owner = $user_id && (int) get_post_field('post_author', $organisateur_id) === $user_id;
if ($is_owner && in_array(ROLE_ORGANISATEUR_CREATION, $roles, true)) {
    verifier_ou_mettre_a_jour_cache_complet($organisateur_id);
}
$peut_modifier = utilisateur_peut_modifier_post($organisateur_id);
$image_logo = get_the_post_thumbnail_url($organisateur_id, 'medium_large');
$nom_organisateur = get_the_title($organisateur_id);
$site_internet = get_field('communication_site_internet', $organisateur_id);
$reseaux_sociaux = get_field('communication_reseaux_sociaux', $organisateur_id);
$statut_organisateur = get_post_status($organisateur_id);
$coordonnees = get_field('coordonnees_bancaires', $organisateur_id);
$iban = $coordonnees['iban'] ?? '';
$bic  = $coordonnees['bic'] ?? '';

// Vérification si l'organisateur a une description publique remplie
$description = get_field('description_longue', $organisateur_id);

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

        <?php
        $afficher_bienvenue =
            !empty($_GET['confirmation']) &&
            is_user_logged_in() &&
            get_current_user_id() === (int) get_post_field('post_author', $organisateur_id) &&
            $statut_organisateur === 'pending' &&
            !organisateur_a_des_chasses($organisateur_id);
        ?>

        <!-- Présentation -->
        <section class="presentation">
            <div class="conteneur">
                <div id="alerte-incomplete" class="alerte-discret" style="display: none;">
                    ⚠️ test
                </div>

                <?php if ($description) : ?>
                    <?php echo limiter_texte_avec_toggle($description, 300); ?>
                <?php endif; ?>

            </div>
        </section>
        <!-- Section Chasses -->
        <section class="chasses">
            <div class="conteneur">
                <div class="titre-chasses-wrapper">
                    <h2>Ses chasses</h2>
                    <div class="separateur-2"></div>
                    <div class="ligne-chasses"></div>
                    <div class="liste-chasses">
                        <div class="grille-3">
                            <?php
                            $organisateur_id = get_the_ID();
                            $query = get_chasses_de_organisateur($organisateur_id);
                            $chasses = is_a($query, 'WP_Query') ? $query->posts : (array) $query;
                            $user_id = get_current_user_id();
                            $chasses = array_values(array_filter($chasses, function ($post) use ($user_id) {
                                return chasse_est_visible_pour_utilisateur($post->ID, $user_id);
                            }));
                            $peut_ajouter = utilisateur_peut_ajouter_chasse($organisateur_id);
                            $has_chasses = !empty($chasses);

                            foreach ($chasses as $post) :
                                $chasse_id = $post->ID;

                            ?>
                                <article class="carte-chasse" data-post-id="<?= esc_attr($chasse_id); ?>">
                                    <div class="carte-core">
                                        <?php afficher_picture_vignette_chasse($chasse_id); ?>
                                        <h2><?= esc_html(get_the_title($chasse_id)); ?></h2>
                                    </div>
                                </article>
                            <?php endforeach; ?>

                            <?php if ($peut_ajouter) :
                                get_template_part('template-parts/chasse/chasse-partial-ajout-chasse', null, [
                                    'has_chasses' => $has_chasses,
                                    'organisateur_id' => $organisateur_id,
                                ]);
                            endif; ?>
                        </div>
                    </div>
                </div>
        </section>

    </main>
</div>

<?php
if ($afficher_bienvenue) :

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
                modal.style.display = 'flex';

                fermer.addEventListener('click', function() {
                    modal.remove();
                });

                modal.addEventListener('click', function(e) {
                    if (e.target === modal) {
                        e.stopPropagation();
                    }
                });
                const fermerHaut = document.querySelector('.modal-close-top');
                fermerHaut?.addEventListener('click', function() {
                    modal.remove();
                });
            });
        </script>
<?php
    endif;
endif;
?>


<?php get_footer(); ?>