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

  // Expose to global scope
  global.loginWithNip07 = loginWithNip07;
})(window);