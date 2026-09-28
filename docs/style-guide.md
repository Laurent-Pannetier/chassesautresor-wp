# Guide de style

## Points de rupture

Les feuilles de style utilisent des variables CSS définies dans `assets/scss/_variables.scss` pour les tailles d'écran.

| Variable | Valeur | Description |
| --- | --- | --- |
| `--bp-xs` | 374px | Très petits écrans |
| `--bp-sm` | 480px | Mobiles |
| `--bp-600` | 600px | Petites tablettes |
| `--bp-md` | 768px | Tablettes |
| `--bp-lg` | 1024px | Laptop |
| `--bp-xl` | 1280px | Laptop HD courant |
| `--bp-xxl` | 1440px | Grand desktop |
| `--bp-xxxl` | 1920px | Full HD |

> Les anciens points `540px` et `921/922px` sont désormais remappés respectivement vers `600px` et `1024px`.

Exemple d'utilisation :

```css
@media (max-width: var(--bp-md)) {
  /* styles pour les tablettes */
}
```

## Asides

Les blocs `aside` utilisent un style commun défini dans `assets/scss/_aside.scss`.
L'opacité de leur fond est centralisée par la variable globale
`--aside-opacity` (valeur par défaut `0.4` dans `assets/scss/_variables.scss`).
Cette variable facilite l'harmonisation des asides sur l'ensemble du site.
En dessous de `1024px` (`--bp-lg`), les asides sont masqués au profit d'un panneau mobile.
