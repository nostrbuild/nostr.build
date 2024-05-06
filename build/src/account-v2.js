import Uppy from '@uppy/core';
import Dashboard from '@uppy/dashboard';
import GoldenRetriever from '@uppy/golden-retriever';
import XHRUpload from '@uppy/xhr-upload';
//import Audio from '@uppy/audio';
//import Compressor from '@uppy/compressor';
//import ImageEditor from '@uppy/image-editor';
import Webcam from '@uppy/webcam';
import DropTarget from '@uppy/drop-target';


import '@uppy/core/dist/style.min.css';
import '@uppy/dashboard/dist/style.min.css';
//import '@uppy/audio/dist/style.min.css';
//import '@uppy/image-editor/dist/style.min.css';
import '@uppy/webcam/dist/style.min.css';
import '@uppy/drop-target/dist/style.css';


import { lock, unlock, clearBodyLocks } from 'tua-body-scroll-lock';
import axios, { all } from 'axios';
import axiosRetry from 'axios-retry';
import Alpine from 'alpinejs';

import intersect from '@alpinejs/intersect';
import focus from '@alpinejs/focus';

Alpine.plugin(focus);
Alpine.plugin(intersect);

window.Alpine = Alpine;

// Video Player
//import 'vidstack/player/styles/default/theme.css';
//import 'vidstack/player/styles/default/layouts/audio.css';
//import { VidstackPlayer, VidstackPlayerLayout } from 'vidstack/global/player';

window.getApiFetcher = function (baseUrl, contentType = 'multipart/form-data') {
  const api = axios.create({
    baseURL: baseUrl,
    headers: {
      'Content-Type': contentType,
    },
    timeout: 30000,
  });

  axiosRetry(api, {
    retries: 5, // Make it resilient
    //retryDelay: axiosRetry.exponentialDelay,
    retryDelay: (
      retryNumber = 0,
      _error = undefined,
      delayFactor = 200 // Slow down there, cowboy
    ) => {
      const delay = 2 ** retryNumber * delayFactor;
      const randomSum = delay * 0.2 * Math.random(); // 0-20% of the delay
      return delay + randomSum;
    },
    retryCondition: (error) => {
      return axiosRetry.isNetworkOrIdempotentRequestError(error) ||
        axiosRetry.isSafeRequestError(error) ||
        axiosRetry.isRetryableError(error)
    },
  });
  return api;
};

window.formatBytes = (bytes) => {
  if (bytes === 0) return '0 Bytes';

  const k = 1024;
  const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));

  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Bech32 encoding and decoding library
const ALPHABET = 'qpzry9x8gf2tvdw0s3jn54khce6mua7l';
const ALPHABET_MAP = {};
for (var z = 0; z < ALPHABET.length; z++) {
  var x = ALPHABET.charAt(z);
  ALPHABET_MAP[x] = z;
}

function polymodStep(pre) {
  var b = pre >> 25;
  return (((pre & 0x1ffffff) << 5) ^
    (-((b >> 0) & 1) & 0x3b6a57b2) ^
    (-((b >> 1) & 1) & 0x26508e6d) ^
    (-((b >> 2) & 1) & 0x1ea119fa) ^
    (-((b >> 3) & 1) & 0x3d4233dd) ^
    (-((b >> 4) & 1) & 0x2a1462b3));
}

function prefixChk(prefix) {
  var chk = 1;
  for (var i = 0; i < prefix.length; ++i) {
    var c = prefix.charCodeAt(i);
    if (c < 33 || c > 126)
      return 'Invalid prefix (' + prefix + ')';
    chk = polymodStep(chk) ^ (c >> 5);
  }
  chk = polymodStep(chk);
  for (var i = 0; i < prefix.length; ++i) {
    var v = prefix.charCodeAt(i);
    chk = polymodStep(chk) ^ (v & 0x1f);
  }
  return chk;
}

function convertbits(data, inBits, outBits, pad) {
  var value = 0;
  var bits = 0;
  var maxV = (1 << outBits) - 1;
  var result = [];
  for (var i = 0; i < data.length; ++i) {
    value = (value << inBits) | data[i];
    bits += inBits;
    while (bits >= outBits) {
      bits -= outBits;
      result.push((value >> bits) & maxV);
    }
  }
  if (pad) {
    if (bits > 0) {
      result.push((value << (outBits - bits)) & maxV);
    }
  } else {
    if (bits >= inBits)
      return 'Excess padding';
    if ((value << (outBits - bits)) & maxV)
      return 'Non-zero padding';
  }
  return result;
}

function toWords(bytes) {
  return convertbits(bytes, 8, 5, true);
}

function fromWordsUnsafe(words) {
  var res = convertbits(words, 5, 8, false);
  if (Array.isArray(res))
    return res;
}

function fromWords(words) {
  var res = convertbits(words, 5, 8, false);
  if (Array.isArray(res))
    return res;
  throw new Error(res);
}

function getLibraryFromEncoding(encoding) {
  var ENCODING_CONST;
  if (encoding === 'bech32') {
    ENCODING_CONST = 1;
  } else {
    ENCODING_CONST = 0x2bc830a3;
  }

  function encode(prefix, words, LIMIT) {
    LIMIT = LIMIT || 90;
    if (prefix.length + 7 + words.length > LIMIT)
      throw new TypeError('Exceeds length limit');
    prefix = prefix.toLowerCase();
    // determine chk mod
    var chk = prefixChk(prefix);
    if (typeof chk === 'string')
      throw new Error(chk);
    var result = prefix + '1';
    for (var i = 0; i < words.length; ++i) {
      var x = words[i];
      if (x >> 5 !== 0)
        throw new Error('Non 5-bit word');
      chk = polymodStep(chk) ^ x;
      result += ALPHABET.charAt(x);
    }
    for (var i = 0; i < 6; ++i) {
      chk = polymodStep(chk);
    }
    chk ^= ENCODING_CONST;
    for (var i = 0; i < 6; ++i) {
      var v = (chk >> ((5 - i) * 5)) & 0x1f;
      result += ALPHABET.charAt(v);
    }
    return result;
  }

  function __decode(str, LIMIT) {
    LIMIT = LIMIT || 90;
    if (str.length < 8)
      return str + ' too short';
    if (str.length > LIMIT)
      return 'Exceeds length limit';
    // don't allow mixed case
    var lowered = str.toLowerCase();
    var uppered = str.toUpperCase();
    if (str !== lowered && str !== uppered)
      return 'Mixed-case string ' + str;
    str = lowered;
    var split = str.lastIndexOf('1');
    if (split === -1)
      return 'No separator character for ' + str;
    if (split === 0)
      return 'Missing prefix for ' + str;
    var prefix = str.slice(0, split);
    var wordChars = str.slice(split + 1);
    if (wordChars.length < 6)
      return 'Data too short';
    var chk = prefixChk(prefix);
    if (typeof chk === 'string')
      return chk;
    var words = [];
    for (var i = 0; i < wordChars.length; ++i) {
      var c = wordChars.charAt(i);
      var v = ALPHABET_MAP[c];
      if (v === undefined)
        return 'Unknown character ' + c;
      chk = polymodStep(chk) ^ v;
      // not in the checksum?
      if (i + 6 >= wordChars.length)
        continue;
      words.push(v);
    }
    if (chk !== ENCODING_CONST)
      return 'Invalid checksum for ' + str;
    return {
      prefix: prefix,
      words: words
    };
  }

  function decodeUnsafe(str, LIMIT) {
    var res = __decode(str, LIMIT);
    if (typeof res === 'object')
      return res;
  }

  function decode(str, LIMIT) {
    var res = __decode(str, LIMIT);
    if (typeof res === 'object')
      return res;
    throw new Error(res);
  }
  return {
    decodeUnsafe: decodeUnsafe,
    decode: decode,
    encode: encode,
    toWords: toWords,
    fromWordsUnsafe: fromWordsUnsafe,
    fromWords: fromWords
  };
}
window.bech32 = getLibraryFromEncoding('bech32');
window.abbreviateBech32 = (bech32Address) => {
  return typeof bech32Address === 'string' ? `${bech32Address.substring(0, 15)}...${bech32Address.substring(bech32Address.length - 10)}` : '';
};

// AlpineJS components and stores
document.addEventListener('alpine:init', () => {
  console.log('Alpine started');
});
document.addEventListener('alpine:initialized', () => {
  console.log('Alpine initialized');
  Alpine.store('menuStore').alpineInitiated = true;
})
const apiUrl = `https://${window.location.hostname}/account/api.php`;

// TODO: Intercept back and forward navigation history
function updateHashURL(f, p) {
  const params = new URLSearchParams(window.location.hash.slice(1));
  if (f) params.set('f', encodeURIComponent(f));
  if (p) params.set('p', encodeURIComponent(p));
  history.replaceState(null, null, `#${params.toString()}`);
}

function getUpdatedHashLink(f, p) {
  const params = new URLSearchParams(window.location.hash.slice(1));
  if (f) params.set('f', encodeURIComponent(f));
  if (p) params.set('p', encodeURIComponent(p));
  return `#${params.toString()}`;
}

function getHashParams() {
  const params = new URLSearchParams(window.location.hash.slice(1));
  const folder = params.get('f');
  const page = params.get('p');
  return {
    folder,
    page
  };
}

window.copyUrlToClipboard = (url) => {
  navigator.clipboard.writeText(url)
    .then(() => {
      console.log('URL copied to clipboard:', url);
    })
    .catch(error => {
      console.error('Error copying URL to clipboard:', error);
    });
}

// Constants
const aiImagesFolderName = 'AI: Generated Images';
const homeFolderName = 'Home: Main Folder';

