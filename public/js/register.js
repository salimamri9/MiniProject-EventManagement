const form = document.getElementById('register-form');
const statusDiv = document.getElementById('status-message');

function showMessage(message, type = 'success') {
    statusDiv.innerHTML = `<div class="alert alert-${type}">${message}</div>`;
}

// Check WebAuthn support
(async () => {
    if (!isWebAuthnSupported()) {
        showMessage('Your browser does not support passkeys. Please use Chrome, Edge, Firefox, or Safari.', 'error');
        form.querySelector('button[type="submit"]').disabled = true;
        return;
    }
    
    const hasPlatformAuth = await isPlatformAuthenticatorAvailable();
    console.log('Platform authenticator available:', hasPlatformAuth);
    
    if (!hasPlatformAuth) {
        console.warn('No platform authenticator (like Windows Hello or Touch ID) detected. You may need a security key.');
    }
})();

form.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const username = document.getElementById('username').value.trim();
    const email = document.getElementById('email').value.trim();
    const button = form.querySelector('button[type="submit"]');
    
    if (!username || !email) {
        showMessage('Please fill in all fields', 'error');
        return;
    }
    
    button.disabled = true;
    button.textContent = 'Creating...';
    
    try {
        showMessage('Setting up your account...', 'info');
        console.log('Step 1: Requesting registration options...');
        
        // Step 1: Get registration options from server
        const optionsResponse = await fetch('/api/auth/register/options', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ username, email })
        });
        
        const optionsData = await optionsResponse.json();
        console.log('Server response:', optionsData);
        
        if (!optionsResponse.ok) {
            throw new Error(optionsData.error || 'Failed to start registration');
        }
        
        const { options, user_id } = optionsData;
        
        showMessage('Creating your passkey... Follow the prompt from your browser.', 'info');
        console.log('Step 2: Creating credential with WebAuthn API...');
        console.log('Options being sent to navigator.credentials.create:', options);
        
        // Step 2: Create passkey with browser WebAuthn API
        let credential;
        try {
            credential = await createCredential(options);
            console.log('Credential created successfully:', credential);
        } catch (webauthnError) {
            console.error('WebAuthn error details:', webauthnError);
            console.error('Error name:', webauthnError.name);
            console.error('Error message:', webauthnError.message);
            
            if (webauthnError.name === 'NotAllowedError') {
                throw new Error('You cancelled the passkey creation or it timed out. Please try again.');
            } else if (webauthnError.name === 'NotSupportedError') {
                throw new Error('Your device does not support the required authentication method.');
            } else if (webauthnError.name === 'InvalidStateError') {
                throw new Error('A passkey already exists for this account on this device.');
            } else if (webauthnError.name === 'SecurityError') {
                throw new Error('Security error: Make sure you\'re on localhost or HTTPS.');
            } else if (webauthnError.name === 'AbortError') {
                throw new Error('The operation was aborted. Please try again.');
            } else {
                throw new Error('Passkey creation failed: ' + webauthnError.message);
            }
        }
        
        showMessage('Saving your passkey...', 'info');
        console.log('Step 3: Verifying credential with server...');
        
        // Step 3: Send credential to server for verification
        const verifyResponse = await fetch('/api/auth/register/verify', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({
                user_id,
                credential
            })
        });
        
        const verifyData = await verifyResponse.json();
        console.log('Verify response:', verifyData);
        
        if (!verifyResponse.ok) {
            throw new Error(verifyData.error || 'Failed to save passkey');
        }
        
        showMessage('Account created successfully! Redirecting...', 'success');
        console.log('Registration complete!');
        
        // Redirect
        setTimeout(() => {
            window.location.href = verifyData.redirect || '/';
        }, 1000);
        
    } catch (error) {
        console.error('Registration error:', error);
        button.disabled = false;
        button.textContent = 'Create Account';
        showMessage('Error: ' + error.message, 'error');
    }
});
