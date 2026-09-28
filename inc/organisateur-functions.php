<?php
defined( 'ABSPATH' ) || exit;

// ==================================================
// 📚 SOMMAIRE DU FICHIER : organisateur-functions.php
// ==================================================
//
//  📦 CHARGEMENT DES DONNEES
//  📦 GESTION DEMANDES DE CONVERSION
//  📩 FORMULAIRE DE CONTACT ORGANISATEUR (WPForms)
//


// ==================================================
// 📦 CHARGEMENT DES DONNEES
// ==================================================
/**
 * 🔹 enqueue_script_header_organisateur_ui
 */
 
/**
 * 🧭 Enfile le script UI de navigation pour le header organisateur.
 *
 * Ce script gère :
 * – l’affichage dynamique de la section #presentation
 * – l’activation du lien actif dans le menu nav
 *
 * Chargé uniquement sur les CPT `organisateur` et `chasse`, quel que soit l’utilisateur.
 *
 * @hook wp_enqueue_scripts
 * @return void
 */
function enqueue_script_header_organisateur_ui() {
    if (is_singular(['organisateur', 'chasse', 'enigme'])) {
        $path = '/assets/js/header-organisateur-ui.js';
        wp_enqueue_script(
            'header-organisateur-ui',
            get_stylesheet_directory_uri() . $path,
            [],
            filemtime(get_stylesheet_directory() . $path),
            true
        );
    }
}
add_action('wp_enqueue_scripts', 'enqueue_script_header_organisateur_ui');




// ==================================================
// 📦 GESTION DEMANDES DE CONVERSION
// ==================================================
/**
 * 🔹 charger_script_conversion → Charger le script `conversion.js` uniquement sur les pages liées à l’espace "Mon Compte".
 * 🔹 verifier_acces_conversion → Vérifier si un utilisateur peut soumettre une demande de conversion.
 * 🔹 afficher_tableau_paiements_organisateur → Afficher le tableau des demandes de paiement d’un organisateur.
 */

/**
 * 📦 Charger le script `conversion.js` uniquement sur les pages liées à l’espace "Mon Compte".
 *
 * Cette fonction enfile le script JavaScript `/assets/js/conversion.js` uniquement si l’utilisateur visite :
 * - une page native WooCommerce de type "Mon Compte" (`is_account_page()`)
 * - ou une page personnalisée définie manuellement sous `/mon-compte/*` (ex: `/mon-compte/outils`)
 *
 * 🔎 Le fichier est versionné dynamiquement via `filemtime()` pour éviter le cache.
 *
 * @hook wp_enqueue_scripts
 *
 * @param bool $force Forcer l'enfilement du script quel que soit l'URL courante.
 * @return void
 */
function charger_script_conversion(bool $force = false): void
{
    if (!$force) {
        // Inclure les pages WooCommerce natives
        if (is_account_page()) {
            $inclure = true;
        } else {
            // Inclure aussi les pages customisées que tu as créées sous /mon-compte/*
            $request_uri = trim($_SERVER['REQUEST_URI'], '/');

            $autorises = [
                'mon-compte/outils',
                'mon-compte/statistiques',
                'mon-compte/organisateurs',
            ];

            $inclure = in_array($request_uri, $autorises, true);
        }

        if (!$inclure) {
            return;
        }
    }

    $script_path = get_stylesheet_directory() . '/assets/js/conversion.js';
    $version     = file_exists($script_path) ? filemtime($script_path) : false;

    wp_enqueue_script(
        'conversion',
        get_stylesheet_directory_uri() . '/assets/js/conversion.js',
        [],
        $version,
        true
    );
}
add_action('wp_enqueue_scripts', 'charger_script_conversion');

/**
 * Vérifie si un utilisateur peut soumettre une demande de conversion.
 *
 * Cette fonction applique plusieurs contrôles d'accès avant qu'un utilisateur puisse demander une conversion :
 * 1. Vérifie que l'utilisateur possède bien le rôle "organisateur".
 * 2. Vérifie qu'il n'a pas déjà une demande en attente.
 * 3. Vérifie que sa dernière demande réglée date de plus de 30 jours.
 * 4. Vérifie qu'il dispose d'au moins 500 points.
 *
 * @param int $user_id L'ID de l'utilisateur à vérifier.
 * 
 * @return string|bool Retourne `true` si l'accès est autorisé, sinon un message d'erreur expliquant la raison du blocage.
 */
/**
 * Vérifie si un utilisateur peut soumettre une demande de conversion.
 *
 * @param int $user_id L'ID de l'utilisateur.
 * @return string|bool Retourne un message d'erreur si une condition bloque l'accès, sinon true.
 */
