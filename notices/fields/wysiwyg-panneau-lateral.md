# 🧩 Notice – Édition d’un champ ACF WYSIWYG dans un panneau latéral

Ce document décrit la mise en place d’un champ ACF de type `wysiwyg` éditable dans un panneau latéral dédié, utilisé notamment pour des champs comme `enigme_visuel_texte` ou `chasse_principale_description`.

---

## ✅ Objectif

- Le champ est **édité dans un panneau latéral** (`.panneau-lateral-large`)
- Le champ est **un wysiwyg ACF natif**, édité via `acf_form()`
- L’enregistrement se fait **via un rechargement**, pas en AJAX
- L’affichage de la valeur est effectué dans le template public (`single-*.php`)

---

## 🔧 Structure recommandée

### 1. `acf_form_head()` requis dans le template parent (ex: `single-enigme.php`)

```php
acf_form_head(); // doit être placé avant get_header()
```

---

### 2. Inclusion du panneau si édition active

```php
if ($edition_active) {
  get_template_part('template-parts/panneaux/enigme-edition-description', null, [
    'enigme_id' => $enigme_id
  ]);
}
```

---

### 3. Fichier `panneau-description-enigme.php`

```php
<?php
defined('ABSPATH') || exit;

$enigme_id = $args['enigme_id'] ?? null;
if (!$enigme_id || get_post_type($enigme_id) !== 'enigme') return;
?>

<div id="panneau-description-enigme" class="panneau-lateral-liens panneau-lateral-large" aria-hidden="true">
  <div class="panneau-lateral__contenu">

    <div class="panneau-lateral__header">
      <button type="button" class="panneau-fermer" aria-label="Fermer le panneau">✖</button>
      <h2>Modifier le texte de l’énigme</h2>
    </div>

    <?php
    acf_form([
      'post_id'             => $enigme_id,
      'fields'              => ['enigme_visuel_texte'],
      'form'                => true,
      'submit_value'        => '💾 Enregistrer',
      'html_submit_button'  => '<div class="panneau-lateral__actions"><button type="submit" class="bouton-enregistrer-description bouton-enregistrer-liens">%s</button></div>',
      'html_before_fields'  => '<div class="champ-wrapper">',
      'html_after_fields'   => '</div>',
      'return'              => get_permalink() . '#texte-enigme',
      'updated_message'     => __('Texte de l’énigme mis à jour.', 'chassesautresor')
    ]);
    ?>
  </div>
</div>
```

---

## 🧪 JS : déclencheur du panneau

Dans `enigme-edit.js` :

```js
document.addEventListener('click', (e) => {
  const btn = e.target.closest('.ouvrir-panneau-description');
  if (!btn || btn.dataset.cpt !== 'enigme') return;

  const panneau = document.getElementById('panneau-description-enigme');
  if (!panneau) return;

  document.querySelectorAll('.panneau-lateral.ouvert, .panneau-lateral-liens.ouvert').forEach((p) => {
    p.classList.remove('ouvert');
    p.setAttribute('aria-hidden', 'true');
  });

  panneau.classList.add('ouvert');
  document.body.classList.add('panneau-ouvert');
  panneau.setAttribute('aria-hidden', 'false');
});
```

---

## 📄 Affichage de la valeur dans `single-enigme.php`

```php
<?php
$texte_intro = get_field('enigme_visuel_texte', $enigme_id);
if (!empty($texte_intro)) :
?>
  <div id="texte-enigme" class="enigme-texte wysiwyg"><?= wp_kses_post($texte_intro); ?></div>
<?php endif; ?>
```

---

## 🛠️ Rappels importants

| Élément                | Obligatoire |
|------------------------|-------------|
| `acf_form_head()`      | ✅ oui       |
| `acf_form()` dans le panneau | ✅ oui       |
| JS pour ouvrir le panneau | ✅ oui       |
| Enregistrement AJAX    | ❌ non (rechargement) |
| Affichage dans single  | ✅ oui       |

---