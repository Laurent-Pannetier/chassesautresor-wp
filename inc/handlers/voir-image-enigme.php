<?php
// 🔒 Sécurité minimale
if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
  http_response_code(400);
  exit('ID manquant ou invalide');
}

$image_id = (int) $_GET['id'];
$taille = $_GET['taille'] ?? 'full';

// 🔎 Récupération de l’URL correspondant à la taille demandée
$src = wp_get_attachment_image_src($image_id, $taille);
$url = $src[0] ?? null;

if (!$url) {
  http_response_code(404);
  exit('Taille non disponible');
}

// 📁 Conversion de l’URL vers le chemin local
$upload_dir = wp_get_upload_dir();
$path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $url);

// ⛔ Fichier non trouvé sur le disque
if (!file_exists($path)) {
  http_response_code(404);
  exit('Fichier introuvable');
}

// 📦 Type MIME cohérent (selon extension du fichier réel)
$extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$mime_types = [
  'jpg'  => 'image/jpeg',
  'jpeg' => 'image/jpeg',
  'png'  => 'image/png',
  'gif'  => 'image/gif',
  'webp' => 'image/webp',
];
$mime = $mime_types[$extension] ?? 'application/octet-stream';

// 🧹 Nettoyage WP
ob_clean();
header_remove();
remove_all_actions('shutdown');
remove_all_actions('template_redirect');

// ✅ Envoi du fichier
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
