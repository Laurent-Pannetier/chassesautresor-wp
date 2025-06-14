jQuery(document).ready(function($) {
    // Cacher tous les textes masqués au chargement
    $('.hidden-text').hide();

    // Gérer l'événement du bouton
    $('.toggle-button').on('click', function(e) {
        e.preventDefault(); // Empêche la remontée de page

        var target = $(this).data('target');
        var span = $('#' + target);

        if (span.is(':visible')) {
            span.slideUp(300);
            $(this).text('En savoir plus');
        } else {
            span.slideDown(300);
            $(this).text('Réduire');
        }
    });
});
