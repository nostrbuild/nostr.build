import Alpine from 'alpinejs';

export function createUppyDialogState() {
  return {
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
      folderName = Alpine.store('menuStore').folders?.find(folder => folder.name === folderName)?.id === 0 ? '' : folderName;
      return this.currentFiles.filter(file => file.folder === folderName);
    }
  };
}
