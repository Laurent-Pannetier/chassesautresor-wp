# Panneau d'édition d'une chasse : onglet Paramètres

Ce document décrit la structure HTML et les principaux comportements de l'onglet **Paramètres** du panneau d'édition des chasses.

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

## Comportements dynamiques

L'onglet repose sur plusieurs scripts pour gérer les interactions :

- [`modal-tabs.js`](../assets/js/core/modal-tabs.js) active le système d'onglets du panneau modal et affiche ou masque le contenu des sections.
- [`champ-init.js`](../assets/js/core/champ-init.js) gère l'édition des champs : sauvegarde AJAX, mise à jour des icônes de complétion et affichage des messages de feedback.

---
Ce socle facilite la maintenance et l'extension de l'onglet Paramètres sur les futures évolutions.

