<?php
defined( 'ABSPATH' ) || exit;

/**
 * ============================================================
 * 🎛️  CONFIGURATION DES TYPES DE LIENS PUBLICS
 * ============================================================
 *
 * 📌 Ce fichier contient la définition centralisée des types de liens
 *     utilisés dans les CPT "organisateur" et "chasse".
 *
 * 🔗 Pour modifier la liste des liens disponibles :
 *     → Modifier uniquement la fonction `get_types_liens_publics()`
 *     → Chaque entrée suit ce format :
 *
 *         'slug_du_lien' => [
 *             'label' => 'Nom visible',
 *             'icone' => 'fa-icon FontAwesome',
 *         ]
 *
 * 🛠️ La liste est utilisée :
 *     - En PHP (affichage des liens dans `render_liens_publics()`)
 *     - En JavaScript (formulaires d'édition, via `wp_localize_script`)
 *
 * ⚠️ Important :
 *     - Ne pas dupliquer la liste ailleurs
 *     - Garder les `slug` simples (sans majuscule ni espace)
 *     - Les icônes doivent être valides (cf. FontAwesome v6)
 */


// ==================================================
// 🔗 LIENS EXTERNES — Affichage & Configuration
// ==================================================
/**
 * 🔹 get_types_liens_publics → Retourne la liste centralisée des types de liens publics (label + icône).
 * 🔹 render_liens_publics → Génère le HTML des liens pour l'affichage public (organisateur ou chasse).
 */


// --------------------------------------------------
// 🔹 Liste centralisée des types de liens publics
// --------------------------------------------------

/**
 * Retourne la liste centralisée des types de liens publics utilisables
 * pour les CPT "organisateur" et "chasse" (label + icône FontAwesome).
 *
 * @return array<string, array{label: string, icone: string}> Tableau des types de lien
 */
function get_types_liens_publics(): array {
    return [
        'site_web' => [
            'label' => 'Site Web',
            'icone' => 'fa-solid fa-globe',
        ],
        'discord' => [
            'label' => 'Discord',
            'icone' => 'fa-brands fa-discord',
        ],
        'facebook' => [
            'label' => 'Facebook',
            'icone' => 'fa-brands fa-facebook-f',
        ],
        'twitter' => [
            'label' => 'Twitter/X',
            'icone' => 'fa-brands fa-x-twitter',
        ],
        'instagram' => [
            'label' => 'Instagram',
            'icone' => 'fa-brands fa-instagram',
        ],
    ];
}


// --------------------------------------------------
// 🔹 Affichage HTML des liens publics
// --------------------------------------------------

/**
 * Génère le HTML d’affichage des liens publics pour un organisateur ou une chasse.
 *
 * @param array $liens         Liste des liens ACF bruts (array d’objets)
 * @param string $contexte     Contexte de l’entité ("organisateur" ou "chasse")
 *                             utilisé pour repérer les clés personnalisées dans ACF
 *                             (ex. "chasse_principale_liens_type")
 *
 * @return string HTML complet (liste UL ou placeholder)
 */
function render_liens_publics(array $liens, string $contexte = 'organisateur'): string {
    $types = get_types_liens_publics();
    $liens_actifs = [];

    foreach ($liens as $entree) {
        $type_raw = $entree[$contexte . '_principale_liens_type'] ?? null;
        $url = $entree[$contexte . '_principale_liens_url'] ?? null;
        $type = is_array($type_raw) ? ($type_raw[0] ?? '') : $type_raw;

        if (is_string($type) && trim($type) !== '' && is_string($url) && trim($url) !== '') {
            $liens_actifs[$type] = $url;
        }
    }

    if (!empty($liens_actifs)) {
        $out = '<ul class="liste-liens-publics">';
        foreach ($liens_actifs as $type => $url) {
            $label = $types[$type]['label'] ?? ucfirst($type);
            $icone = $types[$type]['icone'] ?? 'fa-solid fa-link';
            $out .= '<li class="item-lien-public">
                      <a href="' . esc_url($url) . '" class="lien-public lien-' . esc_attr($type) . '" target="_blank" rel="noopener">
                        <i class="fa ' . esc_attr($icone) . '"></i>
                        <span class="texte-lien">' . esc_html($label) . '</span>
                      </a>
                    </li>';
        }
        $out .= '</ul>';
        return $out;
    }

    // Placeholder si aucun lien
    $out = '<div class="liens-placeholder">';
    $out .= '<p class="liens-placeholder-message">Aucun lien ajouté pour le moment.</p>';
    foreach ($types as $type => $infos) {
        $out .= '<i class="fa ' . esc_attr($infos['icone']) . ' icone-grisee" title="' . esc_attr($infos['label']) . '"></i>';
    }
    $out .= '</div>';

    return $out;
}