<?php

// 🚀 Empêcher l'accès direct au fichier
if (!defined('ABSPATH')) {
    exit;
}

//
// 🧩 GESTION DES STATUTS ET DE L’ACCESSIBILITÉ DES ÉNIGMES
//
// 🧠 GESTION DES STATUTS DES CHASSES
//
// 🧭 CALCUL DU STATUT D’UN ORGANISATEUR
//

// ==================================================
// 🧩 GESTION DES STATUTS ET DE L’ACCESSIBILITÉ DES ÉNIGMES
// ==================================================
/**
 * 🔹 enigme_get_statut                     → Récupérer le statut logique d’une énigme pour un utilisateur.
 * 🔹 enigme_is_accessible                 → Déterminer si une énigme est accessible.
 * 🔹 enigme_pre_requis_remplis            → Vérifier les prérequis d’une énigme pour un utilisateur.
 * 🔹 enigme_verifier_verrouillage         → Détail du verrouillage éventuel d’une énigme.
 * 🔹 traiter_statut_enigme                → Détermine le comportement global à adopter (formulaire, redirection…).
 * 🔹 enigme_est_visible_pour              → Vérifie si un utilisateur peut voir une énigme.
 * 🔹 mettre_a_jour_statuts_enigmes_de_la_chasse → Recalcule tous les statuts des énigmes liées à une chasse.
 * 🔹 enigme_mettre_a_jour_etat_systeme    → Calcule ou met à jour le champ `enigme_cache_etat_systeme`.
 * 🔹 enigme_mettre_a_jour_etat_systeme_automatiquement → Hook ACF (enregistrement admin ou front).
 * 🔹 forcer_recalcul_statut_enigme        → Recalcul AJAX côté front (édition directe).
 */


/**
 * 📄 Récupère le statut logique d'une énigme pour un utilisateur donné.
 *
 * Statuts possibles (retours) :
 * - 'en_cours'        : L'utilisateur a commencé l'énigme.
 * - 'resolue'         : L'utilisateur a résolu l'énigme.
 * - 'terminee'        : La chasse est terminée et l'utilisateur a terminé l’énigme.
 * - 'terminee_non_resolue' : La chasse est terminée mais l'utilisateur n’a pas résolu l’énigme.
 * - 'bloquee_date'    : L’énigme ou la chasse est à venir.
 * - 'bloquee_pre_requis' : L’utilisateur n’a pas rempli les conditions d’accès.
 * - 'bloquee_chasse'  : Aucune chasse valide liée.
 * - 'echouee'         : Statut utilisateur = échouée.
 * - 'abandonnee'      : L’utilisateur a abandonné l’énigme.
 * - 'non_souscrite'   : Aucun statut défini → invite à engager.
 * - 'invalide'        : Erreur de configuration (ACF ou état système incohérent).
 *
 * @param int $enigme_id ID de l'énigme.
 * @param int|null $user_id ID de l'utilisateur (optionnel, auto-détecté).
 * @return string Statut logique de l'énigme.
 */
function enigme_get_statut(int $enigme_id, ?int $user_id = null): string {
    $user_id = $user_id ?: get_current_user_id();

    // 1️⃣ Statut utilisateur (métadonnée directe)
    $statut_meta = get_user_meta($user_id, "statut_enigme_{$enigme_id}", true);

    if (in_array($statut_meta, ['en_cours', 'resolue', 'terminee', 'echouee', 'abandonnee'], true)) {
        return $statut_meta;
    }

    // 2️⃣ État système de l'énigme (ACF)
    $etat = get_field('enigme_cache_etat_systeme', $enigme_id);

    switch ($etat) {
        case 'bloquee_date':
            return 'bloquee_date';
        case 'bloquee_chasse':
            return 'bloquee_chasse';
        case 'invalide':
        case 'cache_invalide':
            return 'invalide';
        case 'accessible':
            break; // on continue l’analyse
        default:
            return 'invalide';
    }

    // 3️⃣ Statut de la chasse liée
    $chasse_val = get_field('enigme_chasse_associee', $enigme_id);
    $chasse_id = null;

    if (is_array($chasse_val)) {
        $chasse_id = is_object($chasse_val[0]) ? $chasse_val[0]->ID : (int) $chasse_val[0];
    } elseif (is_object($chasse_val)) {
        $chasse_id = $chasse_val->ID;
    } else {
        $chasse_id = (int) $chasse_val;
    }

    if ($chasse_id) {
        $cache = get_field('champs_caches', $chasse_id);
        $statut_chasse = $cache['chasse_cache_statut'] ?? null;

        if ($statut_chasse === 'termine') {
            return 'terminee';
        }
        if ($statut_chasse === 'a_venir') {
            return 'bloquee_date';
        }
    } else {
        return 'bloquee_chasse';
    }

    // 4️⃣ Si aucune condition bloquante : état par défaut
    return 'non_souscrite';
}


