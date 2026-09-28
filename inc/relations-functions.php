<?php
defined('ABSPATH') || exit;

// 📚 SOMMAIRE DU FICHIER : relations-functions.php
//  📦 RÉCUPÉRATION CPT ORGANISATEUR
//  📦 RÉCUPÉRATION CPT CHASSE
//  📦 RÉCUPÉRATION CPT ÉNIGME
//  📦 RECUPERATION TROPHEE
//  📦 ASSIGNATION AUTOMATIQUES
//  🔁 SYNCHRONISATION CHASSE ↔ ÉNIGMES



// ==================================================
// 📦 RÉCUPÉRATION CPT ORGANISATEUR
// ==================================================
/**
 * 🔹 get_organisateur_from_user → Récupérer l’ID du CPT "organisateur" associé à un utilisateur.
 * 🔹 get_organisateur_chasse → Récupérer directement le champ ACF `organisateur_id` d’une chasse.
 * 🔹 get_organisateur_from_chasse → Récupérer l’ID du CPT "organisateur" associé à une chasse (fallback intelligent).
 * 🔹 get_organisateur_id_from_context → Déterminer l’ID organisateur à partir du contexte d’un template.
 * 🔹 utilisateur_est_organisateur_associe_a_chasse → Vérifie si un utilisateur est lié à l’organisateur d’une chasse.
 */


/**
 * Récupère l'ID du CPT "organisateur" associé à un utilisateur.
 *
 * @param int $user_id ID de l'utilisateur recherché.
 * @return int|null ID du post organisateur ou null si aucun trouvé.
 */
function get_organisateur_from_user($user_id)
{
  global $wpdb;

  // Recherche l'ID du post organisateur actif lié à l'utilisateur
  $post_id = $wpdb->get_var($wpdb->prepare(
    "SELECT p.ID FROM $wpdb->posts p
      INNER JOIN $wpdb->postmeta pm ON p.ID = pm.post_id
      WHERE pm.meta_key = 'utilisateurs_associes'
        AND pm.meta_value LIKE %s
        AND p.post_type = 'organisateur'
        AND p.post_status IN ('publish','pending','draft')
      LIMIT 1",
    '%"' . esc_sql($user_id) . '"%'
  ));

  return $post_id ? (int) $post_id : null;
}

function get_organisateur_chasse($chasse_id)
{
  $organisateur_id = get_field('organisateur_id', $chasse_id);
  return is_numeric($organisateur_id) ? intval($organisateur_id) : null;
}

/**
 * 📌 Récupère l'ID du CPT "organisateur" associé à une chasse.
 *
 * @param int $chasse_id ID du CPT "chasse".
 * @return int|null ID du post organisateur ou null si non trouvé.
 */
function get_organisateur_from_chasse($chasse_id)
{
  // ✅ Lecture directe
  $relation = get_field('chasse_cache_organisateur', $chasse_id);

  if (!empty($relation)) {

    // Gère tableau ou objet
    if (is_array($relation)) {
      $id = (int) reset($relation);
    } elseif (is_numeric($relation)) {
      $id = (int) $relation;
    } elseif ($relation instanceof WP_Post) {
      $id = (int) $relation->ID;
    } else {
      $id = null;
    }
    return $id;
  }
  return null;
}


/**
 * Récupère l’ID d’un organisateur à partir du contexte actuel.
 *
 * Cette fonction unifie les différents cas de figure :
 * – Si un ID est fourni dans $args['organisateur_id'], il est utilisé en priorité.
 * – Si on est sur une page de type "organisateur", l’ID du post est utilisé.
 * – Sinon, on récupère l’organisateur lié à l’utilisateur connecté.
 *
 * @param array $args Arguments optionnels passés au template.
 * @return int|null L’ID du CPT organisateur ou null si introuvable.
 */
function get_organisateur_id_from_context(array $args = []): ?int
{
  if (isset($args['organisateur_id'])) return (int) $args['organisateur_id'];
  global $post;
  if ($post && get_post_type($post) === 'organisateur') return (int) $post->ID;
  return get_organisateur_from_user(get_current_user_id());
}


/**
 * Vérifie si un utilisateur est associé à l’organisateur lié à une chasse donnée.
 *
 * @param int $user_id ID de l'utilisateur à tester.
 * @param int $chasse_id ID de la chasse concernée.
 * @return bool True si l’utilisateur est lié à l’organisateur de la chasse.
 */
