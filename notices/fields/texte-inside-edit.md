# 🧩 Notice – Champ éditable avec affichage public dynamique hors panneau

Certains champs ACF (texte, nombre, etc.) doivent pouvoir être édités dans le panneau d’édition d’un CPT (chasse, énigme, etc.), mais **ne doivent jamais afficher leur valeur directement dans ce panneau**.

En revanche, leur **valeur est affichée ailleurs**, notamment dans la fiche publique (`single-*`) — et cette valeur doit être mise à jour **dynamiquement en JS** au moment de la sauvegarde.

---

## ✅ Comportement attendu

- Le champ a un libellé fixe dans le panneau d’édition (ex. : "Une légende ✏️").
- L’édition s’ouvre via `champ-edition`, mais **aucune valeur ne s’affiche dans `champ-affichage`**.
- Lors du clic sur le bouton “✓”, la valeur est :
  - enregistrée via AJAX
  - affichée immédiatement dans un élément **en-dehors du panneau** (ex. `p.enigme-legende` dans `single-enigme.php`).
- Le rechargement de la page continue de fonctionner normalement avec ACF.

---

## 🔧 HTML dans le panneau (`edition-*.php`)

```php
<li class="champ-enigme champ-texte"
    data-champ="enigme_visuel_legende"
    data-cpt="enigme"
    data-post-id="<?= esc_attr($post_id); ?>">

  <div class="champ-affichage">
    Une légende
    <button type="button" class="champ-modifier" aria-label="Modifier la légende">✏️</button>
  </div>

  <div class="champ-edition" style="display: none;">
    <input type="text"
           class="champ-input"
           maxlength="100"
           value="<?= esc_attr($legende); ?>"
           placeholder="Ajouter une légende">
    <button type="button" class="champ-enregistrer">✓</button>
    <button type="button" class="champ-annuler">✖</button>
  </div>

  <div class="champ-feedback"></div>
</li>
```

---

## 📄 HTML d’affichage dans `single-*.php`

```php
<?php
$legende = get_field('enigme_visuel_legende', $post_id);
if (!empty($legende)) :
?>
  <p class="enigme-legende"><?= esc_html($legende); ?></p>
<?php endif; ?>
```

---

## 🧠 JS dans `initChampTexte()` (partie `res.success`)

```js
if (champ === 'enigme_visuel_legende') {
  const legendeDOM = document.querySelector('.enigme-legende');
  const valeurTexte = input.value.trim();
  if (legendeDOM) {
    legendeDOM.textContent = valeurTexte;
    legendeDOM.classList.add('modifiee');
  }
}
```

---

## 🧼 Avantages

- Comportement clair et isolé
- Le panneau reste minimal et lisible
- L’affichage est réactif côté utilisateur
- Pas de duplication de valeur ou d’affichage partiel dans le panneau

---

## 📌 Champs actuellement concernés

- `enigme_visuel_legende` (énigme)
- `chasse_infos_nb_max_gagants` (chasse)
- `chasse_infos_date_debut`, `chasse_infos_date_fin` (chasse)
- Tout champ qui ne doit **pas apparaître dans le panneau**, mais **doit être affiché dynamiquement ailleurs**

---