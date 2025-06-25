<?php
defined( 'ABSPATH' ) || exit;
get_header(); // ✅ Ajoute l'en-tête du site
// Vérifier si l'utilisateur est admin
if (!current_user_can('administrator')) {
    wp_redirect(home_url('/mon-compte/'));
    exit;
}

// Récupération des infos utilisateur
$current_user = wp_get_current_user();
$logout_url = wc_get_account_endpoint_url('customer-logout'); // Lien déconnexion


// Récupérer la liste des organisateurs en attente de validation
$organisateurs_liste = recuperer_organisateurs_pending();

?>
<div id="primary" class="content-area primary ">
    
    <main id="main" class="site-main">
        <header class="entry-header">
            <h1 class="entry-title" itemprop="headline">Organisateurs</h1>
        </header>
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
                <!-- 📌 Affichage des points -->
                <div class="user-points">
                    <?php echo afficher_points_utilisateur_callback(); ?>
                </div>
            </div>
                
            <!-- 📌 Barre de navigation Desktop -->
           <nav class="dashboard-nav">
                <ul>
                    <li >
                    <a href="<?php echo esc_url(wc_get_account_endpoint_url('dashboard')); ?>"><i class="fas fa-home"></i> <span>Tableau de bord</span></a>
                    </li>
            
                    <li>
                        <a class=" active" href="<?php echo esc_url('/mon-compte/organisateurs/'); ?>"><i class="fas fa-users"></i> <span>Organisateurs</span></a>
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
                <div class="woocommerce-account-content">
                    <?php 
                    
                    // Vérifier s'il y a des résultats avant d'afficher le tableau
if (!empty($organisateurs_liste)) :
?>
    <h3>Organisateurs en attente</h3>
    <span><?php echo count($organisateurs_liste); ?> résultat(s) trouvé(s)</span>
    <table class="table-organisateurs">
        <thead>
            <tr>
                <th>Organisateur</th>
                <th>Chasse</th>
                <th>Utilisateur</th>
                <th>Créé le</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($organisateurs_liste as $entry) : ?>
                <tr class="<?php echo $entry['validation'] === 'en_attente' ? 'champ-attention' : ''; ?>">
                    <td class="<?php echo $entry['organisateur_complet'] ? 'carte-complete' : 'carte-incomplete'; ?>">
                        <a href="<?php echo esc_url($entry['organisateur_permalink']); ?>" target="_blank">
                            <?php echo esc_html($entry['organisateur_titre']); ?>
                        </a>
                    </td>
                    <td>
                        <?php if ($entry['chasse_id']) : ?>
                            <?php
                                $titre_chasse = $entry['chasse_titre'];
                                if ($entry['nb_enigmes']) {
                                    $titre_chasse .= ' (' . intval($entry['nb_enigmes']) . ')';
                                }
                            ?>
                            <a class="<?php echo $entry['chasse_complet'] ? 'carte-complete' : 'carte-incomplete'; ?>" href="<?php echo esc_url($entry['chasse_permalink']); ?>" target="_blank">
                                <?php echo esc_html($titre_chasse); ?>
                            </a>
                        <?php else : ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($entry['user_id']) : ?>
                            <a href="<?php echo esc_url($entry['user_link']); ?>" target="_blank">
                                <?php echo esc_html($entry['user_name']); ?>
                            </a>
                        <?php else : ?>-
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html(date_i18n('d/m/y', strtotime($entry['date_creation']))); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; // Fin de la condition ?>
   
                    
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
    </main>
</div>
<?php
get_footer(); // ✅ Ajoute le pied de page du site
?>