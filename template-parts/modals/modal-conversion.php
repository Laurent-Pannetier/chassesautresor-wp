<?php
defined('ABSPATH') || exit;

$user_id = (int) get_current_user_id();
$access = $args['access'] ?? (
    function_exists('verifier_acces_conversion') ? verifier_acces_conversion($user_id) : ''
);
$organizer_id = array_key_exists('organizer_id', $args ?? [])
    ? (int) $args['organizer_id']
    : (int) (function_exists('get_organisateur_from_user') ? get_organisateur_from_user($user_id) : 0);
$minimum = array_key_exists('minimum', $args ?? [])
    ? (int) $args['minimum']
    : (function_exists('get_points_conversion_min') ? get_points_conversion_min() : 500);
$balance = array_key_exists('balance', $args ?? [])
    ? (int) $args['balance']
    : (function_exists('get_user_points') ? get_user_points($user_id) : 0);
$rate = array_key_exists('rate', $args ?? [])
    ? (float) $args['rate']
    : (function_exists('get_taux_conversion_actuel') ? get_taux_conversion_actuel() : 0.0);

if (!class_exists(ChassesAuTresor\Core\Points\ConversionModalRenderer::class)) {
    return;
}
?>
<div id="conversion-modal" class="points-modal">
    <div class="points-modal-content">
        <?php
        echo (new ChassesAuTresor\Core\Points\ConversionModalRenderer())->render(
            $access,
            $organizer_id,
            $minimum,
            $balance,
            $rate
        );
        ?>
    </div>
</div>
