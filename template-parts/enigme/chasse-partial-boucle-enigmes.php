<?php

/**
 * Partial : chasse-partial-boucle-enigmes.php
 * Affiche la grille des énigmes d'une chasse (carte par carte).
 */

defined('ABSPATH') || exit;

$chasse_id = $args['chasse_id'] ?? null;
if (!$chasse_id || get_post_type($chasse_id) !== 'chasse') return;

$infos_chasse = $args['infos_chasse'] ?? preparer_infos_affichage_chasse($chasse_id);

$utilisateur_id = get_current_user_id();

// 🔒 Vérification d'accès à la chasse
if (!chasse_est_visible_pour_utilisateur($chasse_id, $utilisateur_id)) return;

$est_orga_associe = $args['est_orga_associe'] ?? utilisateur_est_organisateur_associe_a_chasse($utilisateur_id, $chasse_id);
$show_help_icon  = $args['show_help_icon'] ?? false;
$statut_metier   = $infos_chasse['statut'] ?? 'revision';

$autorise_boucle = (
    user_can($utilisateur_id, 'manage_options') ||
    $est_orga_associe ||
    utilisateur_est_engage_dans_chasse($utilisateur_id, $chasse_id)
);
if (!$autorise_boucle) {
    return;
}

$est_joueur_engage = utilisateur_est_engage_dans_chasse($utilisateur_id, $chasse_id);

// 🔁 Récupération des énigmes associées
$ids_enigmes = $infos_chasse['enigmes_associees'] ?? [];

if (!empty($ids_enigmes)) {
    $posts = get_posts([
        'post_type'      => 'enigme',
        'post_status'    => ['publish', 'pending', 'draft'],
        'posts_per_page' => count($ids_enigmes),
        'post__in'       => array_map('intval', $ids_enigmes),
        'orderby'        => 'post__in',
    ]);
} else {
    $posts = [];
}

$posts_visibles = $posts;
$has_enigmes = !empty($posts_visibles);

$est_orga = est_organisateur();
$statut_chasse = get_post_status($chasse_id);
$peut_reordonner = in_array(
    $infos_chasse['statut_validation'] ?? '',
    ['creation', 'correction'],
    true
) && ($est_orga_associe || user_can($utilisateur_id, 'manage_options'));
$attr_draggable = $peut_reordonner ? ' draggable="true"' : '';

// 📌 Vérifie si une énigme est incomplète
$has_incomplete = false;
$completion_statuses = [];
if ($peut_reordonner || user_can($utilisateur_id, 'manage_options')) {
    foreach ($posts as $p) {
        verifier_ou_mettre_a_jour_cache_complet($p->ID);
        $completion_statuses[$p->ID] = (bool) get_field('enigme_cache_complet', $p->ID);
        if (!$completion_statuses[$p->ID]) {
            $has_incomplete = true;
        }
    }
}

if (!function_exists('enigme_compter_joueurs_engages')) {
  require_once get_stylesheet_directory() . '/inc/enigme/stats.php';
}

if (!function_exists('compter_tentatives_du_jour') || !function_exists('compter_tentatives_en_attente')) {
  require_once get_stylesheet_directory() . '/inc/enigme/tentatives.php';
}
?>

