<?php
defined( 'ABSPATH' ) || exit;

// Vérifier si l'utilisateur est admin
if (!current_user_can('administrator')) {
    wp_redirect(home_url('/mon-compte/'));
    exit;
}
// Récupération des infos utilisateur
$current_user = wp_get_current_user();
$logout_url = wc_get_account_endpoint_url('customer-logout'); // Lien déconnexion
$current_user_id = get_current_user_id();
$commandes_output = afficher_commandes_utilisateur($current_user_id, 3);
$taux_conversion = get_taux_conversion_actuel();


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
            <li>
            <a class=" active" href="<?php echo esc_url(wc_get_account_endpoint_url('dashboard')); ?>"><i class="fas fa-home"></i> <span>Tableau de bord</span></a>
            </li>
    
            <li>
                <a href="<?php echo esc_url('/mon-compte/organisateurs/'); ?>"><i class="fas fa-users"></i> <span>Organisateurs</span></a>
            </li>
    
            <li>
                <a href="<?php echo esc_url('/mon-compte/statistiques/'); ?>"><i class="fas fa-chart-line"></i> <span>Statistiques</span></a>
            </li>
    
            <li>
                <a href="<?php echo esc_url('/mon-compte/outils/'); ?>"><i class="fas fa-wrench"></i> <span>Outils</span></a>
            </li>
        
            <!-- Mon Compte (inchangé) -->
            <li class="menu-deroulant">
                <a href="#" id="menu-account-toggle"><i class="fas fa-user-circle"></i> <span>Mon compte</span> <span class="dropdown-indicator">▼</span></a>
                <ul class="submenu">
                    <li><a href="<?php echo esc_url(wc_get_account_endpoint_url('orders')); ?>">Mes Commandes</a></li>
                    <li><a href="<?php echo esc_url(wc_get_account_endpoint_url('edit-address')); ?>">Mes Adresses</a></li>
                    <li><a href="<?php echo esc_url(wc_get_account_endpoint_url('edit-account')); ?>">Paramètres</a></li>
                    <li><a href="<?php echo esc_url(wc_logout_url()); ?>">Déconnexion</a></li>
                </ul>
            </li>
        </ul>
    </nav>

    <!-- 📌 Contenu Principal -->
    <div class="dashboard-content">
        <h2>📌 Gestion des Paiements</h2>
        <?php afficher_tableau_paiements_admin(); ?>
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
        <?php if (current_user_can('administrator')) : ?>
            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <i class="fas fa-coins"></i>
                    <h3>Gestion Points</h3>
                </div>
                <div class="stats-content">
                    <form method="POST" class="form-gestion-points">
                        <?php wp_nonce_field('gestion_points_action', 'gestion_points_nonce'); ?>
                    
                        <!-- 🔹 Ligne 1 : Sélection utilisateur + action -->
                        <div class="gestion-points-ligne">
                            <label for="utilisateur-points"></label>
                            <input type="text" id="utilisateur-points" name="utilisateur" placeholder="Rechercher un utilisateur..." required>
                    
                            <label for="type-modification"></label>
                            <select id="type-modification" name="type_modification" required>
                                <option value="ajouter">➕</option>
                                <option value="retirer">➖</option>
                            </select>
                        </div>
                    
                        <!-- 🔹 Ligne 2 : Nombre de points + bouton -->
                        <div class="gestion-points-ligne">
                            <label for="nombre-points"</label>
                            <input type="number" id="nombre-points" name="nombre_points" placeholder="nb de points"min="1" required>
                    
                            <button type="submit" name="modifier_points" class="bouton-secondaire">✅</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($commandes_output)) : ?>
            <a href="<?php echo esc_url(wc_get_account_endpoint_url('orders')); ?>" class="dashboard-card">
                <div class="dashboard-card-header">
                    <i class="fas fa-shopping-cart"></i>
                    <h3>Mes Commandes</h3>
                </div>
                <div class="stats-content">
                    <?php echo $commandes_output; ?>
                </div>
            </a>
        <?php endif; ?>
        <div class="dashboard-card">
            <div class="dashboard-card-header">
                <i class="fas fa-euro-sign"></i>
                <h3>Taux Conversion</h3>
            </div>
            <div class="stats-content">
                <p>1 000 points = <strong><?php echo esc_html($taux_conversion); ?> €</strong>
                    <span class="conversion-info">
                        <i class="fas fa-info-circle" id="open-taux-modal"></i>
                    </span>
                </p>
        
                <?php if (current_user_can('administrator')) : ?>
                    <!-- Overlay qui obscurcit la tuile -->
                    <div class="overlay-taux">
                        <button class="bouton-secondaire" id="modifier-taux">Modifier</button>
                    </div>
        
                    <!-- Formulaire caché par défaut -->
                    <form method="POST" class="form-taux-conversion" id="form-taux-conversion" style="display: none;">
                        <?php wp_nonce_field('modifier_taux_conversion_action', 'modifier_taux_conversion_nonce'); ?>
                        <label for="nouveau-taux">Définir un nouveau taux :</label>
                        <input type="number" name="nouveau_taux" id="nouveau-taux" step="0.01" min="0" value="<?php echo esc_attr($taux_conversion); ?>" required>
                        <button type="submit" name="enregistrer_taux" class="bouton-secondaire">Mettre à jour</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php
if (is_page('mon-compte') && current_user_can('administrator')) {
    echo '<script>console.log("✅ gestion-points.js chargé !");</script>';
}
?>

</div>
<?php get_template_part('template-parts/modals/modal-conversion-historique'); ?>
