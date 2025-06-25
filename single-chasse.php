<?php
/**
 * Template: single-chasse.php (refactorisé)
 * Affichage de la fiche chasse avec header organisateur, statuts dynamiques,
 * contenus stylisés, et panneaux d’édition correctement encapsulés.
 */
defined('ABSPATH') || exit;

// 🧠 LOGIQUE MÉTIER
$chasse_id = get_the_ID();
if (!$chasse_id) {
  wp_die('Chasse introuvable.');
}

verifier_ou_recalculer_statut_chasse($chasse_id);
verifier_et_synchroniser_cache_enigmes_si_autorise($chasse_id);
verifier_ou_mettre_a_jour_cache_complet($chasse_id);

$edition_active = utilisateur_peut_modifier_post($chasse_id);
$user_id = get_current_user_id();
$points_utilisateur = get_user_points($user_id);

// Champs principaux
$champs = chasse_get_champs($chasse_id);
$lot = $champs['lot'];
$titre_recompense = $champs['titre_recompense'];
$valeur_recompense = $champs['valeur_recompense'];
$cout_points = $champs['cout_points'];
$date_debut = $champs['date_debut'];
$date_fin = $champs['date_fin'];
$illimitee = $champs['illimitee'];
$nb_max = $champs['nb_max'];
$date_decouverte = $champs['date_decouverte'];
$current_stored_statut = $champs['current_stored_statut'];

$date_debut_formatee = formater_date($date_debut);
$date_fin_formatee = $illimitee ? 'Illimitée' : ($date_fin ? formater_date($date_fin) : 'Non spécifiée');
$date_decouverte_formatee = $date_decouverte ? formater_date($date_decouverte) : '';

$timestamp_debut = convertir_en_timestamp($date_debut);
$timestamp_fin = (!$illimitee && $date_fin) ? convertir_en_timestamp($date_fin) : false;
$timestamp_decouverte = convertir_en_timestamp($date_decouverte);

// Organisateur
$organisateur_id = get_organisateur_from_chasse($chasse_id);
$organisateur_nom = $organisateur_id ? get_the_title($organisateur_id) : get_the_author();

// Contenu
$description = get_field('chasse_principale_description', $chasse_id);
$extrait = wp_trim_words(wp_strip_all_tags($description), 30, '...');

$image_raw = get_field('chasse_principale_image', $chasse_id);
$image_id = is_array($image_raw) ? ($image_raw['ID'] ?? null) : $image_raw;
$image_url = $image_id ? wp_get_attachment_image_src($image_id, 'large')[0] : null;

$enigmes_associees = recuperer_enigmes_associees($chasse_id);
$total_enigmes = count($enigmes_associees);
$enigmes_resolues = compter_enigmes_resolues($chasse_id, $user_id);

$statut = get_field('champs_caches')['chasse_cache_statut'] ?? 'revision';
$cache_global = get_field('champs_caches', $chasse_id);
$statut_validation = $cache_global['chasse_cache_statut_validation'] ?? '';
$nb_joueurs = 0;

get_header();

$can_validate = peut_valider_chasse($chasse_id, $user_id);
$validation_envoyee = !empty($_GET['validation_demandee']);
?>

