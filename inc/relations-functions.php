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

  // Rechercher l'ID du post organisateur lié à l'utilisateur
  $post_id = $wpdb->get_var($wpdb->prepare(
    "SELECT post_id FROM $wpdb->postmeta 
        WHERE meta_key = 'utilisateurs_associes' 
        AND meta_value LIKE %s LIMIT 1",
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
  // ✅ Lecture directe via ACF du groupe
  $champs_caches = get_field('champs_caches', $chasse_id);

  if (!empty($champs_caches['chasse_cache_organisateur'])) {
    $relation = $champs_caches['chasse_cache_organisateur'];

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
    'post_status'    => 'any', // 🔍 Vérifier toutes les chasses, y compris les brouillons
    'meta_query'     => [
      [
        'key'     => 'champs_caches_organisateur_chasse',
        'value'   => '"' . $organisateur_id . '"', // 🔄 Ajout de guillemets pour matcher dans un tableau sérialisé
        'compare' => 'LIKE'
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
  return new WP_Query([
    'post_type'      => 'chasse',
    'posts_per_page' => -1,
    'post_status'    => ['publish', 'pending'], // Inclure les chasses en attente
    'meta_query'     => [
      [
        'key'     => 'champs_caches_organisateur_chasse', // Champ correct
        'value'   => '"' . strval($organisateur_id) . '"', // Recherche dans le tableau sérialisé
        'compare' => 'LIKE'
      ]
    ]
  ]);
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
    error_log("❌ [recuperer_enigmes_associees] Appel invalide pour ID $chasse_id");
    return [];
  }

  $groupe = get_field('champs_caches', $chasse_id);
  $liste_brute = $groupe['chasse_cache_enigmes'] ?? [];

  // Extraction des IDs (objet ou int)
  $ids = [];

  foreach ($liste_brute as $item) {
    $ids[] = is_object($item) && isset($item->ID) ? (int)$item->ID : (int)$item;
  }

  // Détection et log des doublons
  $doublons = array_diff_key($ids, array_unique($ids));
  if (!empty($doublons)) {
    error_log("⚠️ [recuperer_enigmes_associees] Doublons détectés pour la chasse #$chasse_id : " . implode(', ', $doublons));
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
    'post_status'    => ['publish', 'pending', 'draft'], // extensible
    'orderby'        => 'menu_order', // ou autre critère futur
    'order'          => 'ASC',
    'meta_query'     => [
      [
        'key'     => 'enigme_chasse_associee',
        'value'   => '"' . $chasse_id . '"',
        'compare' => 'LIKE',
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
        'value'   => '"' . $chasse_id . '"',
        'compare' => 'LIKE',
      ],
    ],
  ]);

  return $query->posts;
}


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
  $user = get_userdata($auteur_id);

  // Accepte organisateur ET organisateur_creation
  if (array_intersect((array) $user->roles, ['organisateur', 'organisateur_creation'])) {
    update_field('organisateur_id', $auteur_id, $post_id);
  } else {
    error_log("⚠️ Avertissement : L'auteur {$auteur_id} n'a pas un rôle valide (organisateur ou organisateur_creation).");
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
  error_log("🌀 [SYNC] Début de synchronisation pour chasse #$chasse_id");

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

  error_log("📥 [ATTENDU] Énigmes réellement liées à la chasse : " . implode(', ', $attendu));
  error_log("📦 [CACHE AVANT] Contenu actuel de chasse_cache_enigmes : " . implode(', ', $cache));
  error_log("🗑️ [INVALIDES] Énigmes invalides détectées dans le cache : " . implode(', ', $invalides));

  if (!isset($resultat1['synchro'])) {
    error_log("⚠️ [INCOHÉRENCE] Clé 'synchro' manquante dans resultat1 (verifier_chasse_cache_enigmes)");
  }
  if (!isset($resultat2['synchro'])) {
    error_log("⚠️ [INCOHÉRENCE] Clé 'synchro' manquante dans resultat2 (verifier_cache_chasse_enigmes_valides)");
  }

  $ok = null;
  if ($correction1 || $correction2) {
    error_log("🔧 [ACTION] Mise à jour de chasse_cache_enigmes nécessaire");

    $ok = synchroniser_relations_cache_enigmes($chasse_id);

    if ($ok) {
      error_log("✅ [RÉSULTAT] Relations mises à jour proprement via synchroniser_relations_cache_enigmes()");
    } else {
      error_log("❌ [ÉCHEC] La synchronisation ACF relation a échoué pour la chasse #$chasse_id");
    }
  }

  error_log("🌀 [SYNC] Fin de synchronisation pour chasse #$chasse_id");

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
function synchroniser_relations_cache_enigmes($chasse_id): bool
{
  if (get_post_type($chasse_id) !== 'chasse') {
    error_log("❌ [SYNC RELATIONS] Post #$chasse_id n’est pas de type chasse.");
    return false;
  }

  // Récupérer toutes les énigmes existantes
  $posts = get_posts([
    'post_type'      => 'enigme',
    'post_status'    => ['draft', 'pending', 'publish'],
    'posts_per_page' => -1,
    'fields'         => 'ids',
  ]);

  $ids_detectes = [];

  foreach ($posts as $enigme_id) {
    $valeur = get_post_meta($enigme_id, 'enigme_chasse_associee', true);

    if (is_array($valeur)) {
      $associees = array_map('intval', $valeur);
    } else {
      $associees = [(int)$valeur];
    }

    if (in_array((int)$chasse_id, $associees, true)) {
      $ids_detectes[] = $enigme_id;
    }
  }

  if (empty($ids_detectes)) {
    error_log("ℹ️ [SYNC RELATIONS] Aucune énigme détectée pour la chasse #$chasse_id.");
    return false;
  }

  // 🔁 Met à jour chaque ID un par un via ta fonction fiable
  $ok_global = true;
  foreach ($ids_detectes as $enigme_id) {
    $ok = mettre_a_jour_relation_acf(
      $chasse_id,
      'chasse_cache_enigmes',
      $enigme_id,
      'field_67b740025aae0',
      'champs_caches_'
    );

    if (!$ok) {
      error_log("❌ [SYNC RELATIONS] Échec ajout de l’énigme #$enigme_id à la chasse #$chasse_id");
      $ok_global = false;
    }
  }

  if ($ok_global) {
    error_log("✅ [SYNC RELATIONS] Mise à jour complète de la chasse #$chasse_id → " . implode(', ', $ids_detectes));
  }

  return $ok_global;
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
    error_log("❌ [RELATION AUTO] Chasse non valide pour énigme #$enigme_id");
    return;
  }

  $groupe = get_field('champs_caches', $chasse_id);
  $liste = is_array($groupe['chasse_cache_enigmes'] ?? null) ? array_map('intval', $groupe['chasse_cache_enigmes']) : [];

  if (!in_array($enigme_id, $liste, true)) {
    $ok = modifier_relation_acf(
      $chasse_id,
      'champs_caches_chasse_cache_enigmes',
      $enigme_id,
      'field_67b740025aae0',
      'add'
    );

    if ($ok) {
      error_log("✅ [RELATION AUTO] Énigme #$enigme_id ajoutée à la chasse #$chasse_id (groupe champs_caches)");
    } else {
      error_log("❌ [RELATION AUTO] Échec ajout énigme #$enigme_id → chasse #$chasse_id");
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
  if (!current_user_can('administrator') && !current_user_can('organisateur') && !current_user_can('organisateur_creation')) {
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
