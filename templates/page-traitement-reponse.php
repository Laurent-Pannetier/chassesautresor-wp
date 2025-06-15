<?php
/**
 * Template Name: Traitement Réponse (Confirmation + Statistiques)
 */

defined('ABSPATH') || exit;

$uid = sanitize_text_field($_GET['uid'] ?? '');
$resultat = sanitize_text_field($_GET['resultat'] ?? '');

if (!$uid || !in_array($resultat, ['bon', 'faux'], true)) {
    wp_die('Paramètres manquants ou invalides.');
}

global $wpdb;
$table = $wpdb->prefix . 'enigme_tentatives';

$tentative = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE tentative_uid = %s", $uid));
if (!$tentative) {
    wp_die('Tentative introuvable.');
}

// ✅ Définir les variables immédiatement
$user_id = (int) $tentative->user_id;
$enigme_id = (int) $tentative->enigme_id;

// 🔐 Sécurité : vérifier que l'utilisateur connecté est bien organisateur associé
$current_user_id = get_current_user_id();
$chasse_raw       = get_field('enigme_chasse_associee', $enigme_id, false);
if (is_array($chasse_raw)) {
    $first      = reset($chasse_raw);
    $chasse_id  = is_object($first) ? (int) $first->ID : (int) $first;
} elseif (is_object($chasse_raw)) {
    $chasse_id  = (int) $chasse_raw->ID;
} else {
    $chasse_id  = (int) $chasse_raw;
}
$organisateur_id    = $chasse_id ? get_organisateur_from_chasse($chasse_id) : null;
$organisateur_user_ids = $organisateur_id ? get_field('utilisateurs_associes', $organisateur_id) : [];

if (
    !current_user_can('manage_options') &&
    (!is_array($organisateur_user_ids) || !in_array($current_user_id, $organisateur_user_ids))
) {
    wp_die('Accès interdit : vous ne pouvez pas traiter cette tentative.');
}


// ✅ Mettre à jour le statut utilisateur si nécessaire
$statuts_table = $wpdb->prefix . 'enigme_statuts_utilisateur';

// Déterminer le nouveau statut à appliquer
$new_statut = ($resultat === 'bon') ? 'resolue' : 'abandonnee';

// Vérifie si un statut existe déjà pour ce user/enigme
$exists = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM $statuts_table WHERE user_id = %d AND enigme_id = %d",
    $user_id, $enigme_id
));

if ($exists) {
    $statut_actuel = $wpdb->get_var($wpdb->prepare(
        "SELECT statut FROM $statuts_table WHERE user_id = %d AND enigme_id = %d",
        $user_id, $enigme_id
    ));

    if ($statut_actuel !== 'resolue') {
        $wpdb->update(
            $statuts_table,
            ['statut' => $new_statut],
            ['user_id' => $user_id, 'enigme_id' => $enigme_id]
        );
    }
} else {
    $wpdb->insert(
        $statuts_table,
        ['user_id' => $user_id, 'enigme_id' => $enigme_id, 'statut' => $new_statut]
    );
}

$total_user = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE user_id = %d AND enigme_id = %d", $user_id, $enigme_id));
$total_enigme = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE enigme_id = %d", $enigme_id));

$chasse_raw = get_field('enigme_chasse_associee', $enigme_id, false);
if (is_array($chasse_raw)) {
    $first     = reset($chasse_raw);
    $chasse_id = is_object($first) ? (int) $first->ID : (int) $first;
} elseif (is_object($chasse_raw)) {
    $chasse_id = (int) $chasse_raw->ID;
} else {
    $chasse_id = (int) $chasse_raw;
}
$total_chasse = 0;

if ($chasse_id) {
    $ids_enigmes = get_posts([
        'post_type' => 'enigme',
        'fields' => 'ids',
        'posts_per_page' => -1,
        'meta_query' => [
            [
                'key' => 'enigme_chasse_associee',
                'value' => $chasse_id,
                'compare' => '=',
            ]
        ]
    ]);

    if ($ids_enigmes) {
        $in_clause = implode(',', array_map('absint', $ids_enigmes));
        $total_chasse = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE enigme_id IN ($in_clause)");
    }
}

$nom_user = get_userdata($user_id)?->display_name ?? "Utilisateur inconnu";
$titre_enigme = get_the_title($enigme_id);
$url_enigme = get_permalink($enigme_id) . '?statistiques=1';

envoyer_mail_notification_joueur($user_id, $enigme_id, $resultat);

?>

<div style="max-width:600px;margin:3em auto;text-align:center;font-family:sans-serif;">
  <img src="<?= esc_url(get_site_icon_url(96)); ?>" alt="Logo" style="margin-bottom:1em; width:48px; height:48px;">
  <p style="font-size:1.3em;">
    <?= $resultat === 'bon' ? '✅' : '❌'; ?> La réponse a bien été <strong><?= $resultat === 'bon' ? 'validée' : 'refusée'; ?></strong>.
  </p>

  <div style="margin-top:2em;font-size:1em;">
    <p>📌 Tentative <strong><?= $total_user; ?></strong> de <strong><?= esc_html($nom_user); ?></strong></p>
    <p>📊 Tentative <strong><?= $total_enigme; ?></strong> sur l’énigme <strong><?= esc_html($titre_enigme); ?></strong></p>
    <?php if ($total_chasse): ?>
      <p>🧩 Tentative <strong><?= $total_chasse; ?></strong> sur l’ensemble de la chasse</p>
    <?php endif; ?>
  </div>

  <div style="margin-top:3em;">
    <a href="#" onclick="window.close();" style="margin-right:1em;">❎ Fermer cette fenêtre</a>
    <a href="<?= esc_url($url_enigme); ?>" style="background:#0073aa;padding:10px 20px;border-radius:5px;color:white;text-decoration:none;">🔍 Voir cette énigme</a>
  </div>
</div>
