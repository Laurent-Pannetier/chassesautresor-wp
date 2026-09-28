<?php
defined('ABSPATH') || exit;
?>

<!-- 📌 Modal Points -->
<div id="points-modal" class="points-modal">
    <div class="points-modal-content">
        <span class="close-modal">&times;</span>
        <h2><?= esc_html__('À quoi servent les points ?', 'chassesautresor-com'); ?></h2>
        <p><?= esc_html__('Les points vous permettent de débloquer :', 'chassesautresor-com'); ?></p>
        <ul class="points-list">
            <li><?= esc_html__('🎯 Des chasses au trésor', 'chassesautresor-com'); ?></li>
            <li><?= esc_html__('❓ Des tentatives de réponse', 'chassesautresor-com'); ?></li>
            <li><?= esc_html__('💡 Des indices', 'chassesautresor-com'); ?></li>
        </ul>
        <p class="points-info-text">
            <?= esc_html__('La gratuité ou l\'accès par points est choisi librement par chaque organisateur.', 'chassesautresor-com'); ?>
        </p>
    </div>
</div>
