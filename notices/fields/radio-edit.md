# 📻 Notice – Champ ACF `radio` en édition frontale

Cette fiche résume la structure conseillée pour gérer un champ radio dans les panneaux d'édition.

## Exemple minimal

```php
<li class="champ-cpt champ-radio"
    data-champ="mon_champ_radio"
    data-cpt="mon_cpt"
    data-post-id="<?= esc_attr($post_id); ?>">

  <?php $valeur = get_field('mon_champ_radio', $post_id); ?>
  <label><input type="radio" name="mon_champ_radio" value="option1" <?= $valeur === 'option1' ? 'checked' : '' ?>> Option 1</label>
  <label><input type="radio" name="mon_champ_radio" value="option2" <?= $valeur === 'option2' ? 'checked' : '' ?>> Option 2</label>

  <div class="champ-feedback"></div>
</li>
```

Le JS peut se baser sur `modifierChampSimple()` pour envoyer la valeur sélectionnée via AJAX.
