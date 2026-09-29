<?php
defined('ABSPATH') || exit;

if (!class_exists(ChassesAuTresor\Core\Progress\RiddleAttemptService::class, false)) {
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Progress/RiddleAttemptRepository.php';
    require_once dirname(__DIR__, 4)
        . '/plugins/chassesautresor-core/src/Progress/RiddleAttemptService.php';
}

if (!function_exists('cat_get_riddle_attempt_service')) {
    function cat_get_riddle_attempt_service(): ChassesAuTresor\Core\Progress\RiddleAttemptService
    {
        global $wpdb;

        return new ChassesAuTresor\Core\Progress\RiddleAttemptService(
            new ChassesAuTresor\Core\Progress\RiddleAttemptRepository($wpdb)
        );
    }
}


    // ==================================================
    // 📊 GESTION DES TENTATIVES UTILISATEUR
    // ==================================================
    // 🔹 inserer_tentative() → Insère une tentative dans la table personnalisée.
    // 🔹 get_tentative_by_uid() → Récupère une tentative par son identifiant UID.
    // 🔹 traiter_tentative_manuelle() → Effectue la validation/refus d'une tentative (une seule fois).
    // 🔹 recuperer_infos_tentative() → Renvoie toutes les données pour l'affichage d'une tentative.
    // 🔹 get_etat_tentative() → Retourne l'état logique d'une tentative selon son champ `resultat`.

    /**
     * Fonction générique pour insérer une tentative dans la table personnalisée.
     *
     * @param int $user_id
     * @param int $enigme_id
     * @param string $reponse
     * @param string $resultat Valeur par défaut : 'attente'.
     * @param int $points_utilises Points dépensés pour cette tentative.
     * @return string UID unique généré pour cette tentative.
     */
    function inserer_tentative($user_id, $enigme_id, $reponse, $resultat = 'attente', $points_utilises = 0): string
    {
        $uid = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('tent_', true);

        $inserted = cat_get_riddle_attempt_service()->create(
            $uid,
            (int) $user_id,
            (int) $enigme_id,
            (string) $reponse,
            (string) $resultat,
            (int) $points_utilises,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        );
        if ($inserted) {
            do_action('enigme_tentative_created', $enigme_id);
        }

        return $uid;
    }

    /**
     * Récupère une tentative par son UID.
     */
    function get_tentative_by_uid(string $uid): ?object
    {
        return cat_get_riddle_attempt_service()->findByUid($uid);
    }

    function get_last_tentative_insert_id(): int
    {
        return cat_get_riddle_attempt_service()->getLastCreatedId();
    }

    /**
     * Check if the current user can view the proposition linked to a tentative.
     */
    function ca_user_can_view_tentative_proposition(object $tentative): bool
    {
        $current_user_id = (int) get_current_user_id();

        return cat_get_riddle_attempt_service()->canViewAttempt(
            $tentative,
            $current_user_id,
            current_user_can('manage_options'),
            static function (int $user_id, int $enigme_id): bool {
                if (!function_exists('recuperer_id_chasse_associee')) {
                    return false;
                }

                $chasse_id = (int) recuperer_id_chasse_associee($enigme_id);

                return $chasse_id > 0
                    && function_exists('utilisateur_est_organisateur_associe_a_chasse')
                    && utilisateur_est_organisateur_associe_a_chasse($user_id, $chasse_id);
            }
        );
    }

    /**
     * AJAX handler to safely reveal a tentative proposition.
     */
    function ca_ajax_view_tentative_proposition(): void
    {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('Unauthorized', 'chassesautresor-com')], 403);
        }

        $uid = isset($_POST['uid']) ? sanitize_text_field(wp_unslash((string) $_POST['uid'])) : '';
        if ($uid === '') {
            wp_send_json_error(['message' => __('Identifiant de tentative invalide.', 'chassesautresor-com')], 400);
        }

        $nonce_valid = true;
        if (function_exists('check_ajax_referer')) {
            $nonce_valid = check_ajax_referer('ca_view_tentative_' . $uid, 'nonce', false);
        }

        if ($nonce_valid === false) {
            wp_send_json_error(['message' => __('Vérification de sécurité échouée.', 'chassesautresor-com')], 403);
        }

        $tentative = get_tentative_by_uid($uid);
        if (!$tentative) {
            wp_send_json_error(['message' => __('Tentative introuvable.', 'chassesautresor-com')], 404);
        }

        if (!ca_user_can_view_tentative_proposition($tentative)) {
            wp_send_json_error(['message' => __('Unauthorized', 'chassesautresor-com')], 403);
        }

        $proposition = isset($tentative->reponse_saisie) ? (string) $tentative->reponse_saisie : '';

        wp_send_json_success([
            'proposition' => $proposition,
        ]);
    }
    add_action('wp_ajax_ca_view_tentative_proposition', 'ca_ajax_view_tentative_proposition');
    add_action('wp_ajax_nopriv_ca_view_tentative_proposition', 'ca_ajax_view_tentative_proposition');


    /**
     * Traite une tentative manuelle : effectue l'action (validation/refus) une seule fois.
     *
     * @param string $uid Identifiant unique de la tentative.
     * @param string $resultat 'bon' ou 'faux'.
     * @return bool true si traitement effectué, false si déjà traité ou interdit.
     */
    function traiter_tentative_manuelle(string $uid, string $resultat): bool

    {
        cat_debug("👣 Tentative traitement UID=$uid par IP=" . ($_SERVER['REMOTE_ADDR'] ?? 'inconnue'));

        $attempt_service = cat_get_riddle_attempt_service();
        $tentative = $attempt_service->processManualAttempt(
            $uid,
            $resultat,
            (int) get_current_user_id(),
            current_user_can('manage_options'),
            static function (int $enigme_id): array {
                $chasse_id = recuperer_id_chasse_associee($enigme_id);
                $organisateur_id = get_organisateur_from_chasse($chasse_id);

                return (array) get_field('utilisateurs_associes', $organisateur_id);
            }
        );
        if ($tentative === null) {
            cat_debug("⛔ Tentative manuelle non traitée pour UID=$uid");
            return false;
        }

        $user_id = (int) $tentative->user_id;
        $enigme_id = (int) $tentative->enigme_id;
        traiter_tentative($user_id, $enigme_id, (string) $tentative->reponse_saisie, $resultat, false, true, true);

        $chasse_id       = recuperer_id_chasse_associee($enigme_id);
        $organisateur_id = get_organisateur_from_chasse($chasse_id);
        $orga_users      = (array) get_field('utilisateurs_associes', $organisateur_id);
        $notification_plan = $attempt_service->buildManualNotificationPlan($user_id, $orga_users, $resultat);
        $titre_enigme = get_the_title($enigme_id);
        $message      = sprintf(
            $notification_plan['approved']
                ? __(
                    'Votre demande de résolution de l\'énigme %1$s%2$s%3$s a été validée. Félicitations !',
                    'chassesautresor-com'
                )
                : __(
                    'Votre demande de résolution de l\'énigme %1$s%2$s%3$s a été invalidée.',
                    'chassesautresor-com'
                ),
            '<a href="' . esc_url(get_permalink($enigme_id)) . '">',
            esc_html($titre_enigme),
            '</a>'
        );
        foreach ($notification_plan['persistent_recipient_ids'] as $recipient_id) {
            myaccount_remove_persistent_message($recipient_id, 'tentative_' . $uid);
        }

        myaccount_add_flash_message(
            $user_id,
            $message,
            $notification_plan['flash_type']
        );

        cat_debug("✅ Tentative UID=$uid traitée comme $resultat");
        return true;
    }


    /**
     * Récupère toutes les informations nécessaires à l'affichage d'une tentative.
     *
     * @param string $uid UID unique de la tentative.
     * @return array Données enrichies : statut, nom, etc.
     */
    function recuperer_infos_tentative(string $uid): array
    {
        $details = cat_get_riddle_attempt_service()->describeByUid($uid);
        if ($details === null) {
            return ['etat_tentative' => 'inexistante'];
        }

        $tentative = $details['attempt'];
        $user = get_userdata($tentative->user_id);
        $nom_user = ($user && isset($user->display_name))
            ? $user->display_name
            : __('Utilisateur inconnu', 'chassesautresor-com');

        return [
            'etat_tentative'        => $details['state'],
            'statut_initial'        => $details['result'] ?: 'invalide',
            'statut_final'          => $details['result'],
            'resultat'              => $details['result'],
            'deja_traitee'          => $details['already_processed'],
            'traitee'               => $details['processed'],
            'vient_d_etre_traitee'  => $details['just_processed'],
            'tentative'             => $tentative,
            'nom_user'              => $nom_user,
            'permalink'             => get_permalink($tentative->enigme_id),
            'statistiques'          => [
                'total_user'   => 0,
                'total_enigme' => 0,
                'total_chasse' => 0,
            ],
        ];
    }


    /**
     * Retourne l'état logique d'une tentative selon son champ `resultat`.
     *
     * @param string $uid
     * @return string 'attente' | 'validee' | 'refusee' | 'invalide' | 'inexistante'
     */
    function get_etat_tentative(string $uid): string
    {
        return cat_get_riddle_attempt_service()->getStateByUid($uid);
    }

