import Alpine from 'alpinejs';

export function createMediaProperties({ apiUrl, getApiFetcher }) {
  return {
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
    currentTab: 'share',
    callback: null,
    fileMoved: false,
    newParentFolder: '',
    editParentFolder: false,
    savingParentFolder: false,
    parentFolderId: null,

    open(file) {
      if (this.closeTimeout) {
        clearTimeout(this.closeTimeout);
      }
      this.currentTab = 'share';
      this.targetFile = file;
      this.isOpen = true;
      this.contentLoaded = false;
      this.newTitle = file.title ?? file.name;
      this.newDescription = file.description;
      this.callback = null;
      if (this.isNostrExtensionEnabled === null) {
        const nostrStore = Alpine.store('nostrStore');
        nostrStore.share.isNostrExtensionEnabled().then(enabled => {
          this.isNostrExtensionEnabled = enabled;
        });
      }
    },
    close() {
      if (this.isLoading || this.isDeleting || this.isSavingTitle || this.isSavingDescription || this.isSharing) {
        return;
      }
      this.isError = false;
      this.isOpen = false;
      this.isLoading = false;
      this.isDeleteDialogOpen = false;
      this.isDeleting = false;
      this.editTitle = false;
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
      if (typeof this.callback === 'function') {
        this.callback();
      }
      this.closeTimeout = setTimeout(() => {
        this.targetFile = null;
      }, 1000);
    },
    openParentFolderEdit() {
      const fileStore = Alpine.store('fileStore');
      const menuStore = Alpine.store('menuStore');
      this.editParentFolder = true;
      this.parentFolderId = menuStore.folders.find(folder => folder.name === menuStore.activeFolder)?.id || 0;
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
    closeNostrDialogOnly() {
      this.isNostrShareDialogOpen = false;
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
      };

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
        });
    },
    async creatorPageShare(file) {
      console.debug('Toggling sharing of the media on Creators page:', file.id);

      const api = getApiFetcher(apiUrl, 'multipart/form-data');
      const formData = {
        action: 'share_creator_page',
        shareFlag: file?.flag ? 'true' : 'false',
        imagesToShare: JSON.stringify([file.id]),
      };

      return api.post('', formData)
        .then(response => response.data)
        .then(data => {
          const sharedImageIds = data.sharedImages || [];
          const menuStore = Alpine.store('menuStore');
          const fileStore = Alpine.store('fileStore');

          fileStore.files.forEach(file => {
            if (sharedImageIds.includes(file.id)) {
              file.flag = file?.flag ? 1 : 0;
              menuStore.updateSharedStatsFromFile(file, menuStore.activeFolder, file?.flag);
            }
          });
        });
    }
  };
}
