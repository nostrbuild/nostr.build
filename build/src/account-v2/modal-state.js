import Alpine from 'alpinejs';

/**
 * Creates a reusable modal state object with standard open/close/selection behavior.
 * Used by moveToFolder, shareMedia, deleteConfirmation, and similar modals in fileStore.
 *
 * @param {object} [extraState] - Additional state properties to merge in.
 * @returns {object} The modal state object.
 */
export function createModalState(extraState = {}) {
  return {
    isOpen: false,
    isLoading: false,
    isError: false,
    selectedIds: [],
    selectedFiles: [],
    callback: null,
    open(ids, callback) {
      if (!Array.isArray(ids)) {
        ids = [ids];
      }
      this.selectedIds = ids;
      this.selectedFiles = Alpine.store('fileStore').files.filter(file => ids.includes(file.id));
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
    },
    ...extraState,
  };
}
