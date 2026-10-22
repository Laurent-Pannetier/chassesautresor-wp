<?php

/**
 * Partial : chasse-partial-sommaire-enigmes.php
 *
 * Sommaire d'album : une ligne par énigme (vignette, titre, pointillés, numéro).
 * Le joueur ne voit que les énigmes dont l'état d'accessibilité, déjà calculé
 * par get_cta_enigme() à partir des prérequis, vaut « accessible ».
 * Les rôles autorisés gardent l'ajout, l'édition, l'ordre et l'état incomplet.
 */

defined('ABSPATH') || exit;

$chasse_id = $args['chasse_id'] ?? null;
if (!$chasse_id || get_post_type($chasse_id) !== 'chasse') {
    return;
}

$infos_chasse = $args['infos_chasse'] ?? preparer_infos_affichage_chasse($chasse_id);
$utilisateur_id = (int) get_current_user_id();

if (!chasse_est_visible_pour_utilisateur($chasse_id, $utilisateur_id)) {
    return;
}

$est_orga_associe = $args['est_orga_associe']
    ?? utilisateur_est_organisateur_associe_a_chasse($utilisateur_id, $chasse_id);
$show_help_icon = $args['show_help_icon'] ?? false;
$statut_metier = $infos_chasse['statut'] ?? 'revision';
$est_privilegie = user_can($utilisateur_id, 'manage_options') || $est_orga_associe;

$autorise_boucle = (
    $est_privilegie
    || utilisateur_est_engage_dans_chasse($utilisateur_id, $chasse_id)
    || $statut_metier === 'termine'
);
if (!$autorise_boucle) {
    return;
}

$ids_bruts = $infos_chasse['enigmes_associees'] ?? [];
$ids_enigmes = is_array($ids_bruts) ? array_map('intval', $ids_bruts) : [];
$posts = [];
if ($ids_enigmes !== []) {
    $posts = get_posts([
        'post_type' => 'enigme',
        'post_status' => ['publish', 'pending', 'draft'],
        'posts_per_page' => count($ids_enigmes),
        'post__in' => $ids_enigmes,
        'orderby' => 'post__in',
    ]);
}

$posts_par_id = [];
foreach ($posts as $enigme_post) {
    $posts_par_id[(int) $enigme_post->ID] = $enigme_post;
}

$peut_reordonner = in_array(
    $infos_chasse['statut_validation'] ?? '',
    ['creation', 'correction'],
    true
) && $est_privilegie;

$completion_statuses = [];
$has_incomplete = false;
if (
    ($peut_reordonner || user_can($utilisateur_id, 'manage_options'))
    && function_exists('verifier_ou_mettre_a_jour_cache_complet')
) {
    foreach ($posts as $enigme_post) {
        verifier_ou_mettre_a_jour_cache_complet($enigme_post->ID);
        $complet = (bool) get_field('enigme_cache_complet', $enigme_post->ID);
        $completion_statuses[(int) $enigme_post->ID] = $complet;
        if (!$complet) {
            $has_incomplete = true;
        }
    }
}

