# 📅 Notice technique – Champ ACF `date` en édition frontale

Ce document décrit les bonnes pratiques pour gérer un champ ACF de type `date` ou `date_time_picker` en édition frontale, incluant :
- la structure HTML
- les appels AJAX
- l'affichage dynamique
- la conversion et les particularités selon leur usage en groupe ou en racine.

---

## ✅ 1. Structure HTML attendue

```php
<div class="champ-chasse champ-date-debut"
     data-champ="caracteristiques.chasse_infos_date_debut"
     data-cpt="chasse"
     data-post-id="<?= esc_attr($post_id); ?>">

  <label for="chasse-date-debut">Date de début</label>
  <input type="date"
         id="chasse-date-debut"
         name="chasse-date-debut"
         value="<?= esc_attr($date_formatee); ?>"
         class="champ-inline-date champ-date-edit" required />

  <div class="champ-feedback champ-date-feedback" style="display:none;"></div>
</div>
```

**Contraintes :**
- L’attribut `value` de `<input type="date">` **doit être au format** `YYYY-MM-DD`
- Si le champ ACF est un `date_time_picker`, **il retourne `Y-m-d H:i:s`**, il faut donc appliquer `substr($valeur, 0, 10)`
- Si le champ est imbriqué dans un `group`, il doit être récupéré via :

```php
$groupe = get_field('mon_groupe', $post_id);
$date_brute = $groupe['champ_date'] ?? '';
$date_formatee = $date_brute ? substr($date_brute, 0, 10) : '';
```

---

## ✅ 2. JavaScript – `initChampDate()`

Le champ est automatiquement pris en charge par `champ-init.js` :

```js
input.addEventListener('change', () => {
  const valeur = input.value.trim();
  if (!/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/.test(valeur)) return;

  fetch(ajaxurl, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({
      action,
      champ,
      valeur,
      post_id
    })
  }).then(...);
});
```

**Particularités :**
- Le champ est validé côté JS (format `Y-m-d`)
- Le champ `data-champ` peut pointer un champ imbriqué (`group.champ`) → sera traité côté PHP
- Un hook JS personnalisé peut être appelé : `window.onDateFieldUpdated(input, valeur)`

---

## ✅ 3. PHP – Traitement dans `modifier_champ_*()`

### Cas champ simple :
```php
update_field('date_champ', $valeur, $post_id);
```

### Cas champ imbriqué (dans un group) :

```php
// Exemple : enigme_acces.date
if ($champ === 'enigme_acces.date') {
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $valeur)) {
    wp_send_json_error('⚠️ format_date_invalide');
  }
  $valeur .= ' 00:00:00'; // conversion Y-m-d -> Y-m-d H:i:s
  $ok = mettre_a_jour_sous_champ_group($post_id, 'enigme_acces', 'enigme_acces_date', $valeur);
  if ($ok) wp_send_json_success([...]);
  wp_send_json_error('⚠️ echec_mise_a_jour_final');
}
```

---

## 🔁 Affichage dynamique : `onDateFieldUpdated()`

```js
window.onDateFieldUpdated = function(input, valeur) {
  const champ = input.closest('[data-champ]')?.dataset.champ;
  if (champ === 'caracteristiques.chasse_infos_date_debut') {
    const span = document.querySelector('.date-debut');
    if (span) span.textContent = formatDateFr(valeur);
  }
};
```

---

## 🧪 Tests de validation à faire par CPT

| Test                                     | Attendu                         |
|------------------------------------------|---------------------------------|
| Enregistrement ACF (simple ou groupé)   | ✅ Retour `success`             |
| Format front de `value` (`Y-m-d`)       | ✅ Affichage HTML correct        |
| Affichage dynamique (JS)                | ✅ Span ou preview mis à jour   |
| Saisie invalide (`0`, vide, etc.)       | ✅ Ignorée ou corrigée           |

---

## 🧱 Bonnes pratiques

| Bonne pratique                            | Pourquoi                              |
|------------------------------------------|---------------------------------------|
| Toujours formater le `value` en `Y-m-d`  | HTML5 `<input type="date">` l'exige  |
| Appeler `get_field('groupe')['champ']`   | Si champ ACF est imbriqué             |
| Ajouter `00:00:00` si champ = date_time_picker | Format ACF exigé (Y-m-d H:i:s)    |
| Centraliser la logique JS/PHP            | Pour éviter les cas divergents       |

---

## 📦 Exemple complet – groupe imbriqué avec affichage HTML

```php
$groupe = get_field('enigme_acces', $post_id);
$date_raw = $groupe['enigme_acces_date'] ?? '';
$date_formatee = $date_raw ? substr($date_raw, 0, 10) : '';
```

```html
<input type="date"
       id="enigme-date-deblocage"
       name="enigme-date-deblocage"
       value="<?= esc_attr($date_formatee); ?>"
       class="champ-inline-date champ-date-edit" />
```
