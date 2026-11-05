# Charte du thème

 Ce document synthétise les styles du thème à partir des variables définies dans [`assets/scss/_variables.scss`](../assets/scss/_variables.scss). Les variables spécifiques au mode édition sont regroupées dans [`assets/scss/_variables-edition.scss`](../assets/scss/_variables-edition.scss).

## Couleurs

### Palette principale
- `--color-primary` : `#FFD700` – jaune or
- `--color-secondary` : `#E1A95F` – lien
- `--color-accent` : `#CD7F32` – bronze

### Texte et fonds
- `--color-text-primary` : `#F5F5DC` – texte principal
- `--color-text-secondary` : `#555555` – texte secondaire
- `--color-titre-enigme` : `#E5C07B` – titre des énigmes
- `--color-text-fond-clair` : `#1c1c1c` – texte sur fond clair
- `--color-text-fond-clair-rgb` : `28, 28, 28`
- `--color-background` : `#0B132B` – fond général
- `--color-background-dark` : `#060A1F` – fond sombre profond
- `--color-gris-carte` : `#c2c2c2` – fond des cartes d’énigme

### États et feedback
- `--color-success` : `#228B22` – succès / progression
- `--color-error` : `#D93025` – erreur
- `--color-background-button` : `#880000` – fond des boutons
- `--color-background-button-hover` : `#a00000` – survol des boutons
- `--color-background-button-inactive` : `#A9A9A9` – boutons inactifs

### Utilitaires
- `--color-white` : `#FFFFFF`
- `--color-black` : `#000000`
- `--color-black-rgb` : `0, 0, 0`
- `--color-grey-light` : `#EEEEEE`
- `--color-grey-medium` : `#CCCCCC`
- `--color-grey-dark` : `#333333`
- `--color-primary-dark` : `#B8860B`
- `--color-gris-3` : `#adadad`

### Mode édition
Variables définies dans [`assets/scss/_variables-edition.scss`](../assets/scss/_variables-edition.scss).

- `--color-editor-button` : `#1A73E8` – bouton
- `--color-editor-button-hover` : `#1558B0` – survol
- `--editor-button-hsl` : `214 82% 51%`
- `--editor-button-hover-hsl` : `214 79% 39%`

## Typographie

- `--font-main` : Poppins (400–700), avec repli système `Segoe UI`, Roboto, `Helvetica Neue`, Arial
- `--font-body` : `1rem` (16px si le navigateur est à 100 %)
- `--font-sm` : `0.875rem`
- `--font-xs` : `0.75rem` — plancher du texte d'interface
- `--leading-body` : `1.5`
- `--leading-tight` : `1.25`
- `--measure` : `68ch` — largeur de lecture des textes suivis
- `--font-h1` : `clamp(1.75rem, 1.15rem + 2.2vw, 2.625rem)`
- `--font-h2` : `clamp(1.5rem, 1.15rem + 1.4vw, 2rem)`
- `--font-h3` : `clamp(1.25rem, 1.05rem + 0.8vw, 1.5rem)`

Le corps de page reste à `1rem`. Les titres utilisent la rampe fluide ci-dessus. Les champs `input`, `select` et `textarea` restent à au moins 16px pour éviter le zoom au focus d'iOS.

### Couples de contraste

- `--text-on-gold` : `#152033` — texte sur or, bronze ou gris clair
- `--color-text-muted` : `#c8c0b4` — texte atténué sur le fond nuit
- `--color-success-strong` : `#1B7A24` — fond du badge « en cours » (texte blanc)
- `--color-success-text` : `#9AF0C0` — succès écrit sur fond nuit
- `--color-success-on-light` : `#0E6B1C` — succès écrit sur fond clair
- `--color-error-text` : `#FFB4A8` — erreur écrite sur fond nuit
- `--color-error-on-light` : `#B71C1C` — erreur écrite sur fond clair

`--color-success` et `--color-error` restent les aplats (barres, bordures). Le texte sur fond nuit utilise les jetons `*-text`.

## Breakpoints

