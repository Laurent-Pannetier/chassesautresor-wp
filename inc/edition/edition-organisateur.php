<?php
defined('ABSPATH') || exit;

// ==================================================
// 👤 CRÉATION & ÉDITION D’UN ORGANISATEUR
// ==================================================
// 🔹 organisateur_get_liens_actifs() → Retourne les liens publics valides d’un organisateur
// 🔹 enqueue_script_organisateur_edit() → Charge JS si modif organisateur possible
// 🔹 modifier_champ_organisateur() (AJAX) → Enregistre champs organisateur
// 🔹 organisateur_get_liste_liens_publics() → Liste des types de lien publics
// 🔹 organisateur_get_lien_public_infos() → Détails pour un type de lien

/**
 * Retourne un tableau des liens publics actifs pour un organisateur donné.
 *
 * @param int $organisateur_id ID du post organisateur.
 * @return array Tableau associatif [type => url] uniquement pour les entrées valides.
 */
function organisateur_get_liens_actifs(int $organisateur_id): array
{
    $rows = get_field('liens_publics', $organisateur_id);
    $links = (new ChassesAuTresor\Core\Content\PublicLinkService())->activeLinks(
        is_array($rows) ? $rows : []
    );

    return array_map('esc_url', $links);
}


/**
 * Charge les scripts JS de l’éditeur organisateur sur la page Outils.
 *
 * @hook wp_enqueue_scripts
 */
function enqueue_script_organisateur_edit()
{
  $organisateur_id = function_exists('cta_shell_edition_post_id')
      ? (int) cta_shell_edition_post_id('organisateur')
      : 0;

  if ($organisateur_id && utilisateur_peut_modifier_post($organisateur_id)) {
    // 📦 Modules JS partagés + script organisateur
    enqueue_core_edit_scripts(['organisateur-edit', 'table-etiquette']);

    // ✅ Injection JavaScript APRÈS le enqueue (très important)
    $author_id = (int) get_post_field('post_author', $organisateur_id);
    $default_email = get_the_author_meta('user_email', $author_id);

    wp_localize_script('organisateur-edit', 'organisateurData', [
      'defaultEmail' => esc_js($default_email),
      'nonce' => wp_create_nonce('organizer_management'),
      'conversionNonce' => wp_create_nonce('conversion-history-nonce'),
    ]);

    wp_enqueue_media();
  }
}
add_action('wp_enqueue_scripts', 'enqueue_script_organisateur_edit');


/**
 * Retourne la liste complète des types de lien public supportés.
 *
 * Chaque type est représenté par un tableau contenant :
 * - 'label' : Nom lisible du lien (ex : "Site Web", "Discord", ...)
 * - 'icone' : Classe FontAwesome correspondant à l’icône à afficher
 *
 * @return array Liste des types de lien public.
 */
function organisateur_get_liste_liens_publics()
{
    return get_types_liens_publics();
}

/**
 * Retourne les informations associées à un type de lien public donné.
 *
 * Si le type n’est pas reconnu, un fallback est retourné avec :
 * - Label = ucfirst du type
 * - Icône = fa-solid fa-link
 *
 * @param string $type_de_lien Type de lien à interroger (ex : "discord", "site_web").
 * @return array ['label' => string, 'icone' => string]
 */
function organisateur_get_lien_public_infos($type_de_lien)
{
    $links = get_types_liens_publics();
    $type = strtolower(trim($type_de_lien));

    return $links[$type] ?? [
        'label' => ucfirst($type),
        'icone' => 'fa-solid fa-link',
    ];
}
