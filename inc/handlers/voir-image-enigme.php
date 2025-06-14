<?php
// 🚫 Sécurité : accès direct interdit
defined('ABSPATH') || exit;

error_log("💡 Appel voir-image-enigme.php ID=" . ($_GET['id'] ?? 'null'));


require_once dirname(__DIR__, 2) . '/functions.php'; // Assure le chargement des fonctions du thème

// 🔍 Récupération de l’ID d’image
$image_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$image_id || !is_user_logged_in()) {
  status_header(403);
  exit('Accès interdit.');
}

// 🧩 Vérifie que c’est bien une image et qu’elle est attachée à une énigme
$parent_id = wp_get_post_parent_id($image_id);
if (!$parent_id || get_post_type($parent_id) !== 'enigme') {
  status_header(403);
  exit('Fichier non lié à une énigme.');
}

// 🔐 Vérifie les droits d’accès à l’énigme
if (!utilisateur_peut_voir_enigme($parent_id)) {
  status_header(403);
  exit('Accès refusé.');
}


// 🖼️ Gestion de la taille demandée pour l’image
$taille = $_GET['taille'] ?? 'full'; // valeur par défaut : full

$fichier = get_attached_file($image_id);

if ($taille !== 'full' && $image_id) {
    $sizes = wp_get_attachment_image_src($image_id, $taille);
    if (is_array($sizes)) {
        $path_resized = str_replace(basename($fichier), basename($sizes[0]), $fichier);
        if (file_exists($path_resized)) {
            $fichier = $path_resized; // ✅ Remplace uniquement si la version existe
        }
    }
}

// 🔒 Vérifie que le fichier existe
if (!$fichier || !file_exists($fichier)) {
    status_header(404);
    exit('Fichier introuvable.');
}

// 🔍 Détection MIME type
$mime = mime_content_type($fichier);
if (strpos($mime, 'image/') !== 0) {
  status_header(403);
  exit('Type de fichier non autorisé.');
}

// 📦 Envoi du fichier
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($fichier));
readfile($fichier);
exit;
