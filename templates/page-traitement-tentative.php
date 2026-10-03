<?php
/**
 * Template Name: Traitement Tentative (Confirmation explicite)
 */

require_once get_stylesheet_directory() . '/inc/enigme-functions.php';

$uid = sanitize_text_field($_GET['uid'] ?? '');
if (!$uid) wp_die( __( 'Paramètre UID manquant.', 'chassesautresor-com' ) );

$tentative = get_tentative_by_uid($uid);
if (!$tentative) wp_die( __( 'Tentative introuvable.', 'chassesautresor-com' ) );

$enigme_id = (int) $tentative->enigme_id;
$infos = recuperer_infos_tentative($uid);
$etat = $infos['etat_tentative'] ?? 'invalide';
$permalink = get_permalink($enigme_id);

// 🔐 Protection d’accès : organisateur ou admin
$chasse_id = recuperer_id_chasse_associee($enigme_id);
$organisateur_id = get_organisateur_from_chasse($chasse_id);
$organisateur_user_ids = (array) get_field('utilisateurs_associes', $organisateur_id);
$current_user_id = get_current_user_id();

if (
  !current_user_can('manage_options') &&
  !in_array($current_user_id, array_map('intval', $organisateur_user_ids), true)
) {
  wp_die( __( '⛔️ Accès refusé.', 'chassesautresor-com' ) );
}

// 💚 Réinitialisations
$reset_action = isset($_GET['reset_all'])
  ? 'all'
  : (isset($_GET['reset_tentatives']) ? 'attempts' : (isset($_GET['reset_statuts']) ? 'statuses' : ''));
if ($reset_action !== '') {
  global $wpdb;
  $reset = (new ChassesAuTresor\Core\Progress\RiddleAttemptMaintenanceService(
    cat_get_riddle_attempt_service(),
    cat_get_hunt_progress_service(),
    ChassesAuTresor\Core\Support\CoreServiceFactory::riddleRetry($wpdb)
  ))->reset($reset_action, $enigme_id);
  echo '<p style="text-align:center;">' . esc_html(sprintf(
    __('%1$d tentative(s) et %2$d statut(s) supprimé(s).', 'chassesautresor-com'),
    $reset['attempts'],
    $reset['statuses']
  )) . '</p>';
  return;
}

// ✅ Traitement si POST (validation ou refus)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_traitement'], $_POST['uid'])) {
  check_admin_referer('traiter_tentative_' . $uid);
  $action = sanitize_text_field($_POST['action_traitement']);
  $uid_post = sanitize_text_field($_POST['uid']);

  if ($uid_post === $uid && in_array($action, ['valider', 'invalider'], true)) {
    $resultat = $action === 'valider' ? 'bon' : 'faux';
    $effectue = ChassesAuTresor\Core\Progress\ManualAttemptReviewHandler::process($uid, $resultat);
    wp_safe_redirect(add_query_arg('done', $effectue ? '1' : '0'));
    exit;
  }
}

get_header();
?>

<main class="page-traitement-tentative">
  <div class="container">
    <section class="bloc-infos">
      <h2>
        Tentative de <strong><?= esc_html($infos['nom_user'] ?? 'Inconnu'); ?></strong>
        pour l’énigme <strong><?= esc_html(get_the_title($enigme_id)); ?></strong>
      </h2>

      <p><strong>Identifiant unique de tentative :</strong> <?= esc_html($uid); ?></p>
      <p><strong>Statut :</strong> <?= ucfirst(esc_html($etat)); ?></p>
      <p><a href="<?= esc_url($permalink); ?>" class="lien-enigme">🔍 Voir l’énigme</a></p>
    </section>

    <?php if ($etat === 'attente'): ?>
      <form method="post" class="form-traitement">
        <?php wp_nonce_field('traiter_tentative_' . $uid); ?>
        <input type="hidden" name="uid" value="<?= esc_attr($uid); ?>">

        <div class="boutons">
          <button type="submit" name="action_traitement" value="valider" class="bouton-cta">✅ Valider</button>
          <button type="submit" name="action_traitement" value="invalider" class="btn-danger">❌ Refuser</button>
        </div>
      </form>
    <?php else: ?>
      <div class="bloc-deja-traitee">
        Cette tentative a été <strong><?= esc_html($etat === 'validee' ? 'validée' : 'refusée'); ?></strong>.
      </div>
    <?php endif; ?>
  </div>

  <div class="traitement-actions">
    <a href="<?= esc_url(add_query_arg('reset_statuts', '1')); ?>"
      onclick="return confirm('Supprimer tous les statuts utilisateurs pour cette énigme ?');"
      class="btn-danger">
      🧹 Réinitialiser les statuts
    </a>

    <a href="<?= esc_url(add_query_arg('reset_tentatives', '1')); ?>"
      onclick="return confirm('Supprimer toutes les tentatives pour cette énigme ?');"
      class="btn-danger">
      ❌ Supprimer les tentatives
    </a>

    <a href="<?= esc_url(add_query_arg('reset_all', '1')); ?>"
      onclick="return confirm('Supprimer TOUT (statuts + tentatives) ?');"
      class="btn-danger">
      🔥 Tout supprimer
    </a>
  </div>
</main>

<?php get_footer(); ?>
