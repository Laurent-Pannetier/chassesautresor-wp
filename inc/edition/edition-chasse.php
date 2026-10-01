<?php
defined('ABSPATH') || exit;

// ==================================================
// 🗺️ CRÉATION & ÉDITION D’UNE CHASSE
// ==================================================
// 🔹 enqueue_script_chasse_edit() → Charge JS sur single chasse
// 🔹 register_endpoint_creer_chasse() → Enregistre /creer-chasse
// 🔹 creer_chasse_et_rediriger_si_appel() → Crée une chasse et redirige
// 🔹 modifier_champ_chasse() → Mise à jour AJAX (champ ACF ou natif)
// 🔹 assigner_organisateur_a_chasse() → Associe l’organisateur à la chasse en `save_post`


/**
 * Charge les scripts JS frontaux pour l’édition d’une chasse (panneau édition).
 *
 * @hook wp_enqueue_scripts
 */
function enqueue_script_chasse_edit()
{
    if (!is_singular('chasse')) {
        return;
    }

    $chasse_id = get_the_ID();

    if (!utilisateur_peut_modifier_post($chasse_id)) {
        return;
    }

    // Enfile les scripts nécessaires
    enqueue_core_edit_scripts([
        'edition-animation-options',
        'chasse-edit',
        'chasse-stats',
        'table-etiquette',
        'tentatives-toggle',
        'solutions-pager',
        'solutions-create',
        'indices-pager',
        'indices-create',
    ]);
    wp_localize_script(
        'chasse-stats',
        'ChasseStats',
        [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'chasseId' => $chasse_id,
            'nonce' => wp_create_nonce('statistics_management'),
        ]
    );
    wp_localize_script(
        'chasse-edit',
        'ChasseIndices',
        [
            'ajaxUrl'   => admin_url('admin-ajax.php'),
            'chasseId'  => $chasse_id,
            'nonce'     => wp_create_nonce('hunt_management'),
            'hintNonce' => wp_create_nonce('hint_management'),
            'errorText' => __('Erreur lors du chargement des indices.', 'chassesautresor-com'),
        ]
    );

    wp_localize_script(
        'chasse-edit',
        'ChasseSolutions',
        [
            'scrollTarget'  => '#chasse-section-solutions',
            'tooltipChasse' => __('Il existe déjà une solution pour cette chasse', 'chassesautresor-com'),
            'tooltipEnigme' => __('Toutes les énigmes de la chasse ont déjà une solution', 'chassesautresor-com'),
        ]
    );

    wp_localize_script(
        'chasse-edit',
        'ChasseNbGagnantsI18n',
        [
            'unlimited'    => __('illimitée', 'chassesautresor-com'),
            'winnersLabel' => __('Gagnants', 'chassesautresor-com'),
            'limitLabel'   => __('Limite', 'chassesautresor-com'),
            'single'       => _n('%d gagnant', '%d gagnants', 1, 'chassesautresor-com'),
            'plural'       => _n('%d gagnant', '%d gagnants', 2, 'chassesautresor-com'),
        ]
    );

    wp_localize_script(
        'chasse-edit',
        'ChasseModeFinI18n',
        [
            'auto'   => __('automatique', 'chassesautresor-com'),
            'manual' => __('manuelle', 'chassesautresor-com'),
        ]
    );

    // Injecte les valeurs par défaut pour JS
    wp_localize_script('champ-init', 'CHP_CHASSE_DEFAUT', [
        'titre' => strtolower(TITRE_DEFAUT_CHASSE),
        'image_slug' => 'defaut-chasse-2',
        'nonce' => wp_create_nonce('hunt_field_management'),
    ]);

    // Charge les médias pour les champs image
    wp_enqueue_media();
}
add_action('wp_enqueue_scripts', 'enqueue_script_chasse_edit');

function autoriser_modification_dates_chasse(bool $allowed, int $huntId): bool {
    return utilisateur_peut_modifier_post($huntId) && utilisateur_peut_editer_champs($huntId);
}
add_filter('chassesautresor_can_edit_hunt_dates', 'autoriser_modification_dates_chasse', 10, 2);

function actualiser_statuts_apres_modification_dates(int $huntId): void {
    mettre_a_jour_statuts_chasse($huntId);
}
add_action('chassesautresor_hunt_dates_updated', 'actualiser_statuts_apres_modification_dates');


function autoriser_modification_chasse(bool $allowed, int $huntId): bool {
    return utilisateur_peut_modifier_post($huntId);
}
add_filter('chassesautresor_can_modify_hunt', 'autoriser_modification_chasse', 10, 2);

function autoriser_modification_champs_chasse(bool $allowed, int $huntId): bool {
    return utilisateur_peut_editer_champs($huntId);
}
add_filter('chassesautresor_can_edit_hunt_fields', 'autoriser_modification_champs_chasse', 10, 2);

/**
 * Adapte les dépendances historiques de clôture au service métier du cœur.
 *
 * @param mixed $result
 * @param mixed $value
 * @return array{handled:bool,error:?string}
 */
function appliquer_cloture_chasse($result, int $huntId, string $field, $value): array {
    return (new ChassesAuTresor\Core\Content\HuntClosureService())->apply(
        $huntId,
        $field,
        $value,
        'update_field',
        'recuperer_enigmes_associees',
        [ChassesAuTresor\Core\Content\RiddleSolutionFileScheduler::class, 'schedule'],
        'solution_recuperer_par_objet',
        'solution_planifier_publication',
        'gerer_chasse_terminee'
    );
}
add_filter('chassesautresor_apply_hunt_closure', 'appliquer_cloture_chasse', 10, 4);

function actualiser_statuts_apres_modification_champs(int $huntId): void {
    mettre_a_jour_statuts_chasse($huntId);
}
add_action('chassesautresor_hunt_fields_updated', 'actualiser_statuts_apres_modification_champs');
