<?php
/**
 * Trophies: a player shelf, or the staff wall.
 *
 * Players see the trophies they obtained. Organizers see the trophies linked
 * to their hunts. Administrators also see trophies that belong to no hunt.
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

$isPlayer = !function_exists('myaccount_user_is_player') || myaccount_user_is_player();
if ($isPlayer) {
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
    return;
}

if (
    !function_exists('cat_trophy_wall')
    || !class_exists(ChassesAuTresor\Core\Trophies\TrophyWallRenderer::class)
) {
    return;
}

if (
    function_exists('cta_shell_user_is_administrator')
    && cta_shell_user_is_administrator()
    && function_exists('cat_trophy_create_form')
) {
    echo cat_trophy_create_form(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

echo (new ChassesAuTresor\Core\Trophies\TrophyWallRenderer())->render(
    cat_trophy_wall((int) get_current_user_id())
); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