<div id="primary" class="content-area">
    <main id="main" class="site-main">

      <?php
      // 🧭 Header organisateur (dans le flux visible)
      if ($organisateur_id) {
        get_template_part('template-parts/organisateur/organisateur-header', null, [
          'organisateur_id' => $organisateur_id
        ]);
      }
      ?>

      <?php
      if ($can_validate) {
        echo '<div class="cta-chasse">';
        echo '<p>Lorsque vous avez finalisé votre chasse, demandez sa validation :</p>';
        echo render_form_validation_chasse($chasse_id);
        echo '</div>';
      }
      if ($validation_envoyee) {
        echo '<p class="message-succes">✅ Votre demande de validation a bien été envoyée. Elle sera traitée par l’équipe.</p>';
      }
      ?>

      <?php
      if (current_user_can('administrator') && $statut_validation === 'en_attente') {
        get_template_part('template-parts/chasse/chasse-validation-actions', null, [
          'chasse_id' => $chasse_id,
        ]);
      }
      ?>

      <?php if (!empty($_GET['erreur']) && $_GET['erreur'] === 'points_insuffisants') : ?>
        <div class="message-erreur" role="alert" style="color:red; margin-bottom:1em;">
          ❌ Vous n’avez pas assez de points pour engager cette énigme.
          <a href="<?= esc_url(home_url('/boutique')); ?>">Accéder à la boutique</a>
        </div>
      <?php endif; ?>

      <!-- 📦 Fiche complète (images + méta + actions) -->
      <?php
      get_template_part('template-parts/chasse/chasse-affichage-complet', null, [
        'chasse_id' => $chasse_id
      ]);
      ?>

      <div class="separateur-avec-icone"></div>

      <!-- 🧩 Liste des énigmes -->
      <section class="chasse-enigmes-wrapper" id="chasse-enigmes-wrapper">
        <header class="chasse-enigmes-header">
          <div class="barre-progression">
            <div class="remplissage" style="width: <?= ($total_enigmes ? round(100 * $enigmes_resolues / $total_enigmes) : 0); ?>%;"></div>
          </div>

          <?php if (!empty($date_decouverte_formatee)) : ?>
            <div class="meta-etiquette">🕵️‍♂️ Trouvée le <?= esc_html($date_decouverte_formatee); ?></div>
          <?php endif; ?>

          <?php
          $liens = get_field('chasse_principale_liens', $chasse_id);
          $liens = is_array($liens) ? $liens : [];
          $vide  = empty($liens);
          ?>
          <div class="champ-chasse champ-liens champ-fiche-publication <?= $vide ? 'champ-vide' : 'champ-rempli'; ?>"
               data-champ="chasse_principale_liens"
               data-cpt="chasse"
               data-post-id="<?= esc_attr($chasse_id); ?>">
            <div class="champ-donnees"
                 data-valeurs='<?= json_encode($liens, JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>'></div>
            <div class="champ-affichage">
              <?= render_liens_publics($liens, 'chasse', ['afficher_titre' => false, 'wrap' => false]); ?>
            </div>
            <div class="champ-feedback"></div>
          </div>
        </header>

        <h2>Énigmes</h2>
        <div class="chasse-enigmes-liste">
          <?php
          get_template_part('template-parts/enigme/chasse-partial-boucle-enigmes', null, [
            'chasse_id' => $chasse_id
          ]);
          ?>
        </div>

        <footer class="chasse-enigmes-footer">
          
        </footer>
      </section>

      <!-- 📜 Description finale -->
      <?php
      get_template_part('template-parts/chasse/chasse-partial-description', null, [
        'description' => $description,
        'titre_recompense' => $titre_recompense,
        'lot' => $lot,
        'valeur_recompense' => $valeur_recompense,
        'nb_max' => $nb_max,
        'chasse_id' => $chasse_id,
        'mode' => 'complet'
      ]);
      ?>

    </main>
    </div>

<?php
// 💬 Modale d’introduction (affichée une seule fois)
$modal_deja_vue = get_post_meta($chasse_id, 'chasse_modal_bienvenue_vue', true);

if (!$modal_deja_vue) :
  $post_bienvenue = get_post(9004);
  if ($post_bienvenue && $post_bienvenue->post_status === 'publish') :
    update_post_meta($chasse_id, 'chasse_modal_bienvenue_vue', '1');
    $contenu = apply_filters('the_content', $post_bienvenue->post_content);
    ?>
    <div class="modal-bienvenue-wrapper" role="dialog" aria-modal="true" aria-labelledby="modal-titre">
      <div class="modal-bienvenue-inner">
        <button class="modal-close-top" aria-label="Fermer la fenêtre">&times;</button>
        <?= $contenu; ?>
      </div>
    </div>

    <script>
      window.addEventListener('DOMContentLoaded', () => {
        const wrapper = document.querySelector('.modal-bienvenue-wrapper');
        if (!wrapper) return;
        wrapper.classList.add('visible');

        const fermer = () => wrapper.remove();

        wrapper.querySelectorAll('.modal-close-top').forEach(btn => {
          btn.addEventListener('click', fermer);
        });

        wrapper.addEventListener('click', e => {
          if (e.target === wrapper) fermer();
        });

        document.addEventListener('keydown', e => {
          if (e.key === 'Escape') fermer();
        });
      });
    </script>
    <style>
      .modal-bienvenue-wrapper {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.75);
        display: flex;
        justify-content: center;
        align-items: center;
        /* S'assure de passer au-dessus du panneau d'édition (z-index 10000) */
        z-index: 11001;
      }
      .modal-bienvenue-inner {
        background: #fff;
        padding: 2rem;
        border-radius: 1rem;
        max-width: 600px;
        width: 90%;
        max-height: 90vh;
        overflow-y: auto;
        position: relative;
      }
      .modal-close-top {
        position: absolute;
        top: 1rem;
        right: 1rem;
        font-size: 1.5rem;
        background: none;
        border: none;
        cursor: pointer;
        color: #000;
      }
    </style>
  <?php endif; ?>
<?php endif; ?>


<?php get_footer(); ?>
