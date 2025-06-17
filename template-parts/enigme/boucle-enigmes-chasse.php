<?php
defined('ABSPATH') || exit;

$chasse_id = $args['chasse_id'] ?? null;
if (!$chasse_id || get_post_type($chasse_id) !== 'chasse') return;

$enigmes = get_field('chasse_cache_enigmes', $chasse_id);
if (empty($enigmes)) return;
?>

<div class="liste-enigmes-chasse">
  <?php foreach ($enigmes as $enigme_id): ?>
    <?php
    $titre = get_the_title($enigme_id);
    $etat_systeme = get_field('enigme_cache_etat_systeme', $enigme_id);
    $statut_utilisateur = get_field('enigme_statut_utilisateur', 'user_' . get_current_user_id())[$enigme_id] ?? 'non_souscrite';
    $cout_points = get_field('enigme_tentative')['enigme_tentative_cout_points'] ?? 0;
    $peut_engager = ($etat_systeme === 'accessible' && $statut_utilisateur === 'non_souscrite');
    ?>

    <div class="carte-enigme">
      <h3><?= esc_html($titre); ?></h3>
      <p>État : <?= esc_html($etat_systeme); ?> | Statut joueur : <?= esc_html($statut_utilisateur); ?></p>

      <?php if ($peut_engager): ?>
        <form method="post" action="<?= esc_url(site_url('/traitement-engagement')); ?>">
          <input type="hidden" name="enigme_id" value="<?= esc_attr($enigme_id); ?>">
          <button type="submit" class="cta-engager">
            <?= $cout_points > 0 ? "Débloquer pour $cout_points points" : "Commencer gratuitement"; ?>
          </button>
        </form>
      <?php else: ?>
        <p class="message-statut">Cette énigme est <?= esc_html($statut_utilisateur); ?>.</p>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>
