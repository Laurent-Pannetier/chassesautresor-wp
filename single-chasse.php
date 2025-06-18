<?php
defined('ABSPATH') || exit;

/**
 * Template: single-chasse.php
 *
 * Affichage de la fiche chasse avec header organisateur,
 * statuts dynamiques, et panneaux d'édition inline front.
 */

get_header();

// 🔹 Initialisation
$chasse_id = get_the_ID();
if (!$chasse_id) {
  wp_die('Chasse introuvable.');
}

// 🔄 Vérification du statut logique si non à jour
verifier_ou_recalculer_statut_chasse($chasse_id);


// 🔄 Vérification ponctuelle de la cohérence énigmes ↔ cache (30 min, rôles autorisés)
verifier_et_synchroniser_cache_enigmes_si_autorise(get_the_ID());

// 🔹 Mode édition active ?
$edition_active = utilisateur_peut_modifier_post($chasse_id);

// 🔹 Récupération des champs principaux
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

// 🔹 Champs organisateur
$organisateur_id = get_organisateur_from_chasse($chasse_id);
$organisateur_nom = $organisateur_id ? get_the_title($organisateur_id) : get_the_author();

// 🔹 Données description
$description = get_field('chasse_principale_description', $chasse_id);
$extrait = wp_trim_words(wp_strip_all_tags($description), 30, '...');

// 🔹 Image principale
$image_raw = get_field('chasse_principale_image', $chasse_id);
$image_id = is_array($image_raw) ? ($image_raw['ID'] ?? null) : $image_raw;
$image_url = $image_id ? wp_get_attachment_image_src($image_id, 'large')[0] : null;

// 🔹 Points utilisateur
$user_id = get_current_user_id();
$points_utilisateur = get_user_points($user_id);

// 🔹 Enigmes
$enigmes_associees = recuperer_enigmes_associees($chasse_id);
$total_enigmes = count($enigmes_associees);
$enigmes_resolues = compter_enigmes_resolues($chasse_id, $user_id);

// 🔹 Calculs de dates
$date_debut_formatee = formater_date($date_debut);
$date_fin_formatee = $illimitee ? 'Illimitée' : ($date_fin ? formater_date($date_fin) : 'Non spécifiée');
$date_decouverte_formatee = $date_decouverte ? formater_date($date_decouverte) : '';

// 🔹 Calculs de timestamps
$timestamp_debut = convertir_en_timestamp($date_debut);
$timestamp_fin = (!$illimitee && $date_fin) ? convertir_en_timestamp($date_fin) : false;
$timestamp_decouverte = convertir_en_timestamp($date_decouverte);

// 🔹 Calcul du statut actuel
$statut = get_field('champs_caches')['chasse_cache_statut'] ?? 'revision';

// 🔹 Nombre de joueurs (sera dynamique plus tard)
$nb_joueurs = 0;

if (!empty($_GET['erreur']) && $_GET['erreur'] === 'points_insuffisants') {
  echo '<div class="message-erreur" role="alert" style="color:red; margin-bottom:1em;">
        ❌ Vous n’avez pas assez de points pour engager cette énigme.
        <a href="' . esc_url(home_url('/boutique')) . '">Accéder à la boutique</a>
    </div>';
}
?>

<?php if ($organisateur_id) : ?>
  <?php
  get_template_part('template-parts/organisateur/header-organisateur', null, [
    'organisateur_id' => $organisateur_id
  ]);
  ?>
<?php endif; ?>

<div class="page-chasse-wrapper">

  <?php
  // 🧩 Fiche complète de la chasse (édition + contenu)
  get_template_part('template-parts/chasse/chasse-complete', null, [
    'chasse_id' => $chasse_id
  ]);
  ?>

  <div class="separateur-avec-icone"></div>

</div>
<section class="chasse-enigmes-wrapper" id="chasse-enigmes-wrapper">
  <header class="chasse-enigmes-header">
    <p class="progression-joueur">
      🔍 Vous avez résolu <strong>2</strong> énigmes sur <strong>5</strong>.
    </p>
    <div class="barre-progression">
      <div class="remplissage" style="width: 40%;"></div>
    </div>

    <?php if (!empty($date_decouverte_formatee)) : ?>
      <div class="meta-etiquette">
        🕵️‍♂️ Trouvée le <?php echo esc_html($date_decouverte_formatee); ?>
      </div>
    <?php endif; ?>


    <?php
    $liens = get_field('chasse_principale_liens', $chasse_id);
    $liens = is_array($liens) ? $liens : [];
    $vide  = empty($liens);
    ?>

    <div class="champ-chasse champ-liens champ-fiche-publication <?php echo $vide ? 'champ-vide' : 'champ-rempli'; ?>"
      data-champ="chasse_principale_liens"
      data-cpt="chasse"
      data-post-id="<?php echo esc_attr($chasse_id); ?>">


      <div class="champ-donnees"
        data-valeurs='<?php echo json_encode($liens, JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>'></div>

      <div class="champ-affichage">
        <?php echo render_liens_publics($liens, 'chasse', [
          'afficher_titre' => false,
          'wrap'           => false
        ]); ?>
      </div>
      <div class="champ-feedback"></div>
    </div>
  </header>

  <div class="chasse-enigmes-liste">
    <?php
    get_template_part('template-parts/enigme/boucle-enigmes-chasse', null, [
      'chasse_id' => get_the_ID()
    ]);
    ?>

  </div>

  <footer class="chasse-enigmes-footer">
    <!-- Message de fin -->
  </footer>
</section>



<?php
get_template_part('template-parts/chasse/description-chasse', null, [
  'description' => $description,
  'titre_recompense' => $titre_recompense, // 🔥 Manquait ici
  'lot' => $lot,
  'valeur_recompense' => $valeur_recompense,
  'nb_max' => $nb_max,
  'chasse_id' => $chasse_id,
  'mode' => 'complet'
]);
?>

<?php
// Vérifie si la modale a déjà été vue pour cette chasse
$modal_deja_vue = get_post_meta(get_the_ID(), 'chasse_modal_bienvenue_vue', true);

if (!$modal_deja_vue) {
  // Récupère le contenu éditorial du post ID 9004 (type section_editoriale)
  $post_bienvenue = get_post(9004);

  if ($post_bienvenue && $post_bienvenue->post_status === 'publish') {
    // Marque la modale comme vue pour cette chasse
    update_post_meta(get_the_ID(), 'chasse_modal_bienvenue_vue', '1');

    // Prépare le contenu (tu peux aussi utiliser apply_filters('the_content') si blocs Gutenberg)
    $contenu = apply_filters('the_content', $post_bienvenue->post_content);
?>

    <div class="modal-bienvenue-wrapper" role="dialog" aria-modal="true" aria-labelledby="modal-titre">
      <div class="modal-bienvenue-inner">
        <button class="modal-close-top" aria-label="Fermer la fenêtre">&times;</button>
        <?php echo $contenu; ?>
      </div>
    </div>

    <script>
      // Affiche la modale automatiquement au chargement
      window.addEventListener('DOMContentLoaded', () => {
        document.querySelector('.modal-bienvenue-wrapper')?.classList.add('visible');
      });

      // Ferme la modale
      document.querySelector('.modal-close-top')?.addEventListener('click', () => {
        document.querySelector('.modal-bienvenue-wrapper')?.remove();
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
        z-index: 9999;
      }

      .modal-bienvenue-inner {
        background: var(--color-background, #fff);
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
        color: var(--color-text-fond-clair, #000);
      }
    </style>

<?php
  }
}
?>


<?php get_footer(); ?>