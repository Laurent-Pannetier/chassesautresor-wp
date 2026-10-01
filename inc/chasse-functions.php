<?php
defined('ABSPATH') || exit;

require_once __DIR__ . '/badge-functions.php';

// ==================================================
// 📦 AFFICHAGE
// ==================================================
/**
 * 🔹 afficher_picture_vignette_chasse() → Affiche une balise <picture> responsive pour l’image d’une chasse.
 * 🔹 afficher_chasse_associee_callback → ffiche les informations principales de la chasse associée à l’énigme.
 */

/**
 *
 * @param int    $chasse_id
 * @param string $alt Texte alternatif pour l’image (optionnel)
 */
function afficher_picture_vignette_chasse($chasse_id, $alt = '')
{
    if (!is_numeric($chasse_id)) return;

    $image = get_field('chasse_principale_image', $chasse_id);
    $permalink = get_permalink($chasse_id);

    if (!is_array($image) || empty($image['url'])) {
        echo '<a href="' . esc_url($permalink) . '" class="image-chasse-placeholder">';
        echo '<i class="fa-solid fa-map fa-2x"></i>';
        echo '</a>';
        return;
    }

    $src_small = $image['sizes']['medium'] ?? $image['url'];
    $src_large = $image['sizes']['large'] ?? $image['url'];
    $alt = esc_attr($alt ?: $image['alt'] ?? get_the_title($chasse_id));

    echo '<a href="' . esc_url($permalink) . '">';
    echo '<picture>';
    echo '<source media="(min-width: 768px)" srcset="' . esc_url($src_large) . '">';
    echo '<img src="' . esc_url($src_small) . '" alt="' . $alt . '" loading="lazy">';
    echo '</picture>';
    echo '</a>';
}

/**
 * 🏴‍☠️ Affiche les informations principales de la chasse associée à l’énigme.
 *
 * Informations affichées (sauf si l'énigme est souscrite/en cours) :
 * - Titre de la chasse
 * - Lot
 * - Durée
 * - Icône Discord cliquable (si lien ACF disponible)
 *
 * @return string HTML des informations de la chasse ou chaîne vide si aucune chasse associée ou énigme en cours.
 */
function afficher_chasse_associee_callback()
{
    if (!is_singular('enigme')) return '';

    $enigme_id = get_the_ID();
    $user_id = get_current_user_id();

    // ✅ Si l’énigme est souscrite (en cours), on n'affiche pas la chasse associée
    $statut = enigme_get_statut_light($user_id, $enigme_id);
    if ($statut === 'en_cours') return '';

    $chasse = recuperer_chasse_associee($enigme_id);
    if (!$chasse) return ''; // 🚫 Pas de chasse associée

    $infos_chasse = recuperer_infos_chasse($chasse->ID) ?: [
        'lot' => 'Non spécifié',
        'date_de_debut' => 'Non spécifiée',
        'date_de_fin' => 'Non spécifiée',
    ];

    $lien_discord = get_field('lien_discord', $chasse->ID);
    $icone_discord = esc_url(get_stylesheet_directory_uri() . '/assets/images/discord-icon.png');
    $titre = esc_html(get_the_title($chasse->ID));
    $url = esc_url(get_permalink($chasse->ID));

    ob_start(); ?>
    <section class="chasse-associee">
        <h3>Chasse au Trésor</h3>
        <h2><strong><a href="<?= $url; ?>" class="lien-chasse-associee"><?= $titre; ?></a></strong></h2>
        <p>🏆 <strong>Lot :</strong> <?= esc_html($infos_chasse['lot']); ?></p>
        <p>📅 <strong>Durée :</strong> <?= esc_html($infos_chasse['date_de_debut']); ?> au <?= esc_html($infos_chasse['date_de_fin']); ?></p>

        <?php if (!empty($lien_discord)) : ?>
            <p>
                <a href="<?= esc_url($lien_discord); ?>" target="_blank" rel="noopener noreferrer" aria-label="Rejoindre le Discord">
                    <img src="<?= $icone_discord; ?>" alt="Discord" class="discord-icon">
                </a>
            </p>
        <?php endif; ?>
    </section>
<?php
    return ob_get_clean();
}

/**
 * Détermine si l'organisateur peut demander la validation d'une chasse.
 *
 * @param int $chasse_id ID de la chasse.
 * @param int $user_id   ID de l'utilisateur.
 * @return bool
 */
/**
 * Retourne le HTML d'une solution (PDF ou texte).
 *
 * @param WP_Post $solution Solution post object.
 * @return string
 */
