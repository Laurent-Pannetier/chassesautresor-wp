<?php
defined( 'ABSPATH' ) || exit;

// ==================================================
// 📚 SOMMAIRE DU FICHIER
// ==================================================
//
// 1. 📦 TEMPLATES UTILISATEURS
//    - Routage personnalisé de /mon-compte/ vers des templates dédiés
//
// 2. 📦 MODIFICATION AVATAR EN FRONT
//    - Gestion complète de l’upload, affichage et remplacement de l’avatar utilisateur
//
// 3. 📦 TUILES UTILISATEUR
//    - Affichage dynamique des éléments liés à l’utilisateur (commandes WooCommerce)
//
// 4. 📦 ATTRIBUTION DE RÔLE
//     - Attribution des rôles oragnisateurs (création)
//


// ==================================================
// 📦 TEMPLATES UTILISATEURS
// ==================================================
/**
 * 🔹 ajouter_rewrite_rules → Déclarer les règles de réécriture pour les URL personnalisées de l’espace "Mon Compte".
 * 🔹 ajouter_query_vars → Déclarer les variables de requête personnalisées associées aux URL de l’espace utilisateur.
 * 🔹 charger_template_utilisateur → Charger dynamiquement un template spécifique selon l’URL dans "Mon Compte".
 * 🔹 modifier_titre_onglet → Modifier dynamiquement le titre de la page dans l'onglet du navigateur.
 * 🔹 is_woocommerce_account_page → Vérifier si la page actuelle est une sous-page WooCommerce dans "Mon Compte".
 */


/**
 * Charge dynamiquement un template spécifique en fonction de l'URL sous /mon-compte/.
 *
 * Cette fonction intercepte l'affichage des templates WordPress et remplace le fichier de template 
 * si l'URL demandée correspond à une page spécifique de l'espace utilisateur (ex: organisateurs, administrateurs).
 *
 * @param string $template Le chemin du template par défaut déterminé par WordPress.
 * @return string Le chemin du fichier de template personnalisé ou le template par défaut.
 */
 function ajouter_rewrite_rules() {
    add_rewrite_rule('^mon-compte/organisateurs/inscription/?$', 'index.php?mon_compte_organisateurs_inscription=1', 'top');
    add_rewrite_rule('^mon-compte/organisateurs/paiements/?$', 'index.php?mon_compte_organisateurs_paiements=1', 'top');
    add_rewrite_rule('^mon-compte/statistiques/?$', 'index.php?mon_compte_statistiques=1', 'top');
    add_rewrite_rule('^mon-compte/outils/?$', 'index.php?mon_compte_outils=1', 'top');
}
add_action('init', 'ajouter_rewrite_rules');

/**
 * ➕ Déclare les variables de requête personnalisées associées aux URL de l’espace utilisateur.
 *
 * Ces variables sont nécessaires pour que WordPress reconnaisse les URL réécrites
 * comme valides et les transmette aux hooks `template_include`.
 *
 * @param array $vars Tableau des variables de requête connues.
 * @return array Tableau enrichi avec les nouvelles variables personnalisées.
 *
 * @hook query_vars
 */
function ajouter_query_vars($vars) {
    $vars[] = 'mon_compte_organisateurs_inscription';
    $vars[] = 'mon_compte_organisateurs_paiements';
    $vars[] = 'mon_compte_statistiques';
    $vars[] = 'mon_compte_outils';
    return $vars;
}
add_filter('query_vars', 'ajouter_query_vars');

/**
 * 📦 Charge dynamiquement un template spécifique pour certaines URL de l'espace utilisateur.
 *
 * Cette fonction intercepte l’inclusion du template principal WordPress (`template_include`)
 * et remplace le fichier de template par défaut si l’URL correspond à l’une des URL personnalisées
 * définies via les règles de réécriture.
 *
 * - Ignore les endpoints WooCommerce pour éviter les conflits.
 * - Vérifie l’existence des fichiers de template personnalisés dans `/templates/admin/`.
 * - Active un logging (`error_log`) utile au débogage.
 *
 * @param string $template Le chemin du template par défaut déterminé par WordPress.
 * @return string Le chemin du fichier de template personnalisé ou le template par défaut.
 *
 * @hook template_include
 */
