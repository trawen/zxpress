(function () {
  var input = document.getElementById('admin-rewind');
  if (input) {
    input.addEventListener('input', function () {
      var q = input.value.trim().toLowerCase();
      document.querySelectorAll('.admin-list__row:not(.admin-list__row--head)').forEach(function (row) {
        var text = (row.textContent || '').toLowerCase();
        row.style.display = q === '' || text.indexOf(q) !== -1 ? '' : 'none';
      });
    });
  }

  document.querySelectorAll('[data-lang-tabs]').forEach(function (root) {
    var tabs = root.querySelectorAll('[data-lang-tab]');
    var panels = root.querySelectorAll('[data-lang-panel]');
    function activate(lang) {
      tabs.forEach(function (btn) {
        btn.classList.toggle('is-active', btn.getAttribute('data-lang-tab') === lang);
      });
      panels.forEach(function (panel) {
        panel.classList.toggle('is-active', panel.getAttribute('data-lang-panel') === lang);
      });
    }
    tabs.forEach(function (btn) {
      btn.addEventListener('click', function () {
        activate(btn.getAttribute('data-lang-tab'));
      });
    });
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
          if (box) box.hidden = true;
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

    function coverAdvance(replaceFile) {
      if (replaceFile) coverFiles[coverIndex] = replaceFile;
      var next = coverIndex + 1;
      if (next < coverFiles.length) {
        coverOpen(next);
        return;
      }
      coverCommit(coverFiles);
      coverClose();
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
        coverInput.value = '';
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
