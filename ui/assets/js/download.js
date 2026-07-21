(function (window) {
  'use strict';

  function text(content, filename, type = 'text/plain;charset=utf-8') {
    const blob = new Blob([content], { type });
    blobFile(blob, filename);
  }

  function blobFile(blob, filename) {
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');

    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();

    URL.revokeObjectURL(url);
  }

  window.AbhiprayaDownload = { text, blob: blobFile };
})(window);
