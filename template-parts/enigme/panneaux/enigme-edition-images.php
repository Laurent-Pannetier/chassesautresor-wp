<?php
defined('ABSPATH') || exit;

$enigme_id = $args['enigme_id'] ?? null;
if (!$enigme_id || get_post_type($enigme_id) !== 'enigme') return;
?>

<div id="panneau-images-enigme" class="panneau-lateral-liens panneau-lateral-large" aria-hidden="true" role="dialog">
  <div class="panneau-lateral__contenu">
    <header class="panneau-lateral__header">
      <h2><?php echo esc_html__( 'Modifier les images de l’énigme', 'chassesautresor-com' ); ?></h2>
      <button type="button" class="panneau-fermer" aria-label="<?php echo esc_attr__( 'Fermer le panneau', 'chassesautresor-com' ); ?>">✖</button>
    </header>

    <?php
    $hide_label = static function (array $field): array {
        $field['wrapper']['class'] = trim(($field['wrapper']['class'] ?? '') . ' acf-hide-label');
        return $field;
    };
    add_filter('acf/prepare_field/name=enigme_visuel_image', $hide_label);

    $submit_button_html = '<div class="panneau-lateral__actions">'
        . '<button type="submit" class="bouton-enregistrer-description '
        . 'bouton-enregistrer-liens">%s</button>'
        . '</div>';

    acf_form([
        'post_id'            => $enigme_id,
        'fields'             => ['enigme_visuel_image'],
        'form'               => true,
        'submit_value'       => __('💾 Enregistrer', 'chassesautresor-com'),
        'html_submit_button' => $submit_button_html,
        'html_before_fields' => '<div class="champ-wrapper">',
        'html_after_fields'  => '</div>',
        // Après sauvegarde, on revient sur la même page en ouvrant
        // automatiquement le panneau principal grâce au paramètre
        // ?edition=open. L'ancre #images-enigme permet de scroller
        // directement sur le panneau images.
        'return'             => add_query_arg('edition', 'open', get_permalink()) . '#images-enigme',
        'updated_message'    => __('Images mises à jour.', 'chassesautresor-com'),
    ]);

    remove_filter('acf/prepare_field/name=enigme_visuel_image', $hide_label);
    ?>
  </div>
</div>
