# Traductions

Ce document regroupe les instructions de développement concernant les fichiers de langue du thème.

## Chargement des traductions

Ajouter dans `functions.php` :

```php
add_action( 'after_setup_theme', function () {
    load_child_theme_textdomain( 'chassesautresor-com', get_stylesheet_directory() . '/languages' );
} );
```

Pour un thème autonome, utiliser `load_theme_textdomain` à la place.

## Compilation des fichiers .po

Compiler un fichier unique :

```bash
msgfmt languages/fr_FR.po -o languages/fr_FR.mo
msgfmt languages/en_US.po -o languages/en_US.po
```

Compiler toutes les traductions :

```bash
for po in languages/*.po; do
  msgfmt "$po" -o "${po%.po}.mo"
done
```

Ne pas versionner les fichiers `.mo` compilés.

## Mise à jour du catalogue de chaînes

À la racine du thème, regénérer le fichier modèle avant toute session de traduction :

```bash
wp i18n make-pot . languages/chassesautresor-com.pot
```

## Traductions JavaScript

Après avoir mis à jour les fichiers `.po`, créer les fichiers `json` nécessaires aux scripts :

```bash
wp i18n make-json languages
```

## Conseils pratiques

- Encapsuler toutes les chaînes destinées aux utilisateurs avec `__()` ou une fonction équivalente et le domaine `chassesautresor-com`.
- Ajouter des commentaires `/* translators: ... */` pour expliquer les placeholders complexes.
- Utiliser l’interface anglaise pendant le développement pour repérer immédiatement les textes non traduits.
- Utiliser `rg` pour repérer les chaînes non internationalisées dans le code.
- Pour compiler tous les fichiers `.po` en `.mo` dans le dossier `languages`, exécutez :
  
  ```bash
  for po in languages/*.po; do msgfmt "$po" -o "${po%.po}.mo"; done
  ```
- Poedit ou Loco Translate peuvent générer des rapports de couverture de traduction.
