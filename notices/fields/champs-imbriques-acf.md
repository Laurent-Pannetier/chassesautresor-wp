# 🧩 Notice technique — Champs ACF imbriqués (Groupes / Sous-champs)

Cette notice décrit les bonnes pratiques pour la gestion **des champs ACF imbriqués**, c’est-à-dire les champs de type `group`, `repeater` ou `flexible content`, et leur manipulation en PHP et JavaScript.

---

## ✅ 1. Objectif

Permettre la **lecture**, **mise à jour** et **contrôle** des champs ACF imbriqués **sans casser les autres valeurs du groupe**.

Cas typiques :
* `chasse_infos_cout_points`
* `enigme_tentative_max`
* `enigme_acces_date` (type `date_time_picker`)

---

## ✅ 2. Structure des noms de champ

| Format JS (`data-champ`)                    | Format PHP (`update_field`)                        |
| ------------------------------------------- | -------------------------------------------------- |
| `chasse_infos_cout_points` | `chasse_infos_cout_points` |
| `enigme_tentative_max`     | `enigme_tentative_max`     |
| `enigme_acces_date`        | `enigme_acces_date`        |

**Important :**
- JS utilise la notation pointée (`groupe.champ`)
- PHP doit reconstruire le tableau imbriqué avec les bons noms
- ⚠️ ACF ne tolère pas les valeurs vides pour certains types (`relationship`, `post_object`, etc.) dans les groupes

---

## ✅ 3. JS — Envoi AJAX avec champ imbriqué

```js
modifierChampSimple('chasse_infos_date_debut', '2025-06-01', postId, 'chasse');
```

---

## ✅ 4. PHP — Enregistrement d'un champ

La fonction `mettre_a_jour_sous_champ_group()` a été supprimée. Les champs anciennement imbriqués sont maintenant stockés sous forme plate et s'enregistrent directement :

```php
// Exemple pour un champ de chasse
$ok = update_field('chasse_infos_cout_points', (int) $valeur, $post_id);
if ($ok) {
    wp_send_json_success(['champ' => 'chasse_infos_cout_points', 'valeur' => $valeur]);
}
wp_send_json_error('echec_mise_a_jour_final');
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
| Envoi AJAX sur champ                      | ✅ Format `nom_champ` en JS   |
| Traitement PHP `update_field()`           | ✅ Retourne true         |
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