/**
 * Récupère les tentatives enregistrées pour une énigme.
 *
 * @param int $enigme_id ID de l'énigme.
 * @param int $limit     Nombre de résultats à retourner.
 * @param int $offset    Décalage pour la pagination.
 * @return array         Liste des tentatives triées par priorité manuelle puis
 *                       par date de soumission décroissante.
 */
function recuperer_tentatives_enigme(int $enigme_id, int $limit = 5, int $offset = 0): array
{
    return cat_get_riddle_attempt_service()->findForRiddle($enigme_id, $limit, $offset);
}

/**
 * Compte le nombre total de tentatives pour une énigme.
 *
 * @param int $enigme_id ID de l'énigme.
 * @return int Nombre de tentatives.
 */
function compter_tentatives_enigme(int $enigme_id): int
{
    return cat_get_riddle_attempt_service()->countForRiddle($enigme_id);
}

/**
 * Retourne la liste HTML des tentatives pour une page donnée via AJAX.
 *
 * @hook wp_ajax_lister_tentatives_enigme
 */
add_action('wp_ajax_lister_tentatives_enigme', 'ajax_lister_tentatives_enigme');

function ajax_lister_tentatives_enigme()
{
    if (!is_user_logged_in()) {
        wp_send_json_error('non_connecte');
    }

    $enigme_id = isset($_POST['enigme_id']) ? (int) $_POST['enigme_id'] : 0;
    $page      = max(1, (int) ($_POST['page'] ?? 1));
    $par_page  = 20;

    if (!$enigme_id || get_post_type($enigme_id) !== 'enigme') {
        wp_send_json_error('post_invalide');
    }

    if (!utilisateur_peut_modifier_post($enigme_id)) {
        wp_send_json_error('acces_refuse');
    }

    $offset     = ($page - 1) * $par_page;
    $tentatives = recuperer_tentatives_enigme($enigme_id, $par_page, $offset);
    $total      = compter_tentatives_enigme($enigme_id);
    $pages      = (int) ceil($total / $par_page);

    ob_start();
    get_template_part('template-parts/enigme/partials/enigme-partial-tentatives', null, [
        'tentatives' => $tentatives,
        'page'       => $page,
        'par_page'   => $par_page,
        'total'      => $total,
        'pages'      => $pages,
    ]);
    $html = ob_get_clean();

    wp_send_json_success([
        'html'  => $html,
        'total' => $total,
        'page'  => $page,
        'pages' => $pages,
    ]);
}