/**
 * 🔍 Vérifie si les prérequis d'une énigme sont remplis pour un utilisateur donné.
 *
 * @param int $enigme_id ID de l'énigme à vérifier.
 * @param int $user_id   ID de l'utilisateur.
 * @return bool True si tous les prérequis sont remplis ou inexistants, false sinon.
 */
function enigme_pre_requis_remplis(int $enigme_id, int $user_id): bool {
    $pre_requis = get_field('enigme_acces_pre_requis', $enigme_id);

    if (empty($pre_requis) || !is_array($pre_requis)) {
        return true; // ✅ Aucun prérequis → considéré comme rempli
    }

    foreach ($pre_requis as $enigme_requise) {
        $enigme_id_requise = is_object($enigme_requise) ? $enigme_requise->ID : (is_numeric($enigme_requise) ? (int)$enigme_requise : null);

        if ($enigme_id_requise) {
            $statut = get_user_meta($user_id, "statut_enigme_{$enigme_id_requise}", true);
            // Les statuts d'énigme sont stockés sans accent ("terminee")
            // dans les autres parties du code. Utiliser la même valeur ici
            // pour éviter un échec de vérification systématique des
            // prérequis lorsque l'utilisateur a pourtant terminé l'énigme.
            if ($statut !== 'terminee') {
                return false; // ❌ Prérequis non rempli
            }
        }
    }

    return true; // ✅ Tous les prérequis sont remplis
}



/**
 * ✅ Vérifie si l’énigme est verrouillée et retourne le motif.
 *
 * @param int $enigme_id ID de l'énigme.
 * @param int $user_id ID de l'utilisateur.
 * @return array Résultat avec :
 *  - 'est_verrouillee' (bool) : Statut de verrouillage.
 *  - 'motif' (string) : Raison du verrouillage.
 *  - 'date_deblocage' (string|null) : Date formatée si future.
 *  - 'timestamp_restant' (int|null) : Secondes restantes si applicable.
 *  - 'cout_points' (int|null) : Coût en points si concerné.
 *  - 'message_variante' (string|null) : Message en cas de variante.
 */
function enigme_verifier_verrouillage(int $enigme_id, int $user_id): array {
    $resultat = [
        'est_verrouillee'   => false,
        'motif'             => 'aucun',
        'date_deblocage'    => null,
        'timestamp_restant' => null,
        'cout_points'       => null,
        'message_variante'  => null,
    ];

    if ($user_id === 0) {
        return array_merge($resultat, [
            'est_verrouillee' => true,
            'motif'           => 'utilisateur_non_connecte',
        ]);
    }

    $statut = enigme_get_statut($enigme_id, $user_id);

    switch ($statut) {
        case 'bloquee_date':
            $date_str = get_field('enigme_acces')['enigme_acces_date'] ?? null;
            if ($date_str) {
                $date_obj = convertir_en_datetime($date_str);
                if ($date_obj && $date_obj->getTimestamp() > time()) {
                    return array_merge($resultat, [
                        'est_verrouillee'   => true,
                        'motif'             => 'date_future',
                        'date_deblocage'    => $date_obj->format('d/m/Y à H\hi'),
                        'timestamp_restant' => $date_obj->getTimestamp() - time(),
                    ]);
                }
            }
            return array_merge($resultat, [
                'est_verrouillee' => true,
                'motif'           => 'date_non_definie',
            ]);

        case 'bloquee_pre_requis':
            return array_merge($resultat, [
                'est_verrouillee' => true,
                'motif'           => 'pre_requis',
            ]);

        case 'bloquee_chasse':
            return array_merge($resultat, [
                'est_verrouillee' => true,
                'motif'           => 'chasse_indisponible',
            ]);

        case 'non_souscrite':
            return array_merge($resultat, [
                'est_verrouillee' => true,
                'motif'           => 'non_souscrit',
            ]);

        case 'invalide':
            return array_merge($resultat, [
                'est_verrouillee' => true,
                'motif'           => 'erreur_configuration',
            ]);

        default:
            return $resultat;
    }
}



