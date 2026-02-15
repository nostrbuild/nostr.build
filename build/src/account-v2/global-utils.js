const getApiFetcher = (...args) => window.getApiFetcher(...args);
const apiUrl = `https://${window.location.hostname}/account/api.php`;

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

window.checkMediaVirusScanStatus = async (url, mediaType) => {
  if (['image', 'video', 'audio'].includes(mediaType)) {
    return null;
  }
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
          const scanStatus = response.headers.get('x-virus-scan-result') || 'pending';
          const scanMessage = response.headers.get('x-virus-scan-message') || 'Pending virus scan';
          const scanDate = response.headers.get('x-virus-scan-date') || 'Pending virus scan';
          const scanVersion = response.headers.get('x-virus-scan-version') || 'Pending virus scan';
          console.debug('URL scan status:', scanStatus, scanMessage, scanDate, scanVersion);
          return {
            status: scanStatus,
            message: scanMessage,
            date: scanDate,
            version: scanVersion,
            previewMessage: () => {
              switch (scanStatus) {
                case 'clean': return 'Scanned & clean';
                case 'pending': return 'Pending virus scan';
                case 'infected': return 'Infected with virus';
                default: return 'Unknown status';
              }
            },
          };
        case 403:
          return {
            status: 'pending',
            message: 'Pending virus scan',
            date: 'Pending virus scan',
            version: 'Pending virus scan',
            previewMessage: 'Pending virus scan',
          };
        case 451:
          return {
            status: 'infected',
            message: 'Infected with virus',
            date: 'Infected with virus',
            version: 'Infected with virus',
            previewMessage: 'Infected with virus',
          };
        default:
          return {
            status: 'unknown',
            message: 'Unknown status',
            date: 'Unknown status',
            version: 'Unknown status',
            previewMessage: 'Unknown status',
          };
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
      };
    });
};

window.formatBytes = (bytes) => {
  if (bytes === 0 || isNaN(bytes)) return '0 Bytes';

  const k = 1024;
  const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));

  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + sizes[i];
};

window.downloadFile = (url, element = document.body) => {
  url = url + '?download=true';
  const a = document.createElement('a');
  a.href = url;
  element.appendChild(a);
  a.click();
  element.removeChild(a);
};

window.loadBTCPayJS = () => {
  if (!document.querySelector('script[src="https://btcpay.nostr.build/modal/btcpay.js"]')) {
    const script = document.createElement('script');
    script.src = "https://btcpay.nostr.build/modal/btcpay.js";
    script.async = true;

    document.body.appendChild(script);

    script.onload = function () {
      console.log('Script loaded successfully');
    };

    script.onerror = function () {
      console.log('Failed to load the script');
    };
  }
};

window.abbreviateBech32 = (bech32Address) => {
  return typeof bech32Address === 'string' ? `${bech32Address.substring(0, 15)}...${bech32Address.substring(bech32Address.length - 10)}` : '';
};

window.isMobile = (opts) => {
  const mobileRE = /(android|bb\d+|meego).+mobile|armv7l|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|redmi|series[46]0|samsungbrowser.*mobile|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i;
  const notMobileRE = /CrOS/;

  const tabletRE = /android|ipad|playbook|silk/i;
  if (!opts) opts = {};
  let ua = opts.ua;
  if (!ua && typeof navigator !== 'undefined') ua = navigator.userAgent;
  if (ua && ua.headers && typeof ua.headers['user-agent'] === 'string') {
    ua = ua.headers['user-agent'];
  }
  if (typeof ua !== 'string') return false;

  let result =
    (mobileRE.test(ua) && !notMobileRE.test(ua)) ||
    (!!opts.tablet && tabletRE.test(ua));

  if (
    !result &&
    opts.tablet &&
    opts.featureDetect &&
    navigator &&
    navigator.maxTouchPoints > 1 &&
    ua.indexOf('Macintosh') !== -1 &&
    ua.indexOf('Safari') !== -1
  ) {
    result = true;
  }

  return result;
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
};

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
      if (typeof errorCB === 'function') errorCB(error);
      console.error('Error uploading video poster:', error);
    });
};

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
};

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
};

window.copyUrlToClipboard = (url) => {
  navigator.clipboard.writeText(url)
    .then(() => {
      console.debug('URL copied to clipboard:', url);
    })
    .catch(error => {
      console.error('Error copying URL to clipboard:', error);
    });
};

window.copyTextToClipboard = (text, callbackOn = null, callbackOff = null) => {
  navigator.clipboard.writeText(text)
    .then(() => {
      console.debug('Text copied to clipboard:', text);
      if (typeof callbackOn === 'function') callbackOn();
      if (typeof callbackOff === 'function') setTimeout(() => { callbackOff(); }, 2000);
    })
    .catch(error => {
      console.error('Error copying text to clipboard:', error);
    });
};

window.formatNumber = (num) => {
  return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
};

window.abbreviateNumber = (value) => {
  const suffixes = ['', 'k', 'M', 'B', 'T'];
  let suffixNum = 0;

  if (typeof value !== 'number' || isNaN(value)) return value;

  while (Math.abs(value) >= 1000 && suffixNum < suffixes.length - 1) {
    value /= 1000;
    suffixNum++;
  }

  const isWholeNumber = Number.isInteger(value);
  const formattedValue = isWholeNumber ? value.toFixed(0) : value.toFixed(1);

  return formattedValue + suffixes[suffixNum];
};
