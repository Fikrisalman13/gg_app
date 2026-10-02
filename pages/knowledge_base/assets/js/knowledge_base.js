(function ($) {
  'use strict';

  const selectors = {
    addButton: '#kb-add-btn',
    archiveArticles: '#kb-archive-articles',
    archiveBack: '#kb-archive-back',
    archiveBreadcrumb: '#kb-archive-breadcrumb',
    archiveContent: '#kb-archive-content',
    archiveFolders: '#kb-archive-folders',
    archiveSearch: '#kb-archive-search',
    archiveSearchInput: '#kb-archive-search-input',
    archiveStatus: '#kb-archive-status',
    category: '#kb-category',
    detailBagian: '#kb-detail-bagian',
    detailCategory: '#kb-detail-category',
    detailMeta: '#kb-detail-meta',
    detailTitle: '#kb-detail-title',
    form: '#kb-form',
    id: '#kb-id',
    message: '#kb-message',
    modal: '#kb-modal',
    modalTitle: '#kb-modal-title',
    saveButton: '#kb-save-btn',
    shareAll: '#kb-share-all',
    shareBagianList: '#kb-share-bagian-list',
    shareForm: '#kb-share-form',
    shareId: '#kb-share-id',
    shareModal: '#kb-share-modal',
    shareSearch: '#kb-share-search',
    shareSelected: '#kb-share-selected',
    shareSpecific: '#kb-share-specific',
    shareSpecificPanel: '#kb-share-specific-panel',
    shareTitle: '#kb-share-article-title',
    shareSave: '#kb-share-save',
    view: '#kb-view'
  };

  let currentZoom = 1;
  let isDraggingImage = false;
  let imageStartX = 0;
  let imageStartY = 0;
  let imageTranslateX = 0;
  let imageTranslateY = 0;
  let lastImageTranslateX = 0;
  let lastImageTranslateY = 0;

  function showError(message) {
    Swal.fire('Error', message || 'Proses gagal.', 'error');
  }

  const archiveState = {
    level: 'root',
    bagianId: 0,
    categoryId: 0,
    bagianName: '',
    categoryName: '',
    search: ''
  };

  /** Render archive breadcrumb and navigation state. */
  function renderArchiveBreadcrumb() {
    const items = ['<a href="#" data-level="root">Knowledge Base</a>'];
    if (archiveState.bagianId) {
      items.push(`<a href="#" data-level="bagian">${$('<div>').text(archiveState.bagianName).html()}</a>`);
    }
    if (archiveState.categoryId) {
      items.push(`<span>${$('<div>').text(archiveState.categoryName).html()}</span>`);
    }
    $(selectors.archiveBreadcrumb).html(items.join(' <span class="mx-2">/</span> '));
    $(selectors.archiveBack).prop('disabled', archiveState.level === 'root');
  }

  /** Render archive folders or article files. */
  function renderArchive(response) {
    const folders = response.folders || [];
    const articles = response.articles || [];
    const folderMarkup = folders.map(folder => `<div class="col-md-4 col-lg-3 mb-3"><div class="card kb-folder-card h-100" data-folder-id="${folder.id}"><div class="card-body"><i class="fas fa-folder kb-folder-icon"></i><h5 class="mt-2 mb-1">${$('<div>').text(folder.name).html()}</h5><small class="text-muted">${folder.article_count || 0} artikel</small></div></div></div>`).join('');
    const articleMarkup = articles.map(article => { const manageMarkup = article.can_manage ? `<button type="button" class="btn btn-secondary btn-sm action-btn kb-action" data-action="share" data-id="${article.id}" title="Bagikan artikel"><i class="fas fa-share-alt"></i></button><button type="button" class="btn btn-warning btn-sm action-btn kb-action" data-action="edit" data-id="${article.id}" title="Edit artikel"><i class="fas fa-edit"></i></button><button type="button" class="btn btn-danger btn-sm action-btn kb-action" data-action="delete" data-id="${article.id}" title="Hapus artikel"><i class="fas fa-trash"></i></button>` : ''; const shareMarker = article.can_manage && article.is_shared ? '<span class="badge badge-info ml-1" title="Artikel dibagikan ke Bagian lain"><i class="fas fa-share-alt"></i> Dibagikan</span>' : ''; return `<div class="list-group-item kb-article-card" data-article-id="${article.id}"><div class="kb-article-main kb-article-open" data-id="${article.id}" role="button" tabindex="0"><i class="fas fa-file-alt kb-article-icon mr-2"></i><div class="kb-article-meta"><strong>${article.title} ${shareMarker}</strong><div class="small text-muted">${article.bagian} / ${article.category} · ${article.created_by} · ${article.updated_at}</div></div></div><div class="action-button-group"><button type="button" class="btn btn-info btn-sm action-btn kb-action" data-action="view" data-id="${article.id}" title="Lihat artikel"><i class="fas fa-eye"></i></button>${manageMarkup}</div></div>`; }).join('');
    $(selectors.archiveFolders).html(folderMarkup);
    $(selectors.archiveArticles).html(articleMarkup);
    if (archiveState.search && archiveState.level === 'root') {
      $(selectors.archiveStatus).text(articles.length ? `${articles.length} artikel cocok dengan pencarian.` : 'Tidak ada artikel yang cocok.');
    } else {
      $(selectors.archiveStatus).text(folders.length || articles.length ? '' : 'Arsip kosong.');
    }
  }

  /** Load current archive level from server. */
  function loadArchive() {
    renderArchiveBreadcrumb();
    $(selectors.archiveContent).addClass('is-loading');
    $(selectors.archiveStatus).text('Sedang memuat...');
    const params = { level: archiveState.level, search: archiveState.search };
    if (archiveState.level !== 'root') params.bagian_id = archiveState.bagianId;
    if (archiveState.level === 'category') params.category_id = archiveState.categoryId;
    $.getJSON('archive.php', params).done(response => {
      if (!response.ok) { showError(response.message); return; }
      renderArchive(response);
    }).fail(() => showError('Gagal membaca arsip.')).always(() => {
      window.setTimeout(() => $(selectors.archiveContent).removeClass('is-loading'), 120);
    });
  }

  function openFolder() {
    const id = Number($(this).data('folder-id'));
    const name = $(this).find('h5').text();
    archiveState.search = '';
    $(selectors.archiveSearchInput).val('');
    if (archiveState.level === 'root') { archiveState.bagianId = id; archiveState.bagianName = name; archiveState.level = 'bagian'; }
    else { archiveState.categoryId = id; archiveState.categoryName = name; archiveState.level = 'category'; }
    loadArchive();
  }

  function goBackArchive() {
    archiveState.search = '';
    $(selectors.archiveSearchInput).val('');
    if (archiveState.level === 'category') { archiveState.categoryId = 0; archiveState.categoryName = ''; archiveState.level = 'bagian'; }
    else if (archiveState.level === 'bagian') { archiveState.bagianId = 0; archiveState.bagianName = ''; archiveState.level = 'root'; }
    loadArchive();
  }

  /** Initialize folder archive view. */
  function initArchive() {
    $(selectors.archiveFolders).on('click', '.kb-folder-card', openFolder);
    $(selectors.archiveArticles).on('click', '.kb-article-card', function (event) {
      if ($(event.target).closest('.kb-action').length) {
        return;
      }
      openArticle($(this).data('article-id'), 'view');
    });
    $(selectors.archiveArticles).on('click', '.kb-action', handleTableAction);
    $(selectors.archiveBack).on('click', goBackArchive);
    $(selectors.archiveSearch).on('click', () => { archiveState.search = $(selectors.archiveSearchInput).val().trim(); loadArchive(); });
    $(selectors.archiveSearchInput).on('keydown', event => { if (event.key === 'Enter') $(selectors.archiveSearch).trigger('click'); });
    $(selectors.archiveBreadcrumb).on('click', 'a', function (event) { event.preventDefault(); if ($(this).data('level') === 'root') { archiveState.level = 'root'; archiveState.bagianId = archiveState.categoryId = 0; } else { archiveState.level = 'bagian'; archiveState.categoryId = 0; } loadArchive(); });
    loadArchive();
  }

  /** Initialize category selector with per-Bagian tags. */
  function initCategorySelect() {
    $(selectors.category).select2({
      ajax: {
        data: params => ({ term: params.term || '' }),
        dataType: 'json',
        delay: 250,
        processResults: response => ({ results: response.results || [] }),
        url: 'categories.php'
      },
      dropdownParent: $(selectors.modal),
      tags: true,
      theme: 'bootstrap4'
    });
  }

  /** Upload one Summernote image file and insert returned URL. */
  function uploadSummernoteImage(file) {
    const formData = new FormData();
    formData.append('image', file);

    $.ajax({
      contentType: false,
      data: formData,
      method: 'POST',
      processData: false,
      url: 'upload_image.php'
    }).done(response => {
      if (response.ok) {
        $(selectors.message).summernote('insertImage', response.url);
        return;
      }

      showError(response.message || 'Upload gambar gagal.');
    }).fail(() => showError('Upload gambar gagal.'));
  }

  /** Initialize Summernote with custom image upload endpoint. */
  function initSummernote() {
    $(selectors.message).summernote({
      callbacks: {
        onImageUpload: files => {
          Array.from(files).forEach(uploadSummernoteImage);
        }
      },
      height: 260
    });
  }

  /** Reset modal state before create, edit, or view mode. */
  function resetForm() {
    $(selectors.form)[0].reset();
    $(selectors.id).val('');
    $(selectors.category).empty().trigger('change');
    $(selectors.message).summernote('enable');
    $(selectors.message).summernote('code', '');
    $('.note-editor').show();
    $('.kb-detail-edit-only').show();
    $('.kb-detail-message-editor').show();
    $(selectors.view).addClass('d-none').html('');
    $(selectors.detailMeta).addClass('d-none');
    $(selectors.detailTitle).text('');
    $(selectors.detailCategory).text('');
    $(selectors.detailBagian).text('');
    $(selectors.saveButton)
      .show()
      .prop('disabled', false)
      .html('<i class="fas fa-save"></i> Simpan');
  }

  /** Apply zoom and pan transform to preview image. */
  function updateImageZoom() {
    const image = $('#kb-image-preview')[0];
    if (!image) {
      return;
    }

    currentZoom = Math.max(1, Math.min(5, currentZoom));
    image.style.transform = `translate(${imageTranslateX}px, ${imageTranslateY}px) scale(${currentZoom})`;
    image.style.transformOrigin = 'center center';
    image.style.cursor = currentZoom > 1
      ? (isDraggingImage ? 'grabbing' : 'grab')
      : 'zoom-in';
    image.style.transition = isDraggingImage ? 'none' : 'transform 0.1s ease-out';

    if (currentZoom === 1) {
      imageTranslateX = 0;
      imageTranslateY = 0;
      lastImageTranslateX = 0;
      lastImageTranslateY = 0;
    }
  }

  /** Zoom preview image with mouse wheel. */
  function handleImageWheel(event) {
    if (!$('#kb-image-modal').hasClass('show')) {
      return;
    }

    event.preventDefault();
    currentZoom += event.originalEvent.deltaY < 0 ? 0.25 : -0.25;

    if (currentZoom <= 1) {
      currentZoom = 1;
      imageTranslateX = 0;
      imageTranslateY = 0;
      lastImageTranslateX = 0;
      lastImageTranslateY = 0;
    }

    updateImageZoom();
  }

  /** Start panning when preview is zoomed. */
  function handleImageMouseDown(event) {
    if (currentZoom <= 1) {
      return;
    }

    isDraggingImage = true;
    imageStartX = event.clientX;
    imageStartY = event.clientY;
    event.preventDefault();
    updateImageZoom();
  }

  /** Move zoomed preview image. */
  function handleImageMouseMove(event) {
    if (!isDraggingImage || currentZoom <= 1) {
      return;
    }

    imageTranslateX = lastImageTranslateX + event.clientX - imageStartX;
    imageTranslateY = lastImageTranslateY + event.clientY - imageStartY;
    event.preventDefault();
    updateImageZoom();
  }

  /** Finish panning preview image. */
  function handleImageMouseUp() {
    if (!isDraggingImage) {
      return;
    }

    isDraggingImage = false;
    lastImageTranslateX = imageTranslateX;
    lastImageTranslateY = imageTranslateY;
    updateImageZoom();
  }

  /** Reset preview zoom and remove global image interaction handlers. */
  function resetImageZoom() {
    currentZoom = 1;
    isDraggingImage = false;
    imageTranslateX = 0;
    imageTranslateY = 0;
    lastImageTranslateX = 0;
    lastImageTranslateY = 0;
    $(document).off('wheel.kbImageZoom');
    $(document).off('mousemove.kbImageZoom mouseup.kbImageZoom');
    updateImageZoom();
  }

  /** Open full-size image in Knowledge Base preview modal. */
  function openImagePreview(image) {
    const source = $(image).attr('src');
    const alt = $(image).attr('alt') || 'Preview gambar';

    if (!source) {
      return;
    }

    resetImageZoom();
    $('#kb-image-preview').attr({ alt, src: source });
    $('#kb-image-modal').modal('show');
    $(document).on('wheel.kbImageZoom', handleImageWheel);
    $(document).on('mousemove.kbImageZoom', handleImageMouseMove);
    $(document).on('mouseup.kbImageZoom', handleImageMouseUp);
    $('#kb-image-preview').off('mousedown.kbImageZoom').on('mousedown.kbImageZoom', handleImageMouseDown);
  }

  /** Bind image preview behavior in editor and article detail content. */
  function bindImagePreview() {
    $(selectors.message).off('click.kbImage').on('click.kbImage', 'img', function (event) {
      event.preventDefault();
      event.stopPropagation();
      openImagePreview(this);
    });

    $(selectors.view).off('click.kbImage').on('click.kbImage', 'img', function (event) {
      event.preventDefault();
      event.stopPropagation();
      openImagePreview(this);
    });
  }

  function openCreateModal() {
    resetForm();
    $(selectors.modalTitle).text('Tambah Artikel');
    if (archiveState.categoryId) {
      const option = new Option(archiveState.categoryName, archiveState.categoryName, true, true);
      $(selectors.category).append(option).trigger('change');
    }
    $(selectors.modal).modal('show');
  }

  /** Set Select2 category value from loaded article data. */
  function setSelectedCategory(article) {
    const option = new Option(article.category_name, article.category_name, true, true);
    $(selectors.category).append(option).trigger('change');
  }

  /** Fill editable form fields from loaded article data. */
  function fillArticleForm(article) {
    $(selectors.id).val(article.id);
    $('#kb-title').val(article.title);
    setSelectedCategory(article);
    $(selectors.message).summernote('code', article.message_html);
  }

  /** Load article and open modal in view or edit mode. */
  function openArticle(id, action) {
    $.getJSON('get_article.php', { id }).done(response => {
      if (!response.ok) {
        showError(response.message);
        return;
      }

      resetForm();
      fillArticleForm(response.article);
      $('#kb-bagian').val(response.article.owner_bagian || response.article.bagian || '');
      bindImagePreview();

      if (action === 'view') {
        $(selectors.modalTitle).text('Detail Artikel');
        $(selectors.detailTitle).text(response.article.title);
        $(selectors.detailCategory).text(`Kategori: ${response.article.category_name}`);
        $(selectors.detailBagian).text(`Bagian: ${response.article.bagian}`);
        $(selectors.detailMeta).removeClass('d-none');
        $(selectors.view).removeClass('d-none').html(response.article.message_html);
        $('.kb-detail-edit-only').hide();
        $('.kb-detail-message-editor').hide();
        $('.note-editor').hide();
        $(selectors.saveButton).hide();
      } else {
        $('.kb-detail-edit-only').show();
        $('.kb-detail-message-editor').show();
        $(selectors.detailMeta).addClass('d-none');
      }

      $(selectors.modal).modal('show');
    }).fail(() => showError('Gagal membaca artikel.'));
  }

  function renderShareBagianList(items, selectedIds, ownerId) {
    const selected = new Set(selectedIds.map(String));
    $(selectors.shareBagianList).html(items.map(item => {
      const isOwner = String(item.id) === String(ownerId);
      return `<div class="kb-share-bagian-option${isOwner ? ' is-owner' : ''}" data-name="${$('<div>').text(item.name).html().toLowerCase()}"><div class="custom-control custom-checkbox"><input class="custom-control-input kb-share-check" type="checkbox" id="kb-share-check-${item.id}" value="${item.id}"${selected.has(String(item.id)) ? ' checked' : ''}${isOwner ? ' disabled' : ''}><label class="custom-control-label" for="kb-share-check-${item.id}">${$('<div>').text(item.name).html()}${isOwner ? ' <span class="badge badge-info">Pemilik</span>' : ''}</label></div></div>`;
    }).join(''));
    updateShareSelected();
  }

  function updateShareSelected() {
    const names = $(selectors.shareBagianList).find('.kb-share-check:checked').map(function () { return $(this).next('label').text().replace(' Pemilik', '').trim(); }).get();
    $(selectors.shareSelected).html(names.length ? `Terpilih: ${names.map(name => `<span class="badge badge-light">${$('<div>').text(name).html()}</span>`).join('')}` : 'Belum ada Bagian dipilih.');
  }

  function applyShareMode() {
    $(selectors.shareSpecificPanel).toggle($(selectors.shareSpecific).is(':checked'));
  }

  function openShareModal(id) {
    $.getJSON('get_article.php', { id }).done(response => {
      if (!response.ok) { showError(response.message); return; }
      $(selectors.shareId).val(response.article.id);
      $(selectors.shareTitle).text(response.article.title);
      $.getJSON('bagians.php').done(itemsResponse => {
        if (!itemsResponse.ok) { showError(itemsResponse.message); return; }
        const selected = (response.article.shared_bagian || []).map(item => item.id);
        $(selectors.shareSpecific).prop('checked', true);
        renderShareBagianList(itemsResponse.items, selected, response.article.bagian_id);
        applyShareMode();
        $(selectors.shareModal).modal('show');
      }).fail(() => showError('Gagal membaca daftar Bagian.'));
    }).fail(() => showError('Gagal membaca artikel.'));
  }

  function saveSharing(event) {
    event.preventDefault();
    const button = $(selectors.shareSave).prop('disabled', true);
    const mode = $(selectors.shareAll).is(':checked') ? 'all' : 'specific';
    const formData = [{ name: 'id', value: $(selectors.shareId).val() }, { name: 'share_mode', value: mode }];
    if (mode === 'all') {
      formData.push({ name: 'shared_bagian_ids[]', value: 'all' });
    } else {
      $(selectors.shareBagianList).find('.kb-share-check:checked').each(function () { formData.push({ name: 'shared_bagian_ids[]', value: $(this).val() }); });
    }
    $.post('share_article.php', formData).done(response => {
      if (!response.ok) { showError(response.message); return; }
      $(selectors.shareModal).modal('hide');
      Swal.fire('Berhasil', 'Bagian berbagi diperbarui.', 'success');
      loadArchive();
    }).fail(() => showError('Gagal menyimpan Bagian berbagi.')).always(() => button.prop('disabled', false));
  }

  /** Confirm and soft-delete an article. */
  function deleteArticle(id) {
    Swal.fire({
      cancelButtonText: 'Batal',
      confirmButtonText: 'Hapus',
      icon: 'warning',
      showCancelButton: true,
      text: 'Artikel akan disembunyikan dari list.',
      title: 'Hapus artikel?'
    }).then(result => {
      if (!result.isConfirmed) {
        return;
      }

      $.post('delete_article.php', { id }).done(response => {
        if (response.ok) {
          Swal.fire('Berhasil', 'Artikel dihapus.', 'success');
          loadArchive();
          return;
        }

        showError(response.message);
      }).fail(() => showError('Gagal menghapus artikel.'));
    });
  }

  /** Dispatch table business actions from scoped action buttons. */
  function handleTableAction() {
    const id = $(this).data('id');
    const action = $(this).data('action');

    if (action === 'delete') {
      deleteArticle(id);
      return;
    }
    if (action === 'share') {
      openShareModal(id);
      return;
    }

    openArticle(id, action);
  }

  /** Submit article form with double-submit protection. */
  function saveArticle(event) {
    event.preventDefault();

    const saveButton = $(selectors.saveButton);
    const formData = $(selectors.form).serializeArray();
    formData.push({ name: 'message_html', value: $(selectors.message).summernote('code') });

    saveButton
      .prop('disabled', true)
      .html('<i class="fas fa-spinner fa-spin"></i> Menyimpan...');

    $.post('save_article.php', formData).done(response => {
      if (response.ok) {
        $(selectors.modal).modal('hide');
        Swal.fire('Berhasil', 'Artikel tersimpan.', 'success');
        loadArchive();
        return;
      }

      showError(response.message);
    }).fail(() => showError('Gagal menyimpan artikel.')).always(() => {
      saveButton
        .prop('disabled', false)
        .html('<i class="fas fa-save"></i> Simpan');
    });
  }

  $(function () {
    initCategorySelect();
    initSummernote();
    bindImagePreview();
    $('#kb-image-modal').on('hidden.bs.modal', resetImageZoom);

    $(selectors.addButton).on('click', openCreateModal);
    $(selectors.form).on('submit', saveArticle);
    $(selectors.shareForm).on('submit', saveSharing);
    $(selectors.shareForm).on('change', '.kb-share-check', updateShareSelected);
    $(selectors.shareAll).on('change', applyShareMode);
    $(selectors.shareSpecific).on('change', applyShareMode);
    $(selectors.shareSearch).on('input', function () {
      const query = $(this).val().toLowerCase().trim();
      $(selectors.shareBagianList).children().each(function () { $(this).toggle(!query || $(this).data('name').includes(query)); });
    });
    initArchive();
  });
})(jQuery);
