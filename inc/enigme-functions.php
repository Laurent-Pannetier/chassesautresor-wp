 <?php
defined( 'ABSPATH' ) || exit;


//
// 👤 STATUT UTILISATEUR – ÉNIGMES
// 🎯 TENTATIVES – ÉNIGMES (pointage & limitations)
// 🧩 CONTRÔLES ET RÉGLAGES AVANCÉS
// 🖼️️ AFFICHAGE DES VISUELS D’ÉNIGMES
// 🧩 AFFICHAGE DES ÉNIGMES – TEMPLATE UNIQUE & VARIANTS
// 📬 GESTION DES RÉPONSES MANUELLES AUX ÉNIGMES
//


// ==================================================
// 👤 STATUT UTILISATEUR – ÉNIGMES
// ==================================================
/**
 * 🔹 enigme_get_statut_utilisateur() → Retourne le statut actuel de l’utilisateur pour une énigme.
 * 🔹 enigme_mettre_a_jour_statut_utilisateur() → Met à jour le statut utilisateur avec vérification de transition.
 */

 
/**
 * Récupère le statut actuel de l’utilisateur pour une énigme.
 *
 * Valeurs possibles :
 * - non_souscrite
 * - en_cours
 * - resolue
 * - terminee
 * - echouee
 * - abandonnee
 *
 * @param int $enigme_id
 * @param int $user_id
 * @return string
 */
function enigme_get_statut_utilisateur($enigme_id, $user_id) {
    if (!$enigme_id || !$user_id) {
        return 'non_souscrite';
    }

    $meta_key = 'enigme_' . $enigme_id . '_statut';
    $statut = get_user_meta($user_id, $meta_key, true);

    if (empty($statut)) {
        return 'non_souscrite';
    }

    return $statut;
}


/**
 * Met à jour le statut utilisateur pour une énigme.
 * Vérifie que la transition est autorisée.
 *
 * @param int $enigme_id
 * @param int $user_id
 * @param string $nouveau_statut
 * @return bool True si le statut a été modifié, false sinon
 */
function enigme_mettre_a_jour_statut_utilisateur($enigme_id, $user_id, $nouveau_statut) {
    if (!$enigme_id || !$user_id) return false;

    $meta_key = 'enigme_' . $enigme_id . '_statut';
    $ancien_statut = get_user_meta($user_id, $meta_key, true) ?: 'non_souscrite';

    // Liste des statuts autorisés
    $valides = ['non_souscrite', 'en_cours', 'resolue', 'terminee', 'echouee', 'abandonnee'];
    if (!in_array($nouveau_statut, $valides, true)) {
        return false;
    }

    // Sécurité : autoriser uniquement les transitions valides
    $transitions_valides = [
        'non_souscrite' => ['en_cours'],
        'en_cours'      => ['resolue', 'echouee', 'terminee'],
        'resolue'       => [], // état final
        'terminee'      => [], // état final
        'echouee'       => [], // état final
        'abandonnee'    => ['en_cours'], // optionnel : retour autorisé
    ];

    $autorisations = $transitions_valides[$ancien_statut] ?? [];
    if (!in_array($nouveau_statut, $autorisations, true)) {
        return false;
    }

    // Mise à jour
    update_user_meta($user_id, $meta_key, $nouveau_statut);

    return true;
}



// ==================================================
// 🎯 TENTATIVES – ÉNIGMES (pointage & limitations)
// ==================================================
/**
 * 🔹 enigme_get_tentatives_restantes() → Retourne le nombre de tentatives restantes pour un utilisateur sur une énigme.
 */

/**
 * Retourne le nombre de tentatives restantes pour un utilisateur sur une énigme, pour aujourd’hui.
 * Tient compte de la limite quotidienne définie dans `enigme_tentative_max`.
 *
 * @param int $enigme_id
 * @param int $user_id
 * @return int|null Null si illimité, sinon nombre de tentatives restantes
 */
function enigme_get_tentatives_restantes($enigme_id, $user_id) {
    if (!$enigme_id || !$user_id) return null;

    $limite = get_field('enigme_tentative_max', $enigme_id);
    if (!$limite || $limite <= 0) {
        return null; // Tentatives illimitées
    }

    // Clé formatée pour la date du jour
    $date = current_time('Ymd'); // Ex: 20250430
    $meta_key = "enigme_{$enigme_id}_tentatives_{$date}";

    $deja_fait = (int) get_user_meta($user_id, $meta_key, true);
    return max(0, $limite - $deja_fait);
}


