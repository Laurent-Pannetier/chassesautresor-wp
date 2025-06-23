# 🧩 Notice technique — Champs ACF imbriqués (Groupes / Sous-champs)

Cette notice décrit les bonnes pratiques pour la gestion **des champs ACF imbriqués**, c’est-à-dire les champs de type `group`, `repeater` ou `flexible content`, et leur manipulation en PHP et JavaScript.

---

## ✅ 1. Objectif

Permettre la **lecture**, **mise à jour** et **contrôle** des champs ACF imbriqués **sans casser les autres valeurs du groupe**.

Cas typiques :
* `caracteristiques.chasse_infos_cout_points`
* `enigme_tentative.enigme_tentative_max`
* `enigme_acces.enigme_acces_date` (type `date_time_picker`)

---

## ✅ 2. Structure des noms de champ

| Format JS (`data-champ`)                    | Format PHP (`update_field`)                        |
| ------------------------------------------- | -------------------------------------------------- |
| `caracteristiques.chasse_infos_cout_points` | `chasse_infos_cout_points` dans `caracteristiques` |
| `enigme_tentative.enigme_tentative_max`     | `enigme_tentative_max` dans `enigme_tentative`     |
| `enigme_acces.enigme_acces_date`            | `enigme_acces_date` dans `enigme_acces`            |

**Important :**
- JS utilise la notation pointée (`groupe.champ`)
- PHP doit reconstruire le tableau imbriqué avec les bons noms
- ⚠️ ACF ne tolère pas les valeurs vides pour certains types (`relationship`, `post_object`, etc.) dans les groupes

---

## ✅ 3. JS — Envoi AJAX avec champ imbriqué

```js
modifierChampSimple('caracteristiques.chasse_infos_date_debut', '2025-06-01', postId, 'chasse');
```

---

## ✅ 4. PHP — Fonction centrale `mettre_a_jour_sous_champ_group()`

### Structure correcte à utiliser :

```php
function mettre_a_jour_sous_champ_group($post_id, $group_key_or_name, $subfield_name, $new_value) {
    $group_object = get_field_object($group_key_or_name, $post_id);
    if (!$group_object || !isset($group_object['sub_fields'])) return false;

    $groupe = get_field($group_object['name'], $post_id) ?: [];
    $groupe[$subfield_name] = $new_value;

    $champ_a_enregistrer = [];
    foreach ($group_object['sub_fields'] as $sub) {
        $name = $sub['name'];
        $type = $sub['type'];
        $val  = $groupe[$name] ?? null;

        // Conversion automatique date_time_picker
        if ($name === $subfield_name && $type === 'date_time_picker') {
            if (is_string($val) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) {
                $val .= ' 00:00:00';
            }
        }

        // Ignorer relationship vides (empêchent update_field)
        if (in_array($type, ['relationship', 'post_object', 'taxonomy'], true) && empty($val)) {
            continue;
        }

        $champ_a_enregistrer[$name] = $val;
    }

    // ⚠️ ACF exige que tous les champs attendus soient présents OU explicitement ignorés
    foreach ($group_object['sub_fields'] as $sub) {
        $name = $sub['name'];
        if (!array_key_exists($name, $champ_a_enregistrer)) {
            $champ_a_enregistrer[$name] = null;
        }
    }

    return update_field($group_object['name'], $champ_a_enregistrer, $post_id);
}
```

---

## ✅ 5. Traitement AJAX dans `modifier_champ_*()`

```php
if (strpos($champ, '.') !== false) {
  [$groupe, $sous_champ] = explode('.', $champ, 2);
  $ok = mettre_a_jour_sous_champ_group($post_id, $groupe, "{$groupe}_{$sous_champ}", $valeur);
  if ($ok) wp_send_json_success(['champ' => $champ, 'valeur' => $valeur]);
  wp_send_json_error('echec_mise_a_jour_final');
}
```

---

## 🛑 Erreurs fréquentes

| Problème                              | Cause probable                                      |
|---------------------------------------|-----------------------------------------------------|
| `update_field()` retourne `false`     | Champ relationship vide / structure incomplète      |
| Mauvais format `date` (`Y-m-d`)       | Manque ` 00:00:00` pour un champ `date_time_picker` |
| Sous-champ absent dans tableau        | `$champ_a_enregistrer` incomplet                    |
| Utilisation de `key` au lieu de `name`| `update_field(field_key)` ne fonctionne pas pour les groupes |

---

## ✅ À tester systématiquement

| Test                                       | Attendu                        |
|-------------------------------------------|--------------------------------|
| Envoi AJAX sur champ imbriqué             | ✅ Format `group.champ` en JS   |
| Traitement PHP `mettre_a_jour_sous_champ_group()` | ✅ Retourne true           |
| Relecture admin après enregistrement      | ✅ Champ mis à jour            |
| `date_time_picker`                        | ✅ Formattée `Y-m-d H:i:s`     |
| Relationship vide                         | ✅ Ignoré proprement            |

---

## 📦 Bonnes pratiques

| Pratique                                                          | Pourquoi                              |
|------------------------------------------------------------------|---------------------------------------|
| Utiliser `data-champ="groupe.souschamp"`                         | Pour compatibilité front/back          |
| Traiter explicitement les champs imbriqués                       | Évite les bugs silencieux             |
| Ne jamais utiliser `field_key` pour un groupe dans `update_field()` | Cela échoue silencieusement         |
| Ajouter logs par champ dans `modifier_champ_*()`                 | Suivi fin et debug fiable             |

---

Fin de notice mise à jour ✅
