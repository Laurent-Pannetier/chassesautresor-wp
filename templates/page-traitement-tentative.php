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

$infos = recuperer_infos_tentative($uid);
$etat  = $infos['etat_tentative'] ?? 'invalide';
$permalink = get_permalink((int) $tentative->enigme_id);

// 🔐 Sécurisation organisateur ou admin
$chasse_id = recuperer_id_chasse_associee((int) $tentative->enigme_id);
$organisateur_id = get_organisateur_from_chasse($chasse_id);
$organisateur_user_ids = (array) get_field('utilisateurs_associes', $organisateur_id);
$current_user_id = get_current_user_id();

if (
    !current_user_can('manage_options') &&
    !in_array($current_user_id, array_map('intval', $organisateur_user_ids), true)
) {
    wp_die("⛔ Accès refusé.");
}

// ✅ Traitement si POST (validation ou refus)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_traitement'], $_POST['uid'])) {
    check_admin_referer('traiter_tentative_' . $uid);
    $action = $_POST['action_traitement'];
    $uid_post = sanitize_text_field($_POST['uid']);

    if ($uid_post === $uid && in_array($action, ['valider', 'invalider'], true)) {
        $resultat = $action === 'valider' ? 'bon' : 'faux';
        $effectue = traiter_tentative_manuelle($uid, $resultat);
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
        pour l’énigme <strong><?= esc_html(get_the_title($tentative->enigme_id)); ?></strong>
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
          <button type="submit" name="action_traitement" value="valider" class="btn btn-valider">✅ Valider</button>
          <button type="submit" name="action_traitement" value="invalider" class="btn btn-refuser">❌ Refuser</button>
        </div>
      </form>
    <?php else: ?>
      <div class="bloc-deja-traitee">
        Cette tentative a déjà été <strong><?= esc_html($etat === 'validee' ? 'validée' : 'refusée'); ?></strong>.
      </div>
    <?php endif; ?>
  </div>
</main>

<?php get_footer(); ?>
