const STATIC_CACHE = 'static-cache-v1';
const PAGES_CACHE = 'pages-cache-v1';
const LOGOS_CACHE = 'logos-cache-v1';

const staticAssets = [
    'manifest.json',
    'styles/styles.css',
    'styles/dark-theme.css',
    'styles/login.css',
    'styles/font-awesome.min.css',
    'styles/brands.css',
    'styles/barlow.css',
    'styles/themes/red.css',
    'styles/themes/green.css',
    'styles/themes/yellow.css',
    'styles/themes/purple.css',
    'webfonts/fa-solid-900.woff2',
    'webfonts/fa-solid-900.ttf',
    'webfonts/fa-brands-400.woff2',
    'webfonts/fa-brands-400.ttf',
    'webfonts/fa-regular-400.woff2',
    'webfonts/fa-regular-400.ttf',
    'scripts/common.js',
    'scripts/dashboard.js',
    'scripts/subscriptions.js',
    'scripts/stats.js',
    'scripts/settings.js',
    'scripts/theme.js',
    'scripts/notifications.js',
    'scripts/registration.js',
    'scripts/login.js',
    'scripts/admin.js',
    'scripts/calendar.js',
    'scripts/i18n/cs.js',
    'scripts/i18n/da.js',
    'scripts/i18n/de.js',
    'scripts/i18n/el.js',
    'scripts/i18n/en.js',
    'scripts/i18n/es.js',
    'scripts/i18n/fr.js',
    'scripts/i18n/id.js',
    'scripts/i18n/it.js',
    'scripts/i18n/jp.js',
    'scripts/i18n/ko.js',
    'scripts/i18n/nl.js',
    'scripts/i18n/pl.js',
    'scripts/i18n/pt.js',
    'scripts/i18n/pt_br.js',
    'scripts/i18n/ro.js',
    'scripts/i18n/ru.js',
    'scripts/i18n/sl.js',
    'scripts/i18n/sr_lat.js',
    'scripts/i18n/sr.js',
    'scripts/i18n/tr.js',
    'scripts/i18n/uk.js',
    'scripts/i18n/vi.js',
    'scripts/i18n/zh_cn.js',
    'scripts/i18n/zh_tw.js',
    'scripts/i18n/getlang.js',
    'scripts/libs/chart.js',
    'scripts/libs/sortable.min.js',
    'scripts/libs/qrcode.min.js',
    'images/icon/favicon.ico',
    'images/icon/android-chrome-192x192.png',
    'images/icon/apple-touch-icon-180',
    'images/icon/apple-touch-icon-152',
    'images/icon/apple-touch-icon',
    'images/screenshots/desktop.png',
    'images/siteicons/wallos.png',
    'images/siteicons/walloswhite.png',
    'images/siteimages/empty.png',
    'images/siteimages/mobilenav.png',
    'images/siteimages/mobilenavdark.png',
    'images/avatars/1.svg',
    'images/avatars/2.svg',
    'images/avatars/3.svg',
    'images/avatars/4.svg',
    'images/avatars/5.svg',
    'images/avatars/6.svg',
    'images/avatars/7.svg',
    'images/avatars/8.svg',
    'images/avatars/9.svg',
    'images/siteicons/svg/logo.php',
    'images/siteicons/svg/category.php',
    'images/siteicons/svg/check.php',
    'images/siteicons/svg/delete.php',
    'images/siteicons/svg/edit.php',
    'images/siteicons/svg/notes.php',
    'images/siteicons/scg/payment.php',
    'images/siteicons/svg/save.php',
    'images/siteicons/svg/subscription.php',
    'images/siteicons/svg/web.php',
    'images/siteicons/svg/websearch.php',
    'images/siteicons/svg/clone.php',
    'images/siteicons/svg/mobile-menu/calendar.php',
    'images/siteicons/svg/mobile-menu/home.php',
    'images/siteicons/svg/mobile-menu/profile.php',
    'images/siteicons/svg/mobile-menu/settings.php',
    'images/siteicons/svg/mobile-menu/statistics.php',
    'images/siteicons/svg/mobile-menu/subscriptions.php',
    'images/siteicons/pwa/stats.png',
    'images/siteicons/pwa/settings.png',
    'images/siteicons/pwa/about.png',
    'images/siteicons/pwa/calendar.png',
    'images/siteicons/pwa/subscriptions.png',
    'images/siteicons/pwa/dashboard.png',
    'images/uploads/icons/paypal.png',
    'images/uploads/icons/creditcard.png',
    'images/uploads/icons/banktransfer.png',
    'images/uploads/icons/directdebit.png',
    'images/uploads/icons/money.png',
    'images/uploads/icons/googlepay.png',
    'images/uploads/icons/samsungpay.png',
    'images/uploads/icons/applepay.png',
    'images/uploads/icons/crypto.png',
    'images/uploads/icons/klarna.png',
    'images/uploads/icons/amazonpay.png',
    'images/uploads/icons/sepa.png',
    'images/uploads/icons/skrill.png',
    'images/uploads/icons/sofort.png',
    'images/uploads/icons/stripe.png',
    'images/uploads/icons/affirm.png',
    'images/uploads/icons/alipay.png',
    'images/uploads/icons/elo.png',
    'images/uploads/icons/facebookpay.png',
    'images/uploads/icons/giropay.png',
    'images/uploads/icons/ideal.png',
    'images/uploads/icons/unionpay.png',
    'images/uploads/icons/interac.png',
    'images/uploads/icons/wechat.png',
    'images/uploads/icons/paysafe.png',
    'images/uploads/icons/poli.png',
    'images/uploads/icons/qiwi.png',
    'images/uploads/icons/shoppay.png',
    'images/uploads/icons/venmo.png',
    'images/uploads/icons/verifone.png',
    'images/uploads/icons/webmoney.png',
];