function solution_contenu_html(WP_Post $solution): string
{
    $fichier = get_field('solution_fichier', $solution->ID);
    $texte   = get_field('solution_explication', $solution->ID);
    $content = '';

    if ($texte) {
        $content .= '<div class="solution-text"><p>'
            . wp_kses_post($texte)
            . '</p></div>';
    }

    if ($fichier) {
        if (is_array($fichier)) {
            $fichier_url = $fichier['url'] ?? '';
        } else {
            $fichier_url = wp_get_attachment_url($fichier);
        }

        if (!empty($fichier_url)) {
            $content .= '<object data="' . esc_url($fichier_url)
                . '" type="application/pdf" width="100%" height="800">'
                . '<p>'
                . esc_html__(
                    'Votre navigateur ne peut pas afficher le PDF.',
                    'chassesautresor-com'
                )
                . ' <a href="' . esc_url($fichier_url)
                . '" class="lien-solution-pdf" target="_blank" rel="noopener">'
                . esc_html__('Télécharger', 'chassesautresor-com')
                . '</a></p></object>';
        }
    }

    return $content;
}

/**
 * Affiche la section des solutions pour une chasse.
 *
 * @param int $chasse_id ID de la chasse.
 * @param int $user_id   ID de l'utilisateur courant.
 */
function render_chasse_solutions(int $chasse_id, int $user_id): void
{
    if (get_post_type($chasse_id) !== 'chasse') {
        return;
    }

    $sections = '';

    if (solution_chasse_peut_etre_affichee($chasse_id)
        && utilisateur_peut_voir_solution_chasse($chasse_id, $user_id)) {
        $solution = solution_recuperer_par_objet($chasse_id, 'chasse');
        if ($solution) {
            $content = solution_contenu_html($solution);
            if ($content !== '') {
                $sections .= '<section class="solution">';
                $sections .= '<details><summary>'
                    . esc_html__('Solution de la chasse', 'chassesautresor-com')
                    . '</summary>';
                $sections .= '<div class="solution-content">'
                    . $content
                    . '</div></details></section>';
            }
        }
    }

    if ($sections === '') {
        return;
    }

    echo '<section id="chasse-solutions" class="chasse-solutions">';
    echo '<h2>' . esc_html__('Solutions', 'chassesautresor-com') . '</h2>';
    echo $sections;
    echo '</section>';
}

/**
 * Prépare les données d'affichage des termes associés à une chasse.
 *
 * @param int    $chasse_id ID de la chasse.
 * @param string $taxonomy  Taxonomie ciblée.
 *
 * @return array[]
 */
function chasse_preparer_termes_affichage(int $chasse_id, string $taxonomy): array
{
    $terms = [];

    if (function_exists('wp_get_post_terms')) {
        $terms = wp_get_post_terms($chasse_id, $taxonomy, ['orderby' => 'term_order']);
        if (is_wp_error($terms)) {
            $terms = [];
        }
    }

    if (empty($terms) && function_exists('get_field')) {
        $acf_fields = [
            'chasse_region' => 'chasse_region',
            'theme_chasse'  => 'chasse_theme',
        ];

        if (isset($acf_fields[$taxonomy])) {
            $raw_terms = get_field($acf_fields[$taxonomy], $chasse_id);

            if ($raw_terms instanceof \WP_Term) {
                $terms = [$raw_terms];
            } elseif (is_array($raw_terms)) {
                $terms = chasse_is_list($raw_terms) ? $raw_terms : [$raw_terms];
            } elseif ($raw_terms !== null && $raw_terms !== '') {
                $terms = [$raw_terms];
            }
        }
    }

    if (empty($terms)) {
        return [];
    }

    $items = [];

    foreach ($terms as $term) {
        $item = chasse_normalize_term_for_display($term, $taxonomy);

        if ($item !== null) {
            $items[] = $item;
        }
    }

    return $items;
}

/**
 * Format raw term items into HTML snippets for the meta-etiquette blocks.
 *
 * @param array<int, array<string, mixed>>|null $terms Raw terms as returned by chasse_preparer_termes_affichage.
 *
 * @return string[]
 */
function chasse_format_meta_terms($terms): array
{
    if (!is_array($terms) || $terms === []) {
        return [];
    }

    $escape_html = static function (string $value): string {
        if (function_exists('esc_html')) {
            return esc_html($value);
        }

        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    };

    $format_url = static function (string $url): string {
        if (function_exists('esc_url')) {
            return esc_url($url);
        }

        $sanitized = filter_var($url, FILTER_SANITIZE_URL);

        return is_string($sanitized) ? $sanitized : '';
    };

    $formatted = [];

    foreach ($terms as $term) {
        if (!is_array($term)) {
            continue;
        }

        $raw_name = $term['nom'] ?? ($term['name'] ?? '');
        $name     = trim((string) $raw_name);

        if ($name === '') {
            continue;
        }

        $raw_link = $term['lien'] ?? ($term['link'] ?? '');
        $link     = is_string($raw_link) ? trim($raw_link) : '';

        $escaped_name = $escape_html($name);

        if ($link !== '') {
            $escaped_link = $format_url($link);

            if ($escaped_link !== '') {
                $formatted[] = sprintf(
                    '<a class="meta-etiquette__value" href="%s">%s</a>',
                    $escaped_link,
                    $escaped_name
                );
                continue;
            }
        }

        $formatted[] = sprintf('<span class="meta-etiquette__value">%s</span>', $escaped_name);
    }

    return $formatted;
}