function verifier_acces_conversion($user_id) {
    // 1️⃣ Vérification du rôle (bloquant immédiat)
    $user = get_userdata($user_id);
    if (!$user || !in_array(ROLE_ORGANISATEUR, $user->roles)) {
        return __('Inscription en cours', 'chassesautresor');
    }

    // ✅ Récupération de l'ID du CPT "organisateur"
    $organisateur_id = get_organisateur_from_user($user_id);
    if (!$organisateur_id) {
        return __('Erreur : organisateur non trouvé.', 'chassesautresor');
    }

    // 2️⃣ Vérification des demandes via le registre des points
    global $wpdb;
    $repo      = new PointsRepository($wpdb);
    $paiements = $repo->getConversionRequests($user_id);

    foreach ($paiements as $paiement) {
        if ($paiement['request_status'] === 'pending') {
            return __('Demande déjà en cours', 'chassesautresor');
        }
    }

    // 3️⃣ Vérification du dernier règlement (> 30 jours)
    $dernier_paiement = null;
    foreach ($paiements as $paiement) {
        if ($paiement['request_status'] === 'paid') {
            $date_paiement = strtotime($paiement['settlement_date'] ?? $paiement['request_date']);
            if (!$dernier_paiement || $date_paiement > $dernier_paiement) {
                $dernier_paiement = $date_paiement;
            }
        }
    }

    if ($dernier_paiement && $dernier_paiement > strtotime('-30 days')) {
        $jours_restants = ceil(($dernier_paiement - strtotime('-30 days')) / 86400);
        return sprintf(__('Attendez encore %d jours', 'chassesautresor'), $jours_restants);
    }

    // 4️⃣ Vérification du solde de points (seuil minimal)
    $points_actuels = function_exists('get_user_points') ? get_user_points($user_id) : 0;
    $points_minimum = get_points_conversion_min();
    if ((int) $points_actuels < $points_minimum) {
        return 'INSUFFICIENT_POINTS';
    }

    // 5️⃣ Vérification IBAN/BIC
    $iban = get_field('iban', $organisateur_id);
    $bic  = get_field('bic', $organisateur_id);

    if (empty($iban) || empty($bic)) {
        $iban = get_field('gagnez_de_largent_iban', $organisateur_id);
        $bic  = get_field('gagnez_de_largent_bic', $organisateur_id);
    }

    if (empty($iban) || empty($bic)) {
        return 'MISSING_BANK_DETAILS';
    }

    return true; // ✅ Toutes les conditions sont remplies
}

/**
 * Génère le contenu HTML du modal de conversion en fonction des droits d'accès.
 */
function render_conversion_modal_content($access_message = null): string
{
    if ($access_message === null) {
        $access_message = verifier_acces_conversion(get_current_user_id());
    }
    $organisateur_id = get_organisateur_from_user(get_current_user_id());
    $points_minimum  = get_points_conversion_min();

    ob_start();

    if ($access_message === 'INSUFFICIENT_POINTS') {
        ?>
        <span class="close-modal">&times;</span>
        <div class="points-modal-message">
            <i class="fa-solid fa-circle-exclamation modal-icon" aria-hidden="true"></i>
            <h2>solde insuffisant</h2>
            <p>Conversion possible à partir de <?php echo esc_html($points_minimum); ?> points</p>
            <button type="button" class="close-modal">Fermer</button>
        </div>
        <?php
    } elseif ($access_message === 'MISSING_BANK_DETAILS') {
        ?>
        <span class="close-modal">&times;</span>
        <div class="points-modal-message">
            <i class="fa-solid fa-building-columns modal-icon" aria-hidden="true"></i>
            <h2><?php esc_html_e('Coordonnées bancaires manquantes', 'chassesautresor-com'); ?></h2>
            <p><?php esc_html_e("Nous avons besoin d'enregistrer vos coordonnées bancaires pour vous envoyer un versement", 'chassesautresor-com'); ?></p>
            <p>
                <a
                    id="ouvrir-coordonnees-modal"
                    class="champ-modifier"
                    href="#"
                    aria-label="<?php esc_attr_e('Ajouter des coordonnées bancaires', 'chassesautresor-com'); ?>"
                    data-champ="coordonnees_bancaires"
                    data-cpt="organisateur"
                    data-post-id="<?php echo esc_attr($organisateur_id); ?>"
                    data-label-add="<?php esc_attr_e('Ajouter', 'chassesautresor-com'); ?>"
                    data-label-edit="<?php esc_attr_e('Éditer', 'chassesautresor-com'); ?>"
                    data-aria-add="<?php esc_attr_e('Ajouter des coordonnées bancaires', 'chassesautresor-com'); ?>"
                    data-aria-edit="<?php esc_attr_e('Modifier les coordonnées bancaires', 'chassesautresor-com'); ?>"
                ><?php esc_html_e('renseigner coordonnées bancaires', 'chassesautresor-com'); ?></a>
            </p>
            <button type="button" class="close-modal">Fermer</button>
        </div>
        <?php
    } elseif (is_string($access_message) && $access_message !== '') {
        ?>
        <span class="close-modal">&times;</span>
        <p><?php echo esc_html($access_message); ?></p>
        <?php
    } else {
        ?>
        <?php
        $taux_conversion = get_taux_conversion_actuel();
        $user_points     = get_user_points();
        ?>
        <span class="close-modal">&times;</span>
        <span class="conversion-rate-badge">
            <?php printf(esc_html__('1 000 points = %s €', 'chassesautresor-com'), esc_html($taux_conversion)); ?>
        </span>
        <i class="fa-solid fa-right-left modal-top-icon" aria-hidden="true"></i>
        <h2 class="modal-title"><?php esc_html_e('Demande de conversion', 'chassesautresor-com'); ?></h2>
        <p class="modal-description">
            <?php printf(esc_html__('Transformez vos %d points en euros.', 'chassesautresor-com'), esc_html($user_points)); ?>
        </p>
        <form action="" method="POST">
            <div class="conversion-row">
                <label for="points-a-convertir"><?php esc_html_e('Convertir', 'chassesautresor-com'); ?></label>
                <input
                    type="number"
                    name="points_a_convertir"
                    id="points-a-convertir"
                    min="<?php echo esc_attr($points_minimum); ?>"
                    max="<?php echo esc_attr($user_points); ?>"
                    step="1"
                    value=""
                    data-taux="<?php echo esc_attr($taux_conversion); ?>"
                >
                <span class="points-unit"><?php esc_html_e('points', 'chassesautresor-com'); ?></span>
            </div>
            <p class="conversion-equivalent">
                <span class="label"><?php esc_html_e('contre valeur', 'chassesautresor-com'); ?></span>
                <span class="amount"><span id="montant-equivalent">0.00</span> €</span>
            </p>
            <input type="hidden" name="demander_paiement" value="1">
            <?php wp_nonce_field('demande_paiement_action', 'demande_paiement_nonce'); ?>
            <div class="modal-actions">
                <button type="submit" disabled><?php esc_html_e('Convertir', 'chassesautresor-com'); ?></button>
            </div>
        </form>
        <?php
    }

    return ob_get_clean();
}

