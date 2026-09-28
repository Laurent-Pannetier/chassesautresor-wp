# Mode démo des chasses

Le thème permet de marquer automatiquement certaines chasses comme « Démo ». Ce statut est calculé à partir des logins des utilisateurs associés au CPT *organisateur* de la chasse.

## Configuration

- **Constante** : `CA_DEMO_ORGANISATEUR_LOGINS`
  - Définit la liste par défaut des logins organisateur pour lesquels toutes les chasses liées doivent être considérées comme des chasses de démonstration.
  - Par défaut, le login `organisateur1` est configuré comme organisateur de démonstration.
  - La constante peut être définie dans `wp-config.php` ou dans un mu-plugin avant le chargement du thème.
- **Filtre** : `ca_demo_organisateur_logins`
  - Permet d’ajouter/supprimer dynamiquement des logins à la liste finale utilisée par le cœur du thème.
  - Le filtre reçoit et doit retourner un tableau de logins (chaînes de caractères).

## Détection

La fonction `ca_demo_is_demo_hunt( int $chasse_id ): bool` renvoie `true` si la chasse donnée est en mode démo. La valeur est mise en cache pour la durée de la requête et peut être forcée via le filtre `ca_demo_is_demo_hunt`.

Lorsque `true`, les cartes de chasses affichent automatiquement un badge « Démo » en plus du badge de statut principal.
