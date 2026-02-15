import Uppy from '@uppy/core';
import Dashboard from '@uppy/dashboard';
import AwsS3 from '@uppy/aws-s3';
import Alpine from 'alpinejs';
import { createUppyDialogState } from './uppy-dialog-state';

const multipartConcurrencyLimit = 6;

Alpine.store('uppyLargeStore', {
  instance: null,
  mainDialog: createUppyDialogState(),
  getAllowedFileTypes(accountLevel = 0) {
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

    const mimeTypesAddonExtra = {
      'application/zip': 'zip',
      'application/x-tar': 'tar',
    };

    const extsVideo = Object.values(mimeTypesVideo).map(ext => `.${ext}`);
    const extsAddonExtra = Object.values(mimeTypesAddonExtra).map(ext => `.${ext}`);

    const mimesVideo = Object.keys(mimeTypesVideo).map(mime => mime);
    const mimesAddonExtra = Object.keys(mimeTypesAddonExtra).map(mime => mime);

    switch (accountLevel) {
      case 1:
      case 10:
      case 99:
        console.debug('All file types allowed.');
        return [...mimesVideo, ...mimesAddonExtra, ...extsAddonExtra, ...extsVideo];
      default:
        console.debug('Default file types allowed.');
        return [];
    }
  },
  instantiateLargeFileUppy(el) {
    this.mainDialog.dialogEl = el;
    console.debug('Instantiating Large Files Uppy...');
    const menuStore = Alpine.store('menuStore');
    const fileStore = Alpine.store('fileStore');
    const profileStore = Alpine.store('profileStore');

    if (window.__nbUppyLarge && typeof window.__nbUppyLarge.destroy === 'function') {
      try { window.__nbUppyLarge.destroy(); } catch (_) { }
    }
    window.__nbUppyLarge = new Uppy({
      debug: false,
      autoProceed: true,
      allowMultipleUploadBatches: true,
      restrictions: {
        minFileSize: 20 * 1024 * 1024,
        maxFileSize: 9999 * 64 * 1024 ** 2,
        maxTotalFileSize: null,
        allowedFileTypes: this.getAllowedFileTypes(),
      },
    })
      .use(Dashboard, {
        target: el,
        inline: true,
        showLinkToFileUploadResult: false,
        showProgressDetails: true,
        note: 'Video and Archives only',
        fileManagerSelectionType: 'both',
        proudlyDisplayPoweredByUppy: false,
        theme: 'dark',
        closeAfterFinish: false,
        width: '100%',
        height: '100%',
      })
      .use(AwsS3, {
        id: 'largeFilesAWSPlugin',
        shouldUseMultipart() {
          return true;
        },
        getChunkSize(file) {
          const isMobile = window?.isMobile() ?? false;
          const smallChunk = isMobile ? 20 * 1024 * 1024 : 80 * 1024 * 1024;
          const largeChunk = isMobile ? 40 * 1024 * 1024 : 160 * 1024 * 1024;
          const maxParts = 9999;
          const minPartSize = file.size < 4 * 1024 ** 3 ? smallChunk : largeChunk;
          const calculatedPartSize = Math.floor(file.size / maxParts);
          const partSize = file.size <= smallChunk ?
            Math.max(Math.floor(file.size / multipartConcurrencyLimit), 5 * 1024 ** 2)
            : Math.max(calculatedPartSize, minPartSize);

          console.debug(`File size: ${file.size} bytes, Part size: ${partSize} bytes (${Math.round(partSize / 1024 / 1024)}MiB)`);

          return partSize;
        },
        limit: multipartConcurrencyLimit,
        retryDelays: [0, 1000, 3000, 5000],

        async createMultipartUpload(file, signal) {
          signal?.throwIfAborted();

          const metadata = {};

          Object.keys(file.meta || {}).forEach((key) => {
            if (file.meta[key] != null) {
              metadata[key] = file.meta[key].toString();
            }
          });

          return await window.multipartApi('/api/v2/s3/multipart', {
            method: 'POST',
            headers: {
              'content-type': 'application/json',
            },
            body: JSON.stringify({
              filename: file.name || 'unnamed_file',
              type: file.type || 'application/octet-stream',
              metadata,
            }),
            signal,
          });
        },

        async abortMultipartUpload(file, { key, uploadId, signal }) {
          const filename = encodeURIComponent(key);
          const uploadIdEnc = encodeURIComponent(uploadId);

          return await window.multipartApi(`/api/v2/s3/multipart/${uploadIdEnc}?key=${filename}`, {
            method: 'DELETE',
            signal,
          });
        },

        async signPart(file, options) {
          const { uploadId, key, partNumber, signal } = options;

          console.debug('Signing part for upload:', uploadId, key, partNumber);

          signal?.throwIfAborted();

          if (uploadId == null || key == null || partNumber == null) {
            throw new Error('Cannot sign without a key, an uploadId, and a partNumber');
          }

          const filename = encodeURIComponent(key);
          const uploadIdEnc = encodeURIComponent(uploadId);

          return await window.multipartApi(`/api/v2/s3/multipart/${uploadIdEnc}/${partNumber}?key=${filename}`, {
            method: 'GET',
            signal,
          });
        },

        async listParts(file, { key, uploadId }, signal) {
          signal?.throwIfAborted();

          const filename = encodeURIComponent(key);

          try {
            const result = await window.multipartApi(`/api/v2/s3/multipart/${uploadId}?key=${filename}`, {
              method: 'GET',
              signal,
            });

            if (result && typeof result === 'object') {
              if (result.completed === true) {
                console.debug('Multipart upload fully completed, triggering success handler');
                const uploadSuccessResponse = {
                  body: {
                    fileData: result.fileData
                  }
                };

                setTimeout(() => {
                  window.__nbUppyLarge.emit('upload-success', file, uploadSuccessResponse);
                }, 100);

                return [];
              } else if (result.call_completion === true) {
                console.debug('Server detected S3 upload exists, returning Uppy\'s own parts to trigger completion');
                const uppyParts = file.multipart?.parts || [];

                if (uppyParts.length === 0) {
                  console.warn('No parts found in Uppy state, creating minimal part list');
                  return [
                    {
                      PartNumber: 1,
                      ETag: '"recovered-upload-part"',
                      Size: file.size || 0,
                      LastModified: new Date().toISOString()
                    }
                  ];
                }

                console.debug(`Returning ${uppyParts.length} parts from Uppy\'s state to trigger completion`);
                return uppyParts;
              }
            }

            return result;
          } catch (error) {
            console.debug('List parts failed, checking upload status:', error);

            try {
              const statusResult = await window.multipartApi(`/api/v2/s3/multipart/${uploadId}/status?key=${filename}`, {
                method: 'GET',
                signal,
              });

              if (statusResult && statusResult.completed === true) {
                console.debug('Upload was fully completed via status check, triggering success handler');
                const uploadSuccessResponse = {
                  body: {
                    fileData: statusResult.fileData
                  }
                };

                setTimeout(() => {
                  window.__nbUppyLarge.emit('upload-success', file, uploadSuccessResponse);
                }, 100);

                return [];
              } else if (statusResult && statusResult.call_completion === true) {
                console.debug('Status check shows S3 upload exists, returning Uppy\'s own parts to trigger completion');
                const uppyParts = file.multipart?.parts || [];

                if (uppyParts.length === 0) {
                  console.warn('No parts found in Uppy state via status check, creating minimal part list');
                  return [
                    {
                      PartNumber: 1,
                      ETag: '"recovered-status-upload-part"',
                      Size: file.size || 0,
                      LastModified: new Date().toISOString()
                    }
                  ];
                }

                console.debug(`Status check: Returning ${uppyParts.length} parts from Uppy\'s state to trigger completion`);
                return uppyParts;
              }

            } catch (statusError) {
              console.debug('Upload status check failed:', statusError);
            }

            throw error;
          }
        },

        async completeMultipartUpload(file, { key, uploadId, parts }, signal) {
          signal?.throwIfAborted();

          const filename = encodeURIComponent(key);
          const uploadIdEnc = encodeURIComponent(uploadId);

          return await window.multipartApi(`/api/v2/s3/multipart/${uploadIdEnc}/complete?key=${filename}`, {
            method: 'POST',
            headers: {
              'content-type': 'application/json',
            },
            body: JSON.stringify({ parts }),
            signal,
          });
        },

      })
      .on('upload-success', (file, response) => {
        console.debug('Upload result:', response);
        file.progress.uploadComplete = true;

        const fd = response.body?.fileData || response.body;

        if (!fd || !fd.media_type) {
          console.error('Invalid file data received:', fd);
          window.__nbUppyLarge.removeFile(file.id);
          return;
        }

        console.debug('File uploaded:', fd);
        const folderName = JSON.parse(file.meta.folderName || '""');
        const fileFolderName = folderName === '' ? menuStore.folders?.find(folder => folder.id === 0)?.name || '' : folderName;
        menuStore.updateFolderStatsFromFile(fd, fileFolderName, true);
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
          window.__nbUppyLarge.removeFile(file.id);
        }, 1000);
      })
      .on('file-added', (file) => {
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
        window.__nbUppyLarge.setFileMeta(file.id, {
          folderName: JSON.stringify(folderName),
          folderHierarchy: JSON.stringify(folderHierarchy),
          noTransform: noTransform,
        });
        console.debug('File added:', file);
        const currentFile = {
          id: file.id,
          name: file.name,
          mime: 'uppy/upload-lg',
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
          window.__nbUppyLarge.cancelAll();
          this.mainDialog.close();
          this.mainDialog.uploadProgress = null;
          this.mainDialog.clearFiles();
        } else {
          result.successful.forEach(file => {
            window.__nbUppyLarge.removeFile(file.id);
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
        const { info } = window.__nbUppyLarge.getState();
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
        if (window.__nbUppyLarge.getFiles().length === 0) {
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
      if (window.__nbUppyLarge && profileStore.profileInfo) {
        const absoluteMaxFileSize = 9999 * 64 * 1024 ** 2;
        const accountLevel = profileStore.profileInfo.accountLevel || 0;
        const allowedFileTypes = this.getAllowedFileTypes(accountLevel);
        let note = 'Video and Archives';
        note += [10, 99].includes(accountLevel) ? `, no limit per file` : ', 6 GiB limit';
        window.__nbUppyLarge.setOptions({
          restrictions: {
            minFileSize: 20 * 1024 * 1024,
            maxFileSize: [10, 99].includes(accountLevel) ? absoluteMaxFileSize : 6 * 1024 ** 3,
            maxTotalFileSize: null,
            allowedFileTypes: allowedFileTypes,
          },
        });
        const dashboardPlugin = window.__nbUppyLarge.getPlugin('Dashboard');
        if (dashboardPlugin) {
          dashboardPlugin.setOptions({
            note: note,
          });
        }
      }
    });
  },
});
