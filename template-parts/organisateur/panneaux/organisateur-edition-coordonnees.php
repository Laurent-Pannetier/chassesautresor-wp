<?php
defined('ABSPATH') || exit;

$args = $args ?? [];
$organisateur_id = $args['organisateur_id']
    ?? get_query_var('organisateur_id_force')
    ?? get_organisateur_from_user(get_current_user_id());

$coordonnees = get_field('coordonnees_bancaires', $organisateur_id);
$iban = $coordonnees['iban'] ?? '';
$bic  = $coordonnees['bic'] ?? '';
?>

<div id="panneau-coordonnees" class="panneau-lateral-liens" aria-hidden="true" role="dialog">
    <div class="panneau-lateral__contenu">
        <header class="panneau-lateral__header">
            <h2>Modifier vos coordonnées bancaires</h2>
            <button type="button" class="panneau-fermer" aria-label="Fermer le panneau">✖</button>
        </header>
    
        <form id="formulaire-coordonnees" data-post-id="<?= esc_attr($organisateur_id); ?>">
            <div class="champ-wrapper">
                <label for="champ-iban">IBAN</label>
                <input
                    type="text"
                    id="champ-iban"
                    name="iban"
                    class="champ-input"
                    placeholder="FR..."
                    value="<?= esc_attr($iban); ?>"
                    autocomplete="off"
                />
                <div id="feedback-iban" class="champ-feedback"></div>
                
                <label for="champ-bic">BIC</label>
                <input
                  type="text"
                  id="champ-bic"
                  name="bic"
                  class="champ-input"
                  placeholder="XXXXXX..."
                  value="<?= esc_attr($bic); ?>"
                  autocomplete="off"
                />
                <div id="feedback-bic" class="champ-feedback"></div>
            </div>
            <div class="panneau-lateral__actions">
                <button type="submit" class="bouton-enregistrer-coordonnees bouton-enregistrer-liens">💾 Enregistrer</button>
            </div>
        </form>
    </div>
</div>