/**
 * Compte le nombre de tentatives en attente de traitement pour une énigme.
 *
 * @param int $enigme_id ID de l'énigme.
 * @return int Nombre de tentatives non traitées.
 */
function compter_tentatives_en_attente(int $enigme_id): int
{
    return cat_get_riddle_attempt_service()->countPendingForRiddle($enigme_id);
}

/**
 * Récupère les énigmes ayant des tentatives manuelles en attente pour un organisateur.
 *
 * @param int $organisateur_id ID du CPT organisateur.
 * @return array Liste d'IDs d'énigmes.
 */
function recuperer_enigmes_tentatives_en_attente(int $organisateur_id): array
{
    if ($organisateur_id <= 0) {
        return [];
    }

    $query = get_chasses_de_organisateur($organisateur_id);
    if (empty($query->posts)) {
        return [];
    }

    $riddle_modes = [];

    foreach ($query->posts as $chasse_id) {
        $enigmes = recuperer_enigmes_associees((int) $chasse_id);
        foreach ($enigmes as $enigme_id) {
            $enigme_id = (int) $enigme_id;
            $riddle_modes[$enigme_id] = enigme_normaliser_mode_validation(
                get_field('enigme_mode_validation', $enigme_id)
            );
        }
    }

    return cat_get_riddle_attempt_service()->findPendingManualRiddleIds($riddle_modes);
}

/**
 * Compte le nombre de tentatives effectuées par un utilisateur pour une énigme
 * durant la journée courante (heure de Paris).
 */
function compter_tentatives_du_jour(int $user_id, int $enigme_id): int
{
    return cat_get_riddle_attempt_service()->countTodayForUser($user_id, $enigme_id);
}

/**
 * Traite une tentative d'énigme : déduction des points, enregistrement et
 * mise à jour du statut utilisateur.
 *
 * @param bool $email_echec  Envoyer un email même en cas d'échec
 * @param bool $envoyer_mail Envoyer les notifications de résultat
 * @return string UID de la tentative enregistrée (vide si $inserer = false)
 */
function traiter_tentative(
    int $user_id,
    int $enigme_id,
    string $reponse,
    string $resultat,
    bool $inserer = true,
    bool $email_echec = false,
    bool $envoyer_mail = true
): string
{
    $attempt_service = cat_get_riddle_attempt_service();
    $plan = $attempt_service->buildProcessingPlan(
        $user_id,
        $enigme_id,
        $resultat,
        (int) get_field('enigme_tentative_cout_points', $enigme_id),
        $inserer,
        $email_echec
    );
    if ($plan === null) {
        return '';
    }

    $cout = $plan['charge'];
    if ($cout > 0) {
        $reason = sprintf(
            __("Tentative de réponse pour l'énigme #%d", 'chassesautresor-com'),
            $enigme_id
        );
        deduire_points_utilisateur($user_id, $cout, $reason, 'tentative', $enigme_id);
    }

    $uid = '';
    if ($inserer) {
        $uid = inserer_tentative($user_id, $enigme_id, $reponse, $resultat, $cout);
    }

    $outcome = $plan['outcome'];
    enigme_mettre_a_jour_statut_utilisateur($enigme_id, $user_id, $outcome['user_status']);

    if ($outcome['resolved']) {
        do_action('enigme_resolue', $user_id, $enigme_id);
    }

    if ($envoyer_mail && $outcome['notify']) {
        envoyer_mail_resultat_joueur($user_id, $enigme_id, $resultat);
    }

    return $uid;
}
