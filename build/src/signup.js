import { nip98 } from 'nostr-tools'

(function (global) {
  async function verifyWithNip07(verifyApiURL, triggerButton) {
    const triggerButtonValue = triggerButton.innerHTML;
    if (!window.nostr) {
      triggerButton.innerHTML = 'Nostr Extension not installed';
      return;
    }
    triggerButton.innerHTML = 'Signing the request...';
    triggerButton.disabled = true;
    const authHeader = await nip98.getToken(verifyApiURL, 'POST', async (e) => window.nostr.signEvent(e), true);
    triggerButton.innerHTML = 'Verifying...'
    const response = await fetch(verifyApiURL, {
      method: 'POST',
      headers: {
        'Authorization': authHeader,
      },
    });
    // Validate the response and returned JSON
    if (response.ok) {
      // Get JSON response and verify success
      const json = await response.json();
      if (json.status === 'success') {
        triggerButton.innerHTML = 'NPUB verified, reloading...'
        // GET the page again to refresh the page
        window.location.href = window.location.href;
      } else {
        triggerButton.innerHTML = triggerButtonValue;
        alert(json.message);
      }
    } else {
      triggerButton.innerHTML = triggerButtonValue;
      alert('Error verifying npub, try again.');
    }
  }

  // Assuming `global.dmTimerInterval` is already declared somewhere globally
  if (typeof global === 'undefined') {
    global = {}; // Fallback in case `global` is not defined
  }

  async function verifyWithDM(verifyApiURL, triggerButton, dmCodeInput, npubInput) {
    const dmCodeField = dmCodeInput.querySelector('input');
    const dmCodeFieldLabel = dmCodeInput.querySelector('label');
    // Store the original label value if it's not already stored
    if (!global.dmCodeFieldLabelOriginalValue) {
      global.dmCodeFieldLabelOriginalValue = dmCodeFieldLabel.innerHTML;
    }
    if (!global.triggerButtonValue) {
      global.triggerButtonValue = triggerButton.innerHTML;
    }

    // Check if npubInput is valid
    const npub = npubInput.value.trim();
    if (!npub || npub.length !== 63) {
      alert('Please enter a valid NPUB');
      return;
    }
    // Determine if dmCodeInput is visible or not
    const dmCodeInputVisible = dmCodeInput.style.display !== 'none';

    if (dmCodeInputVisible) {
      const dmCode = dmCodeField.value.trim();
      if (!dmCode || dmCode.length !== 6) {
        alert('Please enter a valid verification code');
        return;
      }
      // Submit the POST request to the verfy url with dmCode as a form data field "dmCode"
      triggerButton.innerHTML = 'Verifying...';
      dmCodeField.disabled = true; // Disable the field
      const response = await fetch(verifyApiURL, {
        method: 'POST', // Specifies the request method
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
          'npub': npub,
          'dm_code': dmCode,
        }),
      });
      if (!response.ok) {
        //triggerButton.innerHTML = global.triggerButtonValue;
        alert('Error verifying npub, try again.');
        return;
      }
      // Remove timer text from dmCodeFieldLabel and clear timer interval
      clearInterval(global.dmTimerInterval);
      // Restore the original label value
      dmCodeFieldLabel.innerHTML = global.dmCodeFieldLabelOriginalValue;
      dmCodeField.disabled = false; // Re-enable the field if it was disabled
      dmCodeInput.style.display = 'none';
      triggerButton.innerHTML = 'Verification Complete, reloading...';
      window.location.href = window.location.href;
    } else {
      // Submit the POST request to the verfy url with npub as a form data field "npub"
      triggerButton.innerHTML = 'Sending direct message...';
      const response = await fetch(verifyApiURL, {
        method: 'POST', // Specifies the request method
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
          'npub': npub,
        }),
      });
      if (!response.ok) {
        triggerButton.innerHTML = global.triggerButtonValue;
        alert('Error verifying npub, try again.');
        return;
      }

      // Display countdown timer in the dmCodeFieldLabel element for 11 minutes
      let timer = 11 * 60;
      dmCodeFieldLabel.innerHTML = `${global.dmCodeFieldLabelOriginalValue} (11:00)`;
      const timerInterval = setInterval(() => {
        timer -= 1;
        const minutes = Math.floor(timer / 60);
        const seconds = timer % 60;
        dmCodeFieldLabel.innerHTML = `${global.dmCodeFieldLabelOriginalValue} (${minutes}:${seconds < 10 ? '0' : ''}${seconds})`;
        if (timer === 0) {
          clearInterval(timerInterval);
          dmCodeFieldLabel.innerHTML = `${global.dmCodeFieldLabelOriginalValue} (expired)`;
          dmCodeField.disabled = true;
          window.location.href = window.location.href;
        }
      }, 1000);
      global.dmTimerInterval = timerInterval;
      // Show the DM code input
      dmCodeInput.style.display = 'block';
      dmCodeField.focus();
      // Change the button text
      triggerButton.innerHTML = 'Submit Verification Code';
      return;
    }
  }


  // Expose to global scope
  global.verifyWithNip07 = verifyWithNip07;
  global.verifyWithDM = verifyWithDM;
})(window);