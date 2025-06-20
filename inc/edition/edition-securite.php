<?php
defined('ABSPATH') || exit;


// ==================================================
// 🔐 PROTECTION DES VISUELS (.htaccess)
// ==================================================
// 🔹 rediriger_upload_image_enigme() → Force l’upload dans /_enigmes/enigme-ID/
// 🔹 injecter_htaccess_protection_images_enigme() → Écrit le .htaccess de protection
// 🔹 verrouiller_visuels_enigme_si_nouveau_upload() → Ajoute le .htaccess après upload
// 🔹 filtrer_visuels_enigme_front() → Proxy visuel en front pour galerie
// 🔹 desactiver_htaccess_temporairement_enigme() → Désactivation temporaire via AJAX
// 🔹 reactiver_htaccess_protection_enigme_apres_save() → Restauration après save
// 🔹 verifier_expiration_desactivations_htaccess() → Vérifie les expirations
// 🔹 restaurer_htaccess_si_temporairement_desactive() → Restaure depuis .tmp si oublié
// 🔹 reactiver_htaccess_immediat_enigme() → Réactive immédiate via AJAX
// 🔹 get_expiration_htaccess_enigme() → Retourne le timestamp de fin
// 🔹 verrouillage_termine_enigme() → JS fin de compte à rebours
// 🔹 purger_htaccess_temp_enigmes() → Cron pour restaurer protections expirées
// 🔹 enregistrer_cron_purge_htaccess() → Enregistre la tâche cron


/**
 * Cette fonction permet de conserver les images visibles via ACF tout en les isolant
 * dans un répertoire structuré. Ce filtre est temporairement activé pendant l’upload
 * du champ `enigme_visuel_image`, pour éviter d’impacter les autres envois.
 *
 * @hook acf/upload_prefilter/name=enigme_visuel_image
 * @hook acf/upload_file/name=enigme_visuel_image
 */
function rediriger_upload_image_enigme($dirs)
{
  if (!isset($_REQUEST['post_id'])) return $dirs;

  $post_id = intval($_REQUEST['post_id']);
  if (get_post_type($post_id) !== 'enigme') return $dirs;

  $sous_dossier = '/_enigmes/enigme-' . $post_id;

  $dirs['subdir'] = $sous_dossier;
  $dirs['path']   = $dirs['basedir'] . $sous_dossier;
  $dirs['url']    = $dirs['baseurl'] . $sous_dossier;

  return $dirs;
}

// 🎯 Activation ciblée uniquement pendant l’upload du champ enigme_visuel_image
add_filter('acf/upload_prefilter/name=enigme_visuel_image', function ($errors, $file, $field) {
  add_filter('upload_dir', 'rediriger_upload_image_enigme');
  return $errors;
}, 10, 3);

add_filter('acf/upload_file/name=enigme_visuel_image', function ($file) {
  remove_filter('upload_dir', 'rediriger_upload_image_enigme');
  return $file;
});


/**
 * Injecte un fichier .htaccess dans le dossier /uploads/_enigmes/enigme-{ID}/
 * pour empêcher l’accès direct aux images, sauf depuis l’administration WordPress.
 *
 * Le fichier est écrit uniquement si :
 * - le post est de type 'enigme'
 * - le dossier existe (ou est créé)
 * - le fichier .htaccess n'existe pas déjà (sauf si $forcer = true)
 *
 * @param int  $post_id  ID de l’énigme
 * @param bool $forcer   Si true, écrase le fichier existant
 * @return bool
 */
