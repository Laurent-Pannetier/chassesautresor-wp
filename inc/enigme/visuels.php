<?php
defined('ABSPATH') || exit;

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
