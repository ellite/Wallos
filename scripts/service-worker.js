self.addEventListener('install', function(event) {
    event.waitUntil(
        caches.open('my-cache').then(function(cache) {
            return cache.addAll([
                '/',
                '/index.php',
                '/stats.php',
                '/settings.php',
                '/about.php',
                '/styles/styles.css',
                '/styles/dark-theme.css',
                '/styles/font.css',
                '/styles/font-awesome.min.css',
                '/styles/login.css',
                '/scripts/common.js',
                '/scripts/dashboard.js',
                '/scripts/stats.js',
                '/scripts/settings.js',
                '/scripts/registration.js',
                '/scripts/i18n/en.js',
                '/scripts/i18n/de.js',
                '/scripts/i18n/el.js',
                '/scripts/i18n/es.js',
                '/scripts/i18n/fr.js',
                '/scripts/i18n/jp.js',
                '/scripts/i18n/pt.js',
                '/scripts/i18n/tr.js',
                '/scripts/i18n/zh_cn.js',
                '/scripts/i18n/zh_tw.js',
                '/scripts/i18n/getlang.js',
            ]);
        })
    );
});

self.addEventListener('fetch', function(event) {
    event.respondWith(
        caches.match(event.request).then(function(response) {
            return response || fetch(event.request);
        })
    );
});