/**
 * Analyse le statut d’une énigme pour un utilisateur et détermine le comportement à adopter :
 * - redirection
 * - affichage ou non du formulaire
 * - affichage d’un message explicatif
 *
 * @param int $enigme_id
 * @param int|null $user_id
 *
 * @return array{
 *   etat: string,
 *   rediriger: bool,
 *   url: string|null,
 *   afficher_formulaire: bool,
 *   afficher_message: bool,
 *   message_html: string
 * }
 */
function traiter_statut_enigme(int $enigme_id, ?int $user_id = null): array {
    $user_id = $user_id ?: get_current_user_id();
    $statut  = enigme_get_statut($enigme_id, $user_id);
    $chasse_id = recuperer_id_chasse_associee($enigme_id);

    // 🔓 Bypass total : admin ou organisateur
    if (
        current_user_can('manage_options') ||
        utilisateur_est_organisateur_associe_a_chasse($user_id, $chasse_id)
    ) {
        return [
            'etat' => $statut,
            'rediriger' => false,
            'url' => null,
            'afficher_formulaire' => true,
            'afficher_message' => false,
            'message_html' => '',
        ];
    }

    // ✅ Chasse terminée = accès libre à toutes les énigmes
    $cache_chasse = get_field('champs_caches', $chasse_id);
    $statut_chasse = $cache_chasse['chasse_cache_statut'] ?? null;
    if ($statut_chasse === 'termine') {
        return [
            'etat' => 'terminee',
            'rediriger' => false,
            'url' => null,
            'afficher_formulaire' => true,
            'afficher_message' => false,
            'message_html' => '',
        ];
    }

    // 🔁 Cas interdits : accès refusé
    if (in_array($statut, ['echouee', 'abandonnee'], true)) {
        return [
            'etat' => $statut,
            'rediriger' => true,
            'url' => $chasse_id ? get_permalink($chasse_id) : home_url('/'),
            'afficher_formulaire' => false,
            'afficher_message' => false,
            'message_html' => '',
        ];
    }

    // 🔁 Cas bloqués structurellement (pré-requis, date, etc.)
    if (in_array($statut, ['bloquee_date', 'bloquee_chasse', 'bloquee_pre_requis', 'invalide', 'cache_invalide'], true)) {
        return [
            'etat' => $statut,
            'rediriger' => true,
            'url' => $chasse_id ? get_permalink($chasse_id) : home_url('/'),
            'afficher_formulaire' => false,
            'afficher_message' => false,
            'message_html' => '',
        ];
    }

    // 🎯 Cas d'accès légitime (en cours, non_souscrite, resolue)
    $formulaire = in_array($statut, ['en_cours', 'non_souscrite'], true);
    $message = ($statut === 'resolue');
    $message_html = $message ? '<p class="message-statut">Vous avez déjà résolu cette énigme.</p>' : '';

    return [
        'etat' => $statut,
        'rediriger' => false,
        'url' => null,
        'afficher_formulaire' => $formulaire,
        'afficher_message' => $message,
        'message_html' => $message_html,
    ];
}



/**
 * Vérifie si un utilisateur est autorisé à afficher une énigme.
 * Utilise la logique de `traiter_statut_enigme()` pour autoriser ou refuser l’accès.
 *
 * @param int $user_id ID de l’utilisateur
 * @param int $enigme_id ID de l’énigme
 * @return bool True si l’énigme est visible pour cet utilisateur
 */
function enigme_est_visible_pour(int $user_id, int $enigme_id): bool {
    $data = traiter_statut_enigme($enigme_id, $user_id);
    return !$data['rediriger'];
}



/**
 * 🔁 Recalcule le statut système de toutes les énigmes liées à une chasse.
 *
 * @param int $chasse_id ID de la chasse.
 * @return void
 */