/**
 * Enregistre une tentative pour un utilisateur donné sur une énigme,
 * en incrémentant le compteur du jour.
 *
 * @param int $enigme_id
 * @param int $user_id
 * @return bool True si la tentative a été enregistrée, false sinon
 */
function enigme_enregistrer_tentative($enigme_id, $user_id) {
    if (!$enigme_id || !$user_id) return false;

    $date = current_time('Ymd');
    $meta_key = "enigme_{$enigme_id}_tentatives_{$date}";

    $compteur = (int) get_user_meta($user_id, $meta_key, true);
    $compteur++;

    update_user_meta($user_id, $meta_key, $compteur);

    // 🔍 Log (désactivable plus tard)
    $titre = get_the_title($enigme_id);
    error_log("[Enigme] Tentative enregistrée pour #$user_id sur énigme #$enigme_id ($titre) → $compteur tentative(s)");

    return true;
}


/**
 * Vérifie si l’utilisateur a dépassé le nombre de tentatives autorisées aujourd’hui pour une énigme.
 *
 * @param int $enigme_id
 * @param int $user_id
 * @return bool True si le nombre de tentatives est dépassé, false sinon
 */
function enigme_tentatives_depassees($enigme_id, $user_id) {
    if (!$enigme_id || !$user_id) return false;

    $limite = get_field('enigme_tentative_max', $enigme_id);
    if (!$limite || $limite <= 0) {
        return false; // Illimité = jamais dépassé
    }

    $date = current_time('Ymd');
    $meta_key = "enigme_{$enigme_id}_tentatives_{$date}";

    $compteur = (int) get_user_meta($user_id, $meta_key, true);
    return $compteur >= $limite;
}


/**
 * Réinitialise les tentatives d’un utilisateur pour une énigme donnée.
 *
 * @param int $enigme_id
 * @param int $user_id
 * @param bool $toutes True pour supprimer toutes les tentatives (par défaut : seulement aujourd’hui)
 * @return int Nombre de lignes supprimées
 */
function enigme_reinitialiser_tentatives($enigme_id, $user_id, $toutes = false) {
    if (!$enigme_id || !$user_id) return 0;

    global $wpdb;
    $prefix = "enigme_{$enigme_id}_tentatives_";

    if ($toutes) {
        // Supprime toutes les tentatives (toutes dates)
        $like = $wpdb->esc_like($prefix) . '%';
        return (int) $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key LIKE %s",
                $user_id,
                $like
            )
        );
    } else {
        // Supprime uniquement la tentative du jour
        $date = current_time('Ymd');
        $meta_key = $prefix . $date;
        return delete_user_meta($user_id, $meta_key) ? 1 : 0;
    }
}


// ==================================================
// 🧩 CONTRÔLES ET RÉGLAGES AVANCÉS – ÉNIGMES
// ==================================================
/**
 * 🔹 enigme_get_liste_prerequis_possibles() → Retourne les autres énigmes de la même chasse pouvant être définies comme prérequis.
 * 🔹 get_cta_enigme() → Retourne les informations d'affichage du bouton CTA en fonction du statut et du contexte de l'énigme.
 */


/**
 * 🔍 Retourne la liste des énigmes pouvant être sélectionnées comme prérequis.
 *
 * @param int $enigme_id ID de l’énigme en cours
 * @return array Tableau associatif [id => titre]
 */
function enigme_get_liste_prerequis_possibles(int $enigme_id): array {
    $chasse = get_field('enigme_chasse_associee', $enigme_id, false);
    $chasse_id = is_object($chasse) ? $chasse->ID : (int)$chasse;

    if (!$chasse_id) {
        error_log("[DEBUG] Aucun chasse associée trouvée pour énigme #$enigme_id");
        return [];
    }

    $ids = recuperer_enigmes_associees($chasse_id);
    if (empty($ids)) {
        error_log("[DEBUG] Aucune énigme associée à la chasse #$chasse_id");
        return [];
    }

    $resultats = [];

    foreach ($ids as $id) {
        if ((int)$id === (int)$enigme_id) continue;

        $mode = get_field('enigme_mode_validation', $id);
        $titre = get_the_title($id);

        if (
            $mode !== 'aucune' &&
            $mode !== null &&
            stripos($titre, 'nouvelle énigme') !== 0
        ) {
            $resultats[$id] = $titre;
        }
    }

    error_log("[DEBUG] Liste brute des prérequis possibles : " . print_r($resultats, true));
    return $resultats;
}



