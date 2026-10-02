<?php
defined('ABSPATH') || exit;


// ==================================================
// 🧩 CRÉATION & ÉDITION D’UNE ÉNIGME
// ==================================================
// 🔹 enqueue_script_enigme_edit() → Charge JS sur single énigme
// 🔹 creer_enigme_pour_chasse() → Crée une énigme liée à une chasse
// 🔹 register_endpoint_creer_enigme() → Enregistre /creer-enigme
// 🔹 creer_enigme_et_rediriger_si_appel() → Crée une énigme et redirige
// 🔹 modifier_champ_enigme() (AJAX) → Mise à jour champs ACF ou natifs


/**
 * Charge les scripts JS nécessaires à l’édition frontale d’une énigme :
 * – Modules partagés (core)
 * – Header organisateur
 * – Panneau latéral d’édition de l’énigme
 *
 * Le script est chargé uniquement sur les pages single du CPT "enigme",
 * si l’utilisateur a les droits de modification sur ce post.
 *
 * @hook wp_enqueue_scripts
 * @return void
 */
function enqueue_script_enigme_edit()
{
  if (!is_singular('enigme')) return;

  $enigme_id = get_the_ID();
  if (!utilisateur_peut_modifier_post($enigme_id)) return;

  // 📦 Modules JS partagés + scripts spécifiques
  enqueue_core_edit_scripts([
    'edition-animation-options',
    'organisateur-edit',
    'enigme-edit',
    'enigme-stats',
    'table-etiquette',
    'tentatives-toggle',
    'solutions-pager',
    'solutions-create',
    'indices-pager',
    'indices-create',
    'riddle-steps-edit',
  ]);

  wp_localize_script(
    'enigme-stats',
    'EnigmeStats',
    [
      'ajaxUrl'   => admin_url('admin-ajax.php'),
      'enigmeId'  => $enigme_id,
      'nonce'     => wp_create_nonce('statistics_management'),
    ]
  );

  wp_localize_script(
    'enigme-edit',
    'ChasseSolutions',
    [
      'scrollTarget'  => '#enigme-section-solutions',
      'tooltipChasse' => __('Il existe déjà une solution pour cette chasse', 'chassesautresor-com'),
      'tooltipEnigme' => __('Il existe déjà une solution pour cette énigme', 'chassesautresor-com'),
      'toggleChasse'  => __('Voir toutes les solutions de la chasse', 'chassesautresor-com'),
      'toggleEnigme'  => __('Voir la solution de cette énigme', 'chassesautresor-com'),
    ]
  );

  // Localisation JS si besoin (ex : valeurs par défaut)
  wp_localize_script('champ-init', 'CHP_ENIGME_DEFAUT', [
    'titre' => strtolower(TITRE_DEFAUT_ENIGME),
    'image_slug' => 'defaut-enigme',
    'nonce' => wp_create_nonce('modifier_champ_enigme'),
    'deleteNonce' => wp_create_nonce('supprimer_enigme'),
  ]);

  wp_localize_script('riddle-steps-edit', 'RiddleStepsEdit', [
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'riddleId' => $enigme_id,
    'nonce' => wp_create_nonce('riddle_step_management'),
    'texts' => [
      'newTitle' => __('Nouvelle étape', 'chassesautresor-com'),
      'editTitle' => __('Modifier l’étape', 'chassesautresor-com'),
      'imageTitle' => __('Choisir l’image de l’étape', 'chassesautresor-com'),
      'edit' => __('Modifier', 'chassesautresor-com'),
      'delete' => __('Supprimer', 'chassesautresor-com'),
      'confirmDelete' => __('Supprimer définitivement cette étape ?', 'chassesautresor-com'),
      'error' => __('Une erreur est survenue. Rechargez la page et réessayez.', 'chassesautresor-com'),
    ],
  ]);

  wp_enqueue_media();
}
add_action('wp_enqueue_scripts', 'enqueue_script_enigme_edit');


/**
 * 🔹 creer_enigme_pour_chasse() → Crée une énigme liée à une chasse, avec champs ACF par défaut.
 *
 * @param int $chasse_id
 * @param int|null $user_id
 * @return int|WP_Error
 */
function creer_enigme_pour_chasse($chasse_id, $user_id = null)
{
    return ChassesAuTresor\Core\Content\RiddleCreationRouteHandler::create(
        (int) $chasse_id,
        $user_id === null ? null : (int) $user_id,
        static fn (int $huntId): ?int => get_organisateur_from_chasse($huntId)
    );
}