/**
 * Normalise une valeur de terme afin de la rendre exploitable pour l'affichage.
 *
 * @param mixed  $term     Valeur brute issue de WordPress ou d'ACF.
 * @param string $taxonomy Taxonomie ciblée.
 *
 * @return array{nom: string, slug: string, lien: string}|null
 */
function chasse_normalize_term_for_display($term, string $taxonomy): ?array
{
    $wp_term = chasse_resolve_term_candidate($term, $taxonomy);

    if ($wp_term instanceof \WP_Term) {
        $link = '';

        if (function_exists('get_term_link')) {
            $link = get_term_link($wp_term);
            if (is_wp_error($link)) {
                $link = '';
            }
        }

        return [
            'nom'  => $wp_term->name,
            'slug' => $wp_term->slug,
            'lien' => is_string($link) ? $link : '',
        ];
    }

    $name = '';
    $link = '';
    $slug_candidates = [];

    if (is_array($term)) {
        $link_candidates = [
            $term['lien'] ?? null,
            $term['link'] ?? null,
            $term['url'] ?? null,
        ];

        foreach ($link_candidates as $link_candidate) {
            if (!is_string($link_candidate)) {
                continue;
            }

            $trimmed = trim($link_candidate);

            if ($trimmed === '') {
                continue;
            }

            $link = $trimmed;
            break;
        }

        $name_candidates = [
            $term['nom'] ?? null,
            $term['name'] ?? null,
            $term['label'] ?? null,
            $term['title'] ?? null,
            $term['post_title'] ?? null,
            $term['display_name'] ?? null,
            $term['value'] ?? null,
        ];

        foreach ($name_candidates as $candidate) {
            if (!is_string($candidate) && !is_numeric($candidate)) {
                continue;
            }

            $candidate_value = trim((string) $candidate);

            if ($candidate_value === '') {
                continue;
            }

            if (ctype_digit($candidate_value)) {
                continue;
            }

            $taxonomy_pattern = '/^' . preg_quote($taxonomy, '/') . '[_:-]?\d+$/i';
            if (preg_match('/^term[_:-]?\d+$/i', $candidate_value)
                || preg_match('/^term\s+\d+$/i', $candidate_value)
                || preg_match('/^term\s*id\s*[:=]?\s*\d+$/i', $candidate_value)
                || preg_match($taxonomy_pattern, $candidate_value)
            ) {
                continue;
            }

            $name = $candidate_value;
            break;
        }

        $slug_candidates[] = $term['slug'] ?? null;
        if (isset($term['value']) && is_string($term['value'])) {
            $slug_candidates[] = $term['value'];
        }
    } elseif (is_string($term) || is_numeric($term)) {
        $name = trim((string) $term);
    }

    if ($name === '') {
        return null;
    }

    $slug_candidates[] = $name;

    $slug = '';

    foreach ($slug_candidates as $candidate) {
        if (!is_string($candidate) && !is_numeric($candidate)) {
            continue;
        }

        $candidate_value = trim((string) $candidate);

        if ($candidate_value === '') {
            continue;
        }

        if (function_exists('sanitize_title')) {
            $slug = sanitize_title($candidate_value);
        } else {
            $slug = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '-', $candidate_value));
            $slug = trim($slug, '-');
        }

        if ($slug !== '') {
            break;
        }
    }

    return [
        'nom'  => $name,
        'slug' => $slug,
        'lien' => $link,
    ];
}

/**
 * Extract a numeric term identifier from raw data returned by ACF or caches.
 *
 * @param mixed  $value    Raw candidate value.
 * @param string $taxonomy Related taxonomy slug.
 *
 * @return int|null
 */
function chasse_extract_term_id_from_value($value, string $taxonomy): ?int
{
    if (is_int($value)) {
        return $value;
    }

    if (is_float($value) || (is_numeric($value) && !is_string($value))) {
        return (int) $value;
    }

    if (!is_string($value)) {
        return null;
    }

    $trimmed = trim($value);

    if ($trimmed === '') {
        return null;
    }

    if (ctype_digit($trimmed)) {
        return (int) $trimmed;
    }

    $taxonomy_pattern = '/^' . preg_quote($taxonomy, '/') . '[_:-]?\d+$/i';
    $patterns = [
        '/^term[_:-]?(\d+)$/i',
        '/^term\s+(\d+)$/i',
        '/^term\s*id\s*[:=]?\s*(\d+)$/i',
        $taxonomy_pattern,
        '/^id[_:-]?(\d+)$/i',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $trimmed, $matches)) {
            return (int) $matches[1];
        }
    }

    return null;
}

/**
 * Tente de transformer une valeur brute en objet \WP_Term.
 *
 * @param mixed  $candidate Valeur récupérée via ACF ou le cache.
 * @param string $taxonomy  Taxonomie ciblée.
 *
 * @return \WP_Term|null
 */
