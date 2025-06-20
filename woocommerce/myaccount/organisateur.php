<?php
defined( 'ABSPATH' ) || exit;

// Récupération des infos utilisateur
$current_user = wp_get_current_user();
$logout_url = wc_get_account_endpoint_url('customer-logout'); // Lien déconnexion
$user_id = get_current_user_id();
$organisateur_id = get_organisateur_from_user($user_id);

// récupération CPT Organisateur & data
if ($organisateur_id) {
    $organisateur_titre = get_the_title($organisateur_id);
    $organisateur_logo = get_the_post_thumbnail_url($organisateur_id, 'medium'); // Récupérer l'image mise en avant
} else {
    $organisateur_titre = "Organisateur";
    $organisateur_logo = get_stylesheet_directory_uri() . "/assets/images/default-logo.png"; // Image par défaut si pas de logo
}

// compte le nb de chasses reliées au CPT Organisateur
$nombre_chasses = 0;
if ($organisateur_id) {
    $chasses = get_chasses_de_organisateur($organisateur_id);
    $nombre_chasses = $chasses->found_posts ?? 0;
}

// récupération stats du joueur
if ($user_id) {
    //ob_start(); // Capture l'affichage des stats joueur
    //afficher_stats_utilisateur($user_id);
    //$stats_output = ob_get_clean(); // Récupère le contenu affiché et le stocke
}

//* récupération des trophées
$trophees = get_user_meta($user_id, 'trophees_utilisateur', true);

// Vérifier si l'utilisateur a des trophées
if (!empty($trophees)) {
    ob_start(); // Capture le contenu des trophées
    echo afficher_trophees_utilisateur_callback(3);

    $trophees_output = ob_get_clean();
}
// récupération des commandes
$commandes_output = afficher_commandes_utilisateur($user_id, 3);

// controle d'accès au Convertisseur
$statut_conversion = verifier_acces_conversion($user_id);
$conversion_autorisee = ($statut_conversion === true);

// tableau demande de conversion en attente
ob_start(); // Commencer la capture de sortie
afficher_tableau_paiements_organisateur($user_id, 'en_attente');
$tableau_contenu = ob_get_clean(); // Récupérer la sortie et l'effacer du buffer

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
            
            <!-- Cartes Organisateur en Création / ne s'affiche que sur la page mon-compte-->
            <?php 
            if (
                in_array('organisateur_creation', $current_user->roles) &&  // Vérifie le rôle
                is_page(77) && // Vérifie que nous sommes bien sur la page de compte (ID 77)
                empty(WC()->query->get_current_endpoint()) // Vérifie qu'aucun endpoint WooCommerce n'est actif
            ) : ?>
                <?php get_template_part('template-parts/organisateur-partial-cards'); ?>
            <?php endif; ?>

    
            <!-- Demande paiement en attente -->
            <?php if (!empty(trim($tableau_contenu))) : ?>
                <h3>Demande de conversion en attente</h3>
                <?php echo $tableau_contenu; // Afficher le tableau seulement s'il y a du contenu ?>
            <?php endif; ?>
            
            <!-- Conenu Woocommerce par défaut -->
            <?php if (is_woocommerce_account_page()) {
                woocommerce_account_content();
            } ?>
        </div>
    </div>

    <!-- 📌 Tuiles en Bas (Accès Rapides) -->
    <div class="section-separator">
        <hr class="separator-line">
        <span class="separator-text">MON ESPACE</span>
        <hr class="separator-line">
    </div>
    <div class="dashboard-grid">
        <a href="<?php echo esc_url(get_permalink($organisateur_id)); ?>" class="dashboard-card">
            <div class="dashboard-card-header">
                <i class="fas fa-landmark"></i>
                <h3><?php echo esc_html($organisateur_titre); ?></h3>
            </div>
            <div class="image-container">
                <img src="<?php echo esc_url($organisateur_logo); ?>" alt="Logo <?php echo esc_attr($organisateur_titre); ?>" class="dashboard-logo">
                <div class="nb-chasses-overlay">
                    <?php echo sprintf(_n('%d chasse', '%d chasses', $nombre_chasses, 'text-domain'), $nombre_chasses); ?>
                </div>
            </div>
        </a>
        
        <div class="dashboard-card points-card">
            <div class="dashboard-card-header">
                <i class="fa-solid fa-money-bill-transfer"></i>
                <h3>Convertisseur</h3>
            </div>
            <div class="stats-content">
                <?php echo do_shortcode('[demande_paiement]'); ?>
        
                <?php if (!$conversion_autorisee) : ?>
                    <!-- Overlay bloquant -->
                    <div class="overlay-taux">
                        <p class="message-bloque"><?php echo wp_kses_post($statut_conversion); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="dashboard-card stats-card">
            <div class="dashboard-card-header">
                <i class="fas fa-chart-line"></i>
                <h3>Mes Stats</h3>
            </div>
            <div class="stats-content">
                <?php echo !empty($stats_output) ? $stats_output : "<p>Aucune statistique disponible.</p>"; ?>
            </div>
        </div>
        
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
        
        <?php if (!empty($trophees_output)) : ?>
            <a href="#" class="dashboard-card no-click">
                <div class="dashboard-card-header">
                    <i class="fas fa-trophy"></i>
                    <h3>Mes Trophées</h3>
                </div>
                <div class="trophees-content">
                    <?php echo $trophees_output; ?>
                </div>
            </a>
        <?php endif; ?>
    
        <a href="<?php echo esc_url('/mon-compte/outils/'); ?>" class="dashboard-card">
            <span class="icon">⚙️</span>
            <h3>Outils</h3>
        </a>
    </div>

</div>
