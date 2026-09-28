# Pager par défaut

Composant pour la navigation dans les tableaux paginés.

## Usage

```php
echo cta_render_pager(1, 5, 'points-history-pager');
```

La fonction génère le HTML suivant :

```html
<nav class="pager" data-current="1" data-total="5">
    <button class="etiquette pager-first" type="button">«</button>
    <button class="etiquette pager-prev" type="button">‹</button>
    <span class="pager-info">
        <select class="etiquette pager-select">
            <option value="1" selected>1</option>
            <!-- … -->
        </select>
        / 5
    </span>
    <button class="etiquette pager-next" type="button">›</button>
    <button class="etiquette pager-last" type="button">»</button>
</nav>
```

Chaque changement déclenche l’événement `pager:change` sur l’élément `<nav>` contenant,
avec le numéro de page dans `event.detail.page`.
