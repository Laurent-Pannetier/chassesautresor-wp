# Notice technique globale – chassesautresor.com

## Sommaire

- 0. Préambule : méthode de travail
- 1. Structure du site
- 2. Modèle de développement
- 3. ACF – Champs personnalisés
- 4. Édition frontale
- 5. JavaScript modulaire
- 6. Affichages publics
- 7. Accès & autorisations
- 8. Comportements spéciaux
- 9. Performances et bonnes pratiques
- 10. Appendices

---

## Préambule : méthode de travail

url du site : https://chassesautresor.com
c'est une plateforme de création de chasses au trésor en ligne, avec des énigmes et des organisateurs, à but lucratif.
Chaque utilisateur dispose d un solde de points, qu il peut utiliser pour débloquer des énigmes ou des chasses.
les organisateurs peuvent créer des chasses et des énigmes gratuites ou payantes en points, et récolter des points en retour.
La gestion des points est effectuée en direct par le thème (pas de mycred ou autre extension de gamification).

Développement sous WordPress, thème enfant d’Astra, avec un sous-thème appelé chassesautresor.
hébergé chez hostinger
extensions actives :
- Members (gestion des rôles et permissions)
- Advanced Custom Fields PRO (ACF)
- UpdraftPlus (sauvegarde/restauration)
- WooCommerce
- WooCommerce Stripe Gateway
- WP Mail SMTP
- WPForms
- LiteSpeed Cache
- Hostinger Tools
- Hostinger Easy Onboarding
- Firelight Lightbox
- Imagify 
- MC4WP : Mailchimp pour WordPress


- AUCUN remède sans diagnostic confirmé : toute correction doit être précédée d’un test, d’un retour précis, et d’un résultat observable ou mesuré.
- il est interdit d'inventer, d'extrapoler , de supposer quelque valeur ou fonction que ce soit ! INTERDICTION ABSOLUE. Si tu ne sais pas, tu demandes, et je te donne l'info. Confirme moi cette partie, tu inventes sans cesse.
  → Interdit de proposer un correctif sans avoir vu la cause exacte du problème.
- On avance étape par étape : discussion, validation, développement, tests, confirmation avant étape suivante.
- Aucune supposition : poser une question plutôt que supposer que “ça devrait être comme ça”.
- Les notices sont prioritaires : les utiliser, les mettre à jour, ou les créer si nécessaire.
- Cadre strict de travail : jamais de génération de code sans validation explicite.
- En cas de problème : diagnostic prouvé avec logs, console ou visuel, suivi d’une correction.
- Réponses courtes et fonctionnelles : éviter les formules, les paraphrases, les répétitions.
- Historique de développement : le développement initial a largement été modifié. Quasiment tout a été revu, mais il se peut qu’on rencontre des résidus dans le code, il faut en avoir conscience.

### 🚫 Suppressions obligatoires
Toute évolution ou migration de logique (ex. déplacement d’un champ vers champ-init.js) doit impérativement inclure l’identification et la suppression des doublons ou fonctions redondantes (dans les fichiers JS ou PHP concernés).
Le code ancien ne doit jamais rester actif ou latent, même s’il n’est “plus appelé”.
→ Il est interdit de laisser du code mort, duplicatif ou non utilisé sans lister explicitement ce qu’il faut supprimer.



## 1. Structure du site

on est dans un sous-thème d astra appelé chassesautresor

📦 Arborescence générale

assets/                (CSS, JS, images – voir section Organisation des fichiers)
inc/                   (Fonctions PHP – voir section Organisation des fichiers)
notices/               (Gestion des notifications)
template-parts/        (Parties réutilisables de templates)
templates/             (Templates personnalisés)
woocommerce/           (Surcharges WooCommerce)

footer.php             
functions.php          (infos critiques uniquement, pas de fonctions)
header.php             
page.php               
single-chasse.php      
single-enigme.php      
single-organisateur.php 
style.css              (Obligatoire pour WordPress, vide)
template-creer-mon-profil.php (Redirige l’utilisateur selon l’état de son profil organisateur)
template-devenir-organisateur.php (Page immersive de création d’un espace organisateur)
screenshot.jpg         (Aperçu du thème)


📄 Notices

Le dossier `notices/` contient la documentation technique et fonctionnelle du thème, organisée en fichiers Markdown. Chaque fichier traite d’un aspect précis (ex : édition d’image, gestion des radios, champs imbriqués ACF, etc.).

- `champs-acf-liste.md`
- `champs_imbrques_acf.md`
- `cout-points.md`
- `date-edition.md`
- `enigmes-codes-utiles.md`
- `gallery.md`
- `global.md`
- `image-edit.md`
- `radio-edit.md`
- `texte-inside-edit.md`
- `titre-edition.md`
- `wysiwyg-panneau-lateral.md`


🧩 Types de contenus personnalisés (CPT)

- organisateur : référence un ou plusieurs utilisateurs (organisateurs) + infos classiques. Structure pivot d identification
- chasse : reliée au cpt organisateur, reliée au minimum à une énigme
- enigme : obligatoirement reliée à une seule chasse


🧑‍🤝‍🧑 Rôles & accès (joueur, organisateur, organisateur_creation, admin)

- organisateur_creation : peut modifier organisateur, chasse, enigme.
- organisateur : plus de modification chasse et enigme si publiée sauf statut autorisé par admin

utilisation de l extension Members pour gérer les rôles et les accès. Cette extension permet de créer, modifier et attribuer des rôles personnalisés, ainsi que de contrôler les permissions des utilisateurs.


## 2. Modèle de développement

