<?php

/**
 * Template Part: Panneau d'édition frontale d'une énigme
 * Requiert : $args['enigme_id']
 */

defined('ABSPATH') || exit;

$enigme_id = $args['enigme_id'] ?? null;
if (!$enigme_id || get_post_type($enigme_id) !== 'enigme') {
  return;
}

$peut_modifier = utilisateur_peut_voir_panneau($enigme_id);
$peut_editer   = utilisateur_peut_editer_champs($enigme_id);
$peut_editer_titre = champ_est_editable('post_title', $enigme_id);

$titre = get_the_title($enigme_id);
$titre_defaut = TITRE_DEFAUT_ENIGME;
$isTitreParDefaut = strtolower(trim($titre)) === strtolower($titre_defaut);

$visuel = get_field('enigme_visuel_image', $enigme_id); // champ "gallery" → tableau d’IDs
$has_images = is_array($visuel) && count($visuel) > 0;
$legende = (string) get_field('enigme_visuel_legende', $enigme_id);
$texte_enigme = (string) get_field('enigme_visuel_texte', $enigme_id);
$reponses = enigme_get_bonnes_reponses($enigme_id);
$reponse = implode(', ', $reponses);
$casse = get_field('enigme_reponse_casse', $enigme_id);
$max = (int) get_field('enigme_tentative_max', $enigme_id);
$cout = get_field('enigme_tentative_cout_points', $enigme_id);
$mode_validation = get_field('enigme_mode_validation', $enigme_id) ?? 'aucune';
$date_raw = get_field('enigme_acces_date', $enigme_id);
$date_obj = convertir_en_datetime($date_raw);
$date_deblocage = $date_obj ? $date_obj->format('Y-m-d\TH:i') : '';


$chasse = get_field('enigme_chasse_associee', $enigme_id);
$chasse_id = is_array($chasse) ? $chasse[0] : null;
$chasse_title = $chasse_id ? get_the_title($chasse_id) : '';
$enigme_status = get_post_status($enigme_id);
$chasse_validation = $chasse_id ? get_field('chasse_cache_statut_validation', $chasse_id) : '';
$stats_locked = in_array($chasse_validation, ['creation', 'en_attente', 'correction'], true)
    || $enigme_status !== 'publish';

$nb_variantes   = 0;
$variantes_list = [];
for ($i = 1; $i <= 4; $i++) {
    $texte_variante   = trim((string) get_field("texte_{$i}", $enigme_id));
    $message_variante = trim((string) get_field("message_{$i}", $enigme_id));
    if ($texte_variante && $message_variante) {
        $nb_variantes++;
        $variantes_list[] = [
            'texte'   => $texte_variante,
            'message' => $message_variante,
        ];
    }
}
$has_variantes = ($nb_variantes > 0);


?>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['uid'], $_POST['action_traitement'])) {
    $uid_post = sanitize_text_field($_POST['uid']);
    check_admin_referer('traiter_tentative_' . $uid_post);
    $action = sanitize_text_field($_POST['action_traitement']);
    if (in_array($action, ['valider', 'invalider'], true)) {
        $resultat = $action === 'valider' ? 'bon' : 'faux';
        $effectue = traiter_tentative_manuelle($uid_post, $resultat);
        wp_safe_redirect(add_query_arg('done', $effectue ? '1' : '0'));
        exit;
    }
}
?>
<?php if ($peut_modifier) : ?>
  <section class="edition-panel edition-panel-enigme edition-panel-modal" data-cpt="enigme" data-post-id="<?= esc_attr($enigme_id); ?>">
    <div id="erreur-global"
      style="display:none; background:red; color:white; padding:5px; text-align:center; font-size:0.9em;"></div>

      <div class="edition-panel-header">
        <div class="edition-panel-header-top">
          <h2>
            <i class="fa-solid fa-gear"></i>
            <?= esc_html__('Panneau d\'édition énigme', 'chassesautresor-com'); ?> :
            <span class="titre-objet" data-cpt="enigme"><?= esc_html($titre); ?></span>
          </h2>

        <button type="button" class="panneau-fermer" aria-label="<?= esc_attr__('Fermer les paramètres', 'chassesautresor-com'); ?>">✖</button>
      </div>
      <div class="edition-tabs">
        <button class="edition-tab active" data-target="enigme-tab-param"><?= esc_html__('Paramètres', 'chassesautresor-com'); ?></button>
        <button class="edition-tab" data-target="enigme-tab-stats"><?= esc_html__('Statistiques', 'chassesautresor-com'); ?></button>
        <button class="edition-tab" data-target="enigme-tab-animation"><?= esc_html__('Animation', 'chassesautresor-com'); ?></button>
        <button class="edition-tab" data-target="enigme-tab-soumission"<?= $mode_validation === 'aucune' ? ' style="display:none;"' : ''; ?>><?= esc_html__('Tentatives', 'chassesautresor-com'); ?></button>
      </div>
    </div>

