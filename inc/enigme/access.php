<?php
/**
 * Access control and redirection logic for single enigme pages.
 *
 * @package chassesautresor
 */

defined('ABSPATH') || exit;

/**
 * Handles access checks and redirects for the single enigme template.
 *
 * This function centralizes all redirection logic so that the template
 * focuses solely on rendering once the user is allowed to view the page.
 *
 * @hook template_redirect
 * @return void
 */
function handle_single_enigme_access(): void
{
    if (!is_singular('enigme')) {
        return;
    }

    $enigme_id      = get_queried_object_id();
    $user_id        = get_current_user_id();
    $edition_active = utilisateur_peut_modifier_post($enigme_id);
    $chasse_id      = recuperer_id_chasse_associee($enigme_id);
    $est_engage_chasse = false;

    if ($chasse_id) {
        verifier_et_synchroniser_cache_enigmes_si_autorise($chasse_id);
        if ($user_id) {
            $est_engage_chasse = utilisateur_est_engage_dans_chasse($user_id, $chasse_id);
            if (
                !$est_engage_chasse
                && function_exists('ca_demo_is_demo_hunt')
                && ca_demo_is_demo_hunt($chasse_id)
            ) {
                $est_engage_chasse = true;
            }
        }
    }

    if (!is_user_logged_in()) {
        $url = $chasse_id ? get_permalink($chasse_id) : home_url('/');
        wp_redirect($url);
        exit;
    }

    if (
        $est_engage_chasse &&
        !utilisateur_est_engage_dans_enigme($user_id, $enigme_id) &&
        utilisateur_peut_engager_enigme($enigme_id, $user_id)
    ) {
        marquer_enigme_comme_engagee($user_id, $enigme_id);

        if (get_field('enigme_mode_validation', $enigme_id) === 'aucune') {
            verifier_fin_de_chasse($user_id, $enigme_id);
        }
    }

    if (!enigme_est_visible_pour($user_id, $enigme_id)) {
        $fallback_url = $chasse_id ? get_permalink($chasse_id) : home_url('/');
        wp_redirect($fallback_url);
        exit;
    }

    $etat_systeme    = get_field('enigme_cache_etat_systeme', $enigme_id) ?? 'accessible';
    $condition_acces = get_field('enigme_acces_condition', $enigme_id) ?? 'immediat';
    $chasse_terminee = ($chasse_id && get_field('chasse_cache_statut', $chasse_id) === 'termine');

    if (
        $condition_acces === 'pre_requis' &&
        $etat_systeme === 'bloquee_pre_requis' &&
        !enigme_pre_requis_remplis($enigme_id, $user_id) &&
        !utilisateur_peut_modifier_enigme($enigme_id)
    ) {
        $url = $chasse_id ? get_permalink($chasse_id) : home_url('/');
        wp_safe_redirect($url);
        exit;
    }

    if (
        $etat_systeme !== 'accessible' &&
        $etat_systeme !== 'bloquee_pre_requis' &&
        !($chasse_terminee && $etat_systeme === 'bloquee_date') &&
        !utilisateur_peut_modifier_enigme($enigme_id)
    ) {
        $url = $chasse_id ? get_permalink($chasse_id) : home_url('/');
        wp_safe_redirect($url);
        exit;
    }

    verifier_ou_mettre_a_jour_cache_complet($enigme_id);

    $enigme_complete = (bool) get_field('enigme_cache_complet', $enigme_id);

    if (
        $edition_active &&
        utilisateur_est_organisateur_associe_a_chasse($user_id, $chasse_id) &&
        !$enigme_complete &&
        !isset($_GET['edition'])
    ) {
        wp_redirect(add_query_arg('edition', 'open', get_permalink($enigme_id)));
        exit;
    }

    if (
        $edition_active &&
        utilisateur_est_organisateur_associe_a_chasse($user_id, $chasse_id) &&
        compter_tentatives_en_attente($enigme_id) > 0 &&
        !isset($_GET['edition'])
    ) {
        wp_redirect(
            add_query_arg(
                [
                    'edition' => 'open',
                    'tab'     => 'soumission',
                ],
                get_permalink($enigme_id)
            )
        );
        exit;
    }
}
add_action('template_redirect', 'handle_single_enigme_access');
