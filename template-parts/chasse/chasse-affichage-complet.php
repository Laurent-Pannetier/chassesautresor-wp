<?php
defined('ABSPATH') || exit;

$chasse_id = $args['chasse_id'] ?? null;
if (!$chasse_id || get_post_type($chasse_id) !== 'chasse') {
  return;
}

$titre = get_the_title($chasse_id);
$champTitreParDefaut = 'nouvelle chasse';
$isTitreParDefaut = strtolower(trim($titre)) === strtolower($champTitreParDefaut);

// Récupération centralisée des informations
$infos_chasse = $args['infos_chasse'] ?? preparer_infos_affichage_chasse($chasse_id);
$statut = $infos_chasse['statut'];
$is_demo = !empty($infos_chasse['is_demo']);
$demo_badge = is_array($infos_chasse['demo_badge'] ?? null) ? $infos_chasse['demo_badge'] : null;
$demo_badge_description_id = $is_demo && $demo_badge ? wp_unique_id('badge-demo-desc-') : '';


// Champs principaux (avec fallback direct en meta)
$champs = $infos_chasse['champs'];
$lot               = $champs['lot'];
$titre_recompense  = $champs['titre_recompense'];
$valeur_recompense = $champs['valeur_recompense'];
$date_debut        = $champs['date_debut'];
$date_fin          = $champs['date_fin'];
$illimitee         = $champs['illimitee'];
$nb_max            = $champs['nb_max'];
$cout_points       = (int) ($champs['cout_points'] ?? 0);

// Champs cachés
$date_decouverte      = $champs['date_decouverte'];
$gagnants             = $champs['gagnants'];
$current_stored_statut = $champs['current_stored_statut'];

if ($statut === 'termine' && !empty($date_decouverte)) {
    $date_fin = $date_decouverte;
}


$image_raw = $infos_chasse['image_raw'];
$image_id  = $infos_chasse['image_id'];
$image_url = $infos_chasse['image_url'];
$liens     = $infos_chasse['liens'] ?? [];

// Enigmes
$enigmes_associees = $infos_chasse['enigmes_associees'];
$total_enigmes     = $infos_chasse['total_enigmes'];
$nb_joueurs        = $infos_chasse['nb_joueurs'];
$nb_enigmes_payantes = $infos_chasse['nb_enigmes_payantes'];
$top_avances         = $infos_chasse['top_avances'];
$mode_fin            = $champs['mode_fin'] ?? 'automatique';
$title_mode          = $mode_fin === 'automatique'
    ? __('mode de fin de chasse : automatique', 'chassesautresor-com')
    : __('mode de fin de chasse : manuelle', 'chassesautresor-com');
$mode_auto_icon      = trim(get_svg_icon('automatic'));
$mode_manual_icon    = trim(get_svg_icon('hand'));

// Dates
$date_debut_formatee        = formater_date($date_debut);
$date_fin_formatee          = $illimitee
    ? __('Illimitée', 'chassesautresor-com')
    : ($date_fin ? formater_date($date_fin) : __('Non spécifiée', 'chassesautresor-com'));
$date_decouverte_formatee   = $date_decouverte ? formater_date_heure($date_decouverte) : '';

$now        = current_time('timestamp');
$date_label = '';
$date_value = '';
if ($illimitee) {
    $date_label = __('durée', 'chassesautresor-com');
    $date_value = __('illimitée', 'chassesautresor-com');
} else {
    $debut_ts = $date_debut ? strtotime($date_debut) : null;
    $fin_ts   = $date_fin ? strtotime($date_fin) : null;
    if ($debut_ts && $now < $debut_ts) {
        $diff       = (int) ceil(($debut_ts - $now) / DAY_IN_SECONDS);
        $date_label = __('début dans', 'chassesautresor-com');
        $date_value = sprintf(
            _n('%d jour', '%d jours', $diff, 'chassesautresor-com'),
            $diff
        );
    } elseif ($fin_ts && $now > $fin_ts) {
        $date_label = __('terminée depuis', 'chassesautresor-com');
        $date_value = formater_date($date_fin);
    } elseif ($fin_ts) {
        $diff       = (int) ceil(($fin_ts - $now) / DAY_IN_SECONDS);
        $date_label = __('jours restants', 'chassesautresor-com');
        $date_value = sprintf(
            _n('%d jour', '%d jours', $diff, 'chassesautresor-com'),
            $diff
        );
    }
}

