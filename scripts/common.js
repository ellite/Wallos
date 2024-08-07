let isDropdownOpen = false;

function toggleDropdown() {
  const dropdown = document.querySelector('.dropdown');
  dropdown.classList.toggle('is-open');
  isDropdownOpen = !isDropdownOpen;
}

function showErrorMessage(message) {
  const toast = document.querySelector(".toast#errorToast");
  const closeIcon = document.querySelector(".close-error");
  const errorMessage = document.querySelector(".errorMessage");
  const progress = document.querySelector(".progress.error");
  let timer1, timer2;
  errorMessage.textContent = message;
  toast.classList.add("active");
  progress.classList.add("active");
  timer1 = setTimeout(() => {
    toast.classList.remove("active");
    closeIcon.removeEventListener("click", () => { });
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
    closeIcon.removeEventListener("click", () => { });
  });
}

function showSuccessMessage(message) {
  const toast = document.querySelector(".toast#successToast");
  const closeIcon = document.querySelector(".close-success");
  const successMessage = document.querySelector(".successMessage");
  const progress = document.querySelector(".progress.success");
  let timer1, timer2;
  successMessage.textContent = message;
  toast.classList.add("active");
  progress.classList.add("active");
  timer1 = setTimeout(() => {
    toast.classList.remove("active");
    closeIcon.removeEventListener("click", () => { });
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
    closeIcon.removeEventListener("click", () => { });
  });
}

document.addEventListener('DOMContentLoaded', function () {

  if (window.update_theme_settings) {
    const prefersDarkMode = window.matchMedia('(prefers-color-scheme: dark)').matches;
    const themePreference = prefersDarkMode ? 'dark' : 'light';
    const darkThemeCss = document.querySelector("#dark-theme");
    darkThemeCss.disabled = themePreference === 'light';
    document.body.className = themePreference;
    document.cookie = `inUseTheme=${themePreference}; expires=Fri, 31 Dec 9999 23:59:59 GMT; SameSite=Strict`;
    const themeColorMetaTag = document.querySelector('meta[name="theme-color"]');
    themeColorMetaTag.setAttribute('content', themePreference === 'dark' ? '#222222' : '#FFFFFF');
  }

  document.addEventListener('mousedown', function (event) {
    var dropdown = document.querySelector('.dropdown');
    var dropdownContent = document.querySelector('.dropdown-content');

    if (!dropdown.contains(event.target) && isDropdownOpen) {
      dropdown.classList.remove('is-open');
      isDropdownOpen = false;
    }
  });

  document.querySelector('.dropdown-content').addEventListener('focus', function () {
    isDropdownOpen = true;
  });
});