function injecter_htaccess_protection_images_enigme($post_id, bool $forcer = false)
{
  $post_id = (int) $post_id;
  if ($post_id <= 0 || get_post_type($post_id) !== 'enigme') {
    error_log("❌ Post ID invalide ou type incorrect pour htaccess : {$post_id}");
    return false;
  }

  $upload_dir = wp_upload_dir();
  $base_dir = rtrim($upload_dir['basedir'], '/\\') . '/_enigmes/enigme-' . $post_id;

  if (!is_dir($base_dir)) {
    if (!wp_mkdir_p($base_dir)) {
      error_log("❌ Impossible de créer le dossier {$base_dir}");
      return false;
    }
    error_log("📁 Dossier créé : {$base_dir}");
  }

  $fichier_htaccess = $base_dir . '/.htaccess';
  $fichier_tmp = $fichier_htaccess . '.tmp';

  if (!$forcer && file_exists($fichier_htaccess)) {
    error_log("ℹ️ .htaccess déjà présent pour énigme {$post_id}, pas de réécriture.");
    return true;
  }

  // Supprime le fichier temporaire si présent
  if (file_exists($fichier_tmp)) {
    unlink($fichier_tmp);
    error_log("🧹 Fichier temporaire .htaccess.tmp supprimé");
  }

  $contenu = <<<HTACCESS
# Protection des images de l'énigme {$post_id}
<IfModule mod_rewrite.c>
RewriteEngine On

# ✅ Autorise uniquement l’accès depuis l’administration WordPress
RewriteCond %{REQUEST_URI} ^/wp-admin/ [OR]
RewriteCond %{HTTP_REFERER} ^(/wp-admin/|https?://[^/]+/wp-admin/) [NC]
RewriteRule . - [L]

# ❌ Blocage par défaut
<FilesMatch "\\.(jpg|jpeg|png|gif|webp)\$">
  Require all denied
</FilesMatch>
</IfModule>
HTACCESS;

  if (file_put_contents($fichier_htaccess, $contenu, LOCK_EX) === false) {
    error_log("❌ Échec d’écriture du fichier .htaccess pour énigme {$post_id}");
    return false;
  }

  error_log("✅ .htaccess injecté avec succès pour énigme {$post_id}");
  return true;
}


/**
 * Injecte un .htaccess de protection juste après l’ajout d’un visuel
 * dans le champ `enigme_visuel_image`. S’appuie sur acf/save_post.
 *
 * @hook acf/save_post
 * @param int $post_id
 */
function verrouiller_visuels_enigme_si_nouveau_upload($post_id)
{
  if (get_post_type($post_id) !== 'enigme') return;

  // Récupère les images actuelles (gallerie)
  $images = get_field('enigme_visuel_image', $post_id, false);
  if (!$images || !is_array($images)) return;

  // Vérifie si le .htaccess est déjà en place
  $upload_dir = wp_upload_dir();
  $dossier = $upload_dir['basedir'] . '/_enigmes/enigme-' . $post_id;
  $fichier_htaccess = $dossier . '/.htaccess';

  if (!file_exists($fichier_htaccess)) {
    injecter_htaccess_protection_images_enigme($post_id, true);
  }
}
add_action('acf/save_post', 'verrouiller_visuels_enigme_si_nouveau_upload', 20);


/**
 * pour utiliser le proxy sécurisé /voir-image-enigme
 *
 * @hook acf/format_value/type=gallery
 *
 * @param array|null $images
 * @param string $post_id
 * @param array $field
 * @return array|null
 */
function filtrer_visuels_enigme_front($images, $post_id, $field)
{
  error_log('[DEBUG] filtre gallery appelé pour post ID : ' . $post_id);
  error_log("[✔️ filtre ACF gallery actif] post_id = $post_id | champ = " . ($field['name'] ?? 'inconnu'));


  if (is_admin()) return $images;
  if (!is_array($images)) return $images;

  $taille = 'medium'; // peut être 'full', 'thumbnail', etc.

  foreach ($images as &$image) {
    if (!isset($image['ID'])) continue;

    $image_id = $image['ID'];
    $image['url'] = site_url('/voir-image-enigme?id=' . $image_id . '&taille=' . $taille);
  }

  return $images;
}
add_filter('acf/format_value/type=gallery', 'filtrer_visuels_enigme_front', 20, 3);



/**
 * @hook wp_ajax_desactiver_htaccess_enigme
 */
function desactiver_htaccess_temporairement_enigme()
{
  if (!current_user_can('edit_posts')) {
    wp_send_json_error('Non autorisé');
  }

  $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

  if (!$post_id || get_post_type($post_id) !== 'enigme') {
    wp_send_json_error('ID invalide');
  }

  if (!utilisateur_peut_modifier_post($post_id)) {
    wp_send_json_error('Droits insuffisants');
  }

  $upload_dir = wp_upload_dir();
  $dossier = $upload_dir['basedir'] . '/_enigmes/enigme-' . $post_id;
  $fichier_htaccess = $dossier . '/.htaccess';
  $fichier_tmp = $fichier_htaccess . '.tmp';

  $message = '';

  // ✅ Si .htaccess existe et pas déjà désactivé
  if (file_exists($fichier_htaccess)) {
    if (!@rename($fichier_htaccess, $fichier_tmp)) {
      error_log("❌ Impossible de renommer .htaccess vers .tmp pour énigme {$post_id}");
      wp_send_json_error("Erreur désactivation");
    }
    error_log("✅ .htaccess désactivé temporairement pour énigme {$post_id}");
    $message = 'Protection désactivée (nouveau .tmp)';
  } elseif (file_exists($fichier_tmp)) {
    error_log("ℹ️ .htaccess déjà désactivé pour énigme {$post_id} – renouvellement délai");
    $message = 'Déjà désactivé – délai renouvelé';
  } else {
    error_log("⚠️ Aucun .htaccess trouvé pour énigme {$post_id}");
    wp_send_json_error("Fichier introuvable");
  }

  // 🔁 Dans tous les cas on prolonge pour 3 minutes
  set_transient('htaccess_timeout_enigme_' . $post_id, time() + 180, 180);

  wp_send_json_success($message);
}
add_action('wp_ajax_desactiver_htaccess_enigme', 'desactiver_htaccess_temporairement_enigme');


/**
 * @hook acf/save_post
 */
function reactiver_htaccess_protection_enigme_apres_save($post_id)
{
  if (get_post_type($post_id) !== 'enigme') return;

  // Réinjecte directement
  injecter_htaccess_protection_images_enigme($post_id, true);

  // Supprime le .tmp s’il traîne
  $upload_dir = wp_upload_dir();
  $fichier_tmp = $upload_dir['basedir'] . '/_enigmes/enigme-' . $post_id . '/.htaccess.tmp';
  if (file_exists($fichier_tmp)) {
    unlink($fichier_tmp);
    error_log("🧼 .htaccess.tmp supprimé après enregistrement de l’énigme {$post_id}");
  }

  // Supprime le transient
  delete_transient('htaccess_timeout_enigme_' . $post_id);
}
add_action('acf/save_post', 'reactiver_htaccess_protection_enigme_apres_save', 99);


/**
 *
 * @hook template_redirect
 */
function verifier_expiration_desactivations_htaccess()
{
  $all_enigmes = get_posts([
    'post_type'      => 'enigme',
    'posts_per_page' => -1,
    'fields'         => 'ids',
    'post_status'    => 'any',
  ]);

  $now = time();

  foreach ($all_enigmes as $post_id) {
    $expiration = get_transient('htaccess_timeout_enigme_' . $post_id);
    if (!$expiration) continue;

    $upload_dir = wp_upload_dir();
    $dossier = $upload_dir['basedir'] . '/_enigmes/enigme-' . $post_id;
    $htaccess     = $dossier . '/.htaccess';
    $htaccess_tmp = $htaccess . '.tmp';

    // ✅ Expiration atteinte : on réactive
    if ($now > $expiration) {
      if (file_exists($htaccess_tmp) && !file_exists($htaccess)) {
        if (rename($htaccess_tmp, $htaccess)) {
          error_log("⏲️ Fallback restauration .htaccess (expiration dépassée) pour énigme {$post_id}");
        } else {
          error_log("❌ Échec restauration .htaccess depuis .tmp pour énigme {$post_id}");
        }
      } else {
        // sinon réécrit quand même pour être sûr
        injecter_htaccess_protection_images_enigme($post_id, true);
        error_log("⏱️ Expiration atteinte : .htaccess réinjecté pour énigme {$post_id}");
      }

      // Nettoyage
      delete_transient('htaccess_timeout_enigme_' . $post_id);
    }
  }
}


/**
 * Si un fichier .htaccess.tmp est présent mais qu’aucun .htaccess n’existe,
 * cette fonction le restaure.
 *
 * @param int $post_id
 * @return void
 */
function restaurer_htaccess_si_temporairement_desactive($post_id)
{
  $upload_dir = wp_upload_dir();
  $dossier = $upload_dir['basedir'] . '/_enigmes/enigme-' . $post_id;
  $htaccess = $dossier . '/.htaccess';
  $tmp = $htaccess . '.tmp';

  if (!file_exists($tmp)) return; // rien à faire
  if (file_exists($htaccess)) {
    // .htaccess existe déjà, donc on peut supprimer le temporaire s’il traîne
    unlink($tmp);
    error_log("🧹 .htaccess.tmp supprimé (déjà restauré) pour énigme {$post_id}");
    return;
  }

  if (rename($tmp, $htaccess)) {
    error_log("🔁 .htaccess restauré depuis .tmp pour énigme {$post_id}");
  } else {
    error_log("❌ Impossible de restaurer .htaccess depuis .tmp pour énigme {$post_id}");
  }
}

function reactiver_htaccess_immediat_enigme()
{
  $post_id = intval($_POST['post_id'] ?? 0);
  if (!$post_id || get_post_type($post_id) !== 'enigme') {
    wp_send_json_error('ID invalide');
  }

  if (!utilisateur_peut_modifier_post($post_id)) {
    wp_send_json_error('Non autorisé');
  }

  injecter_htaccess_protection_images_enigme($post_id, true);

  // Nettoyage
  $upload_dir = wp_upload_dir();
  $tmp = $upload_dir['basedir'] . '/_enigmes/enigme-' . $post_id . '/.htaccess.tmp';
  if (file_exists($tmp)) {
    unlink($tmp);
  }

  delete_transient('htaccess_timeout_enigme_' . $post_id);

  wp_send_json_success('Protection restaurée immédiatement');
}
add_action('wp_ajax_reactiver_htaccess_immediat_enigme', 'reactiver_htaccess_immediat_enigme');



add_action('wp_ajax_get_expiration_htaccess_enigme', 'get_expiration_htaccess_enigme');

/**
 * @hook wp_ajax_get_expiration_htaccess_enigme
 */
function get_expiration_htaccess_enigme()
{
  if (!current_user_can('edit_posts')) {
    wp_send_json_error('Non autorisé');
  }

  $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
  if (!$post_id || get_post_type($post_id) !== 'enigme') {
    wp_send_json_error('ID invalide');
  }

  if (!utilisateur_peut_modifier_post($post_id)) {
    wp_send_json_error('Droits insuffisants');
  }

  $upload_dir = wp_upload_dir();
  $dossier = $upload_dir['basedir'] . '/_enigmes/enigme-' . $post_id;
  $fichier_htaccess_tmp = $dossier . '/.htaccess.tmp';
  $fichier_htaccess = $dossier . '/.htaccess';

  // ⛔ Aucun .tmp présent
  if (!file_exists($fichier_htaccess_tmp)) {
    wp_send_json_error('Aucune désactivation active');
  }

  $transient_key = 'htaccess_timeout_enigme_' . $post_id;
  $expiration = get_transient($transient_key);

  // ✅ Transient encore actif → retour normal
  if ($expiration && is_numeric($expiration) && $expiration > time()) {
    wp_send_json_success(['timestamp' => $expiration]);
  }

  // ❌ Transient expiré ou absent, mais .tmp encore là → on restaure
  @unlink($fichier_htaccess); // au cas où résidu
  if (@rename($fichier_htaccess_tmp, $fichier_htaccess)) {
    error_log("🔒 htaccess restauré automatiquement (expiration dépassée) pour énigme {$post_id}");
  } else {
    error_log("⚠️ Échec restauration htaccess expirée pour énigme {$post_id}");
  }

  delete_transient($transient_key);
  wp_send_json_error('Délai expiré');
}
add_action('wp_ajax_get_expiration_htaccess_enigme', 'get_expiration_htaccess_enigme');


/* @hook wp_ajax_verrouillage_termine_enigme
 * Déclenché automatiquement par JS à la fin du compte à rebours
 */
add_action('wp_ajax_verrouillage_termine_enigme', function () {
  $post_id = intval($_POST['post_id'] ?? 0);
  if (!$post_id || get_post_type($post_id) !== 'enigme') {
    wp_send_json_error('ID invalide');
  }

  $upload_dir = wp_upload_dir();
  $dossier = $upload_dir['basedir'] . '/_enigmes/enigme-' . $post_id;

  if (file_exists($dossier . '/.htaccess.tmp') && !file_exists($dossier . '/.htaccess')) {
    rename($dossier . '/.htaccess.tmp', $dossier . '/.htaccess');
    error_log("🔁 .htaccess restauré depuis .tmp (expiration JS) pour énigme #$post_id");
  }

  delete_transient('htaccess_timeout_enigme_' . $post_id);
  wp_send_json_success('Verrouillage terminé et protection rétablie');
});



//
// 🔁 PURGE AUTOMATIQUE DES PROTECTIONS TEMPORAIRES
//

/**
 *
 * Vérifie chaque dossier /_enigmes/enigme-ID/ :
 * - Si un fichier .htaccess.tmp est présent
 * - Et que le transient associé a expiré
 * Alors le fichier est renommé en .htaccess et la protection est réactivée.
 *
 * Appelée via le cron WordPress toutes les 5 minutes.
 * 
 */
function purger_htaccess_temp_enigmes()
{
  error_log('🟡 CRON lancé : purge htaccess');

  $upload_dir = wp_upload_dir();
  $base = $upload_dir['basedir'] . '/_enigmes';

  if (!is_dir($base)) {
    error_log('❌ Base des énigmes introuvable');
    return;
  }

  foreach (glob($base . '/enigme-*') as $dossier) {
    if (!is_dir($dossier)) continue;

    $post_id = intval(basename($dossier, '/'));

    $fichier_tmp = $dossier . '/.htaccess.tmp';
    if (!file_exists($fichier_tmp)) {
      continue;
    }

    $transient = get_transient('htaccess_timeout_enigme_' . $post_id);
    error_log("⏱️ Transient actuel pour $post_id : " . var_export($transient, true));

    if (!$transient || $transient < time()) {
      error_log("🟥 Transient expiré ou absent pour $post_id → tentative restauration");

      $fichier_final = $dossier . '/.htaccess';
      if (@rename($fichier_tmp, $fichier_final)) {
        delete_transient('htaccess_timeout_enigme_' . $post_id);
        error_log("🟢 Restauration OK htaccess pour énigme $post_id");
      } else {
        error_log("⚠️ Échec restauration pour énigme $post_id");
      }
    } else {
      error_log("🟩 Transient encore actif pour $post_id");
    }
  }
}


/**
 *
 * Crée la tâche planifiée `tache_purge_htaccess_enigmes` si elle n’existe pas.
 * Reliée à la fonction `purger_htaccess_temp_enigmes()`.
 */
function enregistrer_cron_purge_htaccess()
{
  if (!wp_next_scheduled('tache_purge_htaccess_enigmes')) {
    wp_schedule_event(time(), 'every_5_minutes', 'tache_purge_htaccess_enigmes');
  }
}
add_action('wp', 'enregistrer_cron_purge_htaccess');

// 🔁 Tâche réellement exécutée toutes les 5 minutes
add_action('tache_purge_htaccess_enigmes', 'purger_htaccess_temp_enigmes');

// ⏱️ Intervalle personnalisé (5 minutes)
add_filter('cron_schedules', function ($schedules) {
  $schedules['every_5_minutes'] = [
    'interval' => 300,
    'display'  => __('Toutes les 5 minutes')
  ];
  return $schedules;
});
