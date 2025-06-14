<!-- 🎯 Panneau WYSIWYG ACF -->
<?php
defined( 'ABSPATH' ) || exit;
$organisateur_id = $args['organisateur_id'] ?? null;

?>

<div id="panneau-description" class="panneau-lateral-liens panneau-lateral-large" aria-hidden="true">
    <div class="panneau-lateral__contenu">

        <div class="panneau-lateral__header">
            <button type="button" class="panneau-fermer" aria-label="Fermer le panneau">✖</button>
            <h2>Modifier votre présentation</h2>
            
        </div>
        <?php
        acf_form([
            'post_id'             => $organisateur_id,
            'fields'              => ['description_longue'],
            'form'                => true,
            'submit_value'        => '💾 Enregistrer',
            'html_submit_button'  => '<div class="panneau-lateral__actions"><button type="submit" class="bouton-enregistrer-description bouton-enregistrer-liens">%s</button></div>',
            'html_before_fields'  => '<div class="champ-wrapper">',
            'html_after_fields'   => '</div>',
            'return'              => get_permalink() . '#presentation', // ✅ ajout de l’ancre
            'updated_message'     => false
        ]);
        ?>
    </div>
</div>