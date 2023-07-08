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


/*
function formatBytes(bytes, decimals = 2) {
  if (bytes === 0) return '0 Bytes';

  const k = 1024;
  const dm = decimals < 0 ? 0 : decimals;
  const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];

  const i = Math.floor(Math.log(bytes) / Math.log(k));

  return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
}

function createMediaLiElem(response) {
  const template = document.createElement('template');

  template.innerHTML = `<li class="relative">
  <div class="relative group aspect-h-7 aspect-w-10 block w-full overflow-hidden rounded-lg bg-indigo-100 focus-within:ring-2 focus-within:ring-indigo-500 focus-within:ring-offset-2 focus-within:ring-offset-indigo-100 transition-all duration-200">
      <div class="absolute inset-0 transition-all duration-200 flex items-center justify-center aspect-content">
          ${response.type.startsWith('image') ?
      `<img src="${response.url}" alt="${response.name}" class="pointer-events-none object-cover w-full h-full">
              <button type="button" class="absolute inset-0 focus:outline-none">
                  <span class="sr-only">View details for ${response.name}</span>
              </button>` : ''}
          ${response.type.startsWith('audio') ?
      `<audio src="${response.url}" controls class="w-full"></audio>` : ''}
          ${response.type.startsWith('video') ?
      `<video src="${response.url}" controls class="object-cover w-full h-full"></video>` : ''}
      </div>
      <div class="absolute inset-0 flex items-center justify-center bg-black bg-opacity-50 opacity-0 transition-opacity duration-200 z-10 pointer-events-none">
          <span class="text-white text-lg font-bold">Link Copied!</span>
      </div>
  </div>
  <p class="pointer-events-none mt-2 block truncate text-sm font-medium text-indigo-100">${response.name}</p>
  <div class="flex justify-between items-center">
      <p class="pointer-events-none block text-sm font-medium text-indigo-300">${formatBytes(response.size)}</p>
      <div class="flex items-center">
          <button class="focus:outline-none text-indigo-300 hover:text-indigo-400 block mr-2 copy-button">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6"> <path stroke-linecap="round" stroke-linejoin="round" d="M11.35 3.836c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m8.9-4.414c.376.023.75.05 1.124.08 1.131.094 1.976 1.057 1.976 2.192V16.5A2.25 2.25 0 0118 18.75h-2.25m-7.5-10.5H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V18.75m-7.5-10.5h6.375c.621 0 1.125.504 1.125 1.125v9.375m-8.25-3l1.5 1.5 3-3.75" /></svg>
          </button>
          <button class="focus:outline-none text-indigo-300 hover:text-indigo-400 block mr-2">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m5.231 13.481L15 17.25m-4.5-15H5.625c-.621 0-1.125.504-1.125 1.125v16.5c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9zm3.75 11.625a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" /></svg>
          </button>
      </div>
  </div>
</li>`;

  const element = template.content.firstChild;

  const copyOverlay = element.querySelector('div.bg-opacity-50');
  const div = element.querySelector('div.aspect-content');

  const copyButton = element.querySelector('button.copy-button');
  copyButton.addEventListener('click', (event) => {
    event.stopPropagation();
    navigator.clipboard.writeText(response.url);

    // Show the notification and blur the media
    copyOverlay.classList.remove('opacity-0');
    div.classList.add('blur-md');

    setTimeout(() => {
      // Hide the notification and unblur the media
      copyOverlay.classList.add('opacity-0');
      div.classList.remove('blur-md');
    }, 2000);
  });

  return element;
}
*/


/*

// Uppy.js
const uppy = new Uppy({
  debug: false,
  allowedFileTypes: ['image/*', 'video/*', 'audio/*'],
  maxFileSize: 15 * 1024 * 1024,
  maxTotalFileSize: 150 * 1024 * 1024,
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
    target: '#files-drag-drop',
    inline: false,
    trigger: '#open-dropzone-button',
    showLinkToFileUploadResult: true,
    showProgressDetails: true,
    note: 'Images, video and audio only, up to 15 MB each',
    fileManagerSelectionType: 'both',
    proudlyDisplayPoweredByUppy: false,
    theme: 'dark',
  })
  .use(Webcam, { target: Dashboard })
  .use(Audio, { target: Dashboard })
  .use(XHRUpload, {
    endpoint: '/api/v2/account/files/uppy',
    method: 'post',
    formData: true,
    bundle: false,
    limit: 5,
    meta: {
      folderName: null, // Initialize folderName metadata
      folderHierarchy: null, // Initialize folderHierarchy metadata
    },
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

    if (!response.body.url) {
      console.error('No URL returned from the server');
      return;
    }

    document.getElementById('no-content-message')?.remove();
    const uploadedImagesUl = document.getElementById('uploaded-images-grid');
    const newElement = createMediaLiElem(response.body);

    uploadedImagesUl.prepend(newElement);
  })
  .on('file-added', (file) => {
    //console.log('Added file', file);
    const path = file.data.relativePath ?? file.data.webkitRelativePath;
    let folderHierarchy = [];
    let folderName = null;

    if (path) {
      const folderPath = path.replace(/\\/g, '/'); // Normalize backslashes to forward slashes
      const folderPathParts = folderPath.split('/').filter(part => part !== '');
      folderHierarchy = folderPathParts.length > 1 ? folderPathParts.slice(0, -1) : [];
      folderName = folderHierarchy.length > 0 ? folderHierarchy[folderHierarchy.length - 1] : null;
    }
    console.log('Folder name', folderName);
    console.log('Folder hierarchy', folderHierarchy);
    uppy.setFileMeta(file.id, {
      folderName: folderName,
      folderHierarchy: folderHierarchy,
    });
  })
  */

const uppy = new Uppy({
  debug: false,
  allowedFileTypes: ['image/*', 'video/*', 'audio/*'],
  //maxFileSize: 15 * 1024 * 1024,
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
    note: 'Images, video and audio only, up to 15 MB each',
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
    meta: {
      folderName: null, // Initialize folderName metadata
      folderHierarchy: null, // Initialize folderHierarchy metadata
    },
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
    let folderName = null;

    if (path) {
      const folderPath = path.replace(/\\/g, '/'); // Normalize backslashes to forward slashes
      const folderPathParts = folderPath.split('/').filter(part => part !== '');
      folderHierarchy = folderPathParts.length > 1 ? folderPathParts.slice(0, -1) : [];
      folderName = folderHierarchy.length > 0 ? folderHierarchy[folderHierarchy.length - 1] : null;
    }
    console.log('Folder name', folderName);
    console.log('Folder hierarchy', folderHierarchy);
    uppy.setFileMeta(file.id, {
      folderName: folderName,
      folderHierarchy: folderHierarchy,
    });
  })
  .on('complete', (result) => {
    // If the failed upload count is zero, all uploads succeeded
    if (result.failed.length === 0) {
      location.reload(); // reload the page
    }
  })