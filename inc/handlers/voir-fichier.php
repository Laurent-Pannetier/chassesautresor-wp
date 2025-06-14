<?php

$log_prefix = '[voir-fichier.php]';

// Activer les logs si besoin
if (!defined('WP_DEBUG')) define('WP_DEBUG', true);
if (!defined('WP_DEBUG_LOG')) define('WP_DEBUG_LOG', true);
if (!defined('WP_DEBUG_DISPLAY')) define('WP_DEBUG_DISPLAY', false);

function logf($message) {
    error_log("[voir-fichier.php] $message");
}

// Vérifier l'utilisateur connecté
$user_id = get_current_user_id();
if (!$user_id) {
    logf("Utilisateur non connecté → 403");
    status_header(403);
    exit('Accès refusé (non connecté).');
}

$enigme_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
logf("Requête reçue pour énigme ID : $enigme_id");
logf("✅ [$user_id] a consulté la solution de l’énigme #$enigme_id");

if (!$enigme_id || get_post_type($enigme_id) !== 'enigme') {
    logf("ID invalide ou post non de type 'enigme' → 404");
    status_header(404);
    exit('Fichier introuvable (ID).');
}

// Vérifie les droits d'accès
if (!function_exists('utilisateur_peut_voir_solution_enigme')) {
    logf("Fonction d'autorisation manquante → 403");
    status_header(403);
    exit('Fonction d’autorisation non disponible.');
}

if (!utilisateur_peut_voir_solution_enigme($enigme_id, $user_id)) {
    logf("Utilisateur $user_id non autorisé à voir l’énigme $enigme_id → 403");
    status_header(403);
    exit('Accès non autorisé à cette solution.');
}

logf("Utilisateur $user_id autorisé.");

// Récupérer l'ID du fichier depuis le champ ACF
$fichier_id = get_field('enigme_solution_fichier', $enigme_id, false);
if (!$fichier_id) {
    logf("Aucun fichier trouvé dans enigme_solution_fichier → 404");
    status_header(404);
    exit('Aucun fichier PDF lié à cette énigme.');
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
