# Charte graphique – Orgy

Ce document regroupe les styles utilisés pour l'affichage en **mode Orgy**. Ce mode est appliqué aux panneaux d'édition des CPT et aux pages `mon-compte/*`.

## Activation

Le mode Orgy est actif lorsque la classe `mode-edition` est présente sur la balise `<body>`. Cette classe est ajoutée automatiquement :

- sur les pages `mon-compte/*` ;
- lorsque l'utilisateur ouvre un panneau d'édition (`organisateur`, `chasse`, `énigme`).

## Feuilles de style

- `assets/scss/_edition.scss` : styles des panneaux d'édition frontaux.
- `assets/scss/_mon-compte.scss` : mise en page du tableau de bord utilisateur.
 - `assets/scss/_variables-edition.scss` : variables CSS du mode Orgy sous la classe `.mode-edition`.

## Structure et composants

- **Panneaux d'édition** : `.edition-panel` et dérivés.
- **Tableau de bord** : `.myaccount-layout`, `.myaccount-sidebar`, `.dashboard-nav`.
- **Grille de cartes** : `.dashboard-grid` organisant les `.dashboard-card` adaptatives.
- **Cartes et tableaux** : utilisation de bordures `1px` et rayons `8px`. Utiliser la classe `.carte-orgy` (alias de `.dashboard-card`) pour appliquer ce style.
- **Boutons CTA** : `.bouton-cta` neutre en niveaux de gris ; ajouter `.bouton-cta--color` pour activer les couleurs définies.
  - `:hover` : pas de soulignement ; utilise `var(--color-gris-carte)` (ou `var(--color-background-button-hover)` avec `.bouton-cta--color`).
  - Dans un `.edition-panel`, l'état `:hover` et `:focus` des CTA conserve un fond transparent.

### Indentation des labels

Les formulaires des panneaux d'édition alignent les zones de saisie grâce à une largeur minimale appliquée aux labels.
Cette valeur est centralisée dans `assets/scss/_variables-edition.scss` via la variable `--editor-label-width` (par défaut : `309px`, réduite sur tablette et petit écran).
Modifier cette variable ajuste l'indentation de tous les champs du mode Orgy.

## Typographie

Le mode Orgy utilise la police principale définie par `var(--font-main)`.

## Palette de couleurs

Les variables ci‑dessous sont disponibles sous `.mode-edition` :

```css
--color-editor-background
--color-editor-border
--color-editor-text
--color-editor-text-muted
--color-editor-heading
--color-editor-accent
--color-editor-button
--color-editor-button-hover
--color-editor-error
--color-editor-success
--color-editor-success-hover
--color-editor-field-hover
--color-editor-placeholder
```

Elles assurent une cohérence visuelle entre les panneaux et le tableau de bord.

### Bridge HSL et tokens

Pour compatibiliser le nuancier actuel avec des composants basés sur des tokens `shadcn/ui`, une passerelle HSL expose les couleurs existantes puis les mappe vers les tokens attendus :

```css
/* 🔗 Bridge HSL ↔️ nuancier existant (ne remplace rien) */
.mode-edition {
  /* dérivés HSL (triplets) de tes variables existantes */
  --editor-background-hsl:      200 12% 95%; /* = #F1F3F4 → var(--color-editor-background) */
  --editor-border-hsl:          220 09% 87%; /* = #DADCE0 → var(--color-editor-border) */
  --editor-text-hsl:            225 06% 13%; /* = #202124 → var(--color-editor-text) */
  --editor-text-muted-hsl:      213 05% 39%; /* = #5F6368 → var(--color-editor-text-muted) */
  --editor-heading-hsl:           0 00% 12%; /* = #1F1F1F → var(--color-editor-heading) */

  --editor-accent-hsl:          214 82% 51%; /* = #1A73E8 → var(--color-editor-accent) */
  --editor-button-hsl:          214 82% 51%; /* = #1A73E8 → var(--color-editor-button) */
  --editor-button-hover-hsl:    214 79% 39%; /* = #1558B0 → var(--color-editor-button-hover) */

  --editor-error-hsl:             4 71% 50%; /* = #D93025 → var(--color-editor-error) */
  --editor-success-hsl:         138 68% 30%; /* = #188038 → var(--color-editor-success) */

  --editor-field-hover-hsl:     218 92% 95%; /* = #E8F0FE → var(--color-editor-field-hover) */
  --editor-placeholder-hsl:     210 06% 63%; /* = #9AA0A6 → var(--color-editor-placeholder) */
}

/* 🎨 Tokens shadcn/ui attendus (utilisés comme hsl(var(--token))) */
:root {
  --background: var(--editor-background-hsl);
  --foreground: var(--editor-text-hsl);

  --card: 0 0% 100%;
  --card-foreground: var(--editor-text-hsl);

  --popover: 0 0% 100%;
  --popover-foreground: var(--editor-text-hsl);

  --primary: var(--editor-accent-hsl);        /* ou var(--editor-button-hsl) */
  --primary-foreground: 0 0% 100%;

  /* Secondary — choisis UNE des deux variantes */
  /* Variante A (neutre) */
  --secondary: var(--editor-background-hsl);
  --secondary-foreground: var(--editor-text-hsl);
  /* Variante B (bleuté) — décommente ces 2 lignes et commente celles de la variante A
  --secondary: var(--editor-field-hover-hsl);
  --secondary-foreground: var(--editor-button-hover-hsl);
  */

  --muted: var(--editor-background-hsl);
  --muted-foreground: var(--editor-text-muted-hsl);

  --accent: var(--editor-field-hover-hsl);
  --accent-foreground: var(--editor-button-hover-hsl);

  --destructive: var(--editor-error-hsl);
  --destructive-foreground: 0 0% 100%;

  --border: var(--editor-border-hsl);
  --input: var(--editor-border-hsl);
  --ring: var(--editor-accent-hsl);

  /* utilitaires */
  --placeholder: var(--editor-placeholder-hsl);
  --heading: var(--editor-heading-hsl);

  /* séries pour graphiques */
  --chart-1: var(--editor-accent-hsl);
  --chart-2: var(--editor-success-hsl);
  --chart-3: var(--editor-error-hsl);
  --chart-4: var(--editor-button-hover-hsl);
  --chart-5: var(--editor-text-muted-hsl);
}
```

Ces tokens sont utilisés notamment par les `.dashboard-card` pour assurer une cohérence visuelle avec les couleurs du mode Orgy.