🧱 Organisation des fichiers (JS/CSS/PHP)
Dossier assets
  ├── css
  │   ├── cartes.css 
  │   ├── chasse.css
  │   ├── commerce.css
  │   ├── components.css
  │   ├── edition.css
  │   ├── enigme.css
  │   ├── enigmes-style.css
  │   ├── gamification.css
  │   ├── general.css
  │   ├── layout.css
  │   ├── mon-compte.css
  │   └── organisateurs.css
  ├── js
  │   ├── accordeon.js
  │   ├── autocomplete-utilisateurs.js
  │   ├── avatar-upload.js
  │   ├── chasse-edit.js
  │   ├── conversion.js
  │   ├── encodage-morse.js
  │   ├── enigme-edit.js
  │   ├── header-organisateur-ui.js
  │   ├── modal-points.js
  │   ├── organisateur-edit.js
  │   ├── taux-conversion.js
  │   ├── toggle-text.js
  │   ├── toggle-tooltip.js
  │   └── core
  │         ├── ajax.js
  │         ├── champ-date-hooks.js
  │         ├── champ-init.js
  │         ├── helpers.js
  │         ├── ui.js
  └── images
      └── (les fichiers images)
       
Dossier inc/
  ├── access-functions.php
  ├── admin-functions.php
  ├── chasse-functions.php
  ├── edition-functions.php
  ├── enigme-functions.php
  ├── gamify-functions.php
  ├── layout-functions.php
  ├── organisateur-functions.php
  ├── relations-functions.php
  ├── shortcodes-init.php
  ├── stat-functions-old.php
  ├── statut-functions.php
  ├── user-functions.php
  ├── handlers/
  │   ├── voir-fichier.php
  └── utils/
      ├── liens.php


structure des templates possibles pour les énigmes
template-parts/
└── enigme/
    └── partials/
        ├── images.php
        ├── texte.php
        ├── pirate/
        │   ├── images.php
        │   └── texte.php
        ├── vintage/
        │   └── images.php
        ...

        


🧬 Standards adoptés (PHPDoc, classes CSS, nommage ACF)

dans le dossier inc

- fichiers organisés en sections
- PHPDoc sur chaque fonction
- forme :
  - en-tête de section exemple (3 sauts de ligne avant, 1 après)
    // ==================================================
    // 📦 CHARGEMENT ET CONTROLE DONNÉES
    // ==================================================
    /**
    * 🔹 forcer_chargement_acf_scripts_chasse → Force le chargement des scripts ACF sur les fiches chasse pour activer les WYSIWYG.
    * 🔹 personnaliser_acf_wysiwyg_toolbars → options minimales en "basic"
    * 🔹 enqueue_core_edit_scripts → chargement des modules JS mutualisés (édition front)
    * 🔹 verifier_et_synchroniser_cache_enigmes_si_autorise → vérifie la synchronisation entre les énigmes et le cache de la chasse (toutes les 30 min par post)
    */
    explication : 3 premières lignes nommage section, en dessous, liste des fonctions contenues avec description courte
  
  - sommaire de fichier, exemple
      //
      //  📦 CHARGEMENT ET CONTROLE DONNÉES
      //  🧩 APPARENCE EN MODE ÉDITION
      //  🧩 FONCTIONS GLOBALES DE FORMATAGE
      //  🧩 GESTION DE CHAMPS ACF PARTICULIERS
      //  🏗️ CRÉATION & ÉDITION ORGANISATEUR
      //  🏗️ CRÉATION & ÉDITION CHASSE
      //  🧩 CRÉATION & ÉDITION ÉNIGME
      //  📦 GESTION DES STATUTS

      explication : le titre de chaque section du fichier est repris sur une seule ligne, sommaire tout en haut de fichier

- respect des normes de nommage ACF


dans le dossier css

- fichiers organisés en sections, comprenant des chapitres
- pas d espace entre les déclarations CSS
- entête de section exemple 
  /* ==================================================
   🌐 GLOBAL
   ================================================== */
   3 sauts de ligne avant, 1 après
  
- entête de chapitre exemple
  /* ========== 🖊️ CHAMPS ÉDITABLES ========== */
  1 saut de ligne avant, 0 après

- sommaire en haut de fichier exemple
    /* 🧭 HEADER */

    /* 📱 Responsive header */


  /* 🪟 PANNEAU D’ÉDITION GLOBAL */

    /* 🧱 Structure */
    /* 🔤 Typographie */
    /* 🧪 Placeholder */
    /* ✏️ Champs particuliers */
    /* 📱 Responsive panneau édition */

  sommaire avec section et parfois chapitres



dans le dossier js

🔧 Fichiers JS – Structure et conventions
Les fichiers JavaScript suivent une convention rigoureuse et simplifiée, distincte des fichiers PHP.

📁 Emplacement
Scripts globaux partagés : assets/js/core/

Scripts spécifiques par CPT : assets/js/organisateur-edit.js, chasse-edit.js, enigme-edit.js, etc.

🧱 Structure des fichiers
Chaque fichier est organisé en sections bien identifiées :


// ==============================
// 🧩 Initialisation des champs texte (inline)
// ==============================
✅ 2 sauts de ligne avant chaque section

✅ 0 saut après le titre

✅ Émoji / mot-clé thématique dans le titre

❌ Pas de PHPDoc, pas de bloc @param / @return

❌ Pas de doc multilignes /* ... */ au-dessus des fonctions

✅ Les fonctions sont nommées clairement, sans commentaire explicite si leur nom est explicite

🧩 Exemple attendu


// ==============================
// 🧩 Initialisation des champs conditionnels (radio)
// ==============================
function initChampConditionnel(nomChamp, correspondance) {
  ...
}
### 📌 Résumé des règles
Élément	Attendu
Commentaire de section	// === TITRE === avec 3 sauts avant
Fonction	Nom explicite, sans doc
Doc multilignes	❌ Jamais en JS
PHPDoc (@param)	❌ Réservé à PHP (inc/)




## 3. ACF – Champs personnalisés

🏗️ Groupes globaux 

ACF – Champs personnalisés par CPT

CPT : organisateur
Groupe : Paramètres organisateur

* message\_acf\_organisateur (group)

  * (message vide)

* profil\_public (group)

  * logo\_organisateur (image)
  * description\_courte (text)
  * email\_contact (email)

* liens\_publics (repeater)

  * type\_de\_lien (select)
  * url\_lien (url)

* coordonnees\_bancaires (group)

  * iban (text)
  * bic (text)

* utilisateurs\_associes (select)

* description\_longue (wysiwyg)

CPT : chasse
Groupe : paramètre de la chasse

* chasse\_principale\_image (image)

* chasse\_principale\_description (wysiwyg)

