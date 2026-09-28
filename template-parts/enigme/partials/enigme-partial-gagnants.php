<?php
/**
 * Liste des gagnants d'une énigme.
 * Variables attendues :
 * - $gagnants (array)
 * - $page (int)
 * - $pages (int)
 * - $user_id (int)
 */

defined('ABSPATH') || exit;

$args      = $args ?? [];
$gagnants  = $args['gagnants'] ?? $gagnants ?? [];
$page      = $args['page'] ?? $page ?? 1;
$pages     = $args['pages'] ?? $pages ?? 1;
$user_id = $args['user_id'] ?? $user_id ?? 0;
$total   = $args['total'] ?? $total ?? 0;
?>
<h3>
  <?php esc_html_e('Gagnants', 'chassesautresor-com'); ?>
</h3>
<div class="bloc-metas-inline bloc-metas-inline--compact">
  <div class="meta-etiquette">
    <span><?php esc_html_e('Gagnants :', 'chassesautresor-com'); ?></span>
    <strong><?php echo esc_html($total); ?></strong>
  </div>
</div>
<?php if (!empty($gagnants)) : ?>
<table class="stats-table compact">
  <thead>
    <tr>
      <th scope="col"></th>
      <th scope="col"><?php esc_html_e('Joueur', 'chassesautresor-com'); ?></th>
      <th scope="col"><?php esc_html_e('Date', 'chassesautresor-com'); ?></th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($gagnants as $index => $g) :
        $rank = ($page - 1) * 10 + $index + 1;
        $highlight = ((int) $g['user_id'] === (int) $user_id) ? ' class="current-user"' : '';
    ?>
    <tr<?php echo $highlight; ?>>
      <td><?php echo esc_html($rank); ?></td>
      <td><?php echo esc_html($g['username']); ?></td>
      <td><?php echo esc_html(mysql2date('d/m/y', $g['date'])); ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php echo cta_render_pager($page, $pages, 'enigme-gagnants-pager'); ?>
<?php else : ?>
<p><?php esc_html_e('Aucun gagnant pour le moment.', 'chassesautresor-com'); ?></p>
<?php endif; ?>
