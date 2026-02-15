import Alpine from 'alpinejs';

const apiUrl = `https://${window.location.hostname}/account/api.php`;
const getApiFetcher = (...args) => window.getApiFetcher(...args);

const aiImagesFolderName = 'AI: Generated Images';
const homeFolderName = 'Home: Main Folder';

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
    route: 'https://gallery.nostr.build',
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
  staticFolders: [{
    id: 0,
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
    const fileType = file.media_type;
    console.debug('File type:', fileType);
    switch (fileType) {
      case 'image':
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
        this.fileStats.totalAudio += increment ? 1 : -1;
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
    folder.stats.publicCount += increment ? file.flag : -file.flag;
    this.fileStats.creatorCount += increment ? file.flag : -file.flag;
  },
  setActiveMenuFromHash() {
    const params = new URLSearchParams(window.location.hash.slice(1));
    const menu = params.get('p');
    const menuItems = [...this.menuItemsAI, ...this.menuItems];
    const activeMenu = menuItems.find(item => item.routeId === menu);
    this.activeMenu = activeMenu ? activeMenu.name : this.menuItems[0].name;
  },
  setActiveFolder(folderName, doUpdateHashURL = true) {
    if (!this.foldersFetched || !folderName) {
      return;
    }
    if (this.activeFolder === folderName) {
      return;
    }
    const fileStore = Alpine.store('fileStore');
    const uppyStore = Alpine.store('uppyStore');
    uppyStore.mainDialog.close();
    fileStore.files = [];
    this.activeFolder = folderName;
    this.activeFolderObj = this.getFolderObjByName(folderName) || {};
    this.activeFolderStats = this.getFolderObjByName(folderName)?.stats || {};
    if (doUpdateHashURL) {
      updateHashURL(folderName);
    }
    console.debug('Active folder set:', folderName);
    fileStore.currentFilter = 'all';
    fileStore.fetchFiles(folderName, true).then(() => {
      this.refreshFoldersStats();
    });
  },
  foldersFetched: false,
  async fetchFolders() {
    const params = {
      action: 'list_folders',
    };

    const api = getApiFetcher(apiUrl, 'application/json');

    await api.get('', {
      params
    })
      .then(response => response.data)
      .then(data => {
        const folders = data || [];
        this.folders = folders.reduce((acc, folder) => {
          const existingFolder = acc.find(f => f.name === folder.name);
          if (!existingFolder) {
            acc.push(folder);
          } else {
            existingFolder.id = folder.id;
            existingFolder.route = folder.route;
            existingFolder.icon = folder.icon;
            if (!existingFolder.stats) {
              existingFolder.stats = folder.stats;
            } else {
              Object.assign(existingFolder.stats, folder.stats);
            }
          }
          return acc;
        }, this.folders);
        this.folders.sort((a, b) => a.name.localeCompare(b.name));

        this.staticFolders = this.staticFolders.map(staticFolder => {
          const existingFolder = this.folders.find(f => f.name === staticFolder.name);
          if (existingFolder) {
            Object.assign(staticFolder, existingFolder);
            this.folders = this.folders.filter(f => f.name !== staticFolder.name);
          }
          return staticFolder;
        });

        this.folders = [...this.staticFolders, ...this.folders];
        this.foldersFetched = true;
        const url = new URL(window.location.href);
        const params = new URLSearchParams(url.hash.slice(1));
        const activeFolder = decodeURIComponent(params.get('f') || '');
        const defaultFolder = this.folders.find(f => f.id === 0).name;
        const folderToSet = this.folders.find(f => f.name === activeFolder) ? activeFolder : defaultFolder;
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
    if (!folderName.trim()) {
      this.newFolderNameError = 'Empty folder name.';
      setTimeout(() => {
        this.newFolderNameError = '';
      }, 1000);
      return;
    }
    if (this.folders.some(folder => folder.name === folderName)) {
      console.error('Folder already exists:', folderName);
      this.newFolderNameError = 'Folder already exists.';
      setTimeout(() => {
        this.newFolderNameError = '';
      }, 1000);
      return;
    }
    const folderNameNormalized = folderName.normalize('NFC');
    const firstChar = [...folderNameNormalized][0];
    const newFolder = {
      name: folderName,
      icon: firstChar.toUpperCase(),
      route: getUpdatedHashLink(folderName),
      allowDelete: true,
    };
    this.folders.push(newFolder);
    this.newFolderDialogClose();
    this.mobileMenuOpen = this.mobileMenuOpen ? false : this.mobileMenuOpen;
    Alpine.store('fileStore').refreshFoldersAfterFetch = true;
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
    if (!Array.isArray(folderIds)) {
      folderIds = [folderIds];
    }
    if (folderIds.length === 0) {
      return;
    }
    this.foldersToDeleteIds = folderIds.filter(id => this.folders.some(folder => folder.id === id));
    this.foldersToDelete = this.folders.filter(folder => this.foldersToDeleteIds.includes(folder.id));
    if (this.foldersToDelete.some(folder => folder.name === this.activeFolder)) {
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

    const folderIds = this.foldersToDeleteIds.filter(id => this.folders.some(folder => folder.id === id));

    this.isDeletingFolders = true;

    this.deleteFolders(folderIds)
      .then(() => {
        this.closeDeleteFolderModal();
      })
      .catch(error => {
        console.error('Error deleting folders:', error);
      })
      .finally(() => {
        this.isDeletingFolders = false;
        this.fetchFolders();
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

        this.folders = this.folders.filter(folder => !deletedFolders.includes(folder.id));
      })
      .catch(error => {
        console.error('Error deleting folders:', error);
      });
  },
  activeMenu: '',
  setActiveMenu(menuName) {
    this.activeMenu = menuName;
    const menuItem = this.menuItemsAI.concat(this.menuItems).find(item => item.name === menuName);
    const rootFolder = menuItem ? menuItem.rootFolder : 'main';
    const routeId = menuItem ? menuItem.routeId : 'main';
    console.debug('Active menu set:', menuName);
    const fileStore = Alpine.store('fileStore');
    fileStore.fullWidth = !this.menuItemsAI.some(item => item.name === menuName);
    console.debug('Full width:', fileStore.fullWidth);
    updateHashURL(rootFolder, routeId);
    this.setActiveFolder(rootFolder);
  },
  updateTotalUsed(addUsed) {
    Alpine.store('profileStore').profileInfo.storageUsed += addUsed;
  },
  fileStats: {
    totalFiles: 0,
    totalGifs: 0,
    totalImages: 0,
    totalVideos: 0,
    totalAudio: 0,
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
        this.fileStats.totalFiles = parseInt(data.totalStats?.all || 0) || 0;
        this.fileStats.totalGifs = parseInt(data.totalStats?.gifs || 0) || 0;
        this.fileStats.totalImages = parseInt(data.totalStats?.images || 0) || 0;
        this.fileStats.totalVideos = parseInt(data.totalStats?.videos || 0) || 0;
        this.fileStats.totalAudio = parseInt(data.totalStats?.audio || 0) || 0;
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