Points de rupture en `min-width` :
- `--bp-xs` : `374px`
- `--bp-small` : `480px`
- `--bp-mobile` : `600px`
- `--bp-tablet` : `768px`
- `--bp-desktop` : `1024px`
- `--bp-wide` : `1280px`
- `--bp-xxl` : `1440px`
- `--bp-xxxl` : `1920px`

### Alias
- `--bp-sm` → `--bp-small`
- `--bp-600` → `--bp-mobile`
- `--bp-540` → `--bp-mobile`
- `--bp-md` → `--bp-tablet`
- `--bp-lg` → `--bp-desktop`
- `--bp-921` → `--bp-desktop`
- `--bp-922` → `--bp-desktop`
- `--bp-xl` → `--bp-wide`

### Breakpoints de layout
- `--breakpoint-small` : alias de `--bp-small`
- `--breakpoint-mobile` : alias de `--bp-mobile`
- `--breakpoint-tablet` : alias de `--bp-tablet`
- `--breakpoint-desktop` : alias de `--bp-desktop`
- `--breakpoint-wide` : alias de `--bp-wide`

Media queries nommées hors échelle principale : `--bp-compact` (720px), `--bp-auth` (960px), `--bp-wp-admin` (max-width 782px, barre d'admin WordPress).

## Grille et layout

- `--container-max-width-default` : `1200px` – largeur max par défaut
- `--container-max-width` : `var(--container-max-width-default)` (plafonné à `90rem` au-delà de `--bp-xxl`)
- `--container-max-width-narrow` : `800px`
- `--grid-gap` : `1rem`
- `--grid-columns` : `4`
- `--dashboard-card-max-width` : `420px`

## Composants

### États du menu d’énigme
- `--etat-enigme-menu-en-cours` : `currentColor` – état par défaut
- `--etat-enigme-menu-bloquee` : `var(--color-grey-medium)` – énigme bloquée
- `--etat-enigme-menu-en-attente` : `var(--color-grey-medium)` – tentative manuelle
- `--etat-enigme-menu-succes` : `var(--color-success)` – énigme réussie
- `--etat-enigme-menu-non-engagee` : `transparent` – non engagée
- `--etat-enigme-menu-incomplete` : `var(--color-error)` – énigme incomplète

### Badges d’état de chasse
- `.badge-statut.statut-en_cours` : fond `var(--color-success-strong)`, texte blanc
- `.badge-statut.statut-payante` : fond `var(--color-primary)`, texte `var(--text-on-gold)`
- `.badge-statut.statut-a_venir` : fond `var(--color-grey-medium)`, texte `var(--text-on-gold)`
- `.badge-statut.statut-termine` : fond `var(--color-text-secondary)`, texte beige
- `.badge-statut.statut-revision` : fond `var(--color-error)`, texte blanc

### Badges de points
- `.badge-cout` : fond `var(--color-secondary)`, texte `var(--text-on-gold)`

### Badges de mode de fin de chasse
- `.badge-recompense` : `background-color: rgba(255, 255, 255, 0.05)` et `color: var(--color-text-primary)` – base du badge
- `.badge-recompense.avec-recompense` : `color: var(--color-primary)` – chasse avec récompense
- `.badge-recompense.sans-recompense` : `color: var(--color-gris-3)` – chasse sans récompense

### Badge de validation d’énigme
- `.badge-validation` : `background: var(--color-grey-light)` et `color: var(--color-text-fond-clair)` – indique la validation d’une réponse

### Asides
- `--aside-bg-rgb` : `238, 238, 238` – fond
- `--aside-border-color` : `var(--color-grey-medium)` – bordure
- `--aside-opacity` : `0.15` – opacité

## Espacements

- `--space-0` : `0`
- `--space-xxs` : `0.25rem`
- `--space-xs` : `0.5rem`
- `--space-sm` : `0.75rem`
- `--space-md` : `1rem`
- `--space-lg` : `1.25rem`
- `--space-xl` : `1.5rem`
- `--space-2xl` : `2rem`
- `--space-3xl` : `2.5rem`
- `--space-4xl` : `3rem`

## Transitions

- `--transition-fast` : `0.15s ease-in-out`
- `--transition-medium` : `0.3s ease`
- `--transition-slow` : `0.6s ease`

