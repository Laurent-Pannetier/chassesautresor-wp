<?php
/**
 * Template Name: Traitement Tentative (Confirmation explicite)
 */

require_once get_stylesheet_directory() . '/inc/enigme-functions.php';

$uid = sanitize_text_field($_GET['uid'] ?? '');

if (!$uid) {
  wp_die("Paramètre UID manquant.");
}

$tentative = get_tentative_by_uid($uid);
if (!$tentative) {
  wp_die("Tentative introuvable.");
}

$etat = get_etat_tentative($uid);
$infos = recuperer_infos_tentative($uid);

$permalink = get_permalink($tentative->enigme_id);

// Protection d’accès (admin ou organisateur lié)
$chasse_id = recuperer_id_chasse_associee((int) $tentative->enigme_id);
$organisateur_id = get_organisateur_from_chasse($chasse_id);
$organisateur_user_ids = (array) get_field('utilisateurs_associes', $organisateur_id);
$current_user_id = get_current_user_id();

if (
  !current_user_can('manage_options') &&
  !in_array($current_user_id, array_map('intval', $organisateur_user_ids), true)
) {
  wp_die('Accès refusé.');
}

// Traitement si POST explicite
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_traitement'], $_POST['uid'])) {
  $action = $_POST['action_traitement'];
  $uid_post = sanitize_text_field($_POST['uid']);

  if ($uid_post === $uid && in_array($action, ['valider', 'invalider'])) {
    $resultat = ($action === 'valider') ? 'bon' : 'faux';
    $effectue = traiter_tentative_manuelle($uid, $resultat);
    wp_safe_redirect(add_query_arg('done', $effectue ? '1' : '0'));
    exit;
  }
}

// Affichage
get_header();
?>
<div style="max-width:700px;margin:4em auto;text-align:center;font-family:sans-serif;">
  <h2>Traitement de la tentative</h2>
  <p><strong>UID :</strong> <?= esc_html($uid); ?></p>
  <p><strong>Statut actuel :</strong> <?= esc_html($etat); ?></p>
  <p><strong>Utilisateur :</strong> <?= esc_html($infos['nom_user'] ?? 'Inconnu'); ?></p>
  <p><a href="<?= esc_url($permalink); ?>">Voir l'énigme</a></p>

  <?php if ($etat === 'attente'): ?>
    <form method="post" style="margin-top:2em;">
      <input type="hidden" name="uid" value="<?= esc_attr($uid); ?>">
      <button name="action_traitement" value="valider" style="background:#28a745;color:#fff;padding:10px 20px;margin:1em;">✅ Valider</button>
      <button name="action_traitement" value="invalider" style="background:#dc3545;color:#fff;padding:10px 20px;margin:1em;">❌ Refuser</button>
    </form>
  <?php else: ?>
    <div style="background:#fff3cd;padding:1em;border:1px solid #ffeeba;border-radius:5px;">
      Cette tentative a déjà été traitée : <strong><?= esc_html($etat); ?></strong>.
    </div>
  <?php endif; ?>
</div>
<?php
get_footer();
