<?php
defined('ABSPATH') || exit;
/**
 * Template Part : Carte de chasse
 * Utilisation : get_template_part('template-parts/chasse-card', null, ['chasse_id' => $chasse_id]);
 */

// Vérification des paramètres
if (!isset($args['chasse_id']) || empty($args['chasse_id'])) {
    return;
}

$chasse_id = $args['chasse_id'];
$completion_class = $args['completion_class'] ?? '';

// 🔹 Récupération des données de la chasse
$titre = get_the_title($chasse_id);
$image = get_the_post_thumbnail_url($chasse_id, 'medium_large');
$permalink = get_permalink($chasse_id);
$description = get_field('description_chasse', $chasse_id);
$statut = mettre_a_jour_statuts_chasse($chasse_id);

// 🔹 Récupération des groupes ACF
$caracteristiques = get_field('caracteristiques', $chasse_id) ?? [];
$trophee = get_field('trophee', $chasse_id) ?? [];

// 🔹 Extraction des sous-champs depuis leurs groupes
$date_debut = $caracteristiques['date_de_debut'] ?? null;
$date_fin = $caracteristiques['date_de_fin'] ?? null;
$illimitee = $caracteristiques['illimitee'] ?? null; // "stop" ou "continue"
$valeur_tresor = $caracteristiques['contre_valeur_tresor'] ?? null;
$lot_description = $caracteristiques['lot'] ?? null;

$nb_joueurs = get_field('total_joueurs_souscription_chasse', $chasse_id);

$trophee_html = afficher_trophee_chasse($chasse_id, $statut);

// 🔹 Définition du statut CSS (badge)
$statut_classes = [
    'En cours' => 'en-cours',
    'À venir' => 'statut-a-venir',
    'Terminée' => 'termine'
];
$classe_statut = $statut_classes[$statut] ?? 'statut-en-cours';
$texte_statut = $statut;
$enigmes_associees = recuperer_enigmes_associees($chasse_id);
$total_enigmes = count($enigmes_associees);

$menu_items = [];  // MENU CONTEXTUEL
$peut_ajouter_enigme = utilisateur_peut_creer_post('enigme', $chasse_id);
if (utilisateur_peut_modifier_post($chasse_id)) {
    $edit_link = get_edit_post_link($chasse_id);
if ($edit_link) {
    $menu_items[] = '<li><a href="' . esc_url($edit_link) . '" class="menu-btn">
                        <i class="fa fa-edit"></i> <span>Modifier</span>
                     </a></li>';
} else {
    error_log("⚠️ [DEBUG] Aucun lien d'édition disponible pour la chasse ID: {$chasse_id}");
}

}

if ($peut_ajouter_enigme) {
    $menu_items[] = '<li>
                        <a href="' . esc_url(admin_url('post-new.php?post_type=enigme&chasse_associee=' . $chasse_id)) . '" 
                           class="menu-btn ajouter-enigme">
                            <i class="fa fa-plus"></i> <span>Ajouter énigme</span>
                        </a>
                    </li>';

    if ($total_enigmes === 0) {
        $menu_items[] = '<li class="tooltip-ajouter-enigme">
                            <div class="tooltip-content">
                                <i class="fa fa-info-circle"></i> Prochaine étape
                                <button class="close-tooltip">&times;</button>
                            </div>
                        </li>';
    }
}

// 🔹 Désactiver le CTA si la chasse est verrouillée
$classe_verrouillee = ($statut === 'Verrouillée') ? 'statut-verrouille' : '';
?>

<div class="carte carte-ligne carte-chasse <?php echo esc_attr(trim($classe_statut . ' ' . $classe_verrouillee . ' ' . $completion_class)); ?>">
    <?php // ✅ Afficher le menu uniquement s'il y a des actions
    if (!empty($menu_items)) : ?>
        <div class="menu-actions">
            <button class="menu-btn-toggle">
                <i class="fa fa-ellipsis-h"></i>
            </button>
            <ul class="menu-dropdown">
                <?php echo implode("\n", $menu_items); ?>
            </ul>
        </div>
    <?php endif; ?>
    <div class="carte-ligne__image">
        <?php if ($statut): ?>
            <span class="badge-statut <?php echo esc_attr($statut_classes[$statut] ?? 'statut-en-cours'); ?>">
                <?php echo esc_html($texte_statut); ?>
            </span>
        <?php endif; ?>


        <img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr($titre); ?>">
    </div>

    <div class="carte-ligne__contenu">
        <h3 class="carte-ligne__titre"><a href="<?php echo esc_url($permalink); ?>"><?php echo esc_html($titre); ?></a></h3>
        
        <?php if ($description): ?>
            <div class="carte-ligne__description"><?php echo limiter_texte_avec_toggle($description, 350); ?></div>
        <?php endif; ?>

        <div class="carte-ligne__details">
            <?php if ($date_debut || $date_fin): ?>
                <span><i class="fa fa-calendar"></i> 
                    Début : <?php echo esc_html(formater_date($date_debut)); ?> - 
                    Fin : <?php echo esc_html($date_fin ? formater_date($date_fin) : 'Illimité'); ?>
                </span>
            <?php endif; ?>
            
            <?php if ($nb_joueurs): ?>
                <span><i class="fa fa-users"></i> <?php echo esc_html($nb_joueurs); ?> joueurs</span>
            <?php endif; ?>

            <?php if ($valeur_tresor): ?>
                <span><i class="fa fa-gem"></i> <?php echo esc_html($valeur_tresor); ?>€</span>
            <?php endif; ?>
            
            <?php if ($total_enigmes > 0): ?>
                <span><i class="fa fa-puzzle-piece"></i> <?php echo esc_html($total_enigmes); ?> énigmes</span>
            <?php else: ?>
                <span>
                    <i class="fa fa-exclamation-triangle" style="color: red;"></i>
                    <span style="color: red; font-weight: bold;">0</span> énigme
                </span>
            <?php endif; ?>


        </div>

        <?php if ($statut === 'Terminée') : ?>
            <div class="chasse-terminee">
                <?php 
                // 🔹 Date de découverte
                $champs_caches = get_field('champs_caches', $chasse_id);
                $date_decouverte = $champs_caches['date_de_decouverte'] ?? null;
                $gagnants = $champs_caches['gagnant'] ?? [];
                ?>
                <p>
                    <?php echo esc_html($date_decouverte ? formater_date($date_decouverte) : __('Solution non trouvée', 'textdomain')); ?>
                </p>
                <?php 
                // 🔹 Limite à 3 gagnants
                if (!empty($gagnants)) :
                    $gagnants_affiches = array_slice((array) $gagnants, 0, 3);
                    ?>
                    <p><i class="fa fa-user"></i> Gagnant(s) : <?php echo esc_html(implode(', ', $gagnants_affiches)); ?></p>
                <?php endif; ?>
        
                <?php 
                // 🔹 Affichage du trophée
                $trophee_html = afficher_trophee_chasse($chasse_id, $statut);
                if ($trophee_html) {
                    echo $trophee_html;
                }
                ?>
            </div>
        <?php endif; ?>


        <?php if ($statut !== 'Verrouillée'): ?>
            <a href="<?php echo esc_url($permalink); ?>" class="bouton bouton-secondaire">Voir la chasse</a>
        <?php endif; ?>
    </div>
</div>
