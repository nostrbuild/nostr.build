import Alpine from 'alpinejs';
import { lock, clearBodyLocks } from 'tua-body-scroll-lock';
import Chart from 'chart.js/auto';
import {
  intervalToMilliseconds,
  toStartOfInterval,
  parseData,
  generateTimeLabels,
  mergeDataWithLabels,
  prepareDatasets,
  getColor,
} from './file-store-stats-helpers';
import { createMediaProperties } from './file-store-media-properties';

const apiUrl = `https://${window.location.hostname}/account/api.php`;
const getApiFetcher = (...args) => window.getApiFetcher(...args);
const copyToClipboard = (...args) => window.copyToClipboard(...args);
const abbreviateNumber = (...args) => window.abbreviateNumber(...args);

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
    const destinationFolderName = Alpine.store('menuStore').folders.find(folder => folder.id === this.moveToFolder.destinationFolderId).name;
    if (destinationFolderName === Alpine.store('menuStore').activeFolder) {
      this.moveToFolder.close();
      this.moveToFolder.isLoading = false;
      return;
    }
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

        this.files.forEach(file => {
          if (movedImageIds.includes(file.id)) {
            menuStore.updateFolderStatsFromFile(file, menuStore.activeFolder, false);
            menuStore.updateFolderStatsFromFile(file, menuStore.getFolderNameById(folderId), true);
          }
        });

        this.files = this.files.filter(file => !movedImageIds.includes(file.id));
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
        const sharedImageIds = data.sharedImages || [];
        const menuStore = Alpine.store('menuStore');

        this.files.forEach(file => {
          if (sharedImageIds.includes(file.id)) {
            file.flag = shareFlag ? 1 : 0;
            menuStore.updateSharedStatsFromFile(file, menuStore.activeFolder, shareFlag);
          }
        });
      })
      .catch(error => {
        console.error('Error sharing media on Creators page:', error);
      });
  },
  mediaProperties: createMediaProperties({ apiUrl, getApiFetcher }),
  deleteConfirmation: {
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
        this.files.forEach(file => {
          if (deletedImageIds.includes(file.id)) {
            menuStore.updateFolderStatsFromFile(file, menuStore.activeFolder, false);
          }
        });

        this.files = this.files.filter(f => !deletedImageIds.includes(f.id));
        this.fetchFiles(this.lastFetchedFolder, true);
      })
      .catch(error => {
        console.error('Error deleting image:', error);
      });
  },
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
  fileFetchLimit: 96,
  fileFetchHasMore: true,
  lastFetchedFolder: '',
  loadingMoreFiles: false,
  refreshFoldersAfterFetch: false,
  fetchRequestSeq: 0,
  activeFetchRequestSeq: 0,
  fetchAbortController: null,
  async fetchFiles(folder, refresh = false) {
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

    const api = getApiFetcher(apiUrl, 'application/json');
    const requestSeq = ++this.fetchRequestSeq;
    this.activeFetchRequestSeq = requestSeq;
    if (this.fetchAbortController) {
      this.fetchAbortController.abort();
    }
    this.fetchAbortController = new AbortController();

    try {
      const response = await api.get('', {
        params,
        signal: this.fetchAbortController.signal,
      });
      if (requestSeq !== this.activeFetchRequestSeq) {
        return;
      }
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
          const existingFilesMap = new Map(this.files.map(file => [file.id, file]));

          data.forEach(file => {
            const existingFile = existingFilesMap.get(file.id);
            if (existingFile) {
              file.loaded = existingFile.loaded;
            }
          });

          this.files = [...uppyStore.mainDialog.getFilesInFolder(folder) ?? [], ...data];

          const expectedLength = fetchLimit;
          this.fileFetchHasMore = data.length === expectedLength;
          this.fileFetchStart = data.length;
        }

        if (this.fileFetchHasMore) {
          const lastFileIndex = this.files.length - Math.floor(this.fileFetchLimit * 0.2) - 1;
          this.files[lastFileIndex].loadMore = true;
        }
      } else {
        this.fileFetchHasMore = false;
        console.debug('No more files to fetch.');
      }
    } catch (error) {
      if (error?.code === 'ERR_CANCELED' || error?.name === 'CanceledError') {
        return;
      }
      console.error('Error fetching files:', error);
    } finally {
      if (requestSeq !== this.activeFetchRequestSeq) {
        return;
      }
      this.fetchAbortController = null;
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

      const lastFileIndex = this.files.findIndex(f => f.loadMore);
      if (lastFileIndex > -1) {
        delete this.files[lastFileIndex].loadMore;
      }

      await this.fetchFiles(this.lastFetchedFolder)
        .finally(() => {
          console.debug('Loading more done.');
          this.loadingMoreFiles = false;
        });
    }
  },
  resetFetchFilesState() {
    if (this.fetchAbortController) {
      this.fetchAbortController.abort();
      this.fetchAbortController = null;
    }
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
    if (this.modalCloseTimeout) {
      clearTimeout(this.modalCloseTimeout);
      this.modalCloseTimeout = null;
    }
    this.updateModalWithAdjacent(file);
    lock();
    this.modalOpen = true;
  },

  async updateModalWithAdjacent(file) {
    if (!file) return;

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
    if (this.modalCloseTimeout) {
      clearTimeout(this.modalCloseTimeout);
      this.modalCloseTimeout = null;
    }
    this.modalOpen = false;
    clearBodyLocks();
    this.modalCloseTimeout = setTimeout(() => {
      if (!this.modalOpen) {
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
      this.modalCloseTimeout = null;
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
    const currentIndex = this.files.findIndex(f => f.id === file.id);
    let nextIndex;
    if (reverse) {
      nextIndex = currentIndex - 1;
      if (nextIndex < 0) {
        nextIndex = this.files.length - 1;
      }
    } else {
      nextIndex = currentIndex + 1;
      if (nextIndex >= this.files.length) {
        nextIndex = 0;
      }
    }
    const nextFile = this.files[nextIndex];
    return nextFile || file;
  },

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
      url.searchParams.append('t', file?.title || file?.name);
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

  stats: {
    isLoading: false,
    isError: false,
    errorMessage: '',
    statsCache: {},

    intervalToMilliseconds(interval) {
      return intervalToMilliseconds(interval);
    },

    toStartOfInterval(time, interval) {
      return toStartOfInterval(time, interval);
    },

    parseData(jsonData, interval) {
      return parseData(jsonData, interval);
    },

    generateTimeLabels(startDate, endDate, interval) {
      return generateTimeLabels(startDate, endDate, interval);
    },

    mergeDataWithLabels(labels, data, metric) {
      return mergeDataWithLabels(labels, data, metric);
    },

    prepareDatasets(labels, data, metrics) {
      return prepareDatasets(labels, data, metrics, abbreviateNumber);
    },

    getColor(index, alpha = 1) {
      return getColor(index, alpha);
    },

    async getStats(mediaId, period = 'day', interval = '1h', groupBy = 'time') {
      const stats = this.statsCache[mediaId];
      const key = `${period}-${interval}-${groupBy}`;
      if (!stats || !stats[key] || !stats[key].data || stats[key].expires < Date.now()) {
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
            period,
            interval,
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

    async renderCharts(mediaId, element, period = 'day', interval = '1h', groupBy = 'time') {
      try {
        this.isLoading = true;
        this.isError = false;
        const rawData = await this.getStats(mediaId, period, interval, groupBy);

        const { data, metrics } = this.parseData(rawData, interval);

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

        const labels = this.generateTimeLabels(startDate, endDate, interval);
        const datasets = this.prepareDatasets(labels, data, metrics);

        const chartData = {
          labels: labels.map(label => label.getTime()),
          datasets: datasets.slice(0, 2),
        };

        const config = {
          type: 'bar',
          data: chartData,
          options: {
            parsing: true,
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
                  color: 'rgba(255, 255, 255, 0.8)',
                }
              },
              tooltip: {
                usePointStyle: true,
                enabled: true,
                callbacks: {
                  labelPointStyle() {
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
                type: 'timestack',
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

        if (element.chartInstance) {
          console.debug('Destroying existing chart instance.');
          element.chartInstance.destroy();
        }

        const ctx = element.getContext('2d');
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
  async checkAndSetPoster(file, el, cb = 600000, bypassCache = false) {
    if (file.posterChecked) return;

    const cacheBust = Math.ceil(Date.now() / cb) * cb;
    const posterUrl = `${file.url}/poster.jpg?_=${cacheBust}`;

    try {
      const headers = {
        Accept: 'image/*',
        'x-nb-no-redirect': '1',
      };
      if (bypassCache) {
        headers['x-nb-bypass-cache'] = '1';
      }
      const posterFetcher = getApiFetcher('', 'application/json', 10000);

      const response = await posterFetcher.get(posterUrl, {
        headers,
        responseType: 'blob',
        maxRedirects: 0,
      });

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
