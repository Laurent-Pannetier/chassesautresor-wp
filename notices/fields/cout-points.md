# 💰 Notice technique — Champ ACF `cout_points` en édition frontale

Ce document décrit la structure HTML, la logique JS et le traitement PHP pour les champs ACF de type `number` associés à une case à cocher `Gratuit`, utilisés pour représenter un "coût en points".

Actuellement utilisé pour :

* `chasse_infos_cout_points` (CPT `chasse`)
* `enigme_tentative_cout_points` (CPT `enigme`)

---

## ✅ 1. Structure HTML attendue

```php
<li class="champ-cpt champ-cout-points <?= empty($cout) ? 'champ-vide' : 'champ-rempli'; ?>"
    data-champ="xxx.yyy"
    data-cpt="xxx"
    data-post-id="<?= esc_attr($post_id); ?>">

  <div class="champ-edition" style="display: flex; align-items: center;">
    <label>Coût <span class="txt-small">(points)</span></label>

    <input type="number"
           class="champ-input champ-cout"
           min="0"
           step="1"
           value="<?= esc_attr($cout); ?>"
           placeholder="0" />

    <div class="champ-option-gratuit" style="margin-left: 15px;">
      <input type="checkbox"
             name="cout-gratuit"
             <?= ((int)$cout === 0) ? 'checked' : ''; ?>>
      <label>Gratuit</label>
    </div>
  </div>

  <div class="champ-feedback"></div>
</li>
```

> Remplacer `champ-cpt` et `xxx.yyy` par les bons identifiants selon le contexte (ex: `champ-chasse`, `chasse_infos_cout_points`).

---

## ✅ 2. JS — `initChampCoutPoints()` dans `champ-init.js`

```js
function initChampCoutPoints() {
  document.querySelectorAll('.champ-cout-points').forEach(bloc => {
    const input = bloc.querySelector('.champ-input.champ-cout[type="number"]');
    const checkbox = bloc.querySelector('input[type="checkbox"]');
    const champ = bloc.dataset.champ;
    const postId = bloc.dataset.postId;
    const cpt = bloc.dataset.cpt;

    if (!input || !checkbox || !champ || !postId || !cpt) return;

    let timerDebounce;
    let ancienneValeur = input.value.trim();

    const enregistrerCout = () => {
      clearTimeout(timerDebounce);
      timerDebounce = setTimeout(() => {
        let valeur = parseInt(input.value.trim(), 10);
        if (isNaN(valeur) || valeur < 0) valeur = 0;
        input.value = valeur;

        if (valeur.toString() === ancienneValeur.toString()) return;
        ancienneValeur = valeur;

        modifierChampSimple(champ, valeur, postId, cpt);

        if (typeof window.onCoutPointsUpdated === 'function') {
          window.onCoutPointsUpdated(bloc, champ, valeur, postId, cpt);
        }
      }, 400);
    };

    // ✅ Init visuelle
    checkbox.checked = parseInt(input.value, 10) === 0;

    input.addEventListener('input', enregistrerCout);

    checkbox.addEventListener('change', () => {
      if (checkbox.checked) {
        ancienneValeur = input.value.trim();
        input.value = 0;
      } else {
        const valeur = parseInt(ancienneValeur, 10);
        input.value = valeur > 0 ? valeur : 10;
      }
      enregistrerCout();
    });
  });
}

document.addEventListener('DOMContentLoaded', initChampCoutPoints);
```

---

## ✅ 3. JS — `onCoutPointsUpdated()` (dans le fichier du CPT concerné)

```js
window.onCoutPointsUpdated = function (bloc, champ, valeur, postId, cpt) {
  if (champ === 'chasse_infos_cout_points') {
    if (typeof mettreAJourAffichageCout === 'function') {
      mettreAJourAffichageCout(postId, valeur);
    }
  }

  if (champ === 'enigme_tentative_cout_points') {
    const champMaxBloc = document.querySelector('[data-champ="enigme_tentative_max"]');
    const champMaxInput = champMaxBloc?.querySelector('.champ-input');
    const message = champMaxBloc?.querySelector('.message-tentatives');

    if (champMaxInput) {
      if (valeur === 0) {
        champMaxInput.value = 5;
        champMaxInput.max = 5;
        modifierChampSimple('enigme_tentative_max', 5, postId, cpt);
      } else {
        champMaxInput.removeAttribute('max');
      }
    }

    if (message) {
      message.textContent = (valeur === 0)
        ? 'Mode gratuit : 5 tentatives maximum par jour.'
        : 'Mode payant : nombre de tentatives illimité.';
    }
  }
};
```

---

## ✅ 4. PHP — `modifier_champ_*` côté serveur

### Exemple `chasse` :

```php
if ($champ === 'chasse_infos_cout_points') {
  $ok = update_field('chasse_infos_cout_points', (int) $valeur, $post_id);
  if ($ok) {
    $champ_valide = true;
    $doit_recalculer_statut = true;
  }
}
```

---

## 🕮 À tester

| Situation             | Comportement attendu                        |
| --------------------- | ------------------------------------------- |
| `valeur = 0`          | case cochée, valeur 0 enregistrée           |
| `valeur > 0`          | case décochée, champ libre                  |
| Coût change via input | enregistrement AJAX + mise à jour dynamique |
| Case cochée           | force à `0`, envoie AJAX                    |
| Case décochée         | remet ancienne valeur > 0 ou 10 par défaut  |

---

## 📆 Bonnes pratiques

| Pratique                      | Pourquoi                                               |
| ----------------------------- | ------------------------------------------------------ |
| `data-*` bien renseignés      | Requis pour les scripts centralisés                    |
| ID différents ou absents      | Pour éviter les conflits entre CPT                     |
| JS : `onCoutPointsUpdated()`  | Gère les interactions spécifiques sans casser le tronc |
| Appel `modifierChampSimple()` | Centralise tous les envois AJAX                        |

---

Fin de notice.