<div id="enigme-tab-param" class="edition-tab-content active">
      <i class="fa-solid fa-sliders tab-watermark" aria-hidden="true"></i>
      <div class="edition-panel-header">
        <h2><i class="fa-solid fa-sliders"></i> <?= esc_html__('Paramètres', 'chassesautresor-com'); ?></h2>
      </div>
      <div class="edition-panel-body">
        <div class="edition-panel-section edition-panel-section-ligne">
          <div class="section-content">
            <div class="resume-blocs-grid">
              <div class="resume-bloc resume-obligatoire">

                <h3><?= esc_html__('Informations', 'chassesautresor-com'); ?></h3>
                <ul class="resume-infos">
                  <?php
                  get_template_part(
                      'template-parts/common/edition-row',
                      null,
                      [
                          'class' => 'champ-enigme champ-titre ' . ($isTitreParDefaut ? 'champ-vide' : 'champ-rempli') . ($peut_editer_titre ? '' : ' champ-desactive'),
                          'attributes' => [
                              'data-champ'   => 'post_title',
                              'data-cpt'     => 'enigme',
                              'data-post-id' => $enigme_id,
                              'data-no-edit' => '1',
                          ],
                          'label' => function () {
                              ?>
                              <label for="champ-titre-enigme"><?= esc_html__('Titre', 'chassesautresor-com'); ?> <span class="champ-obligatoire">*</span></label>
                              <?php
                          },
                          'content' => function () use ($titre, $peut_editer_titre) {
                              ?>
                              <input type="text"
                                class="champ-input champ-texte-edit"
                                maxlength="80"
                                value="<?= esc_attr($titre); ?>"
                                id="champ-titre-enigme" <?= $peut_editer_titre ? '' : 'disabled'; ?>
                                placeholder="<?= esc_attr__('renseigner le titre de l’énigme', 'chassesautresor-com'); ?>" />
                              <div class="champ-feedback"></div>
                              <?php
                          },
                      ]
                  );
                  ?>

                  <?php
                  $has_images_utiles = enigme_a_une_image($enigme_id);
                  $images_ids       = get_field('enigme_visuel_image', $enigme_id, false);
                  get_template_part(
                      'template-parts/common/edition-row',
                      null,
                      [
                          'class'      => 'champ-enigme champ-img ' . ($has_images_utiles ? 'champ-rempli' : 'champ-vide') . ($peut_editer ? '' : ' champ-desactive'),
                          'attributes' => [
                              'data-champ'   => 'enigme_visuel_image',
                              'data-cpt'     => 'enigme',
                              'data-post-id' => $enigme_id,
                              'data-rempli'  => $has_images_utiles ? '1' : '0',
                          ],
                          'label' => function () {
                              ?>
                              <label><?= esc_html__('Illustrations', 'chassesautresor-com'); ?> <span class="champ-obligatoire">*</span></label>
                              <?php
                          },
                          'content' => function () use ($has_images_utiles, $images_ids, $peut_editer, $enigme_id) {
                              ?>
                              <div class="champ-affichage">
                                <?php if ($peut_editer) : ?>
                                  <button type="button"
                                    class="champ-modifier ouvrir-panneau-images"
                                    data-champ="enigme_visuel_image"
                                    data-cpt="enigme"
                                    data-post-id="<?= esc_attr($enigme_id); ?>">
                                    <?php if ($has_images_utiles && is_array($images_ids)) : ?>
                                      <?php foreach ($images_ids as $img_id) : ?>
                                        <?php $thumb_url = esc_url(add_query_arg([
                                          'id'     => $img_id,
                                          'taille' => 'thumbnail',
                                        ], site_url('/voir-image-enigme'))); ?>
                                        <img src="<?= $thumb_url; ?>" alt="" class="vignette-enigme" />
                                      <?php endforeach; ?>
                                    <?php else : ?>
                                      <span class="champ-ajout-image"><?= esc_html__('ajouter', 'chassesautresor-com'); ?></span>
                                    <?php endif; ?>
                                  </button>
                                <?php else : ?>
                                  <?php if ($has_images_utiles && is_array($images_ids)) : ?>
                                    <?php foreach ($images_ids as $img_id) : ?>
                                      <?php $thumb_url = esc_url(add_query_arg([
                                        'id'     => $img_id,
                                        'taille' => 'thumbnail',
                                      ], site_url('/voir-image-enigme'))); ?>
                                      <img src="<?= $thumb_url; ?>" alt="" class="vignette-enigme" />
                                    <?php endforeach; ?>
                                  <?php else : ?>
                                    <span class="champ-ajout-image"><?= esc_html__('ajouter', 'chassesautresor-com'); ?></span>
                                  <?php endif; ?>
                                <?php endif; ?>
                              </div>
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
                          'class'      => 'champ-enigme champ-wysiwyg' . (empty(trim($texte_enigme)) ? ' champ-vide' : ' champ-rempli') . ($peut_editer ? '' : ' champ-desactive'),
                          'attributes' => [
                              'data-champ'   => 'enigme_visuel_texte',
                              'data-cpt'     => 'enigme',
                              'data-post-id' => $enigme_id,
                          ],
                          'label' => function () {
                              ?>
                              <label><?= esc_html__('Texte énigme', 'chassesautresor-com'); ?></label>
                              <?php
                          },
                          'content' => function () use ($texte_enigme, $peut_editer, $enigme_id) {
                              ?>
                              <div class="champ-texte">
                                <?php if (empty(trim($texte_enigme))) : ?>
                                  <?php if ($peut_editer) : ?>
                                    <a href="#" class="champ-ajouter ouvrir-panneau-description"
                                      data-champ="enigme_visuel_texte"
                                      data-cpt="enigme"
                                      data-post-id="<?= esc_attr($enigme_id); ?>">
                                      <?= esc_html__('ajouter', 'chassesautresor-com'); ?>
                                    </a>
                                  <?php endif; ?>
                                <?php else : ?>
                                  <span class="champ-texte-contenu">
                                    <?= esc_html(wp_trim_words(wp_strip_all_tags($texte_enigme), 25)); ?>
                                  </span>
                                  <?php if ($peut_editer) : ?>
                                    <button type="button" class="champ-modifier ouvrir-panneau-description"
                                      data-champ="enigme_visuel_texte"
                                      data-cpt="enigme"
                                      data-post-id="<?= esc_attr($enigme_id); ?>"
                                      aria-label="<?= esc_attr__('Éditer le texte', 'chassesautresor-com'); ?>">
                                      <?= esc_html__('éditer', 'chassesautresor-com'); ?>
                                    </button>
                                  <?php endif; ?>
                                <?php endif; ?>
                              </div>
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
                          'class'      => 'champ-enigme champ-texte champ-soustitre' . (empty(trim($legende)) ? ' champ-vide' : ' champ-rempli') . ($peut_editer ? '' : ' champ-desactive'),
                          'attributes' => [
                              'data-champ'   => 'enigme_visuel_legende',
                              'data-cpt'     => 'enigme',
                              'data-post-id' => $enigme_id,
                              'data-no-edit' => '1',
                          ],
                          'label' => function () {
                              ?>
                              <label for="champ-soustitre-enigme"><?= esc_html__('Sous-titre', 'chassesautresor-com'); ?></label>
                              <?php
                          },
                          'content' => function () use ($legende, $peut_editer) {
                              ?>
                              <input type="text" class="champ-input champ-texte-edit" maxlength="100" value="<?= esc_attr($legende); ?>" id="champ-soustitre-enigme"
                                placeholder="<?= esc_attr__('Ajouter un sous-titre (max 100 caractères)', 'chassesautresor-com'); ?>" <?= $peut_editer ? '' : 'disabled'; ?> />
                              <div class="champ-feedback"></div>
                              <?php
                          },
                      ]
                  );
                  ?>
                </ul>
              </div>

              <!-- Règlages -->
              <div class="resume-bloc resume-reglages">
                <h3><?= esc_html__('Réglages', 'chassesautresor-com'); ?></h3>
                <ul class="resume-infos">

            <!-- Mode de validation -->
            <?php
            get_template_part(
                'template-parts/common/edition-row',
                null,
                [
                    'class'      => 'champ-enigme champ-mode-validation champ-mode-fin' . ($peut_editer ? '' : ' champ-desactive'),
                    'attributes' => [
                        'data-champ'   => 'enigme_mode_validation',
                        'data-cpt'     => 'enigme',
                        'data-post-id' => $enigme_id,
                        'data-no-edit' => '1',
                        'data-no-icon' => '1',
                    ],
                    'label' => function () {
                        ?>
                        <label for="enigme_mode_validation"><?= esc_html__('Validation', 'chassesautresor-com'); ?></label>
                        <?php
                    },
                    'content' => function () use ($mode_validation, $peut_editer, $enigme_id) {
                        ?>
                        <div class="champ-mode-options segmented-control">
                            <input id="enigme_mode_validation_auto" type="radio" name="acf[enigme_mode_validation]" value="automatique" <?= $mode_validation === 'automatique' ? 'checked' : ''; ?> <?= $peut_editer ? '' : 'disabled'; ?>>
                            <label for="enigme_mode_validation_auto">
                                <?= esc_html__('Automatique', 'chassesautresor-com'); ?>
                                <?php
                                get_template_part(
                                    'template-parts/common/help-icon',
                                    null,
                                    [
                                        'aria_label' => __('Explication du mode automatique', 'chassesautresor-com'),
                                        'classes'    => 'validation-aide',
                                        'variant'    => 'aide',
                                        'title'      => __('Validation automatique', 'chassesautresor-com'),
                                        'message'    => __('Le joueur soumet une tentative de réponse. Celle-ci est automatiquement vérifiée selon les critères définis (réponse attendue, respect de la casse, variantes), et le résultat est immédiatement communiqué au joueur.', 'chassesautresor-com'),
                                    ]
                                );
                                ?>
                            </label>

                            <input id="enigme_mode_validation_manuelle" type="radio" name="acf[enigme_mode_validation]" value="manuelle" <?= $mode_validation === 'manuelle' ? 'checked' : ''; ?> <?= $peut_editer ? '' : 'disabled'; ?>>
                            <label for="enigme_mode_validation_manuelle">
                                <?= esc_html__('Manuelle', 'chassesautresor-com'); ?>
                                <?php
                                get_template_part(
                                    'template-parts/common/help-icon',
                                    null,
                                    [
                                        'aria_label' => __('Explication du mode manuel', 'chassesautresor-com'),
                                        'classes'    => 'validation-aide',
                                        'variant'    => 'aide',
                                        'title'      => __('Validation manuelle', 'chassesautresor-com'),
                                        'message'    => __('Le joueur rédige une réponse libre. Vous validez ou refusez ensuite sa tentative depuis votre espace personnel. À chaque nouvelle soumission, vous recevez une notification par email ainsi qu’un message d’alerte.', 'chassesautresor-com'),
                                    ]
                                );
                                ?>
                            </label>

                            <input id="enigme_mode_validation_aucune" type="radio" name="acf[enigme_mode_validation]" value="aucune" <?= $mode_validation === 'aucune' ? 'checked' : ''; ?> <?= $peut_editer ? '' : 'disabled'; ?>>
                            <label for="enigme_mode_validation_aucune">
                                <?= esc_html__('Aucune', 'chassesautresor-com'); ?>
                            </label>
                        </div>
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
                    'class'      => 'champ-enigme champ-bonne-reponse champ-groupe-reponse-automatique cache' . (empty($reponses) ? ' champ-vide' : ' champ-rempli') . ($peut_editer ? '' : ' champ-desactive'),
                    'attributes' => [
                        'data-champ'    => 'enigme_reponse_bonne',
                        'data-cpt'      => 'enigme',
                        'data-post-id'  => $enigme_id,
                        'data-reponses' => wp_json_encode($reponses),
                        'data-no-edit'  => '1',
                        'data-no-icon'  => '1',
                    ],
                    'label' => function () {
                        ?>
                        <label for="champ-bonne-reponse">
                            <?= esc_html__('Bonne(s) réponse(s)', 'chassesautresor-com'); ?> <span class="champ-obligatoire">*</span>
                            <?php
                            get_template_part(
                                'template-parts/common/help-icon',
                                null,
                                [
                                    'title'   => __('La ou les bonnes réponses', 'chassesautresor-com'),
                                    'message' => __('Vous pouvez saisir de 1 à 5 bonnes réponses. Tout joueur qui en soumet une — selon votre réglage de respect de la casse — résout l’énigme.', 'chassesautresor-com'),
                                    'variant' => 'info',
                                    'classes' => 'bonne-reponse-aide',
                                ]
                            );
                            ?>
                        </label>
                        <?php
                    },
                    'content' => function () use ($reponses, $casse, $peut_editer, $enigme_id) {
                        ?>
                        <div class="bonnes-reponses-wrapper<?= empty($reponses) ? ' champ-vide-obligatoire' : ''; ?>"></div>
                        <div class="champ-enigme champ-casse <?= $casse ? 'champ-rempli' : 'champ-vide'; ?><?= $peut_editer ? '' : ' champ-desactive'; ?>" data-champ="enigme_reponse_casse" data-cpt="enigme" data-post-id="<?= esc_attr($enigme_id); ?>" data-no-edit="1">
                            <label style="display: flex; align-items: center; gap: 4px;"><input type="checkbox" <?= $casse ? 'checked' : ''; ?> <?= $peut_editer ? '' : 'disabled'; ?>> <?= esc_html__('Respecter la casse', 'chassesautresor-com'); ?></label>
                            <div class="champ-feedback"></div>
                        </div>
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
                    'class'      => 'champ-enigme champ-variantes-resume champ-groupe-reponse-automatique cache' . ($has_variantes ? ' champ-rempli' : ' champ-vide') . ($peut_editer ? '' : ' champ-desactive'),
                    'attributes' => [
                        'data-champ'   => 'enigme_reponse_variantes',
                        'data-cpt'     => 'enigme',
                        'data-post-id' => $enigme_id,
                        'data-no-edit' => '1',
                        'data-no-icon' => '1',
                    ],
                    'label' => function () {
                        ?>
                        <label>
                            <?= esc_html__('Variantes', 'chassesautresor-com'); ?>
                            <?php
                            get_template_part(
                                'template-parts/common/help-icon',
                                null,
                                [
                                    'aria_label' => __('Explication des variantes', 'chassesautresor-com'),
                                    'classes'    => 'variantes-aide',
                                    'variant'    => 'info',
                                    'title'      => __('Système de variantes', 'chassesautresor-com'),
                                    'message'    => __('Les variantes sont des réponses alternatives qui ne sont pas validées comme correctes, mais qui déclenchent un message personnalisé en retour (par exemple une aide, un indice, un lien ou tout autre contenu de votre choix).', 'chassesautresor-com'),
                                ]
                            );
                            ?>
                        </label>
                        <?php
                    },
                    'content' => function () use ($has_variantes, $variantes_list, $peut_editer, $enigme_id) {
                        ?>
                        <?php if ($has_variantes) : ?>
                            <table class="variantes-table">
                                <thead>
                                    <tr>
                                        <th scope="col"><?= esc_html__('Variante', 'chassesautresor-com'); ?></th>
                                        <th scope="col"><?= esc_html__('Message', 'chassesautresor-com'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($variantes_list as $var) : ?>
                                        <tr class="variante-resume">
                                            <td class="variante-texte"><?= esc_html($var['texte']); ?></td>
                                            <td class="variante-message"><?= esc_html($var['message']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php if ($peut_editer) : ?>
                                <button type="button" class="champ-modifier ouvrir-panneau-variantes" aria-label="<?= esc_attr__('Éditer les variantes', 'chassesautresor-com'); ?>" data-cpt="enigme" data-post-id="<?= esc_attr($enigme_id); ?>">
                                    <?= esc_html__('éditer', 'chassesautresor-com'); ?>
                                </button>
                            <?php endif; ?>
                        <?php elseif ($peut_editer) : ?>
                            <a href="#" class="champ-ajouter ouvrir-panneau-variantes" aria-label="<?= esc_attr__('Ajouter des variantes', 'chassesautresor-com'); ?>" data-cpt="enigme" data-post-id="<?= esc_attr($enigme_id); ?>">
                                <?= esc_html__('ajouter des variantes', 'chassesautresor-com'); ?>
                            </a>
                        <?php endif; ?>
                        <?php
                    },
                ]
            );
            ?>

            <!-- Tentatives -->
            <?php
            get_template_part(
                'template-parts/common/edition-row',
                null,
                [
                    'class'      => 'champ-enigme champ-nb-tentatives ' . (empty($max) ? 'champ-vide' : 'champ-rempli') . ($peut_editer ? '' : ' champ-desactive') . ($mode_validation === 'automatique' ? '' : ' cache'),
                    'attributes' => [
                        'data-champ'   => 'enigme_tentative.enigme_tentative_max',
                        'data-cpt'     => 'enigme',
                        'data-post-id' => $enigme_id,
                        'data-no-edit' => '1',
                        'data-no-icon' => '1',
                    ],
                    'label' => function () {
                        ?>
                        <label for="enigme-nb-tentatives"><?= esc_html__('Nb tentatives', 'chassesautresor-com'); ?>
                            <?php
                            get_template_part(
                                'template-parts/common/help-icon',
                                null,
                                [
                                    'aria_label' => __('Explication du nombre de tentatives', 'chassesautresor-com'),
                                    'classes'    => 'tentatives-aide',
                                    'variant'    => 'info',
                                    'title'      => __('Plafond nb de tentatives quotidiennes', 'chassesautresor-com'),
                                    'message'    => __("Nombre maximal de tentatives quotidiennes par joueur:\n\nMode payant : illimitées\n\nMode gratuit : 24 tentatives par jour", 'chassesautresor-com'),
                                ]
                            );
                            ?>
                        </label>
                        <?php
                    },
                    'content' => function () use ($max, $peut_editer) {
                        ?>
                        <div class="champ-edition">
                            <input type="number" id="enigme-nb-tentatives" class="champ-input champ-nb-tentatives champ-number" min="1" max="999999" step="1" value="<?= esc_attr($max); ?>" placeholder="5" <?= $peut_editer ? '' : 'disabled'; ?> />
                            <span class="champ-status"></span>
                            <span class="txt-small"><?= esc_html__('max par jour', 'chassesautresor-com'); ?></span>
                        </div>
                        <div class="champ-feedback"></div>
                        <?php
                    },
                ]
            );
            ?>

            <?php
            $cout_attrs = [
                'data-champ'   => 'enigme_tentative.enigme_tentative_cout_points',
                'data-cpt'     => 'enigme',
                'data-post-id' => $enigme_id,
                'data-no-edit' => '1',
                'data-no-icon' => '1',
            ];
            if ($mode_validation === 'aucune') {
                $cout_attrs['style'] = 'display:none;';
            }
            $cout_normalise = trim((string) $cout);
            $is_payant = $cout_normalise !== '' && $cout_normalise !== '0' && (int) $cout !== 0;
            get_template_part(
                'template-parts/common/edition-row',
                null,
                [
                    'class'      => 'champ-enigme champ-cout-points champ-mode-fin ' . ($is_payant ? 'champ-rempli' : 'champ-vide') . ($peut_editer ? '' : ' champ-desactive') . ($mode_validation === 'aucune' ? ' cache' : ''),
                    'attributes' => $cout_attrs,
                    'label' => function () {
                        ?>
                        <label for="enigme-cout-toggle"><?= esc_html__('Coût tentative', 'chassesautresor-com'); ?>
                            <?php
                            get_template_part(
                                'template-parts/common/help-icon',
                                null,
                                [
                                    'aria_label' => __('Informations sur le coût des tentatives', 'chassesautresor-com'),
                                    'classes'    => 'open-points-modal',
                                    'variant'    => 'info',
                                    'title'      => __('Tentative gratuite ou payante ?', 'chassesautresor-com'),
                                    'message'    => __('Vous êtes libre de définir le coût d’une tentative pour votre énigme : gratuite ou payante en points. Lorsqu’un joueur dépense des points pour soumettre une réponse, ceux-ci sont immédiatement crédités sur votre compte.', 'chassesautresor-com'),
                                ]
                            );
                            ?>
                        </label>
                        <?php
                    },
                    'content' => function () use ($cout, $peut_editer, $is_payant, $enigme_id) {
                        ?>
                        <div class="champ-mode-options">
                            <span class="toggle-option"><?= esc_html__('Free', 'chassesautresor-com'); ?></span>
                            <label class="switch-control">
                                <input type="checkbox" id="enigme-cout-toggle" <?= $is_payant ? 'checked' : ''; ?> <?= $peut_editer ? '' : 'disabled'; ?>>
                                <span class="switch-slider"></span>
                            </label>
                            <span class="toggle-option"><?= esc_html__('Points', 'chassesautresor-com'); ?></span>
                            <div id="champ-enigme-cout" class="champ-edition<?= $is_payant ? '' : ' cache'; ?>">
                                <input type="number" id="enigme-tentative-cout" class="champ-input champ-cout champ-number" min="0" max="999999" step="1" value="<?= esc_attr($is_payant ? $cout : '0'); ?>" placeholder="0" <?= $peut_editer ? '' : 'disabled'; ?> />
                                <span class="champ-status"></span>
                                <span class="txt-small"><?= esc_html__('points', 'chassesautresor-com'); ?></span>
                            </div>
                        </div>
                        <div class="champ-feedback"></div>
                        <?php
                    },
                ]
            );
            ?>

            <!-- Accès à l'énigme -->
            <?php
            if (!function_exists('enigme_get_liste_prerequis_possibles')) {
                require_once get_stylesheet_directory() . '/inc/enigme/cta.php';
            }

            $condition           = get_field('enigme_acces_condition', $enigme_id) ?? 'immediat';
            $pre_requis_selected = get_field('enigme_acces_pre_requis', $enigme_id) ?: [];
            $prerequis_possibles = enigme_get_liste_prerequis_possibles($enigme_id);
            $has_prerequis       = !empty($prerequis_possibles);
            ?>
            <?php
            get_template_part(
                'template-parts/common/edition-row',
                null,
                [
                    'class'      => 'champ-enigme champ-acces champ-mode-fin' . ($peut_editer ? '' : ' champ-desactive'),
                    'attributes' => [
                        'data-champ'   => 'enigme_acces_condition',
                        'data-cpt'     => 'enigme',
                        'data-post-id' => $enigme_id,
                        'data-no-edit' => '1',
                        'data-no-icon' => '1',
                    ],
                    'label' => function () {
                        ?>
                        <label for="enigme_acces_libre"><?= esc_html__('Accès', 'chassesautresor-com'); ?></label>
                        <?php
                    },
                    'content' => function () use ($condition, $peut_editer, $date_deblocage, $enigme_id, $prerequis_possibles, $pre_requis_selected, $has_prerequis) {
                        ?>
                        <div class="champ-mode-options segmented-control">
                            <input id="enigme_acces_libre" type="radio" name="acf[enigme_acces_condition]" value="immediat" <?= $condition === 'immediat' ? 'checked' : ''; ?> <?= $peut_editer ? '' : 'disabled'; ?>>
                            <label for="enigme_acces_libre"><?= esc_html__('Libre', 'chassesautresor-com'); ?></label>

                            <input id="enigme_acces_date" type="radio" name="acf[enigme_acces_condition]" value="date_programmee" <?= $condition === 'date_programmee' ? 'checked' : ''; ?> <?= $peut_editer ? '' : 'disabled'; ?>>
                            <label for="enigme_acces_date"><?= esc_html__('Date programmée', 'chassesautresor-com'); ?></label>

                            <input id="enigme_acces_prerequis" type="radio" name="acf[enigme_acces_condition]" value="pre_requis" <?= $condition === 'pre_requis' ? 'checked' : ''; ?> <?= $peut_editer && $has_prerequis ? '' : 'disabled'; ?>>
                            <label for="enigme_acces_prerequis">
                                <?= esc_html__('Pré-requis', 'chassesautresor-com'); ?>
                                <?php
                                $message = $has_prerequis
                                    ? __('Sélectionnez les énigmes à résoudre avant celle-ci.', 'chassesautresor-com')
                                    : __('Aucune autre énigme disponible comme pré-requis.', 'chassesautresor-com');
                                get_template_part(
                                    'template-parts/common/help-icon',
                                    null,
                                    [
                                        'aria_label' => $message,
                                        'message'    => $message,
                                        'variant'    => 'info',
                                        'classes'    => 'validation-aide',
                                    ]
                                );
                                ?>
                            </label>
                        </div>

                        <div id="champ-enigme-date" class="champ-enigme champ-date<?= $condition === 'date_programmee' ? '' : ' cache'; ?><?= $peut_editer ? '' : ' champ-desactive'; ?>" data-champ="enigme_acces_date" data-cpt="enigme" data-post-id="<?= esc_attr($enigme_id); ?>" data-no-edit="1">
                            <input type="datetime-local" id="enigme-date-deblocage" name="enigme-date-deblocage" value="<?= esc_attr($date_deblocage); ?>" data-previous="<?= esc_attr($date_deblocage); ?>" class="champ-inline-date champ-date-edit" <?= $peut_editer ? '' : 'disabled'; ?> />
                            <span class="champ-status"></span>
                            <div class="champ-feedback champ-date-feedback" style="display:none;"></div>
                        </div>

                        <div id="champ-enigme-pre-requis" class="champ-enigme champ-pre-requis<?= $condition === 'pre_requis' && $has_prerequis ? '' : ' cache'; ?><?= $peut_editer ? '' : ' champ-desactive'; ?><?= empty($pre_requis_selected) ? ' champ-vide' : ''; ?>" data-champ="enigme_acces_pre_requis" data-cpt="enigme" data-post-id="<?= esc_attr($enigme_id); ?>" data-no-edit="1">
                            <?php if ($has_prerequis) : ?>
                                <div class="liste-pre-requis">
                                    <?php foreach ($prerequis_possibles as $id => $titre) : ?>
                                        <?php
                                        $checked = in_array($id, $pre_requis_selected, true) ? 'checked' : '';
                                        ?>
                                        <label class="prerequis-item">
                                            <input type="checkbox" value="<?= esc_attr($id); ?>" <?= $checked; ?> <?= $peut_editer ? '' : 'disabled'; ?>>
                                            <span class="prerequis-mini">
                                                <span class="prerequis-titre"><?= esc_html($titre); ?></span>
                                                <span class="prerequis-check"><i class="fa-solid fa-check"></i></span>
                                            </span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <span class="champ-status"></span>
                                <div class="champ-feedback"></div>
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
      </div>
      </div>
      </div>

      </div> <!-- .edition-panel-body -->
    <?php if (utilisateur_peut_supprimer_enigme($enigme_id)) : ?>
      <div class="edition-panel-footer">
        <button type="button" id="bouton-supprimer-enigme" class="bouton-texte secondaire"><?= esc_html__('❌ Suppression énigme', 'chassesautresor-com'); ?></button>
      </div>
    <?php endif; ?>
    </div> <!-- #enigme-tab-param -->

    <div id="enigme-tab-stats" class="edition-tab-content" style="display:none;">
      <i class="fa-solid fa-chart-column tab-watermark" aria-hidden="true"></i>
      <div class="edition-panel-header">
        <h2><i class="fa-solid fa-chart-column"></i> <?= esc_html__('Statistiques', 'chassesautresor-com'); ?></h2>
      </div>
      <?php
      if (!function_exists('enigme_compter_joueurs_engages')) {
          require_once get_stylesheet_directory() . '/inc/enigme/stats.php';
      }
        $periode = 'total';
        if ($stats_locked) {
            $nb_participants = $nb_tentatives = $nb_points = $nb_solutions = 0;
        } else {
            $nb_participants = enigme_compter_joueurs_engages($enigme_id, $periode);
            $nb_tentatives   = enigme_compter_tentatives($enigme_id, $mode_validation, $periode);
            $nb_points       = enigme_compter_points_depenses($enigme_id, $mode_validation, $periode);
            $nb_solutions    = enigme_compter_bonnes_solutions($enigme_id, $mode_validation, $periode);
        }
      ?>
      <div class="edition-panel-body">
        <div class="stats-header" style="display:flex;align-items:center;justify-content:flex-end;gap:1rem;">
            <a href="?edition=open&amp;tab=stats" class="stats-reset"><i class="fa-solid fa-rotate-right"></i> <?= esc_html__('Actualiser', 'chassesautresor-com'); ?></a>
          <div class="stats-filtres">
            <label for="enigme-periode"><?= esc_html__('Période :', 'chassesautresor-com'); ?></label>
            <select id="enigme-periode">
              <option value="total"><?= esc_html__('Total', 'chassesautresor-com'); ?></option>
              <option value="jour"><?= esc_html__('Aujourd’hui', 'chassesautresor-com'); ?></option>
              <option value="semaine"><?= esc_html__('Semaine', 'chassesautresor-com'); ?></option>
              <option value="mois"><?= esc_html__('Mois', 'chassesautresor-com'); ?></option>
            </select>
          </div>
        </div>
        <div class="dashboard-grid stats-cards" id="enigme-stats">
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
              'style' => $mode_validation === 'aucune' ? 'display:none;' : '',
              'class' => $card_class,
          ]);
          get_template_part('template-parts/common/stat-card', null, [
              'icon'  => 'fa-solid fa-coins',
                'label' => esc_html__('Points collectés', 'chassesautresor-com'),
              'value' => $nb_points,
              'stat'  => 'points',
              'style' => ($mode_validation === 'aucune' || (int) $cout <= 0) ? 'display:none;' : '',
              'class' => $card_class,
          ]);
          get_template_part('template-parts/common/stat-card', null, [
              'icon'  => 'fa-solid fa-check',
                'label' => esc_html__('Bonnes réponses', 'chassesautresor-com'),
              'value' => $nb_solutions,
              'stat'  => 'solutions',
              'style' => $mode_validation === 'aucune' ? 'display:none;' : '',
              'class' => $card_class,
          ]);
          ?>
        </div>
        <?php
        $resolveurs    = ($stats_locked || $mode_validation === 'aucune') ? [] : enigme_lister_resolveurs($enigme_id);
        $nb_resolveurs = count($resolveurs);
        if ($nb_resolveurs > 0) :
        ?>
        <div id="enigme-resolveurs">
            <h3><?= sprintf(esc_html__('Résolue par (%s) joueurs', 'chassesautresor-com'), esc_html($nb_resolveurs)); ?></h3>
          <div class="stats-table-wrapper">
            <table class="stats-table" id="enigme-resolveurs-table">
              <thead>
                  <tr>
                    <th scope="col" data-format="etiquette"><?= esc_html__('Rang', 'chassesautresor-com'); ?></th>
                    <th scope="col"><?= esc_html__('Joueur', 'chassesautresor-com'); ?></th>
                    <th scope="col"><?= esc_html__('Date', 'chassesautresor-com'); ?></th>
                    <th scope="col" data-format="etiquette"><?= esc_html__('Tentatives', 'chassesautresor-com'); ?></th>
                  </tr>
              </thead>
              <tbody>
                <?php $rang = 1; foreach ($resolveurs as $res) : ?>
                <tr>
                  <td><?= esc_html($rang++); ?></td>
                  <td><?= esc_html($res['username']); ?></td>
                  <td><?= esc_html(mysql2date('d/m/Y H:i', $res['date'])); ?></td>
                  <td><?= esc_html($res['tentatives']); ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <?php endif; ?>

        <?php
        $par_page_participants = 25;
        $pages_participants    = $stats_locked ? 0 : (int) ceil($nb_participants / $par_page_participants);
        $participants         = $stats_locked ? [] : enigme_lister_participants(
            $enigme_id,
            $mode_validation,
            $par_page_participants,
            0,
            'date',
            'ASC'
        );
        ?>
        <div class="liste-participants" data-page="1" data-pages="<?= esc_attr($pages_participants); ?>" data-order="asc" data-orderby="date">
          <?php get_template_part('template-parts/enigme/partials/enigme-partial-participants', null, [
              'participants'  => $participants,
              'page'          => 1,
              'par_page'      => $par_page_participants,
              'total'         => $nb_participants,
              'pages'         => $pages_participants,
              'mode_validation' => $mode_validation,
              'orderby'       => 'date',
              'order'         => 'ASC',
              'stats_locked'  => $stats_locked,
          ]); ?>
        </div>
      </div>
    </div>

<div id="enigme-tab-soumission" class="edition-tab-content" style="display:none;">
  <i class="fa-solid fa-paper-plane tab-watermark" aria-hidden="true"></i>
  <div class="edition-panel-header">
      <h2><i class="fa-solid fa-paper-plane"></i> <?= esc_html__('Tentatives', 'chassesautresor-com'); ?> <span class="total-tentatives">(<?= intval(compter_tentatives_enigme($enigme_id)); ?>)</span></h2>
  </div>
<?php
  if (!function_exists('recuperer_tentatives_enigme')) {
    require_once get_stylesheet_directory() . '/inc/enigme/tentatives.php';
  }

  $page_tentatives = max(1, intval($_GET['page_tentatives'] ?? 1));
  $par_page = 20;
  $offset = ($page_tentatives - 1) * $par_page;
  $tentatives = recuperer_tentatives_enigme($enigme_id, $par_page, $offset);
  $total_tentatives = compter_tentatives_enigme($enigme_id);
  $pages_tentatives = (int) ceil($total_tentatives / $par_page);
  ?>
  <div class="liste-tentatives" data-page="<?= esc_attr($page_tentatives); ?>" data-total="<?= esc_attr($total_tentatives); ?>" data-pages="<?= esc_attr($pages_tentatives); ?>">
    <?php get_template_part('template-parts/enigme/partials/enigme-partial-tentatives', null, [
      'tentatives' => $tentatives,
      'page'       => $page_tentatives,
      'par_page'   => $par_page,
      'total'      => $total_tentatives,
      'pages'      => $pages_tentatives,
    ]); ?>
  </div>
</div>

<?php
get_template_part(
    'template-parts/common/edition-animation',
    null,
    [
        'objet_type' => 'enigme',
        'objet_id'   => $enigme_id,
    ]
);
?>
  </section>
<?php endif; ?>
