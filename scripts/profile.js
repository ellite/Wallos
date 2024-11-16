document.addEventListener('DOMContentLoaded', function () {

    document.getElementById("userForm").addEventListener("submit", function (event) {
        event.preventDefault();
        document.getElementById("userSubmit").disabled = true;
        const formData = new FormData(event.target);
        fetch("endpoints/user/save_user.php", {
            method: "POST",
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById("avatar").src = document.getElementById("avatarImg").src;
                    var newUsername = document.getElementById("username").value;
                    document.getElementById("user").textContent = newUsername;
                    showSuccessMessage(data.message);
                    if (data.reload) {
                        location.reload();
                    }
                } else {
                    showErrorMessage(data.errorMessage);
                }
                document.getElementById("userSubmit").disabled = false;
            })
            .catch(error => {
                showErrorMessage(translate('unknown_error'));
            });
    });

});

function toggleAvatarSelect() {
    var avatarSelect = document.getElementById("avatarSelect");
    if (avatarSelect.classList.contains("is-open")) {
        avatarSelect.classList.remove("is-open");
    } else {
        avatarSelect.classList.add("is-open");
    }
}

function closeAvatarSelect() {
    var avatarSelect = document.getElementById("avatarSelect");
    avatarSelect.classList.remove("is-open");
}

document.querySelectorAll('.avatar-option').forEach((avatar) => {
    avatar.addEventListener("click", () => {
        changeAvatar(avatar.src);
        document.getElementById('avatarUser').value = avatar.getAttribute('data-src');
        closeAvatarSelect();
    })
});

function changeAvatar(src) {
    document.getElementById("avatarImg").src = src;
}

function successfulUpload(field, msg) {
    var reader = new FileReader();

    if (field.files.length === 0) {
        return;
    }

    if (!['image/jpeg', 'image/png', 'image/gif', 'image/jtif', 'image/webp'].includes(field.files[0]['type'])) {
        showErrorMessage(msg);
        return;
    }

    reader.onload = function () {
        changeAvatar(reader.result);
    };

    reader.readAsDataURL(field.files[0]);
    closeAvatarSelect();
}

function deleteAvatar(path) {
    fetch('/endpoints/user/delete_avatar.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ avatar: path }),
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                var avatarContainer = document.querySelector(`.avatar-container[data-src="${path}"]`);
                if (avatarContainer) {
                    avatarContainer.remove();
                }
                showSuccessMessage();
            } else {
                showErrorMessage();
            }
        })
        .catch((error) => {
            console.error('Error:', error);
        });
}

function enableTotp() {
    const totpSecret = document.querySelector('#totp-secret');
    const totpSecretCode = document.querySelector('#totp-secret-code');
    const qrCode = document.getElementById('totp-qr-code');
    totpSecret.value = '';
    totpSecretCode.textContent = '';
    qrCode.innerHTML = '';

    fetch('endpoints/user/enable_totp.php?generate=true', {
        method: 'GET'
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                totpSecret.value = data.secret;
                totpSecretCode.textContent = data.secret;
                new QRCode(qrCode, data.qrCodeUrl);

                openTotpPopup();
            } else {
                showErrorMessage(data.message);
            }
        })
        .catch(error => {
            showErrorMessage(error);
        });
}

function openTotpPopup() {
    const enableTotpButton = document.getElementById('enableTotp');
    enableTotpButton.disabled = true;

    const totpPopup = document.getElementById('totp-popup');
    totpPopup.classList.add('is-open');
}

function closeTotpPopup() {
    const enableTotpButton = document.getElementById('enableTotp');
    enableTotpButton.disabled = false;
    const totpPopup = document.getElementById('totp-popup');
    totpPopup.classList.remove('is-open');

    const totpBackupCodes = document.getElementById('totp-backup-codes');
    if (!totpBackupCodes.classList.contains('hide')) {
        location.reload();
    }
}

function submitTotp() {
    const totpCode = document.getElementById('totp').value;
    const totpSecret = document.getElementById('totp-secret').value;

    fetch('endpoints/user/enable_totp.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ totpCode: totpCode, totpSecret: totpSecret }),
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showSuccessMessage(data.message);
                const backupCodes = data.backupCodes;
                const backupCodesList = document.getElementById('backup-codes');
                backupCodesList.innerHTML = '';
                backupCodes.forEach(code => {
                    const li = document.createElement('li');
                    li.textContent = code;
                    backupCodesList.appendChild(li);
                });

                const totpSetup = document.getElementById('totp-setup');
                const totpBackupCodes = document.getElementById('totp-backup-codes');

                totpSetup.classList.add('hide');
                totpBackupCodes.classList.remove('hide');
            } else {
                showErrorMessage(data.message);
            }
        })
        .catch(error => {
            showErrorMessage(error);
            console.log(error);
        });
}

