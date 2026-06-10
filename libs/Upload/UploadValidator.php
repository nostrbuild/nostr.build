<?php

require_once __DIR__ . '/../../SiteConfig.php';
require_once __DIR__ . '/../utils.funcs.php';
require_once __DIR__ . '/../IpAccessControl.class.php';
require_once __DIR__ . '/../TorExitList.class.php';

class UploadValidator
{
  const IMAGE_SIZE_MULTIPLIER = 2;

  private UploadsData $uploadsData;

  /**
   * Optional explicit client IP. If null at validate() time, the IP is
   * resolved from the request via IpAccessControl::extractClientIp().
   * Setter exists so non-HTTP callers (CLI, tests, queue workers) can pass
   * an explicit value without spoofing $_SERVER.
   */
  private ?string $clientIp = null;

  public function __construct(UploadsData $uploadsData)
  {
    $this->uploadsData = $uploadsData;
  }

  public function setClientIp(?string $ip): void
  {
    $this->clientIp = ($ip !== null && filter_var($ip, FILTER_VALIDATE_IP) !== false) ? $ip : null;
  }

  /**
   * Validate a file before upload processing.
   *
   * @param array $file File data array with keys: error, tmp_name, size, sha256
   * @param bool $pro Whether the user has a pro account
   * @param bool $noTransform Whether transformation is disabled
   * @param Account|null $account The user's account object (required for pro users)
   * @param string $userNpub The user's npub for blacklist checking
   * @return array{0: bool, 1: int, 2: string} [success, HTTP status code, message]
   */
  public function validate(array $file, bool $pro, bool $noTransform, ?Account $account, string $userNpub = ''): array
  {
    if (
      !is_array($file) ||
      !isset($file['error'], $file['tmp_name'], $file['size'], $file['sha256']) ||
      !is_string($file['tmp_name']) ||
      $file['tmp_name'] === '' ||
      !is_file($file['tmp_name'])
    ) {
      error_log('Invalid file structure for upload validation');
      return [false, 400, 'Invalid file upload payload'];
    }

    // Validate if file upload is OK
    if ($file['error'] !== UPLOAD_ERR_OK) {
      error_log('File upload error: ' . $file['error']);
      return [false, 400, "File upload error "];
    }

    $userAccountLevel = 0;
    try {
      $userAccountLevel = $pro && $account !== null ? $account->getAccountLevelInt() : 0;
    } catch (Exception $e) {
      error_log('Failed to get account level: ' . $e->getMessage());
    }

    $fileType = detectFileExt($file['tmp_name'], $userAccountLevel);
    $multiplierSize = 1;
    // Check if uploaded file is an image, and add a fuzz factor to account for future optimization
    if ($fileType['type'] === 'image' && !$noTransform) {
      $multiplierSize = self::IMAGE_SIZE_MULTIPLIER;
    }

    // Check if the file size exceeds the upload limit for free users
    if (!$pro && $file['size'] > (SiteConfig::FREE_UPLOAD_LIMIT * $multiplierSize)) {
      error_log('File size exceeds the limit of ' . formatSizeUnits(SiteConfig::FREE_UPLOAD_LIMIT));
      return [false, 413, "File size exceeds the limit of " . formatSizeUnits(SiteConfig::FREE_UPLOAD_LIMIT)];
    }

    // Check if file has been rejected for free users or if the user has been flagged as rejected
    if (
      (!$pro && $this->uploadsData->checkRejected($file['sha256'])) ||
      (!empty($userNpub) && $this->uploadsData->checkBlacklisted($userNpub))
    ) {
      error_log('File has been flagged as rejected');
      return [false, 403, "File or User has been flagged as rejected"];
    }

    // IP-blocklist gate. Free-only — active subscribers ($pro === true means
    // a valid, non-expired plan with available storage; see routes_blossom /
    // routes_nip96 / routes_account / routes_upload for how that's wired) are
    // implicitly whitelisted. Rationale: paying customers shouldn't get
    // locked out by a shared/VPN/CGNAT block targeting bad-actor traffic. The
    // npub blacklist above still applies to pro users — that's the
    // authoritative ban path.
    //
    // For free users: if the npub passed, drop the upload when the source IP
    // is on the CIDR blocklist. The npub doubles as the whitelist override
    // key, so an admin can manually whitelist a specific free user.
    if (!$pro) {
      $clientIp = $this->clientIp ?? IpAccessControl::extractClientIp();
      if ($clientIp !== null) {
        try {
          $iac = new IpAccessControl($this->uploadsData->getDb());
          if ($iac->isBlocked($clientIp, $userNpub)) {
            error_log('IP blocked: ' . $clientIp . ' (npub: ' . ($userNpub !== '' ? $userNpub : 'anon') . ')');
            return [false, 403, 'Access denied'];
          }
        } catch (\Throwable $e) {
          // Fail open on infrastructure errors so a transient DB problem
          // doesn't take down uploads. Block decisions log loudly above.
          error_log('IP blocklist check failed (allowing upload): ' . $e->getMessage());
        }

        // Tor exit-node gate: accounts without an active paid plan cannot
        // upload via Tor ($pro === true means valid, non-expired plan — see
        // the blocklist rationale above). The per-npub ip_whitelist override
        // applies here too, and the check fails open like the one above.
        // List upkeep is scheduled from api/v2/index.php; the extra schedule
        // call here makes the feature self-healing if that hook ever moves.
        try {
          $torList = new TorExitList();
          $torList->maybeScheduleRefresh();
          if ($torList->contains($clientIp)) {
            $iacTor = new IpAccessControl($this->uploadsData->getDb());
            if ($userNpub === '' || !$iacTor->isWhitelisted($userNpub)) {
              error_log('Tor exit upload blocked: ' . $clientIp . ' (npub: ' . ($userNpub !== '' ? $userNpub : 'anon') . ')');
              return [false, 403, 'Access denied'];
            }
          }
        } catch (\Throwable $e) {
          error_log('Tor exit check failed (allowing upload): ' . $e->getMessage());
        }
      }
    }

    // Calculate remaining space and check if file size exceeds the remaining space for pro users
    if ($pro && $account !== null) {
      // Check if account has expired
      if ($account->isExpired()) {
        error_log('Account has expired');
        return [false, 403, "Account has expired, please renew at https://nostr.build/plans/"];
      }
      // TODO: Need to validate array of files, so we do not allow to go over the limit with batch
      if (!$account->hasSufficientStorageSpace($file['size'])) {
        error_log('File size exceeds the remaining space of ' . formatSizeUnits($account->getPerFileUploadLimit()));
        return [false, 413, "File size exceeds the remaining space of " . formatSizeUnits($account->getPerFileUploadLimit())];
      }
    }

    return [true, 200, "Validation successful"];
  }
}
