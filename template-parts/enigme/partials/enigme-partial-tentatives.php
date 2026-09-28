<?php
/**
 * Affiche le tableau des tentatives de soumission pour une énigme.
 * Variables requises :
 * - $tentatives (array)
 * - $page (int)
 * - $par_page (int)
 * - $total (int)
 */
defined('ABSPATH') || exit;

$args = $args ?? [];
$tentatives = $args['tentatives'] ?? $tentatives ?? [];
$page = $args['page'] ?? $page ?? 1;
$par_page = $args['par_page'] ?? $par_page ?? 20;
$total = $args['total'] ?? $total ?? 0;
$pages = $args['pages'] ?? $pages ?? (int) ceil($total / $par_page);
?>
<?php if (empty($tentatives)) : ?>
<p><?= esc_html__('Aucune tentative de soumission.', 'chassesautresor-com'); ?></p>
<?php else : ?>
<table class="table-tentatives">
  <thead>
      <tr>
        <th><?= esc_html__('Date', 'chassesautresor-com'); ?></th>
        <th><?= esc_html__('Utilisateur', 'chassesautresor-com'); ?></th>
        <th><?= esc_html__('Proposition', 'chassesautresor-com'); ?></th>
        <th><?= esc_html__('Résultat', 'chassesautresor-com'); ?></th>
      </tr>
  </thead>
  <tbody>
  <?php foreach ($tentatives as $tent) :
    $user       = get_userdata($tent->user_id);
    $login      = ($user && isset($user->user_login)) ? $user->user_login : esc_html__('Inconnu', 'chassesautresor-com');
    $date       = mysql2date('d/m/y H:i', $tent->date_tentative);
    $is_pending = ($tent->resultat === 'attente' && (int) $tent->traitee === 0);
    $pending    = $is_pending ? ' tentative-pending' : '';
  ?>
    <tr class="<?= $pending ?>">
      <td><?= esc_html($date); ?></td>
      <td><?= esc_html($login); ?></td>
      <?php
      $uid     = isset($tent->tentative_uid) ? (string) $tent->tentative_uid : '';
      $options = $uid !== '' ? cta_prepare_masked_proposition_options($uid) : [];
      echo cta_render_proposition_cell($uid !== '' ? '' : ($tent->reponse_saisie ?? ''), false, 39, $options);
      ?>
      <td>
        <?php
        $result = $tent->resultat;
        $class  = 'etiquette-error';
        if ($result === 'bon') {
            $class = 'etiquette-success';
        } elseif ($result === 'attente') {
            $class = 'etiquette-pending';
        }

        $result_label = $result;
        if ($result === 'bon') {
            $result_label = esc_html__('bon', 'chassesautresor-com');
        } elseif ($result === 'faux') {
            $result_label = esc_html__('faux', 'chassesautresor-com');
        } elseif ($result === 'attente') {
            $result_label = esc_html__('attente', 'chassesautresor-com');
        }
        ?>
        <span class="etiquette <?= esc_attr($class); ?>"><?= esc_html($result_label); ?></span>
        <?php if ($is_pending) : ?>
          <form method="post" style="display:inline;">
            <?php wp_nonce_field('traiter_tentative_' . $tent->tentative_uid); ?>
            <input type="hidden" name="uid" value="<?= esc_attr($tent->tentative_uid); ?>">
            <button type="submit" name="action_traitement" value="valider" class="bouton-cta">
              <?= esc_html__('Valider', 'chassesautresor-com'); ?>
            </button>
            <button type="submit" name="action_traitement" value="invalider" class="bouton-secondaire">
              <?= esc_html__('Invalider', 'chassesautresor-com'); ?>
            </button>
          </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php echo cta_render_pager($page, $pages, 'enigme-tentatives-pager'); ?>
<?php endif; ?>