* chasse\_principale\_liens (repeater)

  * chasse\_principale\_liens\_type (select)
  * chasse\_principale\_liens\_url (url)

* caracteristiques (group)

  * chasse\_infos\_recompense\_titre (text)
  * chasse\_infos\_recompense\_texte (wysiwyg)
  * chasse\_infos\_recompense\_valeur (number)
  * chasse\_infos\_cout\_points (number)
  * chasse\_infos\_date\_debut (date\_picker)
  * chasse\_infos\_duree\_illimitee (true\_false)
  * chasse\_infos\_date\_fin (date\_picker)
  * chasse\_infos\_nb\_max\_gagants (number)

* champs\_caches (group)

  * chasse\_cache\_gagnants (user)
  * chasse\_cache\_date\_decouverte (date\_picker)
  * chasse\_cache\_statut (select)
  * chasse\_cache\_statut\_validation (select)
  * chasse\_cache\_commentaire (textarea)
  * chasse\_cache\_enigmes (relationship)
  * chasse\_cache\_organisateur (relationship)

CPT : enigme
Groupe : Paramètres de l’énigme

* enigme\_visuel\_image (gallery)

* enigme\_visuel\_texte (wysiwyg)

* enigme\_visuel\_legende (text)

* enigme\_mode\_validation (radio)

* enigme\_tentative (group)

  * enigme\_tentative\_cout\_points (number)
  * enigme\_tentative\_max (number)

* enigme\_reponse\_texte\_manuelle (textarea)  \[a supprimer]

* enigme\_reponse\_bonne (text)

* enigme\_reponse\_casse (true\_false)

* enigme\_reponse\_variantes (group)

  * variante\_1 (group)

    * texte\_1 (text)
    * message\_1 (text)
    * respecter\_casse\_1 (true\_false)
  * variante\_2 (group)

    * texte\_2 (text)
    * message\_2 (text)
    * respecter\_casse\_2 (true\_false)
  * variante\_3 (group)

    * texte\_3 (text)
    * message\_3 (text)
    * respecter\_casse\_3 (true\_false)
  * variante\_4 (group)

    * texte\_4 (text)
    * message\_4 (text)
    * respecter\_casse\_4 (true\_false)


* enigme\_acces\_condition (radio)
* enigme\_acces\_pre\_requis (relationship)
* enigme\_acces\_date (date\_time\_picker)

* enigme\_style\_affichage (select)

* enigme\_solution (group)

  * enigme\_solution\_mode (radio)
  * enigme\_solution\_delai (number)
  * enigme\_solution\_date (date\_time\_picker)
  * enigme\_solution\_explication (wysiwyg)

* enigme\_chasse\_associee (relationship)

* enigme\_cache\_etat\_systeme (select)

* enigme\_statut\_utilisateur (select)

liste avec tous les détails des groupes de champs ACF dans champs-acf-liste.md


## ⚠️ Bonnes pratiques – Enregistrement des champs ACF de type `group` / `repeater`

### ❌ Risque critique

Les champs de type `group`, `repeater`, ou tout champ complexe ACF **ne doivent jamais** être enregistrés via une logique générique ou un fallback automatique.

> Exemple typique de bug : un champ de type `group` est traité via `update_field($champ, $valeur)` alors que `$valeur` est une string JSON, ce qui provoque la suppression ou la corruption de toutes les données internes.

---

### ✅ Traitement correct obligatoire

Un champ `group` doit **toujours** être traité avec :

```php
update_field('NOM_DU_CHAMP', $valeur_formatee, $post_id);
$champ_valide = true; // Toujours marquer comme traité, même si update_field retourne false
```

* Le `return false` de `update_field()` signifie simplement « rien n’a changé », pas « échec »
* **Ne jamais relancer** une mise à jour via fallback sur un champ complexe

---

### 📌 À retenir : cas confirmés dans le projet

* `coordonnees_bancaires` (organisateur) : effacé si pas de protection contre le fallback
* `enigme_reponse_variantes` (énigme) : supprimé si on clique sur "Enregistrer" sans modification réelle

---

### ❌ À ne jamais faire : fallback destructeur

```php
if (!$champ_valide) {
    update_field($champ, $valeur, $post_id); // ❌ dangereux si $champ est un group
}
```

---

### 🔒 Astuce de sécurité minimale

Dans les blocs `if ($champ === 'xxx')`, toujours terminer par :

```php
$champ_valide = true; // Protège du fallback
```

Même si `update_field(...)` retourne false.

---

### ✅ Résultat :

* Plus aucun champ groupé effacé si inchangé
* Aucun effet de bord si on clique sur "Enregistrer" sans modifier
* Code stable et non destructeur


👉 Mise à jour de la structure ACF du CPT enigme :

enigme_acces a été supprimé (ancien groupe)

Les champs suivants sont désormais à la racine :

enigme_acces_condition (radio)

enigme_acces_date (date_picker)

enigme_acces_pre_requis (relationship)

### 📌 Pourquoi : pour éviter les erreurs d’enregistrement et simplifier la logique d’édition JS/PHP.


### 🔁 Champs liés et logique conditionnelle

Certains champs du CPT `enigme` déclenchent automatiquement une mise à jour d'autres champs, afin de garantir une cohérence d'état côté utilisateur et système.

- Si `enigme_acces_pre_requis` contient des valeurs, alors `enigme_acces_condition` est automatiquement enregistré comme `"pre_requis"`.
- Si `enigme_acces_pre_requis` est vidé, alors `enigme_acces_condition` est automatiquement remis à `"immediat"`.

Cette logique est centralisée **dans la fonction PHP `modifier_champ_enigme()`**, dans le bloc de traitement du champ `enigme_acces_pre_requis`.

👉 Le champ `enigme_acces_condition` **ne doit plus être modifié manuellement** dans ce contexte.

### 📌 Objectif : éviter tout décalage d’état lié à des requêtes AJAX arrivant dans un ordre non contrôlé.




### 🔄 Champs de cache, logique, calcul (ex. chasse_cache_statut, enigme_cache_etat_systeme)
pas mis en place

