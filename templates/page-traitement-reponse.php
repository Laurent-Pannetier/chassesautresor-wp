<?php
/**
 * Template Name: Traitement Réponse (Finalisation Sécurisée)
 */

$enigme_functions_path = get_stylesheet_directory() . '/inc/enigme-functions.php';
if (!file_exists($enigme_functions_path)) {
    wp_die("Le fichier de fonctions d'énigme est manquant : {$enigme_functions_path}");
}
require_once $enigme_functions_path;

$uid = sanitize_text_field($_GET['uid'] ?? '');
$resultat_param = sanitize_text_field($_GET['resultat'] ?? '');

if (!$uid || !in_array($resultat_param, ['bon', 'faux'], true)) {
    wp_die('Paramètres manquants ou invalides.');
}
error_log("Traitement réponse : UID reçu = {$uid}, résultat param = {$resultat_param}");

// Vérification si la tentative a déjà été traitée ou non
$tentative_existante = get_tentative_by_uid($uid); // suppose que cette fonction existe dans enigme-functions.php

if ($tentative_existante) {
    error_log("Tentative déjà existante détectée pour UID {$uid} : résultat = {$tentative_existante->resultat}");

    if ($tentative_existante->resultat && $tentative_existante->resultat !== 'attente') {
        error_log("La tentative UID {$uid} a déjà été traitée. Aucun nouveau traitement ne sera effectué.");
    } else {
        error_log("La tentative UID {$uid} existe mais n'est pas encore traitée. Traitement en cours.");
    }

} else {
    error_log("Aucune tentative existante trouvée pour UID {$uid}. Nouvelle tentative, traitement en cours.");
}
// 🧩 Traitement de la tentative (renvoie ses données et l’état du traitement)
$traitement = traiter_tentative_manuelle($uid, $resultat_param);

if (!empty($traitement['erreur'])) {
    wp_die($traitement['erreur']);
}

if (!empty($traitement['reset_message'])) {
    echo $traitement['reset_message'];
    return;
}

// ✅ Variables à passer au template
$tentative = $traitement['tentative'];
$statut_final = $traitement['statut_final'] ?? null;
$statut_initial = $traitement['statut_initial'] ?? null;
$permalink = $traitement['permalink'] ?? '';
$statistiques = $traitement['statistiques'] ?? [];
$nom_user = $traitement['nom_user'] ?? 'Utilisateur inconnu';
$traitement_bloque = $traitement['traitement_bloque'] ?? false;
$traitement_effectue = $traitement['traitement_effectue'] ?? false;


// ⚠️ Correction cruciale ici : on utilise le résultat réel, pas celui de l’URL
$resultat = $tentative->resultat ?? '';

get_template_part('template-parts/traitement/tentative-feedback', null, [
    'statut_initial'    => $statut_initial,
    'statut_final'      => $statut_final,
    'resultat'          => $resultat,
    'traitement_bloque' => $traitement_bloque,
    'permalink'         => $permalink,
    'statistiques'      => $statistiques,
    'nom_user'          => $nom_user,
    'traitement_effectue' => $traitement_effectue,
]);
