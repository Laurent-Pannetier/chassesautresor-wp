 <?php
    defined('ABSPATH') || exit;


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
     */


    /**
     * Récupère le statut actuel de l’utilisateur pour une énigme.
     *
     * Statuts possibles :
     * - non_souscrite : le joueur n'a jamais interagi avec l’énigme
     * - en_cours      : le joueur a commencé l’énigme
     * - resolue       : le joueur a trouvé la bonne réponse
     * - terminee      : l’énigme a été finalisée dans un autre contexte
     * - echouee       : le joueur a tenté et échoué
     * - abandonnee    : le joueur a abandonné explicitement ou par expiration
     *
     * @param int $enigme_id ID de l’énigme.
     * @param int $user_id   ID de l’utilisateur.
     * @return string Statut actuel (par défaut : 'non_souscrite').
     */
    function enigme_get_statut_utilisateur(int $enigme_id, int $user_id): string
    {
        if (!$enigme_id || !$user_id) {
            return 'non_commencee';
        }

        global $wpdb;
        $table = $wpdb->prefix . 'enigme_statuts_utilisateur';

        $statut = $wpdb->get_var($wpdb->prepare(
            "SELECT statut FROM $table WHERE user_id = %d AND enigme_id = %d",
            $user_id,
            $enigme_id
        ));

        return $statut ?: 'non_commencee';
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
    function enigme_get_tentatives_restantes($enigme_id, $user_id)
    {
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
    function enigme_enregistrer_tentative($enigme_id, $user_id)
    {
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
    function enigme_tentatives_depassees($enigme_id, $user_id)
    {
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
    function enigme_reinitialiser_tentatives($enigme_id, $user_id, $toutes = false)
    {
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
    function enigme_get_liste_prerequis_possibles(int $enigme_id): array
    {
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
    function get_cta_enigme(int $enigme_id, ?int $user_id = null): array
    {
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
                wp_safe_redirect(get_permalink($chasse_id));
                exit;
            } else {
                echo '<div class="enigme-inaccessible">';
                echo '<p>🔒 Cette énigme n’est pas accessible actuellement.</p>';
                echo '<p><a href="' . esc_url(home_url('/')) . '" class="bouton-retour-home">← Retour à l’accueil</a></p>';
                echo '</div>';
                return;
            }
        }

        $user_id = get_current_user_id(); // ✅ récupère l'utilisateur ici
        $style = get_field('enigme_style_affichage', $post_id) ?? 'defaut';

        echo '<div class="enigme-affichage enigme-style-' . esc_attr($style) . '">';
        enigme_get_partial('titre', $style, ['post_id' => $post_id]);
        enigme_get_partial('images', $style, ['post_id' => $post_id]);
        enigme_get_partial('texte', $style, ['post_id' => $post_id]);
        enigme_get_partial('bloc-reponse', $style, [ // ✅ ajoute le user_id ici
            'post_id' => $post_id,
            'user_id' => $user_id,
        ]);
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
    // ✅ TRAITEMENT REPONSES A UNE ENIGME
    // ==================================================

    // 🔹 afficher_formulaire_reponse_manuelle() → Affiche le formulaire de réponse manuelle (frontend).
    // 🔹 utilisateur_peut_repondre_manuelle() → Vérifie si l'utilisateur peut répondre à une énigme manuelle.
    // 🔹 soumettre_reponse_manuelle() → Traite la soumission d'une réponse manuelle (frontend).
    // 🔹 envoyer_mail_reponse_manuelle() → Envoie un mail HTML à l'organisateur avec la réponse (expéditeur = joueur).
    // 🔹 envoyer_mail_resultat_joueur() → Envoie un mail HTML au joueur après validation ou refus de sa réponse.
    // 🔹 envoyer_mail_accuse_reception_joueur() → Envoie un accusé de réception au joueur juste après sa soumission.
    // 🔹 enigme_mettre_a_jour_statut_utilisateur() → Met à jour le statut d'un joueur (user_meta).
    // 🔹 inserer_tentative() → Insère une tentative dans la table personnalisée.
    // 🔹 get_tentative_by_uid() → Récupère une tentative par son identifiant UID.
    // 🔹 traiter_tentative_manuelle() → Effectue la validation/refus d'une tentative (une seule fois).
    // 🔹 recuperer_infos_tentative() → Renvoie toutes les données pour l'affichage d'une tentative.
    // 🔹 get_etat_tentative() → Retourne l'état logique d'une tentative selon son champ `resultat`.
    // 🔹 utilisateur_peut_engager_enigme() → Vérifie si un utilisateur peut engager une énigme.



    /**
     * Affiche le formulaire de réponse manuelle pour une énigme.
     *
     * @param int $enigme_id L'ID de l'énigme.
     * @return string HTML du formulaire.
     */
    function afficher_formulaire_reponse_manuelle($enigme_id)
    {
        if (!is_user_logged_in()) {
            return '<p>Veuillez vous connecter pour répondre à cette énigme.</p>';
        }

        $user_id = get_current_user_id();

        if (!utilisateur_peut_repondre_manuelle($user_id, $enigme_id)) {
            return '<p>Vous ne pouvez plus répondre à cette énigme.</p>';
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

    add_shortcode('formulaire_reponse_manuelle', function ($atts) {
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
    function utilisateur_peut_repondre_manuelle(int $user_id, int $enigme_id): bool
    {
        if (!$user_id || !$enigme_id) return false;

        $statut = enigme_get_statut_utilisateur($enigme_id, $user_id);

        // Autoriser uniquement les statuts actifs
        $autorisés = ['en_cours', 'echouee', 'abandonnee'];

        return in_array($statut, $autorisés, true);
    }


    /**
     * Intercepte et traite la soumission d'une réponse manuelle à une énigme (frontend).
     *
     * Conditions :
     * - utilisateur connecté
     * - champ réponse + nonce + enigme_id présents
     * - nonce valide
     */
    function soumettre_reponse_manuelle()
    {
        global $wpdb;

        if (
            isset($_POST['reponse_manuelle_nonce'], $_POST['reponse_manuelle'], $_POST['enigme_id']) &&
            wp_verify_nonce($_POST['reponse_manuelle_nonce'], 'reponse_manuelle_nonce') &&
            is_user_logged_in()
        ) {
            $user_id   = get_current_user_id();
            $enigme_id = (int) $_POST['enigme_id'];
            $reponse   = sanitize_textarea_field($_POST['reponse_manuelle']);

            // Blocage si interdiction de répondre
            if (!utilisateur_peut_repondre_manuelle($user_id, $enigme_id)) {
                return;
            }

            // Vérifie si l'utilisateur a déjà résolu l'énigme
            $current_statut = $wpdb->get_var($wpdb->prepare(
                "SELECT statut FROM {$wpdb->prefix}enigme_statuts_utilisateur WHERE user_id = %d AND enigme_id = %d",
                $user_id,
                $enigme_id
            ));

            if (in_array($current_statut, ['resolue', 'terminee'], true)) {
                error_log("❌ Tentative rejetée car joueur a déjà résolu l’énigme (UID=$user_id / Enigme=$enigme_id).");
                return;
            }

            // Insertion tentative + mise à jour statut = "soumis"
            $uid = inserer_tentative($user_id, $enigme_id, $reponse);
            enigme_mettre_a_jour_statut_utilisateur($enigme_id, $user_id, 'soumis', true);

            envoyer_mail_reponse_manuelle($user_id, $enigme_id, $reponse, $uid);
            envoyer_mail_accuse_reception_joueur($user_id, $enigme_id, $uid);

            add_action('template_redirect', function () {
                wp_redirect(add_query_arg('reponse_envoyee', '1'));
                exit;
            });
        }
    }
    add_action('init', 'soumettre_reponse_manuelle');


    /**
     * Envoie un email à l'organisateur avec la réponse manuelle soumise.
     *
     * @param int    $user_id
     * @param int    $enigme_id
     * @param string $reponse
     * @param string $uid
     */
    function envoyer_mail_reponse_manuelle($user_id, $enigme_id, $reponse, $uid)
    {
        // 🔍 Email organisateur
        $chasse  = get_field('enigme_chasse_associee', $enigme_id, false);
        if (is_array($chasse)) {
            $chasse_id = is_object($chasse[0]) ? (int) $chasse[0]->ID : (int) $chasse[0];
        } elseif (is_object($chasse)) {
            $chasse_id = (int) $chasse->ID;
        } else {
            $chasse_id = (int) $chasse;
        }

        $organisateur_id = $chasse_id ? get_organisateur_from_chasse($chasse_id) : null;
        $email_organisateur = $organisateur_id ? get_field('email_organisateur', $organisateur_id) : '';
        if (!$email_organisateur) {
            $email_organisateur = get_option('admin_email');
        }

        $titre_enigme = html_entity_decode(get_the_title($enigme_id), ENT_QUOTES, 'UTF-8');
        $user = get_userdata($user_id);
        $subject_raw = '[Réponse Énigme] ' . $titre_enigme;

        $subject = function_exists('wp_encode_mime_header')
            ? wp_encode_mime_header($subject_raw)
            : mb_encode_mimeheader($subject_raw, 'UTF-8', 'B', "\r\n");

        $date        = date_i18n('j F Y à H:i', current_time('timestamp'));
        $url_enigme  = get_permalink($enigme_id);
        $profil_url  = get_author_posts_url($user_id);
        $traitement_url = esc_url(add_query_arg([
            'uid' => $uid,
        ], home_url('/traitement-tentative')));

        // 📧 Message HTML
        $message  = '<div style="font-family:Arial,sans-serif; font-size:14px;">';
        $message .= '<p>Une nouvelle réponse manuelle a été soumise par <strong><a href="' . esc_url($profil_url) . '" target="_blank">' . esc_html($user->user_login) . '</a></strong>.</p>';
        $message .= '<p><strong>🧩 Énigme :</strong> <em>' . esc_html($titre_enigme) . '</em></p>';
        $message .= '<p><strong>📝 Réponse :</strong><br><blockquote>' . nl2br(esc_html($reponse)) . '</blockquote></p>';
        $message .= '<p><strong>📅 Soumise le :</strong> ' . esc_html($date) . '</p>';
        $message .= '<p><strong>🔐 Identifiant :</strong> ' . esc_html($uid) . '</p>';
        $message .= '<hr>';
        $message .= '<p style="text-align:center;">';
        $message .= '<a href="' . $traitement_url . '" style="background:#0073aa;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;font-weight:bold;display:inline-block;">🛠️ Traiter cette tentative</a>';
        $message .= '</p>';
        $message .= '<p><strong>✉️ Contacter le joueur :</strong><br>';
        $message .= '<a href="mailto:' . esc_attr($user->user_email) . '">' . esc_html($user->display_name) . ' (' . esc_html($user->user_email) . ')</a></p>';
        $message .= '<p><a href="' . esc_url($url_enigme) . '" target="_blank" style="font-size:0.9em;">🔗 Voir l’énigme en ligne</a></p>';
        $message .= '</div>';

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'Reply-To: ' . $user->display_name . ' <' . $user->user_email . '>',
        ];

        add_filter('wp_mail_from_name', function () use ($user) {
            return $user->display_name;
        });

        wp_mail($email_organisateur, $subject, $message, $headers);
        remove_filter('wp_mail_from_name', '__return_false');
    }


    /**
     * Envoie un email de notification au joueur concernant le résultat de sa réponse à une énigme.
     *
     * @param int    $user_id    L'identifiant de l'utilisateur à notifier.
     * @param int    $enigme_id  L'identifiant de l'énigme concernée.
     * @param string $resultat   Le résultat de la réponse ('bon' pour validée, autre pour refusée).
     *
     * @return void
     */
    function envoyer_mail_resultat_joueur($user_id, $enigme_id, $resultat)
    {
        $user = get_userdata($user_id);
        if (!$user || !is_email($user->user_email)) return;

        $titre_enigme = get_the_title($enigme_id);
        if (!is_string($titre_enigme)) $titre_enigme = '';

        $resultat_txt = $resultat === 'bon' ? 'validée ✅' : 'refusée ❌';
        $sujet = '[Chasses au Trésor] Votre réponse a été ' . $resultat_txt;

        $message  = '<div style="font-family:Arial,sans-serif; font-size:14px;">';
        $message .= '<p>Bonjour <strong>' . esc_html($user->display_name) . '</strong>,</p>';
        $message .= '<p>Votre réponse à l’énigme <strong>« ' . esc_html($titre_enigme) . ' »</strong> a été <strong>' . $resultat_txt . '</strong>.</p>';
        $message .= '<p>Merci pour votre participation !</p>';
        $message .= '<hr>';
        $message .= '<p>🔗 <a href="https://chassesautresor.com/mon-compte" target="_blank">Voir mes réponses</a></p>';
        $message .= '<p style="margin-top:2em;">L’équipe chassesautresor.com</p>';
        $message .= '</div>';

        $headers = [
            'Content-Type: text/html; charset=UTF-8'
        ];

        // Sécurisation du champ ACF enigme_chasse_associee
        $chasse_raw = get_field('enigme_chasse_associee', $enigme_id, false);
        if (is_array($chasse_raw)) {
            $first = reset($chasse_raw);
            $chasse_id = is_object($first) ? (int) $first->ID : (int) $first;
        } elseif (is_object($chasse_raw)) {
            $chasse_id = (int) $chasse_raw->ID;
        } elseif (is_numeric($chasse_raw)) {
            $chasse_id = (int) $chasse_raw;
        } else {
            $chasse_id = 0;
        }

        $organisateur_id = get_organisateur_from_chasse($chasse_id);
        $email_organisateur = get_field('email_organisateur', $organisateur_id);

        if (is_array($email_organisateur)) {
            $email_organisateur = reset($email_organisateur);
        }

        if (!is_string($email_organisateur) || !is_email($email_organisateur)) {
            $email_organisateur = get_option('admin_email');
        }

        $headers[] = 'Reply-To: ' . $email_organisateur;

        add_filter('wp_mail_from_name', function () {
            return 'Chasses au Trésor';
        });

        wp_mail($user->user_email, $sujet, $message, $headers);
        remove_filter('wp_mail_from_name', '__return_false'); // si mis ailleurs
    }

    /**
     * Envoie un accusé de réception au joueur juste après sa soumission.
     *
     * @param int $user_id
     * @param int $enigme_id
     * @return void
     */
    function envoyer_mail_accuse_reception_joueur($user_id, $enigme_id, $uid)
    {
        $user = get_userdata($user_id);
        if (!$user || !is_email($user->user_email)) return;

        $titre_enigme = get_the_title($enigme_id);
        $sujet = '[Chasses au Trésor] Tentative de réponse bien reçue pour : ' . html_entity_decode($titre_enigme, ENT_QUOTES, 'UTF-8');

        $message  = '<div style="font-family:Arial,sans-serif; font-size:14px;">';
        $message .= '<p>Bonjour <strong>' . esc_html($user->display_name) . '</strong>,</p>';
        $message .= '<p>Nous avons bien reçu votre tentative de réponse à l’énigme « <strong>' . esc_html($titre_enigme) . '</strong> ».<br>';
        $message .= 'Votre identifiant de tentative est : <code>' . esc_html($uid) . '</code>.</p>';
        $message .= '<p>Elle sera examinée prochainement par l’organisateur.</p>';
        $message .= '<p>Vous recevrez une notification lorsqu’une décision sera prise.</p>';
        $message .= '<hr>';
        $message .= '<p>🔗 <a href="https://chassesautresor.com/mon-compte" target="_blank">Accéder à votre compte</a></p>';
        $message .= '<p style="margin-top:2em;">Merci pour votre participation,<br>L’équipe chassesautresor.com</p>';
        $message .= '</div>';

        // Reply-to = organisateur
        $chasse_id = get_field('enigme_chasse_associee', $enigme_id, false);
        $organisateur_id = get_organisateur_from_chasse($chasse_id);
        $email_organisateur = get_field('email_organisateur', $organisateur_id);

        if (!is_email($email_organisateur)) {
            $email_organisateur = get_option('admin_email');
        }

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'Reply-To: ' . $email_organisateur
        ];

        add_filter('wp_mail_from_name', function () use ($organisateur_id) {
            return get_the_title($organisateur_id) ?: 'Chasses au Trésor';
        });

        wp_mail($user->user_email, $sujet, $message, $headers);
        remove_filter('wp_mail_from_name', '__return_false'); // si mis ailleurs

    }

    /**
     * Met à jour le statut d'un joueur pour une énigme dans la table personnalisée `wp_enigme_statuts_utilisateur`.
     * La mise à jour ne s'effectue que si le nouveau statut est plus avancé que l'ancien.
     *
     * @param int $enigme_id ID de l'énigme.
     * @param int $user_id   ID de l'utilisateur.
     * @param string $nouveau_statut Nouveau statut ('non_commencee', 'en_cours', 'abandonnee', 'echouee', 'resolue', 'terminee').
     * @return bool True si la mise à jour est faite, false sinon.
     */
    function enigme_mettre_a_jour_statut_utilisateur(int $enigme_id, int $user_id, string $nouveau_statut, bool $forcer = false): bool
    {
        if (!$enigme_id || !$user_id || !$nouveau_statut) {
            return false;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'enigme_statuts_utilisateur';

        $priorites = [
            'non_commencee' => 0,
            'soumis'        => 1,
            'en_cours'      => 2,
            'abandonnee'    => 3,
            'echouee'       => 4,
            'resolue'       => 5,
            'terminee'      => 6,
        ];

        if (!isset($priorites[$nouveau_statut])) {
            error_log("❌ Statut utilisateur invalide : $nouveau_statut");
            return false;
        }

        $statut_actuel = $wpdb->get_var($wpdb->prepare(
            "SELECT statut FROM $table WHERE user_id = %d AND enigme_id = %d",
            $user_id,
            $enigme_id
        ));

        // Protection : interdiction de rétrograder un joueur ayant déjà résolu l’énigme
        if (in_array($statut_actuel, ['resolue', 'terminee'], true)) {
            error_log("🔒 Statut non modifié : $statut_actuel → tentative de mise à jour vers $nouveau_statut bloquée (UID: $user_id / Enigme: $enigme_id)");
            return false;
        }

        $niveau_actuel  = $priorites[$statut_actuel] ?? 0;
        $niveau_nouveau = $priorites[$nouveau_statut];

        if (!$forcer && $niveau_nouveau <= $niveau_actuel) {
            return false;
        }

        $data = [
            'statut'            => $nouveau_statut,
            'date_mise_a_jour'  => current_time('mysql'),
        ];

        $where = [
            'user_id'   => $user_id,
            'enigme_id' => $enigme_id,
        ];

        if ($statut_actuel !== null) {
            $wpdb->update($table, $data, $where, ['%s', '%s'], ['%d', '%d']);
        } else {
            $wpdb->insert($table, array_merge($where, $data), ['%d', '%d', '%s', '%s']);
        }

        return true;
    }



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
        global $wpdb;
        $table = $wpdb->prefix . 'enigme_tentatives';
        $uid = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('tent_', true);

        $wpdb->insert($table, [
            'tentative_uid'   => $uid,
            'user_id'         => $user_id,
            'enigme_id'       => $enigme_id,
            'reponse_saisie'  => $reponse,
            'resultat'        => $resultat,
            'points_utilises' => $points_utilises,
            'ip'              => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent'      => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);

        return $uid;
    }

    /**
     * Récupère une tentative par son UID.
     */
    function get_tentative_by_uid(string $uid): ?object
    {
        global $wpdb;
        $table = $wpdb->prefix . 'enigme_tentatives';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE tentative_uid = %s", $uid));
    }


    /**
     * Traite une tentative manuelle : effectue l'action (validation/refus) une seule fois.
     *
     * @param string $uid Identifiant unique de la tentative.
     * @param string $resultat 'bon' ou 'faux'.
     * @return bool true si traitement effectué, false si déjà traité ou interdit.
     */
    function traiter_tentative_manuelle(string $uid, string $resultat): bool

    {
        global $wpdb;
        $table = $wpdb->prefix . 'enigme_tentatives';


        error_log("👣 Tentative traitement UID=$uid par IP=" . ($_SERVER['REMOTE_ADDR'] ?? 'inconnue'));

        $tentative = get_tentative_by_uid($uid);
        if (!$tentative) {
            error_log("❌ Tentative introuvable");
            return false;
        }

        if ($tentative->resultat !== 'attente') {
            error_log("⛔ Tentative déjà traitée → statut actuel = " . $tentative->resultat);
            return false;
        }


        $user_id = (int) $tentative->user_id;
        $enigme_id = (int) $tentative->enigme_id;

        // 🔐 Sécurité : si déjà "résolue", on refuse toute tentative de traitement
        $statut_user = $wpdb->get_var($wpdb->prepare(
            "SELECT statut FROM {$wpdb->prefix}enigme_statuts_utilisateur WHERE user_id = %d AND enigme_id = %d",
            $user_id,
            $enigme_id
        ));

        if ($statut_user === 'resolue') {
            error_log("⛔ Statut utilisateur déjà 'resolue' → refus de traitement UID=$uid");
            return false;
        }

        // 🔐 Vérification organisateur ou admin
        $current_user_id = get_current_user_id();
        $chasse_id = recuperer_id_chasse_associee($enigme_id);
        $organisateur_id = get_organisateur_from_chasse($chasse_id);
        $organisateur_user_ids = (array) get_field('utilisateurs_associes', $organisateur_id);

        if (
            !current_user_can('manage_options') &&
            !in_array($current_user_id, array_map('intval', $organisateur_user_ids), true)
        ) {
            error_log("⛔ Accès interdit au traitement pour UID=$uid");
            return false;
        }

        // ✅ Mise à jour
        $wpdb->update(
            $table,
            ['resultat' => $resultat, 'traitee' => 1],
            ['tentative_uid' => $uid],
            ['%s', '%d'],
            ['%s']
        );

        $nouveau_statut = $resultat === 'bon' ? 'resolue' : 'echouee';
        enigme_mettre_a_jour_statut_utilisateur($enigme_id, $user_id, $nouveau_statut);
        envoyer_mail_resultat_joueur($user_id, $enigme_id, $resultat);


        error_log("✅ Tentative UID=$uid traitée comme $resultat → statut joueur mis à jour en $nouveau_statut");
        return true;
    }


    /**
     * Renvoie toutes les données d'affichage pour une tentative (état, utilisateur, statut, etc.)
     *
     * @param string $uid Identifiant unique de la tentative.
     * @return array
     */
    /**
     * Récupère toutes les informations nécessaires à l'affichage d'une tentative.
     *
     * @param string $uid UID unique de la tentative.
     * @return array Données enrichies : statut, nom, etc.
     */
    function recuperer_infos_tentative(string $uid): array
    {
        $tentative = get_tentative_by_uid($uid);
        if (!$tentative) {
            return ['etat_tentative' => 'inexistante'];
        }

        $etat_tentative = get_etat_tentative($uid); // logique métier (attente/validee/refusee)
        $resultat = $tentative->resultat ?? '';
        $traitee = (int) ($tentative->traitee ?? 0) === 1;

        return [
            'etat_tentative'        => $etat_tentative,
            'statut_initial'        => $resultat ?: 'invalide',
            'statut_final'          => $resultat,
            'resultat'              => $resultat,
            'deja_traitee'          => ($etat_tentative !== 'attente'),
            'traitee'               => $traitee,
            'vient_d_etre_traitee'  => $traitee && $etat_tentative !== 'attente',
            'tentative'             => $tentative,
            'nom_user'              => get_userdata($tentative->user_id)?->display_name ?? 'Utilisateur inconnu',
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
        global $wpdb;
        $table = $wpdb->prefix . 'enigme_tentatives';
        $resultat = $wpdb->get_var($wpdb->prepare("SELECT resultat FROM $table WHERE tentative_uid = %s", $uid));

        if ($resultat === null) return 'inexistante';
        if ($resultat === 'attente') return 'attente';
        if ($resultat === 'bon') return 'validee';
        if ($resultat === 'faux') return 'refusee';

        return 'invalide';
    }


 * @param int $enigme_id L’ID de l’énigme à tester.
 * @param int|null $user_id L’ID du joueur (par défaut : current_user).
 * @return bool True si engagement autorisé.
 */
function utilisateur_peut_engager_enigme($enigme_id, $user_id = null): bool {
  $user_id = $user_id ?? get_current_user_id();
  $etat = enigme_get_etat_systeme($enigme_id);
  $statut = enigme_get_statut_utilisateur($enigme_id, $user_id);

  return ($etat === 'accessible' && $statut === 'non_souscrite');
}