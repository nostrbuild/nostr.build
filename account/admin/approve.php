<?php

require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/libs/permissions.class.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/SiteConfig.php');

// Create new Permission object
$perm = new Permission();

if (!$perm->isAdmin() && !$perm->hasPrivilege('canModerate')) {
  header("location: /login");
  $link->close(); // CLOSE MYSQL LINK
  exit;
}

// Handle search via GET parameters
$searchFile = isset($_GET['file']) ? trim($_GET['file']) : '';
$searchNpub = isset($_GET['npub']) ? trim($_GET['npub']) : '';

// Handle POST from search form - redirect to GET
if (isset($_POST['searchFile'])) {
  $searchInput = trim($_POST['searchFile']);

  if (strpos($searchInput, 'npub1') === 0) {
    header("Location: ?npub=" . urlencode($searchInput));
  } else {
    $path = parse_url($searchInput, PHP_URL_PATH);
    $filename = basename($path);
    header("Location: ?file=" . urlencode($filename));
  }
  exit;
}
?>
<!DOCTYPE html>
<html lang="en" class="bg-gradient-to-b from-[#292556] to-[#120a24]">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>nostr.build - Admin Moderation</title>
  <link rel="stylesheet" href="/styles/twbuild.css?v=55c61227cf93fa645c958b626ab16209">
  <link rel="icon" href="https://cdn.nostr.build/assets/primo_nostr.png">
  <style>
    [x-cloak] { display: none !important; }
    .media-loaded .media-loading { display: none; }
  </style>
  <script defer src="/scripts/fw/alpinejs.min.js?v=34fbe266eb872c1a396b8bf9022b7105"></script>
  <script>
    if (window.history.replaceState) {
      window.history.replaceState(null, null, window.location.href);
    }

    document.addEventListener('DOMContentLoaded', function() {
      // Modal functionality - event delegation on document
      document.addEventListener('click', function(e) {
        const mediaPreview = e.target.closest('.media-preview');

        // Only proceed if clicked inside media-preview
        if (!mediaPreview) return;

        // Don't open if clicking badges (they're inside media-preview)
        if (e.target.closest('.status-badge') || e.target.tagName === 'SPAN') {
          return;
        }

        const mediaUrl = mediaPreview.getAttribute('data-media-url');
        const mediaType = mediaPreview.getAttribute('data-media-type');

        const modal = document.getElementById('mediaModal');
        const modalContent = document.getElementById('modalMediaContent');

        // Sanitize URL to prevent XSS
        const sanitizedUrl = encodeURI(decodeURI(mediaUrl));

        if (mediaType === 'video') {
          const video = document.createElement('video');
          video.controls = true;
          video.autoplay = true;
          video.className = 'max-h-[80vh] max-w-full mx-auto';
          video.onerror = () => { modalContent.innerHTML = '<p class="text-red-400">Failed to load video</p>'; };
          const source = document.createElement('source');
          source.src = sanitizedUrl;
          source.type = 'video/mp4';
          video.appendChild(source);
          modalContent.innerHTML = '';
          modalContent.appendChild(video);
        } else {
          const img = document.createElement('img');
          img.src = sanitizedUrl;
          img.className = 'max-h-[80vh] max-w-full mx-auto object-contain';
          img.alt = 'Full size media';
          img.onerror = () => { modalContent.innerHTML = '<p class="text-red-400">Failed to load image</p>'; };
          modalContent.innerHTML = '';
          modalContent.appendChild(img);
        }

        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
      });

      window.closeMediaModal = function() {
        const modal = document.getElementById('mediaModal');
        const modalContent = document.getElementById('modalMediaContent');
        
        // Stop and cleanup video elements to prevent memory leaks
        const video = modalContent.querySelector('video');
        if (video) {
          video.pause();
          video.src = '';
          video.load();
        }
        
        modal.classList.add('hidden');
        modalContent.innerHTML = '';
        document.body.style.overflow = 'auto';
      };

      // Close modal on escape key
      document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
          closeMediaModal();
        }
      });

      // Custom confirmation dialog
      window.showConfirmDialog = function(message, onConfirm) {
        return new Promise((resolve) => {
          const dialog = document.getElementById('confirmDialog');
          const messageEl = document.getElementById('confirmMessage');
          const confirmBtn = document.getElementById('confirmBtn');
          const cancelBtn = document.getElementById('cancelBtn');

          messageEl.textContent = message;
          dialog.classList.remove('hidden');
          document.body.style.overflow = 'hidden';

          const handleConfirm = () => {
            dialog.classList.add('hidden');
            document.body.style.overflow = 'auto';
            cleanup();
            resolve(true);
            onConfirm();
          };

          const handleCancel = () => {
            dialog.classList.add('hidden');
            document.body.style.overflow = 'auto';
            cleanup();
            resolve(false);
          };

          const handleKeyPress = (e) => {
            if (e.key === 'Enter') {
              e.preventDefault();
              handleConfirm();
            } else if (e.key === 'Escape') {
              e.preventDefault();
              handleCancel();
            }
          };

          const cleanup = () => {
            confirmBtn.removeEventListener('click', handleConfirm);
            cancelBtn.removeEventListener('click', handleCancel);
            document.removeEventListener('keydown', handleKeyPress);
          };

          confirmBtn.addEventListener('click', handleConfirm);
          cancelBtn.addEventListener('click', handleCancel);
          document.addEventListener('keydown', handleKeyPress);

          // Focus the confirm button
          setTimeout(() => confirmBtn.focus(), 100);
        });
      };

      // Dangerous confirmation dialog with checkbox
      window.showDangerConfirmDialog = function(options) {
        return new Promise((resolve) => {
          const dialog = document.getElementById('dangerConfirmDialog');
          const titleEl = document.getElementById('dangerConfirmTitle');
          const messageEl = document.getElementById('dangerConfirmMessage');
          const warningEl = document.getElementById('dangerConfirmWarning');
          const checkbox = document.getElementById('dangerConfirmCheckbox');
          const checkboxLabel = document.getElementById('dangerConfirmCheckboxLabel');
          const confirmBtn = document.getElementById('dangerConfirmBtn');
          const cancelBtn = document.getElementById('dangerCancelBtn');

          titleEl.textContent = options.title || '⚠️ Dangerous Action';
          messageEl.textContent = options.message || 'Are you sure?';
          warningEl.textContent = options.warning || 'This action cannot be undone.';
          checkboxLabel.textContent = options.checkboxLabel || 'I understand and want to proceed';
          
          checkbox.checked = false;
          confirmBtn.disabled = true;
          
          dialog.classList.remove('hidden');
          document.body.style.overflow = 'hidden';

          const handleCheckboxChange = () => {
            confirmBtn.disabled = !checkbox.checked;
          };

          const handleConfirm = () => {
            if (!checkbox.checked) return;
            dialog.classList.add('hidden');
            document.body.style.overflow = 'auto';
            cleanup();
            resolve(true);
          };

          const handleCancel = () => {
            dialog.classList.add('hidden');
            document.body.style.overflow = 'auto';
            cleanup();
            resolve(false);
          };

          const handleKeyPress = (e) => {
            if (e.key === 'Escape') {
              e.preventDefault();
              handleCancel();
            }
          };

          const cleanup = () => {
            checkbox.removeEventListener('change', handleCheckboxChange);
            confirmBtn.removeEventListener('click', handleConfirm);
            cancelBtn.removeEventListener('click', handleCancel);
            document.removeEventListener('keydown', handleKeyPress);
          };

          checkbox.addEventListener('change', handleCheckboxChange);
          confirmBtn.addEventListener('click', handleConfirm);
          cancelBtn.addEventListener('click', handleCancel);
          document.addEventListener('keydown', handleKeyPress);
        });
      };

      // Progress modal functions
      window.progressModal = {
        show: function(title) {
          const modal = document.getElementById('progressModal');
          const barFill = document.getElementById('progressBarFill');
          document.getElementById('progressTitle').textContent = title;
          barFill.style.width = '0%';
          barFill.className = 'h-full bg-purple-500 rounded-full transition-all duration-300';
          document.getElementById('progressText').textContent = 'Starting...';
          document.getElementById('progressDetails').textContent = '';
          document.getElementById('progressLog').innerHTML = '';
          document.getElementById('progressLog').classList.add('hidden');
          document.getElementById('progressComplete').classList.add('hidden');
          modal.classList.remove('hidden');
          document.body.style.overflow = 'hidden';
        },
        
        update: function(current, total, statusText, detailText) {
          const percent = Math.round((current / total) * 100);
          document.getElementById('progressBarFill').style.width = percent + '%';
          document.getElementById('progressText').textContent = statusText || `Processing ${current} of ${total}...`;
          if (detailText) {
            document.getElementById('progressDetails').textContent = detailText;
          }
        },
        
        log: function(message, isError) {
          const logEl = document.getElementById('progressLog');
          logEl.classList.remove('hidden');
          const line = document.createElement('div');
          line.textContent = message;
          if (isError) line.classList.add('text-red-400');
          logEl.appendChild(line);
          logEl.scrollTop = logEl.scrollHeight;
        },
        
        complete: function(success, message) {
          const barFill = document.getElementById('progressBarFill');
          barFill.style.width = '100%';
          barFill.className = success 
            ? 'h-full bg-green-500 rounded-full transition-all duration-300'
            : 'h-full bg-red-500 rounded-full transition-all duration-300';
          document.getElementById('progressText').textContent = message;
          document.getElementById('progressComplete').classList.remove('hidden');
          
          document.getElementById('progressCloseBtn').onclick = function() {
            document.getElementById('progressModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
            window.location.reload();
          };
        },
        
        hide: function() {
          document.getElementById('progressModal').classList.add('hidden');
          document.body.style.overflow = 'auto';
        }
      };

      // Process items one by one with progress tracking
      window.processItemsOneByOne = async function(ids, status, onItemComplete) {
        let successCount = 0;
        let errorCount = 0;
        const total = ids.length;
        
        for (let i = 0; i < ids.length; i++) {
          const id = ids[i];
          
          try {
            const response = await fetch('change_status.php', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
              },
              body: 'id=' + encodeURIComponent(id) + '&status=' + encodeURIComponent(status),
            });
            
            const data = await response.json();
            
            if (data.success) {
              successCount++;
              if (onItemComplete) onItemComplete(i + 1, total, id, true);
            } else {
              errorCount++;
              progressModal.log(`Error on item ${id}: ${data.error}`, true);
              if (onItemComplete) onItemComplete(i + 1, total, id, false, data.error);
            }
          } catch (error) {
            errorCount++;
            progressModal.log(`Network error on item ${id}: ${error.message}`, true);
            if (onItemComplete) onItemComplete(i + 1, total, id, false, error.message);
          }
          
          // Small delay to avoid overwhelming the server
          if (i < ids.length - 1) {
            await new Promise(resolve => setTimeout(resolve, 100));
          }
        }
        
        return { successCount, errorCount, total };
      };

      // Status change handler
      document.querySelectorAll('.status-btn').forEach(function(button) {
        button.addEventListener('click', function(e) {
          e.preventDefault();
          const card = button.closest('.media-card');
          const imgDiv = button.closest('.media-item');
          const id = card.querySelector('input[name="id"]').value;
          const status = button.value;

          // Customize confirmation text based on the button value
          const confirmText = (status === 'adult') ?
            'Are you sure you want to mark this media as Adult content?' :
            (status === 'rejected') ?
            'Are you sure you want to REJECT this media? This will permanently delete it with no ability to re-upload.' :
            (status === 'approved') ?
            'Are you sure you want to APPROVE this media?' :
            (status === 'ban') ?
            'Are you sure you want to BAN this user and delete all their content?' :
            'Are you sure you want to mark this as CSAM? This will permanently delete it with no ability to re-upload.';

          // Show the confirmation dialog
          showConfirmDialog(confirmText, () => {
            const badge = card.querySelector('.status-badge');
            
            // Disable all buttons in this card during processing and show spinner
            const cardButtons = card.querySelectorAll('.status-btn');
            cardButtons.forEach(btn => {
              btn.disabled = true;
              const originalText = btn.textContent;
              btn.setAttribute('data-original-text', originalText);
              btn.innerHTML = '<span class="inline-block w-3 h-3 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>';
            });
            
            fetch('change_status.php', {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'id=' + encodeURIComponent(id) + '&status=' + encodeURIComponent(status),
              })
              .then(response => response.json())
              .then(data => {
                // Re-enable buttons and restore text
                cardButtons.forEach(btn => {
                  btn.disabled = false;
                  btn.textContent = btn.getAttribute('data-original-text');
                });
                
                if (data.success) {
                  // Change the label and its color based on the status
                  if (status === 'approved') {
                    badge.textContent = 'Approved';
                    badge.className = 'absolute top-2 right-2 px-2 py-1 text-xs font-semibold rounded-full bg-green-500 text-white status-badge';
                    badge.closest('.media-item').setAttribute('data-status', 'approved');
                  } else if (status === 'adult') {
                    badge.textContent = 'Adult';
                    badge.className = 'absolute top-2 right-2 px-2 py-1 text-xs font-semibold rounded-full bg-yellow-500 text-gray-900 status-badge';
                    badge.closest('.media-item').setAttribute('data-status', 'adult');
                  } else if (status === 'rejected' || status === 'csam' || status === 'ban') {
                    // Remove the card with animation
                    imgDiv.style.opacity = '0.5';
                    imgDiv.style.transform = 'scale(0.9)';
                    setTimeout(() => imgDiv.remove(), 300);
                  }
                } else {
                  alert('Error: ' + data.error);
                }
              })
              .catch(error => {
                // Re-enable buttons on error and restore text
                cardButtons.forEach(btn => {
                  btn.disabled = false;
                  btn.textContent = btn.getAttribute('data-original-text');
                });
                alert('Network error: ' + error.message);
              });
          });
        });
      });

      // Approve all on current page
      const buttons = document.querySelectorAll('.approve-page-button');
      buttons.forEach(button => {
        button.addEventListener('click', function(e) {
          e.preventDefault();

          // Collect all image IDs from data-id attribute but exclude already processed statuses
          const imageIds = Array.from(document.querySelectorAll('[data-id]'))
            .filter(el => {
              const status = el.getAttribute('data-status');
              return status !== 'adult' &&
                     status !== 'approved' &&
                     status !== 'rejected' &&
                     status !== 'csam';
            })
            .map(el => el.getAttribute('data-id'));

          // If no valid IDs left after filtering, show an alert and return
          if (imageIds.length === 0) {
            alert('No media items to approve.');
            return;
          }

          showConfirmDialog(`Are you sure you want to approve ${imageIds.length} media items on this page?`, () => {
            // Disable all approve buttons during processing
            const allApproveButtons = document.querySelectorAll('.approve-page-button');
            allApproveButtons.forEach(btn => {
              btn.disabled = true;
              btn.textContent = 'Processing...';
            });
            
            // Make AJAX request to server to approve all
            fetch('approve_all.php', {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                  ids: imageIds
                }),
              })
              .then(response => response.json())
              .then(data => {
                if (data.success) {
                  // Show success message
                  const successMsg = document.createElement('div');
                  successMsg.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50';
                  successMsg.textContent = `Successfully approved ${imageIds.length} items!`;
                  document.body.appendChild(successMsg);

                  setTimeout(() => {
                    successMsg.remove();
                    // Remove ?page=<page number> from URL
                    const urlWithoutPageParam = window.location.href.split('?')[0];
                    window.history.replaceState({}, document.title, urlWithoutPageParam);
                    // Refresh the page
                    window.location.reload();
                  }, 1500);
                } else {
                  // Re-enable buttons on error
                  allApproveButtons.forEach(btn => {
                    btn.disabled = false;
                    btn.textContent = 'Approve Current Page';
                  });
                  alert('Error: ' + data.error);
                }
              })
              .catch(error => {
                // Re-enable buttons on network error
                allApproveButtons.forEach(btn => {
                  btn.disabled = false;
                  btn.textContent = 'Approve Current Page';
                });
                alert('Network error: ' + error.message);
              });
          });
        });
      });

      // Mark All as Adult button handler (npub view only)
      const markAllAdultBtn = document.getElementById('markAllAdultBtn');
      if (markAllAdultBtn) {
        markAllAdultBtn.addEventListener('click', async function(e) {
          e.preventDefault();
          
          // Collect all media IDs that are NOT already adult, rejected, or csam
          const mediaIds = Array.from(document.querySelectorAll('[data-id]'))
            .filter(el => {
              const status = el.getAttribute('data-status');
              return status !== 'adult' && status !== 'rejected' && status !== 'csam';
            })
            .map(el => el.getAttribute('data-id'));
          
          if (mediaIds.length === 0) {
            alert('No media items to mark as adult.');
            return;
          }
          
          const confirmed = await showDangerConfirmDialog({
            title: '⚠️ Mark All Media as Adult',
            message: `You are about to mark ${mediaIds.length} media item(s) as Adult content.`,
            warning: 'This will flag all displayed media from this user as adult content. This action affects all items shown on this page.',
            checkboxLabel: `Yes, I want to mark all ${mediaIds.length} item(s) as Adult content`
          });
          
          if (!confirmed) return;
          
          // Disable the button
          markAllAdultBtn.disabled = true;
          
          // Show progress modal
          progressModal.show('Marking Media as Adult');
          
          // Process one by one
          const result = await processItemsOneByOne(mediaIds, 'adult', (current, total, id, success) => {
            progressModal.update(current, total, `Processing ${current} of ${total}...`, `Item ID: ${id}`);
            
            // Update the UI for this item
            if (success) {
              const item = document.querySelector(`[data-id="${id}"]`);
              if (item) {
                item.setAttribute('data-status', 'adult');
                const badge = item.querySelector('.status-badge');
                if (badge) {
                  badge.textContent = 'Adult';
                  badge.className = 'status-badge absolute top-2 right-2 px-2 py-1 text-xs font-semibold rounded-full bg-yellow-500 text-gray-900 z-20';
                }
              }
            }
          });
          
          // Show completion
          if (result.errorCount === 0) {
            progressModal.complete(true, `Successfully marked ${result.successCount} item(s) as Adult!`);
          } else {
            progressModal.complete(false, `Completed with ${result.errorCount} error(s). ${result.successCount} succeeded.`);
          }
        });
      }

      // Reject All and Ban User button handler (npub view only, admin only)
      const rejectAllBanBtn = document.getElementById('rejectAllBanBtn');
      if (rejectAllBanBtn) {
        rejectAllBanBtn.addEventListener('click', async function(e) {
          e.preventDefault();
          
          const npub = rejectAllBanBtn.getAttribute('data-npub');
          if (!npub) {
            alert('Error: No npub found');
            return;
          }
          
          // Collect all media IDs that are NOT already rejected or csam
          const mediaIds = Array.from(document.querySelectorAll('[data-id]'))
            .filter(el => {
              const status = el.getAttribute('data-status');
              return status !== 'rejected' && status !== 'csam';
            })
            .map(el => el.getAttribute('data-id'));
          
          if (mediaIds.length === 0) {
            alert('No media items to reject.');
            return;
          }
          
          const confirmed = await showDangerConfirmDialog({
            title: '🚨 REJECT ALL & BAN USER',
            message: `You are about to BAN this user and PERMANENTLY DELETE all ${mediaIds.length} media item(s).`,
            warning: 'THIS ACTION CANNOT BE UNDONE! The user will be banned first, then ALL their media will be permanently deleted one by one. The files cannot be re-uploaded.',
            checkboxLabel: `I understand this will BAN the user and PERMANENTLY DELETE all ${mediaIds.length} item(s)`
          });
          
          if (!confirmed) return;
          
          // Disable the button
          rejectAllBanBtn.disabled = true;
          
          // Show progress modal
          progressModal.show('Banning User & Rejecting All Media');
          
          // STEP 1: Ban the user FIRST using the first media item
          progressModal.update(0, mediaIds.length + 1, 'Step 1: Banning user...', `Using media ID: ${mediaIds[0]}`);
          progressModal.log('Initiating user ban...');
          
          try {
            const banResponse = await fetch('change_status.php', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
              },
              body: 'id=' + encodeURIComponent(mediaIds[0]) + '&status=ban',
            });
            
            const banData = await banResponse.json();
            
            if (!banData.success) {
              progressModal.log('Failed to ban user: ' + banData.error, true);
              progressModal.complete(false, 'Failed to ban user. Operation aborted.');
              rejectAllBanBtn.disabled = false;
              return;
            }
            
            progressModal.log('User banned successfully!');
            
            // Remove the first item from the list (it was already deleted by the ban)
            const remainingIds = mediaIds.slice(1);
            
            // Update UI for the first item
            const firstItem = document.querySelector(`[data-id="${mediaIds[0]}"]`);
            if (firstItem) {
              firstItem.style.opacity = '0.5';
              firstItem.style.transform = 'scale(0.9)';
              setTimeout(() => firstItem.remove(), 300);
            }
            
            if (remainingIds.length === 0) {
              progressModal.complete(true, 'User banned and all media deleted!');
              return;
            }
            
            // STEP 2: Reject remaining media one by one
            progressModal.log(`Proceeding to reject ${remainingIds.length} remaining item(s)...`);
            
            let successCount = 1; // Count the ban as first success
            let errorCount = 0;
            
            for (let i = 0; i < remainingIds.length; i++) {
              const id = remainingIds[i];
              
              progressModal.update(i + 2, mediaIds.length + 1, `Step 2: Rejecting media ${i + 1} of ${remainingIds.length}...`, `Item ID: ${id}`);
              
              try {
                const response = await fetch('change_status.php', {
                  method: 'POST',
                  headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                  },
                  body: 'id=' + encodeURIComponent(id) + '&status=rejected',
                });
                
                const data = await response.json();
                
                if (data.success) {
                  successCount++;
                  // Remove the item from UI
                  const item = document.querySelector(`[data-id="${id}"]`);
                  if (item) {
                    item.style.opacity = '0.5';
                    item.style.transform = 'scale(0.9)';
                    setTimeout(() => item.remove(), 300);
                  }
                } else {
                  errorCount++;
                  progressModal.log(`Error rejecting item ${id}: ${data.error}`, true);
                }
              } catch (error) {
                errorCount++;
                progressModal.log(`Network error on item ${id}: ${error.message}`, true);
              }
              
              // Small delay to avoid overwhelming the server
              if (i < remainingIds.length - 1) {
                await new Promise(resolve => setTimeout(resolve, 150));
              }
            }
            
            // Show completion
            if (errorCount === 0) {
              progressModal.complete(true, `User banned and ${successCount} media item(s) deleted!`);
            } else {
              progressModal.complete(false, `Completed with ${errorCount} error(s). User banned, ${successCount} item(s) deleted.`);
            }
            
          } catch (error) {
            progressModal.log('Network error during ban: ' + error.message, true);
            progressModal.complete(false, 'Network error. Please try again.');
            rejectAllBanBtn.disabled = false;
          }
        });
      }

      // Hover autoplay for grid videos: play on mouseenter, pause on mouseleave
      (function() {
        const setupVideo = (video) => {
          if (!video || video.dataset.hoverAutoplayInitialized) return;
          video.dataset.hoverAutoplayInitialized = '1';

          // remember original muted state so we can restore it
          video.dataset.originalMuted = video.muted ? '1' : '0';

          let hovered = false;

          const overlay = video.parentElement && video.parentElement.querySelector && video.parentElement.querySelector('.media-play-overlay');

          const startPlay = () => {
            try {
              if (video.paused) {
                // Ensure muted while autoplaying to satisfy browser autoplay policies
                if (!video.muted) {
                  video.muted = true;
                  video.dataset._tempMuted = '1';
                }
                const p = video.play();
                if (p && typeof p.catch === 'function') p.catch(() => {});
              }
            } catch (e) { /* ignore play errors */ }
            // hide overlay if present (tailwind opacity utility)
            try { if (overlay) overlay.classList.add('opacity-0'); } catch (e) {}
          };

          const stopPlay = () => {
            try {
              if (!video.paused) video.pause();
              // restore muted state if we temporarily muted it
              if (video.dataset._tempMuted) {
                video.muted = (video.dataset.originalMuted === '1');
                delete video.dataset._tempMuted;
              }
            } catch (e) { /* ignore pause errors */ }
            // show overlay again (remove opacity-0)
            try { if (overlay) overlay.classList.remove('opacity-0'); } catch (e) {}
          };

          video.addEventListener('mouseenter', function() {
            hovered = true;
            startPlay();
          });

          video.addEventListener('mouseleave', function() {
            hovered = false;
            stopPlay();
          });

          // On touch devices, pause on touch to avoid accidental plays
          video.addEventListener('touchstart', function() { stopPlay(); }, { passive: true });
        };

        // Initialize existing videos inside media previews
        document.querySelectorAll('.media-preview video').forEach(setupVideo);

        // Watch for dynamically added videos (e.g. AJAX / infinite load)
        const observer = new MutationObserver((mutations) => {
          for (const m of mutations) {
            for (const node of m.addedNodes) {
              if (node.nodeType !== 1) continue;

              // If a media-preview element was added, initialize any video inside it
              if (node.matches && node.matches('.media-preview')) {
                const vids = node.querySelectorAll && node.querySelectorAll('video');
                if (vids && vids.length) vids.forEach(v => setupVideo(v));
                continue;
              }

              // If a video node was added, only initialize it when it's inside a .media-preview
              if (node.matches && node.matches('video')) {
                if (node.closest && node.closest('.media-preview')) setupVideo(node);
                continue;
              }

              // For other added subtrees, only initialize videos that are inside .media-preview
              const vids = node.querySelectorAll && node.querySelectorAll('video');
              if (vids && vids.length) {
                vids.forEach(v => {
                  if (v.closest && v.closest('.media-preview')) setupVideo(v);
                });
              }
            }
          }
        });
        observer.observe(document.body, { childList: true, subtree: true });
      })();

      // Image hover zoom (2x) - pop out of container while preserving aspect ratio
      (function() {
        const setupImage = (img) => {
          if (!img || img.dataset.zoomInit) return;
          img.dataset.zoomInit = '1';

          const preview = img.closest('.media-preview');
          if (!preview) return;

          let touchPrevent = false;

          const enter = (e) => {
            try {
              // Walk up from preview and make any overflow-hidden ancestors visible so
              // the zoomed image can escape the card. Save previous values to data attributes
              const ancestors = [];
              let node = preview;
              while (node && node !== document.body) {
                const prevOverflow = node.style.overflow || '';
                const prevZ = node.style.zIndex || '';
                node.dataset._oldOverflow = prevOverflow;
                node.dataset._oldZ = prevZ;
                // set visible and raise stacking context
                node.style.overflow = 'visible';
                node.style.zIndex = '40';
                ancestors.push(node);
                node = node.parentElement;
              }
              // store ancestors so we can restore them on leave
              img.dataset._zoomAncestors = ancestors.map(n => {
                // use a simple identifier: store an attribute on the element
                const id = 'zoom_' + Math.random().toString(36).slice(2,9);
                n.dataset._zoomId = id;
                return id;
              }).join(' ');

              // Save previous inline styles so we can restore them
              img.dataset._oldTransform = img.style.transform || '';
              img.dataset._oldTransformOrigin = img.style.transformOrigin || '';
              img.dataset._oldObjectFit = img.style.objectFit || '';
              img.dataset._oldBoxShadow = img.style.boxShadow || '';
              img.dataset._oldCursor = img.style.cursor || '';

              // Scale the image, set transform-origin to bottom so bottom edge stays aligned,
              // and translate slightly upward so the zoom appears popped out above the card.
              img.style.transformOrigin = '50% 100%'; // bottom center
              img.style.transform = 'translateY(-8px) scale(2)';
              img.style.zIndex = '50';
              img.style.boxShadow = '0 12px 36px rgba(0,0,0,0.6)';
              img.style.objectFit = 'contain';
              img.style.cursor = 'zoom-out';
            } catch (err) { /* ignore */ }
          };

          const leave = (e) => {
            try {
              // restore image appearance from saved values
              img.style.transform = img.dataset._oldTransform || '';
              img.style.transformOrigin = img.dataset._oldTransformOrigin || '';
              img.style.zIndex = '';
              img.style.boxShadow = img.dataset._oldBoxShadow || '';
              img.style.objectFit = img.dataset._oldObjectFit || '';
              img.style.cursor = img.dataset._oldCursor || 'zoom-in';
              delete img.dataset._oldTransform;
              delete img.dataset._oldTransformOrigin;
              delete img.dataset._oldObjectFit;
              delete img.dataset._oldBoxShadow;
              delete img.dataset._oldCursor;

              // restore ancestor overflow and zIndex from saved data attributes
              let node = preview;
              while (node && node !== document.body) {
                if (node.dataset && typeof node.dataset._oldOverflow !== 'undefined') {
                  node.style.overflow = node.dataset._oldOverflow;
                  delete node.dataset._oldOverflow;
                }
                if (node.dataset && typeof node.dataset._oldZ !== 'undefined') {
                  node.style.zIndex = node.dataset._oldZ;
                  delete node.dataset._oldZ;
                }
                if (node.dataset && node.dataset._zoomId) delete node.dataset._zoomId;
                node = node.parentElement;
              }
              delete img.dataset._zoomAncestors;
            } catch (err) { /* ignore */ }
          };

          img.addEventListener('mouseenter', enter);
          img.addEventListener('mouseleave', leave);

          // Support keyboard focus (accessible zoom with focus/blur)
          img.setAttribute('tabindex', '0');
          img.addEventListener('focus', enter);
          img.addEventListener('blur', leave);

          // Touch: treat touchstart as a cancellation of hover zoom to avoid accidental zooms
          img.addEventListener('touchstart', function() {
            touchPrevent = true;
            leave();
          }, { passive: true });
        };

        // Initialize existing images
        document.querySelectorAll('.media-preview img.media-zoom-img').forEach(setupImage);

        // Observe added nodes for images
        const imgObserver = new MutationObserver((mutations) => {
          for (const m of mutations) {
            for (const node of m.addedNodes) {
              if (node.nodeType !== 1) continue;
              if (node.matches && node.matches('img.media-zoom-img')) setupImage(node);
              const imgs = node.querySelectorAll && node.querySelectorAll('img.media-zoom-img');
              if (imgs && imgs.length) imgs.forEach(setupImage);
            }
          }
        });
        imgObserver.observe(document.body, { childList: true, subtree: true });
      })();

      // Show a global loading overlay immediately when clicking an npub link
      function showGlobalLoading(npub) {
        const overlay = document.getElementById('globalLoadingOverlay');
        if (!overlay) return;
        overlay.classList.remove('hidden');
        // Add flex layout only when showing to avoid CSS conflict with 'hidden'
        overlay.classList.add('flex', 'items-center', 'justify-center', 'flex-col', 'gap-4');

        const msg = document.getElementById('globalLoadingMessage');
        if (msg) {
          if (npub) {
            msg.textContent = `Loading user's (${npub}) media...`;
          } else {
            msg.textContent = 'Loading user media...';
          }
        }
      }

      document.addEventListener('click', function(e) {
        try {
          const anchor = e.target.closest && e.target.closest('a[href*="?npub="]');
          if (!anchor) return;

          // Only show overlay for normal left-click navigation (not ctrl/cmd/shift clicks or middle-click)
          if (e.defaultPrevented) return;
          if (e.button !== 0) return; // only left click
          if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;

          // Extract npub param from the link (anchor.href is fully resolved by browser)
          try {
            const url = new URL(anchor.href);
            const npub = url.searchParams.get('npub');
            showGlobalLoading(npub);
          } catch (err) {
            showGlobalLoading();
          }

          // Let the navigation proceed normally
        } catch (err) {
          // ignore
        }
      });
    });
  </script>
