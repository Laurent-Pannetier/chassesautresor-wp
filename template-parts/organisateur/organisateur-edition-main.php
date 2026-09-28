<?php
// Panneau organisateur (affiché en Orgy)
defined('ABSPATH') || exit;

$organisateur_id = get_organisateur_id_from_context($args ?? []);
$peut_modifier   = utilisateur_peut_voir_panneau($organisateur_id);
$peut_editer     = utilisateur_peut_editer_champs($organisateur_id);


// User
$current_user = wp_get_current_user();
$roles = (array) $current_user->roles;
$profil_expanded = array_intersect($roles, [ROLE_ORGANISATEUR_CREATION, 'abonne']);
$profil_expanded = !empty($profil_expanded);
$infos_expanded = !$profil_expanded;
$cache_complet  = get_field('organisateur_cache_complet', $organisateur_id);
$edition_active = in_array(ROLE_ORGANISATEUR_CREATION, $roles) && !$cache_complet;
$user_points    = function_exists('get_user_points') ? get_user_points((int) $current_user->ID) : 0;

// Post
$titre       = get_post_field('post_title', $organisateur_id);
$logo        = get_field('logo_organisateur', $organisateur_id);
$logo_id     = is_array($logo) ? ($logo['ID'] ?? null) : $logo;
$placeholder_id = 3927;
$logo_src    = $logo_id ? wp_get_attachment_image_src($logo_id, 'thumbnail')
    : wp_get_attachment_image_src($placeholder_id, 'thumbnail');
$logo_url    = is_array($logo_src) ? $logo_src[0] : null;
$description  = get_field('description_longue', $organisateur_id);
$reseaux      = get_field('reseaux_sociaux', $organisateur_id);
$site         = get_field('lien_site_web', $organisateur_id);
$email_contact = get_field('profil_public_email_contact', $organisateur_id);

$iban = get_field('iban', $organisateur_id);
$bic  = get_field('bic', $organisateur_id);
$coordonnees_vides = empty($iban) && empty($bic);

$conversion_access   = verifier_acces_conversion(get_current_user_id());
$conversion_disabled = $conversion_access !== true;

$liens_publics = get_field('liens_publics', $organisateur_id); // ← manquant !
$liens_publics = is_array($liens_publics) ? array_filter($liens_publics, function ($entree) {
    $type_raw = $entree['type_de_lien'] ?? null;
    $url      = $entree['url_lien'] ?? null;
    $type = is_array($type_raw) ? ($type_raw[0] ?? '') : $type_raw;

    return is_string($type) && trim($type) !== '' && is_string($url) && trim($url) !== '';
}) : [];


if (function_exists('charger_script_conversion')) {
    charger_script_conversion(true);
}


$peut_editer_titre = champ_est_editable('post_title', $organisateur_id);

$is_complete = (
  !empty($titre) &&
  !empty($logo) &&
  !empty($description)
);

?>

