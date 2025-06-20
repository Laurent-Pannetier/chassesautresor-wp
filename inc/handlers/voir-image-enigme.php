<?php
// 🔒 Sécurité minimale
if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
  http_response_code(400);
  exit('ID manquant ou invalide');
}

$image_id = (int) $_GET['id'];
$taille = $_GET['taille'] ?? 'full';

// 🔎 Récupération de l'URL de l'image à la bonne taille
$src = wp_get_attachment_image_src($image_id, $taille);
$url = $src[0] ?? null;

if (!$url) {
  http_response_code(404);
  exit('Taille introuvable');
}

// 📁 Conversion URL → chemin absolu du fichier
$upload_dir = wp_get_upload_dir();
$path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $url);

// ⛔ Fichier inexistant
if (!file_exists($path)) {
  http_response_code(404);
  exit('Fichier introuvable');
}

// ✅ Type MIME
$mime = get_post_mime_type($image_id);
if (!$mime) $mime = 'application/octet-stream';

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