function utilisateur_est_organisateur_associe_a_chasse(int $user_id, int $chasse_id): bool
{
  if (!$user_id || !$chasse_id) return false;

  $organisateur_id = get_organisateur_from_chasse($chasse_id);
  if (!$organisateur_id) return false;

  $utilisateurs = get_field('utilisateurs_associes', $organisateur_id);
  if (!is_array($utilisateurs)) return false;

  foreach ($utilisateurs as $user) {
    $id = is_object($user) ? $user->ID : (int) $user;
    if ($id === $user_id) return true;
  }

  return false;
}




// ==================================================
// 📦 RÉCUPÉRATION CPT CHASSE
// ==================================================
/**
 * 🔹 recuperer_chasse_associee → Récupérer la chasse associée à une énigme.
 * 🔹 recuperer_id_chasse_associee → Récupérer l’ID de la chasse associée à une énigme.
 * 🔹 organisateur_a_des_chasses → Vérifier si un organisateur a au moins une chasse associée.
 * 🔹 get_chasses_de_organisateur → Récupérer les chasses associées à un organisateur.
 * 🔹 get_chasses_en_creation() → Récupère les chasses d’un organisateur en cours de création (statuts spécifiques).
 */

/**
 * Récupère la chasse associée à une énigme.
 *
 * @param int $enigme_id ID de l'énigme.
 * @return WP_Post|null Chasse associée ou null si aucune trouvée.
 */
function recuperer_chasse_associee($enigme_id)
{
  $chasse = get_field('chasse_associee', $enigme_id);

  // 📌 ACF peut retourner un tableau (relation multiple) ou un objet unique
  if (is_array($chasse) && !empty($chasse)) {
    return get_post($chasse[0]);
  } elseif ($chasse instanceof WP_Post) {
    return $chasse;
  }

  return null;
}

/**
 * Récupère l'ID de la chasse associée à une énigme.
 *
 * @param int|null $post_id ID du post énigme.
 * @return int|null ID de la chasse ou null si non trouvé.
 */
function recuperer_id_chasse_associee($post_id = null)
{
  static $cached_chasse_id = null;

  if ($cached_chasse_id !== null && $cached_chasse_id > 0) {
    return $cached_chasse_id;
  }

  // 🔹 Option temporaire (création automatique)
  $temp = (int) get_option('chasse_associee_temp');
  if ($temp > 0) {
    delete_option('chasse_associee_temp');
    return $cached_chasse_id = $temp;
  }

  // 🔹 Lecture du champ ACF
  if ($post_id) {
    $champ = get_field('enigme_chasse_associee', $post_id);

    if (is_array($champ)) {
      $chasse_id = is_object($champ[0]) ? (int) $champ[0]->ID : (int) $champ[0];
    } elseif (is_object($champ)) {
      $chasse_id = (int) $champ->ID;
    } else {
      $chasse_id = (int) $champ;
    }

    if ($chasse_id > 0) {
      return $cached_chasse_id = $chasse_id;
    }
  }

  return null;
}


/**
 * Vérifie si un organisateur a au moins une chasse associée.
 *
 * @param int $organisateur_id ID de l'organisateur.
 * @return bool True si au moins une chasse existe, False sinon.
 */
function organisateur_a_des_chasses($organisateur_id)
{
  $query = new WP_Query([
    'post_type'      => 'chasse',
    'posts_per_page' => 1,
    'post_status'    => ['publish', 'pending'],
    'meta_query'     => [
      'relation' => 'AND',
      [
        'key'     => 'chasse_cache_organisateur',
        'value'   => '"' . $organisateur_id . '"',
        'compare' => 'LIKE'
      ],
      [
        'key'     => 'chasse_cache_statut_validation',
        'value'   => 'banni',
        'compare' => '!='
      ]
    ]
  ]);

  return $query->have_posts();
}

/**
 * Vérifie si un organisateur possède au moins une chasse en attente (status "pending").
 *
 * @param int $organisateur_id ID de l'organisateur.
 * @return bool True si une chasse en attente existe, False sinon.
 */
function organisateur_a_chasse_pending(int $organisateur_id): bool
{
  $query = new WP_Query([
    'post_type'      => 'chasse',
    'posts_per_page' => 1,
    'post_status'    => 'pending',
    'meta_query'     => [
      'relation' => 'AND',
      [
        'key'     => 'chasse_cache_organisateur',
        'value'   => '"' . $organisateur_id . '"',
        'compare' => 'LIKE'
      ],
      [
        'key'     => 'chasse_cache_statut_validation',
        'value'   => 'banni',
        'compare' => '!='
      ]
    ]
  ]);

  return $query->have_posts();
}

