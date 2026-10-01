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
    $paiements = cat_get_conversion_service()->getRequests((int) $user_id);

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
 * AJAX : renvoie le contenu du modal de conversion actualisé.
 */
function ajax_conversion_modal_content(): void
{
    ChassesAuTresor\Core\Points\ConversionModalAjaxHandler::handle();
}


/**
 * Affiche le tableau des demandes de paiement d'un organisateur.
 *
 * @param int    $user_id       L'ID de l'utilisateur organisateur.
 * @param string $filtre_statut Filtre optionnel : 'en_attente' pour les demandes en cours, 'toutes' (par défaut) pour l'historique complet.
 */
function afficher_tableau_paiements_organisateur($user_id, $filtre_statut = 'toutes') {
    $paiements = cat_get_conversion_service()->getRequests((int) $user_id);

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
    $service = cat_get_conversion_service();

    $per_page      = 10;
    $total         = $service->countRequests($user_id);
    if ($total === 0) {
        return '';
    }

    $requests      = $service->getRequests($user_id, null, $per_page);
    $paid_totals   = $service->getPaidTotals($user_id);
    $pending_count = $service->countRequests($user_id, 'pending');

    $is_admin_view = $user_id === null;

    $total_points = $paid_totals['points'];
    $total_eur    = $paid_totals['amount'];

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
    ChassesAuTresor\Core\Points\ConversionHistoryAjaxHandler::handle();
}



// ==================================================
// 📩 FORMULAIRE DE CONTACT ORGANISATEUR (WPForms)
// ==================================================
/**
 * 🔹 get_organisateur_id_by_contact_email → retrouve l’ID organisateur à partir de l’email de contact.
 * 🔹 filtrer_destinataire_contact_organisateur → modifie le destinataire du mail via WPForms (email ACF ou auteur, BCC admin)
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

    $request_status = cat_get_organisateur_request_status($user_id);

    if ($request_status['expired']) {
        add_site_message(
            'info',
            __(
                'Votre précédente demande de création de profil a expiré. Vous pouvez en envoyer une nouvelle.',
                'chassesautresor-com'
            ),
            false,
            'profil_expire_' . $user_id
        );
    }

    $organisateur_id = (int) get_organisateur_from_user($user_id);
    $has_pending_chasse = false;
    if ($organisateur_id > 0) {
        $query = get_chasses_de_organisateur($organisateur_id);
        foreach (($query && $query->have_posts()) ? $query->posts : [] as $chasse_id) {
            if (get_field('chasse_cache_statut_validation', (int) $chasse_id) === 'en_attente') {
                $has_pending_chasse = true;
                break;
            }
        }
    }

    $decision = (new ChassesAuTresor\Core\Relationships\OrganizerCtaDecisionService())->decide(
        in_array('administrator', $roles, true),
        !empty($request_status['token']),
        in_array(ROLE_ORGANISATEUR_CREATION, $roles, true),
        in_array(ROLE_ORGANISATEUR, $roles, true),
        $organisateur_id,
        $has_pending_chasse
    );

    $views = [
        'administrator' => [__('Salut Patron', 'chassesautresor-com'), null, true],
        'resend_confirmation' => [
            __('Renvoyer l’email de confirmation', 'chassesautresor-com'),
            home_url('/creer-mon-profil/?resend=1'),
            false,
        ],
        'resend_pending_hunt' => [
            __('Renvoyer l’email', 'chassesautresor-com'),
            home_url('/creer-mon-profil/?resend=1'),
            false,
        ],
        'profile' => [__('Votre profil', 'chassesautresor-com'), get_permalink($organisateur_id), false],
        'apply' => [
            __('Devenir organisateur', 'chassesautresor-com'),
            home_url('/creer-mon-profil/'),
            false,
        ],
        'create' => [$label, $url, $disabled],
    ];
    [$label, $url, $disabled] = $views[$decision];

    return compact('label', 'url', 'disabled');
}
