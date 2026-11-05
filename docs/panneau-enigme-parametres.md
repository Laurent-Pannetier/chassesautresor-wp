# Panneau d'édition d'une énigme : onglet Paramètres

Ce document décrit la structure HTML et les principaux comportements de l'onglet **Paramètres** du panneau d'édition des énigmes.

## Structure générale

L'onglet est divisé en deux sections principales :

- **Informations** : regroupe les champs qui possèdent un indicateur de complétion.
- **Réglages** : contient les autres options sans indicateur.

Chaque champ est rendu via le gabarit [`edition-row`](../template-parts/common/edition-row.php) dont la structure est la suivante :

```html
<li class="edition-row">
  <div class="edition-row-label">
    <span class="edition-row-icon"></span>
    <label>…</label>
  </div>
  <div class="edition-row-content">…</div>
</li>
```

- `edition-row-label` réserve une zone pour l'icône de complétion et fixe la largeur du label via la variable CSS `--editor-label-width`.
- `edition-row-content` héberge l'input ou le rendu du champ.

## Indicateurs de complétion

Pour les champs de la section Informations, l'icône est mise à jour dynamiquement :

- un spinner lors de la sauvegarde,
- puis un petit check vert pendant une seconde en cas de succès.

Ce comportement est géré par [`champ-init.js`](../assets/js/core/champ-init.js).

## Champs obligatoires

Lorsqu'un champ obligatoire est vide, la classe `champ-attention` colore son label en rouge et la classe `champ-vide-obligatoire` déclenche une animation clignotante autour de l'input.

Pour la ligne « Bonne(s) réponse(s) », tant qu'aucune réponse n'est enregistrée, le clignotement se déplace sur le bouton « valider » dès qu'une saisie est entamée afin d'inciter l'utilisateur à confirmer l'ajout.

## Saisie des nombres

Les champs numériques « Coût » et « Nb tentatives » sont limités à six chiffres (`max="999999"`) et leur largeur est fixée à 100px (classe `champ-number`).

## Variantes de réponse

La ligne « Variantes » met à jour dynamiquement le tableau récapitulatif des variantes après chaque sauvegarde, insérant ou supprimant le tableau dans `edition-row-content` selon qu'il existe des données.

---
Ce socle vise à faciliter la maintenance et l'extension de l'onglet Paramètres sur les futures évolutions.