/**
 * Récupère les chasses associées à un organisateur.
 *
 * @param int $organisateur_id ID de l'organisateur.
 * @return WP_Query Objet WP_Query contenant les chasses associées.
 */
function get_chasses_de_organisateur($organisateur_id)
{
    static $cache = [];

    $organisateur_id = (int) $organisateur_id;
    if ($organisateur_id <= 0) {
        return new WP_Query();
    }

    if (isset($cache[$organisateur_id])) {
        return $cache[$organisateur_id];
    }

    $query = new WP_Query([
        'post_type'              => 'chasse',
        'posts_per_page'         => -1,
        'post_status'            => ['publish', 'pending'], // Inclure les chasses en attente
        'fields'                 => 'ids',
        'no_found_rows'          => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
        'meta_query'             => [
            [
                'key'     => 'chasse_cache_organisateur', // Champ correct
                'value'   => '"' . strval($organisateur_id) . '"', // Recherche dans le tableau sérialisé
                'compare' => 'LIKE',
            ],
        ],
    ]);

    $cache[$organisateur_id] = $query;

    return $query;
}

/**
 * Retourne le nombre de chasses publiées pour un organisateur donné.
 *
 * Utilise un cache statique pour éviter des requêtes répétées.
 *
 * @param int $organisateur_id ID de l'organisateur.
 * @return int Nombre de chasses publiées.
 */
function organisateur_get_nb_chasses_publiees(int $organisateur_id): int
{
  static $cache = [];

  if (isset($cache[$organisateur_id])) {
    return $cache[$organisateur_id];
  }

  if ($organisateur_id <= 0) {
    return $cache[$organisateur_id] = 0;
  }

  $query = new WP_Query([
    'post_type'              => 'chasse',
    'posts_per_page'         => 1,
    'post_status'            => 'publish',
    'fields'                 => 'ids',
    'no_found_rows'          => false,
    'update_post_meta_cache' => false,
    'update_post_term_cache' => false,
    'meta_query'             => [
      [
        'key'     => 'chasse_cache_organisateur',
        'value'   => '"' . $organisateur_id . '"',
        'compare' => 'LIKE',
      ],
    ],
  ]);

  $count = (int) $query->found_posts;
  $cache[$organisateur_id] = $count;

  return $count;
}

/**
 * 🔹 get_chasses_en_creation() → Récupère les chasses en création pour un organisateur donné.
 *
 * @param int $organisateur_id
 * @return int[]
 */
function get_chasses_en_creation($organisateur_id)
{
  if (!is_numeric($organisateur_id)) {
    cat_debug("⛔ get_chasses_en_creation : ID non numérique : " . print_r($organisateur_id, true));
    return [];
  }

  $chasses_query = get_chasses_de_organisateur($organisateur_id);
  $chasse_ids    = is_a($chasses_query, 'WP_Query') ? $chasses_query->posts : (array) $chasses_query;

  if (empty($chasse_ids)) {
    cat_debug("🔍 Aucune chasse liée à l’organisateur $organisateur_id");
    return [];
  }

  $filtrees = array_filter($chasse_ids, function ($id) {
    $id               = (int) $id;
    $statut_wp        = get_post_status($id);
    $statut_validation = get_field('chasse_cache_statut_validation', $id);
    $statut_metier    = get_field('chasse_cache_statut', $id);

    cat_debug("🧪 #$id | statut=$statut_wp | validation=$statut_validation | metier=$statut_metier");

    return $statut_wp === 'pending'
      && $statut_validation === 'creation'
      && $statut_metier === 'revision';
  });

  cat_debug("📦 Chasses en création retrouvées : " . count($filtrees));

  return array_values($filtrees);
}


// ==================================================
//  📦 RÉCUPÉRATION CPT ÉNIGME
// ==================================================
/**
 * 🔹 recuperer_enigmes_associees() → Récupère les énigmes associées à une chasse.
 * 🔹 recuperer_enigmes_pour_chasse() → Retourne la liste des énigmes liées à une chasse via WP_Query.
 * 🔹 recuperer_ids_enigmes_pour_chasse() → Retourne les IDs des énigmes liées à une chasse (requête directe).
 */

/**
 * 🔍 Récupère les énigmes associées à une chasse via le champ ACF `chasse_cache_enigmes`.
 *
 * Gère proprement les cas où ACF retourne des objets ou des IDs.
 * Ajoute des logs de débogage si des doublons sont présents.
 *
 * @param int $chasse_id ID de la chasse.
 * @return array Liste unique d’IDs d’énigmes (int).
 */
