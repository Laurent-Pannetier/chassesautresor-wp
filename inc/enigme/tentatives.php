<?php
defined('ABSPATH') || exit;

if (!function_exists('cat_get_riddle_attempt_service')) {
    function cat_get_riddle_attempt_service(): ChassesAuTresor\Core\Progress\RiddleAttemptService
    {
        global $wpdb;
        return ChassesAuTresor\Core\Support\CoreServiceFactory::riddleAttempts($wpdb);
    }
}


    // ==================================================
    // 📊 GESTION DES TENTATIVES UTILISATEUR
    // ==================================================
    // 🔹 inserer_tentative() → Insère une tentative dans la table personnalisée.
    // 🔹 get_tentative_by_uid() → Récupère une tentative par son identifiant UID.
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

        $is_organizer = false;
        if (function_exists('recuperer_id_chasse_associee')) {
            $chasse_id = (int) recuperer_id_chasse_associee((int) ($tentative->enigme_id ?? 0));
            $is_organizer = $chasse_id > 0
                && function_exists('utilisateur_est_organisateur_associe_a_chasse')
                && utilisateur_est_organisateur_associe_a_chasse($current_user_id, $chasse_id);
        }

        return (new ChassesAuTresor\Core\Progress\RiddleAttemptAccessPolicy())->canView(
            $current_user_id,
            (int) ($tentative->user_id ?? 0),
            current_user_can('manage_options'),
            $is_organizer
        );
    }

    /**
     * AJAX handler to safely reveal a tentative proposition.
     */
    function ca_ajax_view_tentative_proposition(): void
    {
        ChassesAuTresor\Core\Progress\RiddleAttemptViewAjaxHandler::handle();
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

/** Retourne le rendu HTML thématique d'une page de tentatives. */
function cat_render_riddle_attempt_list(array $arguments): string
{
    ob_start();
    get_template_part('template-parts/enigme/partials/enigme-partial-tentatives', null, $arguments);

    return (string) ob_get_clean();
}

/** Compatibility facade for the historical callback. */
function ajax_lister_tentatives_enigme(): void
{
    ChassesAuTresor\Core\Progress\RiddleAttemptListAjaxHandler::handle();
}

if (class_exists(ChassesAuTresor\Core\Progress\RiddleAttemptListAjaxHandler::class)) {
    ChassesAuTresor\Core\Progress\RiddleAttemptListAjaxHandler::configure(
        static function (array $arguments): string {
            return cat_render_riddle_attempt_list($arguments);
        }
    );
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
