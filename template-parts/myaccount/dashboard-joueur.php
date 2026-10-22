<?php
/**
 * Player account home shell.
 *
 * @package chassesautresor
 */

defined('ABSPATH') || exit;

if (function_exists('cat_is_single_hunt_mode') && cat_is_single_hunt_mode()) {
    if (function_exists('cta_shell_render_hunt_banner')) {
        cta_shell_render_hunt_banner();
    }
    if (function_exists('cat_render_demo_player_progress_reset')) {
        cat_render_demo_player_progress_reset();
    }
    if (function_exists('cat_render_single_hunt_home_enigmas')) {
        cat_render_single_hunt_home_enigmas();
    }
    return;
} elseif (function_exists('ca_render_dashboard_engaged_hunts')) {
    ca_render_dashboard_engaged_hunts();
}

ob_start();
echo '<div class="dashboard-grid">';
myaccount_render_dashboard_placeholder(
    __('Progression de l’équipe', 'chassesautresor-com'),
    __('Bientôt disponible : le suivi de progression de votre équipe.', 'chassesautresor-com')
);
myaccount_render_dashboard_placeholder(
    __('Votre équipe', 'chassesautresor-com'),
    __(
        'Les informations d’équipe et le lien Discord arriveront ici. Les échanges se font hors site.',
        'chassesautresor-com'
    )
);
myaccount_render_dashboard_placeholder(
    __('Récompenses', 'chassesautresor-com'),
    __('Easter eggs et autres récompenses seront affichés ici plus tard.', 'chassesautresor-com')
);
echo '</div>';
$placeholders_html = ob_get_clean();

myaccount_render_dashboard_section(
    __('Équipe et récompenses', 'chassesautresor-com'),
    __('Ces blocs seront enrichis au fil des prochains lots.', 'chassesautresor-com'),
    $placeholders_html
);
