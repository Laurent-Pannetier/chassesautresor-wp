<?php
/**
 * Template Name: Valider Réponse
 */
defined('ABSPATH') || exit;

$uid = sanitize_text_field($_GET['uid'] ?? '');
if (!$uid) {
    wp_die('UID manquant.');
}

global $wpdb;
$table = $wpdb->prefix . 'enigme_tentatives';

// Vérifier que la tentative existe
$tentative = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE tentative_uid = %s", $uid));
if (!$tentative) {
    wp_die('Tentative introuvable.');
}

// Mettre à jour le résultat
$wpdb->update($table, ['resultat' => 'bon'], ['tentative_uid' => $uid]);

echo '<p style="font-size:1.2em;">✅ La réponse a été marquée comme <strong>VALIDÉE</strong>.</p>';