function recuperer_enigmes_associees(int $chasse_id): array
{
  if (!$chasse_id || get_post_type($chasse_id) !== 'chasse') {
    cat_debug("❌ [recuperer_enigmes_associees] Appel invalide pour ID $chasse_id");
    return [];
  }

  $liste_brute = get_field('chasse_cache_enigmes', $chasse_id) ?? [];

  // Extraction des IDs (objet ou int)
  $ids = [];

  foreach ($liste_brute as $item) {
    $ids[] = is_object($item) && isset($item->ID) ? (int)$item->ID : (int)$item;
  }

  // Détection et log des doublons
  $doublons = array_diff_key($ids, array_unique($ids));
  if (!empty($doublons)) {
    cat_debug("⚠️ [recuperer_enigmes_associees] Doublons détectés pour la chasse #$chasse_id : " . implode(', ', $doublons));
  }

  $ids_valides = array_filter(array_unique($ids), function ($id) {
    return get_post_type($id) === 'enigme';
  });

  return array_values($ids_valides);
}


/** *
 * ⚠️ Contrairement à `chasse_cache_enigmes`, cette fonction interroge la base en direct.
 *
 * @param int $chasse_id
 * @return WP_Post[] Liste d’objets WP_Post
 */
function recuperer_enigmes_pour_chasse(int $chasse_id): array
{
  if (!$chasse_id || get_post_type($chasse_id) !== 'chasse') {
    return [];
  }

  $query = new WP_Query([
    'post_type'      => 'enigme',
    'posts_per_page' => -1,
    'post_status'    => ['publish', 'pending'],
    'orderby'        => 'menu_order',
    'order'          => 'ASC',
    'meta_query'     => [
      [
        'key'     => 'enigme_chasse_associee',
        'value'   => $chasse_id,
        'compare' => '=',
      ],
      [
        'relation' => 'OR',
        [
          'key'     => 'enigme_cache_statut_validation',
          'compare' => 'NOT EXISTS',
        ],
        [
          'key'     => 'enigme_cache_statut_validation',
          'value'   => 'banni',
          'compare' => '!=',
        ],
      ],
    ],
  ]);

  return $query->have_posts() ? $query->posts : [];
}


/**
 * @param int $chasse_id
 * @return int[] Liste d’IDs (int)
 */
function recuperer_ids_enigmes_pour_chasse(int $chasse_id): array
{
  if (!$chasse_id || get_post_type($chasse_id) !== 'chasse') {
    return [];
  }

  $query = new WP_Query([
    'post_type'      => 'enigme',
    'fields'         => 'ids', // ⚠️ retourne un tableau d'IDs
    'posts_per_page' => -1,
    'post_status'    => ['publish', 'pending', 'draft'],
    'meta_query'     => [
      [
        'key'     => 'enigme_chasse_associee',
        'value'   => $chasse_id,
        'compare' => '=',
      ],
    ],
  ]);

  return $query->posts;
}


/**
 * Clear the cached enigme list for a chasse when an enigme is saved.
 *
 * @param int $post_id Post ID of the enigme being saved.
 */
function clear_enigmes_chasse_cache(int $post_id): void
{
    if (get_post_type($post_id) !== 'enigme') {
        return;
    }

    $chasse_id = (int) get_field('enigme_chasse_associee', $post_id);
    if (!$chasse_id) {
        return;
    }

    wp_cache_delete('enigmes_chasse_' . $chasse_id, 'chassesautresor');
}
add_action('save_post_enigme', 'clear_enigmes_chasse_cache', 20, 1);

function recalculate_chasse_cached_flags(int $chasse_id): void
{
    $has_solutions = function_exists('solution_existe_pour_objet')
        && solution_existe_pour_objet($chasse_id, 'chasse');
    $has_indices = function_exists('prochain_rang_indice')
        && prochain_rang_indice($chasse_id, 'chasse') > 1;

    $enigmes = recuperer_enigmes_associees($chasse_id);
    foreach ($enigmes as $eid) {
        if (
            !$has_solutions
            && function_exists('solution_existe_pour_objet')
            && solution_existe_pour_objet($eid, 'enigme')
        ) {
            $has_solutions = true;
        }
        if (
            !$has_indices
            && function_exists('prochain_rang_indice')
            && prochain_rang_indice($eid, 'enigme') > 1
        ) {
            $has_indices = true;
        }
        if ($has_solutions && $has_indices) {
            break;
        }
    }

    update_field('chasse_cache_has_solutions', $has_solutions ? 1 : 0, $chasse_id);
    update_field('chasse_cache_has_indices', $has_indices ? 1 : 0, $chasse_id);
}

