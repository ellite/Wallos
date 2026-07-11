function applyAuthThemeIcon() {
  const darkCss = document.getElementById('dark-theme');
  const icon = document.querySelector('#theme-toggle i');
  if (!darkCss || !icon) {
    return;
  }
  icon.className = 'fa-solid ' + (darkCss.disabled ? 'fa-moon' : 'fa-sun');
}

function toggleAuthTheme() {
  const darkCss = document.getElementById('dark-theme');
  if (!darkCss) {
    return;
  }
  const next = darkCss.disabled ? 'dark' : 'light';
  darkCss.disabled = next === 'light';

  const expirationDate = new Date();
  expirationDate.setFullYear(expirationDate.getFullYear() + 1);
  document.cookie = 'theme=' + next + '; expires=' + expirationDate.toUTCString() + '; SameSite=Lax';

  const themeColorMetaTag = document.querySelector('meta[name="theme-color"]');
  if (themeColorMetaTag) {
    themeColorMetaTag.setAttribute('content', next === 'dark' ? '#12151C' : '#FFFFFF');
  }

  applyAuthThemeIcon();
}

document.addEventListener('DOMContentLoaded', function () {
  const toggle = document.getElementById('theme-toggle');
  if (toggle) {
    toggle.addEventListener('click', toggleAuthTheme);
  }
  // Sync the icon after login.js may have applied the OS preference
  applyAuthThemeIcon();
});
