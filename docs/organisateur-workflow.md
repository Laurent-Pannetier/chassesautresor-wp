# Workflow organisateur

Ce document décrit les différentes étapes qui mènent un utilisateur de la simple inscription à la validation de sa chasse et au changement de rôle.

## 1. Devenir organisateur

1. L'abonné visite la page `/devenir-organisateur` et clique sur « Devenir organisateur ».
2. Le rôle `organisateur_creation` lui est ajouté automatiquement.
3. Un CPT **organisateur** est créé et l'utilisateur est associé au champ `utilisateurs_associes`.
4. L'utilisateur est redirigé vers la prévisualisation de cet organisateur avec le panneau d'édition ouvert (modal de bienvenue).

## 2. Création de la chasse

1. Une fois les champs obligatoires de l'organisateur remplis, un bouton propose de créer une chasse.
2. Au clic :
   - création automatique d'un CPT **chasse** ;
   - préremplissage du champ `chasse_cache_organisateur` avec l'ID de l'organisateur ;
   - redirection vers la prévisualisation de la chasse avec le panneau d'édition ouvert.

## 3. Création de l'énigme

1. Quand tous les champs obligatoires de la chasse sont complétés, un CTA « Créer une énigme » apparaît.
2. Au clic :
   - création automatique d'un CPT **énigme** ;
   - préremplissage du champ `enigme_chasse_associee` avec l'ID de la chasse ;
   - redirection vers la prévisualisation de l'énigme avec le panneau d'édition ouvert.

## 4. Validation et changement de rôle

Lorsque l'administrateur valide la chasse :

- les CPT organisateur, chasse et énigme sont publiés ;
- l'utilisateur passe du rôle `organisateur_creation` au rôle `organisateur`.

## 5. Panneaux d'édition frontale

Les boutons ✏️ portant la classe `champ-modifier` ouvrent les panneaux d'édition. L'affichage est conditionné par les fonctions PHP suivantes :

- `utilisateur_peut_voir_panneau($post_id)` : détermine si le panneau peut s'afficher ;
- `utilisateur_peut_editer_champs($post_id)` et `champ_est_editable()` : contrôlent la possibilité d'éditer un champ donné.

Seuls les utilisateurs possédant un rôle `organisateur` ou `organisateur_creation` et étant associés au contenu peuvent voir et éditer ces panneaux.

## 6. Traitement des demandes de validation

L'administrateur consulte les demandes sur `/mon-compte/organisateurs/`. Depuis cette page il accède à la chasse concernée et choisit l'une des actions suivantes :

### ✅ Valider la chasse

**CPT chasse**
- `post_status` : `pending` → `publish`
- `chasse_cache_statut_validation` : `en_attente` → `valide`
- `chasse_cache_statut` : recalculé via `mettre_a_jour_statuts_chasse()`

**CPTs énigme liés**
- `post_status` : `pending` → `publish`
- `enigme_cache_etat_systeme` : recalculé via `enigme_mettre_a_jour_etat_systeme()`

**CPT organisateur (si concerné)**
- `post_status` : `pending` → `publish`
- ajout du rôle `organisateur` à l'utilisateur
- suppression du rôle `organisateur_creation`

### ✍️ Correction

Une zone de texte permet d'envoyer un message de retour ; deux boutons « Valider » ou « Annuler » sont proposés.

**CPT chasse**
- `post_status` : reste `pending`
- `chasse_cache_statut_validation` : `en_attente` → `correction`
- `chasse_cache_statut` : reste `revision`
- un e-mail est envoyé à l'organisateur (copie à l'admin) avec le message de retour

**CPTs énigme**
- aucun changement

**CPT organisateur**
- aucun changement

### ❌ Bannir

**CPT chasse**
- `post_status` : `pending` → `draft`
- `chasse_cache_statut_validation` : `en_attente` → `banni`
- `chasse_cache_statut` : reste `revision`

**CPTs énigme**
- `post_status` : `pending` → `draft`
- `enigme_cache_etat_systeme` : inchangé

**CPT organisateur**
- aucun changement
- un e-mail informe l'organisateur que la chasse a été bannie

### 🗑️ Supprimer

Confirmation requise avant suppression.

**CPT chasse**
- passage en corbeille et suppression des images associées

**CPTs énigme**
- suppression individuelle via la logique existante

**CPT organisateur**
- aucun changement
- un e-mail informe l'organisateur que la chasse a été supprimée

