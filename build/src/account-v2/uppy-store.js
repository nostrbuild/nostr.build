import Uppy from '@uppy/core';
import Dashboard from '@uppy/dashboard';
import XHRUpload from '@uppy/xhr-upload';
import Webcam from '@uppy/webcam';
import DropTarget from '@uppy/drop-target';
import Alpine from 'alpinejs';
import { createUppyDialogState } from './uppy-dialog-state';

const apiUrl = `https://${window.location.hostname}/account/api.php`;
const getApiFetcher = (...args) => window.getApiFetcher(...args);
const imageVariantsPrecache = (...args) => window.imageVariantsPrecache(...args);
const formatBytes = (...args) => window.formatBytes(...args);

Alpine.store('uppyStore', {
  instance: null,
  mainDialog: createUppyDialogState(),
  largeFileThresholdBytes: 20 * 1024 * 1024,
  getAllowedFileTypes(accountLevel = 0) {
    const mimeTypesImages = {
      'image/jpeg': 'jpg',
      'image/png': 'png',
      'image/apng': 'apng',
      'image/gif': 'gif',
      'image/webp': 'webp',
      'image/bmp': 'bmp',
      'image/tiff': 'tiff',
      'image/heic': 'heic',
      'image/heif': 'heif',
      'image/avif': 'avif',
      'image/jp2': 'jp2',
      'image/jpx': 'jpx',
      'image/jpm': 'jpm',
      'image/jxr': 'jxr',
      'image/pipeg': 'jfif',
      'image/dng': 'dng',
      'image/*': 'jpg'
    };

    const mimeTypesAudio = {
      'audio/mpeg': 'mp3',
      'audio/ogg': 'ogg',
      'audio/wav': 'wav',
      'audio/aac': 'aac',
      'audio/webm': 'weba',
      'audio/flac': 'flac',
      'audio/x-aiff': 'aif',
      'audio/x-ms-wma': 'wma',
      'audio/x-m4a': 'm4a',
      'audio/x-m4b': 'm4b',
      'audio/mp4': 'mp4a',
      'audio/mpegurl': 'm3u',
      'audio/x-mpegurl': 'm3u',
      'audio/x-ms-wax': 'wax',
      'audio/x-realaudio': 'ra',
      'audio/x-pn-realaudio': 'ram',
      'audio/x-pn-realaudio-plugin': 'rmp',
      'audio/x-wav': 'wav',
    };

    const mimeTypesVideo = {
      'video/mp4': 'mp4',
      'video/webm': 'webm',
      'video/ogg': 'ogv',
      'video/x-msvideo': 'avi',
      'video/x-ms-wmv': 'wmv',
      'video/quicktime': 'mov',
      'video/mpeg': 'mpeg',
      'video/3gpp': '3gp',
      'video/3gpp2': '3g2',
      'video/x-flv': 'flv',
      'video/x-m4v': 'm4v',
      'video/x-matroska': 'mkv',
      'video/x-mpeg2': 'mp2v',
      'video/x-m4p': 'm4p',
      'video/mp2t': 'm2ts',
      'video/MP2T': 'ts',
      'video/mp2p': 'mp2',
      'video/x-mxf': 'mxf',
      'video/x-ms-asf': 'asf',
      'video/x-ms-wm': 'asf',
      'video/x-pn-realvideo': 'rm',
      'video/x-ms-vob': 'vob',
      'video/x-f4v': 'f4v',
      'video/x-fli': 'fli',
      'video/x-m2v': 'm2v',
      'video/x-ms-wmx': 'wmx',
      'video/x-ms-wvx': 'wvx',
      'video/x-sgi-movie': 'movie',
    };

    const mimeTypesAddonDocs = {
      'application/pdf': 'pdf',
      'image/svg+xml': 'svg',
    };

    const mimeTypesAddonExtra = {
      'application/zip': 'zip',
      'application/x-tar': 'tar',
    };

    const mimeTypesPurist = {
      'image/jpeg': 'jpg',
      'image/png': 'png',
      'image/gif': 'gif',
      'image/webp': 'webp',
      'image/heic': 'heic',
      'image/avif': 'avif',
      'video/mp4': 'mp4',
      'video/webm': 'webm',
      'video/quicktime': 'mov',
      'video/mpeg': 'mpeg',
      'video/x-mv4': 'm4v',
      'video/x-matroska': 'mkv',
    };

    const extsAddonDocs = Object.values(mimeTypesAddonDocs).map(ext => `.${ext}`);
    const extsAddonExtra = Object.values(mimeTypesAddonExtra).map(ext => `.${ext}`);

    const mimesImages = Object.keys(mimeTypesImages).map(mime => mime);
    const mimesAudio = Object.keys(mimeTypesAudio).map(mime => mime);
    const mimesVideo = Object.keys(mimeTypesVideo).map(mime => mime);
    const mimesAddonDocs = Object.keys(mimeTypesAddonDocs).map(mime => mime);
    const mimesAddonExtra = Object.keys(mimeTypesAddonExtra).map(mime => mime);
    const mimesPurist = Object.keys(mimeTypesPurist).map(mime => mime);

    switch (accountLevel) {
      case 1:
      case 10:
      case 99:
        console.debug('All file types allowed.');
        return [...mimesImages, ...mimesAudio, ...mimesVideo, ...mimesAddonDocs, ...mimesAddonExtra, ...extsAddonDocs, ...extsAddonExtra];
      case 2:
        console.debug('All file types allowed except for archives.');
        return [...mimesImages, ...mimesAudio, ...mimesVideo, ...mimesAddonDocs, ...extsAddonDocs];
      case 3:
        console.debug('Only images, and video allowed.');
        return mimesPurist;
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
    const menuStore = Alpine.store('menuStore');
    const fileStore = Alpine.store('fileStore');
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
        console.debug('Upload result:', response);
        file.progress.uploadComplete = true;

        const fd = response?.body?.fileData;

        if (!fd || !fd.mime) {
          console.error('Invalid file data received:', fd);
          window.__nbUppy.removeFile(file.id);
          return;
        }

        console.debug('File uploaded:', fd);
        const folderName = JSON.parse(file.meta.folderName || '""');
        const fileFolderName = folderName === '' ? menuStore.folders?.find(folder => folder.id === 0)?.name || '' : folderName;
        menuStore.updateFolderStatsFromFile(fd, fileFolderName, true);
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
        if (menuStore.activeFolder === fileFolderName) {
          this.mainDialog.removeFile(file.id);
          fileStore.files = fileStore.files.filter(f => f.id !== file.id);
          if (!fileStore.files.some(f => f.id === fd.id)) {
            fileStore.injectFile(fd);
          }
        } else {
          this.mainDialog.removeFile(file.id);
        }

        setTimeout(() => {
          window.__nbUppy.removeFile(file.id);
        }, 1000);
      })
      .on('file-added', (file) => {
        if (this.routeFileToLargeUploader(file, profileStore)) {
          try {
            window.__nbUppy.removeFile(file.id);
          } catch (_) {
          }
          return;
        }

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
          this.mainDialog.uploadFolder = folderName;
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
          this.mainDialog.uploadFolder = defaultFolder;
        }
        console.debug('Folder name', folderName);
        console.debug('Folder hierarchy', folderHierarchy);
        window.__nbUppy.setFileMeta(file.id, {
          folderName: JSON.stringify(folderName),
          folderHierarchy: JSON.stringify(folderHierarchy),
          noTransform: noTransform,
        });
        console.debug('File added:', file);
        const currentFile = {
          id: file.id,
          name: file.name,
          mime: 'uppy/upload',
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
        this.mainDialog.addFile(currentFile);
        if (folderName === defaultFolder) {
          fileStore.injectFile(currentFile);
        }
      })
      .on('upload', (data) => {
        console.debug('Upload started:', data);
        this.mainDialog.isLoading = true;
      })
      .on('complete', (result) => {
        const isInHomeFolder = menuStore.folders.find(folder => folder.name === menuStore.activeFolder)?.id === 0;
        const activeFolderMatch = result.successful.some(file => {
          const folderName = JSON.parse(file.meta.folderName);
          return folderName === menuStore.activeFolder || (isInHomeFolder && folderName === '');
        });
        if (result.failed.length === 0) {
          console.debug('Upload complete:', result);
          window.__nbUppy.cancelAll();
          this.mainDialog.close();
          this.mainDialog.uploadProgress = null;
          this.mainDialog.clearFiles();
        } else {
          result.successful.forEach(file => {
            window.__nbUppy.removeFile(file.id);
            this.mainDialog.removeFile(file.id);
          });
          this.mainDialog.open();
        }
        if (activeFolderMatch) {
          console.debug('Refreshing files:', menuStore.activeFolder);
          fileStore.refreshFoldersAfterFetch = true;
          fileStore.fetchFiles(menuStore.activeFolder, true);
        } else {
          menuStore.fetchFolders();
        }
        this.mainDialog.isLoading = false;
      })
      .on('progress', (progress) => {
        this.mainDialog.uploadProgress = progress > 0 ? progress + '%' : null;
      })
      .on('upload-progress', (file, progress) => {
        if (file.progress && file.progress.uploadComplete) {
          return;
        }

        const fileData = this.mainDialog.getFileById(file.id);
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
        const fileData = this.mainDialog.getFileById(file.id);
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
        const { info } = window.__nbUppy.getState();
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
        this.mainDialog.removeFile(file.id);
        fileStore.files = fileStore.files.filter(f => f.id !== file.id);
        if (window.__nbUppy.getFiles().length === 0) {
          this.mainDialog.uploadProgress = null;
          this.mainDialog.isLoading = false;
          this.mainDialog.clearFiles();
        }
      })
      .on('upload-retry', (fileId) => {
        console.debug('Retrying upload:', fileId);
        const fileData = this.mainDialog.getFileById(fileId);
        if (fileData) {
          fileData.uppy.uploadError = false;
          fileData.uppy.errorMessage = '';
          fileData.uppy.errorResponse = null;
        }
      })
      .on('retry-all', () => {
        console.debug('Retrying all uploads');
        this.mainDialog.currentFiles.forEach(file => {
          file.uppy.uploadError = false;
          file.uppy.errorMessage = '';
          file.uppy.errorResponse = null;
        });
      })
      .on('thumbnail:generated', (file, preview) => {
        const fileData = this.mainDialog.getFileById(file.id);
        if (fileData) {
          fileData.uppy.preview = preview;
        }
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
            note = 'Images and video only';
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
