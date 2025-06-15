<?php
/**
 * Template Name: Traitement Réponse (Finalisation Étape 6)
 */

if (!function_exists('get_field')) {
  require_once(ABSPATH . 'wp-load.php');
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

defined('ABSPATH') || exit;

$uid = sanitize_text_field($_GET['uid'] ?? '');
$resultat = sanitize_text_field($_GET['resultat'] ?? '');

if (!$uid || !in_array($resultat, ['bon', 'faux'], true)) {
  wp_die('Paramètres manquants ou invalides.');
}

global $wpdb;
$table = $wpdb->prefix . 'enigme_tentatives';

$tentative = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE tentative_uid = %s", $uid));

$user_id = isset($tentative->user_id) ? (int)$tentative->user_id : 0;
$enigme_id = isset($tentative->enigme_id) ? (int)$tentative->enigme_id : 0;

error_log("🔍 DEBUG \$uid = $uid, \$resultat = $resultat, user_id = $user_id, enigme_id = $enigme_id");

$chasse_raw = get_field('enigme_chasse_associee', $enigme_id, false);
error_log('🧪 Bloc 1: get_field enigme_chasse_associee: ' . print_r($chasse_raw, true));

if (is_array($chasse_raw)) {
  $first = reset($chasse_raw);
  $chasse_id = is_object($first) ? (int) $first->ID : (int) $first;
} elseif (is_object($chasse_raw)) {
  $chasse_id = (int) $chasse_raw->ID;
} elseif (is_numeric($chasse_raw)) {
  $chasse_id = (int) $chasse_raw;
} else {
  $chasse_id = 0;
}

$titre = get_the_title($enigme_id);
$permalink = get_permalink($enigme_id);

if (!is_string($titre)) {
  $titre = '';
}
if (!is_string($permalink)) {
  $permalink = '';
}
$permalink .= '?statistiques=1';

error_log('🧪 Bloc 2: titre = ' . print_r($titre, true));
error_log('🧪 Bloc 2: permalink = ' . print_r($permalink, true));

$nom_user = get_userdata($user_id)?->display_name ?? "Utilisateur inconnu";
error_log('🧪 Bloc 3: nom_user = ' . print_r($nom_user, true));

$organisateur_id = $chasse_id ? get_organisateur_from_chasse($chasse_id) : null;
$organisateur_user_ids_raw = $organisateur_id ? get_field('utilisateurs_associes', $organisateur_id) : [];
error_log('🧪 Bloc 4: utilisateurs_associes brut: ' . print_r($organisateur_user_ids_raw, true));

if (!is_array($organisateur_user_ids_raw)) {
  $organisateur_user_ids_raw = [$organisateur_user_ids_raw];
}

$organisateur_user_ids = [];
foreach ($organisateur_user_ids_raw as $item) {
  $organisateur_user_ids[] = is_object($item) ? (int) $item->ID : (int) $item;
}

$current_user_id = get_current_user_id();
error_log('🧪 Bloc 4: current_user_id = ' . $current_user_id);
error_log('🧪 Bloc 4: organisateur_user_ids = ' . print_r($organisateur_user_ids, true));

$acces_autorise = current_user_can('manage_options') || in_array($current_user_id, $organisateur_user_ids, true);
if (!$acces_autorise) {
  wp_die('Accès interdit à cette tentative.');
}

$statuts_table = $wpdb->prefix . 'enigme_statuts_utilisateur';
$new_statut = ($resultat === 'bon') ? 'resolue' : 'abandonnee';

$statut_actuel = $wpdb->get_var($wpdb->prepare(
  "SELECT statut FROM $statuts_table WHERE user_id = %d AND enigme_id = %d",
  $user_id,
  $enigme_id
));

error_log("🧪 Bloc 5: statut actuel = $statut_actuel | nouveau = $new_statut");

if ($statut_actuel && $statut_actuel !== 'resolue') {
  $wpdb->update(
    $statuts_table,
    ['statut' => $new_statut],
    ['user_id' => $user_id, 'enigme_id' => $enigme_id],
    ['%s'],
    ['%d', '%d']
  );
  error_log("🧪 Bloc 5: statut mis à jour vers $new_statut");
}

$total_user = $wpdb->get_var($wpdb->prepare(
  "SELECT COUNT(*) FROM $table WHERE user_id = %d AND enigme_id = %d",
  $user_id, $enigme_id
));
$total_enigme = $wpdb->get_var($wpdb->prepare(
  "SELECT COUNT(*) FROM $table WHERE enigme_id = %d",
  $enigme_id
));

$total_chasse = 0;
if ($chasse_id) {
  $ids_enigmes = get_posts([
    'post_type' => 'enigme',
    'fields' => 'ids',
    'posts_per_page' => -1,
    'meta_query' => [[
      'key' => 'enigme_chasse_associee',
      'value' => $chasse_id,
      'compare' => '=',
    ]]
  ]);

  error_log('🧪 Bloc 6: ids_enigmes pour la chasse: ' . print_r($ids_enigmes, true));

  if ($ids_enigmes) {
    $in_clause = implode(',', array_map('absint', $ids_enigmes));
    $total_chasse = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE enigme_id IN ($in_clause)");
  }
}

error_log("🧪 Bloc 6: total_user = $total_user | total_enigme = $total_enigme | total_chasse = $total_chasse");

// --- Bloc 7 : Envoi de mail ---
envoyer_mail_resultat_joueur($user_id, $enigme_id, $resultat);
error_log("📧 Bloc 7: mail envoyé à l'utilisateur #$user_id pour l'énigme #$enigme_id, résultat = $resultat");

?>

<div style="max-width:600px;margin:3em auto;text-align:center;font-family:sans-serif;">
  <p>✅ La réponse a bien été <strong><?= $resultat === 'bon' ? 'validée' : 'refusée'; ?></strong>.</p>
  <div style="margin-top:2em;font-size:1em;">
    <p>📌 Tentative <strong><?= $total_user; ?></strong> de <strong><?= esc_html($nom_user); ?></strong></p>
    <p>📊 Tentative <strong><?= $total_enigme; ?></strong> sur cette énigme</p>
    <?php if ($total_chasse): ?>
      <p>🧩 Tentative <strong><?= $total_chasse; ?></strong> sur la chasse</p>
    <?php endif; ?>
  </div>
  <div style="margin-top:3em;">
    <a href="#" onclick="window.close();" style="margin-right:1em;">❎ Fermer cette fenêtre</a>
    <a href="<?= esc_url($permalink); ?>" style="background:#0073aa;padding:10px 20px;border-radius:5px;color:white;text-decoration:none;">🔍 Voir cette énigme</a>
  </div>
</div>