</head>

<body class="min-h-screen bg-gradient-to-br from-[#292556] to-[#120a24] text-gray-100">
  <!-- Global Loading Overlay (Tailwind only; hidden by default). Shown when clicking an npub link. -->
  <div id="globalLoadingOverlay" class="hidden fixed inset-0 bg-black bg-opacity-90 z-50" aria-hidden="true">
    <div class="w-16 h-16 border-4 border-gray-500 border-t-purple-400 rounded-full animate-spin" role="status" aria-label="Loading"></div>
    <div id="globalLoadingMessage" class="text-gray-200 text-sm text-center mt-2">Loading user media...</div>
  </div>
  <!-- Media Modal -->
  <div id="mediaModal" class="hidden">
    <div class="fixed inset-0 bg-black bg-opacity-90 z-50 flex items-center justify-center p-4" onclick="closeMediaModal()">
      <button onclick="event.stopPropagation(); closeMediaModal();" class="absolute top-4 right-4 text-white hover:text-gray-300 text-4xl font-bold z-20" aria-label="Close modal">
        &times;
      </button>
      <div id="modalMediaContent" class="relative max-w-full max-h-full flex items-center justify-center z-10" onclick="event.stopPropagation()"></div>
    </div>
  </div>

  <!-- Confirmation Dialog -->
  <div id="confirmDialog" class="hidden">
    <div class="fixed inset-0 bg-black bg-opacity-75 z-50 flex items-center justify-center p-4">
      <div class="bg-[#1a1433] rounded-lg shadow-2xl max-w-md w-full p-6 border border-purple-500">
        <h3 class="text-xl font-bold text-purple-300 mb-4">Confirm Action</h3>
        <p id="confirmMessage" class="text-gray-300 mb-6"></p>
        <div class="flex gap-3 justify-end">
          <button id="cancelBtn" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-md transition-colors">
            Cancel
          </button>
          <button id="confirmBtn" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-md transition-colors font-semibold">
            Confirm
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Enhanced Confirmation Dialog with Checkbox (for dangerous bulk actions) -->
  <div id="dangerConfirmDialog" class="hidden">
    <div class="fixed inset-0 bg-black bg-opacity-75 z-50 flex items-center justify-center p-4">
      <div class="bg-[#1a1433] rounded-lg shadow-2xl max-w-lg w-full p-6 border border-red-500">
        <h3 id="dangerConfirmTitle" class="text-xl font-bold text-red-400 mb-4">⚠️ Dangerous Action</h3>
        <p id="dangerConfirmMessage" class="text-gray-300 mb-4"></p>
        <div id="dangerConfirmWarning" class="bg-red-900/30 border border-red-500/50 rounded-md p-3 mb-4 text-red-300 text-sm"></div>
        <div class="mb-6">
          <label class="flex items-start gap-3 cursor-pointer">
            <input type="checkbox" id="dangerConfirmCheckbox" class="mt-1 w-5 h-5 rounded border-gray-600 text-red-600 focus:ring-red-500 bg-gray-700">
            <span id="dangerConfirmCheckboxLabel" class="text-gray-300 text-sm"></span>
          </label>
        </div>
        <div class="flex gap-3 justify-end">
          <button id="dangerCancelBtn" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-md transition-colors">
            Cancel
          </button>
          <button id="dangerConfirmBtn" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-md transition-colors font-semibold disabled:opacity-50 disabled:cursor-not-allowed" disabled>
            Confirm
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Progress Modal -->
  <div id="progressModal" class="hidden">
    <div class="fixed inset-0 bg-black bg-opacity-90 z-50 flex items-center justify-center p-4">
      <div class="bg-[#1a1433] rounded-lg shadow-2xl max-w-lg w-full p-6 border border-purple-500">
        <h3 id="progressTitle" class="text-xl font-bold text-purple-300 mb-4">Processing...</h3>
        <div class="mb-4">
          <div class="h-2 bg-purple-500/20 rounded-full overflow-hidden">
            <div id="progressBarFill" class="h-full bg-purple-500 rounded-full transition-all duration-300 w-0"></div>
          </div>
        </div>
        <p id="progressText" class="text-gray-300 text-center mb-2">Starting...</p>
        <p id="progressDetails" class="text-gray-500 text-sm text-center mb-4"></p>
        <div id="progressLog" class="bg-black/30 rounded-md p-3 max-h-32 overflow-y-auto text-xs font-mono text-gray-400 hidden"></div>
        <div id="progressComplete" class="hidden mt-4">
          <button id="progressCloseBtn" class="w-full px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-md transition-colors font-semibold">
            Close & Refresh
          </button>
        </div>
      </div>
    </div>
  </div>

  <main class="container mx-auto px-4 py-8 max-w-[1920px]">
    <!-- Header Section -->
    <section class="mb-8">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-4xl font-bold text-purple-300 mb-2">Content Moderation</h1>
          <p class="text-gray-400">
            <?php if (!empty($searchNpub)): ?>
              Viewing all media from user
            <?php elseif (!empty($searchFile)): ?>
              Search results
            <?php else: ?>
              Review and approve pending media uploads
            <?php endif; ?>
          </p>
        </div>
        <?php if (!empty($searchFile) || !empty($searchNpub)): ?>
          <a href="?" class="px-6 py-3 bg-purple-600 hover:bg-purple-700 text-white rounded-md font-semibold transition-colors shadow-lg flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            Back to Queue
          </a>
        <?php endif; ?>
      </div>
    </section>

    <!-- Search Box -->
    <form method="post" class="mb-6">
      <div class="flex gap-2">
        <input
          type="text"
          class="flex-1 px-4 py-2 bg-[#1a1433] border border-purple-500/30 rounded-md text-gray-100 placeholder-gray-500 focus:outline-none focus:border-purple-500 focus:ring-1 focus:ring-purple-500"
          name="searchFile"
          placeholder="Enter filename, URL, or npub to search..."
          value="<?= htmlspecialchars($searchFile ?: $searchNpub) ?>" required>
        <button class="px-6 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-md font-semibold transition-colors" type="submit">
          Search
        </button>
        <?php if (!empty($searchFile) || !empty($searchNpub)): ?>
          <a href="?" class="px-6 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-md font-semibold transition-colors flex items-center">
            Clear
          </a>
        <?php endif; ?>
      </div>
      <?php if (!empty($searchNpub)): ?>
        <p class="mt-2 text-sm text-purple-300">
          Showing all media from user: <span class="font-mono"><?= htmlspecialchars(substr($searchNpub, 0, 20)) ?>...</span>
        </p>
      <?php elseif (!empty($searchFile)): ?>
        <p class="mt-2 text-sm text-purple-300">
          Searching for filename: <span class="font-mono"><?= htmlspecialchars($searchFile) ?></span>
        </p>
      <?php endif; ?>
    </form>

    <?php
    if (isset($_POST['button1'])) {
      $sql = "UPDATE uploads_data SET approval_status='approved' WHERE approval_status='pending'";
      if ($link->query($sql) === TRUE) {
        echo "<div class='bg-green-500/20 border border-green-500 text-green-300 px-4 py-3 rounded-md mb-4'>Images approved successfully!</div>";
      } else {
        error_log('Admin approve.php error: ' . $link->error);
        echo "<div class='bg-red-500/20 border border-red-500 text-red-300 px-4 py-3 rounded-md mb-4'>Error updating record. Please try again.</div>";
      }
    }

    // Query to get the total size of all uploads
    $sql = "SELECT total_files, total_size FROM uploads_summary WHERE id = 1";
    $result = $link->query($sql);
    $row = $result ? $result->fetch_assoc() : null;
    $totalSize = $row['total_size'] ?? 0;
    $totalCount = $row['total_files'] ?? 0;
    if ($result) {
      $result->close();
    }

    $totalSizeGB = number_format($totalSize / (1024 * 1024 * 1024), 2);
    ?>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
      <div class="bg-[#1a1433] border border-purple-500/30 rounded-lg p-6">
        <div class="text-sm text-gray-400 mb-1">Total Files</div>
        <div class="text-3xl font-bold text-purple-300"><?= number_format($totalCount) ?></div>
      </div>
      <div class="bg-[#1a1433] border border-purple-500/30 rounded-lg p-6">
        <div class="text-sm text-gray-400 mb-1">Total Size</div>
        <div class="text-3xl font-bold text-purple-300"><?= $totalSizeGB ?> GB</div>
      </div>
    </div>

    <!-- Weekly Upload Statistics -->
    <?php
    $sql = "SELECT DATE(upload_date) AS upload_day, COUNT(*) AS upload_count, SUM(file_size) AS total_size
        FROM uploads_data
        WHERE upload_date >= DATE(NOW()) - INTERVAL 7 DAY
        GROUP BY DATE(upload_date)
        ORDER BY upload_day DESC";
    $result = $link->query($sql);
    ?>

    <div x-data="{ open: false }" class="bg-[#1a1433] border border-purple-500/30 rounded-lg mb-6">
      <button @click="open = !open" class="w-full px-6 py-4 flex items-center justify-between text-left hover:bg-purple-500/10 transition-colors rounded-lg">
        <h2 class="text-xl font-bold text-purple-300">Past 7 Days Activity</h2>
        <svg class="w-5 h-5 text-purple-300 transition-transform duration-200" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
        </svg>
      </button>
      <div x-show="open" x-collapse x-cloak class="px-6 pb-6">
        <div class="overflow-x-auto">
          <table class="w-full text-left">
            <thead>
              <tr class="border-b border-purple-500/30">
                <th class="pb-3 text-gray-400 font-semibold">Date</th>
                <th class="pb-3 text-gray-400 font-semibold">Uploads</th>
                <th class="pb-3 text-gray-400 font-semibold">Size (GB)</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($row = $result->fetch_assoc()): ?>
                <?php
                $uploadDay = $row['upload_day'];
                $uploadCount = $row['upload_count'];
                $totalSize = $row['total_size'];
                $totalSizeGB = number_format($totalSize / (1024 * 1024 * 1024), 2);
                ?>
                <tr class="border-b border-purple-500/10">
                  <td class="py-3 text-gray-300"><?= $uploadDay ?></td>
                  <td class="py-3 text-gray-300"><?= number_format($uploadCount) ?></td>
                  <td class="py-3 text-gray-300"><?= $totalSizeGB ?></td>
                </tr>
              <?php endwhile; ?>
              <?php $result->close(); ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <?php
    // Here is the code that handles displaying images and pagination
    $isSearchMode = !empty($searchFile) || !empty($searchNpub);
    $searchLimit = 500; // Limit search results for performance
    
    if (!empty($searchFile)) {
      // Search by filename - limit results for performance
      $sql = "SELECT id, filename, type, usernpub, approval_status FROM uploads_data WHERE filename LIKE ? ORDER BY upload_date DESC LIMIT ?";
      $stmt = $link->prepare($sql);
      $searchTerm = $searchFile . '%';
      $stmt->bind_param('si', $searchTerm, $searchLimit);
      $stmt->execute();
      $result = $stmt->get_result();
      $resultCount = $result->num_rows;
      $stmt->close();
    } elseif (!empty($searchNpub)) {
      // Search by npub - show all media regardless of approval status (limited)
      $sql = "SELECT id, filename, type, usernpub, approval_status FROM uploads_data WHERE usernpub = ? ORDER BY upload_date DESC LIMIT ?";
      $stmt = $link->prepare($sql);
      $stmt->bind_param('si', $searchNpub, $searchLimit);
      $stmt->execute();
      $result = $stmt->get_result();
      $resultCount = $result->num_rows;
      $stmt->close();
    } else {
      // General display of pending images only
      $perpage = 204;
      $page = isset($_GET['p']) ? max(0, (int)$_GET['p']) : 0;
      $start = $page * $perpage;

      $sql = "SELECT id, filename, type, usernpub, approval_status FROM uploads_data WHERE approval_status = 'pending' ORDER BY upload_date DESC LIMIT ?, ?";
      $stmt = $link->prepare($sql);
      $stmt->bind_param('ii', $start, $perpage);
      $stmt->execute();
      $result = $stmt->get_result();
      $stmt->close();
    }
    ?>

    <?php if (!empty($searchNpub) && $resultCount > 0): ?>
    <!-- Bulk Actions for npub view -->
    <div class="bg-[#1a1433] border border-purple-500/30 rounded-lg p-4 mb-6">
      <h3 class="text-lg font-semibold text-purple-300 mb-3">Bulk Actions for This User</h3>
      <div class="flex flex-wrap gap-3">
        <button type="button" id="markAllAdultBtn" class="bulk-action-btn px-6 py-2 bg-yellow-600 hover:bg-yellow-700 text-white rounded-md font-semibold transition-colors shadow-lg flex items-center gap-2">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
          </svg>
          Mark All as Adult
        </button>
        
        <?php if ($perm->isAdmin()): ?>
        <button type="button" id="rejectAllBanBtn" data-npub="<?= htmlspecialchars($searchNpub) ?>" class="bulk-action-btn px-6 py-2 bg-red-700 hover:bg-red-800 text-white rounded-md font-semibold transition-colors shadow-lg flex items-center gap-2">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path>
          </svg>
          Reject All &amp; Ban User
        </button>
        <?php endif; ?>
      </div>
      <p class="text-xs text-gray-500 mt-2">
        ⚠️ These actions will be processed one by one with progress tracking. Use with caution.
      </p>
    </div>
    <?php endif; ?>

    <!-- Media Grid -->
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8 2xl:grid-cols-10 gap-4 mb-8">
      <?php 
      $itemCount = 0;
      while ($row = $result->fetch_assoc()): 
        $itemCount++;
      ?>
        <?php
        $filename = $row['filename'];
        $file_type = $row['type'];
        $usernpub = $row['usernpub'];
        $approval_status = $row['approval_status'];

        // Determine status badge color and text
        $statusBadgeClass = 'bg-blue-500 text-white';
        $statusText = 'Pending';

        if ($approval_status === 'approved') {
          $statusBadgeClass = 'bg-green-500 text-white';
          $statusText = 'Approved';
        } elseif ($approval_status === 'adult') {
          $statusBadgeClass = 'bg-yellow-500 text-gray-900';
          $statusText = 'Adult';
        } elseif ($approval_status === 'rejected') {
          $statusBadgeClass = 'bg-red-600 text-white';
          $statusText = 'Rejected';
        } elseif ($approval_status === 'csam') {
          $statusBadgeClass = 'bg-red-800 text-white';
          $statusText = 'CSAM';
        }

        if ($file_type === 'picture') {
          $mediaUrl = SiteConfig::getFullyQualifiedUrl('image') . $filename;
          $thumb = SiteConfig::getThumbnailUrl('image') . $filename;
          $media_type = 'image';
        } elseif ($file_type === 'profile') {
          $mediaUrl = SiteConfig::getFullyQualifiedUrl('profile_picture') . $filename;
          $thumb = SiteConfig::getThumbnailUrl('profile_picture') . $filename;
          $media_type = 'image';
        } elseif ($file_type === 'video') {
          $mediaUrl = SiteConfig::getFullyQualifiedUrl('video') . $filename;
          $thumb = SiteConfig::getThumbnailUrl('video') . $filename;
          $media_type = 'video';
        } else {
          // Skip unknown file types to avoid broken media cards
          continue;
        }
        ?>

        <div class="media-item bg-[#1a1433] border border-purple-500/30 rounded-lg overflow-hidden hover:border-purple-500 transition-all duration-300"
             data-id="<?= $row['id'] ?>"
             data-status="<?= htmlspecialchars($approval_status) ?>">
          <div class="media-card">
            <!-- Media Preview -->
              <div class="media-preview w-full bg-gray-900/50 relative overflow-hidden cursor-pointer min-h-[150px] min-w-[150px] pb-[100%]"
                data-media-url="<?= htmlspecialchars($mediaUrl) ?>"
                data-media-type="<?= $media_type ?>">
              <!-- Loading Spinner -->
              <div class="media-loading absolute inset-0 flex items-center justify-center bg-[#110a1f]/90 z-10">
                <div class="w-10 h-10 border-4 border-purple-500/30 border-t-purple-500 rounded-full animate-spin"></div>
              </div>

              <?php if ($media_type === 'image'): ?>
                 <img src="<?= htmlspecialchars($thumb, ENT_QUOTES) ?>"
                   alt="<?= htmlspecialchars($filename, ENT_QUOTES) ?>"
                   class="absolute inset-0 w-full h-full object-cover transform transition-transform duration-200 ease-in-out will-change-transform cursor-zoom-in media-zoom-img"
                   loading="lazy"
                   onload="this.parentElement.classList.add('media-loaded')"
                   onerror="this.parentElement.classList.add('media-loaded')">
              <?php elseif ($media_type === 'video'): ?>
                <video class="absolute inset-0 w-full h-full object-cover"
                       preload="metadata"
                       muted
                       onloadeddata="this.currentTime = 0.1; this.parentElement.classList.add('media-loaded');"
                       onerror="this.parentElement.classList.add('media-loaded');">
                  <source src="<?= htmlspecialchars($thumb, ENT_QUOTES) ?>" type="video/mp4"
                          onerror="this.parentElement.parentElement.classList.add('media-loaded');">
                </video>
                <div class="absolute inset-0 flex items-center justify-center bg-black bg-opacity-30 pointer-events-none media-play-overlay transition-opacity duration-150 opacity-100">
                  <svg class="w-12 h-12 text-white opacity-80" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M6.3 2.841A1.5 1.5 0 004 4.11V15.89a1.5 1.5 0 002.3 1.269l9.344-5.89a1.5 1.5 0 000-2.538L6.3 2.84z"/>
                  </svg>
                </div>
              <?php endif; ?>

              <!-- Type Badge -->
              <span class="absolute top-2 left-2 px-2 py-1 text-xs font-semibold rounded-full bg-purple-600 text-white z-20">
                <?= htmlspecialchars($file_type) ?>
              </span>

              <!-- Status Badge -->
              <span class="status-badge absolute top-2 right-2 px-2 py-1 text-xs font-semibold rounded-full <?= $statusBadgeClass ?> z-20">
                <?= htmlspecialchars($statusText) ?>
              </span>
            </div>

            <!-- User Info -->
            <div class="px-3 py-2 bg-[#0f0a1f]">
              <a href="?npub=<?= urlencode($usernpub) ?>" class="block text-xs text-gray-400 hover:text-purple-300 transition-colors truncate" title="Click to view all media from <?= htmlspecialchars($usernpub) ?>">
                <?= htmlspecialchars(substr($usernpub, 0, 12)) ?>...
              </a>
            </div>

            <!-- Action Buttons -->
            <div class="p-2 space-y-1">
              <input type="hidden" name="id" value="<?= $row['id'] ?>">

              <button type="button" value="approved" class="status-btn w-full px-2 py-1.5 bg-green-600 hover:bg-green-700 text-white text-xs font-semibold rounded transition-colors">
                Approve
              </button>

              <button type="button" value="adult" class="status-btn w-full px-2 py-1.5 bg-yellow-600 hover:bg-yellow-700 text-white text-xs font-semibold rounded transition-colors">
                Adult
              </button>

              <button type="button" value="rejected" class="status-btn w-full px-2 py-1.5 bg-red-600 hover:bg-red-700 text-white text-xs font-semibold rounded transition-colors">
                Reject
              </button>

              <?php if ($perm->isAdmin()): ?>
                <button type="button" value="csam" class="status-btn w-full px-2 py-1.5 bg-orange-600 hover:bg-orange-700 text-white text-xs font-semibold rounded transition-colors">
                  CSAM
                </button>

                <button type="button" value="ban" class="status-btn w-full px-2 py-1.5 bg-red-800 hover:bg-red-900 text-white text-xs font-semibold rounded transition-colors">
                  Ban User
                </button>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endwhile; ?>
      <?php $result->close(); ?>
    </div>

    <?php if ($itemCount === 0): ?>
      <!-- Empty State -->
      <div class="text-center py-16">
        <svg class="w-24 h-24 mx-auto text-purple-500/30 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <h3 class="text-xl font-semibold text-purple-300 mb-2">
          <?php if ($isSearchMode): ?>
            No results found
          <?php else: ?>
            All caught up!
          <?php endif; ?>
        </h3>
        <p class="text-gray-400">
          <?php if ($isSearchMode): ?>
            Try a different search term or <a href="?" class="text-purple-400 hover:underline">go back to the queue</a>.
          <?php else: ?>
            There are no pending media items to review.
          <?php endif; ?>
        </p>
      </div>
    <?php endif; ?>

    <?php
    // Only show pagination when not searching
    if (!$isSearchMode && $itemCount > 0) {
      // Pagination
      $sql = "SELECT COUNT(*) as total FROM uploads_data WHERE approval_status = 'pending'";
      $stmt = $link->prepare($sql);
      $stmt->execute();
      $countResult = $stmt->get_result();
      $total = $countResult->fetch_assoc()['total'];
      $countResult->close();
      $stmt->close();
      $pages = $perpage > 0 ? ceil($total / $perpage) : 0;
    ?>

    <!-- Pending Count -->
    <div class="text-center mb-4">
      <p class="text-gray-400">
        <span class="text-purple-300 font-semibold"><?= number_format($total) ?></span> pending items
        <?php if ($pages > 1): ?>
          &middot; Page <?= ($page + 1) ?> of <?= $pages ?>
        <?php endif; ?>
      </p>
    </div>

    <!-- Pagination -->
    <?php if ($pages > 1): ?>
      <nav class="flex justify-center mb-6" aria-label="Pagination">
        <ul class="flex flex-wrap gap-2 items-center">
          <?php
          // Smart pagination: show first, last, current, and nearby pages
          $showPages = [];
          $showPages[] = 0; // First page
          $showPages[] = $pages - 1; // Last page
          
          // Pages around current
          for ($i = max(0, $page - 2); $i <= min($pages - 1, $page + 2); $i++) {
            $showPages[] = $i;
          }
          
          $showPages = array_unique($showPages);
          sort($showPages);
          
          $prevShown = -2;
          foreach ($showPages as $i):
            // Add ellipsis if there's a gap
            if ($i > $prevShown + 1): ?>
              <li class="text-gray-500 px-2">...</li>
            <?php endif;
            $prevShown = $i;
            $active = ($i == $page);
          ?>
            <li>
              <a href="?p=<?= $i ?>"
                 class="px-4 py-2 rounded-md font-semibold transition-colors <?= $active ? 'bg-purple-600 text-white' : 'bg-[#1a1433] text-purple-300 hover:bg-purple-600/30 border border-purple-500/30' ?>">
                <?= ($i + 1) ?>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      </nav>
    <?php endif; ?>
    <?php } elseif ($isSearchMode && $itemCount > 0) { ?>
      <!-- Search Results Count -->
      <div class="text-center mb-6">
        <p class="text-gray-400">
          Found <span class="text-purple-300 font-semibold"><?= $resultCount ?></span> result<?= $resultCount !== 1 ? 's' : '' ?>
          <?php if ($resultCount >= $searchLimit): ?>
            <span class="text-yellow-400">(showing first <?= $searchLimit ?>)</span>
          <?php endif; ?>
        </p>
      </div>
    <?php } ?>

    <?php if ($itemCount > 0): ?>
    <!-- Approve All Button (Bottom) -->
    <div class="text-center mb-8">
      <button type="button" class="approve-page-button px-8 py-3 bg-purple-600 hover:bg-purple-700 text-white rounded-md font-semibold transition-colors shadow-lg">
        Approve Current Page
      </button>
    </div>
    <?php endif; ?>
  </main>

  <?php $link->close(); ?>
</body>

</html>