<div class="bloc-enigmes-chasse">
  <div class="cards-grid" data-chasse-id="<?= esc_attr($chasse_id); ?>">
    <?php foreach ($posts_visibles as $post):
      $enigme_id = $post->ID;
      $titre = get_the_title($enigme_id);
      $cta = get_cta_enigme($enigme_id, $utilisateur_id);
      $type_cta = $cta['type'] ?? 'inconnu';
      $classe_cta = 'cta-' . sanitize_html_class($type_cta);
      $mode_validation = get_field('enigme_mode_validation', $enigme_id);
      $linkable = $cta['action'] === 'link';
      $aria_label = $linkable
        ? sprintf(__('Ouvrir l\'énigme — %s', 'chassesautresor-com'), $cta['label'])
        : '';
      $statut_utilisateur = $cta['statut_utilisateur'] ?? '';
      $afficher_validation = in_array(
        $statut_utilisateur,
        ['resolue', 'terminee'],
        true
      );
      $classes_bouton = in_array(
        $statut_utilisateur,
        ['non_commencee', 'echouee', 'abandonnee', 'soumis'],
        true
      )
        ? 'bouton bouton-cta bouton-cta--color'
        : 'bouton bouton-secondaire';

      // 🔍 Vérification bordure admin/orga
      $statut_enigme = get_post_status($enigme_id);
      $voir_bordure = $est_orga &&
        $est_orga_associe &&
        $statut_chasse !== 'publish' &&
        $statut_enigme !== 'publish';

      $classe_completion = '';
      if (
        $voir_bordure
        && ($peut_reordonner || user_can($utilisateur_id, 'manage_options'))
      ) {
        $complet = $completion_statuses[$enigme_id] ?? false;
        $classe_completion = $complet ? 'carte-complete' : 'carte-incomplete';
      }

      $classes_carte = trim("carte carte-enigme $classe_completion $classe_cta");
      if (
        $est_joueur_engage
        && $statut_utilisateur === 'non_commencee'
      ) {
        $classes_carte .= ' carte-engagee';
      }
      $mapping_visuel = get_mapping_visuel_enigme($enigme_id);
      $cout_points    = (int) get_field('enigme_tentative_cout_points', $enigme_id);
      $nb_participants = enigme_compter_joueurs_engages($enigme_id);
      $nb_resolutions  = enigme_compter_bonnes_solutions($enigme_id);
      $tentatives_max  = (int) get_field('enigme_tentative_max', $enigme_id);
      $tentatives_utilisees = ($tentatives_max > 0 && $mode_validation === 'automatique')
        ? compter_tentatives_du_jour($utilisateur_id, $enigme_id)
        : 0;
    ?>
        <article class="<?= esc_attr($classes_carte); ?>" data-enigme-id="<?= esc_attr($enigme_id); ?>"<?= $attr_draggable; ?>>
            <?php if ($peut_reordonner) : ?>
            <span class="carte-enigme-handle" aria-hidden="true"><i class="fa-solid fa-up-down-left-right"></i></span>
            <?php endif; ?>
            <?php if ($linkable) : ?>
              <a href="<?= esc_url($cta['url']); ?>" class="carte-enigme-lien" aria-label="<?= esc_attr($aria_label); ?>">
            <?php else : ?>
              <div class="carte-enigme-lien carte-enigme-lien--disabled">
            <?php endif; ?>
                <div class="carte-core">
                  <div class="carte-enigme-image <?= esc_attr($mapping_visuel['filtre'] ?? ''); ?>" title="<?= esc_attr($mapping_visuel['sens'] ?? ''); ?>">
                    <?php if ($mapping_visuel['image_reelle']) : ?>
                      <?php afficher_picture_vignette_enigme($enigme_id, 'Vignette de l’énigme', ['medium']); ?>
                    <?php else : ?>
                      <div class="enigme-placeholder">
                        <?php
                        $svg = $mapping_visuel['fallback_svg'] ?? 'warning.svg';
                        $svg_path = get_stylesheet_directory() . '/assets/svg/' . $svg;
                        if (file_exists($svg_path)) {
                            echo file_get_contents($svg_path);
                        } else {
                            echo '<div class="svg-manquant">🕳</div>';
                        }
                        ?>
                      </div>
                    <?php endif; ?>
                    <?php if ($linkable) : ?>
                      <span class="carte-enigme-overlay" aria-hidden="true">
                        <span class="carte-enigme-bouton <?= esc_attr($classes_bouton); ?>">
                          <?= esc_html($cta['label']); ?>
                        </span>
                      </span>
                    <?php endif; ?>
                  </div>

                <?php if ($mapping_visuel['image_reelle']) : ?>
                  <h3><span class="carte-enigme-titre"><?= esc_html($titre); ?></span></h3>
                <?php elseif (($mapping_visuel['etat_systeme'] ?? '') === 'bloquee_pre_requis') : ?>
                  <h3><span class="carte-enigme-titre"><?= esc_html__('Pré-requis', 'chassesautresor-com'); ?></span></h3>
                <?php elseif (($mapping_visuel['etat_systeme'] ?? '') === 'bloquee_date') : ?>
                  <h3><span class="carte-enigme-titre"><?= esc_html__('Parution programmée', 'chassesautresor-com'); ?></span></h3>
                <?php endif; ?>

              </div>
          <?php if ($linkable) : ?>
            </a>
          <?php else : ?>
            </div>
          <?php endif; ?>
            <?php if (in_array(($mapping_visuel['etat_systeme'] ?? ''), ['bloquee_pre_requis', 'bloquee_date'], true)) : ?>
              <footer class="carte-enigme-footer carte-enigme-footer--texte">
                <span class="footer-texte">
                  <?php if (($mapping_visuel['etat_systeme'] ?? '') === 'bloquee_pre_requis') :
                    $pre_requis = get_field('enigme_acces_pre_requis', $enigme_id) ?: [];
                    $liens = [];
                    foreach ($pre_requis as $pr) {
                      $pr_id = is_object($pr) ? $pr->ID : (int) $pr;
                      if ($pr_id) {
                        $liens[] = '<a href="' . esc_url(get_permalink($pr_id)) . '">' . esc_html(get_the_title($pr_id)) . '</a>';
                      }
                    }
                    echo wp_kses_post(
                      sprintf(
                        __('Nécessite : %s', 'chassesautresor-com'),
                        implode(', ', $liens)
                      )
                    );
                  elseif (($mapping_visuel['etat_systeme'] ?? '') === 'bloquee_date') :
                    $date_raw     = get_field('enigme_acces_date', $enigme_id);
                    $datetime_txt = formater_date_heure($date_raw);
                    if ($datetime_txt !== __('Non spécifiée', 'chassesautresor-com')) {
                      $format = __('Parution le %s', 'chassesautresor-com');
                      echo esc_html(sprintf($format, $datetime_txt));
                    }
                  endif; ?>
                </span>
              </footer>
            <?php else : ?>
              <footer class="carte-enigme-footer">
                <div class="footer-icons footer-icons-left">
                  <?php if ($mode_validation !== 'aucune' && $cout_points > 0) : ?>
                    <span class="footer-item footer-item--points" title="<?= esc_attr(sprintf(__('Cette énigme coûte %d point(s)', 'chassesautresor-com'), $cout_points)); ?>" aria-label="<?= esc_attr(sprintf(__('Cette énigme coûte %d point(s)', 'chassesautresor-com'), $cout_points)); ?>">
                      <i class="fa-solid fa-coins" aria-hidden="true"></i>
                      <?= esc_html($cout_points); ?>
                    </span>
                  <?php endif; ?>
                  <?php if (in_array($mode_validation, ['automatique', 'manuelle'], true)) :
                    $icon_html = $mode_validation === 'automatique'
                      ? trim(get_svg_icon('automatic'))
                      : '<i class="fa-solid fa-envelope" aria-hidden="true"></i>';
                    $label = $mode_validation === 'automatique'
                      ? esc_html__('Mode de validation : automatique', 'chassesautresor-com')
                      : esc_html__('Mode de validation : manuel', 'chassesautresor-com');
                  ?>
                    <span class="footer-item" title="<?= esc_attr($label); ?>" aria-label="<?= esc_attr($label); ?>">
                      <?= $icon_html; ?>
                    </span>
                  <?php endif; ?>
                </div>
                <div class="footer-icons footer-icons-right">
                  <span class="footer-item footer-item--participants" title="<?= esc_attr__('nombre de participants à cette énigme', 'chassesautresor-com'); ?>" aria-label="<?= esc_attr__('nombre de participants à cette énigme', 'chassesautresor-com'); ?>">
                    <i class="fa-solid fa-users" aria-hidden="true"></i>
                    <?= esc_html($nb_participants); ?>
                  </span>
                  <?php if ($mode_validation !== 'aucune') : ?>
                    <span class="footer-item footer-item--resolutions" title="<?= esc_attr__('nombre de joueurs ayant trouvé la bonne réponse', 'chassesautresor-com'); ?>" aria-label="<?= esc_attr__('nombre de joueurs ayant trouvé la bonne réponse', 'chassesautresor-com'); ?>">
                      <?= get_svg_icon('idea'); ?>
                      <?= esc_html($nb_resolutions); ?>
                    </span>
                  <?php endif; ?>
                  <?php if ($mode_validation === 'automatique' && $tentatives_max > 0) : ?>
                    <span class="footer-item" title="<?= esc_attr__('tentatives quotidiennes utilisées', 'chassesautresor-com'); ?>" aria-label="<?= esc_attr__('tentatives quotidiennes utilisées', 'chassesautresor-com'); ?>">
                      <i class="fa-solid fa-repeat" aria-hidden="true"></i>
                      <?= esc_html($tentatives_utilisees . '/' . $tentatives_max); ?>
                    </span>
                  <?php endif; ?>
                </div>
              </footer>
            <?php endif; ?>
            <?php
            $can_edit = function_exists('utilisateur_peut_modifier_enigme') && utilisateur_peut_modifier_enigme($enigme_id);
            $tab = 'param';
            if (!in_array($infos_chasse['statut_validation'] ?? '', ['creation', 'correction'], true)) {
              $statut_metier = $infos_chasse['statut'] ?? '';
              if (in_array($statut_metier, ['en_cours', 'a_venir', 'payante'], true)) {
                if ($mode_validation === 'manuelle' && compter_tentatives_en_attente($enigme_id) > 0) {
                  $tab = 'soumission';
                } else {
                  $tab = 'stats';
                }
              }
            }
            $settings_url = add_query_arg([
              'edition' => 'open',
              'tab'     => $tab,
            ], get_permalink($enigme_id));
            ?>
            <?php if ($classe_completion === 'carte-incomplete' || $can_edit || $afficher_validation) : ?>
              <div class="carte-icons">
                <?php if ($afficher_validation) : ?>
                  <span class="validation-icon" aria-label="<?= esc_attr__('Énigme résolue', 'chassesautresor-com'); ?>" title="<?= esc_attr__('Énigme résolue', 'chassesautresor-com'); ?>">
                    <i class="fa-solid fa-check" aria-hidden="true"></i>
                  </span>
                <?php endif; ?>
                <?php if ($classe_completion === 'carte-incomplete') : ?>
                  <span class="warning-icon" aria-label="<?= esc_attr__('Énigme incomplète', 'chassesautresor-com'); ?>" title="<?= esc_attr__('Énigme incomplète', 'chassesautresor-com'); ?>">
                    <i class="fa-solid fa-exclamation" aria-hidden="true"></i>
                  </span>
                <?php endif; ?>
                <?php if ($can_edit) : ?>
                  <a class="settings-icon" href="<?= esc_url($settings_url); ?>" aria-label="<?= esc_attr__('Paramètres', 'chassesautresor-com'); ?>">
                    <i class="fa-solid fa-gear" aria-hidden="true"></i>
                  </a>
                <?php endif; ?>
              </div>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>

    <?php
    // ➕ CTA pour ajouter une énigme si besoin
    if (utilisateur_peut_ajouter_enigme($chasse_id, $utilisateur_id) && !$has_incomplete) {
      verifier_ou_mettre_a_jour_cache_complet($chasse_id);
      $complete = (bool) get_field('chasse_cache_complet', $chasse_id);

      $highlight_pulse = false;
      if (!$has_enigmes) {
        $wp_status         = get_post_status($chasse_id);
        $statut_metier     = $infos_chasse['statut'];
        $statut_validation = $infos_chasse['statut_validation'];

        if (
          $wp_status === 'pending' &&
          $statut_metier === 'revision' &&
          in_array($statut_validation, ['creation', 'correction'], true)
        ) {
          $highlight_pulse = true;
        }
      }

      get_template_part('template-parts/enigme/chasse-partial-ajout-enigme', null, [
        'has_enigmes'     => $has_enigmes,
        'chasse_id'       => $chasse_id,
        'highlight_pulse' => $highlight_pulse,
        'use_button'      => false,
        'show_help_icon'  => $show_help_icon,
      ]);
    }
    ?>
  </div>
</div>