function openNotificationsSettings(type) {
    // Get all .account-notification-section-settings elements
    var sections = document.querySelectorAll('.account-notification-section-settings');
    var targetSection = document.querySelector(`.account-notification-section-settings[data-type="${type}"]`);
    
    // Remove the is-open class from all elements
    sections.forEach(function(section) {
      if (section !== targetSection) {
        section.classList.remove('is-open');
      }
    });
  
    // Add the is-open class to the element with data-type=type
  
    if (targetSection && !targetSection.classList.contains('is-open')) {
      targetSection.classList.add('is-open');
    } else {
      targetSection.classList.remove('is-open');
    }
}

function makeFetchCall(url, data, button) {
    return fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            "X-CSRF-Token": window.csrfToken,
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

function saveNotifications() {
    const button = document.getElementById("saveNotifications");
    button.disabled = true;
    const days = document.querySelector('#days').value;
    const periodSummaryAtPeriodStart = document.getElementById("period_summary_at_period_start").checked ? 1 : 0;

    const url = 'endpoints/notifications/savenotificationsettings.php';
    const data = {
        days: days,
        period_summary_at_period_start: periodSummaryAtPeriodStart,
    };

    makeFetchCall(url, data, button);
}

function saveNotificationsEmailButton() {
    const button = document.getElementById("saveNotificationsEmail");
    button.disabled = true;
  
    const enabled = document.getElementById("emailenabled").checked ? 1 : 0;
    const smtpAddress = document.getElementById("smtpaddress").value;
    const smtpPort = document.getElementById("smtpport").value;
    const encryption = document.querySelector('input[name="encryption"]:checked').value;
    const smtpUsername = document.getElementById("smtpusername").value;
    const smtpPassword = document.getElementById("smtppassword").value;
    const fromEmail = document.getElementById("fromemail").value;
    const otherEmails = document.getElementById("otheremails").value;
  
    const data = {
      enabled: enabled,
      smtpaddress: smtpAddress,
      smtpport: smtpPort,
      encryption: encryption,
      smtpusername: smtpUsername,
      smtppassword: smtpPassword,
      fromemail: fromEmail,
      otheremails: otherEmails
    };

    makeFetchCall('endpoints/notifications/saveemailnotifications.php', data, button);
}
  
function testNotificationEmailButton()  {
    const button = document.getElementById("testNotificationsEmail");
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

function saveNotificationsWebhookButton() {
    const button = document.getElementById("saveNotificationsWebhook");
    button.disabled = true;
  
    const enabled = document.getElementById("webhookenabled").checked ? 1 : 0;
    const webhook_url = document.getElementById("webhookurl").value;
    const headers = document.getElementById("webhookcustomheaders").value;
    const payload = document.getElementById("webhookpayload").value;
    const cancelation_payload = document.getElementById("webhookcancelationpayload").value;
    const ignore_ssl = document.getElementById("webhookignoressl").checked ? 1 : 0;
  
    const data = {
      enabled: enabled,
      webhook_url: webhook_url,
      headers: headers,
      payload: payload,
      cancelation_payload: cancelation_payload,
      ignore_ssl: ignore_ssl
    };

    makeFetchCall('endpoints/notifications/savewebhooknotifications.php', data, button);
}

function testNotificationsWebhookButton() {
    const button = document.getElementById("testNotificationsWebhook");
    button.disabled = true;
  
    const enabled = document.getElementById("webhookenabled").checked ? 1 : 0;
    const requestmethod = document.getElementById("webhookrequestmethod").value;
    const url = document.getElementById("webhookurl").value;
    const customheaders = document.getElementById("webhookcustomheaders").value;
    const payload = document.getElementById("webhookpayload").value;
    const cancelation_payload = document.getElementById("webhookcancelationpayload").value;
    const ignore_ssl = document.getElementById("webhookignoressl").checked ? 1 : 0;
  
    const data = {
      enabled: enabled,
      requestmethod: requestmethod,
      url: url,
      customheaders: customheaders,
      payload: payload,
      cancelation_payload: cancelation_payload,
      ignore_ssl: ignore_ssl
    };

    makeFetchCall('endpoints/notifications/testwebhooknotifications.php', data, button);
}

function saveNotificationsTelegramButton() {
    const button = document.getElementById("saveNotificationsTelegram");
    button.disabled = true;
  
    const enabled = document.getElementById("telegramenabled").checked ? 1 : 0;
    const chat_id = document.getElementById("telegramchatid").value;
    const bot_token = document.getElementById("telegrambottoken").value;
  
    const data = {
      enabled: enabled,
      chat_id: chat_id,
      bot_token: bot_token
    };

    makeFetchCall('endpoints/notifications/savetelegramnotifications.php', data, button);
}

function testNotificationsTelegramButton() {
    const button = document.getElementById("testNotificationsTelegram");
    button.disabled = true;
  
    const enabled = document.getElementById("telegramenabled").checked ? 1 : 0;
    const bottoken = document.getElementById("telegrambottoken").value;
    const chatid = document.getElementById("telegramchatid").value;
  
    const data = {
      enabled: enabled,
      bottoken: bottoken,
      chatid: chatid
    };

    makeFetchCall('endpoints/notifications/testtelegramnotifications.php', data, button);
}

function testNotificationsPushPlusButton() {
    const button = document.getElementById("testNotificationsPushPlus");
    button.disabled = true;
  
    const enabled = document.getElementById("pushplusenabled").checked ? 1 : 0;
    const token = document.getElementById("pushplustoken").value;
  
    const data = {
      enabled: enabled,
      token: token
    };

    makeFetchCall('endpoints/notifications/testpushplusnotifications.php', data, button);
}

function saveNotificationsPushPlusButton() {
    const button = document.getElementById("saveNotificationsPushPlus");
    button.disabled = true;
  
    const enabled = document.getElementById("pushplusenabled").checked ? 1 : 0;
    const token = document.getElementById("pushplustoken").value;
  
    const data = {
      enabled: enabled,
      token: token
    };

    makeFetchCall('endpoints/notifications/savepushplusnotifications.php', data, button);
}

function testNotificationsMattermostButton() {
    const button = document.getElementById("testNotificationsMattermost");
    button.disabled = true;
  
    const enabled = document.getElementById("mattermostenabled").checked ? 1 : 0;
    const webhook_url = document.getElementById("mattermostwebhookurl").value;
    const bot_username = document.getElementById("mattermostbotusername").value;
    const bot_icon_emoji = document.getElementById("mattermostboticonemoji").value;
  
    const data = {
      enabled: enabled,
      webhook_url: webhook_url,
      bot_username: bot_username,
      bot_icon_emoji: bot_icon_emoji
    };

    makeFetchCall('endpoints/notifications/testmattermostnotifications.php', data, button);
}

function saveNotificationsMattermostButton() {
    const button = document.getElementById("saveNotificationsMattermost");
    button.disabled = true;
  
    const enabled = document.getElementById("mattermostenabled").checked ? 1 : 0;
    const webhook_url = document.getElementById("mattermostwebhookurl").value;
    const bot_username = document.getElementById("mattermostbotusername").value;
    const bot_icon_emoji = document.getElementById("mattermostboticonemoji").value;
  
    const data = {
      enabled: enabled,
      webhook_url: webhook_url,
      bot_username: bot_username,
      bot_icon_emoji: bot_icon_emoji
    };

    makeFetchCall('endpoints/notifications/savemattermostnotifications.php', data, button);
}

function saveNotificationsGotifyButton() {
    const button = document.getElementById("saveNotificationsGotify");
    button.disabled = true;
  
    const enabled = document.getElementById("gotifyenabled").checked ? 1 : 0;
    const gotify_url = document.getElementById("gotifyurl").value;
    const token = document.getElementById("gotifytoken").value;
    const ignore_ssl = document.getElementById("gotifyignoressl").checked ? 1 : 0;
  
    const data = {
      enabled: enabled,
      gotify_url: gotify_url,
      token: token,
      ignore_ssl: ignore_ssl
    };

    makeFetchCall('endpoints/notifications/savegotifynotifications.php', data, button);
}


function testNotificationsGotifyButton() {
    const button = document.getElementById("testNotificationsGotify");
    button.disabled = true;
  
    const enabled = document.getElementById("gotifyenabled").checked ? 1 : 0;
    const gotify_url = document.getElementById("gotifyurl").value;
    const token = document.getElementById("gotifytoken").value;
    const ignore_ssl = document.getElementById("gotifyignoressl").checked ? 1 : 0;
  
    const data = {
      enabled: enabled,
      gotify_url: gotify_url,
      token: token,
      ignore_ssl: ignore_ssl
    };

    makeFetchCall('endpoints/notifications/testgotifynotifications.php', data, button);
}

function saveNotificationsPushoverButton() {
  const button = document.getElementById("saveNotificationsPushover");
  button.disabled = true;

  const enabled = document.getElementById("pushoverenabled").checked ? 1 : 0;
  const user_key = document.getElementById("pushoveruserkey").value;
  const token = document.getElementById("pushovertoken").value;

  const data = {
    enabled: enabled,
    user_key: user_key,
    token: token
  };

  makeFetchCall('endpoints/notifications/savepushovernotifications.php', data, button);
}

function testNotificationsPushoverButton() {
  const button = document.getElementById("testNotificationsPushover");
  button.disabled = true;

  const enabled = document.getElementById("pushoverenabled").checked ? 1 : 0;
  const user_key = document.getElementById("pushoveruserkey").value;
  const token = document.getElementById("pushovertoken").value;

  const data = {
    enabled: enabled,
    user_key: user_key,
    token: token
  };

  makeFetchCall('endpoints/notifications/testpushovernotifications.php', data, button);
}

function saveNotificationsDiscordButton() {
  const button = document.getElementById("saveNotificationsDiscord");
  button.disabled = true;

  const enabled = document.getElementById("discordenabled").checked ? 1 : 0;
  const url = document.getElementById("discordurl").value;
  const bot_username = document.getElementById("discordbotusername").value;
  const bot_avatar = document.getElementById("discordbotavatar").value;

  const data = {
    enabled: enabled,
    url: url,
    bot_username: bot_username,
    bot_avatar: bot_avatar
  };

  makeFetchCall('endpoints/notifications/savediscordnotifications.php', data, button);
}

function testNotificationsDiscordButton() {
  const button = document.getElementById("testNotificationsDiscord");
  button.disabled = true;

  const enabled = document.getElementById("discordenabled").checked ? 1 : 0;
  const url = document.getElementById("discordurl").value;
  const bot_username = document.getElementById("discordbotusername").value;
  const bot_avatar = document.getElementById("discordbotavatar").value;

  const data = {
    enabled: enabled,
    url: url,
    bot_username: bot_username,
    bot_avatar: bot_avatar
  };

  makeFetchCall('endpoints/notifications/testdiscordnotifications.php', data, button);
}

function testNotificationsNtfyButton() {
  const button = document.getElementById("testNotificationsNtfy");
  button.disabled = true;

  const host = document.getElementById("ntfyhost").value;
  const topic = document.getElementById("ntfytopic").value;
  const headers = document.getElementById("ntfyheaders").value;
  const ignore_ssl = document.getElementById("ntfyignoressl").checked ? 1 : 0;
  
  const data = {
    host: host,
    topic: topic,
    headers: headers,
    ignore_ssl: ignore_ssl
  };

  makeFetchCall('endpoints/notifications/testntfynotifications.php', data, button);
}

function saveNotificationsNtfyButton() {
  const button = document.getElementById("saveNotificationsNtfy");
  button.disabled = true;

  const enabled = document.getElementById("ntfyenabled").checked ? 1 : 0;
  const host = document.getElementById("ntfyhost").value;
  const topic = document.getElementById("ntfytopic").value;
  const headers = document.getElementById("ntfyheaders").value;
  const ignore_ssl = document.getElementById("ntfyignoressl").checked ? 1 : 0;

  const data = {
    enabled: enabled,
    host: host,
    topic: topic,
    headers: headers,
    ignore_ssl: ignore_ssl
  };

  makeFetchCall('endpoints/notifications/saventfynotifications.php', data, button);
}

function testNotificationsServerchanButton() {
  const button = document.getElementById("testNotificationsServerchan");
  button.disabled = true;

  const enabled = document.getElementById("serverchanenabled").checked ? 1 : 0;
  const sendkey = document.getElementById("serverchansendkey").value;

  const data = {
    enabled: enabled,
    sendkey: sendkey
  };

  makeFetchCall('endpoints/notifications/testserverchannotifications.php', data, button);
}

function saveNotificationsServerchanButton() {
  const button = document.getElementById("saveNotificationsServerchan");
  button.disabled = true;

  const enabled = document.getElementById("serverchanenabled").checked ? 1 : 0;
  const sendkey = document.getElementById("serverchansendkey").value;

  const data = {
    enabled: enabled,
    sendkey: sendkey
  };

  makeFetchCall('endpoints/notifications/saveserverchannotifications.php', data, button);
}

// Push notifications ---------------------------------------------------
//
// Unlike every other channel above, there is no host/token/key for the user
// to type in: the "configuration" is the browser's own Push subscription,
// created by subscribePushButtonClick() and handed straight to the server -
// the enabled checkbox is the only thing saveNotificationsPushButton() ever
// saves on its own.

// pushManager.subscribe() takes the VAPID public key as a Uint8Array, not
// the base64url string the server hands over; this is the standard
// conversion (unpadded base64url -> padded base64 -> raw bytes).
function urlBase64ToUint8Array(base64String) {
  const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
  const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
  const rawData = window.atob(base64);
  const outputArray = new Uint8Array(rawData.length);

  for (let i = 0; i < rawData.length; ++i) {
    outputArray[i] = rawData.charCodeAt(i);
  }

  return outputArray;
}

// Builds (or replaces) one device's row in the list from what
// savepushsubscription.php handed back, without touching anything else on
// the page - in particular, without a reload, which would reset every
// notification section back to its default collapsed state along with it.
//
// user_agent is a raw client-supplied HTTP header, so it is never spliced
// into innerHTML: the name goes in through .textContent, and the "delete"
// button through addEventListener rather than an onclick="..." string built
// from the same value.
function renderPushDeviceRow(subscription) {
  const list = document.getElementById("pushDevicesList");
  const existingRow = list.querySelector(`.push-device-row[data-subscriptionid="${subscription.id}"]`);

  const row = existingRow || document.createElement("div");
  row.className = "push-device-row";
  row.setAttribute("data-subscriptionid", subscription.id);
  row.innerHTML = "";

  const name = document.createElement("span");
  name.className = "push-device-name";
  name.textContent = subscription.user_agent;
  row.appendChild(name);

  const deleteButton = document.createElement("button");
  deleteButton.type = "button";
  deleteButton.className = "secondary-button thin";
  deleteButton.textContent = translate('delete');
  deleteButton.addEventListener('click', function () {
    removePushSubscriptionButton(subscription.id, row);
  });
  row.appendChild(deleteButton);

  if (!existingRow) {
    const noDevices = document.getElementById("noPushDevices");
    if (noDevices) {
      noDevices.remove();
    }
    list.appendChild(row);
  }
}

function subscribePushButtonClick() {
  const button = document.getElementById("subscribePushButton");

  if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
    showErrorMessage(translate('push_not_supported'));
    return;
  }

  button.disabled = true;

  Notification.requestPermission().then(function (permission) {
    if (permission !== 'granted') {
      showErrorMessage(translate('push_permission_denied'));
      button.disabled = false;
      return;
    }

    navigator.serviceWorker.ready.then(function (registration) {
      return registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(window.vapidPublicKey),
      });
    }).then(function (subscription) {
      return fetch('endpoints/notifications/savepushsubscription.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': window.csrfToken,
        },
        body: JSON.stringify(subscription.toJSON()),
      });
    }).then(function (response) {
      return response.json();
    }).then(function (data) {
      if (data.success) {
        renderPushDeviceRow(data.subscription);
        showSuccessMessage(data.message);
      } else {
        showErrorMessage(data.message);
      }
      button.disabled = false;
    }).catch(function (error) {
      showErrorMessage(error);
      button.disabled = false;
    });
  });
}

function removePushSubscriptionButton(id, row) {
  row = row || document.querySelector(`.push-device-row[data-subscriptionid="${id}"]`);

  fetch('endpoints/notifications/removepushsubscription.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': window.csrfToken,
    },
    body: JSON.stringify({ id: id }),
  })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        if (row) {
          row.remove();
        }

        const list = document.getElementById("pushDevicesList");
        if (list && !list.querySelector(".push-device-row")) {
          const noDevices = document.createElement("p");
          noDevices.id = "noPushDevices";
          noDevices.className = "push-no-devices";
          noDevices.textContent = translate('no_devices_registered');
          list.appendChild(noDevices);
        }
      } else {
        showErrorMessage(data.message);
      }
    })
    .catch(error => showErrorMessage(error));
}

function testNotificationsPushButton() {
  const button = document.getElementById("testNotificationsPush");
  button.disabled = true;

  makeFetchCall('endpoints/notifications/testpushnotifications.php', {}, button);
}

function saveNotificationsPushButton() {
  const button = document.getElementById("saveNotificationsPush");
  button.disabled = true;

  const enabled = document.getElementById("pushenabled").checked ? 1 : 0;

  makeFetchCall('endpoints/notifications/savenotificationspush.php', { enabled: enabled }, button);
}