import { nip98 } from 'nostr-tools'

(function (global) {
  async function loginWithNip07(loginApiURL, postLoginRedirectURL, triggerButton, enableNostrLoginCheckbox) {
    const triggerButtonValue = triggerButton.value;
    if (!window.nostr) {
      triggerButton.value = 'Nostr Extension not installed';
      return;
    }
    triggerButton.value = 'Logging in...';
    const authHeader = await nip98.getToken(loginApiURL, 'POST', async (e) => window.nostr.signEvent(e), true);
    const response = await fetch(loginApiURL, {
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
        triggerButton.value = 'Success! Redirecting...'
        window.location.href = postLoginRedirectURL;
      } else {
        triggerButton.value = triggerButtonValue;
        alert(json.message);
      }
    } else if (response.status === 403) {
      triggerButton.value = 'Nostr login not enabled';
      triggerButton.disabled = true;
      enableNostrLoginCheckbox.style.display = 'block';
      enableNostrLoginCheckbox.querySelector('input[type="checkbox"]').disabled = false;
      enableNostrLoginCheckbox.querySelector('input[type="checkbox"]').checked = true;
    } else if (response.status === 404) {
      triggerButton.value = 'Redirecting to signup...';
      window.location.href = window.location.origin + '/signup/new';
    } else {
      triggerButton.value = triggerButtonValue;
      alert('Error logging in');
    }
  }

  async function loginWithDM(verifyApiURL, triggerButton, dmCodeInput, npubInput, passwordInput, enableNostrLoginCheckbox, loginButton) {
    if (!global.triggerButtonValue) {
      global.triggerButtonValue = triggerButton.value;
    }

    if (!global.triggerButtonValue) {
      global.triggerButtonValue = triggerButton.value;
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
      // The DM has been already sent, and we are verifying the code
      const dmCode = dmCodeInput.value.trim();
      if (!dmCode) {
        alert('Please enter a valid verification code');
        return;
      }
      // Submit the POST request to the verfy url with dmCode as a form data field "dmCode"
      triggerButton.innerHTML = 'Verifying...';
      dmCodeInput.disabled = true; // Disable the field
      let response
      try {
        response = await fetch(verifyApiURL, {
          method: 'POST', // Specifies the request method
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: new URLSearchParams({
            'npub': npub,
            'dm_code': dmCode.trim(),
          }),
        });
      } catch (e) {
        console.log(e);
      }
      if (response.ok) {
        clearInterval(global.dmTimerInterval);
        // Restore the original label value
        dmCodeInput.style.display = 'none';
        triggerButton.value = 'DM Code Verified, logging in...';
        window.location.href = window.location.href; // This should redirect to the account page
      } else {
        const status = response.status;
        switch (status) {
          case 403:
            clearInterval(global.dmTimerInterval);
            triggerButton.value = 'Nostr login not enabled';
            triggerButton.disabled = true;
            triggerButton.style.background = 'rgba(61, 53, 92, 0.3)';
            triggerButton.style.color = '#d0bed8';
            // Show the enable nostr login checkbox
            enableNostrLoginCheckbox.style.display = 'block';
            enableNostrLoginCheckbox.querySelector('input[type="checkbox"]').disabled = false;
            enableNostrLoginCheckbox.querySelector('input[type="checkbox"]').checked = true;
            // Show password input
            passwordInput.disabled = false;
            passwordInput.style.display = 'block';
            // Show login button
            loginButton.disabled = false;
            loginButton.style.display = 'block';
            // Hide dmCodeInput
            dmCodeInput.style.display = 'none';
            dmCodeInput.disabled = true;
            break;
          case 404:
            // No account found with the given npub, redirect to signup
            triggerButton.value = 'Redirecting to signup...';
            window.location.href = window.location.origin + '/signup/new';
            break;
          case 401:
            // Invalid dmCode, show error and reset the button
            alert('Invalid verification code, try again.');
            dmCodeInput.disabled = false;
            return;
          default:
            const json = await response.json();
            const message = json.message;
            triggerButton.value = global.triggerButtonValue;
            // Restore the login button and password input
            // Show password input
            passwordInput.disabled = false;
            passwordInput.style.display = 'block';
            // Show login button
            loginButton.disabled = false;
            loginButton.style.display = 'block';
            // Hide dmCodeInput
            dmCodeInput.style.display = 'none';
            dmCodeInput.disabled = true;
            alert('Error verifying npub, try again.' + (message ? '\n' + message : ''));
            return;
        }
      }
    } else {
      // Submit the POST request to the verfy url with npub as a form data field "npub"
      // Hide password input
      passwordInput.disabled = true;
      passwordInput.style.display = 'none';
      // Hide login button
      loginButton.disabled = true;
      loginButton.style.display = 'none';
      // Change the button text to indicate that the DM is being sent
      triggerButton.value = 'Sending direct message...';
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
        triggerButton.value = global.triggerButtonValue;
        // Show password input
        passwordInput.disabled = false;
        passwordInput.style.display = 'block';
        // Hide dmCodeInput
        dmCodeInput.style.display = 'none';
        alert('Error verifying npub, try again.');
        return;
      }

      let timer = 5 * 60; // 5 minutes
      triggerButton.value = 'Submit Verification Code (5:00)';
      global.triggerButtonValue = 'Submit Verification Code';
      const timerInterval = setInterval(() => {
        timer -= 1;
        const minutes = Math.floor(timer / 60);
        const seconds = timer % 60;
        triggerButton.value = `${global.triggerButtonValue} (${minutes}:${seconds < 10 ? '0' : ''}${seconds})`;
        if (timer === 0) {
          clearInterval(timerInterval);
          triggerButton.value = `${global.triggerButtonValue} (expired)`;
          dmCodeInput.disabled = true;
          window.location.href = window.location.href;
        }
      }, 1000);
      global.dmTimerInterval = timerInterval;
      // Show the DM code input
      dmCodeInput.style.display = 'block';
      dmCodeInput.focus();
      // Change the button text
      return;
    }
  }

  // Expose to global scope
  global.loginWithNip07 = loginWithNip07;
  global.loginWithDM = loginWithDM;
})(window);