<?php
// ==================================================
// 📩 FORMULAIRE DE CONTACT ORGANISATEUR
// ==================================================
/**
 * Template de la page de contact pour un organisateur.
 * Affiche le formulaire WPForms (ID 8792) avec titre et wrapper.
 * Accessible uniquement aux utilisateurs connectés.
 */

// 🔒 Redirection si l’utilisateur n’est pas connecté
if (!is_user_logged_in()) {
    wp_redirect(wp_login_url());
    exit;
}
add_filter('body_class', function($classes) {
    return array_diff($classes, ['edition-active']);
});

$organisateur_id = get_organisateur_id_from_context($args ?? []);
$nom_organisateur = get_the_title($organisateur_id);

// 📥 Préparation de l’email organisateur
$email_contact = get_field('profil_public_email_contact', $organisateur_id);
if (!$email_contact || !is_email($email_contact)) {
    $auteur_id = get_post_field('post_author', $organisateur_id);
    $email_contact = get_the_author_meta('user_email', $auteur_id);
}

// 🔍 Vérifie si en mode "en création"
$current_user = wp_get_current_user();
$current_user_id = get_current_user_id();
$organisateur_auteur = (int) get_post_field('post_author', $organisateur_id);
$organisateur_statut = get_post_status($organisateur_id);
$est_en_creation = (
    $organisateur_statut === 'pending' &&
    $current_user_id === $organisateur_auteur &&
    in_array('organisateur_creation', (array) $current_user->roles)
);

get_header();

// Affiche le header organisateur personnalisé
get_template_part('template-parts/organisateur/header-organisateur', null, [
    'organisateur_id' => $organisateur_id,
    'onglet_actif' => 'contact',
]);


// 🛑 Interrompt l’affichage si le profil est en cours de création
if ($est_en_creation) :
?>
  <section class="page-contact-organisateur">
    <div class="conteneur formulaire-contact-wrapper">
      <h1 class="titre-contact h1-diff">Contacter <?php echo esc_html($nom_organisateur); ?></h1>
      <div class="bloc-discret">
        <p>
          Votre profil organisateur est en cours de création.<br>
          Le formulaire de contact sera disponible une fois votre profil validé.
        </p>
      </div>
    </div>
  </section>
  <?php get_footer(); ?>
  <?php return; ?>
<?php endif; ?>

<section class="page-contact-organisateur">
  <div class="conteneur formulaire-contact-wrapper">
    <h1 class="titre-contact h1-diff">Contacter <?php echo esc_html($nom_organisateur); ?></h1>

    <div class="bloc-discret">
      <p class="txt-sous-titre">
        Utilisez ce formulaire pour envoyer un message à l’organisateur.<br>
        Pour toute question technique liée au site, <a href="https://chassesautresor.com/contact">utilisez ce formulaire</a>.
      </p>

      <?php
      echo do_shortcode(
        '[wpforms id="8792" title="false" field_values="email_organisateur=' . urlencode($email_contact) . '"]'
      );
      ?>
    </div>
  </div>
</section>

<script>
  document.addEventListener('DOMContentLoaded', () => {
    document.body.classList.remove('edition-active');
    if (localStorage.getItem('modeEditionActif')) {
      localStorage.removeItem('modeEditionActif');
    }
  });
</script>

<?php get_footer(); ?>