<?php if ($peut_modifier) : ?>
  <section class="panneau-organisateur edition-panel edition-panel-organisateur edition-panel-modal<?php echo $edition_active ? ' edition-active' : ''; ?>" aria-hidden="<?php echo $edition_active ? 'false' : 'true'; ?>">

    <div class="edition-panel-header">
      <div class="edition-panel-header-top">
        <h2>
          <i class="fa-solid fa-gear"></i>
          <?= esc_html__('Panneau d\'édition organisateur', 'chassesautresor-com'); ?> :
          <span class="titre-objet" data-cpt="organisateur"><?= esc_html($titre); ?></span>
        </h2>
        <button type="button" class="panneau-fermer" aria-label="Fermer les paramètres organisateur">✖</button>
      </div>
      <div class="edition-tabs">
        <button class="edition-tab active" data-target="organisateur-tab-param"><?= esc_html__('Paramètres', 'chassesautresor-com'); ?></button>
        <button class="edition-tab" data-target="organisateur-tab-stats"><?= esc_html__('Statistiques', 'chassesautresor-com'); ?></button>
        <button class="edition-tab" data-target="organisateur-tab-animation"><?= esc_html__('Animation', 'chassesautresor-com'); ?></button>
        <button class="edition-tab" data-target="organisateur-tab-revenus"><?= esc_html__('Points', 'chassesautresor-com'); ?></button>
      </div>
    </div>

    <div id="organisateur-tab-param" class="edition-tab-content active">
      <i class="fa-solid fa-sliders tab-watermark" aria-hidden="true"></i>
      <div class="edition-panel-header">
        <h2><i class="fa-solid fa-sliders"></i> <?= esc_html__('Paramètres', 'chassesautresor-com'); ?></h2>
      </div>
      <div class="edition-panel-body">
        <div class="edition-panel-section edition-panel-section-ligne">
          <div class="section-content">
            <div class="resume-blocs-grid">
              <!-- SECTION 1 : Informations -->
              <div class="resume-bloc resume-obligatoire">
                <h3>Informations</h3>
                <ul class="resume-infos">
                  <?php
                  get_template_part(
                      'template-parts/common/edition-row',
                      null,
                      [
                          'class' => 'champ-organisateur champ-titre ligne-titre ' . (empty($titre) ? 'champ-vide' : 'champ-rempli') . ($peut_editer_titre ? '' : ' champ-desactive'),
                          'attributes' => [
                              'data-champ'   => 'post_title',
                              'data-cpt'     => 'organisateur',
                              'data-post-id' => $organisateur_id,
                              'data-no-edit' => '1',
                          ],
                          'label' => function () {
                              ?>
                              <label for="champ-titre-organisateur"><?php esc_html_e('Titre', 'chassesautresor-com'); ?> <span class="champ-obligatoire">*</span></label>
                              <?php
                          },
                          'content' => function () use ($titre, $peut_editer_titre) {
                              ?>
                              <input type="text"
                                class="champ-input champ-texte-edit"
                                maxlength="50"
                                value="<?= esc_attr($titre); ?>"
                                id="champ-titre-organisateur" <?= $peut_editer_titre ? '' : 'disabled'; ?>
                                placeholder="<?= esc_attr__('renseigner le titre de l’organisateur', 'chassesautresor-com'); ?>" />
                              <div class="champ-feedback"></div>
                              <?php
                          },
                      ]
                  );
                  ?>

                <?php
                get_template_part(
                    'template-parts/common/edition-row',
                    null,
                    [
                        'class'      => 'champ-organisateur champ-img champ-logo ligne-logo '
                            . (empty($logo_id) ? 'champ-vide' : 'champ-rempli')
                            . ($peut_editer ? '' : ' champ-desactive'),
                        'attributes' => [
                            'data-champ'   => 'logo_organisateur',
                            'data-cpt'     => 'organisateur',
                            'data-post-id' => $organisateur_id,
                        ],
                        'label' => function () {
                            ?>
                            <label>
                                <?= esc_html__('Logo de l\'organisateur', 'chassesautresor-com'); ?>
                                <span class="champ-obligatoire">*</span>
                            </label>
                            <?php
                        },
                        'content' => function () use ($logo_url, $logo_id, $peut_editer, $organisateur_id) {
                            $transparent = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==';
                            ?>
                            <div class="champ-affichage">
                                <?php if ($peut_editer) : ?>
                                    <button type="button"
                                        class="champ-modifier"
                                        data-champ="logo_organisateur"
                                        data-cpt="organisateur"
                                        data-post-id="<?= esc_attr($organisateur_id); ?>"
                                        aria-label="<?= esc_attr__('Modifier le logo de l\'organisateur', 'chassesautresor-com'); ?>">
                                        <img
                                            src="<?= esc_url($logo_url ?: $transparent); ?>"
                                            alt="<?= esc_attr__('Logo de l\'organisateur', 'chassesautresor-com'); ?>"
                                        />
                                        <span class="champ-ajout-image">
                                            <?= esc_html__('ajouter une image', 'chassesautresor-com'); ?>
                                        </span>
                                    </button>
                                <?php else : ?>
                                    <?php if ($logo_url) : ?>
                                        <img
                                            src="<?= esc_url($logo_url); ?>"
                                            alt="<?= esc_attr__('Logo de l’organisateur', 'chassesautresor-com'); ?>"
                                        />
                                    <?php else : ?>
                                        <span class="champ-ajout-image">
                                            <?= esc_html__('ajouter une image', 'chassesautresor-com'); ?>
                                        </span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            <input type="hidden" class="champ-input" value="<?= esc_attr($logo_id ?? '') ?>">
                            <div class="champ-feedback"></div>
                            <?php
                        },
                    ]
                );
                ?>
                <?php
                get_template_part(
                    'template-parts/common/edition-row',
                    null,
                    [
                        'class'      => 'champ-organisateur champ-description ligne-description '
                            . (empty($description) ? 'champ-vide' : 'champ-rempli')
                            . ($peut_editer ? '' : ' champ-desactive'),
                        'attributes' => [
                            'data-champ'   => 'description_longue',
                            'data-cpt'     => 'organisateur',
                            'data-post-id' => $organisateur_id,
                        ],
                        'label' => function () {
                            ?>
                            <label>
                                <?= esc_html__('Présentation', 'chassesautresor-com'); ?>
                                <span class="champ-obligatoire">*</span>
                            </label>
                            <?php
                        },
                        'content' => function () use ($description, $peut_editer, $organisateur_id) {
                            ?>
                            <div class="champ-texte">
                                <?php if (empty(trim($description))) : ?>
                                    <?php if ($peut_editer) : ?>
                                        <a href="#" class="champ-ajouter ouvrir-panneau-description"
                                            data-champ="description_longue"
                                            data-cpt="organisateur"
                                            data-post-id="<?= esc_attr($organisateur_id); ?>">
                                            <?= esc_html__('ajouter', 'chassesautresor-com'); ?>
                                        </a>
                                    <?php endif; ?>
                                <?php else : ?>
                                    <span class="champ-texte-contenu">
                                        <?= esc_html(wp_trim_words(wp_strip_all_tags($description), 25)); ?>
                                        <?php if ($peut_editer) : ?>
                                            <button type="button"
                                                class="champ-modifier ouvrir-panneau-description"
                                                data-champ="description_longue"
                                                data-cpt="organisateur"
                                                data-post-id="<?= esc_attr($organisateur_id); ?>"
                                                aria-label="<?= esc_attr__('Modifier la présentation', 'chassesautresor-com'); ?>">
                                                <?= esc_html__('modifier', 'chassesautresor-com'); ?>
                                            </button>
                                        <?php endif; ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="champ-feedback"></div>
                            <?php
                        },
                    ]
                );
                ?>
              </ul>
              </div>

              <!-- SECTION 2 : Réglages -->
              <div class="resume-bloc resume-reglages">
                <h3>Réglages</h3>
                <ul class="resume-infos">
                <?php
                get_template_part(
                    'template-parts/common/edition-row',
                    null,
                    [
                        'icon'  => 'fa-regular fa-envelope',
                        'class' => 'champ-organisateur champ-email-contact ligne-email ' . (empty($email_contact) ? 'champ-vide' : 'champ-rempli'),
                        'attributes' => [
                            'data-champ'   => 'email_contact',
                            'data-cpt'     => 'organisateur',
                            'data-post-id' => $organisateur_id,
                            'data-no-icon' => '1',
                        ],
                        'label' => function () {
                            ?>
                            <label for="champ-email-contact"><?php esc_html_e('Email de contact', 'chassesautresor-com'); ?></label>
                            <?php
                        },
                        'content' => function () use ($email_contact, $organisateur_id, $peut_editer) {
                            ?>
                            <div class="champ-affichage">
                                <span class="champ-valeur">
                                    <?= esc_html($email_contact ?: get_the_author_meta('user_email', get_post_field('post_author', $organisateur_id))); ?>
                                </span>
                                <?php
                                get_template_part(
                                    'template-parts/common/help-icon',
                                    null,
                                    [
                                        'aria_label' => __('Informations sur l’adresse email de contact', 'chassesautresor-com'),
                                        'variant'    => 'aide',
                                        'title'      => __('Email de contact organisateur', 'chassesautresor-com'),
                                        'message'    => __('Si aucune adresse n’est renseignée, votre adresse email utilisateur est utilisée par défaut.', 'chassesautresor-com'),
                                    ]
                                );
                                if ($peut_editer) :
                                    ?>
                                    <button
                                        type="button"
                                        class="champ-modifier"
                                        aria-label="<?= esc_attr__('Modifier l’adresse email de contact', 'chassesautresor-com'); ?>"
                                    >
                                        <?= esc_html__('modifier', 'chassesautresor-com'); ?>
                                    </button>
                                <?php
                                endif;
                                ?>
                            </div>
                            <div class="champ-edition" style="display: none;">
                                <input
                                    type="email"
                                    maxlength="255"
                                    value="<?= esc_attr($email_contact); ?>"
                                    class="champ-input"
                                    id="champ-email-contact"
                                    placeholder="<?= esc_attr__('exemple@domaine.com', 'chassesautresor-com'); ?>"
                                >
                                <span class="champ-status"></span>
                                <button type="button" class="champ-enregistrer">✓</button>
                                <button type="button" class="champ-annuler">✖</button>
                            </div>
                            <div class="champ-feedback"></div>
                            <?php
                        },
                    ]
                );
                ?>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div> <!-- .edition-panel-body -->
    </div> <!-- #organisateur-tab-param -->

    <div id="organisateur-tab-stats" class="edition-tab-content" style="display:none;">
      <i class="fa-solid fa-chart-column tab-watermark" aria-hidden="true"></i>
      <div class="edition-panel-header">
        <h2><i class="fa-solid fa-chart-column"></i> <?= esc_html__('Statistiques', 'chassesautresor-com'); ?></h2>
      </div>
      <?php get_template_part(
          'template-parts/organisateur/panneaux/organisateur-edition-statistiques',
          null,
          ['organisateur_id' => $organisateur_id]
      ); ?>
    </div>

    <div id="organisateur-tab-revenus" class="edition-tab-content" style="display:none;">
      <i class="fa-solid fa-coins tab-watermark" aria-hidden="true"></i>
      <div class="edition-panel-header">
        <h2><i class="fa-solid fa-coins"></i> <?= esc_html__('Points', 'chassesautresor-com'); ?></h2>
      </div>
        <div class="edition-panel-body">
          <div class="dashboard-grid stats-cards">
            <div class="dashboard-card" data-stat="points">
              <i class="fa-solid fa-coins" aria-hidden="true"></i>
              <h3><?php esc_html_e('Points', 'chassesautresor-com'); ?></h3>
              <p class="stat-value"><?php echo esc_html($user_points); ?></p>
            </div>
            <div class="dashboard-card<?php echo $conversion_disabled ? ' disabled' : ''; ?>" data-stat="conversion">
              <i class="fa-solid fa-right-left" aria-hidden="true"></i>
              <h3>Conversion</h3>
              <button
                type="button"
                id="open-conversion-modal"
                class="stat-value"
              >
                <?php esc_html_e('Convertir', 'chassesautresor-com'); ?>
              </button>
            </div>
            <div class="dashboard-card" data-stat="bank-details">
              <i class="fa-solid fa-building-columns" aria-hidden="true"></i>
              <h3>
                <?= esc_html__('Coordonnées bancaires', 'chassesautresor-com'); ?>
                <?php
                get_template_part(
                    'template-parts/common/help-icon',
                    null,
                    [
                        'aria_label' => __('Informations sur les coordonnées bancaires', 'chassesautresor-com'),
                        'classes'    => 'stat-help',
                        'variant'    => 'aide-small',
                        'title'      => __('Coordonnées bancaires', 'chassesautresor-com'),
                        'message'    => __('Ces informations sont nécessaires uniquement pour vous verser les gains issus de la conversion de vos points en euros. Nous ne prélevons jamais d\'argent.', 'chassesautresor-com'),
                    ]
                );
                ?>
              </h3>
              <?php if ($peut_editer) : ?>
                <?php
                  $bank_label = $coordonnees_vides
                    ? __('Ajouter', 'chassesautresor-com')
                    : __('Éditer', 'chassesautresor-com');
                  $bank_aria = $coordonnees_vides
                    ? __('Ajouter des coordonnées bancaires', 'chassesautresor-com')
                    : __('Modifier les coordonnées bancaires', 'chassesautresor-com');
                ?>
                <a
                  id="ouvrir-coordonnees"
                  class="stat-value champ-modifier"
                  href="#"
                  aria-label="<?php echo esc_attr($bank_aria); ?>"
                  data-champ="coordonnees_bancaires"
                  data-cpt="organisateur"
                  data-post-id="<?php echo esc_attr($organisateur_id); ?>"
                  data-label-add="<?php esc_attr_e('Ajouter', 'chassesautresor-com'); ?>"
                  data-label-edit="<?php esc_attr_e('Éditer', 'chassesautresor-com'); ?>"
                  data-aria-add="<?php esc_attr_e('Ajouter des coordonnées bancaires', 'chassesautresor-com'); ?>"
                  data-aria-edit="<?php esc_attr_e('Modifier les coordonnées bancaires', 'chassesautresor-com'); ?>"
                ><?php echo esc_html($bank_label); ?></a>
              <?php endif; ?>
            </div>
          </div>
          <?php echo render_conversion_history((int) $current_user->ID); ?>
          <?php echo render_points_history_table((int) $current_user->ID); ?>
        </div> <!-- .edition-panel-body -->
    </div>

    <?php
    $format = isset($_GET['format']) ? sanitize_key($_GET['format']) : 'png';
    $formats_autorises = ['png', 'svg', 'eps'];
    if (!in_array($format, $formats_autorises, true)) {
        $format = 'png';
    }
    $url        = get_permalink($organisateur_id);
    $url_qr_code = 'https://api.qrserver.com/v1/create-qr-code/?size=400x400&data='
        . rawurlencode($url)
        . '&format=' . $format;

    get_template_part(
        'template-parts/common/edition-animation',
        null,
        [
            'objet_type'      => 'organisateur',
            'objet_id'        => $organisateur_id,
            'liens'           => $liens_publics,
            'peut_modifier'   => $peut_modifier,
            'afficher_qr_code' => true,
            'url'             => $url,
            'url_qr_code'     => $url_qr_code,
        ]
    );
    ?>

    <div class="edition-panel-footer"></div>
  </section>
<?php endif; ?>
<?php if ($peut_editer) : ?>
  <?php get_template_part('template-parts/organisateur/panneaux/organisateur-edition-description', null, [
    'organisateur_id' => $organisateur_id
  ]); ?>

  <?php get_template_part('template-parts/organisateur/panneaux/organisateur-edition-liens', null, [
    'organisateur_id' => $organisateur_id
  ]); ?>

  <?php get_template_part('template-parts/organisateur/panneaux/organisateur-edition-coordonnees', null, [
    'organisateur_id' => $organisateur_id
  ]); ?>

  <?php get_template_part('template-parts/modals/modal-conversion'); ?>
<?php endif; ?>
