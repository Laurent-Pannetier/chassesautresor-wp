<?php
/**
 * Organizer statistics panel.
 */

defined('ABSPATH') || exit;

$organisateur_id = $args['organisateur_id'] ?? 0;
$joueurs         = organisateur_compter_joueurs_uniques($organisateur_id);
$points          = organisateur_compter_points_collectes($organisateur_id);
?>
<div class="edition-panel-body">
  <div class="dashboard-grid stats-cards">
    <?php
    get_template_part('template-parts/common/stat-card', null, [
        'icon'  => 'fa-solid fa-users',
        'label' => 'Joueurs',
        'value' => $joueurs,
        'stat'  => 'joueurs',
    ]);
    get_template_part('template-parts/common/stat-card', null, [
        'icon'  => 'fa-solid fa-coins',
        'label' => 'Points collectés',
        'value' => $points,
        'stat'  => 'points',
    ]);
    ?>
  </div>
  <?php
  $chasses = get_chasses_de_organisateur($organisateur_id);
  if ($chasses && !empty($chasses->posts)) {
      foreach ($chasses->posts as $chasse_id) {
          $chasse_id        = (int) $chasse_id;
          $participants     = chasse_compter_participants($chasse_id);
          $total_tentatives = 0;
          $total_resolutions = 0;
          $enigmes_stats    = [];
          foreach (recuperer_ids_enigmes_pour_chasse($chasse_id) as $enigme_id) {
              $engagements = enigme_compter_joueurs_engages($enigme_id);
              $tentatives  = enigme_compter_tentatives($enigme_id, 'automatique');
              $resolutions = enigme_compter_bonnes_solutions($enigme_id, 'automatique');
              $enigmes_stats[] = [
                  'id'          => $enigme_id,
                  'titre'       => get_the_title($enigme_id),
                  'engagements' => $engagements,
                  'tentatives'  => $tentatives,
                  'points'      => enigme_compter_points_depenses($enigme_id, 'automatique'),
                  'resolutions' => $resolutions,
              ];
              $total_tentatives += $tentatives;
              $total_resolutions += $resolutions;
          }
          ?>
          <div class="chasse-stats-header">
            <h3><?php echo esc_html(get_the_title($chasse_id)); ?></h3>
            <div class="chasse-stats-summary">
              <div class="meta-etiquette">
                <span><?php echo esc_html($participants . ' participants'); ?></span>
              </div>
              <div class="meta-etiquette">
                <span><?php echo esc_html($total_tentatives . ' tentatives'); ?></span>
              </div>
              <div class="meta-etiquette">
                <span><?php echo esc_html($total_resolutions . ' bonnes réponses'); ?></span>
              </div>
            </div>
          </div>
          <?php
          get_template_part(
              'template-parts/chasse/partials/chasse-partial-enigmes',
              null,
              [
                  'enigmes'       => $enigmes_stats,
                  'total'         => $participants,
                  'cols_etiquette' => [2, 3, 4, 5],
              ]
          );
      }
  } else {
      echo '<p class="edition-placeholder">Aucune statistique détaillée pour le moment.</p>';
  }
  ?>
</div>