function mettre_a_jour_statuts_enigmes_de_la_chasse(int $chasse_id): void {
    
    if (get_post_type($chasse_id) !== 'chasse') return;
    $ids_enigmes = recuperer_enigmes_associees($chasse_id);
    foreach ($ids_enigmes as $enigme_id) {
        if (get_post_type($enigme_id) === 'enigme') {
            $resultat = enigme_mettre_a_jour_etat_systeme((int)$enigme_id);
        }
    }
}




/**
 * 🔁 Calcule ou met à jour le champ `enigme_cache_etat_systeme` d'une énigme.
 *
 * Ce champ reflète l'état global de l'énigme (accessible, bloquée, invalide...),
 * en tenant compte du statut de la chasse, de la condition d'accès et des réglages internes.
 *
 * @param int $enigme_id ID de l'énigme à traiter.
 * @param bool $mettre_a_jour Si true, met à jour ACF. Sinon, retourne uniquement.
 * @param string|null $statut_chasse_forcé Permet de passer un statut de chasse sans relecture ACF.
 * @return string Statut calculé.
 */
function enigme_mettre_a_jour_etat_systeme(int $enigme_id, bool $mettre_a_jour = true, ?string $statut_chasse_forcé = null): string {
    if (get_post_type($enigme_id) !== 'enigme') {
        error_log("❌ [STATUT] Post #$enigme_id n'est pas une énigme");
        return 'cache_invalide';
    }
    $etat = 'accessible';

    // 🔎 Vérifie la chasse liée
    $chasse_id = recuperer_id_chasse_associee($enigme_id);
    if (!$chasse_id || get_post_type($chasse_id) !== 'chasse') {
        $etat = 'bloquee_chasse';
        error_log("🧩 #$enigme_id → bloquee_chasse (aucune chasse valide liée)");
    } else {
        $statut_chasse = $statut_chasse_forcé ?? (get_field('champs_caches', $chasse_id)['chasse_cache_statut'] ?? null);
        error_log("🧩 #$enigme_id → chasse #$chasse_id statut = $statut_chasse");

        if (!in_array($statut_chasse, ['en_cours', 'payante', 'termine'], true)) {
            $etat = 'bloquee_chasse';
        }
    }

    // 🔐 Accès programmé / prérequis
    $condition = get_field('enigme_acces_condition', $enigme_id) ?? 'immediat';

    if ($etat === 'accessible' && $condition === 'date_programmee') {
        $date = get_field('enigme_acces_date', $enigme_id);
        $date_obj = convertir_en_datetime($date);
        if (!$date_obj || $date_obj->getTimestamp() > time()) {
            $etat = 'bloquee_date';
            error_log("🧩 #$enigme_id → bloquee_date (accès programmé futur ou vide)");
        }
    }

    // ❓ Vérifie si la réponse attendue est bien définie si validation = automatique
    $mode = get_field('enigme_mode_validation', $enigme_id);
    $reponse = get_field('enigme_reponse_bonne', $enigme_id);
    if ($etat === 'accessible' && $mode === 'automatique' && !$reponse) {
        $etat = 'invalide';
        error_log("🧩 #$enigme_id → invalide (automatique sans réponse)");
    }

    // ✅ Mise à jour ACF si demandé
    if ($mettre_a_jour) {
        $actuel = get_field('enigme_cache_etat_systeme', $enigme_id);
        if ($actuel !== $etat) {
            update_field('enigme_cache_etat_systeme', $etat, $enigme_id);
        } else {
            error_log("⏸️ [STATUT] Pas de changement pour #$enigme_id (déjà $etat)");
        }
    }

    return $etat;
}




/**
 * Hook automatique ACF : met à jour l’état système d’une énigme après enregistrement.
 *
 * @param int|string $post_id ID de l’énigme ou identifiant ACF (ex : 'options')
 * @return void
 */
function enigme_mettre_a_jour_etat_systeme_automatiquement($post_id): void {
    if (!is_numeric($post_id) || get_post_type($post_id) !== 'enigme') return;
    if ($post_id === 'options' || wp_is_post_revision($post_id)) return;

    enigme_mettre_a_jour_etat_systeme((int) $post_id); // appelle la version unifiée
}



