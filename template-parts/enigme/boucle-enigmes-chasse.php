<?php
defined('ABSPATH') || exit;

$chasse_id = $args['chasse_id'] ?? null;
if (!$chasse_id || get_post_type($chasse_id) !== 'chasse') return;

$enigmes = recuperer_enigmes_associees($chasse_id); // fonction à part, ou get_posts
if (empty($enigmes)) return;
?>

<div class="bloc-enigmes-chasse">
  <?php foreach ($enigmes as $enigme_id): ?>
    <?php
    $titre = get_the_title($enigme_id);
    $etat_systeme = enigme_get_etat_systeme($enigme_id);
    $statut_utilisateur = enigme_get_statut_utilisateur($enigme_id, get_current_user_id());
    $tentative = get_field('enigme_tentative', $enigme_id);
    $cout_points = intval($tentative['enigme_tentative_cout_points'] ?? 0);

    $peut_engager = utilisateur_peut_engager_enigme($enigme_id);
    ?>

    <article class="carte-enigme">
      <h3><?= esc_html($titre); ?></h3>
      <p class="statuts">
        État : <strong><?= esc_html($etat_systeme); ?></strong><br>
        Votre statut : <strong><?= esc_html($statut_utilisateur); ?></strong>
      </p>

      <?php if ($peut_engager): ?>
        <form method="post" action="<?= esc_url(site_url('/traitement-engagement')); ?>">
          <input type="hidden" name="enigme_id" value="<?= esc_attr($enigme_id); ?>">
          <?php wp_nonce_field('engager_enigme_' . $enigme_id, 'engager_enigme_nonce'); ?>
          <button type="submit" class="cta-engager">
            <?= $cout_points > 0
              ? "Débloquer pour $cout_points points"
              : "Commencer gratuitement"; ?>
          </button>
        </form>
      <?php else: ?>
        <?php if ($statut_utilisateur === 'en_cours'): ?>
          <a href="<?= get_permalink($enigme_id); ?>" class="cta-secondaire">
            ▶️ Continuer
          </a>

        <?php elseif ($statut_utilisateur === 'resolue'): ?>
          <p class="message-info">✅ Énigme résolue.</p>
          <a href="<?= get_permalink($enigme_id); ?>" class="cta-secondaire">
            🔁 Revoir
          </a>

        <?php elseif ($statut_utilisateur === 'terminee'): ?>
          <p class="message-info grise">✔️ Énigme finalisée.</p>

        <?php else: ?>
          <p class="message-info">🔒 Énigme inaccessible.</p>
        <?php endif; ?>
      <?php endif; ?>

    </article>

  <?php endforeach; ?>
</div>