(function () {
  var input = document.getElementById('admin-rewind');
  var suggestBox = document.getElementById('admin-suggest');
  if (input && suggestBox) {
    var suggestTimer = null;
    var suggestSeq = 0;
    var suggestItems = [];
    var suggestActive = -1;

    function setSuggestOpen(open) {
      suggestBox.hidden = !open;
      input.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (!open) {
        suggestActive = -1;
      }
    }

    function renderSuggest(items) {
      suggestItems = items || [];
      suggestActive = -1;
      if (!suggestItems.length) {
        suggestBox.innerHTML = '<div class="admin-suggest__empty">Ничего не найдено</div>';
        setSuggestOpen(true);
        return;
      }
      suggestBox.innerHTML = suggestItems.map(function (item, idx) {
        var label = item.label_html || item.label || '';
        var meta = item.meta ? '<span class="admin-suggest__meta">' + String(item.meta).replace(/</g, '&lt;') + '</span>' : '';
        var thumb = '';
        if (item.thumb) {
          thumb = '<span class="admin-suggest__thumb"><img src="'
            + String(item.thumb).replace(/"/g, '&quot;')
            + '" alt="" loading="lazy"></span>';
        } else {
          thumb = '<span class="admin-suggest__thumb admin-suggest__thumb--empty" aria-hidden="true"></span>';
        }
        return '<a class="admin-suggest__item" role="option" id="admin-suggest-opt-' + idx + '" href="'
          + String(item.url || '#').replace(/"/g, '&quot;')
          + '" data-idx="' + idx + '">' + thumb
          + '<span class="admin-suggest__text"><span class="admin-suggest__label">' + label + '</span>' + meta + '</span></a>';
      }).join('');
      setSuggestOpen(true);
    }

    function setActiveSuggest(idx) {
      var nodes = suggestBox.querySelectorAll('.admin-suggest__item');
      if (!nodes.length) return;
      if (idx < 0) idx = nodes.length - 1;
      if (idx >= nodes.length) idx = 0;
      suggestActive = idx;
      nodes.forEach(function (node, i) {
        node.classList.toggle('is-active', i === idx);
      });
      input.setAttribute('aria-activedescendant', 'admin-suggest-opt-' + idx);
      nodes[idx].scrollIntoView({ block: 'nearest' });
    }

    function runSuggest(q) {
      var seq = ++suggestSeq;
      if (q.length < 2) {
        suggestBox.innerHTML = '';
        setSuggestOpen(false);
        return;
      }
      fetch('/admin2/suggest.php?q=' + encodeURIComponent(q), { credentials: 'same-origin' })
        .then(function (response) {
          return response.json().then(function (payload) {
            return { okHttp: response.ok, body: payload };
          });
        })
        .then(function (result) {
          if (seq !== suggestSeq) return;
          if (!result.okHttp || !result.body || !result.body.ok) {
            throw new Error((result.body && result.body.error) || 'Ошибка поиска');
          }
          renderSuggest(result.body.items || []);
        })
        .catch(function () {
          if (seq !== suggestSeq) return;
          suggestBox.innerHTML = '<div class="admin-suggest__empty">Ошибка поиска</div>';
          setSuggestOpen(true);
        });
    }

    input.addEventListener('input', function () {
      var q = input.value.trim();
      window.clearTimeout(suggestTimer);
      suggestTimer = window.setTimeout(function () {
        runSuggest(q);
      }, 180);
    });

    input.addEventListener('keydown', function (ev) {
      if (suggestBox.hidden) return;
      if (ev.key === 'ArrowDown') {
        ev.preventDefault();
        setActiveSuggest(suggestActive + 1);
      } else if (ev.key === 'ArrowUp') {
        ev.preventDefault();
        setActiveSuggest(suggestActive - 1);
      } else if (ev.key === 'Enter' && suggestActive >= 0 && suggestItems[suggestActive]) {
        ev.preventDefault();
        window.location.href = suggestItems[suggestActive].url;
      } else if (ev.key === 'Escape') {
        setSuggestOpen(false);
      }
    });

    input.addEventListener('focus', function () {
      var q = input.value.trim();
      if (q.length >= 2 && suggestBox.innerHTML !== '') setSuggestOpen(true);
    });

    document.addEventListener('click', function (ev) {
      if (ev.target === input || suggestBox.contains(ev.target)) return;
      setSuggestOpen(false);
    });
  }

  function autosizeTextarea(el) {
    if (!el) return;
    el.style.height = 'auto';
    el.style.height = el.scrollHeight + 'px';
  }

  function autosizeAll(root) {
    (root || document).querySelectorAll('textarea[data-autosize]').forEach(autosizeTextarea);
  }

  document.querySelectorAll('[data-lang-tabs]').forEach(function (root) {
    var tabs = root.querySelectorAll('[data-lang-tab]');
    var panels = root.querySelectorAll('[data-lang-panel]');
    function activate(lang) {
      tabs.forEach(function (btn) {
        btn.classList.toggle('is-active', btn.getAttribute('data-lang-tab') === lang);
      });
      panels.forEach(function (panel) {
        var on = panel.getAttribute('data-lang-panel') === lang;
        panel.classList.toggle('is-active', on);
        if (on) autosizeAll(panel);
      });
    }
    tabs.forEach(function (btn) {
      btn.addEventListener('click', function () {
        activate(btn.getAttribute('data-lang-tab'));
      });
    });
  });

  document.querySelectorAll('textarea[data-autosize]').forEach(function (el) {
    autosizeTextarea(el);
    el.addEventListener('input', function () {
      autosizeTextarea(el);
    });
  });

  var docAdd = document.querySelector('.admin-media__add--doc');
  var docInput = docAdd ? docAdd.querySelector('input[type="file"]') : null;
  var pendingDocs = [];

  function docTypeLabel(name) {
    var ext = String(name || '').split('.').pop().toLowerCase();
    var map = {
      pdf: 'PDF', djvu: 'DJVU', html: 'HTML', htm: 'HTML', txt: 'TXT',
      jpg: 'JPG', jpeg: 'JPG', png: 'JPG', doc: 'Word', docx: 'Word', rtf: 'Word', odt: 'Word'
    };
    return map[ext] || (ext ? ext.toUpperCase() : 'Файл');
  }

  function docList(docs) {
    var list = docs.querySelector('.admin-file-list');
    if (list) return list;
    list = document.createElement('ul');
    list.className = 'admin-file-list';
    docs.insertBefore(list, docs.querySelector('.admin-media__add--doc'));
    return list;
  }

  function appendDocChip(list, file) {
    var item = document.createElement('li');
    item.className = 'admin-file-chip';
    var name = document.createElement('a');
    name.className = 'admin-file-chip__name';
    name.textContent = file.file_name;
    name.href = file.public_url;
    var meta = document.createElement('span');
    meta.className = 'admin-file-chip__meta';
    meta.textContent = file.type_label + ' · ' + file.file_size + ' КБ';
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'admin-file-chip__remove admin-file-delete';
    btn.setAttribute('data-file-id', String(file.id));
    btn.setAttribute('aria-label', 'Удалить файл');
    btn.textContent = '×';
    item.appendChild(name);
    item.appendChild(meta);
    item.appendChild(btn);
    list.appendChild(item);
  }

  function renderPendingDocs(docs) {
    var list = pendingDocs.length ? docList(docs) : docs.querySelector('.admin-file-list');
    if (!list) return;
    list.querySelectorAll('[data-pending]').forEach(function (item) { item.remove(); });
    pendingDocs.forEach(function (file, index) {
      var item = document.createElement('li');
      item.className = 'admin-file-chip';
      item.setAttribute('data-pending', String(index));
      var name = document.createElement('span');
      name.className = 'admin-file-chip__name';
      name.textContent = file.name;
      var meta = document.createElement('span');
      meta.className = 'admin-file-chip__meta';
      var kb = Math.max(1, Math.ceil(file.size / 1000));
      meta.textContent = docTypeLabel(file.name) + ' · ' + kb + ' КБ';
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'admin-file-chip__remove admin-file-delete';
      btn.setAttribute('aria-label', 'Удалить файл');
      btn.textContent = '×';
      item.appendChild(name);
      item.appendChild(meta);
      item.appendChild(btn);
      list.appendChild(item);
    });
    if (!list.querySelector('.admin-file-chip')) list.remove();
  }

  function syncPendingDocs() {
    if (!docInput || typeof DataTransfer !== 'function') return;
    var transfer = new DataTransfer();
    pendingDocs.forEach(function (file) { transfer.items.add(file); });
    docInput.files = transfer.files;
  }

  document.addEventListener('click', function (event) {
    var btn = event.target.closest('.admin-file-delete');
    if (!btn || btn.disabled) return;
    var item = btn.closest('.admin-file-chip');
    var form = btn.closest('form');
    if (!item || !form) return;
    if (item.hasAttribute('data-pending')) {
      pendingDocs.splice(Number(item.getAttribute('data-pending')), 1);
      syncPendingDocs();
      renderPendingDocs(item.closest('.admin-media__docs'));
      return;
    }
    var fileId = btn.getAttribute('data-file-id');
    var tokenInput = form.querySelector('[name="csrf_token"]');
    if (!fileId || !tokenInput) return;
    if (!window.confirm('удалить файл?')) return;
    btn.disabled = true;
    var fd = new FormData();
    fd.append('action', 'delete_file');
    fd.append('csrf_token', tokenInput.value);
    fd.append('file_id', fileId);
    fetch(form.action, { method: 'POST', body: fd, credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (response) {
        return response.text().then(function (text) {
          var data = null;
          try { data = JSON.parse(text); } catch (e) { /* html error page */ }
          if (!response.ok || !data || !data.ok) {
            throw new Error((data && data.error) || ('HTTP ' + response.status));
          }
        });
      })
      .then(function () {
        var list = item.parentElement;
        item.remove();
        if (list && list.classList.contains('admin-file-list') && !list.querySelector('.admin-file-chip')) list.remove();
      })
      .catch(function () {
        btn.disabled = false;
        window.alert('Не удалось удалить файл');
      });
  });

  if (docInput && docAdd) {
    docInput.addEventListener('change', function () {
      var picked = Array.prototype.slice.call(docInput.files || []);
      if (!picked.length) return;
      var form = docInput.closest('form');
      var docs = docInput.closest('.admin-media__docs');
      var tokenInput = form ? form.querySelector('[name="csrf_token"]') : null;
      var match = String(form && form.action || '').match(/[?&]id=(\d+)/);
      var workId = match ? Number(match[1]) : 0;
      if (workId <= 0) {
        pendingDocs = pendingDocs.concat(picked);
        syncPendingDocs();
        if (docs) renderPendingDocs(docs);
        return;
      }
      docInput.value = '';
      if (!form || !tokenInput || !docs) return;
      docAdd.classList.add('is-busy');
      var chain = Promise.resolve();
      picked.forEach(function (file) {
        chain = chain.then(function () {
          var fd = new FormData();
          fd.append('action', 'upload_file');
          fd.append('csrf_token', tokenInput.value);
          fd.append('file', file, file.name);
          return fetch(form.action, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: { Accept: 'application/json' }
          }).then(function (response) {
            return response.text().then(function (text) {
              var data = null;
              try { data = JSON.parse(text); } catch (e) { /* html error page */ }
              if (!response.ok || !data || !data.ok || !data.file) {
                throw new Error((data && data.error) || ('HTTP ' + response.status));
              }
              appendDocChip(docList(docs), data.file);
            });
          });
        });
      });
      chain.catch(function (err) {
        window.alert(err && err.message ? err.message : 'Не удалось загрузить файл');
      }).then(function () {
        docAdd.classList.remove('is-busy');
      });
    });
  }

  var translateBtn = document.getElementById('admin-work-translate');
  if (translateBtn) {
    var translateStatus = document.getElementById('admin-work-translate-status');
    var translateFields = ['title', 'subtitle', 'imprint', 'slug', 'annotation', 'meta_description'];

    function translateField(lang, name) {
      return document.querySelector('[data-lang-panel="' + lang + '"] [name="i18n[' + lang + '][' + name + ']"]');
    }

    function setTranslateStatus(text, kind) {
      if (!translateStatus) return;
      translateStatus.textContent = text || '';
      translateStatus.classList.remove('is-error', 'is-ok');
      if (kind) translateStatus.classList.add(kind);
    }

    translateBtn.addEventListener('click', function () {
      var ru = {};
      var hasRu = false;
      ['title', 'subtitle', 'imprint', 'annotation', 'meta_description'].forEach(function (name) {
        var input = translateField('ru', name);
        ru[name] = input ? String(input.value || '') : '';
        if (ru[name].trim() !== '') hasRu = true;
      });
      if (!hasRu) {
        setTranslateStatus('Сначала заполните русскую вкладку', 'is-error');
        return;
      }
      var hasTarget = false;
      ['en', 'es'].forEach(function (lang) {
        translateFields.forEach(function (name) {
          var input = translateField(lang, name);
          if (input && String(input.value || '').trim() !== '') hasTarget = true;
        });
      });
      if (hasTarget && !window.confirm('Заменить английские и испанские поля переводом с русского?')) {
        return;
      }

      var body = new FormData();
      var token = document.querySelector('input[name="csrf_token"]');
      if (token) body.set('csrf_token', token.value);
      Object.keys(ru).forEach(function (name) { body.set(name, ru[name]); });
      var action = translateBtn.form ? translateBtn.form.getAttribute('action') : window.location.pathname;
      var url = action + (action.indexOf('?') >= 0 ? '&' : '?') + 'action=translate_i18n';

      translateBtn.disabled = true;
      setTranslateStatus('Перевожу…', '');
      fetch(url, { method: 'POST', body: body, credentials: 'same-origin' })
        .then(function (response) {
          return response.json().then(function (payload) {
            return { okHttp: response.ok, body: payload };
          });
        })
        .then(function (result) {
          if (!result.body || !result.body.ok || !result.body.i18n) {
            throw new Error((result.body && result.body.error) || 'Ошибка перевода');
          }
          ['en', 'es'].forEach(function (lang) {
            var row = result.body.i18n[lang] || {};
            translateFields.forEach(function (name) {
              var input = translateField(lang, name);
              if (!input || row[name] === undefined) return;
              input.value = row[name];
              input.dispatchEvent(new Event('input', { bubbles: true }));
            });
            var tab = document.querySelector('[data-lang-tab="' + lang + '"]');
            if (tab) tab.classList.toggle('has-data', String(row.title || '').trim() !== '');
          });
          setTranslateStatus('Готово', 'is-ok');
        })
        .catch(function (err) {
          setTranslateStatus(err && err.message ? err.message : 'Ошибка перевода', 'is-error');
        })
        .finally(function () {
          translateBtn.disabled = false;
        });
    });
  }

  var chapterTranslateBtn = document.getElementById('admin-chapter-translate');
  if (chapterTranslateBtn) {
    var chapterRoot = chapterTranslateBtn.closest('[data-lang-tabs]') || document;
    var chapterStatus = document.getElementById('admin-chapter-translate-status');
    var chapterFields = ['title', 'slug', 'body', 'body_format'];

    function chapterField(lang, name) {
      return chapterRoot.querySelector('[data-lang-panel="' + lang + '"] [name="i18n[' + lang + '][' + name + ']"]');
    }

    function setChapterStatus(text, kind) {
      if (!chapterStatus) return;
      chapterStatus.textContent = text || '';
      chapterStatus.classList.remove('is-error', 'is-ok');
      if (kind) chapterStatus.classList.add(kind);
    }

    chapterTranslateBtn.addEventListener('click', function () {
      var titleInput = chapterField('ru', 'title');
      var bodyInput = chapterField('ru', 'body');
      var formatInput = chapterField('ru', 'body_format');
      var title = titleInput ? String(titleInput.value || '') : '';
      var body = bodyInput ? String(bodyInput.value || '') : '';
      var format = formatInput ? String(formatInput.value || 'html') : 'html';
      if (title.trim() === '' && body.trim() === '') {
        setChapterStatus('Сначала заполните русскую вкладку', 'is-error');
        return;
      }
      var hasTarget = false;
      ['en', 'es'].forEach(function (lang) {
        chapterFields.forEach(function (name) {
          var input = chapterField(lang, name);
          if (name === 'body_format') return;
          if (input && String(input.value || '').trim() !== '') hasTarget = true;
        });
      });
      if (hasTarget && !window.confirm('Заменить английские и испанские поля переводом с русского?')) {
        return;
      }

      var payload = new FormData();
      var token = document.querySelector('input[name="csrf_token"]');
      if (token) payload.set('csrf_token', token.value);
      payload.set('title', title);
      payload.set('body', body);
      payload.set('format', format);
      var action = chapterTranslateBtn.form ? chapterTranslateBtn.form.getAttribute('action') : window.location.pathname;
      var url = action + (action.indexOf('?') >= 0 ? '&' : '?') + 'action=translate_i18n';

      chapterTranslateBtn.disabled = true;
      setChapterStatus('Перевожу…', '');
      fetch(url, { method: 'POST', body: payload, credentials: 'same-origin' })
        .then(function (response) {
          return response.json().then(function (data) {
            return { okHttp: response.ok, body: data };
          });
        })
        .then(function (result) {
          if (!result.body || !result.body.ok || !result.body.i18n) {
            throw new Error((result.body && result.body.error) || 'Ошибка перевода');
          }
          ['en', 'es'].forEach(function (lang) {
            var row = result.body.i18n[lang] || {};
            chapterFields.forEach(function (name) {
              var input = chapterField(lang, name);
              if (!input || row[name] === undefined) return;
              input.value = row[name];
              input.dispatchEvent(new Event('input', { bubbles: true }));
            });
            var tab = chapterRoot.querySelector('[data-lang-tab="' + lang + '"]');
            if (tab) tab.classList.toggle('has-data', String(row.title || '').trim() !== '');
          });
          setChapterStatus('Готово', 'is-ok');
        })
        .catch(function (err) {
          setChapterStatus(err && err.message ? err.message : 'Ошибка перевода', 'is-error');
        })
        .finally(function () {
          chapterTranslateBtn.disabled = false;
        });
    });
  }

  document.querySelectorAll('[data-chapter-preview]').forEach(function (box) {
    var panel = box.closest('[data-lang-panel]');
    if (!panel) return;
    var text = panel.querySelector('textarea[name$="[body]"]');
    var format = panel.querySelector('select[name$="[body_format]"]');
    if (!text) return;
    var timer = 0;
    var seq = 0;

    function rootUrls(html) {
      return String(html || '').replace(
        /\b(src|href)=(["'])(?!\/|https?:|\/\/|mailto:|data:|#|javascript:)((?:chapters_images|pictures|books_files|img)\/[^"']*)\2/gi,
        '$1=$2/$3$2'
      );
    }

    function setMode(mode) {
      box.classList.remove('is-html', 'is-md', 'is-txt');
      box.classList.add(mode === 'md' ? 'is-md' : (mode === 'txt' ? 'is-txt' : 'is-html'));
    }

    function paintLocal() {
      var value = String(text.value || '');
      var kind = format ? String(format.value || 'html') : 'html';
      if (kind === 'txt') {
        setMode('txt');
        box.textContent = value;
        return;
      }
      if (kind === 'markdown') return false;
      setMode('html');
      box.innerHTML = rootUrls(value);
      return true;
    }

    function paintServer() {
      var ticket = ++seq;
      var payload = new FormData();
      var token = document.querySelector('input[name="csrf_token"]');
      if (token) payload.set('csrf_token', token.value);
      payload.set('body', text.value);
      payload.set('format', format ? String(format.value || 'html') : 'html');
      var form = text.closest('form');
      var action = form ? form.getAttribute('action') : window.location.pathname;
      var url = action + (action.indexOf('?') >= 0 ? '&' : '?') + 'action=preview_body';
      fetch(url, { method: 'POST', body: payload, credentials: 'same-origin' })
        .then(function (response) { return response.json(); })
        .then(function (data) {
          if (ticket !== seq || !data || !data.ok) return;
          setMode(data.mode || 'md');
          box.innerHTML = data.html || '';
        })
        .catch(function () {});
    }

    function update() {
      if (paintLocal() !== false) {
        seq++;
        return;
      }
      clearTimeout(timer);
      timer = setTimeout(paintServer, 250);
    }

    text.addEventListener('input', update);
    if (format) format.addEventListener('change', update);
    update();
  });

  var authorModal = document.getElementById('admin-author-modal');
  var authorForm = document.getElementById('admin-author-form');
  if (authorModal && authorForm && typeof authorModal.showModal === 'function') {
    var authorError = document.getElementById('admin-author-error');
    var authorTarget = '';

    function showAuthorError(message) {
      if (!authorError) return;
      authorError.textContent = message || '';
      authorError.hidden = !message;
    }

    function upsertAuthorOption(select, id, nickname, selected) {
      var value = String(id);
      var option = null;
      Array.prototype.forEach.call(select.options, function (item) {
        if (item.value === value) option = item;
      });
      if (!option) {
        option = document.createElement('option');
        option.value = value;
        option.textContent = nickname;
        var placed = false;
        Array.prototype.forEach.call(select.options, function (item) {
          if (placed || item.value === '' || item.value === '0') return;
          if (item.textContent.localeCompare(nickname, 'ru', { sensitivity: 'base' }) > 0) {
            select.insertBefore(option, item);
            placed = true;
          }
        });
        if (!placed) select.appendChild(option);
      }
      if (selected) select.value = value;
    }

    document.querySelectorAll('[data-author-add]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        authorTarget = btn.getAttribute('data-author-add') || '';
        authorForm.reset();
        showAuthorError('');
        authorModal.showModal();
        var nick = authorForm.querySelector('[name="nickname"]');
        if (nick) nick.focus();
      });
    });

    var cancel = authorForm.querySelector('[data-author-cancel]');
    if (cancel) {
      cancel.addEventListener('click', function () {
        authorModal.close();
      });
    }

    authorForm.addEventListener('submit', function (event) {
      event.preventDefault();
      var submit = authorForm.querySelector('[type="submit"]');
      if (submit) submit.disabled = true;
      showAuthorError('');
      fetch(authorForm.action, {
        method: 'POST',
        body: new FormData(authorForm),
        credentials: 'same-origin',
        headers: { Accept: 'application/json' }
      }).then(function (response) {
        return response.json().then(function (data) {
          return { ok: response.ok, data: data };
        });
      }).then(function (result) {
        if (!result.ok || !result.data || !result.data.ok) {
          throw new Error((result.data && result.data.error) || 'Не удалось создать автора');
        }
        document.querySelectorAll('select[name="author_from"], select[name="author_to"]').forEach(function (select) {
          upsertAuthorOption(select, result.data.id, result.data.nickname, select.name === authorTarget);
        });
        authorModal.close();
      }).catch(function (err) {
        showAuthorError(err && err.message ? err.message : 'Не удалось создать автора');
      }).then(function () {
        if (submit) submit.disabled = false;
      });
    });
  }

  var ocrBtn = document.getElementById('admin-letter-ocr-btn');
  var ocrForm = ocrBtn ? ocrBtn.closest('form') : null;
  if (ocrBtn && ocrForm) {
    var ocrStatus = document.getElementById('admin-letter-ocr-status');
    var ocrUpload = document.getElementById('admin-letter-upload');
    var ocrBusy = false;
    var ocrLabel = 'Обработать (AI OCR)';

    function setOcrStatus(message, kind) {
      if (!ocrStatus) return;
      ocrStatus.textContent = message || '';
      ocrStatus.hidden = !message;
      ocrStatus.classList.toggle('is-error', kind === 'is-error');
      ocrStatus.classList.toggle('is-ok', kind === 'is-ok');
    }

    function syncOcrButton() {
      var files = ocrUpload && ocrUpload.files ? ocrUpload.files.length : 0;
      var saved = parseInt(ocrBtn.getAttribute('data-saved-count') || '0', 10) || 0;
      var letterId = parseInt(ocrBtn.getAttribute('data-letter-id') || '0', 10) || 0;
      ocrBtn.disabled = ocrBusy || (files === 0 && (saved <= 0 || letterId <= 0));
    }

    function setOcrField(name, value) {
      if (value === undefined || value === null || value === '') return;
      var el = ocrForm.querySelector('[name="' + name + '"]');
      if (!el) return;
      el.value = String(value);
    }

    function applyOcrResult(data) {
      ['title_ru', 'title_en', 'summary_ru', 'summary_en', 'meta_description_ru', 'meta_description_en', 'body_ru', 'body_en'].forEach(function (name) {
        setOcrField(name, data[name] || '');
      });
      if (data.date) setOcrField('date', data.date);
      ['author_from', 'author_to'].forEach(function (name) {
        var id = parseInt(data[name] || '0', 10) || 0;
        var select = ocrForm.querySelector('[name="' + name + '"]');
        if (select && id > 0) select.value = String(id);
      });
    }

    if (ocrUpload) {
      ocrUpload.addEventListener('change', function () {
        syncOcrButton();
      });
    }

    ocrBtn.addEventListener('click', function () {
      if (ocrBusy || ocrBtn.disabled) return;
      var filled = ['title_ru', 'body_ru', 'body_en'].some(function (name) {
        var el = ocrForm.querySelector('[name="' + name + '"]');
        return el && String(el.value || '').trim() !== '';
      });
      if (filled && !window.confirm('Заменить заполненные поля результатом AI?')) return;
      var tokenInput = ocrForm.querySelector('[name="csrf_token"]');
      var token = tokenInput ? tokenInput.value : '';
      if (!token) {
        setOcrStatus('Нет CSRF-токена — обнови страницу', 'is-error');
        return;
      }
      var files = ocrUpload && ocrUpload.files ? ocrUpload.files : [];
      var saved = parseInt(ocrBtn.getAttribute('data-saved-count') || '0', 10) || 0;
      var letterId = parseInt(ocrBtn.getAttribute('data-letter-id') || '0', 10) || 0;
      var pages = files.length > 0 ? files.length : saved;
      ocrBusy = true;
      syncOcrButton();
      ocrBtn.textContent = 'Обрабатываю…';
      setOcrStatus('Отправляю ' + pages + ' стр. в AI…', '');
      var fd = new FormData();
      fd.append('action', 'ocr');
      fd.append('csrf_token', token);
      if (files.length > 0) {
        Array.prototype.forEach.call(files, function (file, i) {
          fd.append('ocr_files[]', file, file.name || ('page-' + (i + 1) + '.jpg'));
        });
      } else {
        fd.append('letter_id', String(letterId));
      }
      fetch(ocrForm.action, {
        method: 'POST',
        body: fd,
        credentials: 'same-origin'
      }).then(function (response) {
        return response.text().then(function (text) {
          var data = null;
          try { data = JSON.parse(text); } catch (e) { /* html error page */ }
          if (!response.ok || !data || !data.ok) {
            var msg = data && data.error ? data.error : '';
            if (!msg) {
              msg = response.status === 504 ? 'Таймаут OCR (сервер ждал ответа AI слишком долго)' : ('HTTP ' + response.status);
            }
            throw new Error(msg);
          }
          return data.data || {};
        });
      }).then(function (data) {
        applyOcrResult(data);
        var notes = [];
        if (data.note) notes.push(data.note);
        if (data.from_nick && !data.author_from) notes.push('отправитель «' + data.from_nick + '» не найден — добавьте через +1');
        if (data.to_nick && !data.author_to) notes.push('адресат «' + data.to_nick + '» не найден — добавьте через +1');
        setOcrStatus('Готово — поля заполнены.' + (notes.length ? ' ' + notes.join(' ') : ''), 'is-ok');
      }).catch(function (err) {
        setOcrStatus(err && err.message ? err.message : 'Ошибка OCR', 'is-error');
      }).then(function () {
        ocrBusy = false;
        ocrBtn.textContent = ocrLabel;
        syncOcrButton();
      });
    });

    syncOcrButton();
  }

  var pageList = document.querySelector('.admin-letter-pages');
  if (pageList) {
    var dragPage = null;
    var orderBefore = '';
    var suppressClick = false;
    var viewer = document.getElementById('admin-letter-view');
    var viewerImg = viewer ? viewer.querySelector('img') : null;

    function pageOrder() {
      return Array.prototype.map.call(pageList.querySelectorAll('.admin-letter-page'), function (page) {
        return page.getAttribute('data-image-id');
      }).join(',');
    }

    function writePageOrder() {
      var n = 1;
      pageList.querySelectorAll('.admin-letter-page').forEach(function (page) {
        var input = page.querySelector('input[name^="sort_order_"]');
        if (input) input.value = String(n++);
      });
    }

    pageList.addEventListener('dragstart', function (event) {
      var page = event.target.closest('.admin-letter-page');
      if (!page || event.target.closest('.admin-letter-page__remove')) {
        event.preventDefault();
        return;
      }
      dragPage = page;
      suppressClick = true;
      orderBefore = pageOrder();
      page.classList.add('is-dragging');
      if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', page.getAttribute('data-image-id') || '');
      }
    });

    pageList.addEventListener('dragover', function (event) {
      if (!dragPage) return;
      event.preventDefault();
      if (event.dataTransfer) event.dataTransfer.dropEffect = 'move';
      var under = document.elementFromPoint(event.clientX, event.clientY);
      var page = under ? under.closest('.admin-letter-page') : null;
      if (!page || page === dragPage) return;
      var box = page.getBoundingClientRect();
      var after = event.clientX > box.left + box.width / 2;
      if (after) {
        page.after(dragPage);
      } else {
        page.before(dragPage);
      }
    });

    pageList.addEventListener('drop', function (event) {
      event.preventDefault();
    });

    pageList.addEventListener('dragend', function () {
      if (dragPage) dragPage.classList.remove('is-dragging');
      if (dragPage && pageOrder() !== orderBefore) writePageOrder();
      dragPage = null;
      setTimeout(function () { suppressClick = false; }, 0);
    });

    pageList.addEventListener('click', function (event) {
      var btn = event.target.closest('.admin-letter-page__remove');
      if (!btn) return;
      event.preventDefault();
      event.stopPropagation();
      var page = btn.closest('.admin-letter-page');
      var confirmText = pageList.getAttribute('data-remove-confirm') || 'удалить картинку?';
      if (!page || page.getAttribute('data-deleting') === '1' || !window.confirm(confirmText)) return;
      if (page.hasAttribute('data-pending-cover')) {
        var pendingUrl = page.querySelector('img');
        if (pendingUrl) {
          var blobUrl = pendingUrl.getAttribute('data-full') || '';
          if (blobUrl.indexOf('blob:') === 0) URL.revokeObjectURL(blobUrl);
        }
        page.remove();
        return;
      }
      var imageId = page.getAttribute('data-image-id');
      var form = pageList.closest('form');
      var tokenInput = form ? form.querySelector('[name="csrf_token"]') : null;
      var token = tokenInput ? tokenInput.value : '';
      if (!form || !imageId || !token) return;
      page.setAttribute('data-deleting', '1');
      btn.disabled = true;
      var fd = new FormData();
      fd.append('action', 'delete_image');
      fd.append('csrf_token', token);
      fd.append('image_id', imageId);
      fetch(form.action, {
        method: 'POST',
        body: fd,
        credentials: 'same-origin',
        headers: { Accept: 'application/json' }
      }).then(function (response) {
        return response.text().then(function (text) {
          var data = null;
          try { data = JSON.parse(text); } catch (e) { /* html error page */ }
          if (!response.ok || !data || !data.ok) {
            throw new Error((data && data.error) || ('HTTP ' + response.status));
          }
        });
      }).then(function () {
        page.remove();
        writePageOrder();
        var ocr = document.getElementById('admin-letter-ocr-btn');
        var left = pageList.querySelectorAll('.admin-letter-page').length;
        if (ocr) ocr.setAttribute('data-saved-count', String(left));
        var upload = document.getElementById('admin-letter-upload');
        var files = upload && upload.files ? upload.files.length : 0;
        if (ocr && files === 0 && left === 0) ocr.disabled = true;
        if (left === 0) {
          var box = pageList.closest('.admin-letter-box');
          if (box && !box.classList.contains('admin-media')) box.hidden = true;
        }
      }).catch(function (err) {
        page.removeAttribute('data-deleting');
        btn.disabled = false;
        window.alert(err && err.message ? err.message : 'Не удалось удалить картинку');
      });
    });

    if (viewer && viewerImg) {
      pageList.addEventListener('click', function (event) {
        if (suppressClick || event.target.closest('.admin-letter-page__remove')) return;
        var thumb = event.target.closest('.admin-letter-page > img');
        if (!thumb) return;
        var full = thumb.getAttribute('data-full') || thumb.currentSrc || thumb.src;
        viewerImg.onerror = function () {
          if (viewerImg.getAttribute('src') !== thumb.src) viewerImg.src = thumb.src;
        };
        viewerImg.src = full;
        viewer.showModal();
      });
      viewer.addEventListener('click', function () {
        viewer.close();
      });
      viewer.addEventListener('close', function () {
        viewerImg.removeAttribute('src');
      });
    }
  }

  document.querySelectorAll('[data-repeat]').forEach(function (btn) {
    var rows = document.getElementById(btn.getAttribute('data-repeat'));
    var tpl = document.getElementById(btn.getAttribute('data-repeat-tpl'));
    if (!rows || !tpl) return;
    btn.addEventListener('click', function () {
      var row = tpl.content.firstElementChild.cloneNode(true);
      rows.insertBefore(row, btn);
      var control = row.querySelector('select, input');
      if (control) control.focus();
    });
  });

  var seriesModal = document.getElementById('admin-series-modal');
  var seriesForm = document.getElementById('admin-series-form');
  var seriesSelect = document.getElementById('admin-series-select');
  if (seriesModal && seriesForm && seriesSelect && typeof seriesModal.showModal === 'function') {
    var seriesError = document.getElementById('admin-series-error');
    var showSeriesError = function (message) {
      if (!seriesError) return;
      seriesError.textContent = message || '';
      seriesError.hidden = !message;
    };

    var seriesPrevious = seriesSelect.value;
    seriesSelect.addEventListener('change', function () {
      if (seriesSelect.value !== '__new__') {
        seriesPrevious = seriesSelect.value;
        return;
      }
      seriesForm.reset();
      showSeriesError('');
      seriesModal.showModal();
      var title = seriesForm.querySelector('[name="title"]');
      if (title) title.focus();
    });
    seriesModal.addEventListener('close', function () {
      if (seriesSelect.value === '__new__') seriesSelect.value = seriesPrevious;
    });

    var seriesCancel = seriesForm.querySelector('[data-series-cancel]');
    if (seriesCancel) {
      seriesCancel.addEventListener('click', function () {
        seriesModal.close();
      });
    }

    seriesForm.addEventListener('submit', function (event) {
      event.preventDefault();
      var submit = seriesForm.querySelector('[type="submit"]');
      if (submit) submit.disabled = true;
      showSeriesError('');
      fetch(seriesForm.action, {
        method: 'POST',
        body: new FormData(seriesForm),
        credentials: 'same-origin',
        headers: { Accept: 'application/json' }
      }).then(function (response) {
        return response.json().then(function (data) {
          return { ok: response.ok, data: data };
        });
      }).then(function (result) {
        if (!result.ok || !result.data || !result.data.ok) {
          throw new Error((result.data && result.data.error) || 'Не удалось создать серию');
        }
        var value = String(result.data.id);
        var option = null;
        Array.prototype.forEach.call(seriesSelect.options, function (item) {
          if (item.value === value) option = item;
        });
        if (!option) {
          option = document.createElement('option');
          option.value = value;
          option.textContent = result.data.title;
          var placed = false;
          Array.prototype.forEach.call(seriesSelect.options, function (item) {
            if (placed || item.value === '0' || item.value === '__new__') return;
            if (item.textContent.localeCompare(result.data.title, 'ru', { sensitivity: 'base' }) > 0) {
              seriesSelect.insertBefore(option, item);
              placed = true;
            }
          });
          if (!placed) seriesSelect.appendChild(option);
        }
        seriesSelect.value = value;
        seriesPrevious = value;
        seriesModal.close();
      }).catch(function (err) {
        showSeriesError(err && err.message ? err.message : 'Не удалось создать серию');
      }).then(function () {
        if (submit) submit.disabled = false;
      });
    });
  }

  var coverInput = document.getElementById('admin-cover-upload');
  var coverDialog = document.getElementById('admin-cover-edit');
  var coverImg = document.getElementById('admin-cover-edit-img');
  var coverSkewX = document.getElementById('admin-cover-edit-skew-x');
  var coverSkewY = document.getElementById('admin-cover-edit-skew-y');
  if (coverInput && coverDialog && coverImg && typeof Cropper !== 'undefined') {
    var coverFiles = [];
    var coverIndex = 0;
    var coverCropper = null;
    var coverAssigning = false;
    var coverSourceUrl = '';
    var coverSourceImg = null;
    var coverPreviewUrl = '';
    var coverSkewTimer = 0;
    var coverPendingView = null;
    var coverTitle = document.getElementById('admin-cover-edit-title');
    var coverCount = document.getElementById('admin-cover-edit-count');

    function coverDestroy() {
      if (coverCropper) {
        coverCropper.destroy();
        coverCropper = null;
      }
    }

    function coverClose() {
      coverDestroy();
      coverSourceImg = null;
      if (coverPreviewUrl) {
        URL.revokeObjectURL(coverPreviewUrl);
        coverPreviewUrl = '';
      }
      coverImg.removeAttribute('src');
      if (coverDialog.open) coverDialog.close();
    }

    function coverCommit(files) {
      coverAssigning = true;
      if (typeof DataTransfer === 'function') {
        var transfer = new DataTransfer();
        files.forEach(function (file) { transfer.items.add(file); });
        coverInput.files = transfer.files;
      }
      coverAssigning = false;
    }

    function coverSkewValue(input) {
      return input ? Number(input.value) || 0 : 0;
    }

    function coverShearCanvas(img, degX, degY) {
      var w = img.naturalWidth || img.width;
      var h = img.naturalHeight || img.height;
      var kx = Math.tan(degX * Math.PI / 180);
      var ky = Math.tan(degY * Math.PI / 180);
      function point(x, y) {
        return [x + kx * y, y + ky * x];
      }
      var pts = [point(0, 0), point(w, 0), point(0, h), point(w, h)];
      var minX = pts[0][0];
      var minY = pts[0][1];
      var maxX = pts[0][0];
      var maxY = pts[0][1];
      pts.forEach(function (p) {
        if (p[0] < minX) minX = p[0];
        if (p[1] < minY) minY = p[1];
        if (p[0] > maxX) maxX = p[0];
        if (p[1] > maxY) maxY = p[1];
      });
      var canvas = document.createElement('canvas');
      canvas.width = Math.max(1, Math.ceil(maxX - minX));
      canvas.height = Math.max(1, Math.ceil(maxY - minY));
      var ctx = canvas.getContext('2d');
      ctx.setTransform(1, ky, kx, 1, -minX, -minY);
      ctx.drawImage(img, 0, 0);
      return canvas;
    }

    function coverShowUrl(url) {
      if (coverCropper) {
        var data = coverCropper.getData();
        coverPendingView = { rotate: data.rotate || 0, scaleX: data.scaleX || 1 };
        coverCropper.replace(url);
        return;
      }
      coverImg.onload = function () {
        coverCropper = new Cropper(coverImg, {
          viewMode: 1,
          autoCropArea: 1,
          responsive: true,
          background: false,
          checkOrientation: true,
          guides: true,
          rotatable: true,
          scalable: true,
          ready: function () {
            if (!coverPendingView || !coverCropper) return;
            var view = coverPendingView;
            coverPendingView = null;
            if (view.rotate) coverCropper.rotateTo(view.rotate);
            if (view.scaleX < 0) coverCropper.scaleX(-1);
          }
        });
      };
      coverImg.src = url;
    }

    function coverApplySkew() {
      if (!coverSourceImg) return;
      var degX = coverSkewValue(coverSkewX);
      var degY = coverSkewValue(coverSkewY);
      if (!degX && !degY) {
        if (coverPreviewUrl) {
          URL.revokeObjectURL(coverPreviewUrl);
          coverPreviewUrl = '';
        }
        coverShowUrl(coverSourceUrl);
        return;
      }
      var canvas = coverShearCanvas(coverSourceImg, degX, degY);
      canvas.toBlob(function (blob) {
        if (!blob) return;
        if (coverPreviewUrl) URL.revokeObjectURL(coverPreviewUrl);
        coverPreviewUrl = URL.createObjectURL(blob);
        coverShowUrl(coverPreviewUrl);
      }, 'image/webp', 0.95);
    }

    function coverOpen(index) {
      var file = coverFiles[index];
      if (!file) return;
      coverIndex = index;
      coverDestroy();
      coverSourceImg = null;
      if (coverPreviewUrl) {
        URL.revokeObjectURL(coverPreviewUrl);
        coverPreviewUrl = '';
      }
      if (coverSkewX) coverSkewX.value = '0';
      if (coverSkewY) coverSkewY.value = '0';
      if (coverTitle) coverTitle.textContent = file.name;
      if (coverCount) coverCount.textContent = (index + 1) + ' / ' + coverFiles.length;
      var reader = new FileReader();
      reader.onload = function () {
        coverSourceUrl = String(reader.result || '');
        var probe = new Image();
        probe.onload = function () {
          coverSourceImg = probe;
          coverShowUrl(coverSourceUrl);
        };
        probe.src = coverSourceUrl;
      };
      reader.readAsDataURL(file);
      if (!coverDialog.open) coverDialog.showModal();
    }

    function coverInsertPending(file) {
      var pages = document.querySelector('.admin-letter-pages');
      if (!pages) return null;
      var page = document.createElement('div');
      page.className = 'admin-letter-page';
      page.draggable = true;
      page.setAttribute('data-pending-cover', '1');
      var blobUrl = URL.createObjectURL(file);
      var img = document.createElement('img');
      img.alt = '';
      img.draggable = false;
      img.src = blobUrl;
      img.setAttribute('data-full', blobUrl);
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'admin-letter-page__remove';
      btn.setAttribute('aria-label', 'Удалить обложку');
      btn.textContent = '×';
      page.appendChild(img);
      page.appendChild(btn);
      var add = pages.querySelector('.admin-media__add');
      pages.insertBefore(page, add);
      return page;
    }

    function coverPromote(page, cover) {
      if (!page || !page.isConnected) return false;
      var img = page.querySelector('img');
      var blobUrl = img ? (img.getAttribute('data-full') || '') : '';
      page.removeAttribute('data-pending-cover');
      page.setAttribute('data-image-id', String(cover.id));
      if (img) {
        img.src = cover.preview_url;
        img.setAttribute('data-full', cover.original_url);
      }
      if (blobUrl.indexOf('blob:') === 0) URL.revokeObjectURL(blobUrl);
      var sort = document.createElement('input');
      sort.type = 'hidden';
      sort.name = 'sort_order_' + cover.id;
      sort.value = String(cover.sort_order || 0);
      page.appendChild(sort);
      return true;
    }

    function coverUpload(file, page) {
      var form = coverInput.closest('form');
      var tokenInput = form ? form.querySelector('[name="csrf_token"]') : null;
      var match = String(form && form.action || '').match(/[?&]id=(\d+)/);
      var workId = match ? Number(match[1]) : 0;
      if (workId <= 0 || !form || !tokenInput) {
        return Promise.resolve(false);
      }
      var fd = new FormData();
      fd.append('action', 'upload_cover');
      fd.append('csrf_token', tokenInput.value);
      fd.append('file', file, file.name);
      return fetch(form.action, {
        method: 'POST',
        body: fd,
        credentials: 'same-origin',
        headers: { Accept: 'application/json' }
      }).then(function (response) {
        return response.text().then(function (text) {
          var data = null;
          try { data = JSON.parse(text); } catch (e) { /* html error page */ }
          if (!response.ok || !data || !data.ok || !data.cover || !data.cover.id) {
            throw new Error((data && data.error) || ('HTTP ' + response.status));
          }
          if (!coverPromote(page, data.cover) && data.cover.id) {
            var drop = new FormData();
            drop.append('action', 'delete_image');
            drop.append('csrf_token', tokenInput.value);
            drop.append('image_id', String(data.cover.id));
            fetch(form.action, { method: 'POST', body: drop, credentials: 'same-origin' });
          }
          return true;
        });
      });
    }

    function coverAdvance(replaceFile) {
      if (replaceFile) coverFiles[coverIndex] = replaceFile;
      var next = coverIndex + 1;
      if (next < coverFiles.length) {
        coverOpen(next);
        return;
      }
      var batch = coverFiles.slice();
      var nodes = batch.map(coverInsertPending);
      coverClose();
      var form = coverInput.closest('form');
      var match = String(form && form.action || '').match(/[?&]id=(\d+)/);
      var workId = match ? Number(match[1]) : 0;
      if (workId <= 0) {
        coverCommit(batch);
        return;
      }
      coverInput.value = '';
      var failed = [];
      var chain = Promise.resolve();
      batch.forEach(function (file, index) {
        chain = chain.then(function () {
          return coverUpload(file, nodes[index]).then(function (saved) {
            if (!saved) failed.push(file);
          }).catch(function (err) {
            failed.push(file);
            window.alert(err && err.message ? err.message : 'Не удалось сохранить обложку');
          });
        });
      });
      chain.then(function () {
        if (failed.length) coverCommit(failed);
      });
    }

    coverInput.addEventListener('change', function () {
      if (coverAssigning) return;
      coverFiles = Array.prototype.slice.call(coverInput.files || []);
      if (!coverFiles.length) return;
      coverOpen(0);
    });

    coverDialog.addEventListener('click', function (event) {
      var rotate = event.target.closest('[data-cover-rotate]');
      if (rotate && coverCropper) {
        coverCropper.rotate(Number(rotate.getAttribute('data-cover-rotate')) || 0);
        return;
      }
      if (event.target.closest('[data-cover-flip]') && coverCropper) {
        var data = coverCropper.getData();
        coverCropper.scaleX((data.scaleX || 1) * -1);
        return;
      }
      if (event.target.closest('[data-cover-skip]')) {
        coverAdvance(null);
        return;
      }
      if (event.target.closest('[data-cover-cancel]')) {
        coverFiles = [];
        coverClose();
        return;
      }
      if (!event.target.closest('[data-cover-apply]') || !coverCropper) return;
      var applyBtn = event.target.closest('[data-cover-apply]');
      var canvas = coverCropper.getCroppedCanvas({ imageSmoothingEnabled: true, imageSmoothingQuality: 'high', maxWidth: 3500, maxHeight: 3500 });
      if (!canvas) return;
      applyBtn.disabled = true;
      canvas.toBlob(function (blob) {
        applyBtn.disabled = false;
        if (!blob) return;
        var base = (coverFiles[coverIndex].name || 'cover').replace(/\.[^.]+$/, '');
        coverAdvance(new File([blob], base + '.jpg', { type: 'image/jpeg', lastModified: Date.now() }));
      }, 'image/jpeg', 0.92);
    });

    function coverSkewInput() {
      window.clearTimeout(coverSkewTimer);
      coverSkewTimer = window.setTimeout(coverApplySkew, 160);
    }
    if (coverSkewX) coverSkewX.addEventListener('input', coverSkewInput);
    if (coverSkewY) coverSkewY.addEventListener('input', coverSkewInput);
  }
})();
