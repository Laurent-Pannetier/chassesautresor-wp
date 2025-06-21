<?php
// 🔒 Sécurité minimale
if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
  http_response_code(400);
  exit('ID manquant ou invalide');
}

$image_id = (int) $_GET['id'];
$taille = $_GET['taille'] ?? 'full';

// 🔁 Chargement de la fonction centralisée
if (!function_exists('trouver_chemin_image')) {
  require_once get_stylesheet_directory() . '/inc/enigme-functions.php';
}

// 🔎 Essai avec la taille demandée
$info = trouver_chemin_image($image_id, $taille);
$path = $info['path'] ?? null;
$mime = $info['mime'] ?? 'application/octet-stream';

// 🔁 Fallback automatique vers full si fichier manquant
if (!$path && $taille !== 'full') {
  $info = trouver_chemin_image($image_id, 'full');
  $path = $info['path'] ?? null;
  $mime = $info['mime'] ?? 'application/octet-stream';
}

if (!$path) {
  http_response_code(404);
  exit('Fichier introuvable');
}

// 🧹 Nettoyage WordPress
ob_clean();
header_remove();
remove_all_actions('shutdown');
remove_all_actions('template_redirect');

// ✅ Envoi du fichier
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