$statut_chasse = function_exists('get_post_status') ? (string) get_post_status($chasse_id) : '';
$est_orga = function_exists('est_organisateur') && est_organisateur();
$lignes = [];
$numero = 0;
foreach ($ids_enigmes as $enigme_id) {
    $numero++;
    if (!isset($posts_par_id[$enigme_id]) || !function_exists('get_cta_enigme')) {
        continue;
    }

    $cta = get_cta_enigme($enigme_id, $utilisateur_id);
    $etat = (string) ($cta['etat_systeme'] ?? '');
    $visible = function_exists('cat_sommaire_enigme_est_visible')
        ? cat_sommaire_enigme_est_visible($etat, (bool) $est_privilegie)
        : ($est_privilegie || $etat === 'accessible');
    if (!$visible) {
        continue;
    }

    $url = '';
    if (($cta['action'] ?? '') === 'link' && !empty($cta['url'])) {
        $url = (string) $cta['url'];
    } elseif (function_exists('get_permalink')) {
        $url = (string) get_permalink($enigme_id);
    }
    if ($url === '') {
        continue;
    }

    $titre = function_exists('get_the_title') ? (string) get_the_title($enigme_id) : '';
    if ($titre === '') {
        $titre = __('Énigme', 'chassesautresor-com');
    }

    $image_id = 0;
    $images = get_field('enigme_visuel_image', $enigme_id, false);
    if (is_array($images) && isset($images[0])) {
        $image_id = (int) $images[0];
    }

    $statut_utilisateur = (string) ($cta['statut_utilisateur'] ?? '');
    $mode_validation = (string) get_field('enigme_mode_validation', $enigme_id);
    $cout_points = (int) get_field('enigme_tentative_cout_points', $enigme_id);
    $afficher_cout = $mode_validation !== 'aucune' && $cout_points > 0;
    $verrouillee = in_array($etat, ['bloquee_pre_requis', 'bloquee_date'], true);
    $precision = '';
    if ($est_privilegie && $etat === 'bloquee_pre_requis') {
        $pre_requis = get_field('enigme_acces_pre_requis', $enigme_id) ?: [];
        $liens = [];
        foreach ((array) $pre_requis as $pr) {
            $pr_id = is_object($pr) ? (int) $pr->ID : (int) $pr;
            if ($pr_id <= 0) {
                continue;
            }
            $liens[] = '<a href="' . esc_url((string) get_permalink($pr_id)) . '">'
                . esc_html((string) get_the_title($pr_id)) . '</a>';
        }
        if ($liens !== []) {
            $precision = sprintf(
                /* translators: %s: linked prerequisite enigma titles. */
                __('Nécessite : %s', 'chassesautresor-com'),
                implode(', ', $liens)
            );
        }
    } elseif ($est_privilegie && $etat === 'bloquee_date' && function_exists('formater_date_heure')) {
        $datetime_txt = formater_date_heure(get_field('enigme_acces_date', $enigme_id));
        $non_specifiee = __('Non spécifiée', 'chassesautresor-com');
        if ($datetime_txt !== '' && $datetime_txt !== $non_specifiee) {
            $precision = esc_html(sprintf(
                /* translators: %s: publication date and time. */
                __('Parution le %s', 'chassesautresor-com'),
                $datetime_txt
            ));
        }
    }

    $statut_enigme = function_exists('get_post_status') ? (string) get_post_status($enigme_id) : '';
    $voir_bordure = $est_orga
        && $est_orga_associe
        && $statut_chasse !== 'publish'
        && $statut_enigme !== 'publish';
    $incomplete = $voir_bordure
        && array_key_exists($enigme_id, $completion_statuses)
        && !$completion_statuses[$enigme_id];

    $reglages_url = '';
    $peut_modifier = function_exists('utilisateur_peut_modifier_enigme')
        && utilisateur_peut_modifier_enigme($enigme_id);
    if ($peut_modifier && function_exists('cta_shell_edition_url')) {
        $tab = 'param';
        $validation = $infos_chasse['statut_validation'] ?? '';
        if (!in_array($validation, ['creation', 'correction'], true)) {
            if (in_array($statut_metier, ['en_cours', 'a_venir', 'payante'], true)) {
                $en_attente = function_exists('compter_tentatives_en_attente')
                    && $mode_validation === 'manuelle'
                    && compter_tentatives_en_attente($enigme_id) > 0;
                $tab = $en_attente ? 'soumission' : 'stats';
            }
        }
        $section = function_exists('cta_shell_normalize_edition_section')
            ? cta_shell_normalize_edition_section('enigme', $tab)
            : 'parametres';
        $reglages_url = cta_shell_edition_url('enigme', $enigme_id, $section);
    }

    $lignes[] = [
        'id' => $enigme_id,
        'numero' => $numero,
        'titre' => $titre,
        'url' => $url,
        'image_id' => $image_id,
        'trouvee' => in_array($statut_utilisateur, ['resolue', 'terminee'], true),
        'attente' => $etat === 'accessible' && $statut_utilisateur === 'non_commencee',
        'verrouillee' => $verrouillee,
        'precision' => $precision,
        'cout' => $afficher_cout ? $cout_points : 0,
        'incomplete' => $incomplete,
        'reglages_url' => $reglages_url,
    ];
}

$reserve_cout = false;
foreach ($lignes as $ligne) {
    if ($ligne['cout'] > 0) {
        $reserve_cout = true;
        break;
    }
}

$peut_ajouter = function_exists('utilisateur_peut_ajouter_enigme')
    && utilisateur_peut_ajouter_enigme($chasse_id, $utilisateur_id);
$peut_editer_chasse = function_exists('utilisateur_peut_modifier_post')
    && utilisateur_peut_modifier_post($chasse_id);
