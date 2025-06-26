# 🧩 Notice technique – Édition inline du champ `post_title` (panneaux frontaux)

Ce document décrit la mise en place d'une édition du champ `post_title` pour les CPT personnalisés (`organisateur`, `chasse`, `enigme`) via un système **inline + AJAX**, réutilisable et cohérent avec les autres champs ACF.

---

## ✅ Structure HTML du champ `post_title`

Le bloc d’édition doit suivre cette structure minimale :

```php
<div class="champ-{cpt} champ-txt-editable champ-titre <?= $isTitreParDefaut ? 'champ-vide' : 'champ-rempli'; ?>"
     data-champ="post_title"
     data-cpt="{cpt}"
     data-post-id="<?= esc_attr($post_id); ?>">

  <div class="champ-affichage">
    <h1><?= esc_html($titre); ?></h1>
    <?php if ($edition_active) : ?>
      <button type="button" class="champ-modifier" aria-label="Modifier le titre">✏️</button>
    <?php endif; ?>
  </div>

  <div class="champ-edition" style="display: none;">
    <input type="text" maxlength="80" value="<?= esc_attr($titre); ?>" class="champ-input">
    <button type="button" class="champ-enregistrer">✓</button>
    <button type="button" class="champ-annuler">✖</button>
  </div>

  <div class="champ-feedback"></div>
</div>
```

---

## ✅ Bouton résumé secondaire (panneau)

À placer dans le panneau latéral `edition-{cpt}.php` :

```php
<li class="champ-{cpt} champ-titre <?= $isTitreParDefaut ? 'champ-vide' : 'champ-rempli'; ?>"
    data-champ="post_title"
    data-cpt="{cpt}"
    data-post-id="<?= esc_attr($post_id); ?>">
  Un titre
  <button type="button" class="champ-modifier"
          data-champ="post_title"
          data-cpt="{cpt}"
          data-post-id="<?= esc_attr($post_id); ?>">✏️</button>
</li>
```

---

## 🧠 JS – Initialisation automatique

Dans `champ-init.js`, la fonction `initChampTexte()` détecte tous les blocs `.champ-{cpt}[data-champ="post_title"]`.

### ✅ Action AJAX à adapter :

```js
const action = (cpt === 'chasse') ? 'modifier_champ_chasse' :
               (cpt === 'enigme') ? 'modifier_champ_enigme' :
               'modifier_champ_organisateur';
```

---

## 🛠️ PHP – Fonction AJAX dédiée

Créer une fonction dédiée dans `edit-functions.php` :

```php
add_action('wp_ajax_modifier_champ_enigme', 'modifier_champ_enigme');

function modifier_champ_enigme() {
  if (!is_user_logged_in()) wp_send_json_error('non_connecte');

  $user_id = get_current_user_id();
  $champ   = sanitize_text_field($_POST['champ'] ?? '');
  $valeur  = wp_kses_post($_POST['valeur'] ?? '');
  $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;

  if (!$champ || !$post_id || get_post_type($post_id) !== 'enigme') {
    wp_send_json_error('⚠️ donnees_invalides');
  }

  $auteur = (int) get_post_field('post_author', $post_id);
  if ($auteur !== $user_id) wp_send_json_error('⚠️ acces_refuse');

  if ($champ === 'post_title') {
    $ok = wp_update_post([
      'ID' => $post_id,
      'post_title' => $valeur
    ], true);
    if (is_wp_error($ok)) wp_send_json_error('⚠️ echec_update_post_title');
    wp_send_json_success([ 'champ' => $champ, 'valeur' => $valeur ]);
  }

  wp_send_json_error('⚠️ champ_non_traite');
}
```

---

## 🎯 Détection de complétion (`mettreAJourResumeInfos`)

Le champ est considéré comme "rempli" si le titre **n’est pas égal au titre par défaut**.

Exemple dans JS :

```js
if (champ === 'post_title') {
  const valeurTitre = bloc?.querySelector('.champ-input')?.value.trim().toLowerCase();
  const titreParDefaut = window.CHP_ENIGME_DEFAUT?.titre || 'nouvelle énigme';
  estRempli = valeurTitre && valeurTitre !== titreParDefaut;
}
```

---

## 🎨 CSS – Affichage conditionnel des stylos

Ajouter les classes nécessaires dans `layout.css` (ou équivalent) pour rendre visibles les stylos :

```css
body.edition-active-enigme .enigme-header .champ-modifier,
body.edition-active-enigme .edition-panel-enigme .champ-modifier,
body.edition-active-chasse .page-chasse-wrapper .champ-modifier,
body.edition-active .header-organisateur-wrapper .champ-modifier,
body.edition-active .edition-panel-organisateur .champ-modifier {
  display: inline-block;
}
```

---

## 🧪 Test de fonctionnement

- ✅ `?edition=open` déclenche le panneau
- ✅ Le bouton ✏️ sur le titre est cliquable
- ✅ L'édition inline s’ouvre, sauvegarde, puis se referme
- ✅ Le résumé se met à jour (icône + classe)
- ✅ Le titre est mis à jour en BDD

---

## 📦 Résumé

| Élément            | Requis      |
|--------------------|-------------|
| Bloc HTML complet  | ✅ oui       |
| AJAX dédié         | ✅ oui       |
| Déclencheur résumé | ✅ recommandé |
| JS centralisé      | ✅ `champ-init.js` |
| CSS conditionnel   | ✅ à ajouter manuellement |

---

## 🧩 Ce module peut être intégré dans une notice plus globale :
> "Notice des champs modifiables inline – structure standard des panneaux"