/**
 * Retourne les données nécessaires à l'affichage du bouton CTA d'une énigme
 * selon le statut utilisateur, le coût en points, et les tentatives restantes.
 *
 * @param int $enigme_id
 * @param int|null $user_id
 * @return array{
 *   type: string,             // Nom logique du CTA (ex: 'decouvrir', 'reessayer')
 *   label: string,            // Texte affiché sur le bouton
 *   sous_label: string|null, // Texte d'aide ou info affiché sous le bouton
 *   points: int|null,         // Coût en points si applicable
 *   action: string            // Type d'action attendue ('formulaire', 'paiement', 'message', etc.)
 * }
 */
function get_cta_enigme(int $enigme_id, ?int $user_id = null): array {
    $user_id = $user_id ?: get_current_user_id();
    $statut = enigme_get_statut($enigme_id, $user_id);
    $points = (int) get_field('enigme_tentative_cout_points', $enigme_id);
    $limite = (int) get_field('enigme_tentative_max', $enigme_id);

    $tentatives_restantes = null;
    if ($points === 0 && $user_id) {
        $tentatives_restantes = enigme_get_tentatives_restantes($enigme_id, $user_id);
    }

    switch ($statut) {
        case 'resolue':
            return [
                'type'       => 'revoir',
                'label'      => 'Revoir',
                'sous_label' => 'Vous avez déjà résolu cette énigme.',
                'points'     => null,
                'action'     => 'formulaire',
            ];

        case 'echouee':
            return [
                'type'       => 'reessayer',
                'label'      => 'Réessayer',
                'sous_label' => ($points === 0 && $tentatives_restantes !== null)
                                ? "$tentatives_restantes tentative(s) restante(s) aujourd'hui"
                                : null,
                'points'     => $points ?: null,
                'action'     => 'formulaire',
            ];

        case 'abandonnee':
            return [
                'type'       => 'reprendre',
                'label'      => 'Reprendre',
                'sous_label' => 'Revenir là où vous en étiez.',
                'points'     => null,
                'action'     => 'formulaire',
            ];

        case 'en_cours':
            return [
                'type'       => 'continuer',
                'label'      => 'Continuer',
                'sous_label' => null,
                'points'     => null,
                'action'     => 'formulaire',
            ];

        case 'non_souscrite':
            if ($points > 0) {
                return [
                    'type'       => 'debloquer',
                    'label'      => 'Débloquer',
                    'sous_label' => "$points points",
                    'points'     => $points,
                    'action'     => 'paiement',
                ];
            }
            return [
                'type'       => 'decouvrir',
                'label'      => 'Découvrir',
                'sous_label' => ($tentatives_restantes !== null)
                                ? "$tentatives_restantes tentative(s) restante(s) aujourd'hui"
                                : null,
                'points'     => null,
                'action'     => 'formulaire',
            ];

        default:
            return [
                'type'       => 'indisponible',
                'label'      => 'Indisponible',
                'sous_label' => 'Cette énigme est actuellement inaccessible.',
                'points'     => null,
                'action'     => 'disabled',
            ];
    }
}



// ==================================================
// 🖼️ AFFICHAGE DES VISUELS D’ÉNIGMES
// ==================================================
/**
 * 🔹 afficher_visuels_enigme() → Affiche la galerie visuelle de l’énigme si l’utilisateur y a droit (image principale + vignettes).
 */

/**
 * Affiche une galerie d’images d’une énigme si l’utilisateur y a droit.
 *
 * Compatible Firelight Lightbox (ancien Easy Fancybox) via `rel="lightbox-enigme"`.
 * Les images sont servies via proxy (/voir-image-enigme) avec tailles adaptées.
 *
 * @param int $enigme_id ID du post de type énigme
 * @return void
 */