/**
 * Met à jour les indicateurs mis en cache lors de la sauvegarde d'une énigme.
 *
 * @param int $post_id ID de l'énigme.
 * @return void
 */
function update_chasse_cached_flags_on_enigme_save(int $post_id): void
{
    $chasse_id = (int) get_field('enigme_chasse_associee', $post_id);
    if ($chasse_id) {
        recalculate_chasse_cached_flags($chasse_id);
    }
}
add_action('save_post_enigme', 'update_chasse_cached_flags_on_enigme_save', 20, 1);

/**
 * Met à jour les indicateurs mis en cache lors de la sauvegarde d'un indice.
 *
 * @param int $post_id ID de l'indice.
 * @return void
 */
function update_chasse_cached_flags_on_indice_save(int $post_id): void
{
    $cible = get_field('indice_cible_type', $post_id);
    $chasse_id = 0;

    if ($cible === 'chasse') {
        $chasse_id = (int) get_field('indice_chasse_linked', $post_id);
    } elseif ($cible === 'enigme') {
        $enigme_id = (int) get_field('indice_enigme_linked', $post_id);
        if ($enigme_id) {
            $chasse = recuperer_chasse_associee($enigme_id);
            $chasse_id = $chasse ? (int) $chasse->ID : 0;
        }
    }

    if ($chasse_id) {
        recalculate_chasse_cached_flags($chasse_id);
    }
}
add_action('save_post_indice', 'update_chasse_cached_flags_on_indice_save', 20, 1);

/**
 * Met à jour les indicateurs mis en cache lors de la sauvegarde d'une solution.
 *
 * @param int $post_id ID de la solution.
 * @return void
 */
function update_chasse_cached_flags_on_solution_save(int $post_id): void
{
    $cible = get_field('solution_cible_type', $post_id);
    $chasse_id = 0;

    if ($cible === 'chasse') {
        $chasse_id = (int) get_field('solution_chasse_linked', $post_id);
    } elseif ($cible === 'enigme') {
        $enigme_id = (int) get_field('solution_enigme_linked', $post_id);
        if ($enigme_id) {
            $chasse = recuperer_chasse_associee($enigme_id);
            $chasse_id = $chasse ? (int) $chasse->ID : 0;
        }
    }

    if ($chasse_id) {
        recalculate_chasse_cached_flags($chasse_id);
    }
}
add_action('save_post_solution', 'update_chasse_cached_flags_on_solution_save', 20, 1);


// ==================================================
// 📦 ASSIGNATION AUTOMATIQUES
// ==================================================
/**
 * 🔹 assigner_organisateur_automatiquement() → Assigne automatiquement l'organisateur d'une chasse lors de sa création.
 */

/**
 * 📌 Assigne automatiquement l'organisateur d'une chasse lors de sa création.
 *
 * 🔹 Vérifie si l'auteur de la chasse a le rôle "organisateur".
 * 🔹 Si oui, enregistre son ID dans le champ ACF "organisateur_id".
 * 🔹 Fonctionne uniquement à la création (pas à l'édition).
 *
 * @param int $post_id ID de la chasse en cours de sauvegarde.
 * @param WP_Post $post Objet du post.
 */
function assigner_organisateur_automatiquement($post_id, $post)
{
  if ($post->post_type !== 'chasse') {
    return;
  }

  $auteur_id = $post->post_author;

  if (est_organisateur($auteur_id)) {
    update_field('organisateur_id', $auteur_id, $post_id);
  } else {
    cat_debug("⚠️ Avertissement : L'auteur {$auteur_id} n'a pas un rôle valide (organisateur ou organisateur_creation).");
  }
}
add_action('save_post', 'assigner_organisateur_automatiquement', 10, 2);



// ==================================================
// 🔁 SYNCHRONISATION CHASSE ↔ ÉNIGMES
// ==================================================
/**
 * 🔹 synchroniser_cache_enigmes_chasse() → Lecture + correction automatique du champ ACF chasse_cache_enigmes
 * 🔹 verifier_chasse_cache_enigmes() → Compare énigmes attendues vs. cache
 * 🔹 verifier_cache_chasse_enigmes_valides() → Supprime les ID orphelins du cache
 * 🔹 synchroniser_relations_cache_enigmes() → Met à jour le champ relation avec le format attendu par ACF
 * 🔹 forcer_relation_enigme_dans_chasse_si_absente() → Depuis une fiche énigme, vérifie que la chasse associée référence bien cette énigme
 * 🔹 verifier_et_synchroniser_cache_enigmes_si_autorise() → Vérifie et synchronise le cache des énigmes liées à une chasse, avec protection par transient
 */




