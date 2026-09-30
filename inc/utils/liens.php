<?php
defined( 'ABSPATH' ) || exit;

if (!class_exists(ChassesAuTresor\Core\Content\PublicLinkService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Content/PublicLinkService.php';
}

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
            'label' => __('Site Web', 'chassesautresor-com'),
            'icone' => 'fa-solid fa-globe',
        ],
        'discord' => [
            'label' => __('Discord', 'chassesautresor-com'),
            'icone' => 'fa-brands fa-discord',
        ],
        'facebook' => [
            'label' => __('Facebook', 'chassesautresor-com'),
            'icone' => 'fa-brands fa-facebook-f',
        ],
        'twitter' => [
            'label' => __('Twitter/X', 'chassesautresor-com'),
            'icone' => 'fa-brands fa-x-twitter',
        ],
        'instagram' => [
            'label' => __('Instagram', 'chassesautresor-com'),
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
 * @param array  $liens     Liste des liens ACF bruts (array d’objets)
 * @param string $contexte  Contexte de l’entité ("organisateur" ou "chasse")
 *                          utilisé pour repérer les clés personnalisées dans ACF
 *                          (ex. "chasse_principale_liens_type")
 * @param array  $options   Options d’affichage (placeholder, etc.)
 *
 * @return string HTML complet (liste UL ou placeholder)
 */
function render_liens_publics(array $liens, string $contexte = 'organisateur', array $options = []): string {
    $types = get_types_liens_publics();
    $liens_actifs = (new ChassesAuTresor\Core\Content\PublicLinkService())->activeLinks($liens, $contexte);

    if (! empty($liens_actifs)) {
        $show_labels = count($liens_actifs) <= 2;
        $classes = 'liste-liens-publics';
        if (! $show_labels) {
            $classes .= ' liens-sans-intitule';
        }

        $out = '<ul class="' . esc_attr($classes) . '">';
        foreach ($liens_actifs as $type => $url) {
            $label = $types[$type]['label'] ?? ucfirst($type);
            $icone = $types[$type]['icone'] ?? 'fa-solid fa-link';

            $out .= '<li class="item-lien-public">'
                . '<a href="' . esc_url($url) . '" class="lien-public lien-' . esc_attr($type) . '" target="_blank" rel="noopener">'
                . '<i class="fa ' . esc_attr($icone) . '"></i>';

            if ($show_labels) {
                $out .= '<span class="texte-lien">' . esc_html($label) . '</span>';
            }

            $out .= '</a>'
                . '</li>';
        }
        $out .= '</ul>';
        return $out;
    }

    if (($options['placeholder'] ?? true) === false) {
        return '';
    }

    // Placeholder si aucun lien
    $out = '<div class="liens-placeholder">';
    $out .= '<p class="liens-placeholder-message">' . esc_html__( 'Aucun lien ajouté pour le moment.', 'chassesautresor-com' ) . '</p>';
    foreach ($types as $type => $infos) {
        $out .= '<i class="fa ' . esc_attr($infos['icone']) . ' icone-grisee" title="' . esc_attr($infos['label']) . '"></i>';
    }
    $out .= '</div>';

    return $out;
}
