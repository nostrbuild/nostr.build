import Alpine from 'alpinejs';

Alpine.effect(() => {
  const uppyStore = Alpine.store('uppyStore');
  const largeFileStore = Alpine.store('uppyLargeStore');
  const urlImportStore = Alpine.store('urlImportStore');
  const GAI = Alpine.store('GAI');

  const isUploading = Boolean(uppyStore?.mainDialog?.isLoading);
  const isLargeFileUploading = Boolean(largeFileStore?.mainDialog?.isLoading);
  const isImporting = Boolean(urlImportStore?.isLoading);
  const isGenerating = Boolean(GAI?.ImageLoading);
  const isUploadingFiles = isUploading || isLargeFileUploading || isImporting || isGenerating;

  if (isUploadingFiles) {
    window.onbeforeunload = function (e) {
      e.preventDefault();
      e.returnValue = 'Are you sure you want to leave? Your files are still uploading.';
      return e.returnValue;
    };
  } else {
    window.onbeforeunload = null;
  }
});