function chasse_resolve_term_candidate($candidate, string $taxonomy): ?\WP_Term
{
    $taxonomy_candidates = chasse_resolve_taxonomy_aliases($taxonomy);

    $load_term_from_value = static function ($value) use ($taxonomy_candidates): ?\WP_Term {
        if (!function_exists('get_term')) {
            return null;
        }

        $term_id = null;

        foreach ($taxonomy_candidates as $taxonomy_candidate) {
            $term_id = chasse_extract_term_id_from_value($value, $taxonomy_candidate);

            if ($term_id !== null) {
                break;
            }
        }

        if ($term_id === null) {
            return null;
        }

        foreach ($taxonomy_candidates as $taxonomy_candidate) {
            $term = get_term($term_id, $taxonomy_candidate);

            if ($term instanceof \WP_Term) {
                return $term;
            }

            if (function_exists('is_wp_error') && is_wp_error($term)) {
                continue;
            }
        }

        $term = get_term($term_id);

        if ($term instanceof \WP_Term) {
            $term_taxonomy = property_exists($term, 'taxonomy') ? (string) $term->taxonomy : '';

            if ($term_taxonomy === '' || in_array($term_taxonomy, $taxonomy_candidates, true)) {
                return $term;
            }
        }

        return null;
    };

    if ($candidate instanceof \WP_Term) {
        return $candidate;
    }

    if (is_array($candidate) && isset($candidate['term'])) {
        $resolved = chasse_resolve_term_candidate($candidate['term'], $taxonomy);

        if ($resolved instanceof \WP_Term) {
            return $resolved;
        }
    }

    $term_from_root_candidate = $load_term_from_value($candidate);
    if ($term_from_root_candidate instanceof \WP_Term) {
        return $term_from_root_candidate;
    }

    if (is_array($candidate)) {
        $id_keys = ['term_id', 'termId', 'ID', 'id', 'value'];

        foreach ($id_keys as $key) {
            if (!isset($candidate[$key]) || !is_numeric($candidate[$key])) {
                continue;
            }

            $term = $load_term_from_value($candidate[$key]);

            if ($term instanceof \WP_Term) {
                return $term;
            }
        }

        $slug_keys = ['slug', 'value'];

        foreach ($slug_keys as $key) {
            if (!isset($candidate[$key]) || (!is_string($candidate[$key]) && !is_numeric($candidate[$key]))) {
                continue;
            }

            $term = $load_term_from_value($candidate[$key]);

            if ($term instanceof \WP_Term) {
                return $term;
            }

            if (function_exists('get_term_by')) {
                $slug = trim((string) $candidate[$key]);

                if ($slug !== '') {
                    foreach ($taxonomy_candidates as $taxonomy_candidate) {
                        $term = get_term_by('slug', $slug, $taxonomy_candidate);

                        if ($term instanceof \WP_Term) {
                            return $term;
                        }
                    }
                }
            }
        }

        $name_keys = ['nom', 'name', 'label', 'value', 'title', 'post_title', 'display_name'];

        foreach ($name_keys as $key) {
            if (!isset($candidate[$key]) || (!is_string($candidate[$key]) && !is_numeric($candidate[$key]))) {
                continue;
            }

            $term = $load_term_from_value($candidate[$key]);

            if ($term instanceof \WP_Term) {
                return $term;
            }

            if (function_exists('get_term_by')) {
                $name_candidate = trim((string) $candidate[$key]);

                if ($name_candidate === '') {
                    continue;
                }

                foreach ($taxonomy_candidates as $taxonomy_candidate) {
                    $term = get_term_by('name', $name_candidate, $taxonomy_candidate);

                    if ($term instanceof \WP_Term) {
                        return $term;
                    }
                }
            }
        }
    }

    if (is_string($candidate) && function_exists('get_term_by')) {
        $candidate = trim($candidate);

        if ($candidate !== '') {
            $term = $load_term_from_value($candidate);

            if ($term instanceof \WP_Term) {
                return $term;
            }

            foreach ($taxonomy_candidates as $taxonomy_candidate) {
                $term = get_term_by('slug', $candidate, $taxonomy_candidate);

                if ($term instanceof \WP_Term) {
                    return $term;
                }

                $term = get_term_by('name', $candidate, $taxonomy_candidate);

                if ($term instanceof \WP_Term) {
                    return $term;
                }
            }
        }
    }

    return null;
}

/**
 * Build a list of taxonomy aliases that may be used in the database.
 */
function chasse_resolve_taxonomy_aliases(string $taxonomy): array
{
    $candidates = [$taxonomy];

    $aliases = [
        'chasse_region' => ['chasse_regions', 'region', 'regions'],
        'theme_chasse'  => ['chasse_theme', 'theme_chasses', 'themes_chasse'],
    ];

    if (isset($aliases[$taxonomy])) {
        foreach ($aliases[$taxonomy] as $alias) {
            if (!in_array($alias, $candidates, true)) {
                $candidates[] = $alias;
            }
        }
    }

    return $candidates;
}

