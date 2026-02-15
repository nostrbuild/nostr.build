import Alpine from 'alpinejs';

const apiUrl = `https://${window.location.hostname}/account/api.php`;
const getApiFetcher = (...args) => window.getApiFetcher(...args);

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
    if (!this.importURL.startsWith('http://') &&
      !this.importURL.startsWith('https://')) {
      console.debug('Invalid URL:', this.importURL);
      this.setErrorWithTimeout('URL is empty or invalid.');
      return;
    }

    this.isLoading = true;
    this.isError = false;
    this.errorMessage = '';

    const menuStore = Alpine.store('menuStore');
    const fileStore = Alpine.store('fileStore');

    const folderName = menuStore.activeFolder;
    const importToHomeFolder = menuStore.folders.find(folder => folder.name === folderName).id === 0;

    const formData = {
      action: 'import_from_url',
      url: this.importURL,
      folder: importToHomeFolder ? '' : folderName,
    };

    const api = getApiFetcher(apiUrl, 'multipart/form-data', (60000 * 5));

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
        const home = menuStore.folders.find(folder => folder.name === menuStore.activeFolder)?.id === 0;
        menuStore.updateFolderStatsFromFile(data, folderName, true);
        if (menuStore.activeFolder === folderName || (home && importToHomeFolder)) {
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
