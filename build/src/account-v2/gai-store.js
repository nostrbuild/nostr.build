import Alpine from 'alpinejs';

const apiUrl = `https://${window.location.hostname}/account/api.php`;
const aiImagesFolderName = 'AI: Generated Images';
const getApiFetcher = (...args) => window.getApiFetcher(...args);

Alpine.store('GAI', {
  ImageShow: false,
  ImageLoading: false,
  ImageUrl: '',
  ImageTitle: '',
  ImagePrompt: '',
  ImageFilesize: '',
  ImageDimensions: '0x0',
  file: {},
  clearImage() {
    this.ImageShow = false;
    this.ImageUrl = '';
    this.ImageTitle = '';
    this.ImagePrompt = '';
    this.ImageFilesize = '';
    this.ImageDimensions = '0x0';
  },
  async generateImage(title, prompt, selectedModel, negativePrompt = '', aspectRatio = '1:1', stylePreset = '') {
    console.debug('Title:', title);
    console.debug('Prompt:', prompt);
    console.debug('Selected Model:', selectedModel);
    console.debug('Negative Prompt:', negativePrompt);
    console.debug('Aspect Ratio:', aspectRatio);
    console.debug('Style Preset:', stylePreset);

    const menuStore = Alpine.store('menuStore');
    const profileStore = Alpine.store('profileStore');
    if (menuStore.activeFolder !== aiImagesFolderName) {
      console.debug('Switching to folder:', aiImagesFolderName);
      console.debug('Current folder:', menuStore.activeFolder);
      menuStore.setActiveFolder(aiImagesFolderName);
    }
    const formData = {
      title: title,
      prompt: prompt,
      model: selectedModel,
      action: 'generate_ai_image',
      negative_prompt: negativePrompt,
      aspect_ratio: aspectRatio,
      style_preset: stylePreset,
    };

    this.ImageShow = false;
    this.ImageLoading = true;
    const api = getApiFetcher(apiUrl, 'multipart/form-data');

    api.post('', formData, {
      timeout: 60000
    })
      .then(response => response.data)
      .then(data => {
        console.debug('Generated image:', data);
        this.ImageUrl = data.url;
        this.ImageFilesize = data.size;
        this.ImageDimensions = `${data.width}x${data.height}`;
        this.ImageTitle = title.length > 0 ? title : data.name;
        this.ImagePrompt = prompt;

        data.title = title;
        data.ai_prompt = prompt;
        menuStore.updateFolderStatsFromFile(data, aiImagesFolderName, true);
        if (selectedModel === '@sd/core') {
          profileStore.profileInfo.availableCredits -= 3;
          profileStore.profileInfo.debitedCredits += 3;
        }
        Alpine.store('fileStore').injectFile(data);
        this.file = data;
      })
      .catch(error => {
        console.error('Error generating image:', error);
        this.ImageLoading = false;
      })
      .finally(() => {
        console.debug('Image loading:', this.ImageLoading);
      });
  },
});