/**
 * Vérifie si un tableau est indexé numériquement en séquence.
 */
function chasse_is_list(array $array): bool
{
    if (function_exists('array_is_list')) {
        return array_is_list($array);
    }

    if ($array === []) {
        return true;
    }

    return array_keys($array) === range(0, count($array) - 1);
}

/**
 * Prépare les représentations courtes des dates d'une chasse.
 *
 * @param string|null $start_date Date de début brute.
 * @param string|null $end_date   Date de fin brute.
 * @param bool        $is_unlimited Indique si la chasse est illimitée.
 *
 * @return array{date_debut_court: string, date_fin_court: string}
 */
function chasse_preparer_dates_courtes(?string $start_date, ?string $end_date, bool $is_unlimited): array
{
    $start_value = $start_date !== null ? (string) $start_date : null;
    $end_value   = $end_date !== null ? (string) $end_date : null;

    $start_timestamp = false;
    if ($start_value !== null && $start_value !== '') {
        if (function_exists('convertir_en_timestamp')) {
            $start_timestamp = convertir_en_timestamp($start_value);
        } else {
            $start_timestamp = strtotime(str_replace('/', '-', $start_value));
        }
    }

    $end_timestamp = false;
    if (!$is_unlimited && $end_value !== null && $end_value !== '') {
        if (function_exists('convertir_en_timestamp')) {
            $end_timestamp = convertir_en_timestamp($end_value);
        } else {
            $end_timestamp = strtotime(str_replace('/', '-', $end_value));
        }
    }

    if (function_exists('_x')) {
        /* translators: Short date format for hunt metadata (day/month/year). */
        $short_date_format = _x('d/m/y', 'short date format for hunts', 'chassesautresor-com');
    } else {
        $short_date_format = 'd/m/y';
    }

    $non_specifiee_label = function_exists('__')
        ? __('Non spécifiée', 'chassesautresor-com')
        : 'Non spécifiée';
    $illimitee_label = function_exists('__')
        ? __('Illimitée', 'chassesautresor-com')
        : 'Illimitée';

    $start_short = $start_timestamp
        ? wp_date($short_date_format, $start_timestamp)
        : $non_specifiee_label;

    $end_short = $is_unlimited
        ? $illimitee_label
        : ($end_timestamp ? wp_date($short_date_format, $end_timestamp) : $non_specifiee_label);

    return [
        'date_debut_court' => $start_short,
        'date_fin_court'   => $end_short,
    ];
}

