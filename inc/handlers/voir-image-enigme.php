<?php
// 🔒 Sécurité minimale
if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
  http_response_code(400);
  exit('ID manquant ou invalide');
}

$image_id = (int) $_GET['id'];
$taille = $_GET['taille'] ?? 'full';

// 🔎 Récupération des infos de l'image
$src = wp_get_attachment_image_src($image_id, $taille);
$fichier = get_attached_file($image_id);

// 🔍 Debug : log si besoin
// error_log("🧩 Proxy image $image_id ($taille) → fichier = $fichier");

// ⛔ Pas de chemin, fichier manquant ou erreur
if (!$fichier || !file_exists($fichier)) {
  http_response_code(404);
  exit('Image non trouvée');
}

// ✅ Type MIME (WordPress sait ce que c’est)
$mime = get_post_mime_type($image_id);
if (!$mime) $mime = 'application/octet-stream';

// 🧹 Nettoyage WordPress
ob_clean();
header_remove();
remove_all_actions('shutdown');
remove_all_actions('template_redirect');

// ✅ Envoi du fichier
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($fichier));
readfile($fichier);
exit;
