<?php
/**
 * Trophies obtained by the current player and active team.
 *
 * @package chassesautresor
 */

defined('ABSPATH') || exit;

if (!is_user_logged_in()) {
    $login = wp_login_url(home_url('/trophees/'));
    echo '<p class="trophies-page__empty">';
    echo esc_html__('Connectez-vous pour consulter vos trophées.', 'chassesautresor-com');
    echo ' <a href="' . esc_url($login) . '">' . esc_html__('Connexion', 'chassesautresor-com') . '</a>';
    echo '</p>';
    return;
}

if (
    !function_exists('cat_user_trophies')
    || !class_exists(ChassesAuTresor\Core\Trophies\TrophyPageRenderer::class)
) {
    echo '<p class="trophies-page__empty">';
    echo esc_html__('Les trophées ne sont pas disponibles pour le moment.', 'chassesautresor-com');
    echo '</p>';
    return;
}

echo (new ChassesAuTresor\Core\Trophies\TrophyPageRenderer())->render(
    cat_user_trophies(get_current_user_id())
); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