function charger_template_utilisateur($template) {
    // Récupération et nettoyage de l'URL demandée
    $request_uri = trim($_SERVER['REQUEST_URI'], '/');

    // Vérification pour éviter les conflits avec WooCommerce
    if (is_wc_endpoint_url()) {
        return $template;
    }
    
    // Liste des chemins d’URL associés à leurs fichiers de template respectifs
    $mapping_templates = array(
        'mon-compte/organisateurs'        => 'organisateurs.php',
        'mon-compte/organisateurs/'       => 'organisateurs.php', // Variante avec /
        'mon-compte/statistiques'         => 'statistiques.php',
        'mon-compte/outils'               => 'outils.php',
    );

    // Vérification si l'URL correspond à un template spécifique
    if (array_key_exists($request_uri, $mapping_templates)) {
        $custom_template = get_stylesheet_directory() . '/templates/admin/' . $mapping_templates[$request_uri];
        
        if (!file_exists($custom_template)) {
            error_log('Fichier de template introuvable : ' . $custom_template);
        }
        error_log('Chargement du template : ' . $custom_template);

        // Vérification de l'existence du fichier avant de le retourner
        if (file_exists($custom_template)) {
            return $custom_template;
        }
    }
    // Retourne le template par défaut si aucune correspondance n'est trouvée
    return $template;
}
add_filter('template_include', 'charger_template_utilisateur');

/**
 * Modifier dynamiquement le titre de la page dans l'onglet du navigateur
 *
 * @param string $title Le titre actuel.
 * @return string Le titre modifié.
 */
function modifier_titre_onglet($title) {
    global $wp;
    $current_url = trim($wp->request, '/');

    // Définition des titres pour chaque page
    $page_titles = [
        'mon-compte/statistiques'              => 'Statistiques - Chasses au Trésor',
        'mon-compte/outils'                    => 'Outils - Chasses au Trésor',
        'mon-compte/organisateurs'             => 'Organisateur - Chasses au Trésor',
    ];

    // Si l’URL correspond à une page définie, modifier le titre
    if (isset($page_titles[$current_url])) {
        return $page_titles[$current_url];
    }

    return $title; // Conserver le titre par défaut si l'URL ne correspond pas
}
add_filter('pre_get_document_title', 'modifier_titre_onglet');

/**
 * Vérifie si la page actuelle est une page WooCommerce spécifique dans "Mon Compte".
 *
 * Cette fonction analyse l'URL actuelle et détermine si elle correspond à l'une
 * des pages WooCommerce spécifiques où le contenu du compte WooCommerce doit être affiché.
 *
 * Liste des pages WooCommerce prises en compte :

 *
 * @return bool True si la page actuelle est une page WooCommerce autorisée, False sinon.
 */
function is_woocommerce_account_page() {
    // Récupérer l'URL actuelle
    $current_url = $_SERVER['REQUEST_URI'];

    // Liste des pages WooCommerce où afficher woocommerce_account_content()
    $pages_woocommerce = [
        '/mon-compte/commandes/',
        '/mon-compte/voir-commandes/',
        '/mon-compte/modifier-adresse/',
        '/mon-compte/modifier-compte/',
        '/mon-compte/telechargements/',
        '/mon-compte/moyens-paiement/',
        '/mon-compte/lost-password/',
        '/mon-compte/customer-logout/'
    ];

    // Vérifier si l'URL actuelle correspond à une page WooCommerce autorisée
    foreach ($pages_woocommerce as $page) {
        if (strpos($current_url, $page) === 0) {
            return true;
        }
    }

    return false;
}


// ==================================================
// 📦 MODIFICATION AVATAR EN FRONT
// ==================================================
/**
 * 🔹 upload_user_avatar → Traiter l’upload d’un avatar utilisateur via AJAX.
 * 🔹 autoriser_avatars_upload → Autoriser les formats d’image pour l’upload d’avatars personnalisés.
 * 🔹 remplacer_avatar_utilisateur → Remplacer l’avatar par défaut par celui défini par l’utilisateur.
 * 🔹 charger_script_avatar_upload → Charger le script JS d’upload uniquement sur les pages commençant par "/mon-compte/".
 */


