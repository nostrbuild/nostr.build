import Alpine from 'alpinejs';

/**
 * Attaches the common Uppy event handlers shared between the standard
 * uploader (uppyStore) and the large-file uploader (uppyLargeStore).
 *
 * @param {object} uppyInstance - The Uppy instance to attach handlers to.
 * @param {object} options
 * @param {string} options.globalName - Window global key, e.g. '__nbUppy' or '__nbUppyLarge'.
 * @param {object} options.store - The Alpine store that owns `mainDialog`.
 * @param {object} options.profileStore - The profileStore Alpine store.
 */
export function attachCommonUppyHandlers(uppyInstance, { globalName, store, profileStore }) {
  const menuStore = Alpine.store('menuStore');
  const fileStore = Alpine.store('fileStore');

  return uppyInstance
    .on('upload', (data) => {
      console.debug('Upload started:', data);
      store.mainDialog.isLoading = true;
    })
    .on('complete', (result) => {
      const isInHomeFolder = menuStore.folders.find(folder => folder.name === menuStore.activeFolder)?.id === 0;
      const activeFolderMatch = result.successful.some(file => {
        const folderName = JSON.parse(file.meta.folderName);
        return folderName === menuStore.activeFolder || (isInHomeFolder && folderName === '');
      });
      if (result.failed.length === 0) {
        console.debug('Upload complete:', result);
        window[globalName].cancelAll();
        store.mainDialog.close();
        store.mainDialog.uploadProgress = null;
        store.mainDialog.clearFiles();
      } else {
        result.successful.forEach(file => {
          window[globalName].removeFile(file.id);
          store.mainDialog.removeFile(file.id);
        });
        store.mainDialog.open();
      }
      if (activeFolderMatch) {
        console.debug('Refreshing files:', menuStore.activeFolder);
        fileStore.refreshFoldersAfterFetch = true;
        fileStore.fetchFiles(menuStore.activeFolder, true);
      } else {
        menuStore.fetchFolders();
      }
      store.mainDialog.isLoading = false;
    })
    .on('progress', (progress) => {
      store.mainDialog.uploadProgress = progress > 0 ? progress + '%' : null;
    })
    .on('upload-progress', (file, progress) => {
      if (file.progress && file.progress.uploadComplete) {
        return;
      }

      const fileData = store.mainDialog.getFileById(file.id);
      const fileProgress = progress.bytesUploaded / progress.bytesTotal;
      if (fileData) {
        fileData.uppy.progress = Math.round(fileProgress * 100);
        fileData.uppy.bytesUploaded = progress.bytesUploaded;
      }
    })
    .on('upload-error', (file, error, response) => {
      console.debug('error with file:', file.id);
      console.debug('error message:', error);
      console.debug('error response:', response);
      const fileData = store.mainDialog.getFileById(file.id);
      if (fileData) {
        fileData.uppy.uploadError = true;
        fileData.uppy.errorMessage = error.message;
        fileData.uppy.errorResponse = response;
      }
      if (response && response.status === 401) {
        console.debug('User is not authenticated, redirecting to login page...');
        profileStore.unauthenticated = true;
      }
    })
    .on('info-visible', () => {
      const { info } = window[globalName].getState();
      console.debug('Full info object:', info);
      if (info && info.message) {
        let infoMessage = info.message;
        if (info.details) {
          infoMessage += ` - ${info.details}`;
        }
        console.debug(`Info (${info.type || 'unknown'}): ${infoMessage}`);
      }
    })
    .on('file-removed', (file) => {
      console.debug('File removed:', file);
      store.mainDialog.removeFile(file.id);
      fileStore.files = fileStore.files.filter(f => f.id !== file.id);
      if (window[globalName].getFiles().length === 0) {
        store.mainDialog.uploadProgress = null;
        store.mainDialog.isLoading = false;
        store.mainDialog.clearFiles();
      }
    })
    .on('upload-retry', (fileId) => {
      console.debug('Retrying upload:', fileId);
      const fileData = store.mainDialog.getFileById(fileId);
      if (fileData) {
        fileData.uppy.uploadError = false;
        fileData.uppy.errorMessage = '';
        fileData.uppy.errorResponse = null;
      }
    })
    .on('retry-all', () => {
      console.debug('Retrying all uploads');
      store.mainDialog.currentFiles.forEach(file => {
        file.uppy.uploadError = false;
        file.uppy.errorMessage = '';
        file.uppy.errorResponse = null;
      });
    })
    .on('thumbnail:generated', (file, preview) => {
      const fileData = store.mainDialog.getFileById(file.id);
      if (fileData) {
        fileData.uppy.preview = preview;
      }
    });
}