### 📌 Bonnes pratiques (racine, retour ID, structure des groupes)
problème récurrent et prioritaire : la gestion des champs imbriqués
- mal documentés par ACF
- organisation initiale avec champ dans des groupes. retour à la racine dans les cas critiques
- fonctions créées dans relations-functions.php pour gérer des aspects critique acf, demander le contenu du fichier si besoin


## 4. Édition frontale
les rôles organisateur et organisateur_creation peuvent avoir la permission d editer leur contenu en le visionnant. un bouton toggle seulement visible après contrôle d accès déclenche l ouverture d un panneau d édition.

🌐 GLOBAL
Le panneau d édition comprend un entête, un body avec les champs obligatoires, facultatifs et les caractéristiques.
champs obligatoires et facultatifs ont des stylos d édition, et des indicateurs de complétion - exemple de html utilisé
<li class="champ-chasse champ-description <?= empty($description) ? 'champ-vide' : 'champ-rempli'; ?>"
                  data-champ="chasse_principale_description"
                  data-cpt="chasse"
                  data-post-id="<?= esc_attr($chasse_id); ?>">
  Une description
  <button type="button"
          class="champ-modifier ouvrir-panneau-description"
          data-cpt="chasse"
          data-champ="chasse_principale_description"
          data-post-id="<?= esc_attr($chasse_id); ?>"
          aria-label="Modifier la description">✏️</button>
</li>

Les champs des caractéristiques sont directement éditables : ils s affichent immédiatement sous forme de champs <input> visibles (text, number, select, etc.), sans bouton d’édition ni stylo.
L enregistrement se fait automatiquement à la saisie ou après délai (debounce), sans clic sur un bouton.

En revanche, les champs obligatoires ou facultatifs sont masqués derrière un résumé. Ils s’ouvrent via un bouton ✏️ (stylo) et déclenchent un panneau latéral ou une édition inline avec affichage progressif (champ-affichage + champ-edition).
<!-- Nombre de gagnants -->
<li class="champ-chasse champ-nb-gagnants <?= empty($nb_max) ? 'champ-vide' : 'champ-rempli'; ?>"
    data-champ="caracteristiques.chasse_infos_nb_max_gagants"
    data-cpt="chasse"
    data-post-id="<?= esc_attr($chasse_id); ?>">

  <label for="chasse-nb-gagnants">Nb gagnants</label>

  <input type="number"
          id="chasse-nb-gagnants"
          name="chasse-nb-gagnants"
          value="<?= esc_attr($nb_max); ?>"
          min="1"
          class="champ-inline-nb champ-nb-edit" 
          <?= ($nb_max == 0 ? 'disabled' : ''); ?> />

  <div class="champ-option-illimitee ">
    <input type="checkbox"
            id="nb-gagnants-illimite"
            name="nb-gagnants-illimite"
            <?= ($nb_max == 0 ? 'checked' : ''); ?>
            data-champ="caracteristiques.chasse_infos_nb_max_gagants">
    <label for="nb-gagnants-illimite">Illimité</label>
  </div>

  <div id="erreur-nb-gagnants" class="message-erreur" style="display:none; color:red; font-size:0.9em; margin-top:5px;"></div>
</li>


👉 Ajout d’un comportement spécial pour la date programmée des énigmes :

Le champ enigme_acces_date est visible uniquement si enigme_acces_condition === date_programmee

Si la date choisie est passée, elle est automatiquement rejetée (fallback côté PHP ou JS selon le contexte)

Si date_programmee est activée avec une date dans le passé, la condition est automatiquement repassée à immediat (en PHP)

La suppression de la valeur est interdite (champ non effaçable)

### 📌 À noter : le recalcul du statut logique (enigme_cache_etat_systeme) est automatiquement déclenché lors de toute mise à jour de la condition d'accès ou de la date.




✏️ Panneaux latéraux (wysiwyg, liens, gallery, etc.)
ces panneaux sont déclenchés par des stylos d édition
ils sont le plus souvent latéraux (parfois modal pour des cas particuliers)
le mode d enregistrement, ajax ou rechargement de page est géré au cas par cas
exemple de panneau latéral : panneau-recompense-chasse.php
<?php
defined('ABSPATH') || exit;
$chasse_id = $args['chasse_id'] ?? null;
if (!$chasse_id || get_post_type($chasse_id) !== 'chasse') return;

$caracteristiques = get_field('caracteristiques', $chasse_id);
$texte_recompense = $caracteristiques['chasse_infos_recompense_texte'] ?? '';
$valeur_recompense = $caracteristiques['chasse_infos_recompense_valeur'] ?? '';
?>


<div id="panneau-recompense-chasse" class="panneau-lateral-liens panneau-lateral-large" aria-hidden="true">
  <div class="panneau-lateral__contenu">

    <div class="panneau-lateral__header">
      <button type="button" class="panneau-fermer" aria-label="Fermer le panneau">✖</button>
      <h2>Configurer la récompense</h2>
    </div>

    <div class="champ-wrapper" style="display: flex; flex-direction: column; gap: 20px;">
        
      <label for="champ-recompense-titre">Titre de la récompense <span class="champ-obligatoire">*</span></label>
      <input id="champ-recompense-titre" type="text" maxlength="40" placeholder="Ex : Un papillon en cristal..." value="<?= esc_attr($caracteristiques['chasse_infos_recompense_titre'] ?? ''); ?>">

      <label for="champ-recompense-texte">Descripton de la récompense <span class="champ-obligatoire">*</span></label>
      <textarea id="champ-recompense-texte" rows="4" placeholder="Ex : Un coffret cadeau comprenant..."><?= esc_textarea(wp_strip_all_tags($texte_recompense)); ?></textarea>


      <label for="champ-recompense-valeur">Valeur en euros (€) <span class="champ-obligatoire">*</span></label>
      <input id="champ-recompense-valeur" class="w-175" type="number" min="0" step="0.01" placeholder="Ex : 50" value="<?= esc_attr($valeur_recompense); ?>">

      <div class="panneau-lateral__actions">
        <button id="bouton-enregistrer-recompense" type="button" class="bouton-enregistrer-description bouton-enregistrer-liens">💾 Enregistrer</button>
      </div>
      <button type="button" id="bouton-supprimer-recompense" class="bouton-texte secondaire">
      ❌ Supprimer la récompense
    </button>

    </div>

  </div>
