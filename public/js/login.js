const form = document.getElementById('login-form');
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
})();

form.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const email = document.getElementById('email').value.trim();
    const button = form.querySelector('button[type="submit"]');
    
    if (!email) {
        showMessage('Please enter your email', 'error');
        return;
    }
    
    button.disabled = true;
    button.textContent = 'Signing in...';
    
    try {
        showMessage('Looking up your account...', 'info');
        console.log('Step 1: Requesting login options...');
        
        // Step 1: Get login options from server
        const optionsResponse = await fetch('/api/auth/login/options', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ email })
        });
        
        const options = await optionsResponse.json();
        console.log('Server response:', options);
        
        if (!optionsResponse.ok) {
            throw new Error(options.error || 'Failed to get login options');
        }
        
        // Check if user has any credentials
        if (!options.allowCredentials || options.allowCredentials.length === 0) {
            throw new Error('No passkey found for this email. Please register first.');
        }
        
        showMessage('Authenticating... Follow the prompt from your browser.', 'info');
        console.log('Step 2: Getting credential with WebAuthn API...');
        
        // Step 2: Get credential from passkey
        let credential;
        try {
            credential = await getCredential(options);
            console.log('Credential retrieved:', credential);
        } catch (webauthnError) {
            console.error('WebAuthn error:', webauthnError);
            
            if (webauthnError.name === 'NotAllowedError') {
                throw new Error('Authentication was cancelled or timed out. Please try again.');
            } else if (webauthnError.name === 'SecurityError') {
                throw new Error('Security error: Make sure you\'re on localhost or HTTPS.');
            } else {
                throw new Error('Authentication failed: ' + webauthnError.message);
            }
        }
        
        showMessage('Verifying...', 'info');
        console.log('Step 3: Verifying credential with server...');
        
        // Step 3: Send credential to server for verification
        const verifyResponse = await fetch('/api/auth/login/verify', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({
                email,
                credential
            })
        });
        
        const result = await verifyResponse.json();
        console.log('Verify response:', result);
        
        if (!verifyResponse.ok) {
            throw new Error(result.error || 'Sign in failed');
        }
        
        showMessage('Welcome back! Redirecting...', 'success');
        console.log('Login complete!');
        
        // Redirect
        setTimeout(() => {
            window.location.href = result.redirect || '/';
        }, 1000);
        
    } catch (error) {
        console.error('Login error:', error);
        button.disabled = false;
        button.textContent = 'Continue with Passkey';
        showMessage('Error: ' + error.message, 'error');
    }
});