/**
 * Common file-added handler logic for folder resolution and meta assignment.
 * Used by both standard and large uploaders.
 *
 * @param {object} file - The Uppy file object.
 * @param {object} options
 * @param {string} options.globalName - Window global key.
 * @param {object} options.store - The Alpine store that owns `mainDialog`.
 * @param {string} options.mimeLabel - MIME label for the placeholder file ('uppy/upload' or 'uppy/upload-lg').
 */
export function handleFileAdded(file, { globalName, store, mimeLabel }) {
  const menuStore = Alpine.store('menuStore');
  const fileStore = Alpine.store('fileStore');

  const activeFolder = menuStore.activeFolder;
  const activeFolderId = menuStore.folders.find(folder => folder.name === activeFolder)?.id || 0;
  const defaultFolder = activeFolderId === 0 ? '' : activeFolder;
  const noTransform = menuStore.noTransform ?? false;
  console.debug('Active folder (Uppy):', activeFolder, activeFolderId, defaultFolder);
  const path = file.data.relativePath ?? file.data.webkitRelativePath;
  let folderHierarchy = [defaultFolder];
  let folderName = defaultFolder;

  if (path && activeFolderId === 0) {
    const folderPath = path.replace(/\\/g, '/');
    const folderPathParts = folderPath.split('/').filter(part => part !== '');
    folderHierarchy = folderPathParts.length > 1 ? folderPathParts.slice(0, -1) : [defaultFolder];
    folderName = folderHierarchy.length > 0 ? folderHierarchy[folderHierarchy.length - 1] : defaultFolder;
    store.mainDialog.uploadFolder = folderName;
    const folderExists = menuStore.folders.some(folder => folder.name === folderName);
    if (!folderExists) {
      const folder = {
        id: folderName,
        name: folderName,
        icon: folderName.substring(0, 1).toUpperCase(),
        route: '#',
        allowDelete: false
      };
      menuStore.folders.push(folder);
      menuStore.folders = menuStore.folders.sort((a, b) => a.name.localeCompare(b.name));
    }
  } else {
    store.mainDialog.uploadFolder = defaultFolder;
  }
  console.debug('Folder name', folderName);
  console.debug('Folder hierarchy', folderHierarchy);
  window[globalName].setFileMeta(file.id, {
    folderName: JSON.stringify(folderName),
    folderHierarchy: JSON.stringify(folderHierarchy),
    noTransform: noTransform,
  });
  console.debug('File added:', file);
  const currentFile = {
    id: file.id,
    name: file.name,
    mime: mimeLabel,
    media_type: 'uppy',
    size: file.size,
    loaded: false,
    folder: folderName,
    uppy: {
      uploadComplete: false,
      uploadError: false,
      errorMessage: '',
      errorResponse: null,
      progress: 0,
      bytesUploaded: 0,
    },
  };
  store.mainDialog.addFile(currentFile);
  if (folderName === defaultFolder) {
    fileStore.injectFile(currentFile);
  }
}

/**
 * Common upload-success handler logic.
 *
 * @param {object} file - The Uppy file object.
 * @param {object} response - The upload response.
 * @param {object} options
 * @param {string} options.globalName - Window global key.
 * @param {object} options.store - The Alpine store that owns `mainDialog`.
 * @param {function} options.validateFileData - Returns the file data object from response, or null if invalid.
 * @param {function} [options.onSuccess] - Optional callback after successful processing (e.g. image precaching).
 */
export function handleUploadSuccess(file, response, { globalName, store, validateFileData, onSuccess }) {
  const menuStore = Alpine.store('menuStore');
  const fileStore = Alpine.store('fileStore');

  console.debug('Upload result:', response);
  file.progress.uploadComplete = true;

  const fd = validateFileData(response);

  if (!fd) {
    console.error('Invalid file data received:', response?.body);
    window[globalName].removeFile(file.id);
    return;
  }

  console.debug('File uploaded:', fd);
  const folderName = JSON.parse(file.meta.folderName || '""');
  const fileFolderName = folderName === '' ? menuStore.folders?.find(folder => folder.id === 0)?.name || '' : folderName;
  menuStore.updateFolderStatsFromFile(fd, fileFolderName, true);
  if (menuStore.activeFolder === fileFolderName) {
    store.mainDialog.removeFile(file.id);
    fileStore.files = fileStore.files.filter(f => f.id !== file.id);
    if (!fileStore.files.some(f => f.id === fd.id)) {
      fileStore.injectFile(fd);
    }
  } else {
    store.mainDialog.removeFile(file.id);
  }

  if (typeof onSuccess === 'function') {
    onSuccess(fd);
  }

  setTimeout(() => {
    window[globalName].removeFile(file.id);
  }, 1000);
}