Alpine.store('profileStore', {
  profileDataInitialized: false,
  init() {
    if (!this.profileDataInitialized) {
      this.refreshProfileInfo().then(() => {
        this.profileDataInitialized = true;
      });
    }
  },
  profileInfo: {
    userId: 0,
    name: '',
    npub: '',
    pfpUrl: '',
    wallet: '',
    defaultFolder: '',
    allowNostrLogin: undefined,
    npubVerified: undefined,
    accountLevel: 0,
    accountFlags: {},
    remainingDays: 0,
    subscriptionExpired: null,
    storageUsed: 0,
    storageLimit: 0,
    totalStorageLimit: '',
    get creatorPageLink() {
      return `https://${window.location.hostname}/creators/creator/?user=${this.userId}`;
    },
    get storageRemaining() {
      return this.storageLimit - this.storageUsed;
    },
    get storageOverLimit() {
      return this.storageRemaining <= 0;
    },
    get planName() {
      switch (this.accountLevel) {
        case 0:
          return 'Free';
        case 1:
          return 'Creator';
        case 2:
          return 'Professional';
        case 3:
        case 4:
        case 5:
          return 'Legacy';
        case 10:
          return 'Advanced';
        case 89:
          return 'Moderator';
        case 99:
          return 'Admin';
        default:
          return 'Unknown';
      }
    },
    get isAdmin() {
      return this.accountLevel === 99;
    },
    get isModerator() {
      return this.accountFlags?.canModerate || this.isAdmin;
    },
    get accountExpired() {
      return this.remainingDays <= 0;
    },
    get accountExpiredDisplay() {
      return this.accountExpired ? 'Expired' : 'Active';
    },
    get accountEligibleForRenewal() {
      return this.remainingDays <= 30;
    },
    get accountEligibleForUpgrade() {
      return this.accountLevel < 10 || this.accountLevel === 89;
    },
    getNameDisplay() {
      return this.name.substring(0, 15) + (this.name.length > 15 ? '...' : '');
    },
    getNpubDisplay() {
      return this.npub.substring(0, 15) + (this.npub.length > 15 ? '...' : '');
    },
    storageRatio() {
      return Math.min(1, Math.max(0, this.storageUsed / this.storageLimit));
    },
    getStorageRatio() {
      return this.storageOverLimit ? 1 : this.storageRatio();
    },
    // Feature flags. This is enforced on the server side, the following only provides the client side checks
    // AI Studio
    get isAIStudioEligible() {
      return [2, 1, 10, 99].includes(this.accountLevel) &&
        !this.accountExpired && !this.storageOverLimit;
    },
    // AI Studio Models
    // Dream Shaper
    get isAIDreamShaperEligible() {
      return [2, 1, 10, 99].includes(this.accountLevel) && this.isAIStudioEligible &&
        !this.accountExpired && !this.storageOverLimit;
    },
    // SDXL-Lightning
    get isAISDXLLightningEligible() {
      return [1, 10, 99].includes(this.accountLevel) && this.isAIStudioEligible &&
        !this.accountExpired && !this.storageOverLimit;
    },
    // Stable Diffusion
    get isAISDiffusionEligible() {
      return [10, 99].includes(this.accountLevel) && this.isAIStudioEligible &&
        !this.accountExpired && !this.storageOverLimit;
    },
    // Other Features
    // Creators Page
    get isCreatorsPageEligible() {
      return [1, 10, 99].includes(this.accountLevel) &&
        !this.accountExpired;
    },
    // Nostr Share
    get isNostrShareEligible() {
      return [1, 2, 10, 99].includes(this.accountLevel) &&
        !this.accountExpired;
    },
    // General upload and URL Import
    get isUploadEligible() {
      // All unexpired accounts can upload with available storage
      return [1, 2, 5, 10, 99].includes(this.accountLevel) &&
        !this.accountExpired && !this.storageOverLimit;
    },
    // General in-account Sharing
    get isShareEligible() {
      return (this.isCreatorsPageEligible || this.isNostrShareEligible) &&
        !this.accountExpired;
    },
    // Search and Filter
    get isSearchEligible() {
      return [1, 2, 10, 99].includes(this.accountLevel) &&
        !this.accountExpired;
    },
    // Free uploads gallery
    get isFreeGalleryEligible() {
      return [1, 2, 10, 99].includes(this.accountLevel) &&
        !this.accountExpired;
    },
    allowed(permission) {
      switch (permission) {
        case 'isAdmin':
          return this.isAdmin;
        case 'isModerator':
          return this.isModerator;
        case 'isAIStudioEligible':
          return this.isAIStudioEligible;
        case 'isAIDreamShaperEligible':
          return this.isAIDreamShaperEligible;
        case 'isAISDXLLightningEligible':
          return this.isAISDXLLightningEligible;
        case 'isAISDiffusionEligible':
          return this.isAISDiffusionEligible;
        case 'isCreatorsPageEligible':
          return this.isCreatorsPageEligible;
        case 'isNostrShareEligible':
          return this.isNostrShareEligible;
        case 'isUploadEligible':
          return this.isUploadEligible;
        case 'isShareEligible':
          return this.isShareEligible;
        case 'isSearchEligible':
          return this.isSearchEligible;
        case 'isFreeGalleryEligible':
          return this.isFreeGalleryEligible;
        default:
          return false;
      }
    }
  },
  dialogOpen: false,
  dialogLoading: false,
  dialogError: false,
  dialogErrorMessages: [],
  dialogSuccessMessages: [],
  isFormUpdated(nym, phpUrl, password) {
    return this.profileInfo.name !== nym || this.profileInfo.pfpUrl !== phpUrl || password;
  },
  closeDialog(force) {
    if (!this.dialogLoading || force) {
      this.dialogOpen = false;
      this.dialogError = false;
      this.dialogErrorMessages = [];
      // clear password $refs
      if (this.$refs?.currentPassword?.value) {
        this.$refs.currentPassword.value = '';
      }
      if (this.$refs?.newPassword?.value) {
        this.$refs.newPassword.value = '';
      }
      if (this.$refs?.confirmPassword?.value) {
        this.$refs.confirmPassword.value = '';
      }
    }
    // Refresh profile info
    this.refreshProfileInfo();
  },
  openDialog() {
    this.dialogOpen = true;
  },
  hideMessages() {
    setTimeout(() => {
      this.dialogError = false;
      this.dialogErrorMessages = [];
      this.dialogSuccessMessages = [];
    }, 3000);
  },
  async updateProfileInfo() {
    this.dialogLoading = true;

    const formData = {
      action: 'update_profile',
      name: this.profileInfo.name,
      pfpUrl: this.profileInfo.pfpUrl,
      wallet: this.profileInfo.wallet,
      defaultFolder: this.profileInfo.defaultFolder,
      allowNostrLogin: this.profileInfo.allowNostrLogin,
    };

    const api = getApiFetcher(apiUrl, 'multipart/form-data');

    api.post('', formData)
      .then(response => response.data)
      .then(data => {
        // Check if error
        if (data.error) {
          console.error('Error updating profile:', data);
          this.dialogError = true;
          this.dialogErrorMessages.push(data.error);
        } else {
          //console.log('Profile updated:', data);
          this.dialogSuccessMessages.push('Profile updated.');
          // Update the profile info
          this.updateProfileInfoFromData(data);
          // Close the dialog
          this.closeDialog(true);
        }
        this.hideMessages();
      })
      .catch(error => {
        console.error('Error updating profile:', error);
        this.dialogError = true;
        this.dialogErrorMessages.push('Error updating profile.');
      })
      .finally(() => {
        this.dialogLoading = false;
      });
  },
  async updatePassword(currentPasswordRef, newPasswordRef, confirmPasswordRef) {
    this.dialogLoading = true;

    const current = currentPasswordRef?.value;
    const newPassword = newPasswordRef?.value;
    const confirmPassword = confirmPasswordRef?.value;

    // Check if any of the fields are empty
    if (!current) {
      this.dialogError = true;
      this.dialogLoading = false;
      this.dialogErrorMessages.push('Please enter your current password');
      this.hideMessages();
      return;
    }
    if (!newPassword) {
      this.dialogError = true;
      this.dialogLoading = false;
      this.dialogErrorMessages.push('Please enter a new password');
      this.hideMessages();
      return;
    }
    if (!confirmPassword) {
      this.dialogError = true;
      this.dialogLoading = false;
      this.dialogErrorMessages.push('Please confirm your new password');
      this.hideMessages();
      return;
    }

    // Check if newPassword and confirmPassword match
    if (newPassword !== confirmPassword) {
      console.error('Passwords do not match:', newPassword, confirmPassword);
      this.dialogError = true;
      this.dialogLoading = false;
      this.dialogErrorMessages.push('New password and confirm password do not match');
      this.hideMessages();
      return;
    }

    const formData = {
      action: 'update_password',
      password: current,
      newPassword: newPassword,
    };
    const api = getApiFetcher(apiUrl, 'multipart/form-data');

    api.post('', formData)
      .then(response => response.data)
      .then(data => {
        // Check if error
        if (data.error) {
          console.error('Error updating password:', data);
          this.dialogError = true;
          this.dialogErrorMessages.push(data.error);
        } else {
          const success = data.success;
          if (!success) {
            console.error('Error updating password:', data);
            this.dialogError = true;
            this.dialogErrorMessages.push('Error updating password.');
          } else {
            //console.log('Password updated:', data);
            this.dialogSuccessMessages.push('Password updated.');
          }
        }
        this.hideMessages();
      })
      .catch(error => {
        console.error('Error updating password:', error);
        this.dialogError = true;
        this.dialogErrorMessages.push('Error updating password.');
      })
      .finally(() => {
        this.dialogLoading = false;
      });
  },
  async refreshProfileInfo() {
    // Updates session profile info and returns authoritatively
    const params = {
      action: 'get_profile_info',
    };
    const api = getApiFetcher(apiUrl, 'application/json');

    api.get('', {
      params
    })
      .then(response => response.data)
      .then(data => {
        //console.log('Profile info:', data);
        if (!data.error) {
          this.updateProfileInfoFromData(data);
        } else {
          console.error('Error fetching profile info:', data);
          this.dialogErrorMessages.push(data.error);
        }
      })
      .catch(error => {
        console.error('Error fetching profile info:', error);
      });
  },
  updateProfileInfoFromData(data) {
    this.profileInfo.userId = data.userId;
    this.profileInfo.name = data.name;
    this.profileInfo.npub = data.npub;
    this.profileInfo.pfpUrl = data.pfpUrl;
    this.profileInfo.wallet = data.wallet;
    this.profileInfo.defaultFolder = data.defaultFolder;
    this.profileInfo.allowNostrLogin = data.allowNostrLogin === 1;
    this.profileInfo.npubVerified = data.npubVerified === 1;
    this.profileInfo.accountLevel = data.accountLevel;
    this.profileInfo.accountFlags = data.accountFlags;
    this.profileInfo.remainingDays = data.remainingDays;
    this.profileInfo.subscriptionExpired = data.remainingDays <= 0;
    this.profileInfo.storageUsed = data.storageUsed;
    this.profileInfo.storageLimit = data.storageLimit;
    this.profileInfo.totalStorageLimit = data.totalStorageLimit;
  },
});