function afficher_visuels_enigme(int $enigme_id): void
{
  if (!utilisateur_peut_voir_enigme($enigme_id)) {
    echo '<div class="visuels-proteges">🔒 Les visuels de cette énigme sont protégés.</div>';
    return;
  }

  $images = get_field('enigme_visuel_image', $enigme_id);
  if (!$images || !is_array($images)) return;

  echo '<div class="galerie-enigme-wrapper">';

  // 📸 Image principale
  $image_id_active = $images[0]['ID'] ?? null;
  if ($image_id_active) {
    $src_main = add_query_arg('id', $image_id_active, site_url('/voir-image-enigme'));
    echo '<div class="image-principale">';
    echo '<a href="' . esc_url($src_main) . '" class="fancybox image" rel="lightbox-enigme">';
    echo '<img src="' . esc_url($src_main) . '" id="image-enigme-active" class="image-active" alt="Visuel énigme">';
    echo '</a>';
    echo '</div>';
  }


  // 🖼️ Vignettes + liens lightbox
  if (count($images) > 1) {
    echo '<div class="galerie-vignettes">';
    foreach ($images as $index => $image) {
      $img_id = $image['ID'] ?? null;
      if (!$img_id) continue;

      $src_thumb = esc_url(add_query_arg([
        'id' => $img_id,
        'taille' => 'thumbnail',
      ], site_url('/voir-image-enigme')));

      $src_full = esc_url(add_query_arg('id', $img_id, site_url('/voir-image-enigme')));

      $class = 'vignette' . ($index === 0 ? ' active' : '');

      echo '<img src="' . $src_thumb . '" class="' . esc_attr($class) . '" alt="" data-image-id="' . esc_attr($img_id) . '">';
      echo '<a href="' . $src_full . '" rel="lightbox-enigme" class="hidden-lightbox-link" style="display:none;"></a>';
    }
    echo '</div>';
  }

  // 🔁 JS interaction
?>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const vignettes = document.querySelectorAll('.vignette');
      const principale = document.getElementById('image-enigme-active');
      const lien = principale?.closest('a');
      const container = principale?.closest('.image-principale');

      vignettes.forEach(v => {
        v.addEventListener('click', () => {
          const id = v.getAttribute('data-image-id');
          if (!id || !principale || !lien) return;

          const url = '/voir-image-enigme?id=' + id;

          if (container) {
            container.style.minHeight = container.offsetHeight + 'px';
          }

          const preload = new Image();
          preload.onload = () => {
            principale.src = preload.src;
            lien.href = preload.src;

            if (container) {
              container.style.minHeight = '';
            }

            vignettes.forEach(x => x.classList.remove('active'));
            v.classList.add('active');
          };

          preload.src = url;
        });
      });
    });
  </script>
<?php
}




// ==================================================
// 🧩 AFFICHAGE DES ÉNIGMES – TEMPLATE UNIQUE & VARIANTS
// ==================================================
/**
 * 🔹 afficher_enigme_stylisee() → Affiche l’énigme avec son style d’affichage (structure unique + blocs surchargeables).
 * 🔸 enigme_get_partial() → Charge un partiel adapté au style (ex: pirate/images.php), avec fallback global.
 *
 */

/**
 * @param int $post_id ID de l’énigme à afficher.
 */
function afficher_enigme_stylisee($post_id)
{
  if (get_post_type($post_id) !== 'enigme') return;

  $etat = get_field('enigme_cache_etat_systeme', $post_id) ?? 'accessible';

  if ($etat !== 'accessible') {
    $chasse = get_field('enigme_chasse_associee', $post_id);
    $chasse_id = is_array($chasse) ? $chasse[0] ?? null : $chasse;

    if ($chasse_id) {
      // Redirection sécurisée
      wp_safe_redirect(get_permalink($chasse_id));
      exit;
    } else {
      // Fallback : énigme inaccessible
      echo '<div class="enigme-inaccessible">';
      echo '<p>🔒 Cette énigme n’est pas accessible actuellement.</p>';
      echo '<p><a href="' . esc_url(home_url('/')) . '" class="bouton-retour-home">← Retour à l’accueil</a></p>';
      echo '</div>';
      return;
    }
  }

  $style = get_field('enigme_style_affichage', $post_id) ?? 'defaut';

  echo '<div class="enigme-affichage enigme-style-' . esc_attr($style) . '">';
  enigme_get_partial('titre', $style, ['post_id' => $post_id]);
  enigme_get_partial('images', $style, ['post_id' => $post_id]);
  enigme_get_partial('texte', $style, ['post_id' => $post_id]);
  enigme_get_partial('bloc-reponse', $style, ['post_id' => $post_id]);
  enigme_get_partial('solution', $style, ['post_id' => $post_id]);
  enigme_get_partial('retour-chasse', $style, ['post_id' => $post_id]);
  echo '</div>';
}