</div>





### 📍 Inline editing (post_title, texte, booleens, etc.)

Certains champs sont édités directement dans le panneau, sans passer par un acf_form(), via un système inline AJAX (modification immédiate).  
Le JS utilisé repose sur `modifierChampSimple()` (texte, nombre, booléen) ou sur un module personnalisé (`initChampTexte`, `initChampImage`, etc.).

🛠️ Fonctionnement général :
- Affichage sous forme de `<div class="champ-affichage">...✏️</div>`
- Clic sur ✏️ ➜ remplacement par un `<input>` ou `<textarea>`
- Sauvegarde automatique via AJAX sur blur ou clic sur ✅
- Mise à jour immédiate du DOM
- Rétroaction par champ-feedback (chargement, succès, erreur)

### 📌 Champs concernés :
- post_title (via `initChampTexte`)
- texte court (text, email, etc.)
- true_false via checkbox directe
- number avec comportement conditionnel (ex : coût en points)
- En partie : select (dans certains cas comme `enigme_mode_validation`)

🧩 Chaque champ doit être clairement identifié avec :

`<div class="champ-<?= $cpt; ?>" data-champ="NOM_DU_CHAMP" data-cpt="<?= $cpt; ?>" data-post-id="<?= $post_id; ?>">`

---

### ⚙️ acf_form() – usage et rechargement

L’utilisation de `acf_form()` est réservée aux champs nécessitant :
- Un affichage natif ACF (WYSIWYG, galerie, repeater…)
- Une interface avancée (éditeur complet, drag & drop…)
- Une sécurité ACF pour des champs complexes

🧾 Fonctionnement :
- Le champ est affiché dans un template-part spécifique (souvent un panneau latéral)
- Le formulaire utilise :
  - `'form' => true`
  - `'fields' => ['nom_du_champ']`
  - `'return' => get_permalink() . '?maj=champ'` pour forcer un rechargement
- Le bouton d’enregistrement est personnalisé
- Aucun AJAX ici : rechargement de la page après validation

### 📌 Champs concernés :
- wysiwyg (`description_longue`, texte énigme, récompense)
- gallery (`enigme_visuel_image`)
- repeater (`liens_publics`, `chasse_principale_liens`)
- Cas spécifiques comme les modaux récompense, coordonnées bancaires, etc.

🧩 La structure des panneaux suit toujours le format :

`<div id="panneau-XXX" class="panneau-lateral-liens panneau-lateral-large" aria-hidden="true">`

🔁 Rechargement ACF_FORM : séquence attendue

1. L’utilisateur soumet le formulaire via bouton personnalisé
2. ACF envoie les données → hook `acf/submit_success` déclenché
3. JS capte cet événement :
   - appelle `mettreAJourResumeInfos()`
   - recharge la page avec `?maj=champ` pour mise à jour visuelle
4. PHP relit les valeurs via `get_field()` ou `the_field()` → nouvelle vue

→ Tout champ traité par `acf_form()` doit suivre cette logique.


♻️ Sauvegardes AJAX : logique centrale et spécialisations par CPT
dans edition-functions.php, des fonctions telles que modifier_champ_organisateur(), modifier_champ_chasse(), modifier_champ_enigme() sont appelées par ajax

## 5. JavaScript modulaire
🧠 JS global : champ-init.js (règles transversales)

🧩 JS par CPT : organisateur-edit.js, chasse-edit.js, enigme-edit.js

🔌 Fonctions centralisées : initChampTexte, initChampImage, modifierChampSimple

JS global : champ-init.js (règles transversales)
JS par CPT : organisateur-edit.js, chasse-edit.js, enigme-edit.js

Fonctions centralisées disponibles :

modifierChampSimple(champ, valeur, postId, cpt)
→ Envoie une requête AJAX pour un champ simple (text, email, number, true_false)

initChampTexte(bloc)
→ Gère inline editing pour les champs text/email/textarea

initChampImage(bloc)
→ Gère les champs image avec sélecteur ACF (media uploader)

initChampDeclencheur(bouton)
→ Utilisé pour relier un bouton résumé à un champ ciblé

initChampConditionnel(nomChamp, correspondance)
→ Affiche/masque des blocs selon la valeur d’un radio

initChampRadioAjax(nomChamp, cpt)
→ Déclenche automatiquement l enregistrement AJAX d’un champ radio (ex : mode_validation)

initChampNbTentatives()
→ Gère les règles et l’enregistrement du champ "tentatives max" selon le coût en points

onCoutPointsUpdated(bloc, champ, valeur, postId, cpt)
→ Hook dynamique appelé après la mise à jour d’un champ coût

initChampNbGagnants()
→ Gère le nombre de gagnants avec case à cocher "illimité" et saisie contrôlée

mettreAJourResumeInfos()
→ Met à jour dynamiquement les indicateurs "champ-vide" / "champ-rempli" dans le résumé

mettreAJourAffichageCout(postId, cout)
→ Met à jour dynamiquement l’affichage du prix (ex : badge Gratuit / X pts)

validerDatesAvantEnvoi(champModifie)
→ Contrôle logique cohérent entre date de début / fin d’une chasse

afficherErreurGlobale(message)
→ Affiche un message d erreur global temporaire en top fixed

Et pour les CPT :
- enigme-edit.js : déclencheurs, panneaux, initialisation conditionnelle
📌 Le module `initChampPreRequis()` gère l’interdépendance logique entre le champ `enigme_acces_pre_requis` et le champ `enigme_acces_condition`.

- À chaque case cochée → AJAX `enigme_acces_pre_requis` + `enigme_acces_condition = pre_requis`
- Si toutes les cases sont décochées → `enigme_acces_condition = immediat`

Ce mécanisme garantit que la BDD reflète toujours un état cohérent, même si l'utilisateur ne touche pas manuellement aux radios.


- organisateur-edit.js : inline header, coordonnées, présentation

Section 5 – JavaScript modulaire
👉 Ajout dans champ-date-hooks.js :

