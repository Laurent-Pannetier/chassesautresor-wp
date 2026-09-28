<?php
/**
 * Affiche le tableau des participants engagés sur une énigme.
 * Variables requises :
 * - $participants (array)
 * - $page (int)
 * - $par_page (int)
 * - $total (int)
 * - $pages (int)
 * - $mode_validation (string)
 * - $orderby (string)
 * - $order (string)
 */
defined('ABSPATH') || exit;

$args = $args ?? [];
$participants = $args['participants'] ?? $participants ?? [];
$page = $args['page'] ?? $page ?? 1;
$par_page = $args['par_page'] ?? $par_page ?? 25;
$total = $args['total'] ?? $total ?? 0;
$pages = $args['pages'] ?? $pages ?? (int) ceil($total / $par_page);
$mode_validation = $args['mode_validation'] ?? $mode_validation ?? 'aucune';
$orderby = $args['orderby'] ?? $orderby ?? 'date';
$order = $args['order'] ?? $order ?? 'ASC';
$stats_locked = $args['stats_locked'] ?? $stats_locked ?? false;

$icon_date = 'fa-sort';
if ($orderby === 'date') {
    $icon_date = strtoupper($order) === 'ASC' ? 'fa-sort-up' : 'fa-sort-down';
}
$icon_tentatives = 'fa-sort';
if ($orderby === 'tentatives') {
    $icon_tentatives = strtoupper($order) === 'ASC' ? 'fa-sort-up' : 'fa-sort-down';
}

if (empty($participants)) :
    if ($stats_locked) :
        ?>
        <p class="edition-placeholder" style="text-align:center;"><?php esc_html_e('Les statistiques seront disponibles une fois la chasse activée.', 'chassesautresor-com'); ?></p>
        <?php
    else :
        ?>
        <p><?= esc_html__('Aucun participant engagé.', 'chassesautresor-com'); ?></p>
        <?php
    endif;
else : ?>
<h3><?= esc_html__('Liste participants', 'chassesautresor-com'); ?></h3>
<table class="stats-table compact">
  <thead>
      <tr>
        <th scope="col"><?= esc_html__('Nom', 'chassesautresor-com'); ?></th>
        <th scope="col">
          <button
            class="sort"
            data-orderby="date"
            aria-label="<?= esc_attr__('Trier par engagement', 'chassesautresor-com'); ?>"
          >
            <?= esc_html__('Engagement', 'chassesautresor-com'); ?>
            <i class="fa-solid <?= esc_attr($icon_date); ?>"></i>
          </button>
        </th>
        <?php if ($mode_validation !== 'aucune') : ?>
        <th scope="col" data-format="etiquette">
          <button
            class="sort"
            data-orderby="tentatives"
            aria-label="<?= esc_attr__('Trier par tentatives', 'chassesautresor-com'); ?>"
          >
            <?= esc_html__('Tentatives', 'chassesautresor-com'); ?>
            <i class="fa-solid <?= esc_attr($icon_tentatives); ?>"></i>
          </button>
        </th>
        <?php endif; ?>
        <th scope="col"><?= esc_html__('Trouvé', 'chassesautresor-com'); ?></th>
      </tr>
  </thead>
  <tbody>
    <?php foreach ($participants as $p) : ?>
    <tr>
      <td><?= esc_html($p['username']); ?></td>
      <td><?= esc_html(mysql2date('d/m/Y H:i', $p['date_engagement'])); ?></td>
      <?php if ($mode_validation !== 'aucune') : ?>
      <td><?= esc_html($p['nb_tentatives']); ?></td>
      <?php endif; ?>
      <td>
        <?php if ($p['trouve']) : ?>
          <i class="fa-solid fa-check fa-xl"></i>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<div class="pager">
    <?php if ($page > 1) : ?>
      <button class="pager-first" aria-label="<?= esc_attr__('Première page', 'chassesautresor-com'); ?>">
        <i class="fa-solid fa-angles-left"></i>
      </button>
      <button class="pager-prev" aria-label="<?= esc_attr__('Page précédente', 'chassesautresor-com'); ?>">
        <i class="fa-solid fa-angle-left"></i>
      </button>
    <?php endif; ?>
  <span class="pager-info"><?= esc_html($page); ?> / <?= esc_html($pages); ?></span>
    <?php if ($page < $pages) : ?>
      <button class="pager-next" aria-label="<?= esc_attr__('Page suivante', 'chassesautresor-com'); ?>">
        <i class="fa-solid fa-angle-right"></i>
      </button>
      <button class="pager-last" aria-label="<?= esc_attr__('Dernière page', 'chassesautresor-com'); ?>">
        <i class="fa-solid fa-angles-right"></i>
      </button>
    <?php endif; ?>
</div>
<?php endif; ?>
