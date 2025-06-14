# 🧩 Notice technique – Champ ACF `image` en édition frontale

Ce document décrit les bonnes pratiques pour gérer un champ ACF de type `image` en édition frontale, incluant les structures HTML, les appels AJAX, et les erreurs à éviter (notamment les champs imbriqués dans un groupe ACF).

---

## ✅ 1. Structure HTML attendue

```html
<div class="champ-enigme champ-img"
     data-champ="enigme_visuel_image"
     data-cpt="enigme"
     data-post-id="<?= esc_attr($post_id); ?>">

  <div class="champ-affichage">
    <button type="button"
            class="champ-modifier"
            aria-label="Modifier l’image"
            data-champ="enigme_visuel_image"
            data-cpt="enigme"
            data-post-id="<?= esc_attr($post_id); ?>">
      <img src="<?= esc_url($image_url); ?>"
           alt="Image"
           style="width:100%; height:auto;" />
      <span class="icone-modif">✏️</span>
    </button>
  </div>

  <input type="hidden" class="champ-input" value="<?= esc_attr($image_id); ?>">
  <div class="champ-feedback"></div>
</div>
```

---

## ✅ 2. JavaScript – `initChampImage()`

L’initialisation se fait automatiquement si `class="champ-img"` est présent. Vérifiez :

```js
const action = (cpt === 'chasse') ? 'modifier_champ_chasse' :
               (cpt === 'enigme') ? 'modifier_champ_enigme' :
               'modifier_champ_organisateur';
```

Et que l’appel AJAX est bien envoyé avec :

```js
fetch('/wp-admin/admin-ajax.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  body: new URLSearchParams({
    action,
    champ,
    valeur: id,
    post_id: postId
  })
})
```

---

## ✅ 3. PHP – Traitement dans `modifier_champ_enigme()`

Aucun traitement spécial n’est requis pour un champ `image` non imbriqué.

```php
$ok = update_field($champ, is_numeric($valeur) ? (int) $valeur : $valeur, $post_id);
```

Puis vérification :

```php
$valeur_meta = get_post_meta($post_id, $champ, true);
$valeur_comparee = stripslashes_deep($valeur);

if ($ok || trim((string) $valeur_meta) === trim((string) $valeur_comparee)) {
    wp_send_json_success([
        'champ'  => $champ,
        'valeur' => $valeur
    ]);
}
```

---

## ❌ Erreurs fréquentes à éviter

### 1. ❌ Ne pas imbriquer les champs `image` dans un `group` ACF si l’objectif est l’édition frontale

Les groupes ACF sont souvent masqués ou non chargés en AJAX si :
- le champ est vide
- ou conditionnel
- ou affiché uniquement en backend

### 2. ❌ Ne pas supposer que `get_field()` renvoie un ID

Un champ image ACF peut renvoyer :
- une URL (`string`)
- un ID (`int`)
- un tableau (`array`)

Réglage : ACF > Type d’image > "Retourner le format" = `ID` (recommandé)

---

## ✅ Recommandé : récupération sûre de l’image

```php
$image_raw = get_field('enigme_visuel_image', $post_id);
$image_url = '';

if (is_array($image_raw) && isset($image_raw['ID'])) {
    $image_url = wp_get_attachment_image_url($image_raw['ID'], 'large');
} elseif (is_numeric($image_raw)) {
    $image_url = wp_get_attachment_image_url((int)$image_raw, 'large');
} elseif (filter_var($image_raw, FILTER_VALIDATE_URL)) {
    $image_url = $image_raw;
}

if (!$image_url) {
    $image_url = wp_get_attachment_image_url(3925, 'large'); // fallback
}
```

---

## 🧪 Vérification finale

- ✅ Le champ est bien modifiable depuis le bouton ✏️
- ✅ L’URL est bien mise à jour après sélection
- ✅ L’image persiste après rechargement de la page
- ✅ Pas de warning PHP, ni d’image vide (`src=""`)

---

## 📦 Bonnes pratiques générales

| Bonne pratique                  | Description |
|---------------------------------|-------------|
| Toujours utiliser un champ `image` à la racine | Pour simplifier l’édition AJAX |
| Retourner `ID` plutôt que `URL` | Pour cohérence et compatibilité |
| Côté PHP, tester tous les formats | array, int, string |
| Utiliser `esc_url()` dans le `src` | Pour éviter les failles XSS |

---