function preparer_infos_affichage_carte_chasse(int $chasse_id, int $word_limit = 300, array $options = []): array
{
    if (get_post_type($chasse_id) !== 'chasse') {
        return [];
    }

    $options = wp_parse_args(
        $options,
        [
            'badge_format' => 'text',
        ]
    );

    $titre     = get_the_title($chasse_id);
    $permalink = get_permalink($chasse_id);

    $description = get_field('chasse_principale_description', $chasse_id);
    $description = preg_replace('/^\s*Présentation\s*2\.1\s*/i', '', (string) $description);
    $texte_complet = wp_strip_all_tags($description);
    $extrait = wp_trim_words($texte_complet, $word_limit, '...');

    $image_data = get_field('chasse_principale_image', $chasse_id);
    $image_id = 0;
    $image = '';
    $image_width = 0;
    $image_height = 0;
    $image_size = 'medium_large';

    if (is_array($image_data)) {
        if (!empty($image_data['ID'])) {
            $image_id = (int) $image_data['ID'];
        } elseif (!empty($image_data['id'])) {
            $image_id = (int) $image_data['id'];
        }
    } elseif (!empty($image_data)) {
        $image_id = (int) $image_data;
    }

    if (!$image_id) {
        $image_id = get_post_thumbnail_id($chasse_id);
    }

    if ($image_id) {
        $preferred_sizes = ['medium_large', 'large', 'medium', 'full'];

        foreach ($preferred_sizes as $size_candidate) {
            $image_src = wp_get_attachment_image_src($image_id, $size_candidate);

            if (!is_array($image_src) || empty($image_src[0])) {
                continue;
            }

            $image = $image_src[0];
            $image_width = (int) $image_src[1];
            $image_height = (int) $image_src[2];
            $image_size = $size_candidate;

            break;
        }

        if ($image === '') {
            $image = wp_get_attachment_url($image_id) ?: '';
            $image_size = 'full';
        }
    } elseif (is_array($image_data) && !empty($image_data['url'])) {
        $image = (string) $image_data['url'];
    } elseif (is_string($image_data) && $image_data !== '') {
        $image = $image_data;
    }

    $image_ratio = '';
    $image_ratio_padding = '';

    $organisateur_id = get_organisateur_from_chasse($chasse_id);

    if ($image_width > 0 && $image_height > 0) {
        $image_ratio = $image_width . ' / ' . $image_height;

        $ratio_value = $image_width / $image_height;
        if ($ratio_value > 0) {
            $ratio_padding_value = 100 / $ratio_value;
            $image_ratio_padding = rtrim(rtrim(sprintf('%.6F', $ratio_padding_value), '0'), '.');
            if ($image_ratio_padding !== '') {
                $image_ratio_padding .= '%';
            }
        }
    }

    $champs = chasse_get_champs($chasse_id);
    $regions = chasse_preparer_termes_affichage($chasse_id, 'chasse_region');
    $themes = chasse_preparer_termes_affichage($chasse_id, 'theme_chasse');
    $region_principale = !empty($regions) ? $regions[0] : null;
    $titre_recompense  = $champs['titre_recompense'];
    $valeur_recompense = $champs['valeur_recompense'];
    $cout_points       = (int) $champs['cout_points'];
    $date_debut        = $champs['date_debut'];
    $date_fin          = $champs['date_fin'];
    $illimitee         = $champs['illimitee'];
    $date_decouverte   = $champs['date_decouverte'];

    verifier_ou_recalculer_statut_chasse($chasse_id);
    $statut            = get_field('chasse_cache_statut', $chasse_id) ?: 'revision';
    $statut_validation = get_field('chasse_cache_statut_validation', $chasse_id);

    if ($statut === 'termine' && $date_decouverte) {
        $date_fin = $date_decouverte;
    }

    $date_debut_affichage = formater_date($date_debut);
    $date_fin_affichage   = $illimitee
        ? __('Illimitée', 'chassesautresor-com')
        : ($date_fin ? formater_date($date_fin) : __('Non spécifiée', 'chassesautresor-com'));

    $dates_courtes    = chasse_preparer_dates_courtes($date_debut, $date_fin, (bool) $illimitee);
    $date_debut_court = $dates_courtes['date_debut_court'];
    $date_fin_court   = $dates_courtes['date_fin_court'];

    $nb_joueurs       = compter_joueurs_engages_chasse($chasse_id);
    $nb_joueurs_label = formater_nombre_joueurs($nb_joueurs);
    $badge_infos = chasse_preparer_badge_statut($statut, $statut_validation);
    $badge_format = ($options['badge_format'] === 'icon' && $badge_infos['icon_html']) ? 'icon' : 'text';
    $badge_class = $badge_infos['base_class'];
    $badge_tooltip = $badge_infos['label'];
    $badge_requires_interaction = ($badge_format === 'icon' && $badge_tooltip !== '');

    if ($badge_format === 'icon') {
        $badge_class .= ' badge-statut--format-icon';
    }

    $badge_content = $badge_format === 'icon'
        ? '<span class="badge-statut__icon" aria-hidden="true">' . $badge_infos['icon_html'] . '</span>'
            . '<span class="screen-reader-text">' . esc_html($badge_infos['label']) . '</span>'
        : esc_html($badge_infos['label']);

    $enigmes_associees = recuperer_enigmes_associees($chasse_id);
    $total_enigmes     = count($enigmes_associees);

    $user_id = get_current_user_id();
    $progression = chasse_calculer_progression_utilisateur($chasse_id, $user_id);
    $cta_data    = generer_cta_chasse($chasse_id, $user_id);

    $liens = get_field('chasse_principale_liens', $chasse_id);
    $liens = is_array($liens) ? $liens : [];
    if (empty($liens)) {
        $orga_id   = get_organisateur_from_chasse($chasse_id);
        $liens_org = organisateur_get_liens_actifs($orga_id);
        foreach ($liens_org as $type => $url) {
            $liens[] = [
                'chasse_principale_liens_type' => $type,
                'chasse_principale_liens_url'  => $url,
            ];
        }
    }
    $has_lien = false;
    foreach ($liens as $entree) {
        $type_raw = $entree['chasse_principale_liens_type'] ?? null;
        $url      = $entree['chasse_principale_liens_url'] ?? null;
        $type     = is_array($type_raw) ? ($type_raw[0] ?? '') : $type_raw;
        if (is_string($type) && trim($type) !== '' && is_string($url) && trim($url) !== '') {
            $has_lien = true;
            break;
        }
    }
    $liens_html = $has_lien ? render_liens_publics($liens, 'chasse') : '';

    $footer_icones = [];
    if ($cout_points > 0) {
        $footer_icones[] = 'coins-points';
    }

    $mode_validation = '';
    $modes          = [];
    $enigmes_validables = [];
    foreach ($enigmes_associees as $eid) {
        $mode = get_field('enigme_mode_validation', $eid);
        if ($mode) {
            $modes[$mode] = true;
            if ($mode !== 'aucune') {
                $enigmes_validables[] = (int) $eid;
            }
        }
    }
    if (isset($modes['manuelle'])) {
        $footer_icones[] = 'reply-mail';
        $mode_validation = 'manuelle';
    } elseif (isset($modes['automatique'])) {
        $footer_icones[] = 'reply-auto';
        $mode_validation = 'automatique';
    }

    $resolues_validables = 0;
    if (!empty($enigmes_validables) && !empty($progression['resolvables']) && $progression['resolvables'] > 0 && $user_id) {
        $resolues_validables = cat_get_hunt_progress_service()->countSolvedRiddles(
            (int) $user_id,
            $enigmes_validables
        );
    }

    $lot_html = '';
    if (!empty($titre_recompense) && (float) $valeur_recompense > 0) {
        $footer_icones[] = 'trophy';
        $lot_html       = '<div class="chasse-lot" aria-live="polite">'
            . '<span class="chasse-lot__icon">' . get_svg_icon('trophy') . '</span>'
            . '<span class="screen-reader-text">' . esc_html__('Récompense :', 'chassesautresor-com') . '</span>'
            . '<span class="badge-recompense avec-recompense">'
            . esc_html(number_format_i18n(round((float) $valeur_recompense), 0))
            . '<span class="badge-recompense__devise prix-devise">€</span>'
            . '</span>'
            . '<span class="chasse-lot__title">' . esc_html($titre_recompense) . '</span>'
            . '</div>';
    }

    $extrait_html = $extrait
        ? '<p class="chasse-intro-extrait liste-elegante">' . esc_html($extrait) . '</p>'
        : '';

    $cta_html    = $cta_data['cta_html'] ?? '';
    $cta_message = $cta_data['cta_message'] ?? '';

    $footer_liens_html = '';
    $footer_icones_html = '';

    if ($has_lien) {
        $footer_liens_html = '<div class="liens-publics-carte">' . $liens_html . '</div>';
    }

    if (!empty($footer_icones)) {
        $footer_icones_html = '<div class="footer-icones">';
        foreach ($footer_icones as $icn) {
            $footer_icones_html .= get_svg_icon($icn);
        }
        $footer_icones_html .= '</div>';
    }

    $footer_html = '';
    if ($footer_liens_html || $footer_icones_html) {
        $footer_html = '<div class="carte-ligne__footer meta-etiquette">'
            . $footer_icones_html
            . $footer_liens_html
            . '</div>';
    }

    $infos = [
        'titre'             => $titre,
        'permalink'         => $permalink,
        'image_id'          => $image_id,
        'image'             => $image,
        'image_ratio'       => $image_ratio,
        'image_ratio_padding' => $image_ratio_padding,
        'image_size'        => $image_size,
        'total_enigmes'     => $total_enigmes,
        'nb_joueurs'        => $nb_joueurs,
        'nb_joueurs_label'  => $nb_joueurs_label,
        'cout_points'       => $cout_points,
        'mode_validation'   => $mode_validation,
        'mode_fin'          => $champs['mode_fin'],
        'date_debut'        => $date_debut_affichage,
        'date_fin'          => $date_fin_affichage,
        'date_debut_court'  => $date_debut_court,
        'date_fin_court'    => $date_fin_court,
        'badge_class'       => trim($badge_class),
        'statut_label'      => $badge_infos['label'],
        'statut_icon'       => $badge_infos['icon_html'],
        'statut_icon_name'  => $badge_infos['icon_name'],
        'badge_format'      => $badge_format,
        'badge_content'     => $badge_content,
        'badge_tooltip'     => $badge_tooltip,
        'badge_requires_interaction' => $badge_requires_interaction,
        'classe_statut'     => $badge_infos['base_class'],
        'extrait_html'      => $extrait_html,
        'lot_html'          => $lot_html,
        'cta_html'          => $cta_html,
        'cta_message'       => $cta_message,
        'cta_type'         => $cta_data['type'] ?? '',
        'footer_html'       => $footer_html,
        'regions'           => $regions,
        'themes'            => $themes,
        'region_principale' => $region_principale,
        'organisateur_id'   => $organisateur_id,
    ];

    if (!empty($progression['resolvables'])) {
        $resolvables_count = (int) $progression['resolvables'];
        $infos['progression'] = $progression;
        $infos['resolues_validables'] = min($resolues_validables, $resolvables_count);
    }

    return $infos;
}

