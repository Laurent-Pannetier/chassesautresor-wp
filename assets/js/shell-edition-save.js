// File d'attente des champs simples sur la page d'édition du dashboard.
// Les tiroirs, la suppression, le réordonnancement et la clôture de chasse restent immédiats.

(function () {
  const closureFields = [
    'champs_caches.chasse_cache_gagnants',
    'champs_caches.chasse_cache_date_decouverte',
  ];

  const fields = new Map();
  let dates = null;
  let dateBaseline = null;
  const baselines = new Map();
  const blocState = new Map();
  const galleryMarkup = new Map();
  const extras = new Map();
  let saving = false;
  let restoring = false;
  let allowUnload = false;
  let state = 'idle';
  let errorMessage = '';
  let button = null;
  let cancelButton = null;
  let statusNode = null;
  let savedTimer = 0;
  let imageAccessOpen = false;
  let imageTogglePending = false;

  function __(text) {
    if (window.wp && window.wp.i18n && typeof window.wp.i18n.__ === 'function') {
      return window.wp.i18n.__(text, 'chassesautresor-com');
    }
    return text;
  }

  function shell() {
    return document.querySelector('.shell-edition');
  }

  function panel() {
    return document.querySelector('.shell-edition .edition-panel');
  }

  function normalize(value) {
    if (Array.isArray(value)) {
      return JSON.stringify(value.map((item) => String(item)));
    }
    return value == null ? '' : String(value);
  }

  function sameDates(left, right) {
    if (!left || !right) return false;
    return left.date_debut === right.date_debut
      && left.date_fin === right.date_fin
      && String(left.illimitee) === String(right.illimitee)
      && String(left.debut_differee) === String(right.debut_differee);
  }

  function readDates() {
    const start = document.getElementById('chasse-date-debut');
    const end = document.getElementById('chasse-date-fin');
    if (!start || !end) return null;
    const toggleEnd = document.getElementById('date-fin-limitee');
    const toggleStart = document.getElementById('date-debut-differee');
    return {
      date_debut: start.value.trim(),
      date_fin: end.value.trim(),
      illimitee: toggleEnd?.checked ? 0 : 1,
      debut_differee: toggleStart?.checked ? 1 : 0,
    };
  }

  function storeBloc(bloc) {
    const champ = bloc?.dataset.champ;
    if (!champ) return;
    const display = bloc.querySelector(
      '.champ-affichage .champ-valeur, .champ-affichage h1, .champ-affichage h2, .champ-affichage p'
    );
    blocState.set(champ, {
      vide: bloc.classList.contains('champ-vide'),
      rempli: bloc.classList.contains('champ-rempli'),
      dataRempli: bloc.hasAttribute('data-rempli') ? (bloc.dataset.rempli || '') : null,
      displayText: display ? display.textContent : null,
    });
  }

  function applyBlocStates() {
    blocState.forEach((saved, champ) => {
      const bloc = document.querySelector(`.shell-edition [data-champ="${champ}"]`);
      if (!bloc) return;
      bloc.classList.toggle('champ-vide', saved.vide);
      bloc.classList.toggle('champ-rempli', saved.rempli);
      if (saved.dataRempli !== null) {
        bloc.dataset.rempli = saved.dataRempli;
      }
      if (saved.displayText === null) return;
      const display = bloc.querySelector(
        '.champ-affichage .champ-valeur, .champ-affichage h1, .champ-affichage h2, .champ-affichage p'
      );
      if (display) display.textContent = saved.displayText;
    });
  }

  function storeGallery(champ, gallery) {
    const attachments = gallery.querySelector('.acf-gallery-attachments');
    galleryMarkup.set(champ, attachments ? attachments.innerHTML : '');
  }

  function captureBaselines() {
    baselines.clear();
    blocState.clear();
    galleryMarkup.clear();
    document.querySelectorAll('.shell-edition [data-champ]').forEach((bloc) => {
      const champ = bloc.dataset.champ;
      if (!champ || baselines.has(champ)) return;
      storeBloc(bloc);

      const rich = bloc.querySelector('.champ-texte-editeur textarea.wp-editor-area');
      if (rich) {
        const editor = window.tinymce?.get(rich.id);
        const live = editor && typeof editor.isHidden === 'function' && !editor.isHidden();
        baselines.set(champ, normalize(live ? editor.getContent() : rich.value));
        return;
      }

      const gallery = bloc.querySelector('.champ-images-editeur');
      if (gallery) {
        baselines.set(champ, normalize(galleryIds(gallery)));
        storeGallery(champ, gallery);
        return;
      }

      const reward = bloc.querySelector('.champ-recompense-editeur');
      if (reward) {
        readReward(reward).forEach((value, name) => baselines.set(name, value));
        return;
      }

      const links = bloc.querySelector('.champ-liens-editeur');
      if (links) {
        baselines.set(champ, linksValue(links));
        return;
      }

      const bank = bloc.querySelector('.champ-coordonnees-editeur');
      if (bank) {
        baselines.set(champ, bankValue(bank));
        return;
      }

      const cost = bloc.querySelector('.champ-cout');
      if (cost) {
        baselines.set(champ, cost.value);
        return;
      }

      const radios = bloc.querySelectorAll('input[type="radio"]');
      if (radios.length) {
        const checked = bloc.querySelector('input[type="radio"]:checked');
        baselines.set(champ, checked ? checked.value : '');
        return;
      }

      const boxes = [...bloc.querySelectorAll('input[type="checkbox"]')];
      if (boxes.length > 1) {
        baselines.set(champ, normalize(boxes.filter((el) => el.checked).map((el) => el.value)));
        return;
      }

      const input = bloc.querySelector('input, textarea, select');
      if (!input) return;
      baselines.set(
        champ,
        input.type === 'checkbox' ? (input.checked ? '1' : '0') : input.value
      );
    });
    dateBaseline = readDates();
    reconcile();
  }

  function reconcile() {
    fields.forEach((value, champ) => {
      if (baselines.has(champ) && baselines.get(champ) === normalize(value)) {
        fields.delete(champ);
      }
    });
    if (dates && sameDates(dates, dateBaseline)) {
      dates = null;
    }
    render();
  }

  function extraDirty() {
    let dirty = false;
    extras.forEach((extra) => {
      if (extra.dirty) dirty = true;
    });
    return dirty;
  }

  function isDirty() {
    return fields.size > 0 || dates !== null || extraDirty();
  }

  function shouldQueue(cpt, champ, valeur) {
    if (!shell()) return false;
    if (cpt !== 'enigme' && cpt !== 'chasse' && cpt !== 'organisateur') return false;
    if (champ === 'logo_organisateur') return false;
    if (closureFields.includes(champ)) return false;
    if (champ === 'champs_caches.chasse_cache_statut' && valeur === 'termine') return false;
    return true;
  }

  function queue(champ, valeur) {
    if (restoring) return;
    const name = String(champ || '');
    if (name === '') return;
    if (baselines.has(name) && baselines.get(name) === normalize(valeur)) {
      fields.delete(name);
    } else {
      fields.set(name, valeur);
    }
    if (state !== 'saving') {
      errorMessage = '';
      state = isDirty() ? 'dirty' : 'idle';
    }
    render();
  }

  function queueDates(payload) {
    if (restoring || !payload) return;
    dates = sameDates(payload, dateBaseline) ? null : { ...payload };
    if (state !== 'saving') {
      errorMessage = '';
      state = isDirty() ? 'dirty' : 'idle';
    }
    render();
  }

  function hasField(champ) {
    return fields.has(champ);
  }

  function postId() {
    return panel()?.dataset.postId || '';
  }

  function cpt() {
    return panel()?.dataset.cpt || '';
  }

  function nonce() {
    if (cpt() === 'chasse') return window.CHP_CHASSE_DEFAUT?.nonce || '';
    if (cpt() === 'organisateur') return window.organisateurData?.nonce || '';
    return window.CHP_ENIGME_DEFAUT?.nonce || '';
  }

  function saveAction() {
    if (cpt() === 'chasse') return 'enregistrer_champs_chasse';
    if (cpt() === 'organisateur') return 'enregistrer_champs_organisateur';
    return 'enregistrer_champs_enigme';
  }

  function appendValue(params, name, value) {
    if (Array.isArray(value)) {
      if (value.length === 0) {
        params.append(name, '');
        return;
      }
      value.forEach((item) => {
        params.append(name + '[]', item == null ? '' : String(item));
      });
      return;
    }
    params.append(name, value == null ? '' : String(value));
  }

  function errorText(data) {
    if (data && typeof data === 'object' && data.message) {
      return String(data.message);
    }
    if (typeof data === 'string' && data !== '') {
      return data;
    }
    return __('Erreur lors de l’enregistrement.');
  }

  function rememberSaved(keys) {
    if (!keys.length) return;
    keys.forEach((key) => {
      if (key === 'dates') {
        dates = null;
        dateBaseline = readDates();
        return;
      }
      const value = fields.get(key);
      fields.delete(key);
      if (value === undefined) return;
      baselines.set(key, normalize(value));
      if (
        key === 'enigme_visuel_texte'
        || key === 'chasse_principale_description'
        || key === 'description_longue'
      ) {
        updateTexteExcerpt(key, value);
      }
      if (key.indexOf('chasse_infos_recompense_') === 0) {
        updateRewardSummary();
      }
      if (key === 'coordonnees_bancaires') {
        updateBankLabel(value);
      }
      if (
        (key === 'chasse_principale_liens' || key === 'liens_publics')
        && typeof updateTargetBlocks === 'function'
      ) {
        let parsed = [];
        try {
          parsed = JSON.parse(value);
        } catch (error) {
          parsed = [];
        }
        const card = document.querySelector(`.shell-edition [data-champ="${key}"]`);
        if (card) updateTargetBlocks(card, key, postId(), parsed);
      }
      if (typeof window.onChampSimpleMisAJour === 'function') {
        window.onChampSimpleMisAJour(key, postId(), value, cpt(), null);
      }
      const savedBloc = document.querySelector(`.shell-edition [data-champ="${key}"]`);
      if (savedBloc) {
        storeBloc(savedBloc);
        const gallery = savedBloc.querySelector('.champ-images-editeur');
        if (gallery) storeGallery(key, gallery);
      }
      if (key.indexOf('chasse_infos_recompense_') === 0) {
        const row = document.querySelector('.shell-edition .champ-recompense-editeur')?.closest('[data-champ]');
        if (row) storeBloc(row);
      }
    });
    if (cpt() !== 'chasse') return;
    if (typeof window.rafraichirStatutChasse === 'function') {
      window.rafraichirStatutChasse(postId());
    }
    if (typeof window.mettreAJourAffichageDateFin === 'function') {
      window.mettreAJourAffichageDateFin();
    }
  }

  function richTextField(area) {
    return area.closest('[data-champ]')?.dataset.champ || '';
  }

  function richTextValue(area) {
    const editor = window.tinymce?.get(area.id);
    const live = editor && typeof editor.isHidden === 'function' && !editor.isHidden();
    return live ? editor.getContent() : area.value;
  }

  function syncRichText() {
    document.querySelectorAll('.shell-edition .champ-texte-editeur textarea.wp-editor-area').forEach((area) => {
      const champ = richTextField(area);
      if (champ) queue(champ, richTextValue(area));
    });
  }

  function readReward(box) {
    return new Map([
      ['chasse_infos_recompense_titre', box.querySelector('#champ-recompense-titre')?.value ?? ''],
      ['chasse_infos_recompense_texte', box.querySelector('#champ-recompense-texte')?.value ?? ''],
      ['chasse_infos_recompense_valeur', box.querySelector('#champ-recompense-valeur')?.value ?? ''],
    ]);
  }

  function syncReward() {
    document.querySelectorAll('.shell-edition .champ-recompense-editeur').forEach((box) => {
      readReward(box).forEach((value, champ) => queue(champ, value));
    });
  }

  function linksValue(box) {
    if (typeof serializeLiensForm !== 'function' || typeof normaliserLiens !== 'function') return '[]';
    return JSON.stringify(normaliserLiens(serializeLiensForm(box), { trier: true }));
  }

  function linksField(box) {
    return box.closest('[data-champ]')?.dataset.champ || 'chasse_principale_liens';
  }

  function syncLinks() {
    document.querySelectorAll('.shell-edition .champ-liens-editeur').forEach((box) => {
      queue(linksField(box), linksValue(box));
    });
  }

  function bankParts(box) {
    const iban = (box.querySelector('#champ-iban')?.value || '').replace(/\s/g, '').toUpperCase();
    const bic = (box.querySelector('#champ-bic')?.value || '').trim().toUpperCase();
    return { iban, bic };
  }

  function bankError(parts) {
    const ibanOk = parts.iban === '' || /^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/.test(parts.iban);
    const bicOk = parts.bic === '' || /^[A-Z]{4}[A-Z]{2}[A-Z0-9]{2}([A-Z0-9]{3})?$/.test(parts.bic);
    if (!ibanOk) return __('Format IBAN invalide.');
    if (!bicOk) return __('Format BIC invalide.');
    return '';
  }

  function bankValue(box) {
    const parts = bankParts(box);
    return JSON.stringify({ iban: parts.iban, bic: parts.bic });
  }

  function syncBank() {
    let ok = true;
    document.querySelectorAll('.shell-edition .champ-coordonnees-editeur').forEach((box) => {
      const message = bankError(bankParts(box));
      if (message) {
        fields.delete('coordonnees_bancaires');
        errorMessage = message;
        ok = false;
        return;
      }
      queue('coordonnees_bancaires', bankValue(box));
    });
    return ok;
  }

  function syncOrganizerText() {
    let ok = true;
    document.querySelectorAll('.shell-edition [data-cpt="organisateur"][data-champ]').forEach((bloc) => {
      if (!ok) return;
      const champ = bloc.dataset.champ || '';
      if (
        champ === ''
        || champ === 'logo_organisateur'
        || champ === 'liens_publics'
        || champ === 'coordonnees_bancaires'
        || champ === 'description_longue'
      ) {
        return;
      }
      if (bloc.querySelector('.champ-texte-editeur, .champ-liens-editeur, .champ-coordonnees-editeur')) {
        return;
      }
      const input = bloc.querySelector('input.champ-input, textarea.champ-input');
      if (!input || input.disabled) return;
      const valeur = input.value.trim();
      if (champ === 'email_contact') {
        const valid = valeur === '' || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(valeur);
        if (!valid) {
          errorMessage = __('Adresse email invalide.');
          ok = false;
          return;
        }
      }
      if (champ === 'post_title' && valeur === '') {
        errorMessage = __('Le titre est obligatoire.');
        ok = false;
        return;
      }
      queue(champ, valeur);
    });
    return ok;
  }

  function updateBankLabel(raw) {
    const trigger = document.getElementById('ouvrir-coordonnees');
    if (!trigger) return;
    let data = {};
    try {
      data = JSON.parse(raw);
    } catch (error) {
      data = {};
    }
    const filled = !!(data.iban && data.bic);
    const label = filled ? trigger.dataset.labelEdit : trigger.dataset.labelAdd;
    const aria = filled ? trigger.dataset.ariaEdit : trigger.dataset.ariaAdd;
    if (label) trigger.textContent = label;
    if (aria) trigger.setAttribute('aria-label', aria);
  }

  function galleryIds(box) {
    return [...box.querySelectorAll('.acf-gallery-attachment input[type="hidden"]')]
      .map((input) => input.value)
      .filter((value) => value !== '');
  }

  function syncGallery() {
    document.querySelectorAll('.shell-edition .champ-images-editeur').forEach((box) => {
      queue('enigme_visuel_image', galleryIds(box));
    });
  }

  function updateImageSummary(urls) {
    const bloc = document.querySelector('.shell-edition .edition-row[data-champ="enigme_visuel_image"]');
    const button = bloc?.querySelector('.ouvrir-panneau-images');
    if (!bloc || !button) return;
    button.replaceChildren();
    if (!urls.length) {
      const span = document.createElement('span');
      span.className = 'champ-ajout-image';
      span.textContent = __('ajouter');
      button.append(span);
      bloc.classList.add('champ-vide');
      bloc.classList.remove('champ-rempli');
      bloc.dataset.rempli = '0';
      return;
    }
    urls.forEach((url) => {
      const img = document.createElement('img');
      img.src = url;
      img.alt = '';
      img.className = 'vignette-enigme';
      button.append(img);
    });
    bloc.classList.add('champ-rempli');
    bloc.classList.remove('champ-vide');
    bloc.dataset.rempli = '1';
  }

  function imageProtection(action) {
    const id = postId();
    if (!id) return Promise.resolve(false);
    const params = new URLSearchParams();
    params.set('action', action);
    params.set('nonce', window.CHP_ENIGME_DEFAUT?.nonce || '');
    params.set('post_id', id);
    return fetch(ajaxurl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: params,
      keepalive: true,
    })
      .then((response) => response.json())
      .then((data) => !!(data && data.success))
      .catch(() => false);
  }

  function refreshImageAccess() {
    const box = document.querySelector('.shell-edition .champ-images-editeur');
    if (!box || box.hidden) {
      imageAccessOpen = false;
      return;
    }
    imageProtection('desactiver_htaccess_enigme').then((ok) => {
      const current = document.querySelector('.shell-edition .champ-images-editeur');
      const stillOpen = !!(current && !current.hidden);
      imageAccessOpen = ok && stillOpen;
      if (ok && !stillOpen) {
        imageProtection('reactiver_htaccess_immediat_enigme');
      }
    });
  }

  function applyImageResult(data) {
    if (!Array.isArray(data?.vignettes)) return;
    updateImageSummary(data.vignettes);
    refreshImageAccess();
  }

  function snapshotImageBloc(keys) {
    if (!Array.isArray(keys) || !keys.includes('enigme_visuel_image')) return;
    const imageBloc = document.querySelector('.shell-edition [data-champ="enigme_visuel_image"]');
    if (!imageBloc) return;
    storeBloc(imageBloc);
    const gallery = imageBloc.querySelector('.champ-images-editeur');
    if (gallery) storeGallery('enigme_visuel_image', gallery);
  }

  function updateTexteExcerpt(champ, value) {
    const bloc = document.querySelector(`.shell-edition [data-champ="${champ}"]`);
    if (!bloc) return;
    const plain = String(value || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
    const words = plain.split(' ').filter(Boolean);
    const excerpt = words.slice(0, 25).join(' ');
    let summary = bloc.querySelector('.champ-texte-contenu');
    if (!excerpt) {
      summary?.remove();
      bloc.classList.add('champ-vide');
      bloc.classList.remove('champ-rempli');
      const edit = bloc.querySelector('.champ-modifier, .champ-ajouter');
      if (edit) {
        edit.classList.remove('champ-modifier');
        edit.classList.add('champ-ajouter');
        edit.textContent = __('ajouter');
        edit.removeAttribute('aria-label');
      }
      return;
    }
    if (!summary) {
      summary = document.createElement('span');
      summary.className = 'champ-texte-contenu';
      bloc.querySelector('.champ-texte')?.prepend(summary);
    }
    summary.textContent = words.length > 25 ? excerpt + '…' : excerpt;
    bloc.classList.add('champ-rempli');
    bloc.classList.remove('champ-vide');
    const add = bloc.querySelector('.champ-ajouter');
    if (add) {
      add.classList.remove('champ-ajouter');
      add.classList.add('champ-modifier');
      const verb = bloc.dataset.cpt === 'enigme' ? __('éditer') : __('modifier');
      add.textContent = verb;
      add.setAttribute('aria-label', verb);
    }
  }

  function updateRewardSummary() {
    const bloc = document.querySelector('.shell-edition .champ-recompense-editeur');
    const row = bloc?.closest('.edition-row');
    if (!bloc || !row) return;
    const titre = bloc.querySelector('#champ-recompense-titre')?.value.trim() || '';
    const texte = bloc.querySelector('#champ-recompense-texte')?.value.trim() || '';
    const valeur = Number(bloc.querySelector('#champ-recompense-valeur')?.value || 0);
    const summary = row.querySelector('.champ-texte-contenu');
    const complet = titre !== '' && texte !== '' && valeur > 0;
    row.classList.toggle('champ-rempli', complet);
    row.classList.toggle('champ-vide', !complet);
    if (!summary) return;
    summary.textContent = complet
      ? Math.round(valeur).toLocaleString('fr-FR') + ' € – ' + titre + ' – ' + texte.slice(0, 200)
      : '';
  }

  function saveFields() {
    if (fields.size === 0 && dates === null) return Promise.resolve(true);
    const currentCpt = cpt();
    const currentId = postId();
    if (!['enigme', 'chasse', 'organisateur'].includes(currentCpt) || !currentId) {
      errorMessage = __('Erreur lors de l’enregistrement.');
      return Promise.resolve(false);
    }

    const params = new URLSearchParams();
    params.set('action', saveAction());
    params.set('post_id', currentId);
    params.set('nonce', nonce());
    fields.forEach((value, champ) => {
      appendValue(params, 'champs[' + champ + ']', value);
    });
    if (dates) {
      params.set('dates[date_debut]', dates.date_debut);
      params.set('dates[date_fin]', dates.date_fin);
      params.set('dates[illimitee]', String(dates.illimitee));
      params.set('dates[debut_differee]', String(dates.debut_differee));
    }

    return fetch(ajaxurl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: params,
    })
      .then((response) => response.json())
      .then((res) => {
        if (!res || !res.success) {
          const saved = Array.isArray(res?.data?.enregistres) ? res.data.enregistres : [];
          rememberSaved(saved);
          applyImageResult(res?.data);
          snapshotImageBloc(saved);
          errorMessage = errorText(res?.data);
          return false;
        }

        const savedKeys = Array.isArray(res.data?.enregistres) ? res.data.enregistres : [];
        rememberSaved(savedKeys);
        applyImageResult(res.data);
        snapshotImageBloc(savedKeys);
        fields.clear();
        dates = null;
        captureBaselines();
        return true;
      })
      .catch(() => {
        errorMessage = __('Erreur lors de l’enregistrement.');
        return false;
      });
  }

  function saveExtras() {
    const pending = [...extras.entries()].filter(([, extra]) => extra.dirty);
    return pending.reduce((chain, [, extra]) => chain.then((ok) => {
      if (!ok) return false;
      return Promise.resolve()
        .then(() => extra.save())
        .then((saved) => {
          if (saved) {
            extra.dirty = false;
            return true;
          }
          if (!errorMessage) errorMessage = __('Erreur lors de l’enregistrement.');
          return false;
        })
        .catch((error) => {
          errorMessage = error instanceof Error && error.message
            ? error.message
            : __('Erreur lors de l’enregistrement.');
          return false;
        });
    }), Promise.resolve(true));
  }

  function save() {
    syncRichText();
    syncGallery();
    syncReward();
    syncLinks();
    if (!syncOrganizerText() || !syncBank()) {
      state = 'error';
      render();
      return Promise.resolve(false);
    }
    if (saving || !isDirty()) return Promise.resolve(false);

    saving = true;
    state = 'saving';
    errorMessage = '';
    render();

    return saveFields()
      .then((fieldsOk) => (fieldsOk ? saveExtras() : false))
      .then((ok) => {
        saving = false;
        if (!ok) {
          state = 'error';
          render();
          return false;
        }
        state = 'saved';
        errorMessage = '';
        render();
        window.clearTimeout(savedTimer);
        savedTimer = window.setTimeout(() => {
          if (!isDirty() && state === 'saved') {
            state = 'idle';
            render();
          }
        }, 2500);
        return true;
      });
  }

  function writeBank(box, raw) {
    let data = {};
    try {
      data = JSON.parse(raw);
    } catch (error) {
      data = {};
    }
    const iban = box.querySelector('#champ-iban');
    const bic = box.querySelector('#champ-bic');
    if (iban) iban.value = data.iban || '';
    if (bic) bic.value = data.bic || '';
  }

  function writeLinks(box, raw) {
    let rows = [];
    try {
      rows = JSON.parse(raw);
    } catch (error) {
      rows = [];
    }
    const byType = new Map();
    rows.forEach((row) => {
      if (row && row.type_de_lien) byType.set(row.type_de_lien, row.url_lien || '');
    });
    box.querySelectorAll('.champ-url-lien').forEach((input) => {
      const type = input.closest('[data-type]')?.dataset.type;
      input.value = type && byType.has(type) ? byType.get(type) : '';
      input.classList.remove('champ-erreur');
    });
  }

  function writeCost(bloc, input, value) {
    input.value = value;
    const paid = Number(value) > 0;
    const toggle = bloc.querySelector('input[type="checkbox"]');
    if (toggle) toggle.checked = paid;
    input.disabled = !paid;
    const actions = input.closest('.cout-points-actions');
    if (actions) actions.style.display = paid ? '' : 'none';
  }

  function writeDates() {
    if (!dateBaseline) return;
    const start = document.getElementById('chasse-date-debut');
    const end = document.getElementById('chasse-date-fin');
    const toggleEnd = document.getElementById('date-fin-limitee');
    const toggleStart = document.getElementById('date-debut-differee');
    if (toggleEnd) toggleEnd.checked = String(dateBaseline.illimitee) === '0';
    if (toggleStart) toggleStart.checked = String(dateBaseline.debut_differee) === '1';
    if (start) {
      start.value = dateBaseline.date_debut;
      start.dataset.previous = dateBaseline.date_debut;
    }
    if (end) {
      end.value = dateBaseline.date_fin;
      end.dataset.previous = dateBaseline.date_fin;
    }
    const startActions = start?.closest('.date-debut-actions');
    if (start && toggleStart && startActions) {
      const deferred = toggleStart.checked;
      startActions.style.display = deferred ? '' : 'none';
      start.disabled = !deferred;
    }
    const endActions = end?.closest('.date-fin-actions');
    if (end && toggleEnd && endActions) {
      const limited = toggleEnd.checked;
      endActions.style.display = limited ? '' : 'none';
      end.disabled = !limited;
    }
    if (typeof window.mettreAJourAffichageDateFin === 'function') {
      window.mettreAJourAffichageDateFin();
    }
    if (typeof window.mettreAJourCaracteristiqueDate === 'function') {
      window.mettreAJourCaracteristiqueDate();
    }
    if (typeof window.mettreAJourMessageDate === 'function') {
      window.mettreAJourMessageDate();
    }
    if (start && typeof window.onDateFieldUpdated === 'function') {
      window.onDateFieldUpdated(start, start.value.trim());
    }
    if (end && typeof window.onDateFieldUpdated === 'function') {
      window.onDateFieldUpdated(end, end.value.trim());
    }
  }

  function writeBaselines() {
    document.querySelectorAll('.shell-edition [data-champ]').forEach((bloc) => {
      const champ = bloc.dataset.champ;
      if (!champ) return;
      if (bloc.querySelector('#chasse-date-debut, #chasse-date-fin')) return;

      const rich = bloc.querySelector('.champ-texte-editeur textarea.wp-editor-area');
      if (rich && baselines.has(champ)) {
        const value = baselines.get(champ);
        rich.value = value;
        const editor = window.tinymce?.get(rich.id);
        const live = editor && typeof editor.isHidden === 'function' && !editor.isHidden();
        if (live && typeof editor.setContent === 'function') {
          editor.setContent(value);
        }
        return;
      }

      const gallery = bloc.querySelector('.champ-images-editeur');
      if (gallery) {
        if (galleryMarkup.has(champ)) {
          const attachments = gallery.querySelector('.acf-gallery-attachments');
          if (attachments) attachments.innerHTML = galleryMarkup.get(champ) || '';
        }
        return;
      }

      const reward = bloc.querySelector('.champ-recompense-editeur');
      if (reward) {
        const titre = reward.querySelector('#champ-recompense-titre');
        const texte = reward.querySelector('#champ-recompense-texte');
        const valeur = reward.querySelector('#champ-recompense-valeur');
        if (titre) titre.value = baselines.get('chasse_infos_recompense_titre') ?? '';
        if (texte) texte.value = baselines.get('chasse_infos_recompense_texte') ?? '';
        if (valeur) valeur.value = baselines.get('chasse_infos_recompense_valeur') ?? '';
        return;
      }

      const links = bloc.querySelector('.champ-liens-editeur');
      if (links && baselines.has(champ)) {
        writeLinks(links, baselines.get(champ));
        return;
      }

      const bank = bloc.querySelector('.champ-coordonnees-editeur');
      if (bank && baselines.has(champ)) {
        writeBank(bank, baselines.get(champ));
        return;
      }

      const cost = bloc.querySelector('.champ-cout');
      if (cost && baselines.has(champ)) {
        writeCost(bloc, cost, baselines.get(champ));
        return;
      }

      const radios = [...bloc.querySelectorAll('input[type="radio"]')];
      if (radios.length && baselines.has(champ)) {
        const value = baselines.get(champ);
        radios.forEach((radio) => {
          radio.checked = radio.value === value;
        });
        radios.find((radio) => radio.checked)?.dispatchEvent(new Event('change', { bubbles: true }));
        return;
      }

      const boxes = [...bloc.querySelectorAll('input[type="checkbox"]')];
      if (boxes.length > 1 && baselines.has(champ)) {
        let selected = [];
        try {
          selected = JSON.parse(baselines.get(champ));
        } catch (error) {
          selected = [];
        }
        boxes.forEach((box) => {
          box.checked = selected.includes(String(box.value));
        });
        return;
      }

      const input = bloc.querySelector('input, textarea, select');
      if (!input || !baselines.has(champ)) return;
      if (input.type === 'checkbox') {
        input.checked = baselines.get(champ) === '1';
        input.dispatchEvent(new Event('change', { bubbles: true }));
        return;
      }
      input.value = baselines.get(champ);
      if (input.tagName === 'SELECT') {
        input.dispatchEvent(new Event('change', { bubbles: true }));
      }
      if (champ === 'enigme_visuel_legende' && typeof window.mettreAJourLegendeEnigme === 'function') {
        window.mettreAJourLegendeEnigme(baselines.get(champ));
      }
    });
    writeDates();
    applyBlocStates();
  }

  function finishRestore() {
    fields.clear();
    dates = null;
    extras.forEach((extra) => {
      extra.dirty = false;
    });
    errorMessage = '';
    state = 'idle';
    render();
  }

  function restore() {
    if (saving || !isDirty()) return;
    restoring = true;
    writeBaselines();
    extras.forEach((extra) => {
      if (typeof extra.restore !== 'function') return;
      try {
        extra.restore();
      } catch (error) {
        errorMessage = '';
      }
    });
    finishRestore();
    window.requestAnimationFrame(() => {
      finishRestore();
      restoring = false;
      state = isDirty() ? 'dirty' : 'idle';
      render();
    });
  }

  function registerExtra(key, handlers) {
    if (!key || !handlers || typeof handlers.save !== 'function') return;
    extras.set(key, {
      dirty: false,
      save: handlers.save,
      restore: typeof handlers.restore === 'function' ? handlers.restore : () => {},
    });
  }

  function setExtra(key, dirty) {
    if (restoring) return;
    const extra = extras.get(key);
    if (!extra) return;
    extra.dirty = !!dirty;
    if (state !== 'saving') {
      errorMessage = '';
      state = isDirty() ? 'dirty' : 'idle';
    }
    render();
  }

  function hasExtra(key) {
    return !!extras.get(key)?.dirty;
  }

  function restoreExtra(key) {
    const extra = extras.get(key);
    if (!extra) return;
    restoring = true;
    try {
      extra.restore();
    } catch (error) {
      errorMessage = '';
    }
    extra.dirty = false;
    restoring = false;
    if (state !== 'saving') {
      errorMessage = '';
      state = isDirty() ? 'dirty' : 'idle';
    }
    render();
  }

  function render() {
    if (!button || !cancelButton || !statusNode) return;
    const dirty = isDirty();
    button.disabled = saving || !dirty;
    cancelButton.disabled = saving || !dirty;
    button.textContent = saving ? __('Enregistrement…') : __('Enregistrer');
    statusNode.classList.toggle('is-error', state === 'error');
    if (state === 'error') {
      statusNode.textContent = errorMessage;
      return;
    }
    if (state === 'saving') {
      statusNode.textContent = __('Enregistrement…');
      return;
    }
    if (state === 'saved') {
      statusNode.textContent = __('Enregistré');
      return;
    }
    if (isDirty()) {
      statusNode.textContent = __('Modifications non enregistrées');
      return;
    }
    statusNode.textContent = '';
  }

  function leaveMessage() {
    return __('Des modifications ne sont pas enregistrées. Quitter la page ?');
  }

  function confirmLeave(event) {
    if (allowUnload || !isDirty()) return true;
    if (!window.confirm(leaveMessage())) {
      event.preventDefault();
      event.stopPropagation();
      return false;
    }
    allowUnload = true;
    return true;
  }

  function onDocumentClick(event) {
    const link = event.target.closest('a[href]');
    if (link) {
      if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) {
        return;
      }
      const href = link.getAttribute('href') || '';
      if (href.startsWith('#') || href.startsWith('javascript:') || link.hasAttribute('download')) {
        return;
      }
      if (link.target === '_blank') return;
      const url = new URL(link.href, window.location.href);
      const sameDocument = url.origin === window.location.origin
        && url.pathname === window.location.pathname
        && url.search === window.location.search;
      if (sameDocument) return;
      confirmLeave(event);
      return;
    }

  }

  function mount() {
    const editionPanel = panel();
    if (!editionPanel || editionPanel.dataset.saveReady === '1') return;
    const kind = editionPanel.dataset.cpt;
    if (kind !== 'enigme' && kind !== 'chasse' && kind !== 'organisateur') return;

    const bar = document.createElement('div');
    bar.className = 'shell-edition-save';
    statusNode = document.createElement('p');
    statusNode.className = 'shell-edition-save__status';
    statusNode.setAttribute('role', 'status');
    statusNode.setAttribute('aria-live', 'polite');
    const actions = document.createElement('div');
    actions.className = 'shell-edition-save__actions';
    cancelButton = document.createElement('button');
    cancelButton.type = 'button';
    cancelButton.className = 'shell-edition-save__cancel';
    cancelButton.textContent = __('Annuler');
    cancelButton.addEventListener('click', () => {
      restore();
    });
    button = document.createElement('button');
    button.type = 'button';
    button.className = 'shell-edition-save__button';
    button.addEventListener('click', () => {
      save();
    });
    actions.append(cancelButton, button);
    bar.append(statusNode, actions);
    document.body.append(bar);

    editionPanel.dataset.saveReady = '1';
    captureBaselines();
    render();
  }

  window.addEventListener('beforeunload', (event) => {
    if (allowUnload || !isDirty()) return;
    event.preventDefault();
    event.returnValue = '';
  });
  document.addEventListener('click', onDocumentClick, true);

  function toggleTexteEnigme(trigger) {
    const bloc = trigger.closest('.edition-row');
    const box = bloc?.querySelector('.champ-texte-editeur');
    if (!box) return;
    const willOpen = box.hasAttribute('hidden');
    box.hidden = !willOpen;
    trigger.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
    if (!willOpen) return;
    window.requestAnimationFrame(() => {
      const area = box.querySelector('textarea.wp-editor-area');
      if (!area) return;
      const editor = window.tinymce?.get(area.id);
      if (editor) {
        const frame = editor.iframeElement;
        if (frame && frame.offsetHeight < 80) {
          frame.style.height = '220px';
        }
        return;
      }
      const wrap = box.querySelector('.acf-editor-wrap.delay');
      if (!wrap) return;
      wrap.dispatchEvent(new MouseEvent('mousedown', { bubbles: true, cancelable: true }));
    });
  }

  let richTextWatched = false;

  function watchRichText() {
    if (richTextWatched || !window.acf || typeof window.acf.addAction !== 'function') return;
    richTextWatched = true;
    window.acf.addAction('wysiwyg_tinymce_init', (editor) => {
      const area = editor?.id ? document.getElementById(editor.id) : null;
      const champ = area ? richTextField(area) : '';
      if (!area || !champ || !area.closest('.champ-texte-editeur') || area.dataset.watched === '1') return;
      area.dataset.watched = '1';
      baselines.set(champ, normalize(editor.getContent()));
      fields.delete(champ);
      editor.on('change keyup undo redo', () => {
        queue(champ, editor.getContent());
      });
      if (state !== 'saving') {
        state = isDirty() ? 'dirty' : 'idle';
      }
      render();
    });
  }

  document.addEventListener('click', (event) => {
    const trigger = event.target.closest('.shell-edition .ouvrir-panneau-description');
    if (!trigger) return;
    const row = trigger.closest('.edition-row');
    if (!row?.querySelector('.champ-texte-editeur')) return;
    event.preventDefault();
    event.stopPropagation();
    toggleTexteEnigme(trigger);
  }, true);

  function toggleBox(trigger, selector) {
    const bloc = trigger.closest('.edition-row, .dashboard-card');
    const box = bloc?.querySelector(selector);
    if (!box) return;
    const willOpen = box.hasAttribute('hidden');
    box.hidden = !willOpen;
    trigger.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
  }

  function watchInlineFields() {
    document.querySelectorAll('.shell-edition .champ-recompense-editeur').forEach((box) => {
      if (box.dataset.watched === '1') return;
      box.dataset.watched = '1';
      box.addEventListener('input', () => {
        readReward(box).forEach((value, champ) => queue(champ, value));
      });
    });
    document.querySelectorAll('.shell-edition .champ-liens-editeur').forEach((box) => {
      if (box.dataset.watched === '1') return;
      box.dataset.watched = '1';
      box.addEventListener('input', () => queue(linksField(box), linksValue(box)));
    });
    document.querySelectorAll('.shell-edition .champ-coordonnees-editeur').forEach((box) => {
      if (box.dataset.watched === '1') return;
      box.dataset.watched = '1';
      box.addEventListener('input', () => {
        const message = bankError(bankParts(box));
        if (message) {
          fields.delete('coordonnees_bancaires');
          errorMessage = message;
          state = 'error';
          render();
          return;
        }
        queue('coordonnees_bancaires', bankValue(box));
      });
    });
  }

  document.addEventListener('click', (event) => {
    const reward = event.target.closest('.shell-edition .ouvrir-panneau-recompense');
    if (reward?.closest('.edition-row')?.querySelector('.champ-recompense-editeur')) {
      event.preventDefault();
      event.stopPropagation();
      toggleBox(reward, '.champ-recompense-editeur');
      return;
    }
    const links = event.target.closest('.shell-edition .ouvrir-panneau-liens');
    if (links?.closest('.dashboard-card')?.querySelector('.champ-liens-editeur')) {
      event.preventDefault();
      event.stopPropagation();
      toggleBox(links, '.champ-liens-editeur');
      return;
    }
    const bankModal = event.target.closest('#ouvrir-coordonnees-modal');
    if (bankModal && document.querySelector('.shell-edition .champ-coordonnees-editeur')) {
      event.preventDefault();
      event.stopPropagation();
      const modal = document.getElementById('conversion-modal');
      if (modal) modal.style.display = 'none';
      document.querySelectorAll('.modal-overlay').forEach((overlay) => {
        overlay.style.display = 'none';
      });
      const box = document.querySelector('.shell-edition .champ-coordonnees-editeur');
      const trigger = document.getElementById('ouvrir-coordonnees');
      if (box) {
        box.hidden = false;
        trigger?.setAttribute('aria-expanded', 'true');
        box.querySelector('#champ-iban')?.focus();
      }
      return;
    }
    const bank = event.target.closest('.shell-edition #ouvrir-coordonnees');
    if (bank?.closest('.dashboard-card')?.querySelector('.champ-coordonnees-editeur')) {
      event.preventDefault();
      event.stopPropagation();
      toggleBox(bank, '.champ-coordonnees-editeur');
    }
  }, true);

  function toggleImages(trigger) {
    const row = trigger.closest('.edition-row');
    const box = row?.querySelector('.champ-images-editeur');
    if (!box || imageTogglePending) return;
    const feedback = row.querySelector('.champ-feedback');
    const willOpen = box.hasAttribute('hidden');
    if (!willOpen) {
      box.hidden = true;
      trigger.setAttribute('aria-expanded', 'false');
      if (!imageAccessOpen) return;
      imageAccessOpen = false;
      imageProtection('reactiver_htaccess_immediat_enigme');
      return;
    }

    imageTogglePending = true;
    imageProtection('desactiver_htaccess_enigme').then((ok) => {
      imageTogglePending = false;
      if (!ok) {
        if (feedback) feedback.textContent = __('Impossible d’ouvrir les illustrations.');
        return;
      }
      if (feedback) feedback.textContent = '';
      imageAccessOpen = true;
      box.hidden = false;
      trigger.setAttribute('aria-expanded', 'true');
      window.requestAnimationFrame(() => {
        window.dispatchEvent(new Event('resize'));
        if (window.acf && typeof window.acf.doAction === 'function') {
          window.acf.doAction('resize');
        }
      });
    });
  }

  function watchGallery() {
    document.querySelectorAll('.shell-edition .champ-images-editeur').forEach((box) => {
      if (box.dataset.watched === '1') return;
      box.dataset.watched = '1';
      const sync = () => queue('enigme_visuel_image', galleryIds(box));
      box.addEventListener('change', sync);
      const observer = new MutationObserver(sync);
      observer.observe(box, { childList: true, subtree: true });
    });
  }

  document.addEventListener('click', (event) => {
    const trigger = event.target.closest('.shell-edition .ouvrir-panneau-images');
    if (!trigger) return;
    const row = trigger.closest('.edition-row');
    if (!row?.querySelector('.champ-images-editeur')) return;
    event.preventDefault();
    event.stopPropagation();
    toggleImages(trigger);
  }, true);

  window.addEventListener('pagehide', () => {
    if (!imageAccessOpen) return;
    imageAccessOpen = false;
    imageProtection('reactiver_htaccess_immediat_enigme');
  });

  window.ctaShellEditionSave = {
    shouldQueue,
    queue,
    queueDates,
    hasField,
    isDirty,
    save,
    restore,
    allowLeave() {
      allowUnload = true;
    },
    registerExtra,
    setExtra,
    hasExtra,
    restoreExtra,
    rebase() {
      fields.clear();
      dates = null;
      captureBaselines();
      if (state !== 'saving') {
        state = isDirty() ? 'dirty' : 'idle';
        errorMessage = '';
      }
      render();
    },
  };

  function start() {
    watchRichText();
    watchGallery();
    watchInlineFields();
    mount();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();
