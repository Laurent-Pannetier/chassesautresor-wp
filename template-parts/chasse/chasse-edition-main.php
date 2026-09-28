<?php

/**
 * Template Part: Panneau d'édition frontale d'une chasse
 * Requiert : $args['chasse_id']
 */

defined('ABSPATH') || exit;

$chasse_id = $args['chasse_id'] ?? null;
if (!$chasse_id || get_post_type($chasse_id) !== 'chasse') {
  return;
}

$peut_modifier = utilisateur_peut_voir_panneau($chasse_id);
$peut_editer   = utilisateur_peut_editer_champs($chasse_id);
$peut_editer_titre = champ_est_editable('post_title', $chasse_id);
$peut_editer_cout  = champ_est_editable('caracteristiques.chasse_infos_cout_points', $chasse_id);

$infos_chasse = $args['infos_chasse'] ?? preparer_infos_affichage_chasse($chasse_id);

$enigme_ids = recuperer_ids_enigmes_pour_chasse($chasse_id);
$nombre_enigmes = count($enigme_ids);

$image_id   = $infos_chasse['image_id'] ?? null;
$image_url  = $image_id ? wp_get_attachment_image_src($image_id, 'thumbnail')[0] : null;
$description = $infos_chasse['description'];
$titre = get_the_title($chasse_id);
$liens = $infos_chasse['liens'];
$recompense = $infos_chasse['champs']['lot'];
$valeur     = $infos_chasse['champs']['valeur_recompense'];
$titre_recompense = $infos_chasse['champs']['titre_recompense'];
$cout       = $infos_chasse['champs']['cout_points'];
$date_debut = $infos_chasse['champs']['date_debut'];
$date_fin   = $infos_chasse['champs']['date_fin'];
$date_decouverte = $infos_chasse['champs']['date_decouverte'];
$date_decouverte_formatee = $date_decouverte ? formater_date_heure($date_decouverte) : '';
$gagnants = $infos_chasse['champs']['gagnants'];

// 🎯 Conversion des dates pour les champs <input>
$date_debut_obj = convertir_en_datetime($date_debut);
$date_debut_iso = $date_debut_obj ? $date_debut_obj->format('Y-m-d\TH:i') : '';

$date_fin_obj = convertir_en_datetime($date_fin);
$date_fin_iso = $date_fin_obj ? $date_fin_obj->format('Y-m-d') : '';
$debut_differe = (bool) get_post_meta($chasse_id, 'chasse_infos_date_debut_differee', true);
$illimitee  = $infos_chasse['champs']['illimitee'];
$nb_max     = $infos_chasse['champs']['nb_max'] ?? 1;
$mode_fin   = $infos_chasse['champs']['mode_fin'] ?? 'automatique';
$statut_metier = $infos_chasse['statut'] ?? 'revision';

$statut_wp     = get_post_status($chasse_id);
$statut_cache  = get_post_meta($chasse_id, 'chasse_cache_statut', true);
$current_user  = wp_get_current_user();
$current_roles = (array) $current_user->roles;
$roles_autorises = [];
if (defined('ROLE_ORGANISATEUR')) {
    $roles_autorises[] = ROLE_ORGANISATEUR;
} else {
    $roles_autorises[] = 'organisateur';
}
if (defined('ROLE_ORGANISATEUR_CREATION')) {
    $roles_autorises[] = ROLE_ORGANISATEUR_CREATION;
} else {
    $roles_autorises[] = 'organisateur_creation';
}

$utilisateur_associe = utilisateur_est_organisateur_associe_a_chasse(get_current_user_id(), $chasse_id);
$peut_supprimer_chasse = $utilisateur_associe
    && !empty(array_intersect($current_roles, $roles_autorises))
    && $statut_metier === 'revision'
    && $statut_cache === 'revision'
    && $statut_wp === 'pending';

$champTitreParDefaut = 'nouvelle chasse'; // À adapter si besoin
$isTitreParDefaut = strtolower(trim($titre)) === strtolower($champTitreParDefaut);

?>

