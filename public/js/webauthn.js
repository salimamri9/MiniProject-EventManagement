// WebAuthn utilities for passkey authentication

function base64urlToBuffer(base64url) {
    const base64 = base64url.replace(/-/g, '+').replace(/_/g, '/');
    const padLen = (4 - (base64.length % 4)) % 4;
    const padded = base64 + '='.repeat(padLen);
    const binary = atob(padded);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) {
        bytes[i] = binary.charCodeAt(i);
    }
    return bytes.buffer;
}

function bufferToBase64url(buffer) {
    const bytes = new Uint8Array(buffer);
    let binary = '';
    for (let i = 0; i < bytes.length; i++) {
        binary += String.fromCharCode(bytes[i]);
    }
    const base64 = btoa(binary);
    return base64.replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
}

export async function createCredential(options) {
    console.log('createCredential called with options:', JSON.stringify(options, null, 2));
    
    // Convert challenge and user ID from base64url to ArrayBuffer
    options.challenge = base64urlToBuffer(options.challenge);
    options.user.id = base64urlToBuffer(options.user.id);
    
    // Convert excludeCredentials if present
    if (options.excludeCredentials && options.excludeCredentials.length > 0) {
        options.excludeCredentials = options.excludeCredentials.map(cred => ({
            ...cred,
            id: base64urlToBuffer(cred.id)
        }));
    }
    
    console.log('Calling navigator.credentials.create...');
    
    // Create credential
    const credential = await navigator.credentials.create({
        publicKey: options
    });
    
    console.log('Credential created:', credential);
    
    // Convert response to base64url for sending to server
    const result = {
        id: credential.id,
        rawId: bufferToBase64url(credential.rawId),
        type: credential.type,
        response: {
            clientDataJSON: bufferToBase64url(credential.response.clientDataJSON),
            attestationObject: bufferToBase64url(credential.response.attestationObject)
        }
    };
    
    console.log('Returning credential:', result);
    return result;
}

export async function getCredential(options) {
    console.log('getCredential called with options:', JSON.stringify(options, null, 2));
    
    // Convert challenge from base64url to ArrayBuffer
    options.challenge = base64urlToBuffer(options.challenge);
    
    // Convert allowCredentials if present
    if (options.allowCredentials && options.allowCredentials.length > 0) {
        options.allowCredentials = options.allowCredentials.map(cred => ({
            ...cred,
            id: base64urlToBuffer(cred.id)
        }));
    }
    
    console.log('Calling navigator.credentials.get...');
    
    // Get credential
    const credential = await navigator.credentials.get({
        publicKey: options
    });
    
    console.log('Credential retrieved:', credential);
    
    // Convert response to base64url
    const result = {
        id: credential.id,
        rawId: bufferToBase64url(credential.rawId),
        type: credential.type,
        response: {
            clientDataJSON: bufferToBase64url(credential.response.clientDataJSON),
            authenticatorData: bufferToBase64url(credential.response.authenticatorData),
            signature: bufferToBase64url(credential.response.signature),
            userHandle: credential.response.userHandle ? bufferToBase64url(credential.response.userHandle) : null
        }
    };
    
    console.log('Returning credential:', result);
    return result;
}

export function isWebAuthnSupported() {
    return window.PublicKeyCredential !== undefined;
}

// Check if platform authenticator is available
export async function isPlatformAuthenticatorAvailable() {
    if (!isWebAuthnSupported()) return false;
    try {
        return await PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable();
    } catch (e) {
        return false;
    }
}