Hook global onDateFieldUpdated() mis à jour pour gérer :

enigme_acces.date

caracteristiques.chasse_infos_date_debut

caracteristiques.chasse_infos_date_fin

👉 Préciser dans la section que :

initChampDate() gère automatiquement l’initialisation du champ + AJAX + fallback

onDateFieldUpdated() est appelé automatiquement dès la réponse AJAX (pas besoin de le rappeler manuellement)


## 6. Affichages publics

🎨 NUANCIER UTILISÉ

:root {
  --color-primary:            #FFD700;   /* 🟡 Couleur principale : jaune or */
  --color-secondary:          #E1A95F;   /* 🟠 Couleur secondaire : lien */
  --color-accent:             #CD7F32;   /* 🟤 Accent : bronze */

  --color-text-primary:       #F5F5DC;   /* 📜 Texte principal : beige clair */
  --color-text-secondary:     #555555;   /* 🎨 Texte secondaire : gris foncé */
  --color-titre-enigme:       #E5C07B;   /* 🏆 Titre des énigmes : or vieilli */

  --color-background:         #0B132B;   /* 🎭 Fond général */
  --color-background-button:  #8B0000;   /* 🔘 Fond des boutons : rouge foncé */

  --color-gris-3:             #adadad;   /* ⚪ Gris neutre n°3 */
  --color-text-fond-clair:    #1c1c1c;    /* noir clair */
}

:root {
  /* 📝 Nuancier d’édition */

  --color-editor-background:       #F1F3F4;   /* 🎭 Fond général : gris clair neutre */
  --color-editor-border:           #DADCE0;   /* 🏛️ Bordures, séparateurs */
  --color-editor-text:             #202124;   /* 🖋️ Texte principal */
  --color-editor-text-muted:       #5F6368;   /* 📎 Texte secondaire : aide, état inactif */
  --color-editor-heading:          #1F1F1F;   /* 📌 Titres, zones importantes */

  --color-editor-accent:           #1A73E8;   /* 🔹 Accent principal : liens, icônes interactives */
  --color-editor-button:           #1A73E8;   /* 🔘 Boutons */
  --color-editor-button-hover:     #1558B0;   /*       Hover bouton */

  --color-editor-error:            #D93025;   /* ⚠️ Message d’erreur */
  --color-editor-success:          #188038;   /* ✅ Message de validation */

  --color-editor-field-hover:      #E8F0FE;   /* 💠 Fond des champs actifs / survolés */
  --color-editor-placeholder:      #9AA0A6;   /* 💬 Placeholder ou aide contextuelle */
}


### 🧾 Pages publiques des CPT (organisateur, chasse, énigme)

principe général : le site est une plateforme de création de chasses, ce sont les organisateur qui sont mis en avant :
- le header du site est dynamique = organisateur lié (à une chasse ou une énigme)
- la présence de chassesautresor.com se limite à une top bar minimaliste et un footer
- le header organisateur comprend un logo, un titre, un menu
-- chasse : toujours visible, conteneur des chasses liées à organisateur
-- présentation : description complète, liens sociaux... (présentation est en réalité un panneau  affiché sous le header avant les chasses ou masqué)
-- contact : formulaire envoyé par mail à l organisateur avec l email renseigné dans le cpt organisateur. 

page organisateur 
  - boucle de chasses liées à organisateur 

page chasse 
  - fiche de présentation de la chasse (inspirée de amazon)
  - conteneur d énigmes (entête, body)

page enigme 
  - champs obligatoires et facultatifs affichés
  - formulaire de réponse selon paramètres de réponse de enigme_mode_validation



### 🎯 CTAs dynamiques selon rôle et statut
- organisateur : bouton de création de chasse et énigme, bouton enregistrement panneau latéral, bouton de soumission de chasse (non créé)
- abonné : bouton de création d organisateur, bouton d accès à une énigme (non créé), bouton de soumission de solution (non créé)



### 🏷️ Badges, statuts, affichages conditionnels

L’affichage dynamique des boutons, statuts ou badges (sur les énigmes notamment)
dépend du champ ACF `enigme_cache_etat_systeme` et du suivi individuel stocké
dans la table `wp_enigme_statuts_utilisateur` :

- `enigme_cache_etat_systeme` → état logique global, calculé automatiquement
- `wp_enigme_statuts_utilisateur` → statut individuel du joueur

Ces deux champs combines determinent :
- le bouton visible (CTA)
- la visibilite de l enigme
- les messages d aide ou de verrouillage
- le badge d etat

### 🔄 enigme_cache_etat_systeme – statut logique global
Definit si l enigme est techniquement disponible ou non.

| Valeur             | Description                                      |
|--------------------|--------------------------------------------------|
| accessible         | L enigme peut etre tentee ou relue               |
| bloquee_date       | A venir – date future de deblocage               |
| bloquee_pre_requis | Pre-requis non remplis                           |
| bloquee_chasse     | La chasse liee est bloquee                       |
| invalide           | Donnees manquantes ou mal configurees            |
| cache_invalide     | Erreur technique, logique ACF cassee             |

👤 Statut individuel du joueur (table `wp_enigme_statuts_utilisateur`)
Definit le niveau de progression du joueur sur une enigme donnee.

| Valeur        | Description                                         |
|---------------|-----------------------------------------------------|
| non_souscrite | Le joueur n a pas encore engage cette enigme        |
| en_cours      | Enigme en cours de resolution                       |
| resolue       | Enigme resolue (bonne reponse validee)              |
| terminee      | Enigme devenue inaccessible (ex : fin de chasse)    |
| echouee       | Toutes les tentatives sont epuisees                 |
| abandonnee    | Le joueur a choisi de l abandonner                  |

🧠 Interaction des deux statuts

