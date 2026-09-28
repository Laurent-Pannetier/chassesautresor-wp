<?php
defined('ABSPATH') || exit;
$organisateur_id = get_organisateur_id_from_context($args ?? []);
$peut_modifier = utilisateur_peut_modifier_post($organisateur_id);


$logo_id = get_field('logo_organisateur', $organisateur_id, false);
$logo = wp_get_attachment_image_src($logo_id, 'full');
$logo_url = $logo ? $logo[0] : wp_get_attachment_image_src(3927, 'full')[0];

$titre_organisateur = get_post_field('post_title', $organisateur_id);

if (!is_numeric($organisateur_id)) return;
$liens_actifs = organisateur_get_liens_actifs($organisateur_id);
$nb_liens     = count($liens_actifs);

$iban  = get_field('iban', $organisateur_id);
$bic   = get_field('bic', $organisateur_id);
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

$description      = get_field('description_longue', $organisateur_id);
$description_full = is_string($description) ? $description : '';
$description_short = wp_trim_words(wp_strip_all_tags($description_full), 40, '…');

$date_inscription = mysql2date(get_option('date_format'), get_post_field('post_date', $organisateur_id));
$nb_chasses       = organisateur_get_nb_chasses_publiees($organisateur_id);
$nb_joueurs       = organisateur_compter_joueurs_uniques($organisateur_id);

$base_url = trailingslashit(get_permalink($organisateur_id));
$url_contact = esc_url($base_url . 'contact?email_organisateur=' . urlencode($email_contact));
$est_complet = organisateur_est_complet($organisateur_id);
$classes_header = 'header-organisateur';
if ($peut_modifier && !$est_complet) {
    $classes_header .= ' champ-organisateur champ-vide-obligatoire';
}
$classes_header .= ' container container--boxed';
?>
<div class="header-organisateur-wrapper">
  <div class="ligne-morse" aria-hidden="true">
    <div class="morse-wrapper" data-morse="<?= esc_attr($titre_organisateur); ?>"></div>
  </div>
  <header class="<?= esc_attr($classes_header); ?>">
    <div class="conteneur-organisateur">
      <div class="header-organisateur__col header-organisateur__col--logo">
        <div class="champ-organisateur champ-img champ-logo <?= empty($logo_id) ? 'champ-vide' : ''; ?>"
          data-cpt="organisateur"
          data-champ="logo_organisateur"
          data-post-id="<?= esc_attr($organisateur_id); ?>">
          <div class="champ-affichage">
            <?php
            $est_page_organisateur = is_singular('organisateur')
              && (int) get_queried_object_id() === (int) $organisateur_id;
            ?>
            <?php if ($est_page_organisateur) : ?>
              <img src="<?= esc_url($logo_url); ?>"
                   alt="<?= esc_attr__('Logo de l\u2019organisateur', 'chassesautresor-com'); ?>"
                   width="500" height="500"
                   class="header-organisateur__logo header-organisateur__logo--static visuel-cpt"
                   data-cpt="organisateur"
                   data-post-id="<?= esc_attr($organisateur_id); ?>" />
            <?php else : ?>
              <a href="<?= esc_url(get_permalink($organisateur_id)); ?>"
                 aria-label="<?= esc_attr__('Voir la page de l\u2019organisateur', 'chassesautresor-com'); ?>">
                <img src="<?= esc_url($logo_url); ?>"
                     alt="<?= esc_attr__('Logo de l\u2019organisateur', 'chassesautresor-com'); ?>"
                     width="500" height="500"
                     class="header-organisateur__logo visuel-cpt"
                     data-cpt="organisateur"
                     data-post-id="<?= esc_attr($organisateur_id); ?>" />
              </a>
            <?php endif; ?>
          </div>
          <input type="hidden" class="champ-input" value="<?= esc_attr($logo_id ?? '') ?>">
          <div class="champ-feedback"></div>
        </div>
      </div>

      <div class="header-organisateur__col header-organisateur__col--infos">
        <div class="header-organisateur__title-row">
          <h1 class="header-organisateur__nom"><?= esc_html($titre_organisateur); ?></h1>
          <div class="header-organisateur__actions">
            <?php if (function_exists('ADDTOANY_SHARE_SAVE_BUTTON')) : ?>
              <?php
              $share_url   = get_permalink($organisateur_id);
              $share_title = $titre_organisateur;
              $share_href  = 'https://www.addtoany.com/share#url=' . rawurlencode($share_url) . '&title=' . rawurlencode($share_title);
              ?>
              <a
                class="a2a_dd a2a_counter organisateur-share-button addtoany_share_save addtoany_share"
                href="<?= esc_url($share_href); ?>"
                data-a2a-url="<?= esc_url($share_url); ?>"
                data-a2a-title="<?= esc_attr($share_title); ?>"
                aria-label="<?= esc_attr__('Partager', 'chassesautresor-com'); ?>"
              >
                <?= get_svg_icon('share-icon'); ?>
              </a>
            <?php endif; ?>
            <?php if ($peut_modifier) : ?>
              <button id="toggle-mode-edition" class="bouton-edition-toggle" aria-label="<?= esc_attr__('Paramètres organisateur', 'chassesautresor-com'); ?>">
                <i class="fa-solid fa-gear"></i>
              </button>
            <?php endif; ?>
          </div>
        </div>
        <p class="header-organisateur__description">
          <?= esc_html($description_short); ?>
          <button type="button" class="header-organisateur__voir-plus" aria-label="<?= esc_attr__('Voir plus', 'chassesautresor-com'); ?>">
            <i class="fa-solid fa-circle-plus" aria-hidden="true"></i>
          </button>
        </p>
        <div class="header-organisateur__liens-row">
          <?php if ($nb_liens > 0) : ?>
            <ul class="header-organisateur__liens">
              <?php foreach ($liens_actifs as $type => $url) :
                $infos = organisateur_get_lien_public_infos($type);
              ?>
                <li class="item-lien-public">
                  <a href="<?= esc_url($url); ?>"
                     class="lien-public lien-<?= esc_attr($type); ?>"
                     target="_blank" rel="noopener"
                     aria-label="<?= esc_attr($infos['label']); ?>">
                    <i class="fa <?= esc_attr($infos['icone']); ?>" aria-hidden="true"></i>
                  </a>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
          <a href="<?= esc_url($url_contact); ?>" class="lien-contact" aria-label="<?= esc_attr__('Contact', 'chassesautresor-com'); ?>">
            <i class="fa-solid fa-envelope"></i>
          </a>
        </div>
      </div>

      <div class="champ-edition" style="display: none;">
        <button type="button" class="champ-enregistrer btn-icon bouton-tertiaire">✓</button>
        <button type="button" class="champ-annuler btn-icon btn-danger">✖</button>
      </div>

      <div class="champ-feedback"></div>
    </div>

  </header>
