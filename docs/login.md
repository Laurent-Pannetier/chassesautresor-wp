# Connexion

L'écran public est `/connexion/`. Il reprend les couleurs du dashboard (thème nuit) et montre l'image de la chasse dans un cadre 16/9.

Discord est le premier bouton, Google le second, l'e-mail le repli. Les deux boutons sont visibles sur la connexion et sur la création de compte. Le clic ouvre Discord ou Google seulement après l'enregistrement de l'identifiant et du secret dans Réglages → Connexion sociale. Tant que ces valeurs manquent, l'écran le dit.

Le parcours OAuth, la création de compte et la liaison vivent dans le plugin `chassesautresor-core`. Le thème ne dessine que la page.

## Comptes déjà existants

Si l'e-mail vérifié du service correspond à un compte du site, la personne saisit le mot de passe de ce compte pour le relier. Un second compte n'est pas créé.

Un compte créé par Discord ou Google n'a pas de mot de passe choisi. « Mot de passe oublié » le dit, puis propose un e-mail pour en définir un.

## Adresses WordPress

`wp_login_url()` et `wp_registration_url()` pointent vers cet écran. `wp-login.php` reste utilisé pour la déconnexion, la saisie d'un nouveau mot de passe, et la confirmation d'e-mail administrateur.

L'URI de redirection à déclarer chez Discord et Google est affichée sur l'écran de réglages. Elle doit être copiée telle quelle.

Après une connexion ou une inscription, l'accueil remplace la page profil. Un retour vers une chasse, une équipe ou une autre page du site est conservé.

Les identifiants se créent sur le [portail Discord](https://discord.com/developers/applications) (OAuth2, Client ID et Client Secret) et dans la [console Google](https://console.cloud.google.com/apis/credentials) (client OAuth de type application Web). Une même application peut lister l'URI locale et celle du site en ligne.
