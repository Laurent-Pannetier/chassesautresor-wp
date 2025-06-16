<?php
/**
 * Template Name: Traitement Réponse (Finalisation Sécurisée)
 */

require_once get_stylesheet_directory() . '/inc/enigme-functions.php';

$uid = sanitize_text_field($_GET['uid'] ?? '');
$resultat_param = sanitize_text_field($_GET['resultat'] ?? '');

if (!$uid || !in_array($resultat_param, ['bon', 'faux'], true)) {
    wp_die('Paramètres manquants ou invalides.');
}

// Détection état initial
$etat = get_etat_tentative($uid);

if ($etat === 'attente') {
    $traitement = traiter_tentative_manuelle($uid, $resultat_param);
    $etat = get_etat_tentative($uid); // relire après traitement
} else {
    $tentative = get_tentative_by_uid($uid);
    if (!$tentative) wp_die("Tentative introuvable.");

    $traitement = [
        'etat_tentative' => $etat,
        'tentative' => $tentative,
        'resultat' => $tentative->resultat ?? '',
        'statut_initial' => null,
        'statut_final' => $tentative->resultat ?? '',
        'nom_user' => get_userdata($tentative->user_id)?->display_name ?? 'Utilisateur inconnu',
        'permalink' => get_permalink($tentative->enigme_id ?? 0) . '?statistiques=1',
        'statistiques' => [
            'total_user' => 0,
            'total_enigme' => 0,
            'total_chasse' => 0,
        ],
    ];
}

get_template_part('template-parts/traitement/tentative-feedback', null, [
    'etat_tentative'    => $etat,
    'resultat'          => $traitement['resultat'] ?? '',
    'statut_initial'    => $traitement['statut_initial'] ?? '',
    'statut_final'      => $traitement['statut_final'] ?? '',
    'nom_user'          => $traitement['nom_user'] ?? '',
    'permalink'         => $traitement['permalink'] ?? '',
    'statistiques'      => $traitement['statistiques'] ?? [],
]);
