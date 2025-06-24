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


// Récupérer tous les utilisateurs ayant le rôle "organisateur_creation"
$utilisateurs = get_users(['role' => ROLE_ORGANISATEUR_CREATION]);

$organisateurs_liste = []; // Stocker les résultats trouvés

foreach ($utilisateurs as $user) {
    // Récupérer l'ID du CPT "organisateur" associé à l'utilisateur
    $organisateur_id = get_organisateur_from_user($user->ID);
    if (!$organisateur_id) continue;

    // Récupérer les chasses associées à l'organisateur
    $chasses = get_chasses_de_organisateur($organisateur_id);

    // Vérifier si une chasse a `champs_caches_statut_validation` = "creation"
    $chasse_associee = null;
    while ($chasses->have_posts()) : $chasses->the_post();
        $statut_validation = get_field('champs_caches_statut_validation'); // Vérification du champ ACF
        if ($statut_validation === 'creation') {
            $chasse_associee = get_the_ID();
            break;
        }
    endwhile;
    wp_reset_postdata();

    if (!$chasse_associee) continue;

    // Récupérer le nombre d'énigmes associées
    $enigmes_associees = recuperer_enigmes_associees($chasse_associee);
    $total_enigmes = count($enigmes_associees);

    // Récupérer la date de dernière modification des CPT
    $organisateur_mod_date = get_the_modified_date('d/m/Y', $organisateur_id);
    $chasse_mod_date = get_the_modified_date('d/m/Y', $chasse_associee);

    // URL de prévisualisation du CPT (pour statut pending)
    $organisateur_preview_url = get_preview_post_link($organisateur_id);
    $chasse_preview_url = get_preview_post_link($chasse_associee);

    // Ajouter à la liste des résultats
    $organisateurs_liste[] = [
        'user_name' => $user->display_name,
        'organisateur_id' => $organisateur_id,
        'organisateur_titre' => get_the_title($organisateur_id),
        'organisateur_preview_url' => $organisateur_preview_url,
        'organisateur_mod_date' => $organisateur_mod_date,
        'chasse_id' => $chasse_associee,
        'chasse_titre' => get_the_title($chasse_associee),
        'chasse_preview_url' => $chasse_preview_url,
        'chasse_mod_date' => $chasse_mod_date,
        'total_enigmes' => $total_enigmes,
    ];
}

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
    <h3>Organisateurs : création en cours</h3>
    <span><?php echo count($organisateurs_liste); ?> résultat(s) trouvé(s)</span>
    <table class="table-organisateurs">
        <thead>
            <tr>
                <th>Utilisateur</th>
                <th>Organisateur</th>
                <th>Chasse</th>
                <th>Nb Énigmes</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($organisateurs_liste as $entry) : ?>
                <tr>
                    <td><?php echo esc_html($entry['user_name']); ?></td>
                    <td>
                        <a href="<?php echo esc_url($entry['organisateur_preview_url']); ?>" target="_blank">
                            <?php echo esc_html($entry['organisateur_titre']); ?>
                        </a>
                        <br><small>Dernière modif : <?php echo esc_html($entry['organisateur_mod_date']); ?></small>
                    </td>
                    <td>
                        <a href="<?php echo esc_url($entry['chasse_preview_url']); ?>" target="_blank">
                            <?php echo esc_html($entry['chasse_titre']); ?>
                        </a>
                        <br><small>Dernière modif : <?php echo esc_html($entry['chasse_mod_date']); ?></small>
                    </td>
                    <td><?php echo esc_html($entry['total_enigmes']); ?></td>
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