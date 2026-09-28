# Groupe d'état : "état énigme menu"

Référence centralisée des états utilisés pour les pastilles du menu des énigmes. Ce groupe peut être invoqué dans le design pour réutiliser les mêmes couleurs ailleurs.

| État | Classe CSS | Description | Couleur (`variables.css`)
| --- | --- | --- | --- |
| en_cours | *(défaut)* | énigme en cours ou simplement affichée | `--etat-enigme-menu-en-cours` |
| non-engagee | `.non-engagee` | l’utilisateur n’a jamais engagé l’énigme | `--etat-enigme-menu-non-engagee` |
| succes | `.succes` | énigme résolue ou terminée | `--etat-enigme-menu-succes` |
| bloquee | `.bloquee` | verrouillée (date, chasse ou prérequis) | `--etat-enigme-menu-bloquee` |
| en-attente | `.en-attente` | tentative manuelle en cours de traitement | `--etat-enigme-menu-en-attente` |
| incomplete | `.incomplete` | énigme incomplète (chasse en création) | `--etat-enigme-menu-incomplete` |

🔁 **Extensibilité**

Pour ajouter un nouvel état :

1. Déclarer la couleur dans `variables.css` via `--etat-enigme-menu-NOUVEL_ETAT`.
2. Ajouter la classe `.NOUVEL_ETAT` dans `enigme.css` en utilisant cette variable pour `--bullet-fill` et `--bullet-outline`.
3. Utiliser la classe dans le menu ou toute autre interface.
