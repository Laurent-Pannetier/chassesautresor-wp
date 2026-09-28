<?php

$log_prefix = '[voir-fichier.php]';

function logf($message) {
    cat_debug("[voir-fichier.php] $message");
}

// Vérifier l'utilisateur connecté
$user_id = get_current_user_id();
if (!$user_id) {
    logf("Utilisateur non connecté → 403");
    status_header(403);
    exit('Accès refusé (non connecté).');
}

$post_id   = isset($_GET['id']) ? intval($_GET['id']) : 0;
$post_type = get_post_type($post_id);
logf("Requête reçue pour post ID : $post_id (type $post_type)");
logf("✅ [$user_id] a consulté la solution du post #$post_id");

if (!$post_id || !in_array($post_type, ['enigme', 'chasse'], true)) {
    logf("ID invalide ou post non supporté → 404");
    status_header(404);
    exit(__('Fichier introuvable (ID).', 'chassesautresor-com'));
}

// Récupérer la solution liée
$solution = solution_recuperer_par_objet($post_id, $post_type);
if (!$solution) {
    logf("Aucune solution liée trouvée → 404");
    status_header(404);
    exit(__('Solution introuvable.', 'chassesautresor-com'));
}

// Vérifie les droits d'accès
if (
    ($post_type === 'enigme' && !utilisateur_peut_voir_solution_enigme($post_id, $user_id)) ||
    ($post_type === 'chasse' && function_exists('utilisateur_peut_voir_solution_chasse')
        && !utilisateur_peut_voir_solution_chasse($post_id, $user_id))
) {
    logf("Utilisateur $user_id non autorisé à voir le post $post_id → 403");
    status_header(403);
    exit(__('Accès non autorisé à cette solution.', 'chassesautresor-com'));
}

logf("Utilisateur $user_id autorisé.");

// Récupérer l'ID du fichier depuis le post solution
$fichier_id = get_field('solution_fichier', $solution->ID, false);
if (!$fichier_id) {
    logf("Aucun fichier trouvé dans solution_fichier → 404");
    status_header(404);
    exit(__('Aucun fichier PDF lié à cette solution.', 'chassesautresor-com'));
}

// Obtenir le chemin physique
$chemin_fichier = get_attached_file($fichier_id);
logf("Chemin absolu détecté : $chemin_fichier");

if (!$chemin_fichier || !file_exists($chemin_fichier)) {
    logf("Le fichier n'existe pas → 404");
    status_header(404);
    exit('Fichier non trouvé sur le serveur.');
}

if (!is_readable($chemin_fichier)) {
    logf("Le fichier existe mais n’est pas lisible (permissions ?) → 403");
    status_header(403);
    exit('Fichier non lisible sur le serveur.');
}

// Tentative de lecture
$filename = basename($chemin_fichier);
$filesize = filesize($chemin_fichier);

logf("Fichier prêt à être servi : $filename ($filesize octets)");

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Content-Length: ' . $filesize);

readfile($chemin_fichier);
exit;