/**
 * 🔁 Recalcule le statut système d’une énigme via appel AJAX sécurisé.
 *
 * @hook wp_ajax_forcer_recalcul_statut_enigme
 * @return void
 */
add_action('wp_ajax_forcer_recalcul_statut_enigme', 'forcer_recalcul_statut_enigme');

function forcer_recalcul_statut_enigme() {
    if (!is_user_logged_in()) {
        wp_send_json_error('non_connecte');
    }

    $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;

    if (!$post_id || get_post_type($post_id) !== 'enigme') {
        wp_send_json_error('post_invalide');
    }

    enigme_mettre_a_jour_etat_systeme($post_id);
    wp_send_json_success('statut_enigme_recalcule');
}



// ==================================================
// 🧠 GESTION DES STATUTS DES CHASSES
// ==================================================
/**
 * 🔹 verifier_ou_recalculer_statut_chasse() → Vérifie et met à jour le statut ACF d'une chasse à l'affichage si nécessaire.
 * 🔹 mettre_a_jour_statuts_chasse() → Met à jour les statuts de validation et de visibilité d'une chasse.
 * 🔹 forcer_recalcul_statut_chasse() → Forcer un recalcul du statut via une requête AJAX.
 * 🔹 mettre_a_jour_statut_si_chasse() → Déclenche la mise à jour automatique du statut après sauvegarde ACF en admin.
 * 🔹 recuperer_statut_chasse() → Retourne dynamiquement le statut pour mise à jour du badge via JS.
 * 🔹 forcer_statut_selon_validation_chasse() → Applique le statut WordPress selon la validation lors de la sauvegarde admin.
 * 🔹 forcer_statut_apres_acf() → Corrige le post_status après sauvegarde ACF pour éviter les incohérences.
 */



/**
 * Vérifie et met à jour le statut d'une chasse si le statut cache semble obsolète.
 *
 * À utiliser lors de l'affichage de la fiche chasse.
 *
 * @param int $chasse_id
 * @return void
 */
function verifier_ou_recalculer_statut_chasse($chasse_id): void {
    if (get_post_type($chasse_id) !== 'chasse') return;
    
    static $chasses_traitees = [];

    if (in_array($chasse_id, $chasses_traitees, true)) return;
    $chasses_traitees[] = $chasse_id;


    $cache = get_field('champs_caches', $chasse_id);
    $statut = $cache['chasse_cache_statut'] ?? null;

    // Si le statut est manquant ou invalide, on le recalcule
    $statuts_valides = ['revision', 'a_venir', 'en_cours', 'payante', 'termine'];
    if (!in_array($statut, $statuts_valides, true)) {
        mettre_a_jour_statuts_chasse($chasse_id);
        return;
    }

    // On pourrait aller plus loin : vérifier si la date est dépassée
    $carac = get_field('caracteristiques', $chasse_id);
    $now = current_time('timestamp');
    $date_fin = !empty($carac['chasse_infos_date_fin']) ? strtotime($carac['chasse_infos_date_fin']) : null;

    if (!empty($carac['chasse_infos_duree_illimitee'])) return;

    if ($statut !== 'termine' && $date_fin && $date_fin < $now) {
        mettre_a_jour_statuts_chasse($chasse_id);
    }
}


/**
 * Met à jour le statut fonctionnel d'une chasse (champ ACF `chasse_cache_statut`).
 *
 * Appelée lors de toute modification importante.
 * Si la chasse devient "termine", planifie (ou déclenche) les déplacements de PDF.
 *
 * @param int $chasse_id ID du post de type "chasse".
 */