// Edition
$edition_active = utilisateur_peut_modifier_post($chasse_id);

// Organisateur
$organisateur_id = get_organisateur_from_chasse($chasse_id);
$organisateur_nom = $organisateur_id ? get_the_title($organisateur_id) : get_the_author();


if (current_user_can('administrator')) {
  $chasse_id = get_the_ID();

  cat_debug("📦 [TEST] Statut stocké (admin) : " . get_field('chasse_cache_statut', $chasse_id));

  verifier_ou_recalculer_statut_chasse($chasse_id);


  mettre_a_jour_statuts_chasse($chasse_id);

  cat_debug("✅ [TEST] Recalcul exécuté via mettre_a_jour_statuts_chasse($chasse_id)");
}


$classe_intro = 'chasse-section-intro';
$est_complet = chasse_est_complet($chasse_id);
if ($edition_active && !$est_complet) {
  $classe_intro .= ' champ-vide-obligatoire';
}
?>


<section class="<?= esc_attr($classe_intro); ?>">

  <div class="chasse-fiche-container">
    <?php
    $statut_validation = $infos_chasse['statut_validation'];
    $statut_label = '';
    $statut_for_class = $statut;

    if ($statut === 'revision') {
      if ($statut_validation === 'creation') {
        $statut_label = __('création', 'chassesautresor-com');
      } elseif ($statut_validation === 'correction') {
        $statut_label = __('correction', 'chassesautresor-com');
      } elseif ($statut_validation === 'en_attente') {
        $statut_label = __('en attente', 'chassesautresor-com');
      } else {
        $statut_label = __('révision', 'chassesautresor-com');
      }
    } elseif ($statut === 'payante' || $statut === 'en_cours') {
      $statut_label = __('en cours', 'chassesautresor-com');
      $statut_for_class = 'en_cours';
    } elseif ($statut === 'a_venir') {
      $statut_label = __('à venir', 'chassesautresor-com');
    } elseif ($statut === 'termine') {
      $statut_label = __('terminée', 'chassesautresor-com');
    } else {
      $statut_label = __($statut, 'chassesautresor-com');
    }
    ?>

    <div class="chasse-visuel-wrapper">
      <!-- 📷 Image principale -->
      <div class="champ-chasse champ-img <?= empty($image_url) ? 'champ-vide' : 'champ-rempli'; ?>"
        data-champ="chasse_principale_image"
        data-cpt="chasse"
        data-post-id="<?= esc_attr($chasse_id); ?>">
        <div class="champ-affichage">
          <div
              class="header-chasse__image"
              data-cout-label="<?= esc_attr__('Coût de participation : %d points.', 'chassesautresor-com'); ?>"
              data-pts-label="<?= esc_attr__('pts', 'chassesautresor-com'); ?>"
              data-mode-auto-label="<?= esc_attr__('mode de fin de chasse : automatique', 'chassesautresor-com'); ?>"
              data-mode-manuel-label="<?= esc_attr__('mode de fin de chasse : manuelle', 'chassesautresor-com'); ?>"
              data-mode-auto-icon="<?= esc_attr($mode_auto_icon); ?>"
              data-mode-manuel-icon="<?= esc_attr($mode_manual_icon); ?>"
          >
              <?php if ($is_demo && $demo_badge) : ?>
                <span
                    class="badge-statut badge-demo"
                    role="img"
                    aria-label="<?= esc_attr($demo_badge['aria_label'] ?? $demo_badge['screen_text'] ?? ''); ?>"
                    <?php if ($demo_badge_description_id) : ?>aria-describedby="<?= esc_attr($demo_badge_description_id); ?>"<?php endif; ?>
                    title="<?= esc_attr($demo_badge['title'] ?? ''); ?>"
                >
                  <?php if (!empty($demo_badge['icon_html'])) : ?>
                    <span class="badge-demo__icon" aria-hidden="true">
                      <?= $demo_badge['icon_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icône SVG préparée. ?>
                    </span>
                  <?php endif; ?>
                  <span class="badge-demo__label"><?= esc_html($demo_badge['label'] ?? ''); ?></span>
                  <?php if ($demo_badge_description_id) : ?>
                    <span id="<?= esc_attr($demo_badge_description_id); ?>" class="screen-reader-text"><?= esc_html($demo_badge['screen_text'] ?? ''); ?></span>
                  <?php endif; ?>
                </span>
              <?php else : ?>
                <span class="badge-statut statut-<?= esc_attr($statut_for_class); ?>"
                  data-post-id="<?= esc_attr($chasse_id); ?>">
                  <?= esc_html($statut_label); ?>
                </span>
              <?php endif; ?>
              <?php if ($cout_points > 0) : ?>
                <span
                    class="badge-cout"
                    data-post-id="<?= esc_attr($chasse_id); ?>"
                    aria-label="<?= esc_attr(
                        sprintf(
                            __('Coût de participation : %d points.', 'chassesautresor-com'),
                            $cout_points
                        )
                    ); ?>"
                >
                  <?= esc_html($cout_points . ' ' . __('pts', 'chassesautresor-com')); ?>
                </span>
              <?php endif; ?>
              <span class="mode-fin-icone" title="<?= esc_attr($title_mode); ?>" aria-label="<?= esc_attr($title_mode); ?>">
                <?php if ($mode_fin === 'automatique') : ?>
                  <?= $mode_auto_icon; ?>
                <?php else : ?>
                  <?= $mode_manual_icon; ?>
                <?php endif; ?>
              </span>
              <?php if ($image_id) : ?>
                <a
                  href="<?= esc_url(wp_get_attachment_image_url($image_id, 'full')); ?>"
                  class="fancybox image"
                >
                  <img
                      class="chasse-image visuel-cpt img-h-max"
                      data-cpt="chasse"
                      data-post-id="<?= esc_attr($chasse_id); ?>"
                      src="<?= esc_url($infos_chasse['image_url']); ?>"
                      alt="<?= esc_attr($infos_chasse['image_alt']); ?>"
                      sizes="(max-width: 800px) 100vw, 800px"
                  />
                </a>
              <?php endif; ?>
            </div>
          </div>

        <input type="hidden" class="champ-input" value="<?= esc_attr($image_id); ?>">
        <div class="champ-feedback"></div>
      </div>
      <?php
      $vide = empty($liens);
      $liens_js = [];
      foreach ($liens as $entree) {
        $type_raw = $entree['chasse_principale_liens_type'] ?? null;
        $url_raw  = $entree['chasse_principale_liens_url'] ?? null;
        $type     = is_array($type_raw) ? ($type_raw[0] ?? '') : $type_raw;
        if (is_string($type) && trim($type) !== '' && is_string($url_raw) && trim($url_raw) !== '') {
          $liens_js[] = [
            'type_de_lien' => sanitize_text_field($type),
            'url_lien'     => esc_url_raw($url_raw),
          ];
        }
      }
      ?>
      <div class="champ-chasse champ-liens champ-fiche-publication <?= $vide ? 'champ-vide' : 'champ-rempli'; ?>"
        data-champ="chasse_principale_liens"
        data-cpt="chasse"
        data-post-id="<?= esc_attr($chasse_id); ?>">
        <div class="champ-donnees"
          data-valeurs='<?= json_encode($liens_js, JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>'></div>
        <div class="champ-affichage">
          <div class="champ-affichage-liens">
            <?= render_liens_publics($liens, 'chasse', [
                'afficher_titre' => false,
                'wrap'           => false,
                'placeholder'    => false,
            ]); ?>
          </div>
        </div>
        <div class="champ-feedback"></div>
      </div>
    </div>


    <!-- 📟 Informations -->
    <div class="chasse-details-wrapper">

      <div class="chasse-details-actions">
        <?php if (function_exists('ADDTOANY_SHARE_SAVE_BUTTON')) : ?>
          <?php
          $share_url   = get_permalink($chasse_id);
          $share_title = $titre;
          $share_href  = 'https://www.addtoany.com/share#url=' . rawurlencode($share_url) . '&title=' . rawurlencode($share_title);
          ?>
          <a
            class="a2a_dd a2a_counter chasse-share-button addtoany_share_save addtoany_share"
            href="<?= esc_url($share_href); ?>"
            data-a2a-url="<?= esc_url($share_url); ?>"
            data-a2a-title="<?= esc_attr($share_title); ?>"
            aria-label="<?= esc_attr__('Partager', 'chassesautresor-com'); ?>"
          >
            <?= get_svg_icon('share-icon'); ?>
          </a>
        <?php endif; ?>
        <?php if ($edition_active) : ?>
          <button id="toggle-mode-edition-chasse" class="bouton-edition-toggle" aria-label="<?php esc_attr_e('Paramètres de chasse', 'chassesautresor-com'); ?>">
            <i class="fa-solid fa-gear"></i>
          </button>
        <?php endif; ?>
      </div>

        <?php if ($organisateur_id) :
            $logo_id = get_field('logo_organisateur', $organisateur_id, false);
            $logo    = wp_get_attachment_image_src($logo_id, 'thumbnail');
            $logo_url = $logo ? $logo[0] : wp_get_attachment_image_src(3927, 'thumbnail')[0];
        ?>
          <div class="chasse-organisateur">
            <a
              href="<?= esc_url(get_permalink($organisateur_id)); ?>"
              aria-label="<?= esc_attr__('Voir la page de l\u2019organisateur', 'chassesautresor-com'); ?>"
            >
              <img
                class="chasse-organisateur__logo visuel-cpt"
                src="<?= esc_url($logo_url); ?>"
                alt="<?= esc_attr__('Logo de l\u2019organisateur', 'chassesautresor-com'); ?>"
                data-cpt="organisateur"
                data-post-id="<?= esc_attr($organisateur_id); ?>"
              />
            </a>
            <span class="chasse-organisateur__texte">
              <a class="chasse-organisateur__nom" href="<?= esc_url(get_permalink($organisateur_id)); ?>"><?= esc_html($organisateur_nom); ?></a>
              <span class="chasse-organisateur__presente"><?php esc_html_e('présente', 'chassesautresor-com'); ?></span>
            </span>
          </div>
        <?php endif; ?>

      <!-- Titre dynamique -->
      <h1 class="titre-objet header-chasse"
        data-cpt="chasse"
        data-post-id="<?= esc_attr($chasse_id); ?>">
        <?= esc_html($titre); ?>
      </h1>

      <?php if ($statut === 'termine' && !empty($date_decouverte) && !empty($gagnants)) : ?>
        <?php
        $solutions_link = '';
        $user_id_solutions = function_exists('get_current_user_id') ? get_current_user_id() : 0;
        if (
            function_exists('solution_chasse_peut_etre_affichee')
            && function_exists('utilisateur_peut_voir_solution_chasse')
            && function_exists('solution_recuperer_par_objet')
            && function_exists('solution_contenu_html')
            && solution_chasse_peut_etre_affichee($chasse_id)
            && utilisateur_peut_voir_solution_chasse($chasse_id, $user_id_solutions)
        ) {
            $sol_post = solution_recuperer_par_objet($chasse_id, 'chasse');
            if ($sol_post && solution_contenu_html($sol_post) !== '') {
                $solutions_link = ' — <a href="#chasse-solutions">'
                    . esc_html__('Voir les solutions', 'chassesautresor-com')
                    . '</a>';
            }
        }
        ?>
        <div class="chasse-gagnant-info">
          <?= sprintf(__('Chasse gagnée le %1$s par %2$s', 'chassesautresor-com'), esc_html($date_decouverte_formatee), esc_html($gagnants)); ?><?= $solutions_link; ?>
        </div>
      <?php endif; ?>

      <?php
      $total_enigmes_formatted     = number_format_i18n((int) $total_enigmes);
      $total_enigmes_for_plural    = $total_enigmes === 1 ? 1 : (int) $total_enigmes;
      $label_total_enigmes         = sprintf(
          _n('%s énigme', '%s énigmes', $total_enigmes_for_plural, 'chassesautresor-com'),
          $total_enigmes_formatted
      );
      $nb_joueurs_formatted        = number_format_i18n((int) $nb_joueurs);
      $nb_joueurs_for_plural       = $nb_joueurs === 1 ? 1 : (int) $nb_joueurs;
      $label_nb_joueurs            = sprintf(
          _n('%s joueur', '%s joueurs', $nb_joueurs_for_plural, 'chassesautresor-com'),
          $nb_joueurs_formatted
      );
      $date_debut_courte = (string) ($infos_chasse['date_debut_court'] ?? '');
      $date_fin_courte   = (string) ($infos_chasse['date_fin_court'] ?? '');

      if ($date_debut_courte === '' || $date_fin_courte === '') {
          $dates_courtes_fallback = chasse_preparer_dates_courtes(
              is_string($date_debut) && $date_debut !== '' ? $date_debut : (is_numeric($date_debut) ? (string) $date_debut : null),
              is_string($date_fin) && $date_fin !== '' ? $date_fin : (is_numeric($date_fin) ? (string) $date_fin : null),
              !empty($illimitee)
          );

          if ($date_debut_courte === '') {
              $date_debut_courte = $dates_courtes_fallback['date_debut_court'];
          }

          if ($date_fin_courte === '') {
              $date_fin_courte = $dates_courtes_fallback['date_fin_court'];
          }
      }

      if ($date_debut_courte === '') {
          $date_debut_courte = __('Non spécifiée', 'chassesautresor-com');
      }

      if ($date_fin_courte === '') {
          $date_fin_courte = __('Non spécifiée', 'chassesautresor-com');
      }

      $date_debut_longue = $date_debut_formatee !== ''
          ? $date_debut_formatee
          : $date_debut_courte;
      $date_fin_longue = $date_fin_formatee !== ''
          ? $date_fin_formatee
          : $date_fin_courte;

      if ($illimitee) {
          $date_plage_title = __('Durée illimitée', 'chassesautresor-com');
      } elseif (!empty($date_debut) && !empty($date_fin)) {
          /* translators: %1$s: start date, %2$s: end date. */
          $date_plage_title = sprintf(__('Du %1$s au %2$s', 'chassesautresor-com'), $date_debut_formatee, $date_fin_formatee);
      } elseif (!empty($date_debut)) {
          /* translators: %s: start date. */
          $date_plage_title = sprintf(__('À partir du %s', 'chassesautresor-com'), $date_debut_formatee);
      } elseif (!empty($date_fin)) {
          /* translators: %s: end date. */
          $date_plage_title = sprintf(__('Jusqu\'au %s', 'chassesautresor-com'), $date_fin_formatee);
      } else {
          $date_plage_title = __('Dates non spécifiées', 'chassesautresor-com');
      }
      ?>
      <div class="meta-row svg-xsmall meta-row--headline">
        <div class="meta-regular">
          <span class="meta-indic meta-indic--static">
            <?php echo get_svg_icon('enigme'); ?>
            <span class="meta-indic__count" aria-hidden="true"><?= esc_html($total_enigmes_formatted); ?></span>
            <span class="screen-reader-text"><?= esc_html($label_total_enigmes); ?></span>
          </span>
          <span class="meta-indic meta-indic--static">
            <?php echo get_svg_icon('participants'); ?>
            <span class="meta-indic__count" aria-hidden="true"><?= esc_html($nb_joueurs_formatted); ?></span>
            <span class="screen-reader-text"><?= esc_html($label_nb_joueurs); ?></span>
          </span>
        </div>
        <div class="meta-etiquette">
          <?php echo get_svg_icon('calendar'); ?>
          <span
            class="chasse-date-plage"
            title="<?= esc_attr($date_plage_title); ?>"
            aria-label="<?= esc_attr($date_plage_title); ?>"
          >
            <span
                class="date-debut"
                data-date-long="<?= esc_attr($date_debut_longue); ?>"
                data-date-short="<?= esc_attr($date_debut_courte); ?>"
            >
              <span class="date-short" aria-hidden="false"><?= esc_html($date_debut_courte); ?></span>
              <span class="date-long" aria-hidden="true"><?= esc_html($date_debut_longue); ?></span>
            </span>
            <span class="date-separator" aria-hidden="true">–</span>
            <span
                class="date-fin"
                data-date-long="<?= esc_attr($date_fin_longue); ?>"
                data-date-short="<?= esc_attr($date_fin_courte); ?>"
            >
              <span class="date-short" aria-hidden="false"><?= esc_html($date_fin_courte); ?></span>
              <span class="date-long" aria-hidden="true"><?= esc_html($date_fin_longue); ?></span>
            </span>
          </span>
        </div>
      </div>

      <div class="separateur-3">
        <div class="trait-gauche"></div>
        <div class="icone-svg"></div>
        <div class="trait-droite"></div>
      </div>
        <?php
        $cta_data = $infos_chasse['cta_data'] ?? [];
        ?>
        <div class="chasse-cta-section cta-chasse">
          <div class="chasse-caracteristiques">
            <?php if ($date_label && $date_value) : ?>
              <div class="caracteristique caracteristique-date">
                <span class="caracteristique-icone" aria-hidden="true">📅</span>
                <span class="caracteristique-label"><?= esc_html($date_label); ?></span>
                <span class="caracteristique-valeur"><?= esc_html($date_value); ?></span>
              </div>
            <?php endif; ?>
            <div
              class="caracteristique caracteristique-limite"
              style="<?= $mode_fin === 'automatique' ? '' : 'display:none;'; ?>"
            >
              <span class="caracteristique-icone" aria-hidden="true">👥</span>
              <?php if ((int) $nb_max === 0) : ?>
                <span class="caracteristique-label"><?= esc_html__('Gagnants', 'chassesautresor-com'); ?></span>
                <span
                  class="caracteristique-valeur nb-gagnants-affichage"
                  data-post-id="<?= esc_attr($chasse_id); ?>"
                >
                  <?= esc_html__('illimitée', 'chassesautresor-com'); ?>
                </span>
              <?php else : ?>
                <span class="caracteristique-label"><?= esc_html__('Limite', 'chassesautresor-com'); ?></span>
                <span
                  class="caracteristique-valeur nb-gagnants-affichage"
                  data-post-id="<?= esc_attr($chasse_id); ?>"
                >
                  <?= esc_html(
                      sprintf(
                          _n('%d gagnant', '%d gagnants', $nb_max, 'chassesautresor-com'),
                          $nb_max
                      )
                  ); ?>
                </span>
              <?php endif; ?>
            </div>

            <div class="caracteristique caracteristique-fin">
              <span class="caracteristique-icone" aria-hidden="true">⏱️</span>
              <span class="caracteristique-label"><?= esc_html__('Fin de chasse', 'chassesautresor-com'); ?></span>
              <span class="caracteristique-valeur">
                <?= esc_html(
                    $mode_fin === 'automatique'
                        ? __('automatique', 'chassesautresor-com')
                        : __('manuelle', 'chassesautresor-com')
                ); ?>
              </span>
            </div>

            <div class="caracteristique caracteristique-acces-chasse">
              <span class="caracteristique-icone" aria-hidden="true">🔑</span>
              <span class="caracteristique-label"><?= esc_html__('Accès chasse', 'chassesautresor-com'); ?></span>
              <span class="caracteristique-valeur">
                <?php if ($cout_points > 0) : ?>
                  <span class="badge-cout"><?= esc_html($cout_points . ' ' . __('pts', 'chassesautresor-com')); ?></span>
                <?php else : ?>
                  <?= esc_html__('libre', 'chassesautresor-com'); ?>
                <?php endif; ?>
              </span>
            </div>

            <div class="caracteristique caracteristique-acces-enigme">
              <span class="caracteristique-icone" aria-hidden="true">🧩</span>
              <span class="caracteristique-label">
                <?php if ($nb_enigmes_payantes > 0) : ?>
                  <?= esc_html__('Points requis', 'chassesautresor-com'); ?>
                <?php else : ?>
                  <?= esc_html__('Tentatives', 'chassesautresor-com'); ?>
                <?php endif; ?>
              </span>
              <span class="caracteristique-valeur">
                <?php if ($nb_enigmes_payantes > 0) : ?>
                  <?= esc_html(
                      sprintf(
                          _n(
                              '%d énigme',
                              '%d énigmes',
                              $nb_enigmes_payantes,
                              'chassesautresor-com'
                          ),
                          $nb_enigmes_payantes
                      )
                  ); ?>
                <?php else : ?>
                  <?= esc_html__('Gratuit', 'chassesautresor-com'); ?>
                <?php endif; ?>
              </span>
            </div>

            <?php if ($top_avances['nb'] > 0 && $top_avances['enigmes'] > 0) : ?>
              <?php
              $txt_top = sprintf(
                  _n(
                      '%1$d joueur a trouvé %2$d énigme',
                      '%1$d joueurs ont trouvé %2$d énigmes',
                      $top_avances['nb'],
                      'chassesautresor-com'
                  ),
                  $top_avances['nb'],
                  $top_avances['enigmes']
              );
              ?>
              <div class="caracteristique caracteristique-top">
                <span class="caracteristique-icone" aria-hidden="true">⭐</span>
                <span class="caracteristique-label"><?= esc_html__('Les + avancés', 'chassesautresor-com'); ?></span>
                <span class="caracteristique-valeur"><?= esc_html($txt_top); ?></span>
              </div>
            <?php endif; ?>
          </div>

          <?php
          $cta_type = $cta_data['type'] ?? '';
          $cta_id   = $cta_type === 'validation' ? 'cta-validation-chasse' : '';
          ?>
          <div class="cta-chasse-row"<?php echo $cta_id ? ' id="' . esc_attr($cta_id) . '"' : ''; ?>>
            <div class="cta-message" aria-live="polite"><?= $cta_data['cta_message']; ?></div>
            <div class="cta-action">
              <?= $cta_data['cta_html']; ?>
            </div>
          </div>
          </div>

        <?php
        get_template_part(
            'template-parts/chasse/chasse-partial-description',
            null,
            [
                'description' => $infos_chasse['description'] ?? '',
            ]
        );
        ?>

        <?php if (!empty($titre_recompense) || (float) $valeur_recompense > 0 || !empty($lot)) : ?>
            <div class="chasse-lot-complet">
                <h3><i class="fa-solid fa-trophy" aria-hidden="true"></i> <?= esc_html__('Récompense', 'chassesautresor-com'); ?></h3>

                <div class="champ-chasse champ-lot-titre <?= empty($titre_recompense) ? 'champ-vide' : 'champ-rempli'; ?>"
                    data-champ="chasse_infos_recompense_titre"
                    data-cpt="chasse"
                    data-post-id="<?= esc_attr($chasse_id); ?>">
                    <div class="champ-affichage">
                        <?php if (!empty($titre_recompense)) : ?>
                            <p class="lot-titre"><?= esc_html($titre_recompense); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="champ-feedback"></div>
                </div>

                <div class="champ-chasse champ-lot-valeur <?= (float) $valeur_recompense > 0 ? 'champ-rempli' : 'champ-vide'; ?>"
                    data-champ="chasse_infos_recompense_valeur"
                    data-cpt="chasse"
                    data-post-id="<?= esc_attr($chasse_id); ?>">
                    <div class="champ-affichage">
                        <?php if ((float) $valeur_recompense > 0) : ?>
                            <p class="lot-valeur">
                                <span class="badge-recompense avec-recompense">
                                    <span class="badge-recompense__label">
                                        <?= esc_html__('Valeur estimée', 'chassesautresor-com'); ?>
                                    </span>
                                    <?= esc_html(number_format_i18n(round((float) $valeur_recompense), 0)); ?>
                                    <span class="badge-recompense__devise prix-devise">€</span>
                                </span>
                            </p>
                        <?php endif; ?>
                    </div>
                    <div class="champ-feedback"></div>
                </div>

                <div class="champ-chasse champ-lot-description <?= empty($lot) ? 'champ-vide' : 'champ-rempli'; ?>"
                    data-champ="chasse_lot"
                    data-cpt="chasse"
                    data-post-id="<?= esc_attr($chasse_id); ?>">
                    <div class="champ-affichage">
                        <?php if (!empty($lot)) : ?>
                            <p class="lot-description"><?= wp_kses_post($lot); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="champ-feedback"></div>
                </div>
            </div>
        <?php endif; ?>

        <?php
        $regions_terms = $infos_chasse['regions'] ?? null;
        if (empty($regions_terms)) {
            $regions_terms = chasse_preparer_termes_affichage($chasse_id, 'chasse_region');
        }

        $themes_terms = $infos_chasse['themes'] ?? null;
        if (empty($themes_terms)) {
            $themes_terms = chasse_preparer_termes_affichage($chasse_id, 'theme_chasse');
        }

        $regions_links = chasse_format_meta_terms($regions_terms);
        $themes_links = chasse_format_meta_terms($themes_terms);
        ?>
        <?php if (!empty($regions_links) || !empty($themes_links)) : ?>
            <div class="chasse-fiche-metas bloc-metas-inline">
                <?php if (!empty($regions_links)) : ?>
                    <?php
                    $regions_label = _n('Région :', 'Régions :', count($regions_links), 'chassesautresor-com');
                    ?>
                    <div class="meta-etiquette meta-etiquette--regions">
                        <span><?php echo esc_html($regions_label); ?></span>
                        <?php echo wp_kses_post(implode(', ', $regions_links)); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($themes_links)) : ?>
                    <?php
                    $themes_label = _n('Thème :', 'Thèmes :', count($themes_links), 'chassesautresor-com');
                    ?>
                    <div class="meta-etiquette meta-etiquette--themes">
                        <span><?php echo esc_html($themes_label); ?></span>
                        <?php echo wp_kses_post(implode(', ', $themes_links)); ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

      </div>
  </div>
</section>

<?php if ($edition_active) : ?>
  <!-- 
    Templates SVG invisibles pour utilisation dynamique en JavaScript.
    Affichés uniquement en Orgy pour éviter de surcharger la page publique.
  -->
  <div id="svg-icons" style="display: none;">
    <template id="icon-free">
      <?php echo get_svg_icon('free'); ?>
    </template>
    <template id="icon-unlock">
      <?php echo get_svg_icon('unlock'); ?>
    </template>
  </div>
<?php endif; ?>

<?php
// Inclure le panneau si édition active
if ($edition_active) {
  get_template_part('template-parts/chasse/chasse-edition-main', null, [
    'chasse_id'   => $chasse_id,
    'infos_chasse' => $infos_chasse
  ]);
}
?>