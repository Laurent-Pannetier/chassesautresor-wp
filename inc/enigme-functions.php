 <?php
    defined('ABSPATH') || exit;


    // 🔧 CONTRÔLES ET RÉGLAGES AVANCÉS – ÉNIGMES
    // 🧾 ENREGISTREMENT DES ENGAGEMENTS
    // 🖼️ AFFICHAGE DES VISUELS D’ÉNIGMES
    // 🎨 AFFICHAGE STYLISÉ DES ÉNIGMES
    // 📬 GESTION DES RÉPONSES MANUELLES (FRONTEND)
    // ✉️ ENVOI D'EMAILS (RÉPONSES MANUELLES)
    // 📊 GESTION DES TENTATIVES UTILISATEUR



    // ==================================================
    // 🔧 CONTRÔLES ET RÉGLAGES AVANCÉS – ÉNIGMES
    // ==================================================
    /**
     * 🔹 enigme_get_liste_prerequis_possibles() → Retourne les autres énigmes de la même chasse pouvant être définies comme prérequis.
     * 🔹 get_cta_enigme() → Retourne les informations d'affichage du bouton CTA en fonction du statut et du contexte de l'énigme.
     * 🔹 render_cta_enigme() → Affiche le bouton CTA d'une énigme à partir des données retournées par get_cta_enigme().
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
        return $resultats;
    }



    /**
     * Retourne les données d’affichage du bouton d’engagement d’une énigme.
     *
     * Types possibles :
     * - voir        → lien direct réservé admin / organisateur
     * - connexion   → utilisateur non connecté
     * - engager     → première tentative ou ré-engagement possible
     * - continuer   → énigme en cours
     * - revoir      → énigme résolue
     * - terminee    → énigme finalisée (lecture seule)
     * - bloquee     → bloquée par la chasse ou une date
     * - invalide    → configuration incorrecte
     *
     * @param int $enigme_id
     * @param int|null $user_id
     * @return array{
     *   type: string,
     *   label: string,
     *   sous_label: string|null,
     *   action: 'form'|'link'|'disabled',
     *   url: string|null,
     *   points: int|null
     * }
     */
    function get_cta_enigme(int $enigme_id, ?int $user_id = null): array
    {
        $user_id = $user_id ?? get_current_user_id();

        $chasse_id = recuperer_id_chasse_associee($enigme_id);
        if (
            current_user_can('manage_options') ||
            utilisateur_est_organisateur_associe_a_chasse($user_id, $chasse_id)
        ) {
            return [
                'type'       => 'voir',
                'label'      => '👁️ Voir l’énigme',
                'sous_label' => 'Accès organisateur',
                'action'     => 'link',
                'url'        => get_permalink($enigme_id),
                'points'     => null,
            ];
        }

        if (!is_user_logged_in()) {
            return [
                'type'       => 'connexion',
                'label'      => '🔐 Connectez-vous',
                'sous_label' => null,
                'action'     => 'link',
                'url'        => site_url('/mon-compte'),
                'points'     => null,
            ];
        }

        $etat = enigme_get_etat_systeme($enigme_id);
        $statut = enigme_get_statut_utilisateur($enigme_id, $user_id);
        $tentative = get_field('enigme_tentative', $enigme_id);
        $points = intval($tentative['enigme_tentative_cout_points'] ?? 0);

        if (!in_array($etat, ['accessible'], true)) {
            $type = in_array($etat, ['bloquee_date', 'bloquee_chasse']) ? 'bloquee' : 'invalide';
            return [
                'type'       => $type,
                'label'      => 'Indisponible',
                'sous_label' => 'Cette énigme est bloquée ou mal configurée.',
                'action'     => 'disabled',
                'url'        => null,
                'points'     => null,
            ];
        }

        switch ($statut) {
            case 'resolue':
                return [
                    'type'       => 'revoir',
                    'label'      => '🔁 Revoir',
                    'sous_label' => 'Énigme déjà résolue',
                    'action'     => 'link',
                    'url'        => get_permalink($enigme_id),
                    'points'     => null,
                ];

            case 'en_cours':
                return [
                    'type'       => 'continuer',
                    'label'      => '▶️ Continuer',
                    'sous_label' => null,
                    'action'     => 'link',
                    'url'        => get_permalink($enigme_id),
                    'points'     => null,
                ];

            case 'terminee':
                return [
                    'type'       => 'terminee',
                    'label'      => '✔️ Terminé',
                    'sous_label' => null,
                    'action'     => 'disabled',
                    'url'        => null,
                    'points'     => null,
                ];

            case 'echouee':
                return [
                    'type'       => 'engager',
                    'label'      => ($points > 0) ? "Réessayer pour $points pts" : "Réessayer",
                    'sous_label' => null,
                    'action'     => 'form',
                    'url'        => site_url('/traitement-engagement'),
                    'points'     => $points,
                ];

            case 'abandonnee':
            case 'echouee':
                return [
                    'type'       => 'engager',
                    'label'      => ($points > 0) ? "Débloquer pour $points pts" : "Commencer",
                    'sous_label' => null,
                    'action'     => 'form',
                    'url'        => site_url('/traitement-engagement'),
                    'points'     => $points,
                ];

            default:
                return [
                    'type'       => 'invalide',
                    'label'      => 'Erreur',
                    'sous_label' => 'Statut utilisateur inconnu',
                    'action'     => 'disabled',
                    'url'        => null,
                    'points'     => null,
                ];
        }
    }


    /**
     * @param array $cta Résultat de get_cta_enigme().
     * @param int $enigme_id ID de l’énigme concernée (utile pour les formulaires).
     */
    function render_cta_enigme(array $cta, int $enigme_id): void
    {
        switch ($cta['action']) {
            case 'form':
    ?>
             <form method="post" action="<?= esc_url($cta['url']); ?>" class="cta-enigme-form">
                 <input type="hidden" name="enigme_id" value="<?= esc_attr($enigme_id); ?>">
                 <?php wp_nonce_field('engager_enigme_' . $enigme_id, 'engager_enigme_nonce'); ?>
                 <button type="submit"><?= esc_html($cta['label']); ?></button>
                 <?php if (!empty($cta['sous_label'])): ?>
                     <div class="cta-sous-label"><?= esc_html($cta['sous_label']); ?></div>
                 <?php endif; ?>
             </form>
         <?php
                break;

            case 'link':
            ?>
             <a href="<?= esc_url($cta['url']); ?>" class="cta-enigme-lien">
                 <?= esc_html($cta['label']); ?>
             </a>
             <?php if (!empty($cta['sous_label'])): ?>
                 <div class="cta-sous-label"><?= esc_html($cta['sous_label']); ?></div>
             <?php endif; ?>
         <?php
                break;

            case 'disabled':
            default:
            ?>
             <p class="cta-enigme-desactive"><?= esc_html($cta['label']); ?></p>
             <?php if (!empty($cta['sous_label'])): ?>
                 <div class="cta-sous-label"><?= esc_html($cta['sous_label']); ?></div>
             <?php endif; ?>
     <?php
                break;
        }
    }



    // ==================================================
    // 🧾 ENREGISTREMENT DES ENGAGEMENTS
    // ==================================================
    /**
     * 🔹 enregistrer_engagement_enigme() → Insère un engagement dans la table SQL `wp_enigme_engagements`.
     * 🔹 marquer_enigme_comme_engagee() → Met à jour le statut utilisateur ET enregistre un engagement SQL.
     */

    /**
     * Vérifie d’abord si un engagement identique existe déjà.
     *
     * @param int $user_id
     * @param int $enigme_id
     * @return bool True si insertion effectuée ou déjà existante.
     */
    function enregistrer_engagement_enigme(int $user_id, int $enigme_id): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . 'enigme_engagements';

        $existe = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE user_id = %d AND enigme_id = %d",
            $user_id,
            $enigme_id
        ));

        if ($existe) return true;

        $result = $wpdb->insert($table, [
            'user_id'         => $user_id,
            'enigme_id'       => $enigme_id,
            'date_engagement' => current_time('mysql'),
        ], ['%d', '%d', '%s']);

        return $result !== false;
    }


    /**
     * @param int $user_id
     * @param int $enigme_id
     * @return bool True si tout s’est bien passé.
     */
    function marquer_enigme_comme_engagee(int $user_id, int $enigme_id): bool
    {
        $ok1 = enigme_mettre_a_jour_statut_utilisateur($enigme_id, $user_id, 'en_cours', true);
        $ok2 = enregistrer_engagement_enigme($user_id, $enigme_id);
        return $ok1 && $ok2;
    }




    // ==================================================
    // 🖼️ AFFICHAGE DES VISUELS D’ÉNIGMES
    /**
     * 🔹 define('ID_IMAGE_PLACEHOLDER_ENIGME', 3925) → Définit l’identifiant de l’image placeholder utilisée pour les énigmes.
     * 🔹 afficher_visuels_enigme() → Affiche la galerie visuelle de l’énigme si l’utilisateur y a droit (image principale + vignettes).
     * 🔹 get_image_enigme() → Renvoie l’URL de l’image principale d’une énigme ou un placeholder.
     * 🔹 enigme_a_une_image() → Vérifie si l’énigme a une image définie.
     * 🔹 get_url_vignette_enigme() → Retourne l’URL proxy de la première vignette d’une énigme.
     * 🔹 afficher_picture_vignette_enigme() → Affiche un bloc <picture> responsive pour une énigme.
     * 🔹 trouver_chemin_image() → Retourne le chemin absolu et le type MIME d’une image à une taille donnée.
     */

    /**
     * Définit l'identifiant de l'image placeholder utilisée pour les énigmes.
     * 
     * Constante : ID_IMAGE_PLACEHOLDER_ENIGME
     * Valeur : 3925
     * 
     * Cette constante est utilisée comme identifiant de l'image par défaut (placeholder)
     * pour les énigmes dans le site WordPress.
     */
    define('ID_IMAGE_PLACEHOLDER_ENIGME', 3925);


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


    /**
     * Renvoie l’URL de l’image principale d’une énigme,
     * ou un placeholder si aucune image n’est définie.
     *
     * @param int $post_id
     * @param string $size
     * @return string|null
     */
    function get_image_enigme(int $post_id, string $size = 'medium'): ?string
    {
        $images = get_field('enigme_visuel_image', $post_id);

        if (is_array($images) && !empty($images[0]['ID'])) {
            return wp_get_attachment_image_url($images[0]['ID'], $size);
        }

        // 🧩 Placeholder image : image statique ou ID définie par toi
        return wp_get_attachment_image_url(3925, $size);
    }


    /**
     * Vérifie si l’énigme a une image définie.
     *
     * @param int $post_id ID du post de type énigme
     * @return bool True si l’énigme a une image, false sinon.
     */
    function enigme_a_une_image(int $post_id): bool
    {
        $images = get_field('enigme_visuel_image', $post_id);
        return is_array($images) && !empty($images[0]['ID']);
    }



    /**
     * Retourne l'URL proxy pour une vignette d’énigme à la taille souhaitée.
     *
     * @param int $enigme_id
     * @param string $taille  Taille WordPress (ex: 'thumbnail', 'medium', 'full')
     * @return string|null
     */
    function get_url_vignette_enigme(int $enigme_id, string $taille = 'thumbnail'): ?string
    {
        if (!utilisateur_peut_voir_enigme($enigme_id)) {
            return null;
        }

        $images = get_field('enigme_visuel_image', $enigme_id, false);
        if (!$images || !is_array($images)) {
            return null;
        }

        $image_id = $images[0] ?? null; // on récupère l’ID brut directement
        if (!$image_id) return null;

        return esc_url(add_query_arg([
            'id'     => $image_id,
            'taille' => $taille,
        ], site_url('/voir-image-enigme')));
    }


    /**
     * Affiche un bloc <picture> responsive pour une énigme.
     *
     * Génère un élément <picture> HTML avec différentes sources pour les tailles d’image spécifiées,
     * en utilisant le proxy /voir-image-enigme. Si aucune image n’est définie, utilise le placeholder.
     *
     * @param int    $enigme_id  ID de l’énigme concernée.
     * @param string $alt        Texte alternatif pour l’image.
     * @param array  $sizes      Liste des tailles WordPress à inclure (ordre croissant).
     * @return void
     */
    function afficher_picture_vignette_enigme(int $enigme_id, string $alt = '', array $sizes = ['thumbnail', 'medium']): void
    {
        if (!utilisateur_peut_voir_enigme($enigme_id)) return;

        $images = get_field('enigme_visuel_image', $enigme_id, false);
        $image_id = (is_array($images) && !empty($images[0])) ? (int) $images[0] : null;

        // ✅ Fallback si aucune image définie
        if (!$image_id) {
            $image_id = defined('ID_IMAGE_PLACEHOLDER_ENIGME') ? ID_IMAGE_PLACEHOLDER_ENIGME : 3925;
        }

        echo '<picture>' . "\n";

        foreach ($sizes as $taille) {
            $base_url = site_url('/voir-image-enigme');
            $src = esc_url(add_query_arg([
                'id'     => $image_id,
                'taille' => $taille,
            ], $base_url));

            echo '  <source srcset="' . $src . '" type="image/webp">' . "\n";
            echo '  <source srcset="' . $src . '" type="image/png">' . "\n";
        }

        $src_default = esc_url(add_query_arg([
            'id'     => $image_id,
            'taille' => end($sizes),
        ], site_url('/voir-image-enigme')));

        echo '  <img src="' . $src_default . '" alt="' . esc_attr($alt) . '" loading="lazy">' . "\n";
        echo '</picture>' . "\n";
    }



    /**
     * Retourne le chemin absolu (serveur) et le type MIME d’une image à une taille donnée.
     * Si une version WebP existe pour cette taille, elle est priorisée.
     *
     * @param int $image_id ID de l’image WordPress
     * @param string $taille Taille WordPress demandée (ex: 'thumbnail', 'medium', 'full')
     * @return array|null Tableau ['path' => string, 'mime' => string] ou null si introuvable
     */
    function trouver_chemin_image(int $image_id, string $taille = 'full'): ?array
    {
        $src = wp_get_attachment_image_src($image_id, $taille);
        $url = $src[0] ?? null;
        if (!$url) return null;

        $upload_dir = wp_get_upload_dir();
        $path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $url);

        // 🔁 Si une version .webp existe, on la préfère
        $webp_path = preg_replace('/\.(jpe?g|png|gif)$/i', '.webp', $path);
        if ($webp_path !== $path && file_exists($webp_path)) {
            return ['path' => $webp_path, 'mime' => 'image/webp'];
        }

        // 🔁 Sinon, on vérifie le fichier d’origine
        if (file_exists($path)) {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $mime = match ($ext) {
                'jpg', 'jpeg' => 'image/jpeg',
                'png'         => 'image/png',
                'gif'         => 'image/gif',
                'webp'        => 'image/webp',
                default       => 'application/octet-stream',
            };
            return ['path' => $path, 'mime' => $mime];
        }

        return null;
    }



    // ==================================================
    // 🎨 AFFICHAGE STYLISÉ DES ÉNIGMES
    // ==================================================
    /**
     * 🔹 afficher_enigme_stylisee() → Affiche l’énigme avec son style d’affichage (structure unique + blocs surchargeables)
     * 🔸 enigme_get_partial() → Charge un partiel adapté au style (ex: pirate/images.php), avec fallback global.
     */

    /**     
     * Affiche l’énigme avec son style et son état selon le contexte utilisateur.
     *
     * @param int $enigme_id ID de l’énigme à afficher.
     * @param array $statut_data Données de statut retournées par traiter_statut_enigme().
     */
    function afficher_enigme_stylisee(int $enigme_id, array $statut_data = []): void
    {
        if (get_post_type($enigme_id) !== 'enigme') return;

        if (!empty($statut_data)) {
            // statut_data transmis
        } else {
            // Aucune donnée statut_data transmise à afficher_enigme_stylisee()
        }

        $etat = get_field('enigme_cache_etat_systeme', $enigme_id) ?? 'accessible';

        if ($etat !== 'accessible' && !utilisateur_peut_modifier_enigme($enigme_id)) {
            echo '<div class="enigme-inaccessible">';
            echo '<p>🔒 Cette énigme n’est pas accessible actuellement.</p>';
            echo '<p><a href="' . esc_url(home_url('/')) . '" class="bouton-retour-home">← Retour à l’accueil</a></p>';
            echo '</div>';
            return;
        }

        if ($etat !== 'accessible') {
            echo '<div class="enigme-message-interne">';
            echo '<p>🛠️ Cette énigme est en cours d’édition.</p>';
            echo '<p class="explication-organisateur">Elle ne sera visible par les joueurs qu’une fois la chasse validée.</p>';
            echo '</div>';
        }


        if (!empty($statut_data['afficher_message'])) {
            echo $statut_data['message_html'];
        }

        $user_id = get_current_user_id();
        $style = get_field('enigme_style_affichage', $enigme_id) ?? 'defaut';

        echo '<div class="enigme-affichage enigme-style-' . esc_attr($style) . '">';

        foreach (['titre', 'images', 'texte', 'bloc-reponse', 'solution', 'retour-chasse'] as $slug) {
            enigme_get_partial($slug, $style, [
                'post_id' => $enigme_id,
                'user_id' => $user_id,
            ]);
        }

        echo '</div>';
    }


    /**
     * Charge un partiel adapté au style d’énigme (ex: pirate/images.php), avec fallback global.
     *
     * @param string $slug   Nom du bloc (titre, images, etc.)
     * @param string $style  Style d’affichage (ex : 'pirate', 'vintage')
     * @param array  $args   Données à transmettre au partial
     */
    function enigme_get_partial(string $slug, string $style = 'defaut', array $args = []): void
    {
        $base_path = "template-parts/enigme/partials";

        // 🧠 Nouveau : on préfixe tous les fichiers par 'enigme-partial-'
        $slug_final = 'enigme-partial-' . $slug;

        $variant = "{$base_path}/{$style}/{$slug_final}.php";
        $fallback = "{$base_path}/{$slug_final}.php";

        if (locate_template($variant)) {
            get_template_part("{$base_path}/{$style}/{$slug_final}", null, $args);
        } elseif (locate_template($fallback)) {
            get_template_part("{$base_path}/{$slug_final}", null, $args);
        } else {
            error_log("❌ Aucun partial trouvé pour $slug (style: $style)");
        }
    }


    // ==================================================
    // 📬 GESTION DES RÉPONSES MANUELLES (FRONTEND)
    // ==================================================

    // 🔹 afficher_formulaire_reponse_manuelle() → Affiche le formulaire de réponse manuelle (frontend).
    // 🔹 utilisateur_peut_repondre_manuelle() → Vérifie si l'utilisateur peut répondre à une énigme manuelle.
    // 🔹 soumettre_reponse_manuelle() → Traite la soumission d'une réponse manuelle (frontend).



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



    // ==================================================
    // ✉️ ENVOI D'EMAILS (RÉPONSES MANUELLES)
    // ==================================================

    // 🔹 envoyer_mail_reponse_manuelle() → Envoie un mail HTML à l'organisateur avec la réponse (expéditeur = joueur).
    // 🔹 envoyer_mail_resultat_joueur() → Envoie un mail HTML au joueur après validation ou refus de sa réponse.
    // 🔹 envoyer_mail_accuse_reception_joueur() → Envoie un accusé de réception au joueur juste après sa soumission.

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
