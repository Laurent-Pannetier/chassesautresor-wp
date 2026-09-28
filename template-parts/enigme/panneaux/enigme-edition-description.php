<?php
/**
 * Template Part: Panneau d'édition du texte principal d'une énigme (WYSIWYG)
 * Basé sur le modèle chasse / organisateur
 * Requiert : $args['enigme_id']
 */

defined('ABSPATH') || exit;

$enigme_id = $args['enigme_id'] ?? null;
if (!$enigme_id || get_post_type($enigme_id) !== 'enigme') return;
?>

<div id="panneau-description-enigme" class="panneau-lateral-liens panneau-lateral-large" aria-hidden="true" role="dialog">
  <div class="panneau-lateral__contenu">

    <header class="panneau-lateral__header">
      <h2><?php echo esc_html__( 'Modifier le texte de l’énigme', 'chassesautresor-com' ); ?></h2>
      <button type="button" class="panneau-fermer" aria-label="<?php echo esc_attr__( 'Fermer le panneau', 'chassesautresor-com' ); ?>">✖</button>
    </header>

    <?php
    $hide_label = static function (array $field): array {
        $field['wrapper']['class'] = trim(($field['wrapper']['class'] ?? '') . ' acf-hide-label');
        return $field;
    };
    add_filter('acf/prepare_field/name=enigme_visuel_texte', $hide_label);

    acf_form([
        'post_id'            => $enigme_id,
        'fields'             => ['enigme_visuel_texte'],
        'form'               => true,
        'submit_value'       => __('💾 Enregistrer', 'chassesautresor-com'),
        'html_submit_button' => '<div class="panneau-lateral__actions"><button type="submit" class="bouton-enregistrer-description bouton-enregistrer-liens">%s</button></div>',
        'html_before_fields' => '<div class="champ-wrapper">',
        'html_after_fields'  => '</div>',
        'return'             => add_query_arg('panneau', 'description-enigme', get_permalink()),
        'updated_message'    => __('Texte de l’énigme mis à jour.', 'chassesautresor-com'),
    ]);

    remove_filter('acf/prepare_field/name=enigme_visuel_texte', $hide_label);
    ?>

  </div>
</div>