Alpine.store('nostrStore', {
  isExtensionInstalled() {
    if (typeof window.nostr === 'undefined') {
      console.error('Nostr extension not installed.');
      // Set error in the share structure
      this.share.isError = true;
      this.share.isErrorMessages.push('Make sure your nostr extension (Alby, Nos2x, Nostr Connect) is installed and enabled.');
      // Make sure that TW CSS class text-nostrpurple-700 is included or pinned
      this.share.isErrorMessages.push('You can find one for you <a class="text-nostrpurple-700 font-bold animate-pulse" href="https://github.com/aljazceru/awesome-nostr?tab=readme-ov-file#nip-07-browser-extensions" target="_blank">HERE</a>.');
      return false;
    }

    return true;
  },
  share: {
    isOpen: false,
    isLoading: false,
    isError: false,
    isCriticalError: false,
    isErrorMessages: [],
    selectedIds: [],
    selectedFiles: [],
    callback: null,
    extNpub: '',
    note: '',
    signedEvent: {},
    getDeduplicatedErrors() {
      return Array.from(new Set(this.isErrorMessages));
    },
    remove(fileId) {
      this.selectedIds = this.selectedIds.filter(id => id !== fileId);
      this.selectedFiles = this.selectedFiles.filter(file => file.id !== fileId);
    },
    open(ids, callback) {
      // Convert single ID to array
      if (!Array.isArray(ids)) {
        ids = [ids];
      }
      console.log('Opening sharing modal:', ids);
      this.selectedIds = ids;
      this.selectedFiles = Alpine.store('fileStore').files.filter(file => ids.includes(file.id));
      console.log('Selected files:', this.selectedFiles);
      this.isOpen = true;
      this.callback = callback;
    },
    close(dontCallback) {
      this.selectedIds = [];
      this.selectedFiles = [];
      this.isError = false;
      this.isErrorMessages = [];
      this.isOpen = false;
      this.isLoading = false;
      this.extNpub = '';
      this.note = '';
      this.signedEvent = {};
      this.isCriticalError = false;
      // execute callback if provided
      if (this.callback && !dontCallback) {
        this.callback();
      }
    },
    async send() {
      const nostrStore = Alpine.store('nostrStore');
      if (nostrStore.isExtensionInstalled()) {
        //console.log('Sending share request to Nostr:', this.selectedFiles, this.extNpub, this.note);
      } else {
        console.error('Nostr extension not installed.');
        this.isError = true;
        return;
      }
      this.isLoading = true;
      this.isError = false;
      this.isErrorMessages = [];
      // Append file URLs to the note
      this.selectedFiles.forEach(file => {
        this.note += `\n${file.url}`;
      });
      // TODO: Add support to create and manage badges - https://github.com/nostr-protocol/nips/blob/master/58.md
      // TODO: Add event deletion - https://github.com/nostr-protocol/nips/blob/master/09.md
      // Create imeta tags:
      // NIP-92 https://github.com/nostr-protocol/nips/blob/master/92.md
      const tags = this.selectedFiles.map(file => {
        // Build the imeta tags based on available file data
        const imeta = [];
        if (file.blurhash) {
          imeta.push(`blurhash ${file.blurhash}`);
        }
        if (file.width && file.height) {
          imeta.push(`dim ${file.width}x${file.height}`);
        }
        if (file.mime) {
          imeta.push(`m ${file.mime}`);
        }
        return [
          'imeta',
          `url ${file.url}`,
          ...imeta,
        ];
      });
      // TODO: NIP-94 https://github.com/nostr-protocol/nips/blob/master/94.md
      // Append the URL r tags
      this.selectedFiles.forEach(file => {
        tags.push([
          'r',
          file.url,
        ]);
      });
      const event = {
        kind: 1,
        created_at: Math.floor(Date.now() / 1000),
        tags: tags,
        content: this.note,
      }
      this.signedEvent = await window.nostr.signEvent(event);
      //console.log('Signed event:', this.signedEvent);
      nostrStore.publishSignedEvent(this.signedEvent, this.selectedIds)
        .then(() => {
          console.log('Published Nostr event:', this.signedEvent);
          this.close();
        })
        .catch(error => {
          console.error('Error publishing Nostr event:', error);
          this.isError = true;
          this.isErrorMessages.push('Error publishing Nostr event.');
        })
        .finally(() => {
          this.isLoading = false;
        });
    },
  },
  async publishSignedEvent(signedEvent, mediaIds = []) {
    const formData = {
      action: 'publish_nostr_event',
      event: JSON.stringify(signedEvent),
      mediaIds: JSON.stringify(mediaIds),
      eventId: signedEvent.id,
      eventCreatedAt: signedEvent.created_at,
      eventContent: signedEvent.content,
    }
    const api = getApiFetcher(apiUrl, 'multipart/form-data');

    return api.post('', formData)
      .then(response => response.data)
      .then(data => {
        //console.log('Published Nostr event:', data);
        // Check if noteId is not null and success is true, otherwise throw an error
        if (!data.noteId || !data.success) {
          throw new Error('Error publishing Nostr event.');
        }
        // Update shared files with the Nostr event ID
        const fileStore = Alpine.store('fileStore');
        const mediaEvents = data.mediaEvents || {};
        fileStore.files.forEach(file => {
          // Update the associated_notes field with the Nostr event ID
          if (mediaEvents[file.id]) {
            if (file.associated_notes?.length) {
              file.associated_notes += ',' + mediaEvents[file.id];
            } else {
              file.associated_notes = mediaEvents[file.id];
            }
          }
        });
        // Remove the deletedEvents from associated_notes,
        // where the string starts with event ID and followed by unix epoch timestamp, until ','
        const deletedEvents = data.deletedEvents || [];
        deletedEvents.forEach(eventId => {
          this.files.forEach(file => {
            if (file.associated_notes?.includes(eventId)) {
              // Remove deleted events
              file.associated_notes = file.associated_notes.split(',').filter(note => !note.startsWith(eventId)).join(',');
            }
          });
        });
        return data;
      })
      .catch(error => {
        console.error('Error publishing Nostr event:', error);
      });
  },
  async deleteEvent(eventIds) {
    // Generate the delete event, NIP-09
    eventIds = Array.isArray(eventIds) ? eventIds : [eventIds];
    const tags = eventIds.map(eventId => ['e', eventId]);
    const event = {
      kind: 5,
      created_at: Math.floor(Date.now() / 1000),
      tags: tags,
      content: 'User requested deletion of these posts',
    };
    signedEvent = await window.nostr.signEvent(event);
    //console.log('Signed event:', this.signedEvent);
    nostrStore.publishSignedEvent(signedEvent)
      .then(() => {
        console.log('Published Nostr event:', this.signedEvent);
        this.close();
      })
      .catch(error => {
        console.error('Error publishing Nostr event:', error);
        this.isError = true;
        this.isErrorMessages.push('Error publishing Nostr event.');
      })
      .finally(() => {
        this.isLoading = false;
      });
  },
  async nostrGetPublicKey() {
    if (!this.isExtensionInstalled()) {
      console.error('Nostr extension not installed.');
      return;
    }
    try {
      const publicKey = await window.nostr.getPublicKey();
      console.log('Nostr public key:', publicKey);
      return publicKey;
    } catch (error) {
      console.error('Error getting Nostr public key:', error);
    }
  },
  async nostrGetBech32Npub() {
    const hexNpub = await this.nostrGetPublicKey();
    if (!hexNpub) {
      console.error('Nostr public key not found.');
      // Set error in the share structure
      this.share.isError = true;
      return;
    }
    const publicKey = bech32.encode('npub', bech32.toWords(new Uint8Array(hexNpub.match(/.{1,2}/g).map(byte => parseInt(byte, 16)))));
    const profileStore = Alpine.store('profileStore');
    if (publicKey && publicKey !== profileStore.profileInfo.npub) {
      console.error('Nostr public keys do not match:', publicKey, profileStore.profileInfo.npub);
      // Set error in the share structure
      this.share.isError = true;
      this.share.isCriticalError = true;
      this.share.isErrorMessages.push('Your account Nostr public key does not match your extension key.');
    }
    return publicKey;
  },
})

