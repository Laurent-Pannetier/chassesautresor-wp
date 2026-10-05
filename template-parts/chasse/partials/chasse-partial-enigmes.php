<?php
/**
 * Displays riddle statistics table for a hunt.
 *
 * Variables:
 * - $enigmes (array)
 * - $total (int) Total participants in the hunt.
 * - $show_points (bool) Whether to display the Points column.
 * - $cols_etiquette (array) 1-based visual column indexes for etiquette styling.
 */

defined('ABSPATH') || exit();

$args           = $args ?? [];
$enigmes        = $args['enigmes'] ?? $enigmes ?? [];
$total          = $args['total'] ?? $total ?? 0;
$title          = $args['title'] ?? '';
$cols_etiquette = $args['cols_etiquette'] ?? [];
$show_points    = array_key_exists('show_points', $args)
    ? (bool) $args['show_points']
    : (!function_exists('cat_is_points_ui_enabled') || cat_is_points_ui_enabled());

if (empty($enigmes)) {
    return;
}

if ($title !== '') {
    echo '<h3>' . esc_html($title) . '</h3>';
}

/**
 * @param int $col 1-based visual column index.
 */
$etiquette_attrs = static function (int $col, array $cols_etiquette): string {
    if (!in_array($col, $cols_etiquette, true)) {
        return '';
    }

    return ' data-format="etiquette" data-col="' . esc_attr((string) $col) . '"';
};

$trouves_col = $show_points ? 5 : 4;
?>
<table class="stats-table compact">
  <thead>
    <tr>
      <th scope="col"><?= esc_html__('Titre', 'chassesautresor-com'); ?></th>
      <th scope="col"<?= $etiquette_attrs(2, $cols_etiquette); ?>><?= esc_html__('Joueurs', 'chassesautresor-com'); ?></th>
      <th scope="col"<?= $etiquette_attrs(3, $cols_etiquette); ?>><?= esc_html__('Tentatives', 'chassesautresor-com'); ?></th>
      <?php if ($show_points) : ?>
        <th scope="col"<?= $etiquette_attrs(4, $cols_etiquette); ?>><?= esc_html__('Points', 'chassesautresor-com'); ?></th>
      <?php endif; ?>
      <th scope="col"<?= $etiquette_attrs($trouves_col, $cols_etiquette); ?>><?= esc_html__('Trouvées', 'chassesautresor-com'); ?></th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($enigmes as $e) : ?>
    <?php $pourcentage = $total > 0 ? (int) round((100 * $e['engagements']) / $total) : 0; ?>
    <tr>
      <td><a href="<?= esc_url(get_permalink($e['id'])); ?>"><?= esc_html($e['titre']); ?></a></td>
      <td><span class="etiquette"><?= esc_html($e['engagements'] . '/' . $total); ?></span> <i>(<?= esc_html($pourcentage); ?>%)</i></td>
      <td><?= $e['tentatives'] ? esc_html($e['tentatives']) : ''; ?></td>
      <?php if ($show_points) : ?>
        <td><?= !empty($e['points']) ? esc_html($e['points']) : ''; ?></td>
      <?php endif; ?>
      <td><?= $e['resolutions'] ? esc_html($e['resolutions']) : ''; ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