const pagesToPrefetch = [
    'index.php',
    'subscriptions.php',
    'profile.php',
    'calendar.php',
    'settings.php',
    'stats.php',
    'about.php',
    'login.php',
    'admin.php',
];

// Install: cache static assets only
self.addEventListener('install', function (event) {
    event.waitUntil(
        caches.open(STATIC_CACHE).then(function (cache) {
            return Promise.allSettled(
                staticAssets.map(url =>
                    fetch(url).then(response => {
                        if (response.ok) cache.put(url, response);
                    }).catch(() => {}) // silently skip missing files
                )
            );
        })
    );
    self.skipWaiting();
});

// Activate: clean up old caches
self.addEventListener('activate', function (event) {
    const validCaches = [STATIC_CACHE, PAGES_CACHE, LOGOS_CACHE];
    event.waitUntil(
        caches.keys().then(keys =>
            Promise.all(
                keys.filter(key => !validCaches.includes(key))
                    .map(key => caches.delete(key))
            )
        )
    );
    self.clients.claim();
});

// Message: prefetch pages after login
self.addEventListener('message', function (event) {
    if (event.data && event.data.type === 'PREFETCH_PAGES') {
        caches.open(PAGES_CACHE).then(cache => {
            pagesToPrefetch.forEach(url => {
                fetch(url).then(response => {
                    // Only cache if user is actually logged in (no redirect)
                    if (response.ok && !response.redirected) {
                        cache.put(url, response);
                    }
                }).catch(() => {});
            });
        });
    }
});

// Fetch: single handler for all requests
self.addEventListener('fetch', function (event) {
    const request = event.request;
    const url = new URL(request.url);

    // Never intercept non-GET requests (POST, etc.)
    if (request.method !== 'GET') return;

    // Logo images: cache-first, populate on first load
    if (url.pathname.includes('images/uploads/logos')) {
        event.respondWith(
            caches.match(request).then(response => {
                return response || fetch(request).then(networkResponse => {
                    return caches.open(LOGOS_CACHE).then(cache => {
                        cache.put(request, networkResponse.clone());
                        return networkResponse;
                    });
                });
            })
        );
        return;
    }

    // Static assets: cache-first (they only change on deploy)
    if (staticAssets.some(asset => url.pathname.endsWith(asset))) {
        event.respondWith(
            caches.match(request).then(response => response || fetch(request))
        );
        return;
    }

    // PHP pages and everything else: network-first, cache as fallback
    // Also update the pages cache on every successful load
    event.respondWith(
        fetch(request).then(response => {
            if (response.ok && !response.redirected) {
                const responseClone = response.clone(); // clone before any async operation
                caches.open(PAGES_CACHE).then(cache => {
                    cache.put(request, responseClone);
                });
            }
            return response;
        }).catch(() => {
            return caches.match(request, { ignoreSearch: true });
        })
    );
});