Alpine.store('menuStore', {
  alpineInitiated: false,
  mobileMenuOpen: false,
  menuStoreInitiated: false,
  init() {
    if (this.menuStoreInitiated) {
      return;
    }
    this.refreshFoldersStats().then(() => {
      this.menuStoreInitiated = true;
    });
    const {
      folder,
      page
    } = getHashParams();
    this.setActiveFolder(folder);
    this.setActiveMenuFromHash();
    console.log('Menu store initiated');
  },
  menuItemsAI: [{
    name: 'AI Studio',
    icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M15.59 14.37a6 6 0 0 1-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 0 0 6.16-12.12A14.98 14.98 0 0 0 9.631 8.41m5.96 5.96a14.926 14.926 0 0 1-5.841 2.58m-.119-8.54a6 6 0 0 0-7.381 5.84h4.8m2.581-5.84a14.927 14.927 0 0 0-2.58 5.84m2.699 2.7c-.103.021-.207.041-.311.06a15.09 15.09 0 0 1-2.448-2.448 14.9 14.9 0 0 1 .06-.312m-2.24 2.39a4.493 4.493 0 0 0-1.757 4.306 4.493 4.493 0 0 0 4.306-1.758M16.5 9a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z" />',
    route: getUpdatedHashLink(aiImagesFolderName, 'gai'),
    routeId: 'gai',
    rootFolder: aiImagesFolderName
  },
    /*
    {
      name: 'AI reImage',
      icon: '<path d="M18 22H4a2 2 0 0 1-2-2V6"/><path d="m22 13-1.296-1.296a2.41 2.41 0 0 0-3.408 0L11 18"/><circle cx="12" cy="8" r="2"/><rect width="16" height="16" x="6" y="2" rx="2"/>',
      route: getUpdatedHashLink(aiImagesFolderName, 'rai'),
      routeId: 'rai',
      rootFolder: aiImagesFolderName
    },
    */
  ],
  menuItems: [{
    name: 'Account Main Page',
    icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />',
    route: getUpdatedHashLink('Home: Main Folder', 'main'),
    routeId: 'main',
    rootFolder: 'Home: Main Folder'
  }],
  externalMenuItems: [{
    name: 'Free Media Gallery',
    icon: '<path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />',
    route: '/viewall/',
    routeId: 'viewall',
    allowed: 'isFreeGalleryEligible',
  }],
  adminMenuItems: [
    {
      name: 'Uploads Moderation',
      icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0-10.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.75c0 5.592 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.57-.598-3.75h-.152c-3.196 0-6.1-1.25-8.25-3.286Zm0 13.036h.008v.008H12v-.008Z" />',
      route: '/account/admin/approve.php',
      allowed: 'isModerator',
    },
    {
      name: 'New Accounts',
      icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM3 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 9.374 21c-2.331 0-4.512-.645-6.374-1.766Z" />',
      route: '/account/admin/newacct.php',
      allowed: 'isAdmin',
    },
    {
      name: 'Update DB Tables',
      icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />',
      route: '/account/admin/update_db.php',
      allowed: 'isAdmin',
    },
    {
      name: 'Free Uploads Stats',
      icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5M9 11.25v1.5M12 9v3.75m3-6v6" />',
      route: '/account/admin/stats.php',
      allowed: 'isAdmin',
    },
    {
      name: 'Accounts Stats',
      icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M9.348 14.652a3.75 3.75 0 0 1 0-5.304m5.304 0a3.75 3.75 0 0 1 0 5.304m-7.425 2.121a6.75 6.75 0 0 1 0-9.546m9.546 0a6.75 6.75 0 0 1 0 9.546M5.106 18.894c-3.808-3.807-3.808-9.98 0-13.788m13.788 0c3.808 3.807 3.808 9.98 0 13.788M12 12h.008v.008H12V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />',
      route: '/account/admin/account_stats.php',
      allowed: 'isAdmin',
    },
    {
      name: 'Promotions',
      icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5m.75-9 3-3 2.148 2.148A12.061 12.061 0 0 1 16.5 7.605" />',
      route: '/account/admin/promo.php',
      allowed: 'isAdmin',
    },
    {
      name: 'BTCPayServer',
      icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" />',
      route: 'https://btcpay.nostr.build/',
      allowed: 'isAdmin',
    }
  ],
  folders: [],
  activeFolder: '',
  // Top most folders
  staticFolders: [{
    id: 0, // There is no actual folder so we use 0
    name: 'Home: Main Folder',
    icon: 'H',
    route: '#',
    allowDelete: false
  },
  {
    name: aiImagesFolderName,
    icon: 'A',
    route: '#',
    allowDelete: false
  },
  ],
  getFolderObjByName(folderName) {
    return this.folders.find(folder => folder.name === folderName);
  },
  getFolderNameById(folderId) {
    return this.folders.find(folder => folder.id === folderId)?.name;
  },
  setActiveMenuFromHash() {
    const params = new URLSearchParams(window.location.hash.slice(1));
    const menu = params.get('p');
    // concat the menuItemsAI with menuItems
    const menuItems = [...this.menuItemsAI, ...this.menuItems];
    // Find the menu name based on routeId, i.e., the page
    const activeMenu = menuItems.find(item => item.routeId === menu);
    // Set this.activeMenu to the activeMenu.name or the first menu item
    this.activeMenu = activeMenu ? activeMenu.name : this.menuItems[0].name;
  },
  setActiveFolder(folderName) {
    if (!this.foldersFetched || !folderName) {
      return;
    }
    // If already same folder, do nothing
    if (this.activeFolder === folderName) {
      return;
    }
    // Deal with fileStore
    const fileStore = Alpine.store('fileStore');
    const uppyStore = Alpine.store('uppyStore');
    // Close upload dialog
    uppyStore.mainDialog.close();
    // Clear the files list
    fileStore.files = [];
    this.activeFolder = folderName;
    updateHashURL(folderName);
    console.log('Active folder set:', folderName);
    // Reset filter
    fileStore.currentFilter = 'all';
    fileStore.fetchFiles(folderName, true).then(() => {
      // Update the folder stats
      this.refreshFoldersStats();
    });
  },
  foldersFetched: false,
  async fetchFolders() {
    const params = {
      action: 'list_folders',
    };

    const api = getApiFetcher(apiUrl, 'aplication/json');

    await api.get('', {
      params
    })
      .then(response => response.data)
      .then(data => {
        const folders = data || [];
        // Deduplicate folders by name property
        // Append fetched folders to this.folders
        this.folders = folders.reduce((acc, folder) => {
          const existingFolder = acc.find(f => f.name === folder.name);
          if (!existingFolder) {
            acc.push(folder);
          } else {
            existingFolder.id = folder.id;
            existingFolder.route = folder.route;
            existingFolder.icon = folder.icon;
          }
          return acc;
        }, this.folders);
        // Sort the fetched folders by name
        this.folders.sort((a, b) => a.name.localeCompare(b.name));

        // Update the staticFolders with fetched data and remove existing entries from this.folders
        this.staticFolders = this.staticFolders.map(staticFolder => {
          const existingFolder = this.folders.find(f => f.name === staticFolder.name);
          if (existingFolder) {
            Object.assign(staticFolder, existingFolder);
            this.folders = this.folders.filter(f => f.name !== staticFolder.name);
          }
          return staticFolder;
        });

        // Add the staticFolders to the beginning of the folders array
        this.folders = [...this.staticFolders, ...this.folders];
        // Folders have been fetched
        this.foldersFetched = true;
        //console.log('Folders fetched:', this.folders);
        // Set this.activeFolder to the value of URL's # parameter
        const url = new URL(window.location.href);
        const params = new URLSearchParams(url.hash.slice(1));
        const activeFolder = decodeURIComponent(params.get('f') || '');
        //console.log('Active folder:', activeFolder);
        //const defaultFolder = this.folders.length > 0 ? this.folders[0].name : '';
        // Set default folder as the one with id 0
        const defaultFolder = this.folders.find(f => f.id === 0).name;
        //console.log('Default folder:', defaultFolder);
        // If URL hash has a folder, set it as active folder, otherwise use defaul
        const folderToSet = this.folders.find(f => f.name === activeFolder) ? activeFolder : defaultFolder;
        //console.log('Folder to set:', folderToSet);
        this.setActiveFolder(folderToSet);
      }).catch(error => {
        console.error('Error fetching folders:', error);
      });
  },
  newFolderNameError: '',
  newFolderName: '',
  newFolderDialog: false,
  newFolderDialogOpen() {
    this.newFolderDialog = true;
  },
  newFolderDialogClose() {
    this.newFolderDialog = false;
    this.newFolderName = '';
    this.newFolderNameError = '';
  },
  newFolderDialogToggle() {
    if (this.newFolderDialog) {
      this.newFolderDialogClose();
    } else {
      this.newFolderDialogOpen();
    }
  },
  createFolder(folderName, callback) {
    // Empty?
    if (!folderName.trim()) {
      this.newFolderNameError = 'Empty folder name.';
      setTimeout(() => {
        this.newFolderNameError = '';
      }, 1000);
      return;
    }
    // Check if duplicate folder name
    if (this.folders.some(folder => folder.name === folderName)) {
      console.error('Folder already exists:', folderName);
      this.newFolderNameError = 'Folder already exists.'
      setTimeout(() => {
        this.newFolderNameError = '';
      }, 1000);
      return;
    }
    // Create new folder structure
    const folderNameNormalized = folderName.normalize('NFC'); // Normalize the string
    const firstChar = [...folderNameNormalized][0]; // Extract the first character as a string
    const newFolder = {
      name: folderName,
      icon: firstChar.toUpperCase(), // Uppercase the first character
      route: getUpdatedHashLink(folderName),
      allowDelete: true,
    };
    // Add new folder to the folders array
    this.folders.push(newFolder);
    // Close the dialog
    this.newFolderDialogClose();
    // Close mobile menu
    this.mobileMenuOpen = this.mobileMenuOpen ? false : this.mobileMenuOpen;
    // Ask fetchFiles to refresh the folders
    Alpine.store('fileStore').refreshFoldersAfterFetch = true;
    // Set new folder as active folder
    this.setActiveFolder(folderName);
    if (callback) {
      callback();
    }
    console.log('Folder created:', folderName);
  },
  showDeleteFolderButtons: false,
  showDeleteFolderModal: false,
  foldersToDeleteIds: [],
  foldersToDelete: [],
  isDeletingFolders: false,
  toggleDeleteFolderButtons() {
    this.showDeleteFolderButtons = !this.showDeleteFolderButtons;
  },
  disableDeleteFolderButtons() {
    this.showDeleteFolderButtons = false;
  },
  openDeleteFolderModal(folderIds) {
    // Check if array or not, and make it an array
    if (!Array.isArray(folderIds)) {
      folderIds = [folderIds];
    }
    // Check if array is empty and return if it is
    if (folderIds.length === 0) {
      return;
    }
    // Set folder IDs to delete while checking them agains this.folders
    this.foldersToDeleteIds = folderIds.filter(id => this.folders.some(folder => folder.id === id));
    this.foldersToDelete = this.folders.filter(folder => this.foldersToDeleteIds.includes(folder.id));
    // Check if activeFolder name matches any of the folders to delete
    if (this.foldersToDelete.some(folder => folder.name === this.activeFolder)) {
      //console.log('Cannot delete active folder:', this.activeFolder);
      return;
    }
    this.showDeleteFolderModal = true;
  },
  closeDeleteFolderModal() {
    this.foldersToDeleteIds = [];
    this.showDeleteFolderModal = false;
    this.showDeleteFolderButtons = false;
  },
  deleteFoldersConfirm() {
    if (this.foldersToDeleteIds.length === 0) {
      return;
    }

    // Set folder IDs to delete while checking them agains this.folders
    const folderIds = this.foldersToDeleteIds.filter(id => this.folders.some(folder => folder.id === id));

    this.isDeletingFolders = true;

    // Send request to delete folders
    this.deleteFolders(folderIds)
      .then(() => {
        this.closeDeleteFolderModal();
      })
      .catch(error => {
        console.error('Error deleting folders:', error);
      })
      .finally(() => {
        this.isDeletingFolders = false; // Reset the flag after folder deletion is complete
        this.fetchFolders(); // Refetch folders
      });
  },
  async deleteFolders(folderIds) {
    console.log('Deleting folders:', folderIds);

    const formData = {
      action: 'delete_folders',
      foldersToDelete: JSON.stringify(folderIds),
    };

    const api = getApiFetcher(apiUrl, 'multipart/form-data');

    return api.post('', formData)
      .then(response => response.data)
      .then(data => {
        console.log('Folders deleted:', data);
        const deletedFolders = data.deletedFolders || [];

        // Remove the deleted folders from this.folders
        this.folders = this.folders.filter(folder => !deletedFolders.includes(folder.id));
      })
      .catch(error => {
        console.error('Error deleting folders:', error);
      });
  },
  activeMenu: '',
  setActiveMenu(menuName) {
    this.activeMenu = menuName;
    // Find rootFolder from menuItemsAI or menuItems
    const menuItem = this.menuItemsAI.concat(this.menuItems).find(item => item.name === menuName);
    const rootFolder = menuItem ? menuItem.rootFolder : 'main';
    const routeId = menuItem ? menuItem.routeId : 'main';
    console.log('Active menu set:', menuName);
    // Update fileStore.fullWidth is menuName is in menuItemsAI
    const fileStore = Alpine.store('fileStore');
    fileStore.fullWidth = !this.menuItemsAI.some(item => item.name === menuName);
    console.log('Full width:', fileStore.fullWidth);
    updateHashURL(rootFolder, routeId);
    this.setActiveFolder(rootFolder);
  },
  updateTotalUsed(addUsed) {
    Alpine.store('profileStore').profileInfo.storageUsed += addUsed;
    //console.log('Total used updated:', Alpine.store('profileStore').profileInfo.storageUsed);
    //console.log('Total used ratio:', Alpine.store('profileStore').profileInfo.getStorageRatio());
    //console.log('Total used added:', addUsed);
  },
  fileStats: {
    totalFiles: 0,
    totalGifs: 0,
    totalImages: 0,
    totalVideos: 0,
    creatorCount: 0,
    totalFolders: 0,
    totalSize: 0,
    stats: {},
  },
  async refreshFoldersStats() {
    const api = getApiFetcher(apiUrl, 'application/json');
    const params = {
      action: 'get_folders_stats',
    };
    api.get('', {
      params
    })
      .then(response => response.data)
      .then(data => {
        this.fileStats.stats = data;
        // Convert potential strings into numbers
        this.fileStats.totalFiles = parseInt(data.totalStats.fileCount);
        this.fileStats.totalGifs = parseInt(data.totalStats.gifCount);
        this.fileStats.totalImages = parseInt(data.totalStats.imageCount);
        this.fileStats.totalVideos = parseInt(data.totalStats.avCount);
        this.fileStats.creatorCount = parseInt(data.totalStats.publicCount);
        this.fileStats.totalFolders = parseInt(data.totalStats.folderCount);
        this.fileStats.totalSize = parseInt(data.totalStats.totalSize);
      })
      .catch(error => {
        console.error('Error refreshing file stats:', error);
      });
  },
  formatNumberInThousands(number) {
    return number > 999 ? `${(number / 1000).toFixed(1)}k` : number;
  },
});

