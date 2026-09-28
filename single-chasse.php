<?php

/**
 * Template: single-chasse.php (refactorisé)
 * Affichage de la fiche chasse avec header organisateur, statuts dynamiques,
 * contenus stylisés, et panneaux d’édition correctement encapsulés.
 */
defined('ABSPATH') || exit;

// 🧠 LOGIQUE MÉTIER
$chasse_id = get_the_ID();
if (!$chasse_id) {
    wp_die(__('Chasse introuvable.', 'chassesautresor-com'));
}

// Utilisateur courant et rôle organisateur
$user_id          = get_current_user_id();
$est_orga_associe = utilisateur_est_organisateur_associe_a_chasse($user_id, $chasse_id);

// Mise à jour des statuts réservée aux administrateurs ou organisateurs
if (current_user_can('manage_options') || $est_orga_associe) {
    verifier_ou_recalculer_statut_chasse($chasse_id);
    verifier_et_synchroniser_cache_enigmes_si_autorise($chasse_id);
    verifier_ou_mettre_a_jour_cache_complet($chasse_id);
    chasse_clear_infos_affichage_cache($chasse_id);
}

$points_utilisateur = get_user_points($user_id);
$est_engage_chasse  = utilisateur_est_engage_dans_chasse($user_id, $chasse_id);
$peut_voir_aside    = $est_engage_chasse
    || current_user_can('manage_options')
    || $est_orga_associe;

// Récupération centralisée des infos
$infos_chasse = preparer_infos_affichage_chasse($chasse_id, $user_id);
// Champs principaux
$champs = $infos_chasse['champs'];
$lot = $champs['lot'];
$titre_recompense = $champs['titre_recompense'];
$valeur_recompense = $champs['valeur_recompense'];
$cout_points = $champs['cout_points'];
$date_debut = $champs['date_debut'];
$date_fin = $champs['date_fin'];
$illimitee = $champs['illimitee'];
$nb_max = $champs['nb_max'];
$date_decouverte = $champs['date_decouverte'];
$current_stored_statut = $champs['current_stored_statut'];

$date_debut_formatee       = formater_date($date_debut);
$date_fin_formatee         = $illimitee
    ? __('Illimitée', 'chassesautresor-com')
    : ($date_fin ? formater_date($date_fin) : __('Non spécifiée', 'chassesautresor-com'));
$date_decouverte_formatee = $date_decouverte ? formater_date_heure($date_decouverte) : '';

$timestamp_debut = convertir_en_timestamp($date_debut);
$timestamp_fin = (!$illimitee && $date_fin) ? convertir_en_timestamp($date_fin) : false;
$timestamp_decouverte = convertir_en_timestamp($date_decouverte);

// Organisateur
$organisateur_id = get_organisateur_from_chasse($chasse_id);
$organisateur_nom = $organisateur_id ? get_the_title($organisateur_id) : get_the_author();



// Contenu
$description = $infos_chasse['description'];
$extrait = wp_trim_words($infos_chasse['texte_complet'], 30, '...');

$image_raw = $infos_chasse['image_raw'];
$image_id  = $infos_chasse['image_id'];
$image_url = $infos_chasse['image_url'];


$progression      = $infos_chasse['progression'];
$enigmes_resolues = $progression['resolues'];
$total_enigmes    = $progression['total'];
$nb_resolvables   = $progression['resolvables'];
$nb_engagees      = $progression['engagees'];

$has_solutions = (bool) get_field('chasse_cache_has_solutions', $chasse_id);
$has_indices   = (bool) get_field('chasse_cache_has_indices', $chasse_id);

$titre_chasse   = get_the_title($chasse_id);
$enigmes_intro  = '';

$mode_fin = get_field('chasse_mode_fin', $chasse_id) ?: 'automatique';
$statut = $infos_chasse['statut'];
$title_filled = trim(get_the_title($chasse_id)) !== '';
$image_filled = !empty($image_id);
$description_filled = !empty(trim($description));
$required_fields_filled = $title_filled && $image_filled && $description_filled;

