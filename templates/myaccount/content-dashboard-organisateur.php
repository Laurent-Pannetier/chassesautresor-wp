<?php
/**
 * Dashboard content for organizer roles.
 *
 * @package chassesautresor
 */

defined('ABSPATH') || exit;

if (function_exists('charger_script_conversion')) {
    charger_script_conversion(true);
}

$current_user = wp_get_current_user();
$user_id      = $current_user->ID;

$orders_output = afficher_commandes_utilisateur($user_id, 3);

if (function_exists('woocommerce_account_content')) {
    woocommerce_account_content();
}

if (isset($_GET['paiement_envoye']) && $_GET['paiement_envoye'] === '1') {
    echo '<div class="notice notice-success">' .
        esc_html__('Votre demande de conversion a bien été envoyée.', 'chassesautresor-com') .
        '</div>';
}


$args = array(
    'orders_output' => $orders_output,
);

get_template_part('template-parts/myaccount/dashboard-organisateur', null, $args);

get_template_part('template-parts/modals/modal-conversion');
