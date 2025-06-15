<?php

/**
 * Template Name: Traitement Réponse (Finalisation Sécurisée)
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

if (!$tentative) {
  wp_die('Tentative introuvable.');
}

$user_id = isset($tentative->user_id) ? (int)$tentative->user_id : 0;
$enigme_id = isset($tentative->enigme_id) ? (int)$tentative->enigme_id : 0;
// Réinitialisation des tentatives (déplacée ici pour accès à $enigme_id)
if (is_user_logged_in() && isset($_GET['reset_tentatives'])) {
  global $wpdb;
  $reset_table = $wpdb->prefix . 'enigme_statuts_utilisateur';
  $reset_rows = $wpdb->delete($reset_table, ['enigme_id' => $enigme_id], ['%d']);
  echo '<div style="text-align:center; background:#ffecec; color:#900; padding:1em; margin:2em auto; max-width:600px; border:1px solid #f00;">
    🧹 Réinitialisation : ' . esc_html($reset_rows) . ' ligne(s) supprimée(s) dans la table des statuts utilisateur.<br>
    <a href="' . esc_url(remove_query_arg('reset_tentatives')) . '" style="display:inline-block;margin-top:1em;">🔄 Revenir</a>
  </div>';
}


if ($tentative->tentative_uid !== $uid || !$user_id || !$enigme_id) {
  wp_die('Tentative invalide (cohérence UID).');
}

$chasse_raw = get_field('enigme_chasse_associee', $enigme_id, false);

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

$nom_user = get_userdata($user_id)?->display_name ?? "Utilisateur inconnu";

$organisateur_id = $chasse_id ? get_organisateur_from_chasse($chasse_id) : null;
$organisateur_user_ids_raw = $organisateur_id ? get_field('utilisateurs_associes', $organisateur_id) : [];

if (!is_array($organisateur_user_ids_raw)) {
  $organisateur_user_ids_raw = [$organisateur_user_ids_raw];
}

$organisateur_user_ids = [];
foreach ($organisateur_user_ids_raw as $item) {
  $organisateur_user_ids[] = is_object($item) ? (int) $item->ID : (int) $item;
}

$current_user_id = get_current_user_id();

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

if ($statut_actuel) {
?>
  <div style="max-width:600px;margin:3em auto;text-align:center;font-family:sans-serif;">
    <?php $logo = get_site_icon_url(96); ?>
    <a href="<?= esc_url(home_url()); ?>">
      <img src="<?= esc_url($logo); ?>" alt="Logo" style="width:48px;height:48px;margin-bottom:1em;">
    </a>
    <p>ℹ️ La tentative a déjà été traitée.</p>
    <p>Résultat actuel : <strong><?= esc_html($statut_actuel); ?></strong></p>
    <div style="margin-top:2em;">
      <a href="#" onclick="fermerFenetreOuRediriger(); return false;" style="margin-right:1em;">❎ Fermer cette fenêtre</a>
      <a href="<?= esc_url($permalink); ?>" style="background:#0073aa;padding:10px 20px;border-radius:5px;color:white;text-decoration:none;">🔍 Voir cette énigme</a>
    </div>
  </div>
  <script>
    function fermerFenetreOuRediriger() {
      window.close();
      setTimeout(function() {
        if (!window.closed) {
          window.location.href = '/';
        }
      }, 500);
    }
  </script>
<?php
  exit;
}

$wpdb->update(
  $statuts_table,
  ['statut' => $new_statut],
  ['user_id' => $user_id, 'enigme_id' => $enigme_id],
  ['%s'],
  ['%d', '%d']
);

$total_user = $wpdb->get_var($wpdb->prepare(
  "SELECT COUNT(*) FROM $table WHERE user_id = %d AND enigme_id = %d",
  $user_id,
  $enigme_id
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

  if ($ids_enigmes) {
    $in_clause = implode(',', array_map('absint', $ids_enigmes));
    $total_chasse = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE enigme_id IN ($in_clause)");
  }
}

envoyer_mail_resultat_joueur($user_id, $enigme_id, $resultat);

// Assure l'affichage du favicon (si thème ne le fait pas déjà)
add_action('wp_head', function () {
  if (!has_site_icon()) {
    echo '<link rel="shortcut icon" href="' . esc_url(get_site_icon_url(32)) . '" type="image/x-icon">';
  }
});
?>

<div style="max-width:600px;margin:3em auto;text-align:center;font-family:sans-serif;">
  <?php $logo = get_site_icon_url(96); ?>
  <a href="<?= esc_url(home_url()); ?>">
    <img src="<?= esc_url($logo); ?>" alt="Logo" style="width:48px;height:48px;margin-bottom:1em;">
  </a>
  <p>✅ La réponse a bien été <strong><?= $resultat === 'bon' ? 'validée' : 'refusée'; ?></strong>.</p>
  <div style="margin-top:2em;font-size:1em;">
    <p>📌 Tentative <strong><?= $total_user; ?></strong> de <strong><?= esc_html($nom_user); ?></strong></p>
    <p>📊 Tentative <strong><?= $total_enigme; ?></strong> sur cette énigme</p>
    <?php if ($total_chasse): ?>
      <p>🧩 Tentative <strong><?= $total_chasse; ?></strong> sur la chasse</p>
    <?php endif; ?>
  </div>
  <div style="margin-top:3em;">
    <a href="#" onclick="fermerFenetreOuRediriger(); return false;" style="margin-right:1em;">❎ Fermer cette fenêtre</a>
    <a href="<?= esc_url($permalink); ?>" style="background:#0073aa;padding:10px 20px;border-radius:5px;color:white;text-decoration:none;">🔍 Voir cette énigme</a>
  </div>
</div>


<div style="text-align:center;margin-top:3em;">
  <a href="<?= esc_url(add_query_arg('reset_tentatives', '1')); ?>"
     onclick="return confirm('Confirmer la réinitialisation des statuts pour cette énigme ?');"
     style="background:#900;color:#fff;padding:10px 20px;border-radius:5px;text-decoration:none;">
    🧹 Réinitialiser les statuts pour cette énigme
  </a>
</div>
<script>
  function fermerFenetreOuRediriger() {
    window.close();
    setTimeout(function() {
      if (!window.closed) {
        window.location.href = '/';
      }
    }, 500);
  }
// fin de la réinitialisation des tentatives

</script>
