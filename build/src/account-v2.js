import Uppy from '@uppy/core';
import Dashboard from '@uppy/dashboard';
//import GoldenRetriever from '@uppy/golden-retriever';
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
import axios from 'axios';
import axiosRetry from 'axios-retry';
import Alpine from 'alpinejs';

import intersect from '@alpinejs/intersect';
import focus from '@alpinejs/focus';
import persist from '@alpinejs/persist';

// Icons
import { getIconByMime, getIcon } from '../lib/icons';
window.getIconByMime = getIconByMime;
window.getIcon = getIcon;

import { nip19 } from 'nostr-tools';

// Chart.js
import Chart from 'chart.js/auto';
//import 'chartjs-adapter-luxon';
import 'chartjs-scale-timestack';

Alpine.plugin(focus);
Alpine.plugin(intersect);
Alpine.plugin(persist);

window.Alpine = Alpine;

window.getApiFetcher = function (baseUrl, contentType = 'multipart/form-data', timeout = 30000) {
  const api = axios.create({
    baseURL: baseUrl,
    headers: {
      'Content-Type': contentType,
    },
    timeout: timeout,
    withCredentials: true,
  });

  // Add a response interceptor to handle HTTP 401
  api.interceptors.response.use(
    (response) => response,
    (error) => {
      if (error.response && error.response.status === 401) {
        // Perform special functions for HTTP 401 error
        console.debug('HTTP 401 Unauthorized error encountered');
        Alpine.store('profileStore').unauthenticated = true;
      }
      return Promise.reject(error);
    }
  );

  axiosRetry(api, {
    retries: 3, // Make it resilient
    //retryDelay: axiosRetry.exponentialDelay,
    retryDelay: (
      retryNumber = 0,
      _error = undefined,
      delayFactor = 300 // Slow down there, cowboy
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

// Image variants pre-cache
window.imageVariantsPrecache = async (urls) => {
  const promises = urls.map((url) => {
    return new Promise((resolve, reject) => {
      const img = new Image();
      img.onload = () => {
        img.onload = null;
        img.onerror = null;
        img.src = '';
        resolve();
      };
      img.onerror = () => {
        img.onload = null;
        img.onerror = null;
        img.src = '';
        reject(new Error(`Failed to load image: ${url}`));
      };
      img.src = url;
    });
  });

  try {
    await Promise.all(promises);
    console.log('All images preloaded successfully.');
  } catch (error) {
    console.error('Error preloading images:', error);
  }
};

// Check URLs Virus scanning status
window.checkMediaVirusScanStatus = async (url, mediaType) => {
  if (['image', 'video', 'audio'].includes(mediaType)) {
    // No scan needed for images, videos, and audio
    return null;
  }
  // HEAD request to check the URL
  return fetch(url,
    {
      method: 'HEAD',
      credentials: 'include',
      mode: 'cors',
      cache: 'no-store',
      redirect: 'follow',
      referrerPolicy: 'no-referrer',
      onerror: (error) => {
        console.error('Error scanning URL:', error);
        return false;
      }
    })
    .then(response => {
      switch (response.status) {
        case 200:
          // If the user is logged in, check the heasders for the virus scan status
          const scanStatus = response.headers.get('x-virus-scan-result') || 'pending'; // Default to pending
          const scanMessage = response.headers.get('x-virus-scan-message') || 'Pending virus scan'; // Default to pending
          const scanDate = response.headers.get('x-virus-scan-date') || 'Pending virus scan'; // Default to pending
          const scanVersion = response.headers.get('x-virus-scan-version') || 'Pending virus scan'; // Default to pending
          console.debug('URL scan status:', scanStatus, scanMessage, scanDate, scanVersion);
          return {
            status: scanStatus,
            message: scanMessage,
            date: scanDate,
            version: scanVersion,
            previewMessage: () => { switch (scanStatus) { case 'clean': return 'Scanned & clean'; case 'pending': return 'Pending virus scan'; case 'infected': return 'Infected with virus'; default: return 'Unknown status'; } },
          };
        case 403: // Not yet scanned
          return {
            status: 'pending',
            message: 'Pending virus scan',
            date: 'Pending virus scan',
            version: 'Pending virus scan',
            previewMessage: 'Pending virus scan',
          };
        case 451: // Infected
          return {
            status: 'infected',
            message: 'Infected with virus',
            date: 'Infected with virus',
            version: 'Infected with virus',
            previewMessage: 'Infected with virus',
          };
        default: // Unknown status
          return {
            status: 'unknown',
            message: 'Unknown status',
            date: 'Unknown status',
            version: 'Unknown status',
            previewMessage: 'Unknown status',
          }
      }
    })
    .catch((error) => {
      console.error('Error scanning URL:', error);
      return {
        status: 'unknown',
        message: 'Unknown status',
        date: 'Unknown status',
        version: 'Unknown status',
        previewMessage: 'Unknown status',
      }
    });
};

window.formatBytes = (bytes) => {
  if (bytes === 0 || isNaN(bytes)) return '0 Bytes';

  const k = 1024;
  const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));

  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + sizes[i];
}

window.downloadFile = (url, element = document.body) => {
  url = url + '?download=true';
  const a = document.createElement('a');
  a.href = url;
  element.appendChild(a);
  a.click();
  element.removeChild(a);
}

window.loadBTCPayJS = () => {
  // Check if the script is already loaded
  if (!document.querySelector('script[src="https://btcpay.nostr.build/modal/btcpay.js"]')) {
    // Create a new script element
    const script = document.createElement('script');
    script.src = "https://btcpay.nostr.build/modal/btcpay.js";
    script.async = true;

    // Append the script to the body
    document.body.appendChild(script);

    script.onload = function () {
      console.log('Script loaded successfully');
    };

    script.onerror = function () {
      console.log('Failed to load the script');
    };
  }
}

window.abbreviateBech32 = (bech32Address) => {
  return typeof bech32Address === 'string' ? `${bech32Address.substring(0, 15)}...${bech32Address.substring(bech32Address.length - 10)}` : '';
};

const captureVideoFrame = (video, scaleFactor, time) => {
  if (scaleFactor == null) {
    scaleFactor = 1;
  }
  if (time) {
    video.currentTime = time;
  }
  const w = video.videoWidth * scaleFactor;
  const h = video.videoHeight * scaleFactor;
  const canvas = document.createElement('canvas');
  canvas.width = w;
  canvas.height = h;
  const ctx = canvas.getContext('2d');
  ctx.drawImage(video, 0, 0, w, h);
  return canvas.toDataURL('image/jpeg');
}

function dataUrlToFile(dataUrl, filename) {
  const arr = dataUrl.split(',');
  const mime = arr[0].match(/:(.*?);/)[1];
  const bstr = atob(arr[1]);
  let n = bstr.length;
  const u8arr = new Uint8Array(n);
  while (n--) {
    u8arr[n] = bstr.charCodeAt(n);
  }
  return new File([u8arr], filename, { type: mime });
}

window.uploadVideoPoster = (videoId, fileId, scaleFactor, time, callback, errorCB) => {
  const video = document.getElementById(videoId);
  const dataUrl = captureVideoFrame(video, scaleFactor, time);
  const file = dataUrlToFile(dataUrl, 'poster.jpg');
  const api = getApiFetcher(apiUrl, 'multipart/form-data');
  const formData = new FormData();
  formData.append('action', 'upload_video_poster');
  formData.append('fileId', fileId);
  formData.append('file', file);
  api.post('', formData)
    .then(response => response.data)
    .then(data => {
      if (data.error) {
        console.error('Error uploading video poster:', data.error);
        if (typeof errorCB === 'function') errorCB(data);
        return;
      }
      console.debug('Video poster uploaded:', data);
      if (typeof callback === 'function') callback(data);
    })
    .catch(error => {
      if (typeof errorCB === 'function') errorCB(data);
      console.error('Error uploading video poster:', error);
    });
}


// Function to check if URL returns http-200 using HEAD request, and return that url back,
// or null if any other status
window.checkURL = async (url) => {
  return fetch(url,
    {
      method: 'HEAD',
      redirect: 'manual',
      credentials: 'include',
      mode: 'cors',
      referrerPolicy: 'no-referrer',
      onerror: (error) => {
        console.error('Error checking URL:', error);
        return null;
      }
    })
    .then(response => {
      if (response.ok && response.status === 200) {
        return url;
      }
      return null;
    })
    .catch((error) => {
      console.error('Error checking URL:', error);
      return null;
    });
}

// AlpineJS components and stores
document.addEventListener('alpine:init', () => {
  console.debug('Alpine started');
});
document.addEventListener('alpine:initialized', () => {
  console.debug('Alpine initialized');
  const menuStore = Alpine.store('menuStore');
  menuStore.alpineInitiated = true;
})
const apiUrl = `https://${window.location.hostname}/account/api.php`;

// TODO: Intercept back and forward navigation history
function updateHashURL(f, p, replace = false) {
  const params = new URLSearchParams(window.location.hash.slice(1));
  if (f) params.set('f', encodeURIComponent(f));
  if (p) params.set('p', encodeURIComponent(p));
  if (replace) {
    window.history.replaceState(null, null, `#${params.toString()}`);
  } else {
    history.pushState(null, null, `#${params.toString()}`);
  }
}

function getUpdatedHashLink(f, p) {
  const params = new URLSearchParams(window.location.hash.slice(1));
  if (f) params.set('f', encodeURIComponent(f));
  if (p) params.set('p', encodeURIComponent(p));
  return `#${params.toString()}`;
}

function getHashParams() {
  const params = new URLSearchParams(window.location.hash.slice(1));
  const folder = decodeURIComponent(params.get('f'));
  const page = decodeURIComponent(params.get('p'));
  return {
    folder,
    page
  };
}

window.logoutAndRedirectHome = () => {
  const logoutApi = `https://${window.location.hostname}/api/v2/account/logout`;
  const api = getApiFetcher(logoutApi, 'application/json');
  api.get('', {}).then(() => {
    setTimeout(() => {
      window.location.href = `https://${window.location.hostname}/`;
    }, 1500);
  })
    .catch(() => {
      window.location.href = `https://${window.location.hostname}/`;
    });
}

window.copyUrlToClipboard = (url) => {
  navigator.clipboard.writeText(url)
    .then(() => {
      console.debug('URL copied to clipboard:', url);
    })
    .catch(error => {
      console.error('Error copying URL to clipboard:', error);
    });
}

window.copyTextToClipboard = (text, callbackOn = null, callbackOff = null) => {
  navigator.clipboard.writeText(text)
    .then(() => {
      console.debug('Text copied to clipboard:', text);
      if (typeof callbackOn === 'function') callbackOn();
      if (typeof callbackOff === 'function') setTimeout(() => { callbackOff() }, 2000);
    })
    .catch(error => {
      console.error('Error copying text to clipboard:', error);
    });
}
// Format number in a human-readable format
window.formatNumber = (num) => {
  return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

// Abbreviate number to a human-readable format (e.g. 1k, 1M, 1B)
window.abbreviateNumber = (value) => {
  const suffixes = ['', 'k', 'M', 'B', 'T'];
  let suffixNum = 0;

  // Ensure the value is a number
  if (typeof value !== 'number' || isNaN(value)) return value;

  while (Math.abs(value) >= 1000 && suffixNum < suffixes.length - 1) {
    value /= 1000;
    suffixNum++;
  }

  // If the value is a whole number, return it without decimals
  const isWholeNumber = Number.isInteger(value);
  const formattedValue = isWholeNumber ? value.toFixed(0) : value.toFixed(1);

  return formattedValue + suffixes[suffixNum];
};

// Constants
const aiImagesFolderName = 'AI: Generated Images';
const homeFolderName = 'Home: Main Folder';

Alpine.store('profileStore', {
  profileDataInitialized: false,
  unauthenticated: false,
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
    availableCredits: 0,
    debitedCredits: 0,
    creditedCredits: 0,
    referralCode: '',
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
          return 'Purist';
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
      return this.remainingDays <= 180;
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
      return [2, 1, 10, 99].includes(this.accountLevel) && this.isAIStudioEligible &&
        !this.accountExpired && !this.storageOverLimit;
    },
    // Stable Diffusion
    get isAISDiffusionEligible() {
      return [2, 1, 10, 99].includes(this.accountLevel) && this.isAIStudioEligible &&
        !this.accountExpired && !this.storageOverLimit;
    },
    // FLUX.1 [schnell]
    get isFluxSchnellEligible() {
      return [1, 10, 99].includes(this.accountLevel) && this.isAIStudioEligible &&
        !this.accountExpired && !this.storageOverLimit;
    },
    // SD Core
    get isSDCoreEligible() {
      return [1, 2, 10, 99].includes(this.accountLevel) && this.isAIStudioEligible &&
        !this.accountExpired && !this.storageOverLimit;
    },
    // AI Tools
    get isAIToolsEligible() {
      return [1, 2, 10, 99].includes(this.accountLevel) &&
        !this.accountExpired;
    },
    // Other Features
    // Creators Page
    get isCreatorsPageEligible() {
      return [1, 10, 99].includes(this.accountLevel) &&
        !this.accountExpired;
    },
    // Nostr Share
    get isNostrShareEligible() {
      return [1, 2, 3, 10, 99].includes(this.accountLevel) &&
        !this.accountExpired;
    },
    // General upload and URL Import
    get isUploadEligible() {
      // All unexpired accounts can upload with available storage
      return [1, 2, 3, 5, 10, 99].includes(this.accountLevel) &&
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
    // Referral program
    get isReferralEligible() {
      return [1, 2, 10].includes(this.accountLevel) &&
        !this.accountExpired;
    },
    // Analytics
    get isAnalyticsEligible() {
      return [1, 2, 3, 10, 99].includes(this.accountLevel) &&
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
        case 'isFluxSchnellEligible':
          return this.isFluxSchnellEligible;
        case 'isSDCoreEligible':
          return this.isSDCoreEligible;
        case 'isAIToolsEligible':
          return this.isAIToolsEligible;
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
        case 'isReferralEligible':
          return this.isReferralEligible;
        case 'isAnalyticsEligible':
          return this.isAnalyticsEligible;
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
          //console.debug('Profile updated:', data);
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
            //console.debug('Password updated:', data);
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
        //console.debug('Profile info:', data);
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
    this.profileInfo.accountFlags = JSON.parse(data.accountFlags);
    this.profileInfo.remainingDays = data.remainingDays;
    this.profileInfo.subscriptionExpired = data.remainingDays <= 0;
    this.profileInfo.storageUsed = data.storageUsed;
    this.profileInfo.storageLimit = data.storageLimit;
    this.profileInfo.totalStorageLimit = data.totalStorageLimit;
    this.profileInfo.availableCredits = data.availableCredits;
    this.profileInfo.debitedCredits = data.debitedCredits;
    this.profileInfo.creditedCredits = data.creditedCredits;
    this.profileInfo.referralCode = data.referralCode;
    this.profileInfo.referralLink = `https://getnb.me/${data.referralCode}`;
  },
  // Credits
  async getCreditHistory(type = "all", limit = 100, offset = 0) {
    const params = {
      action: 'get_credits_tx_history',
      type,
      limit,
      offset,
    };
    const api = getApiFetcher(apiUrl, 'application/json');

    api.get('', {
      params
    })
      .then(response => response.data)
      .then(data => {
        console.debug('Credit history:', data);
      })
      .catch(error => {
        console.error('Error fetching credit history:', error);
      });
  },
  getCreditsInvoice(credits = 0) {
    const params = {
      action: 'get_credits_invoice',
      credits,
    };
    const api = getApiFetcher(apiUrl, 'application/json');

    api.get('', {
      params
    })
      .then(response => response.data)
      .then(data => {
        console.debug('Credit invoice:', data);
      })
      .catch(error => {
        console.error('Error fetching credit invoice:', error);
      });
  },
  getCreditsBalance() {
    const params = {
      action: 'get_credits_balance',
    };
    const api = getApiFetcher(apiUrl, 'application/json');

    api.get('', {
      params
    })
      .then(response => response.data)
      .then(data => {
        console.debug('Credit balance:', data);
      })
      .catch(error => {
        console.error('Error fetching credit balance:', error);
      });
  }
});

Alpine.store('nostrStore', {
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
      console.debug('Opening sharing modal:', ids);
      this.selectedIds = ids;
      this.selectedFiles = Alpine.store('fileStore').files.filter(file => ids.includes(file.id));
      console.debug('Selected files:', this.selectedFiles);
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
    async isNostrExtensionEnabled() {
      return (await window?.nostr?.getPublicKey()) !== null;
    },
    async send(files = [], callback = null) {
      const nostrStore = Alpine.store('nostrStore');

      // If mediaIds and note are not provided, use the selected files and note
      if (files.length > 0) {
        console.debug('Using provided files:', files);
        this.selectedIds = files.map(file => file.id);
        this.selectedFiles = files;
        if (typeof callback === 'function') {
          this.callback = callback;
        }
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
      // Parse for the hashtags and add them to the tags as 't'
      // Regular expression to match hashtags, excluding those in URLs
      const hashtagRegex = /(?<!\w|#)#([\p{L}\p{N}\p{M}\p{Emoji_Presentation}\p{Emoji}]+)/gu;
      const matches = this.note.match(hashtagRegex);

      if (matches) {
        matches.forEach(hashtag => {
          tags.push(['t', hashtag.toLowerCase()]);
        });
      }

      const event = {
        kind: 1,
        created_at: Math.floor(Date.now() / 1000),
        tags: tags,
        content: this.note,
      }
      this.signedEvent = await window.nostr.signEvent(event);
      //console.debug('Signed event:', this.signedEvent);
      nostrStore.publishSignedEvent(this.signedEvent, this.selectedIds)
        .then(() => {
          console.debug('Published Nostr event:', this.signedEvent);
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
        //console.debug('Published Nostr event:', data);
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
        console.debug('Deleted events:', deletedEvents);
        deletedEvents.forEach(eventId => {
          fileStore.files.forEach(file => {
            if (file.associated_notes?.includes(eventId)) {
              // Remove deleted events
              console.debug('Removing deleted event:', eventId);
              file.associated_notes = file.associated_notes.split(',').filter(note => !note.startsWith(eventId)).join(',');
              // Debug result
              console.debug('Updated associated_notes:', file.associated_notes);
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
    const nostrStore = Alpine.store('nostrStore');
    //console.debug('Signed event:', this.signedEvent);
    return nostrStore.publishSignedEvent(signedEvent)
      .then(() => {
        console.debug('Published Nostr event:', this.signedEvent);
        nostrStore.share.close();
      })
      .catch(error => {
        console.error('Error publishing Nostr event:', error);
        nostrStore.share.isError = true;
        nostrStore.share.isErrorMessages.push('Error publishing Nostr event.');
      })
      .finally(() => {
        nostrStore.share.isLoading = false;
      });
  },
  async nostrGetPublicKey() {
    try {
      const publicKey = await window.nostr.getPublicKey();
      console.debug('Nostr public key:', publicKey);
      return publicKey;
    } catch (error) {
      console.error('Error getting Nostr public key:', error ?? 'Unknown error');
      return null;
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
    const publicKey = nip19.npubEncode(hexNpub);
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
  noTransform: false,
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

    // Set popstate event listener
    window.addEventListener('popstate', () => {
      const {
        folder,
        page
      } = getHashParams();
      if (folder) {
        this.setActiveFolder(folder, false);
      } else {
        this.setActiveFolder(homeFolderName, false);
      }
      this.setActiveMenuFromHash();
      const activeMenu = this.activeMenu;
      const fullWidth = !this.menuItemsAI.some(item => item.name === activeMenu);
      Alpine.store('fileStore').fullWidth = fullWidth;
    });
    console.debug('Menu store initiated');
  },
  menuItemsAI: [{
    name: 'AI Studio',
    icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M15.59 14.37a6 6 0 0 1-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 0 0 6.16-12.12A14.98 14.98 0 0 0 9.631 8.41m5.96 5.96a14.926 14.926 0 0 1-5.841 2.58m-.119-8.54a6 6 0 0 0-7.381 5.84h4.8m2.581-5.84a14.927 14.927 0 0 0-2.58 5.84m2.699 2.7c-.103.021-.207.041-.311.06a15.09 15.09 0 0 1-2.448-2.448 14.9 14.9 0 0 1 .06-.312m-2.24 2.39a4.493 4.493 0 0 0-1.757 4.306 4.493 4.493 0 0 0 4.306-1.758M16.5 9a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z" />',
    route: getUpdatedHashLink(aiImagesFolderName, 'gai'),
    routeId: 'gai',
    rootFolder: aiImagesFolderName
  }],
  menuItems: [{
    name: 'Account Main Page',
    icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />',
    route: getUpdatedHashLink(homeFolderName, 'main'),
    routeId: 'main',
    rootFolder: homeFolderName
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
      name: 'CSAM Reporting',
      icon: '<path d="M7 18v-6a5 5 0 1 1 10 0v6"/><path d="M5 21a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-1a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2z"/><path d="M21 12h1"/><path d="M18.5 4.5 18 5"/><path d="M2 12h1"/><path d="M12 2v1"/><path d="m4.929 4.929.707.707"/><path d="M12 12v6"/>',
      route: '/account/admin/admin_csam_cases.php',
      allowed: 'isAdmin',
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
  activeFolderStats: {},
  activeFolderObj: {},
  // Top most folders
  staticFolders: [{
    id: 0, // There is no actual folder so we use 0
    name: homeFolderName,
    icon: 'H',
    route: '#',
    allowDelete: false
  },
  {
    name: aiImagesFolderName,
    icon: 'A',
    route: '#',
    allowDelete: false
  }
  ],
  getFolderObjByName(folderName) {
    return this.folders.find(folder => folder.name === folderName);
  },
  getFolderNameById(folderId) {
    return this.folders.find(folder => folder.id === folderId)?.name;
  },
  updateFolderStatsFromFile(file, folderName, increment = true) {
    const folder = this.getFolderObjByName(folderName);
    if (!folder) {
      return;
    }
    // Determine file type from media_type
    const fileType = file.media_type;
    console.debug('File type:', fileType);
    switch (fileType) {
      case 'image':
        // GIF or others
        if (file.mime === 'image/gif') {
          folder.stats.gifs += increment ? 1 : -1;
          folder.stats.gifsSize += increment ? file.size : -file.size;
          this.fileStats.totalGifs += increment ? 1 : -1;
        } else {
          folder.stats.images += increment ? 1 : -1;
          folder.stats.imagesSize += increment ? file.size : -file.size;
          this.fileStats.totalImages += increment ? 1 : -1;
        }
        break;
      case 'video':
        folder.stats.videos += increment ? 1 : -1;
        folder.stats.videosSize += increment ? file.size : -file.size;
        this.fileStats.totalVideos += increment ? 1 : -1;
        break;
      case 'audio':
        folder.stats.audio += increment ? 1 : -1;
        folder.stats.audioSize += increment ? file.size : -file.size;
        this.fileStats.totalVideos += increment ? 1 : -1;
        break;
      case 'document':
        folder.stats.documents += increment ? 1 : -1;
        folder.stats.documentsSize += increment ? file.size : -file.size;
        this.fileStats.totalDocuments += increment ? 1 : -1;
        break;
      case 'archive':
        folder.stats.archives += increment ? 1 : -1;
        folder.stats.archivesSize += increment ? file.size : -file.size;
        this.fileStats.totalArchives += increment ? 1 : -1;
        break;
      case 'other':
        folder.stats.others += increment ? 1 : -1;
        folder.stats.othersSize += increment ? file.size : -file.size;
        this.fileStats.totalOthers += increment ? 1 : -1;
        break;
    }
    // Update the total files and size
    folder.stats.all += increment ? 1 : -1;
    folder.stats.allSize += increment ? file.size : -file.size;
    folder.stats.publicCount += increment ? file.flag : -file.flag;
    this.fileStats.totalFiles += increment ? 1 : -1;
    this.fileStats.totalSize += increment ? file.size : -file.size;
    this.fileStats.creatorCount += increment ? file.flag : -file.flag;
  },
  updateSharedStatsFromFile(file, folderName, increment = true) {
    const folder = this.getFolderObjByName(folderName);
    if (!folder) {
      return;
    }
    // Update the total files and size
    folder.stats.publicCount += increment ? file.flag : -file.flag;
    this.fileStats.creatorCount += increment ? file.flag : -file.flag;
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
  setActiveFolder(folderName, doUpdateHashURL = true) {
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
    this.activeFolderObj = this.getFolderObjByName(folderName) || {};
    this.activeFolderStats = this.getFolderObjByName(folderName)?.stats || {};
    if (doUpdateHashURL) {
      updateHashURL(folderName);
    }
    console.debug('Active folder set:', folderName);
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
            // Update folder stats
            if (!existingFolder.stats) {
              existingFolder.stats = folder.stats;
            } else {
              Object.assign(existingFolder.stats, folder.stats);
            }
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
        //console.debug('Folders fetched:', this.folders);
        // Set this.activeFolder to the value of URL's # parameter
        const url = new URL(window.location.href);
        const params = new URLSearchParams(url.hash.slice(1));
        const activeFolder = decodeURIComponent(params.get('f') || '');
        //console.debug('Active folder:', activeFolder);
        //const defaultFolder = this.folders.length > 0 ? this.folders[0].name : '';
        // Set default folder as the one with id 0
        const defaultFolder = this.folders.find(f => f.id === 0).name;
        //console.debug('Default folder:', defaultFolder);
        // If URL hash has a folder, set it as active folder, otherwise use defaul
        const folderToSet = this.folders.find(f => f.name === activeFolder) ? activeFolder : defaultFolder;
        //console.debug('Folder to set:', folderToSet);
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
    console.debug('Folder created:', folderName);
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
      //console.debug('Cannot delete active folder:', this.activeFolder);
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
    console.debug('Deleting folders:', folderIds);

    const formData = {
      action: 'delete_folders',
      foldersToDelete: JSON.stringify(folderIds),
    };

    const api = getApiFetcher(apiUrl, 'multipart/form-data');

    return api.post('', formData)
      .then(response => response.data)
      .then(data => {
        console.debug('Folders deleted:', data);
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
    console.debug('Active menu set:', menuName);
    // Update fileStore.fullWidth is menuName is in menuItemsAI
    const fileStore = Alpine.store('fileStore');
    fileStore.fullWidth = !this.menuItemsAI.some(item => item.name === menuName);
    console.debug('Full width:', fileStore.fullWidth);
    updateHashURL(rootFolder, routeId);
    this.setActiveFolder(rootFolder);
  },
  updateTotalUsed(addUsed) {
    Alpine.store('profileStore').profileInfo.storageUsed += addUsed;
    //console.debug('Total used updated:', Alpine.store('profileStore').profileInfo.storageUsed);
    //console.debug('Total used ratio:', Alpine.store('profileStore').profileInfo.getStorageRatio());
    //console.debug('Total used added:', addUsed);
  },
  fileStats: {
    totalFiles: 0,
    totalGifs: 0,
    totalImages: 0,
    totalVideos: 0,
    totalDocuments: 0,
    totalArchives: 0,
    totalOthers: 0,
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
        // Convert potential strings into numbers, ensuring we handle undefined/null values
        this.fileStats.totalFiles = parseInt(data.totalStats?.all || 0) || 0;
        this.fileStats.totalGifs = parseInt(data.totalStats?.gifs || 0) || 0;
        this.fileStats.totalImages = parseInt(data.totalStats?.images || 0) || 0;
        this.fileStats.totalVideos = parseInt(data.totalStats?.videos || 0) + parseInt(data.totalStats?.audio || 0);
        this.fileStats.totalDocuments = parseInt(data.totalStats?.documents || 0) || 0;
        this.fileStats.totalArchives = parseInt(data.totalStats?.archives || 0) || 0;
        this.fileStats.totalOthers = parseInt(data.totalStats?.others || 0) || 0;
        this.fileStats.creatorCount = parseInt(data.totalStats?.publicCount || 0) || 0;
        this.fileStats.totalFolders = Alpine.store('menuStore').folders.length;
        this.fileStats.totalSize = parseInt(data.totalStats?.allSize || 0) || 0;
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
    _files: [],
    _filesById: new Map(),
    _filesByName: new Map(),

    get files() {
      return this._files;
    },

    get filesById() {
      return this._filesById;
    },

    get filesByName() {
      return this._filesByName;
    },

    /**
     * @param {any[]} files
     */
    set files(files) {
      this._files = [...files];
      this.updateMaps();
    },

    updateMaps() {
      this._filesById = new Map(this._files.map(file => [file.id, file]));
      this._filesByName = new Map(this._files.map(file => [file.name, file]));
    },

    addFile(file, position = 'bottom') {
      if (!this._filesById.has(file.id)) {
        if (position === 'top') {
          this._files.unshift(file);
        } else {
          this._files.push(file);
        }
        this.addFileToMaps(file);
      }
    },

    addFiles(newFiles, position = 'bottom') {
      const uniqueFiles = newFiles.filter(file => !this._filesById.has(file.id));
      if (position === 'top') {
        this._files.unshift(...uniqueFiles);
      } else {
        this._files.push(...uniqueFiles);
      }
      uniqueFiles.forEach(file => this.addFileToMaps(file));
    },

    addFileToMaps(file) {
      this._filesById.set(file.id, file);
      this._filesByName.set(file.name, file);
    },

    removeFile(file) {
      const index = this._files.findIndex(f => f.id === file.id);
      if (index !== -1) {
        this._files.splice(index, 1);
        this.removeFileFromMaps(file);
      }
    },

    removeFileFromMaps(file) {
      this._filesById.delete(file.id);
      this._filesByName.delete(file.name);
    },

    getFileByName(name) {
      return this._filesByName.get(name);
    },

    getFileById(id) {
      return this._filesById.get(id);
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
      console.debug('Opening move to folder modal:', ids);
      this.selectedIds = ids;
      this.selectedFiles = Alpine.store('fileStore').files.filter(file => ids.includes(file.id));
      console.debug('Selected files:', this.selectedFiles);
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
    return this.moveItemsToFolder(this.moveToFolder.selectedIds, this.moveToFolder.destinationFolderId)
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
    console.debug('Moving items to folder:', itemIds, folderId);

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
        console.debug('Moved items to folder:', data);
        const movedImageIds = data.movedImages || [];
        const menuStore = Alpine.store('menuStore');

        // Update the file stats for each file
        this.files.forEach(file => {
          if (movedImageIds.includes(file.id)) {
            menuStore.updateFolderStatsFromFile(file, menuStore.activeFolder, false);
            menuStore.updateFolderStatsFromFile(file, menuStore.getFolderNameById(folderId), true);
          }
        });

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
      console.debug('Opening sharing modal:', ids);
      this.selectedIds = ids;
      this.selectedFiles = Alpine.store('fileStore').files.filter(file => ids.includes(file.id));
      console.debug('Selected files:', this.selectedFiles);
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
    console.debug('Sharing media on Creators page:', this.shareMedia.selectedIds);

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
        //console.debug('Shared media on Creators page:', data);
        const sharedImageIds = data.sharedImages || [];
        const menuStore = Alpine.store('menuStore');

        // Update the shared flag and count for each file
        this.files.forEach(file => {
          if (sharedImageIds.includes(file.id)) {
            file.flag = shareFlag ? 1 : 0;
            // We can assume we are in the same folder as the files
            menuStore.updateSharedStatsFromFile(file, menuStore.activeFolder, shareFlag);
          }
        });
      })
      .catch(error => {
        console.error('Error sharing media on Creators page:', error);
      });
  },
  mediaProperties: {
    isOpen: false,
    isLoading: false,
    isError: false,
    targetFile: null,
    closeTimeout: null,
    contentLoaded: false,
    isNostrShareDialogOpen: false,
    isDeleteDialogOpen: false,
    isDeleting: false,
    editTitle: false,
    isSavingTitle: false,
    editDescription: false,
    isSavingDescription: false,
    newTitle: '',
    newDescription: '',
    isSharing: false,
    deleteAssociatedNotes: false,
    // 'share' | 'props' | 'stats' | 'ai_tools'
    currentTab: 'share',
    // Callback for any active submenues to be closed
    callback: null,
    // Change folders
    fileMoved: false,
    newParentFolder: '',
    editParentFolder: false,
    savingParentFolder: false,
    parentFolderId: null,

    open(file) {
      // Clear the timeout if it exists
      if (this.closeTimeout) {
        clearTimeout(this.closeTimeout);
      }
      this.currentTab = 'share'; // Default tab is 'share', set it before opening
      this.targetFile = file;
      this.isOpen = true;
      this.contentLoaded = false;
      this.newTitle = file.title ?? file.name;
      this.newDescription = file.description;
      this.callback = null;
      if (this.isNostrExtensionEnabled === null) {
        // We need to check only once if the Nostr extension is enabled
        const nostrStore = Alpine.store('nostrStore');
        nostrStore.share.isNostrExtensionEnabled().then(enabled => {
          this.isNostrExtensionEnabled = enabled;
        });
      }
    },
    close() {
      // Prevent close if any actions are taking place
      if (this.isLoading || this.isDeleting || this.isSavingTitle || this.isSavingDescription || this.isSharing) {
        return;
      }
      this.isError = false;
      this.isOpen = false;
      this.isLoading = false;
      this.isDeleteDialogOpen = false;
      this.isDeleting = false;
      this.editingTitle = false;
      this.isSavingTitle = false;
      this.editDescription = false;
      this.isSavingDescription = false;
      this.newTitle = '';
      this.newDescription = '';
      this.isSharing = false;
      this.deleteAssociatedNotes = false;
      this.closeNostrDialog();
      this.fileMoved = false;
      this.newParentFolder = '';
      this.editParentFolder = false;
      this.savingParentFolder = false;
      this.parentFolderId = null;
      this.closeParentFolderEdit();
      // Execute callback if provided
      if (typeof this.callback === 'function') {
        this.callback();
      }
      // Delay emptying the target file to allow for the modal to close
      this.closeTimeout = setTimeout(() => {
        this.targetFile = null;
      }, 1000);
    },
    openParentFolderEdit() {
      const fileStore = Alpine.store('fileStore');
      const menuStore = Alpine.store('menuStore');
      this.editParentFolder = true;
      this.parentFolderId = menuStore.folders.find(folder => folder.name === menuStore.activeFolder).id;
      fileStore.moveToFolder.selectedFolderName = menuStore.activeFolder;
      fileStore.moveToFolder.destinationFolderId = this.parentFolderId;
      fileStore.moveToFolder.selectedIds = [fileStore.mediaProperties.targetFile.id];
    },
    saveParentFolder() {
      const fileStore = Alpine.store('fileStore');
      console.log('Saving parent folder:', this.newParentFolder);
      this.savingParentFolder = true;
      this.newParentFolder = fileStore.moveToFolder.selectedFolderName;
      fileStore.moveToFolderConfirm().then(() => {
        console.log('Moved to folder:', this.newParentFolder);
        this.fileMoved = true;
        this.savingParentFolder = false;
        this.closeParentFolderEdit();
      }).catch(() => {
        console.error('Error moving to folder:', this.newParentFolder);
        this.savingParentFolder = false;
      });
    },
    closeParentFolderEdit() {
      const fileStore = Alpine.store('fileStore');
      this.editParentFolder = false;
      this.savingParentFolder = false;
      this.parentFolderId = null;
    },
    openNostrDialog() {
      this.isNostrShareDialogOpen = true;
    },
    closeNostrDialog() {
      this.isNostrShareDialogOpen = false;
      const nostrStore = Alpine.store('nostrStore');
      nostrStore.share.close();
    },
    saveDescription() {
      this.isSavingDescription = true;
      this.targetFile.description = this.newDescription;
      this.saveMediaEdit(this.targetFile)
        .then(() => {
          this.editDescription = false;
          this.isError = false;
        })
        .catch(error => {
          console.error('Error saving description:', error);
          this.isError = true;
        })
        .finally(() => {
          this.isSavingDescription = false;
        });
    },
    saveTitle() {
      this.isSavingTitle = true;
      this.targetFile.title = this.newTitle;
      this.saveMediaEdit(this.targetFile)
        .then(() => {
          this.editTitle = false;
          this.isError = false;
        })
        .catch(error => {
          console.error('Error saving title:', error);
          this.isError = true;
        })
        .finally(() => {
          this.isSavingTitle = false;
        });
    },
    toggleCreatorSharing() {
      this.isSharing = true;
      this.targetFile.flag = this.targetFile.flag ? 0 : 1;
      this.creatorPageShare(this.targetFile)
        .then(() => {
          this.isError = false;
        })
        .catch(error => {
          console.error('Error sharing media:', error);
          this.isError = true;
          // Revert the flag
          this.targetFile.flag = this.targetFile.flag ? 0 : 1;
        })
        .finally(() => {
          this.isSharing = false;
        });
    },
    cancelDescriptionEdit() {
      if (!this.isSavingDescription) {
        this.editDescription = false;
        this.newDescription = this.targetFile.description;
      }
    },
    cancelTitleEdit() {
      if (!this.isSavingTitle) {
        this.editTitle = false;
        this.newTitle = this.targetFile.title ?? this.targetFile.name;
      }
    },
    delete() {
      const id = this.targetFile.id;
      this.isDeleting = true;
      if (this.deleteAssociatedNotes && this.targetFile.associated_notes?.length > 0) {
        const noteIds = this.targetFile.associated_notes.split(',').map(id_ts => id_ts.split(':')[0]);
        const nostrStore = Alpine.store('nostrStore');
        nostrStore.deleteEvent(noteIds)
          .then(() => {
            console.debug('Deleted associated notes:', noteIds);
            this.deleteMedia(id)
              .then(() => {
                this.isDeleting = false;
                console.debug('Deleted media and its notes:', id, noteIds);
                this.close();
              })
              .catch(error => {
                console.error('Error deleting media:', error);
                this.isError = true;
              })
              .finally(() => {
                this.isDeleting = false;
                this.deleteAssociatedNotes = false;
              });
          })
          .catch(error => {
            console.error('Error deleting associated notes:', error);
            this.isError = true;
          })
          .finally(() => {
            this.isDeleting = false;
          });
      } else {
        this.deleteMedia(id)
          .then(() => {
            this.isDeleting = false;
            console.debug('Deleted media:', id);
            this.close();
          })
          .catch(error => {
            console.error('Error deleting media:', error);
            this.isError = true;
          })
          .finally(() => {
            this.isDeleting = false;
          });
      }
    },
    deleteMedia(id) {
      // Get the current store
      const fileStore = Alpine.store('fileStore');
      return fileStore.deleteItem(id);
    },
    async saveMediaEdit(file) {
      console.debug('Saving media edit:', file);
      const api = getApiFetcher(apiUrl, 'multipart/form-data');
      const formData = {
        action: 'update_media_metadata',
        mediaId: file.id,
        title: file.title,
        description: file.description,
      }

      return api.post('', formData)
        .then(response => response.data)
        .then(data => {
          console.debug('Saved media edit:', data);
          const fileStore = Alpine.store('fileStore');
          const updatedFile = fileStore.files.find(f => f.id === file.id);
          if (updatedFile) {
            updatedFile.title = file.title;
            updatedFile.description = file.description;
          }
        })
    },
    async creatorPageShare(file) {
      console.debug('Toggling sharing of the media on Creators page:', file.id);

      const api = getApiFetcher(apiUrl, 'multipart/form-data');
      const formData = {
        action: 'share_creator_page',
        shareFlag: file?.flag ? "true" : "false",
        imagesToShare: JSON.stringify([file.id]),
      };

      return api.post('', formData)
        .then(response => response.data)
        .then(data => {
          //console.debug('Shared media on Creators page:', data);
          const sharedImageIds = data.sharedImages || [];
          const menuStore = Alpine.store('menuStore');
          const fileStore = Alpine.store('fileStore');

          // Update the shared flag and count for each file
          fileStore.files.forEach(file => {
            if (sharedImageIds.includes(file.id)) {
              file.flag = file?.flag ? 1 : 0;
              // We can assume we are in the same folder as the files
              menuStore.updateSharedStatsFromFile(file, menuStore.activeFolder, file?.flag);
            }
          });
        })
    }
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
      console.debug('Opening delete confirmation:', ids);
      this.selectedIds = ids;
      this.selectedFiles = Alpine.store('fileStore').files.filter(file => ids.includes(file.id));
      console.debug('Selected files:', this.selectedFiles);
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
    console.debug('Deleting image:', itemIds);

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
        console.debug('Deleted image:', data);
        const deletedImageIds = data.deletedImages || [];
        // Update the file stats for each file
        this.files.forEach(file => {
          if (deletedImageIds.includes(file.id)) {
            menuStore.updateFolderStatsFromFile(file, menuStore.activeFolder, false);
          }
        });

        // Remove deleted images from the grid
        this.files = this.files.filter(f => !deletedImageIds.includes(f.id));
        // Refresh the files starting at 0 and up to the original length + 
        this.fetchFiles(this.lastFetchedFolder, true);
      })
      .catch(error => {
        console.error('Error deleting image:', error);
      });
  },
  // Filter: "all", "images", "videos", "audio", 'gifs'
  filters: {
    all: {
      filter: 'all',
      name: 'All',
      icon: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="size-5" aria-hidden="true"><path d="M18 22H4a2 2 0 0 1-2-2V6"/><path d="m22 13-1.296-1.296a2.41 2.41 0 0 0-3.408 0L11 18"/><circle cx="12" cy="8" r="2"/><rect width="16" height="16" x="6" y="2" rx="2"/></svg>`,
    },
    images: {
      filter: 'images',
      name: 'Images',
      icon: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="size-5" aria-hidden="true"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>`,
    },
    gifs: {
      filter: 'gifs',
      name: 'GIFs',
      icon: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="size-5" aria-hidden="true"><path d="m11 16-5 5"/><path d="M11 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v6.5"/><path d="M15.765 22a.5.5 0 0 1-.765-.424V13.38a.5.5 0 0 1 .765-.424l5.878 3.674a1 1 0 0 1 0 1.696z"/><circle cx="9" cy="9" r="2"/></svg>`,
    },
    videos: {
      filter: 'videos',
      name: 'Videos',
      icon: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="size-5" aria-hidden="true"><path d="m16 13 5.223 3.482a.5.5 0 0 0 .777-.416V7.87a.5.5 0 0 0-.752-.432L16 10.5"/><rect x="2" y="6" width="14" height="12" rx="2"/></svg>`,
    },
    audio: {
      filter: 'audio',
      name: 'Audio',
      icon: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="size-5" aria-hidden="true"><path d="M3 14h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-7a9 9 0 0 1 18 0v7a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3"/></svg>`,
    },
    documents: {
      filter: 'documents',
      name: 'Documents',
      icon: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="size-5"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/></svg>`,
    },
    archives: {
      filter: 'archives',
      name: 'Archives',
      icon: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="size-5"><path d="M10 12v-1"/><path d="M10 18v-2"/><path d="M10 7V6"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M15.5 22H18a2 2 0 0 0 2-2V7l-5-5H6a2 2 0 0 0-2 2v16a2 2 0 0 0 .274 1.01"/><circle cx="10" cy="20" r="2"/></svg>`,
    },
    others: {
      filter: 'others',
      name: 'Others',
      icon: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="size-5"><path d="M20 7h-3a2 2 0 0 1-2-2V2"/><path d="M9 18a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h7l4 4v10a2 2 0 0 1-2 2Z"/><path d="M3 7.6v12.8A1.6 1.6 0 0 0 4.6 22h9.8"/></svg>`,
    },
  },
  currentFilter: 'all',
  setFilter(filter) {
    this.currentFilter = filter;
    this.fetchFiles(this.lastFetchedFolder, true);
  },
  filterMenuOpen: false,
  fileFetchStart: 0,
  fileFetchLimit: 96, // Increase this number to fetch more files at once
  fileFetchHasMore: true,
  lastFetchedFolder: '',
  loadingMoreFiles: false,
  refreshFoldersAfterFetch: false,
  async fetchFiles(folder, refresh = false) {
    //console.debug('Fetching files:', folder, start, limit, refresh);
    const uppyStore = Alpine.store('uppyStore');

    if (!folder) {
      this.resetFetchFilesState();
      console.debug('Empty folder:', folder);
      return;
    }

    if (this.lastFetchedFolder !== folder) {
      this.resetFetchFilesState();
      this.lastFetchedFolder = folder;
      this.loading = true;
      console.debug('Folder changed:', folder);
    } else {
      this.loadingMoreFiles = true;
      console.debug('Fetching more files...');
    }

    if (!this.fileFetchHasMore && !refresh) {
      this.loading = false;
      this.loadingMoreFiles = false;
      console.debug('No more files to fetch.');
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
    //console.debug('Fetching files:', params);

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
            console.debug('Duplicate files found, refreshing...');
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

        //console.debug('Parameters:', this.fileFetchStart, this.fileFetchLimit, this.fileFetchHasMore, this.files.length, data.length);

        if (this.fileFetchHasMore) {
          const lastFileIndex = this.files.length - Math.floor(this.fileFetchLimit * 0.2) - 1;
          this.files[lastFileIndex].loadMore = true;
        }
      } else {
        this.fileFetchHasMore = false;
        console.debug('No more files to fetch.');
      }
    } catch (error) {
      console.error('Error fetching files:', error);
    } finally {
      this.loading = false;
      this.loadingMoreFiles = false;

      if (this.refreshFoldersAfterFetch) {
        console.debug('Refetching folders...');
        Alpine.store('menuStore').fetchFolders();
        this.refreshFoldersAfterFetch = false;
      }
    }
  },
  async loadMoreFiles() {
    console.debug('Loading more triggered.');
    if (!this.loading && this.fileFetchHasMore && !this.loadingMoreFiles) {
      this.loadingMoreFiles = true;

      // Find the last file object with loadMore property defined and set to true
      // and remove it.
      const lastFileIndex = this.files.findIndex(f => f.loadMore);
      if (lastFileIndex > -1) {
        delete this.files[lastFileIndex].loadMore;
      }
      //console.debug('Last file:', this.files[lastFileIndex]);

      await this.fetchFiles(this.lastFetchedFolder)
        .finally(() => {
          console.debug('Loading more done.');
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
    console.debug('Injecting file:', file);
    this.files.unshift(file);
  },
  modalFile: {},
  modalFileNext: {},
  modalFilePrevious: {},
  modalOpen: false,
  modalImageUrl: '',
  modalImageSrcset: '',
  modalImageSizes: '',
  modalImageAlt: '',
  modalImageDimensions: '',
  modalImageFilesize: '',
  modalImageTitle: '',
  modalImageDescription: '',
  modalImagePrompt: '',
  modalCloseTimeout: null,

  openModal(file) {
    // Clear any pending timeout
    if (this.modalCloseTimeout) {
      clearTimeout(this.modalCloseTimeout);
      this.modalCloseTimeout = null;  // Reset timeout reference
    }
    // Set modal content before opening to prevent flashing
    this.updateModalWithAdjacent(file);
    // Lock body scroll and open modal
    lock();
    this.modalOpen = true;
  },

  async updateModalWithAdjacent(file) {
    if (!file) return;  // Guard clause

    // First update the current file's content
    const {
      url, srcset, sizes, title, name, width, height,
      size, description, ai_prompt
    } = file;

    Object.assign(this, {
      modalFile: file,
      modalImageUrl: url,
      modalImageSrcset: srcset,
      modalImageSizes: sizes,
      modalImageAlt: title || name,
      modalImageDimensions: width && height ? `${width}x${height}` : '',
      modalImageFilesize: size,
      modalImageTitle: title || '',
      modalImageDescription: description || '',
      modalImagePrompt: ai_prompt || ''
    });

    // Then handle adjacent files with loadMore consideration
    const nextFile = await this.getNextFileWithLoading(file, false);
    const prevFile = await this.getNextFileWithLoading(file, true);

    this.modalFileNext = nextFile;
    this.modalFilePrevious = prevFile;
  },

  async getNextFileWithLoading(file, reverse) {
    let nextFile = this.getNextFile(file, reverse);
    if (nextFile.loadMore) {
      await this.loadMoreFiles();
      nextFile = this.getNextFile(file, reverse);
    }
    return nextFile;
  },

  closeModal() {
    // Clear any existing timeout first
    if (this.modalCloseTimeout) {
      clearTimeout(this.modalCloseTimeout);
      this.modalCloseTimeout = null;
    }
    // Close modal first
    this.modalOpen = false;
    clearBodyLocks();
    // Then schedule the cleanup
    this.modalCloseTimeout = setTimeout(() => {
      if (!this.modalOpen) {  // Only clear if still closed
        Object.assign(this, {
          modalFile: {},
          modalFileNext: {},
          modalFilePrevious: {},
          modalImageUrl: '',
          modalImageSrcset: '',
          modalImageSizes: '',
          modalImageAlt: '',
          modalImageDimensions: '',
          modalImageFilesize: '',
          modalImageTitle: '',
          modalImageDescription: '',
          modalImagePrompt: ''
        });
      }
      this.modalCloseTimeout = null;  // Clear timeout reference
    }, 350);
  },

  async modalNext() {
    const nextFile = await this.getNextFileWithLoading(this.modalFile, false);
    this.updateModalWithAdjacent(nextFile);
  },

  async modalPrevious() {
    const previousFile = await this.getNextFileWithLoading(this.modalFile, true);
    this.updateModalWithAdjacent(previousFile);
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

  // Embeddable links and iframe creation code
  embed: {
    getEmbedURL(file) {
      if (!file || !file?.url) {
        return '';
      }
      const { hostname, pathname } = new URL(file.url);
      const prefix = hostname.split('.').shift();
      const filename = pathname.slice(1).split('.').slice(0, -1).join('.');
      const extension = pathname.split('.').pop();
      const url = new URL(`https://e.nostr.build/${prefix}_${filename}_${extension}`);
      // Add parameters using URLSearchParams
      url.searchParams.append('t', file?.title || file?.name);
      // Add user nym as a by parameter
      url.searchParams.append('by', Alpine.store('profileStore').profileInfo.name || 'Anon');
      if (file?.width && file?.height) {
        url.searchParams.append('w', `${file.width}px`);
        url.searchParams.append('h', `${file.height}px`);
      }
      return url.toString();
    },
    generateImgCode(file) {
      const embedCode = `<img src="${file.url}" alt="${file?.title || file?.name}" width="${file?.width}" height="${file?.height}" loading="lazy">`;
      return embedCode;
    },
    generateResponsiveImgCode(file) {
      const embedCode = `<img src="${file.url}" srcset="${file.srcset}" sizes="${file.sizes}" alt="${file?.title || file?.name}" width="${file?.width}" height="${file?.height}" loading="lazy">`;
      return embedCode;
    },
    copyImgCode(file) {
      const embedCode = this.generateImgCode(file);
      copyToClipboard(embedCode);
    },
    generateIframeCode(file) {
      const iframeUrl = this.getEmbedURL(file);
      const iframeCode = `<iframe src="${iframeUrl}" style="border: 0; width: ${file?.width || '100%'}; height: ${file?.height || '100%'}; min-height: 560px; display: block; aspect-ratio: ${file?.width || '1'}/${file?.height || '1'}" allow="fullscreen; encrypted-media; picture-in-picture" loading="lazy" title="${file?.title || file?.name}"></iframe>`;
      return iframeCode;
    },
    copyIframeCode(file) {
      const iframeCode = this.generateIframeCode(file);
      copyToClipboard(iframeCode);
    },
  },

  // Stats and analytics
  stats: {
    isLoading: false,
    isError: false,
    errorMessage: '',
    statsCache: {},

    // Convert interval string to milliseconds
    intervalToMilliseconds(interval) {
      const units = {
        'm': 60 * 1000,
        'h': 60 * 60 * 1000,
        'd': 24 * 60 * 60 * 1000,
      };

      const match = interval.match(/^(\d+)([mhd])$/);
      if (!match) {
        throw new Error('Invalid interval format');
      }

      const value = parseInt(match[1], 10);
      const unit = match[2];

      return value * units[unit];
    },

    // Round time to the start of the interval
    toStartOfInterval(time, interval) {
      const date = new Date(time);

      const match = interval.match(/^(\d+)([mhd])$/);
      if (!match) {
        throw new Error('Invalid interval format');
      }

      const value = parseInt(match[1], 10);
      const unit = match[2];

      switch (unit) {
        case 'm':
          const minutes = date.getMinutes();
          date.setMinutes(Math.floor(minutes / value) * value);
          date.setSeconds(0, 0);
          break;
        case 'h':
          const hours = date.getHours();
          date.setHours(Math.floor(hours / value) * value);
          date.setMinutes(0, 0, 0);
          break;
        case 'd':
          date.setHours(0, 0, 0, 0);
          break;
        default:
          date.setSeconds(0, 0);
          break;
      }

      return date;
    },

    // Parse data and extract metric names from JSON
    parseData(jsonData, interval) {
      try {
        if (!jsonData || !jsonData.data || !jsonData.meta) throw new Error('Invalid JSON data');

        // Extract metric names from meta, excluding 'time'
        const metrics = jsonData.meta
          .map(item => item.name)
          .filter(name => name !== 'time');

        // Parse data
        const data = jsonData.data.map(item => {
          let time = new Date(item.time * 1000);
          time = this.toStartOfInterval(time, interval);

          // Build data object dynamically
          const dataItem = { time };
          metrics.forEach(metric => {
            const value = item[metric];
            if (value !== undefined) {
              dataItem[metric] = parseFloat(value) || 0;
            }
          });

          return dataItem;
        });

        return { data, metrics };
      } catch (error) {
        console.error('Error parsing data:', error);
        return { data: [], metrics: [] };
      }
    },

    // Generate time labels aligned with the data intervals
    generateTimeLabels(startDate, endDate, interval) {
      const labels = [];
      const intervalMs = this.intervalToMilliseconds(interval);
      const current = new Date(startDate);

      while (current <= endDate) {
        labels.push(new Date(current)); // Clone the date
        current.setTime(current.getTime() + intervalMs);
      }

      return labels;
    },

    // Merge data with labels
    mergeDataWithLabels(labels, data, metric) {
      const dataMap = new Map();
      data.forEach(item => {
        const timeKey = item.time.getTime();
        dataMap.set(timeKey, item[metric]);
      });

      const mergedData = labels.map(label => {
        const timeKey = label.getTime();
        return dataMap.get(timeKey) || 0; // Use 0 if data is missing
      });

      return mergedData;
    },

    // Prepare datasets for Chart.js
    prepareDatasets(labels, data, metrics) {
      const datasets = metrics.map((metric, index) => {
        // Calculate the total sum of the data points for the current metric
        const total = this.mergeDataWithLabels(labels, data, metric).reduce((sum, value) => sum + value, 0);

        // Return the dataset with the total appended to the label
        return {
          label: metric
            .replace(/_/g, ' ')
            .toLowerCase()
            .replace(/\b\w/g, char => char.toUpperCase()) + ` (${abbreviateNumber(total)})`,
          data: this.mergeDataWithLabels(labels, data, metric),
          borderColor: this.getColor(index),
          backgroundColor: this.getColor(index, 0.8),
          fill: true,
          pointRadius: 1, // Hide data points for better performance
        };
      });

      return datasets;
    },

    // Colorblind-friendly color palette
    getColor(index, alpha = 1) {
      const colors = [
        'rgba(0, 114, 178, ALPHA)',   // Blue
        'rgba(230, 159, 0, ALPHA)',   // Orange
        'rgba(86, 180, 233, ALPHA)',  // Sky Blue
        'rgba(0, 158, 115, ALPHA)',   // Bluish Green
        'rgba(240, 228, 66, ALPHA)',  // Yellow
        'rgba(204, 121, 167, ALPHA)', // Reddish Purple
        'rgba(213, 94, 0, ALPHA)',    // Vermillion
      ];
      return colors[index % colors.length].replace('ALPHA', alpha);
    },

    // Fetch and cache stats
    async getStats(mediaId, period = 'day', interval = '1h', groupBy = 'time') {
      // Check if the stats are already loaded and cached
      const stats = this.statsCache[mediaId];
      const key = `${period}-${interval}-${groupBy}`;
      if (!stats || !stats[key] || !stats[key].data || stats[key].expires < Date.now()) {
        // Fetch the stats and cache them before returning
        await this.fetchStats(mediaId, period, interval, groupBy);
      }
      return this.statsCache[mediaId][key].data;
    },

    async fetchStats(mediaId, period = 'day', interval = '1h', groupBy = 'time') {
      this.isError = false;
      this.errorMessage = '';

      const api = getApiFetcher(apiUrl, 'application/json');
      console.debug('Fetching stats:', mediaId, period, interval, groupBy);

      try {
        const response = await api.get('', {
          params: {
            action: 'get_media_stats',
            media_id: mediaId,
            period: period,
            interval: interval,
            group_by: groupBy,
          }
        });
        const data = response.data;
        const key = `${period}-${interval}-${groupBy}`;
        const intervalMs = this.intervalToMilliseconds(interval);
        const expires = Date.now() + intervalMs;

        if (!this.statsCache[mediaId]) {
          this.statsCache[mediaId] = {};
        }
        this.statsCache[mediaId][key] = {
          expires,
          data: JSON.parse(data)
        };
      } catch (error) {
        console.error('Error fetching stats:', error);
        this.isError = true;
        this.errorMessage = 'Error fetching stats.';
      }
    },

    // Render charts using Chart.js
    async renderCharts(mediaId, element, period = 'day', interval = '1h', groupBy = 'time') {
      try {
        this.isLoading = true;
        this.isError = false;
        // Get the raw data
        const rawData = await this.getStats(mediaId, period, interval, groupBy);

        // Parse the data and extract metrics
        const { data, metrics } = this.parseData(rawData, interval);

        // Determine start and end dates
        let endDate = new Date();
        endDate = this.toStartOfInterval(endDate, interval);

        let startDate = new Date(endDate);
        switch (period) {
          case '1h':
            startDate.setHours(startDate.getHours() - 1);
            break;
          case '3h':
            startDate.setHours(startDate.getHours() - 3);
            break;
          case '6h':
            startDate.setHours(startDate.getHours() - 6);
            break;
          case '12h':
            startDate.setHours(startDate.getHours() - 12);
            break;
          case 'day':
            startDate.setDate(startDate.getDate() - 1);
            break;
          case 'week':
            startDate.setDate(startDate.getDate() - 7);
            break;
          case 'month':
            startDate.setMonth(startDate.getMonth() - 1);
            break;
          case '3months':
            startDate.setMonth(startDate.getMonth() - 3);
            break;
          default:
            startDate.setDate(startDate.getDate() - 1);
        }
        startDate = this.toStartOfInterval(startDate, interval);

        // Generate labels
        const labels = this.generateTimeLabels(startDate, endDate, interval);

        // Prepare datasets
        const datasets = this.prepareDatasets(labels, data, metrics);

        // Prepare Chart.js data
        const chartData = {
          labels: labels.map(label => label.getTime()),
          // Take only first two datasets for now
          datasets: datasets.slice(0, 2),
        };

        // Chart.js configuration
        const config = {
          type: 'bar',
          data: chartData,
          options: {
            parsing: true, // Enable parsing
            responsive: true,
            interaction: {
              mode: 'nearest'
            },
            plugins: {
              decimation: {
                enabled: true,
                algorithm: 'lttb',
                samples: 100,
              },
              legend: {
                display: true,
                labels: {
                  // Light grey color
                  color: 'rgba(255, 255, 255, 0.8)',
                }
              },
              tooltip: {
                usePointStyle: true,
                enabled: true,
                callbacks: {
                  labelPointStyle: function (context) {
                    return {
                      pointStyle: 'circle',
                      rotation: 0
                    };
                  }
                }
              },
            },
            scales: {
              x: {
                type: 'timestack', // Use time scale
                color: 'rgba(255, 255, 255, 0.8)',
                time: {
                  unit: interval.endsWith('h') ? 'hour' : 'minute',
                  displayFormats: {
                    minute: 'MMM d, HH:mm',
                    hour: 'MMM d, HH:mm',
                  },
                  tooltipFormat: 'MMM d, yyyy HH:mm',
                },
                title: {
                  display: true,
                  text: 'Time',
                  color: 'rgba(255, 255, 255, 0.8)',
                },
              },
              y: {
                beginAtZero: true,
                color: 'rgba(255, 255, 255, 0.8)',
                title: {
                  display: true,
                  text: 'Count',
                  color: 'rgba(255, 255, 255, 0.8)',
                },
              },
            },
          },
        };

        // If a chart instance already exists on the element, destroy it
        if (element.chartInstance) {
          console.debug('Destroying existing chart instance.');
          element.chartInstance.destroy();
        }

        // Get the context from the canvas element
        const ctx = element.getContext('2d');

        // Create a new Chart instance and store it on the element
        element.chartInstance = new Chart(ctx, config);
        this.isLoading = false;

      } catch (error) {
        console.error('Error rendering charts:', error);
        this.isLoading = false;
        this.isError = true;
        this.errorMessage = 'Error rendering charts.';
      }
    },
  },
  // Video Poster
  async checkAndSetPoster(file, el, cb = 600000 /* 10 minutes */, bypassCache = false) {
    if (file.posterChecked) return;

    const cacheBust = Math.ceil(Date.now() / cb) * cb;
    const posterUrl = `${file.url}/poster.jpg?_=${cacheBust}`;

    try {
      // Create one-off configured axios instance for this request
      const headers = {
        'Accept': 'image/*',
        // Set x-nb-no-redirect to prevent default poster redirect
        'x-nb-no-redirect': '1',
      };
      if (bypassCache) {
        headers['x-nb-bypass-cache'] = '1';
      }
      const posterFetcher = axios.create({
        timeout: 10000,
        withCredentials: true,
        headers: headers,
        responseType: 'blob',
        maxRedirects: 0, // Disable redirects
        keepalive: true,
      });

      // Configure retries inline
      axiosRetry(posterFetcher, {
        retries: 3,
        retryDelay: (retryNumber = 0) => {
          const delayFactor = 300;
          const delay = 2 ** retryNumber * delayFactor;
          const randomSum = delay * 0.2 * Math.random();
          return delay + randomSum;
        },
        retryCondition: (error) => {
          return axiosRetry.isNetworkOrIdempotentRequestError(error) ||
            axiosRetry.isSafeRequestError(error) ||
            axiosRetry.isRetryableError(error);
        }
      });

      const response = await posterFetcher.get(posterUrl);

      if (response.status !== 200 || response.request?.responseURL !== posterUrl) {
        throw new Error('Invalid response status or redirect');
      }

      const dataUrl = await new Promise((resolve) => {
        const reader = new FileReader();
        reader.onloadend = () => resolve(reader.result);
        reader.readAsDataURL(response.data);
      });

      el.poster = dataUrl;
      file.posterChecked = true;
      return true;

    } catch (error) {
      file.posterChecked = true;
      return false;
    }
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
    console.debug('Importing from URL:', this.importURL, this.importFolder);
    // Validate URL
    if (!this.importURL.startsWith('http://') &&
      !this.importURL.startsWith('https://')) {
      console.debug('Invalid URL:', this.importURL);
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

    const api = getApiFetcher(apiUrl, 'multipart/form-data', (60000 * 5)); // 5 minutes timeout

    return api.post('', formData)
      .then(response => response.data)
      .then(data => {
        if (data.error) {
          console.error('Error importing from URL:', data.error);
          this.isLoading = false;
          this.setErrorWithTimeout('Error importing from URL.');
          return;
        }
        console.debug('Import from URL:', data);
        // Is current active folder a home folder?
        const home = menuStore.folders.find(folder => folder.name === menuStore.activeFolder).id === 0;
        // Update the folder stats
        menuStore.updateFolderStatsFromFile(data, folderName, true);
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
  getAllowedFileTypes(accountLevel = 0) {
    // Return allowed file types
    // Copy of the server-side libs/utils.funcs.php array
    // This is just for client side convenience, it is still enforced server-side
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

    // Purist mime types
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

    // Construct lists of extensions based on account level
    const extsImages = Object.values(mimeTypesImages).map(ext => `.${ext}`);
    const extsAudio = Object.values(mimeTypesAudio).map(ext => `.${ext}`);
    const extsVideo = Object.values(mimeTypesVideo).map(ext => `.${ext}`);
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
  instantiateUppy(el, dropTarget, onDropCallback, onDragOverCallback, onDragLeaveCallback) {
    this.mainDialog.dialogEl = el;
    console.debug('Instantiating Uppy...');
    // Stores
    const menuStore = Alpine.store('menuStore');
    const fileStore = Alpine.store('fileStore');
    const profileStore = Alpine.store('profileStore');

    this.instance = new Uppy({
      debug: false,
      autoProceed: true, // Automatically upload files after adding them
      allowMultipleUploadBatches: false, // Disallow multiple upload batches
      restrictions: {
        maxFileSize: 4096 * 1024 * 1024, // 4 GB
        maxTotalFileSize: profileStore.profileInfo.storageRemaining,
        allowedFileTypes: this.getAllowedFileTypes(),
      },
      //maxTotalFileSize: 150 * 1024 * 1024,
      onBeforeFileAdded: (currentFile, files) => {
        // Limit the size of the files that are not images, videos, or audio to 1GiB
        const fileType = currentFile.type.split('/')[0]; // Extract the file type from the MIME type

        if (fileType !== 'image' && fileType !== 'video' && fileType !== 'audio') {
          if (currentFile.size > 1024 ** 3) {
            this.instance.info(`Skipping file ${currentFile.name} because it's too large`, 'error', 500);
            return false; // Exclude the file
          }
        }
        /* Probably not needed
        const allowedTypes = ['video', 'audio', 'image'];
        const fileType = currentFile.type.split('/')[0]; // Extract the file type from the MIME type

        if (!allowedTypes.includes(fileType)) {
          // log to console
          this.instance.log(`Skipping file ${currentFile.name} because it's not an allowed file type`);
          // show error message to the user
          this.instance.info(`Skipping file ${currentFile.name} because it's not an allowed file type`, 'error', 500);
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
        }*/

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
        shouldRetry: (xhr) => {
          // Retry on 5xx errors
          return xhr.status >= 500 && xhr.status !== 504 && xhr.status < 600;
        },
        timeout: 60, // LB timeout
        meta: {
          folderName: '', // Initialize folderName metadata
          folderHierarchy: [], // Initialize folderHierarchy metadata
          noTransform: false, // Disable image transformations by the server
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
        console.debug('Upload result:', response);
        // Set uploadComplete state for the file
        file.progress.uploadComplete = true;
        //this.instance.removeFile(file.id);
        const fd = response.body.fileData;
        console.debug('File uploaded:', fd);
        // Get folderName from uppy file metadata
        const folderName = JSON.parse(file.meta.folderName);
        // Check if folderName is default home folder
        const fileFolderName = folderName === '' ? menuStore.folders.find(folder => folder.id === 0).name : folderName;
        // Update the file stats for the folder
        menuStore.updateFolderStatsFromFile(fd, fileFolderName, true);
        // Preload image variants only with image/* MIME type
        if (fd.mime.startsWith('image/')) {
          const urls = [...Object.values(fd.responsive), fd.thumb, fd.url];
          imageVariantsPrecache(urls)
            .then(() => {
              console.debug('Image variants pre-caching completed.');
              // Additional code to run after pre-caching is done
            })
            .catch((error) => {
              console.error('Error during image variants pre-caching:', error);
              // Handle any errors that occurred during pre-caching
            });
        }
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
        const noTransform = menuStore.noTransform ?? false;
        console.debug('Active folder (Uppy):', activeFolder, activeFolderId, defaultFolder);
        //console.debug('Added file', file);
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
        console.debug('Folder name', folderName);
        console.debug('Folder hierarchy', folderHierarchy);
        this.instance.setFileMeta(file.id, {
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
        // Add the file to the currentUploads
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
          console.debug('Upload complete:', result);
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
          console.debug('Refreshing files:', menuStore.activeFolder);
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
        this.mainDialog.uploadProgress = progress > 0 ? progress + '%' : null;
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
        console.debug('error with file:', file.id);
        console.debug('error message:', error);
        console.debug('error response:', response);
        // Find the file in the fileStore and mark it as errored
        const fileData = this.mainDialog.getFileById(file.id);
        if (fileData) {
          fileData.uppy.uploadError = true;
          fileData.uppy.errorMessage = error.message;
          fileData.uppy.errorResponse = response;
        }
        // If we receive a 401 error, it means the user is not authenticated
        if (response && response.status === 401) {
          // Redirect to login page
          console.debug('User is not authenticated, redirecting to login page...');
          profileStore.unauthenticated = true;
        }
      })
      .on('info-visible', () => {
        const { info } = this.instance.getState();
        // Log the entire info object to see its structure
        console.debug('Full info object:', info);
        // According to Uppy docs, info structure should be:
        // info: {
        //  isHidden: false,
        //  type: 'error',
        //  message: 'Failed to upload',
        //  details: 'Error description'
        // }
        if (info && info.message) {
          // Build message with only defined parts
          let infoMessage = info.message;
          if (info.details) {
            infoMessage += ` - ${info.details}`;
          }
          console.debug(`Info (${info.type || 'unknown'}): ${infoMessage}`);
        }
      })
      .on('file-removed', (file) => {
        console.debug('File removed:', file);
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
        console.debug('Retrying upload:', fileId);
        // Reset the uploadError state
        const fileData = this.mainDialog.getFileById(fileId);
        if (fileData) {
          fileData.uppy.uploadError = false;
          fileData.uppy.errorMessage = '';
          fileData.uppy.errorResponse = null;
        }
      })
      .on('retry-all', () => {
        console.debug('Retrying all uploads');
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
    console.debug('Uppy instance created:', el.id);
    // Dynamic note
    Alpine.effect(() => {
      if (this.instance) {
        // Determine if user has less than 4GB remaining storage
        let byteLimit = (profileStore.profileInfo.storageRemaining < 4 * 1024 * 1024 * 1024) ?
          profileStore.profileInfo.storageRemaining : 4 * 1024 * 1024 * 1024;
        const allowedFileTypes = this.getAllowedFileTypes(profileStore.profileInfo.accountLevel);
        let note = 'Images, video, audio';
        switch (profileStore.profileInfo.accountLevel) {
          case 10:
          case 99:
            note += ', including documents and archives';
            break;
          case 1:
            note += ', including documents';
            break;
          case 3:
            note = 'Images and video only';
            byteLimit = Math.min(byteLimit, (1024 * 1024 * 450)); // 450MB limit
        }
        note += `, up to your storage limit, and ${formatBytes(byteLimit)} per file`;
        this.instance.setOptions({
          restrictions: {
            maxFileSize: byteLimit,
            maxTotalFileSize: profileStore.profileInfo.storageRemaining,
            allowedFileTypes: allowedFileTypes,
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
  async generateImage(title, prompt, selectedModel, negativePrompt = '', aspectRatio = '1:1', stylePreset = '') {
    // Access the form inputs passed as arguments
    console.debug('Title:', title);
    console.debug('Prompt:', prompt);
    console.debug('Selected Model:', selectedModel);
    console.debug('Negative Prompt:', negativePrompt);
    console.debug('Aspect Ratio:', aspectRatio);
    console.debug('Style Preset:', stylePreset);
    // Switch to aiImagesFolderName folder
    menuStore = Alpine.store('menuStore');
    profileStore = Alpine.store('profileStore');
    if (menuStore.activeFolder !== aiImagesFolderName) {
      console.debug('Switching to folder:', aiImagesFolderName);
      console.debug('Current folder:', menuStore.activeFolder);
      menuStore.setActiveFolder(aiImagesFolderName);
    }
    // Prepare form data to send to the server
    const formData = {
      title: title,
      prompt: prompt,
      model: selectedModel,
      action: 'generate_ai_image',
      negative_prompt: negativePrompt,
      aspect_ratio: aspectRatio,
      style_preset: stylePreset,
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
        console.debug('Generated image:', data);
        this.ImageUrl = data.url;
        this.ImageFilesize = data.size;
        this.ImageDimensions = `${data.width}x${data.height}`;
        this.ImageTitle = title.length > 0 ? title : data.name;
        this.ImagePrompt = prompt;
        //this.ImageShow = true;

        data.title = title;
        data.ai_prompt = prompt;
        // Update the file stats for the folder
        menuStore.updateFolderStatsFromFile(data, aiImagesFolderName, true);
        // Update availableCredits and debitedCredits
        if (selectedModel === '@sd/core') {
          profileStore.profileInfo.availableCredits -= 3;
          profileStore.profileInfo.debitedCredits += 3;
        }
        // Add file to the grid
        Alpine.store('fileStore').injectFile(data);
        this.file = data;
      })
      .catch(error => {
        console.error('Error generating image:', error);
        this.ImageLoading = false;
      })
      .finally(() => {
        //this.ImageLoading = false;
        console.debug('Image loading:', this.ImageLoading);
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