function copyBackupCodes() {
    const backupCodes = document.querySelectorAll('#backup-codes li');
    const codes = Array.from(backupCodes).map(code => code.textContent).join('\n');

    navigator.clipboard.writeText(codes)
        .then(() => {
            showSuccessMessage(translate('copied_to_clipboard'));
        })
        .catch(() => {
            showErrorMessage(translate('unknown_error'));
        });
}

function downloadBackupCodes() {
    const backupCodes = document.querySelectorAll('#backup-codes li');
    const codes = Array.from(backupCodes).map(code => code.textContent).join('\n');
    const element = document.createElement('a');

    element.setAttribute('href', 'data:text/plain;charset=utf-8,' + encodeURIComponent(codes));
    element.setAttribute('download', 'wallos-backup-codes.txt');
    element.style.display = 'none';
    document.body.appendChild(element);

    element.click();

    document.body.removeChild(element);
}

function closeTotpDisablePopup() {
    const totpPopup = document.getElementById('totp-disable-popup');
    totpPopup.classList.remove('is-open');
}

function disableTotp() {
    const totpPopup = document.getElementById('totp-disable-popup');
    totpPopup.classList.add('is-open');
}

function submitDisableTotp() {
    const totpCode = document.getElementById('totp-disable').value;

    fetch('endpoints/user/disable_totp.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ totpCode: totpCode }),
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showSuccessMessage(data.message);
                if (data.reload) {
                    location.reload();
                }
            } else {
                showErrorMessage(data.message);
            }
        })
        .catch(error => {
            showErrorMessage(error);
        });
}

function regenerateApiKey() {
    const regenerateButton = document.getElementById('regenerateApiKey');
    regenerateButton.disabled = true;

    fetch('endpoints/user/regenerateapikey.php', {
        method: 'POST',
    })
    .then(response => response.json())
    .then(data => {
        regenerateButton.disabled = false;
        if (data.success) {
            const newApiKey = data.apiKey;
            document.getElementById('apikey').value = newApiKey;
            showSuccessMessage(data.message);
        } else {
            showErrorMessage(data.message);
        }
    })
    .catch(error => {
        regenerateButton.disabled = false;
        showErrorMessage(error);
    });
}

function exportAsJson() {
    fetch("endpoints/subscriptions/export.php")
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const subscriptions = JSON.stringify(data.subscriptions);
                const element = document.createElement('a');
                element.setAttribute('href', 'data:text/plain;charset=utf-8,' + encodeURIComponent(subscriptions));
                element.setAttribute('download', 'subscriptions.json');
                element.style.display = 'none';
                document.body.appendChild(element);
                element.click();
                document.body.removeChild(element);
            } else {
                showErrorMessage(data.message);
            }
        })
        .catch(error => {
            console.log(error);
            showErrorMessage(translate('unknown_error'));
        });
}

function exportAsCsv() {
    fetch("endpoints/subscriptions/export.php")
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const subscriptions = data.subscriptions;
                const header = Object.keys(subscriptions[0]).join(',');
                const csv = subscriptions.map(subscription => Object.values(subscription).join(',')).join('\n');
                const csvWithHeader = header + '\n' + csv;
                const element = document.createElement('a');
                element.setAttribute('href', 'data:text/plain;charset=utf-8,' + encodeURIComponent(csvWithHeader));
                element.setAttribute('download', 'subscriptions.csv');
                element.style.display = 'none';
                document.body.appendChild(element);
                element.click();
                document.body.removeChild(element);
            } else {
                showErrorMessage(data.message);
            }
        })
        .catch(error => {
            showErrorMessage(translate('unknown_error'));
        });
}

function deleteAccount(userId) {
    if (!confirm(translate('delete_account_confirmation'))) {
        return;
    }

    if (!confirm(translate('this_will_delete_all_data'))) {
        return;
    }

    fetch('endpoints/settings/deleteaccount.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ userId: userId }),
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.href = 'logout.php';
            } else {
                showErrorMessage(data.message);
            }
        })
        .catch((error) => {
            showErrorMessage(translate('unknown_error'));
        });
}

