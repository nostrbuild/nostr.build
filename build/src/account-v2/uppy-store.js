import Uppy from '@uppy/core';
import Dashboard from '@uppy/dashboard';
import XHRUpload from '@uppy/xhr-upload';
import Webcam from '@uppy/webcam';
import DropTarget from '@uppy/drop-target';
import Alpine from 'alpinejs';
import { createUppyDialogState } from './uppy-dialog-state';
import { mimeTypesImages, mimeTypesAudio, mimeTypesVideo, mimeTypesAddonDocs, mimeTypesAddonExtra } from './mime-types';
import { attachCommonUppyHandlers, handleFileAdded, handleUploadSuccess } from './uppy-shared-handlers';

const imageVariantsPrecache = (...args) => window.imageVariantsPrecache(...args);
const formatBytes = (...args) => window.formatBytes(...args);

Alpine.store('uppyStore', {
  instance: null,
  mainDialog: createUppyDialogState(),
  largeFileThresholdBytes: 40 * 1024 * 1024,
  getAllowedFileTypes(accountLevel = 0) {
    const extsAddonDocs = Object.values(mimeTypesAddonDocs).map(ext => `.${ext}`);
    const extsAddonExtra = Object.values(mimeTypesAddonExtra).map(ext => `.${ext}`);

    const mimesImages = Object.keys(mimeTypesImages);
    const mimesAudio = Object.keys(mimeTypesAudio);
    const mimesVideo = Object.keys(mimeTypesVideo);
    const mimesAddonDocs = Object.keys(mimeTypesAddonDocs);
    const mimesAddonExtra = Object.keys(mimeTypesAddonExtra);

    switch (accountLevel) {
      case 1:
      case 10:
      case 99:
        console.debug('All file types allowed.');
        return [...mimesImages, ...mimesAudio, ...mimesVideo, ...mimesAddonDocs, ...mimesAddonExtra, ...extsAddonDocs, ...extsAddonExtra];
      case 2:
        console.debug('All file types allowed except for archives.');
        return [...mimesImages, ...mimesAudio, ...mimesVideo, ...mimesAddonDocs, ...extsAddonDocs];
      default:
        console.debug('Default file types allowed.');
        return [...mimesImages, ...mimesAudio, ...mimesVideo];
    }
  },
  isFileEligibleForLargeUploader(file, accountLevel = 0) {
    const uppyLargeStore = Alpine.store('uppyLargeStore');
    if (!uppyLargeStore || typeof uppyLargeStore.getAllowedFileTypes !== 'function') {
      return false;
    }

    const allowed = uppyLargeStore.getAllowedFileTypes(accountLevel) || [];
    const fileType = (file?.type || '').toLowerCase();
    const fileName = (file?.name || '').toLowerCase();
    const fileExt = fileName.includes('.') ? `.${fileName.split('.').pop()}` : '';

    return allowed.includes(fileType) || (!!fileExt && allowed.includes(fileExt));
  },
  routeFileToLargeUploader(file, profileStore) {
    const uppyLargeStore = Alpine.store('uppyLargeStore');
    if (!uppyLargeStore || !window.__nbUppyLarge || typeof window.__nbUppyLarge.addFile !== 'function') {
      return false;
    }

    const accountLevel = profileStore.profileInfo?.accountLevel || 0;
    const isLargeEligible = Boolean(profileStore.profileInfo?.isLargeUploadEligible) && accountLevel >= 1;
    if (!isLargeEligible) {
      return false;
    }

    if (!this.isFileEligibleForLargeUploader(file, accountLevel)) {
      return false;
    }

    if ((file?.size || 0) < this.largeFileThresholdBytes) {
      return false;
    }

    try {
      window.__nbUppyLarge.addFile({
        name: file.name,
        type: file.type,
        data: file.data,
        source: 'Local',
      });
      window.__nbUppy.info(`Routed ${file.name || 'file'} to Large Files uploader`, 'info', 2500);
      uppyLargeStore.mainDialog.open();
      this.mainDialog.close(true);
      return true;
    } catch (error) {
      if (!String(error?.message || '').includes('already exists')) {
        console.error('Error routing file to large uploader:', error);
      }
      return false;
    }
  },
  instantiateUppy(el, dropTarget, onDropCallback, onDragOverCallback, onDragLeaveCallback) {
    this.mainDialog.dialogEl = el;
    console.debug('Instantiating Uppy...');
    const profileStore = Alpine.store('profileStore');
    const isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent);
    const dropTargetEl = typeof dropTarget === 'string' ? document.querySelector(dropTarget) : dropTarget;

    if (window.__nbUppy && typeof window.__nbUppy.destroy === 'function') {
      try { window.__nbUppy.destroy(); } catch (_) { }
    }
    if (typeof window.__nbUppySafariDropCleanup === 'function') {
      window.__nbUppySafariDropCleanup();
      window.__nbUppySafariDropCleanup = null;
    }

    let uppyInstance = new Uppy({
      debug: false,
      autoProceed: true,
      allowMultipleUploadBatches: false,
      restrictions: {
        maxFileSize: 4096 * 1024 * 1024,
        maxTotalFileSize: profileStore.profileInfo?.storageRemaining || 4096 * 1024 * 1024,
        allowedFileTypes: this.getAllowedFileTypes(),
      },
      onBeforeFileAdded: (currentFile) => {
        const fileType = (currentFile?.type || '').split('/')[0];

        if (fileType !== 'image' && fileType !== 'video' && fileType !== 'audio') {
          if (currentFile.size > 1024 ** 3) {
            window.__nbUppy.info(`Skipping file ${currentFile.name || 'Unknown'} because it's too large`, 'error', 500);
            return false;
          }
        }

        return true;
      },
    })
      .use(Dashboard, {
        target: el,
        inline: true,
        showLinkToFileUploadResult: false,
        showProgressDetails: true,
        note: 'Images, video and audio only, up to your storage limit, and 4GB per file',
        fileManagerSelectionType: 'both',
        proudlyDisplayPoweredByUppy: false,
        theme: 'dark',
        closeAfterFinish: false,
        width: '100%',
        height: '100%',
      })
      .use(Webcam, { target: Dashboard })
      .use(XHRUpload, {
        endpoint: '/api/v2/account/files/uppy',
        method: 'post',
        formData: true,
        bundle: false,
        limit: 3,
        shouldRetry: (xhr) => xhr.status >= 500 && xhr.status !== 504 && xhr.status < 600,
        timeout: 60 * 1000,
        meta: {
          folderName: '',
          folderHierarchy: [],
          noTransform: false,
        },
      });

    if (isSafari && dropTargetEl) {
      const addDroppedFiles = (event) => {
        const droppedFiles = Array.from(event?.dataTransfer?.files || []);
        droppedFiles.forEach((file) => {
          if (!(file instanceof File)) {
            return;
          }
          try {
            uppyInstance.addFile({
              name: file.name,
              type: file.type,
              data: file,
              source: 'Local',
            });
          } catch (error) {
            if (!String(error?.message || '').includes('already exists')) {
              console.error('Error adding dropped file:', error);
            }
          }
        });
      };

      const handleDragOver = (event) => {
        event.preventDefault();
        if (event.dataTransfer) {
          event.dataTransfer.dropEffect = 'copy';
        }
        if (typeof onDragOverCallback === 'function') {
          onDragOverCallback(event);
        }
      };

      const handleDragLeave = (event) => {
        event.preventDefault();
        if (typeof onDragLeaveCallback === 'function') {
          onDragLeaveCallback(event);
        }
      };

      const handleDrop = (event) => {
        event.preventDefault();
        event.stopPropagation();
        if (typeof onDropCallback === 'function') {
          onDropCallback(event);
        }
        addDroppedFiles(event);
      };

      dropTargetEl.addEventListener('dragover', handleDragOver);
      dropTargetEl.addEventListener('dragleave', handleDragLeave);
      dropTargetEl.addEventListener('drop', handleDrop);

      window.__nbUppySafariDropCleanup = () => {
        dropTargetEl.removeEventListener('dragover', handleDragOver);
        dropTargetEl.removeEventListener('dragleave', handleDragLeave);
        dropTargetEl.removeEventListener('drop', handleDrop);
      };
    } else {
      uppyInstance = uppyInstance.use(DropTarget, {
        target: dropTarget,
        onDragLeave: (event) => {
          if (typeof onDragLeaveCallback === 'function') {
            onDragLeaveCallback(event);
          }
        },
        onDragOver: (event) => {
          if (typeof onDragOverCallback === 'function') {
            onDragOverCallback(event);
          }
        },
        onDrop: (event) => {
          if (typeof onDropCallback === 'function') {
            onDropCallback(event);
          }
        }
      });
    }

    window.__nbUppy = uppyInstance
      .on('upload-success', (file, response) => {
        handleUploadSuccess(file, response, {
          globalName: '__nbUppy',
          store: this,
          validateFileData: (resp) => {
            const fd = resp?.body?.fileData;
            return (fd && fd.mime) ? fd : null;
          },
          onSuccess: (fd) => {
            if (fd.mime.startsWith('image/')) {
              const urls = [...Object.values(fd.responsive), fd.thumb, fd.url];
              imageVariantsPrecache(urls)
                .then(() => {
                  console.debug('Image variants pre-caching completed.');
                })
                .catch((error) => {
                  console.error('Error during image variants pre-caching:', error);
                });
            }
          },
        });
      })
      .on('file-added', (file) => {
        if (this.routeFileToLargeUploader(file, profileStore)) {
          try {
            window.__nbUppy.removeFile(file.id);
          } catch (_) {
          }
          return;
        }

        handleFileAdded(file, {
          globalName: '__nbUppy',
          store: this,
          mimeLabel: 'uppy/upload',
        });
      });

    attachCommonUppyHandlers(window.__nbUppy, {
      globalName: '__nbUppy',
      store: this,
      profileStore,
    });

    console.debug('Uppy instance created:', el.id);
    Alpine.effect(() => {
      if (window.__nbUppy && profileStore.profileInfo) {
        const storageRemaining = profileStore.profileInfo.storageRemaining || 0;
        let byteLimit = (storageRemaining < 4 * 1024 * 1024 * 1024) ?
          storageRemaining : 4 * 1024 * 1024 * 1024;
        const accountLevel = profileStore.profileInfo.accountLevel || 0;
        const allowedFileTypes = this.getAllowedFileTypes(accountLevel);
        let note = 'Images, video, audio';
        switch (accountLevel) {
          case 10:
          case 99:
            note += ', including documents and archives';
            break;
          case 1:
            note += ', including documents';
            break;
          case 3:
            byteLimit = Math.min(byteLimit, (1024 * 1024 * 450));
        }
        note += `, up to your storage limit, and ${formatBytes(byteLimit)} per file`;
        window.__nbUppy.setOptions({
          restrictions: {
            maxFileSize: byteLimit,
            maxTotalFileSize: storageRemaining,
            allowedFileTypes: allowedFileTypes,
          },
        });
        const dashboardPlugin = window.__nbUppy.getPlugin('Dashboard');
        if (dashboardPlugin) {
          dashboardPlugin.setOptions({
            note: note,
          });
        }
      }
    });
  },
});
