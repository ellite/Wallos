function makeFetchCall(url, data, button) {
  return fetch(url, {
      method: 'POST',
      headers: {
          'Content-Type': 'application/json',
      },
      body: JSON.stringify(data),
  })
  .then(response => response.json())
  .then(data => {
      if (data.success) {
          showSuccessMessage(data.message);
      } else {
          showErrorMessage(data.message);
      }
      button.disabled = false;
  })
  .catch((error) => {
      showErrorMessage(error);
      button.disabled = false;
  });

}

function testSmtpSettingsButton()  {
  const button = document.getElementById("testSmtpSettingsButton");
  button.disabled = true;

  const smtpAddress = document.getElementById("smtpaddress").value;
  const smtpPort = document.getElementById("smtpport").value;
  const encryption = document.querySelector('input[name="encryption"]:checked').value;
  const smtpUsername = document.getElementById("smtpusername").value;
  const smtpPassword = document.getElementById("smtppassword").value;
  const fromEmail = document.getElementById("fromemail").value;

  const data = {
    smtpaddress: smtpAddress,
    smtpport: smtpPort,
    encryption: encryption,
    smtpusername: smtpUsername,
    smtppassword: smtpPassword,
    fromemail: fromEmail
  };

  makeFetchCall('endpoints/notifications/testemailnotifications.php', data, button);
}

function saveSmtpSettingsButton() {
  const button = document.getElementById("saveSmtpSettingsButton");
  button.disabled = true;

  const smtpAddress = document.getElementById("smtpaddress").value;
  const smtpPort = document.getElementById("smtpport").value;
  const encryption = document.querySelector('input[name="encryption"]:checked').value;
  const smtpUsername = document.getElementById("smtpusername").value;
  const smtpPassword = document.getElementById("smtppassword").value;
  const fromEmail = document.getElementById("fromemail").value;

  const data = {
    smtpaddress: smtpAddress,
    smtpport: smtpPort,
    encryption: encryption,
    smtpusername: smtpUsername,
    smtppassword: smtpPassword,
    fromemail: fromEmail
  };

  fetch('endpoints/admin/savesmtpsettings.php', {
      method: 'POST',
      headers: {
          'Content-Type': 'application/json',
      },
      body: JSON.stringify(data),
  })
  .then(response => response.json())
  .then(data => {
      if (data.success) {
          const emailVerificationCheckbox = document.getElementById('requireEmail');
          emailVerificationCheckbox.disabled = false;
          showSuccessMessage(data.message);
      } else {
          showErrorMessage(data.message);
      }
      button.disabled = false;
  })
  .catch((error) => {
      showErrorMessage(error);
      button.disabled = false;
  });

}

function backupDB() {
  const button = document.getElementById("backupDB");
  button.disabled = true;

  fetch('endpoints/db/backup.php')
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        const link = document.createElement('a');
        const filename = data.file;
        link.href = '.tmp/' + filename;
        const date = new Date();
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        const timestamp = `${year}${month}${day}-${hours}${minutes}`;
        link.download = `Wallos-Backup-${timestamp}.zip`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        button.disabled = false;
      } else {
        showErrorMessage(data.errorMessage);
        button.disabled = false;
      }
    })
    .catch(error => {
      showErrorMessage(error);
      button.disabled = false;
    });
}
  
function openRestoreDBFileSelect() {
  document.getElementById('restoreDBFile').click();
};

function restoreDB() {
  const input = document.getElementById('restoreDBFile');
  const file = input.files[0];

  if (!file) {
    console.error('No file selected');
    return;
  }

  const formData = new FormData();
  formData.append('file', file);

  fetch('endpoints/db/restore.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      showSuccessMessage(data.message)
      window.location.href = 'logout.php';
    } else {
      showErrorMessage(data.message);
    }
  })
  .catch(error => showErrorMessage('Error:', error));
}

function saveAccountRegistrationsButton () {
  const button = document.getElementById('saveAccountRegistrations');
  button.disabled = true;

  const open_registrations = document.getElementById('registrations').checked ? 1 : 0;
  const max_users = document.getElementById('maxUsers').value;
  const require_email_validation = document.getElementById('requireEmail').checked ? 1 : 0;
  const server_url = document.getElementById('serverUrl').value;
  const disable_login = document.getElementById('disableLogin').checked ? 1 : 0;

  const data = {
    open_registrations: open_registrations,
    max_users: max_users,
    require_email_validation: require_email_validation,
    server_url: server_url,
    disable_login: disable_login
  };

  fetch('endpoints/admin/saveopenregistrations.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify(data)
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      showSuccessMessage(data.message);
      button.disabled = false;
    } else {
      showErrorMessage(data.message);
      button.disabled = false;
    }
  })
  .catch(error => {
    showErrorMessage(error);
    button.disabled = false;
  });
}

function removeUser(userId) {
  const data = {
    userId: userId
  };

  fetch('endpoints/admin/deleteuser.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify(data)
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      showSuccessMessage(data.message);
      const userContainer = document.querySelector(`.form-group-inline[data-userid="${userId}"]`);
      if (userContainer) {
        userContainer.remove();
      }
    } else {
      showErrorMessage(data.message);
    }
  })
  .catch(error => showErrorMessage('Error:', error));

}

function addUserButton() {
  const button = document.getElementById('addUserButton');
  button.disabled = true;

  const username = document.getElementById('newUsername').value;
  const email = document.getElementById('newEmail').value;
  const password = document.getElementById('newPassword').value;

  const data = {
    username: username,
    email: email,
    password: password
  };

  fetch('endpoints/admin/adduser.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify(data)
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      showSuccessMessage(data.message);
      button.disabled = false;
      window.location.reload();
    } else {
      showErrorMessage(data.message);
      button.disabled = false;
    }
  })
  .catch(error => {
    showErrorMessage(error);
    button.disabled = false;
  });
}

function deleteUnusedLogos() {
  const button = document.getElementById('deleteUnusedLogos');
  button.disabled = true;

  fetch('endpoints/admin/deleteunusedlogos.php')
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      showSuccessMessage(data.message);
      const numberOfLogos = document.querySelector('.number-of-logos');
      numberOfLogos.innerText = '0';
    } else {
      showErrorMessage(data.message);
      button.disabled = false;
    }
  })
  .catch(error => {
    showErrorMessage(error);
    button.disabled = false;
  });
}

function toggleUpdateNotification() {
  const notificationEnabledCheckbox = document.getElementById('updateNotification');
  const notificationEnabled = notificationEnabledCheckbox.checked ? 1 : 0;

  const data = {
    notificationEnabled: notificationEnabled
  };

  fetch('endpoints/admin/updatenotification.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify(data)
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      showSuccessMessage(data.message);
      if (notificationEnabled === 1) {
        fetch('endpoints/cronjobs/checkforupdates.php');
      }
    } else {
      showErrorMessage(data.message);
    }
  })
  .catch(error => showErrorMessage('Error:', error));

}

function executeCronJob(job) {
  const url = `endpoints/cronjobs/${job}.php`;
  const resultTextArea = document.getElementById('cronjobResult');

  fetch(url)
    .then(response => {
      return response.text();
    })
    .then(data => {
      const formattedData = data.replace(/<br\s*\/?>/gi, '\n');
      resultTextArea.value = formattedData;
    })
    .catch(error => {
      console.error('Fetch error:', error);
      showErrorMessage('Error:', error);
    });
}