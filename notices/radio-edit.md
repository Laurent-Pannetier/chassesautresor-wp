# 🔘 Notice technique – Champ `radio` ACF `enigme_acces.condition`

Ce document décrit la gestion frontale du champ `radio` ACF `enigme_acces.condition` dans le contexte d'une énigme, en suivant les standards d'édition front utilisés dans le projet.

---

## ✅ 1. Contexte et objectif

Le champ `enigme_acces.condition` permet de déterminer comment l'énigme devient accessible. Il est de type `radio`, avec 3 choix :

* `immediat` : accessible sans condition
* `date_programmee` : accessible à partir d'une date (champ `date` lié)
* `pre_requis` : accessible après la résolution d'autres énigmes (placeholder actuellement)

---

## ✅ 2. Structure HTML attendue

```php
<li class="champ-enigme champ-access"
    data-champ="enigme_acces.condition"
    data-cpt="enigme"
    data-post-id="<?= esc_attr($enigme_id); ?>">

  <fieldset>
    <legend>Condition d'accès</legend>
    <?php
    $condition = $acces['condition'] ?? 'immediat';
    $options = [
      'immediat'        => 'Immédiat',
      'date_programmee' => 'Date programmée',
      'pre_requis'      => 'Pré-requis',
    ];
    foreach ($options as $val => $label) : ?>
      <label>
        <input type="radio"
               name="acf[enigme_acces_condition]"
               value="<?= esc_attr($val); ?>"
               <?= $condition === $val ? 'checked' : ''; ?>>
        <?= esc_html($label); ?>
      </label>
    <?php endforeach; ?>
  </fieldset>

  <div class="champ-feedback"></div>
</li>
```

---

## ✅ 3. JS – Affichage conditionnel (initConditionAcces)

```js
function initConditionAcces() {
  const radios = document.querySelectorAll('input[name="acf[enigme_acces_condition]"]');
  const champDate = document.getElementById('champ-enigme-date');
  const champPreRequis = document.getElementById('champ-enigme-pre-requis');

  if (!radios.length || !champDate || !champPreRequis) return;

  function mettreAJourAffichageCondition() {
    const valeur = [...radios].find(r => r.checked)?.value;

    champDate.style.display = (valeur === 'date_programmee') ? '' : 'none';
    champPreRequis.style.display = (valeur === 'pre_requis') ? '' : 'none';
  }

  radios.forEach(radio => {
    radio.addEventListener('change', mettreAJourAffichageCondition);
  });

  mettreAJourAffichageCondition(); // au chargement
}
```

---

## ✅ 4. JS – Enregistrement AJAX (initEnregistrementConditionAcces)

```js
function initEnregistrementConditionAcces() {
  const radios = document.querySelectorAll('input[name="acf[enigme_acces_condition]"]');

  radios.forEach(radio => {
    radio.addEventListener('change', function () {
      const bloc = radio.closest('[data-champ]');
      const champ = bloc?.dataset.champ;
      const postId = bloc?.dataset.postId;

      if (!champ || !postId) return;

      fetch(ajaxurl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          action: 'modifier_champ_enigme',
          champ,
          valeur: this.value,
          post_id: postId
        })
      })
      .then(r => r.json())
      .then(res => {
        if (!res.success) {
          console.error('❌ Erreur AJAX condition accès :', res.data);
        } else {
          console.log('✅ Condition d’accès enregistrée :', this.value);
          if (typeof window.mettreAJourResumeInfos === 'function') {
            window.mettreAJourResumeInfos();
          }
        }
      })
      .catch(err => {
        console.error('❌ Erreur réseau AJAX condition accès :', err);
      });
    });
  });
}
```

---

## ✅ 5. PHP – Traitement côté serveur

Le champ `enigme_acces_condition` est une simple `radio` dans un `group`, donc la logique serveur applique :

```php
if (strpos($champ, '.') !== false) {
  [$groupe, $sous_champ] = explode('.', $champ);
  $champ = $groupe . '_' . $sous_champ;
}

update_field($champ, $valeur, $post_id);
```

Pas de traitement spécial requis.

---

## 🧪 Tests de validation

| Test                      | Attendu                                      |
| ------------------------- | -------------------------------------------- |
| Changement de choix radio | Enregistrement AJAX + affichage conditionnel |
| Choix = `immediat`        | Aucun champ affiché                          |
| Choix = `date_programmee` | Affiche champ date                           |
| Choix = `pre_requis`      | Affiche placeholder                          |
| Recharge page             | Le bon bloc reste visible selon la valeur    |

---

## 📦 Bonnes pratiques

| Bonne pratique                                      | Description                              |
| --------------------------------------------------- | ---------------------------------------- |
| Affichage direct des radios                         | Pas de bouton ✏️                         |
| Enregistrement AJAX sur `change`                    | Immédiat, sans validation supplémentaire |
| Un seul champ visible à la fois                     | gestion JS claire par valeur             |
| Rendre les blocs masqués visibles via JS uniquement | pour garder un DOM clair                 |
