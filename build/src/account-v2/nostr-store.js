import Alpine from 'alpinejs';
import { nip19 } from 'nostr-tools';

const apiUrl = `https://${window.location.hostname}/account/api.php`;
const getApiFetcher = (...args) => window.getApiFetcher(...args);

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
    selectedKind: 1,
    isSuccessOpen: false,
    publishedEventId: '',
    getDeduplicatedErrors() {
      return Array.from(new Set(this.isErrorMessages));
    },
    getMediaTypeLabel() {
      if (this.selectedFiles.length === 0) return 'Media (Kind 20/21/1222)';

      const isImage = this.selectedFiles.every(f => f.mime && f.mime.startsWith('image/'));
      const isVideo = this.selectedFiles.every(f => f.mime && f.mime.startsWith('video/'));
      const isAudio = this.selectedFiles.every(f => f.mime && f.mime.startsWith('audio/'));

      if (isImage) return 'Images (Kind 20)';
      if (isVideo) return 'Video (Kind 21)';
      if (isAudio) return 'Voice (Kind 1222)';

      return 'Media (Kind 20/21/1222)';
    },
    shouldShowKindSwitch() {
      if (this.selectedFiles.length === 0) return true;

      return this.selectedFiles.every(f => f.mime && (
        f.mime.startsWith('image/') ||
        f.mime.startsWith('video/') ||
        f.mime.startsWith('audio/')
      ));
    },
    remove(fileId) {
      this.selectedIds = this.selectedIds.filter(id => id !== fileId);
      this.selectedFiles = this.selectedFiles.filter(file => file.id !== fileId);
    },
    open(ids, callback) {
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
      if (this.callback && !dontCallback) {
        this.callback();
      }
    },
    openSuccessModal(eventId) {
      console.debug('Opening success modal with event ID:', eventId);
      console.debug('Event ID type:', typeof eventId);
      console.debug('Event ID length:', eventId?.length);
      this.publishedEventId = eventId || '';
      console.debug('Stored publishedEventId:', this.publishedEventId);
      this.isSuccessOpen = true;
      console.debug('Success modal isOpen:', this.isSuccessOpen);
    },
    closeSuccessModal() {
      this.isSuccessOpen = false;
      this.publishedEventId = '';
      if (this.callback && typeof this.callback === 'function') {
        this.callback();
        this.callback = null;
      }
    },
    async isNostrExtensionEnabled() {
      return (await window?.nostr?.getPublicKey()) !== null;
    },
    async send(files = [], callback = null) {
      const nostrStore = Alpine.store('nostrStore');
      console.debug('Sending files:', files);

      let kind = this.selectedKind;

      if (this.selectedKind !== 1) {
        const filesToCheck = files.length > 0 ? files : this.selectedFiles;
        const isImage = filesToCheck.every(f => f.mime && f.mime.startsWith('image/'));
        const isVideo = filesToCheck.every(f => f.mime && f.mime.startsWith('video/'));
        const isAudio = filesToCheck.every(f => f.mime && f.mime.startsWith('audio/'));

        const hasNonMediaFiles = filesToCheck.some(f => !f.mime || (!f.mime.startsWith('image/') && !f.mime.startsWith('video/') && !f.mime.startsWith('audio/')));

        if (hasNonMediaFiles) {
          this.isError = true;
          this.isErrorMessages = ['Media kinds (20/21/1222) only support image, video, or audio files. Use Note (Kind 1) for other file types.'];
          this.isLoading = false;
          return;
        }

        if (!(isImage || isVideo || isAudio)) {
          this.isError = true;
          this.isErrorMessages = ['All files must be images, videos, or audio files'];
          this.isLoading = false;
          return;
        }
        kind = isImage ? 20 : (isVideo ? 21 : 1222);
      }

      if ((kind === 21 || kind === 1222) && this.selectedFiles.length > 1) {
        this.isError = true;
        this.isErrorMessages = [kind === 21 ? 'Kind 21 does not support multiple files' : 'Kind 1222 (voice messages) does not support multiple files'];
        this.isLoading = false;
        return;
      }

      if (typeof callback === 'function') {
        this.callback = callback;
      }

      if (files.length > 0) {
        console.debug('Using provided files:', files);
        this.selectedIds = files.map(file => file.id);
        this.selectedFiles = files;
      }
      this.isLoading = true;
      this.isError = false;
      this.isErrorMessages = [];

      if (kind === 1) {
        this.selectedFiles.forEach(file => {
          this.note += `\n${file.url}`;
        });
      } else if (kind === 1222) {
        if (this.selectedFiles.length > 0) {
          this.note = this.selectedFiles[0].url;
        }
      }

      const tags = this.selectedFiles.map(file => {
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

      if (kind === 21) {
        this.selectedFiles.forEach(file => {
          tags.unshift([`published_at`, `${Math.floor(Date.now() / 1000)}`]);
          tags.unshift([`title`, `${file.title}`]);
        });
      }

      if (kind === 1 || kind === 21) {
        this.selectedFiles.forEach(file => {
          tags.push([
            'r',
            file.url,
          ]);
        });
      }

      const hashtagRegex = /(?<!\w|#)#([\p{L}\p{N}\p{M}\p{Emoji_Presentation}\p{Emoji}]+)/gu;
      const matches = this.note.match(hashtagRegex);

      if (matches) {
        matches.forEach(hashtag => {
          const normalizedHashtag = hashtag.replace(/^#+/, '').toLowerCase();
          if (normalizedHashtag) {
            tags.push(['t', normalizedHashtag]);
          }
        });
      }

      const event = {
        kind: kind,
        created_at: Math.floor(Date.now() / 1000),
        tags: tags,
        content: this.note,
      };
      this.signedEvent = await window.nostr.signEvent(event);
      console.debug('Signed event:', this.signedEvent);
      console.debug('Event ID from signedEvent:', this.signedEvent?.id);
      nostrStore.publishSignedEvent(this.signedEvent, this.selectedIds)
        .then(() => {
          console.debug('Published Nostr event:', this.signedEvent);
          const eventId = this.signedEvent?.id;
          console.debug('About to open success modal with ID:', eventId);
          this.close();
          this.openSuccessModal(eventId);
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
    };
    const api = getApiFetcher(apiUrl, 'multipart/form-data');

    return api.post('', formData)
      .then(response => response.data)
      .then(data => {
        if (!data.noteId || !data.success) {
          throw new Error('Error publishing Nostr event.');
        }
        const fileStore = Alpine.store('fileStore');
        const mediaEvents = data.mediaEvents || {};
        fileStore.files.forEach(file => {
          if (mediaEvents[file.id]) {
            if (file.associated_notes?.length) {
              file.associated_notes += ',' + mediaEvents[file.id];
            } else {
              file.associated_notes = mediaEvents[file.id];
            }
          }
        });
        const deletedEvents = data.deletedEvents || [];
        console.debug('Deleted events:', deletedEvents);
        deletedEvents.forEach(eventId => {
          fileStore.files.forEach(file => {
            if (file.associated_notes?.includes(eventId)) {
              console.debug('Removing deleted event:', eventId);
              file.associated_notes = file.associated_notes.split(',').filter(note => !note.startsWith(eventId)).join(',');
              console.debug('Updated associated_notes:', file.associated_notes);
            }
          });
        });
        return data;
      })
      .catch(error => {
        console.error('Error publishing Nostr event:', error);
        throw error;
      });
  },
  async deleteEvent(eventIds) {
    eventIds = Array.isArray(eventIds) ? eventIds : [eventIds];
    const tags = eventIds.map(eventId => ['e', eventId]);
    const event = {
      kind: 5,
      created_at: Math.floor(Date.now() / 1000),
      tags: tags,
      content: 'User requested deletion of these posts',
    };
    const signedEvent = await window.nostr.signEvent(event);
    const nostrStore = Alpine.store('nostrStore');
    return nostrStore.publishSignedEvent(signedEvent)
      .then(() => {
        console.debug('Published Nostr event:', signedEvent);
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
      this.share.isError = true;
      return;
    }
    const publicKey = nip19.npubEncode(hexNpub);
    const profileStore = Alpine.store('profileStore');
    if (publicKey && publicKey !== profileStore.profileInfo.npub) {
      console.error('Nostr public keys do not match:', publicKey, profileStore.profileInfo.npub);
      this.share.isError = true;
      this.share.isCriticalError = true;
      this.share.isErrorMessages.push('Your account Nostr public key does not match your extension key.');
    }
    return publicKey;
  },
});
