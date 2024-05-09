import Uppy from '@uppy/core';
import Dashboard from '@uppy/dashboard';
import GoldenRetriever from '@uppy/golden-retriever';
import XHRUpload from '@uppy/xhr-upload';
import Audio from '@uppy/audio';
//import Compressor from '@uppy/compressor';
//import ImageEditor from '@uppy/image-editor';
import Webcam from '@uppy/webcam';


import '@uppy/core/dist/style.min.css';
import '@uppy/dashboard/dist/style.min.css';
import '@uppy/audio/dist/style.min.css';
//import '@uppy/image-editor/dist/style.min.css';
import '@uppy/webcam/dist/style.min.css';

const uppy = new Uppy({
  debug: false,
  allowedFileTypes: ['image/*', 'video/*', 'audio/*'],
  maxFileSize: 4096 * 1024 * 1024, // 4 GB
  //maxTotalFileSize: 150 * 1024 * 1024,
  onBeforeFileAdded: (currentFile, files) => {
    const allowedTypes = ['video', 'audio', 'image'];
    const fileType = currentFile.type.split('/')[0]; // Extract the file type from the MIME type

    if (!allowedTypes.includes(fileType)) {
      // log to console
      uppy.log(`Skipping file ${currentFile.name} because it's not a video, audio, or image`);
      // show error message to the user
      uppy.info(`Skipping file ${currentFile.name} because it's not a video, audio, or image`, 'error', 500);
      return false; // Exclude the file
    }

    return true; // Include the file
  },
})
  .use(GoldenRetriever)
  .use(Dashboard, {
    target: '#files-account-dropzone',
    inline: false,
    trigger: '#open-account-dropzone-button',
    showLinkToFileUploadResult: true,
    showProgressDetails: true,
    note: 'Images, video and audio only, up to your storage limit',
    fileManagerSelectionType: 'both',
    proudlyDisplayPoweredByUppy: false,
    theme: 'dark',
    closeAfterFinish: true,
  })
  .use(Webcam, { target: Dashboard })
  .use(Audio, { target: Dashboard })
  .use(XHRUpload, {
    endpoint: '/api/v2/account/files/uppy',
    method: 'post',
    formData: true,
    bundle: false,
    limit: 5,
    timeout: 60 * 60 * 1000, // 1h timeout
    meta: {
      folderName: '', // Initialize folderName metadata
      folderHierarchy: [], // Initialize folderHierarchy metadata
    },
  }, {
    // Override the default `limit` behavior
    limit: 0,
    timeout: 60 * 60 * 1000, // 1h timeout
  })
  .on('upload-success', (file, response) => {
    if (Array.isArray(response.body)) {
      const fileResponse = response.body.find(f => f.id === file.id);
      if (fileResponse) {
        uppy.setFileMeta(file.id, {
          name: fileResponse.name,
          type: fileResponse.type,
          size: fileResponse.size
        });
      }
    } else {
      uppy.setFileMeta(file.id, {
        name: response.body.name,
        type: response.body.type,
        size: response.body.size
      });
    }
  })
  .on('upload-success', (file, response) => {
    console.log('Upload result:', response);
  })
  .on('file-added', (file) => {
    //console.log('Added file', file);
    const path = file.data.relativePath ?? file.data.webkitRelativePath;
    let folderHierarchy = [];
    let folderName = '';

    if (path) {
      const folderPath = path.replace(/\\/g, '/'); // Normalize backslashes to forward slashes
      const folderPathParts = folderPath.split('/').filter(part => part !== '');
      folderHierarchy = folderPathParts.length > 1 ? folderPathParts.slice(0, -1) : [];
      folderName = folderHierarchy.length > 0 ? folderHierarchy[folderHierarchy.length - 1] : null;
    }
    console.log('Folder name', folderName);
    console.log('Folder hierarchy', folderHierarchy);
    uppy.setFileMeta(file.id, {
      folderName: JSON.stringify(folderName),
      folderHierarchy: JSON.stringify(folderHierarchy),
    });
  })
  .on('complete', (result) => {
    // If the failed upload count is zero, all uploads succeeded
    if (result.failed.length === 0) {
      location.reload(); // reload the page
    }
  })