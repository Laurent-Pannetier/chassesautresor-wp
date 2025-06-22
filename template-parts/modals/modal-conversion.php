<?php
defined('ABSPATH') || exit;

?>
<div id="conversion-modal" class="points-modal">
    <div class="points-modal-content">
        <span class="close-modal">&times;</span>
        <h2>💰 Taux de conversion</h2>
        <p>1 000 points = <?php echo esc_html(get_taux_conversion_actuel()); ?> €</p>
        <p>La conversion des points en € n'est possible qu'à partir de 500 points afin d'éviter les mico-paiements qui génèrent des frais fixes</p>
        <p>Ce taux est fixé par chassesautresor.com et peut être modifié : vous serez toujours prévenu préalablement avant toute éventuelle modification</p>
    </div>
</div>
