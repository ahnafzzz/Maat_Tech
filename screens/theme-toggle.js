(function () {
  var STORAGE_KEY = 'maat-theme';
  var root = document.documentElement;

  function normalizeTheme(value) {
    return value === 'light' ? 'light' : 'dark';
  }

  function getStoredTheme() {
    try {
      return normalizeTheme(localStorage.getItem(STORAGE_KEY));
    } catch (error) {
      return 'dark';
    }
  }

  function getIcon(theme) {
    if (theme === 'light') {
      return '<svg class="theme-toggle-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3a7 7 0 0 0 9.79 9.79z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    }

    return '<svg class="theme-toggle-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="1.6"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>';
  }

  function applyTheme(theme, toggleButton) {
    var resolvedTheme = normalizeTheme(theme);

    root.classList.toggle('theme-light', resolvedTheme === 'light');
    root.classList.toggle('theme-dark', resolvedTheme === 'dark');
    root.setAttribute('data-theme', resolvedTheme);

    if (!toggleButton) {
      return;
    }

    toggleButton.setAttribute('aria-pressed', String(resolvedTheme === 'light'));
    toggleButton.setAttribute('aria-label', 'Switch to ' + (resolvedTheme === 'light' ? 'dark' : 'light') + ' theme');
    toggleButton.innerHTML = getIcon(resolvedTheme) + '<span>' + (resolvedTheme === 'light' ? 'Dark mode' : 'Light mode') + '</span>';
  }

  function saveTheme(theme) {
    try {
      localStorage.setItem(STORAGE_KEY, normalizeTheme(theme));
    } catch (error) {
      return;
    }
  }

  function createToggle() {
    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'theme-toggle-btn';

    var initialTheme = root.classList.contains('theme-light') ? 'light' : getStoredTheme();
    applyTheme(initialTheme, button);

    button.addEventListener('click', function () {
      var nextTheme = root.classList.contains('theme-light') ? 'dark' : 'light';
      applyTheme(nextTheme, button);
      saveTheme(nextTheme);
    });

    document.body.appendChild(button);
  }

  var bootTheme = getStoredTheme();
  root.classList.add(bootTheme === 'light' ? 'theme-light' : 'theme-dark');

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', createToggle);
  } else {
    createToggle();
  }
})();
