<?php

/**
 * Template Part : tentative-feedback.php
 * Affiche le retour après traitement d'une tentative manuelle
 * Reçoit via get_template_part(..., ..., [ '...' => ... ]) :
 * - statut_initial, statut_final, resultat, traitement_bloque, permalink, statistiques, nom_user
 */


$traitement_bloque = $args['traitement_bloque'] ?? false;
$traitement_effectue = $args['traitement_effectue'] ?? false;
$statut_initial = $args['statut_initial'] ?? '';
$statut_final = $args['statut_final'] ?? '';
$permalink = $args['permalink'] ?? '';
$nom_user = $args['nom_user'] ?? 'Utilisateur';
$statistiques = $args['statistiques'] ?? [];
$resultat = $args['resultat'] ?? '';


error_log('[TEMPLATE] traitement_bloque = ' . var_export($traitement_bloque, true));
error_log('[TEMPLATE] statut_initial = ' . var_export($statut_initial, true));
error_log('[TEMPLATE] statut_final = ' . var_export($statut_final, true));
error_log('[TEMPLATE] permalink = ' . var_export($permalink, true));
error_log('[TEMPLATE] nom_user = ' . var_export($nom_user, true));
error_log('[TEMPLATE] statistiques = ' . var_export($statistiques, true));
error_log('[TEMPLATE] resultat = ' . var_export($resultat, true));
?>

<div style="max-width:600px;margin:3em auto;text-align:center;font-family:sans-serif;">
  <?php $logo = get_site_icon_url(96); ?>
  <a href="<?= esc_url(home_url()); ?>">
    <img src="<?= esc_url($logo); ?>" alt="Logo" style="width:48px;height:48px;margin-bottom:1em;">
  </a>

  <?php if ($traitement_bloque && $traitement_effectue): ?>
    <p>✅ La réponse a bien été <strong><?= $resultat === 'bon' ? 'validée' : 'refusée'; ?></strong>.</p>
  <?php elseif ($traitement_bloque): ?>
    <p>ℹ️ La tentative a déjà été traitée.</p>
    <p>Résultat actuel : <strong><?= esc_html($resultat === 'bon' ? 'validée' : 'refusée'); ?></strong></p>
  <?php else: ?>
    <p>✅ La réponse a bien été <strong><?= $resultat === 'bon' ? 'validée' : 'refusée'; ?></strong>.</p>
  <?php endif; ?>


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
    setTimeout(function() {
      if (!window.closed) {
        window.location.href = '/';
      }
    }, 500);
  }
</script>