<?php if ($peut_modifier) : ?>
  <section class="edition-panel edition-panel-chasse edition-panel-modal" data-cpt="chasse" data-post-id="<?= esc_attr($chasse_id); ?>">
    <div id="erreur-global" style="display:none; background:red; color:white; padding:5px; text-align:center; font-size:0.9em;"></div>

    <div class="edition-panel-header">
        <div class="edition-panel-header-top">
            <h2>
                <i class="fa-solid fa-gear"></i>
                <?= esc_html__('Panneau d\'édition chasse', 'chassesautresor-com'); ?> :
                <span class="titre-objet" data-cpt="chasse"><?= esc_html($titre); ?></span>
            </h2>
            <button type="button" class="panneau-fermer" aria-label="Fermer les paramètres">✖</button>
        </div>
        <div class="edition-tabs">
          <button class="edition-tab active" data-target="chasse-tab-param"><?= esc_html__('Paramètres', 'chassesautresor-com'); ?></button>
          <button class="edition-tab" data-target="chasse-tab-stats"><?= esc_html__('Statistiques', 'chassesautresor-com'); ?></button>
          <button class="edition-tab" data-target="chasse-tab-animation"><?= esc_html__('Animation', 'chassesautresor-com'); ?></button>
        </div>
    </div>

    <div id="chasse-tab-param" class="edition-tab-content active">
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

                <!-- Titre -->
                <?php
                get_template_part(
                    'template-parts/common/edition-row',
                    null,
                    [
                        'class' => 'champ-chasse champ-titre ' . ($isTitreParDefaut ? 'champ-vide' : 'champ-rempli') . ($peut_editer_titre ? '' : ' champ-desactive'),
                        'attributes' => [
                            'data-champ'   => 'post_title',
                            'data-cpt'     => 'chasse',
                            'data-post-id' => $chasse_id,
                            'data-no-edit' => '1',
                        ],
                        'label' => function () {
                            ?>
                            <label for="champ-titre-chasse"><?php esc_html_e('Titre', 'chassesautresor-com'); ?> <span class="champ-obligatoire">*</span></label>
                            <?php
                        },
                        'content' => function () use ($titre, $peut_editer_titre) {
                            ?>
                            <input type="text" class="champ-input champ-texte-edit" maxlength="70"
                                value="<?= esc_attr($titre); ?>"
                                id="champ-titre-chasse" <?= $peut_editer_titre ? '' : 'disabled'; ?>
                                placeholder="<?= esc_attr__('renseigner le titre de la chasse', 'chassesautresor-com'); ?>" />
                            <div class="champ-feedback"></div>
                            <?php
                        },
                    ]
                );
                ?>
                
                <!-- Image -->
                <?php
                get_template_part(
                    'template-parts/common/edition-row',
                    null,
                    [
                        'class'      => 'champ-chasse champ-img '
                            . (empty($image_id) ? 'champ-vide' : 'champ-rempli')
                            . ($peut_editer ? '' : ' champ-desactive'),
                        'attributes' => [
                            'data-champ'   => 'chasse_principale_image',
                            'data-cpt'     => 'chasse',
                            'data-post-id' => $chasse_id,
                        ],
                        'label' => function () {
                            ?>
                            <label>
                                <?= esc_html__('Image chasse', 'chassesautresor-com'); ?>
                                <span class="champ-obligatoire">*</span>
                            </label>
                            <?php
                        },
                        'content' => function () use ($image_url, $image_id, $peut_editer, $chasse_id) {
                            $transparent = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==';
                            ?>
                            <div class="champ-affichage">
                                <?php if ($peut_editer) : ?>
                                    <?php if ($image_url) : ?>
                                        <img
                                            src="<?= esc_url($image_url); ?>"
                                            alt="<?= esc_attr__('Image de la chasse', 'chassesautresor-com'); ?>"
                                        />
                                        <button type="button"
                                            class="champ-modifier"
                                            data-cpt="chasse"
                                            data-post-id="<?= esc_attr($chasse_id); ?>"
                                            aria-label="<?= esc_attr__('Modifier l’image', 'chassesautresor-com'); ?>">
                                            <?= esc_html__('modifier', 'chassesautresor-com'); ?>
                                        </button>
                                    <?php else : ?>
                                        <button type="button"
                                            class="champ-modifier"
                                            data-cpt="chasse"
                                            data-post-id="<?= esc_attr($chasse_id); ?>"
                                            aria-label="<?= esc_attr__('Ajouter une image', 'chassesautresor-com'); ?>">
                                            <img
                                                src="<?= esc_url($transparent); ?>"
                                                alt="<?= esc_attr__('Image de la chasse', 'chassesautresor-com'); ?>"
                                            />
                                            <span class="champ-ajout-image">
                                                <?= esc_html__('ajouter une image', 'chassesautresor-com'); ?>
                                            </span>
                                        </button>
                                    <?php endif; ?>
                                <?php else : ?>
                                    <?php if ($image_url) : ?>
                                        <img
                                            src="<?= esc_url($image_url); ?>"
                                            alt="<?= esc_attr__('Image de la chasse', 'chassesautresor-com'); ?>"
                                        />
                                    <?php else : ?>
                                        <span class="champ-ajout-image">
                                            <?= esc_html__('ajouter une image', 'chassesautresor-com'); ?>
                                        </span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            <input type="hidden" class="champ-input" value="<?= esc_attr($image_id ?? '') ?>">
                            <div class="champ-feedback"></div>
                            <?php
                        },
                    ]
                );
                ?>

                <!-- Description -->
                <?php
                get_template_part(
                    'template-parts/common/edition-row',
                    null,
                    [
                        'class'      => 'champ-chasse champ-description '
                            . (empty($description) ? 'champ-vide' : 'champ-rempli')
                            . ($peut_editer ? '' : ' champ-desactive'),
                        'attributes' => [
                            'data-champ'   => 'chasse_principale_description',
                            'data-cpt'     => 'chasse',
                            'data-post-id' => $chasse_id,
                        ],
                        'label' => function () {
                            ?>
                            <label>
                                <?= esc_html__('Description chasse', 'chassesautresor-com'); ?>
                                <span class="champ-obligatoire">*</span>
                            </label>
                            <?php
                        },
                        'content' => function () use ($description, $peut_editer, $chasse_id) {
                            ?>
                            <div class="champ-texte">
                                <?php if (empty(trim($description))) : ?>
                                    <?php if ($peut_editer) : ?>
                                        <a href="#" class="champ-ajouter ouvrir-panneau-description"
                                            data-cpt="chasse"
                                            data-champ="chasse_principale_description"
                                            data-post-id="<?= esc_attr($chasse_id); ?>">
                                            <?= esc_html__('ajouter', 'chassesautresor-com'); ?>
                                        </a>
                                    <?php endif; ?>
                                <?php else : ?>
                                    <span class="champ-texte-contenu">
                                        <?= esc_html(wp_trim_words(wp_strip_all_tags($description), 25)); ?>
                                        <?php if ($peut_editer) : ?>
                                            <button type="button"
                                                class="champ-modifier ouvrir-panneau-description"
                                                data-cpt="chasse"
                                                data-champ="chasse_principale_description"
                                                data-post-id="<?= esc_attr($chasse_id); ?>"
                                                aria-label="<?= esc_attr__(
                                                    'Modifier la description',
                                                    'chassesautresor-com'
                                                ); ?>"
                                            >
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

                <!-- Récompense -->
                <?php
                $recompense_remplie = !empty($titre_recompense) && !empty($recompense) && (float) $valeur > 0;
                get_template_part(
                    'template-parts/common/edition-row',
                    null,
                    [
                        'class'      => 'champ-chasse champ-recompense '
                            . ($recompense_remplie ? 'champ-rempli' : 'champ-vide')
                            . ($peut_editer ? '' : ' champ-desactive'),
                        'attributes' => [
                            'data-champ'   => 'chasse_infos_recompense_valeur',
                            'data-cpt'     => 'chasse',
                            'data-post-id' => $chasse_id,
                        ],
                        'label' => function () {
                            ?>
                            <label><?= esc_html__('Récompense', 'chassesautresor-com'); ?></label>
                            <?php
                        },
                        'content' => function () use (
                            $recompense_remplie,
                            $recompense,
                            $valeur,
                            $titre_recompense,
                            $peut_editer,
                            $chasse_id
                        ) {
                            $desc_brut  = wp_strip_all_tags($recompense);
                            $desc_court = mb_substr($desc_brut, 0, 200);
                            if (mb_strlen($desc_brut) > 200) {
                                $desc_court .= '…';
                            }
                            ?>
                            <div class="champ-texte">
                                <?php if ($recompense_remplie) : ?>
                                    <span class="champ-texte-contenu">
                                        <span class="recompense-valeur">
                                            <span class="recompense-valeur__label">
                                                <?= esc_html__('Valeur estimée', 'chassesautresor-com'); ?>
                                            </span>
                                            <?= esc_html(number_format_i18n(round((float) $valeur), 0)); ?>
                                            <span class="recompense-valeur__devise">€</span>
                                        </span>
                                        &nbsp;–&nbsp;
                                        <span class="recompense-titre"><?= esc_html($titre_recompense); ?></span>
                                        &nbsp;–&nbsp;
                                        <span class="recompense-description"><?= esc_html($desc_court); ?></span>
                                    </span>
                                <?php endif; ?>
                                <?php if ($peut_editer) : ?>
                                    <button type="button"
                                        class="champ-modifier ouvrir-panneau-recompense"
                                        data-champ="chasse_infos_recompense_valeur"
                                        data-cpt="chasse"
                                        data-post-id="<?= esc_attr($chasse_id); ?>"
                                        aria-label="<?= esc_attr__(
                                            $recompense_remplie ? 'Modifier la récompense' : 'Ajouter la récompense',
                                            'chassesautresor-com'
                                        ); ?>"
                                    >
                                        <?= esc_html__(
                                            $recompense_remplie ? 'modifier' : 'ajouter',
                                            'chassesautresor-com'
                                        ); ?>
                                    </button>
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
                <h3><?= esc_html__('Réglages', 'chassesautresor-com'); ?></h3>
                <ul class="resume-infos">

                <!-- Mode de fin de chasse -->
                <?php ob_start(); ?>
                <?php if (in_array($statut_metier, ['payante', 'en_cours', 'revision'], true)) : ?>
                  <button
                    type="button"
                    class="terminer-chasse-btn bouton-cta"
                    data-post-id="<?= esc_attr($chasse_id); ?>"
                    data-cpt="chasse"
                    <?= ($statut_metier === 'revision') ? 'disabled' : ''; ?>
                  ><?= esc_html__('Terminer la chasse', 'chassesautresor-com'); ?></button>
                  <div class="zone-validation-fin" style="display:none;">
                    <label for="chasse-gagnants"><?= esc_html__('Gagnants', 'chassesautresor-com'); ?></label>
                    <textarea id="chasse-gagnants" required></textarea>
                    <button
                      type="button"
                      class="valider-fin-chasse-btn bouton-cta"
                      data-post-id="<?= esc_attr($chasse_id); ?>"
                      data-cpt="chasse"
                      disabled
                    ><?= esc_html__('Valider la fin de chasse', 'chassesautresor-com'); ?></button>
                    <button
                      type="button"
                      class="annuler-fin-chasse-btn bouton-secondaire"
                    ><?= esc_html__('Annuler', 'chassesautresor-com'); ?></button>
                  </div>
                <?php endif; ?>
                <?php $bloc_fin_chasse = trim(ob_get_clean()); ?>

                <?php
                get_template_part(
                    'template-parts/common/edition-row',
                    null,
                    [
                        'class'      => 'champ-chasse champ-mode-fin' . ($peut_editer ? '' : ' champ-desactive'),
                        'attributes' => [
                            'data-champ'   => 'chasse_mode_fin',
                            'data-cpt'     => 'chasse',
                            'data-post-id' => $chasse_id,
                            'data-no-edit' => '1',
                        ],
                        'no_icon'   => true,
                        'label'    => function () {
                            ?>
                            <label for="chasse_mode_fin"><?= esc_html__('Mode de fin', 'chassesautresor-com'); ?></label>
                            <?php
                        },
                        'content'  => function () use ($mode_fin, $peut_editer, $statut_metier, $date_decouverte_formatee, $gagnants, $bloc_fin_chasse) {
                            ?>
                            <div class="champ-mode-options">
                                <span class="toggle-option">
                                    <?= esc_html__('Automatique', 'chassesautresor-com'); ?>
                                    <?php
                                    get_template_part(
                                        'template-parts/common/help-icon',
                                        null,
                                        [
                                            'aria_label' => __('Explication du mode automatique', 'chassesautresor-com'),
                                            'variant'    => 'aide',
                                            'title'      => __('Fin de chasse automatique', 'chassesautresor-com'),
                                            'message'    => __('Un joueur est déclaré gagnant lorsqu’il a résolu toutes les énigmes. En mode automatique, la chasse se termine dès que le nombre de gagnants prévu est atteint.', 'chassesautresor-com'),
                                        ]
                                    );
                                    ?>
                                </span>
                                <label class="switch-control">
                                    <input
                                        id="chasse_mode_fin"
                                        type="checkbox"
                                        name="acf[chasse_mode_fin]"
                                        value="manuelle"
                                        <?= $mode_fin === 'manuelle' ? 'checked' : ''; ?>
                                        <?= $peut_editer ? '' : 'disabled'; ?>
                                    >
                                    <span class="switch-slider"></span>
                                </label>
                                <span class="toggle-option">
                                    <?= esc_html__('Manuelle', 'chassesautresor-com'); ?>
                                    <?php
                                    get_template_part(
                                        'template-parts/common/help-icon',
                                        null,
                                        [
                                            'aria_label' => __('Explication du mode manuel', 'chassesautresor-com'),
                                            'variant'    => 'aide',
                                            'title'      => __('Fin de chasse manuelle', 'chassesautresor-com'),
                                            'message'    => __('Vous pouvez arrêter la chasse à tout moment grâce au bouton disponible dans le panneau d’édition de la chasse, onglet Animation.', 'chassesautresor-com'),
                                        ]
                                    );
                                    ?>
                                </span>
                                <div class="fin-chasse-actions">
                                    <?php if ($statut_metier === 'termine') : ?>
                                        <p class="message-chasse-terminee">
                                            <?= sprintf(__('Chasse gagnée le %s par %s', 'chassesautresor-com'), esc_html($date_decouverte_formatee), esc_html($gagnants)); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php
                        },
                    ]
                );
                ?>
                <?php ob_start(); ?>
                <!-- Nombre de gagnants -->
                <?php
                get_template_part(
                    'template-parts/common/edition-row',
                    null,
                    [
                        'class'      => 'champ-chasse champ-nb-gagnants ' . (empty($nb_max) ? 'champ-vide' : 'champ-rempli') . ($peut_editer ? '' : ' champ-desactive'),
                        'attributes' => [
                            'data-champ'   => 'chasse_infos_nb_max_gagants',
                            'data-cpt'     => 'chasse',
                            'data-post-id' => $chasse_id,
                        ],
                        'no_icon'   => true,
                        'label'    => function () {
                            ?>
                            <label for="chasse-nb-gagnants"><?= esc_html__('Nb gagnants', 'chassesautresor-com'); ?></label>
                            <?php
                        },
                        'content'  => function () use ($nb_max, $peut_editer) {
                            ?>
                            <div class="champ-mode-options">
                                <span class="toggle-option"><?= esc_html__('Illimité', 'chassesautresor-com'); ?></span>
                                <label class="switch-control">
                                    <input
                                        id="nb-gagnants-limite"
                                        type="checkbox"
                                        <?= $nb_max != 0 ? 'checked' : ''; ?>
                                        <?= $peut_editer ? '' : 'disabled'; ?>
                                    >
                                    <span class="switch-slider"></span>
                                </label>
                                <span class="toggle-option"><?= esc_html__('Limité', 'chassesautresor-com'); ?></span>
                                <div class="nb-gagnants-actions" style="<?= $nb_max != 0 ? '' : 'display:none;'; ?>">
                                    <input type="number"
                                        id="chasse-nb-gagnants"
                                        name="chasse-nb-gagnants"
                                        value="<?= esc_attr($nb_max); ?>"
                                        min="1"
                                        class="champ-inline-nb champ-nb-edit champ-input champ-number"
                                        <?= ($peut_editer && $nb_max != 0) ? '' : 'disabled'; ?> />

                                    <div id="erreur-nb-gagnants" class="message-erreur" role="alert" aria-live="assertive" style="display:none;"></div>
                                </div>
                            </div>
                            <?php
                        },
                    ]
                );
                ?>
                <?php $bloc_nb_gagnants = ob_get_clean(); ?>

                <?php if ($mode_fin === 'automatique') : ?>
                  <?= $bloc_nb_gagnants; ?>
                <?php endif; ?>

                <template id="template-nb-gagnants">
                  <?= $bloc_nb_gagnants; ?>
                </template>

                <!-- Date de début (édition inline) -->
                <?php
                get_template_part(
                    'template-parts/common/edition-row',
                    null,
                    [
                        'class'      => 'champ-chasse champ-date-debut' . ($peut_editer ? '' : ' champ-desactive'),
                        'attributes' => [
                            'data-champ'   => 'chasse_infos_date_debut',
                            'data-cpt'     => 'chasse',
                            'data-post-id' => $chasse_id,
                        ],
                        'no_icon'   => true,
                        'label'    => function () {
                            ?>
                            <label for="chasse-date-debut"><?= esc_html__('Début', 'chassesautresor-com'); ?></label>
                            <?php
                        },
                        'content'  => function () use ($date_debut_iso, $peut_editer, $debut_differe) {
                            ?>
                            <div class="champ-mode-options">
                                <span class="toggle-option"><?= esc_html__('Now', 'chassesautresor-com'); ?></span>
                                <label class="switch-control">
                                    <input
                                        id="date-debut-differee"
                                        type="checkbox"
                                        <?= $debut_differe ? 'checked' : ''; ?>
                                        <?= $peut_editer ? '' : 'disabled'; ?>
                                    >
                                    <span class="switch-slider"></span>
                                </label>
                                <span class="toggle-option"><?= esc_html__('Other date', 'chassesautresor-com'); ?></span>
                                <div class="date-debut-actions" style="<?= $debut_differe ? '' : 'display:none;'; ?>">
                                    <input type="datetime-local"
                                        id="chasse-date-debut"
                                        name="chasse-date-debut"
                                        value="<?= esc_attr($date_debut_iso); ?>"
                                        class="champ-inline-date champ-date-edit" <?= ($peut_editer && $debut_differe) ? '' : 'disabled'; ?> required />
                                    <div id="erreur-date-debut" class="message-erreur" role="alert" aria-live="assertive" style="display:none;"></div>
                                </div>
                            </div>
                            <?php
                        },
                    ]
                );
                ?>

                <!-- Date de fin -->
                <?php
                get_template_part(
                    'template-parts/common/edition-row',
                    null,
                    [
                        'class'      => 'champ-chasse champ-date-fin' . ($peut_editer ? '' : ' champ-desactive'),
                        'attributes' => [
                            'data-champ'   => 'chasse_infos_date_fin',
                            'data-cpt'     => 'chasse',
                            'data-post-id' => $chasse_id,
                        ],
                        'no_icon'   => true,
                        'label'    => function () {
                            ?>
                            <label for="chasse-date-fin"><?= esc_html__('Date de fin', 'chassesautresor-com'); ?></label>
                            <?php
                        },
                        'content'  => function () use ($date_fin_iso, $peut_editer, $illimitee) {
                            ?>
                            <div class="champ-mode-options">
                                <span class="toggle-option"><?= esc_html__('Illimitée', 'chassesautresor-com'); ?></span>
                                <label class="switch-control">
                                    <input type="checkbox"
                                        id="date-fin-limitee"
                                        name="date-fin-limitee"
                                        data-champ="chasse_infos_duree_illimitee"
                                        <?= $illimitee ? '' : 'checked'; ?> <?= $peut_editer ? '' : 'disabled'; ?>>
                                    <span class="switch-slider"></span>
                                </label>
                                <span class="toggle-option"><?= esc_html__('Limitée', 'chassesautresor-com'); ?></span>
                                <div class="date-fin-actions" style="<?= $illimitee ? 'display:none;' : ''; ?>">
                                    <input type="date"
                                        id="chasse-date-fin"
                                        name="chasse-date-fin"
                                        value="<?= esc_attr($date_fin_iso); ?>"
                                        class="champ-inline-date champ-date-edit" <?= $peut_editer ? '' : 'disabled'; ?> />
                                    <div id="erreur-date-fin" class="message-erreur" role="alert" aria-live="assertive" style="display:none;"></div>
                                </div>
                            </div>
                            <?php
                        },
                    ]
                );
                ?>


                <!-- Accès -->
                <?php
                get_template_part(
                    'template-parts/common/edition-row',
                    null,
                    [
                        'class'      => 'champ-chasse champ-cout-points ' . ((int) $cout === 0 ? 'champ-vide' : 'champ-rempli') . ($peut_editer_cout ? '' : ' champ-desactive'),
                        'attributes' => [
                            'data-champ'   => 'chasse_infos_cout_points',
                            'data-cpt'     => 'chasse',
                            'data-post-id' => $chasse_id,
                        ],
                        'no_icon'   => true,
                        'label'    => function () {
                            ?>
                            <label>
                                <?= esc_html__('Accès', 'chassesautresor-com'); ?>
                                <?php
                                get_template_part(
                                    'template-parts/common/help-icon',
                                    null,
                                    [
                                        'aria_label' => __('En savoir plus sur les points', 'chassesautresor-com'),
                                        'classes'    => 'open-points-modal',
                                        'variant'    => 'info',
                                        'title'      => __('Coût d’accès à une chasse', 'chassesautresor-com'),
                                        'message'    => __('Vous êtes libre de définir le coût d’accès à votre chasse : gratuit ou payant. Cet accès est indispensable pour consulter les énigmes, qui restent invisibles tant qu’il n’a pas été débloqué.', 'chassesautresor-com'),
                                    ]
                                );
                                ?>
                            </label>
                            <?php
                        },
                        'content'  => function () use ($cout, $peut_editer_cout) {
                            ?>
                            <div class="champ-mode-options">
                                <span class="toggle-option"><?= esc_html__('Gratuit', 'chassesautresor-com'); ?></span>
                                <label class="switch-control">
                                    <input type="checkbox"
                                        id="cout-payant"
                                        name="cout-payant"
                                        <?= ((int) $cout > 0) ? 'checked' : ''; ?> <?= $peut_editer_cout ? '' : 'disabled'; ?>>
                                    <span class="switch-slider"></span>
                                </label>
                                <span class="toggle-option"><?= esc_html__('Points', 'chassesautresor-com'); ?></span>
                                <div class="cout-points-actions" style="<?= ((int) $cout > 0) ? '' : 'display:none;'; ?>">
                                    <input type="number"
                                        class="champ-input champ-cout champ-number"
                                        min="1"
                                        step="1"
                                        value="<?= esc_attr($cout); ?>"
                                        placeholder="10" <?= $peut_editer_cout ? '' : 'disabled'; ?> />
                                </div>
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
    </div> <!-- #chasse-tab-param -->

    <div id="chasse-tab-stats" class="edition-tab-content" style="display:none;">
      <i class="fa-solid fa-chart-column tab-watermark" aria-hidden="true"></i>
      <div class="edition-panel-header">
        <h2><i class="fa-solid fa-chart-column"></i> <?= esc_html__('Statistiques', 'chassesautresor-com'); ?></h2>
      </div>
      <?php if (!utilisateur_est_organisateur_associe_a_chasse(get_current_user_id(), $chasse_id)) : ?>
        <p class="edition-placeholder"><?php esc_html_e('Accès refusé.', 'chassesautresor-com'); ?></p>
      <?php else :
        if (!function_exists('chasse_compter_participants')) {
            require_once get_stylesheet_directory() . '/inc/chasse/stats.php';
        }
        $validation = get_field('chasse_cache_statut_validation', $chasse_id);
        $stats_locked = in_array($validation, ['creation', 'en_attente', 'correction'], true);
        $periode = 'total';
        if ($stats_locked) {
            $nb_participants = $nb_tentatives = $nb_points = 0;
            $total_engagements = 0;
            $enigmes_stats = [];
            $progress_data = [];
            $no_validation_enigmas = [];
            $total_enigme_engagements = 0;
            $max_progress = 0;
            $par_page_participants = 25;
            $total_participants = 0;
            $pages_participants = 0;
            $total_enigmes = 0;
            $taux_engagement = 0;
            $participants = [];
        } else {
            $nb_participants = chasse_compter_participants($chasse_id, $periode);
            $nb_tentatives = chasse_compter_tentatives($chasse_id, $periode);
            $nb_points = chasse_compter_points_collectes($chasse_id, $periode);
            $total_engagements = chasse_compter_engagements($chasse_id);
            $enigme_ids = recuperer_ids_enigmes_pour_chasse($chasse_id);
            $enigmes_stats = [];
            $progress_data = [];
            $no_validation_enigmas = [];
            $total_enigme_engagements = 0;
            foreach ($enigme_ids as $enigme_id) {
                $engagements = enigme_compter_joueurs_engages($enigme_id, $periode);
                $total_enigme_engagements += $engagements;
                $resolutions = enigme_compter_bonnes_solutions($enigme_id, 'automatique', $periode);
                $enigmes_stats[] = [
                    'id'          => $enigme_id,
                    'titre'       => get_the_title($enigme_id),
                    'engagements' => $engagements,
                    'tentatives'  => enigme_compter_tentatives($enigme_id, 'automatique', $periode),
                    'points'      => enigme_compter_points_depenses($enigme_id, 'automatique', $periode),
                    'resolutions' => $resolutions,
                ];
                $mode_validation = get_field('enigme_mode_validation', $enigme_id);
                if ($mode_validation === 'aucune') {
                    $no_validation_enigmas[] = [
                        'title' => get_the_title($enigme_id),
                        'url'   => get_permalink($enigme_id),
                    ];
                    continue;
                }
                $progress_data[] = [
                    'title' => get_the_title($enigme_id),
                    'url'   => get_permalink($enigme_id),
                    'value' => $resolutions,
                ];
            }
            usort($progress_data, static function ($a, $b) {
                return $b['value'] <=> $a['value'];
            });
            $max_progress = !empty($progress_data) ? max(array_column($progress_data, 'value')) : 0;
            $par_page_participants = 25;
            $total_participants = chasse_compter_participants($chasse_id);
            $pages_participants = (int) ceil($total_participants / $par_page_participants);
            $total_enigmes = count($enigme_ids);
            $taux_engagement = 0;
            if ($nb_participants > 0 && $total_enigmes > 0) {
                $taux_engagement = (int) round(
                    (100 * $total_enigme_engagements) / ($nb_participants * $total_enigmes)
                );
            }
            $participants = chasse_lister_participants(
                $chasse_id,
                $par_page_participants,
                0,
                'inscription',
                'ASC'
            );
        }
      ?>
        <div class="edition-panel-body">
          <div class="stats-header" style="display:flex;align-items:center;justify-content:flex-end;gap:1rem;">
            <a href="?edition=open&amp;tab=stats" class="stats-reset"><i class="fa-solid fa-rotate-right"></i> <?= esc_html__('Actualiser', 'chassesautresor-com'); ?></a>
            <div class="stats-filtres">
              <label for="chasse-periode"><?= esc_html__('Période :', 'chassesautresor-com'); ?></label>
              <select id="chasse-periode">
                <option value="total">Total</option>
                <option value="jour">Aujourd’hui</option>
                <option value="semaine">Semaine</option>
                <option value="mois">Mois</option>
              </select>
            </div>
          </div>
          <div class="dashboard-grid stats-cards" id="chasse-stats">
            <?php
            $card_class = $stats_locked ? 'disabled' : '';
            get_template_part('template-parts/common/stat-card', null, [
                'icon'  => 'fa-solid fa-users',
                'label' => esc_html__('Participants', 'chassesautresor-com'),
                'value' => $nb_participants,
                'stat'  => 'participants',
                'class' => $card_class,
            ]);
            get_template_part('template-parts/common/stat-card', null, [
                'icon'  => 'fa-solid fa-arrow-rotate-right',
                'label' => esc_html__('Tentatives', 'chassesautresor-com'),
                'value' => $nb_tentatives,
                'stat'  => 'tentatives',
                'class' => $card_class,
            ]);
            get_template_part('template-parts/common/stat-card', null, [
                'icon'  => 'fa-solid fa-coins',
                'label' => esc_html__('Points collectés', 'chassesautresor-com'),
                'value' => $nb_points,
                'stat'  => 'points',
                'class' => $card_class,
            ]);
            get_template_part('template-parts/common/stat-card', null, [
                'icon'  => 'fa-solid fa-percent',
                'label' => esc_html__('Taux d\'engagement', 'chassesautresor-com'),
                'value' => $taux_engagement . '%',
                'stat'  => 'engagement-rate',
                'help'        => __(
                    'Pourcentage moyen d’énigmes auxquelles chaque joueur a participé, par rapport à l’ensemble des énigmes proposées.',
                    'chassesautresor-com'
                ),
                'help_label'  => __('Explication du taux d’engagement', 'chassesautresor-com'),
                'help_title'  => __('Taux d\'engagement', 'chassesautresor-com'),
                'help_variant'=> 'aide-small',
                'class'       => $card_class,
            ]);
            ?>
          </div>
          <?php if ($stats_locked) : ?>
            <p class="edition-placeholder" style="text-align:center;">
              <?php esc_html_e('Les statistiques seront disponibles une fois la chasse activée.', 'chassesautresor-com'); ?>
            </p>
          <?php endif; ?>
          <?php if ($max_progress > 0) :
              get_template_part('template-parts/common/stat-histogram-card', null, [
                  'label' => 'Progressivomètre',
                  'data'  => $progress_data,
                  'max'   => $max_progress,
                  'stat'  => 'progress',
              ]);
              if (!empty($no_validation_enigmas)) : ?>
                <p class="stats-disabled-list"><?php esc_html_e('Énigmes sans validation', 'chassesautresor-com'); ?> :</p>
                <ul class="stats-disabled-list">
                  <?php foreach ($no_validation_enigmas as $e) : ?>
                    <li><a href="<?= esc_url($e['url']); ?>"><?= esc_html($e['title']); ?></a></li>
                  <?php endforeach; ?>
                </ul>
              <?php endif;
          endif;
          get_template_part('template-parts/chasse/partials/chasse-partial-enigmes', null, [
              'title'         => esc_html__('Énigmes', 'chassesautresor-com'),
              'enigmes'       => $enigmes_stats,
              'total'         => $total_engagements,
              'cols_etiquette' => [2, 3, 4, 5, 6],
          ]); ?>
          <div class="liste-participants" data-page="1" data-pages="<?= esc_attr($pages_participants); ?>" data-order="asc" data-orderby="inscription">
            <?php get_template_part('template-parts/chasse/partials/chasse-partial-participants', null, [
              'participants'   => $participants,
              'page'           => 1,
              'par_page'       => $par_page_participants,
              'total'          => $total_participants,
              'pages'          => $pages_participants,
              'total_enigmes'  => $total_enigmes,
            ]); ?>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <?php
    $afficher_qr_code = est_organisateur()
        && ($infos_chasse['statut'] ?? '') !== 'revision'
        && ($infos_chasse['statut_validation'] ?? '') === 'valide';

    if ($afficher_qr_code) {
        $format            = isset($_GET['format']) ? sanitize_key($_GET['format']) : 'png';
        $formats_autorises = ['png', 'svg', 'eps'];
        if (!in_array($format, $formats_autorises, true)) {
            $format = 'png';
        }
        $url       = get_permalink($chasse_id);
        $url_qr_code = 'https://api.qrserver.com/v1/create-qr-code/?size=400x400&data='
            . rawurlencode($url)
            . '&format=' . $format;
    }

    get_template_part(
        'template-parts/common/edition-animation',
        null,
        [
            'objet_type'               => 'chasse',
            'objet_id'                 => $chasse_id,
            'liens'                    => $liens,
            'peut_modifier'            => $peut_modifier,
            'statut_metier'            => $statut_metier,
            'mode_fin'                 => $mode_fin,
            'bloc_fin_chasse'          => $bloc_fin_chasse,
            'date_decouverte_formatee' => $date_decouverte_formatee,
            'gagnants'                 => $gagnants,
            'afficher_qr_code'         => $afficher_qr_code,
            'url'                      => $url ?? '',
            'url_qr_code'              => $url_qr_code ?? '',
        ]
    );
    ?>

      <div class="edition-panel-footer">
        <?php if ($peut_supprimer_chasse) : ?>
          <button
            type="button"
            id="bouton-supprimer-chasse"
            class="bouton-secondaire"
            data-chasse-id="<?= esc_attr($chasse_id); ?>"
            data-enigmes-count="<?= esc_attr($nombre_enigmes); ?>"
          >
            <?= esc_html__('Supprimer la chasse', 'chassesautresor-com'); ?>
          </button>
        <?php endif; ?>
        <?php if (current_user_can('administrator')) : ?>
          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="admin-validation-actions form-traitement-validation-chasse">
            <?php wp_nonce_field('validation_admin_' . $chasse_id, 'validation_admin_nonce'); ?>
            <input type="hidden" name="action" value="traiter_validation_chasse">
            <input type="hidden" name="chasse_id" value="<?php echo esc_attr($chasse_id); ?>">
            <button type="button" class="bouton-secondaire btn-correction">
              <i class="fa-solid fa-triangle-exclamation"></i> Correction
            </button>
            <button type="submit" name="validation_admin_action" value="bannir" class="bouton-secondaire" onclick="return confirm('Bannir cette chasse&nbsp;?');">
              <i class="fa-solid fa-triangle-exclamation"></i> Bannir
            </button>
            <button type="submit" name="validation_admin_action" value="supprimer" class="bouton-secondaire btn-danger" onclick="return confirm('<?php echo esc_js(__('Supprimer cette chasse&nbsp;?', 'chassesautresor-com')); ?>');">
              <i class="fa-solid fa-trash"></i> <?php echo esc_html__('Supprimer', 'chassesautresor-com'); ?>
            </button>
          </form>
        <?php endif; ?>
      </div>

      </div>
  </section>
<?php endif; ?>

<?php
// 📎 Panneaux contextuels (description, liens, etc.)
get_template_part('template-parts/chasse/panneaux/chasse-edition-description', null, [
  'chasse_id' => $chasse_id
]);
get_template_part('template-parts/chasse/panneaux/chasse-edition-recompense', null, [
  'chasse_id' => $chasse_id
]);
get_template_part('template-parts/chasse/panneaux/chasse-edition-liens', null, [
  'chasse_id' => $chasse_id
]);
?>