/**
 * AJAX : renvoie le contenu du modal de conversion actualisé.
 */
function ajax_conversion_modal_content(): void
{
    $access_message = verifier_acces_conversion(get_current_user_id());
    $html           = render_conversion_modal_content($access_message);
    wp_send_json_success([
        'html'   => $html,
        'access' => $access_message === true,
    ]);
}
add_action('wp_ajax_conversion_modal_content', 'ajax_conversion_modal_content');

/**
 * Affiche le tableau des demandes de paiement d'un organisateur.
 *
 * @param int    $user_id       L'ID de l'utilisateur organisateur.
 * @param string $filtre_statut Filtre optionnel : 'en_attente' pour les demandes en cours, 'toutes' (par défaut) pour l'historique complet.
 */
function afficher_tableau_paiements_organisateur($user_id, $filtre_statut = 'toutes') {
    global $wpdb;
    $repo      = new PointsRepository($wpdb);
    $paiements = $repo->getConversionRequests($user_id);

    if (empty($paiements)) {
        return;
    }

    $paiements_filtres = [];
    foreach ($paiements as $paiement) {
        if ($filtre_statut === 'en_attente' && $paiement['request_status'] !== 'pending') {
            continue;
        }
        $paiements_filtres[] = $paiement;
    }

    if (empty($paiements_filtres)) {
        return;
    }

    echo '<table class="stats-table">';
    echo '<thead><tr>';
    echo '<th>' . esc_html__('Date demande', 'chassesautresor') . '</th>';
    echo '<th>' . esc_html__('Montant (€)', 'chassesautresor') . '</th>';
    echo '<th>' . esc_html__('Points utilisés', 'chassesautresor') . '</th>';
    echo '<th>' . esc_html__('Statut', 'chassesautresor') . '</th>';
    echo '</tr></thead>';
    echo '<tbody>';

    foreach ($paiements_filtres as $paiement) {
        switch ($paiement['request_status']) {
            case 'paid':
                $statut_affiche = '✅ ' . __('Réglé', 'chassesautresor');
                break;
            case 'cancelled':
                $statut_affiche = '❌ ' . __('Annulé', 'chassesautresor');
                break;
            case 'refused':
                $statut_affiche = '🚫 ' . __('Refusé', 'chassesautresor');
                break;
            default:
                $statut_affiche = '🟡 ' . __('En attente', 'chassesautresor');
        }
        $points_utilises = esc_html(abs((int) $paiement['points']));

        echo '<tr>';
        echo '<td>' . esc_html(date_i18n('d/m/Y à H:i', strtotime($paiement['request_date']))) . '</td>';
        echo '<td>' . esc_html($paiement['amount_eur']) . ' €</td>';
        echo '<td><span class="etiquette etiquette-grande">' . $points_utilises . '</span></td>';
        echo '<td><span class="etiquette">' . esc_html($statut_affiche) . '</span></td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
}

/**
 * Render conversion history table with AJAX pagination.
 */
function render_conversion_history(?int $user_id = null): string
{
    global $wpdb;
    $repo = new PointsRepository($wpdb);

    $per_page      = 10;
    $total         = $repo->countConversionRequests($user_id);
    if ($total === 0) {
        return '';
    }

    $requests      = $repo->getConversionRequests($user_id, null, $per_page);
    $paid_requests = $repo->getConversionRequests($user_id, 'paid');
    $pending_count = $repo->countConversionRequests($user_id, 'pending');

    $is_admin_view = $user_id === null;

    $total_points = 0;
    $total_eur    = 0.0;
    foreach ($paid_requests as $request) {
        $total_points += abs((int) $request['points']);
        $total_eur    += (float) $request['amount_eur'];
    }

    $total_points_label = sprintf(
        '%s : %s',
        esc_html__('Total points', 'chassesautresor-com'),
        number_format_i18n($total_points)
    );
    $total_eur_label = sprintf(
        '%s : %s €',
        esc_html__('Total €', 'chassesautresor-com'),
        number_format_i18n($total_eur, 2)
    );

    $total_pages = (int) ceil($total / $per_page);
    $expanded    = $pending_count > 0;

    enqueue_conversion_history_script();

    ob_start();
    ?>
    <div class="stats-table-wrapper conversion-history" data-per-page="<?php echo esc_attr($per_page); ?>">
        <h3><?php esc_html_e('Historique conversion de points', 'chassesautresor-com'); ?></h3>
        <div class="stats-table-summary">
            <span class="etiquette etiquette-grande"><?php echo esc_html($total_points_label); ?></span>
            <span class="etiquette etiquette-grande"><?php echo esc_html($total_eur_label); ?></span>
            <button
                type="button"
                class="etiquette etiquette-grande conversion-history-toggle"
                aria-expanded="<?php echo $expanded ? 'true' : 'false'; ?>"
                aria-label="<?php echo $expanded ? esc_attr__('Fermer tableau', 'chassesautresor-com') : esc_attr__('Voir le tableau', 'chassesautresor-com'); ?>"
                data-label-open="<?php esc_attr_e('Voir le tableau', 'chassesautresor-com'); ?>"
                data-label-close="<?php esc_attr_e('Fermer tableau', 'chassesautresor-com'); ?>"
            >
                <span class="conversion-history-toggle-text">
                    <?php echo $expanded ? esc_html__('Fermer tableau', 'chassesautresor-com') : esc_html__('Voir le tableau', 'chassesautresor-com'); ?>
                </span>
            </button>
        </div>
        <div class="conversion-history-table"<?php echo $expanded ? '' : ' style="display:none;"'; ?>>
            <table class="stats-table">
                <thead>
                <tr>
                    <th><?php esc_html_e('Date demande', 'chassesautresor-com'); ?></th>
                    <?php if ($is_admin_view) : ?>
                    <th><?php esc_html_e('Utilisateur', 'chassesautresor-com'); ?></th>
                    <?php endif; ?>
                    <th><?php esc_html_e('Montant (€)', 'chassesautresor-com'); ?></th>
                    <th><?php esc_html_e('Points utilisés', 'chassesautresor-com'); ?></th>
                    <th><?php esc_html_e('Statut', 'chassesautresor-com'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($requests as $paiement) :
                    switch ($paiement['request_status']) {
                        case 'paid':
                            $statut_affiche = '✅ ' . __('Réglé', 'chassesautresor-com');
                            break;
                        case 'cancelled':
                            $statut_affiche = '❌ ' . __('Annulé', 'chassesautresor-com');
                            break;
                        case 'refused':
                            $statut_affiche = '🚫 ' . __('Refusé', 'chassesautresor-com');
                            break;
                        default:
                            $statut_affiche = '🟡 ' . __('En attente', 'chassesautresor-com');
                    }
                    $montant_eur     = number_format_i18n((float) $paiement['amount_eur'], 2);
                    $points_utilises = number_format_i18n(abs((int) $paiement['points']));
                    $user_name       = '';
                    if ($is_admin_view) {
                        $user      = get_userdata((int) $paiement['user_id']);
                        $user_name = $user ? $user->display_name : sprintf(__('ID %d', 'chassesautresor-com'), (int) $paiement['user_id']);
                    }
                    ?>
                    <tr>
                        <td><?php echo esc_html(date_i18n('d/m/Y à H:i', strtotime($paiement['request_date']))); ?></td>
                        <?php if ($is_admin_view) : ?>
                        <td><?php echo esc_html($user_name); ?></td>
                        <?php endif; ?>
                        <td><?php echo esc_html($montant_eur); ?> €</td>
                        <td><span class="etiquette etiquette-grande"><?php echo esc_html($points_utilises); ?></span></td>
                        <td><span class="etiquette"><?php echo esc_html($statut_affiche); ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php if ($total_pages > 1) : ?>
            <?php echo cta_render_pager(1, $total_pages, 'points-history-pager'); ?>
            <span class="conversion-history-loading" aria-hidden="true"></span>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Enqueue script for conversion history table.
 */
function enqueue_conversion_history_script(): void
{
    $path = '/assets/js/conversion-history.js';
    wp_enqueue_script(
        'conversion-history',
        get_stylesheet_directory_uri() . $path,
        [],
        filemtime(get_stylesheet_directory() . $path),
        true
    );

    wp_localize_script(
        'conversion-history',
        'ConversionHistoryAjax',
        [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('conversion-history-nonce'),
        ]
    );
}

/**
 * Ensure conversion history script loads on pages where content may be injected via AJAX.
 */
function maybe_enqueue_conversion_history_script(): void
{
    if (is_account_page() || is_singular('organisateur')) {
        enqueue_conversion_history_script();
    }
}
add_action('wp_enqueue_scripts', 'maybe_enqueue_conversion_history_script');

/**
 * AJAX handler for loading paginated conversion history.
 */
function ajax_load_conversion_history(): void
{
    if (!is_user_logged_in()) {
        wp_send_json_error();
    }

    check_ajax_referer('conversion-history-nonce', 'nonce');

    $page     = isset($_POST['page']) ? (int) $_POST['page'] : 1;
    $page     = max(1, $page);
    $per_page = 10;
    $offset   = ($page - 1) * $per_page;
    $user_id  = current_user_can('administrator') ? null : get_current_user_id();
    $is_admin = $user_id === null;

    global $wpdb;
    $repo     = new PointsRepository($wpdb);
    $requests = $repo->getConversionRequests($user_id, null, $per_page, $offset);

    ob_start();
    foreach ($requests as $paiement) {
        switch ($paiement['request_status']) {
            case 'paid':
                $statut_affiche = '✅ ' . __('Réglé', 'chassesautresor-com');
                break;
            case 'cancelled':
                $statut_affiche = '❌ ' . __('Annulé', 'chassesautresor-com');
                break;
            case 'refused':
                $statut_affiche = '🚫 ' . __('Refusé', 'chassesautresor-com');
                break;
            default:
                $statut_affiche = '🟡 ' . __('En attente', 'chassesautresor-com');
        }
        $points_utilises = esc_html(abs((int) $paiement['points']));
        $user_name       = '';
        if ($is_admin) {
            $user      = get_userdata((int) $paiement['user_id']);
            $user_name = $user ? $user->display_name : sprintf(__('ID %d', 'chassesautresor-com'), (int) $paiement['user_id']);
        }
        ?>
        <tr>
            <td><?php echo esc_html(date_i18n('d/m/Y à H:i', strtotime($paiement['request_date']))); ?></td>
            <?php if ($is_admin) : ?>
            <td><?php echo esc_html($user_name); ?></td>
            <?php endif; ?>
            <td><?php echo esc_html($paiement['amount_eur']); ?> €</td>
            <td><span class="etiquette etiquette-grande"><?php echo $points_utilises; ?></span></td>
            <td><span class="etiquette"><?php echo esc_html($statut_affiche); ?></span></td>
        </tr>
        <?php
    }
    $rows = ob_get_clean();

    wp_send_json_success(['rows' => $rows]);
}
add_action('wp_ajax_load_conversion_history', 'ajax_load_conversion_history');


// ==================================================
// 📩 FORMULAIRE DE CONTACT ORGANISATEUR (WPForms)
// ==================================================
/**
 * 🔹 get_organisateur_id_by_contact_email → retrouve l’ID organisateur à partir de l’email de contact.
 * 🔹 filtrer_destinataire_contact_organisateur → modifie le destinataire du mail via WPForms (email ACF ou auteur, BCC admin)
 * 🔹 ajouter_endpoint_contact_organisateur → ajoute l’endpoint `/contact` sur les URLs des organisateurs (détection côté template)
 */

/**
 * Récupère l'ID d'un organisateur à partir de son email de contact public.
 *
 * Cette fonction recherche d'abord un CPT "organisateur" dont le champ ACF
 * `profil_public_email_contact` correspond à l'email fourni. Si rien n'est trouvé,
 * on tente une correspondance avec l'email de l'auteur du CPT.
 *
 * @param string|null $email Email de contact fourni dans l'URL.
 *
 * @return int|null ID du CPT organisateur correspondant ou null si introuvable.
 */
function get_organisateur_id_by_contact_email(?string $email): ?int
{
    $sanitized = sanitize_email((string) $email);

    if ($sanitized === '') {
        return null;
    }

    static $cache = [];
    $cache_key = strtolower($sanitized);

    if (array_key_exists($cache_key, $cache)) {
        return $cache[$cache_key];
    }

    $query = get_posts([
        'post_type'      => 'organisateur',
        'post_status'    => ['publish', 'pending', 'draft'],
        'meta_key'       => 'profil_public_email_contact',
        'meta_value'     => $sanitized,
        'meta_compare'   => '=',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'suppress_filters' => false,
    ]);

    if (!empty($query)) {
        $organisateur_id = (int) $query[0];
        $cache[$cache_key] = $organisateur_id;

        return $organisateur_id;
    }

    $user = get_user_by('email', $sanitized);

    if ($user) {
        $organisateur_id = get_organisateur_from_user((int) $user->ID);

        if ($organisateur_id) {
            $cache[$cache_key] = (int) $organisateur_id;

            return (int) $organisateur_id;
        }
    }

    $cache[$cache_key] = null;

    return null;
}

/**
 * Ajoute l'endpoint `contact` aux permaliens des organisateurs.
 *
 * Permet de détecter /contact après un CPT organisateur dans le template.
 *
 * @return void
 */
function ajouter_endpoint_contact_organisateur() {
    add_rewrite_endpoint('contact', EP_PERMALINK);
}
add_action('init', 'ajouter_endpoint_contact_organisateur');

/**
 * Enregistre `contact` comme variable de requête valide.
 *
 * Permet d'utiliser get_query_var('contact') de manière fiable.
 *
 * @param array $vars
 * @return array
 */
function ajouter_query_var_contact($vars) {
    $vars[] = 'contact';
    return $vars;
}

add_filter('query_vars', 'ajouter_query_var_contact');

/**
 * Génére une liste hiérarchique des chasses d'un organisateur.
 *
 * Exemple de sortie :
 * - Organisateur (3 chasses)
 *   - Chasse 1 (4 énigmes)
 *   - Chasse 2 (2 énigmes)
 *
 * @param int $organisateur_id ID de l'organisateur.
 * @return string HTML contenant la liste ou chaîne vide si non valide.
 */
function generer_liste_chasses_hierarchique($organisateur_id) {
    if (!$organisateur_id || get_post_type($organisateur_id) !== 'organisateur') {
        return '';
    }

    $query = get_chasses_de_organisateur($organisateur_id);
    $nombre_chasses = $query->found_posts ?? 0;

    $out  = '<ul class="liste-chasses-hierarchique">';
    $out .= '<li>';
    $out .= 'Organisateur : <a href="' . esc_url(get_permalink($organisateur_id)) . '">' . esc_html(get_the_title($organisateur_id)) . '</a> ';
    $out .= '(' . sprintf(_n('%d chasse', '%d chasses', $nombre_chasses, 'text-domain'), $nombre_chasses) . ')';

    if ($nombre_chasses > 0) {
        $out .= '<ul>';
        foreach ($query->posts as $chasse_id) {
            $chasse_id    = (int) $chasse_id;
            $chasse_titre = get_the_title($chasse_id);
            $nb_enigmes   = count(recuperer_enigmes_associees($chasse_id));
            $out         .= '<li>';
            $out         .= 'Chasse : <a href="' . esc_url(get_permalink($chasse_id)) . '">' . esc_html($chasse_titre) . '</a> ';
            $out         .= '(' . sprintf(_n('%d énigme', '%d énigmes', $nb_enigmes, 'text-domain'), $nb_enigmes) . ')';
            $out         .= '</li>';
        }
        $out .= '</ul>';
    }

    $out .= '</li></ul>';

    return $out;
}


// ==================================================
// 🎯 CTA PAGE "DEVENIR ORGANISATEUR"
// ==================================================
/**
 * Remove any pending organiser request metadata for the given user.
 *
 * @param int $user_id Target user identifier.
 *
 * @return void
 */
function cat_clear_organisateur_request(int $user_id): void
{
    delete_user_meta($user_id, 'organisateur_demande_token');
    delete_user_meta($user_id, 'organisateur_demande_date');
}

/**
 * Retrieve the status of the organiser creation request for a user.
 *
 * @param int $user_id Target user identifier.
 *
 * @return array{token:?string,expired:bool,expires_at?:int} Request status payload.
 */
function cat_get_organisateur_request_status(int $user_id): array
{
    $token = (string) get_user_meta($user_id, 'organisateur_demande_token', true);

    if ($token === '') {
        return [
            'token'   => null,
            'expired' => false,
        ];
    }

    $date = get_user_meta($user_id, 'organisateur_demande_date', true);
    $timestamp = $date ? strtotime((string) $date) : false;

    if (!$timestamp) {
        cat_clear_organisateur_request($user_id);

        return [
            'token'   => null,
            'expired' => false,
        ];
    }

    $expires_at = $timestamp + 2 * DAY_IN_SECONDS;
    $now        = (int) current_time('timestamp');

    if ($now > $expires_at) {
        cat_clear_organisateur_request($user_id);

        return [
            'token'   => null,
            'expired' => true,
        ];
    }

    return [
        'token'      => $token,
        'expired'    => false,
        'expires_at' => $expires_at,
    ];
}

/**
 * Retourne le libellé et l'URL du bouton d'appel à l'action
 * présent sur la page "Devenir organisateur".
 *
 * @param int|null $user_id Utilisateur ciblé ou actuel par défaut.
 * @return array{label:string,url:?string,disabled:bool}
 */
function get_cta_devenir_organisateur(?int $user_id = null): array
{
    $user_id = $user_id ?: get_current_user_id();

    $label = 'Créer mon profil';
    $url = home_url('/creer-mon-profil/');
    $disabled = false;

    if (!$user_id) {
        return [
            'label' => 'Devenir organisateur',
            'url'   => wp_login_url($url),
            'disabled' => false,
        ];
    }

    $user = get_userdata($user_id);
    if (!$user) {
        return compact('label', 'url', 'disabled');
    }

    $roles = (array) $user->roles;

    $profile_state = cat_is_user_profile_complete($user_id);
    if (!$profile_state['complete']) {
        $profile_url = function_exists('wc_get_account_endpoint_url')
            ? wc_get_account_endpoint_url('edit-account')
            : home_url('/mon-compte/edit-account/');

        $message = cat_get_missing_profile_fields_message($profile_state['missing']);

        add_site_message('error', $message, false, 'profil_incomplet_' . $user_id);

        return [
            'label'    => __('Compléter mon profil', 'chassesautresor-com'),
            'url'      => $profile_url,
            'disabled' => false,
        ];
    }

    remove_site_message('profil_incomplet_' . $user_id);
    myaccount_remove_persistent_message($user_id, 'profil_incomplet');

    if (in_array('administrator', $roles, true)) {
        return [
            'label' => 'Salut Patron',
            'url'   => null,
            'disabled' => true,
        ];
    }

    $request_status = cat_get_organisateur_request_status($user_id);

    if ($request_status['expired']) {
        add_site_message(
            'info',
            __('Votre précédente demande de création de profil a expiré. Vous pouvez en envoyer une nouvelle.', 'chassesautresor-com'),
            false,
            'profil_expire_' . $user_id
        );
    }

    // Demande d'inscription non confirmée
    if (!empty($request_status['token'])) {
        return [
            'label' => "Renvoyer l'email de confirmation",
            'url'   => home_url('/creer-mon-profil/?resend=1'),
            'disabled' => false,
        ];
    }

    $organisateur_id   = get_organisateur_from_user($user_id);
    $has_pending_chasse = false;
    if ($organisateur_id) {
        $query = get_chasses_de_organisateur($organisateur_id);
        if ($query && $query->have_posts()) {
            foreach ($query->posts as $chasse_id) {
                $statut_validation = get_field('chasse_cache_statut_validation', (int) $chasse_id);
                if ($statut_validation === 'en_attente') {
                    $has_pending_chasse = true;
                    break;
                }
            }
        }
    }

    if (in_array(ROLE_ORGANISATEUR_CREATION, $roles, true) && $has_pending_chasse) {
        return [
            'label' => "Renvoyer l'email",
            'url'   => home_url('/creer-mon-profil/?resend=1'),
            'disabled' => false,
        ];
    }

    if (
        $organisateur_id &&
        (in_array(ROLE_ORGANISATEUR_CREATION, $roles, true) || in_array(ROLE_ORGANISATEUR, $roles, true)) &&
        !$has_pending_chasse
    ) {
        return [
            'label' => 'Votre profil',
            'url'   => get_permalink($organisateur_id),
            'disabled' => false,
        ];
    }

    if (!in_array(ROLE_ORGANISATEUR_CREATION, $roles, true) && !in_array(ROLE_ORGANISATEUR, $roles, true) && !$organisateur_id) {
        return [
            'label' => 'Devenir organisateur',
            'url'   => home_url('/creer-mon-profil/'),
            'disabled' => false,
        ];
    }

    return compact('label', 'url', 'disabled');
}


// ==================================================
// 📩 DEMANDE DE CRÉATION DE PROFIL ORGANISATEUR
// ==================================================
/**
 * 🔹 lancer_demande_organisateur() → Génère un token et envoie l'email de confirmation.
 * 🔹 renvoyer_email_confirmation_organisateur() → Réutilise le token existant.
 * 🔹 confirmer_demande_organisateur() → Valide la demande et crée le CPT.
 */

function envoyer_email_confirmation_organisateur(int $user_id, string $token): bool {
    $user = get_userdata($user_id);
    if (!$user || !is_email($user->user_email)) return false;

    $confirmation_url = add_query_arg([
        'user'  => $user_id,
        'token' => $token,
    ], site_url('/confirmation-organisateur/'));

    $subject_raw = __(
        '[Chasses au Trésor] Confirmez votre inscription organisateur',
        'chassesautresor-com'
    );

    $body  = '<p>' . sprintf(
        esc_html__( 'Bonjour %s,', 'chassesautresor-com' ),
        '<strong>' . esc_html( $user->display_name ) . '</strong>'
    ) . '</p>';
    $body .= '<p>' . esc_html__(
        'Pour finaliser la création de votre profil organisateur, veuillez cliquer sur le bouton ci-dessous :',
        'chassesautresor-com'
    ) . '</p>';
    $cta_styles  = 'background:#0073aa;color:#fff;padding:12px 24px;';
    $cta_styles .= 'border-radius:6px;text-decoration:none;font-weight:bold;';
    $cta_styles .= 'display:inline-block;';
    $body      .= '<p style="text-align:center;margin:24px 0;"><a href="'
        . esc_url( $confirmation_url )
        . '" style="'
        . esc_attr( $cta_styles )
        . '">'
        . esc_html__( 'Confirmer mon inscription', 'chassesautresor-com' )
        . '</a></p>';
    $body .= '<p>' . esc_html__( 'Ce lien est valable pendant 2 jours.', 'chassesautresor-com' ) . '</p>';
    $body .= '<p style="margin-top:2em;">' . esc_html__( 'Merci et à très bientôt !', 'chassesautresor-com' ) . '<br>' . esc_html__(
        'L’équipe chassesautresor.com',
        'chassesautresor-com'
    ) . '</p>';

    $from_filter = static function ($name) {
        return 'Chasses au Trésor';
    };

    $headers = [];

    add_filter('wp_mail_from_name', $from_filter, 10, 1);
    cta_send_email($user->user_email, $subject_raw, $body, $headers);
    remove_filter('wp_mail_from_name', $from_filter, 10);

    return true;
}

function lancer_demande_organisateur(int $user_id): bool {
    if ($user_id <= 0) return false;
    $token = wp_generate_password(20, false);
    update_user_meta($user_id, 'organisateur_demande_token', $token);
    update_user_meta($user_id, 'organisateur_demande_date', current_time('mysql'));
    return envoyer_email_confirmation_organisateur($user_id, $token);
}

function renvoyer_email_confirmation_organisateur(int $user_id): bool {
    if ($user_id <= 0) return false;
    $token = get_user_meta($user_id, 'organisateur_demande_token', true);
    $date  = get_user_meta($user_id, 'organisateur_demande_date', true);
    if (!$token || !$date) {
        return lancer_demande_organisateur($user_id);
    }
    $timestamp = strtotime((string) $date);
    $now = strtotime((string) current_time('mysql'));
    if (!$timestamp || ($now - $timestamp) > 2 * DAY_IN_SECONDS) {
        return lancer_demande_organisateur($user_id);
    }
    return envoyer_email_confirmation_organisateur($user_id, (string) $token);
}

function confirmer_demande_organisateur(int $user_id, string $token): ?int {
    $saved = get_user_meta($user_id, 'organisateur_demande_token', true);
    if (!$saved || $token !== $saved) {
        return null;
    }

    $date      = get_user_meta($user_id, 'organisateur_demande_date', true);
    $timestamp = $date ? strtotime((string) $date) : false;
    $now = strtotime((string) current_time('mysql'));
    if (!$timestamp || ($now - $timestamp) > 2 * DAY_IN_SECONDS) {
        return null;
    }

    delete_user_meta($user_id, 'organisateur_demande_token');
    delete_user_meta($user_id, 'organisateur_demande_date');

    $organisateur_id = creer_organisateur_pour_utilisateur($user_id);
    if ($organisateur_id) {
        $user = new WP_User($user_id);
        $user->add_role(ROLE_ORGANISATEUR_CREATION);
    }
    return $organisateur_id;
}

// ==================================================
// 🌐 ENDPOINT CONFIRMATION ORGANISATEUR
// ==================================================
/**
 * Enregistre l'endpoint /confirmation-organisateur
 *
 * Permet d'accéder à l'URL https://exemple.com/confirmation-organisateur/
 * même si aucune page WordPress n'existe.
 */
function register_endpoint_confirmation_organisateur() {
    add_rewrite_rule('^confirmation-organisateur/?$', 'index.php?confirmation_organisateur=1', 'top');
    add_rewrite_tag('%confirmation_organisateur%', '1');
}
add_action('init', 'register_endpoint_confirmation_organisateur');

/**
 * Traite la confirmation d'inscription organisateur et redirige.
 *
 * Vérifie le token, crée le CPT "organisateur" si nécessaire, connecte
 * l'utilisateur puis redirige vers son espace organisateur.
 */
function traiter_confirmation_organisateur() {
    $is_endpoint = get_query_var('confirmation_organisateur') === '1';
    $is_page     = is_page('confirmation-organisateur');
    if (!$is_endpoint && !$is_page) {
        return;
    }

    $user_id = isset($_GET['user']) ? intval($_GET['user']) : 0;
    $token   = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';

    $organisateur_id = 0;
    if ($user_id && $token) {
        $organisateur_id = confirmer_demande_organisateur($user_id, $token);
    }

    remove_site_message('profil_verification');
    if (function_exists('myaccount_remove_persistent_message')) {
        myaccount_remove_persistent_message($user_id, 'profil_verification');
    }

    if ($organisateur_id) {
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id);
        $redirect = add_query_arg('confirmation', '1', get_permalink($organisateur_id));
        wp_safe_redirect($redirect);
    } else {
        wp_safe_redirect(home_url('/devenir-organisateur'));
    }
    exit;
}
add_action('template_redirect', 'traiter_confirmation_organisateur');