function mettre_a_jour_statuts_chasse($chasse_id) {
    if (get_post_type($chasse_id) !== 'chasse') return;

    $carac = get_field('caracteristiques', $chasse_id);
    $cache = get_field('champs_caches', $chasse_id);
    if (!$carac || !$cache) {
        error_log("⚠️ Données manquantes pour chasse #$chasse_id : caractéristiques ou champs_caches");
        return;
    }

    $maintenant = current_time('timestamp');

    $statut_validation = $cache['chasse_cache_statut_validation'] ?? 'creation';
    $date_debut        = !empty($carac['chasse_infos_date_debut']) ? strtotime($carac['chasse_infos_date_debut']) : null;
    $date_fin          = !empty($carac['chasse_infos_date_fin']) ? strtotime($carac['chasse_infos_date_fin']) : null;
    $date_obj          = convertir_en_datetime($cache['chasse_cache_date_decouverte'] ?? null);
    $date_decouverte   = $date_obj ? $date_obj->getTimestamp() : null;
    $cout_points       = intval($carac['chasse_infos_cout_points'] ?? 0);
    $mode_continue     = empty($carac['chasse_infos_duree_illimitee']);

    $statut = 'revision';

    if ($statut_validation === 'valide') {
        if ($date_decouverte) {
            $statut = 'termine';
        } elseif ($mode_continue && $date_fin && $date_fin < $maintenant) {
            $statut = 'termine';
        } elseif ($date_debut && $date_debut <= $maintenant) {
            $statut = ($cout_points > 0) ? 'payante' : 'en_cours';
        } elseif ($date_debut && $date_debut > $maintenant) {
            $statut = 'a_venir';
        } else {
            $statut = $cache['chasse_cache_statut'] ?? 'revision';
        }
    }

    $ancien = $cache['chasse_cache_statut'] ?? '(inconnu)';
    
    // ✅ Si terminée, déclenche les planifications PDF
    if ($statut === 'termine') {
        $liste_enigmes = recuperer_enigmes_associees($chasse_id);
        
        foreach ($liste_enigmes as $enigme_id) {
            planifier_ou_deplacer_pdf_solution_immediatement($enigme_id);
        }
    }

    mettre_a_jour_sous_champ_group($chasse_id, 'champs_caches', 'chasse_cache_statut', $statut);

    if (function_exists('synchroniser_cache_enigmes_chasse')) {
        synchroniser_cache_enigmes_chasse($chasse_id, true, true);
    }

    mettre_a_jour_statuts_enigmes_de_la_chasse($chasse_id, $statut);
}



/**
 * 🔁 Forcer le recalcul du statut d'une chasse via AJAX.
 *
 * Utilisé pour recalculer le statut après une mise à jour front-end
 * sans attendre la sauvegarde naturelle de WordPress/ACF.
 *
 * @hook wp_ajax_forcer_recalcul_statut_chasse
 * @return void
 */
add_action('wp_ajax_forcer_recalcul_statut_chasse', 'forcer_recalcul_statut_chasse');

/**
 * Forcer le recalcul du statut d'une chasse (via appel AJAX séparé, après modification d’un champ).
 */
function forcer_recalcul_statut_chasse() {
  if (!is_user_logged_in()) {
    wp_send_json_error('non_connecte');
  }

  $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;

  if (!$post_id || get_post_type($post_id) !== 'chasse') {
    wp_send_json_error('post_invalide');
  }

  mettre_a_jour_statuts_chasse($post_id);
  wp_send_json_success('statut_recalcule');
}


/**
 * 🔄 Mettre à jour le statut d'une chasse après enregistrement ACF en admin.
 *
 * Accroché au hook `acf/save_post` pour recalculer automatiquement
 * le statut fonctionnel dès qu'un organisateur modifie ses champs en back-office.
 *
 * @param int $post_id ID du post enregistré par ACF.
 * @return void
 */
add_action('acf/save_post', 'mettre_a_jour_statut_si_chasse', 20);

function mettre_a_jour_statut_si_chasse($post_id) {
  if (!is_numeric($post_id)) return;

  if (get_post_type($post_id) === 'chasse') {
    // 🔁 Supprimer le champ pour forcer une relecture propre (évite valeurs en cache)
    delete_transient("acf_field_{$post_id}_champs_caches");

    error_log("🔁 Recalcul du statut via acf/save_post pour la chasse $post_id");
    mettre_a_jour_statuts_chasse($post_id);
  }
}


/**
 * 🔎 Récupère le statut public actuel d'une chasse (via AJAX).
 *
 * Utilisé pour mettre à jour dynamiquement le badge de statut en front,
 * après une modification qui déclenche un recalcul.
 *
 * @hook wp_ajax_recuperer_statut_chasse
 * @return void
 */
add_action('wp_ajax_recuperer_statut_chasse', 'recuperer_statut_chasse');