/**
 * 🔁 Synchronise le champ chasse_cache_enigmes avec la réalité des énigmes liées.
 *
 * @param int  $chasse_id        ID de la chasse concernée.
 * @param bool $forcer_recalcul  Si true, forcer la lecture réelle des énigmes liées.
 * @param bool $nettoyer_cache   Si true, retirer du cache les énigmes qui ne sont plus valides.
 * @return array Résultat de la synchronisation (détail et corrections).
 */
function synchroniser_cache_enigmes_chasse($chasse_id, $forcer_recalcul = false, $nettoyer_cache = false)
{
  cat_debug("🌀 [SYNC] Début de synchronisation pour chasse #$chasse_id");

  $resultat1 = verifier_chasse_cache_enigmes($chasse_id, $forcer_recalcul);
  $resultat2 = verifier_cache_chasse_enigmes_valides($chasse_id, $nettoyer_cache);

  $valide1     = $resultat1['valide']     ?? false;
  $valide2     = $resultat2['valide']     ?? false;
  $synchro1    = $resultat1['synchro']    ?? false;
  $synchro2    = $resultat2['synchro']    ?? false;
  $correction1 = $resultat1['correction'] ?? false;
  $correction2 = $resultat2['correction'] ?? false;
  $attendu     = $resultat1['attendu']    ?? [];
  $cache       = $resultat1['cache']      ?? [];
  $invalides   = $resultat2['invalides']  ?? [];

  cat_debug("📥 [ATTENDU] Énigmes réellement liées à la chasse : " . implode(', ', $attendu));
  cat_debug("📦 [CACHE AVANT] Contenu actuel de chasse_cache_enigmes : " . implode(', ', $cache));
  cat_debug("🗑️ [INVALIDES] Énigmes invalides détectées dans le cache : " . implode(', ', $invalides));

  if (!isset($resultat1['synchro'])) {
    cat_debug("⚠️ [INCOHÉRENCE] Clé 'synchro' manquante dans resultat1 (verifier_chasse_cache_enigmes)");
  }
  if (!isset($resultat2['synchro'])) {
    cat_debug("⚠️ [INCOHÉRENCE] Clé 'synchro' manquante dans resultat2 (verifier_cache_chasse_enigmes_valides)");
  }

  $ok = null;
  if ($correction1 || $correction2) {
    cat_debug("🔧 [ACTION] Mise à jour de chasse_cache_enigmes nécessaire");

    $ok = synchroniser_relations_cache_enigmes($chasse_id);

    if ($ok) {
      cat_debug("✅ [RÉSULTAT] Relations mises à jour proprement via synchroniser_relations_cache_enigmes()");
    } else {
      cat_debug("❌ [ÉCHEC] La synchronisation ACF relation a échoué pour la chasse #$chasse_id");
    }
  }

  cat_debug("🌀 [SYNC] Fin de synchronisation pour chasse #$chasse_id");

  return [
    'valide'                    => $valide1 && $valide2,
    'chasse_id'                 => $chasse_id,
    'synchro_realite_vs_cache'  => $synchro1,
    'synchro_cache_vs_realite'  => $synchro2,
    'correction_effectuee'     => $correction1 || $correction2,
    'liste_attendue'           => $attendu,
    'liste_cache'              => $cache,
    'invalides_dans_cache'     => $invalides,
  ];
}


/**
 * Vérifie la cohérence entre les énigmes liées à une chasse
 * (via leur champ `enigme_chasse_associee`) et le cache ACF
 * `chasse_cache_enigmes` présent sur la chasse.
 *
 * Peut corriger automatiquement le champ si désynchronisé.
 *
 * @param int $chasse_id
 * @param bool $mettre_a_jour Si true, met à jour automatiquement le cache
 * @return array Tableau avec la liste des ID trouvés et l’état de synchro
 */
