<?php
defined('ABSPATH') || exit;
require_once __DIR__ . '/../sidebar.php';
require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/indices.php';

    // ==================================================
    // 🎨 AFFICHAGE STYLISÉ DES ÉNIGMES
    // ==================================================
    /**
     * 🔹 afficher_enigme_stylisee() → Affiche l’énigme avec son style d’affichage (structure unique + blocs surchargeables)
     * 🔸 enigme_get_partial() → Charge un partiel adapté au style (ex: pirate/images.php), avec fallback global.
     */

    /**
     * Build a cache key for rendered enigma blocks.
     *
     * @param string $block    Block identifier.
     * @param int    $post_id  Enigma identifier.
     *
     * @return string
     */
    function enigme_get_render_cache_key(string $block, int $post_id): string
    {
        $version = (int) get_option('enigme_permissions_cache_version', 1);

        return $block . '_' . $post_id . '_' . $version;
    }

    /**
     * Clear cached rendering for a given enigma.
     *
     * @param int $post_id Enigma identifier.
     */
    function enigme_clear_render_cache(int $post_id): void
    {
        wp_cache_delete(enigme_get_render_cache_key('enigme_sidebar', $post_id), 'chassesautresor');
        wp_cache_delete(enigme_get_render_cache_key('enigme_solution', $post_id), 'chassesautresor');
    }

    /**
     * Bump cache version when user permissions change.
     *
     * @param mixed ...$args Unused.
     */
    function enigme_bump_permissions_cache_version(...$args): void
    {
        $version = (int) get_option('enigme_permissions_cache_version', 1);
        update_option('enigme_permissions_cache_version', $version + 1);
    }

    add_action('save_post_enigme', 'enigme_clear_render_cache', 10, 1);
    add_action('set_user_role', 'enigme_bump_permissions_cache_version', 10, 3);
    add_action('profile_update', 'enigme_bump_permissions_cache_version', 10, 2);
    add_action('user_register', 'enigme_bump_permissions_cache_version', 10, 1);
    add_action('deleted_user', 'enigme_bump_permissions_cache_version', 10, 1);
    add_action('added_user_meta', 'enigme_bump_permissions_cache_version', 10, 4);
    add_action('updated_user_meta', 'enigme_bump_permissions_cache_version', 10, 4);
    add_action('deleted_user_meta', 'enigme_bump_permissions_cache_version', 10, 4);

    /**
     * Clear solution caches when a solution is saved.
     *
     * @param int $solution_id Solution identifier.
     */
    function enigme_clear_render_cache_on_solution_save(int $solution_id): void
    {
        $target = get_field('solution_cible_type', $solution_id);

        if ($target === 'enigme') {
            $enigme_id = (int) get_field('solution_enigme_linked', $solution_id);
            if ($enigme_id) {
                enigme_clear_render_cache($enigme_id);
            }

            return;
        }

        if ($target === 'chasse') {
            $chasse_id = (int) get_field('solution_chasse_linked', $solution_id);
            if ($chasse_id) {
                $enigmes = recuperer_enigmes_pour_chasse($chasse_id);
                foreach ($enigmes as $enigme) {
                    enigme_clear_render_cache((int) $enigme->ID);
                }
            }
        }
    }

    add_action('save_post_solution', 'enigme_clear_render_cache_on_solution_save', 20, 1);
    /**
     * Clear sidebar caches for a given hunt and user.
     *
     * @param int $chasse_id Hunt identifier.
     * @param int $user_id   User identifier.
     */
    function enigme_clear_sidebar_cache(int $chasse_id, int $user_id): void
    {
        wp_cache_delete('enigme_sidebar_progression_' . $chasse_id . '_' . $user_id, 'chassesautresor');
    }

    /**
     * Clear sidebar caches when an enigma is solved.
     *
     * @param int $user_id   User identifier.
     * @param int $enigme_id Enigma identifier.
     */
    function enigme_clear_sidebar_cache_on_solve(int $user_id, int $enigme_id): void
    {
        $chasse_id = recuperer_id_chasse_associee($enigme_id);
        if ($chasse_id) {
            enigme_clear_sidebar_cache($chasse_id, $user_id);
        }
        wp_cache_delete('enigme_sidebar_resolution_' . $enigme_id, 'chassesautresor');
    }

    add_action('enigme_resolue', 'enigme_clear_sidebar_cache_on_solve', 10, 2);

    /**
     * Determine if the enigma menu should be displayed for a user.
     *
     * @param int    $user_id     User identifier.
     * @param int    $chasse_id   Associated hunt ID.
     * @param string $chasse_stat Current hunt status.
     *
     * @return bool
     */
    function enigme_user_can_see_menu(int $user_id, int $chasse_id, string $chasse_stat): bool
    {
        if (!$chasse_id) {
            return false;
        }

        $validation_status = get_field('chasse_cache_statut_validation', $chasse_id) ?? '';
        $is_admin          = current_user_can('administrator');
        $is_associated     = utilisateur_est_organisateur_associe_a_chasse($user_id, $chasse_id);
        $is_organizer      = est_organisateur($user_id);

        if (($is_admin || ($is_organizer && $is_associated)) && $validation_status !== 'banni') {
            return true;
        }

        if (
            !function_exists('utilisateur_est_engage_dans_chasse')
            || !utilisateur_est_engage_dans_chasse($user_id, $chasse_id)
        ) {
            return false;
        }

        return !in_array($chasse_stat, ['revision', 'a_venir'], true);
    }

    /**
     * Render a bar chart section used for stats.
     *
     * @param string $title         Section title.
     * @param int    $user_rate     Percentage for the current user.
     * @param int    $avg_rate      Average percentage among players.
     * @param string $section_class Additional CSS class for the section.
     *
     * @return string
     */
    function enigme_render_bar_row(string $label, int $rate, string $fill_style = ''): string
    {
        $inside = $rate >= 50;
        $style  = $fill_style === '' ? '' : $fill_style . ';';

        $outside_style = $rate === 0
            ? 'left:4px;'
            : 'left:calc(' . $rate . '% + 4px);';

        ob_start();
        ?>
        <div class="bar-row">
          <span class="bar-label"><?= esc_html($label); ?></span>
          <div class="bar-wrapper">
            <div class="bar-fill" style="<?= esc_attr($style); ?>width:<?= esc_attr($rate); ?>%;">
              <?php if ($inside) : ?>
                <span class="bar-value"><?= esc_html($rate); ?>%</span>
              <?php endif; ?>
            </div>
            <?php if (!$inside) : ?>
              <span class="bar-value bar-value--outside" style="<?= esc_attr($outside_style); ?>">
                <?= esc_html($rate); ?>%
              </span>
            <?php endif; ?>
          </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    function enigme_render_bar_section(string $title, int $user_rate, int $avg_rate, string $section_class): string
    {
        ob_start();
        ?>
        <section class="<?= esc_attr($section_class); ?>">
          <h3><?= esc_html($title); ?></h3>
          <div class="stats-bar-chart">
            <?= enigme_render_bar_row(esc_html__('Vous', 'chassesautresor-com'), $user_rate, 'background-color:var(--color-primary)'); ?>
            <?= enigme_render_bar_row(esc_html__('Moyenne', 'chassesautresor-com'), $avg_rate); ?>
          </div>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    function enigme_render_bar_subsection(
        string $title,
        int $user_rate,
        int $avg_rate,
        string $section_class,
        string $help_message = '',
        string $help_label = ''
    ): string {
        ob_start();
        ?>
        <div class="<?= esc_attr($section_class); ?>">
          <p class="aside-subsection-title">
            <?= esc_html($title); ?>
            <?php if ($help_message !== '') : ?>
              <?php
              $icon_args = [
                  'aria_label' => $help_label,
                  'message'    => $help_message,
                  'classes'    => 'mode-fin-aide stat-help',
              ];

              if ($help_label !== '') {
                  $icon_args['attributes'] = [
                      'data-title' => $help_label,
                  ];
              }

              get_template_part(
                  'template-parts/common/help-icon',
                  null,
                  $icon_args
              );
              ?>
            <?php endif; ?>
          </p>
          <div class="stats-bar-chart">
            <?= enigme_render_bar_row(
                esc_html__('Vous', 'chassesautresor-com'),
                $user_rate,
                'background-color:var(--color-primary)'
            ); ?>
            <?= enigme_render_bar_row(esc_html__('Moyenne', 'chassesautresor-com'), $avg_rate); ?>
          </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    function enigme_render_single_bar_subsection(
        string $title,
        int $rate,
        string $section_class,
        string $help_message = '',
        string $help_label = ''
    ): string {
        ob_start();
        ?>
        <div class="<?= esc_attr($section_class); ?>">
          <p class="aside-subsection-title">
            <?= esc_html($title); ?>
            <?php if ($help_message !== '') : ?>
              <?php
              $icon_args = [
                  'aria_label' => $help_label,
                  'message'    => $help_message,
                  'classes'    => 'mode-fin-aide stat-help',
              ];

              if ($help_label !== '') {
                  $icon_args['attributes'] = [
                      'data-title' => $help_label,
                  ];
              }

              get_template_part(
                  'template-parts/common/help-icon',
                  null,
                  $icon_args
              );
              ?>
            <?php endif; ?>
          </p>
          <div class="stats-bar-chart">
            <?= enigme_render_bar_row($title, $rate); ?>
          </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Build meta labels HTML for the sidebar.
     *
     * @param int $enigme_id Enigma identifier.
     *
     * @return string
     */
    function enigme_sidebar_metas_html(int $enigme_id): string
    {
        if (!function_exists('enigme_compter_joueurs_engages')) {
            require_once __DIR__ . '/stats.php';
        }

        $nb_joueurs = enigme_compter_joueurs_engages($enigme_id);
        $mode       = get_field('enigme_mode_validation', $enigme_id);

        $html  = '<div class="bloc-metas-inline bloc-metas-inline--compact">';
        $html .= '<div class="meta-etiquette"><span>'
            . esc_html__('Nb joueurs :', 'chassesautresor-com')
            . '</span><strong>' . esc_html($nb_joueurs) . '</strong></div>';

        if ($mode !== 'aucune') {
            $tentatives = enigme_compter_tentatives($enigme_id);
            $html      .= '<div class="meta-etiquette"><span>'
                . esc_html__('Nb tentatives :', 'chassesautresor-com')
                . '</span><strong>' . esc_html($tentatives) . '</strong></div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Build progression histogram HTML for the sidebar.
     *
     * @param int|null $chasse_id Hunt identifier.
     * @param int      $user_id   Current user identifier.
     *
     * @return string
     */
    function enigme_sidebar_progression_html(?int $chasse_id, int $user_id): string
    {
        if (!$chasse_id || !$user_id) {
            return '';
        }

        $cache_key = 'enigme_sidebar_progression_' . $chasse_id . '_' . $user_id;
        $data      = wp_cache_get($cache_key, 'chassesautresor');

        if (!is_array($data)) {
            $enigme_ids    = recuperer_ids_enigmes_pour_chasse($chasse_id);
            $total_enigmes = count($enigme_ids);
            $user_rate     = 0;

            if ($total_enigmes > 0) {
                global $wpdb;
                $table        = $wpdb->prefix . 'engagements';
                $placeholders = implode(',', array_fill(0, count($enigme_ids), '%d'));
                $sql          = $wpdb->prepare(
                    "SELECT COUNT(DISTINCT enigme_id) FROM {$table} WHERE user_id = %d AND enigme_id IN ($placeholders)",
                    $user_id,
                    ...$enigme_ids
                );
                $engagees = (int) $wpdb->get_var($sql);
                $user_rate = (100 * $engagees) / $total_enigmes;
            }

            $avg_rate = chasse_calculer_taux_engagement($chasse_id);
            $data     = [
                'user' => (int) round($user_rate),
                'avg'  => (int) round($avg_rate),
            ];

            wp_cache_set($cache_key, $data, 'chassesautresor', HOUR_IN_SECONDS);
        }

        return enigme_render_bar_subsection(
            esc_html__('Progression', 'chassesautresor-com'),
            $data['user'],
            $data['avg'],
            'enigme-progression',
            esc_html__(
                'Part moyenne des énigmes auxquelles chaque joueur a participé, '
                . 'rapportée au nombre total d’énigmes de la chasse. '
                . 'Vous : Part des énigmes auxquelles vous avez accédé '
                . 'Moyenne : Moyenne sur l’ensemble des joueurs.',
                'chassesautresor-com'
            ),
            esc_attr__(
                'Définition de la progression',
                'chassesautresor-com'
            )
        );
    }

    /**
     * Build resolution histogram HTML for the sidebar.
     *
     * @param int $enigme_id Enigma identifier.
     *
     * @return string
     */
    function enigme_sidebar_resolution_html(int $enigme_id): string
    {
        if ($enigme_id <= 0) {
            return '';
        }

        $cache_key = 'enigme_sidebar_resolution_' . $enigme_id;
        $rate      = wp_cache_get($cache_key, 'chassesautresor');

        if (!is_int($rate)) {
            global $wpdb;
            $table_engagements = $wpdb->prefix . 'engagements';
            $table_statuts     = $wpdb->prefix . 'enigme_statuts_utilisateur';

            $access_sql = $wpdb->prepare(
                "SELECT COUNT(DISTINCT user_id) FROM {$table_engagements} WHERE enigme_id = %d",
                $enigme_id
            );
            $accessed = (int) $wpdb->get_var($access_sql);

            if ($accessed > 0) {
                $solve_sql = $wpdb->prepare(
                    "SELECT COUNT(DISTINCT user_id) FROM {$table_statuts} WHERE enigme_id = %d AND statut IN ('resolue','terminee','terminée')",
                    $enigme_id
                );
                $solved = (int) $wpdb->get_var($solve_sql);
                $rate   = (int) round((100 * $solved) / $accessed);
            } else {
                $rate = 0;
            }

            wp_cache_set($cache_key, $rate, 'chassesautresor', HOUR_IN_SECONDS);
        }

        return enigme_render_single_bar_subsection(
            esc_html__('Résolution', 'chassesautresor-com'),
            $rate,
            'enigme-resolution',
            esc_html__(
                'Part moyenne des énigmes auxquelles chaque joueur a participé, '
                . 'rapportée au nombre total d’énigmes de la chasse.',
                'chassesautresor-com'
            ),
            esc_attr__(
                'Définition du taux de résolution',
                'chassesautresor-com'
            )
        );
    }

    /**
     * Build winners table HTML for the sidebar.
     *
     * @param int $enigme_id Enigma identifier.
     * @param int $user_id   Current user identifier.
     * @param int $page      Page number.
     *
     * @return string
     */
    function enigme_sidebar_gagnants_html(int $enigme_id, int $user_id, int $page = 1): string
    {
        if (!function_exists('enigme_lister_resolveurs')) {
            require_once __DIR__ . '/stats.php';
        }

        global $wpdb;
        $per_page = 10;
        $solvers  = property_exists($wpdb, 'users') ? enigme_lister_resolveurs($enigme_id) : [];
        $total    = count($solvers);
        $pages    = max(1, (int) ceil($total / $per_page));
        $page     = max(1, min($page, $pages));
        $offset   = ($page - 1) * $per_page;
        $slice    = array_slice($solvers, $offset, $per_page);

        ob_start();
        get_template_part(
            'template-parts/enigme/partials/enigme-partial-gagnants',
            null,
            [
                'gagnants'  => $slice,
                'page'      => $page,
                'pages'     => $pages,
                'user_id'   => $user_id,
                'total'     => $total,
            ]
        );
        return (string) ob_get_clean();
    }


    /**
     * Render the hero section for the enigma.
     *
     * @param int    $enigme_id Enigma identifier.
     * @param string $style     Display style.
     * @param int    $user_id   Current user ID.
     */
    function render_enigme_hero(int $enigme_id, string $style, int $user_id): void
    {
        echo '<section class="hero-visuel">';
        enigme_get_partial(
            'images',
            $style,
            [
                'post_id' => $enigme_id,
                'user_id' => $user_id,
            ]
        );
        echo '</section>';
    }

    /**
     * Render the title and subtitle section of the enigma.
     *
     * @param int    $enigme_id Enigma identifier.
     * @param string $style     Display style.
     * @param int    $user_id   Current user ID.
     */
    function render_enigme_title(int $enigme_id, string $style, int $user_id): void
    {
        echo '<header class="bloc-titre enigme-header">';
        enigme_get_partial(
            'titre',
            $style,
            [
                'post_id' => $enigme_id,
                'user_id' => $user_id,
            ]
        );
        echo '</header>';
    }

    /**
     * Render the textual content section of the enigma.
     *
     * @param int    $enigme_id Enigma identifier.
     * @param string $style     Display style.
     * @param int    $user_id   Current user ID.
     */
    function render_enigme_content(int $enigme_id, string $style, int $user_id): void
    {
        echo '<article class="contenu-principal">';
        enigme_get_partial(
            'texte',
            $style,
            [
                'post_id' => $enigme_id,
                'user_id' => $user_id,
            ]
        );
        echo '</article>';
    }

    /**
     * Render the participation section of the enigma.
     *
     * @param int    $enigme_id Enigma identifier.
     * @param string $style     Display style.
    * @param int    $user_id   Current user ID.
     */
    function render_enigme_participation(int $enigme_id, string $style, int $user_id): void
    {
        if (!function_exists('est_enigme_resolue_par_utilisateur')) {
            require_once __DIR__ . '/../statut-functions.php';
        }

        $deja_resolue = est_enigme_resolue_par_utilisateur($user_id, $enigme_id);

        if ($deja_resolue) {
            global $wpdb;
            $table           = $wpdb->prefix . 'enigme_statuts_utilisateur';
            $resolution_date = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT date_mise_a_jour FROM $table WHERE user_id = %d AND enigme_id = %d",
                    $user_id,
                    $enigme_id
                )
            );
            if ($resolution_date) {
                $formatted_date = wp_date('d/m/y \\à H:i', strtotime($resolution_date));
                $message        = sprintf(
                    __('Vous avez résolu cette énigme le %s.', 'chassesautresor-com'),
                    $formatted_date
                );
            } else {
                $message = __('Énigme résolue', 'chassesautresor-com');
            }
            $bloc_reponse = '<p class="message-joueur-statut">✅ ' . esc_html($message) . '</p>';
        } else {
            ob_start();
            enigme_get_partial(
                'bloc-reponse',
                $style,
                [
                    'post_id' => $enigme_id,
                    'user_id' => $user_id,
                ]
            );
            $bloc_reponse = trim(ob_get_clean());
        }

        $content = '';

        $chasse_id = recuperer_id_chasse_associee($enigme_id);

        $indices_enigme = function_exists('get_posts')
            ? get_posts([
                'post_type'      => 'indice',
                'post_status'    => ['publish', 'draft', 'future', 'pending'],
                'meta_query'     => [
                    [
                        'key'     => 'indice_cible_type',
                        'value'   => 'enigme',
                        'compare' => '=',
                    ],
                    [
                        'key'     => 'indice_enigme_linked',
                        'value'   => $enigme_id,
                        'compare' => '=',
                    ],
                    [
                        'key'     => 'indice_cache_etat_systeme',
                        'value'   => ['accessible', 'programme'],
                        'compare' => 'IN',
                    ],
                ],
                'orderby'        => 'date',
                'order'          => 'ASC',
                'fields'         => 'ids',
                'no_found_rows'  => true,
                'posts_per_page' => -1,
            ])
            : [];

        $indices_chasse = [];
        if ($chasse_id && function_exists('get_posts')) {
            $indices_chasse = get_posts([
                'post_type'      => 'indice',
                'post_status'    => ['publish', 'draft', 'future', 'pending'],
                'meta_query'     => [
                    [
                        'key'     => 'indice_cible_type',
                        'value'   => 'chasse',
                        'compare' => '=',
                    ],
                    [
                        'key'     => 'indice_chasse_linked',
                        'value'   => $chasse_id,
                        'compare' => '=',
                    ],
                    [
                        'key'     => 'indice_cache_etat_systeme',
                        'value'   => ['accessible', 'programme'],
                        'compare' => 'IN',
                    ],
                ],
                'orderby'        => 'date',
                'order'          => 'ASC',
                'fields'         => 'ids',
                'no_found_rows'  => true,
                'posts_per_page' => -1,
            ]);
        }

        if ($bloc_reponse !== '') {
            $content .= '<div class="zone-reponse">' . $bloc_reponse . '</div>';
        }

        if (!empty($indices_enigme) || !empty($indices_chasse)) {
            $content .= '<hr class="reponse-indices-separator" />';
            $build_line = function (array $indices, string $title) use ($user_id) {
                $html = '<div class="zone-indices-line"><span class="zone-indices-line__label">'
                    . esc_html($title)
                    . '</span><div class="indice-list">';
                foreach ($indices as $i => $indice_id) {
                    $cout_indice  = (int) get_field('indice_cout_points', $indice_id);
                    $etat_systeme = get_field('indice_cache_etat_systeme', $indice_id) ?: '';
                    $est_debloque = indice_est_debloque($user_id, $indice_id);

                    if ($etat_systeme === 'programme') {
                        $date_raw  = get_field('indice_date_disponibilite', $indice_id);
                        $timestamp = false;
                        if ($date_raw) {
                            $formats = [
                                'Y-m-d H:i:s',
                                'd/m/Y H:i',
                                'Y-m-d\\TH:i:s',
                                'd/m/Y g:i a',
                                'd/m/Y g:i A',
                                'Y-m-d g:i a',
                            ];
                            foreach ($formats as $format) {
                                $date = date_create_from_format($format, $date_raw, wp_timezone());
                                if ($date !== false) {
                                    $timestamp = $date->getTimestamp();
                                    break;
                                }
                            }
                            if ($timestamp === false) {
                                $date = date_create_from_format('d/m/Y g:i a', $date_raw, wp_timezone());
                                if ($date !== false) {
                                    $timestamp = $date->getTimestamp();
                                }
                            }
                        }

                        $now = current_time('timestamp');
                        if ($timestamp === false || $timestamp > $now) {
                            $date_txt = '';
                            if ($timestamp !== false) {
                                if (wp_date('Y-m-d', $timestamp) === wp_date('Y-m-d', $now)) {
                                    $date_txt = sprintf(
                                        esc_html__("Aujourd’hui à %s", 'chassesautresor-com'),
                                        wp_date('H:i', $timestamp)
                                    );
                                } elseif ($timestamp <= $now + WEEK_IN_SECONDS) {
                                    $date_txt = wp_date('d/m/y \\à H:i', $timestamp);
                                } else {
                                    $date_txt = wp_date('d/m/y', $timestamp);
                                }
                            }
                            if ($date_txt === '') {
                                $date_txt = esc_html__('Bientôt disponible', 'chassesautresor-com');
                            }

                            $html .= '<span class="indice-label indice-link--upcoming etiquette">'
                                . '<i class="fa-solid fa-hourglass" aria-hidden="true"></i> '
                                . esc_html($date_txt)
                                . '</span>';
                            continue;
                        }
                    }
                    if ($est_debloque) {
                        $classes   = 'indice-link indice-link--unlocked etiquette';
                        $etat_icon = 'fa-eye';
                    } else {
                        $classes   = 'indice-link indice-link--locked etiquette';
                        $etat_icon = 'fa-lightbulb';
                    }

                    $title_ind = get_indice_title($indice_id);
                    $label     = esc_html($title_ind);

                    $cout_html = $cout_indice > 0
                        ? ' - ' . $cout_indice . ' <sup>'
                            . esc_html__('pts', 'chassesautresor-com') . '</sup>'
                        : '';

                    $html .= '<a href="#" class="' . esc_attr($classes) . '"'
                        . ' data-indice-id="' . esc_attr($indice_id) . '"'
                        . ' data-cout="' . esc_attr($cout_indice) . '"'
                        . ' data-unlocked="' . ($est_debloque ? '1' : '0') . '">'
                        . '<i class="fa-solid ' . esc_attr($etat_icon) . '" aria-hidden="true"></i> '
                        . $label . $cout_html . '</a>';
                }
                $html .= '</div></div>';
                return $html;
            };

            $content .= '<div class="zone-indices">';
            if (!empty($indices_enigme)) {
                $content .= $build_line($indices_enigme, esc_html__('Indices énigme', 'chassesautresor-com'));
            }
            if (!empty($indices_chasse)) {
                $content .= $build_line($indices_chasse, esc_html__('Indices chasse', 'chassesautresor-com'));
            }
            $content .= '<div class="indice-display"></div></div>';
        }

        $mode_validation = get_field('enigme_mode_validation', $enigme_id);
        $cout            = (int) get_field('enigme_tentative_cout_points', $enigme_id);

        if ($mode_validation === 'aucune') {
            $cout = 0;
        }

        $solde_actuel = ($cout > 0 && function_exists('get_user_points'))
            ? get_user_points($user_id)
            : 0;

        $afficher_tentatives = $mode_validation === 'automatique' && !$deja_resolue;
        $afficher_infos      = $mode_validation !== 'aucune'
            && !$deja_resolue
            && ($cout > 0 || $afficher_tentatives);

        if ($afficher_tentatives && !function_exists('compter_tentatives_du_jour')) {
            require_once __DIR__ . '/tentatives.php';
        }

        if ($afficher_tentatives) {
            $tentatives_utilisees = compter_tentatives_du_jour($user_id, $enigme_id);
            $tentatives_max       = (int) get_field('enigme_tentative_max', $enigme_id);
            $tentatives_max_aff   = $tentatives_max > 0 ? $tentatives_max : '∞';
        }

        if ($afficher_infos) {
            $content .= '<div class="participation-infos txt-small" ';
            $content .= 'style="color:var(--color-text-primary);display:flex;justify-content:space-between;">';

            if ($cout > 0) {
                $content .= '<span class="solde">'
                    . sprintf(esc_html__('Solde : %d pts', 'chassesautresor-com'), $solde_actuel)
                    . '</span>';
            } else {
                $content .= '<span></span>';
            }

            if ($afficher_tentatives) {
                $content .= '<span class="tentatives">'
                    . sprintf(
                        esc_html__('Tentatives quotidiennes : %1$d/%2$s', 'chassesautresor-com'),
                        $tentatives_utilisees,
                        $tentatives_max_aff
                    )
                    . '</span>';
            } elseif ($cout > 0) {
                $content .= '<span></span>';
            }

            $content .= '</div>';
        }

        $cout_badge = '';
        if ($mode_validation !== 'aucune' && $cout > 0) {
            $cout_badge = '<span class="badge-cout" aria-label="'
                . esc_attr(sprintf(
                    esc_html__('Coût par tentative : %d points.', 'chassesautresor-com'),
                    $cout
                ))
                . '">' . esc_html($cout) . ' '
                . esc_html__('pts', 'chassesautresor-com') . '</span>';
        }

        $header = '<div class="participation-header">'
            . '<span></span>'
            . $cout_badge
            . '</div>';

        if ($content !== '') {
            echo '<section class="participation">' . $header . $content . '</section>';
        }
    }

    /**
     * Render the solution section of the enigma.
     *
     * @param int    $enigme_id Enigma identifier.
     * @param string $style     Display style.
     * @param int    $user_id   Current user ID.
     */
    function render_enigme_solution(int $enigme_id, string $style, int $user_id): void
    {
        $cache_key = enigme_get_render_cache_key('enigme_solution', $enigme_id);
        $html      = wp_cache_get($cache_key, 'chassesautresor');

        if ($html === false) {
            ob_start();
            ob_start();
            enigme_get_partial(
                'solution',
                $style,
                [
                    'post_id' => $enigme_id,
                    'user_id' => $user_id,
                ]
            );
            $content = trim(ob_get_clean());
            if ($content !== '') {
                echo '<section class="solution">';
                echo '<details><summary>' . esc_html__('Voir la solution', 'chassesautresor-com') . '</summary>';
                echo '<div class="solution-content">'
                    . $content
                    . '</div>';
                echo '</details>';
                echo '</section>';
            }
            $html = ob_get_clean();
            wp_cache_set($cache_key, $html, 'chassesautresor', HOUR_IN_SECONDS);
        }

        echo $html;
    }

    /**     
     * Affiche l’énigme avec son style et son état selon le contexte utilisateur.
     *
     * @param int $enigme_id ID de l’énigme à afficher.
     * @param array $statut_data Données de statut retournées par traiter_statut_enigme().
     */
    function afficher_enigme_stylisee(int $enigme_id, array $statut_data = []): void
    {
        if (get_post_type($enigme_id) !== 'enigme') {
            return;
        }

        if (!empty($statut_data)) {
            // statut_data transmis
        } else {
            // Aucune donnée statut_data transmise à afficher_enigme_stylisee()
        }

        $etat = get_field('enigme_cache_etat_systeme', $enigme_id) ?? 'accessible';

        if ($etat !== 'accessible' && !utilisateur_peut_modifier_enigme($enigme_id)) {
            $chasse_id = recuperer_id_chasse_associee($enigme_id);
            $url       = $chasse_id ? get_permalink($chasse_id) : home_url('/');
            wp_safe_redirect($url);
            exit;
        }

        if (!empty($statut_data['afficher_message'])) {
            echo $statut_data['message_html'];
        }

        $user_id        = get_current_user_id();
        $style          = get_field('enigme_style_affichage', $enigme_id) ?? 'defaut';
        $chasse_id      = recuperer_id_chasse_associee($enigme_id);
        $chasse_stat = $chasse_id ? get_field('chasse_cache_statut', $chasse_id) : '';
        $show_menu   = enigme_user_can_see_menu($user_id, $chasse_id, $chasse_stat);

        $menu_items           = [];
        $peut_ajouter_enigme  = false;
        $total_enigmes        = 0;
        $has_incomplete_enigme = false;

        if ($chasse_id && $show_menu) {
            $sidebar_data         = sidebar_prepare_chasse_nav(
                $chasse_id,
                $user_id,
                $enigme_id
            );
            $menu_items           = $sidebar_data['menu_items'];
            $peut_ajouter_enigme  = $sidebar_data['peut_ajouter_enigme'];
            $total_enigmes        = $sidebar_data['total_enigmes'];
            $has_incomplete_enigme = $sidebar_data['has_incomplete_enigme'];
        }

        $peut_voir_aside = $chasse_id && $show_menu;
        $layout_class    = $peut_voir_aside ? 'enigme-layout' : 'enigme-layout enigme-layout--aside-hidden';
        echo '<div class="container container--xl-full ' . esc_attr($layout_class) . '">';
        $sidebar_sections = ['navigation' => '', 'stats' => ''];
        if ($peut_voir_aside) {
            $sidebar_sections = render_sidebar(
                'enigme',
                $enigme_id,
                $chasse_id,
                $menu_items,
                $peut_ajouter_enigme,
                $total_enigmes,
                $has_incomplete_enigme
            );
        }

        $retour_url   = $chasse_id ? get_permalink($chasse_id) : home_url('/');
        $settings_icon = '<svg aria-hidden="true" viewBox="0 0 24 24" fill="none"'
            . ' stroke="currentColor" stroke-width="2" stroke-linecap="round"'
            . ' stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path'
            . ' d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83'
            . ' 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1'
            . ' 1.51V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65'
            . ' 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65'
            . ' 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09'
            . ' a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2'
            . ' 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65'
            . ' 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1'
            . ' 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06'
            . ' a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2'
            . ' 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>';
        echo '<header class="enigme-mobile-header">';
        echo '<a class="enigme-mobile-back" href="' . esc_url($retour_url) . '">';
        echo '<span class="screen-reader-text">' . esc_html__('Retour', 'chassesautresor-com') . '</span>';
        echo '<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>';
        echo '</a>';
        echo '<div class="enigme-mobile-actions">';
        if (function_exists('utilisateur_peut_modifier_enigme') && utilisateur_peut_modifier_enigme($enigme_id)) {
            echo '<button type="button" class="toggle-mode-edition-enigme enigme-mobile-edit" aria-label="'
                . esc_attr__('Paramètres', 'chassesautresor-com') . '">';
            echo '<span class="screen-reader-text">' . esc_html__('Paramètres', 'chassesautresor-com') . '</span>';
            echo $settings_icon;
            echo '</button>';
        }
        if ($peut_voir_aside) {
            echo '<button type="button" class="enigme-mobile-panel-toggle" aria-controls="enigme-mobile-panel" aria-expanded="false" aria-label="' . esc_attr__('Menu énigme', 'chassesautresor-com') . '">';
            echo '<span class="screen-reader-text">' . esc_html__('Menu énigme', 'chassesautresor-com') . '</span>';
            echo '<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>';
            echo '</button>';
        }
        echo '</div>';
        echo '</header>';

        if (function_exists('utilisateur_peut_modifier_enigme') && utilisateur_peut_modifier_enigme($enigme_id)) {
            echo '<button type="button" class="bouton-edition-toggle enigme-edit-toggle--desktop toggle-mode-edition-enigme" aria-label="'
                . esc_attr__('Paramètres', 'chassesautresor-com') . '">';
            echo '<span class="screen-reader-text">' . esc_html__('Paramètres', 'chassesautresor-com') . '</span>';
            echo $settings_icon;
            echo '</button>';
        }

        if ($peut_voir_aside) {
            echo '<div id="enigme-mobile-panel" class="enigme-mobile-panel" hidden>';
            echo '<div class="enigme-mobile-panel__overlay" tabindex="-1"></div>';
            echo '<div class="enigme-mobile-panel__sheet" role="dialog" aria-modal="true" aria-labelledby="enigme-mobile-panel-title">';
            echo '<h2 id="enigme-mobile-panel-title" class="screen-reader-text">' . esc_html__('Panneau d\'énigme', 'chassesautresor-com') . '</h2>';
            echo '<nav class="enigme-mobile-panel__tabs" role="tablist">';
            echo '<button type="button" role="tab" aria-selected="true" class="panel-tab" data-target="panel-enigmes">' . esc_html__('Énigmes', 'chassesautresor-com') . '</button>';
            echo '<button type="button" role="tab" aria-selected="false" class="panel-tab" data-target="panel-stats">' . esc_html__('Statistiques', 'chassesautresor-com') . '</button>';
            echo '</nav>';
            echo '<div class="enigme-mobile-panel__content">';
            echo '<div id="panel-enigmes" class="panel-tab-content">' . ($sidebar_sections['navigation'] ?? '') . '</div>';
            $ajax_url = function_exists('admin_url') ? admin_url('admin-ajax.php') : '';
            echo '<div id="panel-stats" class="panel-tab-content" hidden aria-live="polite" data-ajax-url="' . esc_url($ajax_url) . '" data-enigme-id="' . intval($enigme_id) . '">' . ($sidebar_sections['stats'] ?? '') . '</div>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }

        echo '<main class="page-enigme enigme-style-' . esc_attr($style) . '">';
        render_enigme_title($enigme_id, $style, $user_id);
        render_enigme_hero($enigme_id, $style, $user_id);
        render_enigme_content($enigme_id, $style, $user_id);
        if (!(
            est_organisateur($user_id)
            && utilisateur_est_organisateur_associe_a_chasse($user_id, $chasse_id)
        )) {
            render_enigme_participation($enigme_id, $style, $user_id);
        }
        render_enigme_solution($enigme_id, $style, $user_id);
        echo '</main>';
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
            cat_debug("❌ Aucun partial trouvé pour $slug (style: $style)");
        }
    }

    /**
     * AJAX handler to fetch winners table.
     */
    function ajax_enigme_recuperer_gagnants(): void
    {
        $enigme_id = isset($_POST['enigme_id']) ? (int) $_POST['enigme_id'] : 0;
        $page      = isset($_POST['page']) ? (int) $_POST['page'] : 1;

        if ($enigme_id <= 0) {
            wp_send_json_error('missing_enigme', 400);
        }

        if (get_field('enigme_mode_validation', $enigme_id) === 'aucune') {
            wp_send_json_error('disabled', 400);
        }

        $user_id = get_current_user_id();
        $html    = enigme_sidebar_gagnants_html($enigme_id, $user_id, $page);
        wp_send_json_success(['html' => $html]);
    }

    add_action('wp_ajax_enigme_recuperer_gagnants', 'ajax_enigme_recuperer_gagnants');
    add_action('wp_ajax_nopriv_enigme_recuperer_gagnants', 'ajax_enigme_recuperer_gagnants');

    /**
     * AJAX handler to refresh the statistics section.
     */
    function ajax_enigme_recuperer_progression(): void
    {
        if (!is_user_logged_in()) {
            wp_send_json_error('non_connecte', 403);
        }

        $chasse_id = isset($_POST['chasse_id']) ? (int) $_POST['chasse_id'] : 0;
        $enigme_id = isset($_POST['enigme_id']) ? (int) $_POST['enigme_id'] : 0;
        if ($chasse_id <= 0 || $enigme_id <= 0) {
            wp_send_json_error('missing_chasse', 400);
        }

        $user_id = get_current_user_id();

        // Ensure stats are recalculated with up-to-date engagement data.
        enigme_clear_sidebar_cache($chasse_id, $user_id);
        wp_cache_delete('enigme_sidebar_resolution_' . $enigme_id, 'chassesautresor');

        $html    = '<h3>' . esc_html__('Statistiques', 'chassesautresor-com') . '</h3>';
        $html   .= enigme_sidebar_metas_html($enigme_id);
        $html   .= enigme_sidebar_progression_html($chasse_id, $user_id);
        $html   .= enigme_sidebar_resolution_html($enigme_id);

        wp_send_json_success(['html' => $html]);
    }

    add_action('wp_ajax_enigme_recuperer_progression', 'ajax_enigme_recuperer_progression');
    add_action('wp_ajax_nopriv_enigme_recuperer_progression', 'ajax_enigme_recuperer_progression');

    /**
     * Enqueue scripts for the winners pager.
     */
    function enigme_enqueue_gagnants_scripts(): void
    {
        if (!is_singular('enigme')) {
            return;
        }

        $enigme_id = get_queried_object_id();
        if (get_field('enigme_mode_validation', $enigme_id) === 'aucune') {
            return;
        }

        $dir = get_stylesheet_directory();
        $uri = get_stylesheet_directory_uri();

        wp_enqueue_script(
            'pager',
            $uri . '/assets/js/core/pager.js',
            [],
            filemtime($dir . '/assets/js/core/pager.js'),
            true
        );

        $path = '/assets/js/enigme-gagnants.js';
        wp_enqueue_script(
            'enigme-gagnants',
            $uri . $path,
            ['pager'],
            filemtime($dir . $path),
            true
        );
    }
    add_action('wp_enqueue_scripts', 'enigme_enqueue_gagnants_scripts');


