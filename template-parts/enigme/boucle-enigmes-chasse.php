<?php
defined('ABSPATH') || exit;

$chasse_id = $args['chasse_id'] ?? null;
if (!$chasse_id || get_post_type($chasse_id) !== 'chasse') return;

$posts = recuperer_enigmes_pour_chasse($chasse_id);
if (empty($posts)) return;
?>

<div class="bloc-enigmes-chasse">
  <?php foreach ($posts as $post): ?>
    <?php
    $enigme_id = $post->ID;

    // Sécurité supplémentaire
    if (get_post_type($enigme_id) !== 'enigme') continue;

    $titre = get_the_title($enigme_id);
    $etat_systeme = enigme_get_etat_systeme($enigme_id);
    $statut_utilisateur = enigme_get_statut_utilisateur($enigme_id, get_current_user_id());
    $image_src = get_url_vignette_enigme($enigme_id);
    $cta = get_cta_enigme($enigme_id);
    ?>

    <article class="carte-enigme">
      <?php if ($image_src): ?>
        <div class="carte-enigme-image">
          <img src="<?= esc_url($image_src); ?>" alt="Vignette de l’énigme" class="vignette-enigme">
        </div>
      <?php endif; ?>

      <h3><?= esc_html($titre); ?></h3>
      <p>État système : <strong><?= esc_html($etat_systeme); ?></strong></p>
      <p>Statut joueur : <strong><?= esc_html($statut_utilisateur); ?></strong></p>

      <?php render_cta_enigme($cta, $enigme_id); ?>

      <!-- Debug optionnel -->
      <!-- <p style="font-size:0.85em; color:#888;">ID #<?= $enigme_id; ?> / post_type = <?= get_post_type($enigme_id); ?></p> -->
    </article>
  <?php endforeach; ?>
  <?php
  // Vérifie si l'utilisateur peut modifier cette chasse
  if (utilisateur_peut_modifier_post($chasse_id)) {
    $has_enigmes = !empty($posts ?? []);
    get_template_part('template-parts/enigme/carte-ajout-enigme', null, [
      'has_enigmes' => $has_enigmes,
      'chasse_id'   => $chasse_id,
    ]);
  }
  ?>

</div>