function verifier_chasse_cache_enigmes($chasse_id, $mettre_a_jour = false)
{
  if (get_post_type($chasse_id) !== 'chasse') {
    return [
      'valide' => false,
      'erreur' => 'ID de chasse invalide.',
      'attendu' => [],
      'cache' => [],
    ];
  }

  // 🔍 Récupérer toutes les énigmes
  $posts = get_posts([
    'post_type'      => 'enigme',
    'post_status'    => ['draft', 'pending', 'publish'],
    'posts_per_page' => -1,
    'fields'         => 'ids',
  ]);

  $attendu_ids = [];

  foreach ($posts as $post_id) {
    $associee = get_field('enigme_chasse_associee', $post_id, false);

    // Peut être un entier ou un tableau
    if (is_array($associee)) {
      $associee_ids = array_map('intval', $associee);
    } else {
      $associee_ids = [(int)$associee];
    }

    if (in_array((int)$chasse_id, $associee_ids, true)) {
      $attendu_ids[] = (int)$post_id;
    }
  }

  // 📦 Cache actuel
  $cache = get_field('chasse_cache_enigmes', $chasse_id);
  $cache_ids = is_array($cache) ? array_map('intval', $cache) : [];

  // 🎯 Comparaison brute
  $diff1 = array_diff($attendu_ids, $cache_ids);
  $diff2 = array_diff($cache_ids, $attendu_ids);
  $synchronise = empty($diff1) && empty($diff2);

  if ($mettre_a_jour && !$synchronise) {
    update_field('chasse_cache_enigmes', $attendu_ids, $chasse_id);
  }

  return [
    'valide'     => true,
    'synchro'    => $synchronise,
    'attendu'    => $attendu_ids,
    'cache'      => $cache_ids,
    'correction' => $mettre_a_jour && !$synchronise,
  ];
}


/**
 * Vérifie que chaque énigme listée dans chasse_cache_enigmes
 * pointe bien vers la chasse via le champ enigme_chasse_associee.
 *
 * Cette vérification détecte les ID obsolètes ou erronés dans le cache.
 *
 * @param int $chasse_id
 * @param bool $retirer_si_invalide Si true, supprime les ID invalides du cache
 * @return array Résultat de la vérification (synchro, invalides, correction)
 */
function verifier_cache_chasse_enigmes_valides($chasse_id, $retirer_si_invalide = false)
{
  if (get_post_type($chasse_id) !== 'chasse') {
    return [
      'valide' => false,
      'erreur' => 'ID de chasse invalide.',
      'liste_cache' => [],
      'invalides' => [],
    ];
  }

  // 📦 Liste brute depuis le cache
  $cache = get_field('chasse_cache_enigmes', $chasse_id);
  $cache_ids = is_array($cache) ? array_map('intval', $cache) : [];

  $invalides = [];

  foreach ($cache_ids as $enigme_id) {
    if (get_post_type($enigme_id) !== 'enigme') {
      $invalides[] = $enigme_id;
      continue;
    }

    $chasse_associee = get_field('enigme_chasse_associee', $enigme_id);
    $chasse_associee_id = is_object($chasse_associee) ? $chasse_associee->ID : (int) $chasse_associee;

    if ((int)$chasse_associee_id !== (int)$chasse_id) {
      $invalides[] = $enigme_id;
    }
  }

  $est_synchro = empty($invalides);

  // 🔄 Correction : on retire les énigmes invalides du cache
  if ($retirer_si_invalide && !$est_synchro) {
    $nouvelle_liste = array_diff($cache_ids, $invalides);
    update_field('chasse_cache_enigmes', array_values($nouvelle_liste), $chasse_id);
  }

  return [
    'valide'     => true,
    'synchro'    => $est_synchro,
    'liste_cache' => $cache_ids,
    'invalides'  => $invalides,
    'correction' => $retirer_si_invalide && !$est_synchro,
  ];
}


/**
 * 🔁 Synchronise proprement le champ ACF "chasse_cache_enigmes" d'une chasse,
 * en utilisant la fonction centralisée mettre_a_jour_relation_acf() pour garantir le format.
 *
 * @param int $chasse_id ID du post "chasse"
 * @return bool True si au moins une relation a été enregistrée, False sinon.
 */
