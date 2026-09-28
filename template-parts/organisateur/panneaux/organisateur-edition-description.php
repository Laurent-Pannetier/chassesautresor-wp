<!-- 🎯 Panneau WYSIWYG ACF -->
<?php
defined('ABSPATH') || exit;
$organisateur_id = $args['organisateur_id'] ?? null;

?>

<div id="panneau-description" class="panneau-lateral-liens panneau-lateral-large" aria-hidden="true" role="dialog">
    <div class="panneau-lateral__contenu">

        <header class="panneau-lateral__header">
            <h2>Modifier votre présentation</h2>
            <button type="button" class="panneau-fermer" aria-label="Fermer le panneau">✖</button>

        </header>
        <?php
        acf_form([
            'post_id'             => $organisateur_id,
            'fields'              => ['description_longue'],
            'form'                => true,
            'submit_value'        => '💾 Enregistrer',
            'html_submit_button'  => '<div class="panneau-lateral__actions"><button type="submit" class="bouton-enregistrer-description bouton-enregistrer-liens">%s</button></div>',
            'html_before_fields'  => '<div class="champ-wrapper">',
            'html_after_fields'   => '</div>',
            // Après sauvegarde, on revient sur la même page en ouvrant
            // automatiquement le panneau principal grâce au paramètre
            // ?edition=open. L'ancre #presentation permet de scroller
            // directement sur la section description.
            'return' => add_query_arg(
                [
                    'edition' => 'open',
                    'panel'   => 'organisateur',
                ],
                remove_query_arg(['acf'], get_permalink())
            ) . '#presentation',

            'updated_message'     => false
        ]);
        ?>
    </div>
</div>