<?php
defined('ABSPATH') || exit;
$organisateur_id = get_organisateur_id_from_context($args ?? []);
$peut_modifier = utilisateur_peut_modifier_post($organisateur_id);


$logo_id = get_field('profil_public_logo_organisateur', $organisateur_id, false);
$logo = wp_get_attachment_image_src($logo_id, 'medium');
$logo_url = $logo ? $logo[0] : esc_url(wp_get_attachment_image_src(3927, 'medium')[0]);

$titre_organisateur = get_post_field('post_title', $organisateur_id);

if (!is_numeric($organisateur_id)) return;
$liens_actifs = organisateur_get_liens_actifs($organisateur_id);
$types_disponibles = organisateur_get_liste_liens_publics(); // à garder si nécessaire

$iban  = get_field('coordonnees_bancaires_iban', $organisateur_id);
$bic   = get_field('coordonnees_bancaires_bic', $organisateur_id);
$iban_vide = empty($iban);
$bic_vide  = empty($bic);
$classe_vide_coordonnees = ($iban_vide || $bic_vide) ? 'champ-vide' : '';

$base_url = get_permalink($organisateur_id);
$est_contact = (strpos($_SERVER['REQUEST_URI'], '/contact') !== false);

$email_contact = get_field('profil_public_email_contact', $organisateur_id);

if (!$email_contact || !is_email($email_contact)) {
  $auteur_id = get_post_field('post_author', $organisateur_id);
  $email_contact = get_the_author_meta('user_email', $auteur_id);
}

$base_url = trailingslashit(get_permalink($organisateur_id));
$url_contact = esc_url($base_url . 'contact?email_organisateur=' . urlencode($email_contact));
$est_complet = organisateur_est_complet($organisateur_id);
$classes_header = 'header-organisateur';
if ($peut_modifier && !$est_complet) {
  $classes_header .= ' champ-organisateur champ-vide-obligatoire';
}
?>
<div class="header-organisateur-wrapper">
  <div class="ligne-morse" aria-hidden="true">
    <div class="morse-wrapper" data-morse="<?= esc_attr($titre_organisateur); ?>"></div>
  </div>
  <header class="<?= esc_attr($classes_header); ?>">
    <div class="conteneur-organisateur">

      <!-- Colonne gauche : logo -->
      <div class="colonne-logo">
        <div class="champ-organisateur champ-img champ-logo <?= empty($logo_id) ? 'champ-vide' : ''; ?>"
          data-cpt="organisateur"
          data-champ="profil_public_logo_organisateur"
          data-post-id="<?= esc_attr($organisateur_id); ?>">

          <div class="champ-affichage">
            <div class="header-organisateur__logo">
              <a href="<?= esc_url(get_permalink($organisateur_id)); ?>" aria-label="Voir la page de l’organisateur">
                <img src="<?= esc_url($logo_url); ?>"
                  alt="Logo de l’organisateur"
                  class="header-organisateur__logo visuel-cpt"
                  data-cpt="organisateur"
                  data-post-id="<?= esc_attr($organisateur_id); ?>"
                  style="max-width:100%; height:auto;" />
              </a>
            </div>
          </div>

          <input type="hidden" class="champ-input" value="<?= esc_attr($logo_id ?? '') ?>">
          <div class="champ-feedback"></div>
        </div>
      </div>

      <!-- Colonne droite : contenu -->
      <div class="colonne-texte">
        <h1 class="header-organisateur__nom"><?= esc_html($titre_organisateur); ?></h1>
      </div>

      <div class="champ-edition" style="display: none;">
        <button type="button" class="champ-enregistrer">✓</button>
        <button type="button" class="champ-annuler">✖</button>
      </div>

      <div class="champ-feedback"></div>

      <div class="header-organisateur__actions">
        <button type="button" class="bouton-toggle-description" aria-label="Voir la description">
          <i class="fa-solid fa-circle-info"></i>
        </button>
        <a href="<?= esc_url($url_contact); ?>" class="lien-contact" aria-label="Contact">
          <i class="fa-solid fa-envelope"></i>
        </a>
        <button id="toggle-mode-edition" class="bouton-edition-toggle" aria-label="Paramètres organisateur">
          <i class="fa-solid fa-sliders"></i>
        </button>
      </div>
    </div>

  </header>
</div>

<?php
get_template_part('template-parts/organisateur/organisateur-edition-main', null, [
  'organisateur_id' => $organisateur_id
]);
get_template_part('template-parts/organisateur/organisateur-partial-presentation', null, [
  'organisateur_id' => $organisateur_id
]);
?>
</div>


<script>
  document.addEventListener('DOMContentLoaded', function() {
    document.body.dataset.organisateurId = "<?= esc_attr($organisateur_id); ?>";
  });
</script>