/**
 * Prépare les informations complètes d'affichage pour une chasse.
 * Cette fonction centralise tous les appels ACF et fonctions métiers
 * afin d'éviter les appels répétés lors du rendu d'une page.
 *
 * @param int      $chasse_id ID de la chasse.
 * @param int|null $user_id   Utilisateur courant pour le CTA. Par défaut get_current_user_id().
 * @return array
 */
function preparer_infos_affichage_chasse(int $chasse_id, ?int $user_id = null): array
{
    static $memo = [];

    $user_id  = $user_id ?? get_current_user_id();
    $memo_key = $chasse_id . '-' . $user_id;

    if (get_post_type($chasse_id) !== 'chasse') {
        return [];
    }

    if (isset($memo[$memo_key])) {
        return $memo[$memo_key];
    }

    $cache = new ChassesAuTresor\Core\Content\HuntDisplayViewCacheService();
    $cachedData = $cache->get($chasse_id, $user_id);
    if ($cachedData !== null) {
        $memo[$memo_key] = $cachedData;
        return $memo[$memo_key];
    }

    $champs = chasse_get_champs($chasse_id);
    $regions = chasse_preparer_termes_affichage($chasse_id, 'chasse_region');
    $themes = chasse_preparer_termes_affichage($chasse_id, 'theme_chasse');
    $region_principale = !empty($regions) ? $regions[0] : null;

    $raw_start_date   = $champs['date_debut'] ?? null;
    $raw_end_date     = $champs['date_fin'] ?? null;
    $raw_discovery    = $champs['date_decouverte'] ?? null;
    $is_unlimited     = !empty($champs['illimitee']);
    $status_value     = get_field('chasse_cache_statut', $chasse_id) ?: 'revision';
    $status_validation = get_field('chasse_cache_statut_validation', $chasse_id);

    if ($status_value === 'termine' && !empty($raw_discovery)) {
        $raw_end_date = $raw_discovery;
    }

    $start_date_value = null;
    if (is_string($raw_start_date) && $raw_start_date !== '') {
        $start_date_value = $raw_start_date;
    } elseif (is_numeric($raw_start_date)) {
        $start_date_value = (string) $raw_start_date;
    }

    $end_date_value = null;
    if (is_string($raw_end_date) && $raw_end_date !== '') {
        $end_date_value = $raw_end_date;
    } elseif (is_numeric($raw_end_date)) {
        $end_date_value = (string) $raw_end_date;
    }

    $dates_courtes = chasse_preparer_dates_courtes($start_date_value, $end_date_value, (bool) $is_unlimited);

    $description   = get_field('chasse_principale_description', $chasse_id);
    $texte_complet = wp_strip_all_tags($description);
    $extrait       = wp_trim_words($texte_complet, 60, '...');

    $image_raw = get_field('chasse_principale_image', $chasse_id);
    $image_id  = is_array($image_raw) ? ($image_raw['ID'] ?? null) : $image_raw;
    $image_url = $image_id ? wp_get_attachment_image_src($image_id, 'chasse-fiche')[0] : null;
    $image_alt = $image_id ? get_post_meta($image_id, '_wp_attachment_image_alt', true) : '';
    $image_alt = $image_alt !== '' ? $image_alt : get_the_title($chasse_id);

    $liens = get_field('chasse_principale_liens', $chasse_id);
    $liens = is_array($liens) ? $liens : [];

    $enigmes             = recuperer_enigmes_associees($chasse_id);
    $nb_enigmes_payantes = 0;
    foreach ($enigmes as $eid) {
        $cout = (int) get_field('enigme_tentative_cout_points', $eid);
        $mode = get_field('enigme_mode_validation', $eid);
        if ($cout > 0 && $mode !== 'aucune') {
            $nb_enigmes_payantes++;
        }
    }

    $progression = chasse_calculer_progression_utilisateur($chasse_id, $user_id);
    $cta_data    = generer_cta_chasse($chasse_id, $user_id);

    $nb_joueurs = compter_joueurs_engages_chasse($chasse_id);
    $top_nb      = 0;
    $top_enigmes = 0;
    if ($nb_joueurs > 0) {
        $participants = chasse_lister_participants($chasse_id, $nb_joueurs, 0, 'resolution', 'DESC');
        $top_enigmes  = $participants[0]['nb_resolues'] ?? 0;
        if ($top_enigmes > 0) {
            foreach ($participants as $p) {
                if ($p['nb_resolues'] === $top_enigmes) {
                    $top_nb++;
                } else {
                    break;
                }
            }
        }
    }

    $memo[$memo_key] = [
        'champs'              => $champs,
        'description'         => $description,
        'texte_complet'       => $texte_complet,
        'extrait'             => $extrait,
        'image_raw'           => $image_raw,
        'image_id'            => $image_id,
        'image_url'           => $image_url,
        'image_alt'           => $image_alt,
        'liens'               => $liens,
        'enigmes_associees'   => $enigmes,
        'total_enigmes'       => count($enigmes),
        'progression'         => $progression,
        'cta_data'            => $cta_data,
        'cta_type'            => $cta_data['type'] ?? '',
        'nb_joueurs'          => $nb_joueurs,
        'nb_enigmes_payantes' => $nb_enigmes_payantes,
        'top_avances'         => [
            'nb'      => $top_nb,
            'enigmes' => $top_enigmes,
        ],
        'statut'            => $status_value,
        'statut_validation' => $status_validation,
        'regions'           => $regions,
        'themes'            => $themes,
        'region_principale' => $region_principale,
        'date_debut_court'  => $dates_courtes['date_debut_court'],
        'date_fin_court'    => $dates_courtes['date_fin_court'],
    ];

    $cache->put($chasse_id, $user_id, $memo[$memo_key]);

    return $memo[$memo_key];
}