| etat_systeme        | statut_utilisateur | Comportement                                                        |
|---------------------|--------------------|---------------------------------------------------------------------|
| accessible          | non_souscrite      | Bouton de souscription (gratuit ou payant)                          |
| accessible          | en_cours           | Formulaire de reponse actif                                         |
| accessible          | resolue            | Affichage : bouton « Revoir », solution possible                    |
| accessible          | abandonnee         | Affichage desactive ou grise                                        |
| bloquee_date        | (tout)             | Message « disponible a partir du... », pas de bouton                |
| bloquee_pre_requis  | (tout)             | Message « pre-requis non remplis », verrouillage                    |
| bloquee_chasse      | (tout)             | Chasse indisponible → enigme masquee ou bloquee                     |
| invalide            | (tout)             | Message d erreur type admin                                         |
| cache_invalide      | (tout)             | Message critique (champ mal configure)                              |

💰 Cas particulier : enigme payante

Une enigme est dite « payante » si elle est :
- accessible
- non_souscrite
- a un enigme_tentative_cout_points > 0

👉 Dans ce cas, un bouton specifique « Debloquer pour X points » est affiche (CTA conditionnel).


## 7. Accès & autorisations

🔐 Création automatique (profil organisateur, chasse, énigme)
sur la page /devenir-organisateur quand un abonné clique sur le cta "devenir organisateur" :
- organisateur_creation ajouté au rôle abonné
- cpt organisateur créé automatiquement, user est relié au champ utilisateurs_associes
- redirection vers la prévisualisation de l organisateur avec panneau d édition ouvert et modal de bienvenue (1ère visite)

quand tous les champs obligatoires du cpt organisateur sont remplis, affichage d un CTA de création de chasse. Au clic :
- création automatique cpt chasse
- pré remplissage de chasse_cache_organisateur avec cpt organisateur
- redirection vers la prévisualisation de la chasse avec panneau d édition ouvert et modal de guidage (1ère visite)

quand tous les champs obligatoires du cpt chasse sont remplis, affichage d un CTA de création d enigme. Au clic :
- création automatique cpt enigme
- pré remplissage de enigme_chasse_associee avec cpt chasse
- redirection vers la prévisualisation de l enigme avec panneau d édition ouvert 

pas encore créé : un bouton de soumission de validation de la chasse par l admin. Une fois validée
- les cpt organisateur chasse et enigme sont publiés
- l utilisateur organisateur_creation devient organisateur

[Abonné] → crée Organisateur (pending)  
→ complète → débloque création Chasse  
→ complète → débloque création Énigme  
→ complète → futur : bouton de demande de validation


### 🚦 Redirections et filtrages (edition=open, accès par rôle)

aucun accès à l interface admin wp (réservé au seul admin), sinon redirection vers la page d accueil, toute édition est en front.
la création du html des panneaux d édition est conditionnée par le rôle de l utilisateur et le statut de la chasse ou de l énigme, de même que le toggle régissant l affichage du panneau.
seuls les abonnés ont accès à /devenir-organisateur, sinon, redirection vers cpt organisateur pour rôle organisateur ou page identification pour anonyme


🧭 Conditions d’affichage / édition (utilisateur_peut_modifier_post, etc.)
géré par fonction dans access-functions.php
grandes règles : 
- un user avec rôle organisateur_creation peut modifier cpt organisateur, chasse et enigme, toutes en pending.
- un user avec rôle organisateur ne peut plus modifier une chasse ou énigme publiée
- un user avec rôle organisateur peut visualiser le panneau statistique de  enigme ou chasse (non créé)

## 8. Comportements spéciaux

la plupart sont documentés en notices, rubrique à enrichir

### ⚠️ Zones critiques

- Champs imbriqués ACF : manipulations complexes → toujours passer par les helpers du fichier `relations-functions.php`
- Statuts calculés : ne pas modifier manuellement les champs `cache_` sans déclencher leur recalcul
- Fichiers JS par CPT : ne pas mélanger logique d’UI (panneaux, toggles) avec logique métier (validation, statut, ACF)


🎁 Récompenses
panneau latéral avec rechargement de page


⏱️ Déblocages programmés

🧠 Tentatives & statuts utilisateur

🪄 Interactions entre chasses et énigmes

Lien technique
Chaque énigme est obligatoirement rattachée à une chasse via le champ ACF enigme_chasse_associee (relationship, 1 seule valeur).
Inversement, la chasse garde une trace de ses énigmes via un champ chasse_cache_enigmes, mis à jour automatiquement.

Synchronisation automatique
Lorsqu’une énigme est créée, modifiée ou supprimée, un recalcul est effectué pour maintenir la cohérence du champ chasse_cache_enigmes.

Fonction concernée :

verifier_et_synchroniser_cache_enigmes_si_autorise($chasse_id)
Appelée au chargement des templates liés (single-enigme.php, etc.), elle :

- vérifie que l’utilisateur peut agir
- compare la liste des énigmes réellement liées à la chasse
- met à jour le cache si nécessaire

Affichage conditionnel
Le statut de la chasse peut invalider ou verrouiller des énigmes liées.

Statut de la chasse (chasse_cache_statut)    Impact sur les énigmes
en_cours ou payante                         énigmes actives
a_venir                                     toutes les énigmes sont bloquées (bloquee_chasse)
terminee                                    les énigmes deviennent non engageables
revision, correction, banni                 désactivation ou masquage possible

Statuts dynamiques propagés
Les fonctions liées à l’édition ou la suppression d’une énigme (ex. via AJAX ou formulaire ACF) déclenchent :

- une vérification de cohérence
- une mise à jour des statuts calculés (enigme_cache_etat_systeme)

Cela garantit que les énigmes ne deviennent jamais « accessibles » si la chasse ne l’est pas.

etat_systeme	statut_utilisateur	Cas métier
bloquee_date	(tout)	enigme_acces_condition = date_programmee && date future
invalide	(tout)	Si date_programmee sans date OU pre_requis sans valeur

→ Cela permettra de clarifier ce qui est bloqué, ce qui est invalide, et ce qui est accessible.


## 9. Performances et bonnes pratiques
🧹 Optimisation images (Imagify)


## 🔐 Visuels des énigmes (`enigme_visuel_image`)

Les visuels d’énigmes sont protégés par un système de stockage dédié et un proxy sécurisé. Structure en place :