</div>
<div id="description-modal" class="description-modal csa-modal masque">
  <div class="description-modal__content csa-modal__content">
    <button type="button" class="description-modal__close csa-modal__close" aria-label="<?= esc_attr__('Fermer', 'chassesautresor-com'); ?>">✖</button>
    <h2 class="description-modal__title"><?= esc_html($titre_organisateur); ?></h2>
    <ul class="description-modal__stats">
      <li class="description-modal__stat">
        <?php esc_html_e('Inscrit depuis', 'chassesautresor-com'); ?> :
        <?= esc_html($date_inscription); ?>
      </li>
      <li class="description-modal__stat">
        <?php echo esc_html(_n('Chasse', 'Chasses', $nb_chasses, 'chassesautresor-com')); ?> :
        <?= esc_html($nb_chasses); ?>
      </li>
      <li class="description-modal__stat">
        <?php echo esc_html(_n('Joueur', 'Joueurs', $nb_joueurs, 'chassesautresor-com')); ?> :
        <?= esc_html($nb_joueurs); ?>
      </li>
    </ul>
    <section class="description-modal__section description-modal__section--description">
      <h3><?php esc_html_e('Description', 'chassesautresor-com'); ?></h3>
      <?= wpautop($description_full ?: '<em>' . esc_html__('Aucune description fournie pour le moment.', 'chassesautresor-com') . '</em>'); ?>
    </section>
  </div>
</div>

<?php
get_template_part('template-parts/organisateur/organisateur-edition-main', null, [
  'organisateur_id' => $organisateur_id
]);
?>
<script>
  document.addEventListener('DOMContentLoaded', function() {
    document.body.dataset.organisateurId = "<?= esc_attr($organisateur_id); ?>";
  });
</script>