$ajout_url = '';
$highlight_ajout = false;
if ($peut_ajouter && !$has_incomplete) {
    $args_ajout = [
        'chasse_id' => $chasse_id,
        'nonce' => function_exists('wp_create_nonce') ? wp_create_nonce('creer_enigme') : '',
    ];
    $base_ajout = function_exists('home_url') ? home_url('/creer-enigme/') : '/creer-enigme/';
    $ajout_url = function_exists('add_query_arg')
        ? (string) add_query_arg($args_ajout, $base_ajout)
        : $base_ajout;
    if ($ids_enigmes === []) {
        $wp_status = function_exists('get_post_status') ? (string) get_post_status($chasse_id) : '';
        $statut_validation = $infos_chasse['statut_validation'] ?? '';
        $highlight_ajout = $wp_status === 'pending'
            && $statut_metier === 'revision'
            && in_array($statut_validation, ['creation', 'correction'], true);
    }
}

$edition_chasse_url = '';
if ($peut_editer_chasse && function_exists('cta_shell_edition_url')) {
    $edition_chasse_url = cta_shell_edition_url('chasse', (int) $chasse_id, 'parametres');
}
?>
<div class="sommaire-bd">
    <?php if ($peut_ajouter || $edition_chasse_url !== '') : ?>
        <div class="sommaire-bd__outils">
            <?php if ($peut_ajouter) : ?>
                <?php if ($has_incomplete) : ?>
                    <button type="button" class="sommaire-bd__outil" disabled>
                        <i class="fa-solid fa-plus" aria-hidden="true"></i>
                        <span><?php esc_html_e('Ajouter une énigme', 'chassesautresor-com'); ?></span>
                    </button>
                    <p class="sommaire-bd__outil-note">
                        <?php esc_html_e(
                            'Terminez l’énigme incomplète avant d’en ajouter une autre.',
                            'chassesautresor-com'
                        ); ?>
                    </p>
                <?php else : ?>
                    <a
                        class="sommaire-bd__outil<?php echo $highlight_ajout ? ' sommaire-bd__outil--accent' : ''; ?>"
                        href="<?php echo esc_url($ajout_url); ?>"
                    >
                        <i class="fa-solid fa-plus" aria-hidden="true"></i>
                        <span><?php esc_html_e('Ajouter une énigme', 'chassesautresor-com'); ?></span>
                    </a>
                    <?php if ($show_help_icon) : ?>
                        <span
                            class="sommaire-bd__aide"
                            title="<?php esc_attr_e('Validation en ligne nécessaire', 'chassesautresor-com'); ?>"
                        >
                            <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                            <span class="screen-reader-text">
                                <?php esc_html_e('Validation en ligne nécessaire', 'chassesautresor-com'); ?>
                            </span>
                        </span>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
            <?php if ($edition_chasse_url !== '') : ?>
                <a class="sommaire-bd__outil" href="<?php echo esc_url($edition_chasse_url); ?>">
                    <i class="fa-solid fa-gear" aria-hidden="true"></i>
                    <span><?php esc_html_e('Éditer la chasse', 'chassesautresor-com'); ?></span>
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <h3 class="sommaire-bd__kicker"><?php esc_html_e('Sommaire', 'chassesautresor-com'); ?></h3>
    <?php if ($lignes === []) : ?>
        <p class="sommaire-bd__vide">
            <?php
            if ($ids_enigmes === []) {
                esc_html_e('Le sommaire est encore vide.', 'chassesautresor-com');
            } else {
                esc_html_e(
                    'Aucune énigme n’est accessible pour le moment.',
                    'chassesautresor-com'
                );
            }
            ?>
        </p>
    <?php else : ?>
        <ul
            class="sommaire-bd__liste"
            <?php if ($peut_reordonner) : ?>
                data-reorder="1"
                data-chasse-id="<?php echo esc_attr((string) $chasse_id); ?>"
            <?php endif; ?>
        >
            <?php foreach ($lignes as $ligne) : ?>
                <?php
                $classes_item = 'sommaire-bd__item';
                if ($ligne['verrouillee']) {
                    $classes_item .= ' sommaire-bd__item--verrouillee';
                }
                $classes_vignette = 'sommaire-bd__vignette';
                if ($ligne['attente']) {
                    $classes_vignette .= ' sommaire-bd__vignette--attente';
                }
                ?>
                <li
                    class="<?php echo esc_attr($classes_item); ?>"
                    data-enigme-id="<?php echo esc_attr((string) $ligne['id']); ?>"
                >
                    <?php if ($peut_reordonner) : ?>
                        <button
                            type="button"
                            class="sommaire-bd__poignee"
                            draggable="true"
                            aria-label="<?php esc_attr_e('Réordonner l’énigme', 'chassesautresor-com'); ?>"
                        >
                            <i class="fa-solid fa-grip-lines" aria-hidden="true"></i>
                        </button>
                    <?php endif; ?>
                    <div class="sommaire-bd__corps">
                        <a class="sommaire-bd__lien" href="<?php echo esc_url($ligne['url']); ?>">
                            <span class="<?php echo esc_attr($classes_vignette); ?>">
                                <?php if (
                                    $ligne['image_id'] > 0
                                    && function_exists('enigme_sommaire_vignette_html')
                                ) : ?>
                                    <?php echo enigme_sommaire_vignette_html((int) $ligne['image_id'], ''); ?>
                                <?php elseif ($ligne['image_id'] > 0 && function_exists('build_picture_enigme')) : ?>
                                    <?php
                                    echo build_picture_enigme(
                                        $ligne['image_id'],
                                        '',
                                        ['thumbnail'],
                                        ['sizes' => '4.25rem', 'loading' => 'eager']
                                    );
                                    ?>
                                <?php else : ?>
                                    <span class="sommaire-bd__case" aria-hidden="true"></span>
                                <?php endif; ?>
                            </span>
                            <span class="sommaire-bd__titre"><?php echo esc_html($ligne['titre']); ?></span>
                            <span class="sommaire-bd__pointilles" aria-hidden="true"></span>
                            <?php if ($reserve_cout) : ?>
                                <span class="sommaire-bd__cout">
                                    <?php if ($ligne['cout'] > 0) : ?>
                                        <?php
                                        $cout_label = sprintf(
                                            /* translators: %d: point cost of the enigma. */
                                            __('Cette énigme coûte %d point(s)', 'chassesautresor-com'),
                                            $ligne['cout']
                                        );
                                        ?>
                                        <i class="fa-solid fa-coins" aria-hidden="true"></i>
                                        <span aria-label="<?php echo esc_attr($cout_label); ?>">
                                            <?php echo esc_html((string) $ligne['cout']); ?>
                                        </span>
                                    <?php endif; ?>
                                </span>
                            <?php endif; ?>
                            <span class="sommaire-bd__numero"><?php echo esc_html((string) $ligne['numero']); ?></span>
                            <span
                                class="sommaire-bd__trouvee"
                                <?php echo $ligne['trouvee'] ? '' : 'aria-hidden="true"'; ?>
                            >
                                <?php if ($ligne['trouvee']) : ?>
                                    <i class="fa-solid fa-check" aria-hidden="true"></i>
                                    <span class="screen-reader-text">
                                        <?php esc_html_e('Énigme trouvée.', 'chassesautresor-com'); ?>
                                    </span>
                                <?php endif; ?>
                            </span>
                            <?php if ($ligne['verrouillee']) : ?>
                                <span class="screen-reader-text">
                                    <?php esc_html_e('Énigme verrouillée.', 'chassesautresor-com'); ?>
                                </span>
                            <?php endif; ?>
                        </a>
                        <?php if ($ligne['precision'] !== '') : ?>
                            <p class="sommaire-bd__precision">
                                <?php
                                if (function_exists('wp_kses_post')) {
                                    echo wp_kses_post($ligne['precision']);
                                } else {
                                    echo $ligne['precision'];
                                }
                                ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    <?php if ($ligne['incomplete']) : ?>
                        <span
                            class="sommaire-bd__alerte"
                            title="<?php esc_attr_e('Énigme incomplète', 'chassesautresor-com'); ?>"
                        >
                            <i class="fa-solid fa-exclamation" aria-hidden="true"></i>
                            <span class="screen-reader-text">
                                <?php esc_html_e('Énigme incomplète', 'chassesautresor-com'); ?>
                            </span>
                        </span>
                    <?php endif; ?>
                    <?php if ($ligne['reglages_url'] !== '') : ?>
                        <a
                            class="sommaire-bd__reglages"
                            href="<?php echo esc_url($ligne['reglages_url']); ?>"
                            aria-label="<?php esc_attr_e('Paramètres', 'chassesautresor-com'); ?>"
                        >
                            <i class="fa-solid fa-gear" aria-hidden="true"></i>
                        </a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
