<?php
/**
 * Template Part : tentative-feedback.php
 * Affiche le retour après traitement d'une tentative manuelle
 * Reçoit via get_template_part(..., ..., [ '...' => ... ]) :
 * - etat_tentative : 'attente' | 'validee' | 'refusee' | 'invalide' | 'inexistante'
 * - resultat, statut_initial, statut_final, nom_user, permalink, statistiques
 */

$etat_tentative = $args['etat_tentative'] ?? 'invalide';
$resultat = $args['resultat'] ?? '';
$statut_initial = $args['statut_initial'] ?? '';
$statut_final = $args['statut_final'] ?? '';
$permalink = $args['permalink'] ?? '';
$nom_user = $args['nom_user'] ?? 'Utilisateur';
$statistiques = $args['statistiques'] ?? [];

?>

<div style="max-width:600px;margin:3em auto;text-align:center;font-family:sans-serif;">
  <?php $logo = get_site_icon_url(96); ?>
  <a href="<?= esc_url(home_url()); ?>">
    <img src="<?= esc_url($logo); ?>" alt="Logo" style="width:48px;height:48px;margin-bottom:1em;">
  </a>

  <?php
  switch ($etat_tentative) {
    case 'validee':
      echo '<p>✅ Cette tentative a déjà été <strong>validée</strong>.</p>';
      break;
    case 'refusee':
      echo '<p>❌ Cette tentative a déjà été <strong>refusée</strong>.</p>';
      break;
    case 'attente':
      echo '<p>⏳ Votre tentative est en <strong>attente de traitement</strong>.</p>';
      break;
    case 'inexistante':
      echo '<p>🚫 Tentative introuvable.</p>';
      break;
    case 'invalide':
    default:
      echo '<p>❓ État de la tentative inconnu ou invalide.</p>';
      break;
  }
  ?>

  <div style="margin-top:2em;font-size:1em;">
    <?php if (!empty($statistiques)) : ?>
      <p>📌 Tentative <strong><?= (int)($statistiques['total_user'] ?? 0); ?></strong> de <strong><?= esc_html($nom_user); ?></strong></p>
      <p>📊 Tentative <strong><?= (int)($statistiques['total_enigme'] ?? 0); ?></strong> sur cette énigme</p>
      <?php if (!empty($statistiques['total_chasse'])) : ?>
        <p>🧩 Tentative <strong><?= (int)$statistiques['total_chasse']; ?></strong> sur la chasse</p>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <div style="margin-top:3em;">
    <a href="#" onclick="fermerFenetreOuRediriger(); return false;" style="margin-right:1em;">❎ Fermer cette fenêtre</a>
    <a href="<?= esc_url($permalink); ?>" style="background:#0073aa;padding:10px 20px;border-radius:5px;color:white;text-decoration:none;">🔍 Voir cette énigme</a>
  </div>

  <div style="text-align:center;margin-top:3em;">
    <a href="<?= esc_url(add_query_arg('reset_tentatives', '1')); ?>"
      onclick="return confirm('Confirmer la réinitialisation des statuts pour cette énigme ?');"
      style="background:#900;color:#fff;padding:10px 20px;border-radius:5px;text-decoration:none;margin-right:1em;">
      🧹 Réinitialiser les statuts</a>
    <a href="<?= esc_url(add_query_arg('reset_tentatives_totales', '1')); ?>"
      onclick="return confirm('Confirmer la suppression de toutes les tentatives pour cette énigme ?');"
      style="background:#555;color:#fff;padding:10px 20px;border-radius:5px;text-decoration:none;">
      🚫 Supprimer toutes les tentatives</a>
  </div>
</div>

<script>
  function fermerFenetreOuRediriger() {
    window.close();
    setTimeout(function () {
      if (!window.closed) {
        window.location.href = '/';
      }
    }, 500);
  }
</script>