- Les images sont enregistrées dans `/wp-content/uploads/_enigmes/enigme-{ID}/` via un filtre `upload_dir`
- Un fichier `.htaccess` est injecté automatiquement dans ce dossier à chaque ajout d’image
- Ce `.htaccess` bloque l’accès direct aux images, sauf depuis l’admin WordPress ou via AJAX authentifié
- L’affichage public se fait uniquement via le proxy `/voir-image-enigme?id=...`
- Le proxy vérifie les droits d’accès via `utilisateur_peut_voir_enigme()`
- Le système supporte les tailles d’image (full, thumbnail…) via un paramètre `?taille=`

### 🔁 Règle `.htaccess` injectée

Le fichier `.htaccess` contient :
<IfModule mod_rewrite.c>
RewriteEngine On

# Autorise admin et AJAX
RewriteCond %{REQUEST_URI} ^/wp-admin/ [OR]
RewriteCond %{HTTP_REFERER} ^https?://[^/]+/wp-admin/ [NC]
RewriteCond %{HTTP_COOKIE} wordpress_logged_in_ [NC]
RewriteRule . - [L]

# Sinon : bloque tout accès direct aux images
<FilesMatch "\.(jpg|jpeg|png|gif|webp)$">
  Require all denied
</FilesMatch>
</IfModule>



Le fichier est injecté ou mis à jour par la fonction `injecter_htaccess_protection_images_enigme($post_id, $forcer = true)`.

---

### 🖼️ Galerie publique (`afficher_visuels_enigme()`)

- Appelée depuis le template `single-enigme.php`
- Affiche l’image principale dans une `<a>` cliquable, avec `rel="lightbox-enigme"` et `class="fancybox image"`
- Utilise Firelight Lightbox (ancien Easy Fancybox)
- Toutes les images sont servies via `/voir-image-enigme?id=...`
- Les vignettes permettent de changer l’image principale (JS), sans déclencher la lightbox
- Le conteneur `.image-principale` utilise `min-height` pour éviter le scroll au changement

---

### 💡 Résumé du fonctionnement

- Sécurité stricte : `.htaccess` + proxy PHP obligatoire
- Accès conditionné par `utilisateur_peut_voir_enigme()`
- Compatible éditeur ACF (via exception HTTP_COOKIE)
- Galerie responsive, sans scroll indésirable
- Prête à accueillir des extensions (slider, zoom, etc.)




⚠️ Fallbacks / protections (HTML, JS, PHP)

### 🎯 Cache, pré-remplissage, chargements conditionnels
aucun système de cache sofware, serveur mis en place durant le développement


## 10. Appendices

📌 Champs déclencheurs de statut (chasse / énigme)

- chasse_infos_date_debut
- chasse_infos_duree_illimitee
- chasse_infos_nb_max_gagants
- enigme_mode_validation
- enigme_tentative_cout_points

→ Ces champs doivent être surveillés côté JS pour déclencher : recalcul, badge dynamique, MAJ résumé, ou état utilisateur.


### ⚙️ Résumé des hooks JS / PHP utiles
Fichier JS	Rôle principal	Fonctions clés
champ-init.js	JS global transverse	initChampTexte, modifierChampSimple
enigme-edit.js	Edition front énigme	initChampNbTentatives, initChampRadioAjax
chasse-edit.js	Edition front chasse	validerDatesAvantEnvoi, initLiensChasse
organisateur-edit.js	Edition front organisateur (header + liens)	initLiensOrganisateur


### 📋 Formulaires avec comportement critique (AJAX ou rechargement)

| Formulaire                                | JS déclencheur                 | Particularité                     |
|-------------------------------------------|-------------------------------|-----------------------------------|
| `formulaire-coordonnees` (organisateur)   | fetch AJAX + JSON.stringify() | IBAN/BIC validés en JS            |
| `formulaire-liens-publics` (orga)         | initLiensOrganisateur         | Affichage dynamique post-submit   |
| `formulaire-liens-chasse`                | initLiensChasse               | Idem orga, côté chasse            |
| `champ-recompense-*` (champ libre, chasse)| JS personnalisé (saisie + fetch séquencé) | ⚠️ Validation manuelle + reload |


### 🚫 Champs ACF désactivés ou ignorés

- enigme_reponse_texte_manuelle → champ abandonné (soumission manuelle = email, non stockée)
- [placeholder éventuel pour futurs abandons]


⛔ Interdiction de supposer le type d’un champ ACF sans vérification explicite.
Si le type est fourni dans la documentation (ex : enigme_reponse_bonne = text), il doit être considéré comme exact.
Ne jamais déduire qu’un champ est un repeater ou un groupe sans analyse concrète (notice, ACF, ou get_field()).


📌 Attributs HTML obligatoires pour tout champ JS

Chaque champ ciblé par un module JS (inline, conditionnel, panneau, etc.) doit contenir :

- `data-champ="nom_acf_complet"` (ex : `caracteristiques.chasse_infos_cout_points`)
- `data-cpt="chasse"` (ou `organisateur`, `enigme`)
- `data-post-id="ID"` (toujours requis pour AJAX)

Cas particulier : les boutons déclencheurs de panneau doivent en plus avoir `.champ-modifier` et un `aria-label`.



### 📂 Références internes utiles (template-parts/, data-champ, etc.)

### 💡 À venir :

Liste complète des hooks JS centralisés (modifierChampSimple, initChampTexte, initChampRadioAjax, etc.)

Liste des champs à surveiller pour recalculs automatiques (ex : chasse_cache_statut, enigme_cache_etat_systeme)

Dépendances entre champs ACF imbriqués et triggers de statut

Diagrammes de propagation des statuts (chasse ↔︎ énigme)

### 🔄 Dépendances dynamiques entre champs

Certains champs déclenchent une mise à jour automatique d’un autre champ lorsqu’ils sont modifiés.

Exemples :
- `enigme_acces_pre_requis` → force la valeur de `enigme_acces_condition`
- `enigme_tentative_cout_points = 0` → limite `enigme_tentative_max` à 5

Ces mises à jour sont gérées exclusivement côté PHP (dans `modifier_champ_enigme()`), et ne doivent pas être traitées côté JS de manière directe ou forcée.

👉 L’objectif est de rendre le système résilient, même si plusieurs champs sont modifiés en parallèle (ex. : via AJAX).