function synchroniser_relations_cache_enigmes(int $chasse_id): bool
{
    cat_debug("🔄 [SYNC] Début de synchronisation pour chasse #$chasse_id");

    if (get_post_type($chasse_id) !== 'chasse') {
        cat_debug("❌ [SYNC] ID $chasse_id n’est pas une chasse");
        return false;
    }

    // 🧩 Énigmes réellement liées à la chasse, triées par menu_order
    $ids_detectes = get_posts([
        'post_type'      => 'enigme',
        'post_status'    => ['draft', 'pending', 'publish'],
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'orderby'        => 'menu_order',
        'order'          => 'ASC',
        'meta_query'     => [
            [
                'key'     => 'enigme_chasse_associee',
                'value'   => $chasse_id,
                'compare' => 'LIKE',
            ],
        ],
    ]);

    // 🧮 Cache actuel
    $cache_actuel = get_field('chasse_cache_enigmes', $chasse_id, false);
    $cache_ids    = [];

    foreach ((array) $cache_actuel as $item) {
        $cache_ids[] = is_object($item) ? (int) $item->ID : (int) $item;
    }

    // 🧪 Comparaison stricte
    $diff_detectes = array_diff($ids_detectes, $cache_ids);
    $diff_cache    = array_diff($cache_ids, $ids_detectes);

    if (empty($diff_detectes) && empty($diff_cache)) {
        cat_debug("✅ [SYNC] Aucune mise à jour nécessaire pour chasse #$chasse_id");
        return true;
    }

    cat_debug("🔧 [SYNC] Cache obsolète → écrasement nécessaire pour chasse #$chasse_id");
    cat_debug("🗑️ Ancien cache : " . implode(', ', $cache_ids));
    cat_debug("🆕 Nouvel ensemble : " . implode(', ', $ids_detectes));

    $success = update_field('chasse_cache_enigmes', $ids_detectes, $chasse_id);

    if ($success) {
        cat_debug("✅ [SYNC] Mise à jour réussie de chasse_cache_enigmes pour chasse #$chasse_id");
    } else {
        cat_debug("❌ [SYNC] Échec de la mise à jour pour chasse #$chasse_id");
    }

    return (bool) $success;
}



/**
 * 🔁 Depuis une fiche énigme, vérifie que la chasse associée référence bien cette énigme.
 *
 * Cette vérification utilise un transient pour éviter toute surcharge répétée.
 * Si la relation est absente dans le champ ACF (champs_caches.chasse_cache_enigmes),
 * elle est ajoutée automatiquement via modifier_relation_acf().
 *
 * @param int $enigme_id ID du post de type "énigme"
 * @return void
 */
function forcer_relation_enigme_dans_chasse_si_absente(int $enigme_id): void
{
  if (get_post_type($enigme_id) !== 'enigme') return;

  $transient_key = "verif_chasse_relation_$enigme_id";
  if (get_transient($transient_key)) return;
  set_transient($transient_key, 'done', 5 * MINUTE_IN_SECONDS);

  $chasse = get_field('enigme_chasse_associee', $enigme_id, false);
  $chasse_id = is_object($chasse) ? $chasse->ID : (int)$chasse;

  if (!$chasse_id || get_post_type($chasse_id) !== 'chasse') {
    cat_debug("❌ [RELATION AUTO] Chasse non valide pour énigme #$enigme_id");
    return;
  }

  $liste = is_array(get_field('chasse_cache_enigmes', $chasse_id) ?? null) ? array_map('intval', get_field('chasse_cache_enigmes', $chasse_id)) : [];

  if (!in_array($enigme_id, $liste, true)) {
    $ok = modifier_relation_acf(
      $chasse_id,
      'chasse_cache_enigmes',
      $enigme_id,
      'field_67b740025aae0',
      'add'
    );

    if ($ok) {
      cat_debug("✅ [RELATION AUTO] Énigme #$enigme_id ajoutée à la chasse #$chasse_id (groupe champs_caches)");
    } else {
      cat_debug("❌ [RELATION AUTO] Échec ajout énigme #$enigme_id → chasse #$chasse_id");
    }
  }
}


/**
 * 🔁 Vérifie et synchronise le cache des énigmes liées à une chasse, avec protection par transient.
 *
 * ⚠️ Peut déclencher une mise à jour si le cache est désynchronisé.
 *
 * @param int $chasse_id
 * @return void
 */
function verifier_et_synchroniser_cache_enigmes_si_autorise(int $chasse_id): void
{
  if (!current_user_can('administrator') && !current_user_can(ROLE_ORGANISATEUR) && !current_user_can(ROLE_ORGANISATEUR_CREATION)) {
    return;
  }

  if (get_post_type($chasse_id) !== 'chasse') return;

  $transient_key = 'verif_sync_chasse_' . $chasse_id;

  if (!get_transient($transient_key)) {
    // Lancer la synchronisation réelle
    synchroniser_cache_enigmes_chasse($chasse_id, true, true);
    set_transient($transient_key, 'done', 30 * MINUTE_IN_SECONDS);
  }
}