/**
 * 🔸 enigme_get_partial() → Charge un partiel adapté au style (ex: pirate/images.php), avec fallback global.
 *
 * @param string $slug   Nom du bloc (titre, images, etc.)
 * @param string $style  Style d’affichage (ex : 'pirate', 'vintage')
 * @param array  $args   Données à transmettre au partial
 */
function enigme_get_partial(string $slug, string $style = 'defaut', array $args = []): void
{
  $base_path = "template-parts/enigme/partials";
  $variant = "{$base_path}/{$style}/{$slug}.php";
  $fallback = "{$base_path}/{$slug}.php";

  if (locate_template($variant)) {
    get_template_part("template-parts/enigme/partials/{$style}/{$slug}", null, $args);
  } elseif (locate_template($fallback)) {
    get_template_part("template-parts/enigme/partials/{$slug}", null, $args);
  }
}


// ==================================================
// 📬 GESTION DES RÉPONSES MANUELLES AUX ÉNIGMES
// ==================================================
/**
 * 🔹 afficher_formulaire_reponse_manuelle() → Affiche un champ texte et bouton pour soumettre une réponse manuelle (frontend).
 * 🔹 utilisateur_peut_repondre_manuelle() → Vérifie les conditions d’accès avant affichage du formulaire manuel.
 * 🔹 enregistrer_tentative_reponse_manuelle() → Insère la tentative dans la table SQL personnalisée.
 * 🔹 envoyer_mail_reponse_manuelle() → Envoie un mail HTML à l'organisateur avec la réponse (expéditeur = joueur).

 */

/**
 * Affiche le formulaire de réponse manuelle pour une énigme.
 *
 * @param int $enigme_id L'ID de l'énigme.
 * @return string HTML du formulaire.
 */
function afficher_formulaire_reponse_manuelle($enigme_id) {
    if (!is_user_logged_in()) return '<p>Veuillez vous connecter pour répondre à cette énigme.</p>';
    if (!utilisateur_peut_repondre_manuelle(get_current_user_id(), $enigme_id)) {
        return '<p>Vous ne pouvez pas répondre à cette énigme actuellement.</p>';
    }
    $nonce = wp_create_nonce('reponse_manuelle_nonce');
    ob_start();
    ?>
    <form method="post" class="formulaire-reponse-manuelle">
        <label for="reponse_manuelle">Votre réponse :</label>
        <textarea name="reponse_manuelle" id="reponse_manuelle" rows="3" required></textarea>
        <input type="hidden" name="enigme_id" value="<?php echo esc_attr($enigme_id); ?>">
        <input type="hidden" name="reponse_manuelle_nonce" value="<?php echo esc_attr($nonce); ?>">
        <button type="submit">Envoyer la réponse</button>
    </form>
    <?php
    return ob_get_clean();
}

add_shortcode('formulaire_reponse_manuelle', function($atts) {
    $atts = shortcode_atts(['id' => null], $atts);
    return afficher_formulaire_reponse_manuelle($atts['id']);
});

/**
 * Vérifie si un utilisateur peut soumettre une réponse manuelle à une énigme.
 *
 * @param int $user_id
 * @param int $enigme_id
 * @return bool
 */
function utilisateur_peut_repondre_manuelle($user_id, $enigme_id) {
    // ⚠️ MODE TEST — conditions de validation désactivées temporairement
    /*
    if (get_field('enigme_mode_validation', $enigme_id) !== 'manuel') {
        return false;
    }
    */

    return true;
}
/**
 * Traite la soumission du formulaire de réponse manuelle (en POST).
 */
add_action('init', function() {
    if (
        isset($_POST['reponse_manuelle_nonce'], $_POST['reponse_manuelle'], $_POST['enigme_id']) &&
        wp_verify_nonce($_POST['reponse_manuelle_nonce'], 'reponse_manuelle_nonce') &&
        is_user_logged_in()
    ) {
        $user_id = get_current_user_id();
        $enigme_id = (int) $_POST['enigme_id'];
        $reponse = sanitize_textarea_field($_POST['reponse_manuelle']);

        enregistrer_tentative_reponse_manuelle($user_id, $enigme_id, $reponse);

        envoyer_mail_reponse_manuelle($user_id, $enigme_id, $reponse);

        add_action('template_redirect', function() {
            wp_redirect(add_query_arg('reponse_envoyee', '1'));
            exit;
        });
    }
});

