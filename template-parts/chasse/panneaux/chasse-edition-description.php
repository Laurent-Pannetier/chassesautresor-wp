<?php
/**
 * Template Part: Panneau d'édition de la description d'une chasse (WYSIWYG)
 * Reprend exactement le modèle organisateur
 * Appelé depuis n'importe quel template affichant une chasse.
 * Requiert : $args['chasse_id']
 */

defined('ABSPATH') || exit;

$chasse_id = $args['chasse_id'] ?? null;
if (! $chasse_id || get_post_type($chasse_id) !== 'chasse') {
    return;
}

$hide_label = static function (array $field): array {
    $field['wrapper']['class'] = trim(($field['wrapper']['class'] ?? '') . ' acf-hide-label');

    return $field;
};
add_filter('acf/prepare_field/name=chasse_principale_description', $hide_label);
?>

<div id="panneau-description-chasse" class="panneau-lateral-liens panneau-lateral-large" aria-hidden="true" role="dialog">
  <div class="panneau-lateral__contenu">

    <header class="panneau-lateral__header">
      <h2><?php echo esc_html__( 'Modifier la description de la chasse', 'chassesautresor-com' ); ?></h2>
      <button type="button" class="panneau-fermer" aria-label="<?php echo esc_attr__( 'Fermer le panneau', 'chassesautresor-com' ); ?>">✖</button>
    </header>

  <?php
  acf_form([
      'post_id'            => $chasse_id,
      'fields'             => ['chasse_principale_description'],
      'form'               => true,
      'submit_value'       => __( '💾 Enregistrer', 'chassesautresor-com' ),
      'html_submit_button' => '<div class="panneau-lateral__actions"><button type="submit" class="bouton-enregistrer-description bouton-enregistrer-liens">%s</button></div>',
      'html_before_fields' => '<div class="champ-wrapper">',
      'html_after_fields'  => '</div>',
      'return'             => add_query_arg(
          ['edition' => 'open', 'tab' => 'param'],
          get_permalink()
      ) . '#chasse-description',
      'updated_message'    => __( 'Description mise à jour.', 'chassesautresor-com' ),
  ]);
  remove_filter('acf/prepare_field/name=chasse_principale_description', $hide_label);
  ?>

  </div>
</div>
