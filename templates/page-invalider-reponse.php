<?php
/**
 * Template Name: Invalider Réponse
 */
defined('ABSPATH') || exit;

$uid = sanitize_text_field($_GET['uid'] ?? '');
if (!$uid) wp_die('UID manquant.');

global $wpdb;
$table = $wpdb->prefix . 'enigme_tentatives';

$tentative = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM $table WHERE tentative_uid = %s",
    $uid
));
if (!$tentative) wp_die('Tentative introuvable.');

// Mettre à jour la tentative
$wpdb->update($table, ['resultat' => 'faux'], ['tentative_uid' => $uid]);

$user_id = (int) $tentative->user_id;
$enigme_id = (int) $tentative->enigme_id;

// Mettre à jour le statut utilisateur (⚠️ ne pas rétrograder si déjà résolue)
$statuts_table = $wpdb->prefix . 'enigme_statuts_utilisateur';
$statut_existant = $wpdb->get_var($wpdb->prepare(
    "SELECT statut FROM $statuts_table WHERE user_id = %d AND enigme_id = %d",
    $user_id, $enigme_id
));
if ($statut_existant !== 'resolue') {
    if ($statut_existant) {
        $wpdb->update($statuts_table, ['statut' => 'abandonnee'], ['user_id' => $user_id, 'enigme_id' => $enigme_id]);
    } else {
        $wpdb->insert($statuts_table, ['user_id' => $user_id, 'enigme_id' => $enigme_id, 'statut' => 'abandonnee']);
    }
}

// Envoyer le mail de notification au joueur
if (function_exists('envoyer_mail_notification_joueur')) {
    envoyer_mail_notification_joueur($user_id, $enigme_id, 'faux');
}

// Rediriger vers la page de confirmation
wp_redirect(add_query_arg([
    'uid'      => $uid,
    'resultat' => 'faux',
], home_url('/traitement-reponse')));
exit;