/**
 * Enregistre une tentative de réponse manuelle dans la table personnalisée.
 *
 * @param int $user_id
 * @param int $enigme_id
 * @param string $reponse
 */
function enregistrer_tentative_reponse_manuelle($user_id, $enigme_id, $reponse) {
    global $wpdb;

    $table = $wpdb->prefix . 'enigme_tentatives';
    $uid = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('tent_', true);

    $wpdb->insert($table, [
        'tentative_uid'   => $uid,
        'user_id'         => $user_id,
        'enigme_id'       => $enigme_id,
        'reponse_saisie'  => $reponse,
        'resultat'        => 'attente',
        'points_utilises' => 0,
        'ip'              => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent'      => $_SERVER['HTTP_USER_AGENT'] ?? null,
    ]);
}

/**
 * Envoie un email à l'organisateur avec la réponse manuelle soumise.
 *
 * Utilise un courriel de test pour le moment.
 *
 * @param int    $user_id
 * @param int    $enigme_id
 * @param string $reponse
 */
function envoyer_mail_reponse_manuelle($user_id, $enigme_id, $reponse) {
    // 🔍 Récupération de l'email organisateur lié à l'énigme
    $chasse  = get_field('enigme_chasse_associee', $enigme_id, false);
    if (is_array($chasse)) {
        $chasse_id = is_object($chasse[0]) ? (int) $chasse[0]->ID : (int) $chasse[0];
    } elseif (is_object($chasse)) {
        $chasse_id = (int) $chasse->ID;
    } else {
        $chasse_id = (int) $chasse;
    }

    $organisateur_id  = $chasse_id ? get_organisateur_from_chasse($chasse_id) : null;
    $email_organisateur = $organisateur_id ? get_field('email_organisateur', $organisateur_id) : '';

    if (!$email_organisateur) {
        $email_organisateur = get_option('admin_email');
    }

    $titre_enigme = get_the_title($enigme_id);
    $user         = get_userdata($user_id);

    $subject = '[Réponse Énigme] ' . $titre_enigme;

    $valider_url   = esc_url(add_query_arg([
        'user_id'   => $user_id,
        'enigme_id' => $enigme_id
    ], home_url('/valider-reponse')));
    $invalider_url = esc_url(add_query_arg([
        'user_id'   => $user_id,
        'enigme_id' => $enigme_id
    ], home_url('/invalider-reponse')));

    $date = date_i18n('j F Y à H:i', current_time('timestamp'));

    $message  = '<p>Une nouvelle réponse manuelle a été soumise par l\'utilisateur <strong>' . esc_html($user->user_login) . '</strong>.</p>';
    $message .= '<p><strong>🧩 Énigme concernée :</strong> <em>' . esc_html($titre_enigme) . '</em></p>';
    $message .= '<p><strong>📝 Réponse proposée :</strong><br><blockquote>' . nl2br(esc_html($reponse)) . '</blockquote></p>';
    $message .= '<p><strong>📅 Soumise le :</strong> ' . esc_html($date) . '</p>';
    $message .= '<hr>';
    $message .= '<p>';
    $message .= '<a href="' . $valider_url . '" style="display:inline-block; padding:8px 16px; background-color:#28a745; color:white; text-decoration:none; border-radius:4px;">✅ Valider</a> &nbsp; ';
    $message .= '<a href="' . $invalider_url . '" style="display:inline-block; padding:8px 16px; background-color:#dc3545; color:white; text-decoration:none; border-radius:4px;">❌ Invalider</a>';
    $message .= '</p>';
    $message .= '<p style="font-size:small; color:gray;">(ID utilisateur : ' . intval($user_id) . ', ID énigme : ' . intval($enigme_id) . ')</p>';

    $headers   = [
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $user->display_name . ' <' . $user->user_email . '>',
        'Reply-To: ' . $user->user_email,
    ];

    wp_mail($email_organisateur, $subject, $message, $headers);

}
