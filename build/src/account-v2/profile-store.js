import Alpine from 'alpinejs';

const apiUrl = `https://${window.location.hostname}/account/api.php`;
const getApiFetcher = (...args) => window.getApiFetcher(...args);

Alpine.store('profileStore', {
  profileDataInitialized: false,
  unauthenticated: false,
  init() {
    if (!this.profileDataInitialized) {
      this.refreshProfileInfo().then(() => {
        this.profileDataInitialized = true;
      });
    }
  },
  profileInfo: {
    userId: 0,
    name: '',
    npub: '',
    pfpUrl: '',
    wallet: '',
    defaultFolder: '',
    allowNostrLogin: undefined,
    npubVerified: undefined,
    accountLevel: 0,
    accountFlags: {},
    remainingDays: 0,
    subscriptionExpired: null,
    storageUsed: 0,
    storageLimit: 0,
    totalStorageLimit: '',
    availableCredits: 0,
    debitedCredits: 0,
    creditedCredits: 0,
    referralCode: '',
    nlSubEligible: false,
    nlSubActivated: false,
    nlSubInfo: null,
    get creatorPageLink() {
      return `https://${window.location.hostname}/creators/creator/?user=${this.userId}`;
    },
    get storageRemaining() {
      return this.storageLimit - this.storageUsed;
    },
    get storageOverLimit() {
      return this.storageRemaining <= 0;
    },
    get hasNostrLandPlus() {
      return this.nlSubEligible && this.nlSubActivated && this.nlSubInfo && this.nlSubInfo.tier === 'plus';
    },
    get canActivateNostrLandPlus() {
      return this.nlSubEligible && !this.nlSubActivated;
    },
    get nostrLandExpiresAt() {
      return this.nlSubInfo?.tier_ends_at || null;
    },
    get planName() {
      switch (this.accountLevel) {
        case 0:
          return 'Free';
        case 1:
          return 'Creator';
        case 2:
          return 'Professional';
        case 3:
          return 'Purist';
        case 4:
        case 5:
          return 'Legacy';
        case 10:
          return 'Advanced';
        case 89:
          return 'Moderator';
        case 99:
          return 'Admin';
        default:
          return 'Unknown';
      }
    },
    get isAdmin() {
      return this.accountLevel === 99;
    },
    get isModerator() {
      return this.accountFlags?.canModerate || this.isAdmin;
    },
    get accountExpired() {
      return this.remainingDays <= 0;
    },
    get accountExpiredDisplay() {
      return this.accountExpired ? 'Expired' : 'Active';
    },
    get accountEligibleForRenewal() {
      return this.remainingDays <= 180;
    },
    get accountEligibleForUpgrade() {
      return this.accountLevel < 10 || this.accountLevel === 89;
    },
    getNameDisplay() {
      return this.name.substring(0, 15) + (this.name.length > 15 ? '...' : '');
    },
    getNpubDisplay() {
      return this.npub.substring(0, 15) + (this.npub.length > 15 ? '...' : '');
    },
    storageRatio() {
      return Math.min(1, Math.max(0, this.storageUsed / this.storageLimit));
    },
    getStorageRatio() {
      return this.storageOverLimit ? 1 : this.storageRatio();
    },
    get isAIStudioEligible() {
      return [2, 1, 10, 99].includes(this.accountLevel) &&
        !this.accountExpired && !this.storageOverLimit;
    },
    get isAIDreamShaperEligible() {
      return [2, 1, 10, 99].includes(this.accountLevel) && this.isAIStudioEligible &&
        !this.accountExpired && !this.storageOverLimit;
    },
    get isAISDXLLightningEligible() {
      return [2, 1, 10, 99].includes(this.accountLevel) && this.isAIStudioEligible &&
        !this.accountExpired && !this.storageOverLimit;
    },
    get isAISDiffusionEligible() {
      return [2, 1, 10, 99].includes(this.accountLevel) && this.isAIStudioEligible &&
        !this.accountExpired && !this.storageOverLimit;
    },
    get isFluxSchnellEligible() {
      return [1, 10, 99].includes(this.accountLevel) && this.isAIStudioEligible &&
        !this.accountExpired && !this.storageOverLimit;
    },
    get isSDCoreEligible() {
      return [1, 2, 10, 99].includes(this.accountLevel) && this.isAIStudioEligible &&
        !this.accountExpired && !this.storageOverLimit;
    },
    get isAIToolsEligible() {
      return [1, 2, 10, 99].includes(this.accountLevel) &&
        !this.accountExpired;
    },
    get isCreatorsPageEligible() {
      return [1, 10, 99].includes(this.accountLevel) &&
        !this.accountExpired;
    },
    get isNostrShareEligible() {
      return [1, 2, 3, 10, 99].includes(this.accountLevel) &&
        !this.accountExpired;
    },
    get isUploadEligible() {
      return [1, 2, 3, 5, 10, 99].includes(this.accountLevel) &&
        !this.accountExpired && !this.storageOverLimit;
    },
    get isShareEligible() {
      return (this.isCreatorsPageEligible || this.isNostrShareEligible) &&
        !this.accountExpired;
    },
    get isSearchEligible() {
      return [1, 2, 10, 99].includes(this.accountLevel) &&
        !this.accountExpired;
    },
    get isFreeGalleryEligible() {
      return [1, 2, 10, 99].includes(this.accountLevel) &&
        !this.accountExpired;
    },
    get isReferralEligible() {
      return [1, 2, 10].includes(this.accountLevel) &&
        !this.accountExpired;
    },
    get isAnalyticsEligible() {
      return [1, 2, 3, 10, 99].includes(this.accountLevel) &&
        !this.accountExpired;
    },
    get isLargeUploadEligible() {
      return [1, 10, 99].includes(this.accountLevel) &&
        !this.accountExpired && !this.storageOverLimit;
    },
    allowed(permission) {
      switch (permission) {
        case 'isAdmin':
          return this.isAdmin;
        case 'isModerator':
          return this.isModerator;
        case 'isAIStudioEligible':
          return this.isAIStudioEligible;
        case 'isAIDreamShaperEligible':
          return this.isAIDreamShaperEligible;
        case 'isAISDXLLightningEligible':
          return this.isAISDXLLightningEligible;
        case 'isFluxSchnellEligible':
          return this.isFluxSchnellEligible;
        case 'isSDCoreEligible':
          return this.isSDCoreEligible;
        case 'isAIToolsEligible':
          return this.isAIToolsEligible;
        case 'isAISDiffusionEligible':
          return this.isAISDiffusionEligible;
        case 'isCreatorsPageEligible':
          return this.isCreatorsPageEligible;
        case 'isNostrShareEligible':
          return this.isNostrShareEligible;
        case 'isUploadEligible':
          return this.isUploadEligible;
        case 'isShareEligible':
          return this.isShareEligible;
        case 'isSearchEligible':
          return this.isSearchEligible;
        case 'isFreeGalleryEligible':
          return this.isFreeGalleryEligible;
        case 'isReferralEligible':
          return this.isReferralEligible;
        case 'isAnalyticsEligible':
          return this.isAnalyticsEligible;
        case 'isLargeUploadEligible':
          return this.isLargeUploadEligible;
        default:
          return false;
      }
    }
  },
  dialogOpen: false,
  dialogLoading: false,
  dialogError: false,
  dialogErrorMessages: [],
  dialogSuccessMessages: [],
  nlActivationModalOpen: false,
  nlActivationLoading: false,
  isFormUpdated(nym, phpUrl, password) {
    return this.profileInfo.name !== nym || this.profileInfo.pfpUrl !== phpUrl || password;
  },
  closeDialog(force) {
    if (!this.dialogLoading || force) {
      this.dialogOpen = false;
      this.dialogError = false;
      this.dialogErrorMessages = [];
      if (this.$refs?.currentPassword?.value) {
        this.$refs.currentPassword.value = '';
      }
      if (this.$refs?.newPassword?.value) {
        this.$refs.newPassword.value = '';
      }
      if (this.$refs?.confirmPassword?.value) {
        this.$refs.confirmPassword.value = '';
      }
    }
    this.refreshProfileInfo();
  },
  openDialog() {
    this.dialogOpen = true;
  },
  hideMessages() {
    setTimeout(() => {
      this.dialogError = false;
      this.dialogErrorMessages = [];
      this.dialogSuccessMessages = [];
    }, 3000);
  },
  async updateProfileInfo() {
    this.dialogLoading = true;

    const formData = {
      action: 'update_profile',
      name: this.profileInfo.name,
      pfpUrl: this.profileInfo.pfpUrl,
      wallet: this.profileInfo.wallet,
      defaultFolder: this.profileInfo.defaultFolder,
      allowNostrLogin: this.profileInfo.allowNostrLogin,
    };

    const api = getApiFetcher(apiUrl, 'multipart/form-data');

    api.post('', formData)
      .then(response => response.data)
      .then(data => {
        if (data.error) {
          console.error('Error updating profile:', data);
          this.dialogError = true;
          this.dialogErrorMessages.push(data.error);
        } else {
          this.dialogSuccessMessages.push('Profile updated.');
          this.updateProfileInfoFromData(data);
          this.closeDialog(true);
        }
        this.hideMessages();
      })
      .catch(error => {
        console.error('Error updating profile:', error);
        this.dialogError = true;
        this.dialogErrorMessages.push('Error updating profile.');
      })
      .finally(() => {
        this.dialogLoading = false;
      });
  },
  async updatePassword(currentPasswordRef, newPasswordRef, confirmPasswordRef) {
    this.dialogLoading = true;

    const current = currentPasswordRef?.value;
    const newPassword = newPasswordRef?.value;
    const confirmPassword = confirmPasswordRef?.value;

    if (!current) {
      this.dialogError = true;
      this.dialogLoading = false;
      this.dialogErrorMessages.push('Please enter your current password');
      this.hideMessages();
      return;
    }
    if (!newPassword) {
      this.dialogError = true;
      this.dialogLoading = false;
      this.dialogErrorMessages.push('Please enter a new password');
      this.hideMessages();
      return;
    }
    if (!confirmPassword) {
      this.dialogError = true;
      this.dialogLoading = false;
      this.dialogErrorMessages.push('Please confirm your new password');
      this.hideMessages();
      return;
    }

    if (newPassword !== confirmPassword) {
      console.error('Passwords do not match:', newPassword, confirmPassword);
      this.dialogError = true;
      this.dialogLoading = false;
      this.dialogErrorMessages.push('New password and confirm password do not match');
      this.hideMessages();
      return;
    }

    const formData = {
      action: 'update_password',
      password: current,
      newPassword: newPassword,
    };
    const api = getApiFetcher(apiUrl, 'multipart/form-data');

    api.post('', formData)
      .then(response => response.data)
      .then(data => {
        if (data.error) {
          console.error('Error updating password:', data);
          this.dialogError = true;
          this.dialogErrorMessages.push(data.error);
        } else {
          const success = data.success;
          if (!success) {
            console.error('Error updating password:', data);
            this.dialogError = true;
            this.dialogErrorMessages.push('Error updating password.');
          } else {
            this.dialogSuccessMessages.push('Password updated.');
          }
        }
        this.hideMessages();
      })
      .catch(error => {
        console.error('Error updating password:', error);
        this.dialogError = true;
        this.dialogErrorMessages.push('Error updating password.');
      })
      .finally(() => {
        this.dialogLoading = false;
      });
  },
  async refreshProfileInfo() {
    const params = {
      action: 'get_profile_info',
    };
    const api = getApiFetcher(apiUrl, 'application/json');

    api.get('', {
      params
    })
      .then(response => response.data)
      .then(data => {
        if (!data.error) {
          this.updateProfileInfoFromData(data);
        } else {
          console.error('Error fetching profile info:', data);
          this.dialogErrorMessages.push(data.error);
        }
      })
      .catch(error => {
        console.error('Error fetching profile info:', error);
      });
  },
  updateProfileInfoFromData(data) {
    this.profileInfo.userId = data.userId;
    this.profileInfo.name = data.name;
    this.profileInfo.npub = data.npub;
    this.profileInfo.pfpUrl = data.pfpUrl;
    this.profileInfo.wallet = data.wallet;
    this.profileInfo.defaultFolder = data.defaultFolder;
    this.profileInfo.allowNostrLogin = data.allowNostrLogin === 1;
    this.profileInfo.npubVerified = data.npubVerified === 1;
    this.profileInfo.accountLevel = data.accountLevel;
    this.profileInfo.accountFlags = JSON.parse(data.accountFlags);
    this.profileInfo.remainingDays = data.remainingDays;
    this.profileInfo.subscriptionExpired = data.remainingDays <= 0;
    this.profileInfo.storageUsed = data.storageUsed;
    this.profileInfo.storageLimit = data.storageLimit;
    this.profileInfo.totalStorageLimit = data.totalStorageLimit;
    this.profileInfo.availableCredits = data.availableCredits;
    this.profileInfo.debitedCredits = data.debitedCredits;
    this.profileInfo.creditedCredits = data.creditedCredits;
    this.profileInfo.referralCode = data.referralCode;
    this.profileInfo.referralLink = `https://getnb.me/${data.referralCode}`;
    this.profileInfo.nlSubEligible = data.nlSubEligible || false;
    this.profileInfo.nlSubActivated = data.nlSubActivated || false;
    this.profileInfo.nlSubInfo = data.nlSubInfo || null;
  },
  openNlActivationModal() {
    this.nlActivationModalOpen = true;
  },
  closeNlActivationModal() {
    this.nlActivationModalOpen = false;
    this.nlActivationLoading = false;
  },
  async activateNostrLandPlus() {
    this.nlActivationLoading = true;

    const formData = {
      action: 'activate_nostrland_plus'
    };

    const api = getApiFetcher(apiUrl, 'multipart/form-data');

    try {
      const response = await api.post('', formData);
      const data = response.data;

      if (data.error) {
        console.error('Error activating nostr.land Plus:', data);
        alert('Error: ' + data.error);
      } else {
        console.log('nostr.land Plus activated successfully:', data);
        if (data.accountData) {
          this.updateProfileInfoFromData(data.accountData);
        }
        this.refreshProfileInfo();
        alert('nostr.land Plus activated successfully! 🎉');
        this.closeNlActivationModal();
      }
    } catch (error) {
      console.error('Error activating nostr.land Plus:', error);
      alert('Failed to activate nostr.land Plus. Please try again.');
    } finally {
      this.nlActivationLoading = false;
    }
  },
  async getCreditHistory(type = "all", limit = 100, offset = 0) {
    const params = {
      action: 'get_credits_tx_history',
      type,
      limit,
      offset,
    };
    const api = getApiFetcher(apiUrl, 'application/json');

    api.get('', {
      params
    })
      .then(response => response.data)
      .then(data => {
        console.debug('Credit history:', data);
      })
      .catch(error => {
        console.error('Error fetching credit history:', error);
      });
  },
  getCreditsInvoice(credits = 0) {
    const params = {
      action: 'get_credits_invoice',
      credits,
    };
    const api = getApiFetcher(apiUrl, 'application/json');

    api.get('', {
      params
    })
      .then(response => response.data)
      .then(data => {
        console.debug('Credit invoice:', data);
      })
      .catch(error => {
        console.error('Error fetching credit invoice:', error);
      });
  },
  getCreditsBalance() {
    const params = {
      action: 'get_credits_balance',
    };
    const api = getApiFetcher(apiUrl, 'application/json');

    api.get('', {
      params
    })
      .then(response => response.data)
      .then(data => {
        console.debug('Credit balance:', data);
      })
      .catch(error => {
        console.error('Error fetching credit balance:', error);
      });
  }
});
