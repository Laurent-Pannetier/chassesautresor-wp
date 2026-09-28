# Système de grille

Ce document décrit les grilles disponibles dans le thème afin de faciliter leur réutilisation.

## Grille de base (`.row` / `.col-*`)

La grille générique repose sur un conteneur `.row` utilisant **CSS Grid**. Chaque élément enfant reçoit une classe `.col-X` pour définir le nombre de colonnes occupées (de `1` à `12`).

- 4 colonnes par défaut sur mobile.
- 6 colonnes à partir de `--bp-small`, 8 à partir de `--bp-tablet` et 12 à partir de `--bp-desktop`.
- Espacement configurable via la variable `--grid-gap`.
- Les styles de base ciblent les petits écrans : la grille suit une approche mobile-first.

```html
<div class="row">
  <div class="col-6">Bloc A</div>
  <div class="col-6">Bloc B</div>
</div>
```

## Conteneurs

Les classes `.container`, `.container--narrow` et `.container.fullwidth` permettent de contrôler la largeur maximale de la zone de contenu.

## Grilles utilitaires pour cartes

Certaines pages proposent des grilles prêtes à l'emploi pour organiser des cartes :

- `.grille-liste` : une seule colonne.
- `.cards-grid` : une colonne pleine largeur sur mobile, puis des colonnes fixes de `300px` centrées lorsque l'espace le permet.

## Grilles de cartes

- `.dashboard-grid` : grille adaptative pour les tuiles du tableau de bord (`minmax(240px, 1fr)`).
- `.stats-cards` : variante pour les cartes de statistiques (`minmax(220px, 1fr)`).

## Grille des pages d’énigme

La classe `.enigme-layout` crée un gabarit à deux colonnes (`320px 1fr`) pour l’édition d’énigmes. En dessous de certains points de rupture ou avec `.enigme-layout--aside-hidden`, la mise en page repasse sur une seule colonne.

## Références

Les styles correspondants se trouvent dans `assets/scss/_grid.scss`, `assets/scss/_cartes.scss`, `assets/scss/_mon-compte.scss`, `assets/scss/_edition.scss` et `assets/scss/_enigme.scss`.