/**
 * 🖼️ Traiter l'upload d'un avatar utilisateur via AJAX.
 *
 * Cette fonction est appelée via l'action AJAX `wp_ajax_upload_user_avatar` côté authentifié.
 * Elle permet à un utilisateur connecté de téléverser une image personnalisée en tant qu'avatar.
 *
 * Étapes :
 * - Vérifie que l’utilisateur est connecté.
 * - Valide la présence, le format (JPG, PNG, GIF, WEBP) et la taille du fichier (2 Mo max).
 * - Gère le téléversement du fichier via `wp_handle_upload()`.
 * - Enregistre l’URL dans la meta `user_avatar`.
 * - Retourne une réponse JSON avec l’URL du nouvel avatar.
 *
 * 🔐 Sécurité :
 * - Seuls les utilisateurs connectés peuvent utiliser ce point d’entrée.
 * - La validation du type MIME et de la taille empêche les uploads malveillants.
 *
 * 💡 Remarque :
 * - Cette fonction n'utilise pas `media_handle_upload()` mais `wp_handle_upload()` directement,
 *   ce qui est plus léger mais ne crée pas de pièce jointe dans la médiathèque.
 *
 * @return void Réponse JSON (succès ou erreur) via `wp_send_json_*`.
 *
 * @hook wp_ajax_upload_user_avatar
 */
add_action('wp_ajax_upload_user_avatar', 'upload_user_avatar');
function upload_user_avatar() {
    // 🛑 Vérifie si l'utilisateur est connecté
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Vous devez être connecté.']);
    }

    $user_id = get_current_user_id();

    // 📌 Vérifie si un fichier a été envoyé
    if (!isset($_FILES['avatar'])) {
        wp_send_json_error(['message' => 'Aucun fichier reçu.']);
    }

    $file = $_FILES['avatar'];
    $max_size = 2 * 1024 * 1024; // 🔹 2 Mo
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    // 🛑 Vérifie la taille du fichier
    if ($file['size'] > $max_size) {
        wp_send_json_error(['message' => 'Taille dépassée : 2 Mo max.']);
    }

    // 🛑 Vérifie le type MIME du fichier
    if (!in_array($file['type'], $allowed_types)) {
        wp_send_json_error(['message' => 'Format non autorisé. Formats autorisés : JPG, PNG, GIF, WEBP.']);
    }

    // 📌 Déplacer et enregistrer l’image
    require_once ABSPATH . 'wp-admin/includes/file.php';
    $upload = wp_handle_upload($file, ['test_form' => false]);

    // ✅ Vérification de `$upload` avant d'aller plus loin
    if (!$upload || isset($upload['error'])) {
        wp_send_json_error(['message' => 'Erreur lors du téléversement : ' . ($upload['error'] ?? 'Inconnue')]);
    }

    // 📌 Mettre à jour l’avatar dans la base de données
    update_user_meta($user_id, 'user_avatar', $upload['url']);

    // 📌 Répondre en JSON avec l'URL de l'image
    $avatar_url = get_user_meta($user_id, 'user_avatar', true);
    wp_send_json_success([
        'message' => 'Image mise à jour avec succès.',
        'new_avatar_url' => esc_url($avatar_url)
    ]);
}


/**
 * 📌 Autorise les formats d'image pour l'upload des avatars.
 *
 * @param array $mimes Liste des types MIME autorisés.
 * @return array Liste mise à jour avec les formats acceptés.
 */
function autoriser_avatars_upload($mimes) {
    $mimes['jpg'] = 'image/jpeg';
    $mimes['jpeg'] = 'image/jpeg';
    $mimes['png'] = 'image/png';
    $mimes['gif'] = 'image/gif';
    $mimes['webp'] = 'image/webp'; // ✅ Ajout du format WebP
    return $mimes;
}
add_filter('upload_mimes', 'autoriser_avatars_upload');

/**
 * 📌 Remplace l'avatar par défaut par l'avatar personnalisé de l'utilisateur.
 *
 * @param string $avatar Code HTML de l'avatar par défaut.
 * @param mixed $id_or_email Identifiant ou email de l'utilisateur.
 * @param int $size Taille de l'avatar.
 * @param string $default Avatar par défaut si aucun avatar personnalisé n'est trouvé.
 * @param string $alt Texte alternatif de l'avatar.
 * @return string HTML de l'avatar personnalisé ou avatar par défaut.
 */
function remplacer_avatar_utilisateur($avatar, $id_or_email, $size, $default, $alt) {
    $user_id = 0;

    // 🔹 Vérifie si l'entrée est un ID, un objet ou un email
    if (is_numeric($id_or_email)) {
        $user_id = $id_or_email;
    } elseif (is_object($id_or_email) && isset($id_or_email->user_id)) {
        $user_id = $id_or_email->user_id;
    } elseif (is_string($id_or_email)) {
        $user = get_user_by('email', $id_or_email);
        if ($user) {
            $user_id = $user->ID;
        }
    }

    // 📌 Vérifie si l'utilisateur a un avatar enregistré en base
    $avatar_url = get_user_meta($user_id, 'user_avatar', true);

    if (!empty($avatar_url)) {
        return "<img src='" . esc_url($avatar_url) . "' alt='" . esc_attr($alt) . "' width='{$size}' height='{$size}' class='avatar avatar-{$size} photo' />";
    }

    return $avatar;
}
add_filter('get_avatar', 'remplacer_avatar_utilisateur', 10, 5);

