<?php
/**
 * Template Part : tentative-feedback.php
 * Affiche le retour après traitement d'une tentative manuelle
 * Reçoit via get_template_part(..., ..., [ '...' => ... ]) :
 * - statut_initial, statut_final, resultat, traitement_bloque, permalink, statistiques, nom_user
 */

$traitement_bloque = $args['traitement_bloque'] ?? false;
$statut_initial = $args['statut_initial'] ?? '';
$statut_final = $args['statut_final'] ?? '';
$permalink = $args['permalink'] ?? '';
$nom_user = $args['nom_user'] ?? 'Utilisateur';
$statistiques = $args['statistiques'] ?? [];
$resultat = $args['resultat'] ?? '';
?>

<div style="max-width:600px;margin:3em auto;text-align:center;font-family:sans-serif;">
  <?php $logo = get_site_icon_url(96); ?>
  <a href="<?= esc_url(home_url()); ?>">
    <img src="<?= esc_url($logo); ?>" alt="Logo" style="width:48px;height:48px;margin-bottom:1em;">
  </a>

  <?php if ($traitement_bloque): ?>
    <p>ℹ️ La tentative a déjà été traitée.</p>
    <p>Résultat actuel : <strong><?= esc_html($statut_initial); ?></strong></p>
  <?php else: ?>
    <p>✅ La réponse a bien été <strong><?= $resultat === 'bon' ? 'validée' : 'refusée'; ?></strong>.</p>
    <div style="margin-top:2em;font-size:1em;">
      <p>📌 Tentative <strong><?= (int)($statistiques['total_user'] ?? 0); ?></strong> de <strong><?= esc_html($nom_user); ?></strong></p>
      <p>📊 Tentative <strong><?= (int)($statistiques['total_enigme'] ?? 0); ?></strong> sur cette énigme</p>
      <?php if (!empty($statistiques['total_chasse'])): ?>
        <p>🧩 Tentative <strong><?= (int)$statistiques['total_chasse']; ?></strong> sur la chasse</p>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <div style="margin-top:3em;">
    <a href="#" onclick="fermerFenetreOuRediriger(); return false;" style="margin-right:1em;">❎ Fermer cette fenêtre</a>
    <a href="<?= esc_url($permalink); ?>" style="background:#0073aa;padding:10px 20px;border-radius:5px;color:white;text-decoration:none;">🔍 Voir cette énigme</a>
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