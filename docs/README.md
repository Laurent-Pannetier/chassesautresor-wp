# Documentation

Ce répertoire rassemble des documents complémentaires au thème.

- [Workflow organisateur](organisateur-workflow.md)
- [Charte du thème](charte.md)
- [Charte Orgy](orgy-charte.md)
- [Pager par défaut](pager.md)
- [Traductions du thème](traductions.md)
- [Panneau d'édition : Paramètres d'énigme](panneau-enigme-parametres.md)
- [Panneau d'édition : Paramètres de chasse](panneau-chasse-parametres.md)
- [Connexion personnalisée](login.md)

Toutes les nouvelles chaînes de texte doivent utiliser les fonctions d'internationalisation de WordPress avec le domaine `chassesautresor-com`.

## Tests

Installez les dépendances puis exécutez PHPUnit depuis le dossier racine :

```bash
composer install
vendor/bin/phpunit --configuration tests/phpunit.xml
```
