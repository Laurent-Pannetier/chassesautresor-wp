<?php
/**
 * Classement page: player ranking, or the team ranking.
 *
 * @package chassesautresor
 */

defined('ABSPATH') || exit;

$logged_in = function_exists('is_user_logged_in') && is_user_logged_in();
if (!$logged_in) {
    echo '<p class="app-placeholder__note">';
    esc_html_e('Connectez-vous pour consulter le classement.', 'chassesautresor-com');
    echo '</p>';
    return;
}

if (function_exists('cta_shell_render_player_ranking')) {
    cta_shell_render_player_ranking();
}
