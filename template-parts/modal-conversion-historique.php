<?php
defined('ABSPATH') || exit;
$taux_conversion = get_taux_conversion_actuel();
$historique_taux = get_option('historique_taux_conversion', []);
?>

<div id="conversion-modal" class="points-modal">
    <div class="points-modal-content">
        <span class="close-modal">&times;</span>
        <h2>💰 Taux de conversion</h2>
        <p>1 000 points = <?php echo esc_html($taux_conversion); ?> €</p>

        <h3>📜 Historique des taux</h3>
        <ul>
            <?php if (!empty($historique_taux)) : ?>
                <?php
                $historique_taux = get_option('historique_taux_conversion', []);
                
                if (!is_array($historique_taux)) {
                    error_log("❌ `historique_taux_conversion` n'est pas un tableau. Valeur actuelle : " . print_r($historique_taux, true));
                    $historique_taux = []; // Éviter l'erreur en remplaçant par un tableau vide
                }
                ?>
                <?php foreach (array_reverse($historique_taux) as $taux) : ?>
                    <li><strong><?php echo esc_html($taux['date_taux_conversion']); ?></strong> : <?php echo esc_html($taux['valeur_taux_conversion']); ?> €</li>
                <?php endforeach; ?>
            <?php else : ?>
                <li>Aucun historique disponible.</li>
            <?php endif; ?>
        </ul>
    </div>
</div>
