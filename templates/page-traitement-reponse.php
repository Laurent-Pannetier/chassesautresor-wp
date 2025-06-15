<?php
/**
 * Template Name: Traitement Réponse (Finalisation Sécurisée)
 */

defined('ABSPATH') || exit;

if (!function_exists('get_field')) {
    require_once ABSPATH . 'wp-load.php';
}

$uid      = sanitize_text_field($_GET['uid'] ?? '');
$resultat = sanitize_text_field($_GET['resultat'] ?? '');

if (!$uid || !in_array($resultat, ['bon', 'faux'], true)) {
    wp_die('Paramètres manquants ou invalides.');
}

$tentative = enigme_recuperer_tentative_par_uid($uid);
if (!$tentative) {
    wp_die('Tentative introuvable.');
}

if (!enigme_utilisateur_autorise_tentative($tentative)) {
    wp_die('Accès interdit à cette tentative.');
}

// Réinitialisation manuelle des statuts utilisateur
if (is_user_logged_in() && isset($_GET['reset_tentatives'])) {
    global $wpdb;
    $reset_table = $wpdb->prefix . 'enigme_statuts_utilisateur';
    $enigme_id   = (int) $tentative->enigme_id;
    $reset_rows  = $wpdb->delete($reset_table, ['enigme_id' => $enigme_id], ['%d']);
    echo '<div style="text-align:center; background:#ffecec; color:#900; padding:1em; margin:2em auto; max-width:600px; border:1px solid #f00;">';
    echo '🧹 Réinitialisation : ' . esc_html($reset_rows) . ' ligne(s) supprimée(s) dans la table des statuts utilisateur.<br>';
    echo '<a href="' . esc_url(remove_query_arg('reset_tentatives')) . '" style="display:inline-block;margin-top:1em;">🔄 Revenir</a>';
    echo '</div>';
}

$donnees = enigme_mettre_a_jour_tentative($tentative, $resultat);
extract($donnees);
add_action('wp_head', function () {
    if (!has_site_icon()) {
        echo '<link rel="shortcut icon" href="' . esc_url(get_site_icon_url(32)) . '" type="image/x-icon">';
    }
});
?>
<?php if ($traitement_bloque) : ?>
<div style="max-width:600px;margin:3em auto;text-align:center;font-family:sans-serif;">
    <?php $logo = get_site_icon_url(96); ?>
    <a href="<?= esc_url(home_url()); ?>">
        <img src="<?= esc_url($logo); ?>" alt="Logo" style="width:48px;height:48px;margin-bottom:1em;">
    </a>
    <p>ℹ️ La tentative a déjà été traitée.</p>
    <p>Résultat actuel : <strong><?= esc_html($statut_actuel); ?></strong></p>
    <div style="margin-top:2em;">
        <a href="#" onclick="fermerFenetreOuRediriger(); return false;" style="margin-right:1em;">❎ Fermer cette fenêtre</a>
        <a href="<?= esc_url($permalink); ?>" style="background:#0073aa;padding:10px 20px;border-radius:5px;color:white;text-decoration:none;">🔍 Voir cette énigme</a>
    </div>
</div>
<?php else : ?>
<div style="max-width:600px;margin:3em auto;text-align:center;font-family:sans-serif;">
    <?php $logo = get_site_icon_url(96); ?>
    <a href="<?= esc_url(home_url()); ?>">
        <img src="<?= esc_url($logo); ?>" alt="Logo" style="width:48px;height:48px;margin-bottom:1em;">
    </a>
    <p>✅ La réponse a bien été <strong><?= $resultat === 'bon' ? 'validée' : 'refusée'; ?></strong>.</p>
    <div style="margin-top:2em;font-size:1em;">
        <p>📌 Tentative <strong><?= $total_user; ?></strong> de <strong><?= esc_html($nom_user); ?></strong></p>
        <p>📊 Tentative <strong><?= $total_enigme; ?></strong> sur cette énigme</p>
        <?php if ($total_chasse) : ?>
        <p>🧩 Tentative <strong><?= $total_chasse; ?></strong> sur la chasse</p>
        <?php endif; ?>
    </div>
    <div style="margin-top:3em;">
        <a href="#" onclick="fermerFenetreOuRediriger(); return false;" style="margin-right:1em;">❎ Fermer cette fenêtre</a>
        <a href="<?= esc_url($permalink); ?>" style="background:#0073aa;padding:10px 20px;border-radius:5px;color:white;text-decoration:none;">🔍 Voir cette énigme</a>
    </div>
</div>
<?php endif; ?>
<div style="text-align:center;margin-top:3em;">
    <a href="<?= esc_url(add_query_arg('reset_tentatives', '1')); ?>"
       onclick="return confirm('Confirmer la réinitialisation des statuts pour cette énigme ?');"
       style="background:#900;color:#fff;padding:10px 20px;border-radius:5px;text-decoration:none;">
        🧹 Réinitialiser les statuts pour cette énigme
    </a>
</div>
<script>
function fermerFenetreOuRediriger() {
    window.close();
    setTimeout(function() {
        if (!window.closed) {
            window.location.href = '/';
        }
    }, 500);
}
</script>
