let currentPath = null;

$(document).ready(() => {
  if ($('body').data('file-mode-select')) {
    wait.on('[data-file-mode-select-item]', (select) => {
      $(select).removeClass('d-none');
    });
  }

  const formatBytes = (bytes, decimals = 2) => {
    if (!+bytes) return '0 b';
    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['b', 'kb', 'mb', 'gb', 'tb'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return `${parseFloat((bytes / Math.pow(k, i)).toFixed(dm))} ${sizes[i]}`;
  };

  const selectedSize = () => {
    let totalSize = 0;
    $('.ui-selected [data-admin-item-size]').each((i, e) => {
      totalSize += parseInt($(e).data('admin-item-size'));
    });
    return formatBytes(totalSize);
  };

  const removeSelection = () => {
    $('body > [data-admin-contextmenu-remove-all]').remove();
    ContextMenu.hideAll();
  };

  ContextMenu.on('show', (el) => {
    $('body > [data-admin-contextmenu-remove-all]').remove();

    const selectedItems = $('[data-item-selectable] .ui-selected');
    if (selectedItems.length) {
      selectedItems.removeClass('ui-selected');
    }

    $(el).closest('[data-item-selectable-item]').addClass('ui-selected');
  });

  wait.on('[data-item-selectable]', (selectable) => {
    $(selectable).selectable({
      cancel: '[data-item-selectable] > *',
      start: () => removeSelection(),
      unselected: () => removeSelection(),
      stop: (event) => {
        if ($('.ui-selected [data-admin-item-size]').length) {
          $('body').append($('[data-admin-contextmenu-remove-all-template]').html());

          const removeAllContextMenu = $('body > [data-admin-contextmenu-remove-all]');
          removeAllContextMenu.css('transform', 'translate(' + event.clientX + 'px, ' + event.clientY + 'px)')
          removeAllContextMenu.addClass('show');
          removeAllContextMenu.find('[data-admin-contextmenu-remove-all-size]').html(selectedSize());
        }
      }
    });
  });

  $(document).on('contextmenu', () => removeSelection());
});

const viewFile = (path) => {
  $.post('/modal/view', {path}, (content) => {
    modal.html(locale('Preview'), content, {size: 'xLarge'});
    setTimeout(() => wheelzoom(document.querySelector('img[data-zoom]')), 300);
  });
};

const uploadFromComputer = () => {
  $.post('/modal/uploadFromComputer', {path: currentPath}, (modalHtml) => {
    modal.html(locale('Upload from computer'), modalHtml);
  });
};

$(document).on('dragenter', '[data-files]', function (e) {
  $(this).closest('.card').addClass('border-primary').removeClass('border-dark');
});

$(document).on('dragleave', '[data-files]', function (e) {
  $(this).closest('.card').removeClass('border-primary').addClass('border-dark');
});

$(document).on('drop', '[data-files]', function () {
  $('[data-files]').off('change');
  $(this).closest('.card').removeClass('border-primary').addClass('border-dark');
});

$(document).on('change', '[data-files]', function () {
  uploadFiles(
    $(this)[0].files,
    (percents) => $('[data-progress]').css('top', 'calc(100% - ' + percents + '%'),
    () => openFolder(currentPath)
  );
});

const uploadFiles = (files, onProgress, onFinish) => {
  const formData = new FormData();
  formData.append('path', currentPath);

  for (let i = 0; i < files.length; i++) {
    formData.append('files[]', files[i]);
  }
  $.ajax({
    xhr: function () {
      const xhr = $.ajaxSettings.xhr();
      if (xhr.upload) {
        xhr.upload.addEventListener('progress', (event) => {
          let percent = 0;
          const position = event.loaded || event.position;
          const total = event.total;
          if (event.lengthComputable) {
            percent = Math.ceil(position / total * 100);
          }
          onProgress(percent);
        }, false);
      }
      return xhr;
    },
    url: '/index/uploadFile',
    type: "POST",
    contentType: false,
    processData: false,
    cache: false,
    data: formData,
    success: function () {
      onProgress(100);
      onFinish();
    }
  });
};

const openFolder = (path, dontHidePopup) => {
  if (!dontHidePopup) {
    modal.hide();
  }
  $.post('/index/index', {path}, function (data) {
    currentPath = path;
    $('[data-list]').html(data);
  });
};

const removeFile = (path, force) => {
  if (force) {
    modal.hide(() => {
      $.post('/index/deleteFile', {path}, () => {
        openFolder(currentPath);
      });
    });
  } else {
    $.post('/modal/remove', {path}, (modalHtml) => {
      modal.html(locale('Remove file?'), modalHtml);
    });
  }
};

const removeFolder = (path, force) => {
  if (force) {
    modal.hide(() => {
      $.post('/index/deleteFolder', {path}, () => {
        openFolder(currentPath);
      });
    });
  } else {
    $.post('/modal/removeFolder', {path}, (modalHtml) => {
      modal.html(locale('Remove folder?'), modalHtml);
    });
  }
};

const createFolder = (force) => {
  if (force) {
    $.post('/index/createFolder', {path: currentPath, name: $('[data-foldername]').val()})
      .done((folder) => {
        openFolder(folder.path);
      })
      .fail((err) => {
        $('[data-createfolder-form] [data-error]').html(err.responseJSON.message);
      });
  } else {
    $.post('/modal/createFolder', {path: currentPath}, (modalHtml) => {
      modal.html(locale('Create folder'), modalHtml);
      setTimeout(() => $('[data-foldername]').focus(), 500);
    });
  }
};

const uploadByUrl = (force) => {
  if (force) {
    $.post('/index/uploadByUrl', {path: currentPath, url: $('[data-url]').val()})
      .done((file) => {
        openFolder(currentPath, true);
        viewFile(file.path);
      })
      .fail((err) => {
        $('[data-uploadbyurl-form] [data-error]').html(err.responseJSON.message);
      });
  } else {
    $.post('/modal/uploadByUrl', {path: currentPath}, (modalHtml) => {
      modal.html(locale('Upload by URL'), modalHtml);
    });
  }
};

const searchFiles = () => {
  const searchInput = $('[data-search]');
  const query = searchInput.val();
  if (query.length < 3) {
    setTimeout(() => searchInput.focus(), 500);
    return false;
  }
  modal.hide();
  $.post('/index/search', {query}, (l) => $('[data-list]').html(l));
};

const selectFile = (file) => {
  console.log(file);
  window.parent.postMessage({file}, "*");
};

const removeSelected = () => {
  const paths = [];
  $('.ui-selected [data-admin-item-size]').each((i, e) => {
    paths.push($(e).data('admin-item-path'));
  });

  $('.ui-selected').removeClass('ui-selected');
  $('body > [data-admin-contextmenu-remove-all]').remove();

  modal.question('Remove all?').then(() => {
    $.post('/index/removeMultiple', {paths}, () => {
      openFolder(currentPath);
    });
  });
};

