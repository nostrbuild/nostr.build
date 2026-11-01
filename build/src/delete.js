import Alpine from 'alpinejs';
import { getToken } from 'nostr-tools/nip98'

window.Alpine = Alpine;

// NIP-96 DELETE <server>/<file> with a NIP-98 Authorization header. Inlined
// from nostr-tools 2.12's nip96.deleteFile: newer nostr-tools dropped NIP-96.
async function deleteFile(fileHash, serverApiUrl, authorization) {
  const base = serverApiUrl.endsWith('/') ? serverApiUrl : `${serverApiUrl}/`
  const response = await fetch(`${base}${fileHash}`, {
    method: 'DELETE',
    headers: { Authorization: authorization },
  })
  if (!response.ok) {
    throw new Error('Error deleting file!')
  }
  try {
    return await response.json()
  } catch {
    throw new Error('Error parsing JSON response!')
  }
}

document.addEventListener('alpine:init', () => {
  Alpine.data('deleteMediaComponent', () => ({
    filename: '',
    isLoading: false,

    async deleteMedia(mediaUrl) {
      this.isLoading = true;
      try {
        const serverApiUrl = this.getServerApiURL();
        const cleanFileHash = mediaUrl.split('/').pop();
        if (!cleanFileHash) {
          throw new Error('Invalid media URL, hash or filename');
        }
        // Try getting user npub to see if extension is functional
        const userNpub = await window.nostr.getPublicKey();
        if (!userNpub) {
          throw new Error('Nostr extension is not installed or not enabled');
        }
        console.log('serverApiUrl', serverApiUrl);
        const urlToSign = `${serverApiUrl}/${cleanFileHash}`;
        const token = await getToken(urlToSign, 'DELETE', async (e) => window.nostr.signEvent(e), true);
        console.log('token', token);
        console.log('cleanFileHash', cleanFileHash);
        const res = await deleteFile(cleanFileHash, serverApiUrl, token);
        console.log(res);

        if (res.status === 'error') {
          alert(`Delete failed: ${res.message}`);
        } else {
          alert('File deleted successfully');
        }
      } catch (error) {
        alert(`An error occurred: ${error.message}`);
      } finally {
        this.isLoading = false;
      }
    },

    getServerApiURL() {
      const hostname = window.location.hostname;
      const protocol = window.location.protocol;
      const path = '/api/v2/nip96/upload';
      return `${protocol}//${hostname}${path}`;
    }
  }));
});

Alpine.start()