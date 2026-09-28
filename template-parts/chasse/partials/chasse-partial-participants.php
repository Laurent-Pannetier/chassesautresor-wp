<?php
/**
 * Displays hunt participants table.
 *
 * Variables:
 * - $participants (array)
 * - $page (int)
 * - $par_page (int)
 * - $total (int)
 * - $pages (int)
 * - $total_enigmes (int)
 */

defined('ABSPATH') || exit;

$args = $args ?? [];
$participants   = $args['participants'] ?? $participants ?? [];
$page           = $args['page'] ?? $page ?? 1;
$par_page       = $args['par_page'] ?? $par_page ?? 25;
$total          = $args['total'] ?? $total ?? 0;
$pages          = $args['pages'] ?? $pages ?? (int) ceil($total / $par_page);
$total_enigmes  = $args['total_enigmes'] ?? $total_enigmes ?? 0;
$orderby        = $args['orderby'] ?? $orderby ?? 'inscription';
$order          = $args['order'] ?? $order ?? 'ASC';

$icon_participation = 'fa-sort';
if ($orderby === 'participation') {
    $icon_participation = strtoupper($order) === 'ASC' ? 'fa-sort-up' : 'fa-sort-down';
}
$icon_resolution = 'fa-sort';
if ($orderby === 'resolution') {
    $icon_resolution = strtoupper($order) === 'ASC' ? 'fa-sort-up' : 'fa-sort-down';
}
?>
<?php
if (empty($participants)) {
    return;
}
?>
<h3><?= esc_html__('Joueurs', 'chassesautresor-com'); ?></h3>
<table class="stats-table compact">
  <colgroup>
    <col style="width:20%">
    <col style="width:20%">
    <col style="width:20%">
    <col style="width:20%">
    <col style="width:20%">
  </colgroup>
  <thead>
    <tr>
      <th scope="col"><?= esc_html__('Joueur', 'chassesautresor-com'); ?></th>
      <th scope="col"><?= esc_html__('Inscription', 'chassesautresor-com'); ?></th>
      <th scope="col"><?= esc_html__('Énigmes', 'chassesautresor-com'); ?></th>
      <th scope="col" data-format="etiquette">
        <button
          class="sort"
          data-orderby="participation"
          aria-label="<?= esc_attr__('Trier par participation', 'chassesautresor-com'); ?>"
        >
          <?= esc_html__('Participation', 'chassesautresor-com'); ?>
          <i class="fa-solid <?= esc_attr($icon_participation); ?>"></i>
        </button>
      </th>
      <th scope="col" data-format="etiquette">
        <button
          class="sort"
          data-orderby="resolution"
          aria-label="<?= esc_attr__('Trier par énigmes trouvées', 'chassesautresor-com'); ?>"
        >
          <?= esc_html__('Trouvées', 'chassesautresor-com'); ?>
          <i class="fa-solid <?= esc_attr($icon_resolution); ?>"></i>
        </button>
      </th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($participants as $p) :
        $titles = [];
    foreach ($p['enigmes'] as $e) {
        $titles[] = esc_html($e['title']);
    }
    $participation_ratio = sprintf('%d/%d', (int) $p['nb_engagees'], (int) $total_enigmes);
    $trouvees_ratio      = sprintf('%d/%d', (int) $p['nb_resolues'], (int) $total_enigmes);
?>
    <tr>
      <td><?= esc_html($p['username']); ?></td>
      <td><?= $p['date_inscription'] ? esc_html(mysql2date('d/m/Y H:i', $p['date_inscription'])) : ''; ?></td>
      <td>
        <?php
        $etiquettes = array_map(
            static function ($title) {
                return '<span class="etiquette">' . $title . '</span>';
            },
            $titles
        );
        echo implode(' ', $etiquettes);
        ?>
      </td>
      <td><?= esc_html($participation_ratio); ?></td>
      <td><?= esc_html($trouvees_ratio); ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
  </table>
  <?php echo cta_render_pager($page, $pages, 'chasse-participants-pager'); ?>
