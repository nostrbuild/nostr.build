//import Audio from '@uppy/audio';
//import Compressor from '@uppy/compressor';
//import ImageEditor from '@uppy/image-editor';

import '@uppy/core/dist/style.min.css';
import '@uppy/dashboard/dist/style.min.css';
//import '@uppy/audio/dist/style.min.css';
//import '@uppy/image-editor/dist/style.min.css';
import '@uppy/webcam/dist/style.min.css';
import '@uppy/drop-target/dist/style.css';

import Alpine from 'alpinejs';
import intersect from '@alpinejs/intersect';
import focus from '@alpinejs/focus';
import persist from '@alpinejs/persist';

import { getIconByMime, getIcon } from '../lib/icons';
window.getIconByMime = getIconByMime;
window.getIcon = getIcon;

import './account-v2/api-client';
import './account-v2/global-utils';
import './account-v2/url-import-store';
import './account-v2/uppy-store';
import './account-v2/uppy-large-ai';
import './account-v2/gai-store';
import './account-v2/upload-unload-guard';
import './account-v2/profile-store';
import './account-v2/nostr-store';
import './account-v2/menu-store';
import './account-v2/file-store';

Alpine.plugin(focus);
Alpine.plugin(intersect);
Alpine.plugin(persist);

window.Alpine = Alpine;

document.addEventListener('alpine:init', () => {
  console.debug('Alpine started');
});

document.addEventListener('alpine:initialized', () => {
  console.debug('Alpine initialized');
  const menuStore = Alpine.store('menuStore');
  if (menuStore) {
    menuStore.alpineInitiated = true;
  }
});

Alpine.start();
