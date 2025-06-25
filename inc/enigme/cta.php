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
                'label'      => 'Voir l’énigme',
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
                 <button type="submit" class="bouton bouton-secondaire"><?= esc_html($cta['label']); ?></button>
                 <?php if (!empty($cta['sous_label'])): ?>
                     <div class="cta-sous-label"><?= esc_html($cta['sous_label']); ?></div>
                 <?php endif; ?>
             </form>
         <?php
                break;

            case 'link':
            ?>
             <a href="<?= esc_url($cta['url']); ?>" class="cta-enigme-lien bouton bouton-secondaire">
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
             <p class="cta-enigme-desactive bouton-secondaire no-click"><?= esc_html($cta['label']); ?></p>
             <?php if (!empty($cta['sous_label'])): ?>
                 <div class="cta-sous-label"><?= esc_html($cta['sous_label']); ?></div>
             <?php endif; ?>
     <?php
                break;
        }
    }
