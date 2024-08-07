function switchTheme() {
  const darkThemeCss = document.querySelector("#dark-theme");
  darkThemeCss.disabled = !darkThemeCss.disabled;

  const themeChoice = darkThemeCss.disabled ? 'light' : 'dark';
  document.cookie = 'theme=' + themeValue + '; expires=Fri, 31 Dec 9999 23:59:59 GMT; SameSite=Strict';

  document.body.className = themeChoice;

  const button = document.getElementById("switchTheme");
  button.disabled = true;

  fetch('endpoints/settings/theme.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({ theme: themeChoice === 'dark' })
  })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        showSuccessMessage(data.message);
      } else {
        showErrorMessage(data.errorMessage);
      }
      button.disabled = false;
    }).catch(error => {
      button.disabled = false;
    });
}

function setDarkTheme(theme) {
  const darkThemeButton = document.querySelector("#theme-dark");
  const lightThemeButton = document.querySelector("#theme-light");
  const automaticThemeButton = document.querySelector("#theme-automatic");
  const darkThemeCss = document.querySelector("#dark-theme");
  const themes = { 0: 'light', 1: 'dark', 2: 'automatic' };
  const themeValue = themes[theme];
  const prefersDarkMode = window.matchMedia('(prefers-color-scheme: dark)').matches;

  darkThemeButton.disabled = true;
  lightThemeButton.disabled = true;
  automaticThemeButton.disabled = true;

  fetch('endpoints/settings/theme.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({ theme: theme })
  })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        darkThemeButton.disabled = false;
        lightThemeButton.disabled = false;
        automaticThemeButton.disabled = false;
        darkThemeButton.classList.remove('selected');
        lightThemeButton.classList.remove('selected');
        automaticThemeButton.classList.remove('selected');

        document.cookie = `theme=${themeValue}; expires=Fri, 31 Dec 9999 23:59:59 GMT; SameSite=Strict`;

        if (theme == 0) {
          darkThemeCss.disabled = true;
          document.body.className = 'light';
          lightThemeButton.classList.add('selected');
        }

        if (theme == 1) {
          darkThemeCss.disabled = false;
          document.body.className = 'dark';
          darkThemeButton.classList.add('selected');
        }

        if (theme == 2) {
          darkThemeCss.disabled = !prefersDarkMode;
          document.body.className = prefersDarkMode ? 'dark' : 'light';
          automaticThemeButton.classList.add('selected');
          document.cookie = `inUseTheme=${prefersDarkMode ? 'dark' : 'light'}; expires=Fri, 31 Dec 9999 23:59:59 GMT; SameSite=Strict`;
        }

        showSuccessMessage(data.message);
      } else {
        showErrorMessage(data.errorMessage);
        darkThemeButton.disabled = false;
        lightThemeButton.disabled = false;
        automaticThemeButton.disabled = false;
      }
    }).catch(error => {
      darkThemeButton.disabled = false;
      lightThemeButton.disabled = false;
      automaticThemeButton.disabled = false;
    });
}

function setTheme(themeColor) {
  var currentTheme = 'blue';
  var themeIds = ['red-theme', 'green-theme', 'yellow-theme', 'purple-theme'];

  themeIds.forEach(function (id) {
    var themeStylesheet = document.getElementById(id);
    if (themeStylesheet && !themeStylesheet.disabled) {
      currentTheme = id.replace('-theme', '');
      themeStylesheet.disabled = true;
    }
  });

  if (themeColor !== "blue") {
    var enableTheme = document.getElementById(themeColor + '-theme');
    enableTheme.disabled = false;
  }

  var images = document.querySelectorAll('img');
  images.forEach(function (img) {
    if (img.src.includes('siteicons/' + currentTheme)) {
      img.src = img.src.replace(currentTheme, themeColor);
    }
  });

  var labels = document.querySelectorAll('.theme-preview');
  labels.forEach(function (label) {
    label.classList.remove('is-selected');
  });

  var targetLabel = document.querySelector(`.theme-preview.${themeColor}`);
  if (targetLabel) {
    targetLabel.classList.add('is-selected');
  }

  document.cookie = `colorTheme=${themeColor}; expires=Fri, 31 Dec 9999 23:59:59 GMT; SameSite=Strict`;

  fetch('endpoints/settings/colortheme.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({ color: themeColor })
  })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        showSuccessMessage(data.message);
      } else {
        showErrorMessage(data.message);
      }
    })
    .catch(error => {
      showErrorMessage(translate('unknown_error'));
    });

}

function resetCustomColors() {
  const button = document.getElementById("reset-colors");
  button.disabled = true;

  fetch('endpoints/settings/resettheme.php', {
    method: 'DELETE',
  })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        showSuccessMessage(data.message);
        const custom_theme_colors = document.getElementById('custom_theme_colors');
        if (custom_theme_colors) {
          custom_theme_colors.remove();
        }
        document.documentElement.style.removeProperty('--main-color');
        document.documentElement.style.removeProperty('--accent-color');
        document.documentElement.style.removeProperty('--hover-color');
        document.getElementById("mainColor").value = "#FFFFFF";
        document.getElementById("accentColor").value = "#FFFFFF";
        document.getElementById("hoverColor").value = "#FFFFFF";
      } else {
        showErrorMessage(data.message);
      }
      button.disabled = false;
    })
    .catch(error => {
      showErrorMessage(translate('unknown_error'));
      button.disabled = false;
    });
}

function saveCustomColors() {
  const button = document.getElementById("save-colors");
  button.disabled = true;

  const mainColor = document.getElementById("mainColor").value;
  const accentColor = document.getElementById("accentColor").value;
  const hoverColor = document.getElementById("hoverColor").value;

  fetch('endpoints/settings/customtheme.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({ mainColor: mainColor, accentColor: accentColor, hoverColor: hoverColor })
  })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        showSuccessMessage(data.message);
        document.documentElement.style.setProperty('--main-color', mainColor);
        document.documentElement.style.setProperty('--accent-color', accentColor);
        document.documentElement.style.setProperty('--hover-color', hoverColor);
      } else {
        showErrorMessage(data.message);
      }
      button.disabled = false;
    })
    .catch(error => {
      showErrorMessage(translate('unknown_error'));
      button.disabled = false;
    });

}

function saveCustomCss() {
  const button = document.getElementById("save-css");
  button.disabled = true;

  const customCss = document.getElementById("customCss").value;

  fetch('endpoints/settings/customcss.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({ customCss: customCss })
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
    .catch(error => {
      showErrorMessage(translate('unknown_error'));
      button.disabled = false;
    });
}