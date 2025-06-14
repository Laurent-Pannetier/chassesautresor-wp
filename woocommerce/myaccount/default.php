<?php
defined( 'ABSPATH' ) || exit;

// Récupération des infos utilisateur
$current_user = wp_get_current_user();
$logout_url = wc_get_account_endpoint_url('customer-logout'); // Lien déconnexion
?>

<!-- 📌 Conteneur Profil + Points -->
<div class="dashboard-container">
   <!-- 📌 Conteneur Profil + Points -->
    <div class="dashboard-profile-wrapper">
        <!-- 📌 Profil Utilisateur -->
        <div class="dashboard-profile">
            <div class="profile-avatar-container">
                <div class="profile-avatar">
                    <?php echo get_avatar($current_user->ID, 80); ?>
                </div>
            
                <!-- 📌 Bouton pour ouvrir le téléversement -->
                <label for="upload-avatar" class="upload-avatar-btn">Modifier</label>
                <input type="file" id="upload-avatar" class="upload-avatar-input" accept="image/*" style="display: none;">
                
                <!-- 📌 Conteneur des messages d’upload -->
                <div class="message-upload-avatar">
                    <div class="message-size-file-avatar">❗ Taille maximum : 2 Mo</div>
                    <div class="message-format-file-avatar">📌 Formats autorisés : JPG, PNG, GIF</div>
                </div>
            </div>
            <div class="profile-info">
                <h2><?php echo esc_html($current_user->display_name); ?></h2>
                <p><?php echo esc_html($current_user->user_email); ?></p>
            </div>
        </div>
    </div>
        
    <!-- 📌 Barre de navigation Desktop -->
   <nav class="dashboard-nav">
        <ul>
           <li class="<?php echo is_account_page() && !is_wc_endpoint_url() ? 'active' : ''; ?>">
                <a href="<?php echo esc_url(wc_get_account_endpoint_url('dashboard')); ?>">
                    <i class="fas fa-home"></i> <span>Accueil</span>
                </a>
            </li>
            <li class="<?php echo is_wc_endpoint_url('orders') ? 'active' : ''; ?>">
                <a href="<?php echo esc_url(wc_get_account_endpoint_url('orders')); ?>">
                    <i class="fas fa-box"></i> <span>Commandes</span>
                </a>
            </li>
            <li class="<?php echo is_wc_endpoint_url('edit-address') ? 'active' : ''; ?>">
                <a href="<?php echo esc_url(wc_get_account_endpoint_url('edit-address')); ?>">
                    <i class="fas fa-map-marker-alt"></i> <span>Adresses</span>
                </a>
            </li>
            <li class="<?php echo is_wc_endpoint_url('edit-account') ? 'active' : ''; ?>">
                <a href="<?php echo esc_url(wc_get_account_endpoint_url('edit-account')); ?>">
                    <i class="fas fa-cog"></i> <span>Paramètres</span>
                </a>
            </li>
            <li>
                <a href="<?php echo esc_url(wc_logout_url()); ?>">
                    <i class="fas fa-sign-out-alt"></i> <span>Déconnexion</span>
                </a>
            </li>
        </ul>
    </nav>

    <!-- 📌 Contenu Principal -->
    <div class="dashboard-content">
        <div class="woocommerce-account-content">
            <?php if (is_woocommerce_account_page()) {
                woocommerce_account_content();
            } ?>
        </div>
    </div>

    <!-- 📌 Tuiles en Bas (Accès Rapides) -->
    <div class="dashboard-grid">
        <a href="<?php echo esc_url(wc_get_account_endpoint_url('orders')); ?>" class="dashboard-card">
            <span class="icon">📦</span>
            <h3>Organisateurs</h3>
        </a>
        <a href="<?php echo esc_url(wc_get_account_endpoint_url('users')); ?>" class="dashboard-card">
            <span class="icon">👤</span>
            <h3>Statistiques</h3>
        </a>
        <a href="<?php echo esc_url(wc_get_account_endpoint_url('site-settings')); ?>" class="dashboard-card">
            <span class="icon">⚙️</span>
            <h3>Outils</h3>
        </a>
    </div>

</div>