$needs_validatable_message = $statut === 'revision'
    && $mode_fin === 'automatique'
    && !chasse_has_validatable_enigme($chasse_id)
    && $required_fields_filled;

$statut_validation = $infos_chasse['statut_validation'];
$nb_joueurs = $infos_chasse['nb_joueurs'];
$sidebar_data = sidebar_prepare_chasse_nav(
    $chasse_id,
    $user_id,
    0
);

$solved_label  = _n('énigme résolue', 'énigmes résolues', $enigmes_resolues, 'chassesautresor-com');
$engaged_label = _n('engagée', 'engagées', $nb_engagees, 'chassesautresor-com');

if ($statut === 'termine') {
    $enigmes_intro = ($has_solutions || $has_indices)
        ? esc_html__('La chasse est terminée. Vous pouvez revoir toutes les énigmes ainsi que leurs solutions et indices.', 'chassesautresor-com')
        : esc_html__('La chasse est terminée. Les énigmes restent consultables.', 'chassesautresor-com');
} elseif ($statut === 'a_venir') {
    $enigmes_intro = sprintf(
        esc_html__('Les énigmes seront affichées au début de la chasse, le %s.', 'chassesautresor-com'),
        esc_html($date_debut_formatee)
    );
} elseif (in_array($statut, ['en_cours', 'payante'], true) && $statut_validation === 'valide') {
    $est_engage = utilisateur_est_engage_dans_chasse($user_id, $chasse_id);
    if (!$est_engage) {
        if ($est_orga_associe) {
            if ($titre_recompense) {
                $enigmes_intro = sprintf(
                    esc_html__('Voici les énigmes de cette chasse. Résolvez-les pour tenter de remporter %s.', 'chassesautresor-com'),
                    esc_html($titre_recompense)
                );
            } else {
                $enigmes_intro = esc_html__('Voici les énigmes de cette chasse.', 'chassesautresor-com');
            }
        } else {
            $enigmes_intro = esc_html__('L\'accès aux énigmes est réservé aux participants de chasse. Inscrivez-vous !', 'chassesautresor-com');
        }
    } else {
        if ($nb_engagees === 0) {
            $enigmes_intro = esc_html__(
                'commencez par consulter des énigmes parmi celles ci-dessous. Bonne chasse !',
                'chassesautresor-com'
            );
        } else {
            $enigmes_intro = sprintf(
                esc_html__('Progression : %1$d/%2$d %3$s — %4$d/%5$d %6$s.', 'chassesautresor-com'),
                $enigmes_resolues,
                $nb_resolvables,
                esc_html($solved_label),
                $nb_engagees,
                $total_enigmes,
                esc_html($engaged_label)
            );
        }
    }
} elseif ($est_orga_associe && in_array($statut_validation, ['creation', 'correction'], true)) {
    $enigmes_intro = esc_html__(
        'Voici vos énigmes : ajoutez, modifiez ou supprimez celles dont vous n’avez plus besoin !',
        'chassesautresor-com'
    );
}

if (!is_user_logged_in()) {
    $redirect_url     = get_permalink($chasse_id);
    $registration_url = add_query_arg('redirect_to', rawurlencode($redirect_url), wp_registration_url());
    $login_url        = wp_login_url($redirect_url);
    $enigmes_intro    = sprintf(
        wp_kses(
            /* translators: 1: registration URL, 2: login URL */
            __('Énigmes accessibles uniquement pour les joueurs connectés. Nouveau ? <a href="%1$s">S\'enregistrer</a> Déjà inscrit ? <a href="%2$s">Se connecter</a>', 'chassesautresor-com'),
            ['a' => ['href' => []]]
        ),
        esc_url($registration_url),
        esc_url($login_url)
    );
}

get_header();
cat_debug("🧪 test organisateur_associe : " . ($est_orga_associe ? 'OUI' : 'NON'));