function recuperer_statut_chasse() {
  if (!is_user_logged_in()) {
    wp_send_json_error('non_connecte');
  }

  $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;

  if (!$post_id || get_post_type($post_id) !== 'chasse') {
    wp_send_json_error('post_invalide');
  }

  $statut = get_field('champs_caches_chasse_cache_statut', $post_id);
  if (!$statut) {
    wp_send_json_error('statut_indisponible');
  }

  wp_send_json_success([
    'statut' => $statut,
    'statut_label' => ucfirst(str_replace('_', ' ', $statut))
  ]);
}


/**
 * 🔁 Met à jour le champ de validation et force le statut du post en cohérence.
 *
 * Si $nouvelle_validation est fourni, il est appliqué à chasse_cache_statut_validation
 * avant de recalculer et mettre à jour le post_status correspondant.
 *
 * @param int    $post_id              ID du post chasse.
 * @param string|null $nouvelle_validation  Valeur à forcer (valide, banni, etc.). Optionnel.
 * @return void
 */
function forcer_statut_apres_acf($post_id, $nouvelle_validation = null) {
  if (!is_numeric($post_id) || get_post_type($post_id) !== 'chasse') return;

  // Lecture et mise à jour facultative
  $champs = get_field('champs_caches', $post_id) ?: [];

  if ($nouvelle_validation !== null) {
    $champs['chasse_cache_statut_validation'] = sanitize_text_field($nouvelle_validation);
    update_field('champs_caches', $champs, $post_id);
  }

  $validation = $champs['chasse_cache_statut_validation'] ?? null;
  if (!$validation) return;

  $statut_voulu = match ($validation) {
    'valide'     => 'publish',
    'banni'      => 'draft',
    default      => 'pending',
  };

  if (get_post_status($post_id) !== $statut_voulu) {
    wp_update_post([
      'ID'          => $post_id,
      'post_status' => $statut_voulu,
    ]);
  }
}
add_action('acf/save_post', 'forcer_statut_apres_acf', 99);



/**
 * 🔹 is_canevas_creation → Vérifie si l’utilisateur est en train de créer son espace organisateur (aucun CPT associé, et sur la page dédiée).
 */

/**
 * Détermine si l’utilisateur est dans le parcours de création d’un organisateur (canevas).
 *
 * @return bool
 */
function is_canevas_creation() {
    if (!is_user_logged_in()) {
        return false;
    }

    if (!is_page('devenir-organisateur')) {
        return false;
    }

    return !get_organisateur_from_user(get_current_user_id());
}


/**
 * 🔐 Vérifie la cohérence entre post_status natif et statut de validation ACF.
 *
 * Si une incohérence est détectée, elle est loguée.
 * Le changement automatique de statut est désactivé en phase de développement.
 *
 * @hook save_post_chasse
 * @param int     $post_id ID du post.
 * @param WP_Post $post    Objet post complet.
 * @param bool    $update  True si c’est une mise à jour (false si création).
 */
add_action('save_post_chasse', 'forcer_statut_selon_validation_chasse', 20, 3);

function forcer_statut_selon_validation_chasse($post_id, $post, $update) {
  // Éviter boucle infinie
  remove_action('save_post_chasse', 'forcer_statut_selon_validation_chasse', 20);

  // Ne pas agir sur autosave ou révisions
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
  if (wp_is_post_revision($post_id)) return;

  $cache = get_field('champs_caches', $post_id);
  if (!$cache || !isset($cache['chasse_cache_statut_validation'])) return;

  $validation = $cache['chasse_cache_statut_validation'];
  $statut_wp = get_post_status($post_id);

  $statut_attendu = match ($validation) {
    'valide'   => 'publish',
    'banni'    => 'draft',
    default    => 'pending',
  };

  if ($statut_wp !== $statut_attendu) {
    error_log("⚠️ Décalage statut WP vs ACF pour chasse $post_id → WP = $statut_wp / ACF = $validation");

    // ⛔ EN DÉVELOPPEMENT : synchronisation désactivée
    // ✅ À ACTIVER EN PROD :
    /*
    wp_update_post([
      'ID'          => $post_id,
      'post_status' => $statut_attendu,
    ]);
    */
  }
}