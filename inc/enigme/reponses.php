<?php
defined('ABSPATH') || exit;


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

