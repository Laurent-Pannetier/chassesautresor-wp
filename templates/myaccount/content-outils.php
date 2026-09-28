<?php
/**
 * Dynamic content for the "Outils" section.
 *
 * @package chassesautresor
 */

defined('ABSPATH') || exit;

if (!current_user_can('administrator')) {
    wp_redirect(home_url('/mon-compte/'));
    exit;
}

$taux_conversion = get_taux_conversion_actuel();
?>
<section>
    <h1 class="mb-4 text-xl font-semibold"><?php esc_html_e('Outils', 'chassesautresor-com'); ?></h1>
    <div class="dashboard-grid">
        <div class="dashboard-card">
            <div class="dashboard-card-header">
                <i class="fas fa-coins"></i>
                <h3><?php esc_html_e('Gestion Points', 'chassesautresor-com'); ?></h3>
            </div>
            <div class="stats-content">
                <form method="POST" class="form-gestion-points">
                    <?php wp_nonce_field('gestion_points_action', 'gestion_points_nonce'); ?>
                    <div class="gestion-points-ligne">
                        <label for="utilisateur-points"></label>
                        <input
                            type="text"
                            id="utilisateur-points"
                            placeholder="<?php esc_attr_e('Rechercher un utilisateur...', 'chassesautresor-com'); ?>"
                            required
                        >
                        <input type="hidden" id="utilisateur-id" name="utilisateur">
                        <label for="type-modification"></label>
                        <select id="type-modification" name="type_modification" required>
                            <option value="ajouter">➕</option>
                            <option value="retirer">➖</option>
                        </select>
                    </div>
                    <div class="gestion-points-ligne">
                        <label for="nombre-points"></label>
                        <input
                            type="number"
                            id="nombre-points"
                            name="nombre_points"
                            placeholder="<?php esc_attr_e('Nombre de points', 'chassesautresor-com'); ?>"
                            min="1"
                            required
                        >
                        <button type="submit" name="modifier_points" class="btn-icon bouton-tertiaire">✅</button>
                    </div>
                </form>
            </div>
        </div>
        <?php $protection_active = get_option('ca_site_password_enabled', '1') === '1'; ?>
        <div class="dashboard-card">
            <div class="dashboard-card-header">
                <i class="fas fa-lock"></i>
                <h3><?php esc_html_e('Protection globale', 'chassesautresor-com'); ?></h3>
            </div>
            <div class="stats-content">
                <label class="switch-control">
                    <input type="checkbox" id="site-protection-toggle" <?php checked($protection_active); ?>>
                    <span class="switch-slider"></span>
                </label>
                <span id="site-protection-status">
                    <?php echo $protection_active ? esc_html__('Activé', 'chassesautresor-com') : esc_html__('Désactivé', 'chassesautresor-com'); ?>
                </span>
            </div>
        </div>
        <div class="dashboard-card">
            <div class="dashboard-card-header">
                <i class="fas fa-euro-sign"></i>
                <h3><?php esc_html_e('Taux Conversion', 'chassesautresor-com'); ?></h3>
            </div>
            <div class="stats-content">
                <p>1 000 points = <strong><?php echo esc_html($taux_conversion); ?> €</strong>
                    <span class="conversion-info">
                        <i class="fas fa-info-circle" id="open-taux-modal"></i>
                    </span>
                </p>
                <?php if (current_user_can('administrator')) : ?>
                    <div class="overlay-taux">
                        <button class="bouton-secondaire" id="modifier-taux"><?php esc_html_e('Modifier', 'chassesautresor-com'); ?></button>
                    </div>
                    <form method="POST" class="form-taux-conversion" id="form-taux-conversion" style="display: none;">
                        <?php wp_nonce_field('modifier_taux_conversion_action', 'modifier_taux_conversion_nonce'); ?>
                        <label for="nouveau-taux"><?php esc_html_e('Définir un nouveau taux :', 'chassesautresor-com'); ?></label>
                        <input type="number" name="nouveau_taux" id="nouveau-taux" step="0.01" min="0" value="<?php echo esc_attr($taux_conversion); ?>" required>
                        <button type="submit" name="enregistrer_taux" class="bouton-secondaire"><?php esc_html_e('Mettre à jour', 'chassesautresor-com'); ?></button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="dashboard-card">
            <i class="fas fa-tools"></i>
            <h3><?php esc_html_e('ACF', 'chassesautresor-com'); ?></h3>
            <p class="stat-value">
                <button id="afficher-champs-acf" class="bouton-tertiaire"><?php esc_html_e('Afficher les champs ACF', 'chassesautresor-com'); ?></button>
            </p>
            <div class="stats-content">
                <div id="acf-fields-container" style="display:none;margin-top:10px;">
                    <textarea id="acf-fields-output" style="width:100%;height:300px;" readonly></textarea>
                </div>
            </div>
        </div>

        <div class="dashboard-card">
            <div class="dashboard-card-header">
                <i class="fas fa-undo"></i>
                <h3><?php esc_html_e('Reset stats', 'chassesautresor-com'); ?></h3>
            </div>
            <div class="stats-content">
                <button id="reset-stats-btn" class="btn-danger">
                    <?php esc_html_e('Effacer', 'chassesautresor-com'); ?>
                </button>
            </div>
        </div>
    </div>
</section>
