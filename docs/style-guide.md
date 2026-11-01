# Guide de style

## Points de rupture

Les feuilles de style utilisent des requêtes `@custom-media` définies dans `assets/scss/_variables.scss`. Les propriétés `--bp-*` portent les mêmes seuils pour le JavaScript. Une custom property ne fonctionne pas dans une media query : on écrit `@media (--bp-tablet)`, pas `var(--bp-tablet)`.

| Requête | Seuil | Description |
| --- | --- | --- |
| `--bp-xs` | 374px | Très petits écrans |
| `--bp-small` (`--bp-sm`) | 480px | Mobiles |
| `--bp-mobile` (`--bp-600`) | 600px | Petites tablettes |
| `--bp-tablet` (`--bp-md`) | 768px | Tablettes |
| `--bp-desktop` (`--bp-lg`) | 1024px | Laptop |
| `--bp-wide` (`--bp-xl`) | 1280px | Laptop HD courant |
| `--bp-xxl` | 1440px | Grand desktop |
| `--bp-xxxl` | 1920px | Full HD |
| `--bp-compact` | 720px | Grille stats compacte |
| `--bp-auth` | 960px | Mise en page de connexion |
| `--bp-wp-admin` | max 782px | Hauteur de la barre d'admin WordPress |

> Les anciens points `540px` et `921/922px` sont désormais remappés respectivement vers `600px` et `1024px`.

Exemple d'utilisation :

```scss
@media (--bp-tablet) {
  /* styles à partir de 768px */
}

@media not all and (--bp-tablet) {
  /* styles en dessous de 768px */
}
```

## Asides

Les blocs `aside` utilisent un style commun défini dans `assets/scss/_aside.scss`.
L'opacité de leur fond est centralisée par la variable globale
`--aside-opacity` (valeur par défaut `0.15` dans `assets/scss/_variables.scss`).
Cette variable facilite l'harmonisation des asides sur l'ensemble du site.
En dessous de `1024px` (`--bp-desktop`), les asides sont masqués au profit d'un panneau mobile.
