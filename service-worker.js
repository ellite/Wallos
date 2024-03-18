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
                'styles/barlow.css',
                'webfonts/fa-solid-900.woff2',
                'webfonts/fa-solid-900.ttf',
                'scripts/common.js',
                'scripts/dashboard.js',
                'scripts/stats.js',
                'scripts/settings.js',
                'scripts/registration.js',
                'scripts/i18n/en.js',
                'scripts/i18n/de.js',
                'scripts/i18n/el.js',
                'scripts/i18n/es.js',
                'scripts/i18n/fr.js',
                'scripts/i18n/jp.js',
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
                'images/wallossolid.png',
                'images/wallossolidwhite.png',
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
                'images/siteicons/edit.png',
                'images/siteicons/websearch.png',
                'images/siteicons/save.png',
                'images/siteicons/delete.png',
                'images/siteicons/category.png',
                'images/siteicons/check.png',
                'images/siteicons/editavatar.png',
                'images/siteicons/notes.png',
                'images/siteicons/payment.png',
                'images/siteicons/plusicon.png',
                'images/siteicons/sort.png',
                'images/siteicons/subscription.png',
                'images/siteicons/web.png',
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