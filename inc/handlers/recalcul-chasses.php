<?php
// 🔐 Sécurité : uniquement exécutable en ligne de commande
if (php_sapi_name() !== 'cli') exit;

// 🧠 Bootstrap WordPress (ajuster chemin si besoin)
require_once dirname(__DIR__, 2) . '/wp-load.php';

if (!defined('ABSPATH')) exit;

// 🔁 Recalcul des statuts de toutes les chasses
$chasses = get_posts([
    'post_type'   => 'chasse',
    'post_status' => ['publish', 'pending', 'draft'],
    'numberposts' => -1,
    'fields'      => 'ids',
]);

foreach ($chasses as $chasse_id) {
    mettre_a_jour_statuts_chasse($chasse_id);
}
