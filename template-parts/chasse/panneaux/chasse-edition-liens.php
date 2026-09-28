<?php
/**
 * Template Part: Panneau d'édition des liens publics d'une chasse
 * Basé sur la structure validée du panneau organisateur
 * Requiert : $args['chasse_id']
 */

defined('ABSPATH') || exit;

$chasse_id = $args['chasse_id'] ?? null;
if (!$chasse_id || get_post_type($chasse_id) !== 'chasse') return;

$liens = get_field('chasse_principale_liens', $chasse_id);
$liens = is_array($liens) ? $liens : [];
$types_disponibles = get_types_liens_publics();

$liens_actifs = [];
foreach ($liens as $entree) {
  $type_raw = $entree['chasse_principale_liens_type'] ?? null;
  $url = $entree['chasse_principale_liens_url'] ?? null;

  $type = is_array($type_raw) ? ($type_raw[0] ?? '') : $type_raw;
  if (is_string($type) && trim($type) !== '' && is_string($url) && trim($url) !== '') {
    $liens_actifs[$type] = $url;
  }
}
?>

<div id="panneau-liens-chasse" class="panneau-lateral-liens" aria-hidden="true" role="dialog">
  <div class="panneau-lateral__contenu">

    <header class="panneau-lateral__header">
      <h2><?php esc_html_e('Configurer les liens de cette chasse', 'chassesautresor-com'); ?></h2>
      <button type="button" class="panneau-fermer" aria-label="<?php esc_attr_e('Fermer le panneau', 'chassesautresor-com'); ?>">✖</button>
    </header>

    <form id="formulaire-liens-chasse"
          data-post-id="<?= esc_attr($chasse_id); ?>"
          data-cpt="chasse"
          data-champ="chasse_principale_liens">

      <ul class="liste-liens-formulaires">
        <?php foreach ($types_disponibles as $type => $infos) :
          $url = $liens_actifs[$type] ?? '';
          ?>
          <li class="ligne-lien-formulaire" data-type="<?= esc_attr($type); ?>">
            <label for="champ-<?= esc_attr($type); ?>">
              <i class="fa <?= esc_attr($infos['icone']); ?>"></i>
              <?= esc_html($infos['label']); ?>
            </label>
            <input
              type="url"
              name="chasse_principale_liens[<?= esc_attr($type); ?>]"
              id="champ-<?= esc_attr($type); ?>"
              value="<?= esc_attr($url); ?>"
              placeholder="https://..."
              class="champ-url-lien"
              inputmode="url"
            >
          </li>
        <?php endforeach; ?>
      </ul>

      <div class="panneau-lateral__actions">
        <button type="submit" class="bouton-enregistrer-liens"><?php esc_html_e('Enregistrer', 'chassesautresor-com'); ?></button>
      </div>
    </form>

  </div>
</div>