$can_validate     = peut_valider_chasse($chasse_id, $user_id);
echo '<div class="container container--xl-full chasse-layout">';
$sidebar_sections = ['navigation' => '', 'stats' => ''];
if ($peut_voir_aside) {
    $sidebar_sections = render_sidebar(
        'chasse',
        0,
        $chasse_id,
        $sidebar_data['menu_items'],
        $sidebar_data['peut_ajouter_enigme'],
        $sidebar_data['total_enigmes'],
        $sidebar_data['has_incomplete_enigme']
    );
}
?>

<?php
if ($peut_voir_aside) {
    echo '<header class="enigme-mobile-header">';
    echo '<div aria-hidden="true"></div>';
    echo '<div class="enigme-mobile-actions">';
    echo '<button type="button" class="enigme-mobile-panel-toggle" aria-controls="enigme-mobile-panel" aria-expanded="false" aria-label="'
        . esc_attr__('Menu de navigation', 'chassesautresor-com') . '">';
    echo '<span class="screen-reader-text">' . esc_html__('Menu de navigation', 'chassesautresor-com') . '</span>';
    echo '<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>';
    echo '</button>';
    echo '</div>';
    echo '</header>';

    echo '<div id="enigme-mobile-panel" class="enigme-mobile-panel" hidden>';
    echo '<div class="enigme-mobile-panel__overlay" tabindex="-1"></div>';
    echo '<div class="enigme-mobile-panel__sheet" role="dialog" aria-modal="true" aria-labelledby="enigme-mobile-panel-title">';
    echo '<h2 id="enigme-mobile-panel-title" class="screen-reader-text">' . esc_html__('Navigation de la chasse', 'chassesautresor-com') . '</h2>';
    echo '<div class="enigme-mobile-panel__content">' . ($sidebar_sections['navigation'] ?? '') . '</div>';
    echo '</div>';
    echo '</div>';
}
?>

<div id="primary" class="content-area page-chasse-wrapper">
  <main id="main" class="site-main">

    <?php
    // 🧭 Fil d'Ariane
    $breadcrumb_items = [
      [
        'label'      => esc_html__('Accueil', 'chassesautresor-com'),
        'label_html' => '<i class="fa-solid fa-house" aria-hidden="true"></i><span class="screen-reader-text">' . esc_html__('Accueil', 'chassesautresor-com') . '</span>',
        'url'        => home_url('/'),
      ],
    ];
    if ($organisateur_id) {
      $breadcrumb_items[] = [
        'label' => get_the_title($organisateur_id),
        'url'   => get_permalink($organisateur_id),
      ];
    }
    $breadcrumb_items[] = [
      'label'   => get_the_title($chasse_id),
      'current' => true,
    ];
    get_template_part('template-parts/common/breadcrumb', null, ['items' => $breadcrumb_items]);
    ?>

    <?php
    if (current_user_can('administrator') && $statut_validation === 'en_attente') {
      get_template_part('template-parts/chasse/chasse-validation-actions', null, [
        'chasse_id' => $chasse_id,
      ]);
    }
    ?>

    <?php if (!empty($_GET['erreur'])) : ?>
        <?php $error_message = sanitize_text_field(wp_unslash($_GET['erreur'])); ?>
        <?php if ($error_message === 'points_insuffisants') : ?>
            <div class="message-erreur" role="alert" aria-live="assertive">
                ❌ <?= esc_html__('Vous n’avez pas assez de points pour engager cette énigme.', 'chassesautresor-com'); ?>
                <a href="<?= esc_url(home_url('/boutique')); ?>"><?= esc_html__('Accéder à la boutique', 'chassesautresor-com'); ?></a>
            </div>
        <?php else : ?>
            <div class="message-erreur" role="alert" aria-live="assertive">
                <?= esc_html($error_message); ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- 📦 Fiche complète (images + méta + actions) -->
    <?php
    get_template_part('template-parts/chasse/chasse-affichage-complet', null, [
      'chasse_id'   => $chasse_id,
      'infos_chasse'=> $infos_chasse,
    ]);
    ?>

    <!-- 🧩 Liste des énigmes -->
    <section class="chasse-enigmes-wrapper" id="chasse-enigmes-wrapper">
        <div class="titre-enigmes-wrapper">
            <h2><?php printf(esc_html__('Énigmes de %s', 'chassesautresor-com'), esc_html($titre_chasse)); ?></h2>
            <?php if ($enigmes_intro !== '') : ?>
                <p class="enigmes-intro"><?= $enigmes_intro; ?></p>
            <?php endif; ?>
            <div class="separateur-3"></div>
        </div>
        <div id="liste-enigmes" class="chasse-enigmes-liste">
            <?php
            get_template_part('template-parts/enigme/chasse-partial-boucle-enigmes', null, [
                'chasse_id'        => $chasse_id,
                'est_orga_associe' => $est_orga_associe,
                'infos_chasse'     => $infos_chasse,
                'show_help_icon'   => $est_orga_associe && $needs_validatable_message,
            ]);
            ?>
        </div>

        <footer class="chasse-enigmes-footer">

        </footer>
    </section>

    <?php render_chasse_solutions($chasse_id, $user_id); ?>

  </main>