Alpine.store('fileStore', {
  files: [],
  filesById: {},
  loading: false,
  fullWidth: false,
  Files: {
    files: [],
    filesById: new Map(),
    filesByName: new Map(),

    get files() {
      return this.files;
    },

    get filesById() {
      return this.filesById;
    },

    get filesByName() {
      return this.filesByName;
    },

    set files(files) {
      this.files = [...files];
      this.updateMaps();
    },

    updateMaps() {
      this.filesById = new Map(this.files.map(file => [file.id, file]));
      this.filesByName = new Map(this.files.map(file => [file.name, file]));
    },

    addFile(file, position = 'bottom') {
      if (!this.filesById.has(file.id)) {
        if (position === 'top') {
          this.files.unshift(file);
        } else {
          this.files.push(file);
        }
        this.addFileToMaps(file);
      }
    },

    addFiles(newFiles, position = 'bottom') {
      const uniqueFiles = newFiles.filter(file => !this.filesById.has(file.id));
      if (position === 'top') {
        this.files.unshift(...uniqueFiles);
      } else {
        this.files.push(...uniqueFiles);
      }
      uniqueFiles.forEach(file => this.addFileToMaps(file));
    },

    addFileToMaps(file) {
      this.filesById.set(file.id, file);
      this.filesByName.set(file.name, file);
    },

    removeFile(file) {
      const index = this.files.findIndex(f => f.id === file.id);
      if (index !== -1) {
        this.files.splice(index, 1);
        this.removeFileFromMaps(file);
      }
    },

    removeFileFromMaps(file) {
      this.filesById.delete(file.id);
      this.filesByName.delete(file.name);
    },

    getFileByName(name) {
      return this.filesByName.get(name);
    },

    getFileById(id) {
      return this.filesById.get(id);
    }
  },
  moveToFolder: {
    isOpen: false,
    isLoading: false,
    isError: false,
    selectedIds: [],
    selectedFiles: [],
    destinationFolderId: null,
    hoveredFolder: null,
    isDropdownOpen: false,
    searchTerm: '',
    selectedFolderName: '',
    callback: null,
    open(ids, callback) {
      // Convert single ID to array
      if (!Array.isArray(ids)) {
        ids = [ids];
      }
      console.log('Opening move to folder modal:', ids);
      this.selectedIds = ids;
      this.selectedFiles = Alpine.store('fileStore').files.filter(file => ids.includes(file.id));
      console.log('Selected files:', this.selectedFiles);
      this.isOpen = true;
      this.callback = callback;
    },
    close(dontCallback) {
      this.selectedIds = [];
      this.selectedFiles = [];
      this.destinationFolderId = null;
      this.hoveredFolder = null;
      this.isDropdownOpen = false;
      this.searchTerm = '';
      this.selectedFolderName = '';
      this.isError = false;
      this.isOpen = false;
      this.isLoading = false;
      // Execute callback if provided
      if (this.callback && !dontCallback) {
        this.callback();
      }
    },
    toggleDropdown() {
      this.isDropdownOpen = !this.isDropdownOpen;
    },
  },
  moveToFolderConfirm() {
    this.moveToFolder.isLoading = true;
    // Check if destination folder is the same as the current folder
    // Get the folder name from the folders list based on id and compare it with the active name
    const destinationFolderName = Alpine.store('menuStore').folders.find(folder => folder.id === this.moveToFolder.destinationFolderId).name;
    if (destinationFolderName === Alpine.store('menuStore').activeFolder) {
      this.moveToFolder.close();
      this.moveToFolder.isLoading = false;
      return;
    }
    // Proceed otherwise
    this.moveItemsToFolder(this.moveToFolder.selectedIds, this.moveToFolder.destinationFolderId)
      .then(() => {
        this.moveToFolder.close();
        this.isError = false;
      })
      .catch(error => {
        console.error('Error moving files:', error);
        this.isError = true;
      })
      .finally(() => {
        this.moveToFolder.isLoading = false;
      });
  },
  async moveItemsToFolder(itemIds, folderId) {
    console.log('Moving items to folder:', itemIds, folderId);

    itemIds = Array.isArray(itemIds) ? itemIds : [itemIds];

    const formData = {
      action: 'move_to_folder',
      imagesToMove: JSON.stringify(itemIds),
      destinationFolderId: folderId,
    };

    const api = getApiFetcher(apiUrl, 'multipart/form-data');

    return api.post('', formData)
      .then(response => response.data)
      .then(data => {
        console.log('Moved items to folder:', data);
        const movedImageIds = data.movedImages || [];

        // Capture the original files length
        const originalFilesLength = this.files.length;
        // Remove moved images from the files list
        this.files = this.files.filter(file => !movedImageIds.includes(file.id));
        // Refresh the files starting at 0 and up to the original length + 
        this.fetchFiles(this.lastFetchedFolder, true);
      })
      .catch(error => {
        console.error('Error moving items to folder:', error);
      });
  },
  shareMedia: {
    isOpen: false,
    isLoading: false,
    isError: false,
    selectedIds: [],
    selectedFiles: [],
    callback: null,
    open(ids, callback) {
      // Convert single ID to array
      if (!Array.isArray(ids)) {
        ids = [ids];
      }
      console.log('Opening sharing modal:', ids);
      this.selectedIds = ids;
      this.selectedFiles = Alpine.store('fileStore').files.filter(file => ids.includes(file.id));
      console.log('Selected files:', this.selectedFiles);
      this.isOpen = true;
      this.callback = callback;
    },
    close(dontCallback) {
      this.selectedIds = [];
      this.selectedFiles = [];
      this.isError = false;
      this.isOpen = false;
      this.isLoading = false;
      // execute callback if provided
      if (this.callback && !dontCallback) {
        this.callback();
      }
    },
    getFlag() {
      return this.selectedFiles.length > 0 && this.selectedFiles[0].flag === 1;
    },
  },
  shareMediaCreatorConfirm(shareFlag) {
    this.shareMedia.isLoading = true;
    this.shareItemsCreatorPage(shareFlag)
      .then(() => {
        this.isError = false;
      })
      .catch(error => {
        console.error('Error sharing files:', error);
        this.isError = true;
      })
      .finally(() => {
        this.shareMedia.isLoading = false;
      });
  },
  async shareItemsCreatorPage(shareFlag) {
    console.log('Sharing media on Creators page:', this.shareMedia.selectedIds);

    const itemsToShare = Array.isArray(this.shareMedia.selectedIds) ? this.shareMedia.selectedIds : [this.shareMedia.selectedIds];
    const formData = {
      action: 'share_creator_page',
      shareFlag: shareFlag,
      imagesToShare: JSON.stringify(itemsToShare),
    };

    const api = getApiFetcher(apiUrl, 'multipart/form-data');

    return api.post('', formData)
      .then(response => response.data)
      .then(data => {
        //console.log('Shared media on Creators page:', data);
        const sharedImageIds = data.sharedImages || [];
        const menuStore = Alpine.store('menuStore');

        // Update the shared flag and count for each file
        this.files.forEach(file => {
          if (sharedImageIds.includes(file.id)) {
            file.flag = shareFlag ? 1 : 0;
            menuStore.fileStats.creatorCount += shareFlag ? 1 : -1;
          }
        });
      })
      .catch(error => {
        console.error('Error sharing media on Creators page:', error);
      });
  },
  deleteConfirmation: {
    isOpen: false,
    isLoading: false,
    isError: false,
    selectedIds: [],
    selectedFiles: [],
    callback: null,
    open(ids, callback) {
      // Convert single ID to array
      if (!Array.isArray(ids)) {
        ids = [ids];
      }
      console.log('Opening delete confirmation:', ids);
      this.selectedIds = ids;
      this.selectedFiles = Alpine.store('fileStore').files.filter(file => ids.includes(file.id));
      console.log('Selected files:', this.selectedFiles);
      this.isOpen = true;
      this.callback = callback;
    },
    close(dontCallback) {
      this.selectedIds = [];
      this.selectedFiles = [];
      this.isError = false;
      this.isOpen = false;
      this.isLoading = false;
      if (this.callback && !dontCallback) {
        this.callback();
      }
    }
  },
  confirmDelete() {
    this.deleteConfirmation.isLoading = true;
    this.deleteItem(this.deleteConfirmation.selectedIds)
      .then(() => {
        this.deleteConfirmation.close();
        this.isError = false;
      })
      .catch(error => {
        console.error('Error deleting files:', error);
        this.isError = true;
      })
      .finally(() => {
        this.deleteConfirmation.isLoading = false;
      });
  },
  async deleteItem(itemIds) {
    console.log('Deleting image:', itemIds);

    itemIds = Array.isArray(itemIds) ? itemIds : [itemIds];

    const formData = {
      action: 'delete',
      imagesToDelete: JSON.stringify(itemIds),
    };

    const api = getApiFetcher(apiUrl, 'multipart/form-data');
    const menuStore = Alpine.store('menuStore');

    return api.post('', formData)
      .then(response => response.data)
      .then(data => {
        console.log('Deleted image:', data);
        const deletedImageIds = data.deletedImages || [];

        // Collect stats for deleted files
        const stats = deletedImageIds.reduce((acc, id) => {
          const file = this.files.find(f => f.id === id);
          if (file) {
            acc.totalSize += file.size;
            acc.totalCount++;
            if (file.name.endsWith('.gif')) {
              acc.gif++;
            } else {
              acc[file.mime.split('/')[0]]++;
            }
          }
          return acc;
        }, {
          totalSize: 0,
          totalCount: 0,
          image: 0,
          gif: 0,
          video: 0,
          audio: 0
        });

        // Capture the original files length
        const originalFilesLength = this.files.length;
        // Remove deleted images from the grid
        this.files = this.files.filter(f => !deletedImageIds.includes(f.id));
        // Refresh the files starting at 0 and up to the original length + 
        this.fetchFiles(this.lastFetchedFolder, true);

        // Update file stats
        menuStore.fileStats.totalImages -= stats.image;
        menuStore.fileStats.totalGifs -= stats.gif;
        menuStore.fileStats.totalVideos -= stats.video + stats.audio;
        menuStore.fileStats.totalFiles -= stats.totalCount;
        menuStore.updateTotalUsed(-stats.totalSize);
      })
      .catch(error => {
        console.error('Error deleting image:', error);
      });
  },
  // Filter: "all", "images", "videos", "audio", 'gifs'
  currentFilter: 'all',
  setFilter(filter) {
    this.currentFilter = filter;
    this.fetchFiles(this.lastFetchedFolder, true);
  },
  filterMenuOpen: false,
  fileFetchStart: 0,
  fileFetchLimit: 48,
  fileFetchHasMore: true,
  lastFetchedFolder: '',
  loadingMoreFiles: false,
  refreshFoldersAfterFetch: false,
  async fetchFiles(folder, refresh = false) {
    //console.log('Fetching files:', folder, start, limit, refresh);
    const uppyStore = Alpine.store('uppyStore');

    if (!folder) {
      this.resetFetchFilesState();
      console.log('Empty folder:', folder);
      return;
    }

    if (this.lastFetchedFolder !== folder) {
      this.resetFetchFilesState();
      this.lastFetchedFolder = folder;
      this.loading = true;
      console.log('Folder changed:', folder);
    } else {
      this.loadingMoreFiles = true;
      console.log('Fetching more files...');
    }

    if (!this.fileFetchHasMore && !refresh) {
      this.loading = false;
      this.loadingMoreFiles = false;
      console.log('No more files to fetch.');
      return;
    }

    const fetchLimit = refresh ? (this.files.length + this.fileFetchLimit) : this.fileFetchLimit;
    const params = {
      action: 'list_files',
      folder: folder,
      start: refresh ? 0 : this.fileFetchStart,
      limit: fetchLimit,
      filter: this.currentFilter,
    };
    //console.log('Fetching files:', params);

    const api = getApiFetcher(apiUrl, 'application/json');

    try {
      const response = await api.get('', {
        params
      });
      const data = response.data;

      if (data.error) {
        console.error('Error fetching files:', data.error);
        this.loadingMoreFiles = false;
        throw new Error(data.error);
      }

      if (data && (data.length > 0 || refresh)) {
        if (!refresh) {
          const hasDuplicates = data.some(file => this.files.some(f => f.id === file.id));
          if (hasDuplicates) {
            console.log('Duplicate files found, refreshing...');
            await this.fetchFiles(folder, true);
            return;
          }
          this.files = [...uppyStore.mainDialog.getFilesInFolder(folder) ?? [], ...this.files, ...data];

          this.fileFetchHasMore = data.length === this.fileFetchLimit;
          this.fileFetchStart += data.length;
        } else {
          // Create a Map of existing files for faster lookup
          const existingFilesMap = new Map(this.files.map(file => [file.id, file]));

          // Update the loaded state of files in data based on the existing files
          data.forEach(file => {
            const existingFile = existingFilesMap.get(file.id);
            if (existingFile) {
              file.loaded = existingFile.loaded;
            }
          });

          // Replace this.files with the updated data array
          this.files = [...uppyStore.mainDialog.getFilesInFolder(folder) ?? [], ...data];

          const expectedLength = fetchLimit;
          this.fileFetchHasMore = data.length === expectedLength;
          this.fileFetchStart = data.length;
        }

        //console.log('Parameters:', this.fileFetchStart, this.fileFetchLimit, this.fileFetchHasMore, this.files.length, data.length);

        if (this.fileFetchHasMore) {
          const lastFileIndex = this.files.length - Math.floor(this.fileFetchLimit * 0.2) - 1;
          this.files[lastFileIndex].loadMore = true;
        }
      } else {
        this.fileFetchHasMore = false;
        console.log('No more files to fetch.');
      }
    } catch (error) {
      console.error('Error fetching files:', error);
    } finally {
      this.loading = false;
      this.loadingMoreFiles = false;

      if (this.refreshFoldersAfterFetch) {
        console.log('Refetching folders...');
        Alpine.store('menuStore').fetchFolders();
        this.refreshFoldersAfterFetch = false;
      }
    }
  },
  async loadMoreFiles() {
    console.log('Loading more triggered.');
    if (!this.loading && this.fileFetchHasMore && !this.loadingMoreFiles) {
      this.loadingMoreFiles = true;

      // Find the last file object with loadMore property defined and set to true
      // and remove it.
      const lastFileIndex = this.files.findIndex(f => f.loadMore);
      if (lastFileIndex > -1) {
        delete this.files[lastFileIndex].loadMore;
      }
      //console.log('Last file:', this.files[lastFileIndex]);

      await this.fetchFiles(this.lastFetchedFolder)
        .finally(() => {
          console.log('Loading more done.');
          this.loadingMoreFiles = false;
        });
    }
  },
  resetFetchFilesState() {
    this.files = [];
    this.filesById = {};
    this.fileFetchStart = 0;
    this.fileFetchHasMore = true;
    this.lastFetchedFolder = '';
    this.loading = false;
    this.loadingMoreFiles = false;
  },
  injectFile(file) {
    console.log('Injecting file:', file);
    this.files.unshift(file);
  },
  modalFile: {},
  modalOpen: false,
  modalImageUrl: '',
  modalImageSrcset: '',
  modalImageSizes: '',
  modalImageAlt: '',
  modalImageDimensions: '',
  modalImageFilesize: '',
  modalImageTitle: '',
  modalImagePrompt: '',
  openModal(file) {
    // Lock body scroll
    lock();
    this.modalFile = file;
    this.modalImageUrl = file.url;
    this.modalImageSrcset = file.srcset;
    this.modalImageSizes = file.sizes;
    this.modalImageAlt = file.title || file.name;
    this.modalImageDimensions = `${file.width}x${file.height}`;
    this.modalImageFilesize = file.size;
    this.modalImageTitle = file.title || '';
    this.modalImagePrompt = file.ai_prompt || '';
    this.modalOpen = true;
  },
  updateModal(file) {
    this.modalFile = file;
    this.modalImageUrl = file.url;
    this.modalImageSrcset = file.srcset;
    this.modalImageSizes = file.sizes;
    this.modalImageAlt = file.title || file.name;
    this.modalImageDimensions = `${file.width}x${file.height}`;
    this.modalImageFilesize = file.size;
    this.modalImageTitle = file.title || '';
    this.modalImagePrompt = file.ai_prompt || '';
  },
  closeModal() {
    clearBodyLocks();
    this.modalOpen = false;
    this.modalFile = {};
    this.modalImageUrl = '';
    this.modalImageSrcset = '';
    this.modalImageSizes = '';
    this.modalImageAlt = '';
    this.modalImageDimensions = '';
    this.modalImageFilesize = '';
    this.modalImageTitle = '';
    this.modalImagePrompt = '';
  },
  async modalNext() {
    if (this.modalFile.loadMore) {
      // Try fetching more files
      await this.loadMoreFiles();
    }
    const nextFile = this.getNextFile(this.modalFile);
    this.updateModal(nextFile);
  },
  async modalPrevious() {
    if (this.modalFile.loadMore) {
      // Try fetching more files
      await this.loadMoreFiles();
    }
    const previousFile = this.getNextFile(this.modalFile, true);
    this.updateModal(previousFile);
  },
  getNextFile(file, reverse) {
    // Return a file object based on the current file and direction
    const currentIndex = this.files.findIndex(f => f.id === file.id);

    let nextIndex;
    if (reverse) {
      // If going in reverse direction
      nextIndex = currentIndex - 1;
      if (nextIndex < 0) {
        // If at the beginning, wrap around to the last file
        nextIndex = this.files.length - 1;
      }
    } else {
      // If going in forward direction
      nextIndex = currentIndex + 1;
      if (nextIndex >= this.files.length) {
        // If at the end, wrap around to the first file
        nextIndex = 0;
      }
    }

    const nextFile = this.files[nextIndex];
    return nextFile || file;
  },
});

