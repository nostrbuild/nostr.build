import Uppy from '@uppy/core';
import Dashboard from '@uppy/dashboard';
import AwsS3 from '@uppy/aws-s3';
import Alpine from 'alpinejs';
import { createUppyDialogState } from './uppy-dialog-state';
import { mimeTypesVideo, mimeTypesAddonExtra } from './mime-types';
import { attachCommonUppyHandlers, handleFileAdded, handleUploadSuccess } from './uppy-shared-handlers';

const multipartConcurrencyLimit = 6;

Alpine.store('uppyLargeStore', {
  instance: null,
  mainDialog: createUppyDialogState(),
  getAllowedFileTypes(accountLevel = 0) {
    const extsVideo = Object.values(mimeTypesVideo).map(ext => `.${ext}`);
    const extsAddonExtra = Object.values(mimeTypesAddonExtra).map(ext => `.${ext}`);

    const mimesVideo = Object.keys(mimeTypesVideo);
    const mimesAddonExtra = Object.keys(mimeTypesAddonExtra);

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
    const profileStore = Alpine.store('profileStore');

    if (window.__nbUppyLarge && typeof window.__nbUppyLarge.destroy === 'function') {
      try { window.__nbUppyLarge.destroy(); } catch (_) { }
    }
    window.__nbUppyLarge = new Uppy({
      debug: false,
      autoProceed: true,
      allowMultipleUploadBatches: true,
      restrictions: {
        minFileSize: 40 * 1024 * 1024,
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
          const smallChunk = isMobile ? 40 * 1024 * 1024 : 80 * 1024 * 1024;
          const largeChunk = isMobile ? 80 * 1024 * 1024 : 160 * 1024 * 1024;
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
        handleUploadSuccess(file, response, {
          globalName: '__nbUppyLarge',
          store: this,
          validateFileData: (resp) => {
            const fd = resp.body?.fileData || resp.body;
            return (fd && fd.media_type) ? fd : null;
          },
        });
      })
      .on('file-added', (file) => {
        handleFileAdded(file, {
          globalName: '__nbUppyLarge',
          store: this,
          mimeLabel: 'uppy/upload-lg',
        });
      });

    attachCommonUppyHandlers(window.__nbUppyLarge, {
      globalName: '__nbUppyLarge',
      store: this,
      profileStore,
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
            minFileSize: 40 * 1024 * 1024,
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
