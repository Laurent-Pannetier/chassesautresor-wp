<?php
// Récupération de l'utilisateur connecté

// 📌 Charger le script uniquement si ce sous-template est inclus
wp_enqueue_script(
    'case-a-cocher-obligatoire',
    get_stylesheet_directory_uri() . '/assets/js/case-a-cocher-obligatoire.js',
    [],
    filemtime(get_stylesheet_directory() . '/assets/js/case-a-cocher-obligatoire.js'),
    true
);

$current_user_id = get_current_user_id();
$organisateur_id = get_organisateur_from_user($current_user_id);

// Vérification des CPT liés
$has_organisateur = !is_null($organisateur_id);
$has_chasses = $has_organisateur ? organisateur_a_des_chasses($organisateur_id) : false;
$chasses = $has_organisateur ? get_chasses_de_organisateur($organisateur_id) : null;
$has_enigmes = false;
$has_validation = false;
$chasse_associee_id = null;

if ($has_chasses && $chasses->have_posts()) {
    while ($chasses->have_posts()) {
        $chasses->the_post();
        $chasse_associee_id = get_the_ID();
        $enigmes = recuperer_enigmes_associees($chasse_associee_id);
        if (!empty($enigmes)) {
            $has_enigmes = true;
        }
    }
    wp_reset_postdata();
}

// 🔍 Récupération des images en "thumbnail"
$logo = get_field('logo_organisateur', $organisateur_id);
$fallback_id = 3927; // ID de ton média "default organisateur"

if (is_array($logo) && !empty($logo['sizes']['medium'])) {
    $img_organisateur = $logo['sizes']['medium'];
} else {
    $fallback = wp_get_attachment_image_src($fallback_id, 'medium');
    $img_organisateur = $fallback[0] ?? null;
}

$img_chasse = get_the_post_thumbnail_url($chasse_associee_id, 'thumbnail');

// 🔄 Prévisualisation des CPT
$link_organisateur = get_permalink($organisateur_id) . "&preview=true";
$link_chasse = get_permalink($chasse_associee_id) . "&preview=true";

?>

<div class="inscription-container">
    <h2>Votre inscription</h2>
    <p></p>
    <p class="inscription-texte">
        Terminez la création de votre première chasse au trésor pour pouvoir envoyer votre demande d'inscription Organisateur.
    </p>

    <div class="crea-orga-container">
        
        <!-- 🔹 LIGNE 1 : Organisateur, Chasse, Énigmes -->
        <div class="crea-orga-row">
            
            <!-- 📌 Organisateur -->
            <div class="crea-orga-section">
                <div class="crea-orga-header">✅Organisateur</div>
                <div class="crea-orga-card etat-existant">
                    <div class="crea-orga-card-content">
                        <a href="<?php echo esc_url($link_organisateur); ?>">
                            <h3><?php echo get_the_title($organisateur_id); ?></h3>
                            <?php if ($img_organisateur) : ?>
                                <img src="<?php echo esc_url($img_organisateur); ?>" alt="Organisateur">
                            <?php endif; ?>
                        </a>
                    </div>
                </div>
                <div class="crea-orga-footer">
                    <i class="fa fa-info-circle"></i>
                    <span>Affichez votre identité : présentation, visuels et réseaux. Votre bannière sera visible sur toutes vos pages.</span>
                </div>
            </div>

            <!-- 📌 Chasse -->
            <div class="crea-orga-section">
                <div class="crea-orga-header"><?php echo $has_chasses ? '✅' : '📝'; ?>Chasse </div>
                <div class="crea-orga-card etat-existant">
                    <div class="crea-orga-card-content">
                        <a href="<?php echo esc_url($link_chasse); ?>">
                            <h3><?php echo $has_chasses ? get_the_title($chasse_associee_id) : 'Chasse introuvable'; ?></h3>
                            <?php if ($img_chasse) : ?>
                                <img src="<?php echo esc_url($img_chasse); ?>" alt="Chasse">
                            <?php endif; ?>
                        </a>
                    </div>
                </div>
                <div class="crea-orga-footer">
                    <i class="fa fa-info-circle"></i>
                    <span>Définissez le cadre de votre chasse : son introduction, sa couverture et ses caractéristiques essentielles.</span>
                </div>
            </div>

            <!-- 📌 Énigmes -->
            <div class="crea-orga-section crea-enigmes">
                <div class="crea-orga-header">
                    <?php
                    echo $has_chasses
                        ? ($has_enigmes ? '✅' : '📝')
                        : '🔒';
                    ?>
                    Énigmes
                </div>
            
                <div class="crea-orga-card <?php echo $has_chasses ? ($has_enigmes ? 'etat-existant' : 'etat-a-creer') : 'etat-verrouille'; ?>">
                    <div class="crea-orga-card-content">
                        <a href="<?php echo esc_url($link_chasse); ?>"></a>
                
                        <?php if ($has_enigmes && !empty($enigmes)) : ?>
                            <ul class="liste-enigmes-associees">
                                <?php foreach ($enigmes as $enigme_id) :
                                    $titre = get_the_title($enigme_id);
                                    $preview_link = get_permalink($enigme_id); // Lien vers la prévisualisation
                                ?>
                                    <li>
                                        <a href="<?php echo esc_url($preview_link); ?>">
                                            <?php echo esc_html($titre); ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                
                        <!-- Bouton d'ajout en bas -->
                        <a href="/wp-admin/post-new.php?post_type=enigme&chasse_associee=<?php echo $chasse_associee_id; ?>" class="ajout-link">
                            <i class="fas fa-plus-circle"></i> Ajouter énigme
                        </a>
                    </div>
                </div>
            
                <div class="crea-orga-footer">
                    <i class="fa fa-info-circle"></i>
                    <span>Laissez libre cours à votre créativité ! Tous les outils dont vous avez besoin sont à portée de main.</span>
                </div>
            </div>

        </div> <!-- Fin de la ligne 1 -->

        <!-- 🔹 Ligne 2 : Bouton Validation centré avec message -->
        <div class="crea-orga-row validation">
            <div class="certification-container">
                <input type="checkbox" id="certification-checkbox" <?php echo (!$has_chasses || !$has_enigmes) ? 'disabled' : ''; ?>>
                <label for="certification-checkbox">
                    ⚠️ <strong>Attention</strong> : En cochant cette case, je certifie avoir <strong>finalisé la création</strong> de cette chasse et de toutes ses énigmes. </br>
                    📌 <strong>Une fois la demande envoyée, aucune modification ne sera possible.</strong>
                </label>
            </div>
        
            <div class="validation-container">
                <button id="envoyer-demande" class="bouton-secondaire" disabled>
                    <i class="fas fa-lock"></i> Envoyer Demande
                </button>
        
                <?php if (!$has_chasses || !$has_enigmes) : ?>
                    <p class="validation-message">Complétez toutes les étapes pour activer cette option.</p>
                <?php endif; ?>
            </div>
        </div>
    </div> <!-- Fin de crea-orga-container -->
</div> <!-- Fin de inscription-container -->
