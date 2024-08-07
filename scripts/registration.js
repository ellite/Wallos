function setCookie(name, value, days) {
    var expires = "";
    if (days) {
        var date = new Date();
        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
        expires = "; expires=" + date.toUTCString();
    }
    document.cookie = name + "=" + value + expires + "; SameSite=Strict";
}

function storeFormFieldValue(fieldId) {
    var fieldElement = document.getElementById(fieldId);
    if (fieldElement) {
        localStorage.setItem(fieldId, fieldElement.value);
    }
}

function storeFormFields() {
    storeFormFieldValue('username');
    storeFormFieldValue('email');
    storeFormFieldValue('password');
    storeFormFieldValue('confirm_password');
    storeFormFieldValue('currency');
}

function restoreFormFieldValue(fieldId) {
    var fieldElement = document.getElementById(fieldId);
    if (localStorage.getItem(fieldId)) {
        fieldElement.value = localStorage.getItem(fieldId) || '';
    }
}

function restoreFormFields() {
    restoreFormFieldValue('username');
    restoreFormFieldValue('email');
    restoreFormFieldValue('password');
    restoreFormFieldValue('confirm_password');
    restoreFormFieldValue('currency');
}

function removeFromStorage() {
    localStorage.removeItem('username');
    localStorage.removeItem('email');
    localStorage.removeItem('password');
    localStorage.removeItem('confirm_password');
    localStorage.removeItem('currency');
}

function changeLanguage(selectedLanguage) {
    storeFormFields();
    setCookie("language", selectedLanguage, 365);
    location.reload();
}

function runDatabaseMigration() {
    let url = "endpoints/db/migrate.php";
    fetch(url)
    .then(response => {
        if (!response.ok) {
            throw new Error(translate('network_response_error'));
        }
    });
}

function showErrorMessage(message) {
    const toast = document.querySelector(".toast#errorToast");
    (closeIcon = document.querySelector(".close-error")),
    (errorMessage = document.querySelector(".errorMessage")),
    (progress = document.querySelector(".progress.error"));
    let timer1, timer2;
    errorMessage.textContent = message;
    toast.classList.add("active");
    progress.classList.add("active");
    timer1 = setTimeout(() => {
      toast.classList.remove("active");
      closeIcon.removeEventListener("click", () => {});
    }, 5000);
  
    timer2 = setTimeout(() => {
      progress.classList.remove("active");
    }, 5300);
  
    closeIcon.addEventListener("click", () => {
      toast.classList.remove("active");
    
      setTimeout(() => {
        progress.classList.remove("active");
      }, 300);
    
      clearTimeout(timer1);
      clearTimeout(timer2);
      closeIcon.removeEventListener("click", () => {});
    });
}

function showSuccessMessage(message) {
    const toast = document.querySelector(".toast#successToast");
    (closeIcon = document.querySelector(".close-success")),
    (successMessage = document.querySelector(".successMessage")),
    (progress = document.querySelector(".progress.success"));
    let timer1, timer2;
    successMessage.textContent = message;
    toast.classList.add("active");
    progress.classList.add("active");
    timer1 = setTimeout(() => {
      toast.classList.remove("active");
      closeIcon.removeEventListener("click", () => {});
    }, 5000);
  
    timer2 = setTimeout(() => {
      progress.classList.remove("active");
    }, 5300);
  
    closeIcon.addEventListener("click", () => {
      toast.classList.remove("active");
    
      setTimeout(() => {
        progress.classList.remove("active");
      }, 300);
    
      clearTimeout(timer1);
      clearTimeout(timer2);
      closeIcon.removeEventListener("click", () => {});
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
  
    fetch('endpoints/db/import.php', {
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

function checkThemeNeedsUpdate() {
  if (window.update_theme_settings) {
    const prefersDarkMode = window.matchMedia('(prefers-color-scheme: dark)').matches;
    const themePreference = prefersDarkMode ? 'dark' : 'light';
    const darkThemeCss = document.querySelector("#dark-theme");
    darkThemeCss.disabled = themePreference === 'light';
    document.body.className = themePreference;
    const themeColorMetaTag = document.querySelector('meta[name="theme-color"]');
    themeColorMetaTag.setAttribute('content', themePreference === 'dark' ? '#222222' : '#FFFFFF');
  }
}

window.onload = function () {
    restoreFormFields();
    removeFromStorage();
    runDatabaseMigration();
    checkThemeNeedsUpdate();
};
