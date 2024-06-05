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

    const url = 'endpoints/notifications/savenotificationsettings.php';
    const data = { days: days };

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
  
    const data = {
      enabled: enabled,
      smtpaddress: smtpAddress,
      smtpport: smtpPort,
      encryption: encryption,
      smtpusername: smtpUsername,
      smtppassword: smtpPassword,
      fromemail: fromEmail
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
  
    const data = {
      enabled: enabled,
      webhook_url: webhook_url,
      headers: headers,
      payload: payload
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
  
    const data = {
      enabled: enabled,
      requestmethod: requestmethod,
      url: url,
      customheaders: customheaders,
      payload: payload
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

function saveNotificationsGotifyButton() {
    const button = document.getElementById("saveNotificationsGotify");
    button.disabled = true;
  
    const enabled = document.getElementById("gotifyenabled").checked ? 1 : 0;
    const gotify_url = document.getElementById("gotifyurl").value;
    const token = document.getElementById("gotifytoken").value;
  
    const data = {
      enabled: enabled,
      gotify_url: gotify_url,
      token: token
    };

    makeFetchCall('endpoints/notifications/savegotifynotifications.php', data, button);
}

function testNotificationsGotifyButton() {
    const button = document.getElementById("testNotificationsGotify");
    button.disabled = true;
  
    const enabled = document.getElementById("gotifyenabled").checked ? 1 : 0;
    const gotify_url = document.getElementById("gotifyurl").value;
    const token = document.getElementById("gotifytoken").value;
  
    const data = {
      enabled: enabled,
      gotify_url: gotify_url,
      token: token
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
  

  const data = {
    host: host,
    topic: topic,
    headers: headers
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

  const data = {
    enabled: enabled,
    host: host,
    topic: topic,
    headers: headers
  };

  makeFetchCall('endpoints/notifications/saventfynotifications.php', data, button);
}