<?php

/**
 * Serves the PWA manifest dynamically so theme_color/background_color match
 * the user's actual theme instead of being hardcoded white - installed PWAs
 * (Android WebAPKs in particular) use the manifest's theme_color for the
 * status bar/header chrome, independent of the page's own <meta
 * name="theme-color"> tag.
 *
 * No login is required to read this (it's linked from the pre-auth pages
 * too), so theme comes from the same "theme"/"inUseTheme" cookies those
 * pages already use, not from a per-user DB setting.
 */

require_once __DIR__ . '/includes/theme_helpers.php';

$theme = sanitize_theme_mode($_COOKIE['theme'] ?? null);
if ($theme === 'automatic') {
    $theme = sanitize_resolved_theme($_COOKIE['inUseTheme'] ?? null);
}
$themeColor = $theme === 'light' ? '#FFFFFF' : '#12151C';

$manifest = [
    'short_name' => 'Wallos',
    'name' => 'Wallos - Subscription Tracker',
    'icons' => [
        [
            'src' => 'images/icon/android-chrome-192x192.png',
            'type' => 'image/png',
            'sizes' => '192x192',
        ],
        [
            'src' => 'images/icon/android-chrome-512x512.png',
            'type' => 'image/png',
            'sizes' => '512x512',
        ],
        [
            'src' => 'images/icon/maskable_icon_x192.png',
            'type' => 'image/png',
            'sizes' => '192x192',
            'purpose' => 'maskable',
        ],
        [
            'src' => 'images/icon/maskable_icon_x512.png',
            'type' => 'image/png',
            'sizes' => '512x512',
            'purpose' => 'maskable',
        ],
    ],
    'start_url' => '/',
    'id' => 'com.wallos.app',
    'shortcuts' => [
        [
            'name' => 'Dashboard',
            'short_name' => 'Dashboard',
            'description' => 'View your dashboard',
            'url' => 'index.php',
            'icons' => [
                ['src' => 'images/siteicons/pwa/dashboard.png', 'type' => 'image/png', 'sizes' => '96x96'],
            ],
        ],
        [
            'name' => 'Subscriptions',
            'short_name' => 'Subscriptions',
            'description' => 'View your subscriptions',
            'url' => 'subscriptions.php',
            'icons' => [
                ['src' => 'images/siteicons/pwa/subscriptions.png', 'type' => 'image/png', 'sizes' => '96x96'],
            ],
        ],
        [
            'name' => 'Calendar',
            'short_name' => 'Calendar',
            'description' => 'View your calendar',
            'url' => 'calendar.php',
            'icons' => [
                ['src' => 'images/siteicons/pwa/calendar.png', 'type' => 'image/png', 'sizes' => '96x96'],
            ],
        ],
        [
            'name' => 'Stats',
            'short_name' => 'Stats',
            'description' => 'View your statistics',
            'url' => 'stats.php',
            'icons' => [
                ['src' => 'images/siteicons/pwa/stats.png', 'type' => 'image/png', 'sizes' => '96x96'],
            ],
        ],
        [
            'name' => 'Settings',
            'short_name' => 'Settings',
            'description' => 'Change your settings',
            'url' => 'settings.php',
            'icons' => [
                ['src' => 'images/siteicons/pwa/settings.png', 'type' => 'image/png', 'sizes' => '96x96'],
            ],
        ],
        [
            'name' => 'About',
            'short_name' => 'About',
            'description' => 'More info about Wallos',
            'url' => 'about.php',
            'icons' => [
                ['src' => 'images/siteicons/pwa/about.png', 'type' => 'image/png', 'sizes' => '96x96'],
            ],
        ],
    ],
    'screenshots' => [
        [
            'src' => 'images/screenshots/desktop.png',
            'sizes' => '1000x750',
            'type' => 'image/png',
            'form_factor' => 'wide',
        ],
        [
            'src' => 'images/screenshots/mobile.png',
            'sizes' => '600x1000',
            'type' => 'image/png',
        ],
    ],
    'background_color' => $themeColor,
    'display' => 'standalone',
    'scope' => '/',
    'theme_color' => $themeColor,
    'description' => 'Wallos is a personal subscription tracker that helps you keep track of your subscriptions and save money.',
    'orientation' => 'portrait-primary',
    'display_override' => ['window-controls-overlay'],
];

if (!headers_sent()) {
    header('Content-Type: application/manifest+json; charset=utf-8');
}
echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

?>
