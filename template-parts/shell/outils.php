<?php
/**
 * Tools moved off the home pages for organizers and administrators.
 *
 * @package chassesautresor
 */

defined('ABSPATH') || exit;

$is_admin = function_exists('cta_shell_user_is_administrator') && cta_shell_user_is_administrator();
$hunt_id = function_exists('cat_get_managed_hunt_id_for_user')
    ? (int) cat_get_managed_hunt_id_for_user()
    : 0;
$can_reset = $is_admin || (
    function_exists('cat_is_demo_mode')
    && cat_is_demo_mode()
    && function_exists('is_user_logged_in')
    && is_user_logged_in()
);
$can_seed = function_exists('cat_user_can_seed_statistics') && cat_user_can_seed_statistics($hunt_id);
$fixture_count = $can_seed && function_exists('cat_count_statistics_fixtures')
    ? cat_count_statistics_fixtures()
    : 0;
$fixture_total = class_exists(ChassesAuTresor\Core\Admin\StatisticsFixtureService::class)
    ? ChassesAuTresor\Core\Admin\StatisticsFixtureService::PLAYER_COUNT
    : 48;
$hunt_title = $hunt_id > 0 && function_exists('get_the_title') ? (string) get_the_title($hunt_id) : '';

$protection = '';
if ($is_admin && class_exists(ChassesAuTresor\Core\Messages\AccountToolsRenderer::class)) {
    $tools_renderer = new ChassesAuTresor\Core\Messages\AccountToolsRenderer();
    $protection = $tools_renderer->renderProtectionCard();
}
?>
<div class="myaccount-layout">
    <div class="myaccount-main">
        <div class="dashboard-grid">
            <?php
            if (function_exists('cat_render_hunt_lifecycle_switch')) {
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo cat_render_hunt_lifecycle_switch($hunt_id);
            }
            if (function_exists('cta_shell_render_hunt_edit_card')) {
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo cta_shell_render_hunt_edit_card($hunt_id);
            }
            if (function_exists('cta_shell_render_organizer_edit_card')) {
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo cta_shell_render_organizer_edit_card();
            }
            if ($can_reset) :
                ?>
                <div class="dashboard-card">
                    <div class="dashboard-card-header">
                        <i class="fas fa-undo" aria-hidden="true"></i>
                        <h3><?php esc_html_e('Reset stats', 'chassesautresor-com'); ?></h3>
                    </div>
                    <div class="dashboard-card-content">
                        <button type="button" class="btn-danger" data-reset-stats>
                            <?php esc_html_e('Effacer', 'chassesautresor-com'); ?>
                        </button>
                    </div>
                </div>
            <?php endif; ?>
            <?php if ($can_seed) : ?>
                <div class="dashboard-card" data-stats-fixtures>
                    <div class="dashboard-card-header">
                        <i class="fas fa-users" aria-hidden="true"></i>
                        <h3><?php esc_html_e('Joueurs de test', 'chassesautresor-com'); ?></h3>
                    </div>
                    <div class="dashboard-card-content">
                        <p>
                            <?php
                            if ($hunt_title !== '') {
                                echo esc_html(sprintf(
                                    /* translators: 1: player count, 2: hunt title. */
                                    __(
                                        'Crée %1$d comptes fictifs et remplit les statistiques de « %2$s ».',
                                        'chassesautresor-com'
                                    ),
                                    $fixture_total,
                                    $hunt_title
                                ));
                            } else {
                                echo esc_html(sprintf(
                                    /* translators: %d: player count. */
                                    __(
                                        'Crée %d comptes fictifs et remplit les statistiques de cette chasse.',
                                        'chassesautresor-com'
                                    ),
                                    $fixture_total
                                ));
                            }
                            ?>
                        </p>
                        <p>
                            <?php esc_html_e(
                                'Chaque énigme, étape et indice reçoit des joueurs.',
                                'chassesautresor-com'
                            ); ?>
                        </p>
                        <p>
                            <?php esc_html_e(
                                'Les dates couvrent le jour, la semaine, le mois et le total.',
                                'chassesautresor-com'
                            ); ?>
                        </p>
                        <p>
                            <?php esc_html_e(
                                'Ces comptes ne peuvent pas se connecter.',
                                'chassesautresor-com'
                            ); ?>
                            <?php esc_html_e(
                                'Ils disparaissent quand le mode démo est désactivé.',
                                'chassesautresor-com'
                            ); ?>
                        </p>
                        <p>
                            <?php
                            echo esc_html(sprintf(
                                /* translators: %d: current fixture player count. */
                                _n(
                                    '%d joueur de test présent.',
                                    '%d joueurs de test présents.',
                                    $fixture_count,
                                    'chassesautresor-com'
                                ),
                                $fixture_count
                            ));
                            ?>
                        </p>
                        <button type="button" class="bouton-primaire" data-stats-fixtures-action="seed">
                            <?php esc_html_e('Générer', 'chassesautresor-com'); ?>
                        </button>
                        <button type="button" class="bouton-secondaire" data-stats-fixtures-action="purge">
                            <?php esc_html_e('Retirer', 'chassesautresor-com'); ?>
                        </button>
                    </div>
                </div>
            <?php endif; ?>
            <?php if ($protection !== '') : ?>
                <?php echo $protection; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php endif; ?>
            <?php
            if ($is_admin) {
                myaccount_render_dashboard_placeholder(
                    __('Points', 'chassesautresor-com'),
                    __(
                        'Inactif pour le moment. Réactivable plus tard via Expérience du site.',
                        'chassesautresor-com'
                    )
                );
                myaccount_render_dashboard_placeholder(
                    __('Taux de conversion', 'chassesautresor-com'),
                    __('Inactif pour le moment.', 'chassesautresor-com')
                );
                myaccount_render_dashboard_placeholder(
                    __('ACF', 'chassesautresor-com'),
                    __('Inactif pour le moment.', 'chassesautresor-com')
                );
            }
            ?>
        </div>
    </div>
</div>
