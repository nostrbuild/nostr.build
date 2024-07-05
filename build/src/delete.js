import Alpine from 'alpinejs';
import { getToken } from 'nostr-tools/nip98'
import { deleteFile } from 'nostr-tools/nip96'

window.Alpine = Alpine;

/*
function getServerApiURL() {
  const hostname = window.location.hostname
  const protocol = window.location.protocol
  const path = '/api/v2/nip96/upload'
  return `${protocol}//${hostname}${path}`
}

async function deleteMedia(mediaUrl) {
  const serverApiUrl = getServerApiURL()
  const cleanFileHash = mediaUrl.split('/').pop()
  console.log('serverApiUrl', serverApiUrl)
  const urlToSign = `${serverApiUrl}/${cleanFileHash}`
  const token = await getToken(urlToSign, 'DELETE', async (e) => window.nostr.signEvent(e), true)
  console.log('token', token)
  console.log('cleanFileHash', cleanFileHash)
  const res = await deleteFile(cleanFileHash, serverApiUrl, token)
  console.log(res)
}
  */

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