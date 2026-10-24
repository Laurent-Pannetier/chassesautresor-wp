<?php
/**
 * Template Name: Traitement Tentative (Confirmation explicite)
 *
 * Ancien lien de courriel. La file se consulte dans Outils, onglet Tentatives.
 * Une validation déjà postée ici est encore traitée, puis on redirige.
 *
 * @package chassesautresor
 */

defined('ABSPATH') || exit;

require_once get_stylesheet_directory() . '/inc/enigme-functions.php';

$uid = isset($_REQUEST['uid']) ? sanitize_text_field(wp_unslash((string) $_REQUEST['uid'])) : '';
if ($uid === '') {
    wp_die(esc_html__('Paramètre UID manquant.', 'chassesautresor-com'));
}

$tentative = get_tentative_by_uid($uid);
if (!$tentative) {
    wp_die(esc_html__('Tentative introuvable.', 'chassesautresor-com'));
}

$enigme_id = (int) $tentative->enigme_id;
$chasse_id = recuperer_id_chasse_associee($enigme_id);
$organisateur_id = get_organisateur_from_chasse($chasse_id);
$organisateur_user_ids = array_map('intval', (array) get_field('utilisateurs_associes', $organisateur_id));
$current_user_id = get_current_user_id();

if (
    !current_user_can('manage_options')
    && !in_array($current_user_id, $organisateur_user_ids, true)
) {
    wp_die(esc_html__('Accès refusé.', 'chassesautresor-com'));
}

if (
    isset($_SERVER['REQUEST_METHOD'])
    && $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action_traitement'], $_POST['uid'])
) {
    check_admin_referer('traiter_tentative_' . $uid);
    $action = sanitize_text_field(wp_unslash((string) $_POST['action_traitement']));
    $uid_post = sanitize_text_field(wp_unslash((string) $_POST['uid']));
    if ($uid_post === $uid && in_array($action, ['valider', 'invalider'], true)) {
        $resultat = $action === 'valider' ? 'bon' : 'faux';
        ChassesAuTresor\Core\Progress\ManualAttemptReviewHandler::process($uid, $resultat);
    }
}

$target = function_exists('cta_shell_edition_url')
    ? cta_shell_edition_url('enigme', $enigme_id, 'tentatives')
    : home_url('/outils/');
$target = add_query_arg('uid', $uid, $target);
wp_safe_redirect($target);
exit;