// URL Import Store
Alpine.store('urlImportStore', {
  isLoading: false,
  isError: false,
  errorMessage: '',
  importURL: '',
  importFolder: '',
  clear(callback) {
    this.isLoading = false;
    this.isError = false;
    this.errorMessage = '';
    this.importURL = '';
    this.importFolder = '';
    if (typeof callback === 'function') {
      callback();
    }
  },
  setErrorWithTimeout(message) {
    this.isError = true;
    this.errorMessage = message;
    setTimeout(() => {
      this.isError = false;
      this.errorMessage = '';
    }, 5000);
  },
  async importFromURL() {
    console.log('Importing from URL:', this.importURL, this.importFolder);
    // Validate URL
    if (!this.importURL.startsWith('http://') &&
      !this.importURL.startsWith('https://')) {
      console.log('Invalid URL:', this.importURL);
      this.setErrorWithTimeout('URL is empty or invalid.');
      return;
    }

    this.isLoading = true;
    this.isError = false;
    this.errorMessage = '';

    // Stores
    const menuStore = Alpine.store('menuStore');
    const fileStore = Alpine.store('fileStore');

    // Store folder name
    const folderName = menuStore.activeFolder;

    // Check if folderName is default home folder
    const importToHomeFolder = menuStore.folders.find(folder => folder.name === folderName).id === 0;

    const formData = {
      action: 'import_from_url',
      url: this.importURL,
      folder: importToHomeFolder ? '' : folderName,
    };

    const api = getApiFetcher(apiUrl, 'multipart/form-data');

    return api.post('', formData)
      .then(response => response.data)
      .then(data => {
        if (data.error) {
          console.error('Error importing from URL:', data.error);
          this.isLoading = false;
          this.setErrorWithTimeout('Error importing from URL.');
          return;
        }
        console.log('Import from URL:', data);
        // Is current active folder a home folder?
        const home = menuStore.folders.find(folder => folder.name === menuStore.activeFolder).id === 0;
        // Check if the we are in the same folder as the imported file
        if (menuStore.activeFolder === folderName || (home && importToHomeFolder)) {
          // Inject the imported file into the files array
          fileStore.injectFile(data);
        }
        this.clear();
      })
      .catch(error => {
        console.error('Error importing from URL:', error);
        this.isLoading = false;
        this.setErrorWithTimeout('Error importing from URL.');
      });
  }
});