/**
 * 📌 Charge le fichier JavaScript uniquement sur les pages commençant par "/mon-compte/"
 */
function charger_script_avatar_upload() {
    if (strpos($_SERVER['REQUEST_URI'], '/mon-compte/') === 0) {
        wp_enqueue_script(
            'avatar-upload',
            get_stylesheet_directory_uri() . '/assets/js/avatar-upload.js',
            [],
            null,
            true
        );
    }
}
add_action('wp_enqueue_scripts', 'charger_script_avatar_upload');



// ==================================================
// 📦 TUILES UTILISATEUR
// ==================================================
/**
 * 🔹 afficher_commandes_utilisateur → Récupérer et afficher les 4 dernières commandes d’un utilisateur WooCommerce sous forme de tableau.
 */


/**
 * Récupère et affiche les 3 dernières commandes d'un utilisateur WooCommerce sous forme de tableau.
 *
 * @param int $user_id ID de l'utilisateur connecté.
 * @param int $limit Nombre de commandes à afficher (par défaut : 4).
 * @return string HTML du tableau des commandes ou une chaîne vide si aucune commande.
 */
function afficher_commandes_utilisateur($user_id, $limit = 4) {
    if (!$user_id || !class_exists('WooCommerce')) {
        return '';
    }

    $customer_orders = wc_get_orders([
        'limit'    => $limit,
        'customer' => $user_id,
        'status'   => ['wc-completed'], // Commandes valides
        'orderby'  => 'date',
        'order'    => 'DESC'
    ]);
    
    if (empty($customer_orders)) {
        return ''; // Ne rien afficher si aucune commande
    }

    ob_start(); // Capture l'affichage HTML
    ?>
    <table class="stats-table">
        <tbody>
            <?php foreach ($customer_orders as $order) : ?>
                <?php
                $order_id = $order->get_id();
                $order_date = wc_format_datetime($order->get_date_created(), 'd/m/Y');
                $items = $order->get_items();
                $first_item = reset($items); // Récupère le premier produit de la commande
                $product_name = $first_item ? $first_item->get_name() : 'Produit inconnu';
                ?>
                <tr>
                    <td>#<?php echo esc_html($order_id); ?></td>
                    <td><?php echo esc_html($product_name); ?></td>
                    <td><?php echo esc_html($order_date); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
    return ob_get_clean(); // Retourne le HTML capturé
}



// ==================================================
// 📦 ATTRIBUTION DE RÔLE
// ==================================================
/**
 * 🔹 ajouter_role_organisateur_creation() → Ajoute le rôle "organisateur_creation" à un abonné après la création CONFIRMÉE d'un CPT "organisateur"
 */

/**
 * 📌 Ajoute le rôle "organisateur_creation" à un abonné après la création CONFIRMÉE d'un CPT "organisateur".
 *
 * - Vérifie que l'utilisateur est "subscriber" avant de modifier son rôle.
 * - Vérifie que le post n'est pas en mode "auto-draft" (création en cours).
 * - Ne touche AUCUN autre rôle (admin, organisateur...).
 *
 * @param int      $post_id ID du post enregistré.
 * @param WP_Post  $post    Objet du post.
 * @param bool     $update  Indique si le post est mis à jour ou nouvellement créé.
 * @return void
 */
function ajouter_role_organisateur_creation($post_id, $post, $update) {
    // 🔹 Vérifie que le post est bien un CPT "organisateur"
    if ($post->post_type !== 'organisateur') {
        return;
    }

    // 🔹 Vérifie si le post est un "auto-draft" (pas encore enregistré par l'utilisateur)
    if ($post->post_status === 'auto-draft') {
        return;
    }

    $user_id = get_current_user_id();
    $user = new WP_User($user_id);

    // 🔹 Vérifie si l'utilisateur est "subscriber" avant de lui attribuer "organisateur_creation"
    if (in_array('subscriber', $user->roles, true)) {
        $user->add_role('organisateur_creation'); // ✅ Ajoute le rôle sans retirer "subscriber"
        error_log("✅ L'utilisateur $user_id a maintenant aussi le rôle 'organisateur_creation'.");
    }
}
add_action('save_post', 'ajouter_role_organisateur_creation', 10, 3);