</div>
</div>

<?php
// 💬 Modale d’introduction (affichée une seule fois)
$modal_deja_vue = get_post_meta($chasse_id, 'chasse_modal_bienvenue_vue', true);

if (!$modal_deja_vue) :
  $post_bienvenue = get_post(9004);
  if ($post_bienvenue && $post_bienvenue->post_status === 'publish') :
    update_post_meta($chasse_id, 'chasse_modal_bienvenue_vue', '1');
    $contenu = apply_filters('the_content', $post_bienvenue->post_content);
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $contenu);
    libxml_clear_errors();

    foreach ($dom->getElementsByTagName('h1') as $node) {
        $node->setAttribute('class', trim('modal-title ' . $node->getAttribute('class')));
    }
    foreach ($dom->getElementsByTagName('h2') as $node) {
        $node->setAttribute('class', trim('modal-subtitle ' . $node->getAttribute('class')));
    }

    $body = $dom->getElementsByTagName('body')->item(0);
    $contenu = '';
    foreach ($body->childNodes as $child) {
        $contenu .= $dom->saveHTML($child);
    }
?>
    <div class="modal-bienvenue-wrapper" role="dialog" aria-modal="true" aria-labelledby="modal-titre">
      <div class="modal-bienvenue-inner">
        <button class="modal-close-top" aria-label="Fermer la fenêtre">&times;</button>
        <?= $contenu; ?>
      </div>
    </div>

    <script>
      window.addEventListener('DOMContentLoaded', () => {
        const wrapper = document.querySelector('.modal-bienvenue-wrapper');
        if (!wrapper) return;
        wrapper.classList.add('visible');

        const fermer = () => wrapper.remove();

        wrapper.querySelectorAll('.modal-close-top').forEach(btn => {
          btn.addEventListener('click', fermer);
        });

        wrapper.addEventListener('click', e => {
          if (e.target === wrapper) fermer();
        });

        document.addEventListener('keydown', e => {
          if (e.key === 'Escape') fermer();
        });
      });
    </script>
    <style>
      .modal-bienvenue-wrapper {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.75);
        display: flex;
        justify-content: center;
        align-items: center;
        /* S'assure de passer au-dessus du panneau d'édition (z-index 10000) */
        z-index: 11001;
      }

      .modal-bienvenue-inner {
        background: #fff;
        padding: 2rem;
        border-radius: 1rem;
        max-width: 600px;
        width: 90%;
        max-height: 90vh;
        overflow-y: auto;
        position: relative;
      }

      .modal-close-top {
        position: absolute;
        top: 1rem;
        right: 1rem;
        font-size: 1.5rem;
        background: none;
        border: none;
        cursor: pointer;
        color: #000;
      }
    </style>
  <?php endif; ?>
<?php endif; ?>


<?php get_footer(); ?>