// Uppy Store
Alpine.store('uppyStore', {
  // Array of filepond instances
  instance: null,
  // Dialog state
  mainDialog: {
    dialogEl: null,
    isOpen: false,
    isLoading: false,
    uploadProgress: null,
    uploadFolder: '',
    isError: false,
    errorMessage: '',
    callback: null,
    open(callback) {
      this.isOpen = true;
      this.callback = callback;
    },
    close(dontCallback) {
      this.isOpen = false;
      //this.isLoading = false; // Should be controlled by Uppy
      this.isError = false;
      this.errorMessage = '';
      if (this.callback && !dontCallback) {
        this.callback();
      }
    },
    toggle() {
      this.isOpen ? this.close() : this.open();
    },
    currentFiles: [],
    currentFilesById: new Map(),
    addFile(file) {
      this.currentFiles.unshift(file);
      // Update currentFilesById Map
      if (this.currentFilesById.has(file.id)) {
        this.currentFilesById.set(file.id, file);
      } else {
        this.currentFilesById = new Map([...this.currentFilesById, [file.id, file]]);
      }
    },
    removeFile(fileId) {
      const index = this.currentFiles.findIndex(f => f.id === fileId);
      if (index !== -1) {
        this.currentFiles.splice(index, 1);
        // Update currentFilesById Map
        this.currentFilesById.delete(fileId);
      }
    },
    getFileById(fileId) {
      return this.currentFilesById.get(fileId);
    },
    clearFiles() {
      this.currentFiles = [];
      this.currentFilesById = new Map();
    },
    getFilesInFolder(folderName) {
      // Check for default home folder
      folderName = Alpine.store('menuStore').folders.find(folder => folder.name === folderName).id === 0 ? '' : folderName;
      return this.currentFiles.filter(file => file.folder === folderName);
    }
  },
  instantiateUppy(el, dropTarget, onDropCallback, onDragOverCallback, onDragLeaveCallback) {
    this.mainDialog.dialogEl = el;
    console.log('Instantiating Uppy...');
    // Stores
    const menuStore = Alpine.store('menuStore');
    const fileStore = Alpine.store('fileStore');
    const profileStore = Alpine.store('profileStore');

    this.instance = new Uppy({
      debug: false,
      autoProceed: true, // Automatically upload files after adding them
      allowMultipleUploadBatches: false, // Disallow multiple upload batches
      allowedFileTypes: ['image/*', 'video/*', 'audio/*'],
      restrictions: {
        maxFileSize: 4096 * 1024 * 1024, // 4 GB
        maxTotalFileSize: profileStore.profileInfo.storageRemaining,
        allowedFileTypes: ['image/*', 'video/*', 'audio/*'],
      },
      //maxTotalFileSize: 150 * 1024 * 1024,
      onBeforeFileAdded: (currentFile, files) => {
        const allowedTypes = ['video', 'audio', 'image'];
        const fileType = currentFile.type.split('/')[0]; // Extract the file type from the MIME type

        if (!allowedTypes.includes(fileType)) {
          // log to console
          this.instance.log(`Skipping file ${currentFile.name} because it's not a video, audio, or image`);
          // show error message to the user
          this.instance.info(`Skipping file ${currentFile.name} because it's not a video, audio, or image`, 'error', 500);
          return false; // Exclude the file
        }

        // Prevent SVG files from being uploaded
        if (currentFile.type === 'image/svg+xml' || currentFile.name.endsWith('.svg')) {
          this.instance.info(`Skipping file ${currentFile.name} because SVG files are not allowed`, 'error', 500);
          return false; // Exclude the file
        }

        // Prevent PSD files from being uploaded
        if (currentFile.type === 'image/vnd.adobe.photoshop' || currentFile.name.endsWith('.psd')) {
          this.instance.info(`Skipping file ${currentFile.name} because PSD files are not allowed`, 'error', 500);
          return false; // Exclude the file
        }

        return true; // Include the file
      },
    })
      //.use(GoldenRetriever)
      .use(Dashboard, {
        target: el,
        inline: true,
        //trigger: '#open-account-dropzone-button',
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
        timeout: 0, // Unlimited timeout
        meta: {
          folderName: '', // Initialize folderName metadata
          folderHierarchy: [], // Initialize folderHierarchy metadata
        },
      })
      .use(DropTarget, {
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
      })
      .on('upload-success', (file, response) => {
        if (Array.isArray(response.body)) {
          const fileResponse = response.body.find(f => f.id === file.id);
          if (fileResponse) {
            this.instance.setFileMeta(file.id, {
              name: fileResponse.name,
              type: fileResponse.type,
              size: fileResponse.size
            });
          }
        } else {
          this.instance.setFileMeta(file.id, {
            name: response.body.name,
            type: response.body.type,
            size: response.body.size
          });
        }
      })
      .on('upload-success', (file, response) => {
        console.log('Upload result:', response);
        // Set uploadComplete state for the file
        file.progress.uploadComplete = true;
        //this.instance.removeFile(file.id);
        const fd = response.body.fileData;
        console.log('File uploaded:', fd);
        // Get folderName from uppy file metadata
        const folderName = JSON.parse(file.meta.folderName);
        // Check if folderName is default home folder
        const fileFolderName = file.meta.folderName === '' ? menuStore.folders.find(folder => folder.id === 0).name : folderName;
        // Inject file into the fileStore files array if activeFolder matches 
        if (menuStore.activeFolder === fileFolderName) {
          // Remove the file from the currentUploads
          this.mainDialog.removeFile(file.id);
          // Remove the file with file.id from the fileStore.files array
          fileStore.files = fileStore.files.filter(f => f.id !== file.id);
          // Inject fd into the fileStore.files array if it doesn't exist
          if (!fileStore.files.some(f => f.id === fd.id)) {
            fileStore.injectFile(fd);
          }
        } else {
          // Remove the file from the currentUploads
          this.mainDialog.removeFile(file.id);
        }
      })
      .on('file-added', (file) => {
        // Check if the active folder ID is not 0
        const activeFolder = menuStore.activeFolder;
        const activeFolderId = menuStore.folders.find(folder => folder.name === activeFolder).id;
        const defaultFolder = activeFolderId === 0 ? '' : activeFolder;
        console.log('Active folder (Uppy):', activeFolder, activeFolderId, defaultFolder);
        //console.log('Added file', file);
        const path = file.data.relativePath ?? file.data.webkitRelativePath;
        let folderHierarchy = [defaultFolder];
        let folderName = defaultFolder;

        if (path && activeFolderId === 0) {
          const folderPath = path.replace(/\\/g, '/'); // Normalize backslashes to forward slashes
          const folderPathParts = folderPath.split('/').filter(part => part !== '');
          folderHierarchy = folderPathParts.length > 1 ? folderPathParts.slice(0, -1) : [defaultFolder];
          folderName = folderHierarchy.length > 0 ? folderHierarchy[folderHierarchy.length - 1] : defaultFolder;
          this.mainDialog.uploadFolder = folderName;
          // Add folder to the folder list if not already present
          const folderExists = menuStore.folders.some(folder => folder.name === folderName);
          if (!folderExists) {
            const folder = {
              id: folderName,
              name: folderName,
              icon: folderName.substring(0, 1).toUpperCase(),
              route: '#',
              allowDelete: false
            };
            // Add the folder to the folders list
            menuStore.folders.push(folder);
            // Sort the folders list
            menuStore.folders = menuStore.folders.sort((a, b) => a.name.localeCompare(b.name));
          }
        } else {
          this.mainDialog.uploadFolder = defaultFolder;
        }
        console.log('Folder name', folderName);
        console.log('Folder hierarchy', folderHierarchy);
        this.instance.setFileMeta(file.id, {
          folderName: JSON.stringify(folderName),
          folderHierarchy: JSON.stringify(folderHierarchy),
        });
        console.log('File added:', file);
        const currentFile = {
          id: file.id,
          name: file.name,
          mime: 'uppy/upload',
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
        // Add the file to the currentUploads
        this.mainDialog.addFile(currentFile);
        if (folderName === defaultFolder) {
          fileStore.injectFile(currentFile);
        }
      })
      .on('upload', (data) => {
        console.log('Upload started:', data);
        this.mainDialog.isLoading = true;
      })
      .on('complete', (result) => {
        // Iterate of the successful uploads and see if any of them have folderName metadata
        // that match menuStore.activeFolder
        const isInHomeFolder = menuStore.folders.find(folder => folder.name === menuStore.activeFolder).id === 0;
        const activeFolderMatch = result.successful.some(file => {
          const folderName = JSON.parse(file.meta.folderName);
          return folderName === menuStore.activeFolder || (isInHomeFolder && folderName === '');
        });
        // If the failed upload count is zero, all uploads succeeded
        if (result.failed.length === 0) {
          //location.reload(); // reload the page
          console.log('Upload complete:', result);
          // Mark dialog as done!
          this.instance.cancelAll();
          // Close the dialog
          this.mainDialog.close();
          // Clear progress
          this.mainDialog.uploadProgress = null;
          // Clear currentFiles
          this.mainDialog.clearFiles();
        } else {
          // Remove successful uploads
          result.successful.forEach(file => {
            this.instance.removeFile(file.id);
            this.mainDialog.removeFile(file.id);
          });
          // Open the dialog
          this.mainDialog.open();
        }
        // Refresh the files
        if (activeFolderMatch) {
          // We are still in the same folder, so refresh the files
          console.log('Refreshing files:', menuStore.activeFolder);
          fileStore.refreshFoldersAfterFetch = true;
          fileStore.fetchFiles(menuStore.activeFolder, true);
        } else {
          // We are not in the same folder, we may want to preemtively refresh folder list
          menuStore.fetchFolders();
        }
        // Reset the isLoading state
        this.mainDialog.isLoading = false;
      })
      .on('progress', (progress) => {
        // progress: integer (total progress percentage)
        this.mainDialog.uploadProgress = progress + '%';
      })
      .on('upload-progress', (file, progress) => {
        // Update the file progress in the fileStore
        const fileData = this.mainDialog.getFileById(file.id);
        const fileProgress = progress.bytesUploaded / progress.bytesTotal;
        if (fileData) {
          fileData.uppy.progress = Math.round(fileProgress * 100);
          fileData.uppy.bytesUploaded = progress.bytesUploaded;
        }
      })
      .on('upload-error', (file, error, response) => {
        console.log('error with file:', file.id);
        console.log('error message:', error);
        console.log('error response:', response);
        // Find the file in the fileStore and mark it as errored
        const fileData = this.mainDialog.getFileById(file.id);
        if (fileData) {
          fileData.uppy.uploadError = true;
          fileData.uppy.errorMessage = error.message;
          fileData.uppy.errorResponse = response;
        }
      })
      .on('info-visible', () => {
        const { info } = this.instance.getState();
        // info: {
        //  isHidden: false,
        //  type: 'error',
        //  message: 'Failed to upload',
        //  details: 'Error description'
        // }
        console.log(`Info: ${info.type} ${info.message} ${info.details}`);
      })
      .on('file-removed', (file) => {
        console.log('File removed:', file);
        // Remove the file from the fileStore
        this.mainDialog.removeFile(file.id);
        // Remove file from the fileStore
        fileStore.files = fileStore.files.filter(f => f.id !== file.id);
        // If no more files in uppy reset prgress and close the dialog
        if (this.instance.getFiles().length === 0) {
          this.mainDialog.uploadProgress = null;
          this.mainDialog.isLoading = false;
          this.mainDialog.clearFiles();
        }
      })
      .on('upload-retry', (fileId) => {
        console.log('Retrying upload:', fileId);
        // Reset the uploadError state
        const fileData = this.mainDialog.getFileById(fileId);
        if (fileData) {
          fileData.uppy.uploadError = false;
          fileData.uppy.errorMessage = '';
          fileData.uppy.errorResponse = null;
        }
      })
      .on('retry-all', () => {
        console.log('Retrying all uploads');
        // Reset the uploadError state for all files
        this.mainDialog.currentFiles.forEach(file => {
          file.uppy.uploadError = false;
          file.uppy.errorMessage = '';
          file.uppy.errorResponse = null;
        });
      })
      .on('thumbnail:generated', (file, preview) => {
        // This depends on Dashboard plugin
        // Update the file preview in the fileStore
        const fileData = this.mainDialog.getFileById(file.id);
        if (fileData) {
          fileData.uppy.preview = preview;
        }
      });
    console.log('Uppy instance created:', el.id);
    // Dynamic note
    Alpine.effect(() => {
      if (this.instance) {
        // Determine if user has less than 4GB remaining storage
        const byteLimit = (profileStore.profileInfo.storageRemaining < 4 * 1024 * 1024 * 1024) ?
          profileStore.profileInfo.storageRemaining : 4 * 1024 * 1024 * 1024;
        const note = `Images, video and audio only, up to your storage limit, and ${formatBytes(byteLimit)} per file`;
        this.instance.setOptions({
          restrictions: {
            maxFileSize: byteLimit,
            maxTotalFileSize: profileStore.profileInfo.storageRemaining,
            allowedFileTypes: ['image/*', 'video/*', 'audio/*'],
          },
        });
        this.instance.getPlugin('Dashboard').setOptions({
          note: note,
        });
      }
    });
  },
});


Alpine.store('GAI', {
  ImageShow: false,
  ImageLoading: false,
  ImageUrl: '',
  ImageTitle: '',
  ImagePrompt: '',
  ImageFilesize: '',
  ImageDimensions: '0x0',
  file: {},
  clearImage() {
    this.ImageShow = false;
    this.ImageUrl = '';
    this.ImageTitle = '';
    this.ImagePrompt = '';
    this.ImageFilesize = '';
    this.ImageDimensions = '0x0';
  },
  async generateImage(title, prompt, selectedModel) {
    // Access the form inputs passed as arguments
    console.log('Title:', title);
    console.log('Prompt:', prompt);
    console.log('Selected Model:', selectedModel);
    // Switch to aiImagesFolderName folder
    menuStore = Alpine.store('menuStore');
    if (menuStore.activeFolder !== aiImagesFolderName) {
      console.log('Switching to folder:', aiImagesFolderName);
      console.log('Current folder:', menuStore.activeFolder);
      menuStore.setActiveFolder(aiImagesFolderName);
    }
    // Prepare form data to send to the server
    const formData = {
      title: title,
      prompt: prompt,
      model: selectedModel,
      action: 'generate_ai_image',
    };

    // Send the form data to the server
    this.ImageShow = false;
    this.ImageLoading = true;
    const api = getApiFetcher(apiUrl, 'multipart/form-data');

    api.post('', formData, {
      timeout: 60000 // AI model generation can take a while
    })
      .then(response => response.data)
      .then(data => {
        console.log('Generated image:', data);
        this.ImageUrl = data.url;
        this.ImageFilesize = data.size;
        this.ImageDimensions = `${data.width}x${data.height}`;
        this.ImageTitle = title.length > 0 ? title : data.name;
        this.ImagePrompt = prompt;
        //this.ImageShow = true;

        data.title = title;
        data.ai_prompt = prompt;
        // Add file to the grid
        Alpine.store('fileStore').injectFile(data);
        // Update file stats
        menuStore.fileStats.totalImages++;
        menuStore.fileStats.totalFiles++;
        menuStore.updateTotalUsed(data.size);
        this.file = data;
      })
      .catch(error => {
        console.error('Error generating image:', error);
        this.ImageLoading = false;
      })
      .finally(() => {
        this.ImageLoading = false;
        console.log('Image loading:', this.ImageLoading);
      });
  },
});

// Register an AlpineJS effect to warn users about leaving the page or refreshing when uploading files via uppy or URL import
Alpine.effect(() => {
  const uppyStore = Alpine.store('uppyStore');
  const urlImportStore = Alpine.store('urlImportStore');
  const GAI = Alpine.store('GAI');
  const menuStore = Alpine.store('menuStore');
  const fileStore = Alpine.store('fileStore');
  const profileStore = Alpine.store('profileStore');
  const isUploading = uppyStore.mainDialog.isLoading;
  const isImporting = urlImportStore.isLoading;
  const isGenerating = GAI.ImageLoading;
  const isUploadingFiles = isUploading || isImporting || isGenerating;

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

// MUST BE EXECUTED AFTER ALL STORES ARE DEFINED
Alpine.start();

