/**
 * convert RFC 1342-like base64 strings to array buffer
 * @param {mixed} obj
 * @returns {undefined}
 */
function recursiveBase64StrToArrayBuffer(obj) {
    let prefix = '=?BINARY?B?';
    let suffix = '?=';
    if (typeof obj === 'object') {
        for (let key in obj) {
            if (typeof obj[key] === 'string') {
                let str = obj[key];
                if (str.substring(0, prefix.length) === prefix && str.substring(str.length - suffix.length) === suffix) {
                    str = str.substring(prefix.length, str.length - suffix.length);

                    let binary_string = window.atob(str);
                    let len = binary_string.length;
                    let bytes = new Uint8Array(len);
                    for (let i = 0; i < len; i++) {
                        bytes[i] = binary_string.charCodeAt(i);
                    }
                    obj[key] = bytes.buffer;
                }
            } else {
                recursiveBase64StrToArrayBuffer(obj[key]);
            }
        }
    }
}

/**
 * Convert a ArrayBuffer to Base64
 * @param {ArrayBuffer} buffer
 * @returns {String}
 */
function arrayBufferToBase64(buffer) {
    let binary = '';
    let bytes = new Uint8Array(buffer);
    let len = bytes.byteLength;
    for (let i = 0; i < len; i++) {
        binary += String.fromCharCode(bytes[i]);
    }
    return window.btoa(binary);
}

function submitWebAuthnChallenge(challenge, passkeyName) {
    var form = document.createElement('form');
    var challengeInput = document.createElement('input');
    challengeInput.type = 'hidden';
    challengeInput.name = 'challenge';
    challengeInput.value = JSON.stringify(challenge);
    form.appendChild(challengeInput);

    if (passkeyName) {
        form.appendChild(passkeyName.cloneNode(true));
    }

    form.method = 'post';
    form.action = window.location.href;
    document.body.appendChild(form);
    form.submit();
}

document.addEventListener('DOMContentLoaded', () => {
    const addButton = document.getElementById('webauthn-add');
    const inputButton = document.getElementById('webauthnInput');
    const credentialInput = document.getElementById('webauthnCredential');

    if (addButton && credentialInput) {
        addButton.addEventListener('click', async () => {
            try {
                const passkeyName = document.querySelector('input[name=passkeyName]');
                if (!passkeyName || passkeyName.value.length < 1) {
                    alert('Please enter the PassKey name.');
                    return false;
                }

                const options = JSON.parse(credentialInput.value);
                recursiveBase64StrToArrayBuffer(options);

                const credential = await navigator.credentials.create(options);
                submitWebAuthnChallenge({
                    authenticatorAttachment: credential.authenticatorAttachment,
                    id: credential.id.replace(/-/g, '+').replace(/_/g, '/'),
                    type: credential.type,
                    rawId: credential.rawId ? arrayBufferToBase64(credential.rawId) : null,
                    response: {
                        attestationObject: credential.response.attestationObject ? arrayBufferToBase64(credential.response.attestationObject) : null,
                        clientDataJSON: credential.response.clientDataJSON ? arrayBufferToBase64(credential.response.clientDataJSON) : null,
                        getTransports: credential.response.getTransports ? credential.response.getTransports() : null
                    }
                }, passkeyName);
            } catch (err) {
                if (err.name === 'InvalidStateError')
                    window.alert('Communication error\nThe authenticator was previously registered');
                else
                    window.alert('Communication error\n' + (err.message || 'unknown error occurred'));
            }
        });
    }

    if (inputButton && credentialInput) {
        inputButton.addEventListener('click', async () => {
            try {
                const options = JSON.parse(credentialInput.value);
                recursiveBase64StrToArrayBuffer(options);

                const credential = await navigator.credentials.get(options);
                submitWebAuthnChallenge({
                    authenticatorAttachment: credential.authenticatorAttachment,
                    id: credential.id.replace(/-/g, '+').replace(/_/g, '/'),
                    type: credential.type,
                    rawId: credential.rawId ? arrayBufferToBase64(credential.rawId) : null,
                    response: {
                        attestationObject: credential.response.attestationObject ? arrayBufferToBase64(credential.response.attestationObject) : null,
                        clientDataJSON: credential.response.clientDataJSON ? arrayBufferToBase64(credential.response.clientDataJSON) : null,
                        authenticatorData: credential.response.authenticatorData ? arrayBufferToBase64(credential.response.authenticatorData) : null,
                        signature: credential.response.signature ? arrayBufferToBase64(credential.response.signature) : null,
                        userHandle: credential.response.userHandle ? arrayBufferToBase64(credential.response.userHandle) : null
                    }
                });
            } catch (err) {
                window.alert('Communication error\n' + (err.message || 'unknown error occurred'));
            }
        });

        setTimeout(() => inputButton.click(), 1000);
    }
});
