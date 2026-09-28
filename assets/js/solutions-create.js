(function () {
  function openModal(btn) {
    var overlay = document.createElement('div');
    overlay.className = 'solution-modal-overlay';
    var isEdit = !!btn.dataset.solutionId;
    var titre = isEdit
      ? solutionsCreate.texts.editTitre + ' ' + (btn.dataset.solutionTitre || '')
      : solutionsCreate.texts.addTitre;
    var objetTypeLabel = btn.dataset.objetType === 'chasse'
      ? solutionsCreate.texts.laChasse
      : solutionsCreate.texts.lenigme;
    var needEnigme = btn.dataset.chasseId && !btn.dataset.objetId;
    var existingFileId = btn.dataset.solutionFichierId || '';
    var existingFileUrl = btn.dataset.solutionFichierUrl || '';
    var enigmeField;
    if (needEnigme) {
      enigmeField =
        '<p><label>' +
        solutionsCreate.texts.enigmeLabel +
        '<br><select name="solution_enigme_linked"><option value="">' +
        solutionsCreate.texts.loading +
        '</option></select></label></p>';
    } else if (btn.dataset.objetType === 'enigme') {
      enigmeField =
        '<input type="hidden" name="solution_enigme_linked" value="' +
        (btn.dataset.objetId || '') +
        '" />';
    } else {
      enigmeField = '';
    }
    var initialName = '';
    if (existingFileUrl) {
      initialName = '<a href="' +
        existingFileUrl +
        '" target="_blank" rel="noopener noreferrer">' +
        existingFileUrl.split('/').pop() +
        '</a>';
    }
    var fileField =
      '<p><label>' +
      solutionsCreate.texts.fichier +
      '<br><button type="button" class="solution-file-btn bouton-cta">' +
      solutionsCreate.texts.chooseFile +
      '</button> <span class="solution-file-name">' +
      (initialName || solutionsCreate.texts.noFile) +
      '</span> <button type="button" class="solution-file-remove champ-modifier' +
      (initialName ? '' : '" style="display:none"') +
      '">' +
      solutionsCreate.texts.removeFile +
      '</button>' +
      '<input type="file" name="solution_fichier" accept="application/pdf" style="display:none" /></label>';
    if (isEdit) {
      fileField +=
        '<input type="hidden" name="solution_fichier" value="' +
        existingFileId +
        '" />';
    }
    fileField += '</p>';

    var delaiValue = btn.dataset.solutionDelai;
    var heureValue = btn.dataset.solutionHeure;
    if (heureValue) {
      var match = heureValue.match(/^(\d{1,2})(?:h|:)(\d{2})/);
      if (match) {
        heureValue = match[1].padStart(2, '0') + ':' + match[2];
      } else {
        heureValue = '';
      }
    }
    if (isEdit) {
      var now = new Date();
      var pad = function (n) {
        return String(n).padStart(2, '0');
      };
      if (delaiValue === undefined || delaiValue === '') {
        delaiValue = 0;
      }
      if (!heureValue) {
        heureValue = pad(now.getHours()) + ':' + pad(now.getMinutes());
      }
    } else {
      delaiValue = delaiValue || 0;
      heureValue = heureValue || '18:00';
    }

    overlay.innerHTML = `
      <div class="solution-modal">
        <div class="solution-modal-header">
          <h2>${titre}</h2>
          <p>${solutionsCreate.texts.lieeA} ${objetTypeLabel} : <span class="objet-titre">${btn.dataset.objetTitre || ''}</span></p>
        </div>
        <button type="button" class="solution-modal-close" aria-label="${solutionsCreate.texts.close}">×</button>
        <form class="solution-modal-form">
          <input type="hidden" name="action" value="${isEdit ? 'modifier_solution_modal' : 'creer_solution_modal'}" />
          <input type="hidden" name="objet_type" value="${btn.dataset.objetType || ''}" />
          ${enigmeField}
          <input type="hidden" name="objet_id" value="${btn.dataset.objetId || ''}" />
          ${isEdit ? '<input type="hidden" name="solution_id" value="' + btn.dataset.solutionId + '" />' : ''}
          <p><label>${solutionsCreate.texts.contenu}<br><textarea name="solution_explication">${btn.dataset.solutionExplication || ''}</textarea></label></p>
          ${fileField}
          <p><label>${solutionsCreate.texts.disponibilite}<br><select name="solution_disponibilite">
            <option value="fin_chasse">${solutionsCreate.texts.finChasse}</option>
            <option value="differee">${solutionsCreate.texts.differee}</option>
          </select></label></p>
          <p class="delai-wrapper" style="display:none;">
            <input type="number" name="solution_decalage_jours" min="0" value="${delaiValue}" /> ${solutionsCreate.texts.days}
            <input type="time" name="solution_heure_publication" value="${heureValue}" />
          </p>
          <div class="solution-modal-footer"><span class="solution-state-message"></span><button type="submit" class="solution-modal-validate bouton-cta">${solutionsCreate.texts.valider}</button></div>
        </form>
      </div>`;
    document.body.appendChild(overlay);

    function close() { overlay.remove(); }
    overlay.querySelector('.solution-modal-close').addEventListener('click', close);

    var form = overlay.querySelector('.solution-modal-form');
    var validateBtn = overlay.querySelector('.solution-modal-validate');
    var stateMessage = overlay.querySelector('.solution-state-message');
    var selectDispo = overlay.querySelector('select[name="solution_disponibilite"]');
    var delaiWrapper = overlay.querySelector('.delai-wrapper');
    var delaiInput = overlay.querySelector('input[name="solution_decalage_jours"]');
    var heureInput = overlay.querySelector('input[name="solution_heure_publication"]');
    var explicationInput = overlay.querySelector('textarea[name="solution_explication"]');
    var fichierInput = overlay.querySelector('input[name="solution_fichier"][type="file"]');
    var existingFileInput = overlay.querySelector('input[name="solution_fichier"][type="hidden"]');
    var fichierBtn = overlay.querySelector('.solution-file-btn');
    var fichierName = overlay.querySelector('.solution-file-name');
    var removeBtn = overlay.querySelector('.solution-file-remove');
    if (btn.dataset.solutionDisponibilite === 'differee') {
      selectDispo.value = 'differee';
      delaiWrapper.style.display = '';
    }
    selectDispo.addEventListener('change', function () {
      delaiWrapper.style.display = this.value === 'differee' ? '' : 'none';
      refreshState();
    });
    delaiInput.addEventListener('input', refreshState);
    heureInput.addEventListener('input', refreshState);
    explicationInput.addEventListener('input', refreshState);
    if (fichierBtn && fichierInput) {
      fichierBtn.addEventListener('click', function () {
        fichierInput.click();
      });
      fichierInput.addEventListener('change', function () {
        if (existingFileInput) {
          existingFileInput.value = '';
        }
        existingFileId = '';
        fichierName.textContent = fichierInput.files.length
          ? fichierInput.files[0].name
          : solutionsCreate.texts.noFile;
        if (removeBtn) {
          removeBtn.style.display = fichierInput.files.length ? '' : 'none';
        }
        refreshState();
      });
    }
    if (removeBtn) {
      removeBtn.addEventListener('click', function () {
        fichierInput.value = '';
        if (existingFileInput) {
          existingFileInput.value = '';
        }
        existingFileId = '';
        fichierName.textContent = solutionsCreate.texts.noFile;
        removeBtn.style.display = 'none';
        refreshState();
      });
    }

    if (needEnigme) {
      var selectEnigme = overlay.querySelector('select[name="solution_enigme_linked"]');
      var hiddenObjet = overlay.querySelector('input[name="objet_id"]');
      var titleSpan = overlay.querySelector('.objet-titre');
      var fd = new FormData();
      fd.append('action', 'chasse_lister_enigmes');
      fd.append('chasse_id', btn.dataset.chasseId || '');
      fd.append('sans_solution', '1');
      fetch(solutionsCreate.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          selectEnigme.innerHTML = '';
          if (!res.success || !res.data.enigmes.length) {
            var opt = document.createElement('option');
            opt.value = '';
            opt.textContent = solutionsCreate.texts.enigmePlaceholder;
            selectEnigme.appendChild(opt);
            btn.dataset.objetId = '';
            btn.dataset.objetTitre = '';
            hiddenObjet.value = '';
            titleSpan.textContent = '';
            refreshState();
            return;
          }
          res.data.enigmes.forEach(function (enigme) {
            var opt = document.createElement('option');
            opt.value = enigme.id;
            opt.textContent = enigme.title;
            selectEnigme.appendChild(opt);
          });
          var def = btn.dataset.defaultEnigme || btn.dataset.objetId;
          if (def) selectEnigme.value = def;
          if (!selectEnigme.value) selectEnigme.value = selectEnigme.options[0].value;
          var sel = selectEnigme.options[selectEnigme.selectedIndex];
          btn.dataset.objetId = selectEnigme.value;
          btn.dataset.objetTitre = sel ? sel.text : '';
          hiddenObjet.value = selectEnigme.value;
          titleSpan.textContent = sel ? sel.text : '';
          refreshState();
        });
      selectEnigme.addEventListener('change', function () {
        var opt = selectEnigme.options[selectEnigme.selectedIndex];
        btn.dataset.objetId = selectEnigme.value;
        btn.dataset.objetTitre = opt ? opt.text : '';
        hiddenObjet.value = selectEnigme.value;
        titleSpan.textContent = opt ? opt.text : '';
        refreshState();
      });
    }

    function refreshState() {
      var riddleOk = !needEnigme || (btn.dataset.objetId && overlay.querySelector('input[name="objet_id"]').value);
      var explication = explicationInput.value.trim();
      var hasFile =
        (fichierInput && fichierInput.files && fichierInput.files.length > 0) ||
        existingFileId;
      var state = 'desactive';
      var message = '';
      if (!riddleOk) {
        message = solutionsCreate.texts.needEnigme;
      } else if (!explication && !hasFile) {
        message = solutionsCreate.texts.needContent;
      } else {
        state = 'accessible';
        if (selectDispo.value === 'differee') {
          var delaiVide = delaiInput.value === '';
          var heureVide = heureInput.value === '';
          if (delaiVide || heureVide) {
            state = 'desactive';
            message = solutionsCreate.texts.needDate;
          } else {
            var delai = parseInt(delaiInput.value, 10) || 0;
            var heure = heureInput.value;
            var now = new Date();
            var target = new Date(now);
            var parts = heure.split(':');
            target.setDate(target.getDate() + delai);
            target.setHours(parseInt(parts[0], 10), parseInt(parts[1], 10), 0, 0);
            if (target > now) {
              state = 'programme';
            }
          }
        }
      }
      validateBtn.disabled = state === 'desactive';
      stateMessage.textContent = message;
    }

    refreshState();

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (needEnigme && (!btn.dataset.objetId || !overlay.querySelector('input[name="objet_id"]').value)) {
        stateMessage.textContent = solutionsCreate.texts.needEnigme;
        return;
      }
      var data = new FormData(form);
      validateBtn.disabled = true;
      stateMessage.textContent = solutionsCreate.texts.loading;
      fetch(solutionsCreate.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          validateBtn.disabled = false;
          if (res.success) {
            stateMessage.textContent = solutionsCreate.texts.success;
            window.dispatchEvent(new Event('solution-created'));
            if (window.rafraichirCarteSolutions) {
              window.rafraichirCarteSolutions();
            }
            setTimeout(function () {
              close();
              var anchorId = 'chasse-section-solutions';
              var anchor = document.getElementById(anchorId);
              if (!anchor) {
                var list = document.querySelector('.liste-solutions');
                if (list) {
                  anchor = document.createElement('span');
                  anchor.id = anchorId;
                  var heading = list.previousElementSibling;
                  if (heading) {
                    heading.parentNode.insertBefore(anchor, heading);
                  } else {
                    list.parentNode.insertBefore(anchor, list);
                  }
                }
              }
              if (anchor) {
                anchor.scrollIntoView({ behavior: 'smooth' });
              }
            }, 500);
          } else {
            stateMessage.textContent = res.data || solutionsCreate.texts.ajaxError;
          }
        })
        .catch(function () {
          validateBtn.disabled = false;
          stateMessage.textContent = solutionsCreate.texts.ajaxError;
        });
    });
  }

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.ajouter-solution, .badge-action.edit');
    if (!btn || btn.classList.contains('disabled') || btn.disabled) return;
    if (btn.classList.contains('badge-action') && !btn.closest('.liste-solutions')) {
      return;
    }
    e.preventDefault();
    openModal(btn);
  });
})();
