<?php
// 🔒 Sécurité minimale
if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
  http_response_code(400);
  exit('ID manquant ou invalide');
}

$image_id = (int) $_GET['id'];
$taille = $_GET['taille'] ?? 'full';

if (!function_exists('trouver_chemin_image')) {
  require_once get_stylesheet_directory() . '/inc/enigme-functions.php';
}


// 🔎 Essai avec la taille demandée
$path = trouver_chemin_image($image_id, $taille);

// 🔁 Fallback automatique vers full si fichier manquant
if (!$path && $taille !== 'full') {
  $path = trouver_chemin_image($image_id, 'full');
}

if (!$path) {
  http_response_code(404);
  exit('Fichier introuvable');
}

// 📦 Type MIME basé sur l’extension réelle du fichier
$extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$mime_types = [
  'jpg'  => 'image/jpeg',
  'jpeg' => 'image/jpeg',
  'png'  => 'image/png',
  'gif'  => 'image/gif',
  'webp' => 'image/webp',
];
$mime = $mime_types[$extension] ?? 'application/octet-stream';

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
