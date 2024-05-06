self.addEventListener('install', function(event) {
    event.waitUntil(
        caches.open('my-cache').then(function(cache) {
            const urlsToCache = [
                '.',
                'index.php',
                'settings.php',
                'stats.php',
                'about.php',
                'endpoints/subscriptions/get.php',
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
                'webfonts/fa-solid-900.woff2',
                'webfonts/fa-solid-900.ttf',
                'webfonts/fa-brands-400.woff2',
                'webfonts/fa-brands-400.ttf',
                'scripts/common.js',
                'scripts/dashboard.js',
                'scripts/stats.js',
                'scripts/settings.js',
                'scripts/notifications.js',
                'scripts/registration.js',
                'scripts/i18n/en.js',
                'scripts/i18n/de.js',
                'scripts/i18n/el.js',
                'scripts/i18n/es.js',
                'scripts/i18n/fr.js',
                'scripts/i18n/it.js',
                'scripts/i18n/jp.js',
                'scripts/i18n/pl.js',
                'scripts/i18n/pt.js',
                'scripts/i18n/pt_br.js',
                'scripts/i18n/tr.js',
                'scripts/i18n/zh_cn.js',
                'scripts/i18n/zh_tw.js',
                'scripts/i18n/getlang.js',
                'scripts/libs/chart.js',
                'scripts/libs/sortable.min.js',
                'images/icon/favicon.ico',
                'images/icon/android-chrome-192x192.png',
                'images/screenshots/desktop.png',
                'images/siteicons/blue/wallos.png',
                'images/siteicons/blue/walloswhite.png',
                'images/siteicons/green/wallos.png',
                'images/siteicons/green/walloswhite.png',
                'images/siteicons/red/wallos.png',
                'images/siteicons/red/walloswhite.png',
                'images/siteicons/yellow/wallos.png',
                'images/siteicons/yellow/walloswhite.png',
                'images/siteimages/empty.png',
                'images/avatars/1.svg',
                'images/avatars/2.svg',
                'images/avatars/3.svg',
                'images/avatars/4.svg',
                'images/avatars/5.svg',
                'images/avatars/6.svg',
                'images/avatars/7.svg',
                'images/avatars/8.svg',
                'images/avatars/9.svg',
                'images/siteicons/blue/category.png',
                'images/siteicons/blue/check.png',
                'images/siteicons/blue/delete.png',
                'images/siteicons/blue/edit.png',
                'images/siteicons/blue/notes.png',
                'images/siteicons/blue/payment.png',
                'images/siteicons/blue/save.png',
                'images/siteicons/blue/subscription.png',
                'images/siteicons/blue/web.png',
                'images/siteicons/blue/websearch.png',
                'images/siteicons/red/category.png',
                'images/siteicons/red/check.png',
                'images/siteicons/red/delete.png',
                'images/siteicons/red/edit.png',
                'images/siteicons/red/notes.png',
                'images/siteicons/red/payment.png',
                'images/siteicons/red/save.png',
                'images/siteicons/red/subscription.png',
                'images/siteicons/red/web.png',
                'images/siteicons/red/websearch.png',
                'images/siteicons/green/category.png',
                'images/siteicons/green/check.png',
                'images/siteicons/green/delete.png',
                'images/siteicons/green/edit.png',
                'images/siteicons/green/notes.png',
                'images/siteicons/green/payment.png',
                'images/siteicons/green/save.png',
                'images/siteicons/green/subscription.png',
                'images/siteicons/green/web.png',
                'images/siteicons/green/websearch.png',
                'images/siteicons/yellow/category.png',
                'images/siteicons/yellow/check.png',
                'images/siteicons/yellow/delete.png',
                'images/siteicons/yellow/edit.png',
                'images/siteicons/yellow/notes.png',
                'images/siteicons/yellow/payment.png',
                'images/siteicons/yellow/save.png',
                'images/siteicons/yellow/subscription.png',
                'images/siteicons/yellow/web.png',
                'images/siteicons/yellow/websearch.png',
                'images/siteicons/pwa/stats.png',
                'images/siteicons/pwa/settings.png',
                'images/siteicons/pwa/about.png',
                'images/siteicons/pwa/subscriptions.png',
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
                'images/uploads/logos/*',
            ];

            urlsToCache.forEach(function(url) {
                fetch(url).then(function(response) {
                    if (response.ok) {
                        cache.put(url, response);
                    }
                });
            });
        })
    );
});

self.addEventListener('fetch', function(event) {
    event.respondWith(
        fetch(event.request.clone()).then(function(response) {
            // Check if the response is a redirect
            if (response.redirected) {
                // If the response is a redirect, follow it by making a new fetch request
                return fetch(response.url);
            } else {
                // If the response is not a redirect, return it as-is
                return response;
            }
        }).catch(function(error) {
            // If fetching fails, try to retrieve the response from cache
            return caches.match(event.request, { ignoreSearch: true });